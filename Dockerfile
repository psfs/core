FROM ubuntu:18.04
MAINTAINER "Fran López <fran.lopez84@hotmail.es>"

# Update ubuntu
RUN apt-get update

# Install basics
RUN apt-get install locales openssl curl -y
RUN locale-gen es_ES.UTF-8

# Install php
RUN apt-get install php7.2 php7.2-curl php7.2-gmp php7.2-json php7.2-mysql \
    php7.2-xml php7.2-bz2 php7.2-fpm php7.2-intl php7.2-mbstring php7.2-soap php7.2-xsl php7.2-zip -y

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=bin --filename=composer

# Install nginx
RUN apt-get install nginx -y

EXPOSE 80

RUN mkdir /psfs

CMD nginx -g 'daemon off;'
