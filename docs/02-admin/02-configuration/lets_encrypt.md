# Let's Encrypt (TLS)

:::note

The Certbot authors recommend installing through snap as some distros' versions from APT tend to fall out-of-date; see https://eff-certbot.readthedocs.io/en/latest/install.html#snap-recommended for more.

:::

Install Snapd:

```bash
sudo apt-get install snapd
```

Install Certbot:

```bash
sudo snap install core; sudo snap refresh core
sudo snap install --classic certbot
```

Add symlink:

```bash
sudo ln -s /snap/bin/certbot /usr/bin/certbot
```

Follow the prompts to create TLS certificates for your domain(s). If you don't already have NGINX up, you can use standalone mode.

```bash
sudo certbot certonly

# Or if you wish not to use the standalone mode but the Nginx plugin:
sudo certbot --nginx -d domain.tld
```
