# ABSMS Multi

An Android app and PHP backend for queue based SMS sending. The Android foreground service checks a configured server for queued messages, sends them through the device SIM, and reports message status.

> **Use with care:** The backend now uses HTTPS-only app URLs, per-device bearer tokens, authenticated admin sessions, CSRF protection, and SQLite queue transactions. Review [Security](docs/SECURITY.md) and [Deployment](docs/DEPLOYMENT.md) before deployment. The phone still sends queued messages automatically while the user-enabled service is running.

## Project layout

- `app/` — Android application source, resources, and Gradle module configuration.
- `server/smsmulti/` — PHP API and admin pages.
- `docs/ARCHITECTURE.md` — request flow and app behavior.
- `docs/SECURITY.md` — implemented protections and remaining security limits.
- `docs/DEPLOYMENT.md` — server setup, device pairing, and data migration notes.

## Installation

Install the PHP backend over HTTPS first, then build and pair the Android app. The full [installation and deployment guide](docs/DEPLOYMENT.md) includes server requirements, PHP-FPM configuration, Android build commands, device pairing, and troubleshooting.

Quick requirements:

- Backend: PHP 8.1+ with PDO SQLite and sessions enabled; HTTPS; SQLite storage outside the public web root.
- Android build: Android Studio, JDK 17, Android SDK Platform 34, and Gradle 8.9. This repository does not include a Gradle wrapper; see the guide for creating one locally.
- SMS testing: a physical Android phone with a SIM and a test number you control.

## Configure the server

The app has no default SMS server. In **SetServer**, enter the base HTTPS URL for a server you control and a per-device token created in the admin page. The token is stored in private app preferences and is not backed up by Android. The app rejects non-HTTPS URLs.

The PHP backend stores registered devices and queued SMS in a SQLite database outside the public web directory. Configure `SMS_GATEWAY_DB_PATH` and `SMS_GATEWAY_ADMIN_PASSWORD_HASH` in the server environment. See [Deployment](docs/DEPLOYMENT.md).

Keep the database and device tokens private. Revoke a device token immediately if the phone is lost or the token is exposed.

## SMS workflow

1. The user enables the visible SMS service and grants the required permissions.
2. The app authenticates with its device token and pairs its device ID.
3. An admin queues a message for that registered device.
4. The phone sends it through `SmsManager` and reports submission status.

Only send messages to recipients who have consented, and follow applicable carrier, platform, and local requirements.

## Data and privacy

The source ZIP included an embedded server archive with device records, phone numbers, OTP-like message contents, and a backup JSON file. Those records were omitted from this repository. The hard-coded SMS test recipient was also removed. Never commit production SMS data, device tokens, admin credentials, database files, or signing keys.
