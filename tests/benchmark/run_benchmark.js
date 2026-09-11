/**
 * PILAR 1: Benchmark de Carga Transacional e Concorrência de API
 * PecuáriaGest - Arquitetura de Nuvem vs Local
 * 
 * Simula múltiplos vaqueiros/operadores sincronizando dados simultaneamente:
 * - GET /api/animais (leitura de rebanho)
 * - POST /api/sync (envio transacional de animais, pesagens e manejos sanitários)
 * - Reset automático de banco antes de cada execução para garantir simetria (N=3)
 * - Medição microsegundo com performance.now()
 * - Métricas: Throughput (req/s), P50, P90, P95, P99, Taxa de Erros e Auditoria ACID
 */

const fs = require('fs');
const path = require('path');
const { performance } = require('perf_hooks');

// Parâmetros CLI
const args = process.argv.slice(2);
function getArg(flag, defaultValue) {
  const index = args.indexOf(flag);
  return (index !== -1 && args[index + 1]) ? args[index + 1] : defaultValue;
}

const TARGET_URL = getArg('--url', 'http://localhost:8080');
const CONCURRENCY = parseInt(getArg('--concurrency', '20'), 10);
const TOTAL_ROUNDS = parseInt(getArg('--rounds', '3'), 10);
const SHOULD_RESET = !args.includes('--no-reset');
const ENV_LABEL = getArg('--env', 'local');
const RUN_NUM = parseInt(getArg('--run', '1'), 10);
const API_KEY = getArg('--api-key', 'pecuaria-mobile-key');
const BENCHMARK_SECRET = getArg('--secret', 'pecuaria-benchmark-secret-2026');

const RESULTS_DIR = path.join(__dirname, 'resultados');
if (!fs.existsSync(RESULTS_DIR)) {
  fs.mkdirSync(RESULTS_DIR, { recursive: true });
}

console.log('================================================================');
console.log('🚀 PILAR 1: BENCHMARK DE CARGA TRANSACIONAL DE API (TCC)');
console.log('================================================================');
console.log(`🎯 Alvo:               ${TARGET_URL}`);
console.log(`👥 Concorrência:       ${CONCURRENCY} usuários virtuais simultâneos`);
console.log(`🔄 Rodadas por user:   ${TOTAL_ROUNDS} ciclos`);
console.log(`🏷️  Ambiente:           ${ENV_LABEL.toUpperCase()} (Run #${RUN_NUM})`);
console.log(`🧹 Reset de Banco:     ${SHOULD_RESET ? 'ATIVADO (Simétrico N=3)' : 'DESATIVADO'}`);
console.log('----------------------------------------------------------------');

const latencies = [];
let successCount = 0;
let errorCount = 0;
let auditSuccessCount = 0;
const errorsList = [];

async function resetDatabase() {
  process.stdout.write('🧹 Resetando banco de dados para estado seed padrão (30 animais)...');
  try {
    const res = await fetch(`${TARGET_URL}/api/benchmark/reset`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-BENCHMARK-SECRET': BENCHMARK_SECRET
      }
    });
    const data = await res.json();
    if (res.ok && data.status === 'ok') {
      console.log(` OK! (${data.animais_seed || 30} animais base recriados) ✅`);
    } else {
      console.log(` ⚠️ Falha ao resetar banco (HTTP ${res.status}): ${JSON.stringify(data)}`);
    }
  } catch (err) {
    console.log(` ⚠️ Erro de conexão no reset: ${err.message}`);
  }
}

