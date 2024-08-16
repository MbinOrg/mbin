FROM php:8.3-fpm-alpine as base
ARG UID=1000
ARG MBIN_HOME=/var/www/mbin
ENV MBIN_HOME=${MBIN_HOME}
ARG MBIN_USER=mbin
ENV MBIN_USER=${MBIN_USER}
ARG MBIN_GROUP=www-data
ENV MBIN_GROUP=${MBIN_GROUP}

# Create user
RUN adduser -u $UID -D -g "" $MBIN_USER $MBIN_GROUP
# Install deps
RUN apk update && apk add acl fcgi exiftool

# Install php extensions, by docker-php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
      amqp \
      apcu \
      bcmath \
      curl \
      dom \
      exif \
      gd \
      intl \
      opcache \
      pcntl \
      pdo_pgsql \
      pgsql \
      redis \
      simplexml \
      xdebug \
      xml

# Dev will mount the entrypoint
# Prod will embed it
ENTRYPOINT ["/entrypoint.sh"]


###################################################
FROM base as dev
# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Switch user to write as non-root into mounted local directories
USER $MBIN_USER:$MBIN_GROUP


######################################################
FROM base as builder-composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR $MBIN_HOME

# Composer: install package
COPY composer.* $MBIN_HOME
COPY symfony.lock $MBIN_HOME
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# Copy only required resources
# TODO Use one line once COPY --parents is supported
COPY --link assets $MBIN_HOME/assets
COPY --link bin $MBIN_HOME/bin
COPY --link config $MBIN_HOME/config
COPY --link migrations $MBIN_HOME/migrations
COPY --link public $MBIN_HOME/public
COPY --link src $MBIN_HOME/src
COPY --link templates $MBIN_HOME/templates
COPY --link translations $MBIN_HOME/translations

# Increase memory limit for following steps
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory_limit.ini
# Must have an env file: https://github.com/symfony/symfony/issues/34660
# Must have all env vars in it https://github.com/symfony/flex/issues/346
COPY --link .env.example_docker .env

# Dump-autoload and run post-install script
RUN composer dump-autoload --classmap-authoritative --no-dev
RUN composer run-script --no-dev post-install-cmd && \
    chmod +x bin/console && sync


######################################################
FROM node:22.5.1-alpine3.20 as builder-nodejs
ARG MBIN_HOME=/var/www/mbin
ENV MBIN_HOME=${MBIN_HOME}

# Set NodeJS as production by default
ARG NODE_ENV=production
ENV NODE_ENV=${NODE_ENV}

# Setup environment
RUN mkdir -p $MBIN_HOME
WORKDIR $MBIN_HOME

# NPM: install package dependencies
COPY package.json $MBIN_HOME
COPY package-lock.json $MBIN_HOME
COPY --from=builder-composer --link $MBIN_HOME/vendor $MBIN_HOME/vendor
RUN npm ci --include=dev

# NPM: build (production by default)
# TODO Use one line once COPY --parents is supported
COPY --link assets/ $MBIN_HOME/assets/
COPY --link config/ $MBIN_HOME/config/
COPY --link public/ $MBIN_HOME/public/
COPY --link src/ $MBIN_HOME/src/
COPY --link webpack* $MBIN_HOME/
RUN npm run build



###################################################
FROM base as prod

# Copy only necessary resources
# TODO Use one line once COPY --parents is supported
## Folders
COPY --chown=$MBIN_USER:$MBIN_GROUP --link assets/ $MBIN_HOME/assets
COPY --chown=$MBIN_USER:$MBIN_GROUP --link bin/ $MBIN_HOME/bin
COPY --chown=$MBIN_USER:$MBIN_GROUP --link config/ $MBIN_HOME/config
COPY --chown=$MBIN_USER:$MBIN_GROUP --link migrations/ $MBIN_HOME/migrations
COPY --chown=$MBIN_USER:$MBIN_GROUP --link public/ $MBIN_HOME/public
COPY --chown=$MBIN_USER:$MBIN_GROUP --link src/ $MBIN_HOME/src
COPY --chown=$MBIN_USER:$MBIN_GROUP --link templates/ $MBIN_HOME/templates
COPY --chown=$MBIN_USER:$MBIN_GROUP --link translations/ $MBIN_HOME/translations

## Files
COPY --chown=$MBIN_USER:$MBIN_GROUP --link composer* $MBIN_HOME/
COPY --chown=$MBIN_USER:$MBIN_GROUP --link symfony* $MBIN_HOME/

## Build output
COPY --from=builder-composer --chown=$MBIN_USER:$MBIN_GROUP $MBIN_HOME/vendor $MBIN_HOME/vendor
COPY --from=builder-composer --chown=$MBIN_USER:$MBIN_GROUP $MBIN_HOME/public/bundles $MBIN_HOME/public/bundles
COPY --from=builder-nodejs --chown=$MBIN_USER:$MBIN_GROUP $MBIN_HOME/public $MBIN_HOME/public

WORKDIR $MBIN_HOME

# Must have an env file: https://github.com/symfony/symfony/issues/34660
# Must have all env vars in it https://github.com/symfony/flex/issues/346
COPY --link .env.example_docker .env

# Configure PHP for production
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/app.prod.ini /usr/local/etc/php/conf.d/
COPY docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Embed entrypoint declared in base image
COPY ./docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
