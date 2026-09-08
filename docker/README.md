# 🐳 Guia de Infraestrutura Docker — PecuáriaGest

Este diretório contém os manifestos e arquivos de configuração para a execução do ecossistema **PecuáriaGest** em contêineres desacoplados.

---

## 🏗️ Topologia dos Contêineres

O ambiente é orquestrado via `docker-compose.yml` e composto por 3 serviços especializados em rede interna isolada:

```
[ Usuário / Navegador / App Android ]
                 │ (Porta 8080)
                 ▼
     ┌───────────────────────┐
     │   pecuaria-gest-web   │  (Nginx 1.25 Alpine - Proxy Reverso)
     │   - Compressão Gzip   │  - Entrega de estáticos (CSS/JS/Fotos/XMLs)
     │   - Cabeçalhos Cache  │  - Bloqueio de arquivos ocultos (.git, .env)
     └───────────┬───────────┘
                 │ (FastCGI: app:9000)
                 ▼
     ┌───────────────────────┐
     │   pecuaria-gest-app   │  (PHP 8.3-FPM Alpine - Processador de Backend)
     │   - Regras de negócio │  - Sessões, CSRF, Rate Limiting
     │   - Extensões pgsql/gd│  - Renderização de views (SSR)
     └───────────┬───────────┘
                 │ (Rede interna: db:5432)
                 ▼
     ┌───────────────────────┐
     │   pecuaria-gest-db    │  (PostgreSQL 16 Alpine - Banco Relacional)
     │   - Volume 'pgdata'   │  - Porta 5432 PRIVADA (não exposta para fora)
     │   - Healthcheck ativo │  - Índices B-Tree de alta performance
     └───────────────────────┘
```

---

## 📁 Estrutura de Arquivos

* **`nginx/default.conf`**: Configuração do Nginx com Gzip (nível 5), cache estático de 30 dias para fotos/anexos, e repasse FastCGI para o PHP-FPM.
* **`php/Dockerfile`**: Construção da imagem PHP 8.3-FPM com as extensões `pdo_pgsql`, `pgsql`, `gd`, `zip`, `bcmath` e utilitários de sistema.
* **`entrypoint.sh`**: Script executado na inicialização do contêiner PHP para ajuste de permissões nas pastas `storage/` e validação do ambiente.

---

## 🚀 Comandos de Operação

### Subir os contêineres em segundo plano:
```bash
docker compose up -d
```

### Parar os contêineres:
```bash
docker compose down
```

### Recarregar o Nginx após alterar `default.conf` (sem parar a aplicação):
```bash
docker exec pecuaria-gest-web nginx -s reload
```

### Acessar o terminal do contêiner PHP:
```bash
docker exec -it pecuaria-gest-app sh
```

### Acessar o terminal interativo do PostgreSQL (`psql`):
```bash
docker exec -it pecuaria-gest-db psql -U pecuaria_user -d pecuaria
```

### Monitorar logs em tempo real:
```bash
docker compose logs -f app
docker compose logs -f web
```

---

## 🔒 Segurança de Infraestrutura
1. **Porta 5432 Não Exposta:** O PostgreSQL não possui mapeamento de porta externa no host, impossibilitando ataques diretos pela internet.
2. **Raiz Web Restrita:** O Nginx aponta seu `root` exclusivamente para `/var/www/html/public`. O diretório `src/`, scripts internos e variáveis `.env` jamais são servidos diretamente.
