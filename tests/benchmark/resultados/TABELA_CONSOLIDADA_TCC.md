# 📊 Tabela Consolidada de Benchmark Oficial (TCC)

> **Data de Emissão:** 12/09/2026 00:03:21
> **Metodologia Científica:** Tríade Experimental de Desempenho (API Transacional, Mídia I/O e Navegador Headless).
> **Integridade e Auditoria:** 100% dos dados brutos individuais salvos sem agregações destrutivas na pasta `tests/benchmark/resultados/`.

## 1. Pilar 1: Carga Transacional de API

| Ambiente | Concorrência | N Runs | Vazão Média (req/s) | Desvio Padrão | Latência P50 (ms) | P95 (ms) | P99 (ms) | Taxa Erro (%) | Auditoria ACID |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| ☁️ Nuvem AWS | **100 users** | 3 | **47.20 req/s** | ±2.01 | **2034.9 ms** | 2631.6 ms | 2754.1 ms | **0.00%** | **100.0%** |
| ☁️ Nuvem AWS | **20 users** | 4 | **43.40 req/s** | ±4.51 | **447.8 ms** | 620.2 ms | 901.1 ms | **0.00%** | **100.0%** |
| ☁️ Nuvem AWS | **50 users** | 3 | **44.35 req/s** | ±7.53 | **1021.6 ms** | 1749.9 ms | 1910.4 ms | **0.00%** | **100.0%** |
| 🏠 Local (Docker) | **100 users** | 3 | **90.65 req/s** | ±5.31 | **1089.9 ms** | 1386.9 ms | 1429.7 ms | **0.00%** | **100.0%** |
| 🏠 Local (Docker) | **20 users** | 8 | **118.37 req/s** | ±20.83 | **147.3 ms** | 188.0 ms | 196.4 ms | **0.00%** | **100.0%** |
| 🏠 Local (Docker) | **50 users** | 3 | **105.14 req/s** | ±30.58 | **466.9 ms** | 661.3 ms | 690.3 ms | **0.00%** | **100.0%** |

## 2. Pilar 2: Estresse de Mídia & I/O de Disco

| Ambiente | Concorrência | N Runs | Ingestão Média (MB/s) | Desvio Padrão | Latência Upload Foto (P50) | Latência P95 Foto | Sucesso Arquivos (%) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| ☁️ Nuvem AWS | **10 uploads** | 3 | **6.56 MB/s** | ±3.41 | **1202.7 ms** | 4502.1 ms | **100.0%** |
| ☁️ Nuvem AWS | **20 uploads** | 3 | **10.90 MB/s** | ±5.32 | **1895.2 ms** | 4997.4 ms | **100.0%** |
| ☁️ Nuvem AWS | **5 uploads** | 4 | **22.46 MB/s** | ±12.00 | **1655.3 ms** | 1885.1 ms | **100.0%** |
| 🏠 Local (Docker) | **10 uploads** | 3 | **128.82 MB/s** | ±17.88 | **158.7 ms** | 207.5 ms | **100.0%** |
| 🏠 Local (Docker) | **20 uploads** | 3 | **70.49 MB/s** | ±14.04 | **291.4 ms** | 549.5 ms | **100.0%** |
| 🏠 Local (Docker) | **5 uploads** | 9 | **92.32 MB/s** | ±41.75 | **129.6 ms** | 179.2 ms | **94.4%** |

## 3. Pilar 3: Navegador Real Headless (Experiência de Usuário W3C)

| Ambiente | N Runs | Login Operador (ms) | TTFB Rede+Server (ms) | Carga Total Dashboard (ms) | Parse Client NF-e (ms) |
|---|:---:|:---:|:---:|:---:|:---:|
| ☁️ Nuvem AWS | 4 | **2594.7 ms** | **206.7 ms** | **863.6 ms** | **147.8 ms** |
| 🏠 Local (Docker) | 9 | **1496.4 ms** | **65.2 ms** | **662.9 ms** | **78.7 ms** |

