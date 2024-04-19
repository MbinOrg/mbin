# Mbin configuration files

These are additional configuration YAML file changes in the `config` directory.

## Image redirect response code

Assuming you **are using Nginx** (as described above, with the correct configs), you can reduce the server load by changing the image redirect response code from `302` to `301`, which allows the client to cache the complete response. Edit the following file (from the root directory of Mbin):

```bash
nano config/packages/liip_imagine.yaml
```

And now change: `redirect_response_code: 302` to: `redirect_response_code: 301`. If you are experience image loading issues, validate your Nginx configuration or revert back your changes to `302`.

---

> [!TIP]
> There are also other configuration files, eg. `config/packages/monolog.yaml` where you can change logging settings if you wish, but this is not required (these defaults are fine for production).
