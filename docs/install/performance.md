<!-- docs/install/performance.md -->

## Performance & Server Recommendations

TeamPass works out of the box with a standard PHP/MySQL stack. The following optional extensions and settings are recommended for production deployments, especially under high concurrency (~50+ concurrent users).

The installer and upgrade wizard check for these extensions and display a warning if they are absent. None of these checks block the installation.


## 1. OPcache (bytecode cache)

OPcache compiles PHP files once and caches the bytecode in shared memory. This significantly reduces CPU usage and response times.

### Check

```
php -r "echo ini_get('opcache.enable');"
```

A value of `1` means it is active.

### Install (Debian/Ubuntu)

```
sudo apt-get install php8.4-opcache
```

> :bulb: **Note:** Adapt the PHP version number to your environment.

### Enable in `php.ini`

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

Restart your web server or PHP-FPM after changing `php.ini`.

---

## 2. PHP-FPM (process manager)

PHP-FPM handles PHP processes independently of the web server, which significantly improves concurrency compared to `mod_php`.

TeamPass detects the current SAPI at runtime. If you are not running PHP-FPM, the installer and admin health page display a recommendation.

### Install and enable (Debian/Ubuntu with Apache)

```
sudo apt-get install php8.4-fpm libapache2-mod-fcgid
sudo a2enmod proxy_fcgi setenvif
sudo a2enconf php8.4-fpm
sudo systemctl restart apache2
```

---

## 3. APCu (settings cache)

When `ext-apcu` is available, TeamPass caches the full application settings in shared memory (TTL: 60 seconds). This avoids one database query per request for the settings table.

### Check

```
php -r "echo extension_loaded('apcu') ? 'ok' : 'missing';"
```

### Install (Debian/Ubuntu)

```
sudo apt-get install php8.4-apcu
```

### Enable in `php.ini` (if not enabled automatically)

```ini
apc.enabled=1
apc.shm_size=32M
```

No TeamPass configuration is needed — the cache is used automatically when the extension is available.

> :warning: **Important:** Any write to the `teampass_misc` table must be followed by a call to `ConfigManager::invalidateCache()`. This is already handled by the admin settings save handler. Do not write to `teampass_misc` directly without invalidating the cache.

---

## 4. Redis session storage

By default, PHP sessions are stored on the filesystem. For high-concurrency deployments, Redis-based sessions reduce filesystem I/O and allow session sharing across multiple PHP-FPM workers.

### Prerequisites

* A running Redis server (version 5+)
* `ext-redis` PHP extension

### Install (Debian/Ubuntu)

```
sudo apt-get install redis-server php8.4-redis
sudo systemctl enable --now redis-server
```

### Enable in TeamPass

1. Log in as Admin
2. Go to **Administration > Settings > WebSocket**
3. Enable **Redis session storage**
4. Set **Redis host** (default: `127.0.0.1`) and **Redis port** (default: `6379`)
5. Optionally set a **key prefix** (default: `teampass_sess_`)
6. Save settings

The settings are stored in `teampass_misc` and applied on the next request:

| Setting key | Default |
|---|---|
| `redis_session_enabled` | `0` |
| `redis_host` | `127.0.0.1` |
| `redis_port` | `6379` |
| `redis_prefix` | `teampass_sess_` |

> :bulb: **Note:** If Redis is unavailable at startup, TeamPass falls back to filesystem sessions automatically. No data is lost.

### Architecture

```
Redis enabled?
  ext-redis loaded + redis_session_enabled = 1
    → Connect to Redis (2-second timeout)
    → Sessions encrypted at rest (Defuse PHP Encryption)
    → Fallback to filesystem on any connection error

Redis disabled / ext-redis absent:
    → Filesystem sessions (encrypted at rest)
```

---

## 5. Admin health check

Go to **Administration > Dashboard**. The health check section displays the status of each recommended extension:

| Check | Type | Description |
|---|---|---|
| OpenSSL | Required | Encryption |
| OPcache | Recommended | Bytecode cache active |
| PHP-FPM | Recommended | Running under FPM SAPI |
| APCu | Recommended | Settings cache available |
| Redis sessions | Informational | Redis session handler active |
| WebSocket indexes | Informational | DB indexes present (when WebSocket is enabled) |
