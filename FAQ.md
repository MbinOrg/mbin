# FAQ

See below our Frequently Asked Questions (FAQ). The questions (and corresponding answers) below are in random order.

## What is Mbin?

Mbin is an _open-source federated link aggregration, content rating and discussion_ software that is built on top of _ActivityPub_.

## What is ActivityPub (AP)?

ActivityPub is a open standard protocol that empowers the creation of decentralized social networks, allowing different servers to interact and share content while giving users control over their data.  
It fosters a more user-centric and distributed approach to social networking, promoting interoperability across platforms and safeguarding user privacy and choice.

This protocol is vital for building a more open, inclusive, and user-empowered digital social landscape.

## Where can I find more info about AP?

There exists an official [ActivityPub specification](https://www.w3.org/TR/activitypub/), as well as [several AP extensions](https://codeberg.org/fediverse/fep/) on this specification.

There is also a **very good** [forum post on activitypub.rocks](https://socialhub.activitypub.rocks/t/guide-for-new-activitypub-implementers/479), containing a lot of links and resources to various documentation and information pages.

## How to setup my own Mbin instance?

Visit the [documentation directory](docs) for more information. A bare metal/VM setup is **recommended** at this time, however we do provide a Docker setup as well.

## I have an issue!

You can [join our Matrix community](https://matrix.to/#/#mbin:melroy.org) and ask for help, and/or make an [issue ticket](https://github.com/MbinOrg/mbin/issues) in GitHub if that adds value (always check for duplicates).

See also our [contributing page](CONTRIBUTING.md).

## How can I contribute?

New contributors are always _warmly welcomed_ to join us. The most valuable contributions come from helping with bug fixes and features through Pull Requests.
As well as helping out with [translations](https://hosted.weblate.org/projects/mbin/) and documentation.

Read more on our [contributing page](CONTRIBUTING.md).

Do _not_ forget to [join our Matrix community](https://matrix.to/#/#mbin:melroy.org).

## What is Matrix?

Matrix is an open-standard, decentralized, and federated communication protocol. You can the [download clients for various platforms here](https://matrix.org/ecosystem/clients/).

As a part of our software development and discussions, Matrix is our primary platform.

## What is Mercure?

Mercure is a _real-time communication protocol_ and server that facilitates server-sent _events_ for web applications. It enables _real-time updates_ by allowing clients to subscribe and receiving updates pushed by the server.

Mbin uses Mercure (optionally), on very large instances you might want to consider disabling Mercure whenever it _degrates_ our server performance.

## What is Redis?

Redis is a _persinstent key-value store_, which can help for caching purposes or other storage requirements. We **recommend** to setup Redis when running Mbin, but Redis is optional.

## What is RabbitMQ?

RabbitMQ is an open-source _message broker_ software that facilitates the exchange of messages between different server instances (in our case ActivityPub messages), using queues to store and manage messages.

We highly **recommend** to setup RabbitMQ on your Mbin instance, but RabbitMQ is optional. Failed messages are no longer stored in RabbitMQ, but in PostgreSQL instead (table: `public.messenger_messages`).

## How to clean-up all failed messages?

If you wish to **delete all failed messages** at once, execute the following PostgreSQL query (assuming you're connected to the correct PostgreSQL database):

```sql
DELETE FROM messenger_messages;
```

## Where can I find my logging?

You can find the Mbin logging in the `var/log/` directory from the root folder of the Mbin installation. When running production the file is called `prod.log`, when running development the log file is called `dev.log`.

## Should I run development mode?

**NO!** Try to avoid running development mode when you are hosting our own _public_ instance to the public. Running in development mode can cause sensitive data to be leaked, such as secret keys or passwords (eg. via development console).

That being said, if you are experiencing serious problems with your instance and you are unable to solve it and the `prod.log` file won't help you any further. You can try running in development mode to debug the problem or issue you are having.
