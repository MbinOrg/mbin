# Bare Metal/VM Installation

Below is a step-by-step guide of the process for creating your own Mbin instance from the moment a new VPS/VM is created or directly on bare-metal.  
This is a preliminary outline that will help you launch an instance for your own needs.

:::note

Mbin is still in development.

:::

This guide is aimed for Debian / Ubuntu distribution servers, but it could run on any modern Linux distro. This guide will however uses the `apt` commands.

:::note

In this document a few services that are specific to the bare metal installation are configured. 
You do need to follow the configuration guide as well. It describes the configuration of services shared between bare metal and docker.

:::

## Minimum hardware requirements

**CPU:** 2 cores (>2.5 GHz)  
**RAM:** 4GB (more is recommended for large instances)  
**Storage:** 20GB (more is recommended, especially if you have a lot of remote/local magazines and/or have a lot of (local) users)

## System Prerequisites

Bring your system up-to-date:

```bash
sudo apt-get update && sudo apt-get upgrade -y
```

Install prequirements:

```bash
sudo apt-get install lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common python3-launchpadlib git redis-server postgresql postgresql-contrib nginx acl -y
```

On **Ubuntu 22.04 LTS** or older, prepare latest PHP package repositoy (8.2) by using a Ubuntu PPA (this step is optional for Ubuntu 23.10 or later) via:

```bash
sudo add-apt-repository ppa:ondrej/php -y
```

On **Debian 12** or later, you can install the latest PHP package repository (this step is optional for Debian 13 or later) via:

```bash
sudo sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
```

Install PHP 8.2 with some important PHP extensions:

```bash
sudo apt-get update
sudo apt-get install php8.2 php8.2-common php8.2-fpm php8.2-cli php8.2-amqp php8.2-pgsql php8.2-gd php8.2-curl php8.2-xml php8.2-redis php8.2-mbstring php8.2-zip php8.2-bz2 php8.2-intl -y
```

Install Composer:

```bash
sudo curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

## Firewall

If you have a firewall installed (or you're behind a NAT), be sure to open port `443` for the web server. Mbin should run behind a reverse proxy like Nginx.

## Install NodeJS (frontend tools)

1. Prepare & download keyring:

_Note:_ we assumes you already installed all the prerequisites packages from the "System prerequisites" chapter.

```bash
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
```

2. Setup deb repository:

```bash
NODE_MAJOR=20
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list
```

3. Update and install NodeJS:

```bash
sudo apt-get update
sudo apt-get install nodejs -y
```

## Create new user

```bash
sudo adduser mbin
sudo usermod -aG sudo mbin
sudo usermod -aG www-data mbin
sudo su - mbin
```

## Create folder

```bash
sudo mkdir -p /var/www/mbin
sudo chown mbin:www-data /var/www/mbin
```

## Generate Secrets

> **Note**
> This will generate several valid tokens for the Mbin setup, you will need quite a few.

```bash
for counter in {1..2}; do node -e "console.log(require('crypto').randomBytes(16).toString('hex'))"; done && for counter in {1..3}; do node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"; done
```

## First setup steps

### Clone git repository

```bash
cd /var/www/mbin
git clone https://github.com/MbinOrg/mbin.git .
```

### Create & configure media directory

```bash
mkdir public/media
sudo chmod -R 775 public/media
sudo chown -R mbin:www-data public/media
```

### Configure `var` directory

Create & set permissions to the `var` directory (used for cache and log files):

```bash
cd /var/www/mbin
mkdir var

# See also: https://symfony.com/doc/current/setup/file_permissions.html
# if the following commands don't work, try adding `-n` option to `setfacl`
HTTPDUSER=$(ps axo user,comm | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1)

# Set permissions for future files and folders
sudo setfacl -dR -m u:"$HTTPDUSER":rwX -m u:$(whoami):rwX var

# Set permissions on the existing files and folders
sudo setfacl -R -m u:"$HTTPDUSER":rwX -m u:$(whoami):rwX var
```

### The dot env file

Make a copy of the `.env.example` the and edit the `.env` configure file:

```bash
cp .env.example .env
nano .env
```

Make sure you have substituted all the passwords and configured the basic services in `.env` file.

> **Note**
> The snippet below are to variables inside the .env file. Using the keys generated in the section above "Generating Secrets" fill in the values. You should fully review this file to ensure everything is configured correctly.

```ini
REDIS_PASSWORD="{!SECRET!!KEY!-32_1-!}"
APP_SECRET="{!SECRET!!KEY-16_1-!}"
POSTGRES_PASSWORD={!SECRET!!KEY!-32_2-!}
RABBITMQ_PASSWORD="{!SECRET!!KEY!-16_2-!}"
MERCURE_JWT_SECRET="{!SECRET!!KEY!-32_3-!}"
```

Other important `.env` configs:

```ini
# Configure your media URL correctly:
KBIN_STORAGE_URL=https://domain.tld/media

# Ubuntu 22.04 installs PostgreSQL v14 by default, Debian 12 PostgreSQL v15 is the default
POSTGRES_VERSION=14

# Configure email, eg. using SMTP
MAILER_DSN=smtp://127.0.0.1 # When you have a local SMTP server listening
# But if already have Postfix configured, just use sendmail:
MAILER_DSN=sendmail://default
# Or Gmail (%40 = @-sign) use:
MAILER_DSN=gmail+smtp://user%40domain.com:pass@smtp.gmail.com
# Or remote SMTP with TLS on port 587:
MAILER_DSN=smtp://username:password@smtpserver.tld:587?encryption=tls&auth_mode=log
# Or remote SMTP with SSL on port 465:
MAILER_DSN=smtp://username:password@smtpserver.tld:465?encryption=ssl&auth_mode=log
```

OAuth2 keys for API credential grants:

1. Create an RSA key pair using OpenSSL:

```bash
mkdir ./config/oauth2/
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

