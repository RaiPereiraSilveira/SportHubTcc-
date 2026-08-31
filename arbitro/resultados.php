<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('arbitro', '../login.php');
$arbitro_id = $_SESSION['usuario_id'];
$sucesso = '';
$erro = '';

// Buscar jogos do árbitro
$stmt = $pdo->prepare("
    SELECT j.*, m.nome as modalidade, t1.nome as time1_nome, t2.nome as time2_nome 
    FROM jogos j 
    JOIN modalidades m ON j.modalidade_id = m.id 
    JOIN times t1 ON j.time1_id = t1.id 
    JOIN times t2 ON j.time2_id = t2.id 
    WHERE j.arbitro_id = ? 
    ORDER BY j.data_jogo DESC, j.hora DESC
");
$stmt->execute([$arbitro_id]);
$jogos = $stmt->fetchAll();

// Processar registro de resultado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_resultado'])
    && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $erro = 'Requisição inválida (token de segurança). Recarregue a página e tente novamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_resultado'])) {
    $jogo_id = (int)($_POST['jogo_id'] ?? 0);
    $placar_time1 = $_POST['placar_time1'];
    $placar_time2 = $_POST['placar_time2'];
    $cartoes_amarelos_time1 = $_POST['cartoes_amarelos_time1'];
    $cartoes_vermelhos_time1 = $_POST['cartoes_vermelhos_time1'];
    $cartoes_amarelos_time2 = $_POST['cartoes_amarelos_time2'];
    $cartoes_vermelhos_time2 = $_POST['cartoes_vermelhos_time2'];
    $faltas_time1 = $_POST['faltas_time1'];
    $faltas_time2 = $_POST['faltas_time2'];
    $observacoes = $_POST['observacoes'];
    
    try {
        // Atualizar placar e status do jogo
        $stmt = $pdo->prepare("
            UPDATE jogos 
            SET placar_time1 = ?, placar_time2 = ?, status = 'finalizado' 
            WHERE id = ? AND arbitro_id = ?
        ");
        
        if ($stmt->execute([$placar_time1, $placar_time2, $jogo_id, $arbitro_id])) {
            $sucesso = "Resultado registrado com sucesso!";
            
            // Aqui você poderia inserir os cartões e faltas em uma tabela específica
            // Por enquanto, vamos apenas mostrar a mensagem de sucesso
        } else {
            $erro = "Erro ao registrar resultado.";
        }
    } catch(PDOException $e) {
        sh_log_excecao($e, 'registrar resultado');
        $erro = "Não foi possível registrar o resultado. Tente novamente.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="referee-panel">
        <h2 class="panel-title">Registrar Resultados</h2>
        <p>Registre os resultados dos jogos que você apitou.</p>

        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <?= e($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <?= e($erro) ?>
            </div>
        <?php endif; ?>

        <div class="games-list">
            <?php if (count($jogos) > 0): ?>
                <?php foreach ($jogos as $jogo): ?>
                <div class="game-card <?= $jogo['status'] == 'finalizado' ? 'finalizado' : '' ?>">
                    <div class="game-header">
                        <span class="game-sport"><?= e($jogo['modalidade']) ?></span>
                        <span class="game-phase"><?= e($jogo['fase']) ?></span>
                        <span class="game-status status-<?= $jogo['status'] ?>">
                            <?= ucfirst($jogo['status']) ?>
                        </span>
                    </div>
                    
                    <div class="teams-container">
                        <div class="team">
                            <div class="team-name"><?= e($jogo['time1_nome']) ?></div>
                            <div class="team-score<?= ($jogo['placar_time1'] > $jogo['placar_time2']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time1'] ?></div>
                        </div>
                        <div class="vs">X</div>
                        <div class="team">
                            <div class="team-score<?= ($jogo['placar_time2'] > $jogo['placar_time1']) ? ' winning-score' : '' ?>"><?= $jogo['placar_time2'] ?></div>
                            <div class="team-score"><?= $jogo['placar_time2'] ?></div>
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

                    <?php if ($jogo['status'] !== 'finalizado'): ?>
                    <div class="game-actions">
                        <button class="btn btn-accent open-modal" data-jogo-id="<?= $jogo['id'] ?>">
                            <i class="fas fa-edit"></i>
                            Registrar Resultado
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="result-registered">
                        <i class="fas fa-check-circle"></i>
                        Resultado já registrado
                    </div>
                    <?php endif; ?>
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

<!-- Modal para registrar resultado -->
<div class="modal" id="resultModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Registrar Resultado do Jogo</h3>
            <button class="close-modal">&times;</button>
        </div>
        <form method="POST" id="resultForm">
            <?= csrf_field() ?>
            <input type="hidden" name="registrar_resultado" value="1">
            <input type="hidden" name="jogo_id" id="modal_jogo_id">
            
            <div class="score-section">
                <h4>Placar Final</h4>
                <div class="score-inputs">
                    <div class="team-input">
                        <label id="modal_time1_name"></label>
                        <input type="number" name="placar_time1" id="placar_time1" min="0" required>
                    </div>
                    <div class="vs">X</div>
                    <div class="team-input">
                        <label id="modal_time2_name"></label>
                        <input type="number" name="placar_time2" id="placar_time2" min="0" required>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <h4>Estatísticas do Jogo</h4>
                
                <div class="stats-grid">
                    <div class="stat-group">
                        <h5 id="stats_time1_name"></h5>
                        <div class="stat-input">
                            <label>Cartões Amarelos</label>
                            <input type="number" name="cartoes_amarelos_time1" min="0" value="0">
                        </div>
                        <div class="stat-input">
                            <label>Cartões Vermelhos</label>
                            <input type="number" name="cartoes_vermelhos_time1" min="0" value="0">
                        </div>
                        <div class="stat-input">
                            <label>Faltas</label>
                            <input type="number" name="faltas_time1" min="0" value="0">
                        </div>
                    </div>
                    
                    <div class="stat-group">
                        <h5 id="stats_time2_name"></h5>
                        <div class="stat-input">
                            <label>Cartões Amarelos</label>
                            <input type="number" name="cartoes_amarelos_time2" min="0" value="0">
                        </div>
                        <div class="stat-input">
                            <label>Cartões Vermelhos</label>
                            <input type="number" name="cartoes_vermelhos_time2" min="0" value="0">
                        </div>
                        <div class="stat-input">
                            <label>Faltas</label>
                            <input type="number" name="faltas_time2" min="0" value="0">
                        </div>
                    </div>
                </div>
                
                <div class="observations">
                    <label>Observações</label>
                    <textarea name="observacoes" placeholder="Observações sobre o jogo..."></textarea>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-danger close-modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Salvar Resultado</button>
            </div>
        </form>
    </div>
</div>


<script<?= sh_nonce_attr() ?>>
// Modal functionality
const modal = document.getElementById('resultModal');
const openModalButtons = document.querySelectorAll('.open-modal');
const closeModalButtons = document.querySelectorAll('.close-modal');

openModalButtons.forEach(button => {
    button.addEventListener('click', function() {
        const jogoId = this.getAttribute('data-jogo-id');
        const gameCard = this.closest('.game-card');
        
        // Preencher dados no modal
        document.getElementById('modal_jogo_id').value = jogoId;
        
        const time1Name = gameCard.querySelector('.team:first-child .team-name').textContent;
        const time2Name = gameCard.querySelector('.team:last-child .team-name').textContent;
        
        document.getElementById('modal_time1_name').textContent = time1Name;
        document.getElementById('modal_time2_name').textContent = time2Name;
        document.getElementById('stats_time1_name').textContent = time1Name;
        document.getElementById('stats_time2_name').textContent = time2Name;
        
        modal.style.display = 'flex';
    });
});

closeModalButtons.forEach(button => {
    button.addEventListener('click', () => {
        modal.style.display = 'none';
    });
});

// Fechar modal ao clicar fora
window.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});
</script>

<?php include '../includes/footer.php'; ?>