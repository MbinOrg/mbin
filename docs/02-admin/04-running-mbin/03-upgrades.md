# Upgrades

## Bare Metal

If you perform a Mbin upgrade (eg. `git pull`), be aware to _always_ execute the following Bash script:

```bash
./bin/post-upgrade
```

### Clear Cache

Run cache commands from the Mbin installation directory as the application user.

If rendered HTML fragments or other application cache remain stale after an upgrade, clear the Symfony application cache pool:

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console cache:pool:clear cache.app
```

This clears Mbin's application cache namespace in Redis/KeyDB/Valkey while preserving sessions. See [Symfony's cache-clearing documentation](https://symfony.com/doc/current/cache.html#clearing-the-cache).

The `post-upgrade` script already clears the framework cache, including compiled Twig templates. To repeat that step manually:

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
```

If you have OPcache enabled, also reload the PHP-FPM service to clear the PHP opcode cache.

> [!WARNING]
> Do not use `redis-cli FLUSHDB` (or `valkey-cli FLUSHDB` / `keydb-cli FLUSHDB`) for routine cache clearing. It deletes **every key in the selected database**, including sessions stored there since Mbin v1.11.0 and any other application data. This can log users out. `FLUSHDB` cannot be scoped to a key prefix; use the cache pool command above to clear cached HTML fragments without deleting sessions. Persistence does not protect against this intentional deletion.

For session persistence across server restarts, see the [persistence configuration guide](../02-configuration/05-redis.md#persistence).

## Docker

1. Pull the latest Docker image:

```bash
docker compose pull
```

Or, if you are building locally, then you'll need to rebuild the Mbin docker image (without using cached layers):

```bash
docker compose build --no-cache
```

2. Bring down the containers and up again (with `-d` for detach):

```bash
docker compose down
docker compose up -d
```
