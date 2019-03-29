FROM php:7.3.3-cli
RUN apt-get -y update \
    && apt-get -y install git vim
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN mkdir -p /var/www
VOLUME /var/www
EXPOSE 80
ENTRYPOINT ["php", "-S", "0.0.0.0:80", "-t", "/var/www/web/"]