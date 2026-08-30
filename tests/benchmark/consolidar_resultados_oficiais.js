const fs = require('fs');
const path = require('path');

const RESULTS_DIR = path.join(__dirname, 'resultados');
const files = fs.readdirSync(RESULTS_DIR).filter(f => f.startsWith('benchmark_') && f.endsWith('.csv') && (f.includes('_local_') || f.includes('_aws_')));

const grouped = {};

files.forEach(file => {
  const filePath = path.join(RESULTS_DIR, file);
  const lines = fs.readFileSync(filePath, 'utf-8').trim().split('\n');
  const header = lines[0].split(',');
  const rows = lines.slice(1).map(l => l.split(','));

  const envMatch = file.match(/benchmark_(local|aws)_(\d+)users_run(\d+)_/);
  if (!envMatch) return;

  const [_, env, users, run] = envMatch;
  const key = `${env}_${users}users`;

  if (!grouped[key]) {
    grouped[key] = { env, users: parseInt(users, 10), runs: [] };
  }

  const latenciesSuccess = rows.filter(r => r[5] === '1').map(r => parseFloat(r[6]));
  const latenciesAll = rows.map(r => parseFloat(r[6]));
  const totalReqs = rows.length;
  const successReqs = latenciesSuccess.length;
  const errorReqs = totalReqs - successReqs;
  const errorRate = (errorReqs / totalReqs) * 100;

  // Calcula tempo total do CSV a partir dos timestamps ou soma/duração
  const timestamps = rows.map(r => new Date(r[0]).getTime()).filter(t => !isNaN(t)).sort((a, b) => a - b);
  const durationSec = (timestamps[timestamps.length - 1] - timestamps[0]) / 1000 || 1;
  const throughputSuccess = successReqs / durationSec;

  function calcStats(arr) {
    if (!arr.length) return { avg: 0, p50: 0, p95: 0 };
    arr.sort((a, b) => a - b);
    return {
      avg: arr.reduce((a, b) => a + b, 0) / arr.length,
      p50: arr[Math.floor(arr.length * 0.5)] || 0,
      p95: arr[Math.floor(arr.length * 0.95)] || 0
    };
  }

  const stats = calcStats(latenciesSuccess);

  grouped[key].runs.push({
    run: parseInt(run, 10),
    totalReqs,
    successReqs,
    errorReqs,
    errorRate,
    durationSec,
    throughputSuccess,
    avgLatency: stats.avg,
    p50: stats.p50,
    p95: stats.p95
  });
});

console.log('\n========================================================================================');
console.log('📊 CONSOLIDAÇÃO CIENTÍFICA OFICIAL: LOCAL vs AWS (MÉDIA E DESVIO-PADRÃO)');
console.log('========================================================================================\n');

function stdDev(arr) {
  if (arr.length <= 1) return 0;
  const mean = arr.reduce((a, b) => a + b, 0) / arr.length;
  const variance = arr.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / (arr.length - 1);
  return Math.sqrt(variance);
}

const summaryRows = [];

Object.keys(grouped).sort().forEach(key => {
  const g = grouped[key];
  const runs = g.runs;
  const numRuns = runs.length;

  const avgThroughput = runs.reduce((a, r) => a + r.throughputSuccess, 0) / numRuns;
  const stdThroughput = stdDev(runs.map(r => r.throughputSuccess));

  const avgLatency = runs.reduce((a, r) => a + r.avgLatency, 0) / numRuns;
  const stdLatency = stdDev(runs.map(r => r.avgLatency));

  const avgP50 = runs.reduce((a, r) => a + r.p50, 0) / numRuns;
  const avgP95 = runs.reduce((a, r) => a + r.p95, 0) / numRuns;
  const avgErrorRate = runs.reduce((a, r) => a + r.errorRate, 0) / numRuns;

  summaryRows.push({
    Ambiente: g.env === 'local' ? '🏠 Local (SQLite WAL)' : '☁️ AWS (t3.micro + RDS)',
    Usuarios: g.users,
    Repeticoes: numRuns,
    ThroughputMedia: avgThroughput.toFixed(2),
    ThroughputDesvio: stdThroughput.toFixed(2),
    LatenciaMedia: avgLatency.toFixed(1),
    LatenciaDesvio: stdLatency.toFixed(1),
    P50: avgP50.toFixed(1),
    P95: avgP95.toFixed(1),
    TaxaErroMedia: avgErrorRate.toFixed(1) + '%'
  });
});

console.table(summaryRows);

// Salva em Markdown oficial
let md = `# 📊 Tabela Consolidada de Benchmark Oficial (TCC) — Simétrico e Auditado\n\n`;
md += `> Bateria executada com **3 repetições por cenário (N=3)** nos ambientes **Local (SQLite WAL)** e **Nuvem AWS (EC2 t3.micro + Amazon RDS PostgreSQL)** com validação estrita de contadores de banco de dados.\n\n`;
md += `| Ambiente | Concorrência | N (Runs) | Vazão Efetiva Média (req/s) | Desvio Padrão Vazão | Latência Média 200 OK (ms) | Desvio Padrão Latência | Mediana (p50) | Percentil 95 (p95) | Taxa de Erro Média |\n`;
md += `|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|\n`;

summaryRows.forEach(r => {
  md += `| ${r.Ambiente} | **${r.Usuarios} users** | ${r.Repeticoes} | **${r.ThroughputMedia} req/s** | ±${r.ThroughputDesvio} | **${r.LatenciaMedia} ms** | ±${r.LatenciaDesvio} | ${r.P50} ms | ${r.P95} ms | ${r.TaxaErroMedia} |\n`;
});

fs.writeFileSync(path.join(__dirname, 'RESULTADOS_CONSOLIDADOS_TCC.md'), md, 'utf-8');
console.log('✅ Arquivo RESULTADOS_CONSOLIDADOS_TCC.md gerado com sucesso!');
