<?php
$isEdit    = isset($animal);
$a         = $animal ?? [];
$currPasto = (int)($a['pasto_id'] ?? 0);
$stmtPastos = $db->prepare("SELECT id, nome, status FROM pastagens WHERE status='ativa' OR id = ? ORDER BY nome");
$stmtPastos->execute([$currPasto]);
$pastagens  = $stmtPastos->fetchAll();
// Filtro estrito: Apenas fêmeas ativas para Mãe biológica
$animaisF  = $db->query("SELECT id, brinco, nome, status FROM animais WHERE sexo = 'F' AND status != 'morto' ORDER BY brinco")->fetchAll();
// Touros/Reprodutores machos da propriedade para sugestão de Pai
$tourosM   = $db->query("SELECT id, brinco, nome FROM animais WHERE sexo = 'M' AND status != 'morto' ORDER BY brinco")->fetchAll();

if (!empty($a['mae_id'])) {
    $currentMaeFound = false;
    foreach ($animaisF as $af) {
        if ($af['id'] == $a['mae_id']) { $currentMaeFound = true; break; }
    }
    if (!$currentMaeFound) {
        $extraMae = $db->prepare("SELECT id, brinco, nome, status, sexo FROM animais WHERE id = ?");
        $extraMae->execute([$a['mae_id']]);
        $em = $extraMae->fetch();
        if ($em && $em['sexo'] === 'F') $animaisF[] = $em;
    }
}
$sexoInicial = $a['sexo'] ?? 'M';
?>
<!-- Barra Superior com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= $isEdit ? '/animais/'.$a['id'] : '/animais' ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Animais &gt; <?= $isEdit ? 'Editar Animal #'.e($a['brinco']) : 'Novo Cadastro' ?>
    </span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaAnimal()">
    <i class="bi bi-info-circle"></i> <span>Instruções deste Painel</span>
  </button>
</div>

<!-- Assistente em Etapas (AWS Cloudscape Wizard) -->
<div class="aws-wizard-header">
  <ul class="aws-wizard-stepper">
    <li class="aws-step-item active" data-step="1" id="stepItem1" onclick="irParaPasso(1)">
      <div class="aws-step-badge" id="badgeStep1">1</div>
      <span class="aws-step-label">Identificação</span>
    </li>
    <li class="aws-step-divider" id="divider1"></li>
    <li class="aws-step-item" data-step="2" id="stepItem2" onclick="irParaPasso(2)">
      <div class="aws-step-badge" id="badgeStep2">2</div>
      <span class="aws-step-label">Manejo e Pasto</span>
    </li>
    <li class="aws-step-divider" id="divider2"></li>
    <li class="aws-step-item" data-step="3" id="stepItem3" onclick="irParaPasso(3)">
      <div class="aws-step-badge" id="badgeStep3">3</div>
      <span class="aws-step-label">Família e Foto</span>
    </li>
    <li class="aws-step-divider" id="divider3"></li>
    <li class="aws-step-item" data-step="4" id="stepItem4" onclick="irParaPasso(4)">
      <div class="aws-step-badge" id="badgeStep4">4</div>
      <span class="aws-step-label">Revisão</span>
    </li>
  </ul>
</div>

