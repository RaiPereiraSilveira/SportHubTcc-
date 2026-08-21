<?php
/**
 * admin/documento_arbitro.php
 * Entrega o documento comprobatório do credenciamento apenas para administradores
 * autenticados. A pasta de upload é bloqueada no servidor web (uploads/.htaccess),
 * então este é o único caminho de acesso ao arquivo.
 */
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 || !sh_tabela_existe($pdo, 'arbitro_solicitacoes')) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

$stmt = $pdo->prepare("SELECT protocolo, nome, documento_arquivo FROM arbitro_solicitacoes WHERE id = ?");
$stmt->execute([$id]);
$sol = $stmt->fetch();

if (!$sol || empty($sol['documento_arquivo'])) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

// Impede que um caminho manipulado no banco escape da pasta de uploads.
$base    = realpath(dirname(__DIR__) . '/uploads/credenciamento');
$caminho = realpath(dirname(__DIR__) . '/' . $sol['documento_arquivo']);

if ($base === false || $caminho === false || strpos($caminho, $base) !== 0 || !is_file($caminho)) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($caminho) ?: 'application/octet-stream';
if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Tipo de arquivo não suportado.');
}

$extensao = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime];
$nome_download = 'credenciamento_' . preg_replace('/[^A-Za-z0-9_-]/', '', $sol['protocolo']) . '.' . $extensao;

sh_auditar($pdo, 'documento_credenciamento_acessado', 'arbitro_solicitacoes', $id, $sol['protocolo']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: inline; filename="' . $nome_download . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($caminho);
