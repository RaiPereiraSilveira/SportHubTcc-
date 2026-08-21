<?php
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');

if (isset($_GET['id'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        $_SESSION['erro'] = 'Requisição inválida.';
        header('Location: jogos.php'); exit();
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM jogos WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['sucesso'] = "Jogo excluído com sucesso!";
    } catch(PDOException $e) {
        error_log('Erro ao excluir jogo: ' . $e->getMessage());
        $_SESSION['erro'] = 'Erro ao excluir jogo. Contate o administrador.';
    }
}

header('Location: jogos.php');
exit();
?>