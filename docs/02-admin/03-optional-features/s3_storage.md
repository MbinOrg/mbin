# S3 Images storage

Edit your `.env` file:

```ini
S3_KEY=$AWS_ACCESS_KEY_ID
S3_SECRET=$AWS_SECRET_ACCESS_KEY
S3_BUCKET=bucket-name
# safe default for s3 deployments like minio or single zone ceph/radosgw
S3_REGION=us-east-1
# set if not using aws s3, note that the scheme is also required
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
