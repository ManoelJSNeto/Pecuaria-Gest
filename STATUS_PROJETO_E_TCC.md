# 📊 PecuáriaGest — Status do Projeto & Roteiro do TCC

> **Data da Última Atualização:** 26/08/2026  
> **Status Geral do Sistema:** Painel Web 100% Funcional; Módulo PWA estruturado (necessita revisão de instalação no celular em rede local/HTTPS).

---

> [!NOTE]
> **📌 PENDÊNCIA / PRÓXIMO PASSO (Mobile PWA):**  
> Ao testar a instalação do PWA no celular via IP local (`http://192.168.x.x:8080`), o navegador pode bloquear a instalação automática devido à exigência de **HTTPS (Contexto Seguro)** nos navegadores móveis modernos (Android Chrome e iOS Safari).  
> **Ações para validar na próxima sessão:**  
> 1. Testar no Chrome Android ativando a flag `chrome://flags/#unsafely-treat-insecure-origin-as-secure` com a URL do IP local.  
> 2. Ou rodar um túnel HTTPS temporário (ex: `ngrok http 8080`).  
> 3. No deploy final da AWS (EC2), com certificado SSL (HTTPS), a instalação nativa funcionará 100% sem bloqueios.  
> 4. Executar a substituição dos emojis pelos ícones vetoriais do Bootstrap Icons conforme a tabela da Seção 3.

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

### C. Módulo Mobile PWA Offline (`/campo`) & Instalação
- [x] **Geração dos Ícones PWA Oficiais (`public/assets/icons/`):** Ícones em alta resolução gerados (`icon-192.png`, `icon-512.png`, `maskable-icon-512.png` e `apple-touch-icon.png`), eliminando erros 404 que impediam a instalação no celular.
- [x] **Web App Manifest Atualizado (`public/manifest.json`):** Configurado com `id: "/campo"`, `scope: "/"`, `display: "standalone"` e propósitos `any` e `maskable`.
- [x] **Botão Nativo de Instalação e Card Promocional:** Adicionado card com botão *"📲 Instalar Aplicativo"* que escuta o evento `beforeinstallprompt` do navegador e aciona o diálogo nativo com 1 clique.
- [x] **Modal com Guia Passo a Passo:** Instruções visuais detalhadas para usuários Android (Chrome/Edge/Samsung) e iOS (Safari - *Adicionar à Tela de Início*).
- [x] **Service Worker Otimizado (`public/sw.js`):** Cache local inteligente com versionamento `v2` cobrindo assets, telas, ícones e fontes.
- [x] **Engine Offline em IndexedDB (`public/assets/js/pwa-campo.js`):** Armazena no celular todos os lançamentos de pesagens, novos bezerros e eventos de saúde realizados sem conexão.
- [x] **Compressão de Fotos via Canvas:** Redimensionamento automático antes de salvar localmente.
- [x] **Sincronização Híbrida (`/api/sync`):** Botão de sincronização em lote com envio de dados e fotos para o servidor central assim que houver conexão.

### D. Infraestrutura & Banco de Dados
- [x] **Suporte a Banco Dual (SQLite + PostgreSQL):** Configuração em `src/config.php` e `src/db.php` que alterna automaticamente entre SQLite (local) e PostgreSQL (AWS RDS).
- [x] **Ambiente Docker Otimizado (`Dockerfile` + `docker-compose.yml`):** Imagem Debian Slim com PHP 8.2 FPM, Nginx, `php-pgsql` e `php-sqlite3`, com espelhamento de volumes (`./public` e `./src`) para desenvolvimento em tempo real.

---

## 🎨 3. Planejamento de Substituição dos Emojis por Ícones Vetoriais

Para elevar o padrão visual para a apresentação acadêmica do TCC, foi feito o levantamento dos emojis e a correspondência com a biblioteca **Bootstrap Icons** (já integrada ao projeto):

