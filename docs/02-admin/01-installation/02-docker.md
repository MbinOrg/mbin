# Docker Installation

## Minimum hardware requirements

- **vCPU:** 4 virtual cores (>= 2GHz, _more is recommended_ on larger instances)
- **RAM:** 6GB (_more is recommended_ for large instances)
- **Storage:** 40GB (_more is recommended_, especially if you have a lot of remote/local magazines and/or have a lot of (local) users)

You can start with a smaller server and add more resources later if you are using a VPS for example.

## System Prerequisites

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

### Environment configuration

Use either the automatic environment setup script _OR_ manually configure the `.env`, `compose.override.yaml`, and OAuth2 keys. Select one of the two options.

> [!TIP]
> Everything configured for your specific instance is in `.env`, `compose.override.yaml`, and `storage/` (assuming you haven't modified anything else). If you'd like to backup, or even completely reset/delete your instance, then these are the files to do so with.

#### Automatic setup script

Run the setup script and pass in a mode (either `prod` or `dev`) and your domain (which can be `localhost` if you plan to just test locally):

```bash
./docker/setup.sh prod mbin.domain.tld
```

> [!NOTE]
> Once the script has been run, you will not be able to run it again, in order to prevent data loss. You can always edit the `.env` and `compose.override.yaml` files manually if you'd like to make changes.

#### Manually configure `.env` and `compose.override.yaml`

Create config files and storage directories:

```bash
cp .env.example_docker .env
touch compose.override.yaml
mkdir -p storage/{caddy_config,caddy_data,media,messenger_logs,oauth,php_logs,postgres,rabbitmq_data,rabbitmq_logs}
```

1. Choose your Redis password, PostgreSQL password, RabbitMQ password, and Mercure password.
2. Place the passwords in the corresponding variables in `.env`.
3. Update the `SERVER_NAME`, `KBIN_DOMAIN` and `KBIN_STORAGE_URL` in `.env`.
4. Update `APP_SECRET` in `.env`, see the note below to generate one.
5. Update `MBIN_USER` in `.env` to match your user and group id (`id -u` & `id -g`).
6. _Optionally_: Use a newer PostgreSQL version. Update/set the `POSTGRES_VERSION` variable in your `.env`.

> [!NOTE]
> To generate a random password or secret, use the following command:
>
> ```bash
> tr -dc A-Za-z0-9 < /dev/urandom | head -c 32 && echo
> ```

##### Configure OAuth2 keys

1. Create an RSA key pair using OpenSSL:

```bash
# If you protect the key with a passphrase, make sure to remember it!
# You will need it later
openssl genrsa -des3 -out ./storage/oauth/private.pem 4096
openssl rsa -in ./storage/oauth/private.pem --outform PEM -pubout -out ./storage/oauth/public.pem
```

2. Generate a random hex string for the OAuth2 encryption key:

```bash
openssl rand -hex 16
```

3. Add the public and private key paths to `.env`:

```env
OAUTH_PRIVATE_KEY=%kernel.project_dir%/config/oauth2/private.pem
OAUTH_PUBLIC_KEY=%kernel.project_dir%/config/oauth2/public.pem
OAUTH_PASSPHRASE=<Your passphrase from above here>
OAUTH_ENCRYPTION_KEY=<Hex string generated in previous step>
```

### Docker image preparation

> [!NOTE]
> If you're using a version of Docker Engine earlier than 23.0, run `export DOCKER_BUILDKIT=1`, prior to building the image. This does not apply to users running Docker Desktop. More info can be found [here](https://docs.docker.com/build/buildkit/#getting-started)

Use the existing Docker image _OR_ build the docker image. Select one of the two options.

#### Build your own Docker image

If you want to build your own image, run (_no_ need to update the `compose.override.yaml` file):

```bash
docker build --no-cache -t mbin -f docker/Dockerfile .
```

#### Use Mbin pre-build image

_OR_ use our pre-build images from [ghcr.io](https://ghcr.io). In this case you need to update the `compose.override.yaml` file:

```bash
nano compose.override.yaml
```

Add the `image` field and set it to `ghcr.io/mbinorg/mbin:latest` for _both_ the `php` and `messenger` services:

```yaml
services:
  php:
    image: ghcr.io/mbinorg/mbin:latest
  messenger:
    image: ghcr.io/mbinorg/mbin:latest
```

### Uploaded media files

Uploaded media files (e.g. photos uploaded by users) will be stored on the host directory `storage/media`. They will be served by the web server in the `php` container as static files.

Make sure `KBIN_STORAGE_URL` in your `.env` configuration file is set to be `https://yourdomain.tld/media`.

You can also serve those media files on another server by mirroring the files at `storage/media` and changing `KBIN_STORAGE_URL` correspondingly.

> [!TIP]
> S3 can also be utilized to store images in the cloud. Just fill in the `S3_` fields in `.env` and Mbin will take care of the rest. See [this page](../03-optional-features/06-s3_storage.md) for more info.

### Running behind a reverse proxy

A reverse proxy is unneeded with this Docker setup, as HTTPS is automatically applied through the built in Caddy server. If you'd like to use a reverse proxy regardless, then you'll need to make a few changes:

1. In `.env`, change your `SERVER_NAME` to `":80"`:

```env
SERVER_NAME=":80"
```

2. In `compose.override.yaml`, add `CADDY_GLOBAL_OPTIONS: auto_https off` to your php service environment:

```yaml
services:
  php:
    environment:
      CADDY_GLOBAL_OPTIONS: auto_https off
```

3. Also in `compose.override.yaml`, add `!override` to your php `ports` to override the current configuration and add your own based on what your reverse proxy needs:

```yaml
services:
  php:
    ports: !override
      - 8080:80
```

In this example, port `8080` will connect to your Mbin server.

4. Make sure your reverse proxy correctly sets the common `X-Forwarded` headers (especially `X-Forwarded-Proto`). This is needed so that both rate limiting works correctly, but especially so that your server can detect its correct outward facing protocol (HTTP vs HTTPS).

> [!WARNING]
> `TRUSTED_PROXIES` in `.env` needs to be a valid value (which is the default) in order for your server to work correctly behind a reverse proxy.

> [!TIP]
> In order to verify your server is correctly detecting it's public protocol (HTTP vs HTTPS), visit `/.well-known/nodeinfo` and look at which protocol is being used in the `href` fields. A public server should always be using HTTPS and not contain port numbers (i.e., `https://DOMAINHERE/`).

## Running the containers

By default `docker compose` will execute the `compose.yaml` and `compose.override.yaml` files.

Run the container in the background (`-d` means detach, but this can also be omitted for testing or debugging purposes):

```bash
docker compose up -d
```

See your running containers via: `docker ps`.

This docker setup comes with automatic HTTPS support. Assuming you have set up your DNS and firewall (allow ports `80` & `443`) configured correctly, then you should be able to access the new instance via your domain.

> [!NOTE]
> If you specified `localhost` as your domain, then a self signed HTTPS certificate is provided and you should be able to access your instance here: [https://localhost](https://localhost).

You can also access the RabbitMQ management UI via [http://localhost:15672](http://localhost:15672).

> [!WARNING]
> Be sure not to forget the [Mbin first setup](../04-running-mbin/01-first_setup.md) instructions in order to create your admin user, `random` magazine, and AP & Push Notification keys.
