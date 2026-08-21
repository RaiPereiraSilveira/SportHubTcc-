<?php
include '../includes/config.php';
verificarLogin();

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: arbitros.php');
    exit();
}

$id = intval($_GET['id']);

// Buscar dados do árbitro
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND tipo = 'arbitro'");
$stmt->execute([$id]);
$arbitro = $stmt->fetch();

if (!$arbitro) {
    header('Location: arbitros.php?erro=notfound');
    exit();
}

// --- PROCESSAMENTO DO POST (ANTES DO HEADER.PHP) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $nova_senha = $_POST['password'] ?? null;
        try {
            if ($nova_senha !== null && $nova_senha !== '') {
                // Atualiza com nova senha
                $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nome = ?, username = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $username, $hash, $id]);
            } else {
                // Atualiza sem senha
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nome = ?, username = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $username, $id]);
            }
            header('Location: arbitros.php?editado=1');
            exit();
        } catch (PDOException $e) {
            error_log('Erro ao editar arbitro: ' . $e->getMessage());
            $erro = 'Erro interno. Contate o administrador.';
        }
    }
}

// Agora sim é seguro incluir o header.php
include '../includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Editar Árbitro</h2>

        <form method="POST" class="form-panel">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome"
                       value="<?= htmlspecialchars($arbitro['nome']) ?>" required>
            </div>

            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($arbitro['username']) ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Nova Senha (deixe vazio para manter a atual)</label>
                <input type="password" id="password" name="password">
            </div>

            <button type="submit" class="btn-secondary">Salvar Alterações</button>
            <a href="arbitros.php" class="btn-small btn-accent">Cancelar</a>

        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
