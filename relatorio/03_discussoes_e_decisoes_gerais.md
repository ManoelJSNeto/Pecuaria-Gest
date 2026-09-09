# 💬 Caderno de Discussões, Decisões de Negócio & Lições Aprendidas

> **Finalidade:** Espaço documental para registrar o racional humano, operacional e zootécnico por trás das decisões do projeto, servindo como base para os capítulos de Levantamento de Requisitos, Discussão e Considerações Finais do TCC.

---

## 1. O Conceito de Usabilidade "Saque Rápido" no Campo

### 1.1 A Realidade da Lida Rural e a Inclusão Digital
Muitos sistemas de software agropecuário falham na adoção porque são concebidos com a mentalidade de escritórios urbanos. No curral de manejo, a realidade é drástica:
* O operador lida com animais de 400kg a 700kg em movimento, poeira, lama e sol escaldante.
* Há operadores com baixa familiaridade com tecnologia touch ou com dificuldades de letramento técnico.
* Qualquer sistema que exija múltiplos passos, digitação de termos complexos ou configurações de rede é imediatamente abandonado pela equipe de campo.

### 1.2 A Eliminação Completa do IP ("Zero-Touch Network")
* **O Risco da Configuração Manual:** Expor um campo para o vaqueiro digitar endereços de IP (`192.168.x.x` ou portas) gera alto índice de erro e desconfiguração indevida ("dar zulo").
* **A Decisão do App de Saque Rápido:**  
  O aplicativo móvel deve nascer pré-configurado de fábrica apontando para o endpoint seguro do servidor. O operador insere apenas seu **login e senha uma única vez**.
* **Sessão Persistente de Trabalho (Manter Sempre Logado):**  
  Uma vez autenticado, o token de sincronização é mantido salvo no dispositivo com segurança. Quando o operador retorna ao alcance do sinal e aciona o descarregamento (ou via Auto-Sync), a transmissão ocorre de forma invisível em segundo plano, **sem travar a produção nem interromper o serviço exigindo e-mail e senha a cada pesagem**.

---

## 2. Metodologia de Levantamento de Requisitos: Escuta Ativa Direta

### 2.1 Da Abordagem Formal ao Diálogo Empírico
No início do projeto, a equipe planejou a aplicação de formulários estruturados (*Google Forms*) para mapear os processos da fazenda.
* **O Aprendizado Prático:** No contexto da agricultura e pecuária familiar, questionários acadêmicos e frios geram distanciamento e respostas rasas.
* **A Virada Metodológica:** A equipe adotou a técnica de **entrevistas semiestruturadas e escuta ativa verbal** com o produtor rural (o avô do autor). Acompanhando os relatos das frustrações diárias com anotações em papel, perdas de vacinação e falhas de comunicação com a cidade, os requisitos reais emergiram de forma muito mais fidedigna do que qualquer questionário padronizado.

---

## 3. Regras de Negócio e Fidelidade Zootécnica

### 3.1 Genealogia e Consistência Biológica
* **Restrição Estrita de Maternidade:** O sistema implementou validação para que apenas fêmeas ativas e vivas (`sexo = 'F'`) possam ser selecionadas como mães biológicas de novos bezerros, eliminando incongruências de cadastro.
* **Paternidade e Reprodutores:** Sugestão de touros machos ativos (`sexo = 'M'`) cadastrados na fazenda e suporte a digitação livre para sêmen de inseminação artificial (IA), com bloqueio contra atribuição de fêmeas como touro reprodutor.
* **Distinção de Origem:** Separação explícita entre animais nascidos na propriedade (que geram cálculo de idade e controle de maternidade) versus animais adquiridos/comprados de terceiros (com registro de peso de entrada e valor de aquisição).
* **Memória de Filhote / Bezerro:** Detecção automática de animais com idade $\le 12$ meses. A primeira foto de filhote é preservada permanentemente no prontuário no cartão *"Memória de Filhote"*, mantendo o registro visual de nascimento mesmo após o animal atingir a fase adulta.

