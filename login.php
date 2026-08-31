<?php
// login.php — autenticação e criação de conta de aluno.
require_once __DIR__ . '/includes/config.php';

$destinos = [
    'admin'   => 'admin/dashboard.php',
    'arbitro' => 'arbitro/painel.php',
    'aluno'   => 'aluno/painel.php',
];

// Já autenticado: vai direto para o painel do perfil.
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . ($destinos[$_SESSION['usuario_tipo']] ?? 'aluno/painel.php'));
    exit();
}

$erro    = '';
$sucesso = '';

// Bloqueio temporário após tentativas seguidas — mitiga força bruta.
// São duas barreiras: por sessão (imediata) e por IP na trilha de auditoria,
// que continua valendo mesmo se o atacante apagar os cookies.
const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_BLOQUEIO_SEG   = 300;
const LOGIN_MAX_POR_IP     = 15;
const LOGIN_JANELA_IP_MIN  = 15;

$tentativas   = $_SESSION['login_tentativas'] ?? 0;
$bloqueado_ate = $_SESSION['login_bloqueado_ate'] ?? 0;
$bloqueado    = $bloqueado_ate > time();

// Aviso deixado pela expiração de sessão por inatividade.
if (!empty($_SESSION['aviso_sessao'])) {
    $erro = $_SESSION['aviso_sessao'];
    unset($_SESSION['aviso_sessao']);
}

/* ── LOGIN ───────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Sua sessão expirou. Recarregue a página e tente novamente.';
    } elseif ($bloqueado) {
        $erro = 'Muitas tentativas seguidas. Aguarde ' . ceil(($bloqueado_ate - time()) / 60) . ' minuto(s) para tentar de novo.';
    } elseif (sh_falhas_login_ip($pdo, LOGIN_JANELA_IP_MIN) >= LOGIN_MAX_POR_IP) {
        $erro = 'Este dispositivo excedeu o limite de tentativas. Tente novamente em ' . LOGIN_JANELA_IP_MIN . ' minutos.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            $usuario = $stmt->fetch();

            /* Só password_verify (SH-48).
               A versão anterior tinha um segundo caminho: se o hash não
               batesse, comparava a senha digitada com o conteúdo bruto da
               coluna, para migrar o seed em texto puro. O problema é que esse
               desvio não expirava — qualquer linha da tabela `usuarios` cujo
               `password` fosse texto legível continuava sendo uma credencial
               válida, e o seed com "admin1234" está publicado no repositório.
               Agora o seed já nasce em hash e o desvio não existe mais. */
            $senha_valida = $usuario && password_verify($password, $usuario['password']);

            // Conta suspensa ou anonimizada (exclusão pedida via LGPD) não
            // entra, mesmo com a senha correta.
            $status_conta = $usuario['status'] ?? 'ativo';
            if ($senha_valida && $status_conta !== 'ativo') {
                sh_auditar($pdo, 'login_bloqueado_status', 'usuarios', (int)$usuario['id'], $status_conta);
                $senha_valida = false;
                $erro = 'Esta conta não está ativa. Fale com a coordenação.';
            }

            if ($senha_valida) {
                session_regenerate_id(true);
                unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueado_ate']);

                /* ── Senha de fábrica ainda em uso (SH-48) ────────────────
                   A migration só marcou `senha_provisoria` nas contas que
                   guardavam a senha em texto puro; numa instalação que já
                   tinha bcrypt ninguém era marcado, e "admin1234" seguia
                   valendo com o painel jurando que estava tudo certo. Aqui a
                   senha digitada está em mãos: compará-la com a lista não
                   custa nada e obriga a troca antes de abrir qualquer tela. */
                if (sh_senha_de_fabrica($password) && empty($usuario['senha_provisoria'])) {
                    try {
                        $pdo->prepare('UPDATE usuarios SET senha_provisoria = 1 WHERE id = ?')
                            ->execute([(int)$usuario['id']]);
                    } catch (PDOException $ex) {
                        /* Sem a coluna (migration v3 pendente) não há onde
                           registrar, mas a troca ainda é exigida nesta sessão. */
                        sh_log_excecao($ex, 'marcar senha de fábrica no login');
                    }
                    $usuario['senha_provisoria'] = 1;
                    sh_auditar($pdo, 'senha_fabrica_detectada', 'usuarios', (int)$usuario['id']);
                }

                /* ── Segundo fator (SH-65) ────────────────────────────────
                   Com 2FA ativo, a senha correta ainda NÃO autentica: ela só
                   qualifica o usuário para a segunda etapa. Nada de
                   `usuario_id` na sessão antes do código — se fosse gravado
                   aqui, bastaria fechar a tela do segundo fator e navegar
                   direto para o painel. */
                if (!empty($usuario['totp_segredo']) && !empty($usuario['totp_ativado_em'])) {
                    $_SESSION['2fa_usuario_id'] = (int)$usuario['id'];
                    $_SESSION['2fa_expira_em']  = time() + 300;   // 5 minutos para digitar
                    sh_auditar($pdo, 'login_aguardando_2fa', 'usuarios', (int)$usuario['id']);
                    header('Location: verificar_2fa.php');
                    exit();
                }

                $_SESSION['usuario_id']   = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];
                $_SESSION['usuario_foto'] = $usuario['foto_perfil'] ?? '';

                try {
                    $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")
                        ->execute([$usuario['id']]);
                } catch (PDOException $ex) {
                    // Coluna ainda não existe (migration v2 não aplicada): não é bloqueante.
                }
                sh_auditar($pdo, 'login', 'usuarios', (int)$usuario['id'], $usuario['tipo']);

                /* Senha de fábrica: entra, mas o config.php desvia toda tela
                   para trocar_senha.php enquanto isto valer (SH-48). */
                if (!empty($usuario['senha_provisoria'])) {
                    $_SESSION['senha_provisoria'] = true;
                    header('Location: trocar_senha.php');
                    exit();
                }

                header('Location: ' . ($destinos[$usuario['tipo']] ?? 'aluno/painel.php'));
                exit();
            }

            // Mensagem genérica: não revela se o usuário existe.
            sh_auditar($pdo, 'login_falha', 'usuarios', null, $username);
            $tentativas = $tentativas + 1;
            $_SESSION['login_tentativas'] = $tentativas;
            if ($tentativas >= LOGIN_MAX_TENTATIVAS) {
                $_SESSION['login_bloqueado_ate'] = time() + LOGIN_BLOQUEIO_SEG;
                $_SESSION['login_tentativas'] = 0;
                $erro = 'Muitas tentativas seguidas. Tente novamente em 5 minutos.';
            } elseif ($erro === '') {
                $restantes = LOGIN_MAX_TENTATIVAS - $tentativas;
                $erro = 'Usuário ou senha incorretos. Você ainda tem ' . $restantes . ' tentativa(s).';
            }
        } catch (PDOException $e) {
            sh_log_excecao($e, 'login');
            $erro = 'Erro no sistema. Contate o administrador.';
        }
    }
}

