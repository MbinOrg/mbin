# Using the latest LTS release
FROM ubuntu:latest

ARG DEBIAN_FRONTEND=noninteractive

RUN apt update
RUN apt upgrade -y
RUN apt install lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common acl php8.3 php8.3-common php8.3-fpm php8.3-cli php8.3-amqp php8.3-bcmath php8.3-pgsql php8.3-gd php8.3-curl php8.3-xml php8.3-redis php8.3-mbstring php8.3-zip php8.3-bz2 php8.3-intl php8.3-bcmath nodejs composer npm -y

# Unlimited memory
RUN echo "memory_limit = -1" >>/usr/local/etc/php/conf.d/docker-php-memlimit.ini
