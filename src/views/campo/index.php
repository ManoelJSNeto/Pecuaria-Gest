<?php
$animais = $db->query("SELECT id, brinco, nome, sexo, raca, status FROM animais WHERE status != 'morto' AND status != 'vendido' ORDER BY brinco")->fetchAll();
$pastagens = $db->query("SELECT id, nome FROM pastagens WHERE status='ativa' ORDER BY nome")->fetchAll();
?>

<!-- Estilos específicos para interface Touch de Campo -->
<style>
.campo-nav-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-bottom: 1rem;
}
.campo-nav-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 6px;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.03);
  text-align: center;
  user-select: none;
}
.campo-nav-btn i, .campo-nav-btn span.tab-icon {
  font-size: 1.4rem;
  margin-bottom: 4px;
}
.campo-nav-btn:active {
  transform: scale(0.97);
}
.campo-nav-btn.active {
  background: #1a4d2e;
  border-color: #1a4d2e;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(26, 77, 46, 0.25);
}
.campo-nav-btn.active.btn-tab-pesagem {
  background: #d97706;
  border-color: #d97706;
  box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
}
.campo-nav-btn.active.btn-tab-bezerro {
  background: #16a34a;
  border-color: #16a34a;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
}
.campo-nav-btn.active.btn-tab-saude {
  background: #dc2626;
  border-color: #dc2626;
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
}
.campo-nav-btn.active.btn-tab-fila {
  background: #2563eb;
  border-color: #2563eb;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.campo-tab-pane {
  display: none;
  animation: fadeInPane 0.2s ease-in-out;
}
.campo-tab-pane.active {
  display: block;
}
@keyframes fadeInPane {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
.touch-input-lg {
  font-size: 1.15rem !important;
  font-weight: 600;
  padding: 12px 14px !important;
  border-radius: 10px;
}
.touch-btn-submit {
  padding: 14px 20px;
  font-size: 1.1rem;
  font-weight: 800;
  border-radius: 12px;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.12);
}
.foto-preview-box {
  display: none;
  position: relative;
  border-radius: 10px;
  overflow: hidden;
  max-height: 180px;
  margin-top: 8px;
  border: 2px dashed #cbd5e1;
  background: #f8fafc;
  text-align: center;
}
.foto-preview-box img {
  max-height: 175px;
  width: auto;
  max-width: 100%;
  object-fit: cover;
}
.foto-preview-remove {
  position: absolute;
  top: 6px;
  right: 6px;
  background: rgba(0,0,0,0.7);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}
@media (max-width: 576px) {
  .campo-nav-btn {
    font-size: 0.72rem;
    padding: 10px 2px;
  }
  .campo-nav-btn span.tab-icon {
    font-size: 1.25rem;
  }
}
</style>

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
          <small class="text-white text-opacity-75">Coleta autônoma 100% offline no pasto</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div id="connectionStatusBadge" class="badge bg-danger shadow-sm d-inline-flex align-items-center gap-1">
            <span class="status-dot offline"></span> Offline (Modo Campo)
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
      <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
        <i class="bi bi-download fs-5"></i>
      </div>
      <div>
        <h6 class="mb-0 fw-bold text-success">Instalar Aplicativo no Celular</h6>
        <small class="text-muted">Acesse em tela cheia direto da tela inicial</small>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-success fw-bold btn-sm px-3 shadow-sm" id="btnInstallPwa" onclick="triggerPwaInstall()">
        <i class="bi bi-phone-fill me-1"></i> Instalar
      </button>
      <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalComoInstalar">
        <i class="bi bi-question-circle me-1"></i> Como Instalar?
      </button>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- BARRA DE ABAS TÁTEIS (TOUCH TAB BAR) -->
<!-- ============================================================ -->
<div class="campo-nav-bar">
  <button type="button" class="campo-nav-btn btn-tab-pesagem active" id="tabBtn_pesagem" onclick="switchCampoTab('pesagem')">
    <span class="tab-icon">⚖️</span>
    <span>Pesagem</span>
  </button>
  <button type="button" class="campo-nav-btn btn-tab-bezerro" id="tabBtn_bezerro" onclick="switchCampoTab('bezerro')">
    <span class="tab-icon">🐣</span>
    <span>Bezerro</span>
  </button>
  <button type="button" class="campo-nav-btn btn-tab-saude" id="tabBtn_saude" onclick="switchCampoTab('saude')">
    <span class="tab-icon">⚕️</span>
    <span>Saúde</span>
  </button>
  <button type="button" class="campo-nav-btn btn-tab-fila" id="tabBtn_fila" onclick="switchCampoTab('fila')">
    <span class="tab-icon">📦</span>
    <span>Fila (<strong id="tabPendingBadge">0</strong>)</span>
  </button>