| Emoji Atual | Localização | Ícone Vetorial Recomendado | Justificativa |
|:---:|---|---|---|
| 🐄 / 🐂 | Logo, sidebar, login, avatar de animais | `<i class="bi bi-tag-fill"></i>` ou SVG do Rebanho | Padronização e sobriedade institucional |
| ⚖️ | Pesagens, balança, modais | `<i class="bi bi-rulers"></i>` ou `<i class="bi bi-speedometer2"></i>` | Identificação técnica de pesagem/métrica |
| ⚕️ | Saúde, manejo, vacinas | `<i class="bi bi-heart-pulse-fill"></i>` | Símbolo universal de saúde animal |
| 🐣 / 🌱 | Nascimento, bezerro, memória de filhote | `<i class="bi bi-stars"></i>` ou `<i class="bi bi-flower1"></i>` | Representação elegante de início de ciclo |
| ⚠️ | Óbito, censura de foto, alertas | `<i class="bi bi-exclamation-triangle-fill"></i>` | Padrão visual de atenção/conteúdo sensível |
| 📱 | Modo Campo, header PWA | `<i class="bi bi-phone-fill"></i>` | Ícone vetorial limpo |
| 📦 / 🕐 | Sincronizações, pacotes recebidos | `<i class="bi bi-box-seam-fill"></i>`, `<i class="bi bi-clock-history"></i>` | Identificação de logs de dados |
| 💰 / 🧬 / 🌿 | Custos, reprodução, pastagens | `<i class="bi bi-cash-stack"></i>`, `<i class="bi bi-diagram-3-fill"></i>`, `<i class="bi bi-tree-fill"></i>` | Coerência visual no módulo de relatórios |
| ♂ / ♀ | Macho e Fêmea | `<i class="bi bi-gender-male"></i>`, `<i class="bi bi-gender-female"></i>` | Símbolos taxonômicos formais |
| ✅ / ❌ / 🟢 / 🔴 | Toasts e indicadores de conexão | `<i class="bi bi-check-circle-fill"></i>`, `<i class="bi bi-x-circle-fill"></i>` | Feedback moderno e dinâmico |

---

## 🧪 4. Como Testar o PWA no Celular

1. **Obtenha o IP da Máquina:** No terminal Windows, execute `ipconfig` e anote o IPv4 (ex: `192.168.1.105`).
2. **Abra no Celular:** Acesse `http://192.168.1.105:8080/campo`.
3. **Instalação:**
   - **No Android (Chrome):** O card verde exibirá o botão **"Instalar Aplicativo"**. Clique nele ou acerte nos 3 pontinhos (⋮) ➔ *"Instalar aplicativo"*.
   - **No iPhone (Safari):** Toque no botão Compartilhar (quadrado com seta ⎋) ➔ *"Adicionar à Tela de Início"*.
4. **Teste Offline:**
   - Ative o **Modo Avião** no celular.
   - Abra o app instalado pela tela inicial.
   - Registre pesagens e bezerros com fotos.
   - Desative o Modo Avião e clique em **"Sincronizar com a Nuvem"**.

---

## 🎯 5. Roteiro dos Próximos Passos para o TCC

### Passo 1: Deploy na Nuvem (AWS)
- [ ] Criar instância **Amazon EC2** (Ubuntu / Free Tier).
- [ ] Criar instância de banco gerenciado **Amazon RDS (PostgreSQL)**.
- [ ] Configurar Security Groups (Portas 80, 443 e porta 5432 restrita à EC2).
- [ ] Subir o Docker na EC2 configurando as variáveis de ambiente do RDS.
- [ ] Configurar domínio/IP público e certificado SSL (HTTPS Let's Encrypt para habilitar instalação PWA sem avisos).

### Passo 2: Testes de Benchmark (JMeter)
- [ ] Criar script no **Apache JMeter** simulando cenários de carga concorrente (20, 50 e 100 trabalhadores sincronizando pesagens simultaneamente).
- [ ] Executar o teste no ambiente **On-Premise (Local)** e registrar Latência, Throughput e CPU/RAM.
- [ ] Executar o mesmo teste no ambiente **Nuvem (AWS)** e registrar Latência, Throughput e CloudWatch.

### Passo 3: Elaboração dos Resultados do TCC
- [ ] Tabela comparativa de Desempenho (On-Premise vs AWS).
- [ ] Tabela comparativa de Custos (CapEx Local vs OpEx AWS ~US$ 126/mês).
- [ ] Redação do capítulo de Resultados, Discussão e Considerações Finais.
