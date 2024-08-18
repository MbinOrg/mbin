#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

if [ "$1" == "php-fpm" ] || [ "$1" == "php" ] || [ "$1" == "bin/console" ]; then
    pwd
    # if running as a service install assets
    echo "Starting as service..."

    if [ "$APP_ENV" == "dev" ] ; then
      echo "Installing PHP dependencies"
      # In development: dump the development PHP config files,
      # validate the installed packages (including dev dependencies) and dump dev config
      composer install --prefer-dist --no-scripts --no-progress
      composer dump-env dev
    fi

    if [ "$APP_ENV" == "prod" ] ; then
      # Parts of mbin are served directly by the webserver without calling php-fpm (public/ folder)
      # User uploads and other dynamically created content are inserted into public/ by this container
      # The webserver needs access to those newly uploaded files
      echo "Syncing mbin src"
      rsync \
        --links \
        --recursive \
        --chown $MBIN_USER:$MBIN_GROUP \
        $MBIN_SRC/ $MBIN_HOME
    fi

    echo "Waiting for db to be ready..."
    ATTEMPTS_LEFT_TO_REACH_DATABASE=60
    until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(bin/console dbal:run-sql "SELECT 1" 2>&1); do
        if [ $? -eq 255 ]; then
            # If the Doctrine command exits with 255, an unrecoverable error occurred
            ATTEMPTS_LEFT_TO_REACH_DATABASE=0
            break
        fi
        sleep 1
        ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
        echo "Still waiting for db to be ready... Or maybe the db is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left"
    done

    if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
        echo "The database is not up or not reachable:"
        echo "$DATABASE_ERROR"
        exit 1
    else
        echo "The db is now ready and reachable"
    fi

    if [ "$1" == "php-fpm" ]; then
        if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
            bin/console doctrine:migrations:migrate --no-interaction
        fi

        if [ -n "$ENABLE_ACL" ]; then
            if [ "$ENABLE_ACL" -eq 1 ]; then
                setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
                setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var
            else
                echo "Filesystem ACL is disabled."
            fi
        else
            echo "ENABLE_ACL is not set!"
        fi
    fi

    if [ "$APP_ENV" == "prod" ] ; then
      echo "Creating directories and setting ownership"
       # Create necessary directories for php-fpm process run by mbin user
      mkdir -p public/media var/log /data /config
      chown -R $MBIN_USER:$MBIN_GROUP public/media var /data /config .env
      chmod 777 public/media var
    fi
fi

USER=$(whoami)
if [ "$USER" == "$MBIN_USER" ] ; then
  # Probably dev
  exec "$@"
else
  # Most likely prod
  # Run command as non-root user
  # Workaround: Allow php-fpm to write to stderr
  chown "$MBIN_USER" /proc/self/fd/2

  exec su "$MBIN_USER" -c "$*"
fi
