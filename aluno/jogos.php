<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('aluno', '../login.php');
// Buscar todos os jogos
$stmt = $pdo->query("
    SELECT j.*, m.nome as modalidade, t1.nome as time1_nome, t2.nome as time2_nome 
    FROM jogos j 
    JOIN modalidades m ON j.modalidade_id = m.id 
    JOIN times t1 ON j.time1_id = t1.id 
    JOIN times t2 ON j.time2_id = t2.id 
    ORDER BY 
        CASE j.status 
            WHEN 'em_andamento' THEN 1
            WHEN 'agendado' THEN 2
            WHEN 'finalizado' THEN 3
        END,
        j.data_jogo, j.hora
");
$jogos = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="student-panel">
        <h2 class="panel-title">Jogos ao Vivo</h2>
        <p>Acompanhe todos os jogos do interclasse em tempo real.</p>

        <!-- Filtros -->
        <div class="filters" style="margin-bottom: 20px;">
            <div class="filter-buttons">
                <button class="btn-small active" data-filter="all">Todos</button>
                <button class="btn-small" data-filter="live">Ao Vivo</button>
                <button class="btn-small" data-filter="scheduled">Agendados</button>
                <button class="btn-small" data-filter="finished">Finalizados</button>
            </div>
        </div>

        <div class="games-grid" id="gamesContainer">
            <?php if (count($jogos) > 0): ?>
                <?php foreach ($jogos as $jogo): ?>
                <div class="game-card <?= $jogo['status'] == 'em_andamento' ? 'live' : '' ?>" data-status="<?= $jogo['status'] ?>" data-jogo-id="<?= $jogo['id'] ?>" style="cursor:pointer;">
                    <div class="game-header">
                        <span class="game-sport"><?= e($jogo['modalidade']) ?></span>
                        <?php if ($jogo['status'] == 'em_andamento'): ?>
                            <span class="live-badge">AO VIVO</span>
                        <?php elseif ($jogo['status'] == 'finalizado'): ?>
                            <span class="finished-badge">FINALIZADO</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="teams-container">
                        <div class="team">
                            <div class="team-name"><?= e($jogo['time1_nome']) ?></div>
                            <div class="team-score<?= ($jogo['placar_time1'] > $jogo['placar_time2']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time1'] ?></div>
                        </div>
                        <div class="vs">VS</div>
                        <div class="team">
                            <div class="team-name"><?= e($jogo['time2_nome']) ?></div>
                            <div class="team-score<?= ($jogo['placar_time2'] > $jogo['placar_time1']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time2'] ?></div>
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
                            <span class="detail-label">Fase:</span>
                            <span><?= e($jogo['fase']) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($jogo['status'] == 'em_andamento'): ?>
                    <div class="live-update">
                        <div class="live-indicator">
                            <div class="pulse"></div>
                            Atualizando automaticamente
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-games">
                    <h3>Nenhum jogo encontrado</h3>
                    <p>Não há jogos cadastrados no sistema.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    <!-- Modal de estatísticas (oculto por padrão) -->
    <div id="statsModal" class="stats-modal" aria-hidden="true" style="display:none;">
        <div class="stats-modal-backdrop"></div>
        <div class="stats-modal-panel" role="dialog" aria-modal="true">
            <button class="stats-modal-close" aria-label="Fechar">×</button>
            <div class="stats-modal-content">
                <div class="modal-status-bar">
                    <span class="modal-status-label">Status do jogo:</span>
                    <strong id="modal-game-status">-</strong>
                </div>
                <div class="teams-compare">
                    <div class="team-card left">
                        <img src="../img/times.png" class="team-logo" alt="Time 1">
                        <div class="team-name">Time 1</div>
                        <div class="team-score">0</div>
                    </div>
                    <div class="vs-large">VS</div>
                    <div class="team-card right">
                        <img src="../img/times.png" class="team-logo" alt="Time 2">
                        <div class="team-name">Time 2</div>
                        <div class="team-score">0</div>
                    </div>
                </div>
                <div class="stats-totals">
                    <div class="stats-total-item">
                        <span>Cartões Totais</span>
                        <strong id="total-cartoes">0</strong>
                    </div>
                    <div class="stats-total-item">
                        <span>Faltas Totais</span>
                        <strong id="total-faltas">0</strong>
                    </div>
                    <div class="stats-total-item">
                        <span>Escanteios Totais</span>
                        <strong id="total-escanteios">0</strong>
                    </div>
                    <div class="stats-total-item">
                        <span>Gols Totais</span>
                        <strong id="total-gols">0</strong>
                    </div>
                </div>
                <div class="stats-grid">
                    <div class="stat-item"><strong>Cartões Amarelos</strong><div class="stat-left">0</div><div class="stat-right">0</div></div>
                    <div class="stat-item"><strong>Cartões Vermelhos</strong><div class="stat-left">0</div><div class="stat-right">0</div></div>
                    <div class="stat-item"><strong>Faltas</strong><div class="stat-left">0</div><div class="stat-right">0</div></div>
                    <div class="stat-item"><strong>Escanteios</strong><div class="stat-left">0</div><div class="stat-right">0</div></div>
                    <div class="stat-item"><strong>Substituições</strong><div class="stat-left">0</div><div class="stat-right">0</div></div>
                    <div class="stat-item"><strong>Gols</strong><div class="stat-left">0</div><div class="stat-right">0</div></div>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Modal */
    .stats-modal { position: fixed; left:0; right:0; top:0; bottom:0; z-index:5000; display:flex; align-items:center; justify-content:center; }
    .stats-modal-backdrop { position:absolute; inset:0; background:var(--overlay, rgba(0,0,0,.55)); }
    .stats-modal-panel { position:relative; width:min(860px,96%); background:var(--surface); color:var(--ink-soft); border:1px solid var(--line); border-radius:14px; padding:18px; box-shadow:0 20px 60px rgba(0,0,0,.35); z-index:2; }
    .stats-modal-close { position:absolute; right:12px; top:8px; border:none; background:transparent; color:var(--ink); font-size:24px; cursor:pointer; }
    .teams-compare { display:flex; align-items:center; gap:18px; justify-content:center; }
    .team-card { display:flex; flex-direction:column; align-items:center; gap:8px; padding:8px; }
    .team-logo { width:84px; height:84px; object-fit:cover; border-radius:10px; border:1px solid var(--line); }
    .team-name{ font-weight:700; color:var(--ink); }
    .team-score{ font-size:28px; font-weight:800; color:var(--ink); }
    .vs-large { font-weight:800; font-size:18px; color:var(--muted); }
    .stats-totals { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0 6px; justify-content:space-between; }
    .stats-total-item { flex:1 1 45%; display:flex; flex-direction:column; gap:4px; padding:10px 12px; background:var(--surface-2); border-radius:12px; text-align:center; }
    .stats-total-item span { font-size:.9rem; color:var(--muted); }
    .stats-total-item strong { font-size:1.18rem; font-weight:700; color:var(--ink); }
    .modal-status-bar { display:flex; align-items:center; gap:10px; margin-bottom:14px; padding:12px 0; border-bottom:1px solid var(--line); }
    .modal-status-label { color:var(--muted); font-size:.95rem; }
    .modal-status-bar strong { color:var(--ink); font-size:1rem; letter-spacing:.01em; }
    .stats-grid{ display:grid; grid-template-columns: 1fr 80px 80px; gap:8px; margin-top:14px; align-items:center; }
    .stat-item{ display:contents; }
    .stat-item strong{ grid-column:1/2; padding:8px 12px; color:var(--ink-soft); }
    .stat-left{ grid-column:2/3; text-align:center; }
    .stat-right{ grid-column:3/4; text-align:center; }
    </style>

    <script>
// Filtros de jogos
document.querySelectorAll('.filter-buttons .btn-small').forEach(button => {
    button.addEventListener('click', function() {
        // Remover active de todos
        document.querySelectorAll('.filter-buttons .btn-small').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Adicionar active no clicado
        this.classList.add('active');
        
        const filter = this.getAttribute('data-filter');
        const games = document.querySelectorAll('.game-card');
        
        games.forEach(game => {
            if (filter === 'all') {
                game.style.display = 'block';
            } else {
                const status = game.getAttribute('data-status');
                if (filter === 'live' && status === 'em_andamento') {
                    game.style.display = 'block';
                } else if (filter === 'scheduled' && status === 'agendado') {
                    game.style.display = 'block';
                } else if (filter === 'finished' && status === 'finalizado') {
                    game.style.display = 'block';
                } else {
                    game.style.display = 'none';
                }
            }
        });
    });
});

// Simular atualização em tempo real (apenas para demonstração)
setInterval(() => {
    const liveGames = document.querySelectorAll('.game-card[data-status="em_andamento"]');
    liveGames.forEach(game => {
        const scores = game.querySelectorAll('.team-score');
        if (scores.length === 2 && Math.random() > 0.8) {
            const randomTeam = Math.random() > 0.5 ? 0 : 1;
            const currentScore = parseInt(scores[randomTeam].textContent, 10);
            scores[randomTeam].textContent = currentScore + 1;
            scores[randomTeam].style.color = 'var(--accent)';
            setTimeout(() => {
                scores[randomTeam].style.color = '';
            }, 1000);
        }
    });
}, 5000);

const statsModal = document.getElementById('statsModal');
const modalClose = statsModal.querySelector('.stats-modal-close');
const modalBackdrop = statsModal.querySelector('.stats-modal-backdrop');

function hideStatsModal() {
    statsModal.style.display = 'none';
    statsModal.setAttribute('aria-hidden', 'true');
}

function openStatsModal(jogoId) {
    statsModal.style.display = 'flex';
    statsModal.setAttribute('aria-hidden', 'false');

    fetch('ajax_jogo_stats.php?jogo_id=' + encodeURIComponent(jogoId))
        .then(response => response.json())
        .then(json => {
            if (!json.success) {
                alert('Erro ao carregar estatísticas');
                hideStatsModal();
                return;
            }

            const teams = json.data.teams;
            if (!teams || teams.length < 2) {
                alert('Dados de jogo incompletos');
                hideStatsModal();
                return;
            }

            const left = statsModal.querySelector('.team-card.left');
            const right = statsModal.querySelector('.team-card.right');
            const gameStatus = statsModal.querySelector('#modal-game-status');
            const statKeys = ['cartao_amarelo', 'cartao_vermelho', 'faltas', 'escanteios', 'substituicoes', 'gols'];
            const leftEls = statsModal.querySelectorAll('.stat-item .stat-left');
            const rightEls = statsModal.querySelectorAll('.stat-item .stat-right');

            const statusMap = {
                'em_andamento': 'Ao Vivo',
                'agendado': 'Agendado',
                'finalizado': 'Finalizado',
            };
            gameStatus.textContent = statusMap[json.data.status] || json.data.status || 'Desconhecido';

            left.querySelector('.team-logo').src = teams[0].logo;
            left.querySelector('.team-logo').alt = teams[0].name;
            left.querySelector('.team-name').textContent = teams[0].name;
            left.querySelector('.team-score').textContent = teams[0].score;

            right.querySelector('.team-logo').src = teams[1].logo;
            right.querySelector('.team-logo').alt = teams[1].name;
            right.querySelector('.team-name').textContent = teams[1].name;
            right.querySelector('.team-score').textContent = teams[1].score;

            statKeys.forEach((key, idx) => {
                if (leftEls[idx]) leftEls[idx].textContent = teams[0].stats[key] ?? 0;
                if (rightEls[idx]) rightEls[idx].textContent = teams[1].stats[key] ?? 0;
            });

            const totalCartoes = (teams[0].stats.cartao_amarelo || 0) + (teams[1].stats.cartao_amarelo || 0) + (teams[0].stats.cartao_vermelho || 0) + (teams[1].stats.cartao_vermelho || 0);
            const totalFaltas = (teams[0].stats.faltas || 0) + (teams[1].stats.faltas || 0);
            const totalEscanteios = (teams[0].stats.escanteios || 0) + (teams[1].stats.escanteios || 0);
            const totalGols = (teams[0].stats.gols || 0) + (teams[1].stats.gols || 0);

            document.getElementById('total-cartoes').textContent = totalCartoes;
            document.getElementById('total-faltas').textContent = totalFaltas;
            document.getElementById('total-escanteios').textContent = totalEscanteios;
            document.getElementById('total-gols').textContent = totalGols;
        })
        .catch(() => {
            alert('Erro de rede ao carregar estatísticas');
            hideStatsModal();
        });
}

function attachGameClickHandlers() {
    document.querySelectorAll('.game-card').forEach(card => {
        card.addEventListener('click', () => {
            const jogoId = card.getAttribute('data-jogo-id');
            if (!jogoId) return;
            openStatsModal(jogoId);
        });
    });
}

modalClose.addEventListener('click', hideStatsModal);
modalBackdrop.addEventListener('click', hideStatsModal);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && statsModal.style.display === 'flex') {
        hideStatsModal();
    }
});

attachGameClickHandlers();
</script>

<?php include '../includes/footer.php'; ?>