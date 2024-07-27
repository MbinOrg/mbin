FROM php:8.2-fpm-alpine as base

# Install php extensions, by docker-php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions amqp bcmath pgsql pdo_pgsql gd curl simplexml dom xml redis intl opcache apcu pcntl exif

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install caddy
RUN apk update && apk add caddy acl fcgi exiftool

# **Environment variables**
ARG UID=1000
ENV MBIN_HOME=/var/www/mbin \
    USER=mbin \
    GROUP=www-data

# Create user
RUN adduser -u $UID -D -g "" $USER $GROUP

# Create path
RUN mkdir -p $MBIN_HOME && \
    chown -R $USER:$GROUP $MBIN_HOME
WORKDIR $MBIN_HOME

# PHP configuration (Requires these configuration before "composer install" )
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --link docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/app.ini
COPY --link docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/app.prod.or.dev.ini
COPY --link docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/app.ini-production
COPY --link docker/php/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/app.ini-development
COPY --link docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
RUN chown -R $USER:$GROUP $PHP_INI_DIR
RUN chown -R $USER:$GROUP /usr/local/etc/php-fpm.d

####################

FROM caddy:2.8.4-builder-alpine AS builder-caddy

# Build Caddy with the Mercure and Vulcain and brotil cache modules
RUN xcaddy build \
	--with github.com/dunglas/mercure/caddy \
	--with github.com/dunglas/vulcain/caddy \
	--with github.com/ueffel/caddy-brotli

####################

FROM base as builder-composer

# Composer: install package
COPY composer.* $MBIN_HOME
COPY symfony.lock $MBIN_HOME
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# Copy repository
COPY --link ./ $MBIN_HOME
RUN cp .env.example_docker .env

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

####################

FROM base as runner

COPY --chown=$USER:$GROUP --link ./ $MBIN_HOME
RUN cp .env.example_docker .env

COPY --from=builder-caddy --link /usr/bin/caddy /usr/sbin/caddy
COPY --from=builder-composer --chown=$USER:$GROUP $MBIN_HOME/vendor $MBIN_HOME/vendor
COPY --from=builder-composer --chown=$USER:$GROUP $MBIN_HOME/public/bundles $MBIN_HOME/public/bundles
COPY --from=builder-nodejs --chown=$USER:$GROUP $MBIN_HOME/public $MBIN_HOME/public

COPY --link docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --chmod=755 --link docker/docker-entrypoint ./

RUN mkdir -p public/media var/log /data /config && \
    chown -R $USER:$GROUP public/media var /data /config .env && \
    chmod 777 public/media var

# Switch user
USER $USER:$GROUP

ENTRYPOINT ["./docker-entrypoint"]

# Expose port 2019 for caddy metric
EXPOSE 2019/tcp
