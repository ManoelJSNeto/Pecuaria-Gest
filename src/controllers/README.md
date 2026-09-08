# 🎮 Camada de Controladores (Controllers) — `src/controllers/`

Este diretório estabelece a arquitetura modular para desacoplar as rotas e regras de negócio que hoje residem no Front Controller (`public/index.php`).

---

## 🎯 Objetivo da Transição

Transformar o Front Controller de 1.400 linhas em um roteador enxuto de ~50 linhas, delegando cada responsabilidade de domínio para uma classe controladora dedicada:

```
[ Requisição HTTP ] ──► [ public/index.php (Roteador Leve) ]
                                      │
       ┌──────────────────────────────┼──────────────────────────────┐
       ▼                              ▼                              ▼
AuthController               AnimaisController              ComercialController
(Login, Logout, RateLimit)   (Fichas, Genealogia)           (Compras, Vendas, XMLs)
```

---

## 🏗️ Classe Base: `BaseController.php`

Todos os controladores herdam de `BaseController`, que disponibiliza atalhos prontos para:
* `$this->db`: Conexão PDO com PostgreSQL ativa.
* `$this->render($view, $title, $page, $data)`: Renderização com template mestre.
* `$this->json($data, $statusCode)`: Resposta JSON para APIs.
* `$this->redirect($url)`: Redirecionamentos seguros.
* `$this->validateCsrf()`: Validação do token Anti-CSRF.
* `$this->requireLogin()` e `$this->requirePermission($perm)`: Guarda de segurança.

---

## 📋 Mapa de Controladores Planejados para Extração

| Controlador | Módulos e Rotas Atendidas |
|---|---|
| **`AuthController.php`** | `/login`, `/logout`, verificação de tentativas e sessões. |
| **`DashboardController.php`** | `/dashboard`, cálculo de KPIs e consolidação de gráficos. |
| **`AnimaisController.php`** | `/animais`, `/animais/novo`, `/animais/criar`, `/animais/detalhes/{id}`. |
| **`ComercialController.php`** | `/compras`, `/compras/novo`, `/vendas`, `/vendas/novo`, processamento de XMLs de NF-e e GTAs. |
| **`PesagensController.php`** | `/pesagens`, `/pesagens/novo`, cálculo de GMD (Ganho Médio Diário). |
| **`SaudeController.php`** | `/saude`, `/saude/novo`, prontuários clínicos e laudos com desfoque de imagem. |
| **`PastagensController.php`** | `/pastagens`, `/pastagens/novo`, taxa de lotação por hectare. |
| **`ReproducaoController.php`** | `/reproducao`, `/reproducao/novo`, inseminações, coberturas e partos. |
| **`AlertasController.php`** | `/alertas`, avisos de sistema e notificações de sincronização. |
| **`RelatoriosController.php`** | `/relatorios`, filtros e exportação. |
| **`UsuariosController.php`** | `/usuarios`, controle de acesso RBAC e gestão de equipe. |
| **`ConfiguracoesController.php`**| `/configuracoes`, teste de e-mail SMTP e parâmetros rurais. |
| **`ApiController.php`** | `/api/sync`, `/api/animais` (comunicação com o aplicativo Android). |
