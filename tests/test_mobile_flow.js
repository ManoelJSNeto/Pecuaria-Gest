const http = require('http');

async function postJson(urlPath, payload, apiKey = process.env.API_KEY || 'pecuaria-mobile-key') {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(payload);
    const req = http.request({
      hostname: 'localhost',
      port: 8080,
      path: urlPath,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(data),
        'X-API-KEY': apiKey
      }
    }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve({ status: res.statusCode, body: body }));
    });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

async function getJson(urlPath, apiKey = process.env.API_KEY || 'pecuaria-mobile-key') {
  return new Promise((resolve, reject) => {
    const req = http.request({
      hostname: 'localhost',
      port: 8080,
      path: urlPath,
      method: 'GET',
      headers: {
        'X-API-KEY': apiKey
      }
    }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve({ status: res.statusCode, body: body }));
    });
    req.on('error', reject);
    req.end();
  });
}

async function runMobileTest() {
  console.log("=== TESTE DE FLUXO MOBILE NATIVO OFFLINE-TO-ONLINE ===");

  // Simula imagem em base64 (pixel transparente PNG)
  const fakePhotoBase64 = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==";
  const testBrinco = "MOB_" + Date.now().toString().slice(-4);

  // 1. Simula lote offline coletado no pasto
  const mobileBatch = {
    dispositivo: "Samsung Galaxy XCover (App Nativo Campo)",
    api_key: process.env.API_KEY || "pecuaria-mobile-key",
    auth_email: "admin@fazenda.com",
    auth_senha: "admin123",
    animais_novos: [
      {
        brinco: testBrinco,
        sexo: "M",
        raca: "Nelore Mocho",
        nome: "Touro Campestre",
        data_nascimento: "2024-02-10",
        foto_base64: fakePhotoBase64
      }
    ],
    pesagens: [
      {
        brinco: testBrinco,
        peso: 512.4,
        data: "2026-08-30",
        observacao: "Pesagem Balança Curral 2",
        foto_base64: fakePhotoBase64
      }
    ],
    saude: [
      {
        brinco: testBrinco,
        tipo: "Vacinação",
        descricao: "Imunização Raiva e Clostridiose",
        medicamento: "Poli-Star",
        dose: "5ml",
        data: "2026-08-30",
        foto_base64: fakePhotoBase64
      }
    ]
  };

  console.log(`1. Transmitindo lote offline para o servidor central (Animal: ${testBrinco})...`);
  const resSync = await postJson('/api/sync', mobileBatch);
  console.log(`   Status HTTP: ${resSync.status}`);
  console.log(`   Resposta: ${resSync.body}`);

  const syncData = JSON.parse(resSync.body);
  if (resSync.status !== 200 || syncData.status !== 'ok') {
    throw new Error("Falha na sincronização do lote mobile!");
  }

  // 2. Consulta API Central para verificar ingestão
  console.log(`2. Consultando /api/animais para verificar atualização na base...`);
  const resAnimais = await getJson('/api/animais');
  const animaisData = JSON.parse(resAnimais.body);
  const animalCadastrado = (animaisData.animais || []).find(a => a.brinco === testBrinco);

  if (!animalCadastrado) {
    throw new Error(`Animal ${testBrinco} não foi encontrado na base após o sync!`);
  }

  console.log(`   ✅ Animal ${testBrinco} confirmado no PostgreSQL!`);
  console.log(`   Nome: ${animalCadastrado.nome} | Raça: ${animalCadastrado.raca} | Peso Atual: ${animalCadastrado.peso_atual} kg`);
  console.log("=== FLUXO MOBILE VALIDADO COM 100% DE SUCESSO ===");
}

runMobileTest().catch(err => {
  console.error("❌ ERRO NO TESTE MOBILE:", err);
  process.exit(1);
});