### 3.2 O Ciclo de Saída (Faturamento vs Mortalidade)
* **Baixa por Venda:** Registro formal do comprador, valor por arroba/total, peso de embarque e data, alimentando o painel financeiro do proprietário na cidade e baixando o animal do rebanho ativo.
* **Baixa por Óbito:** Exigência de motivo clínico e registro de necropsia, impactando diretamente os índices zootécnicos de taxa de mortalidade do rebanho no dashboard.

### 3.3 Transição Automática e Reativa de Status
Para eliminar intervenção manual e inconsistências de cadastro entre o aplicativo de campo e o banco central:
* **Eventos Sanitários:** A inserção de um evento de óbito altera imediatamente o status do animal para `morto`; procedimentos de tratamento, curativo ou cirurgia alteram para `doente` ("Em Tratamento"); e altas médicas restauram para `ativo`.
* **Eventos Reprodutivos:** O diagnóstico positivo de gestação altera a matriz para `prenha`, e o registro posterior de parto, aborto ou desmame retorna a vaca para o status `ativo`.

---

## 4. Segurança e Integridade Transacional no Campo

### 4.1 Transações Atômicas no Descarregamento (`/api/sync`)
Como a sincronização de campo transmite pacotes contendo dezenas de pesagens e manejos acumulados durante o dia, a API foi construída com controle estrito de transação de banco de dados (`BEGIN / COMMIT / ROLLBACK`):
* Se um lote de 40 pesagens sofrer uma falha de conexão na 39ª, a operação inteira sofre reversão segura (*rollback*). O aplicativo mantém o lote intacto no celular para reenvio, impedindo que registros fiquem duplicados ou gravados pela metade no banco de dados central.

### 4.2 Privacidade e Apresentação Visual Respeitosa
* **Desfoque de Imagens Clínicas/Óbito:** O sistema aplica automaticamente desfoque visual forte (`blur`) em fotos associadas a ferimentos, tratamentos cirúrgicos ou animais mortos. Isso evita choques visuais desnecessários no painel gerencial, mantendo o acesso ao laudo fotográfico disponível mediante um clique de confirmação.

---

## 5. Blindagem de Autenticação e Proteção contra Força Bruta

### 5.1 O Risco de Ataques de Dicionário em Ambientes Web
Como o painel centralizado fica exposto para acesso remoto do proprietário na cidade, tornou-se imperativo blindar a rota de login contra tentativas automatizadas de adivinhação de senhas:
* **Rate Limiting e Bloqueio Temporário (Lockout):** O sistema monitora as tentativas falhas consecutivas. Ao atingir a **5ª tentativa errada**, a aplicação congela novos acessos por **3 minutos**, desestimulando ataques automatizados de força bruta.
* **Proteção de Credenciais em Produção:** As senhas padrão de primeiro acesso só são aceitas caso o ambiente esteja expressamente declarado como `development`, forçando a troca de senhas na implantação real.
* **Proteção contra Timing Attacks na API:** As rotas consumidas pelo aplicativo móvel (`/api/sync` e `/api/animais`) passaram a comparar as chaves de API utilizando `hash_equals()`, eliminando qualquer vazamento de tempo em nanossegundos que pudesse permitir a dedução da chave secreta.

---

## 6. Arquitetura de Software: Monólito Pragmático vs SPA e a Transição para Controladores

### 6.1 Por que não separar o Front-End Web em uma SPA (React/Vue)?
No meio acadêmico e no mercado corporativo, é comum adotar como "padrão" a separação total entre uma Single Page Application (SPA) em JavaScript e uma API REST no back-end. Para o PecuáriaGest, no entanto, a decisão consciente foi manter o **Painel Web em Server-Side Rendering (SSR) com PHP 8.3 e Nginx**:
* **Desempenho Instantâneo:** O servidor entrega o HTML sem exigir que o dispositivo do usuário faça download de pesados bundles de JavaScript de centenas de megabytes.
* **Simplicidade de Manutenção:** Evita a duplicação de regras de validação (uma no front e outra no back), elimina problemas de CORS e complexidade de tokens JWT com expiração e refresh.
* **Separação Onde Importa:** A separação de responsabilidades existe com clareza entre o **Aplicativo Android Nativo** (que coleta no campo) e o **Painel Central** (que consolida e exibe no escritório).