/* ── CADASTRO DE ALUNO ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastro'])) {
    $nome             = trim((string)($_POST['nome'] ?? ''));
    $username         = trim((string)($_POST['username'] ?? ''));
    $email            = trim((string)($_POST['email'] ?? ''));
    $password         = (string)($_POST['password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Sua sessão expirou. Recarregue a página e tente novamente.';
    } elseif ($nome === '' || $username === '' || $password === '') {
        $erro = 'Preencha nome, usuário e senha.';
    } elseif (!preg_match('/^[a-z0-9._-]{4,30}$/i', $username)) {
        $erro = 'O usuário deve ter de 4 a 30 caracteres, sem espaços ou acentos.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'O e-mail informado não é válido.';
    } elseif ($password !== $confirm_password) {
        $erro = 'As senhas informadas não coincidem.';
    } elseif (strlen($password) < 6) {
        $erro = 'A senha deve conter no mínimo 6 caracteres.';
    } elseif (empty($_POST['aceite'])) {
        $erro = 'É preciso aceitar os Termos de Uso e a Política de Privacidade.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                $erro = 'Este nome de usuário já está em uso. Escolha outro.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $ins = $pdo->prepare(
                        "INSERT INTO usuarios (username, password, tipo, nome, email,
                                               aceite_termos_em, aceite_privacidade_em)
                         VALUES (?, ?, 'aluno', ?, ?, NOW(), NOW())"
                    );
                    $ins->execute([$username, $hash, $nome, $email !== '' ? $email : null]);
                } catch (PDOException $ex) {
                    // Estrutura anterior à migration v2.
                    $pdo->prepare("INSERT INTO usuarios (username, password, tipo, nome) VALUES (?, ?, 'aluno', ?)")
                        ->execute([$username, $hash, $nome]);
                }

                if ($email !== '') {
                    sh_registrar_consentimento($pdo, 'termos', $email, true);
                }
                $sucesso = 'Cadastro realizado com sucesso! Faça login para continuar.';
            }
        } catch (PDOException $e) {
            sh_log_excecao($e, 'cadastro');
            $erro = 'Erro no banco de dados. Contate o administrador.';
        }
    }
}

generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR"<?= sh_tema_attr() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= sh_tema_boot() ?>
    <title>Entrar — SportHub | Sistema de Interclasse Escolar</title>
    <meta name="description" content="Acesse a plataforma SportHub para gerenciar o interclasse da sua escola: jogos, súmulas, classificação e resultados.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(sh_asset('img/Logo-96.png')) ?>" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style<?= sh_nonce_attr() ?>>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* Mesma paleta de css/style.css, para o login não destoar do painel. */
        :root, [data-theme="light"] {
            color-scheme: light;
            --canvas:  #e7e5e0;
            --surface: #fbfaf8;
            --surface-2: #f2f0eb;
            --surface-3: #e9e6e0;
            --ink:     #16171a;
            --ink-soft:#3c3f45;
            --muted:   #6f7279;
            --line:    #dcd9d3;
            --line-strong: #c7c3bc;
            --ember:   #d6522f;
            --ember-dark: #b13f1f;
            --on-ember: #ffffff;
            --charcoal: #21242a;
            --green:   #1c6b4a;
            --green-bg:#e3f2ea;
            --red:     #b5372a;
            --red-bg:  #fbeae7;
            --navy:    #21242a;
            --gray-600:#6f7279;
            --placeholder: #9d9891;
            --panel-dark: #17191c;
            --font-display: 'Space Grotesk', system-ui, sans-serif;
            --font-sans:    'DM Sans', system-ui, sans-serif;
            --ease: cubic-bezier(.22,.61,.36,1);
            --sh-sm: 0 2px 8px rgba(20,20,20,.05);
            --sh:    0 8px 30px -12px rgba(20,20,20,.16);
            --sh-lg: 0 40px 90px -40px rgba(20,20,20,.45);
        }

        [data-theme="dark"] {
            color-scheme: dark;
            --canvas:  #101215;
            --surface: #191c20;
            --surface-2: #21252a;
            --surface-3: #2a2f35;
            --ink:     #f0eeeb;
            --ink-soft:#cbc9c5;
            --muted:   #9298a1;
            --line:    #2b3036;
            --line-strong: #3b424a;
            --ember:   #f2764f;
            --ember-dark: #d85c36;
            --on-ember: #1c1310;
            --charcoal: #2a2f35;
            --green:   #4cbe8c;
            --green-bg:rgba(76,190,140,.14);
            --red:     #f0776a;
            --red-bg:  rgba(240,119,106,.14);
            --navy:    #2a2f35;
            --gray-600:#9298a1;
            --placeholder: #7b818a;
            --panel-dark: #0c0e10;
            --sh-sm: 0 2px 8px rgba(0,0,0,.36);
            --sh:    0 8px 30px -12px rgba(0,0,0,.56);
            --sh-lg: 0 40px 90px -40px rgba(0,0,0,.78);
        }

        /* ── BOTÃO DE TEMA ── */
        .theme-toggle {
            position: fixed; top: 20px; right: 20px; z-index: 50;
            width: 42px; height: 42px;
            display: grid; place-items: center;
            border: 1px solid var(--line-strong); border-radius: 12px;
            background: var(--surface); color: var(--ink);
            cursor: pointer; overflow: hidden;
            box-shadow: var(--sh-sm);
            transition: border-color .2s var(--ease), color .2s var(--ease), transform .2s var(--ease);
        }
        .theme-toggle:hover { border-color: var(--ember); color: var(--ember); transform: translateY(-1px); }
        .theme-toggle i { grid-area: 1 / 1; font-size: .95rem; transition: opacity .25s var(--ease), transform .35s var(--ease); }
        .theme-toggle .fa-sun  { opacity: 0; transform: rotate(-90deg) scale(.5); }
        .theme-toggle .fa-moon { opacity: 1; transform: none; }
        [data-theme="dark"] .theme-toggle .fa-sun  { opacity: 1; transform: none; }
        [data-theme="dark"] .theme-toggle .fa-moon { opacity: 0; transform: rotate(90deg) scale(.5); }
        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
        }
        @media (max-width: 620px) { .theme-toggle { top: 12px; right: 12px; } }

        @keyframes rise { from { opacity:0; transform: translateY(16px);} to { opacity:1; transform:none; } }
        @keyframes fade { from { opacity:0 } to { opacity:1 } }
        @media (prefers-reduced-motion: reduce) { *{ animation:none !important; transition:none !important } }

        html { font-size: 16px; }
        body {
            font-family: var(--font-sans);
            background: var(--canvas);
            color: var(--ink-soft);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
        }

        /* ── LAYOUT ── */
        .page-wrap {
            flex: 1;
            width: min(100% - 48px, 1180px);
            margin: 0 auto;
            padding: 56px 0;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 28px;
            align-items: stretch;
            animation: rise .6s var(--ease) both;
        }

        /* ── HERO ── */
        .hero-panel {
            background: var(--panel-dark);
            color: #fff;
            border-radius: 32px;
            padding: 52px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 48px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--sh-lg);
        }
        .hero-panel::after {
            content: '';
            position: absolute; right: -120px; bottom: -160px;
            width: 420px; height: 420px; border-radius: 50%;
            background: radial-gradient(circle, rgba(232,93,58,.38), transparent 62%);
            pointer-events: none;
        }
        .hero-panel > * { position: relative; z-index: 1; }

        .hero-logo { display: flex; align-items: center; gap: 14px; }
        .hero-logo-icon {
            width: 52px; height: 52px; border-radius: 16px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            display: grid; place-items: center;
        }
        .hero-logo-text { display: flex; flex-direction: column; }
        .hero-logo-name {
            font-family: var(--font-display);
            font-size: 1.2rem; font-weight: 700;
            letter-spacing: -0.04em; color: #fff; line-height: 1;
        }
        .hero-logo-sub {
            font-size: .64rem; letter-spacing: .18em; text-transform: uppercase;
            color: rgba(255,255,255,.5); margin-top: 5px; font-weight: 500;
        }

        .hero-badge {
            display: inline-block;
            padding: 5px 13px; border-radius: 99px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.14);
            font-size: .68rem; font-weight: 600;
            letter-spacing: .15em; text-transform: uppercase;
            color: rgba(255,255,255,.72);
            margin-bottom: 24px;
        }
        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(2rem, 3.4vw, 2.9rem);
            font-weight: 600;
            letter-spacing: -0.045em;
            line-height: 1.08;
            color: #fff;
        }
        .hero-title em { font-style: normal; color: var(--ember); }
        .hero-divider {
            width: 54px; height: 3px; border-radius: 3px;
            background: var(--ember);
            margin: 26px 0 22px;
        }
        .hero-desc {
            color: rgba(255,255,255,.6);
            font-size: .98rem;
            max-width: 46ch;
            line-height: 1.75;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,.1);
        }
        .hero-stat { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .hero-stat-num {
            font-family: var(--font-display);
            font-size: 1.7rem; font-weight: 700;
            letter-spacing: -0.05em; color: #fff; line-height: 1;
        }
        .hero-stat-label {
            font-size: .68rem; letter-spacing: .1em; text-transform: uppercase;
            color: rgba(255,255,255,.45); font-weight: 600;
        }

        /* ── FORMULÁRIO ── */
        .form-panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 32px;
            padding: 52px 48px;
            box-shadow: var(--sh);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-header { margin-bottom: 30px; }
        .form-header h2 {
            font-family: var(--font-display);
            font-size: 1.65rem; font-weight: 600;
            letter-spacing: -0.035em; color: var(--ink);
            margin-bottom: 8px;
        }
        .form-header p { color: var(--muted); font-size: .92rem; }

        /* Alternador Entrar / Cadastrar-se, centralizado no painel.
           As duas metades têm a mesma largura (flex:1) para que o indicador
           branco não mude de tamanho ao trocar de aba — um controle que
           "pula" a cada clique é o que fazia o bloco parecer desalinhado. */
        .tabs-nav {
            display: flex; gap: 4px; padding: 5px;
            width: min(100%, 320px);
            margin: 0 auto 28px;
            background: var(--surface-3); border-radius: 14px;
        }
        .tab-btn {
            flex: 1;
            border: none; background: transparent; cursor: pointer;
            padding: 10px 18px; border-radius: 10px;
            font-family: var(--font-sans); font-size: .86rem; font-weight: 600;
            color: var(--muted); transition: all .25s var(--ease);
            text-align: center; white-space: nowrap;
        }
        .tab-btn:hover { color: var(--ink); }
        .tab-btn.active { background: var(--surface); color: var(--ink); box-shadow: var(--sh-sm); }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: rise .4s var(--ease); }

        .field { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
        .field label {
            font-size: .74rem; font-weight: 600;
            letter-spacing: .08em; text-transform: uppercase; color: var(--muted);
        }
        .field input {
            width: 100%; padding: 14px 16px;
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 13px;
            font-family: var(--font-sans); font-size: .94rem; color: var(--ink);
            transition: all .22s var(--ease);
        }
        .field input::placeholder { color: var(--placeholder); }
        .field input:hover { border-color: var(--line-strong); }
        .field input:focus {
            outline: none; background: var(--surface);
            border-color: var(--ember);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--ember) 18%, transparent);
        }
        .field-hint { font-size: .76rem; color: var(--muted); min-height: 1em; }
        .field-hint.error { color: var(--red); }
        .field-hint.ok { color: var(--green); }

        .btn-primary {
            width: 100%;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 15px 24px;
            border: none; border-radius: 13px;
            background: var(--ember); color: var(--on-ember);
            font-family: var(--font-sans); font-size: .93rem; font-weight: 600;
            cursor: pointer; margin-top: 6px;
            box-shadow: 0 12px 26px -12px color-mix(in srgb, var(--ember) 70%, transparent);
            transition: transform .22s var(--ease), background .22s var(--ease), box-shadow .28s var(--ease);
        }
        .btn-primary:hover { background: var(--ember-dark); color: var(--on-ember); transform: translateY(-1px); box-shadow: 0 16px 32px -12px color-mix(in srgb, var(--ember) 62%, transparent); }
        .btn-primary:active { transform: translateY(1px) scale(.995); }

        .account-type-card {
            display: flex; align-items: center; gap: 14px;
            padding: 15px 16px; margin-bottom: 18px;
            background: var(--surface-2); border: 1px solid var(--line); border-radius: 14px;
        }
        .account-type-icon { font-size: 1.5rem; }
        .account-type-info { display: flex; flex-direction: column; }
        .account-type-info span { font-weight: 600; color: var(--ink); font-size: .9rem; }
        .account-type-info small { color: var(--muted); font-size: .78rem; }

        .alert {
            display: flex; align-items: center; gap: 10px;
            padding: 13px 16px; border-radius: 13px;
            font-size: .86rem; font-weight: 500; margin-bottom: 20px;
            border: 1px solid transparent;
            animation: rise .35s var(--ease);
        }
        .alert svg { flex-shrink: 0; }
        .alert-error { background: var(--red-bg); color: var(--red); border-color: color-mix(in srgb, var(--red) 24%, transparent); }
        .alert-success { background: var(--green-bg); color: var(--green); border-color: color-mix(in srgb, var(--green) 24%, transparent); }

        .form-sep {
            display: flex; align-items: center; gap: 14px;
            margin: 24px 0 16px;
            font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: var(--muted);
        }
        .form-sep::before, .form-sep::after { content: ''; flex: 1; height: 1px; background: var(--line); }

        .form-note {
            margin-top: 28px; padding-top: 22px;
            border-top: 1px solid var(--line);
            font-size: .76rem; color: var(--muted); text-align: center; line-height: 1.8;
        }
        .form-note strong { color: var(--ink-soft); }

        /* ── RODAPÉ ── */
        .site-footer {
            padding: 26px 24px;
            text-align: center;
            font-size: .78rem;
            color: var(--muted);
            border-top: 1px solid var(--line);
        }

        /* ── Links auxiliares do hero ── */
        .hero-links {
            display: flex; flex-wrap: wrap; gap: 8px 20px;
            margin-top: 26px; padding-top: 22px;
            border-top: 1px solid rgba(255,255,255,.1);
        }
        .hero-links a {
            font-size: .82rem; font-weight: 500;
            color: rgba(255,255,255,.55);
            text-decoration: none;
            transition: color .2s var(--ease);
        }
        .hero-links a:hover { color: var(--ember); }
        .hero-links a i { font-style: normal; margin-right: 4px; }

        /* ── Aceite de termos no cadastro ── */
        .consent-box {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 14px 15px; margin: 4px 0 18px;
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 13px;
            cursor: pointer;
            font-size: .8rem; line-height: 1.6; color: var(--ink-soft);
            transition: border-color .2s var(--ease), background .2s var(--ease);
        }
        .consent-box:hover { border-color: var(--line-strong); }
        .consent-box input {
            width: 17px; height: 17px; margin-top: 2px;
            accent-color: var(--ember); flex-shrink: 0; cursor: pointer;
        }
        .consent-box a { color: var(--ember); text-decoration: underline; text-underline-offset: 2px; }

        .form-note a { color: var(--ink-soft); text-decoration: underline; text-underline-offset: 2px; }
        .form-note a:hover { color: var(--ember); }

        /* ── LGPD ── */
        #lgpd-banner {
            position: fixed; left: 20px; right: 20px; bottom: 20px;
            max-width: 820px; margin: 0 auto;
            display: flex; align-items: center; gap: 18px;
            padding: 16px 20px;
            background: var(--panel-dark);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 18px;
            box-shadow: var(--sh-lg);
            z-index: 4000;
            animation: rise .5s var(--ease) both;
        }
        #lgpd-banner p { color: rgba(255,255,255,.62); font-size: .8rem; line-height: 1.6; }
        #lgpd-banner a { color: var(--ember); text-decoration: underline; }
        #lgpd-accept {
            flex-shrink: 0;
            padding: 10px 20px; border: none; border-radius: 11px;
            background: var(--ember); color: var(--on-ember);
            font-family: var(--font-sans); font-size: .82rem; font-weight: 600; cursor: pointer;
            transition: background .22s var(--ease), transform .22s var(--ease);
        }
        #lgpd-accept:hover { background: var(--ember-dark); transform: translateY(-1px); }

        @media (max-width: 980px) {
            .page-wrap { grid-template-columns: 1fr; padding: 32px 0; width: calc(100% - 32px); }
            .hero-panel { padding: 40px 32px; gap: 34px; }
            .form-panel { padding: 40px 32px; }
        }
        @media (max-width: 520px) {
            .hero-panel, .form-panel { padding: 32px 22px; border-radius: 26px; }
            .hero-stats { grid-template-columns: 1fr; gap: 16px; }
            #lgpd-banner { flex-direction: column; align-items: flex-start; }
            #lgpd-accept { width: 100%; }
        }
    </style>

    <!-- Camada de vidro: vem depois do <style> acima de propósito, para poder
         sobrepor o fundo sólido do <body> e dos painéis desta página. -->
    <link rel="stylesheet" href="<?= e(sh_asset('css/glass.css')) ?>">
    <link rel="stylesheet" href="<?= e(sh_asset('css/u.css')) ?>">
