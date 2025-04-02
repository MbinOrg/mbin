FROM php:8.4-fpm-alpine as base

# Setup environment
ENV MBIN_HOME=/var/www/mbin
RUN mkdir -p $MBIN_HOME
WORKDIR $MBIN_HOME

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


######################################################
FROM base as builder-composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

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

# Set NodeJS as production by default
ARG NODE_ENV=production
ENV NODE_ENV=${NODE_ENV}

# Setup environment
WORKDIR $MBIN_HOME

# NPM: install package dependencies
COPY package.json .
COPY package-lock.json .
COPY --from=builder-composer --link ${MBIN_HOME}/vendor ./vendor
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

# Copy only necessary resources
# TODO Use one line once COPY --parents is supported
## Folders
COPY --link assets/ ./assets
COPY --link bin/ ./bin
COPY --link config/ ./config
COPY --link migrations/ ./migrations
COPY --link public/ ./public
COPY --link src/ ./src
COPY --link templates/ ./templates
COPY --link translations/ ./translations

## Files
COPY --link composer* $MBIN_HOME/
COPY --link symfony* $MBIN_HOME/

## Build output
COPY --from=builder-composer $MBIN_HOME/vendor $MBIN_HOME/vendor
COPY --from=builder-composer $MBIN_HOME/public/bundles $MBIN_HOME/public/bundles
COPY --from=builder-nodejs $MBIN_HOME/public $MBIN_HOME/public

# Must have an env file: https://github.com/symfony/symfony/issues/34660
# Must have all env vars in it https://github.com/symfony/flex/issues/346
COPY --link .env.example_docker $MBIN_HOME/.env

# Configure PHP for production
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/app.prod.ini /usr/local/etc/php/conf.d/
COPY docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Embed entrypoint declared in base image
COPY ./docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
