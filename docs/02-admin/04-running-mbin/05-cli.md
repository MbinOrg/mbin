# CLI

When you are the server administrator of the Mbin instance and you have access to the terminal / shell (via SSH for example), Mbin provides you several console commands.

> [!WARNING]
> We assume you are in the root directory of the Mbin instance (eg. `/var/www/mbin`),
> this is the directory location where you want to execute console commands listed below. 

> [!WARNING]
> Run the commands as the correct user. In case you used the `mbin` user during setup,
> switch to the mbin user fist via: `sudo -u mbin bash`
> (or if you used the `www-data` user during setup: `sudo -u www-data bash`).
> This prevent potential unwanted file permission issues.

## Getting started

List all available console commands, execute:

```bash
php bin/console
```

In the next chapters the focus is on the `mbin` section of the `bin/console` console commands and go into more detail.

## User Management

### User-Create

This command allows you to create user, optionally granting administrator or global moderator privileges.

Usage:

```bash
php bin/console mbin:user:create [-r|--remove] [--admin] [--moderator] <username> <email> <password>
```

Arguments:
- `username`: the username that should be created.
- `email`: the email for the user, keep in mind that this command will automatically verify the created user, so no email will be sent.
- `password`: the password for the user.

Options:
- `-r|--remove`: purge the user from the database, **without notifying the rest of the fediverse about it**. 
  If you want the rest of the fediverse notified please use the `mbin:user:delete` command instead.
- `--admin`: make the created user an admin.
- `--moderator`: make the created user a global moderator.

### User-Admin

This command allows you to grant administrator privileges to the user.

Usage:

```bash
php bin/console mbin:user:admin [-r|--remove] <username>
```

Arguments:
- `username`: the username whose rights are to be modified.

Options:
- `-r|--remove`: instead of granting privileges, remove them.

### User-delete
This command will delete the supplied user and notify the fediverse about it. This is an asynchronous job, 
so you will not see the change immediately.

Usage:

```bash
php bin/console mbin:user:delete <user>
```

Arguments:
- `user`: the username of the user.

### User-Moderator
This command allows you to grant global moderator privileges to the user.

Usage:

```bash
php bin/console mbin:user:moderator [-r|--remove] <username>
```

Arguments:
- `username`: the username whose rights are to be modified.

Options:
- `-r|--remove`: instead of granting privileges, remove them.

### User-Password
This command allows you to manually set or reset a users' password.

Usage:

```bash
php bin/console mbin:user:password <username> <password>
```

Arguments:
- `username`: the username whose password should be changed.
- `password`: the password to change to.

### User-Verify
This command allows you to manually activate or deactivate a user, bypassing email verification requirement.

Usage:

```bash
php bin/console mbin:user:verify [-a|--activate] [-d|--deactivate] <username>
```

Arguments:
- `username`: the user to activate (verify) or deactivate (remove verification).

Options:
- `-a|--activate`: Activate user, bypass email verification.
- `-d|--deactivate`: Deactivate user, require email (re)verification.

> [!NOTE] 
> If neither `--activate` nor `--deactivate` are provided, the current verification status will be returned


### Rotate users private keys

> [!WARNING]
> After running this command it can take up to 24 hours for other instances to update their stored public keys.
> In this timeframe federation might be impacted by this, 
> as those services cannot successfully verify the identity of your users.
> Please inform your users about this when you're running this command.

This command allows you to rotate the private keys of your users with which the activities sent by them are authenticated.
If private keys have been leaked you should rotate the private keys to avoid the potential for impersonation.

Usage:

```bash
php bin/console mbin:user:private-keys:rotate [-a|--all-local-users] [-r|--revert] [<username>]
```

Arguments:
- `username`: the single user for which this command should be executed (not required when using the `-a` / `--all-local-users` option, see below)

Options:
- `-a|--all-local-users`: Rotate private keys of all local users
- `-r|--revert`: revert to the old private and public keys

### User-Unsub

> [!NOTE]
> This command is old and should probably not be used

Removes all followers from a user.

Usage:

```bash
php bin/console mbin:user:unsub <username>
```

Arguments:
- `username`: the user from which to remove all local followers.

### Fix user duplicates

This command allows you to fix duplicate usernames. There is a unique index on the usernames, but it is case-sensitive.
This command will go through all the users with duplicate case-insensitive usernames,
where the username is not part of the public id (meaning the original URL) and update them from the remote server.
After that it will go through the rest of the duplicates and ask you whether you want to merge matching pairs.

Usage:

```bash
php bin/console mbin:users:fix-duplicates [--dry-run]
```

Options:
- `--dry-run`: don't change anything in the DB


## Magazine Management

### Magazine-Create

This command allows you to create, delete and purge magazines.

Usage:

