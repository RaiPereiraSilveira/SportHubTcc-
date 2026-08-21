<?php
include '../includes/config.php';

// Sempre verificar login ANTES de enviar HTML
verificarLogin();

// Verifica se é admin
if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Adicionar time
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_time'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: times.php?erro=invalid'); exit();
    }
    $nome   = trim((string)($_POST['nome'] ?? ''));
    $sala   = trim((string)($_POST['sala'] ?? ''));
    $genero = trim((string)($_POST['genero'] ?? ''));

    // Validação no servidor: o "required" do HTML não vale para requisições
    // feitas fora do formulário.
    if ($nome === '' || $sala === '') {
        header('Location: times.php?erro=empty'); exit();
    }
    if (mb_strlen($nome) > 100 || mb_strlen($sala) > 20) {
        header('Location: times.php?erro=tamanho'); exit();
    }
    if (!in_array($genero, ['masculino', 'feminino', 'misto'], true)) {
        header('Location: times.php?erro=genero'); exit();
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO times (nome, sala, genero) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $sala, $genero]);
        header('Location: times.php?sucesso=1');
        exit();
    } catch (PDOException $e) {
        error_log('Erro ao inserir time: ' . $e->getMessage());
        header('Location: times.php?erro=internal'); exit();
    }
}

// Adicionar jogador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_jogador'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: times.php?erro=invalid'); exit();
    }
    $time_id       = (int)($_POST['time_id'] ?? 0);
    $nome_jogador  = trim((string)($_POST['nome_jogador'] ?? ''));
    $numero_camisa = trim((string)($_POST['numero_camisa'] ?? ''));
    $numero_camisa = $numero_camisa !== '' ? (int)$numero_camisa : null;

    if ($time_id <= 0 || $nome_jogador === '') {
        header('Location: times.php?erro=empty'); exit();
    }
    if (mb_strlen($nome_jogador) > 100) {
        header('Location: times.php?erro=tamanho'); exit();
    }
    if ($numero_camisa !== null && ($numero_camisa < 0 || $numero_camisa > 999)) {
        header('Location: times.php?erro=camisa'); exit();
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO jogadores (time_id, nome, numero_camisa) VALUES (?, ?, ?)");
        $stmt->execute([$time_id, $nome_jogador, $numero_camisa]);
        header('Location: times.php?sucesso=2');
        exit();
    } catch (PDOException $e) {
        error_log('Erro ao inserir jogador: ' . $e->getMessage());
        header('Location: times.php?erro=internal'); exit();
    }
}

// Buscar times antes da exibição
$stmt = $pdo->query("
    SELECT t.*, 
           (SELECT COUNT(*) FROM jogadores j WHERE j.time_id = t.id) AS total_jogadores
    FROM times t
    ORDER BY t.sala, t.nome
");
$times = $stmt->fetchAll();

// Somente AGORA pode incluir o HTML!
include '../includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Gerenciar Times</h2>

        <!-- MENSAGENS DE FEEDBACK -->
        <?php
        $mensagens_sucesso = [
            '1' => 'Time cadastrado com sucesso!',
            '2' => 'Jogador adicionado com sucesso!',
            '3' => 'Time excluído com sucesso!',
        ];
        $mensagens_erro = [
            '1'        => 'ID do time não informado.',
            'empty'    => 'Preencha todos os campos obrigatórios.',
            'tamanho'  => 'Nome ou sala excedem o tamanho permitido.',
            'genero'   => 'Selecione um gênero válido.',
            'camisa'   => 'Número de camisa inválido.',
            'invalid'  => 'Requisição inválida (token de segurança). Tente novamente.',
            'notfound' => 'Registro não encontrado.',
            'internal' => 'Não foi possível concluir a operação. Tente novamente.',
        ];
        $sucesso_msg = $mensagens_sucesso[$_GET['sucesso'] ?? ''] ?? null;
        $erro_msg    = $mensagens_erro[$_GET['erro'] ?? ''] ?? null;
        ?>
        <?php if ($sucesso_msg): ?>
            <div class="alert alert-success"><?= e($sucesso_msg) ?></div>
        <?php endif; ?>
        <?php if ($erro_msg): ?>
            <div class="alert alert-error"><?= e($erro_msg) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" data-tab="times">Times</div>
            <div class="tab" data-tab="adicionar-time">Adicionar Time</div>
        </div>

        <div class="tab-content active" id="times">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Sala</th>
                        <th>Gênero</th>
                        <th>Jogadores</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($times as $time): ?>
                        <tr>
                            <td><?= htmlspecialchars($time['nome']) ?></td>
                            <td><?= htmlspecialchars($time['sala']) ?></td>
                            <td><?= ucfirst($time['genero']) ?></td>
                            <td><?= $time['total_jogadores'] ?> jogadores</td>
                            <td>
                                <a href="ver_jogadores.php?time_id=<?= $time['id'] ?>" class="btn-small">Ver Jogadores</a>
                                <a href="editar_time.php?time_id=<?= $time['id'] ?>" class="btn-small btn-accent">Editar</a>
                                <a href="excluir_time.php?time_id=<?= $time['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" class="btn-small btn-danger"
                                    onclick="return confirm('Tem certeza que deseja excluir este time? Todos os jogadores também serão removidos.')">
                                    Excluir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="adicionar-time">
            <form method="POST" class="form-panel">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="form-group">
                    <label for="nome">Nome do Time</label>
                    <input type="text" id="nome" name="nome" required>
                </div>

                <div class="form-group">
                    <label for="sala">Sala/Turma</label>
                    <input type="text" id="sala" name="sala" required>
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

                <button type="submit" name="adicionar_time" class="btn-secondary">Cadastrar Time</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
