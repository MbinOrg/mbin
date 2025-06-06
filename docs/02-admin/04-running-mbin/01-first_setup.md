# Mbin first setup

> [!TIP]
> If you are running docker, then you have to prefix the following commands with
> `docker compose exec php`.

Create new admin user (without email verification), please change the `username`, `email` and `password` below:

```bash
php bin/console mbin:user:create <username> <email@example.com> <password>
php bin/console mbin:user:admin <username>
```

```bash
php bin/console mbin:ap:keys:update
```

Next, log in and create a magazine named `random` to which unclassified content from the fediverse will flow.

> [!IMPORTANT]
> Creating a `random` magazine is a requirement to getting microblog posts that don't fall under an existing magazine.

```bash
php bin/console mbin:magazine:create random
```

### Manual user activation

Activate a user account (bypassing email verification), please change the `username` below:

```bash
php bin/console mbin:user:verify <username> -a
```

### Mercure

If you are not going to use Mercure, you have to disable it in the admin panel.

### NPM (bare metal only)

```bash
cd /var/www/mbin
npm install # Installs all NPM dependencies
npm run build # Builds frontend
```

Make sure you have substituted all the passwords and configured the basic services.

### Push Notification setup

The push notification system needs encryption keys to work. They have to be generated only once, by running

```bash
php bin/console mbin:push:keys:update
```
