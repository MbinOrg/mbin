<p align="center">
    <img src="docs/images/mbin.png" alt="Mbin logo" width="400">
</p>
<p align="center">
  <a href="https://github.com/MbinOrg/mbin/actions/workflows/action.yaml?query=branch%3Amain"><img src="https://github.com/MbinOrg/mbin/actions/workflows/action.yaml/badge.svg?branch=main" alt="GitHub Actions Workflow"></a>
  <a href="https://github.com/MbinOrg/mbin/actions/workflows/psalm.yml?query=branch%3Amain"><img src="https://github.com/MbinOrg/mbin/actions/workflows/psalm.yml/badge.svg?branch=main" alt="Psalm Security Scan"></a>
  <a href="https://hosted.weblate.org/engage/mbin/"><img src="https://hosted.weblate.org/widgets/mbin/-/svg-badge.svg" alt="Translation status"></a>
  <a href="https://matrix.to/#/#mbin:melroy.org"><img src="https://img.shields.io/badge/chat-on%20matrix-brightgreen" alt="Matrix chat"></a>
  <a href="https://github.com/MbinOrg/mbin/blob/main/LICENSE"><img src="https://img.shields.io/badge/AGPL%203.0-license-blue" alt="License"></a>
</p>

## Introduction

Mbin is a decentralized content aggregator, voting, discussion, and microblogging platform running on the fediverse. It can
communicate with various ActivityPub services, including but not limited to: Mastodon, Lemmy, Pixelfed, Pleroma, and PeerTube.

