# Upgrades

## Bare Metal

If you perform a Mbin upgrade (eg. `git pull`), be aware to _always_ execute the following Bash script:

```bash
./bin/post-upgrade
```

### Clear Cache

For stale rendered HTML fragments or application cache, run from the Mbin directory as the application user. This clears Symfony's application cache namespace while preserving sessions:

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console cache:pool:clear cache.app
```

`post-upgrade` already clears framework cache, including compiled Twig templates. To repeat manually, run `APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear`. If OPcache is enabled, also reload PHP-FPM. See [Symfony's cache-clearing documentation](https://symfony.com/doc/current/cache.html#clearing-the-cache).

> [!WARNING]
> `FLUSHDB` (via `redis-cli`, `valkey-cli`, or `keydb-cli`) deletes **every key in the selected database**, including sessions since Mbin v1.11.0 and other application data, potentially logging users out. It cannot target a key prefix, and persistence does not protect against deletion. Use the scoped cache command above for routine clearing; see also the [session persistence guide](../02-configuration/05-redis.md#persistence).

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
