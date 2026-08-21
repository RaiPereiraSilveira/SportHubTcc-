<?php
include '../includes/config.php';

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['time_id'])) {
    header('Location: times.php');
    exit();
}

$time_id = (int) $_GET['time_id'];   // inteiro: o valor volta impresso no HTML

// Buscar dados do time
$stmt = $pdo->prepare("SELECT * FROM times WHERE id = ?");
$stmt->execute([$time_id]);
$time = $stmt->fetch();

if (!$time) {
    header('Location: times.php');
    exit();
}

// Buscar jogadores atuais
$stmt = $pdo->prepare("SELECT * FROM jogadores WHERE time_id = ? ORDER BY numero_camisa");
$stmt->execute([$time_id]);
$jogadores = $stmt->fetchAll();

// Buscar alunos disponíveis (usuarios.tipo = aluno)
$stmt = $pdo->query("
    SELECT u.id, u.nome 
    FROM usuarios u
    WHERE u.tipo = 'aluno'
    ORDER BY u.nome
");
$alunos = $stmt->fetchAll();

// Processar adição de aluno → jogador
$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_aluno'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $aluno_nome = $_POST['aluno_nome'] ?? '';
        $numero_camisa = $_POST['numero_camisa'] ?? '';

        try {
            // Verifica número repetido
            $stmt = $pdo->prepare("SELECT id FROM jogadores WHERE time_id = ? AND numero_camisa = ?");
            $stmt->execute([$time_id, $numero_camisa]);

            if ($stmt->fetch()) {
                $erro = "Já existe um jogador com este número nesse time!";
            } else {
                // Inserir jogador usando nome do aluno
                $stmt = $pdo->prepare("INSERT INTO jogadores (time_id, nome, numero_camisa) VALUES (?, ?, ?)");
                $stmt->execute([$time_id, $aluno_nome, $numero_camisa]);
                $sucesso = "Aluno adicionado ao time com sucesso!";

                // Atualizar lista de jogadores
                $stmt = $pdo->prepare("SELECT * FROM jogadores WHERE time_id = ? ORDER BY numero_camisa");
                $stmt->execute([$time_id]);
                $jogadores = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            error_log('Erro ao adicionar aluno como jogador: ' . $e->getMessage());
            $erro = 'Erro interno. Contate o administrador.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Jogadores - <?= htmlspecialchars($time['nome']) ?></h2>
                <p class="panel-subtitle"><?= htmlspecialchars($time['sala']) ?> - <?= ucfirst($time['genero']) ?></p>
            </div>
            <a href="times.php" class="btn btn-secondary">← Voltar</a>
        </div>

        <?php if ($sucesso): ?>
            <div class="alert alert-success">✅ <?= e($sucesso) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-error">❌ <?= e($erro) ?></div>
        <?php endif; ?>

        <div class="content-grid">

            <!-- ===================== -->
            <!--       JOGADORES       -->
            <!-- ===================== -->
            <div class="players-section">
                <h3>👥 Jogadores do Time (<?= count($jogadores) ?>)</h3>

                <?php if (count($jogadores) > 0): ?>
                    <div class="players-grid">
                        <?php foreach ($jogadores as $jogador): ?>
                        <div class="player-card">
                            <div class="player-number"><?= e($jogador['numero_camisa']) ?></div>
                            <div class="player-name"><?= htmlspecialchars($jogador['nome']) ?></div>
                            <div class="player-actions">
                                          <a href="excluir_jogador.php?id=<?= $jogador['id'] ?>&time_id=<?= $time_id ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" 
                                              class="btn-small btn-danger"
                                              onclick="return confirm('Excluir este jogador?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Nenhum jogador no time ainda.</p>
                <?php endif; ?>
            </div>


            <!-- =============================== -->
            <!--      ALUNOS DISPONÍVEIS       -->
            <!-- =============================== -->
            <div class="players-section">
                <h3>📚 Alunos Disponíveis (<?= count($alunos) ?>)</h3>

                <p class="subtitle">Selecione um aluno e escolha o número da camisa:</p>

                <?php if (count($alunos) > 0): ?>
                    <div class="students-grid">
                        <?php foreach ($alunos as $aluno): ?>
                        <div class="student-card">
                            <div class="student-name"><?= htmlspecialchars($aluno['nome']) ?></div>

                            <form method="POST" class="add-student-form">
                                <input type="hidden" name="adicionar_aluno" value="1">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="aluno_nome" value="<?= htmlspecialchars($aluno['nome']) ?>">

                                <input type="number" name="numero_camisa" placeholder="Nº" min="1" max="99"
                                       required class="shirt-input">

                                <button type="submit" class="btn-small btn-primary">
                                    Adicionar
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Nenhum aluno cadastrado.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>
