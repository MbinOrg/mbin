# Image compression

You can enable compression of images uploaded to mbin by users or downloaded from remote instances, 
for increased compatibility, to save on size and for a better user experience.

To enable image compression set `MBIN_IMAGE_COMPRESSION_QUALITY` in your `.env` file to a value between 0.1 and 0.95.
This setting is used as a starting point to compress the image. It is gradually lowered (in 0.05 steps) until the maximum size is no longer exceeded.

> [!HINT]
> The maximum file size is determined by the `MBIN_MAX_IMAGE_BYTES` setting in your `.env` file

> [!NOTE]
> Enabling this setting can cause a higher memory usage

## Better compatibility

If another instance shares a thread with an image attached that exceeds your maximum image size, it will not be downloaded,
but instead loaded directly from the other instance. This works most of the time, 
but sometimes website settings will block it and thus your users will see an image that cannot be loaded.
This behavior also introduces web requests to other servers, which may unintentionally leak information to the remote instance. 

If instead your server compresses the image and saves it locally this will never happen.

## Saving space

When image compression is enabled you can reduce your maximum image size to, lets say 1MB. 
Without the compression this might not be suitable, because too many images exceed that size,
and you don't want to risk compatibility problems, 
but with it enabled the images will just be compressed, saving space.

## A better user experience

Normally there is a maximum image size your users must adhere to, but if image compression is enabled,
instead of showing your user an error that the image exceeds that size, the upload goes through and the image is compressed.
