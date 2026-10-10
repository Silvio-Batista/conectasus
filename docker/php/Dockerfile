# syntax=docker/dockerfile:1

# Imagem PHP-FPM da aplicação ConectaSUS.
# PHP 8.4 é necessário porque o composer.lock utiliza componentes Symfony 8.x (PHP >= 8.4.1).
FROM php:8.4-fpm

# UID/GID do usuário que executa a aplicação. Alinhar ao usuário do host (Linux)
# evita problemas de permissão nos arquivos criados dentro do volume.
ARG UID=1000
ARG GID=1000

# Dependências de sistema e extensões PHP exigidas pelo Laravel + MySQL.
# libfcgi-bin fornece o cgi-fcgi, usado pelo healthcheck do PHP-FPM.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libfcgi-bin \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer copiado da imagem oficial (sem instalar via curl | sh).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Alinha o usuário www-data ao UID/GID informados e garante diretórios graváveis
# para o cache do Composer e para o volume nomeado do vendor (ver compose.yaml).
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data \
    && mkdir -p /var/www/.composer /var/www/html/vendor \
    && chown -R www-data:www-data /var/www

ENV COMPOSER_HOME=/var/www/.composer

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
