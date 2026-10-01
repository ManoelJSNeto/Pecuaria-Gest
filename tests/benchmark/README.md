# 📊 PecuáriaGest — Suíte de Benchmark da Tríade de Testes (TCC)

Este diretório contém a suíte científica completa de testes de desempenho e confiabilidade comparando o **Ambiente Local (Docker On-Premise)** com o **Ambiente Nuvem (AWS)**.

---

## 🏛️ A Tríade Metodológica de Testes

1. **Pilar 1 — Carga Transacional de API (`run_benchmark.js`):**
   - Simula concorrência pesada de vaqueiros no curral descarregando pesagens e manejos (`GET /api/animais` + `POST /api/sync`);
   - Mede: Vazão (*Throughput* em req/s), Latências P50, P95, P99 e Taxa Real de Erro com reset simétrico de banco de dados.
2. **Pilar 2 — Estresse de Mídia & I/O Pesado (`test_heavy_uploads.js`):**
   - Simula o envio simultâneo de fotos reais de animais (~1.5 MB) e arquivos XML de NF-e completos;
   - Mede: Velocidade de ingestão de mídia (*Throughput* em MB/s), tempo de gravação em disco (I/O) e integridade dos arquivos.
3. **Pilar 3 — Navegador Real Headless (`test_browser_headless.js`):**
   - Simula o produtor rural navegando em um browser real (via Playwright CLI em modo headless);
   - Mede: Métricas oficiais W3C Navigation Timing (DNS, Handshake TLS/HTTPS, TTFB, DOM Content Loaded, Full Page Load) e tempo de processamento client-side da NF-e.

---

## 🚀 Como Executar os Testes

### 1. Testar o Ambiente Local (Docker On-Premise)

* **Modo Rápido (Conferência ágil de ~10 segundos):**
  ```bash
  node tests/benchmark/executar_triade.js --url http://localhost:8080 --env local
  ```

* **Modo Oficial do TCC (Bateria Completa N=3 com repetições e reset):**
  ```bash
  node tests/benchmark/executar_triade.js --url http://localhost:8080 --env local --modo oficial
  ```

---

### 2. Testar o Ambiente Nuvem (AWS)

Após subir a instância na AWS, execute diretamente do seu terminal apontando para o IP/Domínio da nuvem:

* **Modo Rápido:**
  ```bash
  node tests/benchmark/executar_triade.js --url http://SEU-IP-DA-AWS:8080 --env aws
  ```

* **Modo Oficial do TCC:**
  ```bash
  node tests/benchmark/executar_triade.js --url http://SEU-IP-DA-AWS:8080 --env aws --modo oficial
  ```

---

### 3. Consolidar Resultados e Gerar Relatórios

Para reprocessar todos os dados e gerar os relatórios a qualquer momento:
```bash
node tests/benchmark/consolidar_triade.js
```

**Arquivos Gerados na pasta `tests/benchmark/resultados/`:**
* 📄 **`TABELA_CONSOLIDADA_TCC.md`**: Tabela formatada em Markdown pronta para ser copiada para o capítulo de resultados da sua monografia.
* 📊 **`dashboard_comparativo_tcc.html`**: Dashboard interativo com gráficos prontos em **Chart.js** (Throughput, Latência, Mídia e Navegador) para usar nos slides de apresentação.
* 💾 **`dados_consolidados_tcc.json`**: Consolidação estruturada em JSON para scripts de análise em Python/Pandas.

---

## 📁 Rastreabilidade Irrestrita e Dados Brutos (Academic Raw Datasets)

Para garantir reprodutibilidade científica total exigida por bancas de TCC, **nenhum dado bruto é descartado ou pré-agregado com perda**:

1. **Dataset Mestre Unificado (`dataset_bruto_unificado_tcc.csv`):**
   * Contém 100% de todas as transações, uploads e eventos dos três pilares em uma tabela unificada.
   * Colunas: `Pilar, Ambiente, Execucao, Concorrencia, Timestamp, Operacao, TamanhoBytes, Latencia_ms, StatusHTTP, Sucesso, AuditoriaACID, Detalhes`.
   * Perfeito para carregar em Python ou Excel:
     ```python
     import pandas as pd
     df = pd.read_csv('tests/benchmark/resultados/dataset_bruto_unificado_tcc.csv')
     print(df.groupby(['Pilar', 'Ambiente'])['Latencia_ms'].describe())
     ```

2. **CSVs Individuais por Rodada:**
   * `benchmark_[env]_[concurrency]users_run[N]_[timestamp].csv`:
     * Colunas: `Timestamp, Worker, Method, Endpoint, Status, Ok, AuditOk, Latency_ms, Env, Concurrency, Run`
   * `heavy_uploads_[env]_[concurrency]users_run[N]_[timestamp].csv`:
     * Colunas: `Timestamp, Worker, Type, Bytes, Latency_ms, Status, Ok, Env, Concurrency, Run`
   * `browser_metrics_[env]_run[N]_[timestamp].csv` & `.json`:
     * Colunas: `Timestamp, Env, Run, Engine, Step, Status, Latency_ms, TTFB_ms, DomContentLoaded_ms, XmlItems`
   * `screenshot_dashboard_[env].png` & `screenshot_xml_preview_[env].png`:
     * Comprovação visual automática capturada pelo navegador headless.

3. **Explorer Interativo de Dados Brutos:**
   * O arquivo `dashboard_comparativo_tcc.html` inclui uma aba interativa **"Auditoria de Dados Brutos"** com filtro, busca em tempo real e visualização direta de cada requisição.
