async function testSecurityNegative(url) {
  console.log('\n============================================================');
  console.log('🔒 TESTE NEGATIVO DE SEGURANÇA: /api/benchmark/reset');
  console.log(`🎯 Alvo: ${url}`);
  console.log('============================================================\n');

  // Teste 1: Sem header algum
  console.log('▶️ [Caso 1] Requisição SEM NENHUM CABEÇALHO secreto:');
  try {
    const res = await fetch(`${url}/api/benchmark/reset`, { method: 'POST' });
    const json = await res.json();
    console.log(`   • HTTP Status: ${res.status} (Esperado: 403) ${res.status === 403 ? '✅ BLOQUEADO COM SUCESSO' : '❌ VULNERÁVEL'}`);
    console.log(`   • Resposta:`, JSON.stringify(json));
  } catch (e) {
    console.log('   • Erro de conexão:', e.message);
  }

  // Teste 2: Com chave falsa / inválida
  console.log('\n▶️ [Caso 2] Requisição com CHAVE FALSA (X-BENCHMARK-SECRET: hacker123):');
  try {
    const res = await fetch(`${url}/api/benchmark/reset`, {
      method: 'POST',
      headers: { 'X-BENCHMARK-SECRET': 'hacker_tentativa_12345' }
    });
    const json = await res.json();
    console.log(`   • HTTP Status: ${res.status} (Esperado: 403) ${res.status === 403 ? '✅ BLOQUEADO COM SUCESSO' : '❌ VULNERÁVEL'}`);
    console.log(`   • Resposta:`, JSON.stringify(json));
  } catch (e) {
    console.log('   • Erro de conexão:', e.message);
  }

  // Teste 3: Tentativa usando a API Key pública do mobile
  console.log('\n▶️ [Caso 3] Requisição com a API Key pública (X-API-KEY: pecuaria-mobile-key):');
  try {
    const res = await fetch(`${url}/api/benchmark/reset`, {
      method: 'POST',
      headers: { 'X-API-KEY': 'pecuaria-mobile-key' }
    });
    const json = await res.json();
    console.log(`   • HTTP Status: ${res.status} (Esperado: 403) ${res.status === 403 ? '✅ BLOQUEADO COM SUCESSO' : '❌ VULNERÁVEL'}`);
    console.log(`   • Resposta:`, JSON.stringify(json));
  } catch (e) {
    console.log('   • Erro de conexão:', e.message);
  }

  console.log('\n============================================================\n');
}

async function run() {
  await testSecurityNegative('http://localhost:8080');
}

run();