### 6.2 O Próximo Passo Evolutivo: Modularização em Controladores Dedicados
Com o amadurecimento dos módulos de Animais, Pesagens, Manejo Sanitário, Reprodução, Pastagens, Alertas e o novo Cockpit Comercial (Compras e Vendas), o arquivo `public/index.php` atingiu seu limite saudável como Front Controller + Roteador. 
* **A Estratégia de Transição:** Para manter o código limpo e sustentável para futuras equipes, foi planejado o desacoplamento do roteamento em **Controladores Dedicados** (`src/controllers/`), permitindo que cada recurso de negócio tenha sua própria classe especializada (`AnimaisController`, `ComercialController`, `AuthController`, etc.), mantendo o `index.php` apenas como despachante de requisições de poucas dezenas de linhas.

### 6.3 Conclusão da Migração Arquitetural MVC (100% dos Módulos Desacoplados)
A transição arquitetural foi executada e validada em 5 etapas incrementais, cada uma acompanhada por bateria de testes de regressão automatizados:
1. **Infraestrutura e Autoloading:** Implementação do `BaseController` (abstração de renderização, CSRF, JSON, redirecionamento e RBAC) e autoloader PSR registrado via `spl_autoload_register`.
2. **Segurança e Acesso:** Criação do `AuthController` isolando login, logout e proteções anti-brute force / rate limiting.
3. **Módulo Comercial:** Criação do `ComercialController` para compras e vendas com upload e parsing de XMLs de NF-e e GTAs.
4. **Manejo Central do Rebanho:** Criação de `AnimaisController` e `PesagensController`, encapsulando genealogia biológica, histórico de pesagens e fotos.
5. **Manejo Sanitário, Pastagens, Reprodução e Alertas:** Criação de `SaudeController`, `PastagensController`, `ReproducaoController` e `AlertasController`.
6. **Administração, APIs e Auditoria:** Criação de `DashboardController`, `RelatoriosController`, `ConfiguracoesController`, `UsuariosController` e `ApiController`.

**Resultados Alcançados:**
* O Front Controller (`public/index.php`) foi reduzido de **1.450 linhas para ~400 linhas**, operando estritamente como roteador despachante.
* **100% de cobertura nos testes de regressão:** 21 rotas de tela e 2 endpoints de API validados com HTTP 200/302 sem nenhum erro de SQL ou template.
* **Zero impacto na latência:** Testes de benchmark comprovaram tempos médios de resposta entre 20ms e 45ms por requisição sob Docker (PHP 8.3 FPM + Nginx + PostgreSQL).
* **Manutenibilidade Acadêmica:** A organização em classes atende plenamente aos critérios de avaliação de engenharia de software para o TCC (baixo acoplamento, alta coesão e separação nítida de camadas).

---

## 7. Discussões em Aberto & Próximas Decisões Arquiteturais

### 🆔 [DISC-INFRA-2026-09-08] Arquitetura da Nova Bateria de Testes na Nuvem (AWS com SSM/IAM & Expansão Multi-Cloud)

> **Status:** 🟡 **EM ABERTO / EM DISCUSSÃO COM O AUTOR**  
> **Identificador Único:** `[DISC-INFRA-2026-09-08]`  
> **Data de Registro:** 08/09/2026

#### 1. Contexto e Objetivos
A equipe planeja refazer integralmente os testes de carga concorrente (20, 50 e 100 usuários) com o sistema padronizado em PostgreSQL 16 nativo, expandindo o comparativo para além da AWS (incluindo GCP e Azure) e medindo também o consumo de CPU (%) e Memória RAM (MB).