</head>
<body>

<?= sh_tema_botao() ?>

<div class="page-wrap">

    <!-- HERO PANEL -->
    <aside class="hero-panel" role="complementary">
        <div class="hero-logo">
            <div class="hero-logo-icon">
                    <?php
                    $logoPath = __DIR__ . '/img/logo.png';
                    $logoUrl = 'img/logo.png';
                    if (file_exists($logoPath)) {
                        $logoUrl .= '?v=' . filemtime($logoPath);
                    }
                    ?>
                    <img class="u-width-42px" src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo SportHub">
                </div>
            <div class="hero-logo-text">
                <span class="hero-logo-name">SportHub</span>
                <span class="hero-logo-sub">Gestão Esportiva Escolar</span>
            </div>
        </div>

        <div class="hero-body">
            <span class="hero-badge">Sistema Acadêmico Integrado</span>
            <h1 class="hero-title">
                Interclasse Escolar<br>
                <em>organizado, transparente</em><br>
                e digital.
            </h1>
            <div class="hero-divider" aria-hidden="true"></div>
            <p class="hero-desc">
                Plataforma de gerenciamento completo de campeonatos interclasse:
                times, árbitros, jogos ao vivo, tabelas de classificação
                e resultados em tempo real.
            </p>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat-num">3</span>
                <span class="hero-stat-label">Perfis de acesso</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-num">100%</span>
                <span class="hero-stat-label">Online</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-num">LGPD</span>
                <span class="hero-stat-label">Em conformidade</span>
            </div>
        </div>

        <div class="hero-links">
            <a href="<?= e(sh_url('index.php')) ?>"><i>&larr;</i> Voltar ao site</a>
            <a href="<?= e(sh_url('como-funciona.php')) ?>">Como funciona</a>
            <a href="<?= e(sh_url('cadastro-arbitro.php')) ?>">Sou árbitro</a>
        </div>
    </aside>

    <!-- FORM PANEL -->
    <main class="form-panel" role="main">
        <div class="form-header">
            <h2>Acesso ao Sistema</h2>
            <p>Insira suas credenciais para continuar ou crie uma nova conta de aluno.</p>
        </div>

        <div class="tabs-nav" role="tablist">
            <button class="tab-btn active" id="tab-login"    role="tab" aria-selected="true"  aria-controls="pane-login"    type="button">Entrar</button>
            <button class="tab-btn"        id="tab-cadastro" role="tab" aria-selected="false" aria-controls="pane-cadastro" type="button">Cadastrar-se</button>
        </div>

        <!-- TAB LOGIN -->
        <div class="tab-pane active" id="pane-login" role="tabpanel">
            <?php if ($erro && !isset($_POST['cadastro'])): ?>
            <div class="alert alert-error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($erro) ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="login" value="1">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="login_username">Usuário</label>
                    <input type="text" id="login_username" name="username" placeholder="Digite seu usuário" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="login_password">Senha</label>
                    <input type="password" id="login_password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-primary">Acessar o sistema →</button>
            </form>

            <p class="u-text-align-center-2">
                <a href="recuperar_senha.php">Esqueci minha senha</a>
            </p>

            <div class="form-sep">ou</div>
            <p class="u-text-align-center-2">
                Não possui conta?
                <button type="button" data-ir-para-aba="cadastro" class="link-inline">
                    Cadastre-se gratuitamente
                </button>
            </p>
        </div>

        <!-- TAB CADASTRO -->
        <div class="tab-pane" id="pane-cadastro" role="tabpanel">
            <?php if ($erro && isset($_POST['cadastro'])): ?>
            <div class="alert alert-error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($erro) ?>
            </div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
            <div class="alert alert-success" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                <?= htmlspecialchars($sucesso) ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="cadastro" value="1">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="cadastro_nome">Nome Completo *</label>
                    <input type="text" id="cadastro_nome" name="nome" placeholder="Seu nome completo" autocomplete="name" required>
                </div>
                <div class="field">
                    <label for="cadastro_username">Nome de Usuário *</label>
                    <input type="text" id="cadastro_username" name="username" placeholder="Escolha um identificador único" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="cadastro_email">E-mail <span class="u-text-transform-none">(opcional)</span></label>
                    <input type="email" id="cadastro_email" name="email" placeholder="Para recuperar o acesso" autocomplete="email">
                </div>
                <div class="account-type-card">
                    <div class="account-type-icon">👨‍🎓</div>
                    <div class="account-type-info">
                        <span>Conta de Aluno</span>
                        <small>Acompanhe jogos, resultados e classificação</small>
                    </div>
                    <input type="hidden" name="tipo" value="aluno">
                </div>
                <div class="field">
                    <label for="cadastro_password">Senha *</label>
                    <input type="password" id="cadastro_password" name="password" placeholder="Mínimo 6 caracteres" autocomplete="new-password" required minlength="6" aria-describedby="pass-hint">
                    <span class="field-hint" id="pass-hint"></span>
                </div>
                <div class="field">
                    <label for="cadastro_confirm_password">Confirmar Senha *</label>
                    <input type="password" id="cadastro_confirm_password" name="confirm_password" placeholder="Repita a senha" autocomplete="new-password" required aria-describedby="confirm-hint">
                    <span class="field-hint" id="confirm-hint"></span>
                </div>

                <label class="consent-box">
                    <input type="checkbox" name="aceite" value="1" required>
                    <span>
                        Li e aceito os <a href="<?= e(sh_url('termos.php')) ?>" target="_blank">Termos de Uso</a>
                        e a <a href="<?= e(sh_url('privacidade.php')) ?>" target="_blank">Política de Privacidade</a>.
                        Se eu for menor de 16 anos, confirmo que um responsável autorizou este cadastro.
                    </span>
                </label>

                <button type="submit" class="btn-primary">Criar minha conta →</button>
            </form>
        </div>

        <p class="form-note">
            Dados tratados conforme a <strong>LGPD</strong> — Lei nº 13.709/2018.<br>
            <a href="<?= e(sh_url('privacidade.php')) ?>">Privacidade</a> ·
            <a href="<?= e(sh_url('termos.php')) ?>">Termos</a> ·
            <a href="<?= e(sh_url('cookies.php')) ?>">Cookies</a> ·
            <a href="<?= e(sh_url('lgpd.php')) ?>">Seus direitos</a>
        </p>
        <p class="u-text-align-center-3">
            É professor ou árbitro?
            <a class="u-color-ember-4" href="<?= e(sh_url('cadastro-arbitro.php')) ?>">Solicite seu credenciamento</a>
        </p>
    </main>

