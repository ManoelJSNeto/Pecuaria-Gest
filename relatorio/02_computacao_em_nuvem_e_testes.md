# ☁️ Caderno de Computação em Nuvem, Benchmarks & Infraestrutura

> **Finalidade:** Registrar a fundamentação experimental, o isolamento das variáveis científicas, o desenho dos testes de estresse com Apache JMeter e a arquitetura segura na AWS para a monografia do TCC.

---

## 1. O Problema da Infraestrutura Rural e a Hipótese da Nuvem

### 1.1 A Inviabilidade Técnica do Servidor On-Premise na Fazenda
Uma propriedade pecuária média no Brasil enfrenta restrições severas de infraestrutura física:
* **Instabilidade Elétrica:** Quedas constantes de energia, picos de tensão provocados por raios e ausência de nobreaks com autonomia prolongada.
* **Ambiente Hostil:** Alta incidência de poeira, umidade e calor no barracão/sede, acelerando a degradação de hardware comum.
* **Ausência de Suporte Local:** A fazenda não possui profissional de TI para realizar rotinas de backup, aplicar patches de segurança do sistema operacional ou restabelecer serviços caídos.
* **Link de Internet Oscilante:** Conexões rurais (via rádio ou satélite) possuem IP dinâmico sob CGNAT, impedindo o redirecionamento de portas (port forwarding) e o acesso remoto estável do proprietário pela cidade.

### 1.2 A Hipótese Científica da Pesquisa
Uma infraestrutura gerenciada em computação em nuvem (**Amazon Web Services — AWS**), quando combinada com uma arquitetura móvel *offline-first*, é capaz de:
1. Absorver as rajadas de sincronização em lote enviadas pelos dispositivos de campo com menor latência e zero perda de pacotes;
2. Proporcionar alta disponibilidade (99,9%+) e tolerância a desastres através de backups automatizados;
3. Apresentar viabilidade financeira (OpEx previsível em nuvem versus o custo de aquisição e manutenção de hardware dedicado — CapEx).

---

## 2. Rigor Científico: O Isolamento da Variável Independente

### 2.1 A Crítica Metodológica do Orientador
Nos testes preliminares apresentados à orientação, identificou-se uma fragilidade no desenho experimental:
* O ambiente local utilizava banco de dados em arquivo (**SQLite**), enquanto a proposta de nuvem previa um banco relacional gerenciado (**PostgreSQL RDS**).
* **O Viés Metodológico:** A diferença de desempenho observada nas requisições concorrentes não refletia puramente a eficiência da nuvem versus local, mas sim as discrepâncias intrínsecas entre os motores de banco (bloqueio de arquivo do SQLite vs concorrência MVCC do PostgreSQL).

### 2.2 A Correção da Variável de Teste
Para garantir o rigor acadêmico exigido em bancas de Ciência da Computação:
* **Padronização do Banco:** O SQLite foi completamente erradicado do código-fonte. O sistema foi refatorado para operar exclusivamente com **PostgreSQL 16 nativo**.
* **Equiparação dos Ambientes:** A stack de contêineres desacoplados (Nginx + PHP 8.3-FPM + PostgreSQL 16) roda idêntica tanto no servidor local quanto na instância em nuvem.
* **Variável Independente Isolada:** O único elemento que varia no experimento é o **ambiente de hospedagem** (On-Premise x Nuvem AWS), validando cientificamente as métricas de comparação.

---

## 3. Expertise Técnica e Escolha da Plataforma AWS

A seleção da **Amazon Web Services (AWS)** fundamenta-se em dois pilares estratégicos:
1. **Maturidade e Padrão de Indústria:** A AWS é a líder global em serviços de nuvem pública, dispondo de zonas de disponibilidade locais (região `sa-east-1` em São Paulo) com baixa latência para o território nacional.
2. **Qualificação da Equipe:** Dois integrantes do grupo de pesquisa são competidores de Computação em Nuvem pelo **SENAI** (preparados para olimpíadas técnicas de TI), trazendo domínio prático de arquitetura em nuvem e suporte de mentores especializados para além do currículo tradicional da ETEC.

---

## 4. Arquitetura de Nuvem com Foco em Segurança Avançada

Para além da performance de rede, o projeto implementa padrões corporativos de segurança em nuvem recomendados pela AWS (*Well-Architected Framework*):

