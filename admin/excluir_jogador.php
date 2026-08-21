<?php
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');

if (isset($_GET['id'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        $_SESSION['erro'] = 'Requisição inválida.';
        header('Location: times.php'); exit();
    }
    try {
        // Buscar o time_id antes de excluir para redirecionar
        $stmt = $pdo->prepare("SELECT time_id FROM jogadores WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $jogador = $stmt->fetch();
        
        if ($jogador) {
            $stmt = $pdo->prepare("DELETE FROM jogadores WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['sucesso'] = "Jogador excluído com sucesso!";
            
            // Redirecionar de volta para a página de jogadores do time
            $time_id = $_GET['time_id'] ?? $jogador['time_id'];
            header('Location: ver_jogadores.php?time_id=' . $time_id);
            exit();
        }
    } catch(PDOException $e) {
        error_log('Erro ao excluir jogador: ' . $e->getMessage());
        $_SESSION['erro'] = 'Erro ao excluir jogador. Contate o administrador.';
    }
}

header('Location: times.php');
exit();
?>