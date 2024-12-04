# Image metadata cleaning with `exiftool`

It is possible to configure Mbin to remove meta-data from images.

To use this feature, install `exiftool` (`libimage-exiftool-perl` package for Ubuntu/Debian)
and make sure `exiftool` executable exist and and visible in PATH

Available options in `.env`:

```bash
# available modes: none, sanitize, scrub
# can be set differently for user uploaded and external media
EXIF_CLEAN_MODE_UPLOADED=sanitize
EXIF_CLEAN_MODE_EXTERNAL=none
# path to exiftool binary, leave blank for auto PATH search
EXIF_EXIFTOOL_PATH=
# max execution time for exiftool in seconds, defaults to 10 seconds
EXIF_EXIFTOOL_TIMEOUT=10
```

Available cleaning modes are:

- `none`: no metadata cleaning occurs.
- `sanitize`: GPS and serial number metadata is removed. This is the default for uploaded images.
- `scrub`: removes most of image metadata save for those needed for proper image rendering
  and XMP IPTC attribution metadata. [This line needs improvement]
