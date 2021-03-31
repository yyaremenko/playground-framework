FROM php:8.0-fpm

RUN apt-get update \
    && apt-get install -y \
        # ps, top commands
        procps \
        # nano
        nano

# install php extensions
RUN \
    # ------------------------------------- pdo_mysql
    docker-php-ext-install pdo_mysql \
    # ------------------------------------- zip
    && apt-get install -y \
        libzip-dev \
        zip \
    && docker-php-ext-install zip

# composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
RUN rm -Rf /var/www/html

# aliases
COPY .bashrc /root/.bashrc

# run script
COPY run.sh /root/run.sh
RUN chmod o+x /root/run.sh
ENTRYPOINT ["/root/run.sh"]