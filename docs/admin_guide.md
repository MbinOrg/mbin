# Admin Bare Metal/VM Guide

Below is a step-by-step guide of the process for creating your own Mbin instance from the moment a new VPS/VM is created or directly on bare-metal.  
This is a preliminary outline that will help you launch an instance for your own needs.

For Docker see: [Admin Docker Deployment Guide](./docker_deployment_guide.md).

> **Note**
> Mbin is still in development.

This guide is aimed for Debian / Ubuntu distribution servers, but it could run on any modern Linux distro. This guide will however uses the `apt` commands.

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

```
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

> **Warning**
> When running in development mode your instance will make _sensitive information_ available,
> such as database credentials, via the debug toolbar and/or stack traces.
> **DOT NOT** expose your development instance to the Internet or you will have a bad time.

```bash
composer install
composer dump-env dev
APP_ENV=dev APP_DEBUG=1 php bin/console cache:clear
composer clear-cache
```

### Caching

You can choose between either Redis or Dragonfly.

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
```

#### Dragonfly

[Dragonfly](https://www.dragonflydb.io/) is a drop-in replacement for Redis. If you wish to use Dragonfly instead, that is possible. Do **NOT** run both Redis & Dragonfly, just pick one. After all they run on the same port by default (6379).

Be sure you disabled redis:

```sh
sudo systemctl stop redis
sudo systemctl disable redis
```

Or even removed Redis: `sudo apt purge redis-server`

Dragonfly Debian file can be downloaded via ([Dragonfly also provide a standalone binary](https://www.dragonflydb.io/docs/getting-started/binary#step-1-download-preferred-release)):

```sh
wget https://dragonflydb.gateway.scarf.sh/latest/dragonfly_amd64.deb
```

Install the deb package via:

```sh
sudo apt install ./dragonfly_amd64.deb
```

Because Dragonfly is fully Redis compatible you can optionally still install the `redis-tools` package
(`apt install redis-tools`), if you want to use the `redis-cli` with Dragonfly for example.

Start & enable the service if it isn't already:

```sh
sudo systemctl start dragonfly
sudo systemctl enable dragonfly
```

Configuration file is located at: `/etc/dragonfly/dragonfly.conf`. See also: [Server config documentation](https://www.dragonflydb.io/docs/managing-dragonfly/flags).  
For example you can also configure Unix socket files if you wish.

If you want to set a password with Dragonfly, edit the `sudo nano /etc/dragonfly/dragonfly.conf` file and append the following option at the bottom of the file:

```ini
# Replace {!SECRET!!KEY!-32_1-!} with the password generated earlier
--requirepass={!SECRET!!KEY!-32_1-!}
```

Dragonfly use the same port as Redis and is fully compatible with Redis APIs, so _no_ client-side changes are needed. Unless you use a different port or another socket location.

### PostgreSQL (Database)

Create new `kbin` database user, using the password, `{!SECRET!!KEY!-32_2-!}`, you generated earlier:

```bash
sudo -u postgres createuser --createdb --createrole --pwprompt kbin
```

Create tables and database structure:

```bash
cd /var/www/mbin
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

#### PostgreSQL configuration

For production, you **do want to change** the default PostgreSQL settings (since the default configuration is _not_ recommended).

Edit your PostgreSQL configuration file (assuming you're running PostgreSQL v14):

```bash
sudo nano /etc/postgresql/14/main/postgresql.conf
```

Then adjust the following settings **depending to your server specifications** (the configuration below is a good indication for a server with around 32GB of RAM):

```ini
# Increase max connections
max_connections = 100

# Increase shared buffers
shared_buffers = 8GB
# Enable huge pages (Be sure to check the note down below in order to enable huge pages!)
# This will fail if you didn't configure huge pages under Linux (if you do NOT want to use huge pages, set it to: try instead of: on)
huge_pages = on

# Increase work memory
work_mem = 20MB
# Increase maintenance work memory
maintenance_work_mem = 1GB

# Should be posix under Linux anyway, just to be sure...
dynamic_shared_memory_type = posix

# Increase the number of IO current disk operations (especially useful for SSDs)
effective_io_concurrency = 200

# Increase the number of work processes (do not exceed your number of CPU cores)
# Adjusting this setting, means you should also change:
# max_parallel_workers, max_parallel_maintenance_workers and max_parallel_workers_per_gather
max_worker_processes = 16

# Increase parallel workers per gather
max_parallel_workers_per_gather = 4
max_parallel_maintenance_workers = 4
# Maximum number of work processes that can be used in parallel operations (we set it the same as max_worker_processes)
# You should *not* increase this value more than max_worker_processes
max_parallel_workers = 16

# Write ahead log sizes (unless you expect to write more than 1GB/hour of data in the DB)
max_wal_size = 8GB
min_wal_size = 2GB
checkpoint_completion_target = 0.9

# Query tuning
# Set to 1.1 for SSDs.
# Increase this number (eg. 4.0) if you are running on slow spinning disks
random_page_cost = 1.1

# Increase the cache size, increasing the likelihood of index scans (if we have enough RAM memory)
# Try to aim for: RAM size * 0.8 (on a dedicated server)
effective_cache_size = 25GB
```

**Note:** We try to set `huge_pages` to: `on` in PostgreSQL, in order to make this works you need to [enable huge pages under Linux (click here)](https://www.enterprisedb.com/blog/tuning-debian-ubuntu-postgresql) as well! Please follow that guide. and play around with your kernel configurations.

### Yarn

```bash
cd /var/www/mbin
npm install # Installs all NPM dependencies
npm run build # Builds frontend
```

Make sure you have substituted all the passwords and configured the basic services.

#### Let's Encrypt (TLS)

> **Note**
> The Certbot authors recommend installing through snap as some distros' versions from APT tend to fall out-of-date; see https://eff-certbot.readthedocs.io/en/latest/install.html#snap-recommended for more.

Install Snapd:

```bash
sudo apt-get install snapd
```

Install Certbot:

```bash
sudo snap install core; sudo snap refresh core
sudo snap install --classic certbot
```

Add symlink:

```bash
sudo ln -s /snap/bin/certbot /usr/bin/certbot
```

Follow the prompts to create TLS certificates for your domain(s). If you don't already have NGINX up, you can use standalone mode.

```bash
sudo certbot certonly

# Or if you wish not to use the standalone mode but the Nginx plugin:
sudo certbot --nginx -d domain.tld
```

### NGINX

We will use NGINX as reverse proxy between the public site and various backend services (static files, PHP and Mercure).

#### General NGINX configs

Generate DH parameters (will be used later):

```bash
sudo openssl dhparam -dsaparam -out /etc/nginx/dhparam.pem 4096
```

Set the correct permissions:

```bash
sudo chmod 644 /etc/nginx/dhparam.pem
```

Edit the main NGINX config file: `sudo nano /etc/nginx/nginx.conf` with the following content within the `http {}` section (replace when needed):

```nginx
ssl_protocols TLSv1.2 TLSv1.3; # Requires nginx >= 1.13.0 else only use TLSv1.2
ssl_dhparam /etc/nginx/dhparam.pem;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305;
ssl_prefer_server_ciphers off;
ssl_ecdh_curve secp521r1:secp384r1:secp256k1; # Requires nginx >= 1.1.0

ssl_session_timeout 1d;
ssl_session_cache shared:MozSSL:10m;  # about 40000 sessions
ssl_session_tickets off; # Requires nginx >= 1.5.9

ssl_stapling on; # Requires nginx >= 1.3.7
ssl_stapling_verify on; # Requires nginx => 1.3.7

# This is an example DNS (replace the DNS IPs if you wish)
resolver 1.1.1.1 9.9.9.9 valid=300s;
resolver_timeout 5s;

# Gzip compression
gzip            on;
gzip_disable    msie6;

gzip_vary       on;
gzip_comp_level 3;
gzip_min_length 256;
gzip_buffers    16 8k;
gzip_proxied    any;
gzip_types
        text/css
        text/plain
        text/javascript
        text/cache-manifest
        text/vcard
        text/vnd.rim.location.xloc
        text/vtt
        text/x-component
        text/x-cross-domain-policy
        application/javascript
        application/json
        application/x-javascript
        application/ld+json
        application/xml
        application/xml+rss
        application/xhtml+xml
        application/x-font-ttf
        application/x-font-opentype
        application/vnd.ms-fontobject
        application/manifest+json
        application/rss+xml
        application/atom_xml
        application/vnd.geo+json
        application/x-web-app-manifest+json
        image/svg+xml
        image/x-icon
        image/bmp
        font/opentype;
```

#### Mbin Nginx Server Block

```bash
sudo nano /etc/nginx/sites-available/mbin.conf
```

With the content:

```ini
# Redirect HTTP to HTTPS
server {
    server_name domain.tld;
    listen 80;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name domain.tld;

    root /var/www/mbin/public;

    index index.php;

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
        # try to serve file directly, fallback to app.php
        try_files $uri /index.php$is_args$args;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:3000$request_uri;
        # Increase this time-out if you want clients have a Mercure connection open for longer (eg. 24h)
        proxy_read_timeout 2h;
        proxy_http_version 1.1;
        proxy_set_header Connection "";

        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location ~ ^/index\.php(/|$) {
        default_type application/x-httpd-php;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;

        # Prevents URIs that include the front controller. This will 404:
        # http://domain.tld/index.php/some-path
        # Remove the internal directive to allow URIs like this
        internal;
    }

    # bypass thumbs cache image files
    location ~ ^/media/cache/resolve {
      expires 1M;
      access_log off;
      add_header Cache-Control "public";
      try_files $uri $uri/ /index.php?$query_string;
    }

    # assets, documents, archives, media
    location ~* \.(?:css(\.map)?|js(\.map)?|jpe?g|png|tgz|gz|rar|bz2|doc|pdf|ptt|tar|gif|ico|cur|heic|webp|tiff?|mp3|m4a|aac|ogg|midi?|wav|mp4|mov|webm|mpe?g|avi|ogv|flv|wmv)$ {
        expires    30d;
        add_header Access-Control-Allow-Origin "*";
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    # svg, fonts
    location ~* \.(?:svgz?|ttf|ttc|otf|eot|woff2?)$ {
        expires    30d;
        add_header Access-Control-Allow-Origin "*";
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # return 404 for all other php files not matching the front controller
    # this prevents access to other php files you don't want to be accessible.
    location ~ \.php$ {
        return 404;
    }
}
```

**Important:** If also want to also configure your `www.domain.tld` subdomain; our advise is to use a HTTP 301 redirect from the `www` subdomain towards the root domain. Do _NOT_ try to setup a double instance (you want to _avoid_ that ActivityPub will see `www` as a separate instance). See Nginx example below:

```nginx
# Example of a 301 redirect response for the www subdomain
server {
    listen 80;
    server_name www.domain.tld;
    if ($host = www.domain.tld) {
        return 301 https://domain.tld$request_uri;
    }
}

server {
    listen 443 ssl;
    http2 on;
    server_name www.domain.tld;

    # TLS
    ssl_certificate /etc/letsencrypt/live/domain.tld/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domain.tld/privkey.pem;

    # Don't leak powered-by
    fastcgi_hide_header X-Powered-By;

    return 301 https://domain.tld$request_uri;
}
```

Enable the NGINX site, using a symlink:

```bash
sudo ln -s /etc/nginx/sites-available/mbin.conf /etc/nginx/sites-enabled/
```

Restart (or reload) NGINX:

```bash
sudo systemctl restart nginx
```

### Additional Mbin configuration files

These are additional configuration YAML file changes in the `config` directory.

#### Image redirect response code

Assuming you **are using Nginx** (as described above, with the correct configs), you can reduce the server load by changing the image redirect response code from `302` to `301`, which allows the client to cache the complete response. Edit the following file (from the root directory of Mbin):

```bash
nano config/packages/liip_imagine.yaml
```

And now change: `redirect_response_code: 302` to: `redirect_response_code: 301`. If you are experience image loading issues, validate your Nginx configuration or revert back your changes to `302`.

---

_Hint:_ There are also other configuration files, eg. `config/packages/monolog.yaml` where you can change logging settings if you wish, but this is not required (these defaults are fine for production).

### Symfony Messenger (Queues)

The symphony messengers are background workers for a lot of different task, the biggest one being handling all the ActivityPub traffic.  
We have 4 different queues:

1. `async` (with jobs coming from your local instance, i.e. posting something to a magazine and delivering that to all followers)
2. `async_ap` (with jobs coming from remote instances, i.e. someone posted something to a remote magazine you're subscribed to)
3. `failed` jobs from the first two queues that have been retried, but failed. They get retried a few times again, before they end up in
4. `dead` dead jobs that will not be retried

We need the `dead` queue so that messages that throw a `UnrecoverableMessageHandlingException`, which is used to indicate that a message should not be retried and go straight to the supplied failure queue

#### Install RabbitMQ (Recommended, but optional)

[RabbitMQ Install](https://www.rabbitmq.com/install-debian.html#apt-quick-start-cloudsmith)

_Note:_ we assumes you already installed all the prerequisites packages from the "System prerequisites" chapter.

```bash
## Team RabbitMQ's main signing key
curl -1sLf "https://keys.openpgp.org/vks/v1/by-fingerprint/0A9AF2115F4687BD29803A206B73A36E6026DFCA" | sudo gpg --dearmor | sudo tee /usr/share/keyrings/com.rabbitmq.team.gpg > /dev/null
## Community mirror of Cloudsmith: modern Erlang repository
curl -1sLf https://ppa1.novemberain.com/gpg.E495BB49CC4BBE5B.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg > /dev/null
## Community mirror of Cloudsmith: RabbitMQ repository
curl -1sLf https://ppa1.novemberain.com/gpg.9F4587F226208342.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.9F4587F226208342.gpg > /dev/null

## Add apt repositories maintained by Team RabbitMQ
sudo tee /etc/apt/sources.list.d/rabbitmq.list <<EOF
## Provides modern Erlang/OTP releases
##
deb [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main

## Provides RabbitMQ
##
deb [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
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

Now, we will add a new `kbin` user with the correct permissions:

```bash
sudo rabbitmqctl add_user 'kbin' '{!SECRET!!KEY!-16_2-!}'
sudo rabbitmqctl set_permissions -p '/' 'kbin' '.' '.' '.*'
```

Remove the `guest` account:

```bash
sudo rabbitmqctl delete_user 'guest'
```

#### Configure Queue Messenger Handler

```bash
cd /var/www/mbin
nano .env
```

```ini
# Use RabbitMQ (recommended for production):
RABBITMQ_PASSWORD=!ChangeThisRabbitPass!
MESSENGER_TRANSPORT_DSN=amqp://kbin:${RABBITMQ_PASSWORD}@127.0.0.1:5672/%2f/messages

# or Redis/Dragonfly:
#MESSENGER_TRANSPORT_DSN=redis://${REDIS_PASSWORD}@127.0.0.1:6379/messages
# or PostgreSQL Database (Doctrine):
#MESSENGER_TRANSPORT_DSN=doctrine://default
```

### Mercure

More info: [Mercure Website](https://mercure.rocks/), Mercure is used in Mbin for real-time communication between the server and the clients.

Download and install Mercure (we are using [Caddyserver.com](https://caddyserver.com/download?package=github.com%2Fdunglas%2Fmercure) mirror to download Mercure):

```bash
sudo wget "https://caddyserver.com/api/download?os=linux&arch=amd64&p=github.com%2Fdunglas%2Fmercure%2Fcaddy&idempotency=69982897825265" -O /usr/local/bin/mercure

sudo chmod +x /usr/local/bin/mercure
```

Prepare folder structure with the correct permissions:

```bash
cd /var/www/mbin
mkdir -p metal/caddy
sudo chmod -R 775 metal/caddy
sudo chown -R mbin:www-data metal/caddy
```

[Caddyfile Global Options](https://caddyserver.com/docs/caddyfile/options)

> **Note**
> Caddyfiles: The one provided should work for most people, edit as needed via the previous link. Combination of mercure.conf and Caddyfile

Add new `Caddyfile` file:

```bash
nano metal/caddy/Caddyfile
```

The content of the `Caddyfile`:

```conf
{
        {$GLOBAL_OPTIONS}
        # No SSL needed
        auto_https off
        http_port {$HTTP_PORT}
        persist_config off

        log {
                # DEBUG, INFO, WARN, ERROR, PANIC, and FATAL
                level WARN
                output discard
                output file /var/www/mbin/var/log/mercure.log {
                        roll_size 50MiB
                        roll_keep 3
                }

                format filter {
                        wrap console
                        fields {
                                uri query {
                                        replace authorization REDACTED
                                }
                        }
                }
        }
}

{$SERVER_NAME:localhost}

{$EXTRA_DIRECTIVES}

route {
	mercure {
		# Transport to use (default to Bolt with max 1000 events)
		transport_url {$MERCURE_TRANSPORT_URL:bolt://mercure.db?size=1000}
		# Publisher JWT key
		publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} {env.MERCURE_PUBLISHER_JWT_ALG}
		# Subscriber JWT key
		subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY} {env.MERCURE_SUBSCRIBER_JWT_ALG}
    # Workaround for now
		anonymous
		# Extra directives
		{$MERCURE_EXTRA_DIRECTIVES}
	}

	respond /healthz 200
	respond "Not Found" 404
}
```

Ensure not random formatting errors in the Caddyfile

```bash
mercure fmt metal/caddy/Caddyfile --overwrite
```

Mercure will be configured further in the next section (Supervisor).

### Setup Supervisor

We use Supervisor to run our background workers, aka. "Messengers".

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
command=php /var/www/mbin/bin/console messenger:consume scheduler_default async async_ap failed --time-limit=3600
user=www-data
numprocs=4
startsecs=0
autostart=true
autorestart=true
startretries=10
process_name=%(program_name)s_%(process_num)02d
```

Save and close the file.

Note: you can increase the number of running messenger jobs if your queue is building up (i.e. more messages are coming in than your messengers can handle)

We also use supervisor for running Mercure job:

```bash
sudo nano /etc/supervisor/conf.d/mercure.conf
```

With the following content:

```ini
[program:mercure]
command=/usr/local/bin/mercure run --config /var/www/mbin/metal/caddy/Caddyfile
process_name=%(program_name)s_%(process_num)s
numprocs=1
environment=MERCURE_PUBLISHER_JWT_KEY="{!SECRET!!KEY!-32_3-!}",MERCURE_SUBSCRIBER_JWT_KEY="{!SECRET!!KEY!-32_3-!}",SERVER_NAME=":3000",HTTP_PORT="3000"
directory=/var/www/mbin/metal/caddy
autostart=true
autorestart=true
startsecs=5
startretries=10
user=www-data
redirect_stderr=false
stdout_syslog=true
```

Save and close the file. Restart supervisor jobs:

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
```

_Hint:_ If you wish to restart your supervisor jobs in the future, use:

```bash
sudo supervisorctl restart all
```

### Mbin first setup

Create new admin user (without email verification), please change the `username`, `email` and `password` below:

```bash
php bin/console mbin:user:create <username> <email@example.com> <password>
php bin/console mbin:user:admin <username>
```

```bash
php bin/console mbin:ap:keys:update
```

Next, log in and create a magazine named "random" to which unclassified content from the fediverse will flow.

### Upgrades

If you perform a Mbin upgrade (eg. `git pull`), be aware to _always_ execute the following Bash script:

```bash
./bin/post-upgrade
```

And when needed also execute: `sudo redis-cli FLUSHDB` to get rid of Redis/Dragonfly cache issues. And reload the PHP FPM service if you have OPCache enabled.

### Manual user activation

Activate a user account (bypassing email verification), please change the `username` below:

```bash
php bin/console mbin:user:verify <username> -a
```

### Backup and restore

```bash
PGPASSWORD="YOUR_PASSWORD" pg_dump -U kbin kbin > dump.sql
psql -U kbin kbin < dump.sql
```

### Logs

RabbitMQ:

- `sudo tail -f /var/log/rabbitmq/rabbit@*.log`

Supervisor:

- `sudo tail -f /var/log/supervisor/supervisord.log`

Supervisor jobs (Mercure and Messenger):

- `sudo tail -f /var/log/supervisor/mercure*.log`
- `sudo tail -f /var/log/supervisor/messenger-ap*.log`
- `sudo tail -f /var/log/supervisor/messenger-mbin*.log`

The separate Mercure log:

- `sudo tail -f /var/www/mbin/var/log/mercure.log`

Application Logs (prod or dev logs):

- `tail -f /var/www/mbin/var/log/prod-{YYYY-MM-DD}.log`

Or:

- `tail -f /var/www/mbin/var/log/dev-{YYYY-MM-DD}.log`

Web-server (Nginx):

- `sudo tail -f /var/log/nginx/mbin_access.log`
- `sudo tail -f /var/log/nginx/mbin_error.log`

### Debugging

**Please, check the logs above first.** If you are really stuck, visit to our [Matrix space](https://matrix.to/#/%23mbin:melroy.org), there is a 'General' room and dedicated room for 'Issues/Support'.

Test PostgreSQL connections if using a remote server, same with Redis (or Dragonfly is you are using Dragonfly instead). Ensure no firewall rules blocking are any incoming or out-coming traffic (eg. port on 80 and 443).

### S3 Images storage (optional)

Edit your `.env` file:

```ini
S3_KEY=$AWS_ACCESS_KEY_ID
S3_SECRET=$AWS_SECRET_ACCESS_KEY
S3_BUCKET=bucket-name
# safe default for s3 deployments like minio or single zone ceph/radosgw
S3_REGION=us-east-1
# set if not using aws s3, note that the scheme is also required
S3_ENDPOINT=https://endpoint.domain.tld
S3_VERSION=latest
```

Then edit the: `config/packages/oneup_flysystem.yaml` file:

```yaml
oneup_flysystem:
  adapters:
    default_adapter:
      local:
        location: "%kernel.project_dir%/public/%uploads_dir_name%"

    kbin.s3_adapter:
      awss3v3:
        client: kbin.s3_client
        bucket: "%amazon.s3.bucket%"
        options:
          ACL: public-read

  filesystems:
    public_uploads_filesystem:
      # switch the adapter to s3 adapter
      #adapter: default_adapter
      adapter: kbin.s3_adapter
      alias: League\Flysystem\Filesystem
```

### Image metadata cleaning with `exiftool` (optional)

To use this feature, install `exiftool` (`libimage-exiftool-perl` package for Ubuntu/Debian)
and make sure `exiftool` executable exist and and visible in PATH

Available options in `.env`:

```sh
# available modes: none, sanitize, scrub
# can be set differently for user uploaded and external media
EXIF_CLEAN_MODE_UPLOADED=sanitize
EXIF_CLEAN_MODE_EXTERNAL=none
# path to exiftool binary, leave blank for auto PATH search
EXIF_EXIFTOOL_PATH=
# max execution time for exiftool in seconds, defaults to 10 seconds
EXIF_EXIFTOOL_TIMEOUT=10
```

Available cleaning modes:

- `none`: no metadata cleaning would be done.
- `sanitize`: removes GPS and serial number metadata. this is the default for uploaded images.
- `scrub`: removes most of image metadata save for those needed for proper image rendering
  and XMP IPTC attribution metadata.

### Captcha (optional)

Go to [hcaptcha.com](https://www.hcaptcha.com) and create a free account. Make a sitekey and a secret. Add domain.tld to the sitekey.
Optionally, increase the difficulty threshold. Making it even harder for bots.

Edit your `.env` file:

```ini
KBIN_CAPTCHA_ENABLED=true
HCAPTCHA_SITE_KEY=sitekey
HCAPTCHA_SECRET=secret
```

Then dump-env your configuration file:

```sh
composer dump-env prod
```

or:

```sh
composer dump-env dev
```

Finally, go to the admin panel, settings tab and check "Captcha enabled" and press "Save".

## See also

- [Frequently Asked Questions](../FAQ.md)

## Performance hints

- [Resolve cache images in background](https://symfony.com/bundles/LiipImagineBundle/current/optimizations/resolve-cache-images-in-background.html#symfony-messenger)

## References

- [https://symfony.com/doc/current/setup.html](https://symfony.com/doc/current/setup.html)
- [https://symfony.com/doc/current/deployment.html](https://symfony.com/doc/current/deployment.html)
- [https://symfony.com/doc/current/setup/web_server_configuration.html](https://symfony.com/doc/current/setup/web_server_configuration.html)
- [https://symfony.com/doc/current/messenger.html#deploying-to-production](https://symfony.com/doc/current/messenger.html#deploying-to-production)
- [https://codingstories.net/how-to/how-to-install-and-use-mercure/](https://codingstories.net/how-to/how-to-install-and-use-mercure/)
