<?php
// logout.php — encerra a sessão de forma completa.
require_once __DIR__ . '/includes/config.php';

if (isset($pdo, $_SESSION['usuario_id'])) {
    sh_auditar($pdo, 'logout', 'usuarios', (int)$_SESSION['usuario_id']);
}

$_SESSION = [];

// Remove também o cookie de sessão do navegador.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: ' . sh_url('login.php'));
exit();