#### 2. Pontos Discutidos e Alinhados para a AWS:
1. **Segurança Avançada com AWS Systems Manager (SSM) e IAM:**
   * **Erradicação do SSH (Porta 22):** A porta 22 será totalmente removida dos Security Groups.
   * **Zero Chaves Privadas `.pem`:** Autenticação gerenciada pelo IAM através de uma Role anexada à EC2 com a política `AmazonSSMManagedInstanceCore`.
   * **Acesso Administrativo:** Conexão via AWS Systems Manager Session Manager (console web ou AWS CLI) com auditoria nativa no AWS CloudTrail.
2. **Topologia de Rede e Isolamento em Camadas:**
   * **Camada Pública (EC2):** Portas 80 e 443 abertas para tráfego do app móvel e painel web.
   * **Camada Privada (RDS PostgreSQL):** Subnet group privado sem IP público, aceitando conexões na porta 5432 exclusivamente a partir do Security Group da EC2.
3. **Empacotamento da Aplicação:**
   * A EC2 executará os contêineres oficiais desacoplados (Nginx + PHP 8.3-FPM), enquanto a variável `DB_HOST` apontará diretamente para a instância externa do Amazon RDS PostgreSQL.
4. **Dimensionamento Proposto (Free Tier):**
   * EC2 `t3.micro` (2 vCPUs, 1 GB RAM, Ubuntu 24.04 LTS) + RDS `db.t3.micro` (PostgreSQL 16, 1 GB RAM, 20 GB gp3).

#### 3. Tópicos Pendentes para Decisão na Próxima Sessão:
* [ ] **Definição da Região:** Validação se a conta AWS do autor possui cotas disponíveis no Free Tier para a região de **São Paulo (`sa-east-1`)**, ou se será necessário adotar **Norte da Virgínia (`us-east-1`)** como contingência realista.
* [ ] **Planejamento da Infraestrutura nas Outras Nuvens (GCP e Azure):**
  * *Google Cloud:* Mapeamento do Compute Engine `e2-micro`/`e2-small` + Cloud SQL PostgreSQL na região `southamerica-east1` (São Paulo).
  * *Microsoft Azure:* Mapeamento da Azure VM `Standard_B1s` + Azure Database for PostgreSQL Flexible Server na região `brazilsouth` (São Paulo).
* [ ] **Execução Prévia em Ambientes Locais:** Testar o novo runner com captura de CPU/RAM em duas máquinas físicas locais (Máquina A do autor e Máquina B adicional) antes de disparar os testes na nuvem.

---

### 🆔 [FEEDBACK-XML-NFE-2026-09-09] Validação de Campo: Pré-visualização Imediata e Edição de Itens do XML da NF-e

> **Status:** 🟢 **CONCLUÍDO E TESTADO COM 100% DE SUCESSO**  
> **Identificador Único:** `[FEEDBACK-XML-NFE-2026-09-09]`  
> **Data de Registro:** 09/09/2026  
> **Branch de Desenvolvimento:** `feature/xml-nfe-preview-edit`

#### 1. Relato da Validação de Campo
Durante os testes práticos de validação da aplicação com usuário/produtor antes das etapas de infraestrutura, foi reportado o seguinte gargalo de usabilidade no módulo de Compras e Vendas:
* Ao anexar o arquivo XML da Nota Fiscal Eletrônica (NF-e), a aplicação preenchia apenas os campos gerais de cabeçalho, **mas não listava na tela, na hora, os itens/produtos contidos na nota fiscal** (detalhamento de itens, animais, quantidades, descrições e valores unitários).
* Se o usuário precisasse **conferir, ajustar ou editar** qualquer dado importado da nota (ou itens específicos) antes ou depois de salvar, a aplicação não oferecia uma interface interativa de revisão imediata nem de edição posterior.

