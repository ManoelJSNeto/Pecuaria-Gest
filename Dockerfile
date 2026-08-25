# ============================================================
# PecuáriaGest — Sistema de Gestão Pecuária
# Dockerfile Otimizado (PHP 8.2 FPM + Nginx)
# ============================================================

FROM debian:bookworm-slim

LABEL maintainer="PecuáriaGest Team"
LABEL description="Sistema Web de Gestão Pecuária"

# Evita prompts interativos durante a instalação
ENV DEBIAN_FRONTEND=noninteractive

# Instala Nginx, PHP-FPM e extensões necessárias (SQLite, cURL, mbstring, etc.)
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    php-fpm \
    php-sqlite3 \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Configura o PHP-FPM para ouvir na porta TCP local 127.0.0.1:9000
RUN sed -i 's|listen = .*|listen = 127.0.0.1:9000|' /etc/php/*/fpm/pool.d/www.conf

WORKDIR /var/www/html

# Copia o código da aplicação
COPY public/ ./public/
COPY src/ ./src/

# Cria a estrutura de armazenamento persistente e ajusta permissões
RUN mkdir -p /var/www/html/storage/data /var/www/html/storage/uploads /run/php && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/storage

# Configura Nginx e Entrypoint
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Expõe a porta padrão HTTP
EXPOSE 80

ENTRYPOINT ["/docker-entrypoint.sh"]