### 4.1 Eliminação de Portas de Gerenciamento Expostas (SSH ➔ AWS SSM)
* **A Vulnerabilidade Tradicional:** Abrir a porta TCP 22 (SSH) para a internet expõe o servidor a varreduras automatizadas e ataques de força bruta, além do risco de vazamento de chaves privadas `.pem`.
* **A Solução Adotada (AWS Systems Manager — SSM Session Manager):** O acesso administrativo à instância EC2 ocorre via canal criptografado da AWS através do IAM, **com a porta 22 totalmente fechada nos Security Groups**. Não há necessidade de IP público direto ou chaves SSH estáticas. Toda sessão é auditada e registrada no **AWS CloudTrail**.

### 4.2 Políticas de Menor Privilégio (AWS IAM)
* Definição de papéis (*IAM Roles*) restritos vinculados à instância EC2, permitindo que a aplicação execute apenas ações essenciais (como envio de logs para o CloudWatch e leitura de parâmetros protegidos no SSM Parameter Store), sem credenciais fixadas em código (`hardcoded credentials`).

### 4.3 Isolamento de Rede em Camadas (VPC & Subnets)
* A instância web/aplicação fica em camada acessível externamente via HTTPS (porta 443 com certificado TLS).
* O banco de dados gerenciado (**Amazon RDS PostgreSQL**) reside em sub-rede privada, sem IP público, comunicando-se exclusivamente com a instância EC2 por regra restrita de Security Group na porta 5432.

---

## 5. Dicionário Técnico e Metodológico das Métricas de Benchmark

Para assegurar rigor analítico e compreensão incontestável na avaliação da banca de TCC, todas as grandezas monitoradas no experimento são formalmente conceituadas abaixo:

### 5.1 Métricas de Experiência e Tempo de Resposta (Latência)
1. **Latência Média (Response Time / Mean — ms):**
   * **Conceito Matemático:** Média aritmética dos tempos decorridos entre o envio do primeiro byte da requisição pelo cliente e o recebimento do último byte de resposta do servidor ($\bar{x} = \frac{1}{n}\sum_{i=1}^n t_i$).
   * **Papel no TCC:** Oferece uma visão panorâmica inicial da rapidez do ambiente, embora seja estatisticamente vulnerável a distorções causadas por poucos valores atípicos (*outliers*).
2. **Mediana / 50º Percentil (P50 — ms):**
   * **Conceito Matemático:** Ponto central da distribuição ordenada de tempos. Exatamente 50% de todas as sincronizações foram processadas em tempo igual ou inferior ao P50.
   * **Papel no TCC:** Representa a experiência real vivenciada pelo vaqueiro/operador no dia a dia da fazenda sob fluxo típico de trabalho.
3. **90º e 95º Percentis (P90 e P95 — ms):**
   * **Conceito Matemático:** O tempo máximo no qual 90% e 95% das requisições foram concluídas. Os 5% restantes representam o comportamento em momentos de pico ou saturação.
   * **Papel no TCC:** É o padrão de ouro da indústria para definição de **SLA (Service Level Agreement)** e **SLO (Service Level Objective)** em arquiteturas distribuídas. No agronegócio, demonstra o comportamento do sistema quando vários operadores chegam ao curral simultaneamente para descarregar dados.
4. **99º Percentil / Cauda Longa (P99 / Tail Latency — ms):**
   * **Conceito Matemático:** O tempo máximo para 99% das requisições, isolando o pior 1% dos casos.
   * **Papel no TCC:** Revela gargalos microscópicos severos de infraestrutura, como pausas de coleta de lixo (*garbage collection*), bloqueios de escrita concorrente em tabelas do PostgreSQL (*table/row lock contention*), esgotamento de conexões ou atrasos de rede (*TCP retransmission*).

### 5.2 Métricas de Produtividade e Confiabilidade do Servidor
5. **Vazão / Produtividade Efetiva (Throughput / RPS — req/s):**
   * **Conceito Matemático:** Quantidade de requisições de sincronização completadas e persistidas com sucesso por segundo ($RPS = \frac{\text{Requisições Válidas}}{\text{Tempo Total de Teste}}$).
   * **Papel no TCC:** Mede a capacidade bruta de processamento da infraestrutura. Demonstra o teto de escalabilidade de cada ambiente antes de sofrer estrangulamento.
6. **Taxa Real de Erro (%):**
   * **Conceito Matemático:** Percentual de requisições que resultaram em códigos de erro HTTP (como 500 Internal Server Error, 502 Bad Gateway, 504 Gateway Timeout) ou falhas de conexão (*socket timeout/connection reset*).
   * **Papel no TCC:** Uma vazão alta não tem valor se os dados forem descartados. A taxa de erro atesta se o sistema manteve a **consistência transacional (ACID)** e resiliência sob pressão extrema.

