<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('arbitro', '../login.php');

$arbitro_id = $_SESSION['usuario_id'];

$stmt = $pdo->prepare("
    SELECT j.*, m.nome AS modalidade,
           t1.nome AS time1_nome, t2.nome AS time2_nome
      FROM jogos j
      JOIN modalidades m ON j.modalidade_id = m.id
      JOIN times t1      ON j.time1_id = t1.id
      JOIN times t2      ON j.time2_id = t2.id
     WHERE j.arbitro_id = ?
  ORDER BY j.data_jogo, j.hora
");
$stmt->execute([$arbitro_id]);
$jogos = $stmt->fetchAll();

/* ── Aviso de designação (SH-58) ─────────────────────────────────────────
   Ao ser escalado, o árbitro precisa ver a partida em destaque — não achá-la
   numa lista de trinta jogos ordenada por data, misturada com o que já
   aconteceu. A separação abaixo é o aviso em tela; a versão por e-mail
   depende do envio real por SMTP (SH-42), que ainda está bloqueado.       */
$hoje = date('Y-m-d');

$em_andamento = [];
$de_hoje      = [];
$proximos     = [];
$encerrados   = [];

foreach ($jogos as $jogo) {
    if ($jogo['status'] === 'finalizado') {
        $encerrados[] = $jogo;
    } elseif ($jogo['status'] === 'em_andamento') {
        $em_andamento[] = $jogo;
    } elseif ($jogo['data_jogo'] === $hoje) {
        $de_hoje[] = $jogo;
    } elseif ($jogo['data_jogo'] >= $hoje) {
        $proximos[] = $jogo;
    } else {
        // Agendado com data no passado: continua pendente de súmula.
        $de_hoje[] = $jogo;
    }
}

$pendentes = count($em_andamento) + count($de_hoje) + count($proximos);
$destaque  = $em_andamento[0] ?? $de_hoje[0] ?? $proximos[0] ?? null;

/** Um cartão de jogo, usado nas três seções. */
function cartao_jogo(array $jogo) {
    $rotulos = ['agendado' => 'Agendado', 'em_andamento' => 'Em andamento', 'finalizado' => 'Finalizado'];
    ?>
    <div class="game-card">
        <div class="game-header">
            <span class="game-sport"><?= e($jogo['modalidade']) ?></span>
            <span class="status-badge <?= e($jogo['status']) ?>">
                <?= e($rotulos[$jogo['status']] ?? $jogo['status']) ?>
            </span>
        </div>

        <div class="teams-container">
            <div class="team">
                <div class="team-name"><?= e($jogo['time1_nome']) ?></div>
                <div class="score<?= $jogo['placar_time1'] > $jogo['placar_time2'] ? ' winning-score' : '' ?>">
                    <?= (int)$jogo['placar_time1'] ?>
                </div>
            </div>
            <div class="vs">VS</div>
            <div class="team">
                <div class="team-name"><?= e($jogo['time2_nome']) ?></div>
                <div class="score<?= $jogo['placar_time2'] > $jogo['placar_time1'] ? ' winning-score' : '' ?>">
                    <?= (int)$jogo['placar_time2'] ?>
                </div>
            </div>
        </div>

        <div class="game-details">
            <div class="detail-item">
                <span class="detail-label">Fase:</span>
                <span><?= e($jogo['fase'] ?: '—') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Data:</span>
                <span><?= $jogo['data_jogo'] ? e(date('d/m/Y', strtotime($jogo['data_jogo']))) : '—' ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Hora:</span>
                <span><?= e($jogo['hora'] ? substr($jogo['hora'], 0, 5) : '—') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Local:</span>
                <span><?= e($jogo['local'] ?: '—') ?></span>
            </div>
        </div>

        <div class="game-actions">
            <a href="registrar_resultado.php?jogo_id=<?= (int)$jogo['id'] ?>"
               class="btn <?= $jogo['status'] === 'finalizado' ? 'btn-secondary' : 'btn-accent' ?>">
                <i class="fas <?= $jogo['status'] === 'finalizado' ? 'fa-eye' : 'fa-pen-to-square' ?>" aria-hidden="true"></i>
                <?= $jogo['status'] === 'finalizado' ? 'Ver súmula' : 'Registrar resultado' ?>
            </a>
        </div>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="referee-panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title"><i class="fas fa-bullhorn" aria-hidden="true"></i> Painel do Árbitro</h2>
                <p>
                    Bem-vindo, <?= e($_SESSION['usuario_nome'] ?? '') ?>.
                    <?= $pendentes > 0
                        ? 'Você tem ' . $pendentes . ' partida' . ($pendentes === 1 ? '' : 's') . ' pendente' . ($pendentes === 1 ? '' : 's') . '.'
                        : 'Nenhuma partida pendente no momento.' ?>
                </p>
            </div>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><?= e($_SESSION['sucesso']) ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <?php if ($destaque): ?>
            <?php $eh_agora = $destaque['status'] === 'em_andamento'; ?>
            <div class="designacao <?= $eh_agora ? 'designacao--vivo' : '' ?>">
                <div class="designacao-selo">
                    <?php if ($eh_agora): ?>
                        <span class="live-badge">EM ANDAMENTO</span>
                    <?php else: ?>
                        <i class="fas fa-bell" aria-hidden="true"></i> Sua próxima designação
                    <?php endif; ?>
                </div>

                <h3 class="designacao-jogo">
                    <?= e($destaque['time1_nome']) ?>
                    <span>×</span>
                    <?= e($destaque['time2_nome']) ?>
                </h3>

                <ul class="designacao-dados">
                    <li><i class="fas fa-medal" aria-hidden="true"></i> <?= e($destaque['modalidade']) ?><?= $destaque['fase'] ? ' · ' . e($destaque['fase']) : '' ?></li>
                    <li><i class="fas fa-calendar-day" aria-hidden="true"></i>
                        <?= $destaque['data_jogo'] ? e(date('d/m/Y', strtotime($destaque['data_jogo']))) : 'Data a definir' ?>
                        <?= $destaque['data_jogo'] === $hoje ? ' (hoje)' : '' ?>
                    </li>
                    <li><i class="fas fa-clock" aria-hidden="true"></i> <?= e($destaque['hora'] ? substr($destaque['hora'], 0, 5) : '—') ?></li>
                    <li><i class="fas fa-location-dot" aria-hidden="true"></i> <?= e($destaque['local'] ?: 'Local a definir') ?></li>
                </ul>

                <a href="registrar_resultado.php?jogo_id=<?= (int)$destaque['id'] ?>" class="btn btn-accent">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i> Abrir a súmula
                </a>
            </div>
        <?php endif; ?>

        <?php if (!$jogos): ?>
            <div class="no-games">
                <div class="no-games-icon"><i class="fas fa-bullhorn" aria-hidden="true"></i></div>
                <h3>Nenhum jogo designado</h3>
                <p>Assim que a coordenação escalar você para uma partida, ela aparece aqui em destaque.</p>
                <a href="<?= e(sh_url('index.php')) ?>" class="btn btn-primary">Voltar ao início</a>
            </div>
        <?php endif; ?>

        <?php
        $secoes = [
            ['Em andamento agora', $em_andamento, 'fa-tower-broadcast'],
            ['Hoje e pendentes',   $de_hoje,      'fa-calendar-day'],
            ['Próximas partidas',  $proximos,     'fa-calendar-days'],
            ['Súmulas encerradas', $encerrados,   'fa-circle-check'],
        ];
        ?>
        <?php foreach ($secoes as [$titulo, $lista, $icone]): ?>
            <?php if (!$lista) continue; ?>
            <section class="bloco-jogos">
                <h3 class="bloco-titulo">
                    <i class="fas <?= e($icone) ?>" aria-hidden="true"></i>
                    <?= e($titulo) ?>
                    <span class="bloco-contagem"><?= count($lista) ?></span>
                </h3>
                <div class="games-grid">
                    <?php foreach ($lista as $jogo) cartao_jogo($jogo); ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
