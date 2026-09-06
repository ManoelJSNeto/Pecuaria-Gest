// ============================================================
// PecuáriaGest App Nativo — Motor Offline, Auto-Sync & UX Tátil
// ============================================================

const STORAGE_QUEUE_KEY = 'pecuaria_native_queue';
const STORAGE_ANIMALS_KEY = 'pecuaria_native_animals';
const STORAGE_SERVER_KEY = 'pecuaria_native_server_url';
const STORAGE_AUTH_KEY = 'pecuaria_native_auth';
const STORAGE_AUTO_SYNC_KEY = 'pecuaria_native_auto_sync';
const STORAGE_DARK_MODE_KEY = 'pecuaria_native_dark_mode';

let isSyncing = false;
let isServerOnline = false;

// ── Resposta Tátil Robusta (Capacitor Nativo Haptics + Fallback Web) ──
async function triggerHapticFeedback(type = 'save') {
  // 1. Tenta Capacitor Plugins Haptics (Nativo Android / Java Vibrator)
  try {
    const haptics = window.Capacitor?.Plugins?.Haptics;
    if (haptics) {
      if (type === 'save') {
        await haptics.notification({ type: 'SUCCESS' }).catch(() => {});
        await haptics.vibrate({ duration: 300 }).catch(() => {});
        return;
      } else if (type === 'tap') {
        await haptics.impact({ style: 'HEAVY' }).catch(() => {});
        return;
      } else if (type === 'warning') {
        await haptics.notification({ type: 'WARNING' }).catch(() => {});
        return;
      }
    }
  } catch (e) {}

  // 2. Fallback via Navigator Vibrate padrão (com pulso perceptível no curral)
  try {
    if (navigator.vibrate) {
      if (type === 'save') {
        navigator.vibrate([250, 100, 250]);
      } else if (type === 'tap') {
        navigator.vibrate(80);
      } else if (type === 'warning') {
        navigator.vibrate([150, 80, 150]);
      }
    }
  } catch (e) {}
}

// ── 1. Modo Escuro & Tema ──────────────────────────────────────
function initDarkMode() {
  const savedTheme = localStorage.getItem(STORAGE_DARK_MODE_KEY);
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const isDark = savedTheme === 'true' || (savedTheme === null && prefersDark);
  
  applyDarkMode(isDark);
}

function applyDarkMode(isDark) {
  const icon = document.getElementById('darkModeIcon');
  if (isDark) {
    document.body.classList.add('dark-mode');
    if (icon) icon.className = 'bi bi-sun-fill text-warning';
  } else {
    document.body.classList.remove('dark-mode');
    if (icon) icon.className = 'bi bi-moon-stars-fill';
  }
}

function toggleDarkMode() {
  const isDark = !document.body.classList.contains('dark-mode');
  localStorage.setItem(STORAGE_DARK_MODE_KEY, isDark ? 'true' : 'false');
  applyDarkMode(isDark);
  triggerHapticFeedback('tap');
}

// ── 2. Configurações de Servidor & Auto-Sync ───────────────────
function getServerUrl() {
  return localStorage.getItem(STORAGE_SERVER_KEY) || 'http://localhost:8080';
}

function setServerUrl(url) {
  let cleanUrl = (url || '').trim();
  if (cleanUrl.endsWith('/')) {
    cleanUrl = cleanUrl.slice(0, -1);
  }
  localStorage.setItem(STORAGE_SERVER_KEY, cleanUrl);
}

function isAutoSyncEnabled() {
  return localStorage.getItem(STORAGE_AUTO_SYNC_KEY) !== 'false';
}

function setAutoSyncEnabled(enabled) {
  localStorage.setItem(STORAGE_AUTO_SYNC_KEY, enabled ? 'true' : 'false');
}

function setQuickServer(url) {
  const inputEl = document.getElementById('server_api_url');
  if (inputEl) {
    inputEl.value = url;
    testServerConnectionUI();
  }
}

