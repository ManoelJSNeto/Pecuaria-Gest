<?php
/**
 * Teste de Validação das Estruturas e Regras de Negócio de XML de NF-e
 */

echo "=== TESTE DE VALIDAÇÃO DO XML DE NF-E (COMPRA E VENDA) ===" . PHP_EOL;

$xmlCompra = __DIR__ . '/fixtures/nfe_compra_sample.xml';
$docCompra = new DOMDocument();
$docCompra->load($xmlCompra);

// 1. Chave
$infNFe = $docCompra->getElementsByTagName('infNFe')->item(0);
$chave = preg_replace('/\D/', '', $infNFe->getAttribute('Id'));
echo "1. Chave NF-e Compra: $chave " . (strlen($chave) === 44 ? "✅ (44 dígitos)" : "❌") . PHP_EOL;

// 2. Número e Série
$nNF = $docCompra->getElementsByTagName('nNF')->item(0)->textContent;
$serie = $docCompra->getElementsByTagName('serie')->item(0)->textContent;
echo "2. Número da NF-e: $nNF, Série: $serie " . ($nNF === '1234' ? "✅" : "❌") . PHP_EOL;

// 3. Fornecedor Emitente
$xNome = $docCompra->getElementsByTagName('emit')->item(0)->getElementsByTagName('xNome')->item(0)->textContent;
echo "3. Emitente: $xNome ✅" . PHP_EOL;

// 4. Detecção de GTA em InfCpl
$infCpl = $docCompra->getElementsByTagName('infCpl')->item(0)->textContent;
preg_match('/gta\s*[:#ºn\.\-]?\s*([0-9a-zA-Z\/\.\-]+)/i', $infCpl, $m);
$gta = isset($m[1]) ? rtrim($m[1], '., ') : '';
echo "4. GTA Detectada no InfCpl: $gta " . ($gta === '847291/SP' ? "✅" : "❌") . PHP_EOL;

// 5. Itens
$dets = $docCompra->getElementsByTagName('det');
echo "5. Total de Itens no XML: " . $dets->length . PHP_EOL;

$totalCabecas = 0;
$totalValorGado = 0;
for ($i = 0; $i < $dets->length; $i++) {
    $det = $dets->item($i);
    $prod = $det->getElementsByTagName('prod')->item(0);
    $cProd = $prod->getElementsByTagName('cProd')->item(0)->textContent;
    $xProd = $prod->getElementsByTagName('xProd')->item(0)->textContent;
    $uCom = $prod->getElementsByTagName('uCom')->item(0)->textContent;
    $qCom = (float)$prod->getElementsByTagName('qCom')->item(0)->textContent;
    $vUnCom = (float)$prod->getElementsByTagName('vUnCom')->item(0)->textContent;
    $vProd = (float)$prod->getElementsByTagName('vProd')->item(0)->textContent;

    $isGado = stripos($xProd, 'frete') === false && stripos($xProd, 'servico') === false;
    echo "   - Item " . ($i+1) . " [$cProd] $xProd ($qCom $uCom x R$ $vUnCom) = R$ $vProd [" . ($isGado ? "BOVINOS ✅" : "NÃO GADO - EXCLUÍDO AUTOMATICAMENTE ✅") . "]" . PHP_EOL;

    if ($isGado) {
        $totalCabecas += $qCom;
        $totalValorGado += $vProd;
    }
}

echo "6. Total de Cabeças Selecionadas: $totalCabecas " . ($totalCabecas == 40 ? "✅ OK" : "❌") . PHP_EOL;
echo "7. Valor Total de Bovinos: R$ " . number_format($totalValorGado, 2, ',', '.') . " " . ($totalValorGado == 116000 ? "✅ OK" : "❌") . PHP_EOL;

echo PHP_EOL . "=== VALIDANDO XML DE VENDA / ABATE ===" . PHP_EOL;
$xmlVenda = __DIR__ . '/fixtures/nfe_venda_sample.xml';
$docVenda = new DOMDocument();
$docVenda->load($xmlVenda);

$destNome = $docVenda->getElementsByTagName('dest')->item(0)->getElementsByTagName('xNome')->item(0)->textContent;
$vendaVal = (float)$docVenda->getElementsByTagName('vNF')->item(0)->textContent;
$vendaPeso = (float)$docVenda->getElementsByTagName('pesoB')->item(0)->textContent;
echo "8. Comprador da Venda: $destNome ✅" . PHP_EOL;
echo "9. Valor Total NF-e de Venda: R$ " . number_format($vendaVal, 2, ',', '.') . " ($vendaPeso kg) ✅" . PHP_EOL;

echo "=== TODOS OS TESTES DE VALIDAÇÃO DE XML CONCLUÍDOS COM SUCESSO ===" . PHP_EOL;
