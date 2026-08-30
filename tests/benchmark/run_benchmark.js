/**
 * ============================================================
 * PecuáriaGest — Motor de Benchmark e Testes de Carga
 * TCC: Comparativo de Desempenho On-Premise vs AWS Cloud
 * ============================================================
 */

const fs = require('fs');
const path = require('path');
const { performance } = require('perf_hooks');

// Parâmetros via linha de comando
const args = process.argv.slice(2);
function getArg(name, defaultValue) {
  const idx = args.indexOf(`--${name}`);
  if (idx !== -1 && args[idx + 1]) return args[idx + 1];
  return defaultValue;
}

const TARGET_URL = getArg('url', 'http://localhost:8080').replace(/\/$/, '');
const CONCURRENCY = parseInt(getArg('concurrency', '20'), 10);
const TOTAL_ROUNDS = parseInt(getArg('rounds', '5'), 10); // Cada worker faz N rodadas
const SCENARIO_NAME = getArg('scenario', `Carga: ${CONCURRENCY} Usuários Simultâneos`);

console.log('\n============================================================');
console.log('🐂 PECUÁRIAGEST — EXECUTOR DE TESTES DE CARGA & BENCHMARK');
console.log('============================================================');
console.log(`🎯 Alvo do Teste:       ${TARGET_URL}`);
console.log(`👥 Usuários Virtuais:   ${CONCURRENCY} conexões concorrentes`);
console.log(`🔄 Ciclos por Usuário:  ${TOTAL_ROUNDS} requisições/cada`);
console.log(`📦 Total de Requisições: ${CONCURRENCY * TOTAL_ROUNDS * 2} (GET + POST)`);
console.log(`📋 Cenário:             ${SCENARIO_NAME}`);
console.log('============================================================\n');

// Diretório de resultados
const RESULTS_DIR = path.join(__dirname, 'resultados');
if (!fs.existsSync(RESULTS_DIR)) {
  fs.mkdirSync(RESULTS_DIR, { recursive: true });
}

// Métricas globais
const latencies = [];
let successCount = 0;
let errorCount = 0;
const errorsList = [];

async function simulateWorker(workerId) {
  for (let r = 1; r <= TOTAL_ROUNDS; r++) {
    let targetBrinco = 'BR0001';

    // 1. GET /api/animais
    const t0 = performance.now();
    try {
      const resGet = await fetch(`${TARGET_URL}/api/animais`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });
      const t1 = performance.now();
      const durGet = t1 - t0;
      latencies.push({
        worker: workerId,
        endpoint: '/api/animais',
        method: 'GET',
        status: resGet.status,
        latency: durGet,
        timestamp: new Date().toISOString()
      });

      if (resGet.ok) {
        successCount++;
        try {
          const list = await resGet.json();
          if (Array.isArray(list) && list.length > 0 && list[0].brinco) {
            targetBrinco = list[Math.floor(Math.random() * list.length)].brinco;
          }
        } catch (e) {}
      } else {
        errorCount++;
        errorsList.push(`GET /api/animais retornou status ${resGet.status}`);
      }
    } catch (err) {
      errorCount++;
      errorsList.push(`GET /api/animais falhou: ${err.message}`);
    }

    // 2. POST /api/sync
    const uniqueCalfBrinco = `BK-${Date.now()}-${workerId}-${r}-${Math.floor(Math.random() * 10000)}`;
    const payload = {
      dispositivo: `Benchmark Worker #${workerId}`,
      auth_email: 'admin@fazenda.com',
      auth_senha: 'admin123',
      animais_novos: [
        {
          brinco: uniqueCalfBrinco,
          sexo: 'M',
          raca: 'Nelore',
          data_nascimento: '2026-08-29',
          nome: `Bezerro Teste W${workerId}`
        }
      ],
      pesagens: [
        {
          brinco: targetBrinco,
          peso: parseFloat((350 + Math.random() * 150).toFixed(1)),
          data: '2026-08-29',
          observacao: `Benchmark carga W#${workerId}`
        }
      ],
      saude: [
        {
          brinco: targetBrinco,
          tipo: 'Vacinação',
          descricao: 'Aftosa Benchmark',
          medicamento: 'Biovet Aftosa',
          dose: '5ml'
        }
      ]
    };

    const t2 = performance.now();
    try {
      const resPost = await fetch(`${TARGET_URL}/api/sync`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const t3 = performance.now();
      const durPost = t3 - t2;
      latencies.push({
        worker: workerId,
        endpoint: '/api/sync',
        method: 'POST',
        status: resPost.status,
        latency: durPost,
        timestamp: new Date().toISOString()
      });

      if (resPost.ok) successCount++;
      else {
        errorCount++;
        errorsList.push(`POST /api/sync retornou status ${resPost.status}`);
      }
    } catch (err) {
      errorCount++;
      errorsList.push(`POST /api/sync falhou: ${err.message}`);
    }
  }
}

