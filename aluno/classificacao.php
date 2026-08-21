<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('aluno', '../login.php');
// Buscar classificação simplificada
$ranking = $pdo->query("
    SELECT 
        t.id,
        t.nome,
        (SELECT COUNT(*) FROM jogos j WHERE (j.time1_id = t.id OR j.time2_id = t.id) AND j.status = 'finalizado') as jogos,
        (SELECT COUNT(*) FROM jogos j WHERE 
            (j.time1_id = t.id AND j.placar_time1 > j.placar_time2) OR 
            (j.time2_id = t.id AND j.placar_time2 > j.placar_time1)
        ) as vitorias,
        (SELECT COUNT(*) FROM jogos j WHERE 
            (j.time1_id = t.id AND j.placar_time1 < j.placar_time2) OR 
            (j.time2_id = t.id AND j.placar_time2 < j.placar_time1)
        ) as derrotas,
        (SELECT COUNT(*) FROM jogos j WHERE 
            (j.time1_id = t.id AND j.placar_time1 = j.placar_time2) OR 
            (j.time2_id = t.id AND j.placar_time2 = j.placar_time1)
        ) as empates
    FROM times t
    ORDER BY (vitorias * 3 + empates) DESC, vitorias DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="student-panel">
        <h2 class="panel-title">Classificação Geral</h2>
        <p>Confira a classificação de todos os times do interclasse.</p>

        <div class="ranking-section">
            <?php if (count($ranking) > 0): ?>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>Pos</th>
                            <th>Time</th>
                            <th>Jogos</th>
                            <th>Vitórias</th>
                            <th>Empates</th>
                            <th>Derrotas</th>
                            <th>Pontos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranking as $index => $time): ?>
                        <?php 
                            $pontos = ($time['vitorias'] * 3) + $time['empates'];
                            $medal_class = '';
                            if ($index === 0) $medal_class = 'medal-gold';
                            elseif ($index === 1) $medal_class = 'medal-silver';
                            elseif ($index === 2) $medal_class = 'medal-bronze';
                        ?>
                        <tr>
                            <td class="ranking-position <?= $medal_class ?>">
                                <?= $index + 1 ?>
                                <?php if ($medal_class): ?>
                                    <span class="medal-icon">🏆</span>
                                <?php endif; ?>
                            </td>
                            <td class="team-name"><?= e($time['nome']) ?></td>
                            <td><?= $time['jogos'] ?></td>
                            <td class="victory"><?= $time['vitorias'] ?></td>
                            <td class="draw"><?= $time['empates'] ?></td>
                            <td class="defeat"><?= $time['derrotas'] ?></td>
                            <td class="points"><strong><?= $pontos ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <h3>Nenhum dado de classificação</h3>
                    <p>Ainda não há jogos finalizados para calcular a classificação.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Estatísticas -->
        <div class="stats-section" style="margin-top: 40px;">
            <h3>Estatísticas do Campeonato</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?= count($ranking) ?></div>
                    <div class="stat-label">Times Participantes</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?= array_sum(array_column($ranking, 'jogos')) ?>
                    </div>
                    <div class="stat-label">Jogos Realizados</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?= array_sum(array_column($ranking, 'vitorias')) ?>
                    </div>
                    <div class="stat-label">Vitórias</div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>