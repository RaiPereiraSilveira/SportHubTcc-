<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('arbitro', '../login.php');
// Buscar jogos do árbitro
$arbitro_id = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("
    SELECT j.*, m.nome as modalidade, t1.nome as time1_nome, t2.nome as time2_nome 
    FROM jogos j 
    JOIN modalidades m ON j.modalidade_id = m.id 
    JOIN times t1 ON j.time1_id = t1.id 
    JOIN times t2 ON j.time2_id = t2.id 
    WHERE j.arbitro_id = ? 
    ORDER BY j.data_jogo, j.hora
");
$stmt->execute([$arbitro_id]);
$jogos = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="referee-panel">
        <h2 class="panel-title">Painel do Árbitro</h2>
        <p>Bem-vindo, <?= e($_SESSION['usuario_nome']) ?>! Aqui estão os jogos que você foi designado para apitar.</p>
        
        <div class="games-grid">
            <?php if (count($jogos) > 0): ?>
                <?php foreach ($jogos as $jogo): ?>
                <div class="game-card">
                    <div class="game-header">
                        <span class="game-sport"><?= e($jogo['modalidade']) ?></span>
                        <span class="game-phase"><?= e($jogo['fase']) ?></span>
                    </div>
                    
                    <div class="teams-container">
                        <div class="team">
                            <div class="team-name"><?= e($jogo['time1_nome']) ?></div>
                            <div class="score<?= ($jogo['placar_time1'] > $jogo['placar_time2']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time1'] ?? '-' ?></div>
                        </div>
                        <div class="vs">VS</div>
                        <div class="team">
                            <div class="team-name"><?= e($jogo['time2_nome']) ?></div>
                            <div class="score<?= ($jogo['placar_time2'] > $jogo['placar_time1']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time2'] ?? '-' ?></div>
                        </div>
                    </div>
                    
                    <div class="game-details">
                        <div class="detail-item">
                            <span class="detail-label">Data:</span>
                            <span><?= date('d/m/Y', strtotime($jogo['data_jogo'])) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Hora:</span>
                            <span><?= $jogo['hora'] ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Local:</span>
                            <span><?= e($jogo['local']) ?></span>
                        </div>
                    </div>
                    
                    <div class="game-actions">
                        <a href="registrar_resultado.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-accent">
                            Registrar Resultado
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-games">
                    <h3>Nenhum jogo designado</h3>
                    <p>Você ainda não foi designado para apitar nenhum jogo.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>