```bash
php bin/console mbin:magazine:create [-o|--owner OWNER] [-r|--remove] [--purge] [--restricted] [-t|--title TITLE] [-d|--description DESCRIPTION] <name>
```

Arguments:
- `name`: the name of the magazine that is part of the URL

Options:
- `-o|--owner OWNER`: makes the supplied username the owner of the newly created magazine. 
  If this is omitted the admin account will be the owner of the magazine.
- `--restricted`: create the magazine with posting restricted to the moderators of this magazine.
- `-r|--remove`: instead of creating the magazine, remove it (not notifying the rest of the fediverse).
- `--purge`: completely remove the magazine from the db (not notifying the rest of the fediverse).
  If this and `--remove` are supplied, `--remove` has precedence over this.
- `-t|--title TITLE`: makes the supplied string the title of the magazine (aka. the display name).
- `-d|--description DESCRIPTION`: makes the supplied string the description of the magazine.

### Magazine-Sub

This command allows to subscribe a user to a magazine.

Usage:

```bash
php bin/console mbin:magazine:sub [-u|--unsub] <magazine> <username>
```

Arguments:
- `magazine`: the magazine name to subscribe the user to.
- `username`: the user that should be subscribed to the magazine.

Options:
- `-u|--unsub`: instead of subscribing to the magazine, unsubscribe the user from the magazine.

### Magazine-Unsub

Remove all the subscribers from a magazine.

Usage:

```bash
php bin/console mbin:magazine:unsub <magazine>
```

Arguments:
- `magazine`: the magazine name from which to remove all the subscribers.

## Direct Messages

### Remove-and-Ban

Search for direct messages using the body input parameter. List all the found matches, and ask for permission to continue.

If you agree to continue, *all* the sender users will be **banned** and *all* the direct messages will be **removed**!

> [!WARNING]
> This action cannot be undone (once you confirmed with `yes`)!

Usage:

```bash
php bin/console mbin:messages:remove_and_ban "<body>"
```

Arguments:
- `body`: the direct message body to search for.

## Post Management

### Entries-Move

> [!WARNING]
> This command should not be used, as none of the changes will be federated.

This command allows you to move entries to a new magazine based on their tag.

Usage:

```bash
php bin/console mbin:entries:move <magazine> <tag>
```

Arguments:
- `magazine`: the magazine to which the entries should be moved
- `tag`: the (hash)tag based on which the entries should be moved 

### Posts-Move

> [!WARNING]
> This command should not be used, as none of the changes will be federated.

This command allows you to move posts to a new magazine based on their tag.

Usage:

```bash
php bin/console mbin:posts:move <magazine> <tag>
```

Arguments:
- `magazine`: the magazine to which the posts should be moved
- `tag`: the (hash)tag based on which the posts should be moved

### Posts-Magazine

> [!WARNING]
> This command should not be used. Posts are automatically assigned to a magazine based on their tag.

This command will assign magazines to posts based on their tags.

Usage:

```bash
php bin/console mbin:posts:magazines
```

## Activity Pub

### Actor update

> [!NOTE]
> This command will trigger **asynchronous** updates of remote users or magazines

This command will allow you to update remote actor (user/magazine) info.

Usage:

```bash
php bin/console mbin:actor:update [--users] [--magazines] [--force] [<user>]
```

Arguments:
- `user`: the username to dispatch an update for. 

Options:
- `--users`: if this options is provided up to 10,000 remote users ordered by their last update time will be updated
- `--magazines`: if this options is provided up to 10,000 remote magazines ordered by their last update time will be updated

### ActivityPub resource import

> [!NOTE]
> This command will trigger an **asynchronous** import

This command allows you to import an AP resource.

Usage:

```bash
php bin/console mbin:ap:import <url>
```

Arguments:
- `url`: the "id" of the ActivityPub object to import

## Images

### Remove cached remote media

This command allows you to remove the cached file of remote media, **without** deleting the reference.
You can run this command as a cron job to only keep cached media from the last 30 days for example.

> [!TIP]
> If a thread or microblog is opened without a local cache of the attached image existing, the image will be downloaded again.
> Once an image is downloaded again, it will not get deleted for the number of days you set as a parameter.

> [!NOTE]
> User avatars and covers and magazine icons and banners are not affected by this command, 
> only images from threads, microblogs and comments.

Usage:

```bash
php bin/console mbin:images:remove-remote [--days|-d] [--batch-size] [--dry-run]
```

Options:
- `--days`|`-d`: the number of days of media you want to keep. Everything older than the amount of days will be deleted
- `--batch-size` (default `10000`): the number of images to retrieve per query from the DB. A higher number means less queries, but higher memory usage.
- `--dry-run`: if set, no images will be deleted

### Refresh the meta data of stored images

