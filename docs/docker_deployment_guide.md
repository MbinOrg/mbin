# Docker Deployment Guide (Alternative)

## System Requirements

- Docker Engine
- Docker Compose V2

  > If you are using Compose V1, replace `docker compose` with `docker-compose` in those commands below.

# Admin Docker Guide

**Docker guide is still WIP. Not all the steps have been fully verified yet.**

For bare metal see: [Admin Bare Metal Guide](./admin_guide.md).

> **Note**
> /kbin is still in the early stages of development.

_Note:_ This guide is using the [v2 docker files](https://codeberg.org/Kbin/kbin-core/src/branch/develop/docker/v2).

## System Requirements

- Docker Engine
- Docker Compose V2

  > If you are using Compose V1, replace `docker compose` with `docker-compose` in those commands below.

### Docker Install

The most convenient way to install docker is using the official [convenience script](https://github.com/docker/docs/blob/main/_includes/install-script.md)
provided at [get.docker.com](https://get.docker.com/):

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
```

Alternatively, you can follow the [Docker install documentation](https://docs.docker.com/engine/install/) for your platform.

Once Docker is installed on your system, it is recommended to create a `docker` group and add it to your user:

```bash
sudo groupadd docker
sudo usermod -aG docker $USER
```

## Kbin Installation

### Preparation

Clone git repository:

```bash
git clone https://codeberg.org/Kbin/kbin-core.git
cd kbin-core
```

Build the Docker image:

```bash
docker build -t kbin -f docker/v2/Dockerfile .
```

Create config files and storage directories:

```bash
cd docker/v2
cp ../../.env.example .env
cp docker-compose.prod.yml docker-compose.override.yml
mkdir -p storage/media storage/caddy_condig storage/caddy_data
sudo chown 1000:82 storage/media storage/caddy_condig storage/caddy_data
```

### Configure `.env`

1. Choose your Redis password, PostgreSQL password, RabbitMQ password, and Mercure password.
2. Place them in the corresponding variables in both `.env` and `docker-compose.override.yml`.
3. In `.env`, change the following line:

   ```env
   DATABASE_URL="postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@127.0.0.1:5432/${POSTGRES_DB}?serverVersion=${POSTGRES_VERSION}&charset=utf8"
   ```

   to:

   ```env
   DATABASE_URL="postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@db:5432/${POSTGRES_DB}?serverVersion=${POSTGRES_VERSION}&charset=utf8"
   ```

4. In `.env`, change the following two lines

   ```env
   MERCURE_URL=http://localhost:3000/.well-known/mercure
   MERCURE_PUBLIC_URL=https://yourdomain.tld/.well-known/mercure
   ```

   to:

   ```env
   MERCURE_URL=http://www:80/.well-known/mercure
   MERCURE_PUBLIC_URL=https://${SERVER_NAME}/.well-known/mercure
   ```

5. In `.env`, change the following line:

   ```env
   REDIS_DNS=redis://${REDIS_PASSWORD}@127.0.0.1
   ```

   to:

   ```env
   REDIS_DNS=redis://${REDIS_PASSWORD}@redis
   ```

6. In `.env`, change the following line:

   ```env
   MESSENGER_TRANSPORT_DSN=amqp://kbin:${RABBITMQ_PASSWORD}@127.0.0.1:5672/%2f/messages
   ```

   to:

   ```env
   MESSENGER_TRANSPORT_DSN=amqp://kbin:${RABBITMQ_PASSWORD}@rabbitmq:5672/%2f/messages
   ```

### Running the containers

By default `docker compose` will execute the `docker-compose.yml` and `docker-compose.override.yml` files.

Run the container in the background (`-d` means detached, but this can also be omitted for testing):

```bash
docker compose up -d
```

See your running containers via: `docker ps`.

Then, you should be able to access the new instance via [http://localhost](http://localhost).  
You can also access RabbitMQ management UI via [http://localhost:15672](http://localhost:15672).

### Kbin first setup

Create new admin user (without email verification), please change the `username`, `email` and `password` below:

```bash
docker compose exec php bin/console kbin:user:create <username> <email@example.com> <password>
docker compose exec php bin/console kbin:user:admin <username>
```

```bash
docker compose exec php bin/console kbin:ap:keys:update
```

Next, log in and create a magazine named "random" to which unclassified content from the fediverse will flow.

### Add auxiliary containers to `docker-compose.yml`

Add any auxiliary container as you want. For example, add a Nginx container as reverse proxy to provide HTTPS encryption.

## Uploaded media files

Uploaded media files (e.g. photos uploaded by users) will be stored on the host directory `storage/media`. They will be served by the Caddy web server in the `www` container as static files.

Make sure `KBIN_STORAGE_URL` in your `.env` configuration file is set to be `https://yourdomain.tld/media` (assuming you setup Nginx with SSL certificate by now).

You can also serve those media files on another server by mirroring the files at `storage/media` and changing `KBIN_STORAGE_URL` correspondingly.

## Filesystem ACL support

The filesystem ACL is disabled by default, in the `kbin` image. You can set the environment variable `ENABLE_ACL=1` to enable it. Remember that not all filesystems support ACL. This will cause an error if you enable filesystem ACL for such filesystems.

## Production

If you created the file `docker-compose.override.yml` with your configs (`cp docker-compose.prod.yml docker-compose.override.yml`), running production would be the same command:

```bash
docker compose up -d
```

See also the official: [Deploying in Production guide](https://github.com/dunglas/symfony-docker/blob/main/docs/production.md).

If you want to deploy your app on a cluster of machines, you can
use [Docker Swarm](https://docs.docker.com/engine/swarm/stack-deploy/), which is compatible with the provided Compose
files.

## Clear cache

```bash
docker compose exec php bin/console cache:clear
docker compose exec redis redis-cli
> auth REDIS_PASSWORD
> FLUSHDB
```

## Backup and restore

```bash
docker exec -it container_id pg_dump -U kbin kbin > dump.sql
docker compose exec -T database psql -U kbin kbin < dump.sql
```
