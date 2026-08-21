<?php
// includes/footer.php — rodapé das páginas autenticadas (painel).
require_once __DIR__ . '/config.php';
?>
</div><!-- /#conteudo-principal -->

<footer class="panel-footer">
    <div class="panel-footer-inner">
        <div class="panel-footer-brand">
            <strong>SportHub</strong>
            <span>Sistema de Gerenciamento de Interclasse &copy; <?= date('Y') ?></span>
        </div>

        <nav class="panel-footer-links" aria-label="Links legais">
            <a href="<?= e(sh_url('privacidade.php')) ?>" target="_blank">Privacidade</a>
            <a href="<?= e(sh_url('termos.php')) ?>" target="_blank">Termos de Uso</a>
            <a href="<?= e(sh_url('cookies.php')) ?>" target="_blank">Cookies</a>
            <a href="<?= e(sh_url('lgpd.php')) ?>" target="_blank">Portal LGPD</a>
            <a href="mailto:<?= e(SH_EMAIL_DPO) ?>">Encarregado (DPO)</a>
        </nav>

        <span class="panel-footer-compliance">
            <i class="fas fa-shield-halved"></i> Dados protegidos conforme a LGPD — Lei nº 13.709/2018
        </span>
    </div>
</footer>

<script>
// Abas usadas nas páginas do painel.
document.querySelectorAll('.tabs').forEach(function (tabGroup) {
    tabGroup.querySelectorAll('.tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabGroup.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
            var container = tabGroup.closest('.admin-panel, .student-panel, .referee-panel') || tabGroup.parentElement;
            container.querySelectorAll('.tab-content').forEach(function (p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var pane = document.getElementById(tab.getAttribute('data-tab'));
            if (pane) pane.classList.add('active');
        });
    });
});
</script>
</body>
</html>