</div>

<!-- Datalist compartilhado com animais ativos para autocompletar -->
<datalist id="animaisListPwa">
  <?php foreach ($animais as $an): ?>
    <option value="<?= e($an['brinco']) ?>"><?= e($an['nome'] ? $an['nome'].' — ' : '') ?><?= e($an['raca'] ?? '') ?> (<?= $an['sexo']==='M'?'Macho':'Fêmea' ?>)</option>
  <?php endforeach; ?>
</datalist>

<!-- ============================================================ -->
<!-- ABA 1: REGISTRO DE PESAGEM RÁPIDA -->
<!-- ============================================================ -->
<div class="campo-tab-pane active" id="tabPane_pesagem">
  <div class="card border-warning border-2 shadow-sm mb-4">
    <div class="card-header bg-warning bg-opacity-25 py-3 d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <span class="fs-4">⚖️</span>
        <h5 class="mb-0 fw-bold text-dark">Registrar Pesagem</h5>
      </div>
      <span class="badge bg-warning text-dark fw-bold">Modo Rápido</span>
    </div>
    <div class="card-body p-3 p-md-4">
      <form id="formPwaPesagem" onsubmit="handlePwaPesagem(event)">
        <div class="mb-3">
          <label class="form-label fw-bold">Brinco do Animal *</label>
          <input type="text" id="p_brinco" class="form-control touch-input-lg" list="animaisListPwa" required placeholder="Digite ou selecione o brinco" autocomplete="off">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Peso Atual (kg) *</label>
          <input type="number" step="0.1" id="p_peso" class="form-control touch-input-lg" required placeholder="0.0" inputmode="decimal">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-bold small">Data da Pesagem</label>
            <input type="date" id="p_data" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold small">Observação</label>
            <input type="text" id="p_obs" class="form-control" placeholder="Pasto, lote, etc.">
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-bold small">📷 Foto da Pesagem (Opcional - Câmera ou Galeria)</label>
          <input type="file" id="p_foto" class="form-control" accept="image/*" onchange="handleFotoPreview(this, 'preview_p')">
          <div id="preview_p" class="foto-preview-box">
            <img src="" alt="Preview">
            <button type="button" class="foto-preview-remove" onclick="removeFotoPreview('p_foto', 'preview_p')"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-warning touch-btn-submit text-dark">
          <i class="bi bi-check-circle-fill"></i> Salvar Pesagem no Celular
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- ABA 2: NOVO BEZERRO / NASCIMENTO -->
<!-- ============================================================ -->
<div class="campo-tab-pane" id="tabPane_bezerro">
  <div class="card border-success border-2 shadow-sm mb-4">
    <div class="card-header bg-success text-white py-3 d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <span class="fs-4">🐣</span>
        <h5 class="mb-0 fw-bold text-white">Nascimento / Bezerro</h5>
      </div>
      <span class="badge bg-white text-success fw-bold">Memória de Filhote</span>
    </div>
    <div class="card-body p-3 p-md-4">
      <form id="formPwaBezerro" onsubmit="handlePwaBezerro(event)">
        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="form-label fw-bold">Brinco do Bezerro *</label>
            <input type="text" id="b_brinco" class="form-control touch-input-lg" required placeholder="Ex: BZ001" autocomplete="off">
          </div>
          <div class="col-5">
            <label class="form-label fw-bold">Sexo *</label>
            <select id="b_sexo" class="form-select touch-input-lg">
              <option value="M">♂ Macho</option>
              <option value="F">♀ Fêmea</option>
            </select>
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-bold small">Nome (Opcional)</label>
            <input type="text" id="b_nome" class="form-control" placeholder="Apelido/Nome">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold small">Raça</label>
            <input type="text" id="b_raca" class="form-control" value="Nelore">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold small">Data de Nascimento</label>
          <input type="date" id="b_data" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label fw-bold small text-success">🌱 Foto do Bezerro (Memória Permanente de Filhote)</label>
          <input type="file" id="b_foto" class="form-control" accept="image/*" onchange="handleFotoPreview(this, 'preview_b')">
          <div id="preview_b" class="foto-preview-box">
            <img src="" alt="Preview">
            <button type="button" class="foto-preview-remove" onclick="removeFotoPreview('b_foto', 'preview_b')"><i class="bi bi-x-lg"></i></button>
          </div>
          <small class="text-muted d-block mt-1">Ficará salva como foto de nascimento permanente no perfil do animal.</small>
        </div>
        <button type="submit" class="btn btn-success touch-btn-submit">
          <i class="bi bi-check-circle-fill"></i> Salvar Bezerro no Celular
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- ABA 3: EVENTO DE SAÚDE / MANEJO / ÓBITO -->
<!-- ============================================================ -->
<div class="campo-tab-pane" id="tabPane_saude">
  <div class="card border-danger border-2 shadow-sm mb-4">
    <div class="card-header bg-danger text-white py-3 d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <span class="fs-4">⚕️</span>
        <h5 class="mb-0 fw-bold text-white">Saúde / Manejo / Óbito</h5>
      </div>
      <span class="badge bg-white text-danger fw-bold">Clínico</span>
    </div>
    <div class="card-body p-3 p-md-4">
      <form id="formPwaSaude" onsubmit="handlePwaSaude(event)">
        <div class="mb-3">
          <label class="form-label fw-bold">Brinco do Animal *</label>
          <input type="text" id="s_brinco" class="form-control touch-input-lg" list="animaisListPwa" required placeholder="Selecione o brinco" autocomplete="off">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Tipo de Evento *</label>
          <select id="s_tipo" class="form-select touch-input-lg" onchange="toggleObitoSensivel(this.value)">
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
          <input type="text" id="s_desc" class="form-control" required placeholder="Ex: Febre aftosa, lesão no casco, etc.">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-bold small">Medicamento</label>
            <input type="text" id="s_med" class="form-control" placeholder="Opcional">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold small">Dose</label>
            <input type="text" id="s_dose" class="form-control" placeholder="Ex: 5ml">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold small">📷 Foto do Manejo / Laudo (Opcional)</label>
          <input type="file" id="s_foto" class="form-control" accept="image/*" onchange="handleFotoPreview(this, 'preview_s')">
          <div id="preview_s" class="foto-preview-box">
            <img src="" alt="Preview">
            <button type="button" class="foto-preview-remove" onclick="removeFotoPreview('s_foto', 'preview_s')"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
        <div class="form-check form-switch mb-4" id="sensivelSwitchGroup">
          <input class="form-check-input" type="checkbox" id="s_sensivel" value="1">
          <label class="form-check-label small fw-bold text-danger" for="s_sensivel">
            ⚠️ Censurar foto por padrão (Desfoque de proteção visual para óbito/ferimentos)
          </label>
        </div>
        <button type="submit" class="btn btn-danger touch-btn-submit">
          <i class="bi bi-check-circle-fill"></i> Salvar Registro de Saúde
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- ABA 4: MEMÓRIA LOCAL DO CELULAR & SINCRONIZAÇÃO -->
<!-- ============================================================ -->
<div class="campo-tab-pane" id="tabPane_fila">
  <div class="card border-primary border-2 shadow-sm mb-4">
    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <span class="fs-4">📦</span>
        <h5 class="mb-0 fw-bold text-white">Memória Local (<span id="pendingCount">0</span> pendências)</h5>
      </div>
      <button type="button" class="btn btn-light fw-bold text-primary shadow-sm" id="btnSyncNow" onclick="syncOfflineData()">
        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Sincronizar com a Nuvem
      </button>
    </div>
    <div class="card-body p-0">
      <ul class="list-group list-group-flush" id="pendingItemsList">
        <li class="list-group-item text-muted text-center py-4 small">
          <span class="spinner-border spinner-border-sm me-1"></span> Carregando registros da memória local...
        </li>
      </ul>
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
            <li class="mb-1">Toque no botão verde <strong>"Instalar"</strong> acima.</li>
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

