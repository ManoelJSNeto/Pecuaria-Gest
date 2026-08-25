// ============================================================
// PecuáriaGest — PWA & Offline Engine para Modo Campo
// ============================================================

// 1. Registro do Service Worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then((reg) => console.log('PWA ServiceWorker registrado com sucesso:', reg.scope))
      .catch((err) => console.warn('Falha ao registrar ServiceWorker:', err));
  });
}

// 2. Banco de Dados Local (IndexedDB)
const DB_NAME = 'PecuariaCampoDB';
const DB_VERSION = 1;
const STORE_NAME = 'fila_offline';

function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
      }
    };
    request.onsuccess = (e) => resolve(e.target.result);
    request.onerror = (e) => reject(e.target.error);
  });
}

async function addQueueItem(tipo, data, fotoBase64 = null) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const item = {
      tipo: tipo, // 'pesagem' | 'saude' | 'animal'
      data: data,
      foto_base64: fotoBase64,
      criado_em: new Date().toISOString()
    };
    const req = store.add(item);
    req.onsuccess = () => {
      updatePendingBadge();
      resolve(req.result);
    };
    req.onerror = () => reject(req.error);
  });
}

async function getAllQueueItems() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);
    const req = store.getAll();
    req.onsuccess = () => resolve(req.result || []);
    req.onerror = () => reject(req.error);
  });
}

async function clearQueueItems(ids) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    ids.forEach(id => store.delete(id));
    tx.oncomplete = () => {
      updatePendingBadge();
      resolve();
    };
    tx.onerror = () => reject(tx.error);
  });
}

// 3. Compressor de Foto com Canvas
function compressImage(file, maxDimension = 1200, quality = 0.75) {
  return new Promise((resolve, reject) => {
    if (!file) {
      resolve(null);
      return;
    }
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
      const img = new Image();
      img.src = event.target.result;
      img.onload = () => {
        let width = img.width;
        let height = img.height;

        if (width > height) {
          if (width > maxDimension) {
            height = Math.round((height * maxDimension) / width);
            width = maxDimension;
          }
        } else {
          if (height > maxDimension) {
            width = Math.round((width * maxDimension) / height);
            height = maxDimension;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        const dataUrl = canvas.toDataURL('image/jpeg', quality);
        resolve(dataUrl);
      };
      img.onerror = (e) => reject(e);
    };
    reader.onerror = (e) => reject(e);
  });
}

// 4. Indicador de Conexão Online/Offline
function updateOnlineStatus() {
  const badge = document.getElementById('connectionStatusBadge');
  if (!badge) return;

  if (navigator.onLine) {
    badge.className = 'badge bg-success d-inline-flex align-items-center gap-1 shadow-sm';
    badge.innerHTML = '<span class="status-dot online"></span> Online (Conectado)';
  } else {
    badge.className = 'badge bg-danger d-inline-flex align-items-center gap-1 shadow-sm';
    badge.innerHTML = '<span class="status-dot offline"></span> Offline (Modo Campo)';
  }
}

window.addEventListener('online', () => {
  updateOnlineStatus();
  showToast('🟢 Conexão restabelecida! Você pode sincronizar os dados.', 'success');
});

window.addEventListener('offline', () => {
  updateOnlineStatus();
  showToast('🔴 Sem internet no momento. Seus lançamentos serão salvos com segurança no celular.', 'warning');
});

// 5. Atualização da Contagem de Pendências no UI
async function updatePendingBadge() {
  const countEl = document.getElementById('pendingCount');
  const countListEl = document.getElementById('pendingItemsList');
  const btnSync = document.getElementById('btnSyncNow');

  try {
    const items = await getAllQueueItems();
    const count = items.length;

    if (countEl) countEl.textContent = count;
    if (btnSync) {
      btnSync.disabled = (count === 0);
      btnSync.innerHTML = count > 0 
        ? `<i class="bi bi-cloud-arrow-up-fill me-1"></i> Sincronizar com a Nuvem (${count})`
        : `<i class="bi bi-check2-all me-1"></i> Tudo Sincronizado`;
    }

    if (countListEl) {
      if (count === 0) {
        countListEl.innerHTML = '<li class="list-group-item text-muted text-center py-3 small">Nenhum registro pendente no celular.</li>';
      } else {
        countListEl.innerHTML = items.map((it, idx) => {
          let icon = '⚖️';
          let title = `Pesagem: ${it.data.brinco || 'Animal'} (${it.data.peso || 0} kg)`;
          if (it.tipo === 'animal') {
            icon = '🐣';
            title = `Novo Animal: ${it.data.brinco || 'Sem brinco'} (${it.data.sexo === 'M' ? 'Macho' : 'Fêmea'})`;
          } else if (it.tipo === 'saude') {
            icon = '⚕️';
            title = `Saúde: ${it.data.brinco || 'Animal'} - ${it.data.tipo || 'Tratamento'}`;
          }
          const hasPhoto = it.foto_base64 ? '<span class="badge bg-secondary ms-1">📷 Foto</span>' : '';
          return `
            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
              <div>
                <span class="me-2">${icon}</span>
                <strong>${title}</strong>
                ${hasPhoto}
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

// 6. Sincronização com o Backend
async function syncOfflineData() {
  const btnSync = document.getElementById('btnSyncNow');
  if (!navigator.onLine) {
    showToast('⚠️ Você está offline. Conecte-se à internet para sincronizar.', 'warning');
    return;
  }

  const items = await getAllQueueItems();
  if (items.length === 0) {
    showToast('Nenhum dado pendente para enviar.', 'info');
    return;
  }

  if (btnSync) {
    btnSync.disabled = true;
    btnSync.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sincronizando...';
  }

  const payload = {
    dispositivo: 'PWA Mobile (' + (navigator.userAgent.includes('Mobile') ? 'Smartphone' : 'Desktop') + ')',
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
        'Content-Type': 'application/json',
        'X-API-KEY': 'pecuaria-mobile-key'
      },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw new Error('Falha na resposta do servidor: ' + response.status);
    }

    const res = await response.json();
    await clearQueueItems(itemIds);
    showToast(`✅ Sincronizado com sucesso! ${res.processados.pesagens || 0} pesagens, ${res.processados.saude || 0} eventos de saúde e ${res.processados.animais_novos || 0} novos animais gravados na nuvem.`, 'success');
  } catch (err) {
    console.error('Erro na sincronização:', err);
    showToast('❌ Erro ao sincronizar com o servidor: ' + err.message, 'danger');
  } finally {
    updatePendingBadge();
  }
}

// 7. Utilitário de Toast de Feedback
function showToast(message, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }

  const toastEl = document.createElement('div');
  toastEl.className = `toast align-items-center text-bg-${type} border-0 show shadow-lg mb-2`;
  toastEl.role = 'alert';
  toastEl.innerHTML = `
    <div class="d-flex">
      <div class="toast-body fw-bold">
        ${message}
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  `;
  container.appendChild(toastEl);
  setTimeout(() => {
    toastEl.remove();
  }, 5000);
}

// Inicialização ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
  updateOnlineStatus();
  updatePendingBadge();
});
