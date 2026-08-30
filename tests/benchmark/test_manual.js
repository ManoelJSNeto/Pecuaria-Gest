const API_KEY = 'pecuaria-mobile-key';
const BENCHMARK_SECRET = process.env.BENCHMARK_SECRET || 'pecuaria-benchmark-secret-2026';

async function testEndpointFullVerification(name, url) {
  console.log(`\n============================================================`);
  console.log(`🧪 TESTE DE VALIDAÇÃO ESTRITA 4 ETAPAS: ${name}`);
  console.log(`🎯 URL: ${url}`);
  console.log(`============================================================`);

  // ETAPA 1: Reset do Banco via Chave Secreta
  console.log('▶️ ETAPA 1: Chamando POST /api/benchmark/reset com chave secreta...');
  let resetOk = false;
  try {
    const resReset = await fetch(`${url}/api/benchmark/reset`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-BENCHMARK-SECRET': BENCHMARK_SECRET
      }
    });
    const bodyReset = await resReset.json();
    console.log(`   • HTTP Status: ${resReset.status} ${resReset.ok ? '✅ OK' : '❌ ERRO'}`);
    console.log(`   • Resposta:`, JSON.stringify(bodyReset));
    if (resReset.ok && bodyReset.status === 'ok') resetOk = true;
  } catch (err) {
    console.log(`   ❌ Falha no reset: ${err.message}`);
    return false;
  }

  if (!resetOk) {
    console.log(`❌ ETAPA 1 FALHOU no ${name}!`);
    return false;
  }

  // ETAPA 2: Confirmar se existem exatamente 30 animais seed
  console.log('\n▶️ ETAPA 2: Consultando GET /api/animais para auditar contagem seed...');
  let totalSeed = 0;
  try {
    const resGet = await fetch(`${url}/api/animais`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-API-KEY': API_KEY
      }
    });
    const json = await resGet.json();
    const animais = json.animais || json;
    totalSeed = Array.isArray(animais) ? animais.length : 0;
    console.log(`   • HTTP Status: ${resGet.status} ${resGet.ok ? '✅ OK' : '❌ ERRO'}`);
    console.log(`   • Total de Animais no Banco: ${totalSeed} (Esperado: 30) ${totalSeed === 30 ? '✅ EXATO' : '⚠️ DIVERGENTE'}`);
  } catch (err) {
    console.log(`   ❌ Falha no GET: ${err.message}`);
    return false;
  }

  if (totalSeed !== 30) {
    console.log(`❌ ETAPA 2 FALHOU: Contagem de animais ${totalSeed} != 30 no ${name}!`);
    return false;
  }

  // ETAPA 3: Envio de 1 Sync Manual com dados
  console.log('\n▶️ ETAPA 3: Enviando 1 lote de sincronização (1 animal, 1 pesagem, 1 saúde)...');
  const payload = {
    animais: [
      {
        brinco: `VAL-${Date.now().toString().slice(-5)}`,
        nome: 'Bezerro Validacao 4-Etapas',
        sexo: 'M',
        raca: 'Nelore',
        data_nascimento: '2026-08-30',
        peso_inicial: 205.0,
        status: 'ativo',
        origem: 'Nascimento'
      }
    ],
    pesagens: [
      {
        animal_id: 1,
        peso: 472.5,
        data: '2026-08-30',
        observacao: 'Pesagem auditada'
      }
    ],
    saude: [
      {
        animal_id: 1,
        tipo: 'Vacinação',
        descricao: 'Vacina Aftosa',
        medicamento: 'Biovet',
        dose: '5ml'
      }
    ]
  };

  let syncOk = false;
  try {
    const resSync = await fetch(`${url}/api/sync`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-API-KEY': API_KEY
      },
      body: JSON.stringify(payload)
    });

    const bodySync = await resSync.json();
    console.log(`   • HTTP Status: ${resSync.status} ${resSync.ok ? '✅' : '❌'}`);
    console.log('   • Resposta JSON:', JSON.stringify(bodySync, null, 2));

    const p = bodySync.processados || {};
    const hasProcessedAll = (p.animais_novos === 1) && (p.pesagens === 1) && (p.saude === 1);
    const noErrors = Array.isArray(bodySync.erros) && bodySync.erros.length === 0;

    console.log('\n▶️ ETAPA 4: Auditoria Estrita dos Contadores:');
    console.log(`   • animais_novos: ${p.animais_novos} / 1  ${p.animais_novos === 1 ? '✅' : '❌'}`);
    console.log(`   • pesagens:      ${p.pesagens} / 1  ${p.pesagens === 1 ? '✅' : '❌'}`);
    console.log(`   • saude:         ${p.saude} / 1  ${p.saude === 1 ? '✅' : '❌'}`);
    console.log(`   • erros:         ${(bodySync.erros || []).length} erros  ${noErrors ? '✅' : '❌'}`);

    if (resSync.ok && bodySync.status === 'ok' && hasProcessedAll && noErrors) {
      syncOk = true;
    }
  } catch (err) {
    console.log(`   ❌ Falha no Sync: ${err.message}`);
    return false;
  }

  if (syncOk) {
    console.log(`\n🎉 TODAS AS 4 ETAPAS APROVADAS COM 100% DE SUCESSO no ${name}!\n`);
    return true;
  } else {
    console.log(`\n❌ FALHA NA AUDITORIA DE SINCRONIZAÇÃO no ${name}!\n`);
    return false;
  }
}

async function runAll() {
  await testEndpointFullVerification('Servidor Local (On-Premise)', 'http://localhost:8080');
  await testEndpointFullVerification('Servidor Nuvem AWS (EC2 t3.micro)', 'http://100.55.16.39:8080');
}

runAll();
