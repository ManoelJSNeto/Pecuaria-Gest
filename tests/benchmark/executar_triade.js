/**
 * ORQUESTRADOR DA TRÍADE DE BENCHMARK & PERFORMANCE
 * PecuáriaGest - Arquitetura de Nuvem vs Local (TCC)
 * 
 * Modos de Execução:
 * 1. Modo Rápido (default): Executa 1 rodada de cada pilar para conferência rápida (~6s)
 * 2. Modo Oficial (--modo oficial): Executa a bateria científica completa do TCC (N=3)
 *    - Pilar 1: 20, 50 e 100 usuários x 3 repetições simétricas com reset de banco
 *    - Pilar 2: 5, 10 e 20 uploads simultâneos x 3 repetições
 *    - Pilar 3: 3 sessões completas de navegador headless
 *    - Consolidação automática com geração de Markdown e Dashboard HTML
 */

const { run: runPilar1 } = require('./run_benchmark.js');
const { run: runPilar2 } = require('./test_heavy_uploads.js');
const { run: runPilar3 } = require('./test_browser_headless.js');
const { consolidar } = require('./consolidar_triade.js');

const args = process.argv.slice(2);
function getArg(flag, defaultValue) {
  const index = args.indexOf(flag);
  return (index !== -1 && args[index + 1]) ? args[index + 1] : defaultValue;
}

const TARGET_URL = getArg('--url', 'http://localhost:8080');
const ENV_LABEL = getArg('--env', 'local');
const IS_OFICIAL = args.includes('--modo') && (args[args.indexOf('--modo') + 1] === 'oficial' || args[args.indexOf('--modo') + 1] === 'completo');

async function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
  console.log('\n');
  console.log('╔══════════════════════════════════════════════════════════════════════════╗');
  console.log('║        ORQUESTRADOR DA TRÍADE DE BENCHMARK & PERFORMANCE (TCC)           ║');
  console.log('║        Ambiente Alvo: ' + ENV_LABEL.toUpperCase().padEnd(49) + '  ║');
  console.log('║        Endpoint:      ' + TARGET_URL.padEnd(49) + '  ║');
  console.log('║        Modo:          ' + (IS_OFICIAL ? 'OFICIAL TCC (Bateria Completa N=3)' : 'RÁPIDO (Verificação Instantânea)').padEnd(49) + '  ║');
  console.log('╚══════════════════════════════════════════════════════════════════════════╝\n');

  if (!IS_OFICIAL) {
    // ── MODO RÁPIDO (1 ciclo de cada para validação ágil) ──────────────────
    console.log('>>> [1/3] EXECUTANDO PILAR 1: CARGA TRANSACIONAL DE API (20 USERS)...');
    await runPilar1({ url: TARGET_URL, env: ENV_LABEL, concurrency: 20, rounds: 3, run: 1, reset: true }).catch(console.error);
    await sleep(1000);

    console.log('>>> [2/3] EXECUTANDO PILAR 2: ESTRESSE DE MÍDIA & I/O PESADO (5 UPLOADS)...');
    await runPilar2({ url: TARGET_URL, env: ENV_LABEL, concurrency: 5, run: 1 }).catch(console.error);
    await sleep(1000);

    console.log('>>> [3/3] EXECUTANDO PILAR 3: NAVEGADOR REAL HEADLESS (E2E)...');
    await runPilar3({ url: TARGET_URL, env: ENV_LABEL, run: 1 }).catch(console.error);

  } else {
    // ── MODO OFICIAL TCC (Bateria Científica Completa N=3) ─────────────────
    console.log('🏛️  INICIANDO BATERIA OFICIAL SIMÉTRICA (N=3 REPETIÇÕES COM RESET DE BANCO)...\n');

    // PILAR 1: 20, 50 e 100 usuários
    const concsPilar1 = [20, 50, 100];
    for (const conc of concsPilar1) {
      for (let runNum = 1; runNum <= 3; runNum++) {
        console.log(`\n▶️ [PILAR 1] Carga ${conc} Usuários — Repetição ${runNum}/3...`);
        await runPilar1({ url: TARGET_URL, env: ENV_LABEL, concurrency: conc, rounds: 3, run: runNum, reset: true }).catch(console.error);
        await sleep(1500);
      }
    }

    // PILAR 2: 5, 10 e 20 uploads concorrentes
    const concsPilar2 = [5, 10, 20];
    for (const conc of concsPilar2) {
      for (let runNum = 1; runNum <= 3; runNum++) {
        console.log(`\n▶️ [PILAR 2] Mídia ${conc} Uploads — Repetição ${runNum}/3...`);
        await runPilar2({ url: TARGET_URL, env: ENV_LABEL, concurrency: conc, run: runNum }).catch(console.error);
        await sleep(1500);
      }
    }

    // PILAR 3: 3 sessões de navegador
    for (let runNum = 1; runNum <= 3; runNum++) {
      console.log(`\n▶️ [PILAR 3] Navegador Headless — Repetição ${runNum}/3...`);
      await runPilar3({ url: TARGET_URL, env: ENV_LABEL, run: runNum }).catch(console.error);
      await sleep(1500);
    }
  }

  // CONSOLIDAÇÃO FINAL AUTOMÁTICA
  console.log('\n>>> CONSOLIDANDO TODAS AS TELEMETRIAS E GERANDO RELATÓRIO DO TCC...');
  consolidar();
}

if (require.main === module) {
  main().catch(console.error);
}

module.exports = { main };
