# ABSMS Multi

An Android app and small PHP backend for queue based SMS sending. The Android foreground service checks a configured server for queued messages, sends them through the device SIM, and reports message status. The PHP scripts expose the queue and a basic device monitor.

> **Prototype status:** This code is not ready for public or production deployment. The PHP endpoints currently have no authentication or authorization, and the Android client automatically sends queued messages. Review [Security](docs/SECURITY.md) before running or deploying either component.

## Project layout

- `app/` — Android application source, resources, and Gradle module configuration.
- `server/smsmulti/` — PHP endpoints and device monitor extracted from the ZIP's embedded archive. Runtime JSON data files are intentionally excluded.
- `docs/ARCHITECTURE.md` — request flow and app behavior.
- `docs/SECURITY.md` — security and privacy review with deployment blockers.

## Requirements

- Android Studio with Android Gradle Plugin 8.7.0 support.
- JDK 17.
- Android SDK platform 34 and a device with a SIM for SMS testing.
- PHP 8 or newer for the backend (the scripts use PHP built-in functions and no framework).

The ZIP did not include a Gradle wrapper. Open the project root in Android Studio, allow Gradle sync, then build the `app` module. The app requests SMS and notification permissions at runtime. Use a test device and test number you control.

## Configure the server

The Android service currently defaults to a URL configured in `SmsService.java`. In the app, use **SetServer** to enter the base URL for a server you control; the app appends `device.php` and `sms.php` to that URL. Use HTTPS only.

The PHP files expect writable `devices.json` and `sms.json` files in their own directory. Create them with `[]` and ensure the PHP process can write them. These files contain device identifiers, phone numbers, message bodies, and delivery status; keep them out of version control and protect them as sensitive data.

Do not expose this backend to the internet until authentication, authorization, input validation, CSRF protection for the web form, rate limiting, and safe concurrent queue storage are implemented. See [Security](docs/SECURITY.md).

## SMS workflow

1. The app starts a foreground service and sends a device heartbeat.
2. The service polls the backend for one unsent SMS.
3. The phone sends the message through `SmsManager` and reports success or failure.
4. The monitor page displays device status.

Only send messages to recipients who have consented, and follow applicable carrier, platform, and local requirements.

## Data and privacy

The source ZIP included an embedded server archive with device records, phone numbers, OTP-like message contents, and a backup JSON file. Those records were omitted from this repository. The demo phone number in the app's **Test** button was also removed; the button now directs the operator to queue an authorized test message through their server. Never commit production SMS data or credentials.
