# 📊 Tabela Consolidada de Benchmark Oficial (TCC)

> **Data de Emissão:** 11/09/2026 18:46:18
> **Metodologia Científica:** Tríade Experimental de Desempenho (API Transacional, Mídia I/O e Navegador Headless).
> **Integridade e Auditoria:** 100% dos dados brutos individuais salvos sem agregações destrutivas na pasta `tests/benchmark/resultados/`.

## 1. Pilar 1: Carga Transacional de API

| Ambiente | Concorrência | N Runs | Vazão Média (req/s) | Desvio Padrão | Latência P50 (ms) | P95 (ms) | P99 (ms) | Taxa Erro (%) | Auditoria ACID |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 🏠 Local (Docker) | **20 users** | 5 | **111.68 req/s** | ±23.28 | **144.7 ms** | 190.8 ms | 198.2 ms | **0.00%** | **100.0%** |

## 2. Pilar 2: Estresse de Mídia & I/O de Disco

| Ambiente | Concorrência | N Runs | Ingestão Média (MB/s) | Desvio Padrão | Latência Upload Foto (P50) | Latência P95 Foto | Sucesso Arquivos (%) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 🏠 Local (Docker) | **5 uploads** | 6 | **75.23 MB/s** | ±39.69 | **138.0 ms** | 196.2 ms | **91.7%** |

## 3. Pilar 3: Navegador Real Headless (Experiência de Usuário W3C)

| Ambiente | N Runs | Login Operador (ms) | TTFB Rede+Server (ms) | Carga Total Dashboard (ms) | Parse Client NF-e (ms) |
|---|:---:|:---:|:---:|:---:|:---:|
| 🏠 Local (Docker) | 6 | **1422.8 ms** | **66.4 ms** | **666.7 ms** | **91.0 ms** |

## 4. 📁 Inventário dos Dados Brutos Salvos (Auditoria Científica)

A tabela abaixo relaciona todos os arquivos CSV de telemetria bruta gerados pelos testes, com carimbo de tempo, contagem de registros e tamanho em disco:

| Arquivo CSV | Pilar de Teste | Ambiente | Execução | Concorrência | Registros Brutos | Tamanho |
|---|---|:---:|:---:|:---:|:---:|:---:|
| `benchmark_local_20users_run1_1789103697308.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 7.9 KB |
| `benchmark_local_20users_run1_1789105377939.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run1_1789105827674.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_20users_run1_1789163171206.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **120 linhas** | 8.2 KB |
| `benchmark_local_5users_run1_1789103206664.csv` | Pilar 1 (API Transacional) | LOCAL | Run #1 | 20 users | **20 linhas** | 1.3 KB |
| `heavy_uploads_local_3users_run1_1789103250455.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **6 linhas** | 0.3 KB |
| `heavy_uploads_local_3users_run1_1789103485343.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **6 linhas** | 0.3 KB |
| `heavy_uploads_local_5users_run1_1789103698549.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.5 KB |
| `heavy_uploads_local_5users_run1_1789105379176.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1789105828891.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.8 KB |
| `heavy_uploads_local_5users_run1_1789163172479.csv` | Pilar 2 (Mídia & I/O) | LOCAL | Run #1 | 5 uploads | **10 linhas** | 0.7 KB |
| `browser_metrics_local_run1_1789105833441.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |
| `browser_metrics_local_run1_1789163178683.csv` | Pilar 3 (Navegador Headless) | LOCAL | Run #1 | 1 user E2E | **3 linhas** | 0.3 KB |

> 📦 **Dataset Mestre Consolidado:** [`dataset_bruto_unificado_tcc.csv`](file:///tests/benchmark/resultados/dataset_bruto_unificado_tcc.csv) contém **558 registros transacionais**, permitindo importação direta em Python (`pd.read_csv`), R (`read.csv`) ou Excel para testes de hipótese (teste t de Student, ANOVA, boxplots de cauda longa).
