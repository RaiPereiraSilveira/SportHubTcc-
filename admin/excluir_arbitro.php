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

if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
    header('Location: arbitros.php?erro=invalid');
    exit();
}

$id = intval($_GET['id']);

// Verifica se o árbitro existe
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND tipo = 'arbitro'");
$stmt->execute([$id]);
$arbitro = $stmt->fetch();

if (!$arbitro) {
    header('Location: arbitros.php?erro=notfound');
    exit();
}

// Verifica se o árbitro já está designado em algum jogo
$stmt = $pdo->prepare("SELECT COUNT(*) FROM jogos WHERE arbitro_id = ?");
$stmt->execute([$id]);
$designado = $stmt->fetchColumn();

if ($designado > 0) {
    header('Location: arbitros.php?erro=designado');
    exit();
}

// Exclui o árbitro
$stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->execute([$id]);

header('Location: arbitros.php?excluido=1');
exit();
