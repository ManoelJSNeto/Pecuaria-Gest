<?php
/**
 * Template do Espelho de Compra de Gado e Lote de Entrada (A4 / PDF)
 */

$pesoTotal = (float)($c['peso_total_kg'] ?? 0);
$qtdCabecas = max(1, (int)($c['quantidade_cabecas'] ?? 1));
$valorTotal = (float)($c['valor_total'] ?? 0);

$totalArrobas = kgParaArroba($pesoTotal);
$pesoMedioCab = $pesoTotal > 0 ? ($pesoTotal / $qtdCabecas) : 0;
$arrobaMediaCab = $totalArrobas > 0 ? ($totalArrobas / $qtdCabecas) : 0;
$custoMedioCab = $valorTotal > 0 ? ($valorTotal / $qtdCabecas) : 0;
$custoArroba = $totalArrobas > 0 ? ($valorTotal / $totalArrobas) : 0;
?>

<div class="doc-title-block">
  <div>
    <div class="doc-title-text">
      <i class="bi bi-cart-check-fill me-1"></i> Espelho de Compra & Lote de Entrada de Gado
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
      Guia de Trânsito Animal (GTA): <strong><?= e($c['numero_gta']) ?></strong>
    </div>
  </div>
  <div>
    <span class="doc-badge">Lote Homologado #<?= $c['id'] ?></span>
  </div>
</div>

<!-- Grid de Indicadores e Metadados do Lote -->
<div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
  <div class="info-box">
    <div class="info-label">Fornecedor / Origem</div>
    <div class="info-val"><?= e($c['fornecedor_origem'] ?: 'Não especificado') ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Data da Transação</div>
    <div class="info-val"><?= formatDate($c['data_compra']) ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Volume do Lote</div>
    <div class="info-val tabular-nums"><?= number_format($qtdCabecas, 0, ',', '.') ?> cabeças</div>
  </div>
  <div class="info-box">
    <div class="info-label">Pasto de Alojamento</div>
    <div class="info-val"><?= e($pasto['nome'] ?? 'Não alocado') ?></div>
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
    <div class="info-label" style="color: var(--primary);">Investimento Total</div>
    <div class="info-val tabular-nums" style="color: var(--primary); font-size: 14px;">
      R$ <?= number_format($valorTotal, 2, ',', '.') ?>
    </div>
  </div>
</div>

<div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="info-box">
    <div class="info-label">Custo Médio / Cabeça</div>
    <div class="info-val tabular-nums">R$ <?= number_format($custoMedioCab, 2, ',', '.') ?></div>
  </div>
  <div class="info-box">
    <div class="info-label">Custo da Arroba (@)</div>
    <div class="info-val tabular-nums">
      <?= $custoArroba > 0 ? 'R$ ' . number_format($custoArroba, 2, ',', '.') . '/@' : '—' ?>
    </div>
  </div>
  <div class="info-box">
    <div class="info-label">Arquivo XML SEFAZ</div>
    <div class="info-val"><?= !empty($c['arquivo_xml']) ? 'Vinculado e Auditado' : 'Não anexado' ?></div>
  </div>
</div>

<!-- Chave Fiscal da NF-e -->
<div style="background: #fafbfa; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; margin-bottom: 16px;">
  <div class="info-label">Chave de Acesso da Nota Fiscal Eletrônica (NF-e)</div>
  <div style="font-family: monospace; font-size: 12px; font-weight: 700; color: var(--text); letter-spacing: 0.8px;">
    <?= !empty($c['chave_nfe']) ? formatChaveNFe($c['chave_nfe']) : '<span style="color: var(--text-muted); font-weight: normal;">NF-e não informada ou não exigida</span>' ?>
  </div>
</div>

<?php if (!empty($c['descricao'])): ?>
  <div style="background: #fafbfa; border: 1px solid var(--border-light); border-radius: 4px; padding: 6px 10px; margin-bottom: 14px; font-size: 11px;">
    <strong>Observações da Operação:</strong> <?= nl2br(e($c['descricao'])) ?>
  </div>
<?php endif; ?>

<!-- Tabela de Animais Vinculados ao Lote -->
<div class="section-title">
  <i class="bi bi-list-check"></i> Romaneio de Animais Ingressados no Rebanho (<?= count($animais) ?> cadastrados individualmente)
</div>

<?php if (empty($animais)): ?>
  <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 16px; background: #fafbfa; padding: 12px; border: 1px dashed var(--border); border-radius: 4px;">
    Este lote foi registrado como compra coletiva sem o desmembramento automático de brincos individuais no rebanho.
  </div>
<?php else: ?>
  <table class="doc-table">
    <thead>
      <tr>
        <th style="width: 35px; text-align: center;">#</th>
        <th style="width: 100px;">Brinco Oficial</th>
        <th>Nome / Identificação</th>
        <th style="width: 80px;">Sexo</th>
        <th style="width: 100px;">Raça</th>
        <th style="width: 90px; text-align: right;">Peso Entrada</th>
        <th style="width: 80px; text-align: right;">Arrobas (@)</th>
        <th style="width: 100px; text-align: right;">Custo Indiv. (R$)</th>
        <th style="width: 80px; text-align: center;">Status Atual</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($animais as $idx => $a): ?>
        <tr>
          <td style="text-align: center; color: var(--text-muted); font-size: 10px;"><?= $idx + 1 ?></td>
          <td style="font-weight: 700;"><?= e($a['brinco']) ?></td>
          <td><?= e($a['nome'] ?: '—') ?></td>
          <td><?= $a['sexo'] === 'F' ? 'Fêmea ♀' : 'Macho ♂' ?></td>
          <td><?= e($a['raca'] ?: 'Nelore') ?></td>
          <td class="tabular-nums" style="text-align: right;">
            <?= $a['peso_inicial'] ? number_format($a['peso_inicial'], 1, ',', '.') . ' kg' : '—' ?>
          </td>
          <td class="tabular-nums" style="text-align: right;">
            <?= $a['peso_inicial'] ? number_format(kgParaArroba($a['peso_inicial']), 2, ',', '.') . ' @' : '—' ?>
          </td>
          <td class="tabular-nums" style="text-align: right; font-weight: 600;">
            <?= $a['valor_compra_individual'] ? number_format($a['valor_compra_individual'], 2, ',', '.') : '—' ?>
          </td>
          <td style="text-align: center;">
            <span class="doc-badge" style="font-size: 9px; padding: 2px 5px;"><?= strtoupper(e($a['status'])) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
