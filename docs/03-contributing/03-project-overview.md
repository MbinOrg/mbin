# Project Overview

Mbin is a big project with a lot of code. We do not use an existing library to handle ActivityPub requests, 
therefore we have a lot of code to handle that. 
While that is more error-prone it is also a lot more flexible.

## Directory Structure

- `.devcontainer` - Docker containers that are configured to provide a fully featured development environment.
- `.github` - our GitHub specific CI workflows are stored here.
- `assets` - the place for all our frontend code, that includes JavaScript and SCSS.
- `bin` - only the Symfony console, PHPUnit and our `post-upgrade` script are stores here.
- `ci` - Storing our CI/CD helper code / Dockerfiles.
- `config` - the config files for Symfony are stored here.
   - `config/mbin_routes` the HTTP routes to our controllers are defined here.
   - `config/packages` all Symfony add-ons are configured here.
-  `docker` - some docker configs that are partly outdated. The one still in use is in `docker/tests`.
- `docs` - you guessed it our documentation is stored here.
- `LICENSES` - third party licenses.
- `migrations` - all SQL migrations are stored here.
- `public` - this is the publicly accessible directory through the webserver. There should mostly be compiled files in here.
- `src` - that is where our PHP files are stored and the directory you will modify the most files.
    - `src/ActivityPub` - some things that are ActivityPub related and do not fit in another directory.
    - `src/ArgumentValueResolver`
    - `src/Command` - Every command that is executable via the symfone cli (`php bin/console`).
    - `src/Controller` - Every Controller, meaning every HTTP endpoint, belongs in the directory.
    - `src/DataFixtures` - The classes responsible for generating test data.
    - `src/DoctrineExtensions` - Some doctrine extensions, mainly to handle enums.
    - `src/Document`
    - `src/DTO` - **D**ata **T**ransport **O**bjects are exactly that, a form for the data that is transferable (e.g.: via API) .
    - `src/Entity` - The classes to represent the data stored in the database, a.k.a. database entities.
    - `src/Enums` - self-explanatory.
    - `src/Event` - self-explanatory.
    - `src/EventListener` - classes that listens on framework events.
    - `src/EventSubscriber` - classes subscribing to our own events.
    - `src/Exception` - self-explanatory.
    - `src/Factory` - classes that transform objects. Mostly entities to DTOs and ActivityPub objects to JSON.
    - `src/Feed` - The home for our RSS feed provider
    - `src/Form` - All form types belong to here, also other things related to forms.
    - `src/Markdown` - Everything markdown related: converter, extensions, events, etc.
    - `src/Message` - All classes sent to RabbitMQ (messaging queue system), they should always only contain primitives and never objects. 
    - `src/MessageHandler` - Our background workers fetching messages from RabbitMQ, getting the `Message` objects, are stored here
    - `src/PageView` - page views are a collection of criteria to query for a specific view
    - `src/Pagination` - some extensions to the `PagerFanta`
    - `src/Payloads` - some objects passed via request body to controllers
    - `src/Provider` - some OAuth providers are stored here
    - `src/Repository` - the classes used to fetch data from the database
    - `src/Scheduler` - the schedule provider (regularly running tasks)
    - `src/Schema` - some OpenAPI schemas are stored here
    - `src/Security` - everything related to authentication and authorization should be stored here, that includes OAuth providers
    - `src/Service` - every service should be stored here. A service should be something that manipulates data or is checking for visibility, etc.
    - `src/Twig` - the PHP code related to Twig is stored here. That includes runtime extensions and component classes. 
    - `src/Utils` - some general utils
    - `src/Validator`
- `templates` - the Twig folder. All Twig files are stored in here.
- `tests` - everything relating to testing is stored here.
- `translations` - self-explanatory.

## Writing Code

Our linter adds `declare(strict_types=1);` to every class, so the parameter typing has to be correct.
Every class in the `src` directory can be injected in the constructor of a class. Be aware of cyclic dependencies.

We will go over some common things one might want to add and further down we'll explain some concepts that Mbin makes use of.

### Changing the database schema

To change the database schema one does not really need to do much. Change the corresponding `Entity`.
For some info on doctrine, check out [their documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/3.5/reference/basic-mapping.html).

After you have changed the entity, open a terminal and go to the mbin repo and run:

```bash
php bin:console doctrine:migrations:diff
```

This will create a class in the `migrations` directory. It might contain things really not relevant to you, 
so you have to manually check the changes created. 

> [!NOTE]
> The `up` and `down` methods both have to be implemented.

After modifying the migration to your needs, you can either have them be executed by running the `bin/post_upgrade` script
or restarting the docker containers or manually execute them by running:

```bash
php bin/console doctrine:migrations:execute [YOUR MIGRATION HERE]
```

After that your changes should have been applied to the database.

> [!NOTE]
> If your handling enums it is a bit more complicated as doctrine needs to know how to decode it. 

### Adding a controller

Adding a controller is very simple. You just need to add a class to the `src/Controller/` directory 
(and the subdirectory that can be applied) and then extend `AbstractController`. 

If your controller is a only-one-endpoint-controller then you can override the `__invoke` methode, 
but you can also just create a normal methode, that is up to you.

After you've created the controller you have to configure a route from which this controller can be accessed.
For that you have to go into the `config/mbin_routes` directory and pick a `yaml` file which fits your controller
(or create a new one if none of them fit).

