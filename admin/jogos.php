<?php
include '../includes/config.php';

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/header.php';

// Mostrar mensagens de sucesso/erro da sessão
$sucesso = '';
$erro = '';

if (isset($_SESSION['sucesso'])) {
    $sucesso = $_SESSION['sucesso'];
    unset($_SESSION['sucesso']);
}

if (isset($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']);
}

// Buscar jogos agendados
$stmt = $pdo->query("
    SELECT j.*, m.nome as modalidade, t1.nome as time1_nome, t2.nome as time2_nome, u.nome as arbitro_nome
    FROM jogos j
    JOIN modalidades m ON j.modalidade_id = m.id
    JOIN times t1 ON j.time1_id = t1.id
    JOIN times t2 ON j.time2_id = t2.id
    LEFT JOIN usuarios u ON j.arbitro_id = u.id
    ORDER BY j.data_jogo, j.hora
");
$jogos = $stmt->fetchAll();
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Gerenciar Jogos</h2>
        
        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                ✅ <?= e($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                ❌ <?= e($erro) ?>
            </div>
        <?php endif; ?>
        
        <div class="panel-actions">
            <a href="editar_jogo.php?acao=gerar" class="btn btn-primary">Gerar Chaveamento</a>
        </div>

        <div class="games-table-section">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Modalidade</th>
                        <th>Times</th>
                        <th>Fase</th>
                        <th>Local</th>
                        <th>Árbitro</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($jogos) > 0): ?>
                        <?php foreach ($jogos as $jogo): ?>
                        <tr>
                            <td>
                                <div class="game-date"><?= date('d/m/Y', strtotime($jogo['data_jogo'])) ?></div>
                                <div class="game-time"><?= $jogo['hora'] ?></div>
                            </td>
                            <td><?= htmlspecialchars($jogo['modalidade']) ?></td>
                            <td>
                                <div class="teams-matchup">
                                    <span class="team-name"><?= htmlspecialchars($jogo['time1_nome']) ?></span>
                                    <span class="vs">vs</span>
                                    <span class="team-name"><?= htmlspecialchars($jogo['time2_nome']) ?></span>
                                </div>
                                <div class="game-score">
                                    <?= $jogo['placar_time1'] ?> - <?= $jogo['placar_time2'] ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($jogo['fase']) ?></td>
                            <td><?= htmlspecialchars($jogo['local']) ?></td>
                            <td>
                                <?= $jogo['arbitro_nome'] ? htmlspecialchars($jogo['arbitro_nome']) : '<span class="not-assigned">Não designado</span>' ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $jogo['status'] ?>">
                                    <?= ucfirst($jogo['status']) ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="editar_jogo.php?id=<?= $jogo['id'] ?>" class="btn-small btn-accent">
                                    <i class="fas fa-edit"></i>
                                    Editar
                                </a>
                                <a href="excluir_jogo.php?id=<?= $jogo['id'] ?>&csrf_token=<?= e(generate_csrf_token()) ?>" class="btn-small btn-danger"
                                   onclick="return confirm('Tem certeza que deseja excluir este jogo?')">
                                    <i class="fas fa-trash"></i>
                                    Excluir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-data">
                                <div class="no-games">
                                    <i class="fas fa-calendar-times"></i>
                                    <h3>Nenhum jogo cadastrado</h3>
                                    <p>Comece cadastrando o primeiro jogo do interclasse.</p>
                                    <a href="editar_jogo.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i>
                                        Cadastrar Primeiro Jogo
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>