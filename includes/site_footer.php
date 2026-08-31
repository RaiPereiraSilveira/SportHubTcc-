<?php
/** includes/site_footer.php — rodapé, banner LGPD e scripts das páginas públicas. */
require_once __DIR__ . '/config.php';
?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="<?= e(sh_url('index.php')) ?>" class="brand">
                    <span class="brand-mark"><img src="<?= e(sh_asset('img/Logo-96.png')) ?>" alt="" width="96" height="96"></span>
                    <span class="brand-text">
                        <span class="brand-name">SportHub</span>
                        <span class="brand-sub">Gestão Interclasse</span>
                    </span>
                </a>
                <p class="footer-about">
                    Plataforma brasileira de gestão de campeonatos interclasse. Do sorteio das chaves
                    à súmula digital, com dados tratados conforme a Lei nº 13.709/2018 (LGPD).
                </p>
                <div class="footer-social">
                    <a href="https://wa.me/<?= e(SH_WHATSAPP) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="<?= e(SH_INSTAGRAM) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="mailto:<?= e(SH_EMAIL) ?>" aria-label="E-mail"><i class="fas fa-envelope"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Plataforma</h4>
                <ul>
                    <li><a href="<?= e(sh_url('como-funciona.php')) ?>">Como funciona</a></li>
                    <li><a href="<?= e(sh_url('index.php#recursos')) ?>">Recursos</a></li>
                    <li><a href="<?= e(sh_url('index.php#perfis')) ?>">Perfis de acesso</a></li>
                    <li><a href="<?= e(sh_url('planos.php')) ?>">Planos e preços</a></li>
                    <li><a href="<?= e(sh_url('login.php')) ?>">Entrar</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Arbitragem</h4>
                <ul>
                    <li><a href="<?= e(sh_url('cadastro-arbitro.php')) ?>">Credenciar-se</a></li>
                    <li><a href="<?= e(sh_url('cadastro-arbitro.php#requisitos')) ?>">Requisitos</a></li>
                    <li><a href="<?= e(sh_url('cadastro-arbitro.php#conduta')) ?>">Código de conduta</a></li>
                    <li><a href="<?= e(sh_url('como-funciona.php#arbitro')) ?>">Súmula digital</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Legal e privacidade</h4>
                <ul>
                    <li><a href="<?= e(sh_url('privacidade.php')) ?>">Política de Privacidade</a></li>
                    <li><a href="<?= e(sh_url('termos.php')) ?>">Termos de Uso</a></li>
                    <li><a href="<?= e(sh_url('cookies.php')) ?>">Política de Cookies</a></li>
                    <li><a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD</a></li>
                    <li><a href="mailto:<?= e(SH_EMAIL_DPO) ?>">Falar com o DPO</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> SportHub. <?= e(SH_TAGLINE) ?></span>
            <div class="footer-legal-links">
                <a href="<?= e(sh_url('privacidade.php')) ?>">Privacidade</a>
                <a href="<?= e(sh_url('termos.php')) ?>">Termos</a>
                <a href="<?= e(sh_url('cookies.php')) ?>">Cookies</a>
                <a href="<?= e(sh_url('lgpd.php')) ?>">Seus direitos</a>
            </div>
            <span class="footer-compliance"><i class="fas fa-shield-halved"></i> Conforme a LGPD — Lei nº 13.709/2018</span>
        </div>
    </div>
</footer>

<!-- Banner de consentimento (LGPD / cookies) -->
<div class="lgpd-banner hidden" id="lgpdBanner" role="dialog" aria-live="polite" aria-label="Aviso de privacidade e cookies">
    <div>
        <div class="lgpd-title"><i class="fas fa-shield-halved"></i> Privacidade e cookies</div>
        <p>
            Usamos cookies necessários para manter sua sessão e, mediante seu consentimento, cookies
            analíticos para entender como a plataforma é usada. Você decide — e pode mudar de ideia
            a qualquer momento. Saiba mais na <a href="<?= e(sh_url('cookies.php')) ?>">Política de Cookies</a>
            e na <a href="<?= e(sh_url('privacidade.php')) ?>">Política de Privacidade</a>.
        </p>
    </div>
    <div class="lgpd-actions">
        <button type="button" class="btn btn-outline btn-sm" data-lgpd="rejeitar">Somente necessários</button>
        <button type="button" class="btn btn-primary btn-sm" data-lgpd="aceitar">Aceitar todos</button>
    </div>
</div>