Then you just add something like this:

```yaml
your_route_name:
    controller: App\Controller\YourControllerName::yourMethodeName
    path: /path/to/your/controller
    methods: [GET]
```

> [!TIP]
> You can look at other examples in there or look at [Symfony's documentation](https://symfony.com/doc/current/routing.html#creating-routes-in-yaml-xml-or-php-files).

> [!NOTE]
> We do not use the attribute style for defining routes.

Your controller needs to return a response. The most common way to do that is to return a rendered Twig template:

```php
return $this->render('some_template.html.twig')
```

> [!TIP]
> You can also pass parameters/variables to the Twig template so it has access to it.

You also have to think about permissions a user needs to access an endpoint. 
On "normal" controllers we do that by added an `IsGranted` attribute like this:

```php
#[IsGranted('ROLE_USER')]
public function someControllerMethod(): Response
```

The options there are (OAuth has a lot more of them):

1. `ROLE_USER`: a logged-in user, anonymouse access is not allowed
2. `ROLE_MODERATOR`: a global moderator
3. `ROLE_ADMIN`: an instance admin

> [!NOTE]
> There are also so called `Voters` which can determine whether a user has access to specific content, 
> which we mostly use in the API at the moment (the syntax is `#[IsGranted('expression', 'subject')]`).  
> [Symfony documentation](https://symfony.com/doc/current/security/voters.html)

### Adding an API controller

This is much the same as the "normal" controller, except that you extend `BaseApi` (or another class derived from that) 
instead of `AbstractController`.

Additionally, you have to return a `JsonResponse` instead of rendering a Twig template
and declare the correct OpenAPI attributes on your controller methods, so that the OpenAPI definition is generated accordingly. 
To check for that you can visit `/api/docs` on your local instance and check for your method and how it is documented there.

## Explanation of some concepts

In this paragraph we'll explain some of our core concepts and nomenclature we use in our code.

Some Mbin terms:

1. `Entry`: an entry is the database representation of a thread. We use the same object for Threads, Links or images.
2. `Post`: a post is called "Microblog" in the UI. The main differentiator from an `Entry` is the missing `title` property.
3. `Favourite`: we have the `favourite` table which contains all the upvotes of entries and all the likes of posts.
4. `*_votes`: the tables `entry_votes`, `entry_comment_votes`, `post_votes` and `post_comment_votes` contain all the downvotes and **boosts**. 
This is very confusing and will be changed in the future. The `choice` property can either be `1`, `0` or `-1`. 
It is at `0` if the user had voted, but decided to undo that. `1` equals a boost, while `-1` equals a downvote. 
That does of course mean that a user cannot downvote and boost content at the same time. 

### Federation

Federation is generally handled by our `MessageHandler`s in the background.
When talking about federation we generally need to differentiate between incoming/inbound/inbox federation and outgoing/outbound/outbox federation. 
Because of that a lof `MessageHandler`s with the same name exist in an `Inbox` and an `Outbox` directory, doing completely different things.
The name of the message handler is usually the type of activity it handles (see sources in [Federation](./04-about-federation.md)) followed by `Handler`
(e.g.: `AnnounceHandler`, `LikeHandler`, `CreateHandler`, etc.).

Outgoing federation is usually triggered by some event subscriber (e.g.: `UserEditedSubscriber`), which sends a specific `Message`
to the `MessageBusInterface`, meaning (in our case) to RabbitMQ.

In the background (handled by another docker container or supervisor) we have some processes retrieving messages from RabbitMQ to process them.

Inbox federation is triggered by other instances sending activities to an inbox (`InstanceInboxController`, `SharedInboxController`, 
`UserInboxController` or `MagazineInboxController`), which sends a new `ActivityMessage` to RabbitMQ. 
This is then handled by the `ActivityHandler`, which then determines whether it is valid, has a correct signature, etc.
and sends another message to RabbitMQ depending on the type of activity it received.

### The markdown compilation process

Since this process is implemented in a complicated way we are going to explain it here.
The classes relevant here are 

1. `ConvertMarkdown` - the event used to transfer the data to different event subscribers
2. `MarkdownConverter` - the class that needs to be called to convert markdown to html
3. `CacheMarkdownListener` - handling the caching of converted markdown
4. `ConvertMarkdownListener` - the class actually converting the markdown

The important thing is the priority of the registered event listeners, which are:

1. At `-64`: `CacheMarkdownListener::preConvertMarkdown`
2. At `0`: `ConvertMarkdownListener::onConvertMarkdown`
3. At `64`: `CacheMarkdownListener::postConvertMarkdown`

So what is happening here is simply: check if we already cached the result of the request, 
if so: return it, if not then compile it and safe the result to the cache.

### The random magazine

Every Mbin server has to have a magazine with the name `random`. The cause of this is simple: every `Entry` and `Post`
has to have a magazine assigned to it. Since microblog posts coming from other platforms such as Mastodon 
do not necessarily have a magazine associated with them (though they might via mentioning) we have this fallback.

It is not the right way to do it, since the software should just be able to handle content without a magazine, 
but we are not there, yet.

The random magazine has a bit of a special treatment in some places:
1. Nobody from another server can subscribe to it
2. The magazine does not announce anything (you could previously subscribe to it, so this was an additional safeguard
against announcing every incoming microblog to other servers)
3. It cannot be found via webfinger request
