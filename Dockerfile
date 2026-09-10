FROM php:8.2-fpm

# Cài đặt các extension cần thiết và Nginx
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

WORKDIR /var/www/html

# Copy toàn bộ mã nguồn vào container
COPY . .

# Cấp quyền cho Nginx và PHP đọc/ghi thư mục
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Cấu hình Nginx trỏ thẳng vào thư mục public chứa index.php
RUN echo 'server {\n\
    listen 80;\n\
    index index.php index.html;\n\
    root /var/www/html/public;\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
    location ~ \.php$ {\n\
        include fastcgi_params;\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
    }\n\
    }' > /etc/nginx/sites-available/default

EXPOSE 80

# Khởi động Nginx và PHP-FPM
CMD service nginx start && php-fpm