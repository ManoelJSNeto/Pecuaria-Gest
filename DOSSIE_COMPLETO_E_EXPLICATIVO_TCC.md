# 📚 DOSSIÊ CIENTÍFICO E COMPARATIVO DE DESEMPENHO (TCC)
## Estudo de Caso: PecuáriaGest — Infraestrutura Física Local (On-Premise) vs Computação em Nuvem (Amazon Web Services)

> **Data de Consolidação:** 12 de Setembro de 2026  
> **Autor do Trabalho:** Manoel Jorge dos Santos Neto  
> **Finalidade:** Pacote Mestre Unificado para Redação e Defesa de Trabalho de Conclusão de Curso (TCC)  
> **Integridade Acadêmica:** 100% dos dados brutos preservados individualmente com carimbo de tempo (*timestamp*).

---

## 📑 Sumário Executivo

Este documento sintetiza, documenta e compara as duas grandes baterias experimentais de desempenho executadas no sistema **PecuáriaGest**:
1. **O 1º Teste (Histórico / Preliminar):** Executado avaliando o ambiente local com SQLite WAL contra uma instância preliminar na AWS.
2. **O Novo Teste (Científico / Oficial N=3):** Executado com total simetria de banco de dados (PostgreSQL 16 oficial no Local e PostgreSQL 16.9 no Amazon RDS), avaliado através de uma **Tríade de Desempenho** (API Transacional, Ingestão de Mídia Pesada e Navegação Real W3C).
3. **Diagnóstico Técnico de CPU e Recursos:** Explicação aprofundada de por que a CPU atingiu 100% no primeiro teste e manteve-se controlada e eficiente no novo teste.
4. **Tabela Comparativa Consolidada:** Síntese lado a lado de todos os cenários.
5. **Guia de Redação para o TCC:** Como estruturar o Capítulo de Resultados e Discussão da monografia.

---

## 🔍 1. A Grande Dúvida Técnica: O Que Aconteceu com a CPU?

Uma das perguntas centrais levantadas durante a pesquisa foi:  
*“Por que no primeiro teste a CPU da máquina foi a 100% (com ventoinhas aceleradas e Docker no limite) e no teste mais recente o processamento foi rápido, frio e estável?”*

A resposta técnica reside na evolução da arquitetura do banco de dados e no modelo de concorrência:

### A. O Comportamento no 1º Teste (Gargalo de Bloqueio no SQLite)
* **Arquitetura Utilizada:** SQLite em modo WAL (*Write-Ahead Logging*).
* **O Problema do Bloqueio de Arquivo (*Lock Contention*):**  
  O SQLite é uma biblioteca embutida em que toda a base de dados reside em um **único arquivo no sistema de arquivos**. Embora o modo WAL permita múltiplos leitores concorrentes, ele permite **apenas um único escritor por vez**.
* **O Efeito na CPU:**  
  Quando 50 e 100 usuários virtuais dispararam sincronizações de dados simultâneas (operações de escrita `INSERT` e `UPDATE`), o arquivo entrou em contenção de concorrência contínua.  
  O PHP-FPM, ao tentar gravar no banco ocupado, entrou em um ciclo de **espera ativa (*busy-wait spinlock*)**: o interpretador PHP ficava repetindo chamadas em microssegundos tentando furar o bloqueio do arquivo. Isso saturou todos os núcleos do processador da máquina hospedeira em **100% de uso constante**, aumentando a temperatura do hardware e gerando a percepção de sobrecarga extrema.

### B. O Comportamento no Novo Teste Oficial (PostgreSQL 16 + MVCC)
* **Arquitetura Utilizada:** PostgreSQL 16 nativo (conteinerizado no Local e Amazon RDS na AWS).
* **O Mecanismo MVCC (*Multi-Version Concurrency Control*):**  
  O PostgreSQL é um Sistema Gerenciador de Banco de Dados Relacional (SGBDR) corporativo projetado para alta concorrência. Ele não bloqueia tabelas ou arquivos inteiros durante escritas; ele cria versões de tuplas em nível de linha (*row-level locking*).
* **O Efeito na CPU:**  
  Quando os 100 usuários enviaram dados em paralelo, o PostgreSQL enfileirou e processou as transações de forma assíncrona e ordenada em conexões dedicadas. O PHP não precisou girar em falso; ele apenas enviou a query e aguardou o retorno via socket de rede. Por essa razão, a CPU permaneceu fria, controlada e o teste concluiu em fração de segundos.

---

## 📊 2. Tabela Comparativa Geral: 1º Teste (Histórico) vs Novo Teste (Oficial)

Abaixo é apresentado o comparativo direto entre as duas baterias de testes sob as mesmas cargas de concorrência (20, 50 e 100 usuários simultâneos):

