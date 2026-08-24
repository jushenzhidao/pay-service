FROM php:7.4-apache

# 安装易支付所需 PHP 扩展
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        mysqli \
        bcmath \
        zip \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 启用 Apache rewrite
RUN a2enmod rewrite

# 允许 .htaccess 覆盖（易支付模板/入口依赖）
RUN sed -ri -e 's!/var/www/html!/var/www/html!g' \
    -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# PHP 运行时配置（兼容旧代码：宽松错误显示）
RUN { \
        echo 'memory_limit = 256M'; \
        echo 'upload_max_filesize = 64M'; \
        echo 'post_max_size = 64M'; \
        echo 'max_execution_time = 300'; \
        echo 'display_errors = On'; \
        echo 'date.timezone = Asia/Shanghai'; \
    } > /usr/local/etc/php/conf.d/epay.ini

WORKDIR /var/www/html
