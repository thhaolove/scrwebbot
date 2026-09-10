FROM php:8.2-fpm

# Cài đặt các extension cần thiết cho PHP (ví dụ: pdo, pdo_mysql, gd, zip...)
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Cấu hình thư mục làm việc
WORKDIR /var/www/html

# Copy toàn bộ mã nguồn vào container
COPY . .

# Cấu hình Nginx trỏ vào thư mục gốc của code (hoặc thư mục public nếu code của bạn yêu cầu)
# Lưu ý: Nếu code của bạn chạy trực tiếp từ thư mục gốc, để nguyên /var/www/html. 
# Nếu code yêu cầu trỏ vào thư mục public, sửa thành /var/www/html/public
RUN echo 'server {\n\
    listen 80;\n\
    index index.php index.html;\n\
    root /var/www/html;\n\
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

# Khởi động Nginx và PHP-FPM cùng lúc khi container chạy
CMD service nginx start && php-fpm