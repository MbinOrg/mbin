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

### Docker image preparation

> [!NOTE]
> If you're using a version of Docker Engine earlier than 23.0, run `export DOCKER_BUILDKIT=1`, prior to building the image. This does not apply to users running Docker Desktop. More info can be found [here](https://docs.docker.com/build/buildkit/#getting-started)

Use the existing Docker image _OR_ build the docker image. Select one of the two options.

#### Build your own Docker image

If you want to build your own image, run (_no_ need to update the `compose.prod.yaml` file):

```bash
docker build --no-cache -t mbin -f docker/Dockerfile  .
```

#### Use Mbin pre-build image

_OR_ use our pre-build images from [ghcr.io](https://ghcr.io). In this case you need to update the `compose.prod.yaml` file:

```bash
nano compose.prod.yaml
```

Find and replace or comment-out the following 4 lines:

```yaml
build:
  dockerfile: docker/Dockerfile
  context: .
  target: prod
```

And instead use the following line on both places (`php` and `messenger` services):

```yaml
image: "ghcr.io/mbinorg/mbin:latest"
```

**Important:** Do _NOT_ forget to change **ALL LINES** in that match to: `image: "ghcr.io/mbinorg/mbin:latest"` in the `compose.prod.yaml` file (should be 2 matches in total).

### Environment configuration

Use either the automatic environment setup script _OR_ manually configure the `.env`, `compose.override.yaml`, and OAuth2 keys. Select one of the two options.

#### Automatic setup

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
cat > compose.override.yaml << EOF
include:
  - compose.prod.yaml
EOF
mkdir -p storage/caddy_data storage/caddy_config storage/media storage/php_logs storage/messenger_logs storage/rabbitmq_data storage/rabbitmq_logs
```

1. Choose your Redis password, PostgreSQL password, RabbitMQ password, and Mercure password.
2. Place the passwords in the corresponding variables in `.env`.
3. Update the `SERVER_NAME`, `KBIN_DOMAIN` and `KBIN_STORAGE_URL` in `.env`.
4. Update `APP_SECRET` in `.env`, generate a new one via: `node -e  "console.log(require('crypto').randomBytes(16).toString('hex'))"`
5. _Optionally_: Use a newer PostgreSQL version. Update/set the `POSTGRES_VERSION` variable in your `.env`.

##### Configure OAuth2 keys

1. Create an RSA key pair using OpenSSL:

```bash
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
OAUTH_PASSPHRASE=<Your passphrase from above here>
OAUTH_ENCRYPTION_KEY=<Hex string generated in previous step>
```

### Running the containers

By default `docker compose` will execute the `compose.yaml` and `compose.override.yaml` files.

Run the container in the background (`-d` means detach, but this can also be omitted for testing or debugging purposes):

```bash
docker compose up -d
```

See your running containers via: `docker ps`.

This docker setup comes with built in automatic HTTPS support. Assuming you have set up your DNS and port forwarding correctly, then you should be able to access the new instance via your domain.

> [!NOTE]
> If you specified `localhost` as your domain, then a self signed HTTPS certificate is provided and you should be able to access your instance here: [https://localhost](https://localhost).

You can also access RabbitMQ management UI via [http://localhost:15672](http://localhost:15672).

### Uploaded media files

Uploaded media files (e.g. photos uploaded by users) will be stored on the host directory `storage/media`. They will be served by the web server in the `php` container as static files.

Make sure `KBIN_STORAGE_URL` in your `.env` configuration file is set to be `https://yourdomain.tld/media`.

You can also serve those media files on another server by mirroring the files at `storage/media` and changing `KBIN_STORAGE_URL` correspondingly.

### Filesystem ACL support

The filesystem ACL is disabled by default, in the `mbin` image. You can set the environment variable `ENABLE_ACL=1` to enable it. Remember that not all filesystems support ACL. This will cause an error if you enable filesystem ACL for such filesystems.

## Run Production

If you created the file `compose.override.yaml` with your configs, running production would be the same command:

```bash
docker compose up -d
```

If you want to deploy your app on a cluster of machines, you can
use [Docker Swarm](https://docs.docker.com/engine/swarm/stack-deploy/), which is compatible with the provided Compose
files.
