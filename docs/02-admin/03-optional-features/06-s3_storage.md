# S3 Images storage

## Migrating the media files

If you're starting a new instance, you can skip this part.

To migrate to S3 storage we have to sync the media files located at `/var/www/mbin/public/media` into our S3 bucket.
We suggest running the sync once while your instance is still up and using the local storage for media, then shutting mbin down,
configure it to use the S3 storage and do another sync to get all the files created during the initial sync.

To actually do the file sync you can use different tools, like `aws-cli`, `rclone` and others,
just search for it and you will find plenty tutorials on how to do that

## Configuring Mbin

Edit your `.env` file:

```ini
S3_KEY=$AWS_ACCESS_KEY_ID
S3_SECRET=$AWS_SECRET_ACCESS_KEY
S3_BUCKET=bucket-name
# safe default for S3 deployments like minio or single zone ceph/radosgw
S3_REGION=us-east-1
# set if not using aws S3, note that the scheme is also required
S3_ENDPOINT=https://endpoint.domain.tld
S3_VERSION=latest
```

Then edit the: `config/packages/oneup_flysystem.yaml` file:

```yaml
oneup_flysystem:
  adapters:
    default_adapter:
      local:
        location: "%kernel.project_dir%/public/%uploads_dir_name%"

    kbin.s3_adapter:
      awss3v3:
        client: kbin.s3_client
        bucket: "%amazon.s3.bucket%"
        options:
          ACL: public-read

  filesystems:
    public_uploads_filesystem:
      # switch the adapter to s3 adapter
      #adapter: default_adapter
      adapter: kbin.s3_adapter
      alias: League\Flysystem\Filesystem
```

## NGINX reverse proxy

If you are using an object storage provider, we strongly advise you to use a media reverse proxy.
That way media URLs will not change and break links on remote instances when you decide to switch providers
and it hides your S3 endpoint from users of your instance.

This replaces the media reverse proxy from [NGINX](../02-configuration/02-nginx.md).

If you already had a reverse proxy for your media, then you only have to change the NGINX config,
otherwise please follow the steps in our [media-reverse-proxy](../02-configuration/02-nginx.md) docs

This config is heavily inspired by [Mastodons Nginx config](https://docs.joinmastodon.org/admin/optional/object-storage-proxy/).

```nginx
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=CACHE:10m inactive=7d max_size=10g;

server {
    server_name https://media.mbin.domain.tld;

    location / {
        try_files $uri @s3;
    }

    set $s3_backend 'https://your.s3.endpoint.tld';

    location @s3 {
        limit_except GET {
            deny all;
        }

        resolver 1.1.1.1;

        proxy_set_header Accept 'image/*';
        proxy_set_header Connection '';
        proxy_set_header Authorization '';
        proxy_hide_header Set-Cookie;
        proxy_hide_header 'Access-Control-Allow-Origin';
        proxy_hide_header 'Access-Control-Allow-Methods';
        proxy_hide_header 'Access-Control-Allow-Headers';
        proxy_hide_header x-amz-id-2;
        proxy_hide_header x-amz-request-id;
        proxy_hide_header x-amz-meta-server-side-encryption;
        proxy_hide_header x-amz-server-side-encryption;
        proxy_hide_header x-amz-bucket-region;
        proxy_hide_header x-amzn-requestid;
        proxy_ignore_headers Set-Cookie;
        proxy_pass $s3_backend$uri;
        proxy_intercept_errors off;

        proxy_cache CACHE;
        proxy_cache_valid 200 48h;
        proxy_cache_use_stale error timeout updating http_500 http_502 http_503 http_504;
        proxy_cache_lock on;

        expires 1y;
        add_header Cache-Control public;
        add_header 'Access-Control-Allow-Origin' '*';
        add_header X-Cache-Status $upstream_cache_status;
        add_header X-Content-Type-Options nosniff;
        add_header Content-Security-Policy "default-src 'none'; form-action 'none'";
    }
    listen 80;
}
```

For it to be a usable HTTPS site you have to run `certbot` or supply your certificates manually.

> [!TIP]
> Do not forget to enable http2 by adding `http2 on;` after certbot ran successfully.
