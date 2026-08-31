<?php
/**
 * redefinir_senha.php — consumo do link de recuperação (SH-64)
 *
 * O token chega pela URL. Três verificações antes de aceitar:
 *
 *   · o hash do token existe na tabela;
 *   · `expira_em` ainda não passou;
 *   · `usado_em` está vazio — uso único, mesmo dentro do prazo.
 *
 * O token continua sendo validado no POST, não só na exibição do formulário.
 * Validar apenas na abertura da página deixaria a gravação aberta a quem
 * fizesse POST direto, sem token nenhum.
 *
 * Ao gravar a senha nova, a conta perde a marca de senha provisória (a
 * redefinição É a escolha de senha) e todos os demais links em aberto são
 * queimados.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_layout.php';

$token   = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$erro    = '';
$sucesso = false;
$usuario = null;

/** Localiza o pedido válido para este token, ou null. */
function sh_pedido_valido(PDO $pdo, $token) {
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    if (!sh_tabela_existe($pdo, 'senha_tokens')) return null;

    try {
        $stmt = $pdo->prepare(
            "SELECT st.id AS token_id, st.usuario_id, u.username, u.nome, u.status
               FROM senha_tokens st
               JOIN usuarios u ON u.id = st.usuario_id
              WHERE st.token_hash = ?
                AND st.usado_em IS NULL
                AND st.expira_em > NOW()
              LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);
        $linha = $stmt->fetch();
        if (!$linha || $linha['status'] !== 'ativo') return null;
        return $linha;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'validar token de recuperação');
        return null;
    }
}

$usuario = sh_pedido_valido($pdo, $token);

if ($token === '') {
    $erro = 'Link inválido: o endereço não trouxe o código de verificação.';
} elseif (!$usuario) {
    $erro = 'Este link não vale mais. Links de redefinição expiram em 30 minutos '
          . 'e só podem ser usados uma vez. Peça um novo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    exigir_csrf();

    $nova     = (string)($_POST['senha_nova'] ?? '');
    $confirma = (string)($_POST['senha_confirma'] ?? '');

    if ($nova !== $confirma) {
        $erro = 'As duas senhas digitadas não são iguais.';
    } elseif (($problema = sh_senha_politica($nova, $usuario['username'])) !== '') {
        $erro = $problema;
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE senha_tokens SET usado_em = NOW() WHERE id = ?')
                ->execute([(int)$usuario['token_id']]);
            // Os outros links em aberto do mesmo usuário morrem junto.
            $pdo->prepare('UPDATE senha_tokens SET usado_em = NOW()
                            WHERE usuario_id = ? AND usado_em IS NULL')
                ->execute([(int)$usuario['usuario_id']]);
            $pdo->prepare(
                'UPDATE usuarios
                    SET password = ?, senha_provisoria = 0, senha_alterada_em = NOW()
                  WHERE id = ?'
            )->execute([password_hash($nova, PASSWORD_DEFAULT), (int)$usuario['usuario_id']]);

            $pdo->commit();

            sh_auditar($pdo, 'senha_redefinida', 'usuarios', (int)$usuario['usuario_id']);
            $sucesso = true;
            $erro    = '';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = sh_erro_usuario($e, 'gravar a senha nova');
        }
    }
}

sh_auth_inicio('Escolher uma senha nova',
    $sucesso ? '' : 'O link é válido. Defina a senha que você vai usar a partir de agora.',
    'fa-key');
?>

<?php sh_auth_alerta('error', $erro); ?>

<?php if ($sucesso): ?>
    <div class="alert alert-success" role="status">Senha redefinida.</div>
    <p class="credencial-texto">
        Já pode entrar com a senha nova. O link que você usou não serve mais.
    </p>
    <a class="btn btn-primary btn-bloco" href="<?= e(sh_url('login.php')) ?>">Ir para o login</a>

<?php elseif ($usuario): ?>
    <p class="credencial-texto">
        Redefinindo a senha de <strong><?= e($usuario['username']) ?></strong>.
    </p>

    <form method="POST" class="form-panel">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="form-group">
            <label for="senha_nova">Senha nova</label>
            <input type="password" id="senha_nova" name="senha_nova"
                   autocomplete="new-password" minlength="<?= SH_SENHA_MINIMA ?>"
                   aria-describedby="regras-senha" required autofocus>
        </div>

        <div class="form-group">
            <label for="senha_confirma">Repita a senha nova</label>
            <input type="password" id="senha_confirma" name="senha_confirma"
                   autocomplete="new-password" minlength="<?= SH_SENHA_MINIMA ?>" required>
        </div>

        <div id="regras-senha">
            <?php sh_auth_regras_senha(); ?>
        </div>

        <button type="submit" class="btn btn-primary btn-bloco">Gravar senha</button>
    </form>

<?php else: ?>
    <a class="btn btn-primary btn-bloco" href="<?= e(sh_url('recuperar_senha.php')) ?>">
        Pedir um link novo
    </a>
<?php endif; ?>

<?php sh_auth_fim([sh_url('login.php'), 'Voltar para o login']); ?>
