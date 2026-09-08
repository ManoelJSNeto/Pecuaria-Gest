# 🖥️ Camada de Visualização (Views) — `src/views/`

Este diretório abriga todos os templates de interface do usuário do Painel Web, gerados via **Server-Side Rendering (SSR)** para máxima velocidade e leveza.

---

## 📐 Estrutura de Layout e Herança

As telas não repetem a estrutura de `<html>`, `<head>` ou barra de navegação. A renderização é orquestrada pela função:
```php
renderView(string $view, string $titulo, string $paginaAtiva, array $dados = [], ?string $scriptsAdicionais = null);
```
O conteúdo específico da view é injetado na variável `$content` dentro do template mestre [src/views/layout.php](file:///c:/xampp/htdocs/ondeSalvaWeb_XAMPP/Pecuaria-Gest/Pecuaria-Gest/src/views/layout.php).

---

## 📁 Mapeamento das Telas por Módulo

| Diretório / Arquivo | Finalidade e Conteúdo |
|---|---|
| **`layout.php`** | **Master Template:** Navbar responsiva, alternador global de unidade de peso (`kg` vs `@`), mensagens de feedback (`flash`), modais e importação de CSS/JS otimizados. |
| **`login.php`** | Tela de entrada com blindagem contra força bruta, feedback de tentativas restantes e token Anti-CSRF. |
| **`dashboard.php`** | Cockpit executivo do produtor com cartões de contagem, alertas sanitários urgentes e gráfico interativo (Chart.js) de tendência de peso. |
| **`animais/`** | `index.php` (tabela de rebanho com filtros e status), `form.php` (cadastro com validação zootécnica) e `detalhes.php` (ficha biológica completa, galeria de fotos e linha do tempo de manejo). |
| **`compras/`** | Cockpit de aquisição de gado (`index.php`) e formulário com importação inteligente de **XML da NF-e** e validação de GTA (`form.php`). |
| **`vendas/`** | Cockpit comercial de faturamento/abate (`index.php`) e formulário com cálculo automático de peso vivo para arroba líquida (`form.php`). |
| **`pesagens/`** | Histórico cronológico de pesagens (`index.php`) e registro manual de pesagem rápida (`form.php`). |
| **`saude/`** | Prontuário veterinário (`index.php`), registro de vacinas/tratamentos (`form.php`) e visualizador com desfoque de segurança para fotos cirúrgicas/óbito. |
| **`pastagens/`** | Controle de áreas, capacidade de suporte e cálculo automático da taxa de lotação (UA/ha). |
| **`reproducao/`** | Controle de IA, coberturas naturais, diagnósticos de gestação e acompanhamento de partos. |
| **`alertas/`** | Central de avisos do sistema e notificações de dados recém-descarregados pelo app móvel. |
| **`relatorios/`** | Painéis analíticos e folhas de impressão para auditoria, controle fiscal e zootécnico. |
| **`usuarios/`** | Gestão de colaboradores e matriz de permissões granulares de acesso. |
| **`configuracoes/`** | Dados da propriedade rural e teste de conectividade com servidor SMTP. |

---

## 🎨 Princípios do Design System PecuáriaGest
1. **Paleta Terrosa:** Uso do verde agro `#1a4d2e` e `#26441F`, garantindo identidade visual de agronegócio e evitando cores primárias genéricas.
2. **Cockpit Unificado (`.metric-cockpit`):** No topo de cada módulo comercial e de rebanho, cartões em grade exibem métricas resumidas (cabeças, valores médios, peso total).
3. **Alternância de Unidade (@ / kg):** Todo valor de peso exibido no HTML deve respeitar as classes `peso-kg` e `peso-arroba`, permitindo ao usuário alternar a unidade em tempo real com um clique na barra superior.
