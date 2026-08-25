# PecuáriaGest — Guia de Deploy em EC2 (AWS)

> Sistema de gestão pecuária — Stack: **React + Vite · Node.js (Express 5) · PostgreSQL · Nginx · Docker**

---

## Índice

1. [Arquitetura da solução](#1-arquitetura-da-solução)
2. [Pré-requisitos](#2-pré-requisitos)
3. [Configurar a instância EC2](#3-configurar-a-instância-ec2)
4. [Instalar Docker na EC2](#4-instalar-docker-na-ec2)
5. [Clonar o repositório](#5-clonar-o-repositório)
6. [Configurar variáveis de ambiente](#6-configurar-variáveis-de-ambiente)
7. [Executar a aplicação](#7-executar-a-aplicação)
8. [Inicializar o banco de dados](#8-inicializar-o-banco-de-dados)
9. [Verificar se está rodando](#9-verificar-se-está-rodando)
10. [Domínio e HTTPS (opcional)](#10-domínio-e-https-opcional)
11. [Atualizar a aplicação](#11-atualizar-a-aplicação)
12. [Solução de problemas](#12-solução-de-problemas)

---

## 1. Arquitetura da Solução

```
Internet
    │
    ▼
┌─────────────┐
│  EC2 :80    │  Nginx (container)
│             │  ─ Serve o React (SPA estática)
│             │  ─ Proxy /api/* → localhost:5000
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Node.js    │  Express 5 (container — porta 5000)
│  API Server │  Drizzle ORM + PostgreSQL
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  PostgreSQL │  Container (porta 5432 — interna)
└─────────────┘
```

---

## 2. Pré-requisitos

| Item | Detalhe |
|------|---------|
| **Conta AWS** | Com permissão para criar instâncias EC2 e Security Groups |
| **Par de chaves SSH** | Arquivo `.pem` gerado no console AWS |
| **Git** | Repositório do projeto disponível (GitHub, GitLab, etc.) |
| **Conhecimento básico** | Terminal Linux e Docker |

---

## 3. Configurar a Instância EC2

### 3.1 Criar a instância

1. No **AWS Console** → **EC2** → **Launch Instance**
2. Escolha a AMI: **Amazon Linux 2023** ou **Ubuntu 24.04 LTS** *(recomendado)*
3. Tipo de instância: **t3.small** (mínimo) | **t3.medium** (recomendado)
4. Configure o **Security Group** com as regras abaixo:

### 3.2 Security Group (regras de entrada)

| Tipo | Protocolo | Porta | Origem |
|------|-----------|-------|--------|
| SSH  | TCP | 22 | Seu IP `/32` |
| HTTP | TCP | 80 | `0.0.0.0/0` |
| HTTPS | TCP | 443 | `0.0.0.0/0` (se usar SSL) |

> [!CAUTION]
> **Não abra** a porta `5432` (PostgreSQL) para a internet. O banco deve ser acessível apenas internamente via Docker network.

### 3.3 Conectar à instância

```bash
chmod 400 sua-chave.pem
ssh -i sua-chave.pem ubuntu@<IP-PÚBLICO-DA-EC2>
```

---

## 4. Instalar Docker na EC2

### Ubuntu (recomendado)

```bash
# Atualiza pacotes
sudo apt-get update -y

# Instala dependências
sudo apt-get install -y ca-certificates curl gnupg lsb-release

# Adiciona repositório oficial Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | \
  sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update -y

# Instala Docker Engine + Compose Plugin
sudo apt-get install -y docker-ce docker-ce-cli containerd.io \
  docker-buildx-plugin docker-compose-plugin

# Adiciona usuário ao grupo docker (não precisa de sudo)
sudo usermod -aG docker $USER
newgrp docker

# Valida instalação
docker --version
docker compose version
```

### Amazon Linux 2023

```bash
sudo dnf update -y
sudo dnf install -y docker
sudo systemctl enable docker --now
sudo usermod -aG docker $USER
newgrp docker

# Instala Docker Compose Plugin
DOCKER_CONFIG=${DOCKER_CONFIG:-$HOME/.docker}
mkdir -p $DOCKER_CONFIG/cli-plugins
curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 \
  -o $DOCKER_CONFIG/cli-plugins/docker-compose
chmod +x $DOCKER_CONFIG/cli-plugins/docker-compose
```

---

## 5. Clonar o Repositório

```bash
# Instala Git se necessário
sudo apt-get install -y git   # Ubuntu

# Clona o projeto
git clone https://github.com/seu-usuario/pecuaria-gest.git
cd pecuaria-gest
```

> [!NOTE]
> Se o repositório for privado, use SSH ou um **Personal Access Token** do GitHub:
> ```bash
> git clone https://seu-token@github.com/seu-usuario/pecuaria-gest.git
> ```

---

## 6. Configurar Variáveis de Ambiente

Crie o arquivo `.env` na **raiz do projeto** (mesmo diretório do `docker-compose.yml`):

```bash
nano .env
```

Cole o conteúdo abaixo e preencha os valores:

```dotenv
# ── PostgreSQL ────────────────────────────────────────────────
POSTGRES_DB=pecuaria
POSTGRES_USER=pecuaria_user
POSTGRES_PASSWORD=TROQUE_POR_UMA_SENHA_FORTE_AQUI

# ── Construída automaticamente pelo docker-compose.yml ────────
# DATABASE_URL=postgres://pecuaria_user:SENHA@db:5432/pecuaria

# ── API Server ────────────────────────────────────────────────
PORT=5000
NODE_ENV=production
```

> [!WARNING]
> **Nunca faça commit do arquivo `.env`** com senhas reais. O `.gitignore` já está configurado para ignorá-lo.

---

## 7. Executar a Aplicação

```bash
# Constrói as imagens e sobe todos os serviços em background
docker compose up --build -d

# Acompanha os logs em tempo real (Ctrl+C para sair)
docker compose logs -f
```

O primeiro build pode levar **3–5 minutos** pois instala todas as dependências.

---

## 8. Inicializar o Banco de Dados

Após os containers subirem, execute as migrations do Drizzle ORM para criar as tabelas:

```bash
# Aguarda o PostgreSQL estar saudável
docker compose ps   # certifique que 'db' mostra "healthy"

# Executa o push do schema Drizzle
docker compose exec app \
  sh -c "DATABASE_URL=$DATABASE_URL pnpm --filter @workspace/db run push"
```

> [!IMPORTANT]
> Só precisa rodar na **primeira vez** ou quando houver mudanças no schema do banco.

---

## 9. Verificar se está Rodando

```bash
# Status dos containers
docker compose ps

# Health check da API
curl http://localhost/api/health

# Acesse no navegador
# http://<IP-PÚBLICO-DA-EC2>
```

Saída esperada do `docker compose ps`:

```
NAME             STATUS          PORTS
pecuaria-app     Up (healthy)    0.0.0.0:80->80/tcp
pecuaria-db      Up (healthy)    5432/tcp
```

---

## 10. Domínio e HTTPS (Opcional)

### 10.1 Apontar domínio para a EC2

No seu provedor de DNS, crie um **registro A**:
```
Tipo: A
Nome: @  (ou subdomínio, ex: app)
Valor: <IP-PÚBLICO-DA-EC2>
TTL: 300
```

### 10.2 Habilitar HTTPS com Certbot (Let's Encrypt)

```bash
# Instala Certbot no host
sudo apt-get install -y certbot python3-certbot-nginx

# Para temporariamente o Nginx do container
docker compose stop app

# Obtém o certificado (substitua seu-dominio.com)
sudo certbot certonly --standalone -d seu-dominio.com

# Atualize o nginx.conf para usar SSL e reinicie
docker compose up -d
```

> [!TIP]
> Para ambientes de produção, considere usar **AWS Certificate Manager (ACM)** com um **Application Load Balancer** para terminar o SSL fora da instância EC2.

---

## 11. Atualizar a Aplicação

```bash
cd pecuaria-gest

# Puxa as últimas mudanças
git pull origin main

# Rebuilda e reinicia apenas os containers alterados
docker compose up --build -d

# Se houver mudanças no schema do banco
docker compose exec app \
  sh -c "DATABASE_URL=$DATABASE_URL pnpm --filter @workspace/db run push"
```

---

## 12. Solução de Problemas

### Container não sobe — ver logs de erro

```bash
docker compose logs app
docker compose logs db
```

### Erro: `PORT environment variable is required`

Certifique-se que o arquivo `.env` foi criado e contém `PORT=5000`.

### Erro: `DATABASE_URL` inválida

Verifique se `POSTGRES_PASSWORD` no `.env` não contém caracteres especiais sem escape (ex: `@`, `#`). Se houver, envolva com aspas duplas no `.env`.

### Container reiniciando constantemente

```bash
# Inspeciona detalhes do container
docker inspect pecuaria-app | grep -A 10 '"State"'

# Reinicia forçado
docker compose restart app
```

### Recriar tudo do zero (dados do banco serão perdidos!)

```bash
docker compose down -v   # remove containers + volumes
docker compose up --build -d
```

### Verificar uso de recursos

```bash
docker stats
```

---

## Arquivos Docker do Projeto

| Arquivo | Descrição |
|---------|-----------|
| `Dockerfile` | Build multi-stage: deps → build API → build Web → imagem final |
| `docker-compose.yml` | Orquestração: app (Nginx+Node) + PostgreSQL |
| `nginx.conf` | Proxy reverso para a API + serving SPA |
| `docker-entrypoint.sh` | Inicia API e Nginx dentro do container |
| `.env` | Variáveis sensíveis (**não versionar**) |

---

*Gerado automaticamente — PecuáriaGest Deploy Guide*
