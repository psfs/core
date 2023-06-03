# 746792098073.dkr.ecr.eu-west-1.amazonaws.com/saas_php_base
FROM --platform=linux/amd64 php:7.4-fpm

LABEL 'author'='Fran Lopez <fran.lopez@pandago.eco>'
LABEL 'version'='1.0.2'

RUN apt-get update && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    libmcrypt-dev libbz2-dev libgmp-dev libcurl4-gnutls-dev libicu-dev libxml2-dev libxslt-dev \
    libzip-dev libonig-dev git unzip
RUN docker-php-ext-configure gd
RUN docker-php-ext-install bz2 curl gd gmp gettext iconv intl mbstring opcache mysqli pcntl pdo pdo_mysql soap xml xsl zip
RUN apt-get install -y exif openssl libssl-dev libmcrypt-dev

RUN pecl install mongodb \
        &&  echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongo.ini

RUN pecl install redis \
        &&  echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini

#RUN pecl install xdebug-3.1.5 \
#        && echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/xdebug.ini

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin  --filename=composer
RUN php -r "unlink('composer-setup.php');"

SHELL ["/bin/bash", "-c"]