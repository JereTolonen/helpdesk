# OpiskelijaHelpdesk – PHP 8.3 + Apache
FROM php:8.3-apache

# PDO MySQL -ajuri ja Apache mod_rewrite (reititin)
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && echo 'Timeout 360' > /etc/apache2/conf-available/timeout.conf \
    && a2enconf timeout

# Document root osoittaa public-kansioon: muut kansiot (config, core, views...)
# eivät ole selaimen saatavilla.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
       > /etc/apache2/conf-available/helpdesk.conf \
    && a2enconf helpdesk

# PHP-asetukset: AI-vastaus voi kestää (mallin lataus muistiin)
RUN { \
      echo 'max_execution_time = 360'; \
      echo 'default_socket_timeout = 360'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
      echo 'date.timezone = Europe/Helsinki'; \
    } > /usr/local/etc/php/conf.d/helpdesk.ini

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80