#### 2. Diagnóstico Técnico Identificado:
1. **Falta de Tabela de Itens em Tempo Real:** O leitor JavaScript (`handleXmlSelect`) em `src/views/compras/form.php` e `src/views/vendas/form.php` extraía valores consolidados (Chave, Fornecedor, Valor Total, Peso Total), mas não gerava uma tabela visual dinâmica renderizando as tags `<det>` / `<prod>` (itens da nota, códigos, descrição, quantidade comercial `qCom`, valor unitário `vUnCom` e valor total `vProd`).
2. **Campos Estáticos vs Editáveis:** Os valores extraídos iam para inputs simples, mas não havia um grid/tabela interativa onde o produtor pudesse alterar uma quantidade, recalcular rateios ou selecionar quais itens da nota desejava realmente importar (excluindo frete ou serviços).
3. **Ausência de Edição Pós-Salvar:** No controlador `ComercialController.php`, existiam apenas métodos para criar e excluir compras/vendas (`comprasNovo`, `comprasSalvar`, `comprasExcluir`, `vendasNovo`, `vendasSalvar`), não existindo rotas e telas de edição (`comprasEditar`, `comprasAtualizar`, `vendasEditar`, `vendasAtualizar`).

#### 3. Implementação e Resolução Entregue:
* **Redesenho do Painel e Calibração de Dimensionamento (`.nfe-preview-panel`):**
  * Alinhado 100% aos tokens do Design System do PecuáriaGest (`--earth-green-950`, `--border-subtle`, `--bg-surface`).
  * Eliminação de quebras de layout e scrollbars horizontais forçadas: colunas otimizadas com inputs em estilo planilha compacta (`.nfe-cell-input`), com formatação de numerais tabulares e destaque sutil no foco.
  * Cabeçalho moderno com chips de metadados (`.nfe-chip`) indicando NF-e, Série, Emitente, Emissão e GTA detectada.
* **Visualizador Oficial de NF-e e Detalhes do XML Original:**
  * **Páginas Dedicadas Danfe (`/compras/{id}/nfe` e `/vendas/{id}/nfe`):** Apresentam o espelho completo da Nota Fiscal com chave de acesso de 44 dígitos (e botão de cópia), dados cadastrais do emitente e destinatário, totais tributários/comerciais, observações (`infCpl`), tabela completa de todos os itens/produtos da nota e visualizador do código XML original na íntegra.
  * **Modais Interativos Instantâneos (`#modalNfeCompleta` e `#modalNfeVendaCompleta`):** Permitem consultar a nota fiscal completa diretamente de dentro do formulário de compra/venda sem sair da tela.
  * **Atalhos Rápidos nas Listagens:** Adicionados botões `"Ver NF-e"` diretamente nas colunas de Chaves Mestras e Ações em `/compras` e `/vendas`.
* **Rotas e Telas de Edição:**
  * Implementadas rotas `GET /compras/{id}/editar`, `POST /compras/{id}/atualizar`, `GET /vendas/{id}/editar` e `POST /vendas/{id}/atualizar`.
  * Atualização inteligente dos animais vinculados e botões de atalho (`<i class="bi bi-pencil"></i>`) nas tabelas.
* **Validação e Testes Automatizados:**
  * 40 rotas do sistema validadas com HTTP 200 e zero erros SQL (`tests/test_integration.js`), incluindo as novas telas de visualização de NF-e e edição.
  * Teste de integração de persistência e redirecionamento (`tests/test_compras_vendas_edit.js`) executado com 100% de aprovação.
  * Validador de regras de negócio XML (`tests/test_xml_parser.php`) aprovado com fixtures reais de compra e venda.
  * **Correção de CI para Ambientes Descartáveis (GitHub Actions):** Incluído seed automático com fixture de XML real para compras e vendas na função `initDb()` (`src/db.php`), assegurando que contêineres e bancos recém-inicializados do zero em pipelines de CI contenham registros de teste e arquivos de XML prontos para inspeção imediata. Alinhada a variável `API_KEY=pecuaria-mobile-key` no `.env.example` e em todos os runners automatizados.




