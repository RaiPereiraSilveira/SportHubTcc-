/* ==========================================================================
   SPORTHUB — comportamento da camada de vidro (css/glass.css)
   --------------------------------------------------------------------------
   Nada aqui é necessário para a página funcionar: é tudo enfeite sobre um
   HTML que já está completo e legível. Por isso o arquivo inteiro roda dentro
   de um try/catch e todo efeito começa desligado — se o script não carregar,
   ou falhar no meio, a página continua exatamente como o servidor entregou.

   O que ele faz:
     1. informa ao CSS onde está o ponteiro dentro de cada cartão (--gx/--gy);
     2. injeta a barra de reflexo nos cartões pequenos;
     3. desenha a ondulação do clique nos botões;
     4. conta os números grandes quando eles entram na tela;
     5. revela os cartões do painel conforme a rolagem.

   Quem pediu "movimento reduzido" no sistema operacional recebe só o item 1
   (que não é animação: é posição) — e nem isso, se não houver mouse.
   ========================================================================== */
(function () {
    'use strict';

    try {

    var reduzido = window.matchMedia &&
                   window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var temMouse = !window.matchMedia ||
                   window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* Cartões que recebem a barra de reflexo. A barra exige overflow:hidden
       no cartão, então ficam de fora os blocos que têm filho propositalmente
       para fora da caixa — .plan, por exemplo, carrega o selo "mais
       escolhido" em top:-13px, e recortá-lo seria um defeito visível.     */
    var SEL_REFLEXO = '.sh-glass, .sh-glass-dark, .card, .step, .role-card,' +
                      '.stat-card, .nav-card, .game-card, .result-card,' +
                      '.player-card, .student-card, .account-type-card';

    /* Blocos que recebem só o brilho que segue o cursor. */
    var SEL_SO_BRILHO = '.plan, .form-card, .faq-item, .legal-toc,' +
                        '.admin-panel, .student-panel, .referee-panel, .form-panel,' +
                        '.profile-panel, .results-section, .ranking-section,' +
                        '.players-section, .stats-section, .games-table-section,' +
                        '.game-info-card';

    var SEL_VIDRO = SEL_REFLEXO + ',' + SEL_SO_BRILHO;

    var $$ = function (sel, raiz) {
        return Array.prototype.slice.call((raiz || document).querySelectorAll(sel));
    };

    /* ── 0. Troca de tema sem arrasto ───────────────────────────────────
       Três telas diferentes trocam o tema (site, login e painel), cada uma
       com seu próprio botão. Em vez de alterar os três, observamos o único
       ponto por onde todas passam: o atributo data-theme no <html>.

       Enquanto ele muda, .sh-tema-trocando desliga todas as transições. Sem
       isso, as cores levam meio segundo para virar — e nos blocos fora da
       área visível o navegador suspende a interpolação pela metade, deixando
       cartões com a borda do tema anterior até que se role até eles.      */
    if ('MutationObserver' in window) {
        var raiz = document.documentElement;
        var destravar = null;

        new MutationObserver(function () {
            raiz.classList.add('sh-tema-trocando');
            clearTimeout(destravar);
            destravar = setTimeout(function () {
                raiz.classList.remove('sh-tema-trocando');
            }, 150);
        }).observe(raiz, { attributes: true, attributeFilter: ['data-theme'] });
    }

    /* ── 1. Brilho que acompanha o ponteiro ─────────────────────────────
       Um único listener no documento, com o trabalho adiado para o próximo
       quadro. Sem isso, mover o mouse sobre uma tela com 40 cartões dispara
       dezenas de leituras de layout por segundo.                          */
    if (temMouse && 'closest' in Element.prototype) {
        var pendente = null;

        var aplicar = function () {
            pendente = null;
            var dados = ultimo;
            if (!dados) return;
            var r = dados.el.getBoundingClientRect();
            if (!r.width || !r.height) return;
            dados.el.style.setProperty('--gx', ((dados.x - r.left) / r.width  * 100).toFixed(1) + '%');
            dados.el.style.setProperty('--gy', ((dados.y - r.top)  / r.height * 100).toFixed(1) + '%');
        };

        var ultimo = null;

        document.addEventListener('pointermove', function (ev) {
            var alvo = ev.target && ev.target.closest ? ev.target.closest(SEL_VIDRO) : null;
            if (!alvo) return;
            ultimo = { el: alvo, x: ev.clientX, y: ev.clientY };
            if (pendente === null) pendente = window.requestAnimationFrame(aplicar);
        }, { passive: true });
    }

    /* ── 2. Barra de reflexo ────────────────────────────────────────────
       Um <span> vazio e decorativo dentro de cada cartão. Fica fora do fluxo
       (position:absolute no CSS) e é invisível para leitores de tela.      */
    if (!reduzido && temMouse) {
        $$(SEL_REFLEXO).forEach(function (cartao) {
            if (cartao.classList.contains('sh-sheen')) return;

            /* position:relative é pré-requisito para a barra se ancorar.
               O CSS já define isso, mas um cartão com estilo inline pode ter
               sobrescrito — então conferimos antes de injetar.             */
            var pos = window.getComputedStyle(cartao).position;
            if (pos === 'static') cartao.style.position = 'relative';

            cartao.classList.add('sh-sheen');
            var barra = document.createElement('span');
            barra.className = 'sh-sheen-bar';
            barra.setAttribute('aria-hidden', 'true');
            cartao.appendChild(barra);
        });
    }

    /* ── 3. Ondulação do clique ─────────────────────────────────────────
       Desenhada a partir do ponto clicado, e removida sozinha ao fim da
       animação — não sobra nó no DOM.                                     */
    if (!reduzido) {
        document.addEventListener('pointerdown', function (ev) {
            var botao = ev.target && ev.target.closest ? ev.target.closest('.btn') : null;
            if (!botao || botao.disabled) return;

            var r = botao.getBoundingClientRect();
            var lado = Math.max(r.width, r.height) * 2.1;

            var onda = document.createElement('span');
            onda.className = 'sh-ripple';
            onda.style.width = onda.style.height = lado + 'px';
            onda.style.left = (ev.clientX - r.left) + 'px';
            onda.style.top  = (ev.clientY - r.top)  + 'px';
            botao.appendChild(onda);

            var limpar = function () { if (onda.parentNode) onda.parentNode.removeChild(onda); };
            onda.addEventListener('animationend', limpar);
            setTimeout(limpar, 900);   // rede de segurança
        }, { passive: true });
    }

    /* ── 4. Contagem dos números ────────────────────────────────────────
       Só entram na contagem os textos que são um número puro (com os pontos
       de milhar do português). "R$ 1.188,00", "12/40" e "—" ficam de fora:
       animar esses valores só produziria lixo na tela.                     */
    var contaveis = [];
    if (!reduzido && 'IntersectionObserver' in window) {
        $$('.stat-number, .hero-meta-num, .hero-stat-num, [data-sh-count]').forEach(function (el) {
            var bruto = el.textContent.trim();
            var m = bruto.match(/^([+~]?)([\d.]+)([%+ºª°]?)$/);
            if (!m) return;

            var digitos = m[2].replace(/\./g, '');
            if (!/^\d+$/.test(digitos)) return;

            var alvo = parseInt(digitos, 10);
            if (!isFinite(alvo) || alvo <= 0 || alvo > 1000000) return;

            contaveis.push({ el: el, prefixo: m[1], alvo: alvo, sufixo: m[3],
                             agrupa: m[2].indexOf('.') !== -1, original: bruto });
        });
    }

    if (contaveis.length) {
        var formatar = function (item, valor) {
            var txt = item.agrupa ? valor.toLocaleString('pt-BR') : String(valor);
            return item.prefixo + txt + item.sufixo;
        };

        var contar = function (item) {
            var inicio = null;
            var duracao = 900;
            var passo = function (agora) {
                if (inicio === null) inicio = agora;
                var t = Math.min((agora - inicio) / duracao, 1);
                var suave = 1 - Math.pow(1 - t, 3);          // desacelera no fim
                item.el.textContent = formatar(item, Math.round(item.alvo * suave));
                if (t < 1) {
                    window.requestAnimationFrame(passo);
                } else {
                    item.el.textContent = item.original;      // volta ao texto exato
                }
            };
            window.requestAnimationFrame(passo);
        };

        var obsNum = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (!entrada.isIntersecting) return;
                obsNum.unobserve(entrada.target);
                var item = contaveis.filter(function (i) { return i.el === entrada.target; })[0];
                if (item) contar(item);
            });
        }, { threshold: 0.4 });

        contaveis.forEach(function (item) { obsNum.observe(item.el); });

        /* Se o observer não disparar por qualquer motivo, os números voltam
           ao valor final — nunca ficam zerados na tela.                    */
        setTimeout(function () {
            contaveis.forEach(function (i) {
                if (i.el.textContent.trim() !== i.original) i.el.textContent = i.original;
            });
        }, 4000);
    }

    /* ── 5. Entrada dos cartões do painel ───────────────────────────────
       As páginas públicas já têm .reveal, tratado em includes/site_footer.php.
       Aqui cobrimos o painel, que não tinha nada. Se já houver .reveal na
       página, saímos: duas animações sobre o mesmo bloco brigam entre si.  */
    if (!reduzido && document.documentElement.classList.contains('js') &&
        !document.querySelector('.reveal')) {

        var blocos = $$('.stat-card, .nav-card, .game-card, .result-card,' +
                        '.player-card, .student-card, .admin-panel,' +
                        '.student-panel, .referee-panel, .results-section,' +
                        '.ranking-section, .players-section, .stats-section');

        if (blocos.length && 'IntersectionObserver' in window) {
            var mostrar = function (el) { el.classList.add('visible'); };

            var obsBloco = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (entrada) {
                    if (!entrada.isIntersecting) return;
                    mostrar(entrada.target);
                    obsBloco.unobserve(entrada.target);
                });
            }, { threshold: 0.06, rootMargin: '0px 0px -30px 0px' });

            blocos.forEach(function (el, i) {
                el.classList.add('sh-reveal');
                el.style.transitionDelay = Math.min(i % 5, 4) * 60 + 'ms';
                if (el.getBoundingClientRect().top < window.innerHeight) {
                    mostrar(el);
                } else {
                    obsBloco.observe(el);
                }
            });

            /* Rede de segurança: conteúdo nunca fica preso invisível. */
            setTimeout(function () { blocos.forEach(mostrar); }, 2500);
        }
    }

    } catch (erro) {
        /* Um efeito que falha não pode levar a página junto. Registramos no
           console e deixamos o HTML sólido no lugar.                       */
        if (window.console && console.warn) {
            console.warn('SportHub: camada visual desativada —', erro);
        }
    }
})();
