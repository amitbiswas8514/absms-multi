package com.my.absmsmulti;

import android.Manifest;
import android.app.AlertDialog;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.text.InputType;
import android.view.View;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.my.absmsmulti.databinding.MainBinding;

import java.util.ArrayList;
import java.util.List;
import java.util.regex.Pattern;

public class MainActivity extends AppCompatActivity {
    private static final int REQUEST_SERVICE_PERMISSIONS = 101;
    private static final Pattern DEVICE_TOKEN = Pattern.compile("^[a-fA-F0-9]{64}$");

    private MainBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = MainBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        setSupportActionBar(binding.Toolbar);
        binding.Toolbar.setNavigationOnClickListener(view -> onBackPressed());
        binding.button1.setOnClickListener(view -> showServerDialog());
        binding.button2.setOnClickListener(view -> toggleService());
        binding.edittext1.setVisibility(View.GONE);
        updateServiceButton();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (binding != null) updateServiceButton();
    }

    private void showServerDialog() {
        LinearLayout fields = new LinearLayout(this);
        fields.setOrientation(LinearLayout.VERTICAL);
        int padding = (int) (20 * getResources().getDisplayMetrics().density);
        fields.setPadding(padding, padding / 2, padding, 0);

        EditText urlInput = new EditText(this);
        urlInput.setSingleLine(true);
        urlInput.setHint("https://your-domain.example/smsmulti/");
        urlInput.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        urlInput.setText(SmsService.getSavedServer(this));

        EditText tokenInput = new EditText(this);
        tokenInput.setSingleLine(true);
        tokenInput.setHint("Device token from the admin page");
        tokenInput.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
        tokenInput.setText(SmsService.getSavedToken(this));

        fields.addView(urlInput);
        fields.addView(tokenInput);
        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle("Secure SMS server")
                .setMessage("Use the HTTPS URL for your server and a token created for this device.")
                .setView(fields)
                .setPositiveButton("Save", null)
                .setNegativeButton("Cancel", null)
                .create();
        dialog.setOnShowListener(ignored -> dialog.getButton(DialogInterface.BUTTON_POSITIVE).setOnClickListener(view -> {
            String server = urlInput.getText().toString().trim();
            String token = tokenInput.getText().toString().trim();
            Uri uri = Uri.parse(server);
            boolean validServer = "https".equalsIgnoreCase(uri.getScheme())
                    && uri.getHost() != null && !uri.getHost().isEmpty()
                    && uri.getUserInfo() == null && uri.getQuery() == null && uri.getFragment() == null;
            if (!validServer) {
                urlInput.setError("Enter an HTTPS URL without credentials, query, or fragment.");
                return;
            }
            if (!DEVICE_TOKEN.matcher(token).matches()) {
                tokenInput.setError("Paste the 64-character token created for this device.");
                return;
            }
            SmsService.saveServer(this, server, token);
            Toast.makeText(this, "Server credentials saved on this device.", Toast.LENGTH_LONG).show();
            dialog.dismiss();
        }));
        dialog.show();
    }

    private void toggleService() {
        if (SmsService.isEnabled(this)) {
            stopService(new Intent(this, SmsService.class));
            SmsService.setEnabled(this, false);
            updateServiceButton();
            Toast.makeText(this, "SMS sending stopped.", Toast.LENGTH_SHORT).show();
            return;
        }

        if (SmsService.getSavedServer(this).isEmpty() || SmsService.getSavedToken(this).isEmpty()) {
            Toast.makeText(this, "Set an HTTPS server URL and device token first.", Toast.LENGTH_LONG).show();
            showServerDialog();
            return;
        }

        new AlertDialog.Builder(this)
                .setTitle("Enable automatic SMS sending?")
                .setMessage("While enabled, this phone sends messages queued for its registered device through the SIM. Only enable this for a server and recipients you are authorized to use.")
                .setNegativeButton("Cancel", null)
                .setPositiveButton("Enable", (dialog, which) -> requestServicePermissions())
                .show();
    }

    private void requestServicePermissions() {
        List<String> permissions = new ArrayList<>();
        if (checkSelfPermission(Manifest.permission.SEND_SMS) != PackageManager.PERMISSION_GRANTED) {
            permissions.add(Manifest.permission.SEND_SMS);
        }
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            permissions.add(Manifest.permission.POST_NOTIFICATIONS);
        }
        if (!permissions.isEmpty()) {
            requestPermissions(permissions.toArray(new String[0]), REQUEST_SERVICE_PERMISSIONS);
        } else {
            startSmsService();
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode != REQUEST_SERVICE_PERMISSIONS) return;
        boolean smsGranted = checkSelfPermission(Manifest.permission.SEND_SMS) == PackageManager.PERMISSION_GRANTED;
        boolean notificationsGranted = Build.VERSION.SDK_INT < 33
                || checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED;
        if (smsGranted && notificationsGranted) {
            startSmsService();
        } else {
            Toast.makeText(this, "SMS permission and notifications are required to enable the visible sending service.", Toast.LENGTH_LONG).show();
        }
    }

    private void startSmsService() {
        Intent service = new Intent(this, SmsService.class);
        SmsService.setEnabled(this, true);
        try {
            if (Build.VERSION.SDK_INT >= 26) startForegroundService(service);
            else startService(service);
            updateServiceButton();
        } catch (RuntimeException error) {
            SmsService.setEnabled(this, false);
            Toast.makeText(this, "The SMS service could not be started on this Android version.", Toast.LENGTH_LONG).show();
        }
    }

    private void updateServiceButton() {
        if (binding == null) return;
        binding.button2.setText(SmsService.isEnabled(this) ? "Stop SMS Service" : "Start SMS Service");
    }
}
