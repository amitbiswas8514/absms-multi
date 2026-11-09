# PHP SMS backend

The backend uses SQLite for devices and message queues. Read [`../docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md) before installing it. Serve this directory only over HTTPS, configure the SQLite database outside the document root, and never commit the live database or environment values.

Admin pages require a password hash configured as `SMS_GATEWAY_ADMIN_PASSWORD_HASH`. Create a separate device token in the signed-in admin page for each phone; the server stores only a SHA-256 token hash and shows a new token once.
