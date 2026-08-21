<?php
include '../includes/config.php';
verificarLogin();

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Buscar árbitros
$arbitros = $pdo->query("SELECT * FROM usuarios WHERE tipo = 'arbitro' ORDER BY nome")->fetchAll();

// Buscar jogos sem árbitro
$jogos_disponiveis = $pdo->query("
    SELECT j.*, 
        t1.nome AS time1_nome,
        t2.nome AS time2_nome,
        m.nome AS modalidade_nome
    FROM jogos j
    JOIN times t1 ON j.time1_id = t1.id
    JOIN times t2 ON j.time2_id = t2.id
    JOIN modalidades m ON j.modalidade_id = m.id
    WHERE j.arbitro_id IS NULL
    ORDER BY j.data_jogo, j.hora
")->fetchAll();

// DESIGNAR ÁRBITRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['designar'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $id_jogo = intval($_POST['id_jogo'] ?? 0);
        $id_arbitro = intval($_POST['id_arbitro'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE jogos SET arbitro_id = ? WHERE id = ?");
            $stmt->execute([$id_arbitro, $id_jogo]);
            header("Location: designar_jogos.php?ok=1");
            exit();
        } catch (PDOException $e) {
            error_log('Erro ao designar arbitro: ' . $e->getMessage());
            $erro = 'Erro interno. Contate o administrador.';
        }
    }
}

// REMOVER DESIGNACAO
if (isset($_GET['remover'])) {
    $id_jogo = intval($_GET['remover']);

    $pdo->prepare("UPDATE jogos SET arbitro_id = NULL WHERE id = ?")->execute([$id_jogo]);

    header("Location: designar_jogos.php?removido=1");
    exit();
}

// JOGOS DESIGNADOS
$jogos_designados = $pdo->query("
    SELECT j.*, 
        t1.nome AS time1_nome,
        t2.nome AS time2_nome,
        m.nome AS modalidade_nome,
        u.nome AS arbitro_nome
    FROM jogos j
    JOIN usuarios u ON j.arbitro_id = u.id
    JOIN times t1 ON j.time1_id = t1.id
    JOIN times t2 ON j.time2_id = t2.id
    JOIN modalidades m ON j.modalidade_id = m.id
    WHERE j.arbitro_id IS NOT NULL
    ORDER BY j.data_jogo, j.hora
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="container">

    <div class="admin-panel">
        <h2 class="panel-title">Designar Árbitros a Jogos</h2>

        <?php if (isset($_GET['ok'])): ?>
            <div class="alert-success">Árbitro designado com sucesso!</div>
        <?php endif; ?>

        <?php if (isset($_GET['removido'])): ?>
            <div class="alert-warning">Designação removida!</div>
        <?php endif; ?>

        <form method="POST" class="form-panel">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="form-group">
                <label>Selecione um Jogo</label>
                <select name="id_jogo" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($jogos_disponiveis as $j): ?>
                        <option value="<?= $j['id'] ?>">
                            <?= e($j['modalidade_nome']) ?> —
                            <?= e($j['time1_nome']) ?> x <?= e($j['time2_nome']) ?> —
                            <?= date('d/m', strtotime($j['data_jogo'])) ?> <?= $j['hora'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Selecione o Árbitro</label>
                <select name="id_arbitro" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($arbitros as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= e($a['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="designar" class="btn-secondary">
                Designar Árbitro ao Jogo
            </button>
        </form>

    </div>

    <div class="admin-panel" style="margin-top: 30px;">
        <h2 class="panel-title">Jogos com Árbitro Designado</h2>

        <table class="tabela-adm">
            <thead>
                <tr>
                    <th>Modalidade</th>
                    <th>Jogo</th>
                    <th>Data</th>
                    <th>Árbitro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jogos_designados as $j): ?>
                <tr>
                    <td><?= e($j['modalidade_nome']) ?></td>
                    <td><?= e($j['time1_nome']) ?> x <?= e($j['time2_nome']) ?></td>
                    <td><?= date('d/m/Y', strtotime($j['data_jogo'])) ?> às <?= $j['hora'] ?></td>
                    <td><?= e($j['arbitro_nome']) ?></td>

                    <td>
                        <a href="designar_jogos.php?remover=<?= $j['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                           class="btn-small btn-danger"
                           onclick="return confirm('Remover o árbitro deste jogo?');">
                            Remover
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

</div>

<?php include '../includes/footer.php'; ?>
