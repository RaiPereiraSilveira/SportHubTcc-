<?php
include '../includes/config.php';
verificarLogin();

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Adicionar modalidade
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: modalidades.php?erro=invalid");
        exit();
    }

    $nome = trim($_POST['nome'] ?? '');
    $genero = trim($_POST['genero'] ?? '');

    if ($nome === "" || $genero === "") {
        header("Location: modalidades.php?erro=empty");
        exit();
    }

    // Evita duplicação
    $check = $pdo->prepare("SELECT COUNT(*) FROM modalidades WHERE nome = ? AND genero = ?");
    $check->execute([$nome, $genero]);

    if ($check->fetchColumn() > 0) {
        header("Location: modalidades.php?erro=duplicada");
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO modalidades (nome, genero) VALUES (?, ?)");
        $stmt->execute([$nome, $genero]);
        header('Location: modalidades.php?sucesso=1');
        exit();
    } catch (PDOException $e) {
        error_log('Erro ao inserir modalidade: ' . $e->getMessage());
        header('Location: modalidades.php?erro=internal');
        exit();
    }
}

// Buscar modalidades
$stmt = $pdo->query("
    SELECT m.*,
           (SELECT COUNT(*) FROM jogos j WHERE j.modalidade_id = m.id) AS total_jogos
    FROM modalidades m
    ORDER BY m.nome
");
$modalidades = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Gerenciar Modalidades</h2>

        <!-- MENSAGENS -->
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert-success">Modalidade cadastrada com sucesso!</div>
        <?php endif; ?>

        <?php if (isset($_GET['excluida'])): ?>
            <div class="alert-success">Modalidade excluída com sucesso!</div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>

            <?php if ($_GET['erro'] === 'empty'): ?>
                <div class="alert-warning">Preencha todos os campos.</div>
            <?php endif; ?>

            <?php if ($_GET['erro'] === 'duplicada'): ?>
                <div class="alert-warning">Esta modalidade já existe.</div>
            <?php endif; ?>

            <?php if ($_GET['erro'] === 'usada'): ?>
                <div class="alert-warning">
                    Não é possível excluir esta modalidade porque existem jogos associados a ela.
                </div>
            <?php endif; ?>

            <?php if ($_GET['erro'] === 'notfound'): ?>
                <div class="alert-danger">Modalidade não encontrada.</div>
            <?php endif; ?>

        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" data-tab="modalidades">Modalidades</div>
            <div class="tab" data-tab="adicionar">Adicionar Modalidade</div>
        </div>

        <!-- LISTAGEM -->
        <div class="tab-content active" id="modalidades">
            <table>
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th>Gênero</th>
                        <th>Jogos Atrelados</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($modalidades as $modalidade): ?>
                    <tr>
                        <td><?= htmlspecialchars($modalidade['nome']) ?></td>
                        <td><?= ucfirst($modalidade['genero']) ?></td>
                        <td><?= $modalidade['total_jogos'] ?></td>

                        <td>
                            <a href="editar_modalidade.php?id=<?= $modalidade['id'] ?>"
                               class="btn-small btn-accent">Editar</a>

                            <?php if ($modalidade['total_jogos'] == 0): ?>
                                <a href="excluir_modalidade.php?id=<?= $modalidade['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                                   class="btn-small btn-danger"
                                   onclick="return confirm('Deseja realmente excluir esta modalidade?');">
                                    Excluir
                                </a>
                            <?php else: ?>
                                <span class="btn-small btn-disabled"
                                      title="Existem jogos cadastrados nesta modalidade">
                                    Bloqueado
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!-- FORM ADICIONAR -->
        <div class="tab-content" id="adicionar">
            <form method="POST" class="form-panel">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="form-group">
                    <label for="nome">Nome da Modalidade</label>
                    <input type="text" id="nome" name="nome" required>
                </div>

                <div class="form-group">
                    <label for="genero">Gênero</label>
                    <select id="genero" name="genero" required>
                        <option value="">Selecione</option>
                        <option value="masculino">Masculino</option>
                        <option value="feminino">Feminino</option>
                        <option value="misto">Misto</option>
                    </select>
                </div>

                <button type="submit" class="btn-secondary">Cadastrar Modalidade</button>
            </form>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
