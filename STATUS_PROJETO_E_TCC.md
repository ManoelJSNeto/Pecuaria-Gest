# 📊 PecuáriaGest — Status do Projeto & Roteiro do TCC

> **Data da Última Atualização:** 28/08/2026  
> **Status Geral do Sistema:** Painel Web 100% Funcional e estável; App Mobile Nativo Android (APK via Capacitor) totalmente implementado, testado e validado em campo com funcionamento offline fluido, Auto-Sync e notificações automáticas por e-mail e painel para o proprietário.

---

> [!IMPORTANT]
> ### 📱 DECISÃO ARQUITETURAL & ACADÊMICA: Descontinuação do PWA e Adoção do App Nativo Android (Capacitor)
> **Estudo de Caso & Fundamentação Teórica para o Artigo / Apresentação do TCC:**
>
> 1. **Motivos Técnicos da Descontinuação do PWA (Progressive Web App):**
>    * **Ciclo de Atualização Oculto do WebAPK (Android/Chrome):** Ao instalar um PWA no Android, o sistema compila um WebAPK que só busca atualizações a cada 24 horas em background. Isso causava retenção de código antigo em desenvolvimento e inconsistência de versão.
>    * **Travamento de Foco e Touch no Cold Start Offline:** Ao abrir o atalho PWA sem rede (Modo Avião), a thread principal do navegador bloqueava o foco em campos de entrada (`inputs`) e disparos de toque, gerando sensação de interface congelada.
>    * **Conflito de Escopo e Sequestro de Rotas:** O Service Worker interceptava rotas de navegação desktop e gerava redundâncias no servidor.
>
> 2. **Adoção da Tecnologia Híbrida Nativa — Apache/Ionic Capacitor 8:**
>    * **Assets 100% Embutidos no Binário (`.apk`):** Os arquivos HTML5, CSS3, Bootstrap, fontes e scripts JavaScript residem diretamente no armazenamento local do aplicativo (`android_asset/public/`), iniciando em 0 milissegundos sem depender de cache de navegador ou Service Worker.
>    * **Toque Fluido & Acesso a Recursos Nativos:** Foco imediato nos inputs, digitação livre, vibração tátil (`navigator.vibrate`), suporte nativo à câmera e persistência local confiável.
>    * **Suporte Completo a HTTP e HTTPS:** Configurado com `network_security_config.xml` e `allowMixedContent: true`, permitindo conexão com IPs locais Wi-Fi (`http://192.168.x.x:8080`) e domínios seguros na nuvem (`https://`).
>    * **Esteira CI/CD Automatizada no GitHub Actions:** O workflow [`.github/workflows/build-apk.yml`](.github/workflows/build-apk.yml) compila o arquivo `app-debug.apk` na nuvem a cada `git push` com Java 21 e Android SDK.

---

## 📌 1. Visão Geral do Projeto

O **PecuáriaGest** é um sistema completo de gestão agropecuária e manejo de rebanho com arquitetura distribuída:
* **Módulo de Campo:** Aplicativo Android nativo (`.apk`) para operadores e vaqueiros coletarem dados no pasto 100% offline.
* **Painel Central de Gestão:** Aplicação Web modular em PHP 8.2 para proprietários, gerentes e veterinários monitorarem métricas consolidadas, genealogia, reprodução e relatórios.

O sistema é o objeto de estudo de caso comparativo do TCC entre dois ambientes:
- **Ambiente On-Premise:** Servidor local Dockerizado com banco de dados SQLite / PostgreSQL.
- **Ambiente Nuvem (AWS):** Servidor rodando em **Amazon EC2** conectado ao banco gerenciado **Amazon RDS (PostgreSQL)**.

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

