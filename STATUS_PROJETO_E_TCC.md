# 📊 PecuáriaGest — Status do Projeto & Roteiro do TCC

> **Data da Última Atualização:** 25/08/2026  
> **Status Geral do Sistema:** Painel Web e Módulo Mobile PWA 100% Funcionais e Estáveis.

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
- [x] **Web App Manifest (`public/manifest.json`):** Permite instalar o aplicativo na tela inicial do celular Android/iOS como app nativo em tela cheia (`standalone`).
- [x] **Service Worker (`public/sw.js`):** Cache inteligente dos recursos para abrir a aplicação mesmo sem internet no meio do pasto.
- [x] **Engine Offline em IndexedDB (`public/assets/js/pwa-campo.js`):** Armazena no celular todos os lançamentos de pesagens, novos bezerros e eventos de saúde realizados offline.
- [x] **Compressão de Fotos via Canvas:** Redimensionamento e compressão automática de fotos da câmera no próprio aparelho antes de salvar localmente.
- [x] **Sincronização Híbrida (`/api/sync`):** Botão de sincronização em lote com envio de dados e fotos para o servidor central assim que houver conexão.

### D. Infraestrutura & Banco de Dados
- [x] **Suporte a Banco Dual (SQLite + PostgreSQL):** Configuração em `src/config.php` e `src/db.php` que alterna automaticamente entre SQLite (local) e PostgreSQL (AWS RDS).
- [x] **Ambiente Docker Otimizado (`Dockerfile` + `docker-compose.yml`):** Imagem Debian Slim com PHP 8.2 FPM, Nginx, `php-pgsql` e `php-sqlite3`, com espelhamento de volumes (`./public` e `./src`) para desenvolvimento em tempo real.

---

## 🧪 3. Como Testar Localmente

1. **Subir com Docker:**
   ```powershell
   docker compose down
   docker compose build --no-cache
   docker compose up -d
   ```
   Acesse: `http://localhost:8080` (ou porta configurada).
   - **Login Padrão:** `admin@fazenda.com` / `admin123`

2. **Acessar o Modo Campo (PWA):**
   - Acesse o menu **"📱 Modo Campo (PWA)"** ou a URL `http://localhost:8080/campo`.
   - Teste registrar uma pesagem ou um bezerro com foto e clique em *"Sincronizar com a Nuvem"*.

---

## 🎯 4. O Que Falta Fazer (Roteiro dos Próximos Passos para o TCC)

### Passo 1: Deploy na Nuvem (AWS)
- [ ] Criar instância **Amazon EC2** (Ubuntu / Free Tier).
- [ ] Criar instância de banco gerenciado **Amazon RDS (PostgreSQL)**.
- [ ] Configurar Security Groups (Portas 80, 443 e porta 5432 restrita à EC2).
- [ ] Subir o Docker na EC2 configurando as variáveis de ambiente do RDS.
- [ ] Configurar domínio/IP público e certificado SSL (HTTPS Let's Encrypt).

### Passo 2: Testes de Benchmark (JMeter)
- [ ] Criar script no **Apache JMeter** simulando cenários de carga concorrente (20, 50 e 100 trabalhadores sincronizando pesagens simultaneamente).
- [ ] Executar o teste no ambiente **On-Premise (Local)** e registrar:
  - Tempo médio de resposta (Latência em ms).
  - Throughput (Requisições por segundo).
  - Taxa de erro (%).
  - Uso de CPU e Memória local.
- [ ] Executar o mesmo teste no ambiente **Nuvem (AWS)** e registrar:
  - Tempo médio de resposta (Latência em ms).
  - Throughput (Requisições por segundo).
  - Métricas do **Amazon CloudWatch** (CPU Utilization, Memory, Database Connections).

### Passo 3: Elaboração dos Resultados do TCC
- [ ] Tabela comparativa de Desempenho (On-Premise vs AWS).
- [ ] Tabela comparativa de Custos (CapEx Local vs OpEx AWS ~US$ 126/mês).
- [ ] Redação do capítulo de Resultados, Discussão e Considerações Finais.
