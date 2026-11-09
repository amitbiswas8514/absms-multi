# Deployment and device pairing

## Requirements

- PHP 8.1 or newer with PDO SQLite enabled.
- A web server configured for HTTPS and a document root that exposes `server/smsmulti/`.
- A writable SQLite path outside the document root.
- A long, unique admin password.

## Configure PHP

Set these environment variables in the hosting control panel or PHP-FPM pool. Do not put production secrets in Git:

```text
SMS_GATEWAY_DB_PATH=/var/lib/absms/sms-gateway.sqlite
SMS_GATEWAY_ADMIN_PASSWORD_HASH=<output from password_hash>
```

Generate a password hash on a trusted machine:

```sh
php -r 'echo password_hash("replace-this-with-a-long-unique-password", PASSWORD_DEFAULT), PHP_EOL;'
```

Use the resulting hash as the environment value. Restrict the parent directory so only the PHP service account can read and write it. The backend rejects a database path that resolves inside the web document root. On Nginx, add equivalent rules to deny direct access to hidden and database files; `.htaccess` applies only to Apache.

Open `login.php` over HTTPS and sign in. Open **Devices**, create one token per phone, and copy each token before leaving the page; the raw token is only displayed once. Revoking a device disables its token.

## Configure the Android app

In **SetServer**, enter the HTTPS base URL ending in `/smsmulti/` (or the deployed equivalent) and paste the token created for that phone. Then press **Start SMS Service**. The app explains that it will send queued messages through the SIM and requests SMS and notification permissions only when the user enables it.

Use **Queue SMS** to select a registered device and add a recipient and message. The device ID is bound to the token the first time that phone connects. Queue delivery is scoped to that device.

## Data migration from the old JSON prototype

The prior JSON files did not have authenticated device registrations or a reliable device-to-message assignment. The hardened backend deliberately does not import the old files automatically. Keep any existing JSON and backups private and out of the web root. Pair phones with new per-device tokens, then manually re-queue only messages that are still authorized and needed. Do not re-queue OTPs or other expired messages.

## Operational limits

- The API and admin UI require HTTPS. Device tokens travel only in the `Authorization` header.
- Admin session cookies are `Secure`, `HttpOnly`, and `SameSite=Strict`; admin writes require CSRF tokens.
- SQLite contains message bodies and numbers. Restrict file permissions, use encrypted backups, and set a short retention period for sent/failed rows.
- Android's SMS submission call has no carrier delivery receipt in this app. A process interruption after submission but before the server records `sent` can cause a retry after the queue lease expires. Avoid using this prototype for time-sensitive or financial messages.
- The app currently targets Android API 34. Before targeting API 35+, review Android's `dataSync` foreground-service time limits and stop behavior.
