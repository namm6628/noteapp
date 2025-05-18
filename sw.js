
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open('note-cache-v1').then((cache) => {
      return cache.addAll([
        '/note_app/offline.html',
        '/note_app/app.js'
      ]);
    }).catch(err => console.error('[Service Worker] Cache failed:', err))
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(event.request).catch(() => {
        if (
          event.request.mode === 'navigate' ||
          event.request.destination === 'document' ||
          event.request.headers.get('accept')?.includes('text/html')
        ) {
          return caches.match('/note_app/offline.html').then(resp => {
            return resp || new Response('<h1>Offline fallback not found</h1>', {
              status: 503,
              headers: { 'Content-Type': 'text/html' }
            });
          });
        }

        return new Response('', { status: 503, statusText: 'Offline & no fallback' });
      });
    })
  );
});

self.addEventListener('sync', event => {
  if (event.tag === 'sync-notes') {
    event.waitUntil(
      self.clients.matchAll().then(clients => {
        clients.forEach(client => client.postMessage('sync'));
      })
    );
  }
});
