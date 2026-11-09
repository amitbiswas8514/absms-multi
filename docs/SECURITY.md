# Security and privacy review

This review is based on a static inspection of the supplied ZIP. The app and backend should be treated as a prototype. Do not expose the PHP backend publicly or use it for real customer messages until the blockers below are resolved.

## Deployment blockers

1. **Unauthenticated SMS control.** `sendsms.php` accepts queue submissions without authentication. `sms.php` allows clients to fetch messages and change their status without credentials. Anyone who can reach the server may enqueue arbitrary messages or interfere with queue state. Add authenticated, per-device authorization and restrict administrative queue operations.
2. **No request integrity or abuse controls.** The queue form has no CSRF protection; endpoints lack rate limits and meaningful validation for phone numbers and message length. Add CSRF protections for browser forms, strict input validation, rate limits, and audit logging.
3. **Sensitive plaintext storage.** The PHP backend stores phone numbers and message bodies in JSON files. These may include OTPs. Keep the files outside the web root, deny direct downloads, set restrictive file permissions, and define retention/deletion rules. Prefer a transactional database for queue state.
4. **Unsafe monitor rendering.** `monitor.php` inserts `device_id` and status data into `innerHTML`. Render untrusted values with `textContent` and validate status values to prevent script injection.
5. **TLS trust disabled in the helper client.** `RequestNetworkController` accepts every server certificate and hostname. Remove the custom trust-all manager and hostname verifier; use Android's normal certificate validation. The active polling service uses `HttpURLConnection`, but the unsafe helper remains in the source.
6. **HTTP server URLs are accepted.** The app allows an operator to save an `http://` URL, and the manifest enables cleartext traffic. Require HTTPS and disable cleartext traffic before release.
7. **Automatic SMS sending.** The foreground service sends any returned queue item through the SIM without per-message confirmation. Require an explicit, informed opt-in, show recipients and content to the device owner where appropriate, and provide a clear stop control.

## Android permission and lifecycle notes

- The manifest declares `SEND_SMS`, `RECEIVE_SMS`, `SEND_SMS_NO_CONFIRMATION`, foreground service, data-sync foreground service, boot-completed, wake-lock, and notification permissions. The code shown requests only `SEND_SMS` and notifications at runtime. `RECEIVE_SMS` has no receiver in the supplied source; `SEND_SMS_NO_CONFIRMATION` is a privileged permission and should not be relied on by ordinary apps.
- The app starts its long-running service on launch and acquires a partial wake lock. Review battery impact and Android foreground-service policy before release.
- `ANDROID_ID` is sent to the configured backend as a device identifier. Treat it as linkable device data; avoid logging or publishing it and provide a privacy notice.
- The manifest allows backup and cleartext traffic. Review backup exclusions and network security configuration before distributing an APK.

## Repository hygiene

The supplied ZIP's embedded archive contained device records, phone numbers, OTP-like message samples, and a JSON backup. These runtime records were not copied into the repository. `.gitignore` excludes live `devices.json`, `sms.json`, and backup files. Do not commit credentials, real phone numbers, OTPs, message bodies, APK signing keys, or server logs.

## Before production

At minimum, add server authentication and per-device tokens, protect queue operations, use safe storage and locking, fix the XSS sink, restore TLS validation, require HTTPS, remove unused/privileged permissions, define consent and retention behavior, and test the supported Android versions and server deployment model.
