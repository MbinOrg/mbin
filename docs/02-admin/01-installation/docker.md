# Docker Installation

> [!NOTE]
> Docker installation is currently not advised for production use. Try the [Bare Metal installation](./bare_metal.md)
> instead.

> [!IMPORTANT]  
> If you were already using docker in production, please see the [migration guide](#migration-guide) for updates

## System Requirements

- Docker Engine
- Docker Compose V2

> [!WARNING]  
> `docker-compose` is **not** supported!

### Docker Install

The most convenient way to install docker is using an
official [convenience script](https://docs.docker.com/engine/install/ubuntu/#install-using-the-convenience-script)
provided at [get.docker.com](https://get.docker.com/):

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
```

Alternatively, you can follow the official [Docker install documentation](https://docs.docker.com/engine/install/) for
your platform.

Once Docker is installed on your system, it is recommended to create a `docker` group and add it to your user:

```bash
sudo groupadd docker
sudo usermod -aG docker $USER
```

## Mbin Installation

First clone the git repository:

```bash
git clone https://github.com/MbinOrg/mbin.git
cd mbin
```

For a test "production" instance, all you have to do is

- [Copy example configuration](#copy-the-example-configuration)
    - Replace `mbin.domain.tdl` with localhost in the `.env` file
- [Make compose override files](#make-override-files)
- [Start the instance](#start-the-instance)

For a full production instance, you'll have to follow the steps below.

### Configuration

The configuration is held in a `.env` file. It contains information to connect the components to each other (passwords,
service names, ...), where certain things should end up (uploaded files and such), the domain name of your instance,
and more.

`docker compose` references it in nearly every service.

#### Copy the example configuration

`.env.example_docker` contains an example configuration that with very a few tweaks can have you up and running.

```shell
cp .env.example_docker .env
```

#### Configure `.env`

Below is a table with env variables that are **mandatory** to update

| Variable              | Purpose                                                                                         |
|-----------------------|-------------------------------------------------------------------------------------------------|
| APP_SECRET            | Salt passwords and other secrets. Generate a random text at least 16 characters long            |
| KBIN_DOMAIN           | The postgres user's password                                                                    |
| KBIN_STORAGE_URL      | Where the uploaded media will reachable from externally. See [doc below](#uploaded-media-files) |
| POSTGRES_PASSWORD     | The postgres user's password                                                                    |
| RABBITMQ_DEFAULT_PASS | Used to connect to RabbitMQ                                                                     |
| REDIS_PASSWORD        | Used to connect to Redis                                                                        |
| SERVER_NAME           | Forces server to accept requests only to this domain. **Must have `www:80`**                    |

These are optional but recommended to update

| Variable           | Purpose                                           |
|--------------------|---------------------------------------------------|
| MERCURE_JWT_SECRET | Used to connect to the optional mercure service   |
| POSTGRES_VERSION   | Ensure you're running the latest postgres version |

> [!IMPORTANT]
> Ensure the `HTTPS` environmental variable is set to `TRUE` in `compose.override.yml` for the `php`, `messenger`,
> and `messenger_ap` containers **if your environment is using a valid certificate behind a reverse proxy**. This is
> likely true for most production environments and is required for proper federation, that is, this will ensure the
> webfinger responses include `https:` in the URLs generated.

#### Configure OAuth2 keys (optional)

OAuth is used by 3rd party app developers. Without these, they will not be able to connect to the server.

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

### Compose override files

`docker compose` allows overriding a compose configuration by merging `compose.yml` and `compose.override.yml`.
The latter is ignored by git, which allows you to make modifications to the services without making changes to version
controlled files.

Create a **compose.override.yml** with these contents

```yaml
include:
  - compose.prod.yml
  - compose.prod.override.yml
```

And an empty `compose.prod.override.yml`.

docker compose will load these files in order and [merge][docker compose merging] sequentially
(last file is most significant):

- `docker.compose.yml`
- `docker.prod.yml`
- `docker.prod.override.yml` (ignored by git)

### Docker image preparation

You have two options for the docker images:

- [Build your own](#build-our-own-docker-image)
- [Use prebuilt images](#use-mbin-prebuilt-images)

#### Build our own Docker image

> ![WARNING]
> Building your own image will use the code you currently checked out!
> Beware that updates to a running instance might break it. Read the release notes first!

If you want to build our own image, run (_no_ need to update the compose files):

```bash
docker compose build --no-cache
```

#### Use Mbin prebuilt images

There are prebuilt images from [ghcr.io](https://ghcr.io) which can speed up deployment. Should you want to use them
In this case you need to update the `compose.prod.override.yml` file with:

```yaml
services:
  www:
    image: "ghcr.io/mbinorg/mbin:latest-caddy"
    pull_policy: never
  php:
    image: "ghcr.io/mbinorg/mbin:latest"
    pull_policy: never
  messenger:
    image: "ghcr.io/mbinorg/mbin:latest"
    pull_policy: never
```

> ![NOTE]
> You can replace `latest` with a version number e.g `1.0.0`

### Running the containers

Run the services in the background (`-d` means detach, but this can also be omitted for testing or debugging purposes):

```bash
# Go to the docker directory within the git repo
cd docker

# Starts the containers
docker compose up -d
```

See your running services via: `docker compose ps`.

Then, you should be able to access the new instance via [http://localhost](http://localhost:8008).
You can also access RabbitMQ management UI via [http://localhost:15672](http://localhost:15672).

## Notes

### Uploaded media files

Uploaded media files (e.g. photos uploaded by users) will be stored on the host directory `storage/mbin/public/media`
by the `php` container and served by the Caddy web server in the `www` container as static files.

Make sure `KBIN_STORAGE_URL` in your `.env` configuration file is set to be `https://yourdomain.tld/media`
(assuming you setup Nginx with SSL certificate by now).

You can also serve those media files on another server by mirroring the files at `storage/mbin/public/media` and
changing `KBIN_STORAGE_URL` correspondingly.

### Filesystem ACL support

The filesystem ACL is disabled by default, in the `mbin` image. You can set the environment variable `ENABLE_ACL=1` to
enable it. Remember that not all filesystems support ACL. This will cause an error if you enable filesystem ACL for such
filesystems.

### Mbin NGINX Server Block

> ![WARNING]
> This is not up to date! PRs for a valid nginx service and config welcome!

NGINX reverse proxy example for the Mbin Docker instance:

```nginx
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

## Migration Guide

For admins using docker already, as of 2024-08-19, the docker configuration has changed. Read this guide to be sure
you're up to date. The major change is where media files are stored.

Previously media files were stored at `docker/storage/media`. They will now be stored in `docker/storage/mbin/public/media`.
The easiest way to migrate them is by running these commands as root (`sudo`)

```shell
mkdir -p docker/storage/www/public
# Copy to have the previous files as a backup
cp -r docker/storage/media docker/storage/www/public
```

You should then be set all set.

[docker compose merging]: https://docs.docker.com/compose/compose-file/13-merge/