### C. Módulo Mobile Nativo Offline (APK Android / Capacitor)
- [x] **App Nativo Android Estruturado (`mobile-app/`):** Projeto Capacitor 8 configurado com Gradle e Android SDK.
- [x] **Interface em Abas Táteis Diretas:** Telas dedicadas para Pesagem, Bezerro, Saúde e Fila de Sincronização, com navegação fluida em JavaScript puro.
- [x] **Motor Offline & Memória Local (`mobile-app/www/js/app.js`):** Armazenamento local de registros pendentes e cache de brincos de animais para autocompletar sem internet.
- [x] **Auto-Sync Inteligente em Segundo Plano:** O app detecta a reconexão à rede Wi-Fi/4G e descarrega automaticamente as pendências com vibração tátil de confirmação.
- [x] **Compressão Inteligente de Fotos no Cliente:** Redução automática de imagens de alta resolução para Base64 leve (~200KB) via Canvas API antes de enfileirar.
- [x] **Conectividade & Configuração Flexível de Servidor:** Modal de ajuste de IP (ex: `http://192.168.x.x:8080` ou URL da AWS) com detecção ativa de status **🟢 Online / 🔴 Offline**.
- [x] **Sincronização Autenticada com Transação Atômica (`/api/sync`):** Envio em lote com validação de credenciais de usuário cadastrado e liberação de CORS.
- [x] **Compilação Automática CI/CD (`.github/workflows/build-apk.yml`):** Geração do arquivo `app-debug.apk` no GitHub Actions a cada push.
- [x] **Design Ergonômico de Curral (Botão 68px e Alto Contraste):** Botão de salvar ampliado para 68px de altura mínima, fonte 1.25rem e ícones destacados para operação sem erro com luvas ou sob sol forte.
- [x] **Resposta Tátil Nativa (`@capacitor/haptics`):** Permissão de vibração no manifesto e driver nativo integrado, gerando confirmação física firme no aparelho ao salvar.
- [x] **Interface Despoluída:** Remoção de chips de recentes no brinco e blocos secundários no rodapé, priorizando o autocomplete compacto com rolagem suave.

### D. Módulo de Notificações & Configurações do Sistema (`/configuracoes`)
- [x] **Geração Automática de Alertas no Painel Web:** Disparo automático de notificações no sininho 🔔 com contadores de pesagens, novos bezerros e manejos recebidos.
- [x] **Disparo de E-mails Automáticos em HTML:** Envio de relatório formatado para a caixa de entrada do proprietário/gerente a cada lote sincronizado.
- [x] **Painel de Configurações do Sistema (`/configuracoes`):** Interface para o proprietário ativar/desativar e-mails, definir destinatário, e-mail remetente e botão de teste de disparo imediato.
- [x] **Tabela de Configurações Persistente (`configuracoes`):** Armazenamento dinâmico de preferências no banco de dados.

### E. Gestão de Equipe & Permissões Granulares (`/usuarios`)
- [x] **Matriz de Permissões Granulares (RBAC/ABAC):** Controle individual por módulo (Animais, Pesagens, Saúde, Pastagens, Reprodução, Relatórios, Alertas, App Mobile e Notificações).
- [x] **Motor de Verificação `can()` e `requirePermission()`:** Validação de privilégios em rotas e botões com bloqueio automático e mensagens flash informativas.
- [x] **Menu Lateral Dinâmico:** Ocultação automática de módulos e relatórios aos quais o colaborador não possui acesso.
- [x] **Presets Rápidos de Permissão:** Botões de 1 clique para configurar perfis de *Veterinário*, *Campo/Vaqueiro* e *Apenas Leitura*.
- [x] **Gestão de Status de Conta:** Ativação/desativação imediata de acesso sem exclusão de histórico.

### F. Infraestrutura & Banco de Dados
- [x] **Suporte a Banco Dual (SQLite + PostgreSQL):** Configuração em `src/config.php` e `src/db.php` que alterna automaticamente entre SQLite (local) e PostgreSQL (AWS RDS).
- [x] **Ambiente Docker Otimizado (`Dockerfile` + `docker-compose.yml`):** Imagem Debian Slim com PHP 8.2 FPM, Nginx, `php-pgsql` e `php-sqlite3`, com espelhamento de volumes (`./public` e `./src`) para desenvolvimento em tempo real.

---

