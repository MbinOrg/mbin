# Using the latest LTS release
FROM ubuntu:latest

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

ARG DEBIAN_FRONTEND=noninteractive

RUN apt update
RUN apt upgrade -y
RUN apt install lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common acl php8.3 php8.3-common php8.3-fpm php8.3-cli php8.3-amqp php8.3-bcmath php8.3-pgsql php8.3-gd php8.3-curl php8.3-xml php8.3-redis php8.3-mbstring php8.3-zip php8.3-bz2 php8.3-intl php8.3-bcmath nodejs npm -y

# Unlimited memory
RUN echo "memory_limit = -1" >>/etc/php/8.3/cli/conf.d/docker-php-memlimit.ini
RUN echo "memory_limit = -1" >>/etc/php/8.3/fpm/conf.d/docker-php-memlimit.ini

# Add cs2pr binary using wget
RUN wget https://raw.githubusercontent.com/staabm/annotate-pull-request-from-checkstyle/refs/heads/master/cs2pr -O /usr/local/bin/cs2pr
RUN chmod +x /usr/local/bin/cs2pr
