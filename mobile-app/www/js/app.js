// ============================================================
// PecuáriaGest App Nativo — Motor Offline, Auto-Sync & API
// ============================================================

const STORAGE_QUEUE_KEY = 'pecuaria_native_queue';
const STORAGE_ANIMALS_KEY = 'pecuaria_native_animals';
const STORAGE_SERVER_KEY = 'pecuaria_native_server_url';
const STORAGE_AUTH_KEY = 'pecuaria_native_auth';
const STORAGE_AUTO_SYNC_KEY = 'pecuaria_native_auto_sync';

let isSyncing = false;

// 1. Obter URL do Servidor Central & Auto-Sync Config
function getServerUrl() {
  return localStorage.getItem(STORAGE_SERVER_KEY) || 'http://192.168.3.56:8080';
}

function setServerUrl(url) {
  let cleanUrl = url.trim();
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

// 2. Fila Offline Local
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

function removeItemsFromQueue(ids) {
  const items = getLocalQueue().filter(it => !ids.includes(it.id));
  saveLocalQueue(items);
}

// 3. Cache Local de Animais para Autocompletar
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
    renderAnimalsDatalist();
  }
}

function setCachedAnimals(animals) {
  localStorage.setItem(STORAGE_ANIMALS_KEY, JSON.stringify(animals));
  renderAnimalsDatalist();
}

function renderAnimalsDatalist() {
  const datalist = document.getElementById('animaisListApp');
  if (!datalist) return;
  const list = getCachedAnimals();
  datalist.innerHTML = list.map(a => 
    `<option value="${a.brinco}">${a.nome ? a.nome + ' — ' : ''}${a.raca || ''} (${a.sexo === 'M' ? 'Macho' : 'Fêmea'})</option>`
  ).join('');
}

// 4. Compressão de Fotos com Canvas
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

// 5. Verificação de Conectividade com o Servidor
let isServerOnline = false;

function renderStatusBadge(online) {
  const badge = document.getElementById('connectionStatusBadge');
  if (!badge) return;

  if (online) {
    badge.className = 'badge bg-success d-inline-flex align-items-center gap-1 shadow-sm';
    badge.innerHTML = '<span class="status-dot online"></span> Online (Conectado)';
  } else {
    badge.className = 'badge bg-danger d-inline-flex align-items-center gap-1 shadow-sm';
    badge.innerHTML = '<span class="status-dot offline"></span> Offline (Modo Campo)';
  }
}

async function checkServerConnectivity() {
  const serverUrl = getServerUrl();
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 4000);

    const res = await fetch(`${serverUrl}/api/animais`, {
      method: 'GET',
      cache: 'no-store',
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
        showToast('Conectado ao servidor da fazenda!', 'success');
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

// 6. Atualização de Contagem e Lista da Fila
function updatePendingBadge() {
  const items = getLocalQueue();
  const count = items.length;

  const countEl = document.getElementById('pendingCount');
  const countListEl = document.getElementById('pendingItemsList');
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

  if (countListEl) {
    if (count === 0) {
      countListEl.innerHTML = '<li class="list-group-item text-muted text-center py-4 small">Nenhum registro pendente no celular.</li>';
    } else {
      countListEl.innerHTML = items.map((it) => {
        let iconHtml = '<i class="bi bi-rulers text-warning"></i>';
        let title = `Pesagem: ${it.data.brinco || 'Animal'} (${it.data.peso || 0} kg)`;
        if (it.tipo === 'animal') {
          iconHtml = '<i class="bi bi-stars text-success"></i>';
          title = `Novo Animal: ${it.data.brinco || 'Sem brinco'} (${it.data.sexo === 'M' ? 'Macho' : 'Fêmea'})`;
        } else if (it.tipo === 'saude') {
          iconHtml = '<i class="bi bi-heart-pulse-fill text-danger"></i>';
          title = `Saúde: ${it.data.brinco || 'Animal'} - ${it.data.tipo || 'Tratamento'}`;
        }
        const hasPhoto = it.foto_base64 ? '<span class="badge bg-secondary ms-1"><i class="bi bi-camera-fill me-1"></i>Foto</span>' : '';
        return `
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div class="d-flex align-items-center gap-2">
              <span class="fs-5">${iconHtml}</span>
              <div>
                <strong>${title}</strong>
                ${hasPhoto}
              </div>
            </div>
            <small class="text-muted">${new Date(it.criado_em).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</small>
          </li>
        `;
      }).join('');
    }
  }
}

// 7. Modais e Ações de Sincronização
function openServerConfigModal() {
  const modalEl = document.getElementById('modalConfigServidor');
  const inputEl = document.getElementById('server_api_url');
  const autoSyncEl = document.getElementById('config_auto_sync');
  
  if (inputEl) inputEl.value = getServerUrl();
  if (autoSyncEl) autoSyncEl.checked = isAutoSyncEnabled();

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
      showToast(`Auto-Sync: ${data.processados?.pesagens || 0} pesagens, ${data.processados?.saude || 0} manejos e ${data.processados?.animais_novos || 0} bezerros sincronizados automaticamente!`, 'success');
      checkServerConnectivity();
    } else if (res.status === 401) {
      localStorage.removeItem(STORAGE_AUTH_KEY);
      openAuthSyncModal();
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
  const btnSync = document.getElementById('btnSyncNow');
  if (btnSync) {
    btnSync.disabled = true;
    btnSync.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sincronizando...';
  }

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
      throw new Error('Servidor retornou erro HTTP ' + res.status);
    }

    const data = await res.json();
    removeItemsFromQueue(itemIds);

    if (shouldSave && authData.email && authData.senha) {
      localStorage.setItem(STORAGE_AUTH_KEY, JSON.stringify(authData));
    }

    showToast(`Sincronizado com sucesso! ${data.processados?.pesagens || 0} pesagens, ${data.processados?.saude || 0} manejos e ${data.processados?.animais_novos || 0} bezerros gravados na nuvem.`, 'success');
  } catch (err) {
    showToast('Erro na sincronização: ' + err.message, 'danger');
  } finally {
    isSyncing = false;
    updatePendingBadge();
  }
}