async function testServerConnectionUI() {
  const inputEl = document.getElementById('server_api_url');
  const feedbackEl = document.getElementById('testConnectionFeedback');
  const btnTest = document.getElementById('btnTestarConexao');
  
  if (!inputEl || !feedbackEl) return;
  const testUrl = (inputEl.value || '').trim().replace(/\/$/, '');
  
  if (!testUrl) {
    feedbackEl.style.display = 'block';
    feedbackEl.className = 'mt-2 small text-danger fw-bold';
    feedbackEl.innerHTML = '<i class="bi bi-exclamation-circle-fill me-1"></i> Informe uma URL válida para testar.';
    return;
  }

  feedbackEl.style.display = 'block';
  feedbackEl.className = 'mt-2 small text-muted';
  feedbackEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testando comunicação com o servidor...';
  if (btnTest) btnTest.disabled = true;

  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 4000);
    const res = await fetch(`${testUrl}/api/animais`, {
      method: 'GET',
      cache: 'no-store',
      headers: { 'X-API-KEY': 'pecuaria-mobile-key' },
      signal: controller.signal
    });
    clearTimeout(timer);

    if (res.ok) {
      const data = await res.json();
      const count = data.animais ? data.animais.length : (Array.isArray(data) ? data.length : 0);
      feedbackEl.className = 'mt-2 small text-success fw-bold';
      feedbackEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Conexão bem-sucedida! (${count} animais disponíveis na central).`;
      if (count > 0 && data.animais) {
        setCachedAnimals(data.animais);
      }
    } else {
      feedbackEl.className = 'mt-2 small text-warning fw-bold';
      feedbackEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> Servidor respondeu com HTTP ${res.status}.`;
    }
  } catch (err) {
    feedbackEl.className = 'mt-2 small text-danger fw-bold';
    feedbackEl.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Falha na conexão. Verifique se o endereço e a porta estão corretos.';
  } finally {
    if (btnTest) btnTest.disabled = false;
  }
}

// ── 3. Fila Offline Local ──────────────────────────────────────
function getLocalQueue() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_QUEUE_KEY) || '[]');
  } catch (e) {
    return [];
  }
}

function saveLocalQueue(items) {
  localStorage.setItem(STORAGE_QUEUE_KEY, JSON.stringify(items));
  updatePendingBadge();
  renderQueueCards();
}

function addToQueue(tipo, data, fotoBase64 = null) {
  const items = getLocalQueue();
  const newItem = {
    id: Date.now() + '_' + Math.random().toString(36).substr(2, 5),
    tipo: tipo, // 'pesagem' | 'saude' | 'animal'
    data: data,
    foto_base64: fotoBase64,
    criado_em: new Date().toISOString()
  };
  items.push(newItem);
  saveLocalQueue(items);

  // Se for novo animal, atualiza cache local de autocompletar
  if (tipo === 'animal' && data.brinco) {
    saveAnimalToCache(data);
  }

  // Dispara Auto-Sync imediatamente se habilitado
  if (isAutoSyncEnabled()) {
    setTimeout(triggerAutoSync, 500);
  }
}

function removeSingleItemFromQueue(id) {
  if (!confirm('Deseja excluir este registro da fila offline?')) return;
  const items = getLocalQueue().filter(it => it.id !== id);
  saveLocalQueue(items);
  triggerHapticFeedback('tap');
  showToast('Registro removido da fila local.', 'info');
}

function removeItemsFromQueue(ids) {
  const items = getLocalQueue().filter(it => !ids.includes(it.id));
  saveLocalQueue(items);
}

// ── 4. Cache Local de Animais para Autocompletar ───────────────
function getCachedAnimals() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_ANIMALS_KEY) || '[]');
  } catch (e) {
    return [];
  }
}

function saveAnimalToCache(animal) {
  const list = getCachedAnimals();
  const exists = list.find(a => a.brinco === animal.brinco);
  if (!exists) {
    list.push(animal);
    localStorage.setItem(STORAGE_ANIMALS_KEY, JSON.stringify(list));
  }
}

function setCachedAnimals(animals) {
  localStorage.setItem(STORAGE_ANIMALS_KEY, JSON.stringify(animals));
}

// ── 4.1 Autocomplete Tátil Compacto com Rolagem (Substitui Datalist) ──
function handleEarringFocus(input, dropdownId, nextFocusId) {
  renderEarringDropdown(dropdownId, input.id, input.value.trim(), nextFocusId);
}

function handleEarringInput(input, dropdownId, nextFocusId) {
  input.value = input.value.toUpperCase();
  renderEarringDropdown(dropdownId, input.id, input.value.trim(), nextFocusId);
}

