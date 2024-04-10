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
