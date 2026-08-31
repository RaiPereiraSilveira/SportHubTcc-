<?php
/**
 * seguranca.php — segundo fator e sessões da própria conta (SH-65)
 *
 * A ativação tem três passos, e a ordem importa:
 *
 *   1. o servidor sorteia um segredo e o guarda na SESSÃO, não no banco;
 *   2. o usuário lê o QR (ou digita a chave) e devolve um código válido;
 *   3. só então o segredo vai para o banco e o 2FA passa a valer.
 *
 * Gravar no banco já no passo 1 seria o erro clássico: quem fechasse a página
 * antes de configurar o aplicativo ficaria com o segundo fator ligado e sem
 * como gerar código — ou seja, trancado para fora da própria conta.
 *
 * Os oito códigos de recuperação aparecem UMA vez, na ativação. Depois só
 * existem como hash. Perder o celular sem eles significa depender de outro
 * administrador (ou de `scripts/preparar_producao.php`) para desligar o 2FA.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/qrcode.php';

verificarLogin();

$erro     = '';
$sucesso  = '';
$codigos_novos = [];

$usuario = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $erro = sh_erro_usuario($e, 'carregar sua conta');
}

$tem_2fa = $usuario ? sh_totp_ativo($usuario) : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    exigir_csrf();
    $acao = (string)($_POST['acao'] ?? '');

    /* ── Confirmar a ativação ───────────────────────────────────────────── */
    if ($acao === 'ativar') {
        $segredo = (string)($_SESSION['totp_pendente'] ?? '');
        $codigo  = trim((string)($_POST['codigo'] ?? ''));

        if ($segredo === '') {
            $erro = 'A configuração expirou. Comece de novo.';
        } elseif (!sh_totp_valido($segredo, $codigo)) {
            $erro = 'Código incorreto. Confira se o relógio do celular está no horário '
                  . 'automático e tente com o código atual do aplicativo.';
        } else {
            try {
                $pdo->prepare(
                    'UPDATE usuarios SET totp_segredo = ?, totp_ativado_em = NOW() WHERE id = ?'
                )->execute([$segredo, (int)$usuario['id']]);

                $codigos_novos = sh_totp_gerar_recuperacao($pdo, (int)$usuario['id']);
                unset($_SESSION['totp_pendente']);

                sh_auditar($pdo, '2fa_ativado', 'usuarios', (int)$usuario['id']);
                $sucesso = 'Verificação em duas etapas ativada.';
                $tem_2fa = true;
                $usuario['totp_segredo']    = $segredo;
                $usuario['totp_ativado_em'] = date('Y-m-d H:i:s');
            } catch (PDOException $e) {
                $erro = sh_erro_usuario($e, 'ativar a verificação em duas etapas');
            }
        }
    }

    /* ── Desativar ──────────────────────────────────────────────────────── */
    if ($acao === 'desativar') {
        $senha = (string)($_POST['senha'] ?? '');
        if (!password_verify($senha, $usuario['password'])) {
            $erro = 'Senha incorreta. O segundo fator não foi desligado.';
            sh_auditar($pdo, '2fa_desativacao_negada', 'usuarios', (int)$usuario['id']);
        } else {
            try {
                $pdo->prepare(
                    'UPDATE usuarios SET totp_segredo = NULL, totp_ativado_em = NULL WHERE id = ?'
                )->execute([(int)$usuario['id']]);
                if (sh_tabela_existe($pdo, 'totp_codigos')) {
                    $pdo->prepare('DELETE FROM totp_codigos WHERE usuario_id = ?')
                        ->execute([(int)$usuario['id']]);
                }
                sh_auditar($pdo, '2fa_desativado', 'usuarios', (int)$usuario['id']);
                $sucesso = 'Verificação em duas etapas desligada.';
                $tem_2fa = false;
            } catch (PDOException $e) {
                $erro = sh_erro_usuario($e, 'desligar a verificação em duas etapas');
            }
        }
    }

    /* ── Gerar novos códigos de recuperação ─────────────────────────────── */
    if ($acao === 'novos_codigos' && $tem_2fa) {
        $senha = (string)($_POST['senha'] ?? '');
        if (!password_verify($senha, $usuario['password'])) {
            $erro = 'Senha incorreta.';
        } else {
            $codigos_novos = sh_totp_gerar_recuperacao($pdo, (int)$usuario['id']);
            sh_auditar($pdo, '2fa_codigos_regerados', 'usuarios', (int)$usuario['id']);
            $sucesso = 'Códigos de recuperação renovados. Os anteriores não valem mais.';
        }
    }
}

/* Segredo pendente para quem está no meio da ativação. */
$segredo_pendente = '';
if (!$tem_2fa && $usuario) {
    if (empty($_SESSION['totp_pendente'])) {
        $_SESSION['totp_pendente'] = sh_totp_segredo();
    }
    $segredo_pendente = $_SESSION['totp_pendente'];
}

