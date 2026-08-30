/**
 * ============================================================
 * PecuáriaGest — Orquestrador Oficial de Benchmark (TCC)
 * 18 Execuções Simétricas com RESET de Banco e Monitoramento de CPU
 * ============================================================
 */

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const { recordCpuSnapshot } = require('./monitor_cpu');

const AWS_URL = 'http://100.55.16.39:8080';
const LOCAL_URL = 'http://localhost:8080';
const SCRIPT_PATH = path.join(__dirname, 'run_benchmark.js');
const RESULTS_DIR = path.join(__dirname, 'resultados');
const BENCHMARK_SECRET = process.env.BENCHMARK_SECRET || 'pecuaria-benchmark-secret-2026';

// Limpa resultados antigos para garantir dados 100% limpos
console.log('🧹 Limpando CSVs de execuções anteriores...');
if (fs.existsSync(RESULTS_DIR)) {
  fs.readdirSync(RESULTS_DIR).forEach(file => {
    if (file.endsWith('.csv') || file === 'cpu_credits_metrics.json') {
      fs.unlinkSync(path.join(RESULTS_DIR, file));
    }
  });
}

const scenarios = [
  // 1. AMBIENTE LOCAL (SQLite WAL) com Reset de Banco
  { env: 'local', url: LOCAL_URL, users: 20, runs: [1, 2, 3] },
  { env: 'local', url: LOCAL_URL, users: 50, runs: [1, 2, 3] },
  { env: 'local', url: LOCAL_URL, users: 100, runs: [1, 2, 3] },

  // 2. AMBIENTE NUVEM AWS (EC2 t3.micro + RDS PostgreSQL) com Reset de Banco
  { env: 'aws', url: AWS_URL, users: 20, runs: [1, 2, 3] },
  { env: 'aws', url: AWS_URL, users: 50, runs: [1, 2, 3] },
  { env: 'aws', url: AWS_URL, users: 100, runs: [1, 2, 3] }
];

async function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function runFullBattery() {
  console.log('\n============================================================');
  console.log('🚀 INICIANDO BATERIA SIMÉTRICA CIENTÍFICA OFICIAL (TCC)');
  console.log('============================================================');
  console.log(`📅 Início: ${new Date().toISOString()}`);
  console.log(`📊 18 Execuções Simétricas (3 Runs por nível de carga com DB Reset)`);
  console.log(`🔍 Monitoramento de CPU CloudWatch Ativado para 50 e 100 Users AWS`);
  console.log('============================================================\n');

  let currentExecution = 1;
  const totalExecutions = 18;

  for (const sc of scenarios) {
    for (const runNum of sc.runs) {
      console.log(`\n------------------------------------------------------------`);
      console.log(`▶️ [${currentExecution}/${totalExecutions}] Executando: ${sc.env.toUpperCase()} — ${sc.users} Usuários (Run #${runNum})`);
      console.log(`------------------------------------------------------------`);

      // Snapshot de CPU ANTES
      if (sc.env === 'aws' && (sc.users === 50 || sc.users === 100)) {
        recordCpuSnapshot('before', sc.env, sc.users, runNum);
      }

      const cmd = `node "${SCRIPT_PATH}" --url "${sc.url}" --concurrency ${sc.users} --rounds 5 --env "${sc.env}" --run "${runNum}" --scenario "${sc.users} Users ${sc.env.toUpperCase()} Run ${runNum}" --apikey "pecuaria-mobile-key" --reset`;

      try {
        execSync(cmd, { stdio: 'inherit' });
      } catch (err) {
        console.error(`❌ Erro na execução [${sc.env} ${sc.users} users Run ${runNum}]:`, err.message);
      }

      // Snapshot de CPU DEPOIS
      if (sc.env === 'aws' && (sc.users === 50 || sc.users === 100)) {
        recordCpuSnapshot('after', sc.env, sc.users, runNum);
      }

      currentExecution++;
      console.log('⏳ Pausa de 4 segundos para estabilização de I/O...');
      await sleep(4000);
    }
  }

  console.log('\n============================================================');
  console.log('🏆 BATERIA DE 18 EXECUÇÕES CONCLUÍDA COM SUCESSO!');
  console.log(`📅 Fim: ${new Date().toISOString()}`);
  console.log('============================================================\n');
}

runFullBattery();
