<?php
/**
 * Template do Laudo Sanitário & Manejo Clínico (A4 / PDF)
 */

$totalEventos = (int)($totais['total_eventos'] ?? 0);
$custoTotal = (float)($totais['custo_total'] ?? 0);
$animaisAtendidos = (int)($totais['animais_atendidos'] ?? 0);
$custoMedioEvento = $totalEventos > 0 ? ($custoTotal / $totalEventos) : 0;
?>

<div class="doc-title-block">
  <div>
    <div class="doc-title-text">
      <i class="bi bi-heart-pulse-fill me-1"></i> Laudo Sanitário & Histórico de Manejo Clínico
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
      Auditoria Zootécnica e Sanitária Consolidada em <?= date('d/m/Y H:i') ?>
    </div>
  </div>
  <div>
    <span class="doc-badge" style="background: #fdf2e9; color: #d35400; border-color: #f5cba7;">
      <?= number_format($totalEventos, 0, ',', '.') ?> eventos registrados
    </span>
  </div>
</div>

<?php if (!empty($filtrosAplicados)): ?>
  <!-- Banner Informativo de Filtros Granulares -->
  <div style="background: #fdfaf6; border: 1px solid #fae5d3; border-left: 4px solid #e67e22; border-radius: 4px; padding: 6px 12px; margin-bottom: 14px; font-size: 11px; display: flex; align-items: center; gap: 8px;">
    <i class="bi bi-funnel-fill text-warning" style="font-size: 13px;"></i>
    <div>
      <strong>Filtros Granulares Ativos:</strong>
      <span style="color: #d35400; font-weight: 600; margin-left: 4px;">
        <?= implode(' &nbsp;•&nbsp; ', array_map('htmlspecialchars', $filtrosAplicados)) ?>
      </span>
    </div>
  </div>
<?php endif; ?>

<!-- Seção 1: Resumo Executivo Sanitário -->
<div id="sec-resumo-saude" data-printable-section="Resumo Sanitário">
  <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="info-box">
      <div class="info-label">Ocorrências Clínicas</div>
      <div class="info-val tabular-nums"><?= number_format($totalEventos, 0, ',', '.') ?> procedimentos</div>
    </div>
    <div class="info-box">
      <div class="info-label">Animais Assistidos</div>
      <div class="info-val tabular-nums"><?= number_format($animaisAtendidos, 0, ',', '.') ?> cabeças</div>
    </div>
    <div class="info-box" style="border-color: var(--primary); background: #f2f7f3;">
      <div class="info-label" style="color: var(--primary);">Investimento em Fármacos</div>
      <div class="info-val tabular-nums" style="color: var(--primary); font-size: 14px;">
        R$ <?= number_format($custoTotal, 2, ',', '.') ?>
      </div>
    </div>
    <div class="info-box">
      <div class="info-label">Custo Médio / Manejo</div>
      <div class="info-val tabular-nums">R$ <?= number_format($custoMedioEvento, 2, ',', '.') ?></div>
    </div>
  </div>
</div>

<!-- Seção 2: Distribuição por Tipo de Manejo -->
<div id="sec-tipos-saude" data-printable-section="Distribuição por Tipo">
  <div class="section-title">
    <i class="bi bi-pie-chart-fill"></i> Distribuição por Tipo de Intervenção Sanitária
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th>Classificação do Manejo</th>
        <th style="width: 120px; text-align: right;">Ocorrências</th>
        <th style="width: 120px; text-align: right;">% do Total</th>
        <th style="width: 150px; text-align: right;">Custo Acumulado (R$)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($porTipo)): ?>
        <tr>
          <td colspan="4" style="text-align: center; color: var(--text-muted);">Nenhum registro sanitário computado para estes critérios.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($porTipo as $pt): 
          $qtd = (int)$pt['qtd'];
          $custo = (float)$pt['total_custo'];
          $pct = $totalEventos > 0 ? round(($qtd / $totalEventos) * 100, 1) : 0;
        ?>
          <tr>
            <td><strong><?= e($pt['tipo']) ?></strong></td>
            <td class="tabular-nums" style="text-align: right; font-weight: 600;"><?= number_format($qtd, 0, ',', '.') ?></td>
            <td class="tabular-nums" style="text-align: right; color: var(--text-muted);"><?= $pct ?>%</td>
            <td class="tabular-nums" style="text-align: right; font-weight: 700; color: var(--primary);">
              <?= $custo > 0 ? 'R$ ' . number_format($custo, 2, ',', '.') : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Seção 3: Log Cronológico de Eventos Sanitários -->
<div id="sec-log-saude" data-printable-section="Log de Procedimentos">
  <div class="section-title">
    <i class="bi bi-journal-medical"></i> Log Cronológico de Manejos e Aplicações Clínicas
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th style="width: 80px;">Data</th>
        <th style="width: 85px;">Brinco</th>
        <th style="width: 100px;">Tipo</th>
        <th>Descrição da Ocorrência</th>
        <th style="width: 140px;">Medicamento / Dose</th>
        <th style="width: 120px;">Veterinário</th>
        <th style="width: 85px; text-align: right;">Custo (R$)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($eventos)): ?>
        <tr>
          <td colspan="7" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 12px;">
            Nenhum histórico sanitário encontrado para os filtros selecionados.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($eventos as $ev): ?>
          <tr>
            <td><?= formatDate($ev['data']) ?></td>
            <td style="font-weight: 700;"><?= e($ev['brinco']) ?></td>
            <td><span class="doc-badge" style="font-size: 9px; padding: 2px 4px;"><?= strtoupper(e($ev['tipo'])) ?></span></td>
            <td><?= e($ev['descricao']) ?></td>
            <td><?= e($ev['medicamento'] ?: '—') ?> <?= !empty($ev['dose']) ? '(' . e($ev['dose']) . ')' : '' ?></td>
            <td><?= e($ev['veterinario'] ?: '—') ?></td>
            <td class="tabular-nums" style="text-align: right;">
              <?= $ev['custo'] ? number_format($ev['custo'], 2, ',', '.') : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
