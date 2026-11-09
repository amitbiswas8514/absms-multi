# Architecture and runtime flow

## Android app

`MainActivity` presents a server URL setting and starts `SmsService`. On first run it requests `SEND_SMS`; Android 13 and newer also require notification permission for the foreground service notification.

`SmsService` runs as a foreground `dataSync` service. It reads the base URL from app preferences (defaulting to a URL in the source), identifies the device with `ANDROID_ID`, and performs a loop approximately every three seconds:

- Sends a heartbeat about every ten seconds to `device.php?action=ping&device=…`.
- Requests one queued item from `sms.php?sms=unsent&device=…`.
- Sends the returned phone number and message with `SmsManager`.
- Calls `sms.php?action=mark_sent&id=…` or `mark_failed`.

`SmsUtil` splits long messages into SMS parts. The Android service does not ask for per-message confirmation, and its send call does not attach delivery/result callbacks.

## PHP backend

- `device.php` registers heartbeats and returns the device list.
- `sms.php` returns and locks a queued message, then updates status.
- `sendsms.php` accepts queue submissions and provides a basic HTML form.
- `monitor.php` polls `device.php` and renders device status.

The backend stores state in local JSON files. It has no database, authentication, authorization, or robust queue locking. These endpoints are suitable only for isolated development until the issues in [Security](SECURITY.md) are addressed.

## Build configuration snapshot

- Application ID / namespace: `com.my.absmsmulti`.
- `minSdkVersion`: 21.
- `targetSdkVersion`: 34.
- `compileSdk`: 34.
- Android Gradle Plugin: 8.7.0.
- Dependencies: AndroidX AppCompat, Material Components, Gson, and OkHttp.

No Gradle wrapper or automated tests were included in the provided ZIP.
