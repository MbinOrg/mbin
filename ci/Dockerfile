# Using the latest LTS release
FROM ubuntu:latest

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

ARG DEBIAN_FRONTEND=noninteractive

RUN apt update
RUN apt upgrade -y
RUN apt install -y lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common acl nodejs npm

# Use PHP 8.5 (which also somes with the latest redis-ext v6) instead of Ubuntu PHP 8.3 (which was still using redis-ext v5)
# Add ondrej PPA
RUN add-apt-repository -y ppa:ondrej/php
RUN apt update
RUN apt install -y php8.5 \
    php8.5-common \
    php8.5-fpm \
    php8.5-cli \
    php8.5-amqp \
    php8.5-bcmath \
    php8.5-pgsql \
    php8.5-gd \
    php8.5-curl \
    php8.5-xml \
    php8.5-redis \
    php8.5-mbstring \
    php8.5-zip \
    php8.5-bz2 \
    php8.5-intl \
    php8.5-bcmath

# Unlimited memory
RUN echo "memory_limit = -1" >>/etc/php/8.5/cli/conf.d/docker-php-memlimit.ini
RUN echo "memory_limit = -1" >>/etc/php/8.5/fpm/conf.d/docker-php-memlimit.ini

# Add cs2pr binary using wget
RUN wget https://raw.githubusercontent.com/staabm/annotate-pull-request-from-checkstyle/refs/heads/master/cs2pr -O /usr/local/bin/cs2pr
RUN chmod +x /usr/local/bin/cs2pr