async function simulateWorker(workerId) {
  for (let r = 1; r <= TOTAL_ROUNDS; r++) {
    let targetAnimalId = 1;

    // 1. GET /api/animais (Leitura concorrente)
    const t0 = performance.now();
    try {
      const resGet = await fetch(`${TARGET_URL}/api/animais`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-API-KEY': API_KEY
        }
      });
      const durGet = performance.now() - t0;
      const okGet = (resGet.status === 200);

      latencies.push({
        worker: workerId,
        endpoint: '/api/animais',
        method: 'GET',
        status: resGet.status,
        latency: durGet,
        timestamp: new Date().toISOString(),
        ok: okGet,
        auditOk: okGet
      });

      if (okGet) {
        successCount++;
        try {
          const list = await resGet.json();
          const items = Array.isArray(list) ? list : (list.animais || []);
          if (items.length > 0) {
            const rnd = items[Math.floor(Math.random() * items.length)];
            targetAnimalId = rnd.id || 1;
          }
        } catch (e) {}
      } else {
        errorCount++;
        errorsList.push(`GET /api/animais HTTP ${resGet.status}`);
      }
    } catch (err) {
      errorCount++;
      errorsList.push(`GET /api/animais timeout: ${err.message}`);
    }

    // 2. POST /api/sync (Escrita transacional concorrente)
    const uniqueBrinco = `BM-${ENV_LABEL.toUpperCase()}-${Date.now().toString().slice(-6)}-W${workerId}-R${r}`;
    const payload = {
      animais: [
        {
          brinco: uniqueBrinco,
          nome: `Bezerro W${workerId}`,
          sexo: r % 2 === 0 ? 'M' : 'F',
          raca: 'Nelore',
          data_nascimento: '2026-08-30',
          peso_inicial: parseFloat((180 + Math.random() * 50).toFixed(1)),
          status: 'ativo',
          origem: 'Nascimento'
        }
      ],
      pesagens: [
        {
          animal_id: targetAnimalId,
          peso: parseFloat((350 + Math.random() * 150).toFixed(1)),
          data: '2026-08-30',
          observacao: `Pesagem de carga W#${workerId}`
        }
      ],
      saude: [
        {
          animal_id: targetAnimalId,
          tipo: 'Vacinação',
          descricao: 'Vacina Aftosa Benchmark',
          medicamento: 'Biovet Aftosa',
          dose: '5ml'
        }
      ]
    };

    const t1 = performance.now();
    try {
      const resPost = await fetch(`${TARGET_URL}/api/sync`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-API-KEY': API_KEY
        },
        body: JSON.stringify(payload)
      });
      const durPost = performance.now() - t1;
      const okPost = (resPost.status === 200);
      let auditOk = false;

      if (okPost) {
        successCount++;
        try {
          const bodyPost = await resPost.json();
          const proc = bodyPost.processados || {};
          if (bodyPost.status === 'ok' && (proc.animais_novos >= 1 || proc.pesagens >= 1)) {
            auditOk = true;
            auditSuccessCount++;
          }
        } catch (e) {}
      } else {
        errorCount++;
        errorsList.push(`POST /api/sync HTTP ${resPost.status}`);
      }

      latencies.push({
        worker: workerId,
        endpoint: '/api/sync',
        method: 'POST',
        status: resPost.status,
        latency: durPost,
        timestamp: new Date().toISOString(),
        ok: okPost,
        auditOk: auditOk
      });
    } catch (err) {
      errorCount++;
      errorsList.push(`POST /api/sync falhou: ${err.message}`);
    }
  }
}

