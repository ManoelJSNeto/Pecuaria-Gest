const CACHE_NAME = 'pecuaria-campo-v8';
const ASSETS_TO_CACHE = [
  '/campo',
  '/mobile',
  '/manifest.json',
  '/assets/js/pwa-campo.js',
  '/assets/vendor/bootstrap/bootstrap.min.css',
  '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
  '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
  '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
  '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/apple-touch-icon.png',
  '/favicon.svg'
];

// Install: Pré-armazena 100% dos ativos locais na memória flash do celular
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch((err) => {
        console.warn('Alerta ao pré-armazenar cache local:', err);
      });
    })
  );
});

// Activate: Assume o controle imediatamente e remove caches antigos
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch: Estratégia Cache-First Pura para Abertura Instantânea em 0ms (Mesmo em Cold Start e Modo Avião)
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // 1. Ignora requisições não-GET e endpoints de sincronização API
  if (event.request.method !== 'GET') return;
  if (url.pathname.startsWith('/api/sync')) return;
  // Ignora pings ativos de verificação de conexão para não retornar cache falso
  if (url.searchParams.has('ping')) return;

  // 2. Endpoint /api/animais: Network First com fallback de Cache
  if (url.pathname === '/api/animais') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response && response.status === 200) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
          }
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // 3. Ativos Estáticos e Imagens: Cache-First Imediato
  if (url.pathname.startsWith('/assets/') || url.pathname === '/favicon.svg' || url.pathname === '/manifest.json') {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) {
          // Atualiza o cache silenciosamente em segundo plano se houver rede
          fetch(event.request).then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse));
            }
          }).catch(() => {});
          return cachedResponse;
        }
        return fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const copy = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
          }
          return networkResponse;
        });
      })
    );
    return;
  }

  // 4. Navegação / Telas HTML (/campo, /mobile, start_url): Cache-First Imediato (Zero Espera de Rede)
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        // Revalida em segundo plano se houver rede sem travar a renderização inicial
        fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const copy = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
          }
        }).catch(() => {});
        return cachedResponse;
      }

      // Se a URL solicitada não estiver exatamente mapeada, entrega o cache do /campo
      return caches.match('/campo').then((campoCached) => {
        if (campoCached) return campoCached;
        
        // Se ainda não estiver em cache, busca na rede e armazena
        return fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const copy = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
          }
          return networkResponse;
        });
      });
    }).catch(async () => {
      const campoFallback = await caches.match('/campo');
      if (campoFallback) return campoFallback;
      return new Response('Offline - Conteúdo não disponível no cache.', {
        status: 503,
        statusText: 'Service Unavailable',
        headers: new Headers({ 'Content-Type': 'text/plain; charset=utf-8' })
      });
    })
  );
});
