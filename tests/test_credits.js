const http = require('http');
const fs = require('fs');
const path = require('path');

function get(urlPath, headers = {}) {
  return new Promise((resolve, reject) => {
    http.get('http://localhost:8080' + urlPath, { headers }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ status: res.statusCode, body: data, headers: res.headers }));
    }).on('error', reject);
  });
}

function post(urlPath, body, headers = {}) {
  return new Promise((resolve, reject) => {
    const req = http.request('http://localhost:8080' + urlPath, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(body),
        ...headers
      }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ status: res.statusCode, body: data, headers: res.headers }));
    });
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

async function run() {
  console.log('=== TESTE DE CRÉDITOS EDUCACIONAIS & AUTORIA (3 GITHUBS + MOBILE) ===\n');

  // 1. Tela de Login
  const loginRes = await get('/login');
  const cookies = loginRes.headers['set-cookie'] || [];
  const sessionCookie = cookies.map(c => c.split(';')[0]).join('; ');
  const csrfMatch = loginRes.body.match(/name="_csrf" value="([^"]+)"/);
  const csrf = csrfMatch ? csrfMatch[1] : '';

  const loginHasEdu = loginRes.body.includes('Trabalho Educacional');
  const loginHasManoelGh = loginRes.body.includes('https://github.com/ManoelJSNeto');
  const loginHasPedroVGh = loginRes.body.includes('https://github.com/pedro-henrique-vs');
  const loginHasPedroRGh = loginRes.body.includes('https://github.com/PedroRoman444');
  const loginFooterDiscreet = loginRes.body.includes('login-footer');

  console.log('1. Tela de Login (/login):');
  console.log('   - Rodapé discreto abaixo do card (login-footer):', loginFooterDiscreet ? '✅ SIM' : '❌ NÃO');
  console.log('   - Menciona Trabalho Educacional:', loginHasEdu ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub ManoelJSNeto:', loginHasManoelGh ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub pedro-henrique-vs:', loginHasPedroVGh ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub PedroRoman444:', loginHasPedroRGh ? '✅ SIM' : '❌ NÃO');

  // 2. Autenticação e Verificação no Layout Geral
  const postData = `email=admin%40fazenda.com&senha=admin123&_csrf=${encodeURIComponent(csrf)}`;
  const authPost = await post('/login', postData, { 'Cookie': sessionCookie });
  const authCookies = (authPost.headers['set-cookie'] || cookies).map(c => c.split(';')[0]).join('; ');

  const dashRes = await get('/dashboard', { 'Cookie': authCookies });
  const dashSidebarClean = !dashRes.body.includes('sidebar-credits');
  const dashHasSiteFooter = dashRes.body.includes('app-site-footer');
  const dashHasManoelGh = dashRes.body.includes('https://github.com/ManoelJSNeto');
  const dashHasPedroVGh = dashRes.body.includes('https://github.com/pedro-henrique-vs');
  const dashHasPedroRGh = dashRes.body.includes('https://github.com/PedroRoman444');
  const dashHasFazenda = /fazenda pecuGest/i.test(dashRes.body);

  console.log('\n2. Layout do Sistema Logado (/dashboard):');
  console.log('   - Sidebar desobstruída (sem poluição visual):', dashSidebarClean ? '✅ SIM' : '❌ NÃO');
  console.log('   - Rodapé geral de rodapé (app-site-footer):', dashHasSiteFooter ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub ManoelJSNeto no rodapé:', dashHasManoelGh ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub pedro-henrique-vs no rodapé:', dashHasPedroVGh ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub PedroRoman444 no rodapé:', dashHasPedroRGh ? '✅ SIM' : '❌ NÃO');
  console.log('   - Nome da Fazenda atualizado no topo (fazenda pecuGest):', dashHasFazenda ? '✅ SIM' : '❌ NÃO');

  // 3. Verificação no Mobile App
  const mobileHtmlPath = path.join(__dirname, '..', 'mobile-app', 'www', 'index.html');
  const mobileHtml = fs.readFileSync(mobileHtmlPath, 'utf-8');

  const mobileHasFilaFooter = mobileHtml.includes('Trabalho Educacional') && mobileHtml.includes('tabPane_fila');
  const mobileHasManoelGh = mobileHtml.includes('https://github.com/ManoelJSNeto');
  const mobileHasPedroVGh = mobileHtml.includes('https://github.com/pedro-henrique-vs');
  const mobileHasPedroRGh = mobileHtml.includes('https://github.com/PedroRoman444');

  console.log('\n3. Aplicativo Mobile (mobile-app/www/index.html):');
  console.log('   - Rodapé na Aba Fila com Trabalho Educacional:', mobileHasFilaFooter ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub ManoelJSNeto no mobile:', mobileHasManoelGh ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub pedro-henrique-vs no mobile:', mobileHasPedroVGh ? '✅ SIM' : '❌ NÃO');
  console.log('   - Link GitHub PedroRoman444 no mobile:', mobileHasPedroRGh ? '✅ SIM' : '❌ NÃO');

  const allPassed = loginHasEdu && loginHasManoelGh && loginHasPedroVGh && loginHasPedroRGh && loginFooterDiscreet
    && dashSidebarClean && dashHasSiteFooter && dashHasManoelGh && dashHasPedroVGh && dashHasPedroRGh && dashHasFazenda
    && mobileHasFilaFooter && mobileHasManoelGh && mobileHasPedroVGh && mobileHasPedroRGh;

  console.log('\nResultado Geral:', allPassed ? '🎉 TODOS OS CRÉDITOS E LINKS VERIFICADOS COM 100% DE SUCESSO!' : '❌ FALHA EM ALGUM PONTO');
}

run().catch(console.error);
