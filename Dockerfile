FROM php:8.2-apache

# ===== تثبيت الإضافات المطلوبة =====
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_sqlite curl mbstring zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# ===== رفع كود المشروع =====
WORKDIR /var/www/html
COPY . /var/www/html/

# ===== إعداد Apache ليستمع على PORT الديناميكي من Render =====
COPY apache-config.conf /etc/apache2/conf-enabled/render.conf

# ===== صلاحيات =====
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

# ===== سكريبت البدء =====
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
