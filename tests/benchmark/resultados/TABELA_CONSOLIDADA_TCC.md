# 📊 Tabela Consolidada de Benchmark Oficial (TCC)

> **Data de Emissão:** 01/10/2026 18:47:08
> **Metodologia Científica:** Tríade Experimental de Desempenho (API Transacional, Mídia I/O e Navegador Headless).
> **Integridade e Auditoria:** 100% dos dados brutos individuais salvos sem agregações destrutivas na pasta `tests/benchmark/resultados/`.

## 1. Pilar 1: Carga Transacional de API

| Ambiente | Concorrência | N Runs | Vazão Média (req/s) | Desvio Padrão | Latência P50 (ms) | P95 (ms) | P99 (ms) | Taxa Erro (%) | Auditoria ACID |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| ☁️ Nuvem AWS | **100 users** | 6 | **45.14 req/s** | ±3.59 | **2099.8 ms** | 2863.5 ms | 2998.9 ms | **0.00%** | **100.0%** |
| ☁️ Nuvem AWS | **20 users** | 6 | **42.03 req/s** | ±4.98 | **456.3 ms** | 676.1 ms | 873.1 ms | **0.00%** | **100.0%** |
| ☁️ Nuvem AWS | **50 users** | 6 | **44.06 req/s** | ±6.10 | **1050.3 ms** | 1687.3 ms | 1821.6 ms | **0.00%** | **100.0%** |
| 🏠 Local (Docker) | **100 users** | 9 | **33.26 req/s** | ±43.30 | **1684.5 ms** | 2493.5 ms | 2509.8 ms | **33.33%** | **66.7%** |
| 🏠 Local (Docker) | **20 users** | 9 | **60.13 req/s** | ±66.91 | **120.6 ms** | 1033.9 ms | 1043.6 ms | **33.33%** | **66.7%** |
| 🏠 Local (Docker) | **50 users** | 9 | **37.39 req/s** | ±53.16 | **928.7 ms** | 2254.2 ms | 2264.5 ms | **33.33%** | **66.7%** |

## 2. Pilar 2: Estresse de Mídia & I/O de Disco

| Ambiente | Concorrência | N Runs | Ingestão Média (MB/s) | Desvio Padrão | Latência Upload Foto (P50) | Latência P95 Foto | Sucesso Arquivos (%) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| ☁️ Nuvem AWS | **10 uploads** | 6 | **15.26 MB/s** | ±11.43 | **1147.9 ms** | 2916.9 ms | **100.0%** |
| ☁️ Nuvem AWS | **20 uploads** | 6 | **12.81 MB/s** | ±3.99 | **1618.7 ms** | 4044.9 ms | **100.0%** |
| ☁️ Nuvem AWS | **5 uploads** | 6 | **26.70 MB/s** | ±6.26 | **793.4 ms** | 881.1 ms | **100.0%** |
| 🏠 Local (Docker) | **10 uploads** | 10 | **157.85 MB/s** | ±134.00 | **194.7 ms** | 121771.8 ms | **70.0%** |
| 🏠 Local (Docker) | **20 uploads** | 10 | **248.07 MB/s** | ±282.51 | **320.9 ms** | 461.4 ms | **70.0%** |
| 🏠 Local (Docker) | **5 uploads** | 10 | **122.68 MB/s** | ±56.66 | **130.6 ms** | 121813.4 ms | **70.0%** |

## 3. Pilar 3: Navegador Real Headless (Experiência de Usuário W3C)

| Ambiente | N Runs | Login Operador (ms) | TTFB Rede+Server (ms) | Carga Total Dashboard (ms) | Parse Client NF-e (ms) |
|---|:---:|:---:|:---:|:---:|:---:|
| ☁️ Nuvem AWS | 6 | **2111.0 ms** | **226.0 ms** | **608.2 ms** | **NaN ms** |
| 🏠 Local (Docker) | 9 | **10774.8 ms** | **66.7 ms** | **5824.4 ms** | **NaN ms** |

## 4. Telemetria de Recursos & Hardware (Docker Host)

