<?php
include 'includes/config.php';
verificarLogin();

$sucesso = '';
$erro = '';
$fotoPreviewUrl = '';
$hasEmailColumn = false;
$hasFotoPerfilColumn = false;

$doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$project_root = str_replace('\\', '/', realpath(__DIR__));
$web_root = str_replace($doc_root, '', $project_root);
if ($web_root === '') {
    $web_root = '';
}

try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM usuarios");
    $columns = $columnStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasEmailColumn = in_array('email', $columns, true);
    $hasFotoPerfilColumn = in_array('foto_perfil', $columns, true);

    if (!$hasEmailColumn) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN email VARCHAR(255) NULL AFTER nome");
        $hasEmailColumn = true;
        $columns[] = 'email';
    }

    if (!$hasFotoPerfilColumn) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NULL AFTER email");
        $hasFotoPerfilColumn = true;
        $columns[] = 'foto_perfil';
    }
} catch (PDOException $e) {
    /* SH-39: a tela ainda abre sem a lista de colunas, mas o silêncio aqui
       já escondeu um ALTER TABLE recusado por falta de permissão. */
    sh_log_excecao($e, 'inspecionar colunas de usuarios (perfil)');
    $columns = [];
}

// Processar atualização do perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil']) && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $erro = "Sessão expirada ou inválida. Recarregue a página e tente novamente.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $uploadDir = __DIR__ . '/uploads/profile_photos/';
    $uploadUrl = 'uploads/profile_photos/';
    $novoFotoPerfil = null;
    $novoFotoCaminho = null;

    try {
        // Upload de foto de perfil
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $imagemInfo = getimagesize($_FILES['foto_perfil']['tmp_name']);
                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if ($_FILES['foto_perfil']['size'] > 2 * 1024 * 1024) {
                    $erro = "A imagem deve ter no máximo 2 MB.";
                } elseif ($imagemInfo && array_key_exists($imagemInfo['mime'], $allowedTypes)) {
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $ext = $allowedTypes[$imagemInfo['mime']];
                    $novoNome = 'perfil_' . $_SESSION['usuario_id'] . '_' . time() . '.' . $ext;
                    $destino = $uploadDir . $novoNome;

                    if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $destino)) {
                        $novoFotoPerfil = $uploadUrl . $novoNome;
                        $novoFotoCaminho = $destino;
                    } else {
                        $erro = "Falha ao enviar a imagem de perfil.";
                    }
                } else {
                    $erro = "Formato de imagem inválido. Use JPG, PNG ou WEBP.";
                }
            } else {
                $erro = "Erro no envio da imagem de perfil.";
            }
        }

        if (empty($erro)) {
            if (!empty($nova_senha)) {
                $query = "SELECT password";
                if ($hasFotoPerfilColumn) {
                    $query .= ", foto_perfil";
                }
                $query .= " FROM usuarios WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$_SESSION['usuario_id']]);
                $usuarioSenha = $stmt->fetch();

                if (!$usuarioSenha || !password_verify($senha_atual, $usuarioSenha['password'])) {
                    $erro = "Senha atual incorreta!";
                } elseif ($nova_senha !== $confirmar_senha) {
                    $erro = "As novas senhas não coincidem!";
                } elseif (strlen($nova_senha) < 6) {
                    $erro = "A nova senha deve ter pelo menos 6 caracteres!";
                } else {
                    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                }
            } else {
                if ($hasFotoPerfilColumn) {
                    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
                    $stmt->execute([$_SESSION['usuario_id']]);
                    $usuarioSenha = $stmt->fetch();
                } else {
                    $usuarioSenha = ['foto_perfil' => null];
                }
            }

            if (empty($erro)) {
                $fotoAntiga = !empty($usuarioSenha['foto_perfil']) ? $usuarioSenha['foto_perfil'] : null;
                $updateFields = ['nome = ?'];
                $params = [$nome];
                if ($hasEmailColumn) {
                    $updateFields[] = 'email = ?';
                    $params[] = $email;
                }

                if (!empty($nova_senha)) {
                    $updateFields[] = 'password = ?';
                    $params[] = $nova_senha_hash;
                }

                if ($novoFotoPerfil !== null && $hasFotoPerfilColumn) {
                    $updateFields[] = 'foto_perfil = ?';
                    $params[] = $novoFotoPerfil;
                }

                $params[] = $_SESSION['usuario_id'];
                $sql = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $_SESSION['usuario_nome'] = $nome;
                if ($novoFotoPerfil !== null) {
                    $_SESSION['usuario_foto'] = $novoFotoPerfil;
                }

                $sucesso = "Perfil atualizado com sucesso!";
                if (!empty($nova_senha)) {
                    $sucesso = "Perfil e senha atualizados com sucesso!";
                }

                if ($novoFotoPerfil !== null && $hasFotoPerfilColumn && !empty($fotoAntiga)) {
                    $antigoCaminho = __DIR__ . '/' . ltrim($fotoAntiga, '/');
                    if (file_exists($antigoCaminho) && strpos(realpath($antigoCaminho), realpath($uploadDir)) === 0) {
                        @unlink($antigoCaminho);
                    }
                }
            }
        }

        if (!empty($erro) && $novoFotoCaminho !== null && file_exists($novoFotoCaminho)) {
            @unlink($novoFotoCaminho);
        }
    } catch(PDOException $e) {
        sh_log_excecao($e, 'atualizar perfil');
        $erro = "Não foi possível atualizar o perfil. Tente novamente.";
        if ($novoFotoCaminho !== null && file_exists($novoFotoCaminho)) {
            @unlink($novoFotoCaminho);
        }
    }
}

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
$fotoPreviewUrl = !empty($usuario['foto_perfil']) ? $web_root . '/' . ltrim($usuario['foto_perfil'], '/') : '';

