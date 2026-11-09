# Security and privacy review

This review describes the controls added to the repository and remaining limits. It is based on static inspection; it is not a penetration test or production security certification.

## Controls now in place

- Each Android installation uses a random 256-bit bearer token created by an authenticated admin. The server stores only a SHA-256 token hash, binds the token to the first device ID that uses it, scopes SMS queue reads to that device, and supports token revocation.
- Admin pages use a password hash configured outside the repository, strict secure session cookies, CSRF tokens for writes, prepared SQL statements, and login throttling.
- Queue/device state uses SQLite transactions instead of mutable JSON files. The configured database path must be absolute and outside the web document root; its directory/file permissions are restricted.
- The Android app accepts HTTPS URLs only, sends the token in an Authorization header, disables cleartext traffic and backups, and relies on the default TLS certificate and hostname checks.
- The notification no longer displays recipient phone numbers or message text. The service starts only after a user action, an explicit opt-in prompt, and runtime permission checks; it can be stopped from the app and does not auto-restart.
- The monitor renders server values as text, not HTML. Unused SMS receive, privileged no-confirmation, boot, and wake-lock permissions were removed.

## Remaining risks and limits

1. **SMS permission and distribution policy.** While enabled, the service sends every queued message addressed to the paired device. The phone user must control the server and recipients, and the app must clearly disclose this behavior. Google Play currently restricts the SMS permission group, including `SEND_SMS`, to apps registered as the default SMS or Assistant handler, subject to the current policy. This app is not a default SMS app, so Play distribution may be blocked unless the design and eligibility change. Review the [current SMS permissions policy](https://support.google.com/googleplay/android-developer/answer/10208820) before distribution.
2. **Delivery is not exactly once.** Android's `SmsManager` call does not include delivery callbacks. If the SMS is submitted but the phone loses connectivity before the server records the status, the server may retry it after the 60-second queue lease. Do not use this prototype for payments, OTP delivery, or other messages where duplicates have material consequences.
3. **Token storage.** The device token is kept in the app's private preferences. Backup is disabled, but a rooted or compromised phone can expose it. Revoke and replace tokens after loss or compromise.
4. **Data retention.** SQLite contains recipient numbers and message bodies, potentially including OTPs. Restrict access, encrypt backups, define retention/deletion, and periodically purge sent/failed records.
5. **Transport/deployment.** HTTPS must terminate at a trusted certificate. Set the database path outside the web root and configure equivalent access-denial rules if using Nginx; the included `.htaccess` covers Apache only.
6. **Foreground-service policy.** The app currently targets API 34. Android 15/API 35 adds a six-hour-per-24-hour limit for `dataSync` foreground services and requires timely stop behavior. Review [Android foreground-service timeouts](https://developer.android.com/develop/background-work/services/fgs/timeout) before raising the target SDK.

## Repository hygiene

The original ZIP included device records, phone numbers, OTP-like messages, and a backup archive. They were excluded. The hard-coded test recipient was removed. Do not commit live database files, device tokens, password hashes, message data, signing keys, or server logs.
