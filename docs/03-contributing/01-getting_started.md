# Getting started as a developer

There are several ways to get started. Like using the Docker setup or use the development server, which is explained in detail below.

The code is mainly written in PHP using the Symfony framework with Twig templating and a bit of JavaScript & CSS and of course HTML.

## Docker as a dev server

To save yourself much time setting up a development server, you can use our Docker setup instead of a manual configuration:

1. Make sure you are currently in your Mbin directory.
2. Run the auto setup script with `./docker/setup.sh dev localhost` to configure `.env`, `compose.override.yaml`, and `storage/`.
3. Use `docker build -t mbin -f docker/Dockerfile --target dev .` to build the dev Docker image.
4. Run `docker compose up` to start the Docker containers.
5. From here, you should be able to access your server at [https://localhost/](https://localhost/). Any edits to the source files will automatically rebuild your server.
6. Optionally, follow the [Mbin first setup](../02-admin/04-running-mbin/01-first_setup.md) instructions.

> [!NOTE]
> If you'd prefer to manually configure your Docker environment (instead of using the setup script) then follow the manual environment setup steps in the [Docker install guide](../02-admin/01-installation/02-docker.md), but while you're creating `compose.override.yaml`, use the following:
>
> ```yaml
> include:
>   - compose.dev.yaml
> ```

If you'd prefer a development setup without using Docker, then continue on to the next sections.

## Initial setup

Requirements:

- PHP v8.3 or higher
- NodeJS v20 or higher
- Valkey / KeyDB / Redis (pick one)
- PostgreSQL
- _Optionally:_ Mercure
- _Optionally:_ Symfony CLI

First install some generic packages you will need:

```sh
sudo apt update
sudo apt install lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common git valkey-server
```

### Clone the code

With an account on [GitHub](https://github.com) you will be able to [fork this repository](https://github.com/MbinOrg/mbin).

Once you forked the GitHub repository you can clone it locally (our advice is to use SSH to clone repositories from GitHub):

```sh
git clone git-repository-url
```

For example:

```sh
git clone git@github.com:MbinOrg/mbin.git
```

> [!TIP]
> You do not need to fork the GitHub repository if you are member of our Mbin Organisation on GitHub. Just create a new branch right away.

### Prepare PHP

1. Install PHP + additional PHP extensions:

```sh
sudo apt install php8.4 php8.4-common php8.4-fpm php8.4-cli php8.4-amqp php8.4-bcmath php8.4-pgsql php8.4-gd php8.4-curl php8.4-xml php8.4-redis php8.4-mbstring php8.4-zip php8.4-bz2 php8.4-intl php8.4-bcmath -y
```

2. Fine-tune PHP settings:

- Increase execution time in PHP config file: `/etc/php/8.4/fpm/php.ini`:

```ini
max_execution_time = 120
```

- _Optional:_ Increase/set max_nesting_level in `/etc/php/8.4/fpm/conf.d/20-xdebug.ini` (in case you have the `xdebug` extension installed):

```ini
xdebug.max_nesting_level=512
```

3. Restart the PHP-FPM service:

```sh
sudo systemctl restart php8.4-fpm.service
```

## Prepare PostgreSQL DB

1. Install PostgreSQL:

```sh
sudo apt-get install postgresql postgresql-contrib
```

2. Connect to PostgreSQL using the postgres user:

```sh
sudo -u postgres psql
```

3. Create a new `mbin` database user with database:

```sql
sudo -u postgres createuser --createdb --createrole --pwprompt mbin
```

4. If you are using `127.0.0.1` to connect to the PostgreSQL server, edit the following file: `/etc/postgresql/<VERSION>/main/pg_hba.conf` and add:

```conf
local   mbin            mbin                                    md5
```

5. Finally, restart the PostgreSQL server:

```
sudo systemctl restart postgresql
```

### Prepare dotenv file

1. Change to the `mbin` git repository directory (if you weren't there already).
2. Copy the dot env file: `cp .env.example .env`. And let's configure the `.env` file to your needs. Pay attention to the following changes:

```ini
# Set domain to 127.0.0.1:8000
SERVER_NAME=127.0.0.1:8000
KBIN_DOMAIN=127.0.0.1:8000
KBIN_STORAGE_URL=http://127.0.0.1:8000/media

# Valkey/Redis (without password)
REDIS_DNS=redis://127.0.0.1:6379

# Set App configs
APP_ENV=dev
APP_SECRET=427f5e2940e5b2472c1b44b2d06e0525

# Configure PostgreSQL
POSTGRES_DB=mbin
POSTGRES_USER=mbin
# Change your PostgreSQL password for Mbin user
POSTGRES_PASSWORD=<password>

# Set messenger to Doctrine (= PostgresQL DB)
MESSENGER_TRANSPORT_DSN=doctrine://default
```

### Install Symfony CLI tool

1. Install Symfony CLI: `wget https://get.symfony.com/cli/installer -O - | bash`
2. Check the requirements: `symfony check:requirements`

### Fill Database

1. Assuming you are still in the `mbin` directory.
2. Create the database: `php bin/console doctrine:database:create`
3. Create tables and database structure: `php bin/console doctrine:migrations:migrate`

### Fixtures

> [!TIP]
> This fixtures section is optional. Feel free to skip this section.

You might want to load random data to database instead of manually adding magazines, users, posts, comments etc.
To do so, execute:

```sh
php bin/console doctrine:fixtures:load --append --no-debug
```

---

If you have messenger jobs configured, be sure to stop them:

- Docker: `docker compose stop messenger`
- Bare Metal: `supervisorctl stop messenger:*`

If you are using the Docker setup and want to load the fixture, execute: `docker compose exec php bin/console doctrine:fixtures:load --append --no-debug`

Please note, that the command may take some time and data will not be visible during the process, but only after the finish.

- Omit `--append` flag to override data currently stored in the database
- Customize inserted data by editing files inside `src/DataFixtures` directory

## Starting the development server

Prepare the server:

1. Build frontend assets: `npm install && npm run dev`
2. Install dependencies: `composer install`
3. Dump `.env` into `.env.local.php` via: `composer dump-env dev`
4. _Optionally:_ Increase verbosity log level in: `config/packages/monolog.yaml` in the `when@dev` section: `level: debug` (instead of `level: info`),
5. **Important:** clear Symfony cache: `APP_ENV=dev APP_DEBUG=1 php bin/console cache:clear -n`
6. _Optionally:_ clear the Composer cache: `composer clear-cache`

Start the development server:

6. Start Mbin: `symfony server:start`
7. Go to: [http://127.0.0.1:8000](http://127.0.0.1:8000/)

You might want to also follow the [Mbin first setup](../02-admin/04-running-mbin/01-first_setup.md). This explains how to create a user.

This will give you a minimal working frontend with PostgreSQL setup. Keep in mind: this will _not_ start federating.

_Optionally:_ If you want to start federating, you will also need to messenger jobs + RabbitMQ and host your server behind a reverse proxy with valid SSL certificate. Generally speaking, it's **not** required to setup federation for development purposes.

More info: [Contributing guide](https://github.com/MbinOrg/mbin/blob/main/CONTRIBUTING.md), [Admin guide](../02-admin/README.md) and [Symfony Local Web Server](https://symfony.com/doc/current/setup/symfony_server.html)

## Testing

When fixing a bug or implementing a new feature or improvement, we expect that test code will also be included with every delivery of production code. There are three levels of tests that we distinguish between:

- Unit Tests: test a specific unit (SUT), mock external functions/classes/database calls, etc. Unit-tests are fast, isolated and repeatable
- Integration Tests: test larger part of the code, combining multiple units together (classes, services or alike).
- Application Tests: test high-level functionality, APIs or web calls.

For more info read: [Symfony Testing guide](https://symfony.com/doc/current/testing.html).

### Prepare testing

1. First increase execution time in your PHP config file: `/etc/php/8.4/fpm/php.ini`:

```ini
max_execution_time = 120
```

2. _Optional:_ Increase/set max_nesting_level in `/etc/php/8.4/fpm/conf.d/20-xdebug.ini` (in case you have the `xdebug` extension installed):

```ini
xdebug.max_nesting_level=512
```

3. Restart the PHP-FPM service: `sudo systemctl restart php8.4-fpm.service`
4. Copy the dot env file (if you haven't already): `cp .env.example .env`
5. Install composer packages: `composer install --no-scripts`

### Running unit tests

Running the unit tests can be done by executing:

```sh
SYMFONY_DEPRECATIONS_HELPER=disabled ./bin/phpunit tests/Unit
```

### Running integration tests

Our integration tests depend on a database and a caching server (Valkey / KeyDB / Redis).
The database and cache are cleared / dumped every test run.

To start the services in the background:

```sh
docker compose -f docker/tests/compose.yaml up -d
```

Then run the integration test(s):

```sh
SYMFONY_DEPRECATIONS_HELPER=disabled ./bin/phpunit tests/Functional
```

## Linting

For linting see the [linting documentation page](02-linting.md).
