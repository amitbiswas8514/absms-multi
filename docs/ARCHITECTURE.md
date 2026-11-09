# Architecture and runtime flow

## Android app

`MainActivity` lets the operator configure an HTTPS backend URL and the one-time device token issued by the admin page. SMS and notification permissions are requested only after the operator chooses **Start SMS Service** and confirms that the phone will send queued messages. The service can be paused from its notification or stopped in the app.

`SmsService` uses app-private preferences for the URL, token, and a random installation ID. It performs a visible foreground-service loop while enabled:

- Sends an authenticated heartbeat to `device.php` about every 15 seconds.
- Polls `sms.php` for one queued message scoped to the paired device.
- Submits the message through `SmsManager`.
- Posts an authenticated status update for that message.

The app sends the device token in an HTTPS `Authorization: Bearer` header. It does not display recipient numbers or message content in notifications. It does not attach SMS sent/delivery callbacks, so a server retry can duplicate a message after an interrupted status update.

## PHP backend

- `login.php` authenticates admin access with a password hash supplied through the hosting environment.
- `devices.php` issues one-time, per-device tokens and supports revocation.
- `device.php` records authenticated device heartbeats and serves the admin-only device list.
- `sendsms.php` provides an authenticated, CSRF-protected form to queue messages for a selected device.
- `sms.php` claims one message with a SQLite write transaction and permits status changes only by the device that claimed it.
- `monitor.php` displays device status with DOM text nodes.

The backend stores device records and messages in SQLite. It requires an absolute database path outside the public document root. Admin sessions use secure cookies and CSRF tokens for writes.

## Build configuration

- Application ID / namespace: `com.my.absmsmulti`.
- `minSdkVersion`: 21.
- `targetSdkVersion`: 34.
- `compileSdk`: 34.
- Android Gradle Plugin: 8.7.0.
- PHP: 8.1+ with PDO SQLite.

No Gradle wrapper or automated tests were included in the supplied ZIP. The current `dataSync` foreground service is started only by an explicit user action. Before raising the target SDK to Android 15/API 35, review [Android's data-sync foreground-service timeout guidance](https://developer.android.com/develop/background-work/services/fgs/timeout).