<!-- ============================================================ -->
<!-- MODAL: AUTENTICAÇÃO PARA SINCRONIZAÇÃO -->
<!-- ============================================================ -->
<div class="modal fade" id="modalAuthSync" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold text-white"><i class="bi bi-shield-lock-fill me-2"></i>Sincronizar com a Nuvem</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAuthSync" onsubmit="handleAuthSync(event)">
        <div class="modal-body p-4">
          <p class="text-muted small mb-3">
            Informe suas credenciais da fazenda para autenticar e enviar os registros coletados para a nuvem:
          </p>
          <div class="mb-3">
            <label class="form-label fw-bold small">E-mail *</label>
            <input type="email" id="sync_email" class="form-control" required placeholder="admin@fazenda.com" autocomplete="username">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold small">Senha *</label>
            <input type="password" id="sync_senha" class="form-control" required placeholder="••••••••" autocomplete="current-password">
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="sync_salvar" checked>
            <label class="form-check-label small text-muted" for="sync_salvar">
              Lembrar credenciais neste celular para próximas sincronizações
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-bold px-4" id="btnConfirmSync">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Confirmar e Sincronizar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/assets/js/pwa-campo.js"></script>
<script>
// Alternância de Abas em JavaScript Puro (Zero Dependência Externa)
function switchCampoTab(tabId) {
  document.querySelectorAll('.campo-tab-pane').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.campo-nav-btn').forEach(el => el.classList.remove('active'));
  
  const targetPane = document.getElementById('tabPane_' + tabId);
  const targetBtn = document.getElementById('tabBtn_' + tabId);
  
  if (targetPane) targetPane.classList.add('active');
  if (targetBtn) targetBtn.classList.add('active');
  
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Manipuladores de Preview de Foto
function handleFotoPreview(input, previewBoxId) {
  const box = document.getElementById(previewBoxId);
  if (!box) return;
  const file = input.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      box.querySelector('img').src = e.target.result;
      box.style.display = 'block';
    };
    reader.readAsDataURL(file);
  } else {
    box.style.display = 'none';
  }
}

