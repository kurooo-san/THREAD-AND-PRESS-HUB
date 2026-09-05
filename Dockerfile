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
        echo '<DirectoryMatch "/var/www/html/(storage|vendor|tests|scripts)/">'; \
        echo '    Require all denied'; \
        echo '</DirectoryMatch>'; \
        echo '<FilesMatch "\.(sql|env|md|lock|bak)$">'; \
        echo '    Require all denied'; \
        echo '</FilesMatch>'; \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'ServerName localhost'; \
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

# Writable trees. Mount a Railway volume over /var/www/html/uploads or
# these are wiped on every redeploy.
RUN mkdir -p \
        uploads/payments uploads/tryon uploads/designs \
        uploads/design_assets uploads/support \
        storage/payment-proofs storage/payment-config \
        images/products \
    && chown -R www-data:www-data uploads storage images/products \
    && chmod -R 775 uploads storage images/products

EXPOSE 8080

# Railway injects $PORT at runtime, so Apache is pointed at it on start
# rather than baked to 80.
CMD ["sh", "-c", "sed -ri \"s/^Listen 80$/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf && sed -ri \"s/:80>/:${PORT:-8080}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
