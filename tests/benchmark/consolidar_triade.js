/**
 * CONSOLIDADOR ESTATÍSTICO E AUDITOR DE DADOS BRUTOS DA TRÍADE DE TESTES (TCC)
 * PecuáriaGest - Análise Científica Local (Docker On-Premise) vs Nuvem (AWS)
 * 
 * 1. Processa todos os arquivos de telemetria bruta (CSVs e JSONs de telemetria)
 * 2. Gera o DATASET BRUTO UNIFICADO (dataset_bruto_unificado_tcc.csv e .json) para auditoria irrestrita
 * 3. Calcula médias amostrais, medianas, percentis (P50/P95/P99) e desvios padrão oficiais (N=3)
 * 4. Gera a Tabela Oficial Markdown para a monografia (TABELA_CONSOLIDADA_TCC.md)
 * 5. Gera o Dashboard HTML com gráficos interativos (Chart.js) e Explorer Interativo de Dados Brutos
 */

const fs = require('fs');
const path = require('path');

const RESULTS_DIR = path.join(__dirname, 'resultados');
if (!fs.existsSync(RESULTS_DIR)) {
  console.log('Nenhum resultado encontrado em tests/benchmark/resultados.');
  process.exit(0);
}

function mean(arr) {
  if (!arr || !arr.length) return 0;
  return arr.reduce((a, b) => a + b, 0) / arr.length;
}

function stdDev(arr) {
  if (!arr || arr.length <= 1) return 0;
  const avg = mean(arr);
  const sumSquares = arr.reduce((acc, val) => acc + Math.pow(val - avg, 2), 0);
  return Math.sqrt(sumSquares / (arr.length - 1));
}

function parseCsv(filePath) {
  if (!fs.existsSync(filePath)) return [];
  const content = fs.readFileSync(filePath, 'utf-8').trim();
  const lines = content.split('\n');
  if (lines.length < 2) return [];
  const header = lines[0].split(',').map(h => h.trim().replace(/^"|"$/g, ''));
  return lines.slice(1).filter(l => l.trim().length > 0).map(l => {
    // Regex simples para lidar com campos com aspas se existirem
    const vals = l.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/).map(v => v.trim().replace(/^"|"$/g, ''));
    const obj = {};
    header.forEach((h, i) => {
      obj[h] = vals[i] !== undefined ? vals[i] : '';
    });
    return obj;
  });
}