function renderEarringDropdown(dropdownId, inputId, query, nextFocusId) {
  const dropdown = document.getElementById(dropdownId);
  if (!dropdown) return;

  const animals = getCachedAnimals();
  const q = (query || '').toUpperCase();

  // Filtra por brinco ou nome
  const matches = animals.filter(a => 
    (a.brinco && a.brinco.toUpperCase().includes(q)) ||
    (a.nome && a.nome.toUpperCase().includes(q))
  );

  if (matches.length === 0) {
    dropdown.innerHTML = `
      <div class="earring-empty-hint">
        ${q ? `Nenhum animal cadastrado com brinco "<strong>${q}</strong>".<br><span class="text-success fw-bold">Pode prosseguir para novo registro.</span>` : 'Nenhum animal na memória local.'}
      </div>
    `;
    dropdown.style.display = 'block';
    return;
  }

  // Renderiza correspondências com altura travada em 185px (rolagem suave)
  dropdown.innerHTML = matches.slice(0, 40).map(a => `
    <button type="button" class="earring-item-btn" onclick="selectEarring('${dropdownId}', '${inputId}', '${a.brinco}', '${nextFocusId || ''}')">
      <span class="earring-item-code">
        <i class="bi bi-tag-fill text-success" style="font-size: 0.95rem;"></i>
        ${a.brinco}
        ${a.nome ? `<span class="text-secondary fw-normal ms-1" style="font-size: 0.85rem;">(${a.nome})</span>` : ''}
      </span>
      <span class="earring-item-meta">
        ${a.raca || 'Nelore'} • ${a.sexo === 'M' ? 'M' : 'F'}
      </span>
    </button>
  `).join('');

  dropdown.style.display = 'block';
}

function selectEarring(dropdownId, inputId, brinco, nextFocusId) {
  const input = document.getElementById(inputId);
  if (input) {
    input.value = brinco;
  }
  
  const dropdown = document.getElementById(dropdownId);
  if (dropdown) {
    dropdown.style.display = 'none';
  }

  // Resposta tátil instantânea
  triggerHapticFeedback('tap');

  // Pula foco automaticamente para o próximo campo
  if (nextFocusId) {
    const nextEl = document.getElementById(nextFocusId);
    if (nextEl) {
      setTimeout(() => {
        nextEl.focus();
        if (nextEl.select) nextEl.select();
      }, 120);
    }
  }
}

function clearEarringInput(inputId, dropdownId) {
  const input = document.getElementById(inputId);
  if (input) {
    input.value = '';
    input.focus();
  }
  const dropdown = document.getElementById(dropdownId);
  if (dropdown) {
    dropdown.style.display = 'none';
  }
}

// ── 5. Compressão de Fotos com Canvas ──────────────────────────
function compressImage(file, maxWidth = 1000, maxHeight = 1000, quality = 0.7) {
  return new Promise((resolve) => {
    if (!file || !file.type.startsWith('image/')) {
      resolve(null);
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        try {
          let width = img.width;
          let height = img.height;

          if (width > height) {
            if (width > maxWidth) {
              height = Math.round((height * maxWidth) / width);
              width = maxWidth;
            }
          } else {
            if (height > maxHeight) {
              width = Math.round((width * maxHeight) / height);
              height = maxHeight;
            }
          }

          const canvas = document.createElement('canvas');
          canvas.width = width;
          canvas.height = height;

          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, width, height);

          const dataUrl = canvas.toDataURL('image/jpeg', quality);
          resolve(dataUrl);
        } catch (err) {
          resolve(e.target.result);
        }
      };
      img.onerror = () => resolve(e.target.result);
      img.src = e.target.result;
    };
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}

// ── 6. Verificação de Conectividade com o Servidor ─────────────
function renderStatusBadge(online) {
  const badge = document.getElementById('connectionStatusBadge');
  if (!badge) return;

  if (online) {
    badge.className = 'badge bg-success d-inline-flex align-items-center gap-1 shadow-sm py-1 px-2 text-white';
    badge.innerHTML = '<span class="status-dot online"></span> Online';
  } else {
    badge.className = 'badge bg-danger d-inline-flex align-items-center gap-1 shadow-sm py-1 px-2 text-white';
    badge.innerHTML = '<span class="status-dot offline"></span> Offline';
  }
}

