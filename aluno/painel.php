<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('aluno', '../login.php');
// Buscar jogos
$stmt = $pdo->query("
    SELECT j.*, m.nome as modalidade, t1.nome as time1_nome, t2.nome as time2_nome 
    FROM jogos j 
    JOIN modalidades m ON j.modalidade_id = m.id 
    JOIN times t1 ON j.time1_id = t1.id 
    JOIN times t2 ON j.time2_id = t2.id 
    ORDER BY j.data_jogo, j.hora 
    LIMIT 10
");
$jogos = $stmt->fetchAll();

// Buscar classificação - QUERY CORRIGIDA SEM ALIAS NO ORDER BY
$ranking = $pdo->query("
    SELECT 
        t.nome,
        COUNT(j.id) as jogos,
        SUM(CASE 
            WHEN j.time1_id = t.id AND j.placar_time1 > j.placar_time2 THEN 1
            WHEN j.time2_id = t.id AND j.placar_time2 > j.placar_time1 THEN 1
            ELSE 0 
        END) as vitorias,
        SUM(CASE 
            WHEN j.time1_id = t.id THEN j.placar_time1
            WHEN j.time2_id = t.id THEN j.placar_time2
            ELSE 0 
        END) as gols_feitos,
        SUM(CASE 
            WHEN j.time1_id = t.id THEN j.placar_time2
            WHEN j.time2_id = t.id THEN j.placar_time1
            ELSE 0 
        END) as gols_sofridos,
        (SUM(CASE 
            WHEN j.time1_id = t.id THEN j.placar_time1
            WHEN j.time2_id = t.id THEN j.placar_time2
            ELSE 0 
        END) - SUM(CASE 
            WHEN j.time1_id = t.id THEN j.placar_time2
            WHEN j.time2_id = t.id THEN j.placar_time1
            ELSE 0 
        END)) as saldo_gols
    FROM times t
    LEFT JOIN jogos j ON (t.id = j.time1_id OR t.id = j.time2_id) AND j.status = 'finalizado'
    GROUP BY t.id
    ORDER BY vitorias DESC, saldo_gols DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="student-panel">
        <h2 class="panel-title">Painel do Aluno</h2>
        <p>Bem-vindo, <?= e($_SESSION['usuario_nome']) ?>! Acompanhe os jogos e resultados.</p>
        
        <div class="tabs">
            <div class="tab active" data-tab="jogos">Próximos Jogos</div>
            <div class="tab" data-tab="classificacao">Classificação</div>
        </div>
        
        <div class="tab-content active" id="jogos">
            <div class="games-grid">
                <?php if (count($jogos) > 0): ?>
                    <?php foreach ($jogos as $jogo): ?>
                    <div class="game-card <?= $jogo['status'] == 'em_andamento' ? 'live' : '' ?>">
                        <div class="game-header">
                            <span class="game-sport"><?= e($jogo['modalidade']) ?></span>
                            <?php if ($jogo['status'] == 'em_andamento'): ?>
                                <span class="live-badge">AO VIVO</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="teams-container">
                            <div class="team">
                                <div class="team-name"><?= e($jogo['time1_nome']) ?></div>
                                <div class="score<?= ($jogo['placar_time1'] > $jogo['placar_time2']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time1'] ?></div>
                            </div>
                            <div class="vs">VS</div>
                            <div class="team">
                                <div class="team-name"><?= e($jogo['time2_nome']) ?></div>
                                <div class="score<?= ($jogo['placar_time2'] > $jogo['placar_time1']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time2'] ?></div>
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
                            <div class="detail-item">
                                <span class="detail-label">Status:</span>
                                <span class="status-<?= $jogo['status'] ?>">
                                    <?= ucfirst($jogo['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-games">
                        <h3>Nenhum jogo agendado</h3>
                        <p>Não há jogos agendados no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tab-content" id="classificacao">
            <?php if (count($ranking) > 0): ?>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>Pos</th>
                            <th>Time</th>
                            <th>Jogos</th>
                            <th>Vitórias</th>
                            <th>Gols Pró</th>
                            <th>Gols Contra</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranking as $index => $time): ?>
                        <tr>
                            <td class="ranking-position"><?= $index + 1 ?></td>
                            <td><?= e($time['nome']) ?></td>
                            <td><?= $time['jogos'] ?></td>
                            <td><?= $time['vitorias'] ?></td>
                            <td><?= $time['gols_feitos'] ?></td>
                            <td><?= $time['gols_sofridos'] ?></td>
                            <td><?= $time['saldo_gols'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-games">
                    <h3>Nenhum dado de classificação</h3>
                    <p>Ainda não há jogos finalizados para calcular a classificação.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>