| Ambiente | Concorrência | Vazão 1º Teste (req/s) | **Vazão Novo Teste (req/s)** | Latência 1º Teste (Média) | **Latência Novo Teste (P50)** | Taxa de Erro HTTP | Integridade ACID | Comportamento da CPU |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|---|
| 🏠 **Local** | **20 users** | 101.55 req/s | **118.37 req/s** (+16.5%) | 188.6 ms | **147.3 ms** | **0.00%** | **100.0%** | CPU Estável |
| 🏠 **Local** | **50 users** | 72.36 req/s | **105.14 req/s** (+45.3%) | 665.7 ms | **466.9 ms** | **0.00%** | **100.0%** | CPU Controlada |
| 🏠 **Local** | **100 users** | 49.62 req/s | **90.65 req/s** (+82.7%) 🚀 | 1.925.5 ms | **1.089.9 ms** ⚡ | **0.00%** | **100.0%** | **Antes: 100% Spinlock<br>Novo: 100% Estável** |
| ☁️ **Nuvem AWS** | **20 users** | 28.26 req/s | **43.40 req/s** (+53.5%) | 692.1 ms | **447.8 ms** | **0.00%** | **100.0%** | Baixa utilização |
| ☁️ **Nuvem AWS** | **50 users** | 27.66 req/s | **44.35 req/s** (+60.3%) | 1.741.6 ms | **1.021.6 ms** | **0.00%** | **100.0%** | Moderada (~45%) |
| ☁️ **Nuvem AWS** | **100 users** | 25.70 req/s | **47.20 req/s** (+83.6%) 🚀 | 3.726.3 ms | **2.034.9 ms** ⚡ | **0.00%** | **100.0%** | **Quase o dobro da vazão!** |

> 📌 **Conclusão Técnica Primária:** A migração de engenharia para o PostgreSQL 16 proporcionou um ganho de até **+83.6% na capacidade de atendimento na nuvem AWS** e reduziu a latência pela metade, eliminando integralmente as falhas de contenção de banco.

---

## 🏛️ 3. A Nova Metodologia: A Tríade Experimental de Desempenho

Diferente de testes sintéticos tradicionais que testam apenas requisições `GET` simples, o novo teste oficial do PecuáriaGest foi modelado em **três pilares interdependentes**:

### Pilar 1: Carga Transacional de API & Integridade ACID
* **O que faz:** Simula 20, 50 e 100 trabalhadores rurais sincronizando pesagens de gado e consultando lotes via API REST autenticada com Bearer Token.
* **Mecanismo Científico:** Antes de cada rodada, o banco é truncado e recebe um seed padrão de exatamente 30 animais (`T0001` a `T0030`). Ao término de cada rodada, um script de auditoria verifica se todas as pesagens enviadas foram fisicamente gravadas no banco (100% de conformidade ACID).

### Pilar 2: Estresse de Mídia & I/O de Disco (Fotos + XML)
* **O que faz:** Dispara pacotes concorrentes contendo fotos em alta resolução de animais (~1.5 MB cada) e notas fiscais eletrônicas (XML de compra de insumos/gado).
* **Cargas avaliadas:** 5, 10 e 20 uploads simultâneos.
* **Resultado:**
  * **Local:** Ingestão de **70 a 128 MB/s** direto no NVMe.
  * **AWS:** Ingestão de **6.5 a 22.4 MB/s** delimitada pela taxa de upload da banda larga.
  * **Taxa de Sucesso:** **100.0%** em todos os arquivos enviados na nuvem.

### Pilar 3: Navegador Real Headless (Métricas Oficiais W3C)
* **O que faz:** Instancia o motor Chromium/Edge sem interface gráfica, acessa a URL pública da AWS, efetua login com sessão criptografada, carrega o Dashboard e realiza o parse de uma NF-e no cliente.
* **Métricas Extraídas:**
  * **TTFB (Time to First Byte):** 65.2 ms (Local) vs **206.7 ms (AWS)**. Ambas com classificação "Excelente" (< 800 ms pelo Google Web Vitals).
  * **Carga Total da Página:** 662.9 ms (Local) vs **863.6 ms (AWS)** (menos de 1 segundo para interatividade completa).

---

## 🌐 4. Explicação Física e Geográfica do Desempenho da Nuvem (AWS)

Um ponto frequentemente questionado por bancas examinadoras é:  
*“Por que a nuvem tem um tempo de resposta maior do que o computador local?”*

A explicação é estritamente **geográfica e física**:
1. **RTT (Round-Trip Time) da Fibra Óptica:**  
   No teste local, os pacotes trafegam pela interface de rede interna do próprio computador (*loopback* `127.0.0.1`), com latência de zero milissegundos. No teste da AWS, cada requisição sai da máquina cliente no Brasil, percorre a rede metropolitana, atravessa cabos submarinos internacionais até o data center da AWS em North Virginia/EUA (`us-east-1`) e retorna. A física da velocidade da luz na fibra dita que esse trajeto de ida e volta consome entre **130 ms e 150 ms** exclusivamente em trânsito de rede.
2. **Desacoplamento de Camadas:**  
   No Local, a aplicação e o banco compartilham a mesma CPU e memória. Na AWS, a instância EC2 (aplicação) se conecta ao Amazon RDS (banco) por meio de uma rede virtual privada (VPC), o que adiciona 1 a 2 ms em cada consulta SQL.
