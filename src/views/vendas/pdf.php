<?php
/**
 * Template do Comprovante Oficial de Venda de Gado (A4 / PDF)
 */

$pesoTotal = (float)($v['peso_total_kg'] ?? 0);
$qtdCabecas = max(1, (int)($v['quantidade_cabecas'] ?? 1));
$valorTotal = (float)($v['valor_total'] ?? 0);

$totalArrobas = kgParaArroba($pesoTotal);
$pesoMedioCab = $pesoTotal > 0 ? ($pesoTotal / $qtdCabecas) : 0;
$arrobaMediaCab = $totalArrobas > 0 ? ($totalArrobas / $qtdCabecas) : 0;
$precoMedioCab = $valorTotal > 0 ? ($valorTotal / $qtdCabecas) : 0;
$precoMedioArroba = $totalArrobas > 0 ? ($valorTotal / $totalArrobas) : 0;

// Análise de Rentabilidade do Lote (se animais possuírem dados de custo)
$custoTotalAquisicao = 0.0;
$animaisComCusto = 0;
foreach ($animais as $a) {
    if (!empty($a['valor_compra_individual'])) {
        $custoTotalAquisicao += (float)$a['valor_compra_individual'];
        $animaisComCusto++;
    }
}
$lucroEstimado = ($animaisComCusto > 0 && $valorTotal > $custoTotalAquisicao) ? ($valorTotal - $custoTotalAquisicao) : null;
$margemEstimada = ($custoTotalAquisicao > 0 && $lucroEstimado !== null) ? (($lucroEstimado / $custoTotalAquisicao) * 100) : null;
?>

<div class="doc-title-block">
  <div>
    <div class="doc-title-text">
      <i class="bi bi-cash-coin me-1"></i> Comprovante Oficial de Venda & Desinvestimento de Gado
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
      Guia de Trânsito Animal (GTA de Saída): <strong><?= e($v['numero_gta']) ?></strong>
    </div>
  </div>
  <div>
    <span class="doc-badge" style="background: #e8f5e9; color: var(--primary); border-color: #a5d6a7;">Baixa Homologada #<?= $v['id'] ?></span>
  </div>
</div>

<!-- Grid de Indicadores e Metadados da Venda -->
<div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
  <div class="info-box">
    <div class="info-label">Comprador / Destino</div>
    <div class="info-val"><?= e($v['comprador_destino']) ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Data da Venda / Abate</div>
    <div class="info-val"><?= formatDate($v['data_venda']) ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Volume Comercializado</div>
    <div class="info-val tabular-nums"><?= number_format($qtdCabecas, 0, ',', '.') ?> cabeças</div>
  </div>
  <div class="info-box">
    <div class="info-label">Modalidade de Preço</div>
    <div class="info-val">
      <?= $v['tipo_precificacao'] === 'arroba' ? 'Por Arroba (@)' : 'Por Cabeça' ?>
      <?php if (!empty($v['preco_unitario'])): ?>
        <span style="font-size: 10px; color: var(--text-muted);">(R$ <?= number_format($v['preco_unitario'], 2, ',', '.') ?>)</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
  <div class="info-box">
    <div class="info-label">Peso Total Líquido</div>
    <div class="info-val tabular-nums"><?= $pesoTotal > 0 ? number_format($pesoTotal, 1, ',', '.') . ' kg' : '—' ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Arrobas Totais (@)</div>
    <div class="info-val tabular-nums"><?= $totalArrobas > 0 ? number_format($totalArrobas, 2, ',', '.') . ' @' : '—' ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Peso Médio / Cabeça</div>
    <div class="info-val tabular-nums">
      <?= $pesoMedioCab > 0 ? number_format($pesoMedioCab, 1, ',', '.') . ' kg' : '—' ?>
      <?php if ($arrobaMediaCab > 0): ?>
        <span style="font-size: 10px; font-weight: 500; color: var(--text-muted);">(<?= number_format($arrobaMediaCab, 2, ',', '.') ?> @)</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="info-box" style="border-color: var(--primary); background: #f2f7f3;">
    <div class="info-label" style="color: var(--primary);">Faturamento Total</div>
    <div class="info-val tabular-nums" style="color: var(--primary); font-size: 14px;">
      R$ <?= number_format($valorTotal, 2, ',', '.') ?>
    </div>
  </div>
</div>

<div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="info-box">
    <div class="info-label">Preço Médio / Cabeça</div>
    <div class="info-val tabular-nums">R$ <?= number_format($precoMedioCab, 2, ',', '.') ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Preço Efetivo / Arroba (@)</div>
    <div class="info-val tabular-nums">
      <?= $precoMedioArroba > 0 ? 'R$ ' . number_format($precoMedioArroba, 2, ',', '.') . '/@' : '—' ?>
    </div>
  </div>
  <div class="info-box">
    <div class="info-label">Arquivo XML SEFAZ</div>
    <div class="info-val"><?= !empty($v['arquivo_xml']) ? 'Vinculado e Auditado' : 'Não anexado' ?></div>
  </div>
