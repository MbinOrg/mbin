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

### Actor-Update

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

### AP-Import

> [!NOTE]
> This command will trigger an **asynchronous** import

This command allows you to import an AP resource.

Usage:

```bash
php bin/console mbin:ap:import <url>
```

Arguments:
- `url`: the "id" of the ActivityPub object to import

## Miscellaneous

### Cache-Build

This command allows you to rebuild image thumbnail cache.
It executes the `liip:imagine:cache:resolve` command for every user- and magazine-avatar and linked image in entries and posts.

> [!NOTE]
> This command will trigger **a lot** of processing if you execute it on a long-running server.

Usage:

```bash
php bin/console mbin:cache:build
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
php bin/console mbin:user:create [-r|--remove] [--admin] [--moderator] [--] <username> <email> <password>
```

### Update-Local-Domain

This command will remove all remote posts from belonging to the local domain. This command is only relevant for instances
created before v1.7.4 as the local domain was the fallback if no domain could be extracted from a post.

Usage:
```bash
php bin/console mbin:update:local-domain [--]
```
