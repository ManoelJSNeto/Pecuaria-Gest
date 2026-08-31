const http = require('http');

async function request(options, data = null) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        resolve({
          statusCode: res.statusCode,
          headers: res.headers,
          body: body
        });
      });
    });
    req.on('error', reject);
    if (data) req.write(data);
    req.end();
  });
}

async function run() {
  console.log("=== TESTE DE INTEGRAÇÃO & SMOKE TEST ===");

  // 1. GET /login
  const res1 = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/login',
    method: 'GET'
  });
  console.log(`1. GET /login -> HTTP ${res1.statusCode} ${res1.statusCode === 200 ? '✅ OK' : '❌ FALHA'}`);

  const cookies = res1.headers['set-cookie'] || [];
  const sessionCookie = cookies.map(c => c.split(';')[0]).join('; ');
  const csrfMatch = res1.body.match(/name="_csrf" value="([^"]+)"/);
  const csrf = csrfMatch ? csrfMatch[1] : '';

  // 2. POST /login com credenciais
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
  console.log(`2. POST /login -> HTTP ${res2.statusCode} (Location: ${res2.headers['location']}) ${res2.statusCode === 302 ? '✅ OK (Redirecionou)' : '❌ FALHA'}`);

  const authCookies = (res2.headers['set-cookie'] || cookies).map(c => c.split(';')[0]).join('; ');

  // 3. GET /dashboard com sessão
  const res3 = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/dashboard',
    method: 'GET',
    headers: {
      'Cookie': authCookies
    }
  });
  console.log(`3. GET /dashboard -> HTTP ${res3.statusCode} ${res3.statusCode === 200 ? '✅ OK' : '❌ FALHA'}`);

  // 4. POST /api/sync
  const syncPayload = JSON.stringify({
    dispositivo: "Node Test Runner",
    auth_email: "admin@fazenda.com",
    auth_senha: "admin123",
    animais_novos: [
      {
        brinco: "INT_" + Date.now().toString().slice(-4),
        sexo: "M",
        raca: "Nelore",
        nome: "Boi Teste Integracao",
        data_nascimento: "2024-01-15"
      }
    ],
    pesagens: [],
    saude: []
  });

  const res4 = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/api/sync',
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(syncPayload),
      'X-API-KEY': 'pecuaria-mobile-key'
    }
  }, syncPayload);
  console.log(`4. POST /api/sync -> HTTP ${res4.statusCode}: ${res4.body}`);

  // 5. GET /api/animais
  const res5 = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/api/animais',
    method: 'GET',
    headers: {
      'X-API-KEY': 'pecuaria-mobile-key'
    }
  });
  const data = JSON.parse(res5.body);
  console.log(`5. GET /api/animais -> Total: ${data.animais.length} animais. ${data.animais.length > 0 ? '✅ OK' : '❌ FALHA'}`);

  console.log("=== FIM DOS TESTES AUTOMATIZADOS ===");
}

run().catch(console.error);
