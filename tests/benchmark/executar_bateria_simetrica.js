/**
 * ============================================================
 * PecuáriaGest — Orquestrador da Bateria Simétrica Oficial (TCC)
 * 18 Execuções: Local (20, 50, 100 x 3) + AWS t3.micro (20, 50, 100 x 3)
 * ============================================================
 */

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const AWS_URL = 'http://32.197.185.161:8080';
const LOCAL_URL = 'http://localhost:8080';
const SCRIPT_PATH = path.join(__dirname, 'run_benchmark.js');

const scenarios = [
  // 1. AMBIENTE LOCAL (SQLite WAL)
  { env: 'local', url: LOCAL_URL, users: 20, runs: [1, 2, 3] },
  { env: 'local', url: LOCAL_URL, users: 50, runs: [1, 2, 3] },
  { env: 'local', url: LOCAL_URL, users: 100, runs: [1, 2, 3] },

  // 2. AMBIENTE NUVEM AWS (EC2 t3.micro + RDS PostgreSQL)
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
  console.log(`📊 Total de Configurações: 6 cenários x 3 repetições = 18 execuções`);
  console.log('============================================================\n');

  let currentExecution = 1;
  const totalExecutions = 18;

  for (const sc of scenarios) {
    for (const runNum of sc.runs) {
      console.log(`\n------------------------------------------------------------`);
      console.log(`▶️ [${currentExecution}/${totalExecutions}] Executando: ${sc.env.toUpperCase()} — ${sc.users} Usuários (Run #${runNum})`);
      console.log(`------------------------------------------------------------`);

      const cmd = `node "${SCRIPT_PATH}" --url "${sc.url}" --concurrency ${sc.users} --rounds 5 --env "${sc.env}" --run "${runNum}" --scenario "${sc.users} Users ${sc.env.toUpperCase()} Run ${runNum}" --apikey "pecuaria-mobile-key"`;

      try {
        execSync(cmd, { stdio: 'inherit' });
      } catch (err) {
        console.error(`❌ Erro na execução [${sc.env} ${sc.users} users Run ${runNum}]:`, err.message);
      }

      currentExecution++;
      console.log('⏳ Aguardando 3 segundos para estabilização de I/O...');
      await sleep(3000);
    }
  }

  console.log('\n============================================================');
  console.log('🏆 BATERIA COMPLETA DE 18 EXECUÇÕES CONCLUÍDA COM SUCESSO!');
  console.log(`📅 Fim: ${new Date().toISOString()}`);
  console.log('============================================================\n');
}

runFullBattery();
