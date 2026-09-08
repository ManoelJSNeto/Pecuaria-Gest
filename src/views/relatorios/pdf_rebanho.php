<?php
/**
 * Template do Inventário Geral do Rebanho & Lotação (A4 / PDF)
 */

$totalAtivos = (int)($totais['ativos'] ?? 0);
$totalMachos = (int)($totais['machos'] ?? 0);
$totalFemeas = (int)($totais['femeas'] ?? 0);
$pesoMedio = (float)($totais['peso_medio'] ?? 0);

$capacidadeTotal = 0;
$alocadosTotal = 0;
$areaTotalHa = 0.0;
foreach ($pastagens as $pst) {
    $capacidadeTotal += (int)($pst['capacidade'] ?? 0);
    $alocadosTotal += (int)($pst['total_alocados'] ?? 0);
    $areaTotalHa += (float)($pst['area_ha'] ?? 0);
}
$ocupacaoGeralPct = $capacidadeTotal > 0 ? round(($alocadosTotal / $capacidadeTotal) * 100, 1) : 0;
$lotacaoMediaHa = $areaTotalHa > 0 ? round($alocadosTotal / $areaTotalHa, 2) : 0;
?>

<div class="doc-title-block">
  <div>
    <div class="doc-title-text">
      <i class="bi bi-clipboard2-data-fill me-1"></i> Inventário do Rebanho & Balanço de Pastagens
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
      Posição Consolidada da Propriedade em <?= date('d/m/Y H:i') ?>
    </div>
  </div>
  <div>
    <span class="doc-badge">Listados: <?= number_format(count($animais), 0, ',', '.') ?> cab</span>
  </div>
</div>

<?php if (!empty($filtrosAplicados)): ?>
  <!-- Banner Informativo de Filtros Granulares -->
  <div style="background: #f0f7f2; border: 1px solid var(--border); border-left: 4px solid var(--primary); border-radius: 4px; padding: 6px 12px; margin-bottom: 14px; font-size: 11px; display: flex; align-items: center; gap: 8px;">
    <i class="bi bi-funnel-fill text-success" style="font-size: 13px;"></i>
    <div>
      <strong>Filtros Granulares Ativos:</strong>
      <span style="color: var(--primary); font-weight: 600; margin-left: 4px;">
        <?= implode(' &nbsp;•&nbsp; ', array_map('htmlspecialchars', $filtrosAplicados)) ?>
      </span>
    </div>
  </div>
<?php endif; ?>

<!-- Seção 1: Resumo Executivo & Indicadores -->
<div id="sec-resumo-rebanho" data-printable-section="Resumo & Indicadores">
  <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="info-box" style="border-color: var(--primary); background: #f2f7f3;">
      <div class="info-label" style="color: var(--primary);">Efetivo Ativo</div>
      <div class="info-val tabular-nums" style="color: var(--primary); font-size: 14px;">
        <?= number_format($totalAtivos, 0, ',', '.') ?> cabeças
      </div>
    </div>
    <div class="info-box">
      <div class="info-label">Distribuição por Sexo</div>
      <div class="info-val tabular-nums">
        ♂ <?= $totalMachos ?> machos | ♀ <?= $totalFemeas ?> fêmeas
      </div>
    </div>
    <div class="info-box">
      <div class="info-label">Peso Médio Estimado</div>
      <div class="info-val tabular-nums">
        <?= $pesoMedio > 0 ? number_format($pesoMedio, 1, ',', '.') . ' kg' : '—' ?>
        <?php if ($pesoMedio > 0): ?>
          <span style="font-size: 10px; font-weight: 500; color: var(--text-muted);">(<?= number_format(kgParaArroba($pesoMedio), 2, ',', '.') ?> @)</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="info-box">
      <div class="info-label">Taxa Lotação Global</div>
      <div class="info-val tabular-nums">
        <?= $lotacaoMediaHa > 0 ? number_format($lotacaoMediaHa, 2, ',', '.') . ' cab/ha' : '—' ?>
      </div>
    </div>
  </div>

  <div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="info-box">
      <div class="info-label">Capacidade dos Pastos</div>
      <div class="info-val tabular-nums"><?= $capacidadeTotal > 0 ? number_format($capacidadeTotal, 0, ',', '.') . ' cabeças' : 'Não definida' ?></div>
    </div>
    <div class="info-box">
      <div class="info-label">Ocupação Atual dos Piquetes</div>
      <div class="info-val tabular-nums" style="color: <?= $ocupacaoGeralPct > 90 ? '#c0392b' : 'inherit' ?>;">
        <?= $ocupacaoGeralPct ?>% da capacidade
      </div>
    </div>
    <div class="info-box">
      <div class="info-label">Área Pastoril Mapeada</div>
      <div class="info-val tabular-nums"><?= $areaTotalHa > 0 ? number_format($areaTotalHa, 1, ',', '.') . ' hectares' : '—' ?></div>
    </div>
  </div>
</div>

