/* ==========================================================================
   SPORTHUB — service worker (SH-69)
   --------------------------------------------------------------------------
   O que este arquivo entrega, e o que ele deliberadamente NÃO entrega.

   ENTREGA
   · Instalação no celular do aluno ("Adicionar à tela de início"), com ícone
     próprio e sem a barra do navegador.
   · Os arquivos de aparência (CSS, JS, ícones) servidos do cache, então a
     segunda visita abre instantaneamente mesmo no 3G do ginásio.
   · Uma página decente quando a rede cai, em vez do dinossauro do Chrome.

   NÃO ENTREGA — e a razão importa
   · Notificação de início de partida. Push exige um servidor capaz de assinar
     mensagens VAPID e manter as inscrições; num XAMPP não há para onde
     enviar. O cartão SH-69 pedia isso, e ele fica registrado como pendente
     honesto em vez de virar um botão que não faz nada.
   · Cache de página. Placar ao vivo, classificação e súmula mudam durante o
     jogo; servir uma versão guardada seria pior do que não abrir. Toda
     navegação vai à rede primeiro.

   IMPORTANTE: service worker só é registrado em HTTPS (ou em localhost).
   Publicar em http:// simplesmente não ativa nada disso — é o SH-43.
   ========================================================================== */

'use strict';

/* Trocar a versão invalida o cache inteiro na próxima visita. É o que fazer
   quando o CSS mudou e alguém continua vendo o layout antigo. */
const VERSAO = 'sporthub-v2';   // v2: logotipo redimensionado, vidro mais leve
const CACHE_ESTATICO = VERSAO + '-estatico';

/* Só o que praticamente nunca muda dentro de uma versão. A lista é curta de
   propósito: cache grande demais é cache que serve arquivo velho. */
const ESSENCIAIS = [
  './css/style.css',
  './css/site.css',
  './css/glass.css',
  './css/u.css',
  './js/sporthub-ui.js',
  './js/sporthub-comportamento.js',
  './img/Logo-96.png',
  './img/times.png',
  './offline.html',
];

self.addEventListener('install', (evento) => {
  evento.waitUntil(
    caches.open(CACHE_ESTATICO)
      /* addAll falha inteiro se UM arquivo faltar, e aí a instalação toda
         é abortada. Guardar um a um deixa o que existe ser guardado. */
      .then((cache) => Promise.allSettled(
        ESSENCIAIS.map((url) => cache.add(url))
      ))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches.keys()
      .then((chaves) => Promise.all(
        chaves.filter((c) => c !== CACHE_ESTATICO).map((c) => caches.delete(c))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (evento) => {
  const req = evento.request;

  // Só GET. POST de súmula ou de login jamais passa por cache.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Recurso de outro domínio (Google Fonts, Font Awesome): deixa o navegador cuidar.
  if (url.origin !== self.location.origin) return;

  /* Endpoints de dados e áreas autenticadas nunca são guardados: servir uma
     resposta velha aqui significaria mostrar o placar da rodada passada, ou
     pior, o painel de outra pessoa a partir do cache do aparelho. */
  const naoGuardar = /\/(ajax_|api\/|admin\/|arbitro\/|login\.php|logout\.php|perfil\.php|seguranca\.php|trocar_senha\.php|recuperar_senha\.php|redefinir_senha\.php|verificar_2fa\.php)/;
  if (naoGuardar.test(url.pathname)) return;

  // Navegação: rede primeiro; sem rede, a página de aviso.
  if (req.mode === 'navigate') {
    evento.respondWith(
      fetch(req).catch(() => caches.match('./offline.html'))
    );
    return;
  }

  /* Arquivos de aparência: cache primeiro (são versionados por ?v=mtime, então
     uma mudança no arquivo gera outra URL e o cache antigo deixa de ser
     consultado sozinho). */
  evento.respondWith(
    caches.match(req).then((guardado) => {
      if (guardado) return guardado;

      return fetch(req).then((resposta) => {
        if (!resposta || resposta.status !== 200 || resposta.type !== 'basic') {
          return resposta;
        }
        const copia = resposta.clone();
        caches.open(CACHE_ESTATICO).then((cache) => cache.put(req, copia));
        return resposta;
      }).catch(() => guardado);
    })
  );
});
