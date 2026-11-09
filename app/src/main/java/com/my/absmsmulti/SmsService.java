package com.my.absmsmulti;

import android.app.Service;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;

import android.content.Intent;
import android.content.Context;

import android.net.ConnectivityManager;
import android.net.NetworkInfo;

import android.os.Handler;
import android.os.Looper;
import android.os.PowerManager;
import android.os.Build;

import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONObject;

import android.provider.Settings;


import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

public class SmsService extends Service {

	/* ================= CONFIG ================= */

	private static final String DEFAULT_SERVER =
	"https://azindiaonline.com/proxy/smsmulti/";

	private static final int LOOP_DELAY = 3000;       // 3 sec
	private static final int HEARTBEAT_DELAY = 10000; // 10 sec
	private static final int NOTIFY_ID = 1;

	public static final String ACTION_PAUSE  = "PAUSE_SERVICE";
	public static final String ACTION_RESUME = "RESUME_SERVICE";

	/* ================= CORE ================= */

	private Handler handler;
	private SmsUtil smsUtil;
	private PowerManager.WakeLock wakeLock;
	private NotificationManager notificationManager;

	private boolean isPaused = false;
	private boolean isForegroundStarted = false;

	private long lastHeartbeat = 0;
	private String deviceId;

	/* ================= LIFECYCLE ================= */


		private String getBaseUrl() {

			String url = getSharedPreferences("gateway_config", MODE_PRIVATE)
			.getString("server_url", DEFAULT_SERVER);

			if (!url.endsWith("/")) {
				url += "/";
			}

			return url;
		}

		public static void saveServer(Context context, String url) {

			if (url == null || url.trim().isEmpty()) {
				return;
			}

			url = url.trim();

			if (!url.endsWith("/")) {
				url += "/";
			}

			context.getSharedPreferences("gateway_config", MODE_PRIVATE)
			.edit()
			.putString("server_url", url)
			.apply();
		}

		public static String getSavedServer(Context context) {

			String url = context.getSharedPreferences("gateway_config", MODE_PRIVATE)
			.getString("server_url", DEFAULT_SERVER);

			if (!url.endsWith("/")) {
				url += "/";
			}

			return url;
		}

		public static void resetServer(Context context) {

			context.getSharedPreferences("gateway_config", MODE_PRIVATE)
			.edit()
			.putString("server_url", DEFAULT_SERVER)
			.apply();
		}






	@Override
	public void onCreate() {
		super.onCreate();

		handler = new Handler(Looper.getMainLooper());
		smsUtil = new SmsUtil();

		notificationManager =
		(NotificationManager) getSystemService(NOTIFICATION_SERVICE);

		// Sketchware-safe unique device ID
		deviceId = Settings.Secure.getString(
		getContentResolver(),
		Settings.Secure.ANDROID_ID
		);

		acquireWakeLock();
		updateNotification("Service started");
		startLoop();










	}






	@Override
	public int onStartCommand(Intent intent, int flags, int startId) {

		if (intent != null && intent.getAction() != null) {

			if (ACTION_PAUSE.equals(intent.getAction())) {
				isPaused = true;
				updateNotification("Paused");
			}

			if (ACTION_RESUME.equals(intent.getAction())) {
				isPaused = false;
				updateNotification("Resumed");
			}
		}
		return START_STICKY;
	}

	@Override
	public void onDestroy() {
		if (wakeLock != null && wakeLock.isHeld()) {
			wakeLock.release();
		}
		super.onDestroy();
	}

	@Override
	public android.os.IBinder onBind(Intent intent) {
		return null;
	}

	/* ================= MAIN LOOP ================= */

	private void startLoop() {

		handler.postDelayed(new Runnable() {
			@Override
			public void run() {

				if (isPaused) {
					updateNotification("Paused");
				} else if (!isNetworkAvailable()) {
					updateNotification("No Internet");
				} else {

					long now = System.currentTimeMillis();

					if (now - lastHeartbeat > HEARTBEAT_DELAY) {
						sendHeartbeat();
						lastHeartbeat = now;
					}

					updateNotification("Checking...");
					checkForSMS();
				}

				handler.postDelayed(this, LOOP_DELAY);
			}
		}, 2000);
	}

	/* ================= HEARTBEAT ================= */

	private void sendHeartbeat() {

		new Thread(new Runnable() {
			@Override
			public void run() {
				try {
					URL url = new URL(
					getBaseUrl() + "device.php?action=ping&device=" + deviceId
					);
					HttpURLConnection c =
					(HttpURLConnection) url.openConnection();
					c.setConnectTimeout(5000);
					c.setReadTimeout(5000);
					c.getInputStream().close();
				} catch (Exception ignored) {}
			}
		}).start();
	}