## 🎨 3. Planejamento de Substituição dos Emojis por Ícones Vetoriais

Para elevar o padrão visual para a apresentação acadêmica formal do TCC, foi feito o levantamento dos emojis e a correspondência com a biblioteca **Bootstrap Icons** (já integrada localmente ao projeto):

| Emoji Atual | Localização | Ícone Vetorial Recomendado | Justificativa |
|:---:|---|---|---|
| 🐄 / 🐂 | Logo, sidebar, login, avatar de animais | `<i class="bi bi-tag-fill"></i>` ou SVG do Rebanho | Padronização e sobriedade institucional |
| ⚖️ | Pesagens, balança, abas | `<i class="bi bi-rulers"></i>` ou `<i class="bi bi-speedometer2"></i>` | Identificação técnica de pesagem/métrica |
| ⚕️ | Saúde, manejo, vacinas | `<i class="bi bi-heart-pulse-fill"></i>` | Símbolo universal de saúde animal |
| 🐣 / 🌱 | Nascimento, bezerro, memória de filhote | `<i class="bi bi-stars"></i>` ou `<i class="bi bi-flower1"></i>` | Representação elegante de início de ciclo |
| ⚠️ | Óbito, censura de foto, alertas | `<i class="bi bi-exclamation-triangle-fill"></i>` | Padrão visual de atenção/conteúdo sensível |
| 📱 | Modo Campo, header Mobile | `<i class="bi bi-phone-fill"></i>` | Ícone vetorial limpo |
| 📦 / 🕐 | Sincronizações, pacotes recebidos | `<i class="bi bi-box-seam-fill"></i>`, `<i class="bi bi-clock-history"></i>` | Identificação de logs de dados |
| 💰 / 🧬 / 🌿 | Custos, reprodução, pastagens | `<i class="bi bi-cash-stack"></i>`, `<i class="bi bi-diagram-3-fill"></i>`, `<i class="bi bi-tree-fill"></i>` | Coerência visual no módulo de relatórios |
| ♂ / ♀ | Macho e Fêmea | `<i class="bi bi-gender-male"></i>`, `<i class="bi bi-gender-female"></i>` | Símbolos taxonômicos formais |
| ✅ / ❌ / 🟢 / 🔴 | Toasts e indicadores de conexão | `<i class="bi bi-check-circle-fill"></i>`, `<i class="bi bi-x-circle-fill"></i>` | Feedback moderno e dinâmico |

---

## 🎯 4. Roteiro dos Próximos Passos para o TCC

### Passo 1: Substituição dos Emojis por Ícones Vetoriais
- [x] Aplicar a tabela de substituição com Bootstrap Icons em todas as views do painel e no app mobile. (Concluído com 100% de cobertura)

### Passo 2: Deploy na Nuvem (AWS)
- [ ] Criar instância **Amazon EC2** (Ubuntu / Free Tier).
- [ ] Criar instância de banco gerenciado **Amazon RDS (PostgreSQL)**.
- [ ] Configurar Security Groups (Portas 80, 443 e porta 5432 restrita à EC2).
- [ ] Subir o Docker na EC2 configurando as variáveis de ambiente do RDS.
- [ ] Testar sincronização do APK diretamente com o IP/domínio da AWS.

### Passo 3: Testes de Benchmark (JMeter)
- [ ] Criar script no **Apache JMeter** simulando cenários de carga concorrente (20, 50 e 100 trabalhadores sincronizando pesagens simultaneamente).
- [ ] Executar o teste no ambiente **On-Premise (Local)** e registrar Latência, Throughput e CPU/RAM.
- [ ] Executar o mesmo teste no ambiente **Nuvem (AWS)** e registrar Latência, Throughput e CloudWatch.

### Passo 4: Elaboração dos Resultados do TCC
- [ ] Tabela comparativa de Desempenho (On-Premise vs AWS).
- [ ] Tabela comparativa de Custos (CapEx Local vs OpEx AWS ~US$ 126/mês).
- [ ] Redação do capítulo de Resultados, Discussão e Considerações Finais.

