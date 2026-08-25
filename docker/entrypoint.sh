#!/bin/sh
set -e

# Garante que os diretórios de armazenamento e logs existam e tenham as permissões corretas
mkdir -p /var/www/html/storage/data /var/www/html/storage/uploads /run/php /var/log/nginx
chown -R www-data:www-data /var/www/html/storage /run/php
chmod -R 775 /var/www/html/storage

# ── 1. Inicia o PHP-FPM ───────────────────────────────────────
echo "[INFO] Iniciando serviço PHP-FPM..."
php_service=$(ls /etc/init.d/php*-fpm 2>/dev/null | head -n1 | xargs -r basename)
if [ -n "$php_service" ]; then
    service "$php_service" start
else
    php-fpm -D 2>/dev/null || php-fpm8.2 -D 2>/dev/null || true
fi

# ── 2. Inicia o Nginx em primeiro plano ───────────────────────
echo "[INFO] Iniciando Nginx..."
exec nginx -g "daemon off;"
