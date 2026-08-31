<?php
/**
 * recuperar_senha.php — pedido de redefinição (SH-64)
 *
 * O cartão estava no backlog esperando o envio real de e-mail (SH-42), que
 * agora existe. Com `includes/email.php` no lugar, o link tem como chegar ao
 * usuário — e, no XAMPP sem SMTP, cai em `logs/emails/`, o que mantém o fluxo
 * inteiro testável.
 *
 * Três cuidados que decidem se isto é uma funcionalidade ou um buraco:
 *
 * 1. RESPOSTA SEMPRE IGUAL. Dizer "e-mail não encontrado" transforma esta
 *    tela num verificador de contas: o atacante descobre quem tem cadastro
 *    antes de tentar a senha. A mensagem é a mesma para e-mail existente e
 *    inexistente.
 *
 * 2. O BANCO GUARDA O HASH DO TOKEN, não o token. Um vazamento do banco não
 *    entrega os links em aberto.
 *
 * 3. LIMITE POR IP. Sem isso, a tela vira um disparador de e-mail gratuito
 *    para quem quiser encher a caixa de alguém — e a conta de SMTP da escola
 *    é bloqueada por spam no mesmo dia.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/auth_layout.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . sh_url('trocar_senha.php'));
    exit();
}

const SH_RECUPERACAO_VALIDADE_MIN = 30;   // o link vale meia hora
const SH_RECUPERACAO_MAX_POR_IP   = 5;    // por hora

$erro    = '';
$enviado = false;
$email_digitado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $email_digitado = trim((string)($_POST['email'] ?? ''));

    if ($email_digitado === '' || !filter_var($email_digitado, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (!sh_tabela_existe($pdo, 'senha_tokens')) {
        $erro = 'A recuperação de senha ainda não foi habilitada nesta instalação. '
              . 'Fale com a coordenação.';
    } else {
        // Limite por IP, contado na trilha de auditoria.
        $pedidos = 0;
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM auditoria
                  WHERE acao = 'senha_recuperacao_pedida' AND ip = ?
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->execute([sh_ip()]);
            $pedidos = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            sh_log_excecao($e, 'contar pedidos de recuperação');
        }

        if ($pedidos >= SH_RECUPERACAO_MAX_POR_IP) {
            $erro = 'Muitos pedidos seguidos deste dispositivo. Tente novamente daqui a uma hora.';
        } else {
            sh_auditar($pdo, 'senha_recuperacao_pedida', null, null, mb_substr($email_digitado, 0, 60));

            try {
                $stmt = $pdo->prepare(
                    "SELECT id, nome, username, email FROM usuarios
                      WHERE email = ? AND status = 'ativo' LIMIT 1"
                );
                $stmt->execute([$email_digitado]);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    /* Um pedido novo invalida os anteriores: dois links vivos
                       ao mesmo tempo dobram a janela de ataque sem ajudar
                       ninguém. */
                    $pdo->prepare('UPDATE senha_tokens SET usado_em = NOW()
                                    WHERE usuario_id = ? AND usado_em IS NULL')
                        ->execute([(int)$usuario['id']]);

                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare(
                        'INSERT INTO senha_tokens (usuario_id, token_hash, expira_em, ip)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)'
                    )->execute([
                        (int)$usuario['id'],
                        hash('sha256', $token),
                        SH_RECUPERACAO_VALIDADE_MIN,
                        sh_ip(),
                    ]);

                    $link = sh_url_absoluta('redefinir_senha.php') . '?token=' . $token;

                    $texto = "Olá, {$usuario['nome']}.\n\n"
                           . "Alguém pediu para redefinir a senha da conta \"{$usuario['username']}\" no "
                           . SH_NOME . ".\n\n"
                           . "Para escolher uma senha nova, abra o endereço abaixo:\n\n"
                           . $link . "\n\n"
                           . "O link vale " . SH_RECUPERACAO_VALIDADE_MIN . " minutos e só pode ser usado uma vez.\n\n"
                           . "Se não foi você quem pediu, ignore esta mensagem: sua senha atual continua valendo "
                           . "e ninguém consegue trocá-la sem abrir este link.\n\n"
                           . SH_NOME . " — " . SH_TAGLINE;

                    $html = sh_email_modelo('Redefinir sua senha', [
                        'Olá, ' . e($usuario['nome']) . '.',
                        'Alguém pediu para redefinir a senha da conta <strong>' . e($usuario['username'])
                            . '</strong> no ' . e(SH_NOME) . '.',
                        '<a href="' . e($link) . '" style="display:inline-block;padding:12px 20px;'
                            . 'background:#0f7a55;color:#fff;border-radius:8px;text-decoration:none;'
                            . 'font-weight:600">Escolher uma senha nova</a>',
                        'O link vale ' . SH_RECUPERACAO_VALIDADE_MIN . ' minutos e só funciona uma vez.',
                        'Se não foi você quem pediu, ignore esta mensagem: sua senha atual continua '
                            . 'valendo e ninguém consegue trocá-la sem abrir este link.',
                    ]);

                    sh_mail($usuario['email'], 'Redefinir sua senha — ' . SH_NOME, $texto, [
                        'html'     => $html,
                        'contexto' => 'recuperacao_senha',
                    ]);
                }
            } catch (PDOException $e) {
                sh_log_excecao($e, 'registrar pedido de recuperação de senha');
                // O usuário continua vendo a mensagem neutra: um erro interno
                // aqui não deve virar pista sobre a existência da conta.
            }

            $enviado = true;
        }
    }
}

sh_auth_inicio('Esqueci minha senha',
    'Informe o e-mail cadastrado. Enviaremos um link para você escolher uma senha nova.',
    'fa-envelope-open-text');
?>

<?php sh_auth_alerta('error', $erro); ?>

<?php if ($enviado): ?>
    <div class="alert alert-success" role="status">
        Se existir uma conta ativa com esse e-mail, a mensagem com o link já foi enviada.
    </div>
    <p class="credencial-texto">
        Confira também a caixa de spam. O link vale
        <?= SH_RECUPERACAO_VALIDADE_MIN ?> minutos e só pode ser usado uma vez.
    </p>
    <?php if (!sh_email_entrega_real()): ?>
        <div class="alert alert-warning" role="status">
            <strong>Ambiente de desenvolvimento.</strong>
            Nenhum servidor de e-mail está configurado, então a mensagem —
            com o link — foi gravada em <code>logs/emails/</code> em vez de sair
            da máquina. Configure <code>SH_SMTP_HOST</code> em
            <code>includes/config.local.php</code> para entregar de verdade.
        </div>
    <?php endif; ?>
<?php else: ?>
    <form method="POST" class="form-panel">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">E-mail cadastrado</label>
            <input type="email" id="email" name="email" autocomplete="email"
                   value="<?= e($email_digitado) ?>" required>
            <small class="form-hint">
                É o e-mail que está no seu perfil. Contas sem e-mail cadastrado
                precisam falar com a coordenação.
            </small>
        </div>
        <button type="submit" class="btn btn-primary btn-bloco">Enviar link de redefinição</button>
    </form>
<?php endif; ?>

<?php sh_auth_fim([sh_url('login.php'), 'Voltar para o login']); ?>
