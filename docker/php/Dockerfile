FROM php:8.3-fpm-alpine as base
ARG UID=1000
ENV MBIN_HOME=/var/www/mbin
ENV USER=mbin
ENV GROUP=www-data

# Create user
RUN adduser -u $UID -D -g "" $USER $GROUP
# Install deps
RUN apk update && apk add acl fcgi exiftool
# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

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


# TODO Use a different entrypoint for prod?
COPY ./docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

#################

FROM base as dev

# Switch user
USER $USER:$GROUP


#################


####################

FROM base as builder-composer

# Composer: install package
COPY composer.* $MBIN_HOME
COPY symfony.lock $MBIN_HOME
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# Copy repository
COPY --link ./ $MBIN_HOME

# Dump-autoload and run post-install script
RUN composer dump-autoload --classmap-authoritative --no-dev
RUN composer run-script --no-dev post-install-cmd && \
    chmod +x bin/console && sync

####################

FROM node:22.5.1-alpine3.20 as builder-nodejs

# Set NodeJS as production by default
ARG NODE_ENV=production
ENV NODE_ENV=${NODE_ENV}

# Setup environment
ENV MBIN_HOME=/var/www/mbin
RUN mkdir -p $MBIN_HOME
WORKDIR $MBIN_HOME

# Copy required files
COPY package.json $MBIN_HOME
COPY package-lock.json $MBIN_HOME
COPY --from=builder-composer --link $MBIN_HOME/vendor $MBIN_HOME/vendor

# NPM: install package
RUN npm ci --include=dev

# NPM: build (production by default)
COPY --link ./ $MBIN_HOME
RUN npm run build


#################

FROM base as prod

COPY --chown=$USER:$GROUP --link ./ $MBIN_HOME

COPY --from=builder-composer --chown=$USER:$GROUP $MBIN_HOME/vendor $MBIN_HOME/vendor
COPY --from=builder-composer --chown=$USER:$GROUP $MBIN_HOME/public/bundles $MBIN_HOME/public/bundles
COPY --from=builder-nodejs --chown=$USER:$GROUP $MBIN_HOME/public $MBIN_HOME/public


RUN mkdir -p public/media var/log /data /config && \
    chown -R $USER:$GROUP public/media var /data /config .env && \
    chmod 777 public/media var

# TODO copy app.prod.ini

# Switch user
USER $USER:$GROUP

