/**
 * ============================================================
 * PecuáriaGest — Motor de Benchmark e Testes de Carga Simétricos
 * TCC: Comparativo Científico On-Premise vs AWS Cloud (t3.micro)
 * Validação Estrita de Contadores e Transações
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
const TOTAL_ROUNDS = parseInt(getArg('rounds', '5'), 10);
const ENV_LABEL = getArg('env', TARGET_URL.includes('localhost') ? 'local' : 'aws');
const RUN_NUM = getArg('run', '1');
const SCENARIO_NAME = getArg('scenario', `Carga: ${CONCURRENCY} Usuários (${ENV_LABEL.toUpperCase()} - Run ${RUN_NUM})`);
const API_KEY = getArg('apikey', 'pecuaria-mobile-key');

console.log('\n============================================================');
console.log(`🐂 BENCHMARK SIMÉTRICO ESTRITO: [${ENV_LABEL.toUpperCase()}] — ${CONCURRENCY} USUÁRIOS (RUN ${RUN_NUM})`);
console.log('============================================================');
console.log(`🎯 Alvo do Teste:        ${TARGET_URL}`);
console.log(`🔑 Header Autenticação:  X-API-KEY: ${API_KEY}`);
console.log(`👥 Conexões Concorrentes: ${CONCURRENCY} workers`);
console.log(`🔄 Ciclos por Worker:    ${TOTAL_ROUNDS} rodadas`);
console.log(`📦 Total de Requisições: ${CONCURRENCY * TOTAL_ROUNDS * 2} (GET + POST)`);
console.log(`🏷️ Identificador Run:    ${ENV_LABEL} #Run ${RUN_NUM}`);
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
    let targetAnimalId = 1;
    let targetBrinco = 'BR0001';

    // 1. GET /api/animais (Consulta prévia para obter IDs reais)
    const t0 = performance.now();
    try {
      const resGet = await fetch(`${TARGET_URL}/api/animais`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-API-KEY': API_KEY
        }
      });
      const t1 = performance.now();
      const durGet = t1 - t0;
      let okGet = resGet.ok;

      if (okGet) {
        try {
          const list = await resGet.json();
          if (Array.isArray(list) && list.length > 0) {
            const randomItem = list[Math.floor(Math.random() * list.length)];
            targetAnimalId = randomItem.id || 1;
            targetBrinco = randomItem.brinco || 'BR0001';
          } else {
            okGet = false; // Se a lista veio vazia ou inválida, não é sucesso de consulta
          }
        } catch (e) {
          okGet = false;
        }
      }

      latencies.push({
        worker: workerId,
        endpoint: '/api/animais',
        method: 'GET',
        status: resGet.status,
        latency: durGet,
        timestamp: new Date().toISOString(),
        ok: okGet
      });

      if (okGet) {
        successCount++;
      } else {
        errorCount++;
        errorsList.push(`GET /api/animais falhou (HTTP ${resGet.status})`);
      }
    } catch (err) {
      errorCount++;
      errorsList.push(`GET /api/animais timeout/erro: ${err.message}`);
    }

    // 2. POST /api/sync (Payload 100% aderente com validação estrita de contadores)
    const uniqueBrinco = `BM-${ENV_LABEL}-${Date.now()}-${workerId}-${r}-${Math.floor(Math.random() * 10000)}`;
    const payload = {
      animais: [
        {
          brinco: uniqueBrinco,
          nome: `Bezerro W${workerId}`,
          sexo: r % 2 === 0 ? 'M' : 'F',
          raca: 'Nelore',
          data_nascimento: '2026-08-30',
          peso_inicial: parseFloat((180 + Math.random() * 40).toFixed(1)),
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

    const t2 = performance.now();
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
      const t3 = performance.now();
      const durPost = t3 - t2;

      let okPost = false;
      if (resPost.ok) {
        try {
          const bodyPost = await resPost.json();
          const proc = bodyPost.processados || {};
          // Validação Estrita: deve ter gravado animal, pesagem e saúde sem erros no array
          const hasProcessed = (proc.animais_novos >= 1) && (proc.pesagens >= 1) && (proc.saude >= 1);
          const hasNoErrors = !bodyPost.erros || bodyPost.erros.length === 0;
          if (bodyPost.status === 'ok' && hasProcessed && hasNoErrors) {
            okPost = true;
          }
        } catch (e) {
          okPost = false;
        }
      }

      latencies.push({
        worker: workerId,
        endpoint: '/api/sync',
        method: 'POST',
        status: resPost.status,
        latency: durPost,
        timestamp: new Date().toISOString(),
        ok: okPost
      });

      if (okPost) {
        successCount++;
      } else {
        errorCount++;
        errorsList.push(`POST /api/sync rejeitado ou 0 processados (HTTP ${resPost.status})`);
      }
    } catch (err) {
      errorCount++;
      errorsList.push(`POST /api/sync falhou: ${err.message}`);
    }
  }
}

async function runBenchmark() {
  const startTime = performance.now();

  process.stdout.write(`⚡ Executando ${CONCURRENCY} workers [${ENV_LABEL.toUpperCase()} - Run ${RUN_NUM}]...`);
  const workers = [];
  for (let w = 1; w <= CONCURRENCY; w++) {
    workers.push(simulateWorker(w));
  }

  await Promise.all(workers);
  const endTime = performance.now();
  const totalElapsedSec = (endTime - startTime) / 1000;

  console.log(' Concluído! ✅\n');

  // Cálculos Estatísticos Estritos (Apenas Requisições com 100% dos registros processados)
  const totalReqs = latencies.length;
  const successLatencies = latencies.filter(l => l.ok).map(l => l.latency).sort((a, b) => a - b);
  const allDurations = latencies.map(l => l.latency).sort((a, b) => a - b);

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

  const successStats = calcStats(successLatencies);
  const throughputSuccess = successCount / totalElapsedSec;
  const errorRate = (errorCount / (totalReqs || 1)) * 100;

  // Quebra por Endpoint
  const getReqs = latencies.filter(l => l.endpoint === '/api/animais');
  const postReqs = latencies.filter(l => l.endpoint === '/api/sync');
  const getErrors = getReqs.filter(l => !l.ok).length;
  const postErrors = postReqs.filter(l => !l.ok).length;

  console.log('============================================================');
  console.log(`📊 RESULTADOS: [${ENV_LABEL.toUpperCase()}] ${CONCURRENCY} USERS (RUN ${RUN_NUM})`);
  console.log('============================================================');
  console.log(`⏱️  Tempo Total:                ${totalElapsedSec.toFixed(2)} s`);
  console.log(`🚀 Total Requisições:          ${totalReqs} (${successCount} Efetivamente Processadas, ${errorCount} Rejeitadas)`);
  console.log(`📈 Vazão EFETIVA (Processadas): ${throughputSuccess.toFixed(2)} req/s`);
  console.log(`❌ Taxa Global de Erro:        ${errorRate.toFixed(2)}%`);
  console.log(`   • GET  /api/animais:        ${getErrors}/${getReqs.length} falhas`);
  console.log(`   • POST /api/sync:           ${postErrors}/${postReqs.length} falhas`);
  console.log('------------------------------------------------------------');
  console.log('⌛ LATÊNCIAS REAIS (Apenas registros gravados no banco):');
  console.log(`   • Média:                    ${successStats.avg.toFixed(1)} ms`);
  console.log(`   • Mediana (p50):            ${successStats.p50.toFixed(1)} ms`);
  console.log(`   • Percentil 95 (p95):       ${successStats.p95.toFixed(1)} ms`);
  console.log('============================================================\n');

  // Gravação do Arquivo CSV Padronizado
  const csvFilename = `benchmark_${ENV_LABEL}_${CONCURRENCY}users_run${RUN_NUM}_${Date.now()}.csv`;
  const csvPath = path.join(RESULTS_DIR, csvFilename);
  const csvContent = [
    'Timestamp,Worker,Method,Endpoint,Status,Ok,Latency_ms,Env,Run',
    ...latencies.map(l => `${l.timestamp},${l.worker},${l.method},${l.endpoint},${l.status},${l.ok ? 1 : 0},${l.latency.toFixed(2)},${ENV_LABEL},${RUN_NUM}`)
  ].join('\n');
  fs.writeFileSync(csvPath, csvContent, 'utf-8');
  console.log(`💾 CSV salvo: ${csvFilename}`);
}

runBenchmark();
