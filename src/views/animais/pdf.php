<?php
/**
 * Template da Ficha Cadastral e Prontuário Individual do Animal (A4 / PDF)
 */

$pesoAtual = !empty($pesagens) ? end($pesagens)['peso'] : ($a['peso_inicial'] ?? null);

// Cálculo de Idade
$idadeTxt = 'Não informada';
if (!empty($a['data_nascimento'])) {
    try {
        $nasc = new DateTime($a['data_nascimento']);
        $hoje = new DateTime();
        $diff = $nasc->diff($hoje);
        if ($diff->y > 0) {
            $idadeTxt = "{$diff->y} ano(s)" . ($diff->m > 0 ? " e {$diff->m} mês(es)" : "");
        } else {
            $idadeTxt = "{$diff->m} mês(es) e {$diff->d} dia(s)";
        }
    } catch (Exception $ex) {
        $idadeTxt = 'Data inválida';
    }
}
?>

<div class="doc-title-block">
  <div>
    <div class="doc-title-text">
      <i class="bi bi-tag-fill me-1"></i> Ficha Cadastral & Prontuário Zootécnico
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
      Identificador Oficial: <strong><?= e($a['brinco']) ?></strong> <?= !empty($a['nome']) ? '— ' . e($a['nome']) : '' ?>
    </div>
  </div>
  <div>
    <span class="doc-badge">Status: <?= strtoupper(e($a['status'] ?? 'ativo')) ?></span>
  </div>
</div>

<!-- Bloco Principal: Foto & Dados Cadastrais -->
<div style="display: flex; gap: 18px; margin-bottom: 16px;">
  <!-- Coluna da Foto -->
  <div style="width: 140px; flex-shrink: 0; text-align: center;">
    <?php if (!empty($a['foto_url'])): ?>
      <img src="<?= e($a['foto_url']) ?>" alt="Foto Animal" style="width: 140px; height: 140px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border);">
    <?php else: ?>
      <div style="width: 140px; height: 140px; background: #eef3ef; border: 2px dashed var(--border); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
        <i class="bi bi-camera" style="font-size: 32px;"></i>
        <span style="font-size: 10px; margin-top: 4px;">Sem foto oficial</span>
      </div>
    <?php endif; ?>
    <div style="font-size: 11px; font-weight: 700; color: var(--primary); margin-top: 6px;">
      Brinco: <?= e($a['brinco']) ?>
    </div>
  </div>

  <!-- Coluna dos Dados -->
  <div style="flex-grow: 1;">
    <div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
      <div class="info-box">
        <div class="info-label">Nome / Apelido</div>
        <div class="info-val"><?= e($a['nome'] ?: '—') ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Sexo Biológico</div>
        <div class="info-val"><?= $a['sexo'] === 'F' ? 'Fêmea ♀' : 'Macho ♂' ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Raça</div>
        <div class="info-val"><?= e($a['raca'] ?: 'Não informada') ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Categoria Zootécnica</div>
        <div class="info-val"><?= e($a['categoria'] ?? (isFilhote($a['data_nascimento']) ? 'Bezerro' : 'Adulto')) ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Data Nascimento</div>
        <div class="info-val"><?= formatDate($a['data_nascimento']) ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Idade Calculada</div>
        <div class="info-val"><?= $idadeTxt ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Pasto / Lote Atual</div>
        <div class="info-val"><?= e($pastoNome) ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Peso Inicial</div>
        <div class="info-val tabular-nums"><?= $a['peso_inicial'] ? number_format($a['peso_inicial'], 1, ',', '.') . ' kg' : '—' ?></div>
      </div>
      <div class="info-box" style="border-color: var(--primary); background: #f2f7f3;">
        <div class="info-label" style="color: var(--primary);">Peso Atual Estimado</div>
        <div class="info-val tabular-nums" style="color: var(--primary); font-size: 14px;">
          <?= $pesoAtual ? number_format($pesoAtual, 1, ',', '.') . ' kg' : '—' ?>
          <?php if ($pesoAtual): ?>
            <span style="font-size: 10px; font-weight: 500; color: var(--text-muted);">(<?= pesoEmArrobas($pesoAtual) ?> @)</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Genealogia & Rastreabilidade -->
<div class="section-title">
  <i class="bi bi-diagram-3-fill"></i> Genealogia Biológica & Rastreabilidade de Origem
</div>
<div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="info-box">
    <div class="info-label">Mãe Biológica</div>
    <div class="info-val">
      <?php if ($mae): ?>
        <?= e($mae['brinco']) ?> <?= !empty($mae['nome']) ? '(' . e($mae['nome']) . ')' : '' ?>
      <?php else: ?>
        <span style="color: var(--text-muted); font-weight: 500;">Não vinculada</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="info-box">
    <div class="info-label">Pai / Touro Reprodutor</div>
    <div class="info-val">
      <?php if ($pai): ?>
        <?= e($pai['brinco']) ?> <?= !empty($pai['nome']) ? '(' . e($pai['nome']) . ')' : '' ?>
      <?php elseif (!empty($a['pai_brinco'])): ?>
        <?= e($a['pai_brinco']) ?>
      <?php else: ?>
        <span style="color: var(--text-muted); font-weight: 500;">Não informado</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="info-box">
    <div class="info-label">Origem & Vínculo Fiscal</div>
    <div class="info-val">
      <?php if ($compra): ?>
        Lote Compra (GTA: <?= e($compra['numero_gta']) ?>)
      <?php else: ?>
        <?= !empty($a['origem']) ? ucfirst(e($a['origem'])) : 'Nascido na Fazenda' ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($a['observacao'])): ?>
  <div style="background: #fafbfa; border: 1px solid var(--border-light); border-radius: 4px; padding: 6px 10px; margin-bottom: 12px; font-size: 11px;">
    <strong>Observações Gerais:</strong> <?= nl2br(e($a['observacao'])) ?>
  </div>