### 5.3 Métricas de Consumo Computacional de Hardware
7. **Consumo de CPU (% de Utilização, User vs System e Throttling):**
   * **Conceito Técnico:** Percentual de ciclos de processador consumidos.
     * *CPU User:* Tempo gasto executando o código PHP 8.3 e parsing do JSON de sincronização.
     * *CPU System:* Tempo gasto pelo kernel do Linux, Nginx e pilha de rede TCP/IP.
     * *CPU Steal / Throttling:* Fenômeno crítico em instâncias elásticas (*burstable* como `t3.micro` da AWS, `B1s` da Azure ou `e2-micro` do GCP). Quando a aplicação consome todos os créditos de CPU acumulados, a nuvem estrangula compulsoriamente a CPU para o nível basal (baseline de 10% a 20%), aumentando a latência de forma drástica.
8. **Consumo de Memória RAM (MB / GB e % Utilizada):**
   * **Conceito Técnico:** Memória residente física (*Resident Set Size — RSS*) alocada pelos processos *workers* do PHP-FPM e pelo buffer compartilhado do PostgreSQL (*shared_buffers* e *work_mem*).
   * **Papel no TCC:** Avalia o risco de esgotamento de memória (*Out-Of-Memory Killer*) e a estabilidade da pilha ao longo do tempo.
9. **Tráfego de Rede (Network I/O — Ingress/Egress em KB/s):**
   * **Conceito Técnico:** Volume de dados transferidos para dentro (*Ingress*) e fora (*Egress*) do servidor, medindo a eficiência da compressão Gzip e o impacto de uploads de imagens de campo.
10. **Eficiência Econômica / Custo por Mil Requisições (R$ ou US$ / 1.000 reqs):**
    * **Conceito Técnico:** Métrica original de Engenharia de Software que correlaciona a capacidade de entrega com o custo financeiro mensal da nuvem, permitindo ao produtor rural calcular o custo exato de TI por cabeça de gado manejada.

---

## 6. Histórico da Primeira Bateria Experimental (Baseline Preliminar N=3)

Para efeito de registro histórico e comparação com as novas rodadas de teste, os dados originais da primeira bateria executada (avaliando o ambiente Local versus a AWS) foram preservados no repositório.

### 6.1 Resultados Consolidados da 1ª Rodada (N=3 Repetições Simétricas)

| Ambiente | Concorrência | N (Runs) | Vazão Média (req/s) | Desvio Padrão | Latência Média (ms) | Desvio Padrão | Mediana (P50) | Percentil 95 (P95) | Taxa Erro HTTP |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 🏠 **Local (SQLite WAL)** | **20 users** | 3 | **101.55 req/s** | ±2.64 | **188.6 ms** | ±5.0 | 189.2 ms | 253.5 ms | **0.00%** |
| 🏠 **Local (SQLite WAL)** | **50 users** | 3 | **72.36 req/s** | ±14.86 | **665.7 ms** | ±128.2 | 698.4 ms | 990.9 ms | **0.00%** |
| 🏠 **Local (SQLite WAL)** | **100 users** | 3 | **49.62 req/s** | ±2.69 | **1925.5 ms** | ±112.3 | 2023.4 ms | 3042.7 ms | **0.00%** |
| ☁️ **AWS (t3.micro + RDS)** | **20 users** | 3 | **28.26 req/s** | ±0.78 | **692.1 ms** | ±17.6 | 681.7 ms | 860.8 ms | **0.00%** |
| ☁️ **AWS (t3.micro + RDS)** | **50 users** | 3 | **27.66 req/s** | ±0.31 | **1741.6 ms** | ±23.5 | 1776.0 ms | 2044.6 ms | **0.00%** |
| ☁️ **AWS (t3.micro + RDS)** | **100 users** | 3 | **25.70 req/s** | ±0.92 | **3726.3 ms** | ±144.8 | 3745.3 ms | 5181.2 ms | **0.00%** |

### 6.2 Preservação do Pacote de Dados Brutos dos Primeiros Testes
Todos os arquivos de log, scripts de consolidação, planilhas CSV individuais de cada run e o dashboard comparativo interativo em HTML gerados na primeira rodada estão arquivados na pasta compactada:
* 📁 **Arquivo:** [`relatorio/dados_primeiros_testes_benchmark.zip`](dados_primeiros_testes_benchmark.zip)

