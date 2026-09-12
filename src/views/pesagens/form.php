<?php
$animaisStmt = $db->query("
    SELECT a.id, a.brinco, a.nome,
           COALESCE(
             (SELECT peso FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
             a.peso_inicial
           ) as ultimo_peso,
           COALESCE(
             (SELECT data::text FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
             a.created_at::text
           ) as ultima_data
    FROM animais a
    WHERE a.status NOT IN ('morto','vendido')
    ORDER BY a.brinco
");
$animais   = $animaisStmt->fetchAll();
$isEdit    = isset($pesagem);
$p         = $pesagem ?? [];
$preAnimal = $p['animal_id'] ?? ($_GET['animal_id'] ?? null);
?>
<!-- Topo com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/pesagens' ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Pesagens &gt; <?= $isEdit ? 'Editar Pesagem' : 'Anotar na Balança' ?>
    </span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaPesagem()">
    <i class="bi bi-info-circle"></i> <span>Instruções de Pesagem</span>
  </button>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7 col-xl-6">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-rulers text-primary"></i> <?= $isEdit ? 'Editar Registro de Pesagem' : 'Lançar Pesagem de Manejo' ?></h6>
        <span class="text-muted small">Acompanhamento de GMD e conversão @</span>
      </div>

      <div class="aws-container-body">
        <form method="POST" action="<?= $isEdit ? '/pesagens/'.$p['id'].'/atualizar' : '/pesagens/salvar' ?>" id="formPesagem">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- 1. Animal -->
            <div class="col-12">
              <label class="form-label fw-bold">Animal (Brinco) *</label>
              <select name="animal_id" id="selectAnimal" class="form-select" required>
                <option value="">— Selecione o brinco do animal —</option>
                <?php foreach ($animais as $a): ?>
                  <option value="<?= $a['id'] ?>"
                          data-peso="<?= (float)($a['ultimo_peso'] ?? 0) ?>"
                          data-data="<?= !empty($a['ultima_data']) ? formatDate($a['ultima_data']) : 'Cadastro' ?>"
                          <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?> <?= !empty($a['ultimo_peso']) ? ' (Último: '.number_format($a['ultimo_peso'], 1, ',', '.').' kg)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <!-- Callout sutil com último peso -->
              <div id="histCallout" class="aws-form-hint text-success d-none">
                <i class="bi bi-clock-history me-1"></i>
                <span id="histCalloutText">—</span>
              </div>
            </div>

            <!-- 2. Peso Balança -->
            <div class="col-12">
              <label class="form-label fw-bold">Peso Atual na Balança (kg) *</label>
              <div class="input-group">
                <input type="number" step="0.01" name="peso" id="inputPeso" class="form-control fw-600" required
                       placeholder="Ex: 420.50" min="1" max="2000"
                       value="<?= e($p['peso'] ?? '') ?>">
                <span class="input-group-text">kg</span>
              </div>

              <!-- Indicador de Arrobas e Variação em Linha (AWS Metric Callout) -->
              <div id="metricCallout" class="d-none mt-2">
                <div class="aws-metric-callout">
                  <i class="bi bi-calculator"></i>
                  <span>Equivale a: <strong id="valArroba">0,00</strong> @ carcaça (50%)</span>
                  <span id="sepGain" class="text-muted">•</span>
                  <span id="valGanho" class="fw-bold">0,00 kg</span>
                </div>
              </div>
              <div class="aws-form-hint">Digite o peso indicado no visor da balança eletrônica ou mecânica.</div>
            </div>

            <!-- 3. Data da Pesagem -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Data da Pesagem *</label>
              <input type="date" name="data" class="form-control" required
                     value="<?= e($p['data'] ?? date('Y-m-d')) ?>">
            </div>

            <!-- 4. Observação -->
            <div class="col-md-6">
              <label class="form-label">Lote / Motivo (Opcional)</label>
              <input type="text" name="observacao" class="form-control" placeholder="Ex: Pesagem periódica, troca de pasto" value="<?= e($p['observacao'] ?? '') ?>">
            </div>
          </div>

          <div class="aws-wizard-actions">
            <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/pesagens' ?>" class="aws-btn-secondary">Cancelar</a>
            <button type="submit" class="aws-btn-primary">
              <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Gravar Pesagem' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const selectAnimal = document.getElementById('selectAnimal');
  const inputPeso = document.getElementById('inputPeso');
  const histCallout = document.getElementById('histCallout');
  const histCalloutText = document.getElementById('histCalloutText');
  const metricCallout = document.getElementById('metricCallout');
  const valArroba = document.getElementById('valArroba');
  const valGanho = document.getElementById('valGanho');
  const sepGain = document.getElementById('sepGain');

  function atualizarCalculos() {
    const selectedOpt = selectAnimal.options[selectAnimal.selectedIndex];
    const ultimoPeso = selectedOpt ? parseFloat(selectedOpt.dataset.peso || 0) : 0;
    const ultimaData = selectedOpt ? (selectedOpt.dataset.data || '') : '';
    const pesoDigitado = parseFloat(inputPeso.value || 0);

    if (selectedOpt && selectAnimal.value && ultimoPeso > 0) {
      histCallout.classList.remove('d-none');
      histCalloutText.textContent = `Último registro em ${ultimaData}: ${ultimoPeso.toFixed(1)} kg (${(ultimoPeso*0.5/15).toFixed(2)} @)`;
    } else {
      histCallout.classList.add('d-none');
    }

    if (pesoDigitado > 0) {
      metricCallout.classList.remove('d-none');
      const arr = (pesoDigitado * 0.5 / 15).toFixed(2).replace('.', ',');
      valArroba.textContent = arr;

      if (ultimoPeso > 0) {
        sepGain.classList.remove('d-none');
        valGanho.classList.remove('d-none');
        const diff = pesoDigitado - ultimoPeso;
        if (diff > 0) {
          valGanho.className = 'fw-bold text-success';
          valGanho.textContent = `Ganho de +${diff.toFixed(1)} kg`;
        } else if (diff < 0) {
          valGanho.className = 'fw-bold text-warning';
          valGanho.textContent = `Variação de ${diff.toFixed(1)} kg`;
        } else {
          valGanho.className = 'fw-bold text-muted';
          valGanho.textContent = 'Manteve o mesmo peso';
        }
      } else {
        sepGain.classList.add('d-none');
        valGanho.classList.add('d-none');
      }
    } else {
      metricCallout.classList.add('d-none');
    }
  }

  selectAnimal.addEventListener('change', atualizarCalculos);
  inputPeso.addEventListener('input', atualizarCalculos);
  atualizarCalculos();
});

window.abrirAjudaPesagem = function() {
  const title = 'Guia: Pesagem e Acompanhamento de Ganho';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-rulers"></i> Como funciona a Pesagem</h7>
      <p>Registrar o peso permite acompanhar o Ganho Médio Diário (GMD) e saber o momento ideal para abate, desmame ou troca de piquete.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-calculator"></i> Conversão em Arrobas (@)</h7>
      <p>No mercado de gado de corte, os negócios são cotados em <strong>Arrobas (@) de carcaça</strong>. A regra prática comercial considera:</p>
      <ul class="small ps-3 mb-2">
        <li><strong>Rendimento estimado:</strong> 50% de peso de carcaça limpa.</li>
        <li><strong>Fórmula:</strong> <code>(Peso Vivo × 0,50) ÷ 15</code></li>
        <li><strong>Exemplo:</strong> Um boi de 450 kg vivo possui 225 kg de carcaça, o que equivale exatamente a <strong>15 @</strong>.</li>
      </ul>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-check-circle"></i> Dica de Manejo na Balança</h7>
      <div class="aws-help-tip-box">
        Procure pesar o lote sempre no mesmo horário (de preferência pela manhã, em jejum hídrico/sólido moderado) para evitar distorções por ingestão de água.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaPesagem;
});
</script>
