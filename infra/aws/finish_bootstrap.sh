#!/usr/bin/env bash
set -e
exec > >(tee -a /var/log/pecuaria-bootstrap.log) 2>&1

echo ">>> [ETAPA 1] Baixando pacote do S3..."
APP_DIR="/opt/pecuaria-gest"
mkdir -p "$APP_DIR"
aws s3 cp s3://pecuaria-deploy-tcc-587620872560/pecuaria-deploy.tar.gz /tmp/pecuaria-deploy.tar.gz --region us-east-1
tar -xzf /tmp/pecuaria-deploy.tar.gz -C "$APP_DIR"
cd "$APP_DIR"

echo ">>> [ETAPA 2] Configurando .env para o Amazon RDS..."
cat << 'EOF' > "$APP_DIR/.env"
APP_NAME=PecuariaGest-AWS
APP_ENV=production
APP_PORT=8080
API_KEY=pecuaria-mobile-key
SESSION_SECRET=pecuaria_secret_aws_session_2026
BENCHMARK_SECRET=pecuaria-benchmark-secret-2026
DEFAULT_ADMIN_EMAIL=admin@fazenda.com
DEFAULT_ADMIN_PASS=admin123
DB_DRIVER=pgsql
DB_HOST=tcc-benchmark-postgres16.c9zbftik2z4d.us-east-1.rds.amazonaws.com
DB_PORT=5432
DB_DATABASE=pecuaria
DB_USERNAME=pecuaria_user
DB_PASSWORD=pecuaria_pass_2026
EOF

chown -R ubuntu:ubuntu "$APP_DIR"
chmod 600 "$APP_DIR/.env"

echo ">>> [ETAPA 3] Gerando docker-compose.aws.yml..."
cat << 'EOF' > "$APP_DIR/docker-compose.aws.yml"
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    image: pecuaria-gest-app:aws
    container_name: pecuaria-gest-app
    restart: always
    env_file: .env
    environment:
      - APP_NAME=PecuariaGest-AWS
      - APP_ENV=production
      - DB_DRIVER=pgsql
      - DB_PORT=5432
    volumes:
      - ./storage:/var/www/html/storage
      - ./public:/var/www/html/public
      - ./src:/var/www/html/src

  web:
    image: nginx:alpine
    container_name: pecuaria-gest-web
    restart: always
    ports:
      - "8080:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
      - ./storage/uploads:/var/www/html/storage/uploads:ro
    depends_on:
      - app
EOF

echo ">>> [ETAPA 4] Subindo contêineres Docker Nginx e PHP-FPM..."
docker compose -f "$APP_DIR/docker-compose.aws.yml" up -d --build

echo ">>> [ETAPA 5] Aguardando e inicializando banco de dados no RDS..."
for i in {1..30}; do
  if docker exec pecuaria-gest-app php -r "
    require '/var/www/html/src/db.php';
    try {
      \$pdo = getDb();
      initDb(\$pdo);
      echo 'SUCCESS';
    } catch (Exception \$e) {
      exit(1);
    }
  " 2>/dev/null | grep -q "SUCCESS"; then
    echo "SUCCESS: Banco RDS inicializado com sucesso!"
    break
  fi
  echo "Aguardando conexao com RDS... tentativa $i/30"
  sleep 3
done

echo "BOOTSTRAP_COMPLETED=$(date '+%Y-%m-%d %H:%M:%S')" > "$APP_DIR/BOOTSTRAP_READY"
echo "======================================================================"
echo "🎉 AMBIENTE PECUÁRIAGEST PRONTO E OPERACIONAL NA AWS!"
echo "======================================================================"
