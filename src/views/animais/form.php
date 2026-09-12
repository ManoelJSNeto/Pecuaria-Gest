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

// Se for edição e a mãe já estiver vinculada (mesmo que inativa), garante sua presença na lista caso seja fêmea
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
<!-- Topo de Navegação -->
<div class="mb-3 d-flex align-items-center justify-content-between">
  <a href="<?= $isEdit ? '/animais/'.$a['id'] : '/animais' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar para Lista
  </a>
  <span class="text-muted small d-none d-sm-inline">
    <i class="bi bi-shield-check text-success me-1"></i> Cadastro Seguro de Rebanho
  </span>
</div>

<div class="row justify-content-center">
  <div class="col-lg-9 col-xl-8">

    <!-- Stepper Visual do Fluxo Guiado -->
    <div class="flow-stepper mb-4">
      <div class="step-item active" data-step="1" id="stepTab1" role="button" tabindex="0">
        <div class="step-bullet" id="bullet1">1</div>
        <div class="step-info">
          <span class="step-number-tag">Etapa 1</span>
          <span class="step-name">Identificação</span>
        </div>
      </div>
      <div class="step-item" data-step="2" id="stepTab2" role="button" tabindex="0">
        <div class="step-bullet" id="bullet2">2</div>
        <div class="step-info">
          <span class="step-number-tag">Etapa 2</span>
          <span class="step-name">Pasto e Manejo</span>
        </div>
      </div>
      <div class="step-item" data-step="3" id="stepTab3" role="button" tabindex="0">
        <div class="step-bullet" id="bullet3">3</div>
        <div class="step-info">
          <span class="step-number-tag">Etapa 3</span>
          <span class="step-name">Família e Foto</span>
        </div>
      </div>
    </div>

    <!-- Formulário Principal -->
    <form method="POST" action="<?= $isEdit ? '/animais/'.$a['id'].'/atualizar' : '/animais/salvar' ?>" enctype="multipart/form-data" id="formAnimal">
      <?= csrf_field() ?>

      <!-- ========================================================
           ETAPA 1: IDENTIFICAÇÃO BÁSICA
           ======================================================== -->
      <div class="step-pane active-step" id="paneStep1">
        <div class="card shadow-sm border mb-4">
          <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <div class="action-icon-box icon-green" style="width:38px; height:38px; font-size:1.15rem;">
                <i class="bi bi-tag-fill"></i>
              </div>
              <div>
                <h6 class="mb-0 fw-bold" style="font-size:1.05rem;">Quem é o animal?</h6>
                <small class="text-muted">Brinco de identificação, sexo, raça e peso de chegada</small>
              </div>
            </div>
          </div>

          <div class="card-body p-4">
            <div class="flow-helper-banner">
              <i class="bi bi-info-circle-fill text-success fs-5"></i>
              <div>
                <strong>Dica:</strong> O <strong>Brinco</strong> é o número gravado na orelha do animal. Ele é a identidade principal na fazenda.
              </div>
            </div>

            <div class="row g-3">
              <!-- Brinco / Código -->
              <div class="col-md-6">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  Brinco / Código de Identificação *
                </label>
                <input type="text" name="brinco" id="inputBrinco" class="form-control form-control-lg text-uppercase fw-bold"
                       required value="<?= e($a['brinco'] ?? '') ?>" placeholder="Ex: BR001 ou 420"
                       style="font-size:1.15rem; letter-spacing:0.02em;">
                <small class="text-muted">Número gravado no brinco de orelha ou tatuagem.</small>
              </div>

              <!-- Nome / Apelido -->
              <div class="col-md-6">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  Nome ou Apelido (Opcional)
                </label>
                <input type="text" name="nome" id="inputNome" class="form-control form-control-lg"
                       value="<?= e($a['nome'] ?? '') ?>" placeholder="Ex: Pintado, Baronesa..."
                       style="font-size:1.05rem;">
                <small class="text-muted">Como você costuma chamar esse animal no lote.</small>
              </div>

              <!-- Sexo (Seletor Visual com Botões Grandes + Select invisível compatível) -->
              <div class="col-12">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  Sexo do Animal *
                </label>
                <!-- Select oficial do backend -->
                <select name="sexo" id="selectSexo" class="form-select d-none" required>
                  <option value="M" <?= $sexoInicial==='M'?'selected':'' ?>>Macho</option>
                  <option value="F" <?= $sexoInicial==='F'?'selected':'' ?>>Fêmea</option>
                </select>

                <!-- Botões táteis fáceis de tocar -->
                <div class="sexo-toggle-group">
                  <div class="sexo-toggle-btn <?= $sexoInicial==='M' ? 'active-macho' : '' ?>" id="btnMacho" role="button" tabindex="0">
                    <span style="font-size:1.4rem;">🐂</span>
                    <div class="text-start">
                      <div class="fw-bold" style="font-size:1rem;">Macho</div>
                      <small style="font-size:0.75rem; opacity:0.85;">Touro, garrote ou bezerro</small>
                    </div>
                  </div>
                  <div class="sexo-toggle-btn <?= $sexoInicial==='F' ? 'active-femea' : '' ?>" id="btnFemea" role="button" tabindex="0">
                    <span style="font-size:1.4rem;">🐄</span>
                    <div class="text-start">
                      <div class="fw-bold" style="font-size:1rem;">Fêmea</div>
                      <small style="font-size:0.75rem; opacity:0.85;">Vaca, novilha ou bezerra</small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Raça -->
              <div class="col-md-6">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  Raça Predominante
                </label>
                <input type="text" name="raca" id="inputRaca" class="form-control" list="racasList"
                       value="<?= e($a['raca'] ?? 'Nelore') ?>" placeholder="Ex: Nelore, Angus..."
                       style="font-size:1rem;">
                <datalist id="racasList">
                  <?php foreach (['Nelore','Angus','Brahman','Girolando','Gir','Senepol','Tabapuã','Hereford','Simental','Limousin','Cruzado Industrial'] as $r): ?>
                    <option value="<?= $r ?>">
                  <?php endforeach; ?>
                </datalist>
                <!-- Pílulas de Raças Mais Usadas -->
                <div class="quick-chip-group">
                  <span class="quick-chip" onclick="definirRaca('Nelore')">Nelore</span>
                  <span class="quick-chip" onclick="definirRaca('Angus')">Angus</span>
                  <span class="quick-chip" onclick="definirRaca('Girolando')">Girolando</span>
                  <span class="quick-chip" onclick="definirRaca('Cruzado Industrial')">Cruzado</span>
                  <span class="quick-chip" onclick="definirRaca('Brahman')">Brahman</span>
                </div>
              </div>

              <!-- Data de Nascimento -->
              <div class="col-md-6">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  Data de Nascimento (ou chegada)
                </label>
                <input type="date" name="data_nascimento" id="inputNascimento" class="form-control"
                       value="<?= e($a['data_nascimento'] ?? '') ?>"
                       style="font-size:1rem;">
                <small class="text-muted">A data ajuda a calcular a idade automaticamente.</small>
              </div>

              <!-- Peso Inicial / Chegada -->
              <div class="col-md-6">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  Peso Inicial (kg)
                </label>
                <div class="input-group">
                  <input type="number" step="0.01" name="peso_inicial" id="inputPesoInicial" class="form-control"
                         value="<?= e($a['peso_inicial'] ?? '') ?>" placeholder="Ex: 180.00"
                         style="font-size:1.05rem;">
                  <span class="input-group-text bg-light text-muted fw-bold">kg</span>
                </div>
                <div id="badgeArrobaInicial" class="live-arroba-badge <?= empty($a['peso_inicial']) ? 'd-none' : '' ?>">
                  <i class="bi bi-calculator me-1"></i> <span id="valArrobaInicial"><?= !empty($a['peso_inicial']) ? number_format($a['peso_inicial']*0.5/15, 2, ',', '.') : '0,00' ?></span> @ carcaça
                </div>
              </div>
            </div>

            <!-- Rodapé da Etapa 1 -->
            <div class="step-footer-actions">
              <span class="text-muted small"><i class="bi bi-check2-circle text-success me-1"></i>Campos marcados com * são obrigatórios</span>
              <button type="button" class="btn-flow-primary" id="btnAvancarParaPasto">
                Avançar para Pasto e Manejo <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================================
           ETAPA 2: LOCALIZAÇÃO E MANEJO
           ======================================================== -->
      <div class="step-pane" id="paneStep2">
        <div class="card shadow-sm border mb-4">
          <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <div class="action-icon-box icon-amber" style="width:38px; height:38px; font-size:1.15rem;">
                <i class="bi bi-geo-alt-fill"></i>
              </div>
              <div>
                <h6 class="mb-0 fw-bold" style="font-size:1.05rem;">Onde ele vai ficar?</h6>
                <small class="text-muted">Piquete atual, situação do animal e origem do rebanho</small>
              </div>
            </div>
          </div>

          <div class="card-body p-4">
            <div class="flow-helper-banner">
              <i class="bi bi-tree-fill text-success fs-5"></i>
              <div>
                <strong>Organização dos Pastos:</strong> Colocar o animal no piquete correto mantém a lotação sob controle e facilita na hora de juntar o gado.
              </div>
            </div>

            <div class="row g-3">
              <!-- Pastagem Atual -->
              <div class="col-md-6">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  <i class="bi bi-geo-alt text-success me-1"></i> Piquete / Pastagem Atual
                </label>
                <select name="pasto_id" id="selectPasto" class="form-select form-select-lg" style="font-size:1rem;">
                  <option value="">— Sem pastagem definida —</option>
                  <?php foreach ($pastagens as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($a['pasto_id']??'')==$p['id']?'selected':'' ?>>
                      🌿 <?= e($p['nome']) ?><?= $p['status'] !== 'ativa' ? ' (' . ucfirst($p['status']) . ')' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Selecione onde o animal está solto hoje.</small>
              </div>

              <!-- Status do Animal -->
              <div class="col-md-6">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  <i class="bi bi-activity text-primary me-1"></i> Situação / Status Atual
                </label>
                <select name="status" id="selectStatus" class="form-select form-select-lg" style="font-size:1rem;">
                  <?php foreach (['ativo' => 'Ativo no Rebanho', 'prenha' => 'Prenha / Matriz', 'doente' => 'Em Tratamento / Enfermaria', 'desmamado' => 'Bezerro Desmamado', 'vendido' => 'Vendido', 'morto' => 'Morto / Baixa'] as $sk => $sl): ?>
                    <option value="<?= $sk ?>" <?= ($a['status']??'ativo')===$sk?'selected':'' ?>><?= $sl ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="quick-chip-group">
                  <span class="quick-chip" onclick="definirStatus('ativo')">Ativo</span>
                  <span class="quick-chip" onclick="definirStatus('prenha')">Prenha</span>
                  <span class="quick-chip" onclick="definirStatus('desmamado')">Bezerro</span>
                  <span class="quick-chip" onclick="definirStatus('doente')">Tratamento</span>
                </div>
              </div>

              <!-- Origem -->
              <div class="col-12">
                <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                  <i class="bi bi-signpost-split text-secondary me-1"></i> Origem do Animal
                </label>
                <input type="text" name="origem" id="inputOrigem" class="form-control" list="origemList"
                       value="<?= e($a['origem'] ?? 'Nascido na fazenda') ?>" placeholder="Ex: Nascido na fazenda, Comprado..."
                       style="font-size:1rem;">
                <datalist id="origemList">
                  <option value="Nascido na fazenda">
                  <option value="Comprado">
                  <option value="Próprio">
                  <option value="Leilão">
                </datalist>
                <div class="quick-chip-group">
                  <span class="quick-chip" onclick="definirOrigem('Nascido na fazenda')">Nascido na Fazenda</span>
                  <span class="quick-chip" onclick="definirOrigem('Comprado')">Comprado</span>
                  <span class="quick-chip" onclick="definirOrigem('Próprio')">Próprio</span>
                  <span class="quick-chip" onclick="definirOrigem('Leilão')">Leilão</span>
                </div>
              </div>
            </div>

            <!-- Rodapé da Etapa 2 -->
            <div class="step-footer-actions">
              <button type="button" class="btn-flow-secondary" onclick="irParaPasso(1)">
                <i class="bi bi-arrow-left me-1"></i> Voltar para Identificação
              </button>
              <button type="button" class="btn-flow-primary" onclick="irParaPasso(3)">
                Avançar para Família e Foto <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================================
           ETAPA 3: FAMÍLIA (GENEALOGIA), FOTO E CONFIRMAÇÃO
           ======================================================== -->
      <div class="step-pane" id="paneStep3">
        <div class="card shadow-sm border mb-4">
          <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <div class="action-icon-box icon-purple" style="width:38px; height:38px; font-size:1.15rem;">
                <i class="bi bi-camera-fill"></i>
              </div>
              <div>
                <h6 class="mb-0 fw-bold" style="font-size:1.05rem;">Família e Foto do Animal</h6>
                <small class="text-muted">Vincule pai/mãe se conhecidos e anexe uma foto para o prontuário</small>
              </div>
            </div>
          </div>

          <div class="card-body p-4">
            <div class="row g-4">
              <!-- Coluna Genealógica -->
              <div class="col-md-6">
                <div class="p-3 rounded border bg-light h-100">
                  <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-diagram-3 text-primary me-1"></i> Família / Pais (Opcional)
                  </h6>

                  <!-- Mãe Biológica -->
                  <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">
                      <i class="bi bi-gender-female text-danger me-1"></i> Mãe Biológica (Vaca/Matriz)
                    </label>
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
                    <small class="text-muted d-block mt-1" style="font-size:0.75rem;">Apenas matrizes fêmeas ativas da fazenda são listadas.</small>
                  </div>

                  <!-- Pai / Touro -->
                  <div class="mb-2">
                    <label class="form-label small fw-bold text-dark">
                      <i class="bi bi-gender-male text-primary me-1"></i> Pai / Touro (Brinco ou Sêmen)
                    </label>
                    <input type="text" name="pai_brinco" id="inputPai" class="form-control text-uppercase" list="tourosList"
                           value="<?= e($a['pai_brinco'] ?? '') ?>" placeholder="Ex: TO042 ou selecione..." autocomplete="off">
                    <datalist id="tourosList">
                      <?php foreach ($tourosM as $tm): ?>
                        <?php if (($tm['id'] ?? 0) !== ($a['id'] ?? null)): ?>
                          <option value="<?= e($tm['brinco']) ?>"><?= e($tm['brinco']) ?><?= $tm['nome'] ? ' — '.e($tm['nome']) : '' ?> (Touro da Fazenda)</option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </datalist>
                    <small class="text-muted d-block mt-1" style="font-size:0.75rem;">Selecione um touro ativo ou digite o código da palheta de inseminação.</small>
                  </div>
                </div>
              </div>

              <!-- Coluna Foto e Observações -->
              <div class="col-md-6">
                <div class="p-3 rounded border bg-light h-100">
                  <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-camera text-success me-1"></i> Foto e Retrato
                  </h6>

                  <!-- Área de Upload de Foto com Preview Instantâneo -->
                  <div class="photo-drop-card mb-3" onclick="document.getElementById('inputFoto').click()">
                    <div id="photoPreviewContainer" class="photo-preview-wrapper <?= empty($a['foto_url']) ? 'd-none' : '' ?>">
                      <img id="imgFotoPreview" src="<?= !empty($a['foto_url']) ? e($a['foto_url']) : '' ?>" alt="Foto do animal">
                    </div>
                    <div id="photoPlaceholder" class="<?= !empty($a['foto_url']) ? 'd-none' : '' ?>">
                      <i class="bi bi-cloud-arrow-up text-muted" style="font-size:2rem;"></i>
                      <div class="fw-bold text-dark mt-1" style="font-size:0.9rem;">Toque aqui para escolher a foto</div>
                      <small class="text-muted" style="font-size:0.75rem;">Formatos JPG ou PNG</small>
                    </div>
                    <input type="file" name="foto" id="inputFoto" class="d-none" accept="image/*">
                  </div>

                  <!-- Observações -->
                  <label class="form-label small fw-bold text-dark">Observações Adicionais</label>
                  <textarea name="observacao" id="inputObs" class="form-control" rows="2" placeholder="Marcas particulares, chifre, etc..."><?= e($a['observacao'] ?? '') ?></textarea>
                </div>
              </div>
            </div>

            <!-- Faixa Resumo de Conferência Antes de Gravar -->
            <div class="flow-summary-strip mt-4">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-bold text-dark"><i class="bi bi-clipboard-check text-success me-1"></i> Resumo para Conferência:</span>
                <span class="badge bg-success">Pronto para Gravar</span>
              </div>
              <div class="flow-summary-grid">
                <div class="flow-summary-item">
                  <span class="flow-summary-label">Brinco</span>
                  <span class="flow-summary-val" id="sumBrinco">—</span>
                </div>
                <div class="flow-summary-item">
                  <span class="flow-summary-label">Sexo</span>
                  <span class="flow-summary-val" id="sumSexo">—</span>
                </div>
                <div class="flow-summary-item">
                  <span class="flow-summary-label">Raça</span>
                  <span class="flow-summary-val" id="sumRaca">—</span>
                </div>
                <div class="flow-summary-item">
                  <span class="flow-summary-label">Pasto</span>
                  <span class="flow-summary-val" id="sumPasto">—</span>
                </div>
                <div class="flow-summary-item">
                  <span class="flow-summary-label">Status</span>
                  <span class="flow-summary-val" id="sumStatus">—</span>
                </div>
              </div>
            </div>

            <!-- Rodapé Final de Submissão -->
            <div class="step-footer-actions">
              <button type="button" class="btn-flow-secondary" onclick="irParaPasso(2)">
                <i class="bi bi-arrow-left me-1"></i> Voltar para Pasto
              </button>
              <button type="submit" class="btn-flow-primary py-3 px-4" id="btnSalvarAnimal" style="font-size:1.05rem;">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <?= $isEdit ? 'Salvar Alterações do Animal' : 'Confirmar e Cadastrar Animal' ?>
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('formAnimal');
  const inputBrinco = document.getElementById('inputBrinco');
  const inputNome = document.getElementById('inputNome');
  const selectSexo = document.getElementById('selectSexo');
  const btnMacho = document.getElementById('btnMacho');
  const btnFemea = document.getElementById('btnFemea');
  const inputRaca = document.getElementById('inputRaca');
  const inputPeso = document.getElementById('inputPesoInicial');
  const badgeArroba = document.getElementById('badgeArrobaInicial');
  const valArroba = document.getElementById('valArrobaInicial');
  const selectPasto = document.getElementById('selectPasto');
  const selectStatus = document.getElementById('selectStatus');
  const inputOrigem = document.getElementById('inputOrigem');
  const selectMae = document.getElementById('selectMae');
  const inputPai = document.getElementById('inputPai');
  const inputFoto = document.getElementById('inputFoto');
  const imgPreview = document.getElementById('imgFotoPreview');
  const previewContainer = document.getElementById('photoPreviewContainer');
  const photoPlaceholder = document.getElementById('photoPlaceholder');

  // Resumo Elements
  const sumBrinco = document.getElementById('sumBrinco');
  const sumSexo = document.getElementById('sumSexo');
  const sumRaca = document.getElementById('sumRaca');
  const sumPasto = document.getElementById('sumPasto');
  const sumStatus = document.getElementById('sumStatus');

  let currentStep = 1;

  // 1. Alternância de Abas / Passos
  window.irParaPasso = function(step) {
    if (step > 1) {
      // Validação rápida da etapa 1 antes de avançar
      const brincoVal = (inputBrinco.value || '').trim();
      if (!brincoVal) {
        alert('Por favor, informe o Brinco de identificação do animal antes de continuar.');
        inputBrinco.focus();
        return;
      }
    }

    currentStep = step;

    // Atualiza stepper visual
    for (let i = 1; i <= 3; i++) {
      const tab = document.getElementById('stepTab' + i);
      const pane = document.getElementById('paneStep' + i);
      const bullet = document.getElementById('bullet' + i);

      if (i === step) {
        tab.classList.add('active');
        pane.classList.add('active-step');
      } else {
        tab.classList.remove('active');
        pane.classList.remove('active-step');
      }

      if (i < step) {
        tab.classList.add('completed');
        bullet.innerHTML = '<i class="bi bi-check-lg"></i>';
      } else {
        tab.classList.remove('completed');
        bullet.textContent = i;
      }
    }

    // Se foi para o passo 3, atualiza o resumo
    if (step === 3) {
      atualizarResumoConferencia();
    }

    // Rola suavemente para o topo do form
    window.scrollTo({ top: 120, behavior: 'smooth' });
  };

  // Stepper Tabs clicáveis
  document.getElementById('stepTab1').addEventListener('click', () => irParaPasso(1));
  document.getElementById('stepTab2').addEventListener('click', () => irParaPasso(2));
  document.getElementById('stepTab3').addEventListener('click', () => irParaPasso(3));
  document.getElementById('btnAvancarParaPasto').addEventListener('click', () => irParaPasso(2));

  // 2. Seleção de Sexo Visual
  btnMacho.addEventListener('click', function() {
    selectSexo.value = 'M';
    btnMacho.classList.add('active-macho');
    btnFemea.classList.remove('active-femea');
  });

  btnFemea.addEventListener('click', function() {
    selectSexo.value = 'F';
    btnFemea.classList.add('active-femea');
    btnMacho.classList.remove('active-macho');
  });

  // 3. Funções de Pílulas Rápidas
  window.definirRaca = function(raca) {
    inputRaca.value = raca;
  };

  window.definirStatus = function(status) {
    selectStatus.value = status;
  };

  window.definirOrigem = function(origem) {
    inputOrigem.value = origem;
  };

  // 4. Conversão de Arrobas do Peso Inicial
  inputPeso.addEventListener('input', function() {
    const p = parseFloat(inputPeso.value || 0);
    if (p > 0) {
      badgeArroba.classList.remove('d-none');
      valArroba.textContent = (p * 0.5 / 15).toFixed(2).replace('.', ',');
    } else {
      badgeArroba.classList.add('d-none');
    }
  });

  // 5. Preview Instantâneo da Foto
  inputFoto.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        imgPreview.src = e.target.result;
        previewContainer.classList.remove('d-none');
        photoPlaceholder.classList.add('d-none');
      };
      reader.readAsDataURL(file);
    }
  });

  // 6. Atualização do Resumo de Conferência
  function atualizarResumoConferencia() {
    sumBrinco.textContent = (inputBrinco.value || '—').toUpperCase();
    sumSexo.textContent = selectSexo.value === 'M' ? '🐂 Macho' : '🐄 Fêmea';
    sumRaca.textContent = inputRaca.value || 'Nelore';
    const pastoOpt = selectPasto.options[selectPasto.selectedIndex];
    sumPasto.textContent = pastoOpt && selectPasto.value ? pastoOpt.textContent.replace('🌿', '').trim() : 'Sem pasto';
    const statusOpt = selectStatus.options[selectStatus.selectedIndex];
    sumStatus.textContent = statusOpt ? statusOpt.textContent.split('/')[0].trim() : 'Ativo';
  }

  // 7. Validação Zootécnica Genealógica
  const femeasBrincos = <?= json_encode(array_values(array_filter(array_map(fn($f) => strtoupper(trim($f['brinco'] ?? '')), $animaisF)))) ?>;
  const femeasMap = <?= json_encode(array_column($animaisF, 'brinco', 'id')) ?>;

  form.addEventListener('submit', function(e) {
    const brincoAtual = (inputBrinco.value || '').trim().toUpperCase();
    const paiBrinco = (inputPai.value || '').trim().toUpperCase();
    const maeId = selectMae.value;
    const maeBrinco = (femeasMap[maeId] || '').trim().toUpperCase();

    // 1. Não pode ser a própria mãe
    if (maeBrinco && brincoAtual && maeBrinco === brincoAtual) {
      e.preventDefault();
      alert('Inconsistência Genealógica: O animal não pode ser a mãe de si mesmo.');
      irParaPasso(3);
      selectMae.focus();
      return;
    }

    // 2. Não pode ser o próprio pai
    if (paiBrinco && brincoAtual && paiBrinco === brincoAtual) {
      e.preventDefault();
      alert('Inconsistência Genealógica: O animal não pode ser o pai de si mesmo.');
      irParaPasso(3);
      inputPai.focus();
      return;
    }

    // 3. Pai e mãe não podem ser o mesmo animal
    if (maeBrinco && paiBrinco && maeBrinco === paiBrinco) {
      e.preventDefault();
      alert('Inconsistência Zootécnica: A mãe e o pai não podem ser o mesmo animal (' + paiBrinco + ').');
      irParaPasso(3);
      inputPai.focus();
      return;
    }

    // 4. O pai não pode ser uma fêmea cadastrada no rebanho
    if (paiBrinco && femeasBrincos.includes(paiBrinco)) {
      e.preventDefault();
      alert('Inconsistência Zootécnica: O brinco "' + paiBrinco + '" pertence a uma FÊMEA do rebanho e não pode ser informado como touro/pai reprodutor.');
      irParaPasso(3);
      inputPai.focus();
      return;
    }
  });
});
</script>
