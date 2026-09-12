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
<div class="mb-3 d-flex align-items-center justify-content-between">
  <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/pesagens' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
  <span class="text-muted small"><i class="bi bi-shield-check text-success me-1"></i>Manejo seguro • Gravação instantânea</span>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card shadow-sm border">
      <div class="card-header bg-white py-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <div class="action-icon-box icon-amber" style="width:40px; height:40px; font-size:1.2rem;">
            <i class="bi bi-rulers"></i>
          </div>
          <div>
            <h6 class="mb-0 fw-bold" style="font-size:1.05rem;"><?= $isEdit ? 'Editar Pesagem' : 'Anotar Nova Pesagem na Balança' ?></h6>
            <small class="text-muted">Acompanhamento de ganho de peso e conversão em arrobas (@)</small>
          </div>
        </div>
      </div>

      <div class="card-body p-4">
        <!-- Banner de Ajuda Amigável -->
        <div class="flow-helper-banner mb-4">
          <i class="bi bi-info-circle-fill text-success fs-5"></i>
          <div>
            <strong>Passo Simples:</strong> Selecione o brinco do animal, confira o histórico anterior e digite o peso que marcou na balança.
          </div>
        </div>

        <form method="POST" action="<?= $isEdit ? '/pesagens/'.$p['id'].'/atualizar' : '/pesagens/salvar' ?>" id="formPesagem">
          <?= csrf_field() ?>

          <!-- 1. Seleção do Animal -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
              <i class="bi bi-tag-fill text-success me-1"></i> Qual é o animal? *
            </label>
            <select name="animal_id" id="selectAnimal" class="form-select form-select-lg" required style="font-size:1rem; padding:0.65rem 1rem;">
              <option value="">— Clique aqui para escolher o brinco —</option>
              <?php foreach ($animais as $a): ?>
                <option value="<?= $a['id'] ?>"
                        data-peso="<?= (float)($a['ultimo_peso'] ?? 0) ?>"
                        data-data="<?= !empty($a['ultima_data']) ? formatDate($a['ultima_data']) : 'Cadastro' ?>"
                        <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                  <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?> <?= !empty($a['ultimo_peso']) ? ' (Último: '.number_format($a['ultimo_peso'], 1, ',', '.').' kg)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Card de Histórico Anterior do Animal (Aparece ao selecionar) -->
          <div id="cardHistorico" class="p-3 mb-3 rounded border bg-light d-none" style="border-left: 4px solid var(--earth-green-600) !important;">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted small text-uppercase fw-bold" style="font-size:0.75rem;">Último Registro Conhecido:</span>
                <div class="fw-bold text-dark" style="font-size:1.05rem;" id="histPesoTexto">—</div>
              </div>
              <div class="text-end">
                <span class="badge bg-secondary text-white" id="histDataTexto">—</span>
              </div>
            </div>
          </div>

          <!-- 2. Peso na Balança e Conversão em Arroba -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
              <i class="bi bi-speedometer text-primary me-1"></i> Peso Atual da Balança (kg) *
            </label>
            <div class="input-group input-group-lg">
              <input type="number" step="0.01" name="peso" id="inputPeso" class="form-control fw-bold" required
                     placeholder="Ex: 420.50" min="1" max="2000"
                     value="<?= e($p['peso'] ?? '') ?>"
                     style="font-size:1.25rem; letter-spacing:0.02em;">
              <span class="input-group-text bg-light fw-bold text-muted" style="font-size:1rem;">kg</span>
            </div>

            <!-- Indicadores Inteligentes em Tempo Real -->
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
              <div id="badgeArroba" class="live-arroba-badge d-none">
                <i class="bi bi-calculator me-1"></i> <span id="valArroba">0,00</span> @ carcaça (50% rendimento)
              </div>
              <div id="badgeGanho" class="badge p-2 d-none" style="font-size:0.85rem;">
                <i class="bi bi-arrow-up-right me-1"></i> <span id="valGanho">0,00 kg</span>
              </div>
            </div>
          </div>

          <!-- 3. Data da Pesagem -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
              <i class="bi bi-calendar-event text-secondary me-1"></i> Data da Pesagem *
            </label>
            <input type="date" name="data" class="form-control" required
                   value="<?= e($p['data'] ?? date('Y-m-d')) ?>"
                   style="font-size:0.95rem; padding:0.6rem;">
          </div>

          <!-- 4. Observações (Opcional) -->
          <div class="mb-4">
            <label class="form-label small text-muted">Observação do Lote / Manejo (Opcional)</label>
            <textarea name="observacao" class="form-control" rows="2" placeholder="Ex: Pesagem pós-vermifugação, troca de pasto..."><?= e($p['observacao'] ?? '') ?></textarea>
          </div>

          <!-- Botões de Ação Grandes e Seguros -->
          <div class="d-grid gap-2 pt-2 border-top">
            <button type="submit" class="btn btn-flow-primary py-3 justify-content-center" style="font-size:1.05rem;">
              <i class="bi bi-check-circle-fill me-2 fs-5"></i> <?= $isEdit ? 'Salvar Alterações da Pesagem' : 'Confirmar e Gravar Pesagem' ?>
            </button>
            <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/pesagens' ?>" class="btn btn-link text-muted text-decoration-none text-center">
              Cancelar e voltar
            </a>
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
  const cardHist = document.getElementById('cardHistorico');
  const histPesoTexto = document.getElementById('histPesoTexto');
  const histDataTexto = document.getElementById('histDataTexto');
  const badgeArroba = document.getElementById('badgeArroba');
  const valArroba = document.getElementById('valArroba');
  const badgeGanho = document.getElementById('badgeGanho');
  const valGanho = document.getElementById('valGanho');

  function atualizarCalculos() {
    const selectedOpt = selectAnimal.options[selectAnimal.selectedIndex];
    const ultimoPeso = selectedOpt ? parseFloat(selectedOpt.dataset.peso || 0) : 0;
    const ultimaData = selectedOpt ? (selectedOpt.dataset.data || '') : '';
    const pesoDigitado = parseFloat(inputPeso.value || 0);

    // 1. Atualiza histórico do animal
    if (selectedOpt && selectAnimal.value && ultimoPeso > 0) {
      cardHist.classList.remove('d-none');
      const arrobasAntigas = (ultimoPeso * 0.5 / 15).toFixed(2).replace('.', ',');
      histPesoTexto.innerHTML = `<strong>${ultimoPeso.toFixed(1).replace('.', ',')} kg</strong> <span class="text-muted small">(${arrobasAntigas} @)</span>`;
      histDataTexto.textContent = 'Registrado em: ' + ultimaData;
    } else {
      cardHist.classList.add('d-none');
    }

    // 2. Converte em Arrobas (@) com rendimento estimado de 50%
    if (pesoDigitado > 0) {
      badgeArroba.classList.remove('d-none');
      const arrobas = (pesoDigitado * 0.5 / 15).toFixed(2).replace('.', ',');
      valArroba.textContent = arrobas;

      // 3. Calcula Ganho/Perda se houver histórico
      if (ultimoPeso > 0) {
        badgeGanho.classList.remove('d-none');
        const diff = pesoDigitado - ultimoPeso;
        const diffArr = (Math.abs(diff) * 0.5 / 15).toFixed(2).replace('.', ',');
        if (diff > 0) {
          badgeGanho.className = 'badge p-2 bg-success text-white';
          badgeGanho.innerHTML = `<i class="bi bi-arrow-up-right me-1"></i> Ganho: +${diff.toFixed(1).replace('.', ',')} kg (+${diffArr} @)`;
        } else if (diff < 0) {
          badgeGanho.className = 'badge p-2 bg-warning text-dark';
          badgeGanho.innerHTML = `<i class="bi bi-arrow-down-right me-1"></i> Variação: ${diff.toFixed(1).replace('.', ',')} kg (-${diffArr} @)`;
        } else {
          badgeGanho.className = 'badge p-2 bg-secondary text-white';
          badgeGanho.innerHTML = `<i class="bi bi-dash me-1"></i> Manteve o mesmo peso`;
        }
      } else {
        badgeGanho.classList.add('d-none');
      }
    } else {
      badgeArroba.classList.add('d-none');
      badgeGanho.classList.add('d-none');
    }
  }

  selectAnimal.addEventListener('change', atualizarCalculos);
  inputPeso.addEventListener('input', atualizarCalculos);

  // Executa no carregamento se já houver valor
  atualizarCalculos();
});
</script>
