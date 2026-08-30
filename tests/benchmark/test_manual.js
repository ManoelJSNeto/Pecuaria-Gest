const API_KEY = 'pecuaria-mobile-key';

async function testEndpoint(name, url) {
  console.log(`\n========================================`);
  console.log(`🧪 Testando: ${name} (${url}/api/sync)`);
  console.log(`========================================`);

  const payload = {
    animais: [
      {
        brinco: `VAL-${Date.now().toString().slice(-4)}`,
        nome: 'Bezerro Validacao Manual',
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
        observacao: 'Pesagem manual de validacao do contrato API'
      }
    ],
    saude: [
      {
        animal_id: 1,
        tipo: 'Vacinação',
        descricao: 'Vacina Aftosa Manual',
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

    console.log(`📡 HTTP Status: ${status} ${res.ok ? '✅ OK' : '❌ ERRO'}`);
    console.log('📦 Resposta JSON:', JSON.stringify(body, null, 2));

    if (res.ok && body.status === 'ok') {
      console.log(`🎯 CRITÉRIO DE ACEITE: 100% SUCESSO no ${name}!`);
      return true;
    } else {
      console.log(`⚠️ Falha na validação no ${name}`);
      return false;
    }
  } catch (err) {
    console.log(`❌ Erro de conexão com ${url}:`, err.message);
    return false;
  }
}

async function runAll() {
  await testEndpoint('Servidor Local (On-Premise)', 'http://localhost:8080');
  await testEndpoint('Servidor Nuvem AWS (EC2 t3.micro)', 'http://44.197.119.138:8080');
}

runAll();
