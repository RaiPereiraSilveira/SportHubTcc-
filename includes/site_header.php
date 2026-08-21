<?php
/**
 * includes/site_header.php — cabeçalho das páginas públicas.
 *
 * Defina antes do include:
 *   $page_title  (string)  título da aba
 *   $page_desc   (string)  meta description
 *   $active      (string)  chave do item de menu ativo
 *   $body_class  (string)  classe extra no <body>  [opcional]
 */
require_once __DIR__ . '/config.php';

$page_title = $page_title ?? 'SportHub — Gestão de campeonatos interclasse';
$page_desc  = $page_desc  ?? 'Plataforma completa para escolas organizarem campeonatos interclasse: times, árbitros credenciados, jogos ao vivo, classificação automática e conformidade com a LGPD.';
$active     = $active     ?? '';
$body_class = $body_class ?? '';

$nav_items = [
    'como-funciona' => ['Como funciona', 'como-funciona.php'],
    'recursos'      => ['Recursos',      'index.php#recursos'],
    'planos'        => ['Planos',        'planos.php'],
    'arbitros'      => ['Para árbitros', 'cadastro-arbitro.php'],
    'privacidade'   => ['Privacidade',   'lgpd.php'],
    'contato'       => ['Contato',       'contato.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR"<?= sh_tema_attr() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= sh_tema_boot() ?>
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e($page_desc) ?>">
<meta name="theme-color" content="#e7e5e0" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#101215" media="(prefers-color-scheme: dark)">
<meta name="author" content="SportHub">

<meta property="og:type" content="website">
<meta property="og:site_name" content="SportHub">
<meta property="og:title" content="<?= e($page_title) ?>">
<meta property="og:description" content="<?= e($page_desc) ?>">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= e(sh_asset('img/Logo.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(sh_asset('img/Logo.png')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e(sh_asset('css/site.css')) ?>">
</head>
<body class="<?= e($body_class) ?>">

<a class="skip-link" href="#conteudo">Ir para o conteúdo principal</a>

<header class="site-nav" id="siteNav">
    <div class="wrap site-nav-inner">
        <a href="<?= e(sh_url('index.php')) ?>" class="brand" aria-label="SportHub — página inicial">
            <span class="brand-mark"><img src="<?= e(sh_asset('img/Logo.png')) ?>" alt=""></span>
            <span class="brand-text">
                <span class="brand-name">SportHub</span>
                <span class="brand-sub">Gestão Interclasse</span>
            </span>
        </a>

        <nav class="nav-links" aria-label="Navegação principal">
            <?php foreach ($nav_items as $chave => [$rotulo, $href]): ?>
                <a href="<?= e(sh_url($href)) ?>"<?= $active === $chave ? ' class="active" aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="nav-actions">
            <?= sh_tema_botao() ?>
            <?php if (isLogado()): ?>
                <a href="<?= e(sh_url('perfil.php')) ?>" class="btn btn-ghost btn-sm btn-hide-sm">
                    <i class="fas fa-user-circle"></i> <?= e(explode(' ', trim($_SESSION['usuario_nome'] ?? 'Minha conta'))[0]) ?>
                </a>
                <a href="<?= e(sh_url('login.php')) ?>" class="btn btn-dark btn-sm">Ir para o painel</a>
            <?php else: ?>
                <a href="<?= e(sh_url('login.php')) ?>" class="btn btn-ghost btn-sm btn-hide-sm">Entrar</a>
                <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-primary btn-sm">Assinar</a>
            <?php endif; ?>
            <button class="nav-toggle" id="navToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="navDrawer">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<div class="nav-drawer" id="navDrawer">
    <?php foreach ($nav_items as $chave => [$rotulo, $href]): ?>
        <a href="<?= e(sh_url($href)) ?>"><?= e($rotulo) ?></a>
    <?php endforeach; ?>
    <div class="drawer-actions">
        <?= sh_tema_botao('theme-toggle-wide') ?>
        <a href="<?= e(sh_url('login.php')) ?>" class="btn btn-outline btn-block">
            <i class="fas fa-right-to-bracket"></i> <?= isLogado() ? 'Ir para o painel' : 'Entrar na plataforma' ?>
        </a>
        <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-primary btn-block">Ver planos e assinar</a>
    </div>
</div>

<main id="conteudo">
