const http = require('http');

function request(options, postData) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ statusCode: res.statusCode, headers: res.headers, body: data }));
    });
    req.on('error', reject);
    if (postData) req.write(postData);
    req.end();
  });
}

(async () => {
  console.log("=== VERIFICAÇÃO DAS REFORMULAÇÕES UI/UX ===");
  const res1 = await request({ hostname: 'localhost', port: 8080, path: '/login', method: 'GET' });
  const cookies = res1.headers['set-cookie'] || [];
  let sessionCookie = cookies.map(c => c.split(';')[0]).join('; ');
  const csrfMatch = res1.body.match(/name="_csrf" value="([^"]+)"/);
  const csrf = csrfMatch ? csrfMatch[1] : '';

  const postData = `email=admin%40fazenda.com&senha=admin123&_csrf=${encodeURIComponent(csrf)}`;
  const res2 = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/login',
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Content-Length': Buffer.byteLength(postData),
      'Cookie': sessionCookie
    }
  }, postData);

  if (res2.headers['set-cookie']) {
    sessionCookie = res2.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');
  }

  // 1. /usuarios/novo
  const resNovo = await request({ hostname: 'localhost', port: 8080, path: '/usuarios/novo', method: 'GET', headers: { 'Cookie': sessionCookie } });
  console.log("1. /usuarios/novo -> HTTP", resNovo.statusCode, 
    "| Wizard Stepper:", resNovo.body.includes('aws-wizard-stepper') ? "✅ OK" : "❌ Faltando",
    "| Review Table:", resNovo.body.includes('aws-review-table') ? "✅ OK" : "❌ Faltando",
    "| Drawer Ajuda:", resNovo.body.includes('abrirAjudaUsuarios') ? "✅ OK" : "❌ Faltando"
  );

  // 2. /usuarios
  const resUsers = await request({ hostname: 'localhost', port: 8080, path: '/usuarios', method: 'GET', headers: { 'Cookie': sessionCookie } });
  console.log("2. /usuarios -> HTTP", resUsers.statusCode,
    "| Drawer Ajuda:", resUsers.body.includes('abrirAjudaUsuarios') ? "✅ OK" : "❌ Faltando"
  );

  // 3. /relatorios
  const resRel = await request({ hostname: 'localhost', port: 8080, path: '/relatorios', method: 'GET', headers: { 'Cookie': sessionCookie } });
  console.log("3. /relatorios -> HTTP", resRel.statusCode,
    "| Drawer Ajuda:", resRel.body.includes('abrirAjudaRelatorios') ? "✅ OK" : "❌ Faltando"
  );

  // 4. /compras/novo
  const resCom = await request({ hostname: 'localhost', port: 8080, path: '/compras/novo', method: 'GET', headers: { 'Cookie': sessionCookie } });
  console.log("4. /compras/novo -> HTTP", resCom.statusCode,
    "| Sem abas (Mode Tabs removido):", !resCom.body.includes('aws-mode-tabs') ? "✅ OK" : "❌ Falhou",
    "| XML Dropzone permanente:", resCom.body.includes('xml-import-zone') ? "✅ OK" : "❌ Falhou"
  );

  // 5. /vendas/novo
  const resVen = await request({ hostname: 'localhost', port: 8080, path: '/vendas/novo', method: 'GET', headers: { 'Cookie': sessionCookie } });
  console.log("5. /vendas/novo -> HTTP", resVen.statusCode,
    "| Sem abas (Mode Tabs removido):", !resVen.body.includes('aws-mode-tabs') ? "✅ OK" : "❌ Falhou",
    "| XML Dropzone permanente:", resVen.body.includes('xml-import-zone') ? "✅ OK" : "❌ Falhou"
  );

  console.log("=== TODAS AS VERIFICAÇÕES CONCLUÍDAS ===");
})();