async function checkServerConnectivity() {
  const serverUrl = getServerUrl();
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 3500);

    const res = await fetch(`${serverUrl}/api/animais`, {
      method: 'GET',
      cache: 'no-store',
      headers: { 'X-API-KEY': 'pecuaria-mobile-key' },
      signal: controller.signal
    });
    clearTimeout(timer);

    if (res.ok) {
      const data = await res.json();
      const animais = data.animais || data || [];
      if (animais.length > 0) {
        setCachedAnimals(animais);
      }
      if (!isServerOnline) {
        showToast('Conectado ao servidor central da fazenda!', 'success');
      }
      isServerOnline = true;
      renderStatusBadge(true);

      // Dispara Auto-Sync se houver registros na fila
      if (isAutoSyncEnabled()) {
        triggerAutoSync();
      }
      return true;
    } else {
      isServerOnline = false;
      renderStatusBadge(false);
      return false;
    }
  } catch (e) {
    isServerOnline = false;
    renderStatusBadge(false);
    return false;
  }
}

// ── 7. Renderização Visual da Fila ─────────────────────────────
function updatePendingBadge() {
  const items = getLocalQueue();
  const count = items.length;

  const countEl = document.getElementById('pendingCount');
  const tabBadgeEl = document.getElementById('tabPendingBadge');
  const btnSync = document.getElementById('btnSyncNow');

  if (countEl) countEl.textContent = count;
  if (tabBadgeEl) tabBadgeEl.textContent = count;

  if (btnSync) {
    btnSync.disabled = (count === 0);
    btnSync.innerHTML = count > 0 
      ? `<i class="bi bi-cloud-arrow-up-fill me-1"></i> Sincronizar (${count})`
      : `<i class="bi bi-check2-all me-1"></i> Tudo Sincronizado`;
  }
}

function renderQueueCards() {
  const container = document.getElementById('pendingItemsContainer');
  if (!container) return;

  const items = getLocalQueue();
  if (items.length === 0) {
    container.innerHTML = `
      <div class="text-center py-5 text-muted">
        <i class="bi bi-check2-circle text-success fs-1 d-block mb-2"></i>
        <h6 class="fw-bold mb-1">Nenhum registro pendente</h6>
        <p class="small text-muted mb-0">Todos os dados coletados já foram sincronizados com a central.</p>
      </div>
    `;
    return;
  }

  let html = '';
  items.forEach((it) => {
    let iconClass = 'queue-icon-pesagem';
    let icon = 'bi-rulers';
    let title = `Pesagem: ${it.data.brinco || 'Animal'} — ${it.data.peso || 0} kg`;
    let sub = `Data: ${it.data.data || '-'} ${it.data.observacao ? '• ' + it.data.observacao : ''}`;

    if (it.tipo === 'animal') {
      iconClass = 'queue-icon-animal';
      icon = 'bi-stars';
      title = `Novo Bezerro: ${it.data.brinco || 'Sem brinco'}`;
      sub = `${it.data.sexo === 'M' ? 'Macho' : 'Fêmea'} • Raça: ${it.data.raca || 'Nelore'} • Nasc: ${it.data.data_nascimento || '-'}`;
    } else if (it.tipo === 'saude') {
      iconClass = 'queue-icon-saude';
      icon = 'bi-heart-pulse-fill';
      title = `Saúde: ${it.data.brinco || 'Animal'} — ${it.data.tipo || 'Tratamento'}`;
      sub = `${it.data.descricao || ''} ${it.data.medicamento ? '• Med: ' + it.data.medicamento : ''}`;
    }

    const timeStr = new Date(it.criado_em).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const hasPhoto = it.foto_base64 ? `
      <img src="${it.foto_base64}" class="queue-thumb" alt="Foto">
    ` : `
      <div class="queue-icon-box ${iconClass}">
        <i class="bi ${icon}"></i>
      </div>
    `;

    html += `
      <div class="queue-card tipo-${it.tipo}">
        ${hasPhoto}
        <div class="queue-info">
          <div class="queue-title">${title}</div>
          <div class="queue-sub">${sub}</div>
          <div class="queue-sub mt-1 text-muted" style="font-size:0.72rem;">Salvo às ${timeStr}</div>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm border-0 p-2" onclick="removeSingleItemFromQueue('${it.id}')" title="Excluir da fila">
          <i class="bi bi-trash-fill fs-5"></i>
        </button>
      </div>
    `;
  });

  container.innerHTML = html;
}