<form method="POST" action="<?= $isEdit ? '/animais/'.$a['id'].'/atualizar' : '/animais/salvar' ?>" enctype="multipart/form-data" id="formAnimal">
  <?= csrf_field() ?>

  <!-- ========================================================
       ETAPA 1: IDENTIFICAÇÃO BÁSICA
       ======================================================== -->
  <div class="aws-step-pane active-step" id="paneStep1">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-tag text-success"></i> Passo 1 de 4: Identificação do Animal</h6>
        <span class="text-muted small">Campos com * são obrigatórios</span>
      </div>
      <div class="aws-container-body">
        <div class="row g-3">
          <!-- Brinco -->
          <div class="col-md-6">
            <label class="form-label fw-bold">Brinco / Identificador *</label>
            <input type="text" name="brinco" id="inputBrinco" class="form-control text-uppercase"
                   required value="<?= e($a['brinco'] ?? '') ?>" placeholder="Ex: BR-0042">
            <div class="aws-form-hint">Código único estampado no brinco auricular ou tatuagem.</div>
          </div>

          <!-- Nome / Apelido -->
          <div class="col-md-6">
            <label class="form-label">Nome ou Apelido</label>
            <input type="text" name="nome" id="inputNome" class="form-control"
                   value="<?= e($a['nome'] ?? '') ?>" placeholder="Opcional">
            <div class="aws-form-hint">Identificação afetiva ou nome de registro genealógico.</div>
          </div>

          <!-- Sexo com Controle Segmentado AWS -->
          <div class="col-md-6">
            <label class="form-label fw-bold d-block">Sexo do Animal *</label>
            <select name="sexo" id="selectSexo" class="d-none" required>
              <option value="M" <?= $sexoInicial==='M'?'selected':'' ?>>Macho</option>
              <option value="F" <?= $sexoInicial==='F'?'selected':'' ?>>Fêmea</option>
            </select>
            <div class="aws-segmented-control">
              <button type="button" class="aws-segment-btn <?= $sexoInicial==='M'?'active':'' ?>" id="btnSegMacho" onclick="setSexo('M')">
                <i class="bi bi-gender-male"></i> Macho
              </button>
              <button type="button" class="aws-segment-btn <?= $sexoInicial==='F'?'active':'' ?>" id="btnSegFemea" onclick="setSexo('F')">
                <i class="bi bi-gender-female"></i> Fêmea
              </button>
            </div>
            <div class="aws-form-hint">Define se é matriz (vaca/novilha) ou reprodutor/garrote.</div>
          </div>

          <!-- Raça -->
          <div class="col-md-6">
            <label class="form-label">Raça Predominante</label>
            <input type="text" name="raca" id="inputRaca" class="form-control" list="racasList"
                   value="<?= e($a['raca'] ?? 'Nelore') ?>" placeholder="Selecione ou digite...">
            <datalist id="racasList">
              <?php foreach (['Nelore','Angus','Brahman','Girolando','Gir','Senepol','Tabapuã','Hereford','Simental','Cruzado Industrial'] as $r): ?>
                <option value="<?= $r ?>">
              <?php endforeach; ?>
            </datalist>
            <div class="aws-form-hint">Padrão zootécnico principal do animal.</div>
          </div>

          <!-- Data de Nascimento -->
          <div class="col-md-6">
            <label class="form-label">Data de Nascimento / Entrada</label>
            <input type="date" name="data_nascimento" id="inputNascimento" class="form-control"
                   value="<?= e($a['data_nascimento'] ?? '') ?>">
            <div class="aws-form-hint">Utilizada para cálculo automático de idade e desmame.</div>
          </div>

          <!-- Peso Inicial -->
          <div class="col-md-6">
            <label class="form-label">Peso de Chegada / Entrada (kg)</label>
            <div class="input-group">
              <input type="number" step="0.01" name="peso_inicial" id="inputPesoInicial" class="form-control"
                     value="<?= e($a['peso_inicial'] ?? '') ?>" placeholder="0.00">
              <span class="input-group-text">kg</span>
            </div>
            <div id="calloutArrobaInicial" class="aws-metric-callout <?= empty($a['peso_inicial']) ? 'd-none' : '' ?>">
              <i class="bi bi-calculator"></i> Equivale a <strong id="valArrobaInicial"><?= !empty($a['peso_inicial']) ? number_format($a['peso_inicial']*0.5/15, 2, ',', '.') : '0,00' ?></strong> @ carcaça (50%)
            </div>
          </div>
        </div>

        <div class="aws-wizard-actions">
          <a href="<?= $isEdit ? '/animais/'.$a['id'] : '/animais' ?>" class="aws-btn-secondary">Cancelar</a>
          <button type="button" class="aws-btn-primary" onclick="irParaPasso(2)">
            Próximo: Manejo e Pasto <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================
       ETAPA 2: LOCALIZAÇÃO E MANEJO
       ======================================================== -->
  <div class="aws-step-pane" id="paneStep2">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-geo-alt text-success"></i> Passo 2 de 4: Alocação em Pastagem e Status</h6>
        <span class="text-muted small">Controle de lotação e situação cadastral</span>
      </div>
      <div class="aws-container-body">
        <div class="row g-3">
          <!-- Pastagem Atual -->
          <div class="col-md-6">
            <label class="form-label">Pastagem / Piquete Atual</label>
            <select name="pasto_id" id="selectPasto" class="form-select">
              <option value="">— Sem pastagem definida —</option>
              <?php foreach ($pastagens as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($a['pasto_id']??'')==$p['id']?'selected':'' ?>>
                  <?= e($p['nome']) ?><?= $p['status'] !== 'ativa' ? ' (' . ucfirst($p['status']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="aws-form-hint">Onde o animal permanecerá solto no momento.</div>
          </div>

          <!-- Status -->
          <div class="col-md-6">
            <label class="form-label">Situação Cadastral</label>
            <select name="status" id="selectStatus" class="form-select">
              <?php foreach (['ativo' => 'Ativo no Rebanho', 'prenha' => 'Prenha / Matriz', 'doente' => 'Em Tratamento / Enfermaria', 'desmamado' => 'Bezerro Desmamado', 'vendido' => 'Vendido', 'morto' => 'Baixa / Morto'] as $sk => $sl): ?>
                <option value="<?= $sk ?>" <?= ($a['status']??'ativo')===$sk?'selected':'' ?>><?= $sl ?></option>
              <?php endforeach; ?>
            </select>
            <div class="aws-form-hint">Animais em tratamento ou prenhas recebem acompanhamento no painel de alertas.</div>
          </div>

          <!-- Origem -->
          <div class="col-12">
            <label class="form-label">Origem do Animal</label>
            <input type="text" name="origem" id="inputOrigem" class="form-control" list="origemList"
                   value="<?= e($a['origem'] ?? 'Nascido na fazenda') ?>" placeholder="Ex: Nascido na fazenda, Comprado...">
            <datalist id="origemList">
              <option value="Nascido na fazenda">
              <option value="Comprado">
              <option value="Próprio">
              <option value="Leilão">
            </datalist>
            <div class="aws-form-hint">Procedência para rastreabilidade de custos e compras.</div>
          </div>
        </div>

        <div class="aws-wizard-actions">
          <button type="button" class="aws-btn-secondary" onclick="irParaPasso(1)">
            <i class="bi bi-chevron-left"></i> Voltar
          </button>
          <button type="button" class="aws-btn-primary" onclick="irParaPasso(3)">
            Próximo: Família e Foto <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================
       ETAPA 3: GENEALOGIA E FOTO
       ======================================================== -->
  <div class="aws-step-pane" id="paneStep3">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-diagram-3 text-success"></i> Passo 3 de 4: Genealogia e Foto do Animal</h6>
        <span class="text-muted small">Filiação zootécnica e registro visual</span>
      </div>
      <div class="aws-container-body">
        <div class="row g-3">
          <!-- Mãe Biológica -->
          <div class="col-md-6">
            <label class="form-label">Mãe Biológica (apenas matrizes fêmeas)</label>
            <select name="mae_id" id="selectMae" class="form-select">
              <option value="">— Desconhecida / Não informada —</option>
              <?php foreach ($animaisF as $af): ?>
                <?php if (($af['id'] ?? 0) !== ($a['id'] ?? null)): ?>
                <option value="<?= $af['id'] ?>" <?= ($a['mae_id']??'')==$af['id']?'selected':'' ?>>
                  <?= e($af['brinco']) ?><?= $af['nome'] ? ' — '.e($af['nome']) : '' ?><?= !empty($af['status']) && $af['status'] !== 'ativo' ? ' ('.ucfirst($af['status']).')' : '' ?>
                </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <div class="aws-form-hint">Apenas matrizes fêmeas ativas da fazenda são listadas.</div>
          </div>

          <!-- Pai / Touro -->
          <div class="col-md-6">
            <label class="form-label">Pai / Reprodutor (brinco ou código do sêmen)</label>
            <input type="text" name="pai_brinco" id="inputPai" class="form-control text-uppercase" list="tourosList"
                   value="<?= e($a['pai_brinco'] ?? '') ?>" placeholder="Ex: TO-0042 ou código de inseminação" autocomplete="off">
            <datalist id="tourosList">
              <?php foreach ($tourosM as $tm): ?>
                <?php if (($tm['id'] ?? 0) !== ($a['id'] ?? null)): ?>
                  <option value="<?= e($tm['brinco']) ?>"><?= e($tm['brinco']) ?><?= $tm['nome'] ? ' — '.e($tm['nome']) : '' ?> (Touro da Fazenda)</option>
                <?php endif; ?>
              <?php endforeach; ?>
            </datalist>
            <div class="aws-form-hint">Selecione um touro ativo ou digite o código da palheta.</div>
          </div>

          <!-- Foto com Preview Sutil -->
          <div class="col-md-6">
            <label class="form-label">Foto do Animal</label>
            <input type="file" name="foto" id="inputFoto" class="form-control" accept="image/*">
            <div class="aws-form-hint">Formatos suportados: JPG, PNG, WebP (máx. 5 MB).</div>
            <div id="fotoPreviewContainer" class="mt-2 <?= empty($a['foto_url']) ? 'd-none' : '' ?>">
              <img id="imgFotoPreview" src="<?= !empty($a['foto_url']) ? e($a['foto_url']) : '' ?>" alt="Pré-visualização" class="rounded border" style="max-height: 120px; object-fit: cover;">
            </div>
          </div>

          <!-- Observações -->
          <div class="col-md-6">
            <label class="form-label">Observações Clínicas / Particulares</label>
            <textarea name="observacao" id="inputObs" class="form-control" rows="3" placeholder="Ex: Sinais corporais, chifre mocha, cicatriz..."><?= e($a['observacao'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="aws-wizard-actions">
          <button type="button" class="aws-btn-secondary" onclick="irParaPasso(2)">
            <i class="bi bi-chevron-left"></i> Voltar
          </button>
          <button type="button" class="aws-btn-primary" onclick="irParaPasso(4)">
            Próximo: Revisar Dados <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================
       ETAPA 4: REVISÃO E CONFIRMAÇÃO (ESTILO AWS REVIEW)
       ======================================================== -->
  <div class="aws-step-pane" id="paneStep4">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-check2-all text-success"></i> Passo 4 de 4: Revisão e Confirmação</h6>
        <span class="text-muted small">Confira os dados antes de gravar no banco de dados</span>
      </div>
      <div class="aws-container-body">
        <div class="row g-4">
          <!-- Bloco 1: Identificação -->
          <div class="col-md-6">
            <div class="p-3 border rounded bg-light">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong class="text-dark small text-uppercase">1. Identificação</strong>
                <a href="javascript:void(0)" onclick="irParaPasso(1)" class="small text-primary text-decoration-none">Editar</a>
              </div>
              <table class="aws-review-table">
                <tr><td class="label-cell">Brinco:</td><td class="value-cell" id="revBrinco">—</td></tr>
                <tr><td class="label-cell">Nome:</td><td class="value-cell" id="revNome">—</td></tr>
                <tr><td class="label-cell">Sexo:</td><td class="value-cell" id="revSexo">—</td></tr>
                <tr><td class="label-cell">Raça:</td><td class="value-cell" id="revRaca">—</td></tr>
                <tr><td class="label-cell">Nascimento:</td><td class="value-cell" id="revNasc">—</td></tr>
                <tr><td class="label-cell">Peso Inicial:</td><td class="value-cell" id="revPeso">—</td></tr>
              </table>
            </div>
          </div>

          <!-- Bloco 2: Manejo e Família -->
          <div class="col-md-6">
            <div class="p-3 border rounded bg-light">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong class="text-dark small text-uppercase">2. Manejo & Família</strong>
                <a href="javascript:void(0)" onclick="irParaPasso(2)" class="small text-primary text-decoration-none">Editar</a>
              </div>
              <table class="aws-review-table">
                <tr><td class="label-cell">Pasto:</td><td class="value-cell" id="revPasto">—</td></tr>
                <tr><td class="label-cell">Status:</td><td class="value-cell" id="revStatus">—</td></tr>
                <tr><td class="label-cell">Origem:</td><td class="value-cell" id="revOrigem">—</td></tr>
                <tr><td class="label-cell">Mãe:</td><td class="value-cell" id="revMae">—</td></tr>
                <tr><td class="label-cell">Pai:</td><td class="value-cell" id="revPai">—</td></tr>
              </table>
            </div>
          </div>
        </div>

        <div class="aws-wizard-actions">
          <button type="button" class="aws-btn-secondary" onclick="irParaPasso(3)">
            <i class="bi bi-chevron-left"></i> Voltar
          </button>
          <button type="submit" class="aws-btn-primary" id="btnConfirmarCadastro">
            <i class="bi bi-check-circle me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Concluir e Cadastrar Animal' ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('formAnimal');
  const inputBrinco = document.getElementById('inputBrinco');
  const inputNome = document.getElementById('inputNome');
  const selectSexo = document.getElementById('selectSexo');
  const btnSegMacho = document.getElementById('btnSegMacho');
  const btnSegFemea = document.getElementById('btnSegFemea');
  const inputRaca = document.getElementById('inputRaca');
  const inputNascimento = document.getElementById('inputNascimento');
  const inputPeso = document.getElementById('inputPesoInicial');
  const calloutArroba = document.getElementById('calloutArrobaInicial');
  const valArroba = document.getElementById('valArrobaInicial');
  const selectPasto = document.getElementById('selectPasto');
  const selectStatus = document.getElementById('selectStatus');
  const inputOrigem = document.getElementById('inputOrigem');
  const selectMae = document.getElementById('selectMae');
  const inputPai = document.getElementById('inputPai');
  const inputFoto = document.getElementById('inputFoto');
  const imgPreview = document.getElementById('imgFotoPreview');
  const previewContainer = document.getElementById('fotoPreviewContainer');

  // Elementos de Revisão
  const revBrinco = document.getElementById('revBrinco');
  const revNome = document.getElementById('revNome');
  const revSexo = document.getElementById('revSexo');
  const revRaca = document.getElementById('revRaca');
  const revNasc = document.getElementById('revNasc');
  const revPeso = document.getElementById('revPeso');
  const revPasto = document.getElementById('revPasto');
  const revStatus = document.getElementById('revStatus');
  const revOrigem = document.getElementById('revOrigem');
  const revMae = document.getElementById('revMae');
  const revPai = document.getElementById('revPai');

  let currentStep = 1;

  window.setSexo = function(val) {
    selectSexo.value = val;
    btnSegMacho.classList.toggle('active', val === 'M');
    btnSegFemea.classList.toggle('active', val === 'F');
  };

  window.irParaPasso = function(step) {
    if (step > 1) {
      const brincoVal = (inputBrinco.value || '').trim();
      if (!brincoVal) {
        alert('Por favor, informe o Brinco de identificação do animal antes de continuar.');
        inputBrinco.focus();
        return;
      }
    }

    currentStep = step;

    // Atualiza Stepper
    for (let i = 1; i <= 4; i++) {
      const item = document.getElementById('stepItem' + i);
      const pane = document.getElementById('paneStep' + i);
      const badge = document.getElementById('badgeStep' + i);
      const divider = document.getElementById('divider' + (i - 1));

      if (pane) pane.classList.toggle('active-step', i === step);
      if (item) {
        item.classList.toggle('active', i === step);
        item.classList.toggle('completed', i < step);
      }
      if (badge) {
        if (i < step) {
          badge.innerHTML = '<i class="bi bi-check-lg"></i>';
        } else {
          badge.textContent = i;
        }
      }
      if (divider) {
        divider.classList.toggle('completed-line', i <= step);
      }
    }

    if (step === 4) {
      atualizarRevisao();
    }
  };

  function atualizarRevisao() {
    revBrinco.textContent = (inputBrinco.value || '—').toUpperCase();
    revNome.textContent = inputNome.value || 'Não informado';
    revSexo.textContent = selectSexo.value === 'M' ? 'Macho' : 'Fêmea';
    revRaca.textContent = inputRaca.value || 'Nelore';
    revNasc.textContent = inputNascimento.value ? inputNascimento.value.split('-').reverse().join('/') : 'Não informado';
    const pesoVal = parseFloat(inputPeso.value || 0);
    revPeso.textContent = pesoVal > 0 ? `${pesoVal.toFixed(1)} kg (${(pesoVal*0.5/15).toFixed(2)} @)` : 'Não pesado';

    const pOpt = selectPasto.options[selectPasto.selectedIndex];
    revPasto.textContent = (pOpt && selectPasto.value) ? pOpt.textContent.trim() : 'Sem pasto';
    const sOpt = selectStatus.options[selectStatus.selectedIndex];
    revStatus.textContent = sOpt ? sOpt.textContent.trim() : 'Ativo';
    revOrigem.textContent = inputOrigem.value || 'Nascido na fazenda';

    const mOpt = selectMae.options[selectMae.selectedIndex];
    revMae.textContent = (mOpt && selectMae.value) ? mOpt.textContent.trim() : 'Não informada';
    revPai.textContent = inputPai.value || 'Não informado';
  }

  // Conversão de Arrobas
  inputPeso.addEventListener('input', function() {
    const p = parseFloat(this.value || 0);
    if (p > 0) {
      calloutArroba.classList.remove('d-none');
      valArroba.textContent = (p * 0.5 / 15).toFixed(2).replace('.', ',');
    } else {
      calloutArroba.classList.add('d-none');
    }
  });

  // Preview Instantâneo da Foto
  inputFoto.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        imgPreview.src = e.target.result;
        previewContainer.classList.remove('d-none');
      };
      reader.readAsDataURL(file);
    }
  });

  // Validação Zootécnica Genealógica
  const femeasBrincos = <?= json_encode(array_values(array_filter(array_map(fn($f) => strtoupper(trim($f['brinco'] ?? '')), $animaisF)))) ?>;
  const femeasMap = <?= json_encode(array_column($animaisF, 'brinco', 'id')) ?>;

  form.addEventListener('submit', function(e) {
    const brincoAtual = (inputBrinco.value || '').trim().toUpperCase();
    const paiBrinco = (inputPai.value || '').trim().toUpperCase();
    const maeId = selectMae.value;
    const maeBrinco = (femeasMap[maeId] || '').trim().toUpperCase();

    if (maeBrinco && brincoAtual && maeBrinco === brincoAtual) {
      e.preventDefault();
      alert('Inconsistência Genealógica: O animal não pode ser a mãe de si mesmo.');
      irParaPasso(3);
      selectMae.focus();
      return;
    }

    if (paiBrinco && brincoAtual && paiBrinco === brincoAtual) {
      e.preventDefault();
      alert('Inconsistência Genealógica: O animal não pode ser o pai de si mesmo.');
      irParaPasso(3);
      inputPai.focus();
      return;
    }

    if (maeBrinco && paiBrinco && maeBrinco === paiBrinco) {
      e.preventDefault();
      alert('Inconsistência Zootécnica: A mãe e o pai não podem ser o mesmo animal (' + paiBrinco + ').');
      irParaPasso(3);
      inputPai.focus();
      return;
    }

    if (paiBrinco && femeasBrincos.includes(paiBrinco)) {
      e.preventDefault();
      alert('Inconsistência Zootécnica: O brinco "' + paiBrinco + '" pertence a uma FÊMEA do rebanho e não pode ser informado como touro reprodutor.');
      irParaPasso(3);
      inputPai.focus();
      return;
    }
  });
});

