# Installation and deployment

This guide installs the PHP backend on an HTTPS web server, builds the Android app, and pairs a phone with the server. Install the backend before configuring the phone. Use a server and SIM that you control, and send only messages you are authorized to send.

## 1. Requirements

### Backend host

- A domain name with a valid HTTPS certificate.
- Apache with PHP-FPM or Nginx with PHP-FPM.
- PHP 8.1 or newer with the `pdo_sqlite` and `session` extensions enabled.
- A writable database directory outside the web document root.
- A long, unique admin password.

Check the PHP runtime used by the web server, not only the command-line PHP version. For example:

```sh
php -v
php -m | grep -E 'PDO|pdo_sqlite|session'
```

The PHP output should include `PDO`, `pdo_sqlite`, and `session`.

### Android build computer

- Android Studio with Android SDK Platform 34 installed.
- JDK 17.
- Gradle 8.9 if building from the command line. This project uses Android Gradle Plugin 8.7.0, which requires Gradle 8.9 and JDK 17 ([compatibility details](https://developer.android.com/build/releases/past-releases/agp-8-7-0-release-notes)).
- A physical Android phone with a SIM for SMS testing. An emulator can build and launch the app, but it cannot verify carrier SMS delivery.

This repository does not include a Gradle wrapper. Install Gradle 8.9, then create a local wrapper from the project root if you prefer wrapper commands:

```sh
gradle wrapper --gradle-version 8.9
./gradlew :app:assembleDebug
```

The wrapper files are generated locally. Alternatively, run `gradle :app:assembleDebug` directly.

## 2. Install the PHP backend

### 2.1 Deploy only the backend directory

Clone the repository somewhere private, then copy only `server/smsmulti/` to a dedicated HTTPS site path, for example `/var/www/sms.example.com/smsmulti/`:

```sh
git clone https://github.com/amitbiswas8514/absms-multi.git
sudo install -d -o root -g www-data -m 0750 /var/www/sms.example.com/smsmulti
sudo cp -a absms-multi/server/smsmulti/. /var/www/sms.example.com/smsmulti/
sudo chown -R root:www-data /var/www/sms.example.com/smsmulti
sudo find /var/www/sms.example.com/smsmulti -type d -exec chmod 0750 {} +
sudo find /var/www/sms.example.com/smsmulti -type f -exec chmod 0640 {} +
```

Replace `www-data` with the web server/PHP-FPM group on your host. The public URL will then look like `https://sms.example.com/smsmulti/`. Do not make the Android project root or its `app/` and `docs/` directories public.

For a Git checkout, copy or link only this directory into the web root. Make sure the PHP files are handled by PHP-FPM and are not served as downloadable source.

### 2.2 Create a private database directory

Set the directory owner to the account used by the PHP-FPM pool. On Debian or Ubuntu this account is often `www-data`; substitute the actual service account on your host.

```sh
sudo install -d -o www-data -g www-data -m 0700 /var/lib/absms
```

The backend creates the SQLite database at the configured path and attempts to apply restrictive directory and file permissions. SQLite may also create `-wal` and `-shm` companion files in this directory, so the PHP service account needs write access to the directory. Keep this directory outside the web document root and do not expose it through a file-sharing or static-file rule.

### 2.3 Configure server environment variables

Set both values in the PHP-FPM pool or your hosting provider's PHP environment. Do not put real credentials in `.env`, source control, or a publicly served file. `.env.example` documents the variable names; this PHP application does not load `.env` files itself.

Generate the password hash on a trusted machine. Replace the placeholder with a long, unique password and keep the output private. This example contains only a placeholder; avoid putting your real password directly into a command that may be saved in shell history:

```sh
php -r 'echo password_hash("replace-with-a-long-unique-password", PASSWORD_DEFAULT), PHP_EOL;'
```

Configure the pool (example for `/etc/php/8.3/fpm/pool.d/www.conf`; paths and PHP version vary by host). Inject the hash into the PHP-FPM service environment using your host's secret/environment mechanism, then pass it from the master process into this pool:

```ini
env[SMS_GATEWAY_DB_PATH] = /var/lib/absms/sms-gateway.sqlite
env[SMS_GATEWAY_ADMIN_PASSWORD_HASH] = $SMS_GATEWAY_ADMIN_PASSWORD_HASH
```

If PHP-FPM uses `clear_env = yes`, explicit `env[...]` entries are needed so the application receives these values. Protect the service/pool configuration from other users, then reload PHP-FPM and the web server. On managed hosting, enter the values in its PHP environment-variable settings instead.

The application refuses a relative database path and refuses a path inside `DOCUMENT_ROOT`. A missing or inaccessible variable will cause the backend to fail; check the private PHP-FPM error log when diagnosing setup problems. Never display PHP errors publicly on a production site.

### 2.4 Configure the web server

Terminate HTTPS with a trusted certificate and redirect HTTP requests to HTTPS. The Android client rejects HTTP URLs, and admin cookies are marked `Secure`.

For Apache, enable PHP-FPM handling and allow the supplied `.htaccess` rules in this directory (for example, `AllowOverride Limit` or an equivalent setting supported by your Apache version). Those rules disable directory listings and deny access to hidden files and common JSON/database/backup files. Confirm that requesting `/.env.example` and any database file returns a denial, not file contents.

For Nginx, `.htaccess` is ignored. Add equivalent rules inside the HTTPS server block; for example:

```nginx
location ~ (^|/)\.(?!well-known/) { deny all; }
location ~* \.(json|sqlite(?:-shm|-wal)?|db|bak)$ { deny all; }
```

Also configure PHP-FPM for the PHP files and restrict access to logs, environment files, database files, and backups. Do not copy the SQLite database into the web root to make it writable.

### 2.5 Sign in and register a phone

1. Visit `https://sms.example.com/smsmulti/login.php` in a browser.
2. Sign in with the password whose hash you configured.
3. Open **Devices**, create a device entry, and copy its token. The raw token appears only once. Create a separate token for every phone.
4. Keep the token in a password manager until it is entered into the corresponding phone. If it is lost or exposed, revoke it and create a replacement.

## 3. Build and install the Android app

1. Clone or download this repository. Open its root directory (the directory containing `settings.gradle`) in Android Studio.
2. Set Android Studio's Gradle JDK to JDK 17, install Android SDK Platform 34 through SDK Manager, and sync the project.
3. Build the **app** module's **debug** variant. From a terminal, use `gradle :app:assembleDebug` or the generated `./gradlew :app:assembleDebug` command above.
4. Install the resulting `app/build/outputs/apk/debug/app-debug.apk` on a development phone. With USB debugging enabled and the phone connected, you can use:

   ```sh
   adb install -r app/build/outputs/apk/debug/app-debug.apk
   ```

   Android Studio's Run action can also build and install the debug app on a selected device.

This debug APK is for development and controlled testing. It is signed with a debug key. Do not distribute it as a production release. A release build needs a private signing key and a deliberate distribution process; never commit the key or passwords. Review the [Android build guide](https://developer.android.com/build/building-cmdline) before producing a release.

## 4. Pair the app and send a controlled test

1. Open the app and select **SetServer**.
2. Enter the backend base URL ending in `/smsmulti/`, for example `https://sms.example.com/smsmulti/`, and paste the token created for this phone. The app accepts HTTPS URLs only.
3. Press **Start SMS Service** and confirm the disclosure. Grant SMS and notification permissions when Android asks. The service runs visibly and can be paused from its notification or stopped in the app.
4. In the admin site, open **Devices** and confirm that the phone has paired. The server binds the token to the random installation ID on its first authenticated request.
5. Open **Queue SMS**, select that phone, and queue one test message to a number you control. Verify the phone receives or submits it and the queue status changes. SMS submission status is not a carrier delivery receipt.
6. Stop the service after testing and remove any test messages or numbers that should not remain in the queue/database.

## 5. Upgrade, backup, and operational care

- Deploy backend code updates without copying a live database into the web root. Back up SQLite consistently using SQLite's online backup mechanism, store backups encrypted outside the document root, and test restoration procedures.
- Set a retention period and regularly delete sent/failed rows that are no longer needed. The database contains recipient numbers and message bodies.
- Back up the password hash and deployment configuration securely. Do not back up device bearer tokens in plaintext. Revoke tokens for lost phones or suspected exposure.
- The backend intentionally does not migrate old JSON files. Pair devices with new tokens and only re-queue messages that remain valid and authorized. Never re-queue expired OTPs.
- If login or API calls fail, check the HTTPS certificate, PHP-FPM environment variables, enabled PDO SQLite extension, PHP-FPM/web-server error logs, database-directory permissions, and Apache/Nginx access rules. Keep diagnostics private because server logs may contain sensitive operational details.

## Platform and delivery limits

- `SEND_SMS` is a restricted Android permission. Google Play generally requires an eligible app to be the default SMS or Assistant handler; this app does not implement that role. Check the [current SMS permissions policy](https://support.google.com/googleplay/android-developer/answer/10208820) before planning Play distribution.
- As of November 2026, Android developer-verification protections have started rolling out for apps from participating stores in selected regions, with broader rollout planned in 2027. See the [current Android developer-verification guidance](https://developer.android.com/developer-verification) if distributing outside Play.
- The app targets Android API 34. Android 15/API 35 adds limits for `dataSync` foreground services; review the [foreground-service timeout guidance](https://developer.android.com/develop/background-work/services/fgs/timeout) before changing the target SDK.
- If a message is submitted by the SIM but the server does not receive the status update, the queue lease can expire and the message may be retried. Do not use this prototype for payments, OTP delivery, or other use cases where duplicate messages could cause harm.
