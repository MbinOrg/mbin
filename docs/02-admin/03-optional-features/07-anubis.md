# Anubis setup for Mbin

### Why?

Anubis is a bot programm trying to block bots by presenting proof-of-work challengers to the user. A normal browser will just solve them (provided JavaScript is enabled on it) whilst AI scrapers will most likely just accept the challenge as the response.

Ths simple answer is: because it is better than just blocking anonymous access to Mbin.

### How does it work?

See the official [How Anubis works](https://anubis.techaro.lol/docs/design/how-anubis-works) web page.

# Bare metal with Nginx

## External links

- [Installation (Official documentation)](https://anubis.techaro.lol/docs/admin/installation)
- [Native installation (Official documentation)](https://anubis.techaro.lol/docs/admin/native-install)
- [Best practices for unix socket (on Anubis GitHub.com project)](https://github.com/TecharoHQ/anubis/discussions/541)

## Anubis setup

### Installation

Download the package for your system from [the most recent release on GitHub](https://github.com/TecharoHQ/anubis/releases) and install the package via your package manager:

- `deb`: `sudo apt install ./anubis-$VERSION-$ARCH.deb`
- `rpm`: `sudo dnf -y install ./anubis-$VERSION.$ARCH.rpm`

### Configuration

Then create the environment file `/etc/anubis/mbin.env` with the following content:

```dotenv
BIND=/run/anubis/mbin.sock
BIND_NETWORK=unix
SOCKET_MODE=0666
DIFFICULTY=4
METRICS_BIND=:4673
SERVE_ROBOTS_TXT=0
TARGET=unix:///run/nginx/mbin.sock
POLICY_FNAME=/etc/anubis/mbin.botPolicies.yaml
```

Copy the content from [default bot policy](https://github.com/TecharoHQ/anubis/blob/main/data/botPolicies.yaml) to `/etc/anubis/mbin.botPolicies.yaml`.

In the `bots` section of the `mbin.botPolicies.yaml` file, prepend this (has to be in front of the other rules):

```yaml
  - name: mbin-activity-pub
    headers_regex:
      Accept: application\/activity\+json|application\/ld\+json
    action: ALLOW
  - name: mbin-api
    headers_regex:
      Accept: application\/json
    action: ALLOW
  - name: mbin-rss
    headers_regex:
      Accept: application\/rss\+xml
    action: ALLOW
  - name: nodeinfo
    path_regex: ^\/nodeinfo\/.*$
    action: ALLOW
```

To explicitly allow all API, RSS and ActivityPub requests. You should also switch the store backend to something different from the default in-memory one. If you want to use a local bbolt db ([see alternatives](https://anubis.techaro.lol/docs/admin/policies#storage-backends)) change the `store` section to the following (in `mbin.botPolicies.yaml`):

```yaml
store:
  backend: bbolt
  parameters:
    path: /opt/anubis/mbin.bdb
```

Adjust the `thresholds` section to match this (the only difference is that the `preact` type of challenge is removed):

```yaml
thresholds:
  # By default Anubis ships with the following thresholds:
  - name: minimal-suspicion # This client is likely fine, its soul is lighter than a feather
    expression: weight <= 0 # a feather weighs zero units
    action: ALLOW # Allow the traffic through
  # For clients that had some weight reduced through custom rules, give them a
  # lightweight challenge.
  - name: mild-suspicion
    expression:
      all:
        - weight > 0
        - weight < 10
    action: CHALLENGE
    challenge:
      # https://anubis.techaro.lol/docs/admin/configuration/challenges/metarefresh
      algorithm: metarefresh
      difficulty: 1
      report_as: 1
  # For clients that are browser-like but have either gained points from custom rules or
  # report as a standard browser.
  - name: moderate-suspicion
    expression:
      all:
        - weight >= 10
        - weight < 30
    action: CHALLENGE
    challenge:
      # https://anubis.techaro.lol/docs/admin/configuration/challenges/proof-of-work
      algorithm: fast
      difficulty: 2 # two leading zeros, very fast for most clients
      report_as: 2
  # For clients that are browser like and have gained many points from custom rules
  - name: extreme-suspicion
    expression: weight >= 30
    action: CHALLENGE
    challenge:
      # https://anubis.techaro.lol/docs/admin/configuration/challenges/proof-of-work
      algorithm: fast
      difficulty: 4
      report_as: 4
```

The default config includes a few snippets that requires a subscription. To avoid warn messages you should comment-out everything that "Requires a subscription to Thoth to use" (just search for it in the file).

For Anubis to be able to access the socket, that we will use later, we will have to change the service file (`/usr/lib/systemd/system/anubis@.service`) and set the user anubis is being executed by to `www-data`:

1. Remove: `DynamicUser=yes`
2. Add: `User=www-data`

There are some paths that have to be created and then owned by `www-data`:

- `/opt/anubis/`
- `/run/anubis/`
- `/run/nginx/`

### Starting it

Then start Anubis, via:

```bash
sudo systemctl enable --now anubis@mbin.service
```

Test to make sure Anibus is running using the `curl` command:

```bash
curl http://localhost:4673/metrics
```

If you need to restart Anubis, just run:

```bash
sudo systemctl restart anubis@mbin.service
```

## Nginx preparations

Create an nginx upstream to anubis ([anubis docs](https://anubis.techaro.lol/docs/admin/environments/nginx)):
```nginx
upstream anubis {
  # Make sure this matches the values you set for `BIND` and `BIND_NETWORK`.
  # If this does not match, your services will not be protected by Anubis.

  # Try Anubis first over a UNIX socket
  server unix:/run/anubis/mbin.sock;
  #server 127.0.0.1:8923;

  # Optional: fall back to serving the websites directly. This allows your
  # websites to be resilient against Anubis failing, at the risk of exposing
  # them to the raw internet without protection. This is a tradeoff and can
  # be worth it in some edge cases.
  #server unix:/run/nginx.sock backup;
}
```

You can just put it in `/etc/nginx/conf.d/anubis.conf` for example and the default Nginx configuration will then import this file.

## Change nginx mbin.conf

Now we have to change the Nginx configuration that is serving Mbin. We will use the default config as an example.

### Short Explainer Version

Without Anubis:

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

    root /var/www/mbin/public;

    location / {
        # try to serve file directly, fallback to index.php
        try_files $uri /index.php$is_args$args;
    }
}
```

With Anubis:

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

    location / {
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Http-Version $server_protocol;
        proxy_pass http://anubis;
    }
}

server {
    listen unix:/run/nginx/mbin.sock;
    server_name domain.tld;
    root /var/www/mbin/public;

    location / {
        # try to serve file directly, fallback to index.php
        try_files $uri /index.php$is_args$args;
        
        # lie to Symfony that the request is an HTTPS one, so it generates HTTPS URLs
        fastcgi_param SERVER_PORT "443";
        fastcgi_param HTTPS "on";
    }
}
```

As you can see instead of serving Mbin directly we proxy it through the Anubis service. Anubis is then going to decide whether to call the UNIX socket that the actual Mbin site is served over or if it presents a challenge to the client (or straight up denying it).

In the actual Mbin call we lie to Symfony that the request is coming from port 443 (`fastcgi_param SERVER_PORT`) and the https scheme (`fastcgi_param HTTPS`).
The reason is that it will otherwise generate HTTP URLs which are incompatible with some other fediverse software, like Lemmy.

### The long one

```nginx
upstream mercure {
    server 127.0.0.1:3000;
    keepalive 10;
}

# Map instance requests vs the rest
map "$http_accept:$request" $mbinInstanceRequest {
    ~^.*:GET\ \/.well-known\/.+                                                                       1;
    ~^.*:GET\ \/nodeinfo\/.+                                                                          1;
    ~^.*:GET\ \/i\/actor                                                                              1;
    ~^.*:POST\ \/i\/inbox                                                                             1;
    ~^.*:POST\ \/i\/outbox                                                                            1;
    ~^.*:POST\ \/f\/inbox                                                                             1;
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:GET\ \/               1;
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:GET\ \/f\/object\/.+  1;
    default                                                                                           0;
}

# Map user requests vs the rest
map "$http_accept:$request" $mbinUserRequest {
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:GET\ \/u\/.+   1;
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:POST\ \/u\/.+  1;
    default                                                                                    0;
}

# Map magazine requests vs the rest
map "$http_accept:$request" $mbinMagazineRequest {
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:GET\ \/m\/.+   1;
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:POST\ \/m\/.+  1;
    default                                                                                    0;
}

# Miscellaneous requests
map "$http_accept:$request" $mbinMiscRequest {
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:GET\ \/reports\/.+  1;
    ~^(?:application\/activity\+json|application\/ld\+json|application\/json).*:GET\ \/message\/.+  1;
    ~^.*:GET\ \/contexts\..+                                                                        1;
    default                                                                                         0;
}

# Determine if a request should go into the regular log
map "$mbinInstanceRequest$mbinUserRequest$mbinMagazineRequest$mbinMiscRequest" $mbinRegularRequest {
    0000    1; # Regular requests
    default 0; # Other requests
}

map $mbinRegularRequest $mbin_limit_key {
    0 "";
    1 $binary_remote_addr;
}

# Two stage rate limit (10 MB zone): 5 requests/second limit (=second stage)
limit_req_zone $mbin_limit_key zone=mbin_limit:10m rate=5r/s;

# Redirect HTTP to HTTPS
server {
    server_name domain.tld;
    listen 80;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name domain.tld;    
    
    # uncomment for troubleshooting purposes
    #access_log /var/log/nginx/anubis_mbin_access.log combined;


    location / {
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Http-Version $server_protocol;
        proxy_pass http://anubis;
    }
}

server {
    listen unix:/run/nginx/mbin.sock;
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

    # Two stage rate limit
    limit_req zone=mbin_limit burst=300 delay=200;

    # Error log (if you want you can add "warn" at the end of error_log to also log warnings)
    error_log /var/log/nginx/mbin_error.log;

    # Access logs
    access_log /var/log/nginx/mbin_access.log combined if=$mbinRegularRequest;
    access_log /var/log/nginx/mbin_instance.log combined if=$mbinInstanceRequest buffer=32k flush=5m;
    access_log /var/log/nginx/mbin_user.log combined if=$mbinUserRequest buffer=32k flush=5m;
    access_log /var/log/nginx/mbin_magazine.log combined if=$mbinMagazineRequest buffer=32k flush=5m;
    access_log /var/log/nginx/mbin_misc.log combined if=$mbinMiscRequest buffer=32k flush=5m;

    open_file_cache          max=1000 inactive=20s;
    open_file_cache_valid    60s;
    open_file_cache_min_uses 2;
    open_file_cache_errors   on;

    location / {
        # try to serve file directly, fallback to index.php
        try_files $uri /index.php$is_args$args;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { allow all; access_log off; log_not_found off; }

    location /.well-known/mercure {
        proxy_pass http://mercure$request_uri;
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
        
        # lie to Symfony that the request is an HTTPS one, so it generates HTTPS URLs
        fastcgi_param SERVER_PORT "443";
        fastcgi_param HTTPS "on";

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

To test whether Mbin correctly uses the HTTPS scheme, you can run this command (replaced with you URL and username):

```bash
curl --header "Accept: application/activity+json" https://example.mbin/u/admin | jq
```

The `| jq` part outputs formatted json which should make this easier to see. There should not be any `http://` URLs in this output.  

### Take it live

To start routing the traffic through Anubis Nginx has to be restarted (not just reloaded), because of the new socket that needs to be created. But before we do that we should check the config for validity:

```bash
nginx -t
```

If `nginx -t` runs successfully and the Anubis service is also running without any issues:

```bash
systemctl status anubis@mbin.service
```

Then you finally restart Nginx with:

```bash
systemctl restart nginx
```

If you reload the Mbin website, you should see the Anubis page for checking your browser.

### Troubleshooting

To get the logs of anubis:

```bash
journalctl -ru anubis@mbin.service
```

In the Nginx config for Mbin, you can uncomment the access log line to see the access logs for the Anubis upstream. If you combine that with changing the status codes in the Anubis policy (just open the policy and search for `status_codes`) this is a good way to check whether RSS, API and ActivityPub requests still make it through.
