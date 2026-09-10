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
  console.log("=== TESTE DE ENTRADA DE COMPRA: ROMANEIO CABEÇA A CABEÇA ===");

  // 1. Login
  const res1 = await request({ hostname: 'localhost', port: 8080, path: '/login', method: 'GET' });
  const cookies = res1.headers['set-cookie'] || [];
  const sessionCookie = cookies.map(c => c.split(';')[0]).join('; ');
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

  const authCookies = (res2.headers['set-cookie'] || cookies).map(c => c.split(';')[0]).join('; ');

  // 2. Obter CSRF de /compras/novo
  const resNovo = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/compras/novo',
    method: 'GET',
    headers: { 'Cookie': authCookies }
  });

  const csrfNovoMatch = resNovo.body.match(/name="_csrf" value="([^"]+)"/);
  const csrfNovo = csrfNovoMatch ? csrfNovoMatch[1] : '';

  // 3. Submeter compra com modo individual (3 cabeças com brincos e pesos únicos)
  const timestamp = Date.now().toString().slice(-4);
  const brinco1 = `TEST-ROM1-${timestamp}`;
  const brinco2 = `TEST-ROM2-${timestamp}`;
  const brinco3 = `TEST-ROM3-${timestamp}`;

  const payload = new URLSearchParams({
    _csrf: csrfNovo,
    numero_gta: `GTA-ROM-${timestamp}`,
    chave_nfe: '35260900000000000000550010000000001000000000',
    fornecedor_origem: 'Fazenda Estrela do Sul (Romaneio Individual)',
    data_compra: '2026-09-09',
    descricao: 'Lote Teste Entrada Individual Cabeça a Cabeça',
    quantidade_cabecas: '3',
    peso_total_kg: '1050.0',
    valor_total: '9450.00',
    pasto_destino_id: '1',
    cadastrar_animais: '1',
    modo_entrada_animais: 'individual',
    'animais_individuais[1][brinco]': brinco1,
    'animais_individuais[1][raca]': 'Nelore',
    'animais_individuais[1][sexo]': 'M',
    'animais_individuais[1][peso]': '340.5',
    'animais_individuais[1][valor]': '3150.00',
    'animais_individuais[2][brinco]': brinco2,
    'animais_individuais[2][raca]': 'Angus',
    'animais_individuais[2][sexo]': 'M',
    'animais_individuais[2][peso]': '365.0',
    'animais_individuais[2][valor]': '3150.00',
    'animais_individuais[3][brinco]': brinco3,
    'animais_individuais[3][raca]': 'Cruzamento Industrial',
    'animais_individuais[3][sexo]': 'F',
    'animais_individuais[3][peso]': '344.5',
    'animais_individuais[3][valor]': '3150.00'
  }).toString();

  const resSalvar = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/compras/salvar',
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Content-Length': Buffer.byteLength(payload),
      'Cookie': authCookies
    }
  }, payload);

  console.log(`1. POST /compras/salvar -> HTTP ${resSalvar.statusCode} (Redirect: ${resSalvar.headers.location})`);
  if (resSalvar.statusCode !== 302 || resSalvar.headers.location !== '/compras') {
    throw new Error(`Falha ao salvar compra individual: esperado 302 /compras, obtido ${resSalvar.statusCode}`);
  }

  // 4. Verificar se os animais foram cadastrados no rebanho
  const resAnimais = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/animais',
    method: 'GET',
    headers: { 'Cookie': authCookies }
  });

  if (!resAnimais.body.includes(brinco1) || !resAnimais.body.includes(brinco2) || !resAnimais.body.includes(brinco3)) {
    throw new Error(`Animais do romaneio individual não foram encontrados em /animais!`);
  }
  console.log(`2. Verificação em /animais -> Todos os 3 brincos (${brinco1}, ${brinco2}, ${brinco3}) foram encontrados com sucesso ✅`);

  // 5. Verificar via API Mobile
  const resApi = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/api/animais',
    method: 'GET',
    headers: { 'x-api-key': 'pecuaria-mobile-key' }
  });

  const animaisJson = JSON.parse(resApi.body);
  const lista = animaisJson.animais || [];
  const a1 = lista.find(a => a.brinco === brinco1);
  const a2 = lista.find(a => a.brinco === brinco2);
  const a3 = lista.find(a => a.brinco === brinco3);

  if (!a1 || a1.raca !== 'Nelore' || a1.sexo !== 'M' || parseFloat(a1.peso_atual) !== 340.5) {
    throw new Error(`Dados incorretos para animal ${brinco1}: ${JSON.stringify(a1)}`);
  }
  if (!a2 || a2.raca !== 'Angus' || a2.sexo !== 'M' || parseFloat(a2.peso_atual) !== 365.0) {
    throw new Error(`Dados incorretos para animal ${brinco2}: ${JSON.stringify(a2)}`);
  }
  if (!a3 || a3.raca !== 'Cruzamento Industrial' || a3.sexo !== 'F' || parseFloat(a3.peso_atual) !== 344.5) {
    throw new Error(`Dados incorretos para animal ${brinco3}: ${JSON.stringify(a3)}`);
  }

  console.log(`3. Validação API Mobile (/api/animais) -> Pesos e dados biométricos individuais conferidos perfeitamente:`);
  console.log(`   - ${brinco1}: Raça=${a1.raca}, Sexo=${a1.sexo}, Peso=${a1.peso_atual} kg ✅`);
  console.log(`   - ${brinco2}: Raça=${a2.raca}, Sexo=${a2.sexo}, Peso=${a2.peso_atual} kg ✅`);
  console.log(`   - ${brinco3}: Raça=${a3.raca}, Sexo=${a3.sexo}, Peso=${a3.peso_atual} kg ✅`);

  console.log("\n=== TESTE DE ROMANEIO CABEÇA A CABEÇA: 100% APROVADO COM SUCESSO! ===");
}

run().catch(err => {
  console.error("ERRO NO TESTE:", err.message);
  process.exit(1);
});
