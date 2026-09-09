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
  console.log("=== TESTE DE EDIÇÃO E ATUALIZAÇÃO: COMPRAS E VENDAS ===");

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

  // 2. Obter token CSRF da página de edição de compras (/compras/1/editar)
  const resEditCompra = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/compras/1/editar',
    method: 'GET',
    headers: { 'Cookie': authCookies }
  });

  if (resEditCompra.statusCode !== 200) {
    throw new Error(`Falha ao abrir /compras/1/editar: HTTP ${resEditCompra.statusCode}`);
  }
  console.log("1. GET /compras/1/editar -> HTTP 200 ✅ Formulário de edição renderizado com sucesso");

  const csrfCompraMatch = resEditCompra.body.match(/name="_csrf" value="([^"]+)"/);
  const csrfCompra = csrfCompraMatch ? csrfCompraMatch[1] : '';

  // 3. POST /compras/1/atualizar com novo número de GTA e descrição atualizada
  const novoGta = "GTA-TEST-" + Date.now().toString().slice(-4);
  const updateCompraData = [
    `_csrf=${encodeURIComponent(csrfCompra)}`,
    `numero_gta=${encodeURIComponent(novoGta)}`,
    `chave_nfe=35260912345678000195550010000012341000012340`,
    `fornecedor_origem=${encodeURIComponent('Fazenda Teste Agro S/A')}`,
    `data_compra=2026-09-09`,
    `quantidade_cabecas=12`,
    `peso_total_kg=4320`,
    `valor_total=38400.00`,
    `descricao=${encodeURIComponent('Lote Teste Atualizado com Sucesso')}`,
    `pasto_destino_id=1`
  ].join('&');

  const resUpdCompra = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/compras/1/atualizar',
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Content-Length': Buffer.byteLength(updateCompraData),
      'Cookie': authCookies
    }
  }, updateCompraData);

  console.log(`2. POST /compras/1/atualizar -> HTTP ${resUpdCompra.statusCode} (Redirect: ${resUpdCompra.headers['location']}) ✅ Atualização efetuada`);

  // 4. Conferir se a listagem ou a tela de edição reflete o novo número de GTA
  const resCheckCompra = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/compras/1/editar',
    method: 'GET',
    headers: { 'Cookie': authCookies }
  });
  if (resCheckCompra.body.includes(novoGta)) {
    console.log(`3. Conferência de Persistência Compra: Encontrado GTA ${novoGta} ✅ OK`);
  } else {
    throw new Error("GTA atualizado não encontrado na tela de edição!");
  }

  // 5. Teste de Edição de Vendas (/vendas/1/editar)
  const resEditVenda = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/vendas/1/editar',
    method: 'GET',
    headers: { 'Cookie': authCookies }
  });
  if (resEditVenda.statusCode !== 200) {
    throw new Error(`Falha ao abrir /vendas/1/editar: HTTP ${resEditVenda.statusCode}`);
  }
  console.log("4. GET /vendas/1/editar -> HTTP 200 ✅ Formulário de edição de venda renderizado com sucesso");

  const csrfVendaMatch = resEditVenda.body.match(/name="_csrf" value="([^"]+)"/);
  const csrfVenda = csrfVendaMatch ? csrfVendaMatch[1] : '';

  const novoGtaVenda = "GTA-VND-" + Date.now().toString().slice(-4);
  const updateVendaData = [
    `_csrf=${encodeURIComponent(csrfVenda)}`,
    `numero_gta=${encodeURIComponent(novoGtaVenda)}`,
    `chave_nfe=35260998765432000109550010000056781000056789`,
    `comprador_destino=${encodeURIComponent('Frigorifico JBS Andradina')}`,
    `data_venda=2026-09-09`,
    `tipo_precificacao=arroba`,
    `preco_unitario=250.00`,
    `peso_total_kg=5400.0`,
    `valor_total=45000.00`,
    `descricao=${encodeURIComponent('Venda de Teste Atualizada')}`
  ].join('&');

  const resUpdVenda = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/vendas/1/atualizar',
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Content-Length': Buffer.byteLength(updateVendaData),
      'Cookie': authCookies
    }
  }, updateVendaData);

  console.log(`5. POST /vendas/1/atualizar -> HTTP ${resUpdVenda.statusCode} (Redirect: ${resUpdVenda.headers['location']}) ✅ Atualização efetuada`);

  const resCheckVenda = await request({
    hostname: 'localhost',
    port: 8080,
    path: '/vendas/1/editar',
    method: 'GET',
    headers: { 'Cookie': authCookies }
  });
  if (resCheckVenda.body.includes(novoGtaVenda)) {
    console.log(`6. Conferência de Persistência Venda: Encontrado GTA ${novoGtaVenda} ✅ OK`);
  } else {
    throw new Error("GTA de venda atualizado não encontrado na tela de edição!");
  }

  console.log("=== TESTE DE EDIÇÃO E ATUALIZAÇÃO CONCLUÍDO COM 100% DE SUCESSO ===");
}

run().catch(err => {
  console.error("ERRO NO TESTE DE EDIÇÃO:", err);
  process.exit(1);
});