3. **Trade-off de Engenharia:**  
   Embora a nuvem possua latência ligeiramente superior à rede local, ela entrega **alta disponibilidade**, **redundância de energia**, **backups automáticos contínuos**, **acesso remoto para gestores fora da fazenda** e proteção contra perdas de dados por sinistros físicos na propriedade rural.

---

## 📁 5. Estrutura de Arquivos deste Pacote Completo

Este pacote contém todas as pastas organizadas para consulta imediata:

```text
PACOTE_COMPLETO_BENCHMARK_TCC/
│
├── DOSSIE_COMPLETO_E_EXPLICATIVO_TCC.md   <-- (Este documento explicativo mestre)
│
├── 01_PRIMEIRO_TESTE_HISTORICO/           <-- Pacote original do 1º teste (SQLite vs AWS preliminar)
│   ├── README.md
│   ├── RESULTADOS_CONSOLIDADOS_TCC.md
│   ├── DOSSIE_INFRA_PRIMEIROS_TESTES.md
│   ├── monitor_cpu.js                    <-- Script original de telemetria de CPU
│   └── resultados/                       <-- Planilhas CSV do 1º teste
│
├── 02_NOVO_TESTE_CIENTIFICO_OFICIAL/      <-- Bateria científica oficial N=3 da Tríade
│   ├── TABELA_CONSOLIDADA_TCC.md          <-- Tabela formatada em Markdown com médias e desvios
│   ├── dashboard_comparativo_tcc.html     <-- Dashboard interativo com gráficos Chart.js
│   ├── dataset_bruto_unificado_tcc.csv    <-- 7.249 registros brutos individuais para auditoria
│   ├── dataset_bruto_unificado_tcc.json
│   ├── screenshot_dashboard_aws.png       <-- Evidência visual do Dashboard na nuvem
│   ├── screenshot_xml_preview_aws.png     <-- Evidência visual do leitor de NF-e na nuvem
│   └── scripts_de_teste/                  <-- Scripts orquestradores (executar_triade.js, etc.)
│
├── 03_INFRAESTRUTURA_TERRAFORM_AWS/       <-- Código-fonte completo de Infraestrutura como Código (IaC)
│   ├── ec2.tf                             <-- Provisionamento da máquina virtual Ubuntu 24.04
│   ├── rds.tf                             <-- Banco gerenciado PostgreSQL 16.9 oficial
│   ├── vpc.tf                             <-- Rede isolada com subnets públicas
│   ├── security_groups.tf                 <-- Regras de firewall blindadas (Zero SSH)
│   ├── user_data.sh                       <-- Bootstrap automatizado de Docker e contêineres
│   ├── variables.tf
│   └── outputs.tf
│
└── 04_RELATORIOS_E_FUNDAMENTACAO/         <-- Documentos teóricos da pesquisa
    ├── 00_estrategia_e_decisao_tcc.md
    ├── 01_desenvolvimento_pecuariagest.md
    ├── 02_computacao_em_nuvem_e_testes.md
    └── 03_discussoes_e_decisoes_gerais.md
```

---

## 🎯 6. Roteiro Sugerido para a Redação do TCC

Ao redigir o **Capítulo de Resultados e Discussão**, siga a seguinte estrutura lógica:

1. **Seção 4.1 — Ambiente Experimental e Configuração:**  
   Descreva a máquina física local (processador, memória, Docker Desktop) e a infraestrutura na nuvem AWS (VPC, EC2 t3.micro, RDS PostgreSQL 16.9 gerenciado), anexando o diagrama de blocos arquitetural.
2. **Seção 4.2 — O Experimento Preliminar e a Descoberta da Concorrência:**  
   Apresente o 1º teste. Discuta como o SQLite gerou saturação de 100% de CPU por contenção de arquivo (*file locking*), fundamentando a decisão de migrar para o PostgreSQL com controle de concorrência multiversão (MVCC).
3. **Seção 4.3 — Análise da Tríade de Desempenho Oficial ($N=3$):**  
   * Apresente a tabela do **Pilar 1 (API)** demonstrando a estabilidade de vazão (47 req/s na AWS e 90 req/s no Local) e **0.00% de taxa de erro**.
   * Apresente o **Pilar 2 (Mídia)** discutindo o limite de banda de upload da conexão física em relação ao barramento NVMe local.
   * Apresente o **Pilar 3 (Navegador Headless)** destacando que o tempo total de carregamento da aplicação na nuvem foi de **863 ms** (< 1 segundo), garantindo excelente usabilidade para o produtor rural.
4. **Seção 4.4 — Considerações Finais de Custo e Disponibilidade:**  
   Conclua ponderando que, para operações isoladas sem conectividade, a borda (*Edge/Local*) é essencial, mas para a consolidação dos dados da fazenda e acesso remoto gerencial, a nuvem AWS provou ser altamente resiliente e confiável.
