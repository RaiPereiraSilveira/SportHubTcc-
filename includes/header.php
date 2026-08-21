<?php
// includes/header.php — cabeçalho das páginas autenticadas (painel).
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . sh_url('login.php'));
    exit();
}

$page_titles = [
    'dashboard.php'            => 'Dashboard',
    'times.php'                => 'Gerenciar Times',
    'team_logos.php'           => 'Escudos dos Times',
    'modalidades.php'          => 'Modalidades Esportivas',
    'jogos.php'                => 'Agendar Jogos',
    'arbitros.php'             => 'Gerenciar Árbitros',
    'solicitacoes_arbitros.php'=> 'Credenciamento de Árbitros',
    'assinaturas.php'          => 'Assinaturas e Mensagens',
    'lgpd.php'                 => 'Portal LGPD',
    'painel.php'               => 'Painel Principal',
    'classificacao.php'        => 'Classificação',
    'resultados.php'           => 'Resultados',
    'perfil.php'               => 'Meu Perfil',
    'ver_jogadores.php'        => 'Jogadores',
    'designar_jogos.php'       => 'Designar Jogos',
    'registrar_resultado.php'  => 'Registrar Resultado',
    'painel_arbitro.php'       => 'Painel Árbitro',
];

$current_page = basename($_SERVER['PHP_SELF']);
$tipo_usuario = $_SESSION['usuario_tipo'] ?? 'aluno';

// Alguns nomes de arquivo se repetem entre os perfis (jogos.php, painel.php,
// resultados.php). O título correto depende de quem está logado.
$titulos_por_perfil = [
    'aluno'   => ['jogos.php' => 'Jogos', 'painel.php' => 'Início', 'resultados.php' => 'Resultados'],
    'arbitro' => ['painel.php' => 'Meus Jogos', 'resultados.php' => 'Registrar Resultados'],
];
$page_title = $titulos_por_perfil[$tipo_usuario][$current_page]
    ?? $page_titles[$current_page]
    ?? 'SportHub';

$roles_label = [
    'admin'   => 'Administrador',
    'arbitro' => 'Árbitro',
    'aluno'   => 'Aluno',
];

$web_root       = sh_web_root();
$logo_path      = sh_asset('img/Logo.png');
$perfil_path    = sh_url('perfil.php');
$logout_path    = sh_url('logout.php');
$home_por_perfil = [
    'admin'   => sh_url('admin/dashboard.php'),
    'arbitro' => sh_url('arbitro/painel.php'),
    'aluno'   => sh_url('aluno/painel.php'),
];
$home_path = $home_por_perfil[$tipo_usuario] ?? sh_url('aluno/painel.php');

