# NGINX

We will use NGINX as reverse proxy between the public site and various backend services (static files, PHP and Mercure).

## General NGINX configs

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

## Mbin Nginx Server Block

```bash
sudo nano /etc/nginx/sites-available/mbin.conf
```

With the content:

```nginx
# Map between POST requests on inbox vs the rest
map $request $inboxRequest {
    ~^POST\ \/f\/inbox      1;
    ~^POST\ \/i\/inbox      1;
    ~^POST\ \/m\/.+\/inbox  1;
    ~^POST\ \/u\/.+\/inbox  1;
    default                 0;
}

map $request $regularRequest {
    ~^POST\ \/f\/inbox      0;
    ~^POST\ \/i\/inbox      0;
    ~^POST\ \/m\/.+\/inbox  0;
    ~^POST\ \/u\/.+\/inbox  0;
    default                 1;
}

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
    access_log /var/log/nginx/mbin_access.log if=$regularRequest;
    access_log /var/log/nginx/mbin_inbox.log if=$inboxRequest;

    location / {
        # try to serve file directly, fallback to index.php
        try_files $uri /index.php$is_args$args;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { allow all; access_log off; log_not_found off; }

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

    # Static assets
    location ~* \.(?:css(\.map)?|js(\.map)?|jpe?g|png|tgz|gz|rar|bz2|doc|pdf|ptt|tar|gif|ico|cur|heic|webp|tiff?|mp3|m4a|aac|ogg|midi?|wav|mp4|mov|webm|mpe?g|avi|ogv|flv|wmv|svgz?|ttf|ttc|otf|eot|woff2?)$ {
        expires    30d;
        add_header Access-Control-Allow-Origin "*";
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    # return 404 for all other php files not matching the front controller
    # this prevents access to other php files you don't want to be accessible.
    location ~ \.php$ {
        return 404;
    }

    # Deny dot folders and files, except for the .well-known folder
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> [!TIP]
> If have multiple PHP versions installed. You can switch the PHP version that Nginx is using (`/var/run/php/php-fpm.sock`) via the the following command:
> `sudo update-alternatives --config php-fpm.sock`
>
> Same is true for the PHP CLI command (`/usr/bin/php`), via the following command:
> `sudo update-alternatives --config php`

> [!WARNING]
> If also want to also configure your `www.domain.tld` subdomain; our advise is to use a HTTP 301 redirect from the `www` subdomain towards the root domain. Do _NOT_ try to setup a double instance (you want to _avoid_ that ActivityPub will see `www` as a separate instance). See Nginx example below

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

## Trusted Proxies

If you are using a reverse proxy, you need to configure your trusted proxies to use the `X-Forwarded-For` header. Mbin configured the following trusted headers for you already: `x-forwarded-for`, `x-forwarded-proto`, `x-forwarded-port` and `x-forwarded-prefix`.

Trusted proxies can be configured in the `.env` file (or your `.env.local` file):

```sh
nano /var/www/mbin/.env
```

You can configure a single IP address and/or a range of IP addresses (this configuration should be sufficient if you are running Nginx yourself):

```dotenv
# Change the IP range if needed, this is just an example
TRUSTED_PROXIES=127.0.0.1,192.168.1.0/24
```

Or if the IP address is dynamic, you can set the `REMOTE_ADDR` string which will be replaced at runtime by `$_SERVER['REMOTE_ADDR']`:

```dotenv
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
```

> [!WARNING]
> In this last example be sure that you configure the web server to _not_
> respond to traffic from _any_ clients other than your trusted load balancers
> (eg. within AWS this can be achieved via security groups).

Finally run the `post-upgrade` script to dump the `.env` to the `.env.local.php` and clear any cache:

```sh
./bin/post-upgrade
```

More detailed info can be found at: [Symfony Trusted Proxies docs](https://symfony.com/doc/current/deployment/proxies.html)

## Media reverse proxy

we suggest that you do not use this configuration:

```dotenv
KBIN_STORAGE_URL=https://mbin.domain.tld/media
```

Instead we suggest to use a subdomain for serving your media files:

```dotenv
KBIN_STORAGE_URL=https://media.mbin.domain.tld
```

That way you can let nginx cache media assets and seamlessly switch to an object storage provider later.

```bash
sudo nano /etc/nginx/sites-available/mbin-media.conf
```

```nginx
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=CACHE:10m inactive=7d max_size=10g;

server {
    server_name media.mbin.domain.tld;
    root /var/www/mbin/public/media;

    listen 80;
}
```

Be sure that the `root /path` is correct (maybe you use `/var/www/kbin/public`).

Enable the NGINX site, using a symlink:

```bash
sudo ln -s /etc/nginx/sites-available/mbin-media.conf /etc/nginx/sites-enabled/
```

> [!TIP]
> before reloading nginx in a production environment you can run `nginx -t` to test your configuration.
> If your configuration is faulty and you run `systemctl reload nginx` it will crash the webserver.

Run `systemctl reload nginx` so the site is loaded.
For it to be a usable https site you have to run `certbot --nginx` and select the media domain or supply your certificates manually.

> [!TIP]
> don't forget to enable http2 by adding `http2 on;` after certbot ran (underneath the `listen 443 ssl;` line)
