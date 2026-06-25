# [IN]: Backend source and Composer manifest / 后端源码与 Composer 清单
# [OUT]: PHP-FPM runtime with Laravel dependencies / 带 Laravel 依赖的 PHP-FPM 运行时
# [POS]: Backend PHP container image / 后端 PHP 容器镜像
# Protocol: When updating me, sync this header + parent folder's .folder.md
# 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash \
        freetype-dev \
        git \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd intl mbstring pdo_mysql zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

CMD ["php-fpm"]
