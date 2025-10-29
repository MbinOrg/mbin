# Using the latest LTS release
FROM ubuntu:latest

ARG DEBIAN_FRONTEND=noninteractive

RUN apt update
RUN apt upgrade -y
RUN apt install lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common acl php8.4 php8.4-common php8.4-fpm php8.4-cli php8.4-amqp php8.4-bcmath php8.4-pgsql php8.4-gd php8.4-curl php8.4-xml php8.4-redis php8.4-mbstring php8.4-zip php8.4-bz2 php8.4-intl php8.4-bcmath nodejs composer npm -y

# Unlimited memory
RUN echo "memory_limit = -1" >>/etc/php/8.4/cli/conf.d/docker-php-memlimit.ini
RUN echo "memory_limit = -1" >>/etc/php/8.4/fpm/conf.d/docker-php-memlimit.ini

# Add cs2pr binary using wget
RUN wget https://raw.githubusercontent.com/staabm/annotate-pull-request-from-checkstyle/refs/heads/master/cs2pr -O /usr/local/bin/cs2pr
RUN chmod +x /usr/local/bin/cs2pr
