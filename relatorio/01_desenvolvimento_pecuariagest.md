# 📖 Caderno de Desenvolvimento: Sistema PecuáriaGest

> **Finalidade:** Servir como base documental e memorial descritivo para os capítulos de Introdução, Motivação, Metodologia e Engenharia de Software do TCC.

---

## 1. Origem e Motivação: O "Porquê" Real do PecuáriaGest

### 1.1 O Desafio da Gestão Remota (Cidade vs Campo)
O projeto nasceu de uma dor familiar concreta: o avô do autor é pecuarista e proprietário rural, mas reside predominantemente na cidade. 
* **O Problema da Comunicação:** O distanciamento físico gerava uma defasagem crítica nas informações. Quando um bezerro nascia, um animal adoecia ou ocorria uma pesagem, o proprietário só ficava sabendo dias depois — ou quando visitava a fazenda.
* **A Fragilidade da Caderneta de Papel:** Toda a anotação do rebanho era feita manualmente em cadernos no curral, sujeita a rasuras, umidade, perda de folhas e erros de transcrição, tornando a tomada de decisão lenta e baseada em estimativas imprecisas.
* **A Proposta de Valor:** Desenvolver um ecossistema com dois pilares integrados:
  1. **Aplicativo Mobile (Curral/Pasto):** Coletar dados com rapidez na mão do vaqueiro, funcionando 100% offline e enviando lotes assim que houver conexão.
  2. **Painel Web Centralizado (Cidade/Escritório):** Permitir que o proprietário, mesmo a centenas de quilômetros de distância, visualize os dados organizados, monitore ganho de peso, aprove coberturas e gere relatórios gerenciais consolidados.

### 1.2 O Ponto de Inflexão (Validação Externa)
Inicialmente, o PecuáriaGest foi concebido como uma aplicação teste para gerar carga de dados para a pesquisa de Computação em Nuvem. No entanto, dois eventos reais mudaram o rumo do projeto:
1. **Aprovação Imediata do Usuário-Chave:** O avô do autor testou o sistema, aprovou o fluxo e solicitou prontamente um plano para migrar todo o histórico acumulado em papel para a plataforma digital.
2. **Interesse de Mercado (Validação Agrícola):** Durante um encontro casual, um ex-aluno formado na mesma ETEC Agrícola (Colégio Agrícola) tomou conhecimento da proposta do sistema para o terceiro ano e demonstrou interesse imediato, solicitando contato para implantar a solução em sua propriedade no estado de Minas Gerais.

Esses fatos comprovaram que o software havia superado o escopo de um "protótipo acadêmico descartável", tornando-se uma solução viável com alto potencial de adoção no agronegócio familiar e de médio porte.

---

## 2. A Jornada da Arquitetura e Decisões Técnicas

### 2.1 A Descontinuação do PWA e a Adoção do Capacitor 8
* **A Tentativa com Progressive Web App (PWA):**  
  A primeira versão buscou utilizar PWA para evitar lojas de aplicativos e facilitar o acesso via navegador.
* **A Falha Prática:**  
  O PWA falhou na premissa fundamental do projeto: **confiabilidade offline absoluta**. O ciclo de atualização do Service Worker/WebAPK no Android Chrome retinha código antigo em cache por até 24 horas, e o cold-start (abertura do app sem sinal) frequentemente travava a thread do navegador, gerando sensação de tela congelada e perda de foco nos inputs.
* **A Solução com Ionic Capacitor 8:**  
  O Capacitor permitiu reaproveitar 100% da base web (HTML5, CSS e JS) empacotando-a como um aplicativo Android nativo (`.apk`). Os ativos residem diretamente no armazenamento do dispositivo (`assets/public/`), iniciando em 0ms sem depender de conexão ou Service Worker, com acesso direto a recursos de hardware (câmera traseira nativa e vibração física).

### 2.2 Da Arquitetura Monolítica aos Microsserviços Docker
Para viabilizar o estudo comparativo de nuvem e garantir isolamento de processos, a stack foi desacoplada em 3 contêineres independentes:
* **`web` (Nginx 1.25 Alpine):** Proxy reverso, entrega de arquivos estáticos, terminação HTTP/HTTPS e bloqueio de acessos a pastas internas.
* **`app` (PHP 8.3-FPM Alpine):** Processamento das regras de negócio, autenticação e geração de views.
* **`db` (PostgreSQL 16 Alpine):** Instância relacional pura com volume de dados persistente (`./storage/data`).

### 2.3 Padronização Estrita no PostgreSQL (Fim do SQLite)
O sistema inicialmente possuía suporte dual (SQLite local e PostgreSQL em produção). No entanto, as diferenças de dialeto (`strftime` vs `TO_CHAR`), tipagem fraca e limitações de concorrência em gravações simultâneas do SQLite criavam ruído metodológico nos testes de estresse. A decisão técnica foi **padronizar 100% no PostgreSQL nativo**, garantindo paridade exata entre o ambiente de desenvolvimento local e o banco gerenciado da nuvem (AWS RDS).