include 'includes/header.php';
?>

<div class="container">
    <div class="profile-panel">
        <h2 class="panel-title">Meu Perfil</h2>
        
        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <div class="profile-content">
            <div class="profile-sidebar">
                <div class="profile-identity">
                    <div class="avatar-circle">
                        <?php if (!empty($fotoPreviewUrl)): ?>
                            <img src="<?= htmlspecialchars($fotoPreviewUrl) ?>" alt="Foto de perfil de <?= htmlspecialchars($usuario['nome']) ?>">
                        <?php else: ?>
                            <?= htmlspecialchars(mb_strtoupper(mb_substr($usuario['nome'], 0, 2))) ?>
                        <?php endif; ?>
                    </div>
                    <h3 class="profile-identity-name"><?= htmlspecialchars($usuario['nome']) ?></h3>
                    <?php
                    $roles = [
                        'admin'   => 'Administrador',
                        'arbitro' => 'Árbitro',
                        'aluno'   => 'Aluno',
                    ];
                    ?>
                    <span class="profile-identity-role"><?= htmlspecialchars($roles[$usuario['tipo']] ?? 'Usuário') ?></span>
                </div>

                <dl class="profile-meta">
                    <?php if ($hasEmailColumn && !empty($usuario['email'])): ?>
                        <div class="profile-meta-item">
                            <dt>E-mail</dt>
                            <dd><?= htmlspecialchars($usuario['email']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="profile-meta-item">
                        <dt>Usuário</dt>
                        <dd><?= htmlspecialchars($usuario['username']) ?></dd>
                    </div>
                    <div class="profile-meta-item">
                        <dt>Membro desde</dt>
                        <dd><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></dd>
                    </div>
                </dl>
            </div>
            
            <div class="profile-main">
                <form method="POST" enctype="multipart/form-data" class="profile-form">
                    <input type="hidden" name="atualizar_perfil" value="1">
                    <?= csrf_field() ?>
                    
                    <div class="form-section">
                        <h3>Informações Pessoais</h3>
                        <div class="form-group">
                            <label for="nome">Nome Completo</label>
                            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" placeholder="seu@email.com">
                        </div>

                        <div class="form-group">
                            <label for="foto_perfil">Foto de Perfil</label>
                            <div class="photo-field">
                                <span class="photo-field-thumb">
                                    <?php if (!empty($fotoPreviewUrl)): ?>
                                        <img src="<?= htmlspecialchars($fotoPreviewUrl) ?>" alt="Foto de perfil atual">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </span>
                                <div class="photo-field-input">
                                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png,image/webp">
                                    <small class="field-hint">JPG, PNG ou WEBP — até 2 MB.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Usuário</label>
                            <input type="text" id="username" value="<?= htmlspecialchars($usuario['username']) ?>" disabled>
                            <small class="u-color-muted-2">O usuário não pode ser alterado</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Alterar Senha</h3>
                        <p class="section-description">Deixe em branco se não quiser alterar a senha</p>
                        
                        <div class="form-group">
                            <label for="senha_atual">Senha Atual</label>
                            <input type="password" id="senha_atual" name="senha_atual" placeholder="Digite sua senha atual">
                        </div>
                        
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha</label>
                            <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo 6 caracteres" minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Nova Senha</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Digite a nova senha novamente">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Atualizar Perfil
                        </button>
                        <a href="<?= $_SESSION['usuario_tipo'] == 'admin' ? $web_root . '/admin/dashboard.php' : ($_SESSION['usuario_tipo'] == 'arbitro' ? $web_root . '/arbitro/painel.php' : $web_root . '/aluno/painel.php') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>