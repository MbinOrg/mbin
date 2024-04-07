# Symfony Messenger (Queues)

The symphony messengers are background workers for a lot of different task, the biggest one being handling all the ActivityPub traffic.  
We have a few different queues:

1. `receive` [RabbitMQ]: everything any remote instance sends to us will first end up in this queue.
   When processing it will be determined what kind of message it is (creation of a thread, a new comment, etc.)
2. `inbox` [RabbitMQ]: messages from `receive` with the determined kind of incoming message will end up here and the necessary actions will be executed.
   This is the place where the thread or comment will actually be created
3. `outbox` [RabbitMQ]: when a user creates a thread or a comment, a message will be created and send to the outbox queue
   to build the ActivityPub object that will be sent to remote instances.
   After the object is built and the inbox addresses of all the remote instances who are interested in the message are gathered,
   we will create a `DeliverMessage` for every one of them, which will be sent to the `deliver` queue
4. `deliver` [RabbitMQ]: Actually sending out the ActivityPub objects to other instances
5. `resolve` [RabbitMQ]: Resolving dependencies or ActivityPub actors.
   For example if your instance gets a like message for a post that is not on your instance a message resolving that dependency will be dispatched to this queue
6. `async` [RabbitMQ]: messages in async are local actions that are relevant to this instance, e.g. creating notifications, fetching embedded images, etc.
7. `old` [RabbitMQ]: the standard messages queue that existed before. This exists solely for compatibility purposes and might be removed later on
8. `failed` [PostgreSQL]: jobs from the other queues that have been retried, but failed. They get retried a few times again, before they end up in
9. `dead` [PostgreSQL]: dead jobs that will not be retried

We need the `dead` queue so that messages that throw a `UnrecoverableMessageHandlingException`, which is used to indicate that a message should not be retried and go straight to the supplied failure queue

## Install RabbitMQ

[RabbitMQ Install](https://www.rabbitmq.com/install-debian.html#apt-quick-start-cloudsmith)

:::note

we assumes you already installed all the prerequisites packages from the "System prerequisites" chapter.

:::

```bash
## Team RabbitMQ's main signing key
curl -1sLf "https://keys.openpgp.org/vks/v1/by-fingerprint/0A9AF2115F4687BD29803A206B73A36E6026DFCA" | sudo gpg --dearmor | sudo tee /usr/share/keyrings/com.rabbitmq.team.gpg > /dev/null
## Community mirror of Cloudsmith: modern Erlang repository
curl -1sLf https://ppa1.novemberain.com/gpg.E495BB49CC4BBE5B.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg > /dev/null
## Community mirror of Cloudsmith: RabbitMQ repository
curl -1sLf https://ppa1.novemberain.com/gpg.9F4587F226208342.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.9F4587F226208342.gpg > /dev/null

## Add apt repositories maintained by Team RabbitMQ
sudo tee /etc/apt/sources.list.d/rabbitmq.list <<EOF
## Provides modern Erlang/OTP releases
##
deb [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main

## Provides RabbitMQ
##
deb [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
EOF

## Update package indices
sudo apt-get update -y

## Install Erlang packages
sudo apt-get install -y erlang-base \
                        erlang-asn1 erlang-crypto erlang-eldap erlang-ftp erlang-inets \
                        erlang-mnesia erlang-os-mon erlang-parsetools erlang-public-key \
                        erlang-runtime-tools erlang-snmp erlang-ssl \
                        erlang-syntax-tools erlang-tftp erlang-tools erlang-xmerl

## Install rabbitmq-server and its dependencies
sudo apt-get install rabbitmq-server -y --fix-missing
```

Now, we will add a new `kbin` user with the correct permissions:

```bash
sudo rabbitmqctl add_user 'kbin' '{!SECRET!!KEY!-16_2-!}'
sudo rabbitmqctl set_permissions -p '/' 'kbin' '.' '.' '.*'
```

Remove the `guest` account:

```bash
sudo rabbitmqctl delete_user 'guest'
```

## Configure Queue Messenger Handler

```bash
cd /var/www/mbin
nano .env
```

```ini
# Use RabbitMQ (recommended for production):
RABBITMQ_PASSWORD=!ChangeThisRabbitPass!
MESSENGER_TRANSPORT_DSN=amqp://kbin:${RABBITMQ_PASSWORD}@127.0.0.1:5672/%2f/messages

# or Redis/KeyDB:
#MESSENGER_TRANSPORT_DSN=redis://${REDIS_PASSWORD}@127.0.0.1:6379/messages
# or PostgreSQL Database (Doctrine):
#MESSENGER_TRANSPORT_DSN=doctrine://default
```


## Setup Supervisor

We use Supervisor to run our background workers, aka. "Messengers".

Install Supervisor:

```bash
sudo apt-get install supervisor
```

Configure the messenger jobs:

```bash
sudo nano /etc/supervisor/conf.d/messenger-worker.conf
```

With the following content:

```ini
[program:messenger]
command=php /var/www/mbin/bin/console messenger:consume scheduler_default old async outbox deliver inbox resolve receive failed --time-limit=3600
user=www-data
numprocs=6
startsecs=0
autostart=true
autorestart=true
startretries=10
process_name=%(program_name)s_%(process_num)02d
```

Save and close the file.

Note: you can increase the number of running messenger jobs if your queue is building up (i.e. more messages are coming in than your messengers can handle)

We also use supervisor for running Mercure job:

```bash
sudo nano /etc/supervisor/conf.d/mercure.conf
```

With the following content:

```ini
[program:mercure]
command=/usr/local/bin/mercure run --config /var/www/mbin/metal/caddy/Caddyfile
process_name=%(program_name)s_%(process_num)s
numprocs=1
environment=MERCURE_PUBLISHER_JWT_KEY="{!SECRET!!KEY!-32_3-!}",MERCURE_SUBSCRIBER_JWT_KEY="{!SECRET!!KEY!-32_3-!}",SERVER_NAME=":3000",HTTP_PORT="3000"
directory=/var/www/mbin/metal/caddy
autostart=true
autorestart=true
startsecs=5
startretries=10
user=www-data
redirect_stderr=false
stdout_syslog=true
```

Save and close the file. Restart supervisor jobs:

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
```
:::hint

If you wish to restart your supervisor jobs in the future, use:

:::

```bash
sudo supervisorctl restart all
```
