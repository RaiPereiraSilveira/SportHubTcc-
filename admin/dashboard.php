<?php
/** admin/dashboard.php — visão geral do campeonato para a coordenação. */
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');

$stats = [
    'usuarios'    => (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
    'times'       => (int)$pdo->query("SELECT COUNT(*) FROM times")->fetchColumn(),
    'modalidades' => (int)$pdo->query("SELECT COUNT(*) FROM modalidades")->fetchColumn(),
    'jogos'       => (int)$pdo->query("SELECT COUNT(*) FROM jogos")->fetchColumn(),
];

$jogos_status = ['agendado' => 0, 'em_andamento' => 0, 'finalizado' => 0];
try {
    foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM jogos GROUP BY status") as $linha) {
        $jogos_status[$linha['status']] = (int)$linha['total'];
    }
} catch (PDOException $ex) {
    error_log('Erro ao agregar jogos: ' . $ex->getMessage());
}

// Próximas partidas agendadas.
$proximos = [];
try {
    $stmt = $pdo->query(
        "SELECT j.data_jogo, j.hora, j.local, j.fase,
                m.nome AS modalidade, t1.nome AS time1, t2.nome AS time2,
                u.nome AS arbitro
         FROM jogos j
         LEFT JOIN modalidades m ON m.id = j.modalidade_id
         LEFT JOIN times t1 ON t1.id = j.time1_id
         LEFT JOIN times t2 ON t2.id = j.time2_id
         LEFT JOIN usuarios u ON u.id = j.arbitro_id
         WHERE j.status = 'agendado'
         ORDER BY j.data_jogo ASC, j.hora ASC
         LIMIT 5"
    );
    $proximos = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log('Erro ao carregar próximos jogos: ' . $ex->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="page-hero-inner">
        <span class="page-hero-badge">Painel Administrativo</span>
        <h2>Dashboard</h2>
        <p>Bem-vindo, <?= e($_SESSION['usuario_nome']) ?>. Aqui está o retrato do campeonato agora.</p>
    </div>
</div>

<div class="container">

    <?php if ($total_pendencias > 0): ?>
        <div class="alert alert-warning">
            <strong>Você tem <?= (int)$total_pendencias ?> item(ns) aguardando ação.</strong>
            <?php if ($pendencias['credenciamentos'] > 0): ?>
                <a href="solicitacoes_arbitros.php" style="text-decoration:underline"><?= (int)$pendencias['credenciamentos'] ?> credenciamento(s) de árbitro</a>.
            <?php endif; ?>
            <?php if ($pendencias['lgpd'] > 0): ?>
                <a href="lgpd.php" style="text-decoration:underline"><?= (int)$pendencias['lgpd'] ?> solicitação(ões) de LGPD</a>.
            <?php endif; ?>
            <?php if ($pendencias['mensagens'] > 0): ?>
                <a href="assinaturas.php" style="text-decoration:underline"><?= (int)$pendencias['mensagens'] ?> mensagem(ns) não lida(s)</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-stats">
        <div class="stat-card">
            <img src="../img/user.png" width="40" height="40" alt="">
            <div class="stat-number"><?= $stats['usuarios'] ?></div>
            <div class="stat-label">Usuários</div>
        </div>
        <div class="stat-card">
            <img src="../img/times.png" width="40" height="40" alt="">
            <div class="stat-number"><?= $stats['times'] ?></div>
            <div class="stat-label">Times</div>
        </div>
        <div class="stat-card">
            <img src="../img/modal.png" width="40" height="40" alt="">
            <div class="stat-number"><?= $stats['modalidades'] ?></div>
            <div class="stat-label">Modalidades</div>
        </div>
        <div class="stat-card">
            <img src="../img/jogos.png" width="40" height="40" alt="">
            <div class="stat-number"><?= $stats['jogos'] ?></div>
            <div class="stat-label">Jogos</div>
        </div>
    </div>

    <div class="content-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:22px;margin-top:26px">
        <section class="admin-panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="fas fa-calendar-day"></i> Próximas partidas</h2>
                <div class="panel-actions"><a href="jogos.php" class="btn-small btn-secondary">Ver todas</a></div>
            </div>
            <?php if (!$proximos): ?>
                <div class="no-data"><i class="fas fa-calendar-xmark no-games-icon"></i> Nenhuma partida agendada.</div>
            <?php else: ?>
                <div class="players-list">
                    <?php foreach ($proximos as $j): ?>
                        <div class="evento-item" style="align-items:flex-start">
                            <div style="flex:1;min-width:0">
                                <div class="evento-tipo"><?= e($j['time1'] ?? '—') ?> <span class="vs">vs</span> <?= e($j['time2'] ?? '—') ?></div>
                                <div class="team-stats">
                                    <?= e($j['modalidade'] ?? 'Modalidade') ?>
                                    <?= $j['fase'] ? ' · ' . e($j['fase']) : '' ?>
                                    <?= $j['local'] ? ' · ' . e($j['local']) : '' ?>
                                    <br>
                                    Árbitro: <?= $j['arbitro'] ? e($j['arbitro']) : '<em class="not-assigned">não designado</em>' ?>
                                </div>
                            </div>
                            <span class="evento-time">
                                <?= $j['data_jogo'] ? e(date('d/m', strtotime($j['data_jogo']))) : '—' ?>
                                <?= $j['hora'] ? ' ' . e(substr($j['hora'], 0, 5)) : '' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="fas fa-chart-pie"></i> Situação dos jogos</h2>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $jogos_status['agendado'] ?></div>
                    <div class="stat-label">Agendados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $jogos_status['em_andamento'] ?></div>
                    <div class="stat-label">Em andamento</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $jogos_status['finalizado'] ?></div>
                    <div class="stat-label">Finalizados</div>
                </div>
            </div>
            <?php if ($stats['jogos'] > 0): ?>
                <p class="subtitle" style="margin-top:18px">
                    <?= round($jogos_status['finalizado'] / max($stats['jogos'], 1) * 100) ?>% do campeonato já foi disputado.
                </p>
            <?php endif; ?>
        </section>
    </div>

    <h3 class="section-title" style="margin-top:34px">Gerenciamento do campeonato</h3>
    <div class="nav-grid">
        <a href="times.php" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-users"></i></div>
            <h3>Times</h3>
            <p>Gerenciar times e jogadores do campeonato</p>
        </a>
        <a href="modalidades.php" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-running"></i></div>
            <h3>Modalidades</h3>
            <p>Cadastrar e editar esportes disponíveis</p>
        </a>
        <a href="jogos.php" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-calendar-alt"></i></div>
            <h3>Jogos</h3>
            <p>Agendar e gerenciar partidas</p>
        </a>
        <a href="arbitros.php" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-bullhorn"></i></div>
            <h3>Árbitros</h3>
            <p>Gerenciar e designar árbitros para os jogos</p>
        </a>
    </div>

    <h3 class="section-title" style="margin-top:34px">Gestão institucional</h3>
    <div class="nav-grid">
        <a href="solicitacoes_arbitros.php" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-id-card"></i></div>
            <h3>Credenciamentos<?= $pendencias['credenciamentos'] > 0 ? ' <span class="nav-badge-count">' . (int)$pendencias['credenciamentos'] . '</span>' : '' ?></h3>
            <p>Analisar solicitações de profissionais aplicadores</p>
        </a>
        <a href="assinaturas.php" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <h3>Assinaturas<?= $pendencias['mensagens'] > 0 ? ' <span class="nav-badge-count">' . (int)$pendencias['mensagens'] . '</span>' : '' ?></h3>
            <p>Contratações e mensagens recebidas pelo site</p>
        </a>
        <a href="lgpd.php" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-user-shield"></i></div>
            <h3>Portal LGPD<?= $pendencias['lgpd'] > 0 ? ' <span class="nav-badge-count">' . (int)$pendencias['lgpd'] . '</span>' : '' ?></h3>
            <p>Requisições de titulares e registro de consentimentos</p>
        </a>
        <a href="<?= e(sh_url('index.php')) ?>" target="_blank" class="nav-card">
            <div class="nav-card-icon"><i class="fas fa-arrow-up-right-from-square"></i></div>
            <h3>Site público</h3>
            <p>Ver a página que as escolas e árbitros acessam</p>
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
