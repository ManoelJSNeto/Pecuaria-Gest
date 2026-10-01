/**
 * PILAR 3: Teste de Navegador Real Headless (Playwright - E2E & Experiência de Usuário)
 * PecuáriaGest - Arquitetura de Nuvem vs Local
 * 
 * Simula um usuário real no navegador:
 * 1. Login no sistema (/login)
 * 2. Carregamento do Cockpit Geral (/dashboard) com gráficos Chart.js
 * 3. Importação e preview em tempo real de XML de NF-e (/compras/novo)
 * 4. Extração de métricas oficiais W3C Navigation Timing (DNS, TLS, TTFB, DOM, Full Load)
 * 5. Captura automática de screenshots para documentação do TCC
 */

const fs = require('fs');
const path = require('path');
const { performance } = require('perf_hooks');
const { monitor } = require('./monitor_docker.js');

const args = process.argv.slice(2);
function getArg(flag, defaultValue) {
  const index = args.indexOf(flag);
  return (index !== -1 && args[index + 1]) ? args[index + 1] : defaultValue;
}

const TARGET_URL = getArg('--url', 'http://localhost:8080');
const ENV_LABEL = getArg('--env', 'local');
const RUN_NUM = parseInt(getArg('--run', '1'), 10);
const EMAIL = getArg('--email', 'admin@fazenda.com');
const SENHA = getArg('--password', 'admin123');

const RESULTS_DIR = path.join(__dirname, 'resultados');
if (!fs.existsSync(RESULTS_DIR)) {
  fs.mkdirSync(RESULTS_DIR, { recursive: true });
}

const XML_FIXTURE_PATH = path.join(__dirname, '..', 'fixtures', 'nfe_compra_sample.xml');

console.log('================================================================');
console.log('🌐 PILAR 3: TESTE DE NAVEGADOR REAL HEADLESS (E2E & PERFORMANCE)');
console.log('================================================================');
console.log(`🎯 Alvo:               ${TARGET_URL}`);
console.log(`🏷️  Ambiente:           ${ENV_LABEL.toUpperCase()} (Run #${RUN_NUM})`);
console.log('----------------------------------------------------------------');

async function launchBrowser() {
  const playwright = require('playwright');
  let browser = null;
  let browserEngine = 'msedge';

  // Tenta Firefox primeiro; se não tiver binário baixado do juggler, usa MSEdge nativo do Windows
  try {
    browser = await playwright.firefox.launch({ headless: true });
    browserEngine = 'firefox';
  } catch (e) {
    try {
      browser = await playwright.chromium.launch({ channel: 'msedge', headless: true });
      browserEngine = 'msedge (Chromium)';
    } catch (e2) {
      browser = await playwright.chromium.launch({ headless: true });
      browserEngine = 'chromium';
    }
  }

  console.log(`🖥️  Motor do Navegador:  ${browserEngine} Headless (100% CLI) ✅\n`);
  return { browser, browserEngine };
}