Mbin is a fork and continuation of [/kbin](https://codeberg.org/Kbin/kbin-core), but community-focused. Feel free to chat on [Matrix](https://matrix.to/#/#mbin:melroy.org). Pull requests are always welcome.

> [!Important]
> Mbin is focused on what the community wants. Pull requests can be merged by any repo maintainer with merge rights in GitHub. Discussions take place on [Matrix](https://matrix.to/#/#mbin:melroy.org) then _consensus_ has to be reached by the community.

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
- _Developer_ server explained (see [Development Server documentation here](https://docs.joinmbin.org/contributing/development_server) )
- GitHub Security advisories, vulnerability reporting, [Dependabot](https://github.com/features/security) and [Advanced code scanning](https://docs.github.com/en/code-security/code-scanning/introduction-to-code-scanning/about-code-scanning) enabled. And we run `composer audit`.
- Improved **code documentation**
- **Tight integration** with [Mbin Weblate project](https://hosted.weblate.org/engage/mbin/) for translations (Two way sync)
- Last but not least, a **community-focus project embracing the [Collective Code Construction Contract](./C4.md)** (C4). No single maintainer.

## Instances

- [List of instances](https://joinmbin.org/servers)
- [Alternative list of instances at fedidb.org](https://fedidb.org/software/mbin)
- [Alternative list of instances at fediverse.observer](https://mbin.fediverse.observer/list)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=MbinOrg/mbin&type=Date)](https://star-history.com/#MbinOrg/mbin&Date)

## Contributing

- [Official repository on GitHub](https://github.com/MbinOrg/mbin)
- [Matrix Space for discussions](https://matrix.to/#/#mbin:melroy.org)
- [Translations](https://hosted.weblate.org/engage/mbin/)
- [Contribution guidelines](docs/03-contributing) - please read first, including before opening an issue!

## Magazines

Unofficial magazines:

- [@mbinmeta@gehirneimer.de](https://gehirneimer.de/m/mbinmeta)
- [@updates@kbin.melroy.org](https://kbin.melroy.org/m/updates)
- [@AskMbin@fedia.io](https://fedia.io/m/AskMbin)

## Contributors

<!-- readme: contributors -start -->
<table>
	<tbody>
		<tr>
            <td align="center">
                <a href="https://github.com/ernestwisniewski">
                    <img src="https://avatars.githubusercontent.com/u/10058784?v=4" width="100;" alt="ernestwisniewski"/>
                    <br />
                    <sub><b>Ernest</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/melroy89">
                    <img src="https://avatars.githubusercontent.com/u/628926?v=4" width="100;" alt="melroy89"/>
                    <br />
                    <sub><b>Melroy van den Berg</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/BentiGorlich">
                    <img src="https://avatars.githubusercontent.com/u/25664458?v=4" width="100;" alt="BentiGorlich"/>
                    <br />
                    <sub><b>BentiGorlich</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/e-five256">
                    <img src="https://avatars.githubusercontent.com/u/146029455?v=4" width="100;" alt="e-five256"/>
                    <br />
                    <sub><b>e-five</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/weblate">
                    <img src="https://avatars.githubusercontent.com/u/1607653?v=4" width="100;" alt="weblate"/>
                    <br />
                    <sub><b>Weblate (bot)</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/asdfzdfj">
                    <img src="https://avatars.githubusercontent.com/u/20770492?v=4" width="100;" alt="asdfzdfj"/>
                    <br />
                    <sub><b>asdfzdfj</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/SzymonKaminski">
                    <img src="https://avatars.githubusercontent.com/u/8536735?v=4" width="100;" alt="SzymonKaminski"/>
                    <br />
                    <sub><b>SzymonKaminski</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/cooperaj">
                    <img src="https://avatars.githubusercontent.com/u/400210?v=4" width="100;" alt="cooperaj"/>
                    <br />
                    <sub><b>Adam Cooper</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/simonrcodrington">
                    <img src="https://avatars.githubusercontent.com/u/12083338?v=4" width="100;" alt="simonrcodrington"/>
                    <br />
                    <sub><b>Simon Codrington</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/kkoyung">
                    <img src="https://avatars.githubusercontent.com/u/11942650?v=4" width="100;" alt="kkoyung"/>
                    <br />
                    <sub><b>Kingsley Yung</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/TheVillageGuy">
                    <img src="https://avatars.githubusercontent.com/u/47496248?v=4" width="100;" alt="TheVillageGuy"/>
                    <br />
                    <sub><b>TheVillageGuy</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/danielpervan">
                    <img src="https://avatars.githubusercontent.com/u/5121830?v=4" width="100;" alt="danielpervan"/>
                    <br />
                    <sub><b>Daniel Pervan</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/garrettw">
                    <img src="https://avatars.githubusercontent.com/u/84885?v=4" width="100;" alt="garrettw"/>
                    <br />
                    <sub><b>Garrett W.</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/Ahrotahn">
                    <img src="https://avatars.githubusercontent.com/u/40727284?v=4" width="100;" alt="Ahrotahn"/>
                    <br />
                    <sub><b>Ahrotahn</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/GauthierPLM">
                    <img src="https://avatars.githubusercontent.com/u/2579741?v=4" width="100;" alt="GauthierPLM"/>
                    <br />
                    <sub><b>Gauthier POGAM--LE MONTAGNER</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/CocoPoops">
                    <img src="https://avatars.githubusercontent.com/u/7891055?v=4" width="100;" alt="CocoPoops"/>
                    <br />
                    <sub><b>CocoPoops</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/jwr1">
                    <img src="https://avatars.githubusercontent.com/u/47087725?v=4" width="100;" alt="jwr1"/>
                    <br />
                    <sub><b>John Wesley</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/thepaperpilot">
                    <img src="https://avatars.githubusercontent.com/u/3683148?v=4" width="100;" alt="thepaperpilot"/>
                    <br />
                    <sub><b>Anthony Lawn</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/chall8908">
                    <img src="https://avatars.githubusercontent.com/u/315948?v=4" width="100;" alt="chall8908"/>
                    <br />
                    <sub><b>Chris Hall</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/andrewmoise">
                    <img src="https://avatars.githubusercontent.com/u/8404538?v=4" width="100;" alt="andrewmoise"/>
                    <br />
                    <sub><b>andrewmoise</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/piotr-sikora-v">
                    <img src="https://avatars.githubusercontent.com/u/1295000?v=4" width="100;" alt="piotr-sikora-v"/>
                    <br />
                    <sub><b>Piotr Sikora</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/ryanmonsen">
                    <img src="https://avatars.githubusercontent.com/u/55466117?v=4" width="100;" alt="ryanmonsen"/>
                    <br />
                    <sub><b>ryanmonsen</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/drupol">
                    <img src="https://avatars.githubusercontent.com/u/252042?v=4" width="100;" alt="drupol"/>
                    <br />
                    <sub><b>Pol Dellaiera</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/MakaryGo">
                    <img src="https://avatars.githubusercontent.com/u/24472656?v=4" width="100;" alt="MakaryGo"/>
                    <br />
                    <sub><b>Makary</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/cavebob">
                    <img src="https://avatars.githubusercontent.com/u/75441692?v=4" width="100;" alt="cavebob"/>
                    <br />
                    <sub><b>cavebob</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/vpzomtrrfrt">
                    <img src="https://avatars.githubusercontent.com/u/3528358?v=4" width="100;" alt="vpzomtrrfrt"/>
                    <br />
                    <sub><b>vpzomtrrfrt</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/lilfade">
                    <img src="https://avatars.githubusercontent.com/u/4168401?v=4" width="100;" alt="lilfade"/>
                    <br />
                    <sub><b>Bryson</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/comradekingu">
                    <img src="https://avatars.githubusercontent.com/u/13802408?v=4" width="100;" alt="comradekingu"/>
                    <br />
                    <sub><b>Allan Nordhøy</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/CSDUMMI">
                    <img src="https://avatars.githubusercontent.com/u/31551856?v=4" width="100;" alt="CSDUMMI"/>
                    <br />
                    <sub><b>CSDUMMI</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/MHLoppy">
                    <img src="https://avatars.githubusercontent.com/u/12670674?v=4" width="100;" alt="MHLoppy"/>
                    <br />
                    <sub><b>Mark Heath</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/robertolopezlopez">
                    <img src="https://avatars.githubusercontent.com/u/1519467?v=4" width="100;" alt="robertolopezlopez"/>
                    <br />
                    <sub><b>Roberto López López</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/privacyguard">
                    <img src="https://avatars.githubusercontent.com/u/92675882?v=4" width="100;" alt="privacyguard"/>
                    <br />
                    <sub><b>privacyguard</b></sub>
                </a>
            </td>
		</tr>
	<tbody>
</table>
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
- Valkey / KeyDB / Redis
- Mercure (optional)

## Languages

Following languages are currently supported/translated:

- Bulgarian
- Catalan
- Chinese
- Danish
- Dutch
- English
- Esperanto
- Filipino
- French
- Galician
- German
- Greek
- Italian
- Japanese
- Polish
- Portuguese
- Portuguese (Brazil)
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