// Foto de perfil (sessão primeiro, banco como fallback).
$profilePhotoUrl = '';
if (!empty($_SESSION['usuario_foto'])) {
    $profilePhotoUrl = sh_url(ltrim($_SESSION['usuario_foto'], '/'));
} elseif (isset($pdo, $_SESSION['usuario_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $userFoto = $stmt->fetchColumn();
        if ($userFoto) {
            $_SESSION['usuario_foto'] = $userFoto;
            $profilePhotoUrl = sh_url(ltrim($userFoto, '/'));
        }
    } catch (PDOException $ex) {
        // Não bloqueia a página se a foto não puder ser carregada.
    }
}

// Pendências que merecem atenção do administrador.
$pendencias = ['credenciamentos' => 0, 'lgpd' => 0, 'mensagens' => 0];
if ($tipo_usuario === 'admin' && isset($pdo)) {
    try {
        if (sh_tabela_existe($pdo, 'arbitro_solicitacoes')) {
            $pendencias['credenciamentos'] = (int)$pdo->query(
                "SELECT COUNT(*) FROM arbitro_solicitacoes WHERE status IN ('recebida','em_analise')"
            )->fetchColumn();
        }
        if (sh_tabela_existe($pdo, 'lgpd_solicitacoes')) {
            $pendencias['lgpd'] = (int)$pdo->query(
                "SELECT COUNT(*) FROM lgpd_solicitacoes WHERE status IN ('recebida','em_analise')"
            )->fetchColumn();
        }
        if (sh_tabela_existe($pdo, 'contatos')) {
            $pendencias['mensagens'] = (int)$pdo->query("SELECT COUNT(*) FROM contatos WHERE lido = 0")->fetchColumn();
        }
    } catch (PDOException $ex) {
        error_log('Erro ao contar pendências: ' . $ex->getMessage());
    }
}
$total_pendencias = array_sum($pendencias);

generate_csrf_token();

/** Itens de navegação por perfil. */
$nav_principal = [
    'admin' => [
        ['dashboard.php',   'admin/dashboard.php',   'fa-chart-line',    'Dashboard'],
        ['times.php',       'admin/times.php',       'fa-users',         'Times'],
        ['team_logos.php',  'admin/team_logos.php',  'fa-image',         'Escudos'],
        ['modalidades.php', 'admin/modalidades.php', 'fa-running',       'Modalidades'],
        ['jogos.php',       'admin/jogos.php',       'fa-calendar-alt',  'Jogos'],
        ['arbitros.php',    'admin/arbitros.php',    'fa-bullhorn',      'Árbitros'],
    ],
    'arbitro' => [
        ['painel.php',      'arbitro/painel.php',    'fa-tachometer-alt', 'Meus Jogos'],
        ['resultados.php',  'arbitro/resultados.php','fa-clipboard-list', 'Registrar Resultados'],
    ],
    'aluno' => [
        ['painel.php',        'aluno/painel.php',        'fa-home',      'Início'],
        ['jogos.php',         'aluno/jogos.php',         'fa-futbol',    'Jogos'],
        ['classificacao.php', 'aluno/classificacao.php', 'fa-trophy',    'Classificação'],
        ['resultados.php',    'aluno/resultados.php',    'fa-list-alt',  'Resultados'],
    ],
];

$nav_gestao = [
    ['solicitacoes_arbitros.php', 'admin/solicitacoes_arbitros.php', 'fa-id-card',       'Credenciamentos', $pendencias['credenciamentos']],
    ['assinaturas.php',           'admin/assinaturas.php',           'fa-file-invoice-dollar', 'Assinaturas',   $pendencias['mensagens']],
    ['lgpd.php',                  'admin/lgpd.php',                  'fa-user-shield',   'Portal LGPD',     $pendencias['lgpd']],
];

$itens = $nav_principal[$tipo_usuario] ?? $nav_principal['aluno'];
?>
<!DOCTYPE html>
<html lang="pt-BR"<?= sh_tema_attr() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#17191c">
    <?= sh_tema_boot() ?>
    <title><?= e($page_title) ?> — SportHub</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e($logo_path) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(sh_asset('css/style.css')) ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<a class="skip-to-content" href="#conteudo-principal">Ir para o conteúdo</a>

<header class="main-header">
    <div class="header-container">
        <div class="header-content">
            <div class="header-left">
                <a href="<?= e($home_path) ?>" class="logo">
                    <div class="logo-icon"><img src="<?= e($logo_path) ?>" alt="SportHub"></div>
                    <div class="logo-text">
                        <h1>SportHub</h1>
                        <span class="logo-subtitle">Sistema Interclasse</span>
                    </div>
                </a>

                <nav class="main-nav" aria-label="Navegação principal">
                    <ul class="nav-list">
                        <?php foreach ($itens as [$arquivo, $href, $icone, $rotulo]): ?>
                            <li class="nav-item">
                                <a href="<?= e(sh_url($href)) ?>" class="nav-link <?= $current_page === $arquivo ? 'active' : '' ?>">
                                    <i class="fas <?= e($icone) ?>"></i><span><?= e($rotulo) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>

                        <?php if ($tipo_usuario === 'admin'): ?>
                            <li class="nav-item has-sub">
                                <button type="button" class="nav-link nav-sub-toggle" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-sliders"></i><span>Gestão</span>
                                    <?php if ($total_pendencias > 0): ?>
                                        <span class="nav-badge-count"><?= $total_pendencias ?></span>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-down" style="font-size:.62rem;opacity:.6"></i>
                                </button>
                                <div class="nav-sub">
                                    <?php foreach ($nav_gestao as [$arquivo, $href, $icone, $rotulo, $contagem]): ?>
                                        <a href="<?= e(sh_url($href)) ?>" class="<?= $current_page === $arquivo ? 'active' : '' ?>">
                                            <i class="fas <?= e($icone) ?>"></i><span><?= e($rotulo) ?></span>
                                            <?php if ($contagem > 0): ?><span class="nav-badge-count"><?= (int)$contagem ?></span><?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                    <a href="<?= e(sh_url('index.php')) ?>" target="_blank">
                                        <i class="fas fa-arrow-up-right-from-square"></i><span>Ver o site público</span>
                                    </a>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>

            <div class="header-right">
                <div class="user-menu">
                    <div class="user-info" id="userInfoBtn" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <div class="user-avatar">
                            <?php if (!empty($profilePhotoUrl)): ?>
                                <img src="<?= e($profilePhotoUrl) ?>" alt="Foto de <?= e($_SESSION['usuario_nome']) ?>">
                            <?php else: ?>
                                <?= e(mb_strtoupper(mb_substr($_SESSION['usuario_nome'], 0, 2))) ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?= e($_SESSION['usuario_nome']) ?></span>
                            <span class="user-role"><?= e($roles_label[$tipo_usuario] ?? 'Usuário') ?></span>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>

                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-head">
                            <span class="dropdown-head-name"><?= e($_SESSION['usuario_nome']) ?></span>
                            <span class="dropdown-head-role"><?= e($roles_label[$tipo_usuario] ?? 'Usuário') ?></span>
                        </div>

                        <a href="<?= e($perfil_path) ?>" class="dropdown-item">
                            <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
                        </a>
                        <a href="<?= e(sh_url('lgpd.php')) ?>" class="dropdown-item">
                            <i class="fas fa-shield-halved"></i><span>Meus dados e privacidade</span>
                        </a>

                        <hr class="dropdown-sep">

                        <button type="button" class="dropdown-item dropdown-toggle-item" id="themeToggle" role="switch" aria-checked="false">
                            <i class="fas fa-moon" id="themeToggleIcon"></i>
                            <span id="themeToggleLabel">Tema escuro</span>
                            <span class="theme-switch" aria-hidden="true"><span class="theme-switch-knob"></span></span>
                        </button>

                        <hr class="dropdown-sep">

                        <a href="<?= e($logout_path) ?>" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i><span>Sair do Sistema</span>
                        </a>
                    </div>
                </div>

                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menu" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
</header>

<div class="mobile-menu" id="mobileMenu">
    <nav aria-label="Navegação móvel">
        <ul class="mobile-nav-list">
            <?php foreach ($itens as [$arquivo, $href, $icone, $rotulo]): ?>
                <li class="mobile-nav-item">
                    <a href="<?= e(sh_url($href)) ?>" class="mobile-nav-link <?= $current_page === $arquivo ? 'active' : '' ?>">
                        <i class="fas <?= e($icone) ?>"></i><span><?= e($rotulo) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>

            <?php if ($tipo_usuario === 'admin'): ?>
                <li class="mobile-nav-sep">Gestão</li>
                <?php foreach ($nav_gestao as [$arquivo, $href, $icone, $rotulo, $contagem]): ?>
                    <li class="mobile-nav-item">
                        <a href="<?= e(sh_url($href)) ?>" class="mobile-nav-link <?= $current_page === $arquivo ? 'active' : '' ?>">
                            <i class="fas <?= e($icone) ?>"></i><span><?= e($rotulo) ?></span>
                            <?php if ($contagem > 0): ?><span class="nav-badge-count"><?= (int)$contagem ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>

            <li class="mobile-nav-item"><a href="<?= e($perfil_path) ?>" class="mobile-nav-link"><i class="fas fa-user-circle"></i><span>Meu Perfil</span></a></li>
            <li class="mobile-nav-item"><a href="<?= e($logout_path) ?>" class="mobile-nav-link logout"><i class="fas fa-sign-out-alt"></i><span>Sair</span></a></li>
        </ul>
    </nav>
</div>

<div id="conteudo-principal">

<script>
(function () {
    'use strict';

    var userInfoBtn  = document.getElementById('userInfoBtn');
    var userDropdown = document.getElementById('userDropdown');
    if (userInfoBtn && userDropdown) {
        var alternarUsuario = function (ev) {
            ev.stopPropagation();
            var aberto = userDropdown.classList.toggle('show');
            userInfoBtn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        };
        userInfoBtn.addEventListener('click', alternarUsuario);
        userInfoBtn.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); alternarUsuario(ev); }
        });
        document.addEventListener('click', function () {
            userDropdown.classList.remove('show');
            userInfoBtn.setAttribute('aria-expanded', 'false');
        });
        // Cliques dentro do menu não devem fechá-lo (ex.: alternar o tema).
        userDropdown.addEventListener('click', function (ev) { ev.stopPropagation(); });
    }

    /* Alternância de tema claro/escuro — a preferência fica no navegador. */
    var themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        var icone  = document.getElementById('themeToggleIcon');
        var rotulo = document.getElementById('themeToggleLabel');

        var refletir = function (tema) {
            var escuro = tema === 'dark';
            themeToggle.setAttribute('aria-checked', escuro ? 'true' : 'false');
            if (rotulo) rotulo.textContent = escuro ? 'Tema claro' : 'Tema escuro';
            if (icone)  icone.className = escuro ? 'fas fa-sun' : 'fas fa-moon';
        };

        refletir(document.documentElement.getAttribute('data-theme'));

        themeToggle.addEventListener('click', function () {
            var novo = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', novo);
            try { localStorage.setItem('sporthub-tema', novo); } catch (e) {}
            // Espelha no cookie: assim o servidor já monta a próxima página
            // (inclusive as do site público) no tema escolhido.
            document.cookie = 'sporthub-tema=' + novo + ';path=/;max-age=31536000;samesite=Lax';
            refletir(novo);
        });
    }

    // Submenu "Gestão" — abre no clique e fecha ao clicar fora ou com Esc.
    document.querySelectorAll('.nav-item.has-sub').forEach(function (item) {
        var botao = item.querySelector('.nav-sub-toggle');
        if (!botao) return;
        botao.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var aberto = item.classList.toggle('open');
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
        document.addEventListener('click', function () {
            item.classList.remove('open');
            botao.setAttribute('aria-expanded', 'false');
        });
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        document.querySelectorAll('.nav-item.has-sub.open').forEach(function (i) { i.classList.remove('open'); });
        if (userDropdown) userDropdown.classList.remove('show');
    });

    var mobileMenuToggle = document.getElementById('mobileMenuToggle');
    var mobileMenu       = document.getElementById('mobileMenu');
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function () {
            var aberto = mobileMenu.classList.toggle('show');
            mobileMenuToggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            mobileMenuToggle.innerHTML = aberto ? '<i class="fas fa-xmark"></i>' : '<i class="fas fa-bars"></i>';
        });
        mobileMenu.querySelectorAll('.mobile-nav-link').forEach(function (link) {
            link.addEventListener('click', function () { mobileMenu.classList.remove('show'); });
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                mobileMenu.classList.remove('show');
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
                mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
    }
})();
</script>
