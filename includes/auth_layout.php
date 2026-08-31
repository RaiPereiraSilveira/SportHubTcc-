<?php
/**
 * includes/auth_layout.php — moldura das telas de credencial.
 *
 * Trocar senha (SH-48), recuperar senha (SH-64) e confirmar o segundo fator
 * (SH-65) são páginas que acontecem ANTES de haver sessão completa: não podem
 * usar `includes/header.php`, que já pressupõe usuário autenticado e monta o
 * menu do painel.
 *
 * Em vez de repetir o `<head>` em quatro arquivos — e correr o risco de
 * esquecer o nonce da CSP ou o script anti-flash do tema em um deles — a
 * moldura mora aqui. As cores vêm de css/style.css; nada de paleta nova.
 */

require_once __DIR__ . '/config.php';

/**
 * Abre a página.
 *
 * @param string $titulo    aparece na aba e como <h1> do cartão
 * @param string $subtitulo linha de apoio sob o título (pode ser '')
 * @param string $icone     classe do Font Awesome
 */
function sh_auth_inicio($titulo, $subtitulo = '', $icone = 'fa-shield-halved') {
    ?><!DOCTYPE html>
<html lang="pt-BR"<?= sh_tema_attr() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?= sh_tema_boot() ?>
    <title><?= e($titulo) ?> — <?= e(SH_NOME) ?></title>
    <link rel="icon" href="<?= e(sh_asset('img/Logo-96.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(sh_asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(sh_asset('css/glass.css')) ?>">
    <link rel="stylesheet" href="<?= e(sh_asset('css/u.css')) ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="tela-credencial">

<?= sh_tema_botao('theme-toggle--fixo') ?>

<main class="credencial-wrap" id="conteudo-principal">
    <section class="credencial-card">
        <header class="credencial-topo">
            <span class="credencial-icone" aria-hidden="true"><i class="fas <?= e($icone) ?>"></i></span>
            <h1><?= e($titulo) ?></h1>
            <?php if ($subtitulo !== ''): ?>
                <p class="credencial-sub"><?= $subtitulo ?></p>
            <?php endif; ?>
        </header>
<?php
}

/** Fecha a página. $voltar é um par [href, rótulo] ou null. */
function sh_auth_fim($voltar = null) {
    ?>
    </section>

    <?php if ($voltar !== null): ?>
        <p class="credencial-rodape">
            <a href="<?= e($voltar[0]) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> <?= e($voltar[1]) ?></a>
        </p>
    <?php endif; ?>
</main>

<script src="<?= e(sh_asset('js/sporthub-comportamento.js')) ?>" defer></script>
<script src="<?= e(sh_asset('js/sporthub-ui.js')) ?>" defer></script>
</body>
</html>
<?php
}

/** Caixa de alerta padronizada das telas de credencial. */
function sh_auth_alerta($tipo, $mensagem) {
    if ($mensagem === '') return;
    $papel = ($tipo === 'error') ? 'alert' : 'status';
    echo '<div class="alert alert-' . e($tipo) . '" role="' . $papel . '">'
       . e($mensagem) . '</div>';
}

/**
 * Medidor de força da senha: as três regras, ditas antes de o usuário errar.
 * É a mesma lista que sh_senha_politica() verifica no servidor.
 */
function sh_auth_regras_senha() {
    ?>
    <ul class="regras-senha">
        <li>Pelo menos <?= SH_SENHA_MINIMA ?> caracteres.</li>
        <li>Pelo menos uma letra e um número.</li>
        <li>Não pode conter o seu nome de usuário nem ser uma senha de fábrica.</li>
    </ul>
    <?php
}
