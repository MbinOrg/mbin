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



COPY ./docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

# Switch user
USER $USER:$GROUP