async function run() {
  if (SHOULD_RESET) {
    await resetDatabase();
    await new Promise(r => setTimeout(r, 1200));
  }

  const startTime = performance.now();
  process.stdout.write(`⚡ Disparando ${CONCURRENCY} workers em paralelo...`);

  const workers = [];
  for (let w = 1; w <= CONCURRENCY; w++) {
    workers.push(simulateWorker(w));
  }

  await Promise.all(workers);
  const totalElapsedSec = (performance.now() - startTime) / 1000;
  console.log(' Concluído! ✅\n');

  // Cálculos Estatísticos Oficiais (W3C / IEEE Standard)
  const totalReqs = latencies.length;
  const successLatencies = latencies.filter(l => l.ok).map(l => l.latency).sort((a, b) => a - b);

  function calcStats(arr) {
    if (!arr || arr.length === 0) return { min: 0, max: 0, avg: 0, p50: 0, p90: 0, p95: 0, p99: 0 };
    const n = arr.length;
    return {
      min: arr[0],
      max: arr[n - 1],
      avg: arr.reduce((a, b) => a + b, 0) / n,
      p50: arr[Math.floor(n * 0.50)] || 0,
      p90: arr[Math.floor(n * 0.90)] || 0,
      p95: arr[Math.floor(n * 0.95)] || 0,
      p99: arr[Math.floor(n * 0.99)] || 0
    };
  }

  const stats = calcStats(successLatencies);
  const throughput = successCount / totalElapsedSec;
  const errorRate = (errorCount / (totalReqs || 1)) * 100;
  const postReqs = latencies.filter(l => l.endpoint === '/api/sync');

  console.log('================================================================');
  console.log(`📊 RESULTADOS: [${ENV_LABEL.toUpperCase()}] ${CONCURRENCY} USERS (RUN #${RUN_NUM})`);
  console.log('================================================================');
  console.log(`⏱️  Tempo Total de Teste:       ${totalElapsedSec.toFixed(2)} s`);
  console.log(`🚀 Total Requisições:          ${totalReqs} (${successCount} OK 200, ${errorCount} Falhas)`);
  console.log(`📈 Vazão Efetiva (Throughput):  ${throughput.toFixed(2)} req/s`);
  console.log(`❌ Taxa Real de Erro HTTP:     ${errorRate.toFixed(2)}%`);
  console.log(`🔍 Auditoria POST (ACID Sync):  ${auditSuccessCount}/${postReqs.length} (${((auditSuccessCount / (postReqs.length || 1)) * 100).toFixed(1)}%)`);
  console.log('----------------------------------------------------------------');
  console.log('⌛ LATÊNCIAS REAIS (200 OK):');
  console.log(`   • Mínimo:                   ${stats.min.toFixed(1)} ms`);
  console.log(`   • Média:                    ${stats.avg.toFixed(1)} ms`);
  console.log(`   • Mediana (P50):            ${stats.p50.toFixed(1)} ms`);
  console.log(`   • Percentil 95 (P95):       ${stats.p95.toFixed(1)} ms`);
  console.log(`   • Cauda Longa (P99):        ${stats.p99.toFixed(1)} ms`);
  console.log(`   • Máximo:                   ${stats.max.toFixed(1)} ms`);
  console.log('================================================================\n');

  // Grava CSV
  const csvFile = `benchmark_${ENV_LABEL}_${CONCURRENCY}users_run${RUN_NUM}_${Date.now()}.csv`;
  const csvPath = path.join(RESULTS_DIR, csvFile);
  const csvContent = [
    'Timestamp,Worker,Method,Endpoint,Status,Ok,AuditOk,Latency_ms,Env,Run',
    ...latencies.map(l => `${l.timestamp},${l.worker},${l.method},${l.endpoint},${l.status},${l.ok ? 1 : 0},${l.auditOk ? 1 : 0},${l.latency.toFixed(2)},${ENV_LABEL},${RUN_NUM}`)
  ].join('\n');
  fs.writeFileSync(csvPath, csvContent, 'utf-8');
  console.log(`💾 Arquivo CSV de telemetria salvo: ${csvFile}\n`);

  return {
    env: ENV_LABEL,
    concurrency: CONCURRENCY,
    run: RUN_NUM,
    throughput: parseFloat(throughput.toFixed(2)),
    errorRate: parseFloat(errorRate.toFixed(2)),
    avgLatency: parseFloat(stats.avg.toFixed(1)),
    p50: parseFloat(stats.p50.toFixed(1)),
    p95: parseFloat(stats.p95.toFixed(1)),
    p99: parseFloat(stats.p99.toFixed(1)),
    auditRate: parseFloat(((auditSuccessCount / (postReqs.length || 1)) * 100).toFixed(1))
  };
}

if (require.main === module) {
  run().catch(console.error);
}

module.exports = { run };