## 4. 📁 Inventário dos Dados Brutos Salvos (Auditoria Científica)

A tabela abaixo relaciona todos os arquivos CSV de telemetria bruta gerados pelos testes, com carimbo de tempo, contagem de registros e tamanho em disco:

| Arquivo CSV | Pilar de Teste | Ambiente | Execução | Concorrência | Registros Brutos | Tamanho |
|---|---|:---:|:---:|:---:|:---:|:---:|
| `benchmark_aws_100users_run1_1789182098737.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 100 users | **600 linhas** | 41.0 KB |
| `benchmark_aws_100users_run2_1789182114397.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 100 users | **600 linhas** | 41.0 KB |
| `benchmark_aws_100users_run3_1789182131695.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 100 users | **600 linhas** | 41.0 KB |
| `benchmark_aws_20users_run1_1789181995885.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_aws_20users_run1_1789182038527.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_aws_20users_run2_1789182044824.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_aws_20users_run3_1789182050901.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_aws_50users_run1_1789182062860.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 50 users | **300 linhas** | 20.1 KB |
| `benchmark_aws_50users_run2_1789182072502.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 50 users | **300 linhas** | 20.1 KB |
| `benchmark_aws_50users_run3_1789182082445.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 50 users | **300 linhas** | 20.1 KB |
| `benchmark_local_100users_run1_1789163315322.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 100 users | **600 linhas** | 42.1 KB |
| `benchmark_local_100users_run2_1789163325103.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 100 users | **600 linhas** | 42.0 KB |
| `benchmark_local_100users_run3_1789163334355.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 100 users | **600 linhas** | 41.9 KB |
| `benchmark_local_20users_run1_1789103697308.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 7.9 KB |
| `benchmark_local_20users_run1_1789105377939.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run1_1789105827674.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run1_1789163171206.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run1_1789163279018.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run2_1789163283075.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run3_1789163286949.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_50users_run1_1789163292223.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 50 users | **300 linhas** | 20.5 KB |
| `benchmark_local_50users_run2_1789163299025.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 50 users | **300 linhas** | 20.5 KB |
| `benchmark_local_50users_run3_1789163305329.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 50 users | **300 linhas** | 20.5 KB |
| `benchmark_local_5users_run1_1789103206664.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **20 linhas** | 1.3 KB |
| `heavy_uploads_aws_10users_run1_1789182148887.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_10users_run2_1789182153872.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_10users_run3_1789182158657.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_20users_run1_1789182163406.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_aws_20users_run2_1789182169261.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_aws_20users_run3_1789182178983.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_aws_5users_run1_1789182002227.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_aws_5users_run1_1789182135082.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_aws_5users_run2_1789182137494.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_aws_5users_run3_1789182139763.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_10users_run1_1789163341337.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run2_1789163343196.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run3_1789163345005.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_20users_run1_1789163347250.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run2_1789163349685.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run3_1789163352099.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_3users_run1_1789103250455.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **6 linhas** | 0.3 KB |
| `heavy_uploads_local_3users_run1_1789103485343.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **6 linhas** | 0.3 KB |
| `heavy_uploads_local_5users_run1_1789103698549.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.5 KB |
| `heavy_uploads_local_5users_run1_1789105379176.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1789105828891.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1789163172479.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_5users_run1_1789163336068.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_5users_run2_1789163337774.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_5users_run3_1789163339486.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 5 uploads | **10 linhas** | 0.7 KB |
| `browser_metrics_aws_run1_1789182013755.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1789182186138.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1789182193548.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1789182199930.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789105833441.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163178683.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163357437.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163362820.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163367540.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |

> 📦 **Dataset Mestre Consolidado:** [`dataset_bruto_unificado_tcc.csv`](file:///tests/benchmark/resultados/dataset_bruto_unificado_tcc.csv) contém **7249 registros transacionais**, permitindo importação direta em Python (`pd.read_csv`), R (`read.csv`) ou Excel para testes de hipótese (teste t de Student, ANOVA, boxplots de cauda longa).
