<?php
// includes/config.php — configuração central, conexão e helpers compartilhados

/* ── Sessão endurecida ───────────────────────────────────────────────────
   Tudo aqui precisa vir ANTES de session_start():
   · httponly  → o cookie fica invisível para JavaScript (um XSS não o rouba);
   · SameSite=Lax → o navegador não envia o cookie em requisição de outro site,
     que é a base de todo ataque CSRF;
   · strict_mode → o PHP recusa IDs de sessão inventados pelo atacante
     (impede fixação de sessão);
   · secure → em HTTPS, o cookie nunca trafega em texto puro.            */
if (session_status() === PHP_SESSION_NONE) {
    $sh_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '7200');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $sh_https,
    ]);
    session_name('SPORTHUBSESSID');
    session_start();
}

/* Expiração por inatividade e renovação periódica do identificador. */
const SH_SESSAO_INATIVIDADE = 7200;  // 2 h sem atividade encerram a sessão
const SH_SESSAO_ROTACAO     = 1800;  // ID trocado a cada 30 min

if (isset($_SESSION['usuario_id'])) {
    $sh_agora = time();

    if (isset($_SESSION['ultima_atividade']) && ($sh_agora - $_SESSION['ultima_atividade']) > SH_SESSAO_INATIVIDADE) {
        $_SESSION = [];
        session_regenerate_id(true);   // o ID antigo deixa de valer
        $_SESSION['aviso_sessao'] = 'Sua sessão expirou por inatividade. Entre novamente.';
    } else {
        $_SESSION['ultima_atividade'] = $sh_agora;

        if (!isset($_SESSION['sessao_criada_em'])) {
            $_SESSION['sessao_criada_em'] = $sh_agora;
        } elseif (($sh_agora - $_SESSION['sessao_criada_em']) > SH_SESSAO_ROTACAO) {
            session_regenerate_id(true);
            $_SESSION['sessao_criada_em'] = $sh_agora;
        }
    }
}

/* ── Identidade do produto ───────────────────────────────────────────────── */
const SH_NOME         = 'SportHub';
const SH_TAGLINE      = 'Interclasse organizado, transparente e digital.';
const SH_EMAIL        = 'contato@sporthub.com.br';
const SH_EMAIL_DPO    = 'dpo@sporthub.com.br';
const SH_WHATSAPP     = '5519994825860';
const SH_INSTAGRAM    = 'https://www.instagram.com/sporthubgg/';
const SH_VERSAO_POLITICA = '1.0';
const SH_POLITICA_DATA   = '19 de agosto de 2026';

/* ── Banco de dados ──────────────────────────────────────────────────────── */
$host     = 'localhost';
$dbname   = 'olimpiasp';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // A mensagem original vai só para o log: exibi-la revelaria host, usuário
    // e estrutura do banco para quem estivesse sondando o servidor.
    error_log('Falha na conexão com o banco: ' . $e->getMessage());
    http_response_code(503);
    die('Não foi possível conectar ao banco de dados. Tente novamente em instantes.');
}

/* ── Cabeçalhos de segurança ─────────────────────────────────────────────
   O .htaccess já envia parte disso, mas depende do Apache com mod_headers
   ativo. Enviando também pelo PHP, a proteção existe em qualquer servidor. */
function sh_headers_seguranca() {
    if (PHP_SAPI === 'cli' || headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header_remove('X-Powered-By');

    /* Content-Security-Policy: mesmo que alguém consiga injetar HTML numa
       página, o navegador se recusa a carregar script de qualquer origem
       fora desta lista — é o que impede um XSS de virar roubo de sessão.
       'unsafe-inline' ainda é necessário porque o projeto usa <script> e
       style="" embutidos no HTML. */
    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "img-src 'self' data: blob:",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "connect-src 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));

    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
sh_headers_seguranca();

/* ── Sessão e permissões ─────────────────────────────────────────────────── */
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        // sh_url() monta o caminho a partir da raiz do projeto: funciona tanto
        // para páginas na raiz quanto para as que estão em admin/, aluno/ etc.
        header('Location: ' . sh_url('login.php'));
        exit();
    }
}

function getUsuarioTipo() {
    return $_SESSION['usuario_tipo'] ?? null;
}

function isAdmin()   { return ($_SESSION['usuario_tipo'] ?? '') === 'admin'; }
function isArbitro() { return ($_SESSION['usuario_tipo'] ?? '') === 'arbitro'; }
function isAluno()   { return ($_SESSION['usuario_tipo'] ?? '') === 'aluno'; }
function isLogado()  { return isset($_SESSION['usuario_id']); }

/**
 * Exige um perfil específico; redireciona para o login caso contrário.
 * Ex.: exigirPerfil('admin', '../login.php');
 */
function exigirPerfil($tipo, $redirect = '../login.php') {
    if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== $tipo) {
        header('Location: ' . $redirect);
        exit();
    }
}