function removeFotoPreview(inputId, previewBoxId) {
  const input = document.getElementById(inputId);
  const box = document.getElementById(previewBoxId);
  if (input) input.value = '';
  if (box) {
    box.querySelector('img').src = '';
    box.style.display = 'none';
  }
}

// Manipuladores de submissão do PWA com tratamento de erro e feedback imediato
async function handlePwaPesagem(e) {
  e.preventDefault();
  try {
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
    
    // Limpa formulário e preview
    document.getElementById('formPwaPesagem').reset();
    removeFotoPreview('p_foto', 'preview_p');
    document.getElementById('p_data').value = new Date().toISOString().split('T')[0];

    if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
    showToast(`✅ Pesagem de ${peso}kg salva no celular!`, 'success');
  } catch (err) {
    console.error('Erro ao salvar pesagem:', err);
    showToast('⚠️ Erro ao salvar pesagem: ' + err.message, 'danger');
  }
}

async function handlePwaBezerro(e) {
  e.preventDefault();
  try {
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
    
    document.getElementById('formPwaBezerro').reset();
    removeFotoPreview('b_foto', 'preview_b');
    document.getElementById('b_data').value = new Date().toISOString().split('T')[0];
    document.getElementById('b_raca').value = 'Nelore';

    if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
    showToast(`✅ Bezerro ${brinco} cadastrado e salvo no celular!`, 'success');
  } catch (err) {
    console.error('Erro ao cadastrar bezerro:', err);
    showToast('⚠️ Erro ao salvar bezerro: ' + err.message, 'danger');
  }
}

async function handlePwaSaude(e) {
  e.preventDefault();
  try {
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
    
    document.getElementById('formPwaSaude').reset();
    removeFotoPreview('s_foto', 'preview_s');

    if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
    showToast(`✅ Evento de ${tipo} para ${brinco} salvo no celular!`, 'success');
  } catch (err) {
    console.error('Erro ao registrar saúde:', err);
    showToast('⚠️ Erro ao salvar saúde: ' + err.message, 'danger');
  }
}

function toggleObitoSensivel(val) {
  const check = document.getElementById('s_sensivel');
  if (check && val === 'Óbito') {
    check.checked = true;
  }
}
</script>
