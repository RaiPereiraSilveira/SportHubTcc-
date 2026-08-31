/* ==========================================================================
   SPORTHUB — comportamento da interface (SH-37)
   --------------------------------------------------------------------------
   Este arquivo nasceu ao tirar 'unsafe-inline' da Content-Security-Policy.

   Um atributo `onclick="..."` escrito no HTML é código embutido: o navegador
   só o executa se a política permitir script inline — e permitir script
   inline é justamente o que faz um XSS virar sequestro de sessão. Nonce não
   resolve atributo de evento; a única saída é não ter nenhum.

   Então os punhados de `onclick`, `onchange`, `onfocus` e `onerror` que
   existiam espalhados pelas páginas viraram atributos `data-*` declarativos,
   e o comportamento correspondente mora aqui, ligado por delegação no
   documento inteiro. Delegação também resolve o conteúdo que chega depois,
   como as linhas que o placar ao vivo redesenha.

   Por que NÃO fica em js/sporthub-ui.js: aquele arquivo é enfeite e declara
   isso — roda inteiro dentro de um try/catch e pode falhar sem consequência.
   O que está aqui é comportamento de verdade (confirmar antes de excluir,
   enviar o filtro escolhido). Merece um arquivo próprio, sem try/catch
   global engolindo erro em silêncio.

   Degradação: sem JavaScript, os links de exclusão continuam funcionando
   (levam à página que já exige CSRF e confirma no servidor) e os filtros
   continuam tendo o botão de enviar visível — nenhum caminho depende deste
   arquivo para existir.

   Atributos reconhecidos:

     data-confirmar="mensagem"     pergunta antes de seguir o link ou enviar
     data-auto-submit              envia o formulário ao mudar o campo
     data-selecionar-ao-focar      seleciona todo o texto ao receber o foco
     data-fallback="url"           imagem alternativa quando a original falha
     data-copiar="#id"             copia para a área de transferência
   ========================================================================== */
(function () {
    'use strict';

    var doc = document;

    /* ── Confirmação antes de uma ação destrutiva ─────────────────────────
       Vale para <a> e para <button>. O `capture` não é necessário: queremos
       justamente ser o último a falar antes da navegação.                 */
    doc.addEventListener('click', function (ev) {
        var alvo = ev.target.closest && ev.target.closest('[data-confirmar]');
        if (!alvo) return;

        var mensagem = alvo.getAttribute('data-confirmar') || 'Confirmar esta ação?';
        if (!window.confirm(mensagem)) {
            ev.preventDefault();
            ev.stopPropagation();
        }
    });

    /* ── Filtro que se envia sozinho ao mudar ─────────────────────────────
       O <select> de modalidade nas telas do aluno e o de status nas
       assinaturas. O botão "Filtrar" continua no HTML para quem está sem
       JavaScript ou navegando por teclado com JS desligado.               */
    doc.addEventListener('change', function (ev) {
        var alvo = ev.target;
        if (!alvo || !alvo.matches || !alvo.matches('[data-auto-submit]')) return;

        var form = alvo.form || (alvo.closest && alvo.closest('form'));
        if (form) {
            /* requestSubmit dispara a validação do formulário; submit() a
               pularia. Nem todo navegador tem, daí o desvio. */
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        }
    });

    /* ── Campo que se seleciona ao receber o foco ─────────────────────────
       É o endereço do calendário .ics: quem clica ali quer copiar o link
       inteiro, não posicionar o cursor no meio dele.                      */
    doc.addEventListener('focusin', function (ev) {
        var alvo = ev.target;
        if (alvo && alvo.matches && alvo.matches('[data-selecionar-ao-focar]')) {
            if (typeof alvo.select === 'function') alvo.select();
        }
    });

    /* ── Imagem que falhou ────────────────────────────────────────────────
       Escudo de time que ainda não foi enviado, ou arquivo removido do
       servidor. O evento `error` de <img> não borbulha, então a escuta é na
       fase de captura — que é como se pega um evento que não sobe.

       A troca acontece uma vez só: se a própria imagem alternativa falhar,
       o navegador mostra o ícone quebrado em vez de entrar em laço.       */
    doc.addEventListener('error', function (ev) {
        var img = ev.target;
        if (!img || img.tagName !== 'IMG') return;
        if (img.getAttribute('data-fallback-aplicado') === '1') return;

        var alternativa = img.getAttribute('data-fallback');
        if (!alternativa) return;

        img.setAttribute('data-fallback-aplicado', '1');
        img.src = alternativa;
    }, true);

    /* ── Copiar para a área de transferência ──────────────────────────────
       Usado no endereço do calendário e no código da cobrança Pix. O
       navegador antigo cai no execCommand; se nem isso existir, o texto
       continua selecionável à mão.                                        */
    doc.addEventListener('click', function (ev) {
        var botao = ev.target.closest && ev.target.closest('[data-copiar]');
        if (!botao) return;

        var campo = doc.querySelector(botao.getAttribute('data-copiar'));
        if (!campo) return;

        ev.preventDefault();
        var texto = campo.value !== undefined ? campo.value : campo.textContent;

        function avisar(ok) {
            var rotulo = botao.getAttribute('data-rotulo-original') || botao.textContent;
            botao.setAttribute('data-rotulo-original', rotulo);
            botao.textContent = ok ? 'Copiado!' : 'Selecione e copie';
            window.setTimeout(function () { botao.textContent = rotulo; }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(function () { avisar(true); },
                                                      function () { avisar(false); });
        } else {
            try {
                if (campo.select) campo.select();
                avisar(doc.execCommand('copy'));
            } catch (e) {
                avisar(false);
            }
        }
    });
    /* ── Barras proporcionais ─────────────────────────────────────────────
       Os indicadores da direção (SH-72) e os medidores de limite de plano
       desenham barras cuja largura depende de dado. Largura variável não cabe
       numa classe CSS fixa, e `style="width:70%"` no HTML voltaria a exigir
       'unsafe-inline' na CSP — a permissão que o SH-37 tirou.

       A saída é escrever a largura pelo CSSOM: estilo aplicado por script já
       autorizado não passa pela política de style-src. Sem JavaScript, a barra
       fica vazia e o número ao lado dela continua dizendo tudo — a barra é
       ilustração, o valor é o dado.                                        */
    function pintarBarras(raiz) {
        var alvos = (raiz || doc).querySelectorAll('[data-largura]');
        for (var i = 0; i < alvos.length; i++) {
            var pct = parseFloat(alvos[i].getAttribute('data-largura'));
            if (isNaN(pct)) continue;
            alvos[i].style.width = Math.max(0, Math.min(100, pct)) + '%';
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', function () { pintarBarras(); });
    } else {
        pintarBarras();
    }

})();