---

## 3. Ergonomia de Campo & Acessibilidade Visual (O Foco no Vaqueiro)

### 3.1 Alfabetização Visual e Ícones Intuitivos
Em propriedades rurais, é comum que colaboradores do campo possuam diferentes graus de escolaridade ou dificuldades com leitura técnica rápida.
* **Priorização de Símbolos:** O sistema substituiu rótulos puramente textuais por figuras e ícones vetoriais universais (balança para peso, seringa/coração para saúde, bezerro para nascimento).
* **Fluxo Semântico:** Uso de cores intuitivas (verde para saudável/ativo, vermelho para atenção/doente, preto para óbito).

### 3.2 Usabilidade sob Sol Forte e Curral
* **Design de Alto Contraste:** Cores calibradas (paleta verde terroso `#26441F` e contrastes escuros) para garantir legibilidade sob incidência solar direta.
* **Toque Amplo (Glove-Friendly):** Botões com altura mínima de **68px** e campos com 50px de altura para permitir acionamento preciso mesmo com luvas de manejo ou mãos calejadas.
* **Confirmação Tátil Nativa:** Ao salvar uma pesagem ou manejo, o hardware do celular vibra com pulso firme (`@capacitor/haptics`), dispensando a necessidade de o vaqueiro parar a lida para conferir visualmente a tela.

---

## 4. Módulo Comercial de Compra e Venda de Gado com Chaves Mestras

### 4.1 Rastreabilidade Oficial e Controle Fiscal (GTA e NF-e)
Para além do controle zootécnico interno, a comercialização de bovinos exige rigor sanitário e fiscal perante os órgãos de defesa agropecuária (como o IMA, Defesa Agropecuária estadual e Receita Federal).
* **As Chaves Mestras:** O sistema estabeleceu a obrigatoriedade/rastreabilidade do Número da **GTA (Guia de Trânsito Animal)** e da **Chave de Acesso da NF-e (44 dígitos numéricos)** como identificadores soberanos de cada operação comercial.
* **Importação Inteligente de XML da NF-e:**
  * Para eliminar erros humanos de digitação e poupar tempo do produtor, foi desenvolvido um componente de importação inteligente de arquivos XML padrão SEFAZ.
  * O leitor extrai diretamente dos nós do XML a chave de 44 dígitos, número da nota, emitente/destinatário, data de emissão, quantidade de cabeças, peso total e valor total da nota, preenchendo automaticamente os formulários em tempo real.
* **Cálculo de Precificação Dual:** Suporte a negociação tanto por **cabeça fixa (R$/cab)** quanto por **peso vivo / arroba (@)** com taxa de rendimento de carcaça configurável (ex: 50% a 54%).

---

## 5. Engenharia de Performance, Diagnóstico de Latência e Otimização

### 5.1 Diagnóstico de Gargalos Reais no Ambiente Docker
Atendendo a queixas de lentidão no navegador, foi realizada uma auditoria profunda de latência com medições comparativas (tempo de banco, tempo de CPU PHP e tempo de entrega Nginx):
1. **O Gargalo do `initDb()` repetitivo:**
   * Constatou-se que a cada requisição HTTP, a aplicação executava 25 comandos DDL (`CREATE TABLE IF NOT EXISTS`, `ALTER TABLE` e `COUNT`), somando de **15ms a 35ms de atraso desnecessário por clique**.
   * **Solução:** Implementação de um arquivo sentinela de trava (`.db_ready`), garantindo que o PostgreSQL execute as migrações apenas na primeira inicialização.
2. **Purga Inadvertida de Cache:**
   * Identificou-se um script herdado no layout web que executava `caches.delete()` a cada navegação, forçando o navegador a baixar novamente todos os assets a cada página visitada.
   * **Solução:** Remoção do expurgo e configuração de cabeçalhos de cache imutáveis de 30 dias no Nginx.
3. **Ativação da Compressão Gzip:**
   * O servidor web Nginx foi reconfigurado com compressão Gzip (nível 5), reduzindo a carga de transferência de CSS e scripts de **~500 KB para ~85 KB** (redução de 83% no tráfego de rede).
4. **Criação de Índices B-Tree Estratégicos:**
   * Foram adicionados índices em `pesagens(animal_id, data DESC)`, `animais(pasto_id, status)`, `saude`, `compras` e `vendas`, acelerando relatórios e consultas filtradas.

