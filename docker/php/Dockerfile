FROM php:8.4-fpm-alpine as base
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
ARG MBIN_SRC=/usr/src/mbin
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR $MBIN_SRC

# Composer: install package
COPY composer.* ./
COPY symfony.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# Copy only required resources
# TODO Use one line once COPY --parents is supported
COPY --link assets ./assets
COPY --link bin ./bin
COPY --link config ./config
COPY --link migrations ./migrations
COPY --link public ./public
COPY --link src ./src
COPY --link templates ./templates
COPY --link translations ./translations

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
FROM node:22.7.0-alpine3.20 as builder-nodejs
ARG MBIN_SRC=/usr/src/mbin

# Set NodeJS as production by default
ARG NODE_ENV=production
ENV NODE_ENV=${NODE_ENV}

# Setup environment
WORKDIR $MBIN_SRC

# NPM: install package dependencies
COPY package.json .
COPY package-lock.json .
COPY --from=builder-composer --link ${MBIN_SRC}/vendor ./vendor
RUN npm ci --include=dev

# NPM: build (production by default)
# TODO Use one line once COPY --parents is supported
COPY --link assets/ ./assets/
COPY --link config/ ./config/
COPY --link public/ ./public/
COPY --link src/ ./src/
COPY --link webpack* ./
RUN npm run build



###################################################
FROM base as prod
ARG MBIN_SRC=/usr/src/mbin
ENV MBIN_SRC=${MBIN_SRC}

# rsync for syncing the public/ folder
RUN apk update && apk add rsync

WORKDIR $MBIN_SRC

# Copy only necessary resources
# TODO Use one line once COPY --parents is supported
## Folders
COPY --chown=$MBIN_USER:$MBIN_GROUP --link assets/ ./assets
COPY --chown=$MBIN_USER:$MBIN_GROUP --link bin/ ./bin
COPY --chown=$MBIN_USER:$MBIN_GROUP --link config/ ./config
COPY --chown=$MBIN_USER:$MBIN_GROUP --link migrations/ ./migrations
COPY --chown=$MBIN_USER:$MBIN_GROUP --link public/ ./public
COPY --chown=$MBIN_USER:$MBIN_GROUP --link src/ ./src
COPY --chown=$MBIN_USER:$MBIN_GROUP --link templates/ ./templates
COPY --chown=$MBIN_USER:$MBIN_GROUP --link translations/ ./translations

## Files
COPY --chown=$MBIN_USER:$MBIN_GROUP --link composer* $MBIN_SRC/
COPY --chown=$MBIN_USER:$MBIN_GROUP --link symfony* $MBIN_SRC/

## Build output
COPY --from=builder-composer --chown=$MBIN_USER:$MBIN_GROUP $MBIN_SRC/vendor $MBIN_SRC/vendor
COPY --from=builder-composer --chown=$MBIN_USER:$MBIN_GROUP $MBIN_SRC/public/bundles $MBIN_SRC/public/bundles
COPY --from=builder-nodejs --chown=$MBIN_USER:$MBIN_GROUP $MBIN_SRC/public $MBIN_SRC/public

# Must have an env file: https://github.com/symfony/symfony/issues/34660
# Must have all env vars in it https://github.com/symfony/flex/issues/346
COPY --link .env.example_docker $MBIN_SRC/.env

# Configure PHP for production
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/app.prod.ini /usr/local/etc/php/conf.d/
COPY docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Empty on first start
# sources from $MBIN_SRC have to be copied into it
WORKDIR $MBIN_HOME

# Embed entrypoint declared in base image
COPY ./docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