```ini
OAUTH_PRIVATE_KEY=%kernel.project_dir%/config/oauth2/private.pem
OAUTH_PUBLIC_KEY=%kernel.project_dir%/config/oauth2/public.pem
OAUTH_PASSPHRASE=<Your (optional) passphrase from above here>
OAUTH_ENCRYPTION_KEY=<Hex string generated in previous step>
```

## Service Configuration

### PHP

Edit some PHP settings within your `php.ini` file:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
; Both max file size and post body size are personal preferences
upload_max_filesize = 8M
post_max_size = 8M
; Remember the memory limit is per child process
memory_limit = 256M
; maximum memory allocated to store the results
realpath_cache_size = 4096K
; save the results for 10 minutes (600 seconds)
realpath_cache_ttl = 600
```

Optionally also enable OPCache for improved performances with PHP:

```ini
opcache.enable=1
opcache.enable_cli=1
; Memory consumption (in MBs), personal preference
opcache.memory_consumption=512
; Internal string buffer (in MBs), personal preference
opcache.interned_strings_buffer=128
opcache.max_accelerated_files=100000
; Enable PHP JIT
opcache.jit_buffer_size=500M
```

More info: [Symfony Performance docs](https://symfony.com/doc/current/performance.html)

Edit your PHP `www.conf` file as well, to increase the amount of PHP child processes (optional):

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

With the content (these are personal preferences, adjust to your needs):

```ini
pm = dynamic
pm.max_children = 60
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 10
```

Be sure to restart (or reload) the PHP-FPM service after you applied any changing to the `php.ini` file:

```bash
sudo systemctl restart php8.2-fpm.service
```

### Composer

Choose either production or developer (not both).

#### Composer Production

```bash
composer install --no-dev
composer dump-env prod
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
composer clear-cache
```

#### Composer Development

If you run production already then _skip the steps below_.

:::danger

When running in development mode your instance will make _sensitive information_ available,
such as database credentials, via the debug toolbar and/or stack traces.
**DOT NOT** expose your development instance to the Internet or you will have a bad time.

:::

```bash
composer install
composer dump-env dev
APP_ENV=dev APP_DEBUG=1 php bin/console cache:clear
composer clear-cache
```

### Caching

You can choose between either Redis or KeyDB.

#### Redis

Edit `redis.conf` file:

```bash
sudo nano /etc/redis/redis.conf

# Search on (ctrl + w): requirepass foobared
# Remove the #, change foobared to the new {!SECRET!!KEY!-32_1-!} password, generated earlier

# Search on (ctrl + w): supervised no
# Change no to systemd, considering Ubuntu is using systemd
```

Save and exit (ctrl+x) the file.

Restart Redis:

```bash
sudo systemctl restart redis.service
```

Within your `.env` file set your Redis password:

```ini
REDIS_PASSWORD={!SECRET!!KEY!-32_1-!}
REDIS_DNS=redis://${REDIS_PASSWORD}@$127.0.0.1:6379

# Or if you want to use socket file:
#REDIS_DNS=redis://${REDIS_PASSWORD}/var/run/redis/redis-server.sock
# Or KeyDB socket file:
#REDIS_DNS=redis://${REDIS_PASSWORD}/var/run/keydb/keydb.sock
```

#### KeyDB

[KeyDB](https://github.com/Snapchat/KeyDB) is a fork of Redis. If you wish to use KeyDB instead, that is possible. Do **NOT** run both Redis & KeyDB, just pick one. After KeyDB run on the same default port 6379 (IANA #815344).

Be sure you disabled redis first:

```bash
sudo systemctl stop redis
sudo systemctl disable redis
```

Or even removed Redis: `sudo apt purge redis-server`

For Debian/Ubuntu you can install KeyDB package repository via:

```bash
echo "deb https://download.keydb.dev/open-source-dist $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/keydb.list
sudo wget -O /etc/apt/trusted.gpg.d/keydb.gpg https://download.keydb.dev/open-source-dist/keyring.gpg
sudo apt update
sudo apt install keydb
```

During the install you can choose between different installation methods, I advice to pick: "keydb", which comes with systemd files as well as the CLI tools (eg. `keydb-cli`).

Start & enable the service if it isn't already:

```bash
sudo systemctl start keydb-server
sudo systemctl enable keydb-server
```

Configuration file is located at: `/etc/keydb/keydb.conf`. See also: [config documentation](https://docs.keydb.dev/docs/config-file).  
For example, you can also configure Unix socket files if you wish:

```ini
unixsocket /var/run/keydb/keydb.sock
unixsocketperm 777
```

Optionally, if you want to set a password with KeyDB, _also add_ the following option to the bottom of the file:

```ini
# Replace {!SECRET!!KEY!-32_1-!} with the password generated earlier
requirepass "{!SECRET!!KEY!-32_1-!}"
```

### PostgreSQL (Database)

Create new `kbin` database user (or `mbin` user if you know what you are doing), using the password, `{!SECRET!!KEY!-32_2-!}`, you generated earlier:

```bash
sudo -u postgres createuser --createdb --createrole --pwprompt kbin
```

Create tables and database structure:

```bash
cd /var/www/mbin
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

:::hint

Check out [PostgreSQL tuning](../99-tuning/postgresql.md), you should not run the default PostgreSQL configuration in production.

:::
