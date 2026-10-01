#!/usr/bin/env bash
# ==============================================================================
# BOOTSTRAP AUTOMATIZADO DA INSTÂNCIA EC2 (CLOUD-INIT / USER DATA)
# PecuáriaGest - Arquitetura em Nuvem para TCC
# ==============================================================================
# Este script é executado automaticamente pelo Ubuntu no primeiro boot da EC2.
# Ele prepara todo o ambiente sem nenhuma necessidade de intervenção manual:
# 1. Atualiza o sistema operacional e instala o Docker Engine oficial.
# 2. Clona o repositório oficial do PecuáriaGest.
# 3. Monta o arquivo .env apontando para a instância Amazon RDS PostgreSQL 16.
# 4. Inicializa os contêineres Nginx e PHP 8.3 FPM via Docker Compose.
# 5. Executa as migrações e o seed de 30 animais base com o prefixo 'T'.
# ==============================================================================

set -euo pipefail
LOG_FILE="/var/log/pecuaria-bootstrap.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "======================================================================"
echo "🚀 [$(date '+%Y-%m-%d %H:%M:%S')] INICIANDO BOOTSTRAP DO PECUÁRIAGEST NA AWS"
echo "======================================================================"

# 1. Atualização de pacotes do sistema operacional Ubuntu 24.04 LTS
echo "📦 [1/6] Atualizando repositórios do sistema..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y ca-certificates curl gnupg lsb-release git htop jq

# 2. Instalação oficial do Docker CE e Docker Compose Plugin
echo "🐳 [2/6] Instalando Docker Engine oficial..."
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch="$(dpkg --print-architecture)" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  "$(. /etc/os-release && echo "$VERSION_CODENAME")" stable" | \
  tee /etc/apt/sources.list.d/docker.list > /dev/null

apt-get update -y
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

systemctl enable docker
systemctl start docker
usermod -aG docker ubuntu

# 3. Download da Aplicação a partir do Git
echo "📥 [3/6] Clonando repositório do PecuáriaGest (${GIT_BRANCH})..."
APP_DIR="/opt/pecuaria-gest"
rm -rf "$APP_DIR"
git clone -b "${GIT_BRANCH}" "${GITHUB_REPO_URL}" "$APP_DIR"
cd "$APP_DIR"

# 4. Configuração das Variáveis de Ambiente (.env) para a Nuvem AWS
echo "⚙️ [4/6] Gerando arquivo .env conectado ao Amazon RDS..."
cat << EOF > "$APP_DIR/.env"
APP_NAME=PecuariaGest-AWS
APP_ENV=production
APP_PORT=${APP_PORT}
API_KEY=${API_KEY}
SESSION_SECRET=pecuaria_secret_aws_session_2026
BENCHMARK_SECRET=${BENCHMARK_SECRET}
DEFAULT_ADMIN_EMAIL=admin@fazenda.com
DEFAULT_ADMIN_PASS=admin123
DB_DRIVER=pgsql
DB_HOST=${DB_HOST}
DB_PORT=5432
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
EOF

chown -R ubuntu:ubuntu "$APP_DIR"
chmod 600 "$APP_DIR/.env"

# 5. Configuração do Docker Compose Dedicado para a Nuvem (Sem banco local)
# Na AWS, o banco de dados é o Amazon RDS gerenciado externamente.
# Portanto, a EC2 precisa executar apenas os contêineres do Nginx e do PHP 8.3 FPM.
echo "🏗️ [5/6] Construindo e subindo contêineres Nginx e PHP-FPM..."
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
      - "${APP_PORT}:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
      - ./storage/uploads:/var/www/html/storage/uploads:ro
    depends_on:
      - app
EOF

docker compose -f "$APP_DIR/docker-compose.aws.yml" up -d --build

# 6. Aguarda disponibilidade do RDS e inicializa esquema de tabelas e dados seed
echo "⏳ [6/6] Aguardando inicialização completa do banco de dados RDS..."
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
    echo "✅ Banco de Dados RDS conectado e inicializado com sucesso (Tabelas e Seed 'T' criados)!"
    break
  fi
  echo "Aguardando conexão com RDS... tentativa $i/30"
  sleep 4
done

# Cria arquivo sinalizador de conclusão com carimbo de data/hora
echo "BOOTSTRAP_COMPLETED=$(date '+%Y-%m-%d %H:%M:%S')" > "$APP_DIR/BOOTSTRAP_READY"
echo "======================================================================"
echo "🎉 [$(date '+%Y-%m-%d %H:%M:%S')] AMBIENTE PECUÁRIAGEST PRONTO NA AWS!"
echo "======================================================================"