async function runBenchmark() {
  const startTime = performance.now();

  process.stdout.write('⚡ Disparando carga concorrente...');
  const workers = [];
  for (let w = 1; w <= CONCURRENCY; w++) {
    workers.push(simulateWorker(w));
  }

  await Promise.all(workers);
  const endTime = performance.now();
  const totalElapsedSec = (endTime - startTime) / 1000;

  console.log(' Concluído! ✅\n');

  // Cálculos Estatísticos
  const allDurations = latencies.map(l => l.latency).sort((a, b) => a - b);
  const totalReqs = latencies.length;
  const minLatency = allDurations[0] || 0;
  const maxLatency = allDurations[allDurations.length - 1] || 0;
  const avgLatency = allDurations.reduce((a, b) => a + b, 0) / (totalReqs || 1);
  const p50 = allDurations[Math.floor(totalReqs * 0.50)] || 0;
  const p90 = allDurations[Math.floor(totalReqs * 0.90)] || 0;
  const p95 = allDurations[Math.floor(totalReqs * 0.95)] || 0;
  const p99 = allDurations[Math.floor(totalReqs * 0.99)] || 0;
  const throughput = totalReqs / totalElapsedSec;
  const errorRate = (errorCount / (totalReqs || 1)) * 100;

  // Exibição dos Resultados no Terminal
  console.log('============================================================');
  console.log('📊 RESULTADOS CONSOLIDADOS DO BENCHMARK');
  console.log('============================================================');
  console.log(`⏱️  Tempo Total do Teste:       ${totalElapsedSec.toFixed(2)} segundos`);
  console.log(`🚀 Total de Requisições:       ${totalReqs} (${successCount} OK, ${errorCount} Falhas)`);
  console.log(`📈 Vazão Média (Throughput):    ${throughput.toFixed(2)} req/segundo`);
  console.log(`❌ Taxa de Erro:               ${errorRate.toFixed(2)}%`);
  console.log('------------------------------------------------------------');
  console.log('⌛ LATÊNCIAS DE RESPOSTA (Tempo gasto pelo Servidor):');
  console.log(`   • Mínima:                   ${minLatency.toFixed(1)} ms`);
  console.log(`   • Média:                    ${avgLatency.toFixed(1)} ms`);
  console.log(`   • Mediana (p50):            ${p50.toFixed(1)} ms`);
  console.log(`   • Percentil 90 (p90):       ${p90.toFixed(1)} ms`);
  console.log(`   • Percentil 95 (p95):       ${p95.toFixed(1)} ms`);
  console.log(`   • Máxima (p99):             ${maxLatency.toFixed(1)} ms`);
  console.log('============================================================\n');

  // Gravação do Arquivo CSV
  const csvFilename = `benchmark_${Date.now()}_${CONCURRENCY}users.csv`;
  const csvPath = path.join(RESULTS_DIR, csvFilename);
  const csvContent = [
    'Timestamp,Worker,Method,Endpoint,Status,Latency_ms',
    ...latencies.map(l => `${l.timestamp},${l.worker},${l.method},${l.endpoint},${l.status},${l.latency.toFixed(2)}`)
  ].join('\n');
  fs.writeFileSync(csvPath, csvContent, 'utf-8');
  console.log(`💾 Dados brutos salvos em: ${csvPath}`);

  // Geração do Relatório HTML Interativo
  const htmlFilename = 'relatorio_benchmark.html';
  const htmlPath = path.join(RESULTS_DIR, htmlFilename);
  const htmlContent = generateHtmlReport({
    scenario: SCENARIO_NAME,
    targetUrl: TARGET_URL,
    concurrency: CONCURRENCY,
    totalReqs,
    successCount,
    errorCount,
    errorRate,
    totalElapsedSec,
    throughput,
    minLatency,
    avgLatency,
    p50,
    p90,
    p95,
    p99,
    maxLatency,
    latencies
  });
  fs.writeFileSync(htmlPath, htmlContent, 'utf-8');
  console.log(`🌐 Dashboard Visual gerado em: ${htmlPath}\n`);
}

