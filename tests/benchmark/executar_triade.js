/**
 * ORQUESTRADOR DA TRÍADE DE BENCHMARK & PERFORMANCE
 * PecuáriaGest - Arquitetura de Nuvem vs Local (TCC)
 * 
 * Executa sequencialmente os 3 pilares de testes e consolida em relatório único:
 * 1. Pilar 1: Carga Transacional de API (Concorrência, Throughput e Latência)
 * 2. Pilar 2: Estresse de Mídia & I/O Pesado (Upload de Fotos 1.5MB e XMLs de NF-e)
 * 3. Pilar 3: Navegador Real Headless (Login, Dashboard, Preview NF-e e W3C Timing)
 */

const { run: runPilar1 } = require('./run_benchmark.js');
const { run: runPilar2 } = require('./test_heavy_uploads.js');
const { run: runPilar3 } = require('./test_browser_headless.js');

const args = process.argv.slice(2);
function getArg(flag, defaultValue) {
  const index = args.indexOf(flag);
  return (index !== -1 && args[index + 1]) ? args[index + 1] : defaultValue;
}

const TARGET_URL = getArg('--url', 'http://localhost:8080');
const ENV_LABEL = getArg('--env', 'local');

async function main() {
  console.log('\n');
  console.log('╔══════════════════════════════════════════════════════════════════════════╗');
  console.log('║        INICIANDO BATERIA COMPLETA DA TRÍADE DE TESTES (TCC)              ║');
  console.log('║        Ambiente Alvo: ' + ENV_LABEL.toUpperCase().padEnd(49) + '  ║');
  console.log('║        Endpoint:      ' + TARGET_URL.padEnd(49) + '  ║');
  console.log('╚══════════════════════════════════════════════════════════════════════════╝\n');

  // PILAR 1: Carga Transacional
  console.log('>>> [1/3] EXECUTANDO PILAR 1: CARGA TRANSACIONAL DE API...');
  const res1 = await runPilar1().catch(err => {
    console.error('Erro no Pilar 1:', err.message);
    return null;
  });

  // Pausa de 1s para assentar conexões
  await new Promise(r => setTimeout(r, 1000));

  // PILAR 2: Mídia Pesada
  console.log('>>> [2/3] EXECUTANDO PILAR 2: ESTRESSE DE MÍDIA & I/O PESADO...');
  const res2 = await runPilar2().catch(err => {
    console.error('Erro no Pilar 2:', err.message);
    return null;
  });

  // Pausa de 1s
  await new Promise(r => setTimeout(r, 1000));

  // PILAR 3: Navegador Real Headless
  console.log('>>> [3/3] EXECUTANDO PILAR 3: NAVEGADOR REAL HEADLESS (E2E)...');
  const res3 = await runPilar3().catch(err => {
    console.error('Erro no Pilar 3:', err.message);
    return null;
  });

  // CONSOLIDAÇÃO FINAL EM TABELA EXECUTIVA
  console.log('\n');
  console.log('========================================================================================');
  console.log(`                     TABELA CONSOLIDADA DA TRÍADE [${ENV_LABEL.toUpperCase()}]`);
  console.log('========================================================================================');
  console.log(' Pilar / Dimensão Avaliada              | Resultado Obtido     | Métrica / Status');
  console.log('----------------------------------------+----------------------+------------------------');
  if (res1) {
    console.log(` [PILAR 1] Vazão Transacional (API)     | ${res1.throughput.toString().padEnd(20)} | req/s (200 OK)`);
    console.log(` [PILAR 1] Latência Mediana P50         | ${(res1.p50 + ' ms').padEnd(20)} | Experiência típica`);
    console.log(` [PILAR 1] Percentil 95 (P95)           | ${(res1.p95 + ' ms').padEnd(20)} | Teto de SLA`);
    console.log(` [PILAR 1] Taxa Real de Erro HTTP       | ${(res1.errorRate + '%').padEnd(20)} | Tolerância a falhas`);
    console.log(` [PILAR 1] Auditoria ACID (POST Sync)   | ${(res1.auditRate + '%').padEnd(20)} | Integridade de dados`);
  }
  console.log('----------------------------------------+----------------------+------------------------');
  if (res2) {
    console.log(` [PILAR 2] Throughput Ingestão Mídia    | ${(res2.throughputMBs + ' MB/s').padEnd(20)} | Velocidade de upload`);
    console.log(` [PILAR 2] Latência Upload Foto (1.5MB) | ${(res2.p50Photo + ' ms').padEnd(20)} | Mediana I/O de disco`);
    console.log(` [PILAR 2] Sucesso de Envio de Arquivos | ${(res2.successRate + '%').padEnd(20)} | Zero perda de arquivo`);
  }
  console.log('----------------------------------------+----------------------+------------------------');
  if (res3) {
    console.log(` [PILAR 3] Login de Operador no Browser | ${(res3.login_ms + ' ms').padEnd(20)} | Autenticação completa`);
    console.log(` [PILAR 3] TTFB (Time to First Byte)    | ${(res3.ttfb_ms + ' ms').padEnd(20)} | Latência Rede+Server`);
    console.log(` [PILAR 3] Carga do Dashboard (Browser) | ${(res3.dashboard_total_ms + ' ms').padEnd(20)} | Renderização total`);
    console.log(` [PILAR 3] Processamento NF-e Cliente   | ${(res3.xml_parse_client_ms + ' ms').padEnd(20)} | CPU economizada na nuvem`);
  }
  console.log('========================================================================================\n');
}

if (require.main === module) {
  main().catch(console.error);
}