### 6.3 Diagnóstico Crítico dos Primeiros Testes
A análise dos dados do primeiro experimento revelou duas oportunidades capitais de evolução metodológica:
1. **Assimetria de Bancos:** O ambiente local utilizava SQLite WAL e a AWS utilizava PostgreSQL RDS. Na nova rodada, ambos rodarão exatamente a mesma imagem do PostgreSQL 16 nativo.
2. **Latência Geográfica Elevada:** O teste na AWS sofreu penalidade de tráfego de longa distância (região internacional ou rota desotimizada), atingindo ~692ms para 20 usuários.

---

## 7. Nova Proposta Científica: O Estudo Comparativo Multi-Cloud

Em vez de limitar o TCC à clássica dicotomia "Local vs AWS", a pesquisa avança para um **Benchmarking Científico Multi-Cloud**, comparando as três maiores provedoras de computação em nuvem do mercado mundial contra a infraestrutura física local:

```
                               ┌─────────────────────────────┐
                               │  Bateria Concorrente JMeter │
                               │   (20, 50 e 100 Usuários)   │
                               └──────────────┬──────────────┘
                                              │
               ┌──────────────────────────────┼──────────────────────────────┐
               ▼                              ▼                              ▼
    ┌──────────────────────┐      ┌──────────────────────┐      ┌──────────────────────┐
    │     Amazon AWS       │      │     Google GCP       │      │   Microsoft Azure    │
    │ • EC2 t3.micro       │      │ • Compute Engine e2  │      │ • Azure VM B1s       │
    │ • RDS PostgreSQL     │      │ • Cloud SQL Postgres │      │ • Postgres Flexible  │
    │ • Região São Paulo   │      │ • Região São Paulo   │      │ • Região BrazilSouth │
    └──────────────────────┘      └──────────────────────┘      └──────────────────────┘
               ▲                              ▲                              ▲
               └──────────────────────────────┼──────────────────────────────┘
                                              │
                               ┌──────────────┴──────────────┐
                               │     Ambiente On-Premise     │
                               │  Docker Local PostgreSQL 16 │
                               └─────────────────────────────┘
```

### 7.1 Por que o Comparativo Multi-Cloud Eleva o Nível do TCC?
1. **Rigor Científico de Padrão Internacional:** Comparações multi-cloud aplicadas ao agronegócio representam estado da arte acadêmico, agregando imenso valor ao currículo da equipe e atraindo atenção destacada da banca examinadora.
2. **Portabilidade Real de Contêineres:** Demonstra empiricamente que a arquitetura Docker construída para o PecuáriaGest é verdadeiramente agnóstica de provedor (*cloud-agnostic*), sem aprisionamento tecnológico (*vendor lock-in*).
3. **Cenário Financeiro Real (Free Tier / Créditos de Estudante):**
   * **AWS:** Coberta pelo Free Tier de 12 meses (EC2 `t3.micro` + RDS `db.t3.micro`).
   * **Google Cloud (GCP):** Conta com US$ 300 em créditos de teste válidos por 90 dias, permitindo testes completos com instâncias `e2-micro`/`e2-small` e Cloud SQL na região de São Paulo (`southamerica-east1`) a custo real zero.
   * **Microsoft Azure:** Conta com US$ 200 em créditos iniciais para 30 dias, viabilizando instâncias `Standard_B1s` e banco flexível na região de São Paulo (`brazilsouth`).

---

## 8. Engenharia de Otimização e Minimização de Latência na Nuvem

Para que a nuvem compita em igualdade de condições de rede com a infraestrutura local, foi desenhado um pacote de otimizações de baixa latência para os testes:

### 8.1 Proximidade Geográfica (Região de São Paulo)
* **O Efeito da Distância Física:** Conexões com datacenters nos Estados Unidos (como `us-east-1` no Norte da Virgínia) impõem um atraso físico de ida e volta (*Round-Trip Time — RTT*) de **120ms a 180ms**, puramente pela velocidade da luz na fibra ótica submarina.
* **A Implantação em São Paulo:** Ao alocar a infraestrutura na região metropolitana de São Paulo (`sa-east-1` na AWS, `southamerica-east1` no GCP e `brazilsouth` na Azure), a latência física da rota cai para **15ms a 35ms** a partir do território paulista/mineiro, reduzindo o tempo de resposta em até **80%**.

