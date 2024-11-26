# Captcha

Go to [hcaptcha.com](https://www.hcaptcha.com) and create a free account. Make a sitekey and a secret. Add domain.tld to the sitekey.
Optionally, increase the difficulty threshold. Making it even harder for bots.

Edit your `.env` file:

```ini
KBIN_CAPTCHA_ENABLED=true
HCAPTCHA_SITE_KEY=sitekey
HCAPTCHA_SECRET=secret
```

Then dump-env your configuration file:

```bash
composer dump-env prod
```

or:

```bash
composer dump-env dev
```

Finally, go to the admin panel, settings tab and check "Captcha enabled" and press "Save".