### 5.2 A Decisão Técnica de Não Adoção do Redis (Evitando Overengineering)
Durante os estudos de otimização, avaliou-se a introdução de uma camada de cache em memória com **Redis**. 
* **A Conclusão Metodológica:** Após a aplicação das correções estruturais descritas acima, a latência média das páginas do PostgreSQL caiu para **25ms a 35ms**, e as consultas individuais responderam em menos de **2ms**.
* **Princípio da Simplicidade:** Introduzir um quarto contêiner Docker apenas para cache traria complexidade desnecessária de invalidação de dados, consumo de memória RAM e ponto adicional de falha no servidor da fazenda, sem trazer ganho perceptível para o usuário final. Concluiu-se que o banco relacional PostgreSQL com índices adequados é mais do que suficiente para suportar rebanhos de milhares de cabeças com folga extrema de performance.

---

## 6. Arquitetura Modular MVC e Desacoplamento dos Controladores

### 6.1 O Desafio da Coesão no Front Controller Monolítico
Inicialmente, o sistema utilizava um padrão simples de arquivo único (`public/index.php`) atuando simultaneamente como roteador, processador de formulários, camada de persistência e orquestrador de templates. Com o acréscimo de regras complexas de validação zootécnica (genealogia biológica e reprodução) e importação de XMLs fiscais, o arquivo ultrapassou 1.400 linhas, gerando alta complexidade ciclomática e dificultando a manutenção paralela.

### 6.2 Implementação da Camada de Controladores Especializados (`src/controllers/`)
Para elevar o projeto ao padrão esperado pela banca acadêmica e pelas boas práticas de engenharia de software (Clean Architecture / MVC), a lógica procedural foi totalmente refatorada em **13 controladores especializados**, mantendo o `public/index.php` apenas como despachante de rotas:

1. **`BaseController.php`:** Abstração comum que provê injeção de dependência do PDO, renderização de views, respostas padronizadas em JSON, redirecionamentos e validação de tokens anti-CSRF e controle de acesso RBAC.
2. **`AuthController.php`:** Centralização do fluxo de login, encerramento de sessão e rate limiting com lockout temporário.
3. **`ComercialController.php`:** Gestão do cockpit de compras e vendas de animais, integração de XMLs e controle de faturamento por lote.
4. **`AnimaisController.php` & `PesagensController.php`:** Gestão central do rebanho, genealogia, fotos e evolução ponderal com cálculo de GMD.
5. **`SaudeController.php`, `PastagensController.php`, `ReproducaoController.php` & `AlertasController.php`:** Manejo sanitário com atualização de status, rotação de piquetes, coberturas/partos e notificações.
6. **`DashboardController.php`, `RelatoriosController.php`, `ConfiguracoesController.php`, `UsuariosController.php` & `ApiController.php`:** Gestão estratégica, auditoria de sincronizações móveis, SMTP e RBAC.

### 6.3 Resultados Práticos da Refatoração
* **Redução de Código:** O `public/index.php` foi reduzido de **~1.450 linhas para 425 linhas** (redução de 71%).
* **Autoloading Dinâmico:** Implementado via `spl_autoload_register`, garantindo que cada requisição carregue em memória apenas o controlador demandado.
* **Validação Contínua:** 100% das rotas de tela e endpoints REST validados com sucesso em suíte automatizada de testes de regressão.

---

## 7. Suíte Oficial de Relatórios Gerenciais & Fichas Técnicas em PDF (A4)

### 7.1 Engenharia de Impressão Nativa (CSS Paged Media vs Binários Pesados)
Para viabilizar a geração de documentos formais para impressão física e arquivamento fiscal sem onerar a imagem Docker Alpine com dependências pesadas (como `wkhtmltopdf` ou navegadores headless de centenas de megabytes):
* **Padronização Internacional A4:** Utilização da especificação W3C CSS Paged Media (`@page { size: A4 portrait; margin: 12mm 14mm; }`).
* **Preservação de Cores e Gráficos:** Ativação de `-webkit-print-color-adjust: exact` e `print-color-adjust: exact`, garantindo fidelidade das cores corporativas e gráficos na impressão e na exportação em PDF.
* **Logotipo Vetorial Institucional:** Inclusão do brasão oficial em SVG (`/favicon.svg`) com escala vetorial nítida em qualquer resolução de impressão.
* **Barra de Ferramentas com Disparo Nativo (`.print-toolbar`):** Visualizador em tela com botão de disparo direto de `window.print()` e retorno suave, ocultado automaticamente na impressão (`@media print { .no-print { display: none !important; } }`).