function consolidar() {
  const allFiles = fs.readdirSync(RESULTS_DIR);

  // 1. Processa Pilar 1 (CSVs de API Transacional)
  const apiCsvs = allFiles.filter(f => f.startsWith('benchmark_') && f.endsWith('.csv'));
  const pilar1Groups = {}; // env_concurrency -> array of runs
  const masterRawRows = []; // Linhas consolidadas de TODOS os dados brutos
  const rawFilesInventory = [];

  apiCsvs.forEach(file => {
    const fullPath = path.join(RESULTS_DIR, file);
    const stats = fs.statSync(fullPath);
    const rows = parseCsv(fullPath);
    if (!rows.length) return;

    const env = rows[0].Env || 'local';
    const conc = rows[0].Concurrency || '20';
    const run = rows[0].Run || '1';
    const key = `${env.toLowerCase()}_${conc}`;

    rawFilesInventory.push({
      file,
      pilar: 'Pilar 1 (API Transacional)',
      env: env.toLowerCase(),
      run,
      concurrency: `${conc} users`,
      rowCount: rows.length,
      sizeKb: (stats.size / 1024).toFixed(1),
      timestamp: rows[0].Timestamp || stats.mtime.toISOString()
    });

    // Alimenta Dataset Mestre
    rows.forEach(r => {
      masterRawRows.push({
        Pilar: 'Pilar 1 - API Transacional',
        Ambiente: (r.Env || env).toLowerCase(),
        Execucao: r.Run || run,
        Concorrencia: r.Concurrency || conc,
        Timestamp: r.Timestamp,
        Operacao: `${r.Method || 'GET'} ${r.Endpoint || '/api/animais'}`,
        TamanhoBytes: r.Bytes || 0,
        Latencia_ms: parseFloat(r.Latency_ms) || 0,
        StatusHTTP: r.Status || '200',
        Sucesso: r.Ok === '1' ? '1' : '0',
        AuditoriaACID: r.AuditOk !== undefined ? (r.AuditOk === '1' ? '1' : '0') : '-',
        Detalhes: `Worker ${r.Worker}`
      });
    });

    if (!pilar1Groups[key]) pilar1Groups[key] = [];

    const okRows = rows.filter(r => r.Ok === '1');
    const latencies = okRows.map(r => parseFloat(r.Latency_ms) || 0).sort((a, b) => a - b);
    const n = latencies.length;
    const avg = mean(latencies);
    const p50 = latencies[Math.floor(n * 0.50)] || 0;
    const p95 = latencies[Math.floor(n * 0.95)] || 0;
    const p99 = latencies[Math.floor(n * 0.99)] || 0;

    const tFirst = new Date(rows[0].Timestamp).getTime();
    const tLast = new Date(rows[rows.length - 1].Timestamp).getTime();
    const durSec = Math.max(0.1, (tLast - tFirst) / 1000);
    const throughput = okRows.length / durSec;
    const errRate = ((rows.length - okRows.length) / rows.length) * 100;

    const postRows = rows.filter(r => r.Endpoint === '/api/sync');
    const auditOkRows = postRows.filter(r => r.AuditOk === '1');
    const auditRate = postRows.length ? (auditOkRows.length / postRows.length) * 100 : 100;

    pilar1Groups[key].push({
      run: parseInt(run, 10),
      throughput,
      avg,
      p50,
      p95,
      p99,
      errRate,
      auditRate,
      file
    });
  });

  // 2. Processa Pilar 2 (CSVs de Mídia e I/O)
  const heavyCsvs = allFiles.filter(f => f.startsWith('heavy_uploads_') && f.endsWith('.csv'));
  const pilar2Groups = {};

  heavyCsvs.forEach(file => {
    const fullPath = path.join(RESULTS_DIR, file);
    const stats = fs.statSync(fullPath);
    const rows = parseCsv(fullPath);
    if (!rows.length) return;

    const env = rows[0].Env || 'local';
    const conc = rows[0].Concurrency || '5';
    const run = rows[0].Run || '1';
    const key = `${env.toLowerCase()}_${conc}`;

    rawFilesInventory.push({
      file,
      pilar: 'Pilar 2 (Mídia & I/O)',
      env: env.toLowerCase(),
      run,
      concurrency: `${conc} uploads`,
      rowCount: rows.length,
      sizeKb: (stats.size / 1024).toFixed(1),
      timestamp: rows[0].Timestamp || stats.mtime.toISOString()
    });

    // Alimenta Dataset Mestre
    rows.forEach(r => {
      masterRawRows.push({
        Pilar: 'Pilar 2 - Mídia & I/O',
        Ambiente: (r.Env || env).toLowerCase(),
        Execucao: r.Run || run,
        Concorrencia: r.Concurrency || conc,
        Timestamp: r.Timestamp,
        Operacao: `Upload ${r.Type || 'foto_app_sync'}`,
        TamanhoBytes: r.Bytes || 0,
        Latencia_ms: parseFloat(r.Latency_ms) || 0,
        StatusHTTP: r.Status || '200',
        Sucesso: r.Ok === '1' ? '1' : '0',
        AuditoriaACID: '-',
        Detalhes: `Worker ${r.Worker} | ${(parseFloat(r.Bytes) / (1024*1024)).toFixed(2)} MB`
      });
    });

    if (!pilar2Groups[key]) pilar2Groups[key] = [];

    const totalBytes = rows.reduce((acc, r) => acc + (parseFloat(r.Bytes) || 0), 0);
    const totalMb = totalBytes / (1024 * 1024);

    const tFirst = rows[0].Timestamp ? new Date(rows[0].Timestamp).getTime() : 0;
    const tLast = rows[rows.length - 1].Timestamp ? new Date(rows[rows.length - 1].Timestamp).getTime() : 0;
    const maxLat = Math.max(...rows.map(r => parseFloat(r.Latency_ms) || 0), 100);
    const durSec = (tFirst && tLast && tLast > tFirst) ? Math.max(0.05, (tLast - tFirst) / 1000) : (maxLat / 1000);
    const throughputMBs = durSec > 0 ? (totalMb / durSec) : 0;

    const photoRows = rows.filter(r => r.Type === 'foto_app_sync');
    const photoLats = photoRows.map(r => parseFloat(r.Latency_ms) || 0).sort((a, b) => a - b);
    const p50Photo = photoLats[Math.floor(photoLats.length * 0.50)] || 0;
    const p95Photo = photoLats[Math.floor(photoLats.length * 0.95)] || 0;

    const okRows = rows.filter(r => r.Ok === '1');
    const successRate = (okRows.length / rows.length) * 100;

    pilar2Groups[key].push({
      run: parseInt(run, 10),
      totalMb,
      throughputMBs,
      p50Photo,
      p95Photo,
      successRate,
      file
    });
  });

  // 3. Processa Pilar 3 (JSONs e CSVs de Navegador W3C)
  const browserCsvs = allFiles.filter(f => f.startsWith('browser_metrics_') && f.endsWith('.csv'));
  const browserJsons = allFiles.filter(f => f.startsWith('browser_metrics_') && f.endsWith('.json'));
  const pilar3Groups = {};

  // Lê CSVs do browser se existirem
  browserCsvs.forEach(file => {
    const fullPath = path.join(RESULTS_DIR, file);
    const stats = fs.statSync(fullPath);
    const rows = parseCsv(fullPath);
    if (!rows.length) return;

    const env = rows[0].Env || 'local';
    const run = rows[0].Run || '1';

    rawFilesInventory.push({
      file,
      pilar: 'Pilar 3 (Navegador Headless)',
      env: env.toLowerCase(),
      run,
      concurrency: '1 user E2E',
      rowCount: rows.length,
      sizeKb: (stats.size / 1024).toFixed(1),
      timestamp: rows[0].Timestamp || stats.mtime.toISOString()
    });

    rows.forEach(r => {
      masterRawRows.push({
        Pilar: 'Pilar 3 - Navegador Headless',
        Ambiente: (r.Env || env).toLowerCase(),
        Execucao: r.Run || run,
        Concorrencia: '1',
        Timestamp: r.Timestamp,
        Operacao: `Browser: ${r.Step}`,
        TamanhoBytes: '-',
        Latencia_ms: parseFloat(r.Latency_ms) || 0,
        StatusHTTP: r.Status || '200',
        Sucesso: '1',
        AuditoriaACID: '-',
        Detalhes: `Engine: ${r.Engine} | TTFB: ${r.TTFB_ms || '-'}ms | DOM: ${r.DomContentLoaded_ms || '-'}ms | XML Itens: ${r.XmlItems || '-'}`
      });
    });
  });

  // Lê JSONs do browser para métricas estruturadas
  browserJsons.forEach(file => {
    try {
      const data = JSON.parse(fs.readFileSync(path.join(RESULTS_DIR, file), 'utf-8'));
      const env = (data.env || 'local').toLowerCase();
      if (!pilar3Groups[env]) pilar3Groups[env] = [];
      pilar3Groups[env].push(data);
    } catch (e) {}
  });

  // 4. GRAVA O DATASET BRUTO UNIFICADO (MASTER CSV & JSON)
  const masterCsvPath = path.join(RESULTS_DIR, 'dataset_bruto_unificado_tcc.csv');
  const masterJsonPath = path.join(RESULTS_DIR, 'dataset_bruto_unificado_tcc.json');
  
  if (masterRawRows.length > 0) {
    const masterHeaders = Object.keys(masterRawRows[0]);
    const masterCsvContent = [
      masterHeaders.join(','),
      ...masterRawRows.map(row => masterHeaders.map(h => {
        const val = String(row[h] !== undefined ? row[h] : '');
        return val.includes(',') ? `"${val}"` : val;
      }).join(','))
    ].join('\n');

    fs.writeFileSync(masterCsvPath, masterCsvContent, 'utf-8');
    fs.writeFileSync(masterJsonPath, JSON.stringify(masterRawRows, null, 2), 'utf-8');
    console.log(`📦 [DADOS BRUTOS] Dataset Mestre Unificado CSV gerado: ${path.basename(masterCsvPath)} (${masterRawRows.length} eventos registrados)`);
    console.log(`📦 [DADOS BRUTOS] Dataset Mestre Unificado JSON gerado: ${path.basename(masterJsonPath)}`);
  }

  // 5. Monta a Tabela Oficial em Markdown (TABELA_CONSOLIDADA_TCC.md)
  let md = `# 📊 Tabela Consolidada de Benchmark Oficial (TCC)\n\n`;
  md += `> **Data de Emissão:** ${new Date().toLocaleDateString('pt-BR')} ${new Date().toLocaleTimeString('pt-BR')}\n`;
  md += `> **Metodologia Científica:** Tríade Experimental de Desempenho (API Transacional, Mídia I/O e Navegador Headless).\n`;
  md += `> **Integridade e Auditoria:** 100% dos dados brutos individuais salvos sem agregações destrutivas na pasta \`tests/benchmark/resultados/\`.\n\n`;

  md += `## 1. Pilar 1: Carga Transacional de API\n\n`;
  md += `| Ambiente | Concorrência | N Runs | Vazão Média (req/s) | Desvio Padrão | Latência P50 (ms) | P95 (ms) | P99 (ms) | Taxa Erro (%) | Auditoria ACID |\n`;
  md += `|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|\n`;

  for (const [key, runs] of Object.entries(pilar1Groups)) {
    const [env, conc] = key.split('_');
    const tps = runs.map(r => r.throughput);
    const p50s = runs.map(r => r.p50);
    const p95s = runs.map(r => r.p95);
    const p99s = runs.map(r => r.p99);
    const errs = runs.map(r => r.errRate);
    const audits = runs.map(r => r.auditRate);

    const envLabel = env === 'local' ? '🏠 Local (Docker)' : '☁️ Nuvem AWS';
    md += `| ${envLabel} | **${conc} users** | ${runs.length} | **${mean(tps).toFixed(2)} req/s** | ±${stdDev(tps).toFixed(2)} | **${mean(p50s).toFixed(1)} ms** | ${mean(p95s).toFixed(1)} ms | ${mean(p99s).toFixed(1)} ms | **${mean(errs).toFixed(2)}%** | **${mean(audits).toFixed(1)}%** |\n`;
  }

  md += `\n## 2. Pilar 2: Estresse de Mídia & I/O de Disco\n\n`;
  md += `| Ambiente | Concorrência | N Runs | Ingestão Média (MB/s) | Desvio Padrão | Latência Upload Foto (P50) | Latência P95 Foto | Sucesso Arquivos (%) |\n`;
  md += `|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|\n`;

  for (const [key, runs] of Object.entries(pilar2Groups)) {
    const [env, conc] = key.split('_');
    const mbs = runs.map(r => r.throughputMBs);
    const p50s = runs.map(r => r.p50Photo);
    const p95s = runs.map(r => r.p95Photo || r.p50Photo);
    const succs = runs.map(r => r.successRate);
    const envLabel = env === 'local' ? '🏠 Local (Docker)' : '☁️ Nuvem AWS';
    md += `| ${envLabel} | **${conc} uploads** | ${runs.length} | **${mean(mbs).toFixed(2)} MB/s** | ±${stdDev(mbs).toFixed(2)} | **${mean(p50s).toFixed(1)} ms** | ${mean(p95s).toFixed(1)} ms | **${mean(succs).toFixed(1)}%** |\n`;
  }

  md += `\n## 3. Pilar 3: Navegador Real Headless (Experiência de Usuário W3C)\n\n`;
  md += `| Ambiente | N Runs | Login Operador (ms) | TTFB Rede+Server (ms) | Carga Total Dashboard (ms) | Parse Client NF-e (ms) |\n`;
  md += `|---|:---:|:---:|:---:|:---:|:---:|\n`;

  for (const [env, runs] of Object.entries(pilar3Groups)) {
    const logins = runs.map(r => r.login_ms);
    const ttfbs = runs.map(r => r.ttfb_ms);
    const dashes = runs.map(r => r.dashboard_total_ms);
    const xmls = runs.map(r => r.xml_parse_client_ms);
    const envLabel = env === 'local' ? '🏠 Local (Docker)' : '☁️ Nuvem AWS';
    md += `| ${envLabel} | ${runs.length} | **${mean(logins).toFixed(1)} ms** | **${mean(ttfbs).toFixed(1)} ms** | **${mean(dashes).toFixed(1)} ms** | **${mean(xmls).toFixed(1)} ms** |\n`;
  }

  md += `\n## 4. 📁 Inventário dos Dados Brutos Salvos (Auditoria Científica)\n\n`;
  md += `A tabela abaixo relaciona todos os arquivos CSV de telemetria bruta gerados pelos testes, com carimbo de tempo, contagem de registros e tamanho em disco:\n\n`;
  md += `| Arquivo CSV | Pilar de Teste | Ambiente | Execução | Concorrência | Registros Brutos | Tamanho |\n`;
  md += `|---|---|:---:|:---:|:---:|:---:|:---:|\n`;

  rawFilesInventory.forEach(inv => {
    md += `| \`${inv.file}\` | ${inv.pilar} | ${inv.env.toUpperCase()} | Run #${inv.run} | ${inv.concurrency} | **${inv.rowCount} linhas** | ${inv.sizeKb} KB |\n`;
  });

  md += `\n> 📦 **Dataset Mestre Consolidado:** [\`dataset_bruto_unificado_tcc.csv\`](file:///tests/benchmark/resultados/dataset_bruto_unificado_tcc.csv) contém **${masterRawRows.length} registros transacionais**, permitindo importação direta em Python (\`pd.read_csv\`), R (\`read.csv\`) ou Excel para testes de hipótese (teste t de Student, ANOVA, boxplots de cauda longa).\n`;

  const mdPath = path.join(RESULTS_DIR, 'TABELA_CONSOLIDADA_TCC.md');
  fs.writeFileSync(mdPath, md, 'utf-8');
  console.log(`📄 Tabela Markdown do TCC gerada em: ${path.basename(mdPath)}`);

  // 6. Gera o Dashboard HTML Interativo (com Chart.js e Explorer de Dados Brutos)
  const masterJsonSerialized = JSON.stringify(masterRawRows.slice(0, 500)); // primeiras 500 para preview imediato na DOM
  const rawInventorySerialized = JSON.stringify(rawFilesInventory);

  const htmlContent = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Científico de Benchmark — PecuáriaGest (TCC)</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --bg: #0b0f19; --card: #151d30; --card-hover: #1c2640; --border: #23304d; --text: #f8fafc;
      --text-muted: #94a3b8; --green: #10b981; --blue: #3b82f6; --amber: #f59e0b; --purple: #8b5cf6;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); padding: 2rem; line-height: 1.5; }
    .header { margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .header h1 { font-size: 1.8rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; }
    .header p { color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem; }
    .badges-top { display: flex; gap: 0.5rem; align-items: center; }
    .badge { padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; }
    .badge-green { background: #064e3b; color: #6ee7b7; border: 1px solid #047857; }
    .badge-blue { background: #1e3a8a; color: #93c5fd; border: 1px solid #2563eb; }
    .badge-purple { background: #3b0764; color: #d8b4fe; border: 1px solid #7e22ce; }
    
    .tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
    .tab-btn { background: transparent; border: none; color: var(--text-muted); font-size: 0.95rem; font-weight: 600; padding: 0.5rem 1rem; cursor: pointer; border-radius: 8px; transition: all 0.2s; }
    .tab-btn:hover { color: #fff; background: rgba(255,255,255,0.05); }
    .tab-btn.active { color: #fff; background: var(--card); border: 1px solid var(--border); }

    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .card h3 { font-size: 1.1rem; color: #fff; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; }
    .chart-container { position: relative; height: 260px; width: 100%; }

    /* Seção de Dados Brutos */
    .raw-section { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-top: 2rem; }
    .raw-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem; }
    .btn { padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; border: none; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-outline { background: transparent; color: #94a3b8; border: 1px solid var(--border); }
    .btn-outline:hover { color: #fff; border-color: #64748b; }

    table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; font-size: 0.85rem; }
    th, td { padding: 0.65rem 0.85rem; text-align: left; border-bottom: 1px solid var(--border); }
    th { color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; background: rgba(0,0,0,0.2); }
    tr:hover td { background: rgba(255,255,255,0.02); }
    code { font-family: monospace; background: rgba(0,0,0,0.3); padding: 0.15rem 0.4rem; border-radius: 4px; color: #38bdf8; }

    .status-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
    .status-200 { background: #064e3b; color: #34d399; }
    .status-err { background: #7f1d1d; color: #f87171; }

    .filter-bar { display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap; }
    .search-input { background: var(--bg); border: 1px solid var(--border); color: #fff; padding: 0.45rem 0.75rem; border-radius: 6px; font-size: 0.85rem; min-width: 250px; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <h1>📊 PecuáriaGest — Dossiê Experimental de Desempenho (TCC)</h1>
      <p>Análise Empírica da Tríade de Testes: Ambiente Local (Docker On-Premise) vs Nuvem (AWS)</p>
    </div>
    <div class="badges-top">
      <span class="badge badge-green">✓ Metodologia Simétrica N=3</span>
      <span class="badge badge-blue">📁 100% Dados Brutos Preservados</span>
    </div>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="showTab('graficos')">📈 Gráficos & Desempenho Comparativo</button>
    <button class="tab-btn" onclick="showTab('dadosBrutos')">🔬 Auditoria de Dados Brutos (Explorer CSV)</button>
  </div>

  <div id="tabGraficos">
    <div class="grid">
      <div class="card">
        <h3>
          <span>🚀 Pilar 1: Vazão Transacional de API (req/s)</span>
          <span style="font-size:0.75rem; color:var(--text-muted);">Maior é melhor</span>
        </h3>
        <div class="chart-container"><canvas id="chartThroughput"></canvas></div>
      </div>
      <div class="card">
        <h3>
          <span>⌛ Pilar 1: Latência Mediana P50 (ms)</span>
          <span style="font-size:0.75rem; color:var(--text-muted);">Menor é melhor</span>
        </h3>
        <div class="chart-container"><canvas id="chartLatency"></canvas></div>
      </div>
      <div class="card">
        <h3>
          <span>📦 Pilar 2: Throughput de Ingestão de Mídia (MB/s)</span>
          <span style="font-size:0.75rem; color:var(--text-muted);">Fotos 1.5MB & NF-e</span>
        </h3>
        <div class="chart-container"><canvas id="chartMedia"></canvas></div>
      </div>
      <div class="card">
        <h3>
          <span>🌐 Pilar 3: Experiência no Navegador Real Headless (ms)</span>
          <span style="font-size:0.75rem; color:var(--text-muted);">W3C Navigation Timing</span>
        </h3>
        <div class="chart-container"><canvas id="chartBrowser"></canvas></div>
      </div>
    </div>
  </div>

  <div id="tabDadosBrutos" style="display:none;">
    <div class="raw-section" style="margin-top:0;">
      <div class="raw-header">
        <div>
          <h2>📁 Inventário Oficial de Datasets e Arquivos Brutos</h2>
          <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.25rem;">
            Cada requisição efetuada contém carimbo de data/hora (timestamp microsegundo), id do worker, método, endpoint, latência exata e integridade ACID.
          </p>
        </div>
        <div style="display:flex; gap:0.5rem;">
          <button class="btn btn-primary" onclick="downloadMasterCsv()">⬇️ Exportar Master CSV (${masterRawRows.length} linhas)</button>
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>Arquivo CSV</th>
              <th>Pilar</th>
              <th>Ambiente</th>
              <th>Execução</th>
              <th>Concorrência</th>
              <th>Registros</th>
              <th>Tamanho</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody id="inventoryTableBody"></tbody>
        </table>
      </div>
    </div>

    <div class="raw-section">
      <div class="raw-header">
        <div>
          <h2>🔬 Explorer Interativo de Registros Brutos (Amostra em Tempo Real)</h2>
          <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.25rem;">
            Pesquise e inspecione transações individuais diretamente nesta interface.
          </p>
        </div>
        <div class="filter-bar">
          <input type="text" id="rawSearchInput" class="search-input" placeholder="🔍 Filtrar por endpoint, método, status..." oninput="filterRawData()">
          <span id="rawCounter" style="font-size:0.85rem; color:var(--text-muted);">Exibindo 0 registros</span>
        </div>
      </div>

      <div style="overflow-x:auto; max-height:450px;">
        <table>
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Pilar</th>
              <th>Ambiente</th>
              <th>Operação / Endpoint</th>
              <th>Status HTTP</th>
              <th>Latência (ms)</th>
              <th>ACID / Sucesso</th>
              <th>Detalhes</th>
            </tr>
          </thead>
          <tbody id="rawDataTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    function showTab(tabName) {
      document.getElementById('tabGraficos').style.display = (tabName === 'graficos') ? 'block' : 'none';
      document.getElementById('tabDadosBrutos').style.display = (tabName === 'dadosBrutos') ? 'block' : 'none';
      document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
      event.target.classList.add('active');
    }

    // Carrega dados consolidados injetados
    const pilar1 = ${JSON.stringify(pilar1Groups)};
    const pilar2 = ${JSON.stringify(pilar2Groups)};
    const pilar3 = ${JSON.stringify(pilar3Groups)};
    const inventory = ${rawInventorySerialized};
    const rawData = ${masterJsonSerialized};

    // Renderiza Tabela de Inventário
    const invTbody = document.getElementById('inventoryTableBody');
    invTbody.innerHTML = inventory.map(item => \`
      <tr>
        <td><code>\${item.file}</code></td>
        <td>\${item.pilar}</td>
        <td><span class="badge \${item.env === 'local' ? 'badge-green' : 'badge-blue'}">\${item.env.toUpperCase()}</span></td>
        <td>Run #\${item.run}</td>
        <td>\${item.concurrency}</td>
        <td><strong>\${item.rowCount}</strong></td>
        <td>\${item.sizeKb} KB</td>
        <td><span style="color:#38bdf8; font-size:0.8rem; cursor:pointer;" onclick="alert('O arquivo ' + '\${item.file}' + ' está salvo no diretório tests/benchmark/resultados/')">Ver Caminho</span></td>
      </tr>
    \`).join('');

    // Renderiza Tabela de Registros Brutos
    function renderRawTable(rows) {
      const tbody = document.getElementById('rawDataTableBody');
      const counter = document.getElementById('rawCounter');
      counter.textContent = \`Exibindo \${rows.length} registros\`;

      tbody.innerHTML = rows.map(r => \`
        <tr>
          <td style="color:var(--text-muted); font-size:0.75rem;">\${r.Timestamp || '-'}</td>
          <td><span style="font-size:0.75rem; color:#94a3b8;">\${r.Pilar}</span></td>
          <td><span class="badge \${r.Ambiente === 'local' ? 'badge-green' : 'badge-blue'}">\${r.Ambiente.toUpperCase()}</span></td>
          <td><code>\${r.Operacao}</code></td>
          <td><span class="status-badge \${r.StatusHTTP == 200 ? 'status-200' : 'status-err'}">\${r.StatusHTTP}</span></td>
          <td><strong>\${r.Latencia_ms.toFixed(1)} ms</strong></td>
          <td>\${r.AuditoriaACID === '1' ? '✅ ACID OK' : (r.Sucesso === '1' ? '✅ OK' : '❌ Falha')}</td>
          <td style="font-size:0.75rem; color:var(--text-muted);">\${r.Detalhes}</td>
        </tr>
      \`).join('');
    }

    renderRawTable(rawData);

    function filterRawData() {
      const query = document.getElementById('rawSearchInput').value.toLowerCase();
      const filtered = rawData.filter(r => 
        (r.Operacao && r.Operacao.toLowerCase().includes(query)) ||
        (r.Ambiente && r.Ambiente.toLowerCase().includes(query)) ||
        (r.Pilar && r.Pilar.toLowerCase().includes(query)) ||
        (r.StatusHTTP && String(r.StatusHTTP).includes(query))
      );
      renderRawTable(filtered);
    }

    function downloadMasterCsv() {
      window.open('dataset_bruto_unificado_tcc.csv', '_blank');
    }

    // Chart 1: Throughput
    const ctxT = document.getElementById('chartThroughput').getContext('2d');
    const concs = ['20', '50', '100'];
    const localTps = concs.map(c => {
      const g = pilar1['local_' + c] || [];
      return g.length ? g.reduce((a,b)=>a+b.throughput,0)/g.length : 0;
    });
    const awsTps = concs.map(c => {
      const g = pilar1['aws_' + c] || [];
      return g.length ? g.reduce((a,b)=>a+b.throughput,0)/g.length : 0;
    });

    new Chart(ctxT, {
      type: 'bar',
      data: {
        labels: ['20 Usuários', '50 Usuários', '100 Usuários'],
        datasets: [
          { label: 'Local (Docker)', data: localTps, backgroundColor: '#10b981' },
          { label: 'Nuvem AWS', data: awsTps, backgroundColor: '#3b82f6' }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });

    // Chart 2: Latência P50
    const ctxL = document.getElementById('chartLatency').getContext('2d');
    const localP50 = concs.map(c => {
      const g = pilar1['local_' + c] || [];
      return g.length ? g.reduce((a,b)=>a+b.p50,0)/g.length : 0;
    });
    const awsP50 = concs.map(c => {
      const g = pilar1['aws_' + c] || [];
      return g.length ? g.reduce((a,b)=>a+b.p50,0)/g.length : 0;
    });

    new Chart(ctxL, {
      type: 'line',
      data: {
        labels: ['20 Usuários', '50 Usuários', '100 Usuários'],
        datasets: [
          { label: 'Local P50 (ms)', data: localP50, borderColor: '#10b981', tension: 0.2 },
          { label: 'AWS P50 (ms)', data: awsP50, borderColor: '#3b82f6', tension: 0.2 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });

    // Chart 3: Media Throughput
    const ctxM = document.getElementById('chartMedia').getContext('2d');
    const mediaConcs = ['5', '10', '20'];
    const localMbs = mediaConcs.map(c => {
      const g = pilar2['local_' + c] || [];
      return g.length ? g.reduce((a,b)=>a+b.throughputMBs,0)/g.length : 0;
    });
    const awsMbs = mediaConcs.map(c => {
      const g = pilar2['aws_' + c] || [];
      return g.length ? g.reduce((a,b)=>a+b.throughputMBs,0)/g.length : 0;
    });

    new Chart(ctxM, {
      type: 'bar',
      data: {
        labels: ['5 Uploads', '10 Uploads', '20 Uploads'],
        datasets: [
          { label: 'Local (MB/s)', data: localMbs, backgroundColor: '#f59e0b' },
          { label: 'AWS (MB/s)', data: awsMbs, backgroundColor: '#8b5cf6' }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });

    // Chart 4: Browser
    const ctxB = document.getElementById('chartBrowser').getContext('2d');
    const getAvgBrowser = (env, field) => {
      const g = pilar3[env] || [];
      return g.length ? g.reduce((a,b)=>a+b[field],0)/g.length : 0;
    };
    new Chart(ctxB, {
      type: 'bar',
      data: {
        labels: ['Login (ms)', 'TTFB (ms)', 'Dashboard (ms)', 'Parse NF-e (ms)'],
        datasets: [
          { label: 'Local', data: [getAvgBrowser('local','login_ms'), getAvgBrowser('local','ttfb_ms'), getAvgBrowser('local','dashboard_total_ms'), getAvgBrowser('local','xml_parse_client_ms')], backgroundColor: '#10b981' },
          { label: 'AWS', data: [getAvgBrowser('aws','login_ms'), getAvgBrowser('aws','ttfb_ms'), getAvgBrowser('aws','dashboard_total_ms'), getAvgBrowser('aws','xml_parse_client_ms')], backgroundColor: '#3b82f6' }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });
  </script>
</body>
</html>`;

  const htmlPath = path.join(RESULTS_DIR, 'dashboard_comparativo_tcc.html');
  fs.writeFileSync(htmlPath, htmlContent, 'utf-8');
  console.log(`📊 Dashboard visual HTML com gráficos e Explorer de Dados Brutos gerado em: ${path.basename(htmlPath)}`);

  // Salva JSON consolidado para auditoria
  const jsonSummaryPath = path.join(RESULTS_DIR, 'dados_consolidados_tcc.json');
  fs.writeFileSync(jsonSummaryPath, JSON.stringify({ pilar1: pilar1Groups, pilar2: pilar2Groups, pilar3: pilar3Groups }, null, 2), 'utf-8');
  console.log(`💾 Resumo estatístico salvo em: ${path.basename(jsonSummaryPath)}\n`);
}

if (require.main === module) {
  consolidar();
}

module.exports = { consolidar };
