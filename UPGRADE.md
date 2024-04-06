# Upgrade

## Bare Metal / VM Upgrade

If you perform a mbin upgrade (eg. `git pull`), be aware to _always_ execute the following Bash script:

```bash
./bin/post-upgrade
```

And when needed also execute: `sudo redis-cli FLUSHDB` to get rid of Redis cache issues. And reload the PHP FPM service if you have OPCache enabled.

## Docker Upgrade

> [!Note]
> When you're using the [Docker v2 guide](docker/v2/), then the database migration is executed during the Docker container start-up.

```bash
$ docker compose exec php bin/console cache:clear
$ docker compose exec redis redis-cli
> auth REDIS_PASSWORD
> FLUSHDB
```
