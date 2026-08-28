// ============================================================
// PecuáriaGest — PWA & Offline Engine para Modo Campo (App Shell)
// ============================================================

// 1. Registro do Service Worker (Sem reload bloqueante)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then((reg) => {
        console.log('PWA ServiceWorker registrado:', reg.scope);
        if (navigator.onLine) {
          reg.update().catch(() => {});
        }
      })
      .catch((err) => console.warn('Falha ao registrar ServiceWorker:', err));
  });
}

// 2. Banco de Dados Local (IndexedDB v2: Fila Offline + Cache de Animais)
const DB_NAME = 'PecuariaCampoDB';
const DB_VERSION = 2;
const STORE_QUEUE = 'fila_offline';
const STORE_ANIMALS = 'animais_cache';

function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(STORE_QUEUE)) {
        db.createObjectStore(STORE_QUEUE, { keyPath: 'id', autoIncrement: true });
      }
      if (!db.objectStoreNames.contains(STORE_ANIMALS)) {
        db.createObjectStore(STORE_ANIMALS, { keyPath: 'brinco' });
      }
    };
    request.onsuccess = (e) => resolve(e.target.result);
    request.onerror = (e) => reject(e.target.error);
  });
}

async function addQueueItem(tipo, data, fotoBase64 = null) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_QUEUE, 'readwrite');
    const store = tx.objectStore(STORE_QUEUE);
    const item = {
      tipo: tipo, // 'pesagem' | 'saude' | 'animal'
      data: data,
      foto_base64: fotoBase64,
      criado_em: new Date().toISOString()
    };
    const req = store.add(item);
    req.onsuccess = () => {
      // Se for novo animal, atualiza o cache local de animais imediatamente
      if (tipo === 'animal' && data.brinco) {
        saveAnimalToCache(data);
      }
      updatePendingBadge();
      resolve(req.result);
    };
    req.onerror = () => reject(req.error);
  });
}

async function getAllQueueItems() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_QUEUE, 'readonly');
    const store = tx.objectStore(STORE_QUEUE);
    const req = store.getAll();
    req.onsuccess = () => resolve(req.result || []);
    req.onerror = () => reject(req.error);
  });
}

async function clearQueueItems(ids) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_QUEUE, 'readwrite');
    const store = tx.objectStore(STORE_QUEUE);
    ids.forEach(id => store.delete(id));
    tx.oncomplete = () => {
      updatePendingBadge();
      resolve();
    };
    tx.onerror = () => reject(tx.error);
  });
}

// 3. Cache Local de Animais (IndexedDB) para Autocompletar 100% Offline
async function saveAnimalToCache(animal) {
  try {
    const db = await openDB();
    const tx = db.transaction(STORE_ANIMALS, 'readwrite');
    const store = tx.objectStore(STORE_ANIMALS);
    store.put(animal);
    populateAnimalsDatalist();
  } catch (e) {
    console.warn('Erro ao salvar animal no cache local:', e);
  }
}

async function syncAnimalsCache() {
  if (!navigator.onLine) return;
  try {
    const res = await fetch('/api/animais');
    if (!res.ok) return;
    const data = await res.json();
    const animais = data.animais || data || [];
    const db = await openDB();
    const tx = db.transaction(STORE_ANIMALS, 'readwrite');
    const store = tx.objectStore(STORE_ANIMALS);
    animais.forEach(a => store.put(a));
    populateAnimalsDatalist();
  } catch (err) {
    console.warn('Erro ao atualizar cache de animais:', err);
  }
}

async function populateAnimalsDatalist() {
  try {
    const db = await openDB();
    const tx = db.transaction(STORE_ANIMALS, 'readonly');
    const store = tx.objectStore(STORE_ANIMALS);
    const req = store.getAll();
    req.onsuccess = () => {
      const animais = req.result || [];
      const datalist = document.getElementById('animaisListPwa');
      if (datalist && animais.length > 0) {
        datalist.innerHTML = animais.map(a => 
          `<option value="${a.brinco}">${a.nome ? a.nome + ' — ' : ''}${a.raca || ''} (${a.sexo === 'M' ? 'Macho' : 'Fêmea'})</option>`
        ).join('');
      }
    };
  } catch (err) {
    console.warn('Erro ao ler animais do IndexedDB:', err);
  }
}

