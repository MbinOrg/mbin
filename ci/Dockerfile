FROM php:8.5-fpm-trixie

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

ARG DEBIAN_FRONTEND=noninteractive

RUN apt update && apt update -y

# install other dev dependencies
RUN apt install -y lsb-release ca-certificates curl wget unzip gnupg apt-transport-https acl nodejs npm

# Install PHP extensions
RUN apt install -y \
    librabbitmq-dev \
    libpq-dev zlib1g-dev \
    libpng-dev \
    libcurl4-openssl-dev \
    libxml2-dev libonig-dev \
    libzip-dev \
    libbz2-dev \
    icu-devtools \
    libicu-dev
RUN docker-php-ext-install bcmath pgsql gd curl xml mbstring zip bz2 intl
RUN yes '' | pecl install amqp redis \
    && docker-php-ext-enable redis amqp

# Unlimited memory
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN sed -i 's/memory_limit = 128M/memory_limit = -1/' "$PHP_INI_DIR/php.ini"
RUN printf '[www]\nphp_admin_value[memory_limit] = -1\n' > /usr/local/etc/php-fpm.d/memory.conf

# Add cs2pr binary using wget
RUN wget https://raw.githubusercontent.com/staabm/annotate-pull-request-from-checkstyle/refs/heads/master/cs2pr -O /usr/local/bin/cs2pr
RUN chmod +x /usr/local/bin/cs2pr

RUN php -i