// ── 8. Funções de Auxílio Local & Gerenciamento de Sessão ─────────
const DEFAULT_ADMIN_PIN = '1234';

function openAdminPinModal() {
  const pinInput = document.getElementById('admin_pin_input');
  if (pinInput) pinInput.value = '';
  const errorEl = document.getElementById('pinErrorFeedback');
  if (errorEl) errorEl.style.display = 'none';

  const modalEl = document.getElementById('modalPinAdmin');
  if (modalEl && typeof bootstrap !== 'undefined') {
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    setTimeout(() => pinInput && pinInput.focus(), 300);
  }
}

function handleVerifyAdminPin(e) {
  e.preventDefault();
  const inputPin = document.getElementById('admin_pin_input').value.trim();
  const savedPin = localStorage.getItem('pecuaria_admin_pin') || DEFAULT_ADMIN_PIN;

  if (inputPin === savedPin || inputPin === DEFAULT_ADMIN_PIN) {
    const modalPinEl = document.getElementById('modalPinAdmin');
    if (modalPinEl) bootstrap.Modal.getInstance(modalPinEl)?.hide();
    triggerHapticFeedback('tap');
    openServerConfigModal();
  } else {
    triggerHapticFeedback('warning');
    const errorEl = document.getElementById('pinErrorFeedback');
    if (errorEl) errorEl.style.display = 'block';
  }
}

function getSavedOperator() {
  const savedAuthStr = localStorage.getItem(STORAGE_AUTH_KEY);
  if (!savedAuthStr) return null;
  try {
    const auth = JSON.parse(savedAuthStr);
    return (auth && auth.email) ? auth : null;
  } catch (e) {
    return null;
  }
}

function updateOperatorUI() {
  const op = getSavedOperator();
  const badge = document.getElementById('operatorEmailBadge');
  if (badge) {
    if (op && op.email) {
      badge.textContent = op.email;
      badge.className = 'text-success fw-bold';
    } else {
      badge.textContent = 'Nenhum logado (clique p/ entrar)';
      badge.className = 'text-muted';
    }
  }

  // Preenche campos do modal de login se já houver credencial
  if (op && op.email) {
    const emailInput = document.getElementById('sync_email');
    const senhaInput = document.getElementById('sync_senha');
    if (emailInput && !emailInput.value) emailInput.value = op.email;
    if (senhaInput && !senhaInput.value) senhaInput.value = op.senha || '';
  }
}

function logoutCurrentOperator() {
  if (!confirm('Deseja desconectar este usuário do celular?')) return;
  localStorage.removeItem(STORAGE_AUTH_KEY);
  updateOperatorUI();
  triggerHapticFeedback('tap');
  showToast('Sessão encerrada neste dispositivo.', 'info');
}

