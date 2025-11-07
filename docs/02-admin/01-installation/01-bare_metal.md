# Bare Metal/VM Installation

Below is a step-by-step guide of the process for creating your own Mbin instance from the moment a new VPS/VM is created or directly on bare-metal.  
This is a preliminary outline that will help you launch an instance for your own needs.

This guide is aimed for Debian / Ubuntu distribution servers, but it could run on any modern Linux distro. This guide will however uses the `apt` commands.

> [!NOTE]
> In this document a few services that are specific to the bare metal installation are configured.
> You do need to follow the configuration guide as well. It describes the configuration of services shared between bare metal and docker.

## Minimum hardware requirements

- **vCPU:** 4 virtual cores (>= 2GHz, _more is recommended_ on larger instances)
- **RAM:** 6GB (_more is recommended_ for large instances)
- **Storage:** 40GB (_more is recommended_, especially if you have a lot of remote/local magazines and/or have a lot of (local) users)

You can start with a smaller server and add more resources later if you are using a VPS for example. Our _recommendation_ is to have 12 vCPUs with 32GB of RAM.

## Software Requirements

- Debian 12 or Ubuntu 22.04 LTS or later
- PHP v8.3 or higher
- NodeJS v22 or higher
- Valkey / KeyDB / Redis (pick one)
- PostgreSQL
- Supervisor
- RabbitMQ
- Nginx / OpenResty (pick one)
- _Optionally:_ Mercure

This guide will show you how-to install and configure all of the above. Except for Mercure and Nginx, for Mercure see the [optional features page](../03-optional-features/README.md).

> [!TIP]
> Once the installation is completed, also check out the [additional configuration guides](../02-configuration/README.md) (including the Nginx setup).

## System Prerequisites

Bring your system up-to-date:

```bash
sudo apt-get update && sudo apt-get upgrade -y
```

Install prequirements:

```bash
sudo apt-get install lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common python3-launchpadlib git redis-server postgresql postgresql-contrib nginx acl -y
```

On **Ubuntu 22.04 LTS** or older, prepare latest PHP package repositoy (8.4) by using a Ubuntu PPA (this step is optional for Ubuntu 23.10 or later) via:

```bash
sudo add-apt-repository ppa:ondrej/php -y
```

On **Debian 12** or later, you can install the latest PHP package repository (this step is optional for Debian 13 or later) via:

```bash
sudo apt-get -y install lsb-release ca-certificates curl
sudo curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb
sudo sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
```

Install _PHP 8.4_ with the required additional PHP extensions:

```bash
sudo apt-get update
sudo apt-get install php8.4 php8.4-common php8.4-fpm php8.4-cli php8.4-amqp php8.4-bcmath php8.4-pgsql php8.4-gd php8.4-curl php8.4-xml php8.4-redis php8.4-mbstring php8.4-zip php8.4-bz2 php8.4-intl php8.4-bcmath -y
```

