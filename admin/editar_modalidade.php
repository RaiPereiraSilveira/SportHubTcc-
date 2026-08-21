<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');
$sucesso = '';
$erro = '';

// Buscar dados da modalidade se estiver editando
$modalidade = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM modalidades WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $modalidade = $stmt->fetch();
    
    if (!$modalidade) {
        $erro = "Modalidade não encontrada!";
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome   = trim((string)($_POST['nome'] ?? ''));
    $genero = (string)($_POST['genero'] ?? '');
    $generos_validos = ['masculino', 'feminino', 'misto'];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    // Validação no servidor: o "required" do HTML é só conveniência —
    // qualquer requisição feita fora do formulário passa por cima dele.
    } elseif ($nome === '') {
        $erro = 'Informe o nome da modalidade.';
    } elseif (mb_strlen($nome) > 50) {
        $erro = 'O nome da modalidade deve ter no máximo 50 caracteres.';
    } elseif (!in_array($genero, $generos_validos, true)) {
        $erro = 'Selecione um gênero válido.';
    } else {
        try {
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("UPDATE modalidades SET nome = ?, genero = ? WHERE id = ?");
                $stmt->execute([$nome, $genero, (int)$_GET['id']]);

                sh_auditar($pdo, 'modalidade_atualizada', 'modalidades', (int)$_GET['id'], $nome);
                $_SESSION['sucesso'] = "Modalidade atualizada com sucesso!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO modalidades (nome, genero) VALUES (?, ?)");
                $stmt->execute([$nome, $genero]);

                sh_auditar($pdo, 'modalidade_criada', 'modalidades', (int)$pdo->lastInsertId(), $nome);
                $_SESSION['sucesso'] = "Modalidade cadastrada com sucesso!";
            }
            header('Location: modalidades.php');
            exit();
        } catch (PDOException $e) {
            error_log('Erro ao salvar modalidade: ' . $e->getMessage());
            $erro = 'Erro ao salvar modalidade. Contate o administrador.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">
            <?= isset($_GET['id']) ? 'Editar Modalidade' : 'Cadastrar Nova Modalidade' ?>
        </h2>
        
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &gt; 
            <a href="modalidades.php">Modalidades</a> &gt; 
            <span><?= isset($_GET['id']) ? 'Editar' : 'Nova' ?></span>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                ❌ <?= e($erro) ?>
            </div>
        <?php endif; ?>

        <div class="form-panel">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="form-section">
                    <h3>⚽ Informações da Modalidade</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome da Modalidade *</label>
                            <input type="text" id="nome" name="nome" 
                                value="<?= $modalidade ? htmlspecialchars($modalidade['nome']) : '' ?>" 
                                placeholder="Ex: Futebol, Vôlei, Basquete" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="genero">Gênero *</label>
                            <select id="genero" name="genero" required>
                                <option value="">Selecione o gênero</option>
                                <option value="masculino" <?= ($modalidade && $modalidade['genero'] == 'masculino') ? 'selected' : '' ?>>Masculino</option>
                                <option value="feminino" <?= ($modalidade && $modalidade['genero'] == 'feminino') ? 'selected' : '' ?>>Feminino</option>
                                <option value="misto" <?= ($modalidade && $modalidade['genero'] == 'misto') ? 'selected' : '' ?>>Misto</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?= isset($_GET['id']) ? 'Atualizar Modalidade' : 'Cadastrar Modalidade' ?>
                    </button>
                    <a href="modalidades.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar para Lista
                    </a>
                    
                          <?php if (isset($_GET['id'])): ?>
                          <a href="excluir_modalidade.php?id=<?= $_GET['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" class="btn btn-danger"
                              onclick="return confirm('Tem certeza que deseja excluir esta modalidade?')">
                        <i class="fas fa-trash"></i>
                        Excluir Modalidade
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (isset($_GET['id']) && $modalidade): ?>
        <div class="stats-section">
            <h3>📊 Estatísticas da Modalidade</h3>
            <div class="stats-grid">
                <?php
                // Buscar estatísticas
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_jogos,
                        SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) as jogos_finalizados,
                        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as jogos_andamento
                    FROM jogos 
                    WHERE modalidade_id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $stats = $stmt->fetch();
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_jogos'] ?></div>
                    <div class="stat-label">Total de Jogos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['jogos_finalizados'] ?></div>
                    <div class="stat-label">Jogos Finalizados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['jogos_andamento'] ?></div>
                    <div class="stat-label">Jogos em Andamento</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>


<?php include '../includes/footer.php'; ?>