This command allows you to refresh the filesize of the stored media, as well as the status.
If an image is no longer present on storage this command adjusts it in the DB.

Usage:

```bash
php bin/console mbin:images:refresh-meta [--batch-size] [--dry-run]
```

Options:
- `--batch-size` (default `10000`): the number of images to retrieve per query from the DB. A higher number means less queries, but higher memory usage.
- `--dry-run`: if set, no metadata will be changed

### Remove old federated images

This command allows you to remove old federated images, without removing the content.  
The image delete command works in batches, by default it will remove 800 images for each type.

The image(s) will be removed from the database as well as from disk / storage.

> [!WARNING]
> This action cannot be undone!

Usage:

```bash
php bin/console mbin:images:delete
```

Arguments:
- `type`: type of images that will get deleted, either: `all` (except for user images), `threads`, `thread_comments`, `posts`, `post_comments` or `users`. (default: `all`)
- `monthsAgo`: Delete images older than given months are getting deleted (default: `12`)

Options:
- `--noActivity`: delete images that doesn't have recorded activity. Like comments, updates and/or boosts. (default: `false`)
- `--batchSize`: the number of images to delete for each type at a time. (default: `800`)

### Remove orphaned media

This command iterates over your media filesystem and deletes all files that do not appear in the database.

```bash
php bin/console mbin:images:remove-orphaned
```

Options:
- `--ignored-paths=IGNORED-PATHS`: A comma seperated list of paths to be ignored in this process. If the path starts with one of the supplied strings it will be skipped. e.g. "/cache" [default: ""]
- `--dry-run`: Dry run, don't delete anything

### Rebuild image cache

This command allows you to rebuild image thumbnail cache.
It executes the `liip:imagine:cache:resolve` command for every user- and magazine-avatar and linked image in entries and posts.

> [!NOTE]
> This command will trigger **a lot** of processing if you execute it on a long-running server.

Usage:

```bash
php bin/console mbin:cache:build
```

## Miscellaneous

### Delete monitoring data

> [!HINT]
> For information about monitoring see [Optional Features/Monitoring](../03-optional-features/08-monitoring.md).

This command allows you to delete monitoring data according to the passed parameters.

Usage:

```bash
php bin/console mbin:monitoring:delete-data [-a|--all] [--queries] [--twig] [--requests] [--before [BEFORE]]
```

Options:
- `-a`|`--all`: delete all contexts, including all linked data (queries, twig renders and curl requests)
- `--queries`: delete all query data (this is the most space consuming data)
- `--twig`: delete all twig rendering data (this is the second most space consuming data)
- `--requests`: delete all curl request data
- `--before [BEFORE]]`: if you want to limit the data deleted by their creation date, including via the `-a|--all` option. You can pass something like _"now - 1 day"_

As an example you could delete all query data by running
`php bin/console mbin:monitoring:delete-data --queries --before "now - 8 hours"`.
This way you could still view the average request times without the query data for every request older than 8 hours
and the newer requests would not be affected at all. This way you can limit the space consumed by query data.
You can also mix and match the `--queries`, `--twig` and `--requests` options.

### Search for duplicate magazines or users and remove them

This command provides a guided tour to search for, and remove duplicate magazines or users.
This has been added to make the creation of unique indexes easier if the migration failed.

Usage:

```bash
php bin/console mbin:check:duplicates-users-magazines
```

### Users-Remove-Marked-For-Deletion

> [!NOTE]
> The same job is executed on a daily schedule automatically. There should be no need to execute this command.

Removes all accounts that are marked for deletion today or in the past.

Usage:

```bash
php bin/console mbin:users:remove-marked-for-deletion
```

### Messengers-Failed-Remove-All

> [!NOTE]
> The same job is executed on a daily schedule automatically. There should be no need to execute this command.
 
This command removes all failed messages from the failed queue (database).

Usage:

```bash
php bin/console mbin:messenger:failed:remove_all
```

### Messengers-Dead-Remove-All

> [!NOTE]
> The same job is executed on a daily schedule automatically. There should be no need to execute this command.

This command removes all dead messages from the dead queue (database).

Usage:

```bash
php bin/console mbin:messenger:dead:remove_all
```

### Post-Remove-Duplicates

This command removes post and user duplicates by their ActivityPub ID.

> [!NOTE]
> We've had a unique index on the ActivityPub ID for a while, hence this command should not do anything

Usage:

```bash
php bin/console mbin:user:create [-r|--remove] [--admin] [--moderator] <username> <email> <password>
```

### Update-Local-Domain

This command will remove all remote posts from belonging to the local domain. This command is only relevant for instances
created before v1.7.4 as the local domain was the fallback if no domain could be extracted from a post.

Usage:
```bash
php bin/console mbin:update:local-domain
```