<?php endif; ?>

<!-- Histórico de Pesagens & GMD -->
<div class="section-title">
  <i class="bi bi-speedometer2"></i> Histórico Biométrico & Ganho Médio Diário (GMD)
</div>
<?php if (empty($pesagens)): ?>
  <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 14px;">
    Nenhuma pesagem individual registrada além do peso inicial.
  </div>
<?php else: ?>
  <table class="doc-table">
    <thead>
      <tr>
        <th style="width: 100px;">Data</th>
        <th style="width: 100px;">Peso (kg)</th>
        <th style="width: 80px;">Arrobas (@)</th>
        <th style="width: 110px;">Ganho Período</th>
        <th style="width: 90px;">Intervalo</th>
        <th style="width: 110px;">GMD (kg/dia)</th>
        <th style="width: 80px;">Origem</th>
        <th>Observações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pesagens as $p): ?>
        <tr>
          <td><?= formatDate($p['data']) ?></td>
          <td class="tabular-nums" style="font-weight: 700;"><?= number_format($p['peso'], 1, ',', '.') ?> kg</td>
          <td class="tabular-nums"><?= pesoEmArrobas($p['peso']) ?> @</td>
          <td class="tabular-nums">
            <?php if ($p['ganho'] !== null): ?>
              <span style="color: <?= $p['ganho'] >= 0 ? 'var(--primary)' : '#c0392b' ?>; font-weight: 600;">
                <?= $p['ganho'] >= 0 ? '+' : '' ?><?= number_format($p['ganho'], 1, ',', '.') ?> kg
              </span>
            <?php else: ?>
              <span style="color: var(--text-muted); font-size: 11px;">1ª Medição</span>
            <?php endif; ?>
          </td>
          <td><?= $p['dias'] ? $p['dias'] . ' dias' : '—' ?></td>
          <td class="tabular-nums">
            <?php if ($p['gmd'] !== null): ?>
              <strong style="color: <?= $p['gmd'] >= 0.5 ? 'var(--primary)' : '#d35400' ?>;">
                <?= number_format($p['gmd'], 3, ',', '.') ?> kg/d
              </strong>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><span class="badge" style="font-size: 10px; background: #eef3ef; color: var(--primary); padding: 2px 5px; border-radius: 3px;"><?= strtoupper(e($p['origem'])) ?></span></td>
          <td style="font-size: 11px; color: var(--text-muted);"><?= e($p['observacao'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<!-- Histórico Sanitário -->
<div class="section-title">
  <i class="bi bi-heart-pulse-fill"></i> Manejo Sanitário & Medicamentos Aplicados
</div>
<?php if (empty($saude)): ?>
  <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 14px;">
    Nenhum registro sanitário ou ocorrência clínica para este animal.
  </div>
<?php else: ?>
  <table class="doc-table">
    <thead>
      <tr>
        <th style="width: 95px;">Data</th>
        <th style="width: 110px;">Tipo de Manejo</th>
        <th>Descrição da Ocorrência</th>
        <th style="width: 140px;">Medicamento / Dose</th>
        <th style="width: 130px;">Veterinário</th>
        <th style="width: 90px; text-align: right;">Custo (R$)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($saude as $s): ?>
        <tr>
          <td><?= formatDate($s['data']) ?></td>
          <td><strong><?= e($s['tipo']) ?></strong></td>
          <td><?= e($s['descricao']) ?></td>
          <td><?= e($s['medicamento'] ?: '—') ?> <?= !empty($s['dose']) ? '(' . e($s['dose']) . ')' : '' ?></td>
          <td><?= e($s['veterinario'] ?: '—') ?></td>
          <td class="tabular-nums" style="text-align: right;">
            <?= $s['custo'] ? number_format($s['custo'], 2, ',', '.') : '—' ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<!-- Manejo Reprodutivo (se fêmea) -->
<?php if ($a['sexo'] === 'F'): ?>
  <div class="section-title">
    <i class="bi bi-gender-female"></i> Histórico Reprodutivo da Matriz
  </div>
  <?php if (empty($reproducao)): ?>
    <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 14px;">
      Nenhum evento reprodutivo registrado para esta matriz.
    </div>
  <?php else: ?>
    <table class="doc-table">
      <thead>
        <tr>
          <th style="width: 95px;">Data</th>
          <th style="width: 120px;">Tipo de Evento</th>
          <th style="width: 130px;">Touro / Sêmen</th>
          <th style="width: 110px;">Resultado</th>
          <th>Observações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reproducao as $r): ?>
          <tr>
            <td><?= formatDate($r['data']) ?></td>
            <td><strong><?= e($r['tipo']) ?></strong></td>
            <td><?= e($r['touro_brinco'] ?: '—') ?></td>
            <td>
              <span style="font-weight: 700; color: <?= in_array(strtolower($r['resultado'] ?? ''), ['prenha', 'positivo']) ? 'var(--primary)' : 'inherit' ?>;">
                <?= e($r['resultado'] ?: '—') ?>
              </span>
            </td>
            <td><?= e($r['observacao'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>