// Configuração da Ajuda Lateral AWS
window.abrirAjudaAnimal = function() {
  const title = 'Guia: Cadastro e Manejo de Animais';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-tag"></i> Finalidade do Cadastro</h7>
      <p>Esta tela permite registrar formalmente a entrada de cada cabeça no rebanho, vinculando genealogia (pai e mãe), pasto onde viverá e peso inicial.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-list-ol"></i> Passo a Passo Recomendado</h7>
      <div class="aws-help-step-item">
        <div class="aws-help-step-num">1</div>
        <div><strong>Identificação:</strong> Digite o brinco gravado na orelha do animal e selecione Macho ou Fêmea. Se tiver balança, anote o peso inicial.</div>
      </div>
      <div class="aws-help-step-item">
        <div class="aws-help-step-num">2</div>
        <div><strong>Manejo e Pasto:</strong> Escolha em qual piquete o animal vai ficar para manter a capacidade de pastagem sob controle.</div>
      </div>
      <div class="aws-help-step-item">
        <div class="aws-help-step-num">3</div>
        <div><strong>Família e Foto:</strong> Se for filhote nascido na fazenda, selecione a mãe na lista. Se tiver foto no celular ou máquina, anexe aqui.</div>
      </div>
      <div class="aws-help-step-item">
        <div class="aws-help-step-num">4</div>
        <div><strong>Revisão:</strong> Confira tudo antes de clicar em Confirmar e Cadastrar.</div>
      </div>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-shield-check"></i> Regras Zootécnicas</h7>
      <div class="aws-help-tip-box mb-2">
        O sistema bloqueia inconsistências biológicas automaticamente: um animal não pode ser a mãe de si mesmo e uma fêmea não pode ser informada como touro reprodutor.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

// Vincula no botão de ajuda geral do topo
document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaAnimal;
});
</script>