| Ambiente | Concorrência | N Runs | Pico de CPU (%) | Média de CPU (%) | Pico de RAM (MB) | Média de RAM (MB) | Tamanho Banco (MB) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| ☁️ Nuvem AWS | **100 users** | 1 | **121.8%** | 22.5% | **69.8 MB** | 48.8 MB | **0.0 MB** |
| 🏠 Local (Docker) | **100 users** | 6 | **28.6%** | 1.5% | **77.4 MB** | 76.6 MB | **63.0 MB** |
| 🏠 Local (Docker) | **10 users** | 7 | **2.0%** | 1.3% | **79.2 MB** | 78.4 MB | **63.0 MB** |
| 🏠 Local (Docker) | **1 users** | 6 | **6.5%** | 0.8% | **85.9 MB** | 84.0 MB | **63.1 MB** |
| 🏠 Local (Docker) | **20 users** | 13 | **1.3%** | 1.0% | **72.9 MB** | 72.7 MB | **63.0 MB** |
| 🏠 Local (Docker) | **50 users** | 6 | **23.8%** | 1.6% | **74.4 MB** | 73.8 MB | **62.9 MB** |
| 🏠 Local (Docker) | **5 users** | 7 | **0.9%** | 0.1% | **77.7 MB** | 77.1 MB | **63.0 MB** |

## 5. 📁 Inventário dos Dados Brutos Salvos (Auditoria Científica)

A tabela abaixo relaciona todos os arquivos CSV de telemetria bruta gerados pelos testes, com carimbo de tempo, contagem de registros e tamanho em disco:

