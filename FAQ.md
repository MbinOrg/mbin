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

## How do I know Redis is working?

Execute: `sudo redis-cli ping` expect a PONG back. If it requires authentication, add the following flags: `--askpass` to the `redis-cli` command.

Ensure you do not see any connection errors in your `var/log/prod.log` file.

In the Mbin Admin settings, be sure to also enable Mercure:

![image](https://github.com/MbinOrg/mbin/assets/628926/7a955912-57c1-4d5a-b0bc-4aab6e436cb4)

When you visit your own Mbin instance domain, you can validate whether a connection was successfully established between your browser (client) and Mercure (server), by going to the browser developer toolbar and visit the "Network" tab.

The browser should succesfully connect to the `https://<yourdomain>/.well-known/mercure` URL (thus without any errors). Since it's streaming data, don't expect any response from Mercure.

## How do I know RabbitMQ is working?

Execute: `sudo rabbitmqctl status`, that should provide details about your RabbitMQ instance. The output should also contain information about which plugins are installed, various usages and on which ports it is listening on (eg. `5672` for AMQP protocol).

Ensure you do not see any connection errors in your `var/log/prod-{YYYY-MM-DD}.log` file.

Talking about plugins, we advise to also enable the `rabbitmq_management` plugin by executing:

```sh
sudo rabbitmq-plugins enable rabbitmq_management
```

Let's create a new admin user in RabbitMQ (replace `<user>` and `password` with a username & password you like to use):

```sh
sudo rabbitmqctl add_user <user> <password>
```

Give this new user administrator permissions (`-p /` is the virtual host path of RabbitMQ, which is `/` by default):

```sh
# Again don't forget to change <user> to your username in the lines below
sudo rabbitmqctl set_user_tags <user> administrator
sudo rabbitmqctl set_permissions -p / <user> ".*" ".*" ".*"
```

Now you can open the RabbitMQ management page: (insecure connection!) `http://<server-ip>:15672` with the username and the password provided earlier. [More info can be found here](https://www.rabbitmq.com/management.html#getting-started). See screenshot below of a typical small instance of Mbin running RabbitMQ management interface:

![image](https://github.com/MbinOrg/mbin/assets/628926/ce47213e-13c5-4b57-9fd3-c5b4a64138ef)

## How to clean-up all failed messages?

If you wish to **delete all failed messages** at once, execute the following PostgreSQL query (assuming you're connected to the correct PostgreSQL database):

```sql
DELETE FROM messenger_messages;
```

## Where can I find my logging?

You can find the Mbin logging in the `var/log/` directory from the root folder of the Mbin installation. When running production the file is called `prod-{YYYY-MM-DD}.log`, when running development the log file is called `dev-{YYYY-MM-DD}.log`.

## Should I run development mode?

**NO!** Try to avoid running development mode when you are hosting our own _public_ instance. Running in development mode can cause sensitive data to be leaked, such as secret keys or passwords (eg. via development console). Development mode will log a lot of messages to disk (incl. stacktraces).

That said, if you are _experiencing serious issues_ with your instance which you cannot resolve by looking at the log file (`prod-{YYYY-MM-DD}.log`) or server logs, you can try running in development mode to debug the problem or issue you are having. Enabling development mode **during development** is also very useful.