// 8. Utilitário de Toast com Ícones Vetoriais
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
  setTimeout(() => toastEl.remove(), 5000);
}

// 9. Alternância de Abas e Formulários
function switchCampoTab(tabId) {
  document.querySelectorAll('.campo-tab-pane').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.campo-nav-btn').forEach(el => el.classList.remove('active'));
  
  const targetPane = document.getElementById('tabPane_' + tabId);
  const targetBtn = document.getElementById('tabBtn_' + tabId);
  
  if (targetPane) targetPane.classList.add('active');
  if (targetBtn) targetBtn.classList.add('active');
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

// Submissão de Pesagem (Suporte flexível a ponto e vírgula)
async function handleAppPesagem(e) {
  e.preventDefault();
  try {
    const brinco = document.getElementById('p_brinco').value.trim();
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

    if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
    showToast(`Pesagem de ${peso}kg salva no celular!`, 'success');
  } catch (err) {
    showToast('Erro ao salvar: ' + err.message, 'danger');
  }
}

// Submissão de Bezerro
async function handleAppBezerro(e) {
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

    addToQueue('animal', { brinco, sexo, nome, raca, data_nascimento: data }, fotoBase64);
    
    document.getElementById('formAppBezerro').reset();
    removeFotoPreview('b_foto', 'preview_b');
    document.getElementById('b_data').value = new Date().toISOString().split('T')[0];
    document.getElementById('b_raca').value = 'Nelore';

    if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
    showToast(`Bezerro ${brinco} salvo no celular!`, 'success');
  } catch (err) {
    showToast('Erro ao salvar: ' + err.message, 'danger');
  }
}

// Submissão de Saúde
async function handleAppSaude(e) {
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

    addToQueue('saude', { brinco, tipo, descricao: desc, medicamento: med, dose, is_sensivel: isSensivel ? 1 : 0 }, fotoBase64);
    
    document.getElementById('formAppSaude').reset();
    removeFotoPreview('s_foto', 'preview_s');

    if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
    showToast(`Evento de ${tipo} para ${brinco} salvo no celular!`, 'success');
  } catch (err) {
    showToast('Erro ao salvar: ' + err.message, 'danger');
  }
}

// Inicialização do App
document.addEventListener('DOMContentLoaded', () => {
  renderStatusBadge(false);
  renderAnimalsDatalist();
  updatePendingBadge();

  // Datas padrão
  const hoje = new Date().toISOString().split('T')[0];
  const pData = document.getElementById('p_data');
  const bData = document.getElementById('b_data');
  if (pData) pData.value = hoje;
  if (bData) bData.value = hoje;

  // Checa conexão em segundo plano
  setTimeout(() => {
    checkServerConnectivity();
  }, 800);
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