</div>

<?php if ($lucroEstimado !== null): ?>
  <div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="info-box">
      <div class="info-label">Custo Histórico Aquisição</div>
      <div class="info-val tabular-nums">R$ <?= number_format($custoTotalAquisicao, 2, ',', '.') ?></div>
    </div>
    <div class="info-box" style="background: #e8f5e9;">
      <div class="info-label" style="color: var(--primary);">Lucro Bruto Comercial</div>
      <div class="info-val tabular-nums" style="color: var(--primary); font-size: 14px;">
        + R$ <?= number_format($lucroEstimado, 2, ',', '.') ?>
      </div>
    </div>
    <div class="info-box">
      <div class="info-label">Margem de Retorno Comercial</div>
      <div class="info-val tabular-nums" style="color: var(--primary); font-weight: 700;">
        <?= number_format($margemEstimada, 1, ',', '.') ?>%
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Chave Fiscal da NF-e -->
<div style="background: #fafbfa; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; margin-bottom: 16px;">
  <div class="info-label">Chave de Acesso da Nota Fiscal Eletrônica (NF-e)</div>
  <div style="font-family: monospace; font-size: 12px; font-weight: 700; color: var(--text); letter-spacing: 0.8px;">
    <?= !empty($v['chave_nfe']) ? formatChaveNFe($v['chave_nfe']) : '<span style="color: var(--text-muted); font-weight: normal;">NF-e não informada ou não exigida</span>' ?>
  </div>
</div>

<?php if (!empty($v['descricao'])): ?>
  <div style="background: #fafbfa; border: 1px solid var(--border-light); border-radius: 4px; padding: 6px 10px; margin-bottom: 14px; font-size: 11px;">
    <strong>Observações da Operação:</strong> <?= nl2br(e($v['descricao'])) ?>
  </div>
<?php endif; ?>

<!-- Tabela de Animais Baixados do Rebanho -->
<div class="section-title">
  <i class="bi bi-box-arrow-up-right"></i> Romaneio de Animais Baixados do Rebanho (<?= count($animais) ?> cabeças)
</div>

<?php if (empty($animais)): ?>
  <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 16px; background: #fafbfa; padding: 12px; border: 1px dashed var(--border); border-radius: 4px;">
    Venda registrada como lote coletivo sem desmembramento de identificadores individuais.
  </div>
<?php else: ?>
  <table class="doc-table">
    <thead>
      <tr>
        <th style="width: 35px; text-align: center;">#</th>
        <th style="width: 105px;">Brinco Oficial</th>
        <th style="width: 80px;">Sexo</th>
        <th style="width: 110px;">Raça</th>
        <th style="width: 90px; text-align: right;">Peso Entrada</th>
        <th style="width: 90px; text-align: right;">Peso Venda</th>
        <th style="width: 85px; text-align: right;">Arrobas (@)</th>
        <th style="width: 95px; text-align: right;">Ganho (kg)</th>
        <th style="width: 105px; text-align: right;">Valor Venda (R$)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($animais as $idx => $a): 
        $pesoEntrada = (float)($a['peso_inicial'] ?? 0);
        $pesoSaida = (float)($a['peso_venda'] ?? 0);
        $ganho = ($pesoSaida > $pesoEntrada && $pesoEntrada > 0) ? ($pesoSaida - $pesoEntrada) : null;
      ?>
        <tr>
          <td style="text-align: center; color: var(--text-muted); font-size: 10px;"><?= $idx + 1 ?></td>
          <td style="font-weight: 700;"><?= e($a['brinco']) ?></td>
          <td><?= $a['sexo'] === 'F' ? 'Fêmea ♀' : 'Macho ♂' ?></td>
          <td><?= e($a['raca'] ?: 'Nelore') ?></td>
          <td class="tabular-nums" style="text-align: right;">
            <?= $pesoEntrada > 0 ? number_format($pesoEntrada, 1, ',', '.') . ' kg' : '—' ?>
          </td>
          <td class="tabular-nums" style="text-align: right; font-weight: 600;">
            <?= $pesoSaida > 0 ? number_format($pesoSaida, 1, ',', '.') . ' kg' : '—' ?>
          </td>
          <td class="tabular-nums" style="text-align: right;">
            <?= $pesoSaida > 0 ? number_format(kgParaArroba($pesoSaida), 2, ',', '.') . ' @' : '—' ?>
          </td>
          <td class="tabular-nums" style="text-align: right;">
            <?php if ($ganho !== null): ?>
              <span style="color: var(--primary); font-weight: 600;">+<?= number_format($ganho, 1, ',', '.') ?> kg</span>
            <?php else: ?>
              <span style="color: var(--text-muted);">—</span>
            <?php endif; ?>
          </td>
          <td class="tabular-nums" style="text-align: right; font-weight: 700; color: var(--primary);">
            <?= $a['valor_venda_individual'] ? number_format($a['valor_venda_individual'], 2, ',', '.') : '—' ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
