# Upgrades

## Bare Metal

If you perform a Mbin upgrade (eg. `git pull`), be aware to _always_ execute the following Bash script:

```bash
./bin/post-upgrade
```

### Clear Cache

And when needed also execute: `sudo redis-cli FLUSHDB` to get rid of Redis/KeyDB cache issues. And reload the PHP FPM service if you have OPCache enabled.

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
