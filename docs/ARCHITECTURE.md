# PecuáriaGest — Arquitetura do Sistema

Documentação técnica da arquitetura, fluxo de dados, modelo de persistência e decisões de engenharia.

---

## 1. Visão Geral

O **PecuáriaGest** é um sistema web responsivo e resiliente projetado para gestão integral de propriedades pecuárias (rebanho, pesagens, manejo sanitário, reprodução e pastagens), com suporte a sincronização offline para dispositivos móveis.

```
                  ┌───────────────────────────────┐
                  │    App Mobile (Offline-first) │
                  └──────────────┬────────────────┘
                                 │
                                 │ HTTP POST /api/sync (X-API-KEY)
                                 ▼
┌─────────────────────────────────────────────────────────────┐
│                      PecuáriaGest Docker                    │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │                      Nginx 1.22                     │   │
│   │  - Servidor Web & Proxy Reverso                     │   │
│   │  - Roteamento Front Controller (index.php)          │   │
│   │  - Bloqueio de arquivos ocultos e storage direto    │   │
│   └──────────────────────────┬──────────────────────────┘   │
│                              │ FastCGI (127.0.0.1:9000)     │
│                              ▼                              │
│   ┌─────────────────────────────────────────────────────┐   │
│   │                     PHP 8.2-FPM                     │   │
│   │  - Autenticação e Gestão de Sessões                 │   │
│   │  - Roteamento e Regras de Negócio (src/)            │   │
│   │  - Renderização de Views com Bootstrap 5            │   │
│   └──────────────────────────┬──────────────────────────┘   │
│                              │ PDO SQLite                   │
│                              ▼                              │
│   ┌─────────────────────────────────────────────────────┐   │
│   │              SQLite 3 (/storage/data)               │   │
│   │  - Tabelas: animais, pesagens, saude, pastagens,    │   │
│   │             reproducao, alertas, usuarios           │   │
│   └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Padrão de Diretórios (Separation of Concerns)

* **`public/` (Document Root)**:
  Único diretório exposto ao servidor web. Contém o `index.php` (Front Controller) e os ativos estáticos (`assets/`).
* **`src/` (Core Logic)**:
  Contém as regras de negócio, autenticação, modelos de banco de dados e views PHP. Fica fora do Document Root para prevenir acessos diretos indesejados.
* **`storage/` (Persistência)**:
  Armazena arquivos SQLite e uploads. Mapeado como volume Docker (`./storage:/var/www/html/storage`) para garantir que os dados não sejam perdidos entre recriações de containers.
* **`docker/` (Infraestrutura)**:
  Scripts de inicialização e configurações do Nginx.

---

## 3. Segurança

1. **Proteção contra Injeção SQL**: 100% das operações de banco utilizam Prepared Statements do PHP PDO.
2. **Proteção contra CSRF**: Validação de token em formulários de autenticação e ações críticas.
3. **Escapamento XSS**: Funções auxiliares `e()` para sanitização de saída HTML nas views.
4. **Isolamento de Credenciais**: Configurações sensíveis parametrizadas via variáveis de ambiente (`.env`).
