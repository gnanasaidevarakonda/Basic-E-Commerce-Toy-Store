# 1. Base Image Start with an official lightweight linux Conataining PHP 8.2-FPM
FROM php:8.2-fpm-alpine

# 2. Install Linux packages: curl, git, bash, and zip libraries
RUN apk add --no-cache \
    curl \
    git \
    bash \
    libzip-dev

# 3. Enable PHP extensions for MYSQL(PDO is how PHP talks securely to MYSQL)
RUN docker-php-ext-install pdo pdo_mysql

# 4. Set the working folder inside the container
WORKDIR /var/www/html

# 5. Tell Docker that PHP-FPM listens on the internal port 9000
EXPOSE 9000

# 6. Default command to run when the container starts
CMD [ "php-fpm" ]