---

## 📌 5. Pendências Críticas de Usabilidade & Regras de Negócio (Solicitadas pelo Usuário)

### A. Módulo Mobile — Usabilidade de Campo & Conexão pelo Login: [CONCLUÍDO]
1. **Identidade Visual e Nome do Aplicativo (`PecuGest-Campo`):**
   - Nome oficial unificado como **PecuGest-Campo** no Android (`strings.xml`), Capacitor (`capacitor.config.json`) e webapp.
   - Ícone padrão do Capacitor substituído pelo ícone oficial do PecuáriaGest em todas as densidades (`mipmap-mdpi`, `hdpi`, `xhdpi`, `xxhdpi`, `xxxhdpi`) com fundo verde floresta (`#1a4d2e`).
2. **Tela Dedicada de Login (Sem Conflito de Modais):**
   - Substituição do modal flutuante por uma tela de login dedicada em tela cheia (`appLoginScreen`), eliminando 100% dos bugs de backdrops encavalados com o modal de PIN (`0032`) ou configuração de IP.
   - Sessão persistente: uma vez autenticado, o vaqueiro entra direto no curral nas próximas aberturas.
3. **Ergonomia de Curral, Fontes e Aproveitamento de Espaço:**
   - Display estilo balança digital com numerais grandes e nítidos para leitura sob luz forte do sol.
   - Seletor de sexo do bezerro em botões de toque rápido (Macho ♂ / Fêmea ♀).
   - Botões de submissão ampliados com 68px de altura (glove-friendly).
4. **Redesenho Completo da Aba de Saúde (Manejo Sanitário):**
   - Distribuição em 4 painéis lógicos limpos: 1. Animal, 2. Tipo de Ocorrência (chips táteis de vacina, tratamento, vermífugo, curativo e óbito), 3. Data em linha espaçosa & Medicamento/Dose, 4. Câmera.
   - Remoção da opção desnecessária de censura no celular: o aplicativo apenas envia e o servidor trata censura por padrão.
5. **Gestão de Fotos no Painel Web (Censura e Download):**
   - O proprietário pode visualizar com ou sem censura (individual e global "Revelar Todas") e baixar a foto original limpa em alta resolução diretamente pelo prontuário do animal.

6. **Vínculo Automático de Status (Saúde e Reprodução): [CONCLUÍDO]**
   - Eventos de saúde agora refletem automaticamente no status do animal: Óbito ➔ `morto`, Tratamento/Curativo/Cirurgia ➔ `doente` (Em Tratamento), Alta Médica/Curado ➔ `ativo`.
   - Eventos reprodutivos refletem no status da matriz: Diagnóstico Prenha/Positivo ➔ `prenha`, Parto/Aborto/Desmame ➔ `ativo`.
   - Removidos todos os resquícios de texto "PWA" na observação de fotos, nomes de arquivos e identificadores do app.
   - Correção do volume de uploads no Nginx Docker e restauração do formulário de Reprodução.

### B. Regras de Negócio & Genealogia (Web & Banco de Dados)
1. **Validação Estrita de Mãe / Pai no Cadastro de Filhotes & Reprodução: [CONCLUÍDO]**
   - **Mãe:** Impedido estritamente que machos/bois sejam selecionados como mãe biológica. Apenas fêmeas ativas e vivas (`sexo = 'F'`) figuram como opção de mãe, com validação de consistência no backend.
   - **Pai / Touro:** Sugestão de touros machos ativos (`sexo = 'M'`) via datalist e digitação livre de sêmen/inseminação. Bloqueio contra atribuição de fêmeas como touro reprodutor.
2. **Ciclo de Venda e Saída de Animais: [PENDENTE]**
   - Tela/modal para registro de venda formal: comprador, valor por kg/total, peso de saída e baixa do rebanho ativo com cálculo de receita.
3. **Relatórios Gerenciais para Impressão (PDF / Laudo): [PENDENTE]**
   - Exportação em PDF formatado (ficha do animal e balanço do rebanho para veterinário ou proprietário).