// 4. Utilitário de Compressão de Imagens no Cliente (Canvas)
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
          console.warn('Falha no redimensionamento Canvas, usando original:', err);
          resolve(e.target.result);
        }
      };

      img.onerror = () => {
        console.warn('Erro ao processar imagem para compressão');
        resolve(e.target.result);
      };

      img.src = e.target.result;
    };

    reader.onerror = (err) => {
      console.warn('Erro ao ler arquivo com FileReader:', err);
      resolve(null);
    };

    reader.readAsDataURL(file);
  });
}

// 5. Indicador de Conexão Ativo & Visão Pessimista
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

async function checkRealConnectivity() {
  // Verificação rápida de modo avião / sem rede nativo
  if (!navigator.onLine || (navigator.connection && navigator.connection.type === 'none')) {
    isServerOnline = false;
    renderStatusBadge(false);
    return false;
  }

  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 800); // 800ms max timeout para não travar a UI

    const res = await fetch('/favicon.svg?ping=' + Date.now(), {
      method: 'HEAD',
      cache: 'no-store',
      signal: controller.signal
    });
    clearTimeout(timer);

    if (res.ok || res.status === 304) {
      if (!isServerOnline) {
        showToast('Conexão com o servidor confirmada! Você pode sincronizar.', 'success');
        syncAnimalsCache(); // Atualiza cache de animais silenciosamente
      }
      isServerOnline = true;
      renderStatusBadge(true);
      return true;
    } else {
      isServerOnline = false;
      renderStatusBadge(false);
      return false;
    }
  } catch (err) {
    isServerOnline = false;
    renderStatusBadge(false);
    return false;
  }
}

window.addEventListener('online', () => {
  checkRealConnectivity();
});

window.addEventListener('offline', () => {
  isServerOnline = false;
  renderStatusBadge(false);
  showToast('Sem internet no momento. Seus lançamentos serão salvos com segurança no celular.', 'warning');
});

// 6. Atualização da Contagem de Pendências no UI
async function updatePendingBadge() {
  const countEl = document.getElementById('pendingCount');
  const countListEl = document.getElementById('pendingItemsList');
  const btnSync = document.getElementById('btnSyncNow');

  try {
    const items = await getAllQueueItems();
    const count = items.length;

    if (countEl) countEl.textContent = count;
    const tabBadgeEl = document.getElementById('tabPendingBadge');
    if (tabBadgeEl) tabBadgeEl.textContent = count;

    if (btnSync) {
      btnSync.disabled = (count === 0);
      btnSync.innerHTML = count > 0 
        ? `<i class="bi bi-cloud-arrow-up-fill me-1"></i> Sincronizar com a Nuvem (${count})`
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
  } catch (err) {
    console.error('Erro ao ler fila pendente:', err);
  }
}

// 7. Sincronização com o Backend & Autenticação
function openAuthSyncModal() {
  const modalEl = document.getElementById('modalAuthSync');
  if (!modalEl) return;

  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  } else {
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    let backdrop = document.getElementById('modalBackdropFallback');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'modalBackdropFallback';
      backdrop.className = 'modal-backdrop fade show';
      document.body.appendChild(backdrop);
    }
  }
}

function closeAuthSyncModal() {
  const modalEl = document.getElementById('modalAuthSync');
  if (!modalEl) return;

  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
  }
  modalEl.classList.remove('show');
  modalEl.style.display = 'none';
  modalEl.setAttribute('aria-hidden', 'true');
  const backdrop = document.getElementById('modalBackdropFallback');
  if (backdrop) backdrop.remove();
}

async function syncOfflineData() {
  const items = await getAllQueueItems();
  if (items.length === 0) {
    showToast('Nenhum dado pendente para enviar.', 'info');
    return;
  }

  // Verifica conectividade real primeiro
  const online = await checkRealConnectivity();
  if (!online) {
    showToast('O servidor está inacessível no momento. Seus registros continuam salvos com segurança no celular.', 'warning');
    return;
  }

  // Verifica se há credenciais salvas no dispositivo
  const savedAuthStr = localStorage.getItem('pwa_sync_auth');
  if (savedAuthStr) {
    try {
      const authObj = JSON.parse(savedAuthStr);
      if (authObj.email && authObj.senha) {
        await executeSync({ auth_email: authObj.email, auth_senha: authObj.senha }, true);
        return;
      }
    } catch (e) {
      localStorage.removeItem('pwa_sync_auth');
    }
  }

  // Se não há credenciais salvas, abre o modal de autenticação imediatamente
  openAuthSyncModal();
}

