<?php
$animais = $db->query("SELECT id, brinco, nome, sexo, raca, status FROM animais WHERE status != 'morto' AND status != 'vendido' ORDER BY brinco")->fetchAll();
$pastagens = $db->query("SELECT id, nome FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();
?>

<!-- Header Modo Campo -->
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card bg-success text-white p-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4e 100%) !important;">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <div class="d-flex align-items-center gap-2">
            <span class="fs-4">📱</span>
            <h4 class="mb-0 fw-800 text-white">Modo Campo (PWA)</h4>
          </div>
          <small class="text-white text-opacity-75">Coleta de dados em campo com funcionamento 100% offline</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div id="connectionStatusBadge" class="badge bg-success shadow-sm">
            <span class="status-dot online"></span> Verificando conexão...
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Card de Instalação do Aplicativo (PWA) -->
<div id="pwaInstallCard" class="card mb-3 border-success border-2 shadow-sm" style="background: #e8f5ee;">
  <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
        <i class="bi bi-download fs-4"></i>
      </div>
      <div>
        <h6 class="mb-0 fw-bold text-success">Instalar Aplicativo no Celular</h6>
        <small class="text-muted">Acesse em tela cheia direto da tela inicial e use sem internet no pasto</small>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-success fw-bold px-3 shadow-sm" id="btnInstallPwa" onclick="triggerPwaInstall()">
        <i class="bi bi-phone-fill me-1"></i> Instalar Aplicativo
      </button>
      <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalComoInstalar">
        <i class="bi bi-question-circle me-1"></i> Como Instalar?
      </button>
    </div>
  </div>
</div>

<!-- Ações Rápidas de Campo (Grandes Botões Touch) -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100 text-center p-3 border-warning border-2 shadow-sm action-touch-card" data-bs-toggle="modal" data-bs-target="#modalPesagem" style="cursor: pointer;">
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div class="fs-1 mb-2">⚖️</div>
        <h5 class="fw-bold text-dark mb-1">+ Registrar Pesagem</h5>
        <p class="text-muted small mb-0">Lançar peso do animal e foto opcional</p>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card h-100 text-center p-3 border-success border-2 shadow-sm action-touch-card" data-bs-toggle="modal" data-bs-target="#modalBezerro" style="cursor: pointer;">
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div class="fs-1 mb-2">🐣</div>
        <h5 class="fw-bold text-dark mb-1">+ Nascimento / Bezerro</h5>
        <p class="text-muted small mb-0">Novo animal com foto de filhote para a memória</p>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card h-100 text-center p-3 border-danger border-2 shadow-sm action-touch-card" data-bs-toggle="modal" data-bs-target="#modalSaude" style="cursor: pointer;">
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div class="fs-1 mb-2">⚕️</div>
        <h5 class="fw-bold text-dark mb-1">+ Saúde / Manejo / Óbito</h5>
        <p class="text-muted small mb-0">Tratamentos, vacinas ou registro de morte com censura</p>
      </div>
    </div>
  </div>
</div>

<!-- Barra de Sincronização Offline -->
<div class="card mb-4 border-primary border-opacity-50 shadow-sm">
  <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-phone text-primary fs-5"></i>
      <h6 class="mb-0 fw-bold text-primary">Memória Local do Celular (<span id="pendingCount">0</span> pendências)</h6>
    </div>
    <button type="button" class="btn btn-sm btn-primary fw-bold" id="btnSyncNow" onclick="syncOfflineData()">
      <i class="bi bi-cloud-arrow-up-fill me-1"></i> Sincronizar com a Nuvem
    </button>
  </div>
  <div class="card-body p-0">
    <ul class="list-group list-group-flush" id="pendingItemsList">
      <li class="list-group-item text-muted text-center py-3 small">Carregando registros locais...</li>
    </ul>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: PESAGEM RÁPIDA -->
