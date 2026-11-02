FROM wordpress:latest

# Install additional PHP extensions
RUN docker-php-ext-install pdo_mysql

# Install WP-CLI
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# Enable Apache modules
RUN a2enmod headers rewrite

# PHP settings optimization
RUN echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini \
    && echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/docker-php-upload.ini \
    && echo 'post_max_size = 64M' >> /usr/local/etc/php/conf.d/docker-php-upload.ini \
    && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/docker-php-upload.ini