| Arquivo CSV | Pilar de Teste | Ambiente | Execução | Concorrência | Registros Brutos | Tamanho |
|---|---|:---:|:---:|:---:|:---:|:---:|
| `benchmark_aws_100users_run1_1789182098737.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 100 users | **600 linhas** | 41.6 KB |
| `benchmark_aws_100users_run1_1790891018211.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 100 users | **600 linhas** | 41.0 KB |
| `benchmark_aws_100users_run2_1789182114397.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 100 users | **600 linhas** | 41.6 KB |
| `benchmark_aws_100users_run2_1790891037408.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 100 users | **600 linhas** | 41.0 KB |
| `benchmark_aws_100users_run3_1789182131695.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 100 users | **600 linhas** | 41.6 KB |
| `benchmark_aws_100users_run3_1790891054335.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 100 users | **600 linhas** | 41.0 KB |
| `benchmark_aws_20users_run1_1789181995885.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 20 users | **120 linhas** | 8.1 KB |
| `benchmark_aws_20users_run1_1789182038527.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 20 users | **120 linhas** | 8.1 KB |
| `benchmark_aws_20users_run1_1790890956378.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_aws_20users_run2_1789182044824.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 20 users | **120 linhas** | 8.1 KB |
| `benchmark_aws_20users_run2_1790890962624.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_aws_20users_run3_1789182050901.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 20 users | **120 linhas** | 8.1 KB |
| `benchmark_aws_20users_run3_1790890969094.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_aws_50users_run1_1789182062860.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 50 users | **300 linhas** | 20.4 KB |
| `benchmark_aws_50users_run1_1790890981101.csv` | Pilar 1 (API Transacional) | AWS | Run #1 | 50 users | **300 linhas** | 20.2 KB |
| `benchmark_aws_50users_run2_1789182072502.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 50 users | **300 linhas** | 20.4 KB |
| `benchmark_aws_50users_run2_1790890991012.csv` | Pilar 1 (API Transacional) | AWS | Run #2 | 50 users | **300 linhas** | 20.1 KB |
| `benchmark_aws_50users_run3_1789182082445.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 50 users | **300 linhas** | 20.4 KB |
| `benchmark_aws_50users_run3_1790891001108.csv` | Pilar 1 (API Transacional) | AWS | Run #3 | 50 users | **300 linhas** | 20.2 KB |
| `benchmark_local_100users_run1_1789163315322.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 100 users | **600 linhas** | 42.6 KB |
| `benchmark_local_100users_run1_1790816157295.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 100 users | **600 linhas** | 39.9 KB |
| `benchmark_local_100users_run1_1790886122765.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 100 users | **600 linhas** | 42.1 KB |
| `benchmark_local_100users_run2_1789163325103.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 100 users | **600 linhas** | 42.6 KB |
| `benchmark_local_100users_run2_1790816163075.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 100 users | **600 linhas** | 39.9 KB |
| `benchmark_local_100users_run2_1790886196427.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 100 users | **600 linhas** | 42.1 KB |
| `benchmark_local_100users_run3_1789163334355.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 100 users | **600 linhas** | 42.5 KB |
| `benchmark_local_100users_run3_1790816168434.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 100 users | **600 linhas** | 40.0 KB |
| `benchmark_local_100users_run3_1790886265638.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 100 users | **600 linhas** | 42.1 KB |
| `benchmark_local_20users_run1_1789103697308.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.0 KB |
| `benchmark_local_20users_run1_1789105377939.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.3 KB |
| `benchmark_local_20users_run1_1789105827674.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.3 KB |
| `benchmark_local_20users_run1_1789163171206.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.3 KB |
| `benchmark_local_20users_run1_1789163279018.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.3 KB |
| `benchmark_local_20users_run1_1790816124635.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 7.9 KB |
| `benchmark_local_20users_run1_1790885882176.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.3 KB |
| `benchmark_local_20users_run2_1789163283075.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 20 users | **120 linhas** | 8.3 KB |
| `benchmark_local_20users_run2_1790816130197.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 20 users | **120 linhas** | 7.9 KB |
| `benchmark_local_20users_run2_1790885895275.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run3_1789163286949.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 20 users | **120 linhas** | 8.3 KB |
| `benchmark_local_20users_run3_1790816135569.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 20 users | **120 linhas** | 7.9 KB |
| `benchmark_local_20users_run3_1790885902011.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_50users_run1_1789163292223.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 50 users | **300 linhas** | 20.8 KB |
| `benchmark_local_50users_run1_1790816141073.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 50 users | **300 linhas** | 19.7 KB |
| `benchmark_local_50users_run1_1790885961760.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 50 users | **300 linhas** | 20.7 KB |
| `benchmark_local_50users_run2_1789163299025.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 50 users | **300 linhas** | 20.8 KB |
| `benchmark_local_50users_run2_1790816146617.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 50 users | **300 linhas** | 19.7 KB |
| `benchmark_local_50users_run2_1790886001045.csv` | Pilar 1 (API Transacional) | LOCAL | Run #2 | 50 users | **300 linhas** | 20.7 KB |
| `benchmark_local_50users_run3_1789163305329.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 50 users | **300 linhas** | 20.8 KB |
| `benchmark_local_50users_run3_1790816152180.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 50 users | **300 linhas** | 19.7 KB |
| `benchmark_local_50users_run3_1790886051556.csv` | Pilar 1 (API Transacional) | LOCAL | Run #3 | 50 users | **300 linhas** | 20.7 KB |
| `benchmark_local_5users_run1_1789103206664.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_10users_run1_1789182148887.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_aws_10users_run1_1790891065968.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_10users_run2_1789182153872.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_aws_10users_run2_1790891068838.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_10users_run3_1789182158657.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_10users_run3_1790891071793.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_aws_20users_run1_1789182163406.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_aws_20users_run1_1790891077030.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_aws_20users_run2_1789182169261.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_aws_20users_run2_1790891081991.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_aws_20users_run3_1789182178983.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_aws_20users_run3_1790891086987.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_aws_5users_run1_1789182002227.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_aws_5users_run1_1789182135082.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_aws_5users_run1_1790891057554.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #1 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_aws_5users_run2_1789182137494.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_aws_5users_run2_1790891059967.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #2 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_aws_5users_run3_1789182139763.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_aws_5users_run3_1790891062353.csv` | Pilar 2 (Mídia & I/O) | AWS | Run #3 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_10users_run1_1789163341337.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run1_1790818625316.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_local_10users_run1_1790823952849.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run1_1790886283182.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run2_1789163343196.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run2_1790818628909.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_local_10users_run2_1790886287304.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run3_1789163345005.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_10users_run3_1790818633289.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 10 uploads | **20 linhas** | 1.4 KB |
| `heavy_uploads_local_10users_run3_1790886291785.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 10 uploads | **20 linhas** | 1.5 KB |
| `heavy_uploads_local_20users_run1_1789163347250.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run1_1790818637623.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_local_20users_run1_1790824030206.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run1_1790886295830.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run2_1789163349685.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run2_1790818642109.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_local_20users_run2_1790886300869.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run3_1789163352099.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_20users_run3_1790818646554.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 20 uploads | **40 linhas** | 2.8 KB |
| `heavy_uploads_local_20users_run3_1790886305738.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 20 uploads | **40 linhas** | 2.9 KB |
| `heavy_uploads_local_3users_run1_1789103250455.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **6 linhas** | 0.3 KB |
| `heavy_uploads_local_3users_run1_1789103485343.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **6 linhas** | 0.3 KB |
| `heavy_uploads_local_5users_run1_1789103698549.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.5 KB |
| `heavy_uploads_local_5users_run1_1789105379176.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1789105828891.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1789163172479.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1789163336068.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1790816172806.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_5users_run1_1790823941828.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1790886270064.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run2_1789163337774.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run2_1790817397538.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_5users_run2_1790886274376.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #2 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run3_1789163339486.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run3_1790817401825.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 5 uploads | **10 linhas** | 0.7 KB |
| `heavy_uploads_local_5users_run3_1790886278640.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #3 | 5 uploads | **10 linhas** | 0.8 KB |
| `browser_metrics_aws_run1_1789182013755.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1789182186138.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1789182193548.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1789182199930.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1790891099137.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1790891104655.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_aws_run1_1790891109743.csv` | Pilar 3 (Navegador Headless) | AWS | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789105833441.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163178683.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163357437.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163362820.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163367540.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1790823842905.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1790824280725.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1790885816155.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1790886478554.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1790886647358.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1790886816519.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `telemetria_hardware_aws_100users_run1_1790890808495.csv` | Telemetria Hardware (Docker) | AWS | Run #1 | 100 users | **224 linhas** | 22.9 KB |
| `telemetria_hardware_local_100users_run1_1790816157294.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 100 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_100users_run1_1790886122762.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 100 users | **99 linhas** | 13.3 KB |
| `telemetria_hardware_local_100users_run2_1790816163073.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 100 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_100users_run2_1790886196425.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 100 users | **102 linhas** | 13.7 KB |
| `telemetria_hardware_local_100users_run3_1790816168433.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 100 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_100users_run3_1790886265636.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 100 users | **96 linhas** | 13.0 KB |
| `telemetria_hardware_local_10users_run1_1790818625307.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 10 users | **1815 linhas** | 218.7 KB |
| `telemetria_hardware_local_10users_run1_1790823952848.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 10 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_10users_run1_1790886283181.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 10 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_10users_run2_1790818628908.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 10 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_10users_run2_1790886287303.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 10 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_10users_run3_1790818633287.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 10 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_10users_run3_1790886291784.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 10 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_1users_run1_1790823842900.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 1 users | **297 linhas** | 36.5 KB |
| `telemetria_hardware_local_1users_run1_1790824280723.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 1 users | **297 linhas** | 36.8 KB |
| `telemetria_hardware_local_1users_run1_1790885816152.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 1 users | **273 linhas** | 33.6 KB |
| `telemetria_hardware_local_1users_run1_1790886478552.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 1 users | **246 linhas** | 31.1 KB |
| `telemetria_hardware_local_1users_run1_1790886647357.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 1 users | **246 linhas** | 31.1 KB |
| `telemetria_hardware_local_1users_run1_1790886816517.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 1 users | **246 linhas** | 31.1 KB |
| `telemetria_hardware_local_20users_run1_1790816124633.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run1_1790818637622.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run1_1790824030204.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 20 users | **6 linhas** | 0.9 KB |
| `telemetria_hardware_local_20users_run1_1790885882173.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 20 users | **45 linhas** | 6.0 KB |
| `telemetria_hardware_local_20users_run1_1790886295829.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run2_1790816130195.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run2_1790818642109.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run2_1790885895274.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 20 users | **12 linhas** | 1.7 KB |
| `telemetria_hardware_local_20users_run2_1790886300868.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run3_1790816135568.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run3_1790818646553.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run3_1790885902010.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_20users_run3_1790886305737.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 20 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_50users_run1_1790816141072.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 50 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_50users_run1_1790885961758.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 50 users | **81 linhas** | 10.8 KB |
| `telemetria_hardware_local_50users_run2_1790816146616.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 50 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_50users_run2_1790886001044.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 50 users | **51 linhas** | 6.9 KB |
| `telemetria_hardware_local_50users_run3_1790816152180.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 50 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_50users_run3_1790886051554.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 50 users | **69 linhas** | 9.2 KB |
| `telemetria_hardware_local_5users_run1_1790816172802.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 5 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_5users_run1_1790823941826.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 5 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_5users_run1_1790886270063.csv` | Telemetria Hardware (Docker) | LOCAL | Run #1 | 5 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_5users_run2_1790817397523.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 5 users | **1815 linhas** | 217.0 KB |
| `telemetria_hardware_local_5users_run2_1790886274375.csv` | Telemetria Hardware (Docker) | LOCAL | Run #2 | 5 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_5users_run3_1790817401824.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 5 users | **3 linhas** | 0.5 KB |
| `telemetria_hardware_local_5users_run3_1790886278639.csv` | Telemetria Hardware (Docker) | LOCAL | Run #3 | 5 users | **3 linhas** | 0.5 KB |

> 📦 **Dataset Mestre Consolidado:** [`dataset_bruto_unificado_tcc.csv`](file:///tests/benchmark/resultados/dataset_bruto_unificado_tcc.csv) contém **17156 registros transacionais**, permitindo importação direta em Python (`pd.read_csv`), R (`read.csv`) ou Excel para testes de hipótese (teste t de Student, ANOVA, boxplots de cauda longa).
