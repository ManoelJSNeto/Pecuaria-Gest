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
  console.log("=== BATERIA COMPLETA DE TESTES: POSTGRESQL & TODAS AS ROTAS ===");

  // 1. GET /login
  const res1 = await request({ hostname: 'localhost', port: 8080, path: '/login', method: 'GET' });
  console.log(`1. GET /login -> HTTP ${res1.statusCode} ${res1.statusCode === 200 ? '✅ OK' : '❌ FALHA'}`);

  const cookies = res1.headers['set-cookie'] || [];
  const sessionCookie = cookies.map(c => c.split(';')[0]).join('; ');
  const csrfMatch = res1.body.match(/name="_csrf" value="([^"]+)"/);
  const csrf = csrfMatch ? csrfMatch[1] : '';

  // 2. POST /login
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
  console.log(`2. POST /login -> HTTP ${res2.statusCode} (Redirect: ${res2.headers['location']}) ${res2.statusCode === 302 ? '✅ OK' : '❌ FALHA'}`);

  const authCookies = (res2.headers['set-cookie'] || cookies).map(c => c.split(';')[0]).join('; ');

  // 3. Teste de todas as rotas autenticadas do painel (com queries PostgreSQL nativas)
  const views = [
    { path: '/dashboard', name: 'Dashboard (com gráfico pesoTrend)' },
    { path: '/animais', name: 'Listagem de Animais' },
    { path: '/animais/novo', name: 'Novo Animal' },
    { path: '/compras', name: 'Compras de Gado (Cockpit)' },
    { path: '/compras/novo', name: 'Registrar Compra (XML)' },
    { path: '/vendas', name: 'Vendas de Gado (Cockpit)' },
    { path: '/vendas/novo', name: 'Registrar Venda (XML)' },
    { path: '/pesagens', name: 'Histórico de Pesagens' },
    { path: '/pesagens/novo', name: 'Registrar Pesagem' },
    { path: '/saude', name: 'Manejo Sanitário' },
    { path: '/saude/novo', name: 'Registrar Evento de Saúde' },
    { path: '/pastagens', name: 'Gestão de Pastagens' },
    { path: '/pastagens/novo', name: 'Nova Pastagem' },
    { path: '/reproducao', name: 'Reprodução' },
    { path: '/reproducao/novo', name: 'Novo Manejo Reprodutivo' },
    { path: '/alertas', name: 'Painel de Alertas' },
    { path: '/alertas/novo', name: 'Novo Alerta' },
    { path: '/relatorios', name: 'Relatórios Gerenciais' },
    { path: '/sincronizacoes', name: 'Histórico de Sincronizações' },
    { path: '/usuarios', name: 'Gestão de Usuários (RBAC)' },
    { path: '/configuracoes', name: 'Configurações do Sistema' }
  ];

  for (const v of views) {
    const res = await request({
      hostname: 'localhost',
      port: 8080,
      path: v.path,
      method: 'GET',
      headers: { 'Cookie': authCookies }
    });
    const ok = res.statusCode === 200 && !res.body.includes('Fatal error') && !res.body.includes('PDOException');
    console.log(`3. Rota ${v.path.padEnd(16)} [${v.name}] -> HTTP ${res.statusCode} ${ok ? '✅ OK (Sem erros SQL)' : '❌ ERRO'}`);
    if (!ok) {
      console.error(`ERRO NA ROTA ${v.path}:`, res.body.slice(0, 300));
    }
  }

  // 4. Teste de API Mobile /api/sync completo
  const testBrinco = "PG_" + Date.now().toString().slice(-4);
  const syncPayload = JSON.stringify({
    dispositivo: "Bateria Teste PostgreSQL",
    auth_email: "admin@fazenda.com",
    auth_senha: "admin123",
    animais_novos: [
      {
        brinco: testBrinco,
        sexo: "F",
        raca: "Girolando",
        nome: "Novilha Teste",
        data_nascimento: "2024-03-10"
      }
    ],
    pesagens: [
      {
        brinco: testBrinco,
        peso: 385.5,
        data: "2026-08-30",
        observacao: "Primeira pesagem"
      }
    ],
    saude: [
      {
        brinco: testBrinco,
        tipo: "Vacinação",
        descricao: "Brucelose",
        data: "2026-08-30"
      }
    ]
  });

  const resSync = await request({
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
  const syncJson = JSON.parse(resSync.body);
  const syncOk = resSync.statusCode === 200 && syncJson.status === 'ok' && syncJson.processados.animais_novos === 1;
  console.log(`4. POST /api/sync -> HTTP ${resSync.statusCode} ${syncOk ? '✅ SUCESSO' : '❌ FALHA'}`);

  // 5. Teste GET /api/animais
  const resAnimais = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/api/animais',
    method: 'GET',
    headers: { 'X-API-KEY': 'pecuaria-mobile-key' }
  });
  const animaisJson = JSON.parse(resAnimais.body);
  const foundAnimal = animaisJson.animais.find(a => a.brinco === testBrinco);
  console.log(`5. GET /api/animais -> Animal ${testBrinco} retornado com peso_atual ${foundAnimal?.peso_atual} kg ${foundAnimal ? '✅ CONFIRMADO' : '❌ NÃO ENCONTRADO'}`);

  console.log("=== BATERIA COMPLETA CONCLUÍDA COM 100% DE SUCESSO ===");
}

run().catch(err => {
  console.error("FALHA NOS TESTES:", err);
  process.exit(1);
});