### 7.2 Os 5 Documentos Zootécnicos e Comerciais Homologados
1. **Ficha Cadastral & Prontuário Zootécnico Individual (`/animais/{id}/pdf`):** Foto identificadora, biometria (peso inicial e atual em kg e @), cálculo de idade, genealogia biológica (mãe e touro), histórico de pesagens com Ganho Médio Diário (GMD em kg/dia), ocorrências sanitárias e histórico reprodutivo.
2. **Espelho de Compra de Gado & Lote de Entrada (`/compras/{id}/pdf`):** GTA de entrada, Chave de Acesso da NF-e (44 dígitos), fornecedor de origem, peso total, arrobas totais (@), custo médio por cabeça, custo da arroba (R$/@), status do XML SEFAZ e romaneio de animais ingressados.
3. **Comprovante Oficial de Venda & Desinvestimento (`/vendas/{id}/pdf`):** GTA de saída, NF-e de 44 dígitos, comprador/frigorífico, modalidade de precificação (@ ou cabeça), peso total, preço efetivo da arroba, apuração de lucro bruto e margem comercial (%), e lista de animais baixados do rebanho ativo.
4. **Inventário Geral do Rebanho & Balanço de Pastagens (`/relatorios/pdf?tipo=rebanho`):** Posição consolidada do rebanho, total de ativos, machos vs fêmeas, peso médio (kg e @), balanço por pastagem/piquete (área, capacidade, lotação atual, % de ocupação e densidade cab/ha) e romaneio analítico.
5. **Laudo Sanitário & Manejo Clínico (`/relatorios/pdf?tipo=saude`):** Auditoria clínica consolidada, investimento total acumulado em fármacos (R$), custo médio por intervenção, distribuição por tipo de manejo (vacinas, tratamentos, exames) e log cronológico detalhado por veterinário responsável.

### 7.3 Customização e Filtragem Granular dos Relatórios
Para evitar a simples emissão de documentos estáticos de todo o rebanho, implementou-se um sistema duplo de filtragem e customização:
* **Filtros Granulares Pré-Geração (Servidor):**
  * Modais em `/relatorios` permitindo selecionar: pastagem/piquete, sexo biológico, categoria de idade (bezerros ≤12 meses vs adultos >12 meses com cálculo dinâmico), raça, status (ativos, vendidos, mortos ou todos) e modo sintético executivo de 1 página (omissão do romaneio individual).
  * **Filtro por Prefixo / Início do Brinco ou Nome:** Busca de lotes específicos de teste ou manejo por prefixo (ex: `T001`, `T002`, `PG_` ou múltiplos valores separados por vírgula).
  * **Seleção Manual Individual ("Vaca por Vaca"):** Sub-aba retrátil no modal com busca instantânea em tempo real via JavaScript, botões para marcar visíveis/limpar e contador dinâmico de animais selecionados.
* **Emissão Direta por Animal:**
  * Botão com ícone de PDF em cada linha da tabela de animais (`/animais`), permitindo baixar a ficha técnica de qualquer vaca com 1 clique direto.
  * Botão de impressão no topo de `/animais` preservando os filtros ativos da tela.
  * Link e botão interativo no brinco de cada animal na tabela analítica do PDF consolidado.
* **Alternância Dinâmica de Seções (Live Section Toggles):**
  * Na própria barra de ferramentas de impressão (`.print-toolbar`), o menu *"Personalizar Seções"* detecta elementos com `[data-printable-section]` e permite que o usuário desmarque blocos inteiros em tempo real, sumindo da tela e da impressão física/PDF sem necessidade de nova requisição ao servidor.

---

## 8. Padronização Visual e Sobriedade Institucional (Bootstrap Icons)

### 8.1 Da Linguagem Informal à Sobriedade Acadêmica
Nas primeiras iterações do sistema, foram utilizados emojis de navegador (como 🐄, ⚖️, ⚕️, 🐣, ⚠️) para sinalizar rapidamente as seções.
* **O Risco Perante a Banca:** Em uma apresentação acadêmica formal de TCC, o uso de emojis pode transmitir informalidade excessiva ou causar variações indesejadas de renderização visual entre diferentes sistemas operacionais (Android, Windows, Linux e macOS).
* **A Padronização com Bootstrap Icons:**
  * Todos os emojis foram substituídos por ícones vetoriais puros da biblioteca **Bootstrap Icons**, integrada localmente ao projeto para não depender de CDN externa.
  * Padronização de ícones técnicos: `<i class="bi bi-tag-fill"></i>` para animais e brincos, `<i class="bi bi-rulers"></i>` para pesagens e balanças, `<i class="bi bi-heart-pulse-fill"></i>` para manejo sanitário, `<i class="bi bi-stars"></i>` para bezerros e memória de filhote, `<i class="bi bi-exclamation-triangle-fill"></i>` para censura de fotos sensíveis e óbitos, e `<i class="bi bi-file-earmark-pdf-fill"></i>` para documentos oficiais.
  * O resultado garantiu harmonia estética, identidade corporativa limpa e máxima sobriedade visual para a banca examinadora.


