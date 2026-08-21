<?php
include '../includes/config.php';
verificarLogin();

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Cadastrar árbitro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = password_hash(trim($_POST['password'] ?? ''), PASSWORD_DEFAULT);
        $nome = trim($_POST['nome'] ?? '');
        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, tipo, nome) VALUES (?, ?, 'arbitro', ?)");
            $stmt->execute([$username, $password, $nome]);
            header('Location: arbitros.php?sucesso=1');
            exit();
        } catch (PDOException $e) {
            error_log('Erro ao cadastrar arbitro: ' . $e->getMessage());
            $erro = 'Erro interno. Contate o administrador.';
        }
    }
}

// Buscar árbitros
$stmt = $pdo->query("SELECT * FROM usuarios WHERE tipo = 'arbitro' ORDER BY nome");
$arbitros = $stmt->fetchAll();

// Buscar total de jogos por árbitro
$jogos_por_arbitro = $pdo->query("
    SELECT 
        u.id,
        u.nome AS arbitro_nome,
        COUNT(j.id) AS total_jogos
    FROM usuarios u
    LEFT JOIN jogos j ON u.id = j.arbitro_id
    WHERE u.tipo = 'arbitro'
    GROUP BY u.id
    ORDER BY u.nome
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Gerenciar Árbitros</h2>
        
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert-success">Árbitro cadastrado com sucesso!</div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="arbitros">Árbitros</div>
            <div class="tab" data-tab="cadastrar">Cadastrar Árbitro</div>
            <div class="tab" data-tab="designacoes">Designações</div>
        </div>
        
        <div class="tab-content active" id="arbitros">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($arbitros as $arbitro): ?>
                    <tr>
                        <td><?= htmlspecialchars($arbitro['nome']) ?></td>
                        <td><?= htmlspecialchars($arbitro['username']) ?></td>
                        <td><?= date('d/m/Y', strtotime($arbitro['created_at'])) ?></td>
                        <td>
                            <a href="editar_arbitro.php?id=<?= $arbitro['id'] ?>" class="btn-small btn-accent">Editar</a>
                            <a href="excluir_arbitro.php?id=<?= $arbitro['id'] ?>&csrf_token=<?= e(generate_csrf_token()) ?>" class="btn-small btn-danger"
                               onclick="return confirm('Deseja excluir este árbitro?');">
                               Excluir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="tab-content" id="cadastrar">
            <form method="POST" class="form-panel">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-secondary">Cadastrar Árbitro</button>
            </form>
        </div>
        
        <div class="tab-content" id="designacoes">
            <table>
                <thead>
                    <tr>
                        <th>Árbitro</th>
                        <th>Jogos Designados</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jogos_por_arbitro as $designacao): ?>
                    <tr>
                        <td><?= htmlspecialchars($designacao['arbitro_nome']) ?></td>
                        <td><?= $designacao['total_jogos'] ?> jogos</td>
                        <td>
                            <a href="designar_jogos.php?arbitro_id=<?= $designacao['id'] ?>" class="btn-small">
                                Designar Jogos
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