	/* ================= FETCH & SEND SMS ================= */

	private void checkForSMS() {

		new Thread(new Runnable() {
			@Override
			public void run() {

				try {

					URL url = new URL(
					getBaseUrl() + "sms.php?sms=unsent&device=" + deviceId
					);

					HttpURLConnection conn =
					(HttpURLConnection) url.openConnection();
					conn.setConnectTimeout(10000);
					conn.setReadTimeout(10000);

					BufferedReader br = new BufferedReader(
					new InputStreamReader(conn.getInputStream())
					);

					StringBuilder result = new StringBuilder();
					String line;
					while ((line = br.readLine()) != null) {
						result.append(line);
					}

					JSONArray arr = new JSONArray(result.toString());

					if (arr.length() == 0) {
						updateNotification("Queue empty");
						return;
					}

					JSONObject sms = arr.getJSONObject(0);

					int id = sms.getInt("id");
					String phone = sms.getString("phone");
					String message = sms.getString("message");

					updateNotification("Sending to " + phone);

					boolean sent = smsUtil.sendSMS(phone, message);

					updateStatus(id, sent ? "mark_sent" : "mark_failed");

				} catch (Exception e) {
					updateNotification("Server error");
				}
			}
		}).start();
	}

	/* ================= UPDATE SERVER ================= */

	private void updateStatus(int id, String action) {

		try {
			URL url = new URL(
			getBaseUrl() + "sms.php?action=" + action + "&id=" + id
			);
			HttpURLConnection conn =
			(HttpURLConnection) url.openConnection();
			conn.getInputStream().close();
		} catch (Exception ignored) {}
	}

	/* ================= NETWORK ================= */

	private boolean isNetworkAvailable() {

		ConnectivityManager cm =
		(ConnectivityManager) getSystemService(CONNECTIVITY_SERVICE);

		NetworkInfo info = cm.getActiveNetworkInfo();
		return info != null && info.isConnected();
	}

	/* ================= WAKELOCK ================= */

	private void acquireWakeLock() {

		PowerManager pm =
		(PowerManager) getSystemService(POWER_SERVICE);

		wakeLock = pm.newWakeLock(
		PowerManager.PARTIAL_WAKE_LOCK,
		"ABSMS::WAKELOCK"
		);
		wakeLock.acquire();
	}

	/* ================= NOTIFICATION ================= */

	private void updateNotification(final String text) {

		handler.post(new Runnable() {
			@Override
			public void run() {

				String CHANNEL = "sms_gateway";

				if (Build.VERSION.SDK_INT >= 26) {
					NotificationChannel ch =
					new NotificationChannel(
					CHANNEL,
					"SMS Gateway",
					NotificationManager.IMPORTANCE_LOW
					);
					notificationManager.createNotificationChannel(ch);
				}

				Intent actionIntent =
				new Intent(SmsService.this, SmsService.class);
				actionIntent.setAction(
				isPaused ? ACTION_RESUME : ACTION_PAUSE
				);

				PendingIntent pi =
				PendingIntent.getService(
				SmsService.this,
				0,
				actionIntent,
				PendingIntent.FLAG_UPDATE_CURRENT |
				PendingIntent.FLAG_IMMUTABLE
				);

				Notification.Builder builder =
				Build.VERSION.SDK_INT >= 26
				? new Notification.Builder(
				SmsService.this, CHANNEL
				)
				: new Notification.Builder(SmsService.this);

				builder.setContentTitle("SMS Gateway Running")
				.setContentText(text)
				.setSmallIcon(
				android.R.drawable.ic_dialog_email
				)
				.setOnlyAlertOnce(true)
				.addAction(
				isPaused
				? android.R.drawable.ic_media_play
				: android.R.drawable.ic_media_pause,
				isPaused ? "Resume" : "Pause",
				pi
				);

				Notification notification = builder.build();

				if (!isForegroundStarted) {
					startForeground(NOTIFY_ID, notification);
					isForegroundStarted = true;
				} else {
					notificationManager.notify(
					NOTIFY_ID, notification
					);
				}
			}
		});
	}

	/* ================= TOAST (OPTIONAL) ================= */

	private void show(final String msg) {
		handler.post(new Runnable() {
			@Override
			public void run() {
				Toast.makeText(
				getApplicationContext(),
				msg,
				Toast.LENGTH_SHORT
				).show();
			}
		});
	}
}
