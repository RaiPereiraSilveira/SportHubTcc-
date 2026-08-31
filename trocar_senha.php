<?php
/**
 * trocar_senha.php — troca de senha (SH-48)
 *
 * Duas situações levam até aqui:
 *
 *  · A conta ainda está com a senha de fábrica (`senha_provisoria = 1`).
 *    Nesse caso `sh_guardar_senha_provisoria()`, no config.php, desvia TODA
 *    tela para cá — não há como usar o sistema sem trocar. É o que impede
 *    uma instalação de ficar no ar com "admin1234".
 *
 *  · O usuário quer trocar por vontade própria, vindo do perfil. Aí a senha
 *    atual é exigida: sessão sequestrada não deve poder trocar a senha e
 *    expulsar o dono.
 *
 * A senha provisória NÃO é pedida na primeira situação — quem chegou aqui já
 * provou que a conhece ao entrar, e pedi-la de novo só faria o professor
 * copiar e colar do papel duas vezes.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_layout.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . sh_url('login.php'));
    exit();
}

$provisoria = !empty($_SESSION['senha_provisoria']);
$erro       = '';
$sucesso    = '';

$usuario = null;
try {
    $stmt = $pdo->prepare('SELECT id, username, nome, password FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $erro = sh_erro_usuario($e, 'carregar sua conta');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    exigir_csrf();

    $atual     = (string)($_POST['senha_atual'] ?? '');
    $nova      = (string)($_POST['senha_nova'] ?? '');
    $confirma  = (string)($_POST['senha_confirma'] ?? '');

    if (!$provisoria && !password_verify($atual, $usuario['password'])) {
        $erro = 'A senha atual não confere.';
        sh_auditar($pdo, 'troca_senha_negada', 'usuarios', (int)$usuario['id'], 'senha atual incorreta');
    } elseif ($nova !== $confirma) {
        $erro = 'As duas senhas digitadas não são iguais.';
    } elseif (($problema = sh_senha_politica($nova, $usuario['username'])) !== '') {
        $erro = $problema;
    } elseif (password_verify($nova, $usuario['password'])) {
        $erro = 'A senha nova precisa ser diferente da atual.';
    } elseif (!sh_definir_senha($pdo, (int)$usuario['id'], $nova)) {
        $erro = 'Não foi possível gravar a senha. Tente novamente.';
    } else {
        /* Trocar a senha invalida os links de recuperação em aberto: se
           alguém pediu um e você trocou a senha, o link não deve mais servir. */
        try {
            if (sh_tabela_existe($pdo, 'senha_tokens')) {
                $pdo->prepare('UPDATE senha_tokens SET usado_em = NOW()
                                WHERE usuario_id = ? AND usado_em IS NULL')
                    ->execute([(int)$usuario['id']]);
            }
        } catch (PDOException $e) {
            sh_log_excecao($e, 'invalidar links de recuperação após troca de senha');
        }

        // ID de sessão novo: fecha a janela de uma sessão paralela antiga.
        session_regenerate_id(true);
        $sucesso = 'Senha alterada.';
        $provisoria = false;
    }
}

$destinos = [
    'admin'   => sh_url('admin/dashboard.php'),
    'arbitro' => sh_url('arbitro/painel.php'),
    'aluno'   => sh_url('aluno/painel.php'),
];
$destino = $destinos[$_SESSION['usuario_tipo'] ?? 'aluno'] ?? sh_url('aluno/painel.php');

sh_auth_inicio(
    $provisoria ? 'Defina sua senha' : 'Trocar senha',
    $provisoria
        ? 'Esta conta ainda está com a senha de fábrica. Escolha uma senha só sua para continuar.'
        : 'Informe a senha atual e escolha a nova.',
    'fa-key'
);
?>

<?php sh_auth_alerta('error', $erro); ?>

<?php if ($sucesso !== ''): ?>
    <div class="alert alert-success" role="status"><?= e($sucesso) ?></div>
    <p class="credencial-texto">
        Sua senha foi atualizada. Guarde-a no gerenciador de senhas do navegador
        ou do celular — o sistema não tem como recuperá-la, só redefini-la.
    </p>
    <a class="btn btn-primary btn-bloco" href="<?= e($destino) ?>">Ir para o painel</a>
<?php else: ?>

    <?php if ($provisoria): ?>
        <p class="credencial-texto">
            As senhas que vêm na instalação estão publicadas na documentação do
            projeto — servem para o primeiro acesso e para nada mais. Enquanto
            esta conta usar uma delas, nenhuma tela do sistema abre.
        </p>
    <?php endif; ?>

    <form method="POST" class="form-panel">
        <?= csrf_field() ?>

        <?php if (!$provisoria): ?>
            <div class="form-group">
                <label for="senha_atual">Senha atual</label>
                <input type="password" id="senha_atual" name="senha_atual"
                       autocomplete="current-password" required>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="senha_nova">Senha nova</label>
            <input type="password" id="senha_nova" name="senha_nova"
                   autocomplete="new-password" minlength="<?= SH_SENHA_MINIMA ?>"
                   aria-describedby="regras-senha" required>
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

<?php endif; ?>

<?php
sh_auth_fim($provisoria && $sucesso === ''
    ? [sh_url('logout.php'), 'Sair sem trocar']
    : [$destino, 'Voltar ao painel']);
