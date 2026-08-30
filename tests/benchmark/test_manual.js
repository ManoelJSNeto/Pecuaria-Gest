const API_KEY = 'pecuaria-mobile-key';

async function testEndpoint(name, url) {
  console.log(`\n========================================`);
  console.log(`🧪 Testando: ${name} (${url}/api/sync)`);
  console.log(`========================================`);

  const payload = {
    animais: [
      {
        brinco: `VAL-${Date.now().toString().slice(-5)}`,
        nome: 'Bezerro Validacao Estrita',
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
        observacao: 'Pesagem manual com checagem estrita de contadores'
      }
    ],
    saude: [
      {
        animal_id: 1,
        tipo: 'Vacinação',
        descricao: 'Vacina Aftosa Estrita',
        medicamento: 'Biovet',
        dose: '5ml'
      }
    ]
  };

  try {
    const res = await fetch(`${url}/api/sync`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-API-KEY': API_KEY
      },
      body: JSON.stringify(payload)
    });

    const status = res.status;
    const body = await res.json();

    console.log(`📡 HTTP Status: ${status} ${res.ok ? '✅' : '❌'}`);
    console.log('📦 Resposta JSON Retornada:');
    console.log(JSON.stringify(body, null, 2));

    const p = body.processados || {};
    const hasProcessedAll = (p.animais_novos === 1) && (p.pesagens === 1) && (p.saude === 1);
    const noErrors = Array.isArray(body.erros) && body.erros.length === 0;

    console.log('\n🔍 Auditoria Estrita de Contadores:');
    console.log(`   • animais_novos: ${p.animais_novos} / 1  ${p.animais_novos === 1 ? '✅' : '❌'}`);
    console.log(`   • pesagens:      ${p.pesagens} / 1  ${p.pesagens === 1 ? '✅' : '❌'}`);
    console.log(`   • saude:         ${p.saude} / 1  ${p.saude === 1 ? '✅' : '❌'}`);
    console.log(`   • erros:         ${(body.erros || []).length} erros  ${noErrors ? '✅' : '❌'}`);

    if (res.ok && body.status === 'ok' && hasProcessedAll && noErrors) {
      console.log(`\n🎉 CRITÉRIO DE ACEITE ATINGIDO COM 100% DE SUCESSO no ${name}!`);
      return true;
    } else {
      console.log(`\n⚠️ CRITÉRIO DE ACEITE NÃO ATINGIDO: Registros não foram processados pelo banco no ${name}!`);
      return false;
    }
  } catch (err) {
    console.log(`❌ Erro de conexão com ${url}:`, err.message);
    return false;
  }
}

async function runAll() {
  await testEndpoint('Servidor Local (On-Premise)', 'http://localhost:8080');
  await testEndpoint('Servidor Nuvem AWS (EC2 t3.micro)', 'http://32.197.185.161:8080');
}

runAll();
