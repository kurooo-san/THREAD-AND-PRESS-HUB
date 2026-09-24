#!/bin/sh
# Container entrypoint: bind Apache to Railway's $PORT, move writable
# trees onto the Railway volume (if one is attached), then start Apache.
set -e

# Railway can end up with a second MPM enabled at runtime even though the
# build asserted exactly one ("AH00534: More than one MPM loaded"). mod_php
# needs prefork, so drop the others right before Apache starts.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
a2enmod -q mpm_prefork >/dev/null
echo "MPMs enabled: $(ls /etc/apache2/mods-enabled/ | grep '^mpm_.*\.load$' | tr '\n' ' ')"

PORT="${PORT:-8080}"
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Railway sets RAILWAY_VOLUME_MOUNT_PATH when a volume is attached. The
# free plan allows one volume per service, so every writable directory
# lives under it and is symlinked back into the web root. Without a
# volume, uploads still work but are wiped on every redeploy.
if [ -n "$RAILWAY_VOLUME_MOUNT_PATH" ]; then
    for dir in uploads storage/payment-proofs storage/payment-config images/products images/payment-qr; do
        src="/var/www/html/$dir"
        dst="$RAILWAY_VOLUME_MOUNT_PATH/$dir"
        mkdir -p "$dst"
        if [ ! -L "$src" ]; then
            # Seed with files shipped in the image; never overwrite uploads.
            [ -d "$src" ] && cp -an "$src/." "$dst/"
            rm -rf "$src"
            ln -s "$dst" "$src"
        fi
    done
    chown -R www-data:www-data "$RAILWAY_VOLUME_MOUNT_PATH"
fi

exec apache2-foreground
