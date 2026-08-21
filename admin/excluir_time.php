<?php
include '../includes/config.php';
verificarLogin();

// Só admin pode excluir
if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Verifica se o ID foi enviado
if (!isset($_GET['time_id']) || empty($_GET['time_id'])) {
    header('Location: times.php?erro=1');
    exit();
}

if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
    header('Location: times.php?erro=invalid'); exit();
}

$time_id = (int) $_GET['time_id'];
try {
    // Excluir jogadores do time primeiro (para evitar erro de chave estrangeira)
    $stmt = $pdo->prepare("DELETE FROM jogadores WHERE time_id = ?");
    $stmt->execute([$time_id]);

    // Depois excluir o time
    $stmt = $pdo->prepare("DELETE FROM times WHERE id = ?");
    $stmt->execute([$time_id]);

    // Redirecionar com mensagem de sucesso
    header('Location: times.php?sucesso=3');
    exit();
} catch (PDOException $e) {
    error_log('Erro ao excluir time: ' . $e->getMessage());
    header('Location: times.php?erro=internal'); exit();
}
