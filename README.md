# Mbin

Mbin is a fork of [/kbin](https://codeberg.org/Kbin/kbin-core), community-focused. Feel free to discuss on [Matrix](https://matrix.to/#/#mbin:melroy.org) and to create Pull Requests.

> [!Important]
> Mbin is focused on what the community wants, pull requests can be merged by any repo maintainer (with merge rights in GitHub). Discussions take place on [Matrix](https://matrix.to/#/#mbin:melroy.org) then _consensus_ has to be reached by the community. If approved by the community, only one approval on the PR is required by one of the Mbin maintainers. It's built entirely on trust.

Mbin is a decentralized content aggregator, voting, discussion and microblogging platform running on the fediverse network. It can
communicate with many other ActivityPub services, including Kbin, Mastodon, Lemmy, Pleroma, Peertube. The initiative aims to
promote a free and open internet.

[![Mbin Workflow](https://github.com/MbinOrg/mbin/actions/workflows/action.yaml/badge.svg?branch=main)](https://github.com/MbinOrg/mbin/actions/workflows/action.yaml?query=branch%3Amain)
[![Psalm Security Scan](https://github.com/MbinOrg/mbin/actions/workflows/psalm.yml/badge.svg?branch=main)](https://github.com/MbinOrg/mbin/actions/workflows/psalm.yml?query=branch%3Amain)
[![Translation status](https://hosted.weblate.org/widgets/mbin/-/svg-badge.svg)](https://hosted.weblate.org/engage/mbin/)
[![Matrix](https://img.shields.io/badge/chat-on%20matrix-brightgreen)](https://matrix.to/#/#mbin:melroy.org)

Unique Features of Mbin for server owners & users alike:

- Tons of **[GUI improvements](https://github.com/MbinOrg/mbin/pulls?q=is%3Apr+is%3Amerged+label%3Afrontend)**
- A lot of **[enhancements](https://github.com/MbinOrg/mbin/pulls?q=is%3Apr+is%3Amerged+label%3Aenhancement)**
- Various **[bug fixes](https://github.com/MbinOrg/mbin/pulls?q=is%3Apr+is%3Amerged+label%3Abug)**
- Support of **all** ActivityPub Actor Types (including also "Service" account support; thus support for robot accounts)
- **Up-to-date** PHP packages and **security/vulnerability** issues fixed
- Support for `application/json` Accept request header on all ActivityPub end-points
- Introducing a hosted documentation: [docs.joinmbin.org](https://docs.joinmbin.org)

See also: [all merged PRs](https://github.com/MbinOrg/mbin/pulls?q=is%3Apr+is%3Amerged) or [our releases](https://github.com/MbinOrg/mbin/releases).

For developers:

- Improved [bare metal/VM guide](https://docs.joinmbin.org/admin/installation/bare_metal) and [Docker guide](https://docs.joinmbin.org/admin/installation/docker/)
- [Improved Docker setup](https://github.com/MbinOrg/mbin/pulls?q=is%3Apr+is%3Amerged+label%3Adocker)
- _Developer_ server explained (see "Developers" section down below)
- GitHub Security advisories, vulnerability reporting, [Dependabot](https://github.com/features/security) and [Advanced code scanning](https://docs.github.com/en/code-security/code-scanning/introduction-to-code-scanning/about-code-scanning) enabled. And we run [`local-php-security-checker`](https://github.com/fabpot/local-php-security-checker).
- Improved **code documentation**
- **Tight integration** with [Mbin Weblate project](https://hosted.weblate.org/engage/mbin/) for translations (Two way sync)
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
- [Unofficial magazine for discussions within the fediverse](https://kbin.run/m/Mdev)
- [Translations](https://hosted.weblate.org/engage/mbin/)
- [Contribution guidelines](CONTRIBUTING.md) - please read first, including before opening an issue!

## Collaborators

<!-- readme: collaborators -start -->
<!-- readme: collaborators -end -->

## Contributors

<!-- readme: contributors -start -->
<!-- readme: contributors -end -->

## Getting Started

### Documentation

See [docs.joinmbin.org](https://docs.joinmbin.org)

### Requirements

[See also Symfony requirements](https://symfony.com/doc/current/setup.html#technical-requirements)

- PHP version: 8.2 or higher
- GD or Imagemagick PHP extension
- NGINX / Apache / Caddy
- PostgreSQL
- RabbitMQ
- Redis / KeyDB
- Mercure (optional)

## Languages

Following languages are currently supported/translated:

- Bulgarian
- Chinese
- Dutch
- English
- Esperanto
- French
- German
- Greek
- Italian
- Japanese
- Polish
- Portuguese
- Russian
- Spanish
- Turkish
- Ukrainian

## Credits

- [grumpyDev](https://karab.in/u/grumpyDev): icons, kbin-theme
- [Emma](https://codeberg.org/LItiGiousemMA/Postmill): Postmill
- [Ernest](https://github.com/ernestwisniewski): Kbin

## License

[AGPL-3.0 license](LICENSE)