// ── 9. Modais e Ações de Sincronização ─────────────────────────
function openServerConfigModal() {
  const modalEl = document.getElementById('modalConfigServidor');
  const inputEl = document.getElementById('server_api_url');
  const autoSyncEl = document.getElementById('config_auto_sync');
  const feedbackEl = document.getElementById('testConnectionFeedback');
  
  if (inputEl) inputEl.value = getServerUrl();
  if (autoSyncEl) autoSyncEl.checked = isAutoSyncEnabled();
  if (feedbackEl) feedbackEl.style.display = 'none';

  if (modalEl && typeof bootstrap !== 'undefined') {
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function handleSaveServerConfig(e) {
  e.preventDefault();
  const inputEl = document.getElementById('server_api_url');
  const autoSyncEl = document.getElementById('config_auto_sync');

  if (inputEl && inputEl.value) {
    setServerUrl(inputEl.value);
  }
  if (autoSyncEl) {
    setAutoSyncEnabled(autoSyncEl.checked);
  }

  showToast('Configurações salvas com sucesso!', 'success');
  const modalEl = document.getElementById('modalConfigServidor');
  if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
  checkServerConnectivity();
}

function openAuthSyncModal() {
  const modalEl = document.getElementById('modalAuthSync');
  if (modalEl && typeof bootstrap !== 'undefined') {
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function closeAuthSyncModal() {
  const modalEl = document.getElementById('modalAuthSync');
  if (modalEl && typeof bootstrap !== 'undefined') {
    bootstrap.Modal.getInstance(modalEl)?.hide();
  }
}

function showSyncProgress(show, title = '', subtitle = '', status = '') {
  const overlay = document.getElementById('syncProgressOverlay');
  const titleEl = document.getElementById('syncProgressTitle');
  const subEl = document.getElementById('syncProgressSubtitle');
  const statusEl = document.getElementById('syncProgressStatus');
  
  if (!overlay) return;
  overlay.style.display = show ? 'flex' : 'none';
  if (title && titleEl) titleEl.textContent = title;
  if (subtitle && subEl) subEl.textContent = subtitle;
  if (status && statusEl) statusEl.textContent = status;
}

async function syncOfflineData() {
  const items = getLocalQueue();
  if (items.length === 0) {
    showToast('Nenhum dado pendente para enviar.', 'info');
    return;
  }

  const online = await checkServerConnectivity();
  if (!online) {
    showToast('Servidor inacessível. Verifique se está conectado ao Wi-Fi da fazenda ou ajuste as configurações de IP.', 'warning');
    return;
  }

  const savedAuthStr = localStorage.getItem(STORAGE_AUTH_KEY);
  if (savedAuthStr) {
    try {
      const auth = JSON.parse(savedAuthStr);
      if (auth.email && auth.senha) {
        await executeSync(auth, true);
        return;
      }
    } catch (e) {
      localStorage.removeItem(STORAGE_AUTH_KEY);
    }
  }

  openAuthSyncModal();
}

async function handleAuthSync(e) {
  e.preventDefault();
  const email = document.getElementById('sync_email').value.trim();
  const senha = document.getElementById('sync_senha').value;
  const salvar = document.getElementById('sync_salvar').checked;

  closeAuthSyncModal();
  await executeSync({ email, senha }, salvar);
  updateOperatorUI();
}

// Auto-Sync Silencioso em Segundo Plano
async function triggerAutoSync() {
  if (isSyncing || !isAutoSyncEnabled()) return;
  const items = getLocalQueue();
  if (items.length === 0) return;

  const savedAuthStr = localStorage.getItem(STORAGE_AUTH_KEY);
  let authData = {};
  if (savedAuthStr) {
    try {
      authData = JSON.parse(savedAuthStr) || {};
    } catch (e) {}
  }

  isSyncing = true;
  const payload = {
    dispositivo: 'App Nativo Android (Auto-Sync Automático)',
    api_key: 'pecuaria-mobile-key',
    auth_email: authData.email || '',
    auth_senha: authData.senha || '',
    animais_novos: [],
    pesagens: [],
    saude: []
  };

  const itemIds = [];
  items.forEach(it => {
    itemIds.push(it.id);
    if (it.tipo === 'animal') {
      payload.animais_novos.push({ ...it.data, foto_base64: it.foto_base64 });
    } else if (it.tipo === 'pesagem') {
      payload.pesagens.push({ ...it.data, foto_base64: it.foto_base64 });
    } else if (it.tipo === 'saude') {
      payload.saude.push({ ...it.data, foto_base64: it.foto_base64 });
    }
  });

  const serverUrl = getServerUrl();
  try {
    const res = await fetch(`${serverUrl}/api/sync`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-KEY': 'pecuaria-mobile-key'
      },
      body: JSON.stringify(payload)
    });

    if (res.ok) {
      const data = await res.json();
      removeItemsFromQueue(itemIds);
      if (navigator.vibrate) navigator.vibrate([30, 20, 30]);
      showToast(`Auto-Sync: ${data.processados?.pesagens || 0} pesagens, ${data.processados?.saude || 0} manejos e ${data.processados?.animais_novos || 0} bezerros sincronizados!`, 'success');
      checkServerConnectivity();
    } else if (res.status === 401) {
      localStorage.removeItem(STORAGE_AUTH_KEY);
    }
  } catch (e) {
    console.warn('Falha no Auto-Sync:', e);
  } finally {
    isSyncing = false;
    updatePendingBadge();
  }
}

async function executeSync(authData, shouldSave = false) {
  const items = getLocalQueue();
  if (items.length === 0) return;

  isSyncing = true;
  showSyncProgress(true, `Sincronizando ${items.length} Registros...`, 'Preparando e enviando lote para a central...');

  const payload = {
    dispositivo: 'App Nativo Android (Sincronização Manual)',
    api_key: 'pecuaria-mobile-key',
    auth_email: authData.email || '',
    auth_senha: authData.senha || '',
    animais_novos: [],
    pesagens: [],
    saude: []
  };

  const itemIds = [];
  items.forEach(it => {
    itemIds.push(it.id);
    if (it.tipo === 'animal') {
      payload.animais_novos.push({ ...it.data, foto_base64: it.foto_base64 });
    } else if (it.tipo === 'pesagem') {
      payload.pesagens.push({ ...it.data, foto_base64: it.foto_base64 });
    } else if (it.tipo === 'saude') {
      payload.saude.push({ ...it.data, foto_base64: it.foto_base64 });
    }
  });

  const serverUrl = getServerUrl();
  try {
    showSyncProgress(true, 'Gravando no Banco de Dados...', 'Aguardando processamento do servidor central...');
    
    const res = await fetch(`${serverUrl}/api/sync`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-KEY': 'pecuaria-mobile-key'
      },
      body: JSON.stringify(payload)
    });

    if (res.status === 401) {
      localStorage.removeItem(STORAGE_AUTH_KEY);
      showToast('E-mail ou senha incorretos para autorizar a sincronização.', 'danger');
      openAuthSyncModal();
      return;
    }

    if (!res.ok) {
      throw new Error('Servidor retornou status HTTP ' + res.status);
    }

    const data = await res.json();
    removeItemsFromQueue(itemIds);

    if (shouldSave && authData.email && authData.senha) {
      localStorage.setItem(STORAGE_AUTH_KEY, JSON.stringify(authData));
      updateOperatorUI();
    }

    triggerHapticFeedback('save');
    showToast(`Sincronizado com sucesso! ${data.processados?.pesagens || 0} pesagens, ${data.processados?.saude || 0} manejos e ${data.processados?.animais_novos || 0} bezerros gravados na nuvem.`, 'success');
  } catch (err) {
    showToast('Erro na sincronização: ' + err.message, 'danger');
  } finally {
    isSyncing = false;
    showSyncProgress(false);
    updatePendingBadge();
  }
}

