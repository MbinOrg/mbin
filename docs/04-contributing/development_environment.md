There are multiple ways to get started.

If you can run `docker compose` follow the steps for [Docker](#docker), otherwise you can set up your
own [development server](#development-server)

# Docker

Using docker can get you up and running quickly as it spins up containers of all the required components.

**Supported operating systems**

 - Linux

**Requirements**

 - [Docker](https://docs.docker.com/get-docker/)

## Quickstart

The development environment is run with [docker compose].

```shell
# Create a symbolic link to the dev setup that merges and overrides part of compose.yml
ln -nfs compose.dev.yml compose.override.yml
# Bring up all the services and detach from the console
docker compose up -d
```

Once everything has started, you can navigate to http://localhost:8008. Here's a [docker compose cheatsheet]

### Alternatives for getting started

Should you want to edit the `compose.override.yml` file without making changes to `compose.dev.yml`, read further.
Your changes will be ignored by git.

**Use the `include` directive**

For docker versions `>= 2.20.0`, you can [include][docker compose include] other YAML files into a compose file.
This has the benefit of keeping up to date with changes to the `compose.dev.yml` without modifying it.

```yaml
include:
  - compose.dev.yml
```

**Copy the compose.dev.yml**

Instead of linking, copy the `compose.dev.yml` to `compose.override.yml` and make your changes.

The downside of this approach is that changes and fixes to `compose.dev.yml` from version control will not reflect
in your compose setup automatically.

## How it works

MBin depends on multiple services (postgreSQL, redis, a reverse proxy, PHP, ...). Instead of having one monolithic
docker image that includes these services and all their configs, the services each run in their own containers.

There's minimal overlap between most services and their `Dockerfile`s. Here are a few things to know.

### Split compose file

Instead of having one large compose file to cater to every need, there are multiple - the main one being `compose.yml`.
This takes advantage of [merging][docker compose merging]. Merging is done automatically when a `compose.override.yml`
is present, otherwise the list of files has to be passed with `docker compose -f compose.yml -f file1.yml -f file2.yml`.

### php/Dockerfile

[This][php-dockerfile] is the biggest and most complex `Dockerfile` in the repo.
It's a multi-stage file that attempts to make the final image minimal. You can read it, but the most important things
to keep in mind are

- the `base` target only has the minimal common items for the next steps
- for production there are builder targets to build the frontend and backend of mbin
- for development, the `dev` target is minimal as the frontend and backend are built when the services are first run 
- the final target is `prod` which unites the outputs of the builder targets

### Building the frontend and backend

As mentioned [above](#phpdockerfile), the front and backend are built in the `Dockerfile` for production.

In development, this is taken care of at runtime in [compose.dev.yml]. The `messenger` service is the first PHP
service to run, so it installs PHP dependencies and such. The `node` service is only in the dev compose not production
and requires files from the PHP dependencies to successfully build the frontend. It is thus run afterward.


## Frequently executed tasks

### Modifying behavior with a quick turn-around

The entire repository is mounted into the `caddy`, `php`, and `messenger` services.

**PHP code**

Most PHP code changes are picked up immediately after refreshing the page in the browser.

**Other important files**

| file          | services       | how to refresh                                                         |
|---------------|----------------|------------------------------------------------------------------------|
| Caddyfile     | caddy          | `docker compose exec caddy caddy reload --config /etc/caddy/Caddyfile` |
| entrypoint.sh | php, messenger | `docker compose up -d --no-deps $service`                              |


### Debugging PHP (XDebug)

PHP supports remote debugging using [XDebug](https://xdebug.org/). This works by hosting an Xdebug server the php
process can call. 

```mermaid
sequenceDiagram
    participant Browser
    participant rp as Reverse Proxy
    participant php as PHP-FPM
    participant xdebug as XDebug Host

    Browser->>+rp: http://localhost
    rp->>+php: index.php
    php->>+xdebug: :9003
    xdebug->>php: break
    php-->>xdebug: 
    xdebug->>php: continue
    php-->>xdebug: 
    xdebug-->>-php: done
    php-->>-rp: response
    rp-->>-Browser: response
```

**Requirement**

An XDebug server. The XDebug server is often hosted on port 9000 or 9003. Some IDEs (like [PHPStorm]) have it builtin.

**Enabling**

Open [app.dev.ini] and uncomment `;xdebug.start_with_request=yes` by removing the `;`, 
then restart the `php` service using `docker compose restart php`.

Once you navigate to a page, your IDE/editor should get called from `php-fpm` within docker.

#### Special cases

Firewalls can sometimes get in the way of communication between the docker container and the docker host.

**NixOS**

Nixos needs [iptables rules](https://discourse.nixos.org/t/docker-container-not-resolving-to-host/30259/8).

# Development Server

Requirements:

- PHP v8.2
- NodeJS
- Redis
- PostgreSQL
- _Optionally:_ Mercure

---

- Increase execution time in PHP config file: `/etc/php/8.2/fpm/php.ini`:

```ini
max_execution_time = 120
```

- Restart the PHP-FPM service: `sudo systemctl restart php8.2-fpm.service`
- Connect to PostgreSQL using the postgres user:

```bash
sudo -u postgres psql
```

- Create new mbin database user:

```sql
sudo -u postgres createuser --createdb --createrole --pwprompt mbin
```

- Correctly configured `.env` file (`cp .env.example .env`), these are only the changes you need to pay attention to:

```env
# Set domain to 127.0.0.1:8000
SERVER_NAME=127.0.0.1:8000
KBIN_DOMAIN=127.0.0.1:8000
KBIN_STORAGE_URL=http://127.0.0.1:8000/media

#Redis (without password)
REDIS_DNS=redis://127.0.0.1:6379

# Set App configs
APP_ENV=dev
APP_SECRET=427f5e2940e5b2472c1b44b2d06e0525

# Configure PostgreSQL
POSTGRES_DB=mbin
POSTGRES_USER=mbin
POSTGRES_PASSWORD=<password>

# Set messenger to Doctrine (= PostgresQL DB)
MESSENGER_TRANSPORT_DSN=doctrine://default
```

- If you are using `127.0.0.1` to connect to the PostgreSQL server, edit the following file: `/etc/postgresql/<VERSION>/main/pg_hba.conf` and add:

```conf
local   mbin            mbin                                    md5
```

- Restart the PostgreSQL server: `sudo systemctl restart postgresql`
- Create database: `php bin/console doctrine:database:create`
- Create tables and database structure: `php bin/console doctrine:migrations:migrate`
- Build frontend assets: `npm install && npm run dev`

Starting the server:

1. Install Symfony CLI: `wget https://get.symfony.com/cli/installer -O - | bash`
2. Check the requirements: `symfony check:requirements`
3. Install dependencies: `composer install`
4. Dump `.env` into `.env.local.php` via: `composer dump-env dev`
5. _Optionally:_ Increase verbosity log level in: `config/packages/monolog.yaml` in the `when@dev` section: `level: debug` (instead of `level: info`),
6. Clear cache: `APP_ENV=dev APP_DEBUG=1 php bin/console cache:clear -n`
7. Start Mbin: `symfony server:start`
8. Go to: [http://127.0.0.1:8000](http://127.0.0.1:8000/)

This will give you a minimal working frontend with PostgreSQL setup. Keep in mind: this will _not_ start federating, for that you also need to setup Mercure to test the full Mbin setup.

_Optionally:_ you could also setup RabbitMQ, but the Doctrine messenger configuration will be sufficient for local development.

More info: [Contributing guide](./README.md), [Admin guide](../02-admin/README.md) and [Symfony Local Web Server](https://symfony.com/doc/current/setup/symfony_server.html)

[app.dev.ini]: ../../docker/php/conf.d/app.dev.ini
[compose.dev.yml]: ../../compose.dev.yml
[docker compose]: https://docs.docker.com/compose/reference/
[docker compose cheatsheet]: https://devhints.io/docker-compose
[docker compose include]: https://docs.docker.com/compose/compose-file/14-include/
[docker compose merging]: https://docs.docker.com/compose/compose-file/13-merge/
[php-dockerfile]: ../../docker/php/Dockerfile
[PHPStorm]: https://www.jetbrains.com/phpstorm/
