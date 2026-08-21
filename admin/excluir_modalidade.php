<?php
// admin/excluir_modalidade.php — exclusão de modalidade esportiva.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');

if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
    header('Location: modalidades.php?erro=invalid');
    exit();
}

if (empty($_GET['id'])) {
    header('Location: modalidades.php?erro=missing_id');
    exit();
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT nome FROM modalidades WHERE id = ?");
    $stmt->execute([$id]);
    $modalidade = $stmt->fetch();

    if (!$modalidade) {
        header('Location: modalidades.php?erro=notfound');
        exit();
    }

    // Não excluir modalidade que já tem jogos vinculados.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jogos WHERE modalidade_id = ?");
    $stmt->execute([$id]);
    $total_jogos = (int)$stmt->fetchColumn();

    if ($total_jogos > 0) {
        header('Location: modalidades.php?erro=usada&jogos=' . $total_jogos);
        exit();
    }

    $pdo->prepare("DELETE FROM modalidades WHERE id = ?")->execute([$id]);
    sh_auditar($pdo, 'modalidade_excluida', 'modalidades', $id, $modalidade['nome']);

    header('Location: modalidades.php?excluida=1');
    exit();
} catch (PDOException $e) {
    error_log('Erro ao excluir modalidade: ' . $e->getMessage());
    header('Location: modalidades.php?erro=internal');
    exit();
}
