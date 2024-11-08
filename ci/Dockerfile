FROM php:8.3

# Add composer
COPY --from=composer:2.7.8 /usr/bin/composer /usr/bin/composer

# Add NodeJS
RUN apt-get update && apt-get install -y nodejs npm

# Add additional APT packages
RUN apt-get update && apt install -y unzip postgresql

# Add additional PHP packages
RUN curl -sSLf \
  -o /usr/local/bin/install-php-extensions \
  https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions
RUN chmod +x /usr/local/bin/install-php-extensions

RUN install-php-extensions amqp intl redis gd zip bcmath xsl

# Unlimited memory
RUN echo "memory_limit = -1" >>/usr/local/etc/php/conf.d/docker-php-memlimit.ini
