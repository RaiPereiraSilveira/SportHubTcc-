<?php
/**
 * verificar_2fa.php — segunda etapa do login (SH-65)
 *
 * A senha correta deixa o usuário AQUI, não no painel. Enquanto o código não
 * confere, `$_SESSION['usuario_id']` não existe — então fechar esta tela e
 * digitar o endereço do dashboard não leva a lugar nenhum: o `verificarLogin()`
 * de cada página continua vendo uma sessão anônima.
 *
 * Cuidados:
 *
 * · Prazo de 5 minutos para digitar. Passou, volta ao login — uma etapa
 *   pendurada indefinidamente numa máquina compartilhada é uma porta aberta.
 * · Limite de 5 tentativas. Seis dígitos são 1 milhão de combinações, mas o
 *   código vale 90 segundos: sem limite, um script tem chance real.
 * · O código de recuperação entra pelo mesmo campo. Quem perdeu o celular
 *   digita um dos oito códigos guardados; ele é queimado no uso.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/auth_layout.php';

const SH_2FA_MAX_TENTATIVAS = 5;

// Já autenticado: não há segunda etapa pendente.
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . sh_url('perfil.php'));
    exit();
}

$pendente = (int)($_SESSION['2fa_usuario_id'] ?? 0);
$expira   = (int)($_SESSION['2fa_expira_em'] ?? 0);

if ($pendente <= 0 || $expira < time()) {
    unset($_SESSION['2fa_usuario_id'], $_SESSION['2fa_expira_em'], $_SESSION['2fa_tentativas']);
    $_SESSION['aviso_sessao'] = 'A verificação em duas etapas expirou. Entre novamente.';
    header('Location: ' . sh_url('login.php'));
    exit();
}

$erro = '';

$usuario = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$pendente]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $erro = sh_erro_usuario($e, 'carregar sua conta');
}

if (!$usuario || empty($usuario['totp_segredo'])) {
    unset($_SESSION['2fa_usuario_id'], $_SESSION['2fa_expira_em']);
    header('Location: ' . sh_url('login.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro === '') {
    exigir_csrf();

    $tentativas = (int)($_SESSION['2fa_tentativas'] ?? 0);
    $codigo     = trim((string)($_POST['codigo'] ?? ''));

    if ($tentativas >= SH_2FA_MAX_TENTATIVAS) {
        sh_auditar($pdo, '2fa_bloqueado', 'usuarios', $pendente);
        unset($_SESSION['2fa_usuario_id'], $_SESSION['2fa_expira_em'], $_SESSION['2fa_tentativas']);
        $_SESSION['aviso_sessao'] = 'Muitos códigos errados. Entre novamente.';
        header('Location: ' . sh_url('login.php'));
        exit();
    }

    $ok = sh_totp_valido($usuario['totp_segredo'], $codigo)
       || sh_totp_usar_recuperacao($pdo, $pendente, $codigo);

    if ($ok) {
        session_regenerate_id(true);
        unset($_SESSION['2fa_usuario_id'], $_SESSION['2fa_expira_em'], $_SESSION['2fa_tentativas']);

        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        $_SESSION['usuario_foto'] = $usuario['foto_perfil'] ?? '';

        try {
            $pdo->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?')
                ->execute([$usuario['id']]);
        } catch (PDOException $ex) {
            // não bloqueante
        }
        sh_auditar($pdo, 'login_2fa_ok', 'usuarios', (int)$usuario['id'], $usuario['tipo']);

        if (!empty($usuario['senha_provisoria'])) {
            $_SESSION['senha_provisoria'] = true;
            header('Location: ' . sh_url('trocar_senha.php'));
            exit();
        }

        $destinos = [
            'admin'   => sh_url('admin/dashboard.php'),
            'arbitro' => sh_url('arbitro/painel.php'),
            'aluno'   => sh_url('aluno/painel.php'),
        ];
        header('Location: ' . ($destinos[$usuario['tipo']] ?? sh_url('aluno/painel.php')));
        exit();
    }

    $_SESSION['2fa_tentativas'] = $tentativas + 1;
    $restantes = SH_2FA_MAX_TENTATIVAS - $_SESSION['2fa_tentativas'];
    sh_auditar($pdo, '2fa_codigo_incorreto', 'usuarios', $pendente);
    $erro = 'Código incorreto. Você ainda tem ' . max(0, $restantes) . ' tentativa(s). '
          . 'Confira se o relógio do celular está com a hora automática ligada.';
}

$restam_recuperacao = sh_totp_recuperacao_restantes($pdo, $pendente);

sh_auth_inicio('Verificação em duas etapas',
    'Abra o aplicativo autenticador e digite o código de seis dígitos da conta '
    . '<strong>' . e($usuario['username']) . '</strong>.',
    'fa-mobile-screen');
?>

<?php sh_auth_alerta('error', $erro); ?>

<form method="POST" class="form-panel">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="codigo">Código de verificação</label>
        <input type="text" id="codigo" name="codigo"
               inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9A-Za-z\-]{6,11}" maxlength="11"
               class="campo-codigo" required autofocus
               aria-describedby="ajuda-codigo">
        <small class="form-hint" id="ajuda-codigo">
            Seis dígitos que mudam a cada 30 segundos. Perdeu o celular? Digite
            aqui um dos seus códigos de recuperação
            <?php if ($restam_recuperacao > 0): ?>
                (<?= $restam_recuperacao ?> ainda válido<?= $restam_recuperacao === 1 ? '' : 's' ?>).
            <?php else: ?>
                — mas não resta nenhum: procure a coordenação.
            <?php endif; ?>
        </small>
    </div>

    <button type="submit" class="btn btn-primary btn-bloco">Confirmar</button>
</form>

<?php sh_auth_fim([sh_url('logout.php'), 'Cancelar e voltar ao login']); ?>
