package com.my.absmsmulti;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.UUID;
import java.util.concurrent.atomic.AtomicBoolean;

public class SmsService extends Service {
    private static final String PREFS = "gateway_config";
    private static final String DEFAULT_SERVER = "";
    private static final String CHANNEL_ID = "sms_gateway";
    private static final int LOOP_DELAY_MS = 5000;
    private static final int HEARTBEAT_DELAY_MS = 15000;
    private static final int NOTIFICATION_ID = 1;

    public static final String ACTION_PAUSE = "com.my.absmsmulti.PAUSE_SERVICE";
    public static final String ACTION_RESUME = "com.my.absmsmulti.RESUME_SERVICE";

    private final AtomicBoolean requestInFlight = new AtomicBoolean(false);
    private Handler handler;
    private SmsUtil smsUtil;
    private NotificationManager notificationManager;
    private boolean paused;
    private boolean foregroundStarted;
    private long lastHeartbeat;
    private String deviceId;

    public static boolean isEnabled(Context context) {
        return context.getSharedPreferences(PREFS, MODE_PRIVATE).getBoolean("service_enabled", false);
    }

    public static void setEnabled(Context context, boolean enabled) {
        context.getSharedPreferences(PREFS, MODE_PRIVATE).edit().putBoolean("service_enabled", enabled).apply();
    }

    public static String getSavedServer(Context context) {
        String server = context.getSharedPreferences(PREFS, MODE_PRIVATE).getString("server_url", DEFAULT_SERVER);
        server = server == null ? "" : server.trim();
        return server.isEmpty() || server.endsWith("/") ? server : server + "/";
    }

    public static String getSavedToken(Context context) {
        String token = context.getSharedPreferences(PREFS, MODE_PRIVATE).getString("device_token", "");
        return token == null ? "" : token;
    }

    public static void saveServer(Context context, String server, String token) {
        String normalized = server.trim();
        if (!normalized.endsWith("/")) normalized += "/";
        context.getSharedPreferences(PREFS, MODE_PRIVATE).edit()
                .putString("server_url", normalized)
                .putString("device_token", token.trim().toLowerCase())
                .apply();
    }

    @Override
    public void onCreate() {
        super.onCreate();
        handler = new Handler(Looper.getMainLooper());
        smsUtil = new SmsUtil();
        notificationManager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        deviceId = getOrCreateDeviceId(this);
        updateNotification("Waiting for the SMS server");
        scheduleLoop();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (!isEnabled(this) || getSavedServer(this).isEmpty() || getSavedToken(this).isEmpty()) {
            stopSelf();
            return START_NOT_STICKY;
        }
        if (intent != null && ACTION_PAUSE.equals(intent.getAction())) {
            paused = true;
            updateNotification("Paused");
        } else if (intent != null && ACTION_RESUME.equals(intent.getAction())) {
            paused = false;
            updateNotification("Resumed");
            scheduleLoop();
        }
        return START_NOT_STICKY;
    }

