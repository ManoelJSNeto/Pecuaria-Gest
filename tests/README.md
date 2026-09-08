# 🧪 Bateria de Testes Automatizados — `tests/`

Este diretório contém a suíte de testes de integração, auditoria e validação de rotas do sistema **PecuáriaGest**.

---

## 📋 Arquivos de Teste

| Arquivo | Escopo e Finalidade |
|---|---|
| **`test_integration.js`** | Teste completo de integração HTTP contra o ambiente Docker (porta 8080). Simula o ciclo completo de login, valida o envio de formulários, testa as **21 rotas autenticadas do painel** e confere a persistência de registros de sincronização no PostgreSQL. |

---

## 🚀 Como Executar os Testes

Com os contêineres Docker rodando (`docker compose up -d`), execute no terminal da raiz do projeto:

```bash
# Executa a bateria completa de integração
node tests/test_integration.js
```

### O que o teste valida:
1. Resposta `HTTP 200` na tela de login.
2. Autenticação com credenciais legítimas (`admin@fazenda.com`) e redirecionamento para o dashboard (`HTTP 302 -> /dashboard`).
3. Renderização correta sem erros SQL em todas as 21 rotas do sistema (Animais, Compras com XML, Vendas, Pesagens, Saúde, Pastagens, Reprodução, Alertas, Relatórios, etc.).
4. Chamada de API autenticada em `POST /api/sync` simulando o envio de dados do aplicativo Android.
5. Verificação da recuperação de dados via `GET /api/animais`.

---

## 🛡️ Critérios de Aceite para Deploy
Nenhum commit deve ser mesclado na branch principal sem que o comando `node tests/test_integration.js` retorne:
```
=== BATERIA COMPLETA CONCLUÍDA COM 100% DE SUCESSO ===
```
