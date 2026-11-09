# PHP SMS backend

The scripts in `smsmulti/` are a prototype backend. They store queue/device state in JSON files and currently expose unauthenticated queue and status operations. Do not deploy them to a public server until the blockers in [`../docs/SECURITY.md`](../docs/SECURITY.md) are fixed.

For isolated local development only, create `smsmulti/devices.json` and `smsmulti/sms.json` containing `[]`. The PHP process needs write access to both files. Keep these runtime files outside the document root where possible and never commit them.