<!-- Seção 2: Ocupação das Pastagens -->
<div id="sec-pastagens-rebanho" data-printable-section="Balanço de Pastagens">
  <div class="section-title">
    <i class="bi bi-tree-fill"></i> Balanço de Lotação e Alocação em Pastagens
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th>Nome do Pasto / Piquete</th>
        <th style="width: 80px; text-align: center;">Status</th>
        <th style="width: 90px; text-align: right;">Área (ha)</th>
        <th style="width: 100px; text-align: right;">Capacidade</th>
        <th style="width: 100px; text-align: right;">Lotação Atual</th>
        <th style="width: 90px; text-align: right;">Ocupação</th>
        <th style="width: 100px; text-align: right;">Densidade</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pastagens)): ?>
        <tr>
          <td colspan="7" style="text-align: center; color: var(--text-muted);">Nenhuma pastagem cadastrada.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($pastagens as $pst): 
          $cap = (int)($pst['capacidade'] ?? 0);
          $aloc = (int)($pst['total_alocados'] ?? 0);
          $ha = (float)($pst['area_ha'] ?? 0);
          $pct = $cap > 0 ? round(($aloc / $cap) * 100) : 0;
          $dens = $ha > 0 ? round($aloc / $ha, 2) : 0;
        ?>
          <tr>
            <td><strong><?= e($pst['nome']) ?></strong></td>
            <td style="text-align: center;">
              <span class="doc-badge" style="font-size: 9px; padding: 2px 5px;"><?= strtoupper(e($pst['status'] ?? 'ativo')) ?></span>
            </td>
            <td class="tabular-nums" style="text-align: right;"><?= $ha > 0 ? number_format($ha, 1, ',', '.') : '—' ?></td>
            <td class="tabular-nums" style="text-align: right;"><?= $cap > 0 ? number_format($cap, 0, ',', '.') : '—' ?></td>
            <td class="tabular-nums" style="text-align: right; font-weight: 700;"><?= number_format($aloc, 0, ',', '.') ?> cab</td>
            <td class="tabular-nums" style="text-align: right; font-weight: 600; color: <?= $pct > 95 ? '#c0392b' : 'inherit' ?>;">
              <?= $cap > 0 ? $pct . '%' : '—' ?>
            </td>
            <td class="tabular-nums" style="text-align: right; color: var(--text-muted);">
              <?= $dens > 0 ? number_format($dens, 2, ',', '.') . ' cab/ha' : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Seção 3: Romaneio de Animais Ativos -->
<div id="sec-animais-rebanho" data-printable-section="Romaneio Individual" <?= !empty($semAnimais) ? 'data-default-hidden="1"' : '' ?>>
  <div class="section-title">
    <i class="bi bi-tag-fill"></i> Romaneio Individual de Animais (<?= count($animais) ?> listados)
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th style="width: 35px; text-align: center;">#</th>
        <th style="width: 100px;">Brinco</th>
        <th>Nome / Apelido</th>
        <th style="width: 70px;">Sexo</th>
        <th style="width: 90px;">Raça</th>
        <th style="width: 110px;">Pasto Atual</th>
        <th style="width: 85px;">Idade</th>
        <th style="width: 95px; text-align: right;">Peso Atual</th>
        <th style="width: 80px; text-align: right;">Arrobas (@)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($animais)): ?>
        <tr>
          <td colspan="9" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 12px;">
            Nenhum animal localizado para os filtros selecionados.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($animais as $idx => $a): 
          $peso = (float)($a['peso_atual'] ?? 0);
          $idade = calcIdade($a['data_nascimento']);
        ?>
          <tr>
            <td style="text-align: center; color: var(--text-muted); font-size: 10px;"><?= $idx + 1 ?></td>
            <td style="font-weight: 700;">
              <a href="/animais/<?= $a['id'] ?>/pdf" target="_blank" style="text-decoration: none; color: inherit;" title="Abrir Ficha Individual deste Animal"><?= e($a['brinco']) ?></a>
              <a href="/animais/<?= $a['id'] ?>/pdf" target="_blank" class="no-print doc-badge" style="font-size: 8px; padding: 1px 4px; margin-left: 4px; text-decoration: none; vertical-align: middle;" title="Abrir Ficha Individual em PDF"><i class="bi bi-file-earmark-pdf" style="color: #c0392b;"></i> PDF</a>
            </td>
            <td><?= e($a['nome'] ?: '—') ?></td>
            <td><?= $a['sexo'] === 'F' ? 'Fêmea ♀' : 'Macho ♂' ?></td>
            <td><?= e($a['raca'] ?: 'Nelore') ?></td>
            <td><?= e($a['pasto_nome'] ?? 'Sem pasto') ?></td>
            <td style="font-size: 11px; color: var(--text-muted);"><?= $idade ?></td>
            <td class="tabular-nums" style="text-align: right; font-weight: 600;">
              <?= $peso > 0 ? number_format($peso, 1, ',', '.') . ' kg' : '—' ?>
            </td>
            <td class="tabular-nums" style="text-align: right; color: var(--primary); font-weight: 700;">
              <?= $peso > 0 ? number_format(kgParaArroba($peso), 2, ',', '.') . ' @' : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