### 8.2 Colocation e Baixa Latência Interna (Mesma AZ e Subnet)
* A máquina virtual de aplicação (Nginx + PHP-FPM) e a instância de banco de dados gerenciado (PostgreSQL RDS) devem ser fixadas na **mesma Zona de Disponibilidade física** (ex: `sa-east-1a`).
* Isso garante que a comunicação entre o PHP e o banco de dados trafegue por fibra ótica interna de altíssima velocidade do datacenter, com latência entre servidores inferior a **1 milissegundo**.

### 8.3 Otimização de Conexões Persistentes do Banco de Dados
* Em cargas elevadas de requisições concorrentes, abrir e fechar uma conexão TCP com o banco de dados a cada lote de sincronização gera um *overhead* proibitivo de handshakes.
* **Solução:** Configuração de conexões persistentes no driver PDO (`PDO::ATTR_PERSISTENT => true`) ou ativação de *Connection Pooling*, permitindo que os processos reutilizem canais já autenticados instantaneamente.

### 8.4 Ajuste Fino dos Workers do PHP-FPM e Nginx
* Habilitação de keep-alive de longa duração (`keepalive_timeout 65;`) e buffers de conexão adequados no Nginx.

---

## 9. A Tríade Experimental Oficial: PostgreSQL 16 com MVCC (Local Docker vs AWS)

A segunda bateria experimental consolidou o método definitivo do TCC, aplicando a **Tríade de Testes Simétrica N=3** com controle estrito de concorrência multiversão (MVCC) do PostgreSQL 16 e verificação de integridade transacional ACID em tempo real.

### 9.1 Resultados Consolidados Oficiais ($N=3$ Rodadas Independentes)

#### Pilar 1: API Transacional (Carga Concorrente de 20, 50 e 100 Usuários)
| Ambiente | Concorrência | N (Runs) | Vazão Média (req/s) | Desvio Padrão | Latência Mediana (P50) | Percentil 95 (P95) | Erro HTTP | Auditoria ACID |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 🏠 **Local (Docker)** | **20 users** | 3 | **127.52 req/s** | ±6.85 | **147.4 ms** | 189.8 ms | **0.00%** | **100.0%** |
| 🏠 **Local (Docker)** | **50 users** | 3 | **105.14 req/s** | ±30.49 | **529.4 ms** | 657.4 ms | **0.00%** | **100.0%** |
| 🏠 **Local (Docker)** | **100 users** | 3 | **90.65 req/s** | ±5.31 | **1094.2 ms** | 1349.7 ms | **0.00%** | **100.0%** |
| ☁️ **AWS (EC2 + RDS)** | **20 users** | 3 | **45.09 req/s** | ±3.64 | **446.8 ms** | 546.9 ms | **0.00%** | **100.0%** |
| ☁️ **AWS (EC2 + RDS)** | **50 users** | 3 | **44.35 req/s** | ±7.54 | **1008.3 ms** | 1325.9 ms | **0.00%** | **100.0%** |
| ☁️ **AWS (EC2 + RDS)** | **100 users** | 3 | **47.21 req/s** | ±2.01 | **2031.9 ms** | 2614.7 ms | **0.00%** | **100.0%** |

#### Pilar 2: Ingestão de Mídia & I/O Pesado (Fotos de 1,5 MB e NF-e)
* **Local (Docker):** Throughput de ingestão de **119.2 MB/s** sob 10 uploads simultâneos (latência mediana de 152 ms por foto de 1,5 MB).
* **AWS (EC2 + RDS):** Throughput de ingestão de **15.9 MB/s** sob 20 uploads simultâneos, refletindo a saturação do link de envio (banda de upload da conexão WAN). 100% de sucesso sem corrupção de imagens.

#### Pilar 3: Experiência Real E2E Headless (Microsoft Edge / Chromium)
* **Login E2E:** 1.321 ms no Local versus 2.271 ms na AWS.
* **TTFB (Time to First Byte):** 49,5 ms no Local versus 200,5 ms na AWS.
* **Renderização Completa do Dashboard:** 617,8 ms no Local versus 873,6 ms na AWS.

#### Pilar 4: Telemetria de Hardware Nativa (`tests/benchmark/monitor_docker.js`)
* Monitoramento de hardware a cada 1 segundo acoplado ao benchmark:
  - **CPU Peak e Média (%):** Isolamento do esforço de processamento sob 100 usuários.
  - **Memória RAM Efetiva (MB):** Verificação de vazamento de memória do PHP-FPM e PostgreSQL.
  - **Pegada de Disco do Banco (Storage MB):** Mensuração do crescimento físico do banco via `/var/lib/postgresql/data`.


