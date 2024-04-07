# Upgrades

## Bare Metal

If you perform a Mbin upgrade (eg. `git pull`), be aware to _always_ execute the following Bash script:

```bash
./bin/post-upgrade
```

### Clear Cache

And when needed also execute: `sudo redis-cli FLUSHDB` to get rid of Redis/KeyDB cache issues. And reload the PHP FPM service if you have OPCache enabled.

## Docker


1. Go to the `docker` directory:

```bash
cd docker
```

2. (Re)build a new Mbin docker image (without using cached layers):

```bash
docker build --no-cache -t mbin -f Dockerfile  ..
```

3. Bring down the containers and up again (with `-d` for detach):

```bash
docker compose down
docker compose up -d
```

4. Clear the caches, see section below "Clear caches".

### Clear cache

Clear caches on **running** containers:

```bash
docker compose exec php bin/console cache:clear -n
docker compose exec redis redis-cli
> auth <your_redis_password>
> FLUSHDB
```