</div>

<footer class="site-footer">
    SportHub &mdash; Sistema de Gerenciamento de Interclasse Escolar &copy; <?= date('Y') ?> &nbsp;|&nbsp;
    Dados protegidos conforme a LGPD — Lei nº 13.709/2018
</footer>

<div id="lgpd-banner">
    <p>
        <strong class="u-color-fff">🔒 Aviso de Privacidade</strong> — Este sistema coleta dados pessoais exclusivamente
        para autenticação e operação do campeonato escolar, conforme a
        <a href="<?= e(sh_url('privacidade.php')) ?>">LGPD — Lei nº 13.709/2018</a>.
        Nenhum dado é vendido ou compartilhado para fins publicitários.
        <a href="<?= e(sh_url('lgpd.php')) ?>">Exercer meus direitos</a>.
    </p>
    <button id="lgpd-accept">Entendi</button>
</div>

<script<?= sh_nonce_attr() ?>>
/* Alternância de tema — mesma preferência usada no painel (localStorage + cookie). */
(function () {
    var CHAVE_TEMA = 'sporthub-tema';
    document.querySelectorAll('[data-theme-toggle]').forEach(function (botao) {
        var refletir = function (tema) {
            botao.setAttribute('aria-pressed', tema === 'dark' ? 'true' : 'false');
            var rotulo = botao.querySelector('.sr-only');
            if (rotulo) rotulo.textContent = tema === 'dark' ? 'Tema claro' : 'Tema escuro';
        };
        refletir(document.documentElement.getAttribute('data-theme'));
        botao.addEventListener('click', function () {
            var novo = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', novo);
            try { localStorage.setItem(CHAVE_TEMA, novo); } catch (e) {}
            document.cookie = CHAVE_TEMA + '=' + novo + ';path=/;max-age=31536000;samesite=Lax';
            refletir(novo);
        });
    });
})();

