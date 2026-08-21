<?php
/** 404.php — página de erro amigável (usada pelo ErrorDocument do Apache). */
require_once __DIR__ . '/includes/config.php';

http_response_code(404);

$page_title = 'Página não encontrada — SportHub';
$page_desc  = 'O endereço acessado não existe ou foi movido.';
$active     = '';

include __DIR__ . '/includes/site_header.php';
?>

<section class="section" style="min-height:52vh;display:grid;place-items:center">
    <div class="wrap-narrow center">
        <span class="pill"><span class="dot"></span> Erro 404</span>
        <h1 style="font-size:clamp(3rem,10vw,6rem);letter-spacing:-.06em">Bola fora.</h1>
        <p class="lead" style="margin:20px auto 0">
            O endereço que você acessou não existe, foi movido ou o link ficou desatualizado.
            Nada grave — dá para voltar ao jogo por aqui.
        </p>

        <div class="btn-group mt-3" style="justify-content:center">
            <a href="<?= e(sh_url('index.php')) ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-house"></i> Voltar ao início
            </a>
            <a href="<?= e(sh_url('login.php')) ?>" class="btn btn-outline btn-lg">Entrar na plataforma</a>
        </div>

        <div class="grid grid-4 mt-4" style="text-align:left">
            <a href="<?= e(sh_url('como-funciona.php')) ?>" class="card">
                <div class="card-icon"><i class="fas fa-circle-play"></i></div>
                <h3 style="font-size:1rem">Como funciona</h3>
                <p class="small">O passo a passo do campeonato na plataforma.</p>
            </a>
            <a href="<?= e(sh_url('planos.php')) ?>" class="card">
                <div class="card-icon"><i class="fas fa-tags"></i></div>
                <h3 style="font-size:1rem">Planos</h3>
                <p class="small">Assinatura anual e teste de 30 dias.</p>
            </a>
            <a href="<?= e(sh_url('cadastro-arbitro.php')) ?>" class="card">
                <div class="card-icon"><i class="fas fa-id-card"></i></div>
                <h3 style="font-size:1rem">Sou árbitro</h3>
                <p class="small">Credenciamento do profissional aplicador.</p>
            </a>
            <a href="<?= e(sh_url('contato.php')) ?>" class="card">
                <div class="card-icon"><i class="fas fa-comments"></i></div>
                <h3 style="font-size:1rem">Contato</h3>
                <p class="small">Fale com a equipe do SportHub.</p>
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
