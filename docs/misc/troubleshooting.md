<!-- docs/misc/troubleshooting.md -->

## LDAP login shows "wrong passphrase" on first-time login

### Symptom
During first-time LDAP logins, users may see:
> "Wrong / not accepted passphrase"

and cannot proceed to the database key setup screen.

### Root cause
This can occur if the PHP-FPM pool is saturated or too small, causing POST requests (especially the passphrase submission) to time out.

**In php-fpm logs:**
`WARNING: [pool www] server reached pm.max_children setting (5), consider raising it`

**In Apache access logs:**
`HTTP 408 (Request Timeout) entries around the same time`

These timeouts make Teampass appear to reject the passphrase, while in fact the request was never processed.

### Solution

Tune your PHP-FPM pool settings. Edit `/etc/php/8.x/fpm/pool.d/www.conf`:

```
pm = dynamic
pm.max_children = 20      ; was 5
pm.start_servers = 4      ; was 2
pm.min_spare_servers = 4  ; was 1
pm.max_spare_servers = 10 ; was 3
pm.max_requests = 500     ; optional but recommended
```

Then reload:
```bash
systemctl reload php8.x-fpm
systemctl reload apache2
```

> **TL;DR** — If you see "wrong passphrase" but logs show FPM warnings or HTTP 408s: it is not a bad passphrase, it is PHP-FPM capacity. Increase pool size and try again.

---

## Page loads but items or folders do not appear

### Possible causes and fixes

**1. Session expired silently**
Reload the page. If you are redirected to the login page, your session expired. See [Session management](session-management.md).

**2. Folder tree not loading**
Open the browser console (F12 → Console). If you see JavaScript errors, clear your browser cache and reload.

**3. No access to any folder**
If the folder tree is empty after login, your account has no role assigned. Ask an administrator to assign a role with folder access (see [Users](../features/users.md)).

**4. APCu cache serving stale settings**
If a setting change is not reflected, restart PHP-FPM to clear the 60-second APCu cache: `systemctl reload php8.x-fpm`.

---

## Emails are not being sent

### Checklist

1. **Verify email settings** — Go to **Admin → Emails** and confirm the SMTP host, port, and credentials are correct.
2. **Test sending** — Use the **Send test email** button on the Emails page.
3. **Check the task queue** — Email sending is handled by background tasks. Go to **Tasks** and verify the email task is not stuck or in error.
4. **PHP mail function** — If using `mail()` instead of SMTP, verify that the server's mail transfer agent (Postfix, Sendmail, etc.) is running.
5. **Firewall** — Verify outbound connections on port 25, 465, or 587 are not blocked.

---

## Background tasks are not running

Teampass uses a cron job to run background tasks (key generation, email, statistics). If tasks are stuck:

1. **Verify the cron entry**:
```bash
crontab -l -u www-data
```
It should contain something like:
```
* * * * * php /var/www/html/teampass/scripts/background_tasks___handler.php
```

2. **Run manually to see errors**:
```bash
php /var/www/html/teampass/scripts/background_tasks___handler.php
```

3. **Check file permissions** — The `www-data` user must be able to read and write inside the Teampass directory.

4. **Check the Tasks page** — In the Admin menu, **Tasks** shows each task's last execution time, status, and any error messages.

---

## A user's items all show as empty after re-encryption

After a key regeneration or migration, items may temporarily appear empty while the background task processes the sharekeys. This is normal and should resolve within a few minutes.

To monitor progress:

1. Go to **Admin → Tasks**.
2. Find the key generation task for that user.
3. Wait for it to complete (status changes from `in progress` to `done`).

If the task completed but items are still empty, use the **Tools** page to run a diagnostic and repair.

---

## Upgrade fails or leaves the interface broken

If an upgrade via `upgrade.php` fails partway through:

1. **Do not run the upgrade script again immediately** — check the error message first.
2. **Restore your database backup** (taken before the upgrade).
3. **Check PHP error logs**: `tail -f /var/log/php_errors.log` or the Apache/Nginx error log.
4. **Common cause**: PHP version mismatch. Teampass requires PHP 8.1+. Verify with `php -v`.
5. **Permissions**: ensure the web server can write to the Teampass directory during upgrade.

If you cannot resolve the issue, open a ticket on [GitHub Issues](https://github.com/nilsteampassnet/TeamPass/issues) with the error message and your PHP / database versions.

---

## OAuth2 / Azure Entra users cannot log in on second attempt

### Symptom
A user authenticates successfully via Azure the first time, but on subsequent logins sees "Login credentials do not correspond" or is redirected back to the login page.

### Cause
The account creation background task (key generation) may not have completed before the second login attempt. The account is created but `is_ready_for_usage` is still `0`.

### Fix
1. Go to **Admin → Tasks** and verify the key generation task for that user completed successfully.
2. If the task failed, check the task error message. Common causes: missing email address in the Azure profile, or the background task cron job is not running.
3. Once the task completes, the user can log in normally.
