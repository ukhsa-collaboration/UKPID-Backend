#########
# Build and push this docker image to the gitlab docker registry when updated to ensure an accurate CI/CD pipeline
#
# docker build -t registry.gitlab.com/juicy-media-ltd/ukpid/ukpid-backend/laravel . -f laravel.dockerfile
# docker push registry.gitlab.com/juicy-media-ltd/ukpid/ukpid-backend/laravel
#########

FROM php:8.5.5-fpm

RUN apt-get update \
    && apt-get install -y  \
    nano \
    vim \
    libzip-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    zip

# Configure PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql sockets bcmath zip

RUN pecl install mongodb
RUN echo "extension=mongodb.so" > $PHP_INI_DIR/conf.d/mongo.ini;

ARG MEMORY_LIMIT=8G

RUN echo "memory_limit = $MEMORY_LIMIT" >> $PHP_INI_DIR/conf.d/custom.ini
RUN echo "upload_max_filesize = 256M" >> $PHP_INI_DIR/conf.d/custom.ini
RUN echo "post_max_size = 256M" >> $PHP_INI_DIR/conf.d/custom.ini
RUN echo "max_input_time = 180" >> $PHP_INI_DIR/conf.d/custom.ini
RUN echo "max_execution_time = 180" >> $PHP_INI_DIR/conf.d/custom.ini

# Configure cron - cron can only be run as root, so it's conditional as volume mounts aren't writable when user is root on Linux/WSL
ARG WITHOUT_CRON=false
ENV WITHOUT_CRON=$WITHOUT_CRON
RUN if [ $WITHOUT_CRON != "true" ] ; then \
    apt-get install -y cron; \
    echo "* * * * * www-data . /opt/env_exports && php artisan schedule:run >> /dev/null 2>&1" >> /etc/cron.d/laravel-scheduler; \
fi ;

# Copy the laravel files
COPY application /var/www/html/
VOLUME ["/var/www/html"]

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN composer install --no-interaction

# Copy the entrypoint
COPY docker-entrypoint-laravel.sh /usr/local/bin/

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-laravel.sh"]
CMD ["php-fpm"]

# XDebug
ARG WITH_XDEBUG=false

RUN if [ $WITH_XDEBUG = "true" ] ; then \
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
    echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    echo "display_startup_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    echo "display_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
fi ;