/* ── Tema claro/escuro ───────────────────────────────────────────────────
   A preferência mora em localStorage (chave 'sporthub-tema') e é espelhada
   num cookie de mesmo nome, para que o PHP já entregue o <html> com o tema
   certo — sem o "flash" branco antes do JavaScript rodar.
   Não é dado pessoal: é preferência de exibição, então não entra no
   inventário da LGPD nem depende do banner de consentimento.             */
const SH_COOKIE_TEMA = 'sporthub-tema';

/** 'dark', 'light' ou '' (sem escolha salva: segue o sistema operacional). */
function sh_tema() {
    $t = $_COOKIE[SH_COOKIE_TEMA] ?? '';
    return in_array($t, ['dark', 'light'], true) ? $t : '';
}

/** Atributo pronto para a tag <html>. */
function sh_tema_attr() {
    $t = sh_tema();
    return $t !== '' ? ' data-theme="' . $t . '"' : '';
}

/**
 * Script que aplica o tema antes da primeira pintura da página.
 * Vai no <head>, antes do CSS, em todas as páginas.
 */
function sh_tema_boot() {
    return '<script>(function(){try{'
         . 'var k="' . SH_COOKIE_TEMA . '",t=null;'
         . 'try{t=localStorage.getItem(k);}catch(e){}'
         . 'if(t!=="dark"&&t!=="light"){var m=document.cookie.match(/(?:^|; )sporthub-tema=(dark|light)/);t=m?m[1]:null;}'
         . 'if(t!=="dark"&&t!=="light"){t=(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches)?"dark":"light";}'
         . 'document.documentElement.setAttribute("data-theme",t);'
         . 'document.documentElement.classList.add("js");'
         . 'document.cookie=k+"="+t+";path=/;max-age=31536000;samesite=Lax";'
         . '}catch(e){}})();</script>';
}

/** Botão redondo de alternância de tema (site público e tela de login). */
function sh_tema_botao($classe = '') {
    return '<button type="button" class="' . e(trim('theme-toggle ' . $classe)) . '" data-theme-toggle'
         . ' aria-label="Alternar entre tema claro e escuro" title="Alternar tema claro/escuro">'
         . '<i class="fas fa-sun" aria-hidden="true"></i>'
         . '<i class="fas fa-moon" aria-hidden="true"></i>'
         . '<span class="sr-only">Alternar tema</span></button>';
}

/* ── CSRF ────────────────────────────────────────────────────────────────── */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/** Campo <input type="hidden"> pronto com o token CSRF. */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/* ── Helpers de saída ────────────────────────────────────────────────────── */
/** Escapa texto para HTML. Use SEMPRE ao imprimir dados vindos do usuário. */
function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/** Formata um valor em reais: 1188 -> "1.188,00" */
function sh_money($valor) {
    return number_format((float)$valor, 2, ',', '.');
}

/** Caminho web da raiz do projeto (ex.: "/sporthub_tcc02"). */
function sh_web_root() {
    static $root = null;
    if ($root !== null) return $root;

    $doc_root     = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $project_root = str_replace('\\', '/', (string)realpath(dirname(__DIR__)));
    $root = ($doc_root !== '' && strpos($project_root, $doc_root) === 0)
        ? substr($project_root, strlen($doc_root))
        : '';
    return $root = rtrim($root, '/');
}

/** Monta uma URL absoluta a partir da raiz do projeto. */
function sh_url($caminho = '') {
    return sh_web_root() . '/' . ltrim($caminho, '/');
}

/** URL do asset com cache-busting pelo mtime do arquivo. */
function sh_asset($caminho) {
    $absoluto = dirname(__DIR__) . '/' . ltrim($caminho, '/');
    $versao   = file_exists($absoluto) ? filemtime($absoluto) : time();
    return sh_url($caminho) . '?v=' . $versao;
}

/* ── Utilidades de dados ─────────────────────────────────────────────────── */
/** IP do visitante (usado nos registros de aceite exigidos pela LGPD). */
function sh_ip() {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Verifica se uma tabela existe — permite degradar sem erro fatal. */
function sh_tabela_existe(PDO $pdo, $tabela) {
    static $cache = [];
    if (isset($cache[$tabela])) return $cache[$tabela];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables
                               WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$tabela]);
        return $cache[$tabela] = ((int)$stmt->fetchColumn() > 0);
    } catch (PDOException $e) {
        return $cache[$tabela] = false;
    }
}

/**
 * Gera um protocolo sequencial legível: PREFIXO-ANO-0001.
 * Ex.: sh_protocolo($pdo, 'arbitro_solicitacoes', 'protocolo', 'ARB')
 */