// ── 10. Utilitário de Toast com Ícones Vetoriais ───────────────
function showToast(message, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }

  const iconClass = type === 'success' ? 'bi-check-circle-fill' : (type === 'danger' ? 'bi-x-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'));

  const toastEl = document.createElement('div');
  toastEl.className = `toast align-items-center text-bg-${type} border-0 show shadow-lg mb-2`;
  toastEl.role = 'alert';
  toastEl.innerHTML = `
    <div class="d-flex align-items-center">
      <div class="toast-body fw-bold d-flex align-items-center gap-2">
        <i class="bi ${iconClass} fs-5"></i>
        <span>${message}</span>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  `;
  container.appendChild(toastEl);
  setTimeout(() => toastEl.remove(), 4500);
}

// ── 11. Alternância de Abas e Formulários ───────────────────────
function switchCampoTab(tabId) {
  document.querySelectorAll('.campo-tab-pane').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.campo-nav-btn').forEach(el => el.classList.remove('active'));
  
  const targetPane = document.getElementById('tabPane_' + tabId);
  const targetBtn = document.getElementById('tabBtn_' + tabId);
  
  if (targetPane) targetPane.classList.add('active');
  if (targetBtn) targetBtn.classList.add('active');
  
  if (tabId === 'fila') {
    renderQueueCards();
  }
  
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

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

function toggleObitoSensivel(val) {
  const check = document.getElementById('s_sensivel');
  if (check && val === 'Óbito') {
    check.checked = true;
  }
}

// Submissão de Pesagem
async function handleAppPesagem(e) {
  e.preventDefault();
  try {
    const brinco = document.getElementById('p_brinco').value.trim().toUpperCase();
    const rawPeso = (document.getElementById('p_peso').value || '').toString().replace(',', '.');
    const peso = parseFloat(rawPeso);
    const data = document.getElementById('p_data').value;
    const obs = document.getElementById('p_obs').value.trim();
    const fotoFile = document.getElementById('p_foto').files[0];

    if (isNaN(peso) || peso <= 0) {
      showToast('Informe um peso válido.', 'warning');
      return;
    }

    let fotoBase64 = null;
    if (fotoFile) {
      fotoBase64 = await compressImage(fotoFile);
    }

    addToQueue('pesagem', { brinco, peso, data, observacao: obs }, fotoBase64);
    
    document.getElementById('formAppPesagem').reset();
    removeFotoPreview('p_foto', 'preview_p');
    document.getElementById('p_data').value = new Date().toISOString().split('T')[0];

    // Resposta tátil firme de confirmação de curral (Haptics nativo + vibração)
    triggerHapticFeedback('save');
    showToast(`Pesagem de ${peso}kg salva no celular!`, 'success');
  } catch (err) {
    showToast('Erro ao salvar: ' + err.message, 'danger');
  }
}

// Submissão de Bezerro
async function handleAppBezerro(e) {
  e.preventDefault();
  try {
    const brinco = document.getElementById('b_brinco').value.trim().toUpperCase();
    const sexo = document.getElementById('b_sexo').value;
    const nome = document.getElementById('b_nome').value.trim();
    const raca = document.getElementById('b_raca').value.trim();
    const data = document.getElementById('b_data').value;
    const fotoFile = document.getElementById('b_foto').files[0];

    let fotoBase64 = null;
    if (fotoFile) {
      fotoBase64 = await compressImage(fotoFile);
    }

    addToQueue('animal', { brinco, sexo, nome, raca, data_nascimento: data }, fotoBase64);
    
    document.getElementById('formAppBezerro').reset();
    removeFotoPreview('b_foto', 'preview_b');
    document.getElementById('b_data').value = new Date().toISOString().split('T')[0];
    document.getElementById('b_raca').value = 'Nelore';

    // Resposta tátil firme de confirmação de curral (Haptics nativo + vibração)
    triggerHapticFeedback('save');
    showToast(`Bezerro ${brinco} salvo no celular!`, 'success');
  } catch (err) {
    showToast('Erro ao salvar: ' + err.message, 'danger');
  }
}

// Submissão de Saúde
async function handleAppSaude(e) {
  e.preventDefault();
  try {
    const brinco = document.getElementById('s_brinco').value.trim().toUpperCase();
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

    addToQueue('saude', { brinco, tipo, descricao: desc, medicamento: med, dose, is_sensivel: isSensivel ? 1 : 0 }, fotoBase64);
    
    document.getElementById('formAppSaude').reset();
    removeFotoPreview('s_foto', 'preview_s');

    // Resposta tátil firme de confirmação de curral (Haptics nativo + vibração)
    triggerHapticFeedback('save');
    showToast(`Evento de ${tipo} para ${brinco} salvo no celular!`, 'success');
  } catch (err) {
    showToast('Erro ao salvar: ' + err.message, 'danger');
  }
}

// ── 12. Inicialização do App ───────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  renderStatusBadge(false);
  updatePendingBadge();
  renderQueueCards();
  updateOperatorUI();

  // Fecha dropdowns de autocompletar ao tocar fora
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.position-relative')) {
      document.querySelectorAll('.earring-autocomplete-dropdown').forEach(el => el.style.display = 'none');
    }
  });

  // Datas padrão
  const hoje = new Date().toISOString().split('T')[0];
  const pData = document.getElementById('p_data');
  const bData = document.getElementById('b_data');
  const sData = document.getElementById('s_data');
  if (pData) pData.value = hoje;
  if (bData) bData.value = hoje;
  if (sData) sData.value = hoje;

  // Checa conexão em segundo plano
  setTimeout(() => {
    checkServerConnectivity();
  }, 600);
  setInterval(checkServerConnectivity, 10000);

  // Monitor contínuo de Auto-Sync a cada 5 segundos se houver registros na fila
  setInterval(() => {
    const pending = getLocalQueue();
    if (pending.length > 0 && isAutoSyncEnabled()) {
      triggerAutoSync();
    }
  }, 5000);

  // Reconexão imediata ao voltar à rede
  window.addEventListener('online', () => {
    checkServerConnectivity().then(online => {
      if (online) triggerAutoSync();
    });
  });
});
