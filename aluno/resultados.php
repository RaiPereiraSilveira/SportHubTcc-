<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('aluno', '../login.php');
// Buscar jogos finalizados
$stmt = $pdo->query("
    SELECT j.*, m.nome as modalidade, t1.nome as time1_nome, t2.nome as time2_nome 
    FROM jogos j 
    JOIN modalidades m ON j.modalidade_id = m.id 
    JOIN times t1 ON j.time1_id = t1.id 
    JOIN times t2 ON j.time2_id = t2.id 
    WHERE j.status = 'finalizado'
    ORDER BY j.data_jogo DESC, j.hora DESC
");
$resultados = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="student-panel">
        <h2 class="panel-title">Resultados dos Jogos</h2>
        <p>Confira os resultados de todos os jogos finalizados.</p>

        <div class="results-section">
            <?php if (count($resultados) > 0): ?>
                <div class="results-grid">
                    <?php foreach ($resultados as $jogo): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <span class="sport"><?= e($jogo['modalidade']) ?></span>
                            <span class="phase"><?= e($jogo['fase']) ?></span>
                        </div>
                        
                        <div class="result-teams">
                            <div class="result-team winner">
                                <div class="team-name"><?= e($jogo['time1_nome']) ?></div>
                                <div class="team-score <?= $jogo['placar_time1'] > $jogo['placar_time2'] ? 'winning-score' : '' ?>">
                                    <?= $jogo['placar_time1'] ?>
                                </div>
                            </div>
                            
                            <div class="result-vs">X</div>
                            
                            <div class="result-team <?= $jogo['placar_time2'] > $jogo['placar_time1'] ? 'winner' : '' ?>">
                                <div class="team-name"><?= e($jogo['time2_nome']) ?></div>
                                <div class="team-score <?= $jogo['placar_time2'] > $jogo['placar_time1'] ? 'winning-score' : '' ?>">
                                    <?= $jogo['placar_time2'] ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="result-details">
                            <div class="detail">
                                <i class="fas fa-calendar"></i>
                                <?= date('d/m/Y', strtotime($jogo['data_jogo'])) ?>
                            </div>
                            <div class="detail">
                                <i class="fas fa-clock"></i>
                                <?= $jogo['hora'] ?>
                            </div>
                            <div class="detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= e($jogo['local']) ?>
                            </div>
                        </div>
                        
                        <?php if ($jogo['placar_time1'] == $jogo['placar_time2']): ?>
                        <div class="result-status draw">
                            <i class="fas fa-equals"></i>
                            Empate
                        </div>
                        <?php else: ?>
                        <div class="result-status winner">
                            <i class="fas fa-trophy"></i>
                            Vencedor: <?= $jogo['placar_time1'] > $jogo['placar_time2'] ? $jogo['time1_nome'] : $jogo['time2_nome'] ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <div class="no-results-icon">📊</div>
                    <h3>Nenhum resultado disponível</h3>
                    <p>Ainda não há jogos finalizados para mostrar os resultados.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>