<!-- ============================================================ -->
<div class="modal fade" id="modalPesagem" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning bg-opacity-25">
        <h5 class="modal-title fw-bold">⚖️ Registrar Pesagem Rápida</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formPwaPesagem" onsubmit="handlePwaPesagem(event)">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Brinco do Animal *</label>
            <input type="text" id="p_brinco" class="form-control form-control-lg" list="animaisListPwa" required placeholder="Digite ou selecione o brinco">
            <datalist id="animaisListPwa">
              <?php foreach ($animais as $an): ?>
                <option value="<?= e($an['brinco']) ?>"><?= e($an['nome'] ? $an['nome'].' — ' : '') ?><?= e($an['raca'] ?? '') ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Peso Atual (kg) *</label>
            <input type="number" step="0.1" id="p_peso" class="form-control form-control-lg" required placeholder="0.0">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Data da Pesagem</label>
            <input type="date" id="p_data" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">📷 Foto da Pesagem (Opcional)</label>
            <input type="file" id="p_foto" class="form-control" accept="image/*" capture="environment">
            <small class="text-muted">Abre a câmera do celular no campo</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Observação</label>
            <input type="text" id="p_obs" class="form-control" placeholder="Ex: Lote pasto A, bom ganho">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          <button type="submit" class="btn btn-warning fw-bold px-4">Salvar no Celular</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: NASCIMENTO / NOVO BEZERRO -->
<!-- ============================================================ -->
<div class="modal fade" id="modalBezerro" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold text-white">🐣 Novo Animal / Bezerro</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="formPwaBezerro" onsubmit="handlePwaBezerro(event)">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-7">
              <label class="form-label fw-bold">Brinco *</label>
              <input type="text" id="b_brinco" class="form-control" required placeholder="Ex: BZ001">
            </div>
            <div class="col-5">
              <label class="form-label fw-bold">Sexo *</label>
              <select id="b_sexo" class="form-select">
                <option value="M">♂ Macho</option>
                <option value="F">♀ Fêmea</option>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Nome</label>
              <input type="text" id="b_nome" class="form-control" placeholder="Opcional">
            </div>
            <div class="col-6">
              <label class="form-label">Raça</label>
              <input type="text" id="b_raca" class="form-control" value="Nelore">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label fw-bold">Data de Nascimento</label>
            <input type="date" id="b_data" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label fw-bold">📷 Foto do Bezerro (Memória de Filhote)</label>
            <input type="file" id="b_foto" class="form-control" accept="image/*" capture="environment">
            <small class="text-success fw-bold">🌱 Ficará gravada com destaque permanente como foto de nascimento.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          <button type="submit" class="btn btn-success fw-bold px-4">Salvar Bezerro</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: SAÚDE / TRATAMENTO / ÓBITO -->
<!-- ============================================================ -->
<div class="modal fade" id="modalSaude" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-bold text-white">⚕️ Evento de Saúde / Óbito</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="formPwaSaude" onsubmit="handlePwaSaude(event)">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Brinco do Animal *</label>
            <input type="text" id="s_brinco" class="form-control" list="animaisListPwa" required placeholder="Selecione o brinco">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Tipo de Evento *</label>
            <select id="s_tipo" class="form-select" onchange="toggleObitoSensivel(this.value)">
              <option value="Vacinação">Vacinação</option>
              <option value="Tratamento">Tratamento</option>
              <option value="Curativo">Curativo</option>
              <option value="Vermifugação">Vermifugação</option>
              <option value="Exame">Exame</option>
              <option value="Óbito">⚠️ Óbito / Morte do Animal</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Descrição / Motivo *</label>
            <input type="text" id="s_desc" class="form-control" required placeholder="Ex: Febre aftosa, ferimento na pata, morte natural">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Medicamento</label>
              <input type="text" id="s_med" class="form-control" placeholder="Opcional">
            </div>
            <div class="col-6">
              <label class="form-label">Dose</label>
              <input type="text" id="s_dose" class="form-control" placeholder="Ex: 5ml">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">📷 Foto do Manejo / Laudo</label>
            <input type="file" id="s_foto" class="form-control" accept="image/*" capture="environment">
          </div>
          <div class="form-check form-switch mb-2" id="sensivelSwitchGroup">
            <input class="form-check-input" type="checkbox" id="s_sensivel" value="1">
            <label class="form-check-label small" for="s_sensivel">
              ⚠️ Censurar foto por padrão (Desfoque de proteção visual para óbito/ferimentos)
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          <button type="submit" class="btn btn-danger fw-bold px-4">Salvar Registro</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: COMO INSTALAR O APLICATIVO -->
<!-- ============================================================ -->
<div class="modal fade" id="modalComoInstalar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-phone-fill me-2"></i>Como Instalar o Aplicativo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-4">
          <h6 class="fw-bold text-success d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-android2 fs-5"></i> No Android (Google Chrome / Edge / Samsung)
          </h6>
          <ol class="small text-secondary ps-3 mb-0">
            <li class="mb-1">Toque no botão verde <strong>"Instalar Aplicativo"</strong> acima.</li>
            <li class="mb-1">Se não abrir automaticamente, toque nos <strong>3 pontinhos (⋮)</strong> no canto superior direito do navegador.</li>
            <li>Selecione a opção <strong>"Instalar aplicativo"</strong> ou <strong>"Adicionar à tela inicial"</strong>.</li>
          </ol>
        </div>
        
        <hr>

        <div class="mb-3">
          <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-apple fs-5"></i> No iPhone / iPad (Safari)
          </h6>
          <ol class="small text-secondary ps-3 mb-0">
            <li class="mb-1">Abra este link no navegador <strong>Safari</strong> da Apple.</li>
            <li class="mb-1">Toque no botão <strong>Compartilhar</strong> (<i class="bi bi-box-arrow-up text-primary"></i> ícone quadrado com seta no rodapé do Safari).</li>
            <li>Role para baixo e toque em <strong>"Adicionar à Tela de Início"</strong> (<i class="bi bi-plus-square text-primary"></i>).</li>
          </ol>
        </div>

        <div class="alert alert-info py-2 px-3 small mb-0">
          <i class="bi bi-info-circle-fill me-1"></i> O aplicativo funcionará em tela cheia direto do seu celular, mesmo 100% offline no pasto!
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success fw-bold w-100" data-bs-dismiss="modal">Entendi, vamos lá!</button>
      </div>
    </div>
  </div>
