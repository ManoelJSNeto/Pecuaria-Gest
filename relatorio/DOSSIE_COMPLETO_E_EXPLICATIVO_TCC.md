# 📚 DOSSIÊ CIENTÍFICO E COMPARATIVO DE DESEMPENHO (TCC)
## Estudo de Caso: PecuáriaGest — Infraestrutura Física Local (On-Premise) vs Computação em Nuvem (Amazon Web Services)

> **Data de Atualização:** 29 de Setembro de 2026  
> **Tema Central da Pesquisa:** Análise Comparativa de Desempenho, Estabilidade e Viabilidade entre Infraestrutura On-Premise (Docker Local) e Nuvem Pública (AWS)  
> **Sistema Avaliado:** PecuáriaGest (Gestão Agropecuária e Rastreabilidade Bovina)  
> **Integridade Acadêmica:** 100% dos dados brutos preservados com carimbo de tempo em microssegundos ($N=3$).

---

## 📑 Sumário Estrutural do Estudo

1. **[O Objeto Central da Pesquisa: Infraestrutura Local vs Nuvem AWS](#1-o-objeto-central-da-pesquisa-infraestrutura-local-vs-nuvem-aws)**
2. **[Resultados Oficiais da Tríade Experimental (Local vs AWS)](#2-resultados-oficiais-da-tríade-experimental-local-vs-aws)**
3. **[A Física da Infraestrutura: Por que os Ambientes se Comportam de Maneira Distinta?](#3-a-física-da-infraestrutura-por-que-os-ambientes-se-comportam-de-maneira-distinta)**
4. **[Telemetria de Hardware e Estabilidade Operacional](#4-telemetria-de-hardware-e-estabilidade-operacional)**
5. **[Análise Econômica e Viabilidade para a Propriedade Rural (CapEx vs OpEx)](#5-análise-econômica-e-viabilidade-para-a-propriedade-rural-capex-vs-opex)**
6. **[Apêndice Metodológico: O Isolamento da Variável Independente (SQLite vs PostgreSQL)](#6-apêndice-metodológico-o-isolamento-da-variável-independente)**

---

## 1. O Objeto Central da Pesquisa: Infraestrutura Local vs Nuvem AWS

O objetivo central deste trabalho de graduação é responder a uma dúvida crítica enfrentada pelo agronegócio moderno:  
> **“Para um sistema de gestão pecuária intensiva, vale mais a pena manter um servidor físico na fazenda (On-Premise) ou hospedar a aplicação na nuvem pública (AWS)?”**

Para responder a essa pergunta com rigor científico, o software **PecuáriaGest** foi submetido a baterias de testes idênticas em duas topologias de infraestrutura:

```
┌─────────────────────────────────────────┐       ┌─────────────────────────────────────────┐
│     TOPOLOGIA 1: ON-PREMISE (LOCAL)     │       │       TOPOLOGIA 2: NUVEM (AWS)          │
├─────────────────────────────────────────┤       ├─────────────────────────────────────────┤
│ • Hardware Físico Local (Host Bare-Metal)│       │ • Amazon VPC na região sa-east-1 (SP)   │
│ • Docker Compose com Nginx + PHP 8.2    │       │ • Instância EC2 t3.micro (Web/App)      │
│ • PostgreSQL 16 Conteinerizado          │       │ • Instância RDS PostgreSQL 16 Gerenciada│
│ • Armazenamento em SSD NVMe Local       │       │ • Armazenamento em Volumes EBS gp3      │
│ • Conexão via Rede Interna / Loopback   │       │ • Conexão via Internet Pública (WAN)    │
└─────────────────────────────────────────┘       └─────────────────────────────────────────┘
```

---

## 2. Resultados Oficiais da Tríade Experimental (Local vs AWS)

A metodologia oficial adotou **3 rodadas independentes para cada nível de concorrência ($N=3$)**, totalizando mais de 7.000 requisições auditadas em 3 pilares complementares:

### Pilar 1: Capacidade Transacional da API (Vazão e Latência)

| Cenário de Carga | Infraestrutura | Vazão Média ($N=3$) | Desvio Padrão ($s$) | Latência Mediana (P50) | Percentil 95 (P95) | Erro HTTP | Integridade ACID |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **20 Usuários** | 🏠 **Local (Docker)** | **127,52 req/s** | $\pm 6,85$ | **147,4 ms** | 189,8 ms | **0,00%** | **100,0%** |
| **20 Usuários** | ☁️ **Nuvem (AWS)** | **45,09 req/s** | $\pm 3,64$ | **446,8 ms** | 546,9 ms | **0,00%** | **100,0%** |
| **50 Usuários** | 🏠 **Local (Docker)** | **105,14 req/s** | $\pm 30,49$ | **529,4 ms** | 657,4 ms | **0,00%** | **100,0%** |
| **50 Usuários** | ☁️ **Nuvem (AWS)** | **44,35 req/s** | $\pm 7,54$ | **1.008,3 ms** | 1.325,9 ms | **0,00%** | **100,0%** |
| **100 Usuários** | 🏠 **Local (Docker)** | **90,65 req/s** | $\pm 5,31$ | **1.094,2 ms** | 1.349,7 ms | **0,00%** | **100,0%** |
| **100 Usuários** | ☁️ **Nuvem (AWS)** | **47,21 req/s** | $\pm 2,01$ | **2.031,9 ms** | 2.614,7 ms | **0,00%** | **100,0%** |

* **Integridade Absoluta:** Em ambas as infraestruturas, a taxa de erro HTTP foi de **0,00%** e a consistência transacional (**ACID Audit**) foi de **100,0%**. Nenhuma transação foi corrompida.
* **Comportamento da Vazão:** O ambiente Local atingiu taxas de transferência superiores (de 1,9x a 2,8x maiores), mantendo latências medianas mais baixas sob todas as cargas.

---

### Pilar 2: Ingestão de Mídia & I/O de Disco (Fotos de 1,5 MB e Notas Fiscais)

O manejo pecuário exige o arquivamento de fotos de identificação dos animais e arquivos XML de NF-e:
* **🏠 Infraestrutura Local (Docker):** Atingiu **119,2 MB/s** de ingestão sob 10 uploads concorrentes, processando cada foto de 1,5 MB em uma média de **152 ms**.
* **☁️ Infraestrutura em Nuvem (AWS):** Atingiu **15,9 MB/s** sob 20 uploads concorrentes.  
* **Interpretação:** A velocidade de gravação na AWS foi limitada pela **banda larga de upload do provedor de internet**, e não pelo servidor em nuvem. Ambas garantiram integridade física sem perda de arquivos.

---

### Pilar 3: Experiência Real do Usuário (Navegador Headless W3C)

Medição da experiência do usuário na ponta final utilizando automação headless (Microsoft Edge):
* **Tempo de Login (Autenticação + Sessão):** 1.321 ms (Local) vs 2.271 ms (AWS).
* **TTFB (Time to First Byte):** 49,5 ms (Local) vs 200,5 ms (AWS).
* **Renderização Completa do Dashboard:** 617,8 ms (Local) vs 873,6 ms (AWS).
* **Conclusão:** Ambas as infraestruturas entregam a página inicial interativa em **menos de 1 segundo**, atendendo com louvor aos padrões de usabilidade recomendados pelo Google Web Vitals (< 2.500 ms).

---

## 3. A Física da Infraestrutura: Por que os Ambientes se Comportam de Maneira Distinta?

A diferença de velocidade observada nos testes não decorre de deficiência de hardware na AWS, mas sim de **leis físicas da transmissão de dados e arquitetura de redes**:

```
[Cliente / Vaqueiro]  ──(Memória RAM / Loopback < 0,5ms)──>  [Docker Local]
       │
       └──(Fibra Ótica / Roteadores / Internet 25 a 45ms)──> [Datacenter AWS]
```

1. **Atraso de Propagação na WAN (*Round-Trip Time — RTT*):**
   * No Docker Local, o cliente e o servidor comunicam-se via barramento interno do sistema operacional (latência de transporte inferior a 0,5 ms).
   * Na AWS, cada requisição viaja pela internet pública atravessando múltiplos saltos de roteadores até o datacenter da AWS (em São Paulo ou Virgínia) e retorna, adicionando de **20 ms a 45 ms fixos de trânsito de rede** por requisição, independentemente do poder de processamento do servidor.
2. **Taxa de Transferência de Disco (IOPS):**
   * O servidor Local grava diretamente no SSD NVMe via barramento PCIe local.
   * Na AWS, a instância EC2 comunica-se com o volume de armazenamento EBS e com o banco RDS através de uma rede dedicada da nuvem (*Storage Area Network*), operando dentro dos limites de IOPS da camada contratada (gp3).

---

## 4. Telemetria de Hardware e Estabilidade Operacional

O monitoramento nativo em tempo real (`monitor_docker.js`) registrou o esforço de máquina durante as baterias de estresse:
* **Consumo de CPU:** Durante os picos com 100 usuários concorrentes, o ambiente Local manteve o processador estável sem superaquecimento, enquanto a instância da AWS operou na faixa de 40% a 55% de utilização sem esgotar seus créditos de burst de CPU (*vCPU credits*).
* **Memória RAM:** O consumo combinado da aplicação (Nginx + PHP-FPM) e do PostgreSQL manteve-se rigorosamente estável em torno de **120 MB a 180 MB**, sem qualquer indício de vazamento de memória (*memory leak*).
* **Pegada de Disco:** O diretório de dados do PostgreSQL (`/var/lib/postgresql/data`) estabilizou em aproximadamente **62,8 MB**, demonstrando alta densidade e eficiência de armazenamento.

---

## 5. Análise Econômica e Viabilidade para a Propriedade Rural (CapEx vs OpEx)

A escolha da infraestrutura não é apenas técnica; é uma decisão de viabilidade financeira para o produtor rural:

| Critério | Infraestrutura Local (On-Premise) | Computação em Nuvem (AWS) |
| :--- | :--- | :--- |
| **Modelo Financeiro** | **CapEx** (Investimento inicial em hardware físico). | **OpEx** (Custo operacional contínuo como serviço). |
| **Investimento Inicial** | R$ 3.500 a R$ 6.000 (Aquisição de PC/Servidor dedicado e nobreak). | **R$ 0,00** (Infraestrutura contratada sob demanda). |
| **Custo Mensal Recorrente** | Custo elétrico contínuo + manutenção física eventual. | ~US$ 18 a US$ 35 / mês (EC2 + RDS no Free Tier / instâncias reservadas). |
| **Disponibilidade Rural** | Sujeita a quedas de energia, raios e queima física no barracão. | **99,95% de SLA** com tolerância a desastres e redundância. |
| **Rotina de Backup** | Manual, dependente de pendrives ou rotinas do produtor. | **Automatizada (Point-in-Time)** com retenção e restauração em minutos. |
| **Acesso Remoto (Cidade)** | Difícil configuração (exige IP fixo, DDNS e abertura de portas). | **Nativo e Imediato** de qualquer lugar via internet segura HTTPS. |

---

## 6. Apêndice Metodológico: O Isolamento da Variável Independente

> 💡 **Nota Histórica para a Banca Examinadora:**  
> Esta seção documenta a evolução científica do trabalho entre o 1º teste preliminar e o teste definitivo oficial.

### O Desafio dos Testes Preliminares
Na primeira fase do projeto, os testes preliminares avaliaram um servidor local rodando sobre **SQLite (WAL)** contra uma instância na nuvem rodando sobre **PostgreSQL RDS**.

Durante esses testes sob 100 usuários simultâneos, o computador local atingiu **100% de ocupação de CPU**:
* **A Causa Identificada:** O SQLite opera com bloqueio de arquivo em nível de tabela (*file lock contention*). Quando dezenas de usuários tentaram inserir dados ao mesmo tempo, os processos PHP entraram em espera ativa (*busy-wait spinlock*), saturando os núcleos da CPU.
* **O Viés Científico Identificado:** Não era possível comparar cientificamente "Local vs AWS" enquanto os bancos fossem diferentes, pois o resultado refletiria a diferença entre os motores de banco, e não entre as infraestruturas.

### A Solução e Padronização Definitiva
Para isolar a variável independente com rigor acadêmico absoluto:
1. O SQLite foi completamente descartado da aplicação.
2. O sistema foi refatorado para utilizar **PostgreSQL 16 com controle de concorrência multiversão (MVCC)** de forma idêntica em ambos os lados.
3. No novo teste oficial, as transações transcorreram de forma ordenada, a CPU permaneceu fria e estável, e **100% das métricas passaram a refletir com fidelidade única a comparação entre a Infraestrutura Local e a Nuvem AWS**.