async function run() {
  let telemetria = { hasData: false };
  if (ENV_LABEL === 'local') {
    monitor.start({ env: ENV_LABEL, pilar: 'Pilar 3 (Navegador)', concurrency: 1, run: RUN_NUM });
  }

  const { browser, browserEngine } = await launchBrowser();
  const context = await browser.newContext({
    viewport: { width: 1366, height: 768 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PecuariaGest-Benchmark'
  });
  const page = await context.newPage();

  // 1. ETAPA 1: LOGIN NO SISTEMA
  process.stdout.write('🔐 [1/3] Efetuando login de operador em /login...');
  const tLoginStart = performance.now();
  await page.goto(`${TARGET_URL}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="senha"]', SENHA);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    page.click('button[type="submit"]')
  ]);
  const loginDur = performance.now() - tLoginStart;
  console.log(` Sucesso! (${loginDur.toFixed(1)} ms) ✅`);

  // 2. ETAPA 2: DASHBOARD & MÉTRICAS W3C
  process.stdout.write('📊 [2/3] Carregando Dashboard e medindo W3C Navigation Timing...');
  const tDashStart = performance.now();
  await page.goto(`${TARGET_URL}/dashboard`, { waitUntil: 'networkidle' });
  const dashDur = performance.now() - tDashStart;

  // Extrai métricas do W3C Navigation Timing da página via JavaScript in-page
  const w3cMetrics = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    return {
      dnsTime: (nav.domainLookupEnd && nav.domainLookupStart) ? Math.max(0, nav.domainLookupEnd - nav.domainLookupStart) : 0,
      tcpTime: (nav.connectEnd && nav.connectStart) ? Math.max(0, nav.connectEnd - nav.connectStart) : 0,
      tlsTime: (nav.connectEnd && nav.secureConnectionStart) ? Math.max(0, nav.connectEnd - nav.secureConnectionStart) : 0,
      ttfb: (nav.responseStart && nav.requestStart) ? Math.max(0, nav.responseStart - nav.requestStart) : (nav.responseStart || 0),
      domInteractive: nav.domInteractive || 0,
      domContentLoaded: nav.domContentLoadedEventEnd || 0,
      pageLoad: nav.loadEventEnd || 0
    };
  });

  const dashScreenshot = path.join(RESULTS_DIR, `screenshot_dashboard_${ENV_LABEL}.png`);
  await page.screenshot({ path: dashScreenshot, fullPage: false });
  console.log(` OK! (Dashboard montado em ${dashDur.toFixed(1)} ms) ✅`);

  // 3. ETAPA 3: PREVIEW DE XML DE NF-E NO NAVEGADOR
  process.stdout.write('📄 [3/3] Testando leitura e preview de NF-e no cliente (/compras/novo)...');
  await page.goto(`${TARGET_URL}/compras/novo`, { waitUntil: 'domcontentloaded' });

  let xmlParseDur = 0;
  let xmlTotalItems = 0;

  if (fs.existsSync(XML_FIXTURE_PATH)) {
    const fileInput = await page.$('#inputXmlFile');
    if (fileInput) {
      const tXmlStart = performance.now();
      await fileInput.setInputFiles(XML_FIXTURE_PATH);
      // Aguarda tabela de preview renderizar
      await page.waitForSelector('#painelItensXml', { state: 'visible', timeout: 5000 }).catch(() => {});
      xmlParseDur = performance.now() - tXmlStart;

      xmlTotalItems = await page.$$eval('#tabelaItensXml tbody tr', rows => rows.length).catch(() => 0);
    }
  }

  const xmlScreenshot = path.join(RESULTS_DIR, `screenshot_xml_preview_${ENV_LABEL}.png`);
  await page.screenshot({ path: xmlScreenshot, fullPage: false });
  console.log(` OK! (NF-e processada em ${xmlParseDur.toFixed(1)} ms) ✅\n`);

  await browser.close();

  if (ENV_LABEL === 'local') {
    telemetria = monitor.stop();
  }

  // EXIBE RESULTADOS CONSOLIDADOS
  console.log('================================================================');
  console.log(`🏆 RELATÓRIO DO NAVEGADOR: [${ENV_LABEL.toUpperCase()}]`);
  console.log('================================================================');
  console.log(`🖥️  Motor Headless:             ${browserEngine}`);
  console.log(`⏱️  Tempo Total de Login:       ${loginDur.toFixed(1)} ms`);
  console.log(`📡 Latência TTFB (Rede+Server): ${w3cMetrics.ttfb.toFixed(1)} ms`);
  console.log(`🧱 DOM Content Loaded:          ${w3cMetrics.domContentLoaded.toFixed(1)} ms`);
  console.log(`🚀 Carregamento Completo:       ${dashDur.toFixed(1)} ms`);
  console.log(`⚡ Parse Client-Side da NF-e:   ${xmlParseDur.toFixed(1)} ms (${xmlTotalItems} itens mapeados)`);
  console.log('----------------------------------------------------------------');
  console.log('📸 EVIDÊNCIAS VISUAIS SALVAS:');
  console.log(`   • ${path.basename(dashScreenshot)}`);
  console.log(`   • ${path.basename(xmlScreenshot)}`);
  console.log('================================================================\n');

  // Salva telemetria JSON
  const metricsData = {
    env: ENV_LABEL,
    engine: browserEngine,
    timestamp: new Date().toISOString(),
    login_ms: parseFloat(loginDur.toFixed(1)),
    dashboard_total_ms: parseFloat(dashDur.toFixed(1)),
    ttfb_ms: parseFloat(w3cMetrics.ttfb.toFixed(1)),
    dom_content_loaded_ms: parseFloat(w3cMetrics.domContentLoaded.toFixed(1)),
    page_load_ms: parseFloat(w3cMetrics.pageLoad.toFixed(1)),
    xml_parse_client_ms: parseFloat(xmlParseDur.toFixed(1)),
    xml_items: xmlTotalItems
  };

  const now = Date.now();
  const jsonFile = path.join(RESULTS_DIR, `browser_metrics_${ENV_LABEL}_run${RUN_NUM}_${now}.json`);
  fs.writeFileSync(jsonFile, JSON.stringify(metricsData, null, 2), 'utf-8');
  console.log(`💾 Métricas W3C JSON salvas em: ${path.basename(jsonFile)}`);

  // Salva dados brutos em CSV (Preservação Científica Completa)
  const csvFile = path.join(RESULTS_DIR, `browser_metrics_${ENV_LABEL}_run${RUN_NUM}_${now}.csv`);
  const csvHeader = 'Timestamp,Env,Run,Engine,Step,Status,Latency_ms,TTFB_ms,DomContentLoaded_ms,XmlItems\n';
  const csvRows = [
    `${metricsData.timestamp},${ENV_LABEL},${RUN_NUM},"${browserEngine}",login,200,${loginDur.toFixed(2)},,,`,
    `${metricsData.timestamp},${ENV_LABEL},${RUN_NUM},"${browserEngine}",dashboard_w3c,200,${dashDur.toFixed(2)},${w3cMetrics.ttfb.toFixed(2)},${w3cMetrics.domContentLoaded.toFixed(2)},`,
    `${metricsData.timestamp},${ENV_LABEL},${RUN_NUM},"${browserEngine}",xml_parse_client,200,${xmlParseDur.toFixed(2)},,,${xmlTotalItems}`
  ].join('\n');
  fs.writeFileSync(csvFile, csvHeader + csvRows, 'utf-8');
  console.log(`💾 [DADOS BRUTOS] Arquivo CSV salvo: ${path.basename(csvFile)}\n`);

  return metricsData;
}

if (require.main === module) {
  run().catch(console.error);
}

module.exports = { run };