</div>

<script src="/assets/js/pwa-campo.js"></script>
<script>
// Manipuladores de submissão do PWA
async function handlePwaPesagem(e) {
  e.preventDefault();
  const brinco = document.getElementById('p_brinco').value.trim();
  const peso = parseFloat(document.getElementById('p_peso').value);
  const data = document.getElementById('p_data').value;
  const obs = document.getElementById('p_obs').value.trim();
  const fotoFile = document.getElementById('p_foto').files[0];

  let fotoBase64 = null;
  if (fotoFile) {
    fotoBase64 = await compressImage(fotoFile);
  }

  await addQueueItem('pesagem', { brinco, peso, data, observacao: obs }, fotoBase64);
  bootstrap.Modal.getInstance(document.getElementById('modalPesagem')).hide();
  document.getElementById('formPwaPesagem').reset();
  showToast(`✅ Pesagem de ${peso}kg para ${brinco} salva no celular!`, 'success');
}

async function handlePwaBezerro(e) {
  e.preventDefault();
  const brinco = document.getElementById('b_brinco').value.trim();
  const sexo = document.getElementById('b_sexo').value;
  const nome = document.getElementById('b_nome').value.trim();
  const raca = document.getElementById('b_raca').value.trim();
  const data = document.getElementById('b_data').value;
  const fotoFile = document.getElementById('b_foto').files[0];

  let fotoBase64 = null;
  if (fotoFile) {
    fotoBase64 = await compressImage(fotoFile);
  }

  await addQueueItem('animal', { brinco, sexo, nome, raca, data_nascimento: data }, fotoBase64);
  bootstrap.Modal.getInstance(document.getElementById('modalBezerro')).hide();
  document.getElementById('formPwaBezerro').reset();
  showToast(`✅ Bezerro ${brinco} cadastrado e foto salva no celular!`, 'success');
}

async function handlePwaSaude(e) {
  e.preventDefault();
  const brinco = document.getElementById('s_brinco').value.trim();
  const tipo = document.getElementById('s_tipo').value;
  const desc = document.getElementById('s_desc').value.trim();
  const med = document.getElementById('s_med').value.trim();
  const dose = document.getElementById('s_dose').value.trim();
  const isSensivel = document.getElementById('s_sensivel').checked || tipo === 'Óbito';
  const fotoFile = document.getElementById('s_foto').files[0];

  let fotoBase64 = null;
  if (fotoFile) {
    fotoBase64 = await compressImage(fotoFile);
  }

  await addQueueItem('saude', { brinco, tipo, descricao: desc, medicamento: med, dose, is_sensivel: isSensivel ? 1 : 0 }, fotoBase64);
  bootstrap.Modal.getInstance(document.getElementById('modalSaude')).hide();
  document.getElementById('formPwaSaude').reset();
  showToast(`✅ Evento de ${tipo} para ${brinco} salvo no celular!`, 'success');
}

function toggleObitoSensivel(val) {
  const check = document.getElementById('s_sensivel');
  if (val === 'Óbito') {
    check.checked = true;
  }
}
</script>
