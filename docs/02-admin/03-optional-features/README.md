# Optional Features

There are several options features in Mbin, which you may want to configure.

Like setting-up:

- [Mercure](01-mercure.md) - Mercure is used to provide real-time data from the server towards the clients.
- [Single sign-on (SSO)](02-sso.md) - SSO can be configured to allow registrations via other SSO providers.
- [Captcha](03-captcha.md) - Captcha protection against spam and anti-bot.
- [User application approval](04-user_application.md) - Manually approve users before they can log into your server (eg. to avoid spam accounts).
- [Image metadata cleaning](05-image_metadata_cleaning.md) - Clean-up and remove metadata from images using `exiftool`.
- [S3 storage](06-s3_storage.md) - Configure an object storage service (S3) compatible bucket for storing images.
- [Anubis](07-anubis.md) - A service for weighing the incoming requests and may present them with a proof-of-work challenge. It is useful if your instance gets hit a lot of bot traffic that you're tired of filtering through
