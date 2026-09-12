<?php
$animaisStmt = $db->query("
    SELECT a.id, a.brinco, a.nome, a.sexo, a.raca, a.data_nascimento, a.status, a.foto_url, a.peso_inicial,
           p.nome as pasto_nome,
           COALESCE(
             (SELECT peso FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
             a.peso_inicial
           ) as ultimo_peso,
           COALESCE(
             (SELECT data::text FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
             a.created_at::text
           ) as ultima_data
    FROM animais a
    LEFT JOIN pastagens p ON a.pasto_id = p.id
    WHERE a.status NOT IN ('morto','vendido')
    ORDER BY a.brinco
");
$animais   = $animaisStmt->fetchAll();
$isEdit    = isset($pesagem);
$p         = $pesagem ?? [];
$preAnimal = $p['animal_id'] ?? ($_GET['animal_id'] ?? null);

// Mapa JSON para alimentar o cockpit lateral instantaneamente no frontend
$animaisMap = [];
foreach ($animais as $an) {
    $animaisMap[$an['id']] = [
        'id'          => $an['id'],
        'brinco'      => $an['brinco'],
        'nome'        => $an['nome'] ?: 'Sem nome',
        'sexo'        => $an['sexo'] === 'M' ? 'Macho' : 'Fêmea',
        'raca'        => $an['raca'] ?: 'Nelore',
        'status'      => ucfirst($an['status']),
        'pasto'       => $an['pasto_nome'] ?: 'Sem pastagem',
        'foto_url'    => $an['foto_url'] ?: null,
        'ultimo_peso' => (float)($an['ultimo_peso'] ?? 0),
        'ultima_data' => !empty($an['ultima_data']) ? formatDate($an['ultima_data']) : 'No cadastro',
        'idade'       => calcIdade($an['data_nascimento'])
    ];
}
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

<!-- Layout 2 Colunas Widescreen (Aproveitamento Total do Espaço) -->
<div class="row g-3">
  <!-- Coluna Esquerda: Formulário de Pesagem -->
  <div class="col-lg-7">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-rulers text-primary"></i> <?= $isEdit ? 'Editar Registro de Pesagem' : 'Lançar Pesagem na Balança' ?></h6>
        <span class="text-muted small">Gravação rápida e cálculo de arrobas (@)</span>
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
              <div class="aws-form-hint">Ao selecionar, a ficha cadastral completa é carregada ao lado.</div>
            </div>

            <!-- 2. Peso Balança -->
            <div class="col-12">
              <label class="form-label fw-bold">Peso Lido na Balança (kg) *</label>
              <div class="input-group">
                <input type="number" step="0.01" name="peso" id="inputPeso" class="form-control fw-600" required
                       placeholder="Ex: 420.50" min="1" max="2000"
                       value="<?= e($p['peso'] ?? '') ?>" autocomplete="off">
                <span class="input-group-text">kg</span>
              </div>

              <!-- Indicador de Arrobas e Variação em Linha (AWS Metric Callout) -->
              <div id="metricCallout" class="d-none mt-2">
                <div class="aws-metric-callout">
                  <i class="bi bi-calculator text-success"></i>
                  <span>Equivale a: <strong id="valArroba">0,00</strong> @ carcaça (50%)</span>
                  <span id="sepGain" class="text-muted">•</span>
                  <span id="valGanho" class="fw-bold">0,00 kg</span>
                </div>
              </div>
              <div class="aws-form-hint">Conversão padrão: 50% de rendimento de carcaça (30 kg vivo = 1 @).</div>
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
              <input type="text" name="observacao" class="form-control" placeholder="Ex: Pós-vermifugação, troca de pasto" value="<?= e($p['observacao'] ?? '') ?>">
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

  <!-- Coluna Direita: Prontuário / Ficha do Animal Selecionado -->
  <div class="col-lg-5">
    <div class="aws-container h-100">
      <div class="aws-container-header">
        <h6><i class="bi bi-person-vcard text-success"></i> Prontuário do Animal Selecionado</h6>
        <span class="badge bg-light text-secondary border" id="badgeAnimalStatus">Aguardando seleção</span>
      </div>

      <div class="aws-container-body" id="cockpitAnimalContainer">
        <!-- Estado Vazio (Antes de escolher) -->
        <div id="cockpitEmptyState" class="text-center text-muted py-4">
          <i class="bi bi-tag fs-2 d-block mb-2 text-muted" style="opacity: 0.5;"></i>
          <p class="small mb-0">Selecione um animal ao lado para carregar a ficha cadastral, histórico e localização.</p>
        </div>

        <!-- Estado Preenchido (Ativo ao escolher) -->
        <div id="cockpitFilledState" class="d-none">
          <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div id="cockpitFotoWrapper" class="rounded border d-flex align-items-center justify-content-center bg-light" style="width:64px; height:64px; overflow:hidden; flex-shrink:0;">
              <i class="bi bi-image text-muted fs-3" id="cockpitNoFotoIcon"></i>
              <img id="cockpitFotoImg" src="" alt="Foto" class="d-none w-100 h-100" style="object-fit:cover;">
            </div>
            <div>
              <h5 class="mb-0 fw-bold text-dark" id="cockpitBrinco">—</h5>
              <small class="text-muted d-block" id="cockpitNome">—</small>
            </div>
          </div>

          <table class="aws-review-table mb-3">
            <tr>
              <td class="label-cell">Raça / Sexo:</td>
              <td class="value-cell" id="cockpitRacaSexo">—</td>
            </tr>
            <tr>
              <td class="label-cell">Idade Estimada:</td>
              <td class="value-cell" id="cockpitIdade">—</td>
            </tr>
            <tr>
              <td class="label-cell">Pastagem Atual:</td>
              <td class="value-cell text-success" id="cockpitPasto">—</td>
            </tr>
            <tr>
              <td class="label-cell">Último Peso:</td>
              <td class="value-cell" id="cockpitUltimoPeso">—</td>
            </tr>
            <tr>
              <td class="label-cell">Último Registro:</td>
              <td class="value-cell text-muted small" id="cockpitUltimaData">—</td>
            </tr>
          </table>

          <div class="p-2 rounded bg-light border small text-muted">
            <i class="bi bi-info-circle text-primary me-1"></i>
            O histórico completo com gráficos pode ser consultado a qualquer momento no <strong>Prontuário Individual</strong> do animal.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const animaisData = <?= json_encode($animaisMap) ?>;

document.addEventListener('DOMContentLoaded', function() {
  const selectAnimal = document.getElementById('selectAnimal');
  const inputPeso = document.getElementById('inputPeso');
  const metricCallout = document.getElementById('metricCallout');
  const valArroba = document.getElementById('valArroba');
  const valGanho = document.getElementById('valGanho');
  const sepGain = document.getElementById('sepGain');

  // Elementos do Cockpit Lateral
  const emptyState = document.getElementById('cockpitEmptyState');
  const filledState = document.getElementById('cockpitFilledState');
  const badgeStatus = document.getElementById('badgeAnimalStatus');
  const cockpitBrinco = document.getElementById('cockpitBrinco');
  const cockpitNome = document.getElementById('cockpitNome');
  const cockpitRacaSexo = document.getElementById('cockpitRacaSexo');
  const cockpitIdade = document.getElementById('cockpitIdade');
  const cockpitPasto = document.getElementById('cockpitPasto');
  const cockpitUltimoPeso = document.getElementById('cockpitUltimoPeso');
  const cockpitUltimaData = document.getElementById('cockpitUltimaData');
  const fotoImg = document.getElementById('cockpitFotoImg');
  const noFotoIcon = document.getElementById('cockpitNoFotoIcon');

  function atualizarCockpit() {
    const animalId = selectAnimal.value;
    const a = animaisData[animalId];

    if (a) {
      emptyState.classList.add('d-none');
      filledState.classList.remove('d-none');

      badgeStatus.textContent = a.status;
      badgeStatus.className = 'badge ' + (a.status === 'Ativo' ? 'bg-success-subtle text-success border-success' : 'bg-light text-dark border');

      cockpitBrinco.textContent = a.brinco;
      cockpitNome.textContent = a.nome;
      cockpitRacaSexo.textContent = `${a.raca} • ${a.sexo}`;
      cockpitIdade.textContent = a.idade;
      cockpitPasto.textContent = '🌿 ' + a.pasto;

      if (a.ultimo_peso > 0) {
        cockpitUltimoPeso.innerHTML = `<strong>${a.ultimo_peso.toFixed(1).replace('.', ',')} kg</strong> <span class="text-muted small">(${(a.ultimo_peso*0.5/15).toFixed(2).replace('.', ',')} @)</span>`;
      } else {
        cockpitUltimoPeso.textContent = 'Sem pesagens';
      }
      cockpitUltimaData.textContent = a.ultima_data;

      if (a.foto_url) {
        fotoImg.src = a.foto_url;
        fotoImg.classList.remove('d-none');
        noFotoIcon.classList.add('d-none');
      } else {
        fotoImg.classList.add('d-none');
        noFotoIcon.classList.remove('d-none');
      }
    } else {
      emptyState.classList.remove('d-none');
      filledState.classList.add('d-none');
      badgeStatus.textContent = 'Aguardando seleção';
      badgeStatus.className = 'badge bg-light text-secondary border';
    }
  }

  function atualizarCalculos() {
    const selectedOpt = selectAnimal.options[selectAnimal.selectedIndex];
    const ultimoPeso = selectedOpt ? parseFloat(selectedOpt.dataset.peso || 0) : 0;
    const pesoDigitado = parseFloat(inputPeso.value || 0);

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
          valGanho.textContent = `Ganho: +${diff.toFixed(1)} kg`;
        } else if (diff < 0) {
          valGanho.className = 'fw-bold text-warning';
          valGanho.textContent = `Variação: ${diff.toFixed(1)} kg`;
        } else {
          valGanho.className = 'fw-bold text-muted';
          valGanho.textContent = 'Mesmo peso';
        }
      } else {
        sepGain.classList.add('d-none');
        valGanho.classList.add('d-none');
      }
    } else {
      metricCallout.classList.add('d-none');
    }
  }

  selectAnimal.addEventListener('change', function() {
    atualizarCockpit();
    atualizarCalculos();
  });

  inputPeso.addEventListener('input', atualizarCalculos);

  atualizarCockpit();
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
      <h7><i class="bi bi-person-vcard"></i> Ficha Lateral Automática</h7>
      <p>Ao selecionar o brinco do boi na lista, a coluna direita carrega automaticamente o histórico, pasto atual e a última pesagem para você conferir a evolução na hora.</p>
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
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaPesagem;
});
</script>
