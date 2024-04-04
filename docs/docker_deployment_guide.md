# Admin Docker Guide

For bare metal see: [Admin Bare Metal Guide](./admin_guide.md).

> [!Note]
> Mbin is still in development.

## System Requirements

- Docker Engine
- Docker Compose V2

  > If you are using Compose V1, replace `docker compose` with `docker-compose` in those commands below.

### Docker Install

The most convenient way to install docker is using an official [convenience script](https://docs.docker.com/engine/install/ubuntu/#install-using-the-convenience-script)
provided at [get.docker.com](https://get.docker.com/):

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
```

Alternatively, you can follow the official [Docker install documentation](https://docs.docker.com/engine/install/) for your platform.

Once Docker is installed on your system, it is recommended to create a `docker` group and add it to your user:

```bash
sudo groupadd docker
sudo usermod -aG docker $USER
```

## Mbin Installation

### Preparation

Clone git repository:

```bash
git clone https://github.com/MbinOrg/mbin.git
cd mbin
```

### Docker image preparation

> [!Note]
> If you're using a version of Docker Engine earlier than 23.0, run `export DOCKER_BUILDKIT=1`, prior to building the image. This does not apply to users running Docker Desktop. More info can be found [here](https://docs.docker.com/build/buildkit/#getting-started)

1. First go to the _docker directory_:

```bash
cd docker
```

2. Use the existing Docker image _OR_ build the docker image. Select one of the two options.

#### Build our own Docker image

If you want to build our own image, run (_no_ need to update the `compose.yml` file):

```bash
docker build --no-cache -t mbin -f Dockerfile  ..
```

#### Use Mbin pre-build image

_OR_ use our pre-build images from [ghcr.io](https://ghcr.io). In this case you need to update the `compose.yml` file:

```bash
nano compose.yml
```

Find and replace or comment-out the following 4 lines:

```yml
build:
  context: ../
  dockerfile: docker/Dockerfile
image: mbin
```

And instead use the following line on all places (`www`, `php`, and `messenger` services):

```yml
image: "ghcr.io/mbinorg/mbin:latest"
```

> [!Important]
> Do _NOT_ forget to change **ALL LINES** in that matches `image: mbin` to: `image: "ghcr.io/mbinorg/mbin:latest"` in the `compose.yml` file (should be 4 matches in total).

3. Create config files and storage directories:

```bash
cp ../.env.example_docker .env
cp compose.prod.yml compose.override.yml
mkdir -p storage/media storage/caddy_config storage/caddy_data storage/logs
sudo chown $USER:$USER storage/media storage/caddy_config storage/caddy_data storage/logs
```

### Configure `.env` and `compose.override.yml`

1. Choose your Redis password, PostgreSQL password, RabbitMQ password, and Mercure password.
2. Place the passwords in the corresponding variables in both `.env` and `compose.override.yml`.
3. Update the `SERVER_NAME`, `KBIN_DOMAIN` and `KBIN_STORAGE_URL` in `.env`.
4. Update `APP_SECRET` in `.env`, generate a new one via: `node -e  "console.log(require('crypto').randomBytes(16).toString('hex'))"`
5. _Optionally_: Use a newer PostgreSQL version (current fallback is v13). Update/set the `POSTGRES_VERSION` variable in your `.env` and `compose.override.yml` under `db`.

> [!Note]
> Ensure the `HTTPS` environmental variable is set to `TRUE` in `compose.override.yml` for the `php`, `messenger`, and `messenger_ap` containers **if your environment is using a valid certificate behind a reverse proxy**. This is likely true for most production environments and is required for proper federation, that is, this will ensure the webfinger responses include `https:` in the URLs generated.

### Configure OAuth2 keys

1. Create an RSA key pair using OpenSSL:

```bash
# Replace <mbin_dir> with Mbin's root directory
mkdir <mbin_dir>/config/oauth2/
# If you protect the key with a passphrase, make sure to remember it!
# You will need it later
openssl genrsa -des3 -out ./config/oauth2/private.pem 4096
openssl rsa -in ./config/oauth2/private.pem --outform PEM -pubout -out ./config/oauth2/public.pem
```

2. Generate a random hex string for the OAuth2 encryption key:

```bash
openssl rand -hex 16
```

3. Add the public and private key paths to `.env`:

```env
OAUTH_PRIVATE_KEY=%kernel.project_dir%/config/oauth2/private.pem
OAUTH_PUBLIC_KEY=%kernel.project_dir%/config/oauth2/public.pem
OAUTH_PASSPHRASE=<Your (optional) passphrase from above here>
OAUTH_ENCRYPTION_KEY=<Hex string generated in previous step>
```

### Running the containers

By default `docker compose` will execute the `compose.yml` and `compose.override.yml` files.

Run the container in the background (`-d` means detach, but this can also be omitted for testing or debugging purposes):

```bash
# Go to the docker directory within the git repo
cd docker

# Starts the containers
docker compose up -d
```

See your running containers via: `docker ps`.

Then, you should be able to access the new instance via [http://localhost:8008](http://localhost:8008).
You can also access RabbitMQ management UI via [http://localhost:15672](http://localhost:15672).

### Mbin first setup

Create new admin user (without email verification), please change the `username`, `email` and `password` below:

```bash
docker compose exec php bin/console mbin:user:create <username> <email@example.com> <password>
docker compose exec php bin/console mbin:user:admin <username>
```

```bash
docker compose exec php bin/console mbin:ap:keys:update
```

Next, log in and create a magazine named "random" to which unclassified content from the fediverse will flow.

### Debugging / Logging

1. List the running service containers with `docker compose ps`
2. You can see the logs with `docker compose logs -f <service>` (use `-f` to follow the output)
3. for `php`, `messenger` and `messenger_ap` services, the application log is also available at
   `storage/logs` directory on the host, named after the running environment and date
   (e.g. `storage/logs/prod-2023-12-01.log`)

### Add auxiliary containers to `compose.yml`

Add any auxiliary container as you want. For example, add a Nginx container as reverse proxy to provide HTTPS encryption.

### Uploaded media files

Uploaded media files (e.g. photos uploaded by users) will be stored on the host directory `storage/media`. They will be served by the Caddy web server in the `www` container as static files.

Make sure `KBIN_STORAGE_URL` in your `.env` configuration file is set to be `https://yourdomain.tld/media` (assuming you setup Nginx with SSL certificate by now).

You can also serve those media files on another server by mirroring the files at `storage/media` and changing `KBIN_STORAGE_URL` correspondingly.

### Filesystem ACL support

The filesystem ACL is disabled by default, in the `mbin` image. You can set the environment variable `ENABLE_ACL=1` to enable it. Remember that not all filesystems support ACL. This will cause an error if you enable filesystem ACL for such filesystems.

## Run Production

If you created the file `compose.override.yml` with your configs (`cp compose.prod.yml compose.override.yml`), running production would be the same command:

```bash
docker compose up -d
```

> [!Important]
> The docker instance is can be reached at [http://127.0.0.1:8008](http://127.0.0.1:8008), we strongly advise you to put a reverse proxy (like Nginx) in front of the docker instance. Nginx can could listen on ports 80 and 443 and Nginx should handle SSL/TLS offloading. See also Nginx example below.

If you want to deploy your app on a cluster of machines, you can
use [Docker Swarm](https://docs.docker.com/engine/swarm/stack-deploy/), which is compatible with the provided Compose
files.

### Mbin NGINX Server Block

NGINX reverse proxy example for the Mbin Docker instance:

```conf
# Redirect HTTP to HTTPS
server {
    server_name domain.tld;
    listen 80;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name domain.tld;

    charset utf-8;

    # TLS
    ssl_certificate /etc/letsencrypt/live/domain.tld/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domain.tld/privkey.pem;

    # Don't leak powered-by
    fastcgi_hide_header X-Powered-By;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "same-origin" always;
    add_header X-Download-Options "noopen" always;
    add_header X-Permitted-Cross-Domain-Policies "none" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    client_max_body_size 20M; # Max size of a file that a user can upload

    # Logs
    error_log /var/log/nginx/mbin_error.log;
    access_log /var/log/nginx/mbin_access.log;

    location / {
        proxy_set_header HOST $host;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_pass http://127.0.0.1:8008;
    }

    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:8008$request_uri;
        proxy_read_timeout 24h;
        proxy_http_version 1.1;
        proxy_set_header Connection "";

        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

See also the [Admin guide](./admin_guide.md#nginx) for NGINX setup details.

## Upgrade Docker setup

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

## Clear caches

Clear caches on **running** containers:

```bash
docker compose exec php bin/console cache:clear -n
docker compose exec redis redis-cli
> auth <your_redis_password>
> FLUSHDB
```

## Manual user activation

Activate a user account (bypassing email verification), please change the `username` below:

```bash
docker compose exec php bin/console mbin:user:verify <username> -a
```

## Backup and restore

Backup:

```bash
docker compose exec -it db pg_dump -U kbin kbin > dump.sql
```

Restore:

```bash
docker compose exec -T db psql -U kbin kbin < dump.sql
```

## See also

- [Frequently Asked Questions](/FAQ.md)