$restantes = $tem_2fa ? sh_totp_recuperacao_restantes($pdo, (int)$usuario['id']) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            Verificação em duas etapas
        </h2>
        <p class="panel-sub">
            Além da senha, entrar passa a exigir um código de seis dígitos gerado
            no seu celular. Mesmo que alguém descubra a sua senha, não entra sem
            o aparelho.
        </p>

        <?php if ($erro !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status"><?= e($sucesso) ?></div>
        <?php endif; ?>

        <?php if ($codigos_novos): ?>
            <div class="alert alert-warning" role="alert">
                <strong>Guarde estes códigos agora.</strong>
                Eles não voltam a aparecer. Cada um serve uma única vez e substitui
                o código do aplicativo quando você estiver sem o celular.
            </div>
            <ul class="codigos-recuperacao">
                <?php foreach ($codigos_novos as $c): ?>
                    <li><code><?= e($c) ?></code></li>
                <?php endforeach; ?>
            </ul>
            <p class="panel-sub">
                Imprima ou salve no gerenciador de senhas. Não guarde no mesmo
                celular que gera os códigos — se ele sumir, os dois somem juntos.
            </p>
        <?php endif; ?>

        <?php if ($tem_2fa): ?>

            <div class="estado-2fa estado-2fa--ativo">
                <p>
                    <strong>Ativa</strong> desde
                    <?= e(date('d/m/Y', strtotime($usuario['totp_ativado_em']))) ?>.
                    Restam <strong><?= $restantes ?></strong> código(s) de recuperação.
                </p>
            </div>

            <?php if ($restantes <= 2): ?>
                <div class="alert alert-warning" role="alert">
                    Você está com poucos códigos de recuperação. Gere um lote novo
                    antes que acabem.
                </div>
            <?php endif; ?>

            <div class="grade-acoes">
                <form method="POST" class="form-panel">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="novos_codigos">
                    <h3 class="subtitulo">Gerar novos códigos de recuperação</h3>
                    <p class="panel-sub">Os códigos antigos deixam de valer imediatamente.</p>
                    <div class="form-group">
                        <label for="senha_codigos">Confirme sua senha</label>
                        <input type="password" id="senha_codigos" name="senha"
                               autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">Gerar novos códigos</button>
                </form>

                <form method="POST" class="form-panel">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="desativar">
                    <h3 class="subtitulo">Desligar a verificação em duas etapas</h3>
                    <p class="panel-sub">
                        A conta volta a depender só da senha. Não recomendado para a
                        coordenação, que enxerga dado pessoal de aluno e de árbitro.
                    </p>
                    <div class="form-group">
                        <label for="senha_off">Confirme sua senha</label>
                        <input type="password" id="senha_off" name="senha"
                               autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-danger"
                            data-confirmar="Desligar a verificação em duas etapas desta conta?">
                        Desligar
                    </button>
                </form>
            </div>

        <?php else: ?>

            <ol class="passos-2fa">
                <li>
                    <h3>Instale um aplicativo autenticador</h3>
                    <p>
                        Google Authenticator, Microsoft Authenticator, Authy, ou o
                        próprio gerenciador de senhas do celular. Qualquer um serve:
                        todos falam o mesmo padrão.
                    </p>
                </li>
                <li>
                    <h3>Aponte a câmera para o código</h3>
                    <div class="qr-caixa">
                        <?= sh_qrcode_svg(
                                sh_totp_uri($segredo_pendente, $usuario['username']),
                                220,
                                'QR Code de configuração do segundo fator'
                            ) ?>
                    </div>
                    <p>
                        Sem câmera? No aplicativo, escolha “inserir chave manualmente”
                        e digite:
                    </p>
                    <p class="chave-manual">
                        <code id="chave-totp"><?= e(sh_totp_segredo_legivel($segredo_pendente)) ?></code>
                        <button type="button" class="btn-small" data-copiar="#chave-totp">Copiar</button>
                    </p>
                    <p class="panel-sub">
                        Conta: <strong><?= e($usuario['username']) ?></strong> ·
                        Tipo: baseado em tempo (TOTP) · 6 dígitos · 30 segundos.
                    </p>
                </li>
                <li>
                    <h3>Confirme com o código que apareceu</h3>
                    <form method="POST" class="form-panel">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="ativar">
                        <div class="form-group">
                            <label for="codigo">Código de seis dígitos</label>
                            <input type="text" id="codigo" name="codigo" class="campo-codigo"
                                   inputmode="numeric" autocomplete="one-time-code"
                                   pattern="[0-9]{6}" maxlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Ativar verificação em duas etapas</button>
                    </form>
                </li>
            </ol>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
