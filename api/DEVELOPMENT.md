# API Development Mode (safe activation)

This file documents how to enable the API bootstrap "development" mode that returns detailed error information in JSON responses. Do NOT enable this in production.

Ways to enable (choose one):

- Temporarily (Windows PowerShell, current user):

```powershell
# Set environment variable for the current user (requires a new shell or restart of services to take effect)
setx API_DEV 1
# Then restart Apache from XAMPP Control Panel
```

- Per-project via `.htaccess` (local dev machine only — DO NOT deploy this to production):

Add to `api/.htaccess` (only on your local machine):

```
# Enable API development mode (development machine only)
SetEnv API_DEV 1
```

Note: depending on your PHP/Apache setup, `SetEnv` may be read by PHP as an env var. If not, you can use `php_value` to set an env var or `auto_prepend_file` to include debug helpers. Restart Apache after changes.

- Server config (httpd.conf / Apache VirtualHost) — preferred for dev VMs:

Inside your `<VirtualHost>` for localhost, add:

```
SetEnv API_DEV 1
```

Then restart Apache.

Why manual activation?

- Prevents accidental exposure of stack traces or sensitive paths in production.
- Gives developers explicit control when they need verbose debug information.

Security reminder

- Never commit environment-specific `.htaccess` with `API_DEV=1` to a repo used for production deployment.
- When finished debugging, unset the variable or remove the `.htaccess` line and restart Apache.

How to disable:

```powershell
setx API_DEV ""
# or remove the SetEnv/API_DEV line from .htaccess or httpd.conf and restart Apache
```

If you want, I can add a short CLI script to toggle this locally and restart Apache from the XAMPP CLI.
