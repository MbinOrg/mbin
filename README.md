# Mbin

Mbin is a fork of kbin, community-focused. Feel free to discuss on [Matrix](https://matrix.to/#/#mbin:melroy.org) and to create Pull Requests.

**Important:** Mbin is focused on what the community wants, pull requests can be merged by any repo member. Discussions take place on [Matrix](https://matrix.to/#/#mbin:melroy.org) then _consensus_ has to be reached by the community. If approved by the community, no additional reviews are required on the PR. It's built entirely on trust.

Mbin is a decentralized content aggregator, voting, discussion and microblogging platform running on the fediverse network. It can
communicate with many other ActivityPub services, including Kbin, Mastodon, Lemmy, Pleroma, Peertube. The initiative aims to
promote a free and open internet.

Unique Features of Mbin:

- Support of **all** ActivityPub Actor Types (including also "Service" accounts, which are robot accounts)
- Tons of **GUI improvements**
- Various **bug fixes**, additional error checking and more to come soon!
- Improved **admin guide** and setup
- Support for `application/json` Accept request header on all ActivityPub end-points
- Up-to-date PHP (Composer) dependency packages and **security/vulnerability** fixes
  - Enabled: GitHub Security advisories, vulnerability reporting, Dependabot and code scanning
- Improved _code documentation_, making the code easier to understand and contribute
- **Tight integration** with [Mbin Weblate project](https://hosted.weblate.org/projects/mbin/kbin/) for translations (Two way sync)
- Last but not least, a **community-focus project embracing the Collective Code Construction Contract** (C4). No single maintainer.

## Instances

- [List of instances](https://fedidb.org/software/mbin)
- [Alternative listing of instances](https://mbin.fediverse.observer/list)

![Mbin logo](docs/images/mbin.png)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=MbinOrg/mbin&type=Date)](https://star-history.com/#MbinOrg/mbin&Date)

## Contributing

- [Official repository on GitHub](https://github.com/MbinOrg/mbin)
- [Matrix Space for discussions](https://matrix.to/#/#mbin:melroy.org)
- [Translations](https://hosted.weblate.org/projects/mbin/)
- [Contribution guidelines](CONTRIBUTING.md) - please read first, including before opening an issue!

## Getting Started

### Migrating?

If you want to migrate from Kbin to Mbin (on bare metal), just follow the easy steps below (default branch is `main`):

```bash
cd /var/www/your-instance
git remote set-url origin https://github.com/MbinOrg/mbin.git
git fetch
git checkout main

./bin/post-upgrade
```

Done!

### Requirements

[See also Symfony requirements](https://symfony.com/doc/current/setup.html#technical-requirements)

- PHP version: 8.2 or higher
- GD or Imagemagick PHP extension
- NGINX / Apache / Caddy
- PostgreSQL
- Redis (optional)
- Mercure (optional)
- RabbitMQ (optional)

## Documentation

- [User Guide](docs/user_guide.md)
- [Admin Bare Metal/VM Guide](docs/admin_guide.md)
- [Admin Docker Guide](docs/docker_deployment_guide.md)
- [Frequently Asked Questions (FAQ)](FAQ.md)
- [Mbin REST API Swagger Docs](https://kbin.melroy.org/api/docs)
- [Mbin ActivityPub Reference](https://fedidevs.org/projects/kbin/)

## Developers

### Start development server

Requirements:

- PHP v8.2
- NodeJS + Yarn
- Redis
- PostgreSQL
- _Optionally:_ Mercure

---

- Increase execution time in PHP config file: `/etc/php/8.2/fpm/php.ini`:

```ini
max_execution_time = 60
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
- Build frontend assets: `yarn && yarn build`

Starting the server:

1. Install Symfony CLI: `wget https://get.symfony.com/cli/installer -O - | bash`
2. Check the requirements: `symfony check:requirements`
3. Install depedencies: `composer install`
4. Dump `.env` into `.env.local.php` via: `composer dump-env dev`
5. _Optionally:_ Increase verbosity log level in: `config/packages/monolog.yaml` in the `when@dev` section: `level: debug` (instead of `level: info`),
6. Clear cache: `APP_ENV=dev APP_DEBUG=1 php bin/console cache:clear -n`
7. Start Mbin: `symfony server:start`
8. Go to: [http://127.0.0.1:8000](http://127.0.0.1:8000/)

This will give you a minimal working frontend with PostgreSQL setup. Keep in mind: this will _not_ start federating, for that you also need to setup Mercure to test the full Mbin setup.

_Optionally:_ you could also setup RabbitMQ, but the Doctrine messenger configuration will be sufficient for local development.

More info: [Symfony Local Web Server](https://symfony.com/doc/current/setup/symfony_server.html)

### Linting

Install tooling via:

```sh
composer -d tools install
```

Try to automatically fix linting errors:

```sh
tools/vendor/bin/php-cs-fixer fix
```

## Federation

### Official Documents

- [ActivityPub standard](https://www.w3.org/TR/activitypub/)
- [ActivityPub vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/)
- [Activity Streams](https://www.w3.org/TR/activitystreams-core/)

### Unofficial Sources

- [A highly opinionated guide to learning about ActivityPub](https://tinysubversions.com/notes/reading-activitypub/)
- [ActivityPub as it has been understood](https://flak.tedunangst.com/post/ActivityPub-as-it-has-been-understood)
- [Schema Generator 3: A Step Towards Redecentralizing the Web!](https://dunglas.fr/2021/01/schema-generator-3-a-step-towards-redecentralizing-the-web/)
- [API Platform ActivityPub](https://github.com/api-platform/activity-pub)

## Languages

Following languages are currently supported/translated:

- English
- German
- Greek
- Esperanto
- Spanish
- French
- Italian
- Japanese
- Dutch
- Polish
- Turkish
- Chinese

## Credits

- [grumpyDev](https://karab.in/u/grumpyDev): icons, kbin-theme
- [Ernest](https://codeberg.org/ernest): Kbin

## License

[AGPL-3.0 license](LICENSE)
