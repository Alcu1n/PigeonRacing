/*
 * The admin PWA deliberately does not cache document or Livewire responses.
 * Registration data must always come from the server, including after a
 * manual refresh from the registration list.
 */
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});
