# =========================================================
# Thread & Press Hub — production image (Railway)
#
# Replaces the previous `php:8.2-cli` + `php -S` setup. The built-in
# server is single-threaded, so one slow AI request (the product
# generator budgets up to 240s) blocked every other visitor.
# =========================================================
FROM php:8.2-apache

# ---- PHP extensions -----------------------------------------------
# mysqli/pdo_mysql : the app's database layer
# gd               : image handling (disabled locally in XAMPP; enabled here)
# mbstring, zip    : required by dompdf for invoice generation
# intl             : locale-aware number/date formatting
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libonig-dev \
        libicu-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql gd mbstring zip intl \
    && rm -rf /var/lib/apt/lists/*

# Apache modules. Exactly one MPM may be loaded or startup aborts with
# "AH00534: Configuration error: More than one MPM loaded." The apt step
# above can leave a second one enabled, so the others are explicitly
# disabled before prefork (the MPM mod_php requires) is turned back on.
RUN a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true; \
    a2enmod mpm_prefork rewrite headers \
    && echo "MPMs enabled: $(ls /etc/apache2/mods-enabled/ | grep -c '^mpm_.*\.load$') (must be 1)" \
    && test "$(ls /etc/apache2/mods-enabled/ | grep -c '^mpm_.*\.load$')" = "1"

# ---- PHP runtime settings -----------------------------------------
# The AI product generator makes three sequential Gemini calls and
# raises its own limit to 240s; the pool must allow at least that.
RUN { \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'error_log = /dev/stderr'; \
        echo 'max_execution_time = 300'; \
        echo 'upload_max_filesize = 8M'; \
        echo 'post_max_size = 10M'; \
        echo 'memory_limit = 256M'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# ---- Apache hardening ---------------------------------------------
# AllowOverride All: storage/.htaccess denies direct access to payment
#   proofs (RA 10173) and is a no-op without it.
# Options -Indexes: without this, uploads/ is browsable and every
#   customer receipt can be enumerated.
RUN { \
        echo '<Directory /var/www/html>'; \
        echo '    Options -Indexes +FollowSymLinks'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
        echo '<DirectoryMatch "/var/www/html/(storage|vendor|tests|scripts|docker)/">'; \
        echo '    Require all denied'; \
        echo '</DirectoryMatch>'; \
        echo '<FilesMatch "\.(sql|env|md|lock|bak)$">'; \
        echo '    Require all denied'; \
        echo '</FilesMatch>'; \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'ServerName localhost'; \
        echo '# TLS ends at the Railway proxy; tell PHP the request was https'; \
        echo '# so FORCE_HTTPS does not redirect-loop and cookies stay Secure.'; \
        echo 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on'; \
        echo '# Small worker pool: the free plan caps RAM at ~0.5 GB.'; \
        echo '<IfModule mpm_prefork_module>'; \
        echo '    StartServers 2'; \
        echo '    MinSpareServers 2'; \
        echo '    MaxSpareServers 4'; \
        echo '    MaxRequestWorkers 10'; \
        echo '    MaxConnectionsPerChild 1000'; \
        echo '</IfModule>'; \
    } > /etc/apache2/conf-available/zz-app.conf \
    && a2enconf zz-app

# ---- Dependencies --------------------------------------------------
# vendor/ is gitignored, so Composer must run during the build.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist \
        --optimize-autoloader --no-scripts

# ---- Application ---------------------------------------------------
COPY . .

# Writable trees. docker/start.sh moves them onto the Railway volume
# (mount it at /data) so uploads survive redeploys.
RUN mkdir -p \
        uploads/payments uploads/tryon uploads/designs \
        uploads/design_assets uploads/support \
        storage/payment-proofs storage/payment-config \
        images/products images/payment-qr \
    && chown -R www-data:www-data uploads storage images/products images/payment-qr \
    && chmod -R 775 uploads storage images/products images/payment-qr \
    && sed -i 's/\r$//' docker/start.sh \
    && chmod +x docker/start.sh

EXPOSE 8080

# Railway injects $PORT at runtime; start.sh points Apache at it.
CMD ["/var/www/html/docker/start.sh"]
