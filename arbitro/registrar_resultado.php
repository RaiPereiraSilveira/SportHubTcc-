<?php
include '../includes/config.php';

// Verificar se é árbitro ANTES de incluir o header
if ($_SESSION['usuario_tipo'] !== 'arbitro') {
    header('Location: ../login.php');
    exit();
}

$arbitro_id = $_SESSION['usuario_id'];
$jogo_id = (int)($_GET['jogo_id'] ?? 0);   // inteiro: o valor volta impresso no HTML

// Buscar informações do jogo
$stmt = $pdo->prepare("
    SELECT j.*, m.nome as modalidade, t1.nome as time1_nome, t2.nome as time2_nome,
           t1.id as time1_id, t2.id as time2_id
    FROM jogos j 
    JOIN modalidades m ON j.modalidade_id = m.id 
    JOIN times t1 ON j.time1_id = t1.id 
    JOIN times t2 ON j.time2_id = t2.id 
    WHERE j.id = ? AND j.arbitro_id = ?
");
$stmt->execute([$jogo_id, $arbitro_id]);
$jogo = $stmt->fetch();

if (!$jogo) {
    // Incluir header apenas se for mostrar erro
    include '../includes/header.php';
    echo "<div class='container'><div class='alert alert-error'>Jogo não encontrado ou você não tem permissão para acessá-lo.</div></div>";
    include '../includes/footer.php';
    exit();
}

// Processar o formulário ANTES de qualquer output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $erro = 'Requisição inválida (token de segurança). Recarregue a página e tente novamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $placar_time1 = intval($_POST['placar_time1']);
    $placar_time2 = intval($_POST['placar_time2']);
    $observacoes = $_POST['observacoes'] ?? '';
    $status = 'finalizado';
    
    try {
        $pdo->beginTransaction();
        
        // Atualizar o placar do jogo
        // O arbitro_id no WHERE garante que ninguem grave sumula de jogo alheio.
        $stmt = $pdo->prepare("
            UPDATE jogos 
            SET placar_time1 = ?, placar_time2 = ?, status = ?
            WHERE id = ? AND arbitro_id = ?
        ");
        $result = $stmt->execute([$placar_time1, $placar_time2, $status, $jogo_id, $arbitro_id]);
        
        if (!$result) {
            throw new Exception("Erro ao atualizar o placar do jogo");
        }
        
        // Tentar atualizar observações se a coluna existir
        try {
            $stmt = $pdo->prepare("
                UPDATE jogos 
                SET observacoes = ?
                WHERE id = ? AND arbitro_id = ?
            ");
            $stmt->execute([$observacoes, $jogo_id, $arbitro_id]);
        } catch (Exception $e) {
            // Coluna observacoes não existe, ignorar
            error_log("Coluna observacoes não existe: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        $_SESSION['sucesso'] = "Resultado registrado com sucesso!";
        header("Location: painel_arbitro.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erro ao registrar resultado: ' . $e->getMessage());
        $erro = "Não foi possível registrar o resultado. Tente novamente.";
    }
}

// Agora incluir o header depois de todo o processamento
include '../includes/header.php';

// Definir valor padrão para observações
$observacoes_value = $jogo['observacoes'] ?? '';
?>

<div class="container">
    <div class="referee-panel">
        <h2 class="panel-title">Registrar Resultado</h2>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?= e($erro) ?></div>
        <?php endif; ?>
        
        <div class="game-info-card">
            <div class="game-header">
                <span class="game-sport"><?= htmlspecialchars($jogo['modalidade']) ?></span>
                <span class="game-phase"><?= htmlspecialchars($jogo['fase']) ?></span>
            </div>
            
            <div class="teams-container">
                <div class="team">
                    <div class="team-name"><?= htmlspecialchars($jogo['time1_nome']) ?></div>
                </div>
                <div class="vs">VS</div>
                <div class="team">
                    <div class="team-name"><?= htmlspecialchars($jogo['time2_nome']) ?></div>
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
        </div>

        <form method="POST" class="result-form">
            <?= csrf_field() ?>
            <input type="hidden" name="jogo_id" value="<?= $jogo_id ?>">
            
            <div class="form-section">
                <h3>Placar Final</h3>
                <div class="score-inputs">
                    <div class="score-input">
                        <label for="placar_time1"><?= htmlspecialchars($jogo['time1_nome']) ?></label>
                        <input type="number" id="placar_time1" name="placar_time1" 
                               value="<?= $jogo['placar_time1'] ?? 0 ?>" min="0" required>
                    </div>
                    <div class="score-separator">X</div>
                    <div class="score-input">
                        <label for="placar_time2"><?= htmlspecialchars($jogo['time2_nome']) ?></label>
                        <input type="number" id="placar_time2" name="placar_time2" 
                               value="<?= $jogo['placar_time2'] ?? 0 ?>" min="0" required>
                    </div>
                </div>
            </div>

            <?php if ($jogo['modalidade'] === 'Futebol'): ?>
            <div class="form-section">
                <h3>Estatísticas do Jogo</h3>
                <div class="stats-grid">
                    <div class="team-stats">
                        <h4><?= htmlspecialchars($jogo['time1_nome']) ?></h4>
                        <input type="hidden" name="estatisticas[<?= $jogo['time1_id'] ?>][time_id]" value="<?= $jogo['time1_id'] ?>">
                        
                        <div class="stat-input">
                            <label>Posse de Bola (%)</label>
                            <input type="number" name="estatisticas[<?= $jogo['time1_id'] ?>][posse_bola]" 
                                   min="0" max="100" placeholder="0-100">
                        </div>
                        <div class="stat-input">
                            <label>Finalizações</label>
                            <input type="number" name="estatisticas[<?= $jogo['time1_id'] ?>][finalizacoes]" 
                                   min="0" placeholder="0">
                        </div>
                        <div class="stat-input">
                            <label>Escanteios</label>
                            <input type="number" name="estatisticas[<?= $jogo['time1_id'] ?>][escanteios]" 
                                   min="0" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="team-stats">
                        <h4><?= htmlspecialchars($jogo['time2_nome']) ?></h4>
                        <input type="hidden" name="estatisticas[<?= $jogo['time2_id'] ?>][time_id]" value="<?= $jogo['time2_id'] ?>">
                        
                        <div class="stat-input">
                            <label>Posse de Bola (%)</label>
                            <input type="number" name="estatisticas[<?= $jogo['time2_id'] ?>][posse_bola]" 
                                   min="0" max="100" placeholder="0-100">
                        </div>
                        <div class="stat-input">
                            <label>Finalizações</label>
                            <input type="number" name="estatisticas[<?= $jogo['time2_id'] ?>][finalizacoes]" 
                                   min="0" placeholder="0">
                        </div>
                        <div class="stat-input">
                            <label>Escanteios</label>
                            <input type="number" name="estatisticas[<?= $jogo['time2_id'] ?>][escanteios]" 
                                   min="0" placeholder="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Eventos do Jogo</h3>
                <div id="eventos-container">
                    <div class="evento-item">
                        <select name="eventos[0][tipo]" class="evento-tipo">
                            <option value="">Selecione o tipo</option>
                            <option value="gol">Gol</option>
                            <option value="cartao_amarelo">Cartão Amarelo</option>
                            <option value="cartao_vermelho">Cartão Vermelho</option>
                            <option value="substituicao">Substituição</option>
                        </select>
                        <select name="eventos[0][time_id]" class="evento-time">
                            <option value="<?= $jogo['time1_id'] ?>"><?= htmlspecialchars($jogo['time1_nome']) ?></option>
                            <option value="<?= $jogo['time2_id'] ?>"><?= htmlspecialchars($jogo['time2_nome']) ?></option>
                        </select>
                        <input type="text" name="eventos[0][jogador]" placeholder="Nome do jogador">
                        <input type="number" name="eventos[0][minuto]" placeholder="Minuto" min="1" max="120">
                        <input type="text" name="eventos[0][descricao]" placeholder="Descrição (opcional)">
                        <button type="button" class="remover-evento">×</button>
                    </div>
                </div>
                <button type="button" id="adicionar-evento" class="btn btn-secondary">+ Adicionar Evento</button>
            </div>
            <?php endif; ?>

            <div class="form-section">
                <h3>Observações</h3>
                <textarea name="observacoes" placeholder="Observações sobre o jogo, incidentes, etc." 
                          rows="4"><?= htmlspecialchars($observacoes_value) ?></textarea>
            </div>

            <div class="form-actions">
                <a href="painel_arbitro.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Registrar Resultado</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const adicionarEventoBtn = document.getElementById('adicionar-evento');
    const eventosContainer = document.getElementById('eventos-container');
    
    if (adicionarEventoBtn && eventosContainer) {
        adicionarEventoBtn.addEventListener('click', function() {
            const index = eventosContainer.children.length;
            
            const novoEvento = document.createElement('div');
            novoEvento.className = 'evento-item';
            novoEvento.innerHTML = `
                <select name="eventos[${index}][tipo]" class="evento-tipo">
                    <option value="">Selecione o tipo</option>
                    <option value="gol">Gol</option>
                    <option value="cartao_amarelo">Cartão Amarelo</option>
                    <option value="cartao_vermelho">Cartão Vermelho</option>
                    <option value="substituicao">Substituição</option>
                </select>
                <select name="eventos[${index}][time_id]" class="evento-time">
                    <option value="<?= $jogo['time1_id'] ?>"><?= htmlspecialchars($jogo['time1_nome']) ?></option>
                    <option value="<?= $jogo['time2_id'] ?>"><?= htmlspecialchars($jogo['time2_nome']) ?></option>
                </select>
                <input type="text" name="eventos[${index}][jogador]" placeholder="Nome do jogador">
                <input type="number" name="eventos[${index}][minuto]" placeholder="Minuto" min="1" max="120">
                <input type="text" name="eventos[${index}][descricao]" placeholder="Descrição (opcional)">
                <button type="button" class="remover-evento">×</button>
            `;
            
            eventosContainer.appendChild(novoEvento);
            
            // Adicionar evento de remover
            novoEvento.querySelector('.remover-evento').addEventListener('click', function() {
                eventosContainer.removeChild(novoEvento);
            });
        });
        
        // Adicionar eventos de remover aos eventos existentes
        document.querySelectorAll('.remover-evento').forEach(btn => {
            btn.addEventListener('click', function() {
                eventosContainer.removeChild(this.parentElement);
            });
        });
    }
});
</script>


<?php include '../includes/footer.php'; ?>