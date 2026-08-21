<?php
/**
 * api/consentimento.php
 * Registra o consentimento de cookies do visitante (LGPD, art. 8º, §1º:
 * o controlador precisa ser capaz de comprovar que o consentimento foi obtido).
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Token inválido.']);
    exit;
}

$finalidades_aceitas = ['cookies_analiticos', 'comunicacoes'];
$finalidade = (string)($_POST['finalidade'] ?? '');

if (!in_array($finalidade, $finalidades_aceitas, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Finalidade desconhecida.']);
    exit;
}

$concedido = ($_POST['concedido'] ?? '0') === '1';

// Identificador anônimo e estável por sessão — evita guardar dado pessoal
// de quem apenas visitou o site.
if (empty($_SESSION['sh_visitante'])) {
    try {
        $_SESSION['sh_visitante'] = 'anon_' . bin2hex(random_bytes(8));
    } catch (Exception $e) {
        $_SESSION['sh_visitante'] = 'anon_' . substr(sha1(session_id()), 0, 16);
    }
}

sh_registrar_consentimento($pdo, $finalidade, $_SESSION['sh_visitante'], $concedido);

echo json_encode([
    'ok'         => true,
    'finalidade' => $finalidade,
    'concedido'  => $concedido,
    'versao'     => SH_VERSAO_POLITICA,
]);
