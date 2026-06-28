FROM fedora:latest

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

RUN dnf update -y

# install other dev dependencies
RUN dnf install -y lsb-release ca-certificates curl wget unzip gnupg apt-transport-https acl nodejs npm

# Install PHP
# (Fedora ships the latest PHP version)
RUN dnf install -y php \
    php-common \
    php-fpm \
    php-cli \
    php-amqp \
    php-bcmath \
    php-pgsql \
    php-gd \
    php-curl \
    php-xml \
    php-redis \
    php-mbstring \
    php-zip \
    php-bz2 \
    php-intl

# Unlimited memory
RUN echo "memory_limit = -1" >>/etc/php.d/90-mem.ini
RUN sed -i 's/;php_admin_value\[memory_limit\] = 128M/php_admin_value[memory_limit] = -1/' /etc/php-fpm.d/www.conf

# Add cs2pr binary using wget
RUN wget https://raw.githubusercontent.com/staabm/annotate-pull-request-from-checkstyle/refs/heads/master/cs2pr -O /usr/local/bin/cs2pr
RUN chmod +x /usr/local/bin/cs2pr

RUN php -i