function sh_protocolo(PDO $pdo, $tabela, $coluna, $prefixo) {
    $ano = date('Y');
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$tabela`");
        $seq  = ((int)$stmt->fetchColumn()) + 1;
    } catch (PDOException $e) {
        $seq = 1;
    }
    return sprintf('%s-%s-%04d', $prefixo, $ano, $seq);
}

/** Registra uma ação sensível na trilha de auditoria. */
function sh_auditar(PDO $pdo, $acao, $entidade = null, $entidade_id = null, $detalhe = null) {
    if (!sh_tabela_existe($pdo, 'auditoria')) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, acao, entidade, entidade_id, detalhe, ip)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['usuario_id'] ?? null,
            $acao, $entidade, $entidade_id,
            $detalhe !== null ? mb_substr($detalhe, 0, 255) : null,
            sh_ip(),
        ]);
    } catch (PDOException $e) {
        error_log('Falha ao auditar: ' . $e->getMessage());
    }
}

/**
 * Tentativas de login malsucedidas vindas deste IP nos últimos minutos.
 * O bloqueio guardado só na sessão é fraco — basta apagar os cookies para
 * zerar o contador. A trilha de auditoria não tem esse problema.
 */
function sh_falhas_login_ip(PDO $pdo, $minutos = 15) {
    if (!sh_tabela_existe($pdo, 'auditoria')) return 0;
    $minutos = max(1, (int)$minutos);   // valor interno, nunca vem do usuário
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM auditoria
             WHERE acao = 'login_falha' AND ip = ?
               AND created_at > DATE_SUB(NOW(), INTERVAL $minutos MINUTE)"
        );
        $stmt->execute([sh_ip()]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Falha ao contar tentativas de login: ' . $e->getMessage());
        return 0;
    }
}

/** Exige um token CSRF válido; encerra a requisição se não houver. */
function exigir_csrf($token = null, $redirect = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    if (verify_csrf_token($token)) return true;

    if ($redirect !== null) {
        header('Location: ' . $redirect);
        exit();
    }
    http_response_code(403);
    exit('Requisição inválida (token de segurança ausente ou expirado). Volte e tente novamente.');
}

/** Registra um consentimento LGPD (finalidade + versão do texto aceito). */
function sh_registrar_consentimento(PDO $pdo, $finalidade, $identificador = null, $concedido = true) {
    if (!sh_tabela_existe($pdo, 'lgpd_consentimentos')) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO lgpd_consentimentos
            (usuario_id, identificador, finalidade, concedido, versao_texto, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['usuario_id'] ?? null,
            $identificador,
            $finalidade,
            $concedido ? 1 : 0,
            SH_VERSAO_POLITICA,
            sh_ip(),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (PDOException $e) {
        error_log('Falha ao registrar consentimento: ' . $e->getMessage());
    }
}

/** Mascara um CPF para exibição: 123.456.789-00 -> ***.456.789-** */
function sh_mascarar_cpf($cpf) {
    $d = preg_replace('/\D/', '', (string)$cpf);
    if (strlen($d) !== 11) return '***';
    return '***.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-**';
}

/** Validação de CPF (dígitos verificadores). */
function sh_cpf_valido($cpf) {
    $c = preg_replace('/\D/', '', (string)$cpf);
    if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) $soma += (int)$c[$i] * (($t + 1) - $i);
        $digito = ((10 * $soma) % 11) % 10;
        if ((int)$c[$t] !== $digito) return false;
    }
    return true;
}

/** Planos ativos, com fallback caso a migration v2 ainda não tenha rodado. */
function sh_planos(PDO $pdo) {
    if (sh_tabela_existe($pdo, 'planos')) {
        try {
            $rows = $pdo->query("SELECT * FROM planos WHERE ativo = 1 ORDER BY ordem, preco_anual")->fetchAll();
            if ($rows) return $rows;
        } catch (PDOException $e) {
            error_log('Falha ao carregar planos: ' . $e->getMessage());
        }
    }
    return [
        ['id' => 0, 'slug' => 'essencial', 'nome' => 'Essencial',
         'descricao' => 'Para a escola que quer tirar o interclasse do papel e do grupo de WhatsApp.',
         'preco_anual' => 1188.00, 'preco_mensal_equivalente' => 99.00,
         'limite_times' => 12, 'limite_modalidades' => 3, 'limite_arbitros' => 5, 'destaque' => 0],
        ['id' => 0, 'slug' => 'pro', 'nome' => 'Pro',
         'descricao' => 'Para quem organiza um campeonato inteiro por ano, com várias modalidades.',
         'preco_anual' => 2388.00, 'preco_mensal_equivalente' => 199.00,
         'limite_times' => 40, 'limite_modalidades' => null, 'limite_arbitros' => 20, 'destaque' => 1],
        ['id' => 0, 'slug' => 'institucional', 'nome' => 'Institucional',
         'descricao' => 'Para redes de ensino e múltiplas unidades, com suporte dedicado.',
         'preco_anual' => 4788.00, 'preco_mensal_equivalente' => 399.00,
         'limite_times' => null, 'limite_modalidades' => null, 'limite_arbitros' => null, 'destaque' => 0],
    ];
}

/** Modalidades oferecidas no credenciamento de árbitros. */
function sh_modalidades_arbitragem() {
    return ['Futsal', 'Futebol de campo', 'Vôlei', 'Basquete', 'Handebol',
            'Queimada', 'Tênis de mesa', 'Xadrez', 'Atletismo', 'Natação'];
}
