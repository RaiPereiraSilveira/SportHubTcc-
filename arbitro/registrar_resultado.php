<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/campeonato.php';

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


/* ══ Gravação da súmula ══════════════════════════════════════════════════
   O formulário já coletava gols, cartões, substituições e as estatísticas de
   posse/finalizações — e NADA disso era gravado. A tabela `eventos_jogo`
   existia desde o começo do projeto, era lida pelo placar ao vivo e nunca
   recebia uma linha sequer. Era o que fazia toda estatística individual
   (SH-67) mostrar zero.

   Duas correções, além da gravação em si:

   · Os eventos deixaram de ficar atrás de `modalidade === 'Futebol'`. Cartão
     existe no futsal, no handebol e no vôlei; a comparação por nome ainda
     quebrava se a escola cadastrasse "Futebol de campo".
   · Regravar a súmula não duplica os eventos: os antigos do jogo são
     apagados dentro da mesma transação antes de inserir os novos. Antes não
     havia esse problema porque não havia gravação nenhuma.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $erro = 'Requisição inválida (token de segurança). Recarregue a página e tente novamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $placar_time1 = max(0, (int)($_POST['placar_time1'] ?? 0));
    $placar_time2 = max(0, (int)($_POST['placar_time2'] ?? 0));
    $observacoes  = mb_substr(trim((string)($_POST['observacoes'] ?? '')), 0, 2000);
    $status       = 'finalizado';

    // Os times deste jogo: nenhum evento pode apontar para outro time.
    $times_validos = [(int)$jogo['time1_id'], (int)$jogo['time2_id']];
    $tipos_validos = ['gol', 'cartao_amarelo', 'cartao_vermelho', 'substituicao'];

    try {
        $pdo->beginTransaction();

        // O arbitro_id no WHERE garante que ninguém grave súmula de jogo alheio.
        $stmt = $pdo->prepare("
            UPDATE jogos
            SET placar_time1 = ?, placar_time2 = ?, status = ?
            WHERE id = ? AND arbitro_id = ?
        ");
        $result = $stmt->execute([$placar_time1, $placar_time2, $status, $jogo_id, $arbitro_id]);

        if (!$result) {
            throw new Exception("Erro ao atualizar o placar do jogo");
        }

        // Observações: a coluna pode não existir em instalação anterior à v2.
        try {
            $pdo->prepare("UPDATE jogos SET observacoes = ? WHERE id = ? AND arbitro_id = ?")
                ->execute([$observacoes, $jogo_id, $arbitro_id]);
        } catch (PDOException $e) {
            sh_log_excecao($e, 'gravar observações da súmula (coluna ausente?)');
        }

        /* ── Eventos da partida ─────────────────────────────────────────── */
        $pdo->prepare("DELETE FROM eventos_jogo WHERE jogo_id = ?")->execute([$jogo_id]);

        $ins_evento = $pdo->prepare(
            "INSERT INTO eventos_jogo (jogo_id, time_id, jogador, tipo, minuto, descricao)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $eventos_gravados = 0;
        foreach ((array)($_POST['eventos'] ?? []) as $ev) {
            $tipo    = (string)($ev['tipo'] ?? '');
            $time_id = (int)($ev['time_id'] ?? 0);
            $jogador = mb_substr(trim((string)($ev['jogador'] ?? '')), 0, 100);

            // Linha em branco é a que o formulário deixa quando ninguém a usou.
            if ($tipo === '' || !in_array($tipo, $tipos_validos, true)) continue;
            if (!in_array($time_id, $times_validos, true)) continue;

            $minuto = $ev['minuto'] ?? '';
            $minuto = ($minuto === '' || $minuto === null) ? null : max(0, min(200, (int)$minuto));

            $ins_evento->execute([
                $jogo_id, $time_id,
                $jogador !== '' ? $jogador : null,
                $tipo, $minuto,
                mb_substr(trim((string)($ev['descricao'] ?? '')), 0, 255) ?: null,
            ]);
            $eventos_gravados++;
        }

        /* ── Estatísticas por time ──────────────────────────────────────── */
        $ins_stat = $pdo->prepare(
            "INSERT INTO estatisticas_jogo (jogo_id, time_id, tipo, valor)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        );
        foreach ((array)($_POST['estatisticas'] ?? []) as $time_id => $stats) {
            $time_id = (int)$time_id;
            if (!in_array($time_id, $times_validos, true)) continue;

            foreach (['posse_bola', 'finalizacoes', 'escanteios', 'faltas'] as $campo) {
                $valor = $stats[$campo] ?? '';
                if ($valor === '' || $valor === null) continue;
                $ins_stat->execute([$jogo_id, $time_id, $campo, (string)max(0, (int)$valor)]);
            }
        }

        $pdo->commit();

        /* Mata-mata (SH-55): se esta partida resolve uma posição da chave, o
           vencedor sobe sozinho para a fase seguinte. Fora da transação de
           propósito — uma falha aqui não deve desfazer a súmula. */
        sh_chaveamento_ao_encerrar_jogo($pdo, $jogo_id);

        sh_auditar($pdo, 'sumula_registrada', 'jogos', $jogo_id,
                   $placar_time1 . 'x' . $placar_time2 . ' · ' . $eventos_gravados . ' evento(s)');

        $_SESSION['sucesso'] = 'Súmula registrada' . ($eventos_gravados > 0
            ? ' com ' . $eventos_gravados . ' evento(s).' : '.');
        header("Location: painel_arbitro.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = sh_erro_usuario($e, 'registrar o resultado');
    }
}

// Agora incluir o header depois de todo o processamento
include '../includes/header.php';

/* Eventos já gravados: sem isto, reabrir a súmula mostraria a lista vazia e
   gravar de novo apagaria tudo o que o árbitro tinha lançado antes. */
$eventos_existentes = [];
try {
    $stmt = $pdo->prepare("SELECT time_id, jogador, tipo, minuto, descricao
                             FROM eventos_jogo WHERE jogo_id = ? ORDER BY minuto, id");
    $stmt->execute([$jogo_id]);
    $eventos_existentes = $stmt->fetchAll();
} catch (PDOException $e) {
    sh_log_excecao($e, 'carregar eventos da súmula');
}

/* Estatísticas já gravadas, indexadas por time e tipo. */
$stats_existentes = [];
try {
    $stmt = $pdo->prepare("SELECT time_id, tipo, valor FROM estatisticas_jogo WHERE jogo_id = ?");
    $stmt->execute([$jogo_id]);
    foreach ($stmt->fetchAll() as $s) {
        $stats_existentes[(int)$s['time_id']][$s['tipo']] = $s['valor'];
    }
} catch (PDOException $e) {
    sh_log_excecao($e, 'carregar estatísticas da súmula');
}
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

            <?php /* Gols, cartões e substituições valem para qualquer modalidade:
                     a versão anterior só mostrava estes campos quando a modalidade
                     se chamava exatamente "Futebol", o que já não pegava
                     "Futebol de campo" nem o futsal. */ ?>
            <div class="form-section">
                <h3>Estatísticas do Jogo</h3>
                <div class="stats-grid">
                    <div class="team-stats">
                        <h4><?= htmlspecialchars($jogo['time1_nome']) ?></h4>
                        <input type="hidden" name="estatisticas[<?= $jogo['time1_id'] ?>][time_id]" value="<?= $jogo['time1_id'] ?>">
                        
                        <div class="stat-input">
                            <label>Posse de Bola (%)</label>
                            <input type="number" name="estatisticas[<?= (int)$jogo['time1_id'] ?>][posse_bola]"
                                   min="0" max="100" placeholder="0-100"
                                   value="<?= e($stats_existentes[(int)$jogo['time1_id']]['posse_bola'] ?? '') ?>">
                        </div>
                        <div class="stat-input">
                            <label>Finalizações</label>
                            <input type="number" name="estatisticas[<?= (int)$jogo['time1_id'] ?>][finalizacoes]"
                                   min="0" placeholder="0"
                                   value="<?= e($stats_existentes[(int)$jogo['time1_id']]['finalizacoes'] ?? '') ?>">
                        </div>
                        <div class="stat-input">
                            <label>Escanteios</label>
                            <input type="number" name="estatisticas[<?= (int)$jogo['time1_id'] ?>][escanteios]"
                                   min="0" placeholder="0"
                                   value="<?= e($stats_existentes[(int)$jogo['time1_id']]['escanteios'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="team-stats">
                        <h4><?= htmlspecialchars($jogo['time2_nome']) ?></h4>
                        <input type="hidden" name="estatisticas[<?= $jogo['time2_id'] ?>][time_id]" value="<?= $jogo['time2_id'] ?>">
                        
                        <div class="stat-input">
                            <label>Posse de Bola (%)</label>
                            <input type="number" name="estatisticas[<?= (int)$jogo['time2_id'] ?>][posse_bola]"
                                   min="0" max="100" placeholder="0-100"
                                   value="<?= e($stats_existentes[(int)$jogo['time2_id']]['posse_bola'] ?? '') ?>">
                        </div>
                        <div class="stat-input">
                            <label>Finalizações</label>
                            <input type="number" name="estatisticas[<?= (int)$jogo['time2_id'] ?>][finalizacoes]"
                                   min="0" placeholder="0"
                                   value="<?= e($stats_existentes[(int)$jogo['time2_id']]['finalizacoes'] ?? '') ?>">
                        </div>
                        <div class="stat-input">
                            <label>Escanteios</label>
                            <input type="number" name="estatisticas[<?= (int)$jogo['time2_id'] ?>][escanteios]"
                                   min="0" placeholder="0"
                                   value="<?= e($stats_existentes[(int)$jogo['time2_id']]['escanteios'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Eventos do Jogo</h3>
                <p class="form-hint">
                    Gol, cartão e substituição, com o nome do jogador. É daqui que
                    saem a artilharia e o fair play em <em>Estatísticas dos atletas</em>.
                </p>
                <div id="eventos-container">
                    <?php
                    /* Uma linha por evento já gravado, mais uma em branco no fim
                       para o próximo lançamento. */
                    $linhas_evento = $eventos_existentes;
                    $linhas_evento[] = ['tipo' => '', 'time_id' => '', 'jogador' => '',
                                        'minuto' => '', 'descricao' => ''];
                    foreach ($linhas_evento as $i => $ev):
                    ?>
                    <div class="evento-item">
                        <select name="eventos[<?= $i ?>][tipo]" class="evento-tipo"
                                aria-label="Tipo do evento <?= $i + 1 ?>">
                            <option value="">Selecione o tipo</option>
                            <?php foreach (['gol' => 'Gol',
                                            'cartao_amarelo' => 'Cartão Amarelo',
                                            'cartao_vermelho' => 'Cartão Vermelho',
                                            'substituicao' => 'Substituição'] as $valor => $rotulo): ?>
                                <option value="<?= $valor ?>"
                                        <?= ($ev['tipo'] ?? '') === $valor ? 'selected' : '' ?>>
                                    <?= $rotulo ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="eventos[<?= $i ?>][time_id]" class="evento-time"
                                aria-label="Time do evento <?= $i + 1 ?>">
                            <option value="<?= (int)$jogo['time1_id'] ?>"
                                    <?= (int)($ev['time_id'] ?? 0) === (int)$jogo['time1_id'] ? 'selected' : '' ?>>
                                <?= e($jogo['time1_nome']) ?>
                            </option>
                            <option value="<?= (int)$jogo['time2_id'] ?>"
                                    <?= (int)($ev['time_id'] ?? 0) === (int)$jogo['time2_id'] ? 'selected' : '' ?>>
                                <?= e($jogo['time2_nome']) ?>
                            </option>
                        </select>
                        <input type="text" name="eventos[<?= $i ?>][jogador]"
                               placeholder="Nome do jogador" maxlength="100"
                               aria-label="Jogador do evento <?= $i + 1 ?>"
                               value="<?= e($ev['jogador'] ?? '') ?>">
                        <input type="number" name="eventos[<?= $i ?>][minuto]"
                               placeholder="Minuto" min="0" max="200"
                               aria-label="Minuto do evento <?= $i + 1 ?>"
                               value="<?= e($ev['minuto'] ?? '') ?>">
                        <input type="text" name="eventos[<?= $i ?>][descricao]"
                               placeholder="Descrição (opcional)" maxlength="255"
                               aria-label="Descrição do evento <?= $i + 1 ?>"
                               value="<?= e($ev['descricao'] ?? '') ?>">
                        <button type="button" class="remover-evento"
                                aria-label="Remover evento <?= $i + 1 ?>">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="adicionar-evento" class="btn btn-secondary">+ Adicionar Evento</button>
            </div>

            <div class="form-section">
                <h3>Observações</h3>
                <textarea name="observacoes" placeholder="Observações sobre o jogo, incidentes, etc." 
                          rows="4"><?= htmlspecialchars($observacoes_value) ?></textarea>
            </div>

            <div class="form-actions">
                <a href="sumula_pdf.php?jogo_id=<?= (int)$jogo_id ?>" class="btn btn-secondary" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Súmula em PDF</a>
                <a href="painel_arbitro.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Registrar Resultado</button>
            </div>
        </form>
    </div>
</div>

<script<?= sh_nonce_attr() ?>>
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