<script<?= sh_nonce_attr() ?>>
(function () {
    'use strict';

    /* ── Tema claro/escuro ──────────────────────────────────────────────
       O tema já foi aplicado no <head> (script de boot). Aqui só tratamos
       o clique: grava em localStorage e espelha num cookie, para que o PHP
       consiga entregar a próxima página já no tema certo. */
    var CHAVE_TEMA = 'sporthub-tema';

    var aplicarTema = function (tema) {
        document.documentElement.setAttribute('data-theme', tema);
        try { localStorage.setItem(CHAVE_TEMA, tema); } catch (e) {}
        document.cookie = CHAVE_TEMA + '=' + tema + ';path=/;max-age=31536000;samesite=Lax';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
            b.setAttribute('aria-pressed', tema === 'dark' ? 'true' : 'false');
            var rotulo = b.querySelector('.sr-only');
            if (rotulo) rotulo.textContent = tema === 'dark' ? 'Tema claro' : 'Tema escuro';
        });
    };

    aplicarTema(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-toggle]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            aplicarTema(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    });

    /* Sombra da navbar ao rolar */
    var nav = document.getElementById('siteNav');
    if (nav) {
        var onScroll = function () { nav.classList.toggle('scrolled', window.scrollY > 8); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* Menu mobile */
    var toggle = document.getElementById('navToggle');
    var drawer = document.getElementById('navDrawer');
    if (toggle && drawer) {
        toggle.addEventListener('click', function () {
            var open = drawer.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.innerHTML = open ? '<i class="fas fa-xmark"></i>' : '<i class="fas fa-bars"></i>';
        });
        drawer.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                drawer.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.innerHTML = '<i class="fas fa-bars"></i>';
            });
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1000) {
                drawer.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
    }

    /* Animação de entrada dos blocos.
       O conteúdo nunca pode ficar preso invisível: além do observer, revelamos
       o que já está na tela no carregamento e mantemos um prazo de segurança. */
    var alvos = document.querySelectorAll('.reveal');
    if (alvos.length) {
        var revelar = function (el) { el.classList.add('visible'); };
        var revelarTudo = function () { alvos.forEach(revelar); };

        if (!('IntersectionObserver' in window)) {
            revelarTudo();
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        revelar(entry.target);
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

            alvos.forEach(function (el, i) {
                el.style.transitionDelay = Math.min(i % 4, 3) * 70 + 'ms';
                // Já está na área visível? Revela sem esperar o observer.
                if (el.getBoundingClientRect().top < window.innerHeight) {
                    revelar(el);
                } else {
                    io.observe(el);
                }
            });

            // Rede de segurança: se algo impedir o observer de disparar,
            // o conteúdo aparece de qualquer forma.
            setTimeout(revelarTudo, 3000);
        }
    }

    /* Índice ativo nas páginas legais */
    var toc = document.querySelector('.legal-toc');
    if (toc) {
        var secoes = document.querySelectorAll('.legal-body section[id]');
        if (secoes.length && 'IntersectionObserver' in window) {
            var spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    toc.querySelectorAll('a').forEach(function (a) {
                        a.classList.toggle('active', a.getAttribute('href') === '#' + entry.target.id);
                    });
                });
            }, { rootMargin: '-15% 0px -70% 0px' });
            secoes.forEach(function (s) { spy.observe(s); });
        }
    }

    /* Consentimento LGPD — registrado no servidor para fins de prova (art. 8º, §1º) */
    var banner = document.getElementById('lgpdBanner');
    if (!banner) return;

    var CHAVE = 'sporthub_consentimento_v<?= e(SH_VERSAO_POLITICA) ?>';
    var salvo = null;
    try { salvo = localStorage.getItem(CHAVE); } catch (e) {}

    if (!salvo) {
        setTimeout(function () { banner.classList.remove('hidden'); }, 900);
    }

    banner.querySelectorAll('[data-lgpd]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var aceitou = btn.getAttribute('data-lgpd') === 'aceitar';
            try {
                localStorage.setItem(CHAVE, JSON.stringify({
                    analiticos: aceitou,
                    data: new Date().toISOString()
                }));
            } catch (e) {}
            banner.classList.add('hidden');

            var corpo = new URLSearchParams();
            corpo.set('finalidade', 'cookies_analiticos');
            corpo.set('concedido', aceitou ? '1' : '0');
            corpo.set('csrf_token', '<?= e(generate_csrf_token()) ?>');
            fetch('<?= e(sh_url('api/consentimento.php')) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: corpo.toString()
            }).catch(function () { /* consentimento local já foi gravado */ });
        });
    });

    /* Link "Gerenciar cookies" reabre o banner */
    document.querySelectorAll('[data-abrir-cookies]').forEach(function (link) {
        link.addEventListener('click', function (ev) {
            ev.preventDefault();
            banner.classList.remove('hidden');
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
})();
</script>

<script src="<?= e(sh_asset('js/sporthub-comportamento.js')) ?>" defer></script>
<script src="<?= e(sh_asset('js/sporthub-ui.js')) ?>" defer></script>
<?= sh_registro_service_worker() ?>

</body>
</html>
