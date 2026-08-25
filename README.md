# 🐄 PecuáriaGest — Sistema de Gestão Pecuária

[![Docker Build](https://img.shields.io/badge/Docker-Ready-blue.svg?logo=docker)](https://www.docker.com/)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg?logo=php)](https://www.php.net/)
[![Nginx](https://img.shields.io/badge/Nginx-1.22-009639.svg?logo=nginx)](https://nginx.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

O **PecuáriaGest** é uma plataforma completa e moderna para gerenciamento de propriedades pecuárias e rebanhos de corte e leite. Desenvolvido para oferecer máxima eficiência na tomada de decisão, o sistema conta com controle de pesagens, manejo sanitário, gestão de pastagens, reprodução, relatórios analíticos e API para sincronização com aplicativos de campo.

---

## ✨ Funcionalidades Principais

- 🐂 **Gestão de Rebanho**: Cadastro completo de animais (brinco, raça, sexo, genealogia, fotos e status).
- ⚖️ **Histórico de Pesagens**: Monitoramento de ganho de peso, curvas de evolução e GMD (Ganho Médio Diário).
- 💉 **Manejo Sanitário**: Controle de vacinações, tratamentos, vermifugações e vencimentos de medicamentos.
- 🌾 **Gestão de Pastagens e Lotes**: Controle de capacidade de lotação por hectare e rotação de piquetes.
- 🧬 **Módulo Reprodutivo**: Registro de coberturas, inseminações, confirmação de prenhez e previsão de partos.
- 📊 **Dashboard & Relatórios**: Indicadores gráficos em tempo real e exportação de relatórios gerenciais.
- 📱 **API de Sincronização Mobile**: Endpoint seguro (`/api/sync`) para sincronização em lote de dados coletados offline no campo.

---

## 🏗️ Arquitetura do Projeto

```
Pecuaria-Gest/
├── .github/workflows/       # Automações CI/CD (lint & build Docker)
├── docker/                  # Configurações de container (Nginx + Entrypoint)
├── docs/                    # Documentação técnica e especificações da API
├── public/                  # Document Root público (index.php, CSS, assets)
├── src/                     # Núcleo da aplicação (views, regras, conexão DB)
│   ├── views/               # Telas do sistema organizadas por módulo
│   ├── auth.php             # Autenticação e controle de sessões
│   ├── config.php           # Tratamento de variáveis de ambiente
│   ├── db.php               # Gerenciador de banco de dados SQLite / PDO
│   └── helpers.php          # Funções utilitárias e segurança
├── storage/                 # Armazenamento persistente (banco de dados e uploads)
├── .env.example             # Modelo de variáveis de ambiente
├── .gitignore               # Regras de exclusão do Git
├── docker-compose.yml       # Orquestração do container em 1 comando
├── Dockerfile               # Imagem otimizada PHP 8.2 FPM + Nginx (~80MB)
└── README.md                # Documentação principal
```

---

## 🚀 Como Executar

### 1. Usando Docker (Recomendado)

Certifique-se de ter o [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado.

```bash
# 1. Clone o repositório
git clone https://github.com/SEU_USUARIO/Pecuaria-Gest.git
cd Pecuaria-Gest

# 2. Copie o arquivo de variáveis de ambiente
cp .env.example .env

# 3. Suba o container
docker compose up -d
```

Acesse a aplicação no navegador em:
👉 **[http://localhost:8080](http://localhost:8080)**

---

### 2. Desenvolvimento Local (PHP Embutido / XAMPP)

Se preferir rodar sem Docker, utilizando o PHP local:

```bash
# Inicie o servidor PHP apontando para a pasta public
php -S localhost:8080 -t public
```

---

## 🔑 Credenciais Iniciais de Acesso

Ao inicializar o sistema pela primeira vez, o banco de dados de demonstração é criado automaticamente com o seguinte usuário administrador:

| Campo | Valor Padrão |
|---|---|
| **E-mail** | `admin@fazenda.com` |
| **Senha** | `admin123` |

> ⚠️ **Importante**: Em ambientes de produção, altere as credenciais no arquivo `.env`.

---

## ⚙️ Variáveis de Ambiente

| Variável | Descrição | Valor Padrão |
|---|---|---|
| `APP_NAME` | Nome do sistema exibido na interface | `PecuáriaGest` |
| `APP_ENV` | Ambiente (`development` ou `production`) | `production` |
| `APP_PORT` | Porta HTTP exposta no host | `8080` |
| `API_KEY` | Chave de autenticação da API mobile | `pecuaria-mobile-key` |
| `SESSION_SECRET` | Chave de segurança para sessões | `pecuaria_secret_2026` |
| `DEFAULT_ADMIN_EMAIL`| E-mail inicial do administrador | `admin@fazenda.com` |
| `DEFAULT_ADMIN_PASS` | Senha inicial do administrador | `admin123` |

---

## 📱 Documentação da API

Para integrar aplicativos móveis com o PecuáriaGest, consulte a documentação detalhada dos endpoints:
📖 [Especificação da API Mobile (docs/API.md)](docs/API.md)

---

## 🛠️ Comandos Úteis do Docker

```bash
# Ver logs em tempo real
docker compose logs -f

# Parar a aplicação
docker compose down

# Reconstruir a imagem após alterações
docker compose up -d --build
```

---

## 📄 Licença

Este projeto está sob a licença [MIT](LICENSE).
