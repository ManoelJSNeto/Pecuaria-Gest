# 📊 PecuáriaGest — Status do Projeto & Roteiro do TCC

> **Data da Última Atualização:** 27/08/2026  
> **Status Geral do Sistema:** Painel Web 100% Funcional; App Nativo Android (Capacitor) criado e integrado com workflow CI/CD no GitHub Actions para geração automática do APK.

---

> [!NOTE]
> ### 📱 DECISÃO ARQUITETURAL DO TCC: Transição de PWA para App Híbrido Nativo (Capacitor)
> **Estudo de Caso & Fundamentação Teórica para a Apresentação do TCC:**
> 1. **Limitações Práticas do PWA em Campo Descobertas nos Testes:**
>    - *Mecanismo WebAPK do Android:* Ao instalar o PWA via Chrome, o Android gera um WebAPK isolado cujo ciclo de atualização ocorre em background a cada 24h, gerando inconsistência de cache em desenvolvimento e travamento de foco/touch em cold start offline.
>    - *Conflito SSR (PHP) vs App Shell:* Ambientes que misturam páginas renderizadas no servidor com Service Workers geram dependência de contexto de rede na inicialização.
> 2. **Solução Adotada — App Nativo com Capacitor:**
>    - Criada a pasta [`mobile-app/`](file:///c:/xampp/htdocs/ondeSalvaWeb_XAMPP/Pecuaria-Gest/Pecuaria-Gest/mobile-app) empacotando os assets web diretamente dentro do APK (`android_asset/public/`).
>    - **Zero Service Worker & Zero WebAPK:** Abertura instantânea (0ms), toque 100% livre e persistência local garantida.
>    - **Esteira de Build Nuvem:** Workflow [`.github/workflows/build-apk.yml`](file:///c:/xampp/htdocs/ondeSalvaWeb_XAMPP/Pecuaria-Gest/Pecuaria-Gest/.github/workflows/build-apk.yml) que compila automaticamente o APK Android a cada `git push`.

---

## 📌 1. Visão Geral do Projeto

O **PecuáriaGest** é um sistema completo de gestão agropecuária e manejo de rebanho com suporte a funcionamento híbrido (online/offline) para trabalhadores em campo e painel consolidado para gerentes/proprietários.

O sistema foi preparado para ser o objeto de estudo de caso comparativo do TCC:
- **Ambiente On-Premise:** Docker container local conectado a PostgreSQL/SQLite.
- **Ambiente Nuvem (AWS):** Docker container rodando em **Amazon EC2** conectado ao banco gerenciado **Amazon RDS (PostgreSQL)**.

---

## ✅ 2. O Que Já Foi Implementado & Validado

### A. Painel Web de Gestão (Proprietário / Gerente)
- [x] **Dashboard:** Métricas consolidadas (Total de Rebanho, Animais Ativos, Doentes, Prenhas, Pastagens Ativas, Alertas Pendentes) e gráficos Chart.js de evolução de peso e distribuição de raças.
- [x] **CRUD Completo de Animais:** Cadastro, visualização detalhada com árvore genealógica, filtros por pastagem e busca textual rápida.
- [x] **CRUD de Pesagens:** Registro, edição, histórico cronológico e cálculo automático de ganho/perda de peso entre medições.
- [x] **CRUD de Saúde & Manejo:** Registro de vacinações, tratamentos, exames, vermifugações, dosagens, custos e controle de próximas datas.
- [x] **CRUD de Reprodução:** Registro e controle de coberturas, inseminações artificiais, diagnósticos de gestação e partos.
- [x] **CRUD de Pastagens:** Controle de áreas (hectares), capacidade de suporte, status de ocupação e listagem dos animais por lote.
- [x] **Central de Alertas:** Notificações automáticas com botão para *"Marcar todos como lidos"*.
- [x] **Exportação de Relatórios:** Download de dados em formato CSV para Animais, Pesagens, Saúde, Reprodução e Pastagens.
- [x] **Segurança e Sessão:** Autenticação de usuários com hash seguro (`bcrypt`), proteção contra CSRF em todos os formulários e logout seguro.

### B. Linha do Tempo Fotográfica & Regras Visuais Especiais
- [x] **Galeria & Linha do Tempo Visual (`fotos_animais`):** Registro de fotos vinculadas a eventos de nascimento, pesagem, saúde, óbito ou fotos de perfil.
- [x] **Memória de Bezerro / Filhote:** Detecção automática de animais com idade $\le 12$ meses com selo *"🌱 Bezerro / Filhote"*. A primeira foto de filhote é preservada no cartão *"Memória de Filhote"* mesmo após o animal se tornar adulto.
- [x] **Censura Automática (Óbito / Conteúdo Sensível):** Fotos de animais mortos ou marcadas como sensíveis recebem desfoque visual forte por padrão (`filter: blur(18px)`) com aviso *"⚠️ Conteúdo Sensível — Clique para ver"* e revelação interativa com um clique.

### C. Módulo Mobile PWA Offline (`/campo`)
- [x] **Eliminação Total de CDNs Externas:** Download local de todos os assets (`public/assets/vendor/bootstrap/`, `public/assets/vendor/bootstrap-icons/` com fontes WOFF2 e `chartjs/`).
- [x] **Web App Manifest Atualizado (`public/manifest.json`):** Configurado com `id: "/campo"`, `scope: "/"`, `display: "standalone"` e propósitos `any` e `maskable`.
- [x] **Interface em Abas Táteis Diretas:** Redesenho de `/campo` com abas para Pesagem, Bezerro, Saúde e Fila de Sincronização, removendo dependência de modais suspensos.
- [x] **Engine Offline em IndexedDB (`public/assets/js/pwa-campo.js`):** Gravação local de registros com suporte a fotos comprimidas via Canvas.
- [x] **Sincronização com Nuvem (`/api/sync`):** Suporte a envio autenticado de dados e fotos para o servidor central com validação de credenciais de usuário.
- [ ] **Estabilização do Cold Start Offline:** Resolver o congelamento da interface quando aberto sem internet diretamente pelo atalho do celular.

### D. Infraestrutura & Banco de Dados
- [x] **Suporte a Banco Dual (SQLite + PostgreSQL):** Configuração em `src/config.php` e `src/db.php` que alterna automaticamente entre SQLite (local) e PostgreSQL (AWS RDS).
- [x] **Ambiente Docker Otimizado (`Dockerfile` + `docker-compose.yml`):** Imagem Debian Slim com PHP 8.2 FPM, Nginx, `php-pgsql` e `php-sqlite3`, com espelhamento de volumes (`./public` e `./src`) para desenvolvimento em tempo real.

---

## 👥 3. Planejamento da Hierarquia de Usuários & Controle de Acesso (RBAC)

Para atender aos requisitos de segurança e governança de dados da fazenda e enriquecer o TCC, foi desenhada a seguinte estrutura de perfis de usuário:

| Perfil | Destinatário Principal | Telas e Recursos Permitidos | Restrições de Acesso |
|---|---|---|---|
| **`admin` / `gerente`** | Proprietário, Administrador, Gerente Geral | Acesso irrestrito a todo o sistema: Dashboard consolidado, Relatórios analíticos com CSV, Custos de medicamentos, Gestão de Pastos, Central de Alertas, Histórico completo e Gerenciamento de Usuários. | Nenhuma restrição. |
| **`campo` / `trabalhador`** | Peão, Campeiro, Operador de Manejo | Acesso direto e exclusivo ao **Modo Campo PWA (`/campo`)**. Registro de pesagens, nascimentos de bezerros, manejos sanitários e sincronização offline. | Sem acesso ao Dashboard, faturamento, custos de medicamentos, relatórios gerenciais ou exclusão definitiva de animais. |
| **`veterinario`** | Médico Veterinário, Zootecnista | Acesso a Fichas de Animais, Histórico de Pesagens, Módulo de Saúde & Vacinas, Módulo Reprodutivo e Alertas Clínicos. | Sem acesso a dados de faturamento/custos administrativos e sem permissão de alteração de usuários. |

### Fluxo de Login por Perfil:
1. Usuário com perfil `campo` autentica-se e é **redirecionado automaticamente** para `/campo`.
2. A barra lateral administrativa e os links para dashboards/relatórios são suprimidos para perfis de campo.
3. Se um trabalhador tentar digitar URLs administrativas na barra de endereços (ex: `/dashboard` ou `/relatorios`), o sistema redireciona com mensagem informativa.

---

## 🎨 4. Planejamento de Substituição dos Emojis por Ícones Vetoriais

Para elevar o padrão visual para a apresentação acadêmica formal do TCC, foi feito o levantamento dos emojis e a correspondência com a biblioteca **Bootstrap Icons** (já integrada localmente ao projeto):

| Emoji Atual | Localização | Ícone Vetorial Recomendado | Justificativa |
|:---:|---|---|---|
| 🐄 / 🐂 | Logo, sidebar, login, avatar de animais | `<i class="bi bi-tag-fill"></i>` ou SVG do Rebanho | Padronização e sobriedade institucional |
| ⚖️ | Pesagens, balança, abas | `<i class="bi bi-rulers"></i>` ou `<i class="bi bi-speedometer2"></i>` | Identificação técnica de pesagem/métrica |
| ⚕️ | Saúde, manejo, vacinas | `<i class="bi bi-heart-pulse-fill"></i>` | Símbolo universal de saúde animal |
| 🐣 / 🌱 | Nascimento, bezerro, memória de filhote | `<i class="bi bi-stars"></i>` ou `<i class="bi bi-flower1"></i>` | Representação elegante de início de ciclo |
| ⚠️ | Óbito, censura de foto, alertas | `<i class="bi bi-exclamation-triangle-fill"></i>` | Padrão visual de atenção/conteúdo sensível |
| 📱 | Modo Campo, header PWA | `<i class="bi bi-phone-fill"></i>` | Ícone vetorial limpo |
| 📦 / 🕐 | Sincronizações, pacotes recebidos | `<i class="bi bi-box-seam-fill"></i>`, `<i class="bi bi-clock-history"></i>` | Identificação de logs de dados |
| 💰 / 🧬 / 🌿 | Custos, reprodução, pastagens | `<i class="bi bi-cash-stack"></i>`, `<i class="bi bi-diagram-3-fill"></i>`, `<i class="bi bi-tree-fill"></i>` | Coerência visual no módulo de relatórios |
| ♂ / ♀ | Macho e Fêmea | `<i class="bi bi-gender-male"></i>`, `<i class="bi bi-gender-female"></i>` | Símbolos taxonômicos formais |
| ✅ / ❌ / 🟢 / 🔴 | Toasts e indicadores de conexão | `<i class="bi bi-check-circle-fill"></i>`, `<i class="bi bi-x-circle-fill"></i>` | Feedback moderno e dinâmico |

---

## 🎯 5. Roteiro dos Próximos Passos para o TCC

### Passo 1: Depuração & Estabilização do Modo Campo (PWA)
- [ ] Analisar o ciclo de vida de cold start no smartphone para destravar inputs e botões quando aberto offline.
- [ ] Testar persistência do Service Worker em modo standalone.
- [ ] Estudo e ponderação comparativa entre PWA vs. Solução Nativa/Capacitor para fundamentação no TCC.

### Passo 2: Hierarquia de Acesso (RBAC)
- [ ] Criar middleware/função de checagem de perfil no `src/auth.php`.
- [ ] Redirecionamento automático de usuários `campo` para `/campo`.
- [ ] Ocultação contextual do menu lateral conforme o tipo de usuário.
- [ ] Tela de cadastro e listagem de usuários para o Administrador.

### Passo 3: Deploy na Nuvem (AWS)
- [ ] Criar instância **Amazon EC2** (Ubuntu / Free Tier).
- [ ] Criar instância de banco gerenciado **Amazon RDS (PostgreSQL)**.
- [ ] Configurar Security Groups (Portas 80, 443 e porta 5432 restrita à EC2).
- [ ] Subir o Docker na EC2 configurando as variáveis de ambiente do RDS.
- [ ] Configurar domínio/IP público e certificado SSL (HTTPS Let's Encrypt para habilitar instalação PWA sem avisos).

### Passo 4: Testes de Benchmark (JMeter)
- [ ] Criar script no **Apache JMeter** simulando cenários de carga concorrente (20, 50 e 100 trabalhadores sincronizando pesagens simultaneamente).
- [ ] Executar o teste no ambiente **On-Premise (Local)** e registrar Latência, Throughput e CPU/RAM.
- [ ] Executar o mesmo teste no ambiente **Nuvem (AWS)** e registrar Latência, Throughput e CloudWatch.

### Passo 5: Elaboração dos Resultados do TCC
- [ ] Tabela comparativa de Desempenho (On-Premise vs AWS).
- [ ] Tabela comparativa de Custos (CapEx Local vs OpEx AWS ~US$ 126/mês).
- [ ] Redação do capítulo de Resultados, Discussão e Considerações Finais.
