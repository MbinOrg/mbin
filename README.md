# Mbin - Fork of /kbin

Mbin is a fork of kbin, community-focused. Feel free to discuss on [Matrix](https://matrix.to/#/#mbin:melroy.org) and to create Pull Requests.

**Important:** Mbin is focused on what the community wants, pull requests can be merged by any repo member. Discussions take place on [Matrix](https://matrix.to/#/#mbin:melroy.org) then _consensus_ has to be reached by the community. If approved by the community, no additional reviews are required on the PR. It's built entirely on trust.

Mbin is a modular, decentralized content aggregator and microblogging platform running on the Fediverse network. It can
communicate with many other ActivityPub services, including Kbin, Mastodon, Lemmy, Pleroma, Peertube. The initiative aims to
promote a free and open internet.

The inspiration came from kbin. Unique Features of Mbin:

- Support of **all** ActivityPub Actor Types (including also "Service" accounts, which are robot accounts)
- Tons of **GUI improvements**
- Various **bug fixes**, additional error checking and more to come soon!
- Improved **admin guide** and setup
- Support for `application/json` Accept request header on all ActivityPub end-points
- Up-to-date PHP (Composer) dependency packages and **security/vulnerability** fixes
  - Enabled: GitHub Security advisories, vulnerability reporting, Dependabot and code scanning
- Improved _code documentation_, making the code easier to understand and contribute
- **Tight integration** with [Mbin Webplate project](https://hosted.weblate.org/projects/mbin/kbin/) for translations (Two way sync)
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
- [Admin Guide](docs/admin_guide.md)
- [Frequently Asked Questions (FAQ)](FAQ.md)
- [Mbin REST API Swagger Docs](https://kbin.melroy.org/api/docs)
- [Mbin ActivityPub Reference](https://fedidevs.org/projects/kbin/)

## Developers

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