    @Override
    public void onDestroy() {
        if (handler != null) handler.removeCallbacksAndMessages(null);
        if (notificationManager != null) notificationManager.cancel(NOTIFICATION_ID);
        if (Build.VERSION.SDK_INT >= 24) stopForeground(STOP_FOREGROUND_REMOVE);
        else stopForeground(true);
        super.onDestroy();
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    private void scheduleLoop() {
        handler.postDelayed(new Runnable() {
            @Override
            public void run() {
                if (!isEnabled(SmsService.this)) {
                    stopSelf();
                    return;
                }
                if (paused) {
                    updateNotification("Paused");
                } else if (!isNetworkAvailable()) {
                    updateNotification("No internet connection");
                } else {
                    long now = System.currentTimeMillis();
                    if (now - lastHeartbeat >= HEARTBEAT_DELAY_MS) {
                        sendHeartbeat();
                        lastHeartbeat = now;
                    }
                    fetchAndSendOneMessage();
                }
                if (!paused && isEnabled(SmsService.this)) handler.postDelayed(this, LOOP_DELAY_MS);
            }
        }, 2000);
    }

    private HttpURLConnection openConnection(String path) throws Exception {
        String base = getSavedServer(this);
        if (!base.startsWith("https://")) throw new IllegalStateException("An HTTPS server URL is required.");
        HttpURLConnection connection = (HttpURLConnection) new URL(base + path).openConnection();
        connection.setConnectTimeout(8000);
        connection.setReadTimeout(12000);
        connection.setUseCaches(false);
        connection.setRequestProperty("Accept", "application/json");
        connection.setRequestProperty("Authorization", "Bearer " + getSavedToken(this));
        connection.setRequestProperty("X-Device-ID", deviceId);
        return connection;
    }

    private static String getOrCreateDeviceId(Context context) {
        android.content.SharedPreferences preferences = context.getSharedPreferences(PREFS, MODE_PRIVATE);
        String id = preferences.getString("device_id", "");
        if (id == null || id.isEmpty()) {
            id = UUID.randomUUID().toString().replace("-", "");
            preferences.edit().putString("device_id", id).apply();
        }
        return id;
    }

    private void sendHeartbeat() {
        new Thread(() -> {
            HttpURLConnection connection = null;
            try {
                connection = openConnection("device.php");
                connection.setRequestMethod("POST");
                connection.setDoOutput(true);
                connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
                try (OutputStream output = connection.getOutputStream()) {
                    output.write("action=ping".getBytes(StandardCharsets.UTF_8));
                }
                if (connection.getResponseCode() != HttpURLConnection.HTTP_OK) {
                    updateNotification("Server authorization failed");
                }
            } catch (Exception ignored) {
                updateNotification("SMS server unavailable");
            } finally {
                if (connection != null) connection.disconnect();
            }
        }, "absms-heartbeat").start();
    }

    private void fetchAndSendOneMessage() {
        if (!requestInFlight.compareAndSet(false, true)) return;
        new Thread(() -> {
            HttpURLConnection connection = null;
            try {
                connection = openConnection("sms.php?sms=unsent");
                if (connection.getResponseCode() != HttpURLConnection.HTTP_OK) {
                    updateNotification("Server authorization failed");
                    return;
                }
                JSONArray messages = new JSONArray(readResponse(connection.getInputStream()));
                if (messages.length() == 0) {
                    updateNotification("Queue empty");
                    return;
                }
                JSONObject message = messages.getJSONObject(0);
                int id = message.getInt("id");
                String phone = message.getString("phone");
                String body = message.getString("message");
                updateNotification("Sending a queued message");
                boolean accepted = smsUtil.sendSMS(phone, body);
                updateStatus(id, accepted ? "mark_sent" : "mark_failed");
                updateNotification(accepted ? "Queued message submitted" : "Message could not be submitted");
            } catch (Exception ignored) {
                updateNotification("SMS server unavailable");
            } finally {
                if (connection != null) connection.disconnect();
                requestInFlight.set(false);
            }
        }, "absms-queue").start();
    }

    private String readResponse(InputStream input) throws Exception {
        StringBuilder result = new StringBuilder();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(input, StandardCharsets.UTF_8))) {
            String line;
            while ((line = reader.readLine()) != null) result.append(line);
        }
        return result.toString();
    }

    private void updateStatus(int id, String action) {
        HttpURLConnection connection = null;
        try {
            connection = openConnection("sms.php");
            connection.setRequestMethod("POST");
            connection.setDoOutput(true);
            connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
            byte[] body = ("action=" + action + "&id=" + id).getBytes(StandardCharsets.UTF_8);
            try (OutputStream output = connection.getOutputStream()) {
                output.write(body);
            }
            if (connection.getResponseCode() != HttpURLConnection.HTTP_OK) {
                updateNotification("Message status was not accepted by the server");
            }
        } catch (Exception ignored) {
            updateNotification("Message status could not be saved");
        } finally {
            if (connection != null) connection.disconnect();
        }
    }

    private boolean isNetworkAvailable() {
        ConnectivityManager manager = (ConnectivityManager) getSystemService(CONNECTIVITY_SERVICE);
        if (manager == null) return false;
        NetworkInfo info = manager.getActiveNetworkInfo();
        return info != null && info.isConnected();
    }

    private void updateNotification(String text) {
        if (handler == null) return;
        handler.post(() -> {
            if (Build.VERSION.SDK_INT >= 26) {
                NotificationChannel channel = new NotificationChannel(CHANNEL_ID, "SMS Gateway", NotificationManager.IMPORTANCE_LOW);
                notificationManager.createNotificationChannel(channel);
            }
            Intent actionIntent = new Intent(this, SmsService.class);
            actionIntent.setAction(paused ? ACTION_RESUME : ACTION_PAUSE);
            PendingIntent pendingIntent = PendingIntent.getService(
                    this, 0, actionIntent, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
            Notification.Builder builder = Build.VERSION.SDK_INT >= 26
                    ? new Notification.Builder(this, CHANNEL_ID)
                    : new Notification.Builder(this);
            Notification notification = builder.setContentTitle("SMS Gateway")
                    .setContentText(text)
                    .setSmallIcon(android.R.drawable.ic_dialog_email)
                    .setOnlyAlertOnce(true)
                    .addAction(paused ? android.R.drawable.ic_media_play : android.R.drawable.ic_media_pause,
                            paused ? "Resume" : "Pause", pendingIntent)
                    .build();
            if (!foregroundStarted) {
                startForeground(NOTIFICATION_ID, notification);
                foregroundStarted = true;
            } else {
                notificationManager.notify(NOTIFICATION_ID, notification);
            }
        });
    }
}