function generateHtmlReport(data) {
  const chartLabels = data.latencies.map((_, i) => `#${i + 1}`);
  const chartValues = data.latencies.map(l => l.latency.toFixed(1));

  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>PecuáriaGest — Relatório de Benchmark</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background-color: #f4f6f4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .header-box { background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4e 100%); color: white; border-radius: 12px; padding: 25px; }
    .card-metric { border-radius: 10px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
  </style>
</head>
<body class="p-4">
  <div class="container-fluid" style="max-width: 1100px;">
    
    <!-- Cabeçalho -->
    <div class="header-box mb-4 shadow-sm">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h3 class="fw-bold mb-1">🐂 PecuáriaGest — Relatório Científico de Desempenho</h3>
          <p class="mb-0 opacity-75">TCC: Análise Comparativa de Carga e Sincronização Mobile</p>
        </div>
        <span class="badge bg-light text-dark fs-6 px-3 py-2 fw-bold">Ambiente: ${data.targetUrl.includes('localhost') ? '🏠 Local (On-Premise)' : '☁️ Nuvem (AWS)'}</span>
      </div>
    </div>

    <!-- Cards com Métricas-Chave -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card card-metric p-3 bg-white">
          <span class="text-muted small fw-bold">VAZÃO (THROUGHPUT)</span>
          <h2 class="fw-bold text-success my-1">${data.throughput.toFixed(1)} <small class="fs-6 text-muted">req/s</small></h2>
          <small class="text-muted">Total: ${data.totalReqs} requisições</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-metric p-3 bg-white">
          <span class="text-muted small fw-bold">LATÊNCIA MÉDIA</span>
          <h2 class="fw-bold text-primary my-1">${data.avgLatency.toFixed(1)} <small class="fs-6 text-muted">ms</small></h2>
          <small class="text-muted">Mediana (p50): ${data.p50.toFixed(1)} ms</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-metric p-3 bg-white">
          <span class="text-muted small fw-bold">PERCENTIL 95 (p95)</span>
          <h2 class="fw-bold text-warning my-1">${data.p95.toFixed(1)} <small class="fs-6 text-muted">ms</small></h2>
          <small class="text-muted">95% das reqs abaixo deste tempo</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-metric p-3 bg-white">
          <span class="text-muted small fw-bold">TAXA DE ERRO</span>
          <h2 class="fw-bold text-${data.errorRate === 0 ? 'success' : 'danger'} my-1">${data.errorRate.toFixed(1)}%</h2>
          <small class="text-muted">${data.successCount} OK / ${data.errorCount} Falhas</small>
        </div>
      </div>
    </div>

    <!-- Gráfico de Latência -->
    <div class="card border-0 shadow-sm p-4 mb-4 bg-white rounded-3">
      <h5 class="fw-bold mb-3">📈 Curva de Tempo de Resposta por Requisição (ms)</h5>
      <canvas id="latencyChart" style="max-height: 320px;"></canvas>
    </div>

    <!-- Tabela Detalhada para o Artigo do TCC -->
    <div class="card border-0 shadow-sm p-4 bg-white rounded-3">
      <h5 class="fw-bold mb-3">📋 Tabela Consolidada de Dados para o TCC</h5>
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr><th>Parâmetro</th><th>Valor Registrado</th><th>Significado Acadêmico</th></tr>
        </thead>
        <tbody>
          <tr><td><strong>Cenário de Teste</strong></td><td>${data.scenario}</td><td>Nível de estresse simultâneo</td></tr>
          <tr><td><strong>Usuários Concorrentes</strong></td><td>${data.concurrency} conexões ativas</td><td>Vaqueiros sincronizando no mesmo segundo</td></tr>
          <tr><td><strong>Vazão do Servidor</strong></td><td>${data.throughput.toFixed(2)} requisições/s</td><td>Capacidade de vazão do backend PHP</td></tr>
          <tr><td><strong>Tempo Mínimo / Máximo</strong></td><td>${data.minLatency.toFixed(1)} ms / ${data.maxLatency.toFixed(1)} ms</td><td>Variação nos picos de I/O de banco</td></tr>
          <tr><td><strong>Percentil 90 (p90)</strong></td><td>${data.p90.toFixed(1)} ms</td><td>Padrão SLA de qualidade de serviço</td></tr>
        </tbody>
      </table>
    </div>

  </div>

  <script>
    const ctx = document.getElementById('latencyChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ${JSON.stringify(chartLabels.slice(0, 100))},
        datasets: [{
          label: 'Latência da Requisição (ms)',
          data: ${JSON.stringify(chartValues.slice(0, 100))},
          borderColor: '#1a4d2e',
          backgroundColor: 'rgba(26, 77, 46, 0.1)',
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { title: { display: true, text: 'Milissegundos (ms)' } },
          x: { title: { display: true, text: 'Amostras de Requisições' } }
        }
      }
    });
  </script>
</body>
</html>`;
}

runBenchmark();
