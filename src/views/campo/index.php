<?php
// Consulta inicial de animais (para primeiro carregamento com rede)
$animaisIniciais = [];
try {
    $animaisIniciais = $db->query("SELECT brinco, nome, sexo, raca FROM animais WHERE status != 'morto' AND status != 'vendido' ORDER BY brinco")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#1a4d2e">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="PecuáriaGest">
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
  <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
  <title>Modo Campo — PecuáriaGest</title>
  
  <!-- CSS 100% Local (Zero Dependência Externa) -->
  <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  
  <style>
    :root {
      --green-dark: #1a4d2e;
      --green-mid: #2d7a4e;
      --green-light: #4caf78;
      --green-pale: #e8f5ee;
    }
    body {
      background: #f4f6f4;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      color: #2c3e2d;
      margin: 0;
      padding: 0;
      -webkit-tap-highlight-color: transparent;
      touch-action: manipulation;
    }
    input, textarea, select, button, .touch-input-lg, .campo-nav-btn {
      -webkit-user-select: text !important;
      user-select: text !important;
      touch-action: manipulation !important;
      pointer-events: auto !important;
    }
    .campo-nav-btn, button {
      -webkit-user-select: none !important;
      user-select: none !important;
    }
    .mobile-app-wrapper {
      max-width: 600px;
      margin: 0 auto;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: #ffffff;
      box-shadow: 0 0 20px rgba(0,0,0,0.06);
    }
    .mobile-top-header {
      background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4e 100%);
      color: #ffffff;
      padding: 1rem;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .mobile-content {
      padding: 1rem;
      flex: 1;
    }
    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }
    .status-dot.online { background-color: #10b981; box-shadow: 0 0 6px #10b981; }
    .status-dot.offline { background-color: #ef4444; box-shadow: 0 0 6px #ef4444; }

    /* Barra de Abas Táteis */
    .campo-nav-bar {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 6px;
      margin-bottom: 1rem;
    }
    .campo-nav-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 10px 4px;
      border-radius: 12px;
      border: 2px solid #e2e8f0;
      background: #ffffff;
      color: #475569;
      font-weight: 700;
      font-size: 0.78rem;
      cursor: pointer;
      transition: all 0.15s ease;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      text-align: center;
      touch-action: manipulation;
    }
    .campo-nav-btn span.tab-icon {
      font-size: 1.35rem;
      margin-bottom: 2px;
      line-height: 1;
    }
    .campo-nav-btn:active {
      transform: scale(0.96);
    }
    .campo-nav-btn.active.btn-tab-pesagem {
      background: #d97706;
      border-color: #d97706;
      color: #ffffff;
      box-shadow: 0 3px 10px rgba(217, 119, 6, 0.25);
    }
    .campo-nav-btn.active.btn-tab-bezerro {
      background: #16a34a;
      border-color: #16a34a;
      color: #ffffff;
      box-shadow: 0 3px 10px rgba(22, 163, 74, 0.25);
    }
    .campo-nav-btn.active.btn-tab-saude {
      background: #dc2626;
      border-color: #dc2626;
      color: #ffffff;
      box-shadow: 0 3px 10px rgba(220, 38, 38, 0.25);
    }
    .campo-nav-btn.active.btn-tab-fila {
      background: #2563eb;
      border-color: #2563eb;
      color: #ffffff;
      box-shadow: 0 3px 10px rgba(37, 99, 235, 0.25);
    }
    .campo-tab-pane {
      display: none;
    }
    .campo-tab-pane.active {
      display: block;
    }
    .touch-input-lg {
      font-size: 1.15rem !important;
      font-weight: 600;
      padding: 12px 14px !important;
      border-radius: 10px;
    }
    .touch-btn-submit {
      padding: 14px 20px;
      font-size: 1.05rem;
      font-weight: 800;
      border-radius: 12px;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
      touch-action: manipulation;
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
      background: rgba(0,0,0,0.75);
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
  </style>
</head>
<body>

<div class="mobile-app-wrapper">

  <!-- Header Superior Mobile -->
  <header class="mobile-top-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-phone-fill fs-4 text-white"></i>
        <div>
          <h5 class="mb-0 fw-bold text-white">PecuáriaGest Campo</h5>
          <small class="text-white text-opacity-75" style="font-size: 0.72rem;">Coleta 100% autônoma no pasto</small>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div id="connectionStatusBadge" class="badge bg-danger shadow-sm d-inline-flex align-items-center gap-1">
          <span class="status-dot offline"></span> Offline (Modo Campo)
        </div>
        <a href="/login" class="btn btn-sm btn-outline-light py-1 px-2" style="font-size:0.75rem" title="Acessar Painel">
          <i class="bi bi-box-arrow-in-right"></i> Painel
        </a>
      </div>
    </div>
  </header>

  <!-- Conteúdo Principal do App Shell -->
  <main class="mobile-content">

    <!-- Card de Instalação do Aplicativo (PWA) -->
    <div id="pwaInstallCard" class="card mb-3 border-success border-2 shadow-sm" style="background: #e8f5ee; display:none;">
      <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;">
            <i class="bi bi-download fs-5"></i>
          </div>
          <div>
            <h6 class="mb-0 fw-bold text-success" style="font-size:0.9rem;">Instalar Aplicativo</h6>
            <small class="text-muted" style="font-size:0.75rem;">Acesse direto da tela inicial</small>
          </div>
        </div>
        <div class="d-flex gap-1">
          <button type="button" class="btn btn-success fw-bold btn-sm px-2 py-1 shadow-sm" id="btnInstallPwa" onclick="triggerPwaInstall()" style="font-size:0.8rem;">
            <i class="bi bi-phone-fill me-1"></i> Instalar
          </button>
          <button type="button" class="btn btn-outline-success btn-sm px-2 py-1" data-bs-toggle="modal" data-bs-target="#modalComoInstalar" style="font-size:0.8rem;">
            <i class="bi bi-question-circle"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Barra de Abas Táteis (Touch Tab Bar) -->
    <div class="campo-nav-bar">
      <button type="button" class="campo-nav-btn btn-tab-pesagem active" id="tabBtn_pesagem" onclick="switchCampoTab('pesagem')">
        <i class="bi bi-rulers tab-icon fs-5"></i>
        <span>Pesagem</span>
      </button>
      <button type="button" class="campo-nav-btn btn-tab-bezerro" id="tabBtn_bezerro" onclick="switchCampoTab('bezerro')">
        <i class="bi bi-stars tab-icon fs-5"></i>
        <span>Bezerro</span>
      </button>
      <button type="button" class="campo-nav-btn btn-tab-saude" id="tabBtn_saude" onclick="switchCampoTab('saude')">
        <i class="bi bi-heart-pulse-fill tab-icon fs-5"></i>
        <span>Saúde</span>
      </button>
      <button type="button" class="campo-nav-btn btn-tab-fila" id="tabBtn_fila" onclick="switchCampoTab('fila')">
        <i class="bi bi-box-seam-fill tab-icon fs-5"></i>
        <span>Fila (<strong id="tabPendingBadge">0</strong>)</span>
      </button>
    </div>

    <!-- Datalist para autocompletar de brincos (populado via IndexedDB) -->
    <datalist id="animaisListPwa">
      <?php foreach ($animaisIniciais as $an): ?>
        <option value="<?= htmlspecialchars($an['brinco']) ?>"><?= htmlspecialchars($an['nome'] ? $an['nome'].' — ' : '') ?><?= htmlspecialchars($an['raca'] ?? '') ?> (<?= $an['sexo']==='M'?'Macho':'Fêmea' ?>)</option>
      <?php endforeach; ?>
    </datalist>

    <!-- ============================================================ -->
    <!-- ABA 1: REGISTRO DE PESAGEM RÁPIDA -->
    <!-- ============================================================ -->
    <div class="campo-tab-pane active" id="tabPane_pesagem">
      <div class="card border-warning border-2 shadow-sm mb-4">
        <div class="card-header bg-warning bg-opacity-25 py-2 px-3 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-rulers fs-5 text-dark"></i>
            <h6 class="mb-0 fw-bold text-dark">Registrar Pesagem</h6>
          </div>
          <span class="badge bg-warning text-dark fw-bold">Modo Rápido</span>
        </div>
        <div class="card-body p-3">
          <form id="formPwaPesagem" onsubmit="handlePwaPesagem(event)">
            <div class="mb-3">
              <label class="form-label fw-bold small">Brinco do Animal *</label>
              <input type="text" id="p_brinco" class="form-control touch-input-lg" list="animaisListPwa" required placeholder="Digite ou selecione o brinco" autocomplete="off">
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Peso Atual (kg) *</label>
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
            <div class="mb-3">
              <label class="form-label fw-bold small"><i class="bi bi-camera-fill me-1"></i> Foto da Pesagem (Câmera ou Galeria)</label>
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
        <div class="card-header bg-success text-white py-2 px-3 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-stars fs-5 text-white"></i>
            <h6 class="mb-0 fw-bold text-white">Nascimento / Bezerro</h6>
          </div>
          <span class="badge bg-white text-success fw-bold">Memória de Filhote</span>
        </div>
        <div class="card-body p-3">
          <form id="formPwaBezerro" onsubmit="handlePwaBezerro(event)">
            <div class="row g-2 mb-3">
              <div class="col-7">
                <label class="form-label fw-bold small">Brinco do Bezerro *</label>
                <input type="text" id="b_brinco" class="form-control touch-input-lg" required placeholder="Ex: BZ001" autocomplete="off">
              </div>
              <div class="col-5">
                <label class="form-label fw-bold small">Sexo *</label>
                <select id="b_sexo" class="form-select touch-input-lg">
                  <option value="M">Macho</option>
                  <option value="F">Fêmea</option>
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
            <div class="mb-3">
              <label class="form-label fw-bold small text-success"><i class="bi bi-stars me-1"></i> Foto do Bezerro (Memória de Filhote)</label>
              <input type="file" id="b_foto" class="form-control" accept="image/*" onchange="handleFotoPreview(this, 'preview_b')">
              <div id="preview_b" class="foto-preview-box">
                <img src="" alt="Preview">
                <button type="button" class="foto-preview-remove" onclick="removeFotoPreview('b_foto', 'preview_b')"><i class="bi bi-x-lg"></i></button>
              </div>
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
        <div class="card-header bg-danger text-white py-2 px-3 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-heart-pulse-fill fs-5 text-white"></i>
            <h6 class="mb-0 fw-bold text-white">Saúde / Manejo / Óbito</h6>
          </div>
          <span class="badge bg-white text-danger fw-bold">Clínico</span>
        </div>
        <div class="card-body p-3">
          <form id="formPwaSaude" onsubmit="handlePwaSaude(event)">
            <div class="mb-3">
              <label class="form-label fw-bold small">Brinco do Animal *</label>
              <input type="text" id="s_brinco" class="form-control touch-input-lg" list="animaisListPwa" required placeholder="Selecione o brinco" autocomplete="off">
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Tipo de Evento *</label>
              <select id="s_tipo" class="form-select touch-input-lg" onchange="toggleObitoSensivel(this.value)">
                <option value="Vacinação">Vacinação</option>
                <option value="Tratamento">Tratamento</option>
                <option value="Curativo">Curativo</option>
                <option value="Vermifugação">Vermifugação</option>
                <option value="Exame">Exame</option>
                <option value="Óbito">Óbito / Morte do Animal</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Descrição / Motivo *</label>
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
              <label class="form-label fw-bold small"><i class="bi bi-camera-fill me-1"></i> Foto do Manejo / Laudo (Opcional)</label>
              <input type="file" id="s_foto" class="form-control" accept="image/*" onchange="handleFotoPreview(this, 'preview_s')">
              <div id="preview_s" class="foto-preview-box">
                <img src="" alt="Preview">
                <button type="button" class="foto-preview-remove" onclick="removeFotoPreview('s_foto', 'preview_s')"><i class="bi bi-x-lg"></i></button>
              </div>
            </div>
            <div class="form-check form-switch mb-3" id="sensivelSwitchGroup">
              <input class="form-check-input" type="checkbox" id="s_sensivel" value="1">
              <label class="form-check-label small fw-bold text-danger" for="s_sensivel">
                <i class="bi bi-eye-slash-fill me-1"></i> Censurar foto por padrão (Desfoque de proteção visual)
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
        <div class="card-header bg-primary text-white py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-box-seam-fill fs-5 text-white"></i>
            <h6 class="mb-0 fw-bold text-white">Memória Local (<span id="pendingCount">0</span> pendências)</h6>
          </div>
          <button type="button" class="btn btn-light fw-bold text-primary btn-sm shadow-sm" id="btnSyncNow" onclick="syncOfflineData()">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Sincronizar
          </button>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush" id="pendingItemsList">
            <li class="list-group-item text-muted text-center py-4 small">
              <span class="spinner-border spinner-border-sm me-1"></span> Carregando registros locais...
            </li>
          </ul>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- ============================================================ -->
<!-- MODAL: COMO INSTALAR O APLICATIVO -->
<!-- ============================================================ -->
<div class="modal fade" id="modalComoInstalar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h6 class="modal-title fw-bold"><i class="bi bi-phone-fill me-2"></i>Como Instalar o Aplicativo</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <div class="mb-3">
          <h6 class="fw-bold text-success d-flex align-items-center gap-2 mb-1" style="font-size:0.9rem;">
            <i class="bi bi-android2 fs-5"></i> No Android (Chrome / Edge)
          </h6>
          <ol class="small text-secondary ps-3 mb-0">
            <li class="mb-1">Toque no botão verde <strong>"Instalar"</strong> acima.</li>
            <li class="mb-1">Ou toque nos <strong>3 pontinhos (⋮)</strong> no canto superior direito do navegador.</li>
            <li>Selecione <strong>"Instalar aplicativo"</strong> ou <strong>"Adicionar à tela inicial"</strong>.</li>
          </ol>
        </div>
        <hr class="my-2">
        <div class="mb-2">
          <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-1" style="font-size:0.9rem;">
            <i class="bi bi-apple fs-5"></i> No iPhone / iPad (Safari)
          </h6>
          <ol class="small text-secondary ps-3 mb-0">
            <li class="mb-1">Abra este link no navegador <strong>Safari</strong>.</li>
            <li class="mb-1">Toque no botão <strong>Compartilhar</strong> (<i class="bi bi-box-arrow-up text-primary"></i> no rodapé).</li>
            <li>Toque em <strong>"Adicionar à Tela de Início"</strong>.</li>
          </ol>
        </div>
      </div>
      <div class="modal-footer p-2">
        <button type="button" class="btn btn-success fw-bold w-100 btn-sm" data-bs-dismiss="modal">Entendi, vamos lá!</button>
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
      <div class="modal-header bg-primary text-white py-2 px-3">
        <h6 class="modal-title fw-bold text-white"><i class="bi bi-shield-lock-fill me-2"></i>Sincronizar com a Nuvem</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAuthSync" onsubmit="handleAuthSync(event)">
        <div class="modal-body p-3">
          <p class="text-muted small mb-3">
            Para descarregar os dados coletados no servidor central, informe suas credenciais da fazenda:
          </p>
          <div class="mb-2">
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
              Lembrar credenciais neste celular
            </label>
          </div>
        </div>
        <div class="modal-footer p-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm fw-bold px-3" id="btnConfirmSync">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Confirmar e Sincronizar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts 100% Locais -->
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/js/pwa-campo.js"></script>
<script>
// Alternância de Abas em JavaScript Puro
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

// Manipuladores de Submissão de Formulários no IndexedDB
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
    
    document.getElementById('formPwaPesagem').reset();
    removeFotoPreview('p_foto', 'preview_p');
    document.getElementById('p_data').value = new Date().toISOString().split('T')[0];

    if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
    showToast(`Pesagem de ${peso}kg salva no celular!`, 'success');
  } catch (err) {
    console.error('Erro ao salvar pesagem:', err);
    showToast('Erro ao salvar pesagem: ' + err.message, 'danger');
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
    showToast(`Bezerro ${brinco} cadastrado e salvo no celular!`, 'success');
  } catch (err) {
    console.error('Erro ao cadastrar bezerro:', err);
    showToast('Erro ao salvar bezerro: ' + err.message, 'danger');
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
    showToast(`Evento de ${tipo} para ${brinco} salvo no celular!`, 'success');
  } catch (err) {
    console.error('Erro ao registrar saúde:', err);
    showToast('Erro ao salvar saúde: ' + err.message, 'danger');
  }
}

function toggleObitoSensivel(val) {
  const check = document.getElementById('s_sensivel');
  if (check && val === 'Óbito') {
    check.checked = true;
  }
}
</script>
</body>
</html>