> [!NOTE]
> If you are upgrading to PHP 8.3 from an older version, please re-review the [PHP configuration](#php) section of this guide as existing `ini` settings are NOT automatically copied to new versions. Additionally review which php-fpm version is configured in your Nginx site.

> [!IMPORTANT]
> **Never** even install `xdebug` PHP extension in production environments. Even if you don't enabled it but only installed `xdebug` can give massive performance issues.

Install Composer:

```bash
sudo curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

## Nginx / OpenResty

Mbin bare metal setup requires a reverse proxy called Nginx (or OpenResty) to be installed and configured correctly. This is a requirement for Mbin to work safe, properly and to scale well.

For Nginx/OpenResty setup see the [Nginx configuration](../02-configuration/02-nginx.md).

## Firewall

If you have a firewall installed (or you're behind a NAT), be sure to open port `443` for the web server. As said above, Mbin should run behind a reverse proxy like Nginx or OpenResty.

## Install Node.JS (frontend tools)

1. Prepare & download keyring:

> [!NOTE]
> This assumes you already installed all the prerequisites packages from the "System prerequisites" chapter.

1. Setup the Nodesource repository:

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo bash -
```

3. Install Node.JS:

```bash
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
cd /var/www/mbin
sudo chown mbin:www-data /var/www/mbin
```

## Generate Secrets

> [!NOTE]
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

> [!TIP]
> You might now want to switch to the latest stable release tag instead of using the `main` branch.
> Try: `git checkout v1.7.4` (v1.7.4 might **not** be the latest version: [lookup the latest version](https://github.com/MbinOrg/mbin/releases))

### Create & configure media directory

```bash
cd /var/www/mbin
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

The `.env` file holds a lot of environment variables and is the main point for configuring mbin.
We suggest you place your variables in the `.env.local` file and have a 'clean' default one as the `.env` file.
Each time this documentation talks about the `.env` file be sure to edit the `.env.local` file if you decided to use that.

> In all environments, the following files are loaded if they exist, the latter taking precedence over the former:
>
> - .env contains default values for the environment variables needed by the app
> - .env.local uncommitted file with local overrides

Make a copy of the `.env.example` to `.env` and `.env.local` and edit the `.env.local` file:

```bash
cp .env.example .env
cp .env.example .env.local
nano .env.local
```

#### Service Passwords

Make sure you have substituted all the passwords and configured the basic services in `.env` file.

> [!NOTE]
> The snippet below are to variables inside the `.env` file. Using the keys generated in the section above "Generating Secrets" fill in the values. You should fully review this file to ensure everything is configured correctly.

```ini
REDIS_PASSWORD="{!SECRET!!KEY!-32_1-!}"
APP_SECRET="{!SECRET!!KEY-16_1-!}"
POSTGRES_PASSWORD={!SECRET!!KEY!-32_2-!}
RABBITMQ_PASSWORD="{!SECRET!!KEY!-16_2-!}"
MERCURE_JWT_SECRET="{!SECRET!!KEY!-32_3-!}"
```

#### Other important `.env` configs:

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

### OAuth2 keys for API credential grants

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

See also: [Mbin config files](../02-configuration/01-mbin_config_files.md) for more configuration options.

## Service Configuration

### PHP

Edit some PHP settings within your `php.ini` file:

```bash
sudo nano /etc/php/8.4/fpm/php.ini
```

```ini
; Maximum execution time of each script, in seconds
max_execution_time = 60
; Both max file size and post body size are personal preferences
upload_max_filesize = 12M
post_max_size = 12M
; Remember the memory limit is per child process
memory_limit = 512M
; maximum memory allocated to store the results
realpath_cache_size = 4096K
; save the results for 10 minutes (600 seconds)
realpath_cache_ttl = 600
```

Optionally also enable OPCache for improved performances with PHP (recommended for both fpm and cli ini files):

```ini
opcache.enable = 1
opcache.enable_cli = 1
opcache.preload = /var/www/mbin/config/preload.php
opcache.preload_user = www-data
; Memory consumption (in MBs), personal preference
opcache.memory_consumption = 512
; Internal string buffer (in MBs), personal preference
opcache.interned_strings_buffer = 128
opcache.max_accelerated_files = 100000
opcache.validate_timestamps = 0
; Enable PHP JIT with all optimizations
opcache.jit = 1255
opcache.jit_buffer_size = 500M
```

> [!CAUTION]
> Be aware that activating `opcache.preload` can lead to errors if you run multiple sites
> (because of re-declaring classes).

More info: [Symfony Performance docs](https://symfony.com/doc/current/performance.html)

Edit your PHP `www.conf` file as well, to increase the amount of PHP child processes (optional):

```bash
sudo nano /etc/php/8.4/fpm/pool.d/www.conf
```

With the content (these are personal preferences, adjust to your needs):

```ini
pm = dynamic
pm.max_children = 70
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 10
```

Be sure to restart (or reload) the PHP-FPM service after you applied any changing to the `php.ini` file:

```bash
sudo systemctl restart php8.4-fpm.service
```

### Composer

Setup composer in production mode:

```bash
composer install --no-dev
composer dump-env prod
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
composer clear-cache
```

> [!CAUTION]
> When running Symfony in _development mode_, your instance may _expose sensitive information_ to the public,
> including database credentials, through the debug toolbar and stack traces.
> **NEVER** expose your development instance to the Internet — doing so can lead to serious security risks.

### Caching

You can choose between either Valkey, KeyDB or Redis.

> [!TIP]
> More Valkey/KeyDB/Redis fine-tuning settings can be found in the [Valkey / KeyDB / Redis configuration guide](../02-configuration/05-redis.md).

#### Valkey / KeyDB or Redis

Edit `redis.conf` file (or the corresponding Valkey or KeyDB config file):

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

Create new `mbin` database user, using the password, `{!SECRET!!KEY!-32_2-!}`, you generated earlier:

```bash
sudo -u postgres createuser --createdb --createrole --pwprompt mbin
```

Create tables and database structure:

```bash
cd /var/www/mbin
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

> [!IMPORTANT]
> Check out the [PostgreSQL configuration page](../02-configuration/04-postgresql.md). You should not run the default PostgreSQL configuration in production!

## RabbitMQ

RabbitMQ is a feature rich, multi-protocol messaging and streaming broker, used by Mbin to process outgoing and incoming messages. 

Read also [What is RabbitMQ](../FAQ.md#what-is-rabbitmq) and [Symfony Messenger Queues](../04-running-mbin/04-messenger.md) for more information.

### Install RabbitMQ

See also: [RabbitMQ Install](https://www.rabbitmq.com/install-debian.html#apt-quick-start-cloudsmith).

> [!NOTE]
> This assumes you already installed all the prerequisites packages from the "System prerequisites" chapter.

```bash
## Team RabbitMQ's main signing key
curl -1sLf "https://keys.openpgp.org/vks/v1/by-fingerprint/0A9AF2115F4687BD29803A206B73A36E6026DFCA" | sudo gpg --dearmor | sudo tee /usr/share/keyrings/com.rabbitmq.team.gpg > /dev/null
## Community mirror of Cloudsmith: modern Erlang repository
curl -1sLf https://github.com/rabbitmq/signing-keys/releases/download/3.0/cloudsmith.rabbitmq-erlang.E495BB49CC4BBE5B.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg > /dev/null
## Community mirror of Cloudsmith: RabbitMQ repository
curl -1sLf https://github.com/rabbitmq/signing-keys/releases/download/3.0/cloudsmith.rabbitmq-server.9F4587F226208342.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.9F4587F226208342.gpg > /dev/null

## Add apt repositories maintained by Team RabbitMQ
sudo tee /etc/apt/sources.list.d/rabbitmq.list <<EOF
## Provides modern Erlang/OTP releases
##
deb [arch=amd64 signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main

# another mirror for redundancy
deb [arch=amd64 signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa2.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa2.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main

## Provides RabbitMQ
##
deb [arch=amd64 signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main

# another mirror for redundancy
deb [arch=amd64 signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa2.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa2.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
EOF

## Update package indices
sudo apt-get update -y

## Install Erlang packages
sudo apt-get install -y erlang-base \
                        erlang-asn1 erlang-crypto erlang-eldap erlang-ftp erlang-inets \
                        erlang-mnesia erlang-os-mon erlang-parsetools erlang-public-key \
                        erlang-runtime-tools erlang-snmp erlang-ssl \
                        erlang-syntax-tools erlang-tftp erlang-tools erlang-xmerl

## Install rabbitmq-server and its dependencies
sudo apt-get install rabbitmq-server -y --fix-missing
```

Now, we will add a new `mbin` user with the correct permissions:

```bash
sudo rabbitmqctl add_user 'mbin' '{!SECRET!!KEY!-16_2-!}'
sudo rabbitmqctl set_permissions -p '/' 'mbin' '.' '.' '.*'
```

Remove the `guest` account:

```bash
sudo rabbitmqctl delete_user 'guest'
```

### Configure Queue Messenger Handler

```bash
cd /var/www/mbin
nano .env
```

We do recommend to use RabbitMQ, which is listening on port `5672` by default:

```ini
# Use RabbitMQ (recommended for production):
RABBITMQ_PASSWORD=!ChangeThisRabbitPass!
MESSENGER_TRANSPORT_DSN=amqp://mbin:${RABBITMQ_PASSWORD}@127.0.0.1:5672/%2f/messages

# or Redis/KeyDB:
#MESSENGER_TRANSPORT_DSN=redis://${REDIS_PASSWORD}@127.0.0.1:6379/messages
# or PostgreSQL Database (Doctrine):
#MESSENGER_TRANSPORT_DSN=doctrine://default
```

### Setup Supervisor

We use Supervisor to run our background workers, aka. "Messengers", which are processes that work together with RabbitMQ to consume the actual data.

Install Supervisor:

```bash
sudo apt-get install supervisor
```

Configure the messenger jobs:

```bash
sudo nano /etc/supervisor/conf.d/messenger-worker.conf
```

With the following content:

```ini
[program:messenger]
command=php /var/www/mbin/bin/console messenger:consume scheduler_default old async outbox deliver inbox resolve receive failed --time-limit=3600
user=www-data
numprocs=6
startsecs=0
autostart=true
autorestart=true
startretries=10
process_name=%(program_name)s_%(process_num)02d
```

Save and close the file.

Note: you can increase the number of running messenger jobs if your queue is building up (i.e. more messages are coming in than your messengers can handle)

Save and close the file. Restart supervisor jobs:

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
```

> [!TIP]
> If you wish to restart your supervisor jobs in the future, use:
>
> ```bash
> sudo supervisorctl restart all
> ```
