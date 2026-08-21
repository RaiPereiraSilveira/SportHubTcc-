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
        
        <!-- Mensagem de sucesso -->
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success">
                <?= e($_SESSION['sucesso']) ?>
                <?php unset($_SESSION['sucesso']); ?>
            </div>
        <?php endif; ?>
        
        <p>Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>! Aqui estão os jogos que você foi designado para apitar.</p>
        
        <div class="games-grid">
            <?php if (count($jogos) > 0): ?>
                <?php foreach ($jogos as $jogo): ?>
                <div class="game-card">
                    <div class="game-header">
                        <span class="game-sport"><?= htmlspecialchars($jogo['modalidade']) ?></span>
                        <span class="game-phase"><?= htmlspecialchars($jogo['fase']) ?></span>
                        <span class="game-status <?= $jogo['status'] ?>"><?= $jogo['status'] ?></span>
                    </div>
                    
                    <div class="teams-container">
                        <div class="team">
                            <div class="team-name"><?= htmlspecialchars($jogo['time1_nome']) ?></div>
                            <div class="team-score"><?= $jogo['placar_time1'] ?? '-' ?></div>
                        </div>
                        <div class="score-divider">
                            <span class="vs">VS</span>
                            <?php if ($jogo['status'] === 'finalizado'): ?>
                                <div class="final-score">
                                    <?= $jogo['placar_time1'] ?? '0' ?> - <?= $jogo['placar_time2'] ?? '0' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team">
                            <div class="team-name"><?= htmlspecialchars($jogo['time2_nome']) ?></div>
                            <div class="team-score"><?= $jogo['placar_time2'] ?? '-' ?></div>
                        </div>
                    </div>
                    
                    <div class="game-details">
                        <div class="detail-item">
                            <span class="detail-label">Data:</span>
                            <span><?= date('d/m/Y', strtotime($jogo['data_jogo'])) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Hora:</span>
                            <span><?= htmlspecialchars($jogo['hora']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Local:</span>
                            <span><?= htmlspecialchars($jogo['local']) ?></span>
                        </div>
                    </div>
                    
                    <div class="game-actions">
                        <?php if ($jogo['status'] !== 'finalizado'): ?>
                            <a href="registrar_resultado.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-accent">
                                <i class="fas fa-edit"></i> Registrar Resultado
                            </a>
                        <?php else: ?>
                            <a href="registrar_resultado.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> Ver Detalhes
                            </a>
                            <span class="result-badge">Resultado Registrado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-games">
                    <div class="no-games-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3>Nenhum jogo designado</h3>
                    <p>Você ainda não foi designado para apitar nenhum jogo.</p>
                    <a href="../index.php" class="btn btn-primary">Voltar ao Início</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>