async function handleAuthSync(e) {
  e.preventDefault();
  const email = document.getElementById('sync_email').value.trim();
  const senha = document.getElementById('sync_senha').value;
  const salvar = document.getElementById('sync_salvar').checked;

  closeAuthSyncModal();
  await executeSync({ auth_email: email, auth_senha: senha }, salvar);
}

async function executeSync(authData = {}, shouldSave = false) {
  const btnSync = document.getElementById('btnSyncNow');
  const items = await getAllQueueItems();
  if (items.length === 0) return;

  if (btnSync) {
    btnSync.disabled = true;
    btnSync.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sincronizando...';
  }

  const payload = {
    dispositivo: 'PWA Mobile (' + (navigator.userAgent.includes('Mobile') ? 'Smartphone' : 'Desktop') + ')',
    auth_email: authData.auth_email || '',
    auth_senha: authData.auth_senha || '',
    animais_novos: [],
    pesagens: [],
    saude: []
  };

  const itemIds = [];

  items.forEach(it => {
    itemIds.push(it.id);
    if (it.tipo === 'animal') {
      payload.animais_novos.push({
        ...it.data,
        foto_base64: it.foto_base64
      });
    } else if (it.tipo === 'pesagem') {
      payload.pesagens.push({
        ...it.data,
        foto_base64: it.foto_base64
      });
    } else if (it.tipo === 'saude') {
      payload.saude.push({
        ...it.data,
        foto_base64: it.foto_base64
      });
    }
  });

  try {
    const response = await fetch('/api/sync', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    if (response.status === 401) {
      localStorage.removeItem('pwa_sync_auth');
      showToast('E-mail ou senha incorretos para autorizar a sincronização.', 'danger');
      openAuthSyncModal();
      return;
    }

    if (!response.ok) {
      throw new Error('Falha na resposta do servidor (' + response.status + ')');
    }

    const res = await response.json();
    await clearQueueItems(itemIds);

    if (shouldSave && authData.auth_email && authData.auth_senha) {
      localStorage.setItem('pwa_sync_auth', JSON.stringify({ email: authData.auth_email, senha: authData.auth_senha }));
    }

    showToast(`Sincronizado com sucesso! ${res.processados.pesagens || 0} pesagens, ${res.processados.saude || 0} eventos de saúde e ${res.processados.animais_novos || 0} novos animais gravados na nuvem.`, 'success');
  } catch (err) {
    console.error('Erro na sincronização:', err);
    showToast('Erro ao sincronizar com o servidor: ' + err.message, 'danger');
  } finally {
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
  setTimeout(() => {
    toastEl.remove();
  }, 5000);
}

// 9. Controle de Instalação do PWA
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  const installCard = document.getElementById('pwaInstallCard');
  if (installCard && !isAppInstalled()) {
    installCard.style.display = 'block';
  }
});

function isAppInstalled() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

async function triggerPwaInstall() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    if (outcome === 'accepted') {
      showToast('Aplicativo adicionado à sua tela inicial!', 'success');
      const installCard = document.getElementById('pwaInstallCard');
      if (installCard) installCard.style.display = 'none';
    }
    deferredPrompt = null;
  } else {
    const modalEl = document.getElementById('modalComoInstalar');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    } else {
      alert("Para instalar no celular:\n\n• No Android/Chrome: Toque nos 3 pontinhos (⋮) e selecione 'Instalar aplicativo'.\n• No iPhone/Safari: Toque no botão de Compartilhar e selecione 'Adicionar à Tela de Início'.");
    }
  }
}

window.addEventListener('appinstalled', () => {
  deferredPrompt = null;
  const installCard = document.getElementById('pwaInstallCard');
  if (installCard) installCard.style.display = 'none';
  showToast('Aplicativo instalado com sucesso!', 'success');
});

// Inicialização ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
  renderStatusBadge(false); // Sempre começa como Offline por padrão
  
  // Prioridade 1: Carrega dados do IndexedDB local imediatamente
  populateAnimalsDatalist();
  updatePendingBadge();

  if (isAppInstalled()) {
    const installCard = document.getElementById('pwaInstallCard');
    if (installCard) installCard.style.display = 'none';
  }

  // Prioridade 2: Checagem de rede em segundo plano após a interface estar montada
  setTimeout(() => {
    checkRealConnectivity();
  }, 1000);

  setInterval(checkRealConnectivity, 12000);
});