function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        const active = b.id === 'tab-' + name;
        b.classList.toggle('active', active);
        b.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.tab-pane').forEach(p => {
        p.classList.toggle('active', p.id === 'pane-' + name);
    });
}

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.id.replace('tab-', '')));
});

/* Atalhos "Cadastre-se" / "Entrar" no meio do texto (SH-37): o
   o atributo de evento saiu do HTML porque a CSP nao aceita mais script embutido. */
document.querySelectorAll('[data-ir-para-aba]').forEach(el => {
    el.addEventListener('click', () => switchTab(el.getAttribute('data-ir-para-aba')));
});

<?php if (isset($_POST['cadastro']) || $sucesso): ?>
switchTab('cadastro');
<?php endif; ?>

const passInput    = document.getElementById('cadastro_password');
const passHint     = document.getElementById('pass-hint');
const confirmInput = document.getElementById('cadastro_confirm_password');
const confirmHint  = document.getElementById('confirm-hint');

passInput.addEventListener('input', () => {
    const v = passInput.value;
    if (!v)               { passHint.textContent = ''; passHint.className = 'field-hint'; }
    else if (v.length < 6){ passHint.textContent = 'Muito curta — mínimo 6 caracteres'; passHint.className = 'field-hint error'; }
    else if (v.length < 9){ passHint.textContent = 'Senha razoável'; passHint.className = 'field-hint ok'; }
    else                  { passHint.textContent = 'Senha forte ✓';  passHint.className = 'field-hint ok'; }
    checkMatch();
});

confirmInput.addEventListener('input', checkMatch);

function checkMatch() {
    const v = confirmInput.value;
    if (!v) { confirmHint.textContent = ''; confirmHint.className = 'field-hint'; return; }
    const match = v === passInput.value;
    confirmHint.textContent = match ? 'Senhas coincidem ✓' : 'As senhas não coincidem';
    confirmHint.className   = 'field-hint ' + (match ? 'ok' : 'error');
    confirmInput.style.borderColor = match ? 'var(--green)' : 'var(--red)';
}

const lgpdBanner = document.getElementById('lgpd-banner');
document.getElementById('lgpd-accept').addEventListener('click', () => {
    lgpdBanner.style.display = 'none';
    try { sessionStorage.setItem('lgpd_accepted', '1'); } catch(e) {}
});
try {
    if (sessionStorage.getItem('lgpd_accepted') === '1') lgpdBanner.style.display = 'none';
} catch(e) {}
</script>

<script src="<?= e(sh_asset('js/sporthub-comportamento.js')) ?>" defer></script>
<script src="<?= e(sh_asset('js/sporthub-ui.js')) ?>" defer></script>

</body>
</html>
