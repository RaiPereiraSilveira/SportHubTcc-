<?php
// admin/team_logos.php
include '../includes/config.php';

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/header.php';

$error = '';
$success = '';

/* Upload do escudo do time.
   Regras: token CSRF, limite de tamanho, tipo REAL da imagem (getimagesize,
   nunca a extensão enviada) e reencode em PNG — que descarta qualquer
   conteúdo estranho embutido no arquivo. */
const LOGO_TAM_MAX = 2097152; // 2 MB
$tipos_permitidos = [
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo'], $_POST['team_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Requisição inválida (token de segurança). Recarregue a página e tente novamente.';
    } else {
        $team_id = (int) $_POST['team_id'];

        $checa = $pdo->prepare("SELECT COUNT(*) FROM times WHERE id = ?");
        $checa->execute([$team_id]);

        if ((int)$checa->fetchColumn() === 0) {
            $error = 'Time inexistente.';
        } elseif (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Erro no upload. Verifique o arquivo e tente novamente.';
        } elseif ($_FILES['logo']['size'] > LOGO_TAM_MAX) {
            $error = 'A imagem excede o limite de 2 MB.';
        } elseif (!is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $error = 'Envio inválido.';
        } else {
            $tmp  = $_FILES['logo']['tmp_name'];
            $info = @getimagesize($tmp);

            if ($info === false || !isset($tipos_permitidos[$info[2]])) {
                $error = 'Envie uma imagem PNG, JPG, GIF ou WEBP válida.';
            } else {
                $targetDir = dirname(__DIR__) . '/img/times';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $ext          = $tipos_permitidos[$info[2]];
                $uploadedPath = $targetDir . '/' . $team_id . '.' . $ext;

                if (move_uploaded_file($tmp, $uploadedPath)) {
                    $finalPath = $uploadedPath;

                    if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
                        $im = @imagecreatefromstring((string)@file_get_contents($uploadedPath));
                        if ($im !== false) {
                            $pngPath = $targetDir . '/' . $team_id . '.png';
                            imagepng($im, $pngPath);
                            imagedestroy($im);
                            if ($pngPath !== $uploadedPath) {
                                @unlink($uploadedPath);
                            }
                            $finalPath = $pngPath;
                        }
                    }

                    @chmod($finalPath, 0644);

                    // Remove versoes antigas com outras extensoes.
                    foreach ($tipos_permitidos as $outroExt) {
                        $outro = $targetDir . '/' . $team_id . '.' . $outroExt;
                        if ($outro !== $finalPath && file_exists($outro)) {
                            @unlink($outro);
                        }
                    }

                    sh_auditar($pdo, 'escudo_atualizado', 'times', $team_id);
                    $success = 'Logo enviada com sucesso para o time ID ' . $team_id . '.';
                } else {
                    $error = 'Falha ao mover o arquivo enviado.';
                }
            }
        }
    }
}

// Buscar times
$stmt = $pdo->query("SELECT id, nome FROM times ORDER BY nome");
$teams = $stmt->fetchAll();
?>
<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Logos dos Times</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p>Envie um logo para cada time. O arquivo será salvo em <code>img/times/{id}.png</code> e usado automaticamente nas páginas.</p>

        <div class="team-logos-grid">
            <?php foreach ($teams as $team):
                $id = $team['id'];
                $exts = ['png','jpg','jpeg','gif','webp'];
                $logoUrl = '../img/times.png';
                foreach ($exts as $e) {
                    $rel = '../img/times/' . $id . '.' . $e;
                    $abs = dirname(__DIR__) . '/img/times/' . $id . '.' . $e;
                    if (file_exists($abs)) {
                        $logoUrl = $rel . '?v=' . filemtime($abs);
                        break;
                    }
                }
            ?>
            <div class="team-card">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo <?= htmlspecialchars($team['nome']) ?>" style="width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
                <div class="team-name"><?= htmlspecialchars($team['nome']) ?> (ID <?= $team['id'] ?>)</div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
                    <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                    <input type="file" name="logo" accept="image/*" required>
                    <button type="submit" name="upload_logo" class="btn btn-primary">Enviar logo</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.team-logos-grid{ display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-top:18px; }
.team-card{ background:var(--surface);border:1px solid var(--line);padding:12px;border-radius:10px;text-align:center; }
.team-name{ margin:8px 0 10px;font-weight:700;color:var(--ink) }
</style>

<?php include '../includes/footer.php'; ?>
