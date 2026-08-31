<?php
// includes/config.php — configuração central, conexão e helpers compartilhados

/* ── Exibição de erros (SH-50) ───────────────────────────────────────────
   Uma mensagem de erro do PHP na tela entrega, de graça, o caminho absoluto
   dos arquivos, o nome das tabelas e às vezes o próprio SQL — material de
   reconhecimento para quem estiver sondando o servidor. Em produção ela some;
   o registro, porém, nunca some: vai inteiro para o error_log.

   A decisão é automática para não depender de alguém lembrar de trocar um
   valor no dia da publicação: só o acesso local (XAMPP, `php -S`, linha de
   comando) é tratado como desenvolvimento. Para forçar um dos modos, defina
   SH_DEBUG antes de incluir este arquivo, ou a variável de ambiente
   SPORTHUB_DEBUG (1 = liga, 0 = desliga).                                 */
if (!defined('SH_DEBUG')) {
    $sh_env = getenv('SPORTHUB_DEBUG');
    if ($sh_env !== false && $sh_env !== '') {
        define('SH_DEBUG', $sh_env === '1' || strtolower($sh_env) === 'true');
    } else {
        $sh_host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        $sh_host = explode(':', $sh_host)[0];
        define('SH_DEBUG', PHP_SAPI === 'cli'
            || in_array($sh_host, ['localhost', '127.0.0.1', '::1', ''], true)
            || substr($sh_host, -6) === '.local'
            || substr($sh_host, -5) === '.test');
    }
    unset($sh_env, $sh_host);
}

error_reporting(E_ALL);
ini_set('display_errors',         SH_DEBUG ? '1' : '0');
ini_set('display_startup_errors', SH_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

/* Se o servidor não definiu um destino, o log vai para logs/php-error.log
   dentro do projeto — pasta bloqueada pelo .htaccess, então o arquivo não é
   servido pela web. Sem isso, num XAMPP recém-instalado os erros somem sem
   deixar rastro nenhum.                                                    */
if (!ini_get('error_log')) {
    $sh_dir_log = dirname(__DIR__) . '/logs';
    if (is_dir($sh_dir_log) && is_writable($sh_dir_log)) {
        ini_set('error_log', $sh_dir_log . '/php-error.log');
    }
    unset($sh_dir_log);
}

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

/* ── Configuração local (SH-44, SH-49, SH-84) ────────────────────────────
   Tudo que muda de uma instalação para outra — senha do banco, segredo do
   feed, servidor de e-mail, dados do controlador — vive em
   `includes/config.local.php`, um arquivo que NÃO acompanha o repositório.
   O modelo comentado está em `includes/config.local.example.php`.

   O arquivo local é lido ANTES dos valores de fábrica abaixo; cada constante
   só recebe o padrão se a configuração local ainda não a tiver definido.
   Assim ninguém precisa editar este arquivo para publicar o sistema — e uma
   atualização do código nunca sobrescreve a senha de produção.            */
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

/** Define a constante apenas se a configuração local não a definiu. */
function sh_padrao($nome, $valor) {
    if (!defined($nome)) define($nome, $valor);
}

/* ── Identidade do produto ───────────────────────────────────────────────── */
sh_padrao('SH_NOME',            'SportHub');
sh_padrao('SH_TAGLINE',         'Interclasse organizado, transparente e digital.');
sh_padrao('SH_EMAIL',           'contato@sporthub.com.br');
sh_padrao('SH_EMAIL_DPO',       'dpo@sporthub.com.br');
sh_padrao('SH_WHATSAPP',        '5519994825860');
sh_padrao('SH_INSTAGRAM',       'https://www.instagram.com/sporthubgg/');
sh_padrao('SH_VERSAO_POLITICA', '1.0');
sh_padrao('SH_POLITICA_DATA',   '19 de agosto de 2026');

/* ── Controlador e encarregado (SH-44) ───────────────────────────────────
   A LGPD (art. 9º, I e art. 41, §1º) exige que o titular saiba QUEM trata
   seus dados e a quem reclamar — com identificação e contato reais, não com
   um endereço genérico. Enquanto a escola não preencher esses valores em
   `config.local.php`, `sh_controlador_pendente()` devolve true e o painel da
   coordenação mostra o aviso; os documentos legais exibem a marcação de
   pendência em vez de um dado inventado.                                  */
sh_padrao('SH_CONTROLADOR_NOME',     'SportHub Tecnologia Educacional (razão social a preencher)');
sh_padrao('SH_CONTROLADOR_CNPJ',     '00.000.000/0001-00');
sh_padrao('SH_CONTROLADOR_ENDERECO', 'Endereço do controlador a preencher');
sh_padrao('SH_CONTROLADOR_CIDADE',   'Cidade/UF a preencher');
sh_padrao('SH_DPO_NOME',             'Encarregado a designar');
sh_padrao('SH_DPO_TELEFONE',         '');

/** Os dados do controlador ainda são os de fábrica? */
function sh_controlador_pendente() {
    return SH_CONTROLADOR_CNPJ === '00.000.000/0001-00'
        || strpos(SH_CONTROLADOR_NOME, 'a preencher') !== false
        || strpos(SH_DPO_NOME, 'a designar') !== false;
}

/* ── Banco de dados (SH-49) ──────────────────────────────────────────────
   O padrão do XAMPP (`root` sem senha) continua valendo para quem só quer
   abrir o projeto na máquina. Em produção, `config.local.php` aponta para um
   usuário dedicado, sem privilégio administrativo — o script que o cria está
   em `scripts/usuario_banco.sql`.                                          */
sh_padrao('SH_DB_HOST',    'localhost');
sh_padrao('SH_DB_NOME',    'olimpiasp');
sh_padrao('SH_DB_USUARIO', 'root');
sh_padrao('SH_DB_SENHA',   '');

try {
    $pdo = new PDO(
        'mysql:host=' . SH_DB_HOST . ';dbname=' . SH_DB_NOME . ';charset=utf8mb4',
        SH_DB_USUARIO,
        SH_DB_SENHA,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // A mensagem original vai só para o log: exibi-la revelaria host, usuário
    // e estrutura do banco para quem estivesse sondando o servidor.
    sh_log_excecao($e, 'conexão com o banco');

    /* Na linha de comando o banco fora do ar não deve matar o processo:
       o executor de testes (SH-63), o verificador de backup e os scripts de
       manutenção precisam rodar sem MySQL ligado. Quem realmente depende da
       conexão confere `$pdo === null`. Pela web, nada muda: 503 e fim. */
    if (PHP_SAPI === 'cli') {
        $pdo = null;
        fwrite(STDERR, "[SportHub] aviso: sem conexão com o banco (\$pdo = null).\n");
    } else {
        http_response_code(503);
        die('Não foi possível conectar ao banco de dados. Tente novamente em instantes.');
    }
}

/* ══ Nonce da CSP (SH-37) ════════════════════════════════════════════════
   Enquanto a política dizia `script-src 'self' 'unsafe-inline'`, o navegador
   executava QUALQUER <script> que aparecesse no HTML — inclusive um injetado
   por XSS. É o buraco que anula boa parte do valor de ter CSP.

   A saída é o nonce: um valor aleatório, novo a cada resposta, que vai no
   cabeçalho e no atributo de cada bloco embutido que nós mesmos escrevemos.
   O atacante não tem como adivinhá-lo — ele injeta o HTML antes de o nonce
   existir — então o script dele não roda.

   Duas consequências que valem registrar:

   · Ao declarar um nonce, o navegador passa a IGNORAR 'unsafe-inline'. Por
     isso ele foi removido: quem entende nonce obedece ao nonce, e quem é
     antigo demais para entender continua caindo em 'self'.
   · Nonce não cobre atributo `onclick=""` nem `style=""` escritos no HTML.
     Os manipuladores viraram listeners em js/sporthub-ui.js e os estilos
     embutidos viraram classes utilitárias em css/u.css — foi o que permitiu
     tirar 'unsafe-inline' também de style-src.                            */
function sh_nonce() {
    static $nonce = null;
    if ($nonce === null) {
        try {
            $nonce = base64_encode(random_bytes(16));
        } catch (Exception $e) {
            $nonce = base64_encode(openssl_random_pseudo_bytes(16));
        }
    }
    return $nonce;
}

/** Atributo pronto para <script> e <style> embutidos: ` nonce="..."`. */
function sh_nonce_attr() {
    return ' nonce="' . htmlspecialchars(sh_nonce(), ENT_QUOTES, 'UTF-8') . '"';
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
       fora desta lista — é o que impede um XSS de virar roubo de sessão. */
    $nonce = "'nonce-" . sh_nonce() . "'";
    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "img-src 'self' data: blob:",
        "script-src 'self' $nonce",
        "style-src 'self' $nonce https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "connect-src 'self'",
        "manifest-src 'self'",
        "worker-src 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));

    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
sh_headers_seguranca();

/* ══ Tratamento padronizado de exceção (SH-39) ═══════════════════════════
   Antes, cada `catch` escrevia sua própria linha no error_log — algumas com
   "Erro ao...", outras com "Falha ao...", nenhuma dizendo em que arquivo,
   em que URL ou para qual usuário aquilo aconteceu. Depurar um relato de
   "deu erro na tela de times" virava caça ao tesouro.

   Agora todo catch chama sh_log_excecao(). O log ganha sempre os mesmos
   campos, e a função devolve uma REFERÊNCIA curta que também aparece na
   mensagem mostrada ao usuário. Quando alguém diz "deu erro, referência
   7F3A21", a linha exata do log é encontrada com um grep.

   O que o usuário lê continua genérico de propósito: mensagem de exceção na
   tela entrega caminho de arquivo, nome de tabela e às vezes o próprio SQL. */

/**
 * Registra uma exceção no error_log em formato uniforme.
 *
 * @param Throwable $e        a exceção capturada
 * @param string    $contexto o que estava sendo feito ("salvar time")
 * @return string   referência curta (6 caracteres) para citar ao usuário
 */
function sh_log_excecao(Throwable $e, $contexto = '') {
    try {
        $ref = strtoupper(bin2hex(random_bytes(3)));
    } catch (Exception $falha) {
        $ref = strtoupper(dechex(mt_rand(0x100000, 0xFFFFFF)));
    }

    $partes = [
        'ref=' . $ref,
        'ctx=' . ($contexto !== '' ? $contexto : 'não informado'),
        get_class($e) . ': ' . $e->getMessage(),
        'em ' . basename($e->getFile()) . ':' . $e->getLine(),
    ];
    if (PHP_SAPI !== 'cli') {
        $partes[] = 'req=' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?');
        $partes[] = 'ip=' . sh_ip();
    }
    $partes[] = 'usuario=' . ($_SESSION['usuario_id'] ?? '-');

    error_log('[SportHub] ' . implode(' | ', $partes));
    return $ref;
}

/**
 * Registra a exceção e devolve a frase que vai para a tela.
 * Use nos catch que precisam avisar o usuário:
 *
 *     } catch (PDOException $e) {
 *         $erro = sh_erro_usuario($e, 'salvar time');
 *     }
 */
function sh_erro_usuario(Throwable $e, $contexto = '', $acao = null) {
    $ref   = sh_log_excecao($e, $contexto);
    $acao  = $acao ?? ($contexto !== '' ? $contexto : 'concluir a operação');
    return 'Não foi possível ' . $acao . '. Tente novamente; se o erro continuar, '
         . 'informe a referência ' . $ref . ' à coordenação.';
}

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
    return '<script' . sh_nonce_attr() . '>(function(){try{'
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

/**
 * URL completa (esquema + host + caminho).
 * Necessária onde o endereço vai sair do site: assinatura de calendário,
 * link copiado para outro aplicativo, e-mail.
 */
function sh_url_absoluta($caminho = '') {
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
          || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host . sh_url($caminho);
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
        /* SH-39: voltar para 1 sem avisar significa reemitir o ARB-2026-0001
           que já existe. O número continua saindo para não travar o
           atendimento, mas a falha fica registrada. */
        sh_log_excecao($e, 'contar registros para o protocolo ' . $prefixo);
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
        sh_log_excecao($e, 'auditar');
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
        sh_log_excecao($e, 'contar tentativas de login');
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
        sh_log_excecao($e, 'registrar consentimento');
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
            sh_log_excecao($e, 'carregar planos');
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

/* ══ Limites do plano contratado (SH-57) ═════════════════════════════════
   Os planos já traziam limite_times, limite_modalidades e limite_arbitros na
   tabela `planos`, mas nada era verificado: a escola do plano Essencial podia
   cadastrar 200 times. As funções abaixo fecham essa lacuna.

   Duas decisões deliberadas:

   · NULL significa ilimitado (é assim que a tabela já modelava o plano
     Institucional) — nunca zero.
   · Sem assinatura vigente, não há limite. Uma instalação recém-montada, ou o
     ambiente de demonstração do TCC, precisa funcionar sem que alguém tenha
     de contratar um plano antes de cadastrar o primeiro time.

   O limite trava a CRIAÇÃO, nunca apaga nem esconde o que já existe: se a
   escola rebaixar o plano, os times excedentes continuam lá e visíveis — só
   não é possível criar mais até que a contagem volte ao teto.            */

/** Assinatura em vigor (ativa tem prioridade sobre trial), ou null. */
function sh_assinatura_vigente(PDO $pdo) {
    static $cache = false;                 // false = ainda não consultado
    if ($cache !== false) return $cache;

    if (!sh_tabela_existe($pdo, 'assinaturas') || !sh_tabela_existe($pdo, 'planos')) {
        return $cache = null;
    }
    try {
        $stmt = $pdo->query(
            "SELECT a.id, a.codigo, a.status, a.expira_em,
                    p.nome AS plano_nome, p.slug AS plano_slug,
                    p.limite_times, p.limite_modalidades, p.limite_arbitros
               FROM assinaturas a
               JOIN planos p ON p.id = a.plano_id
              WHERE a.status IN ('trial', 'ativa')
              ORDER BY FIELD(a.status, 'ativa', 'trial'), a.created_at DESC
              LIMIT 1"
        );
        return $cache = ($stmt->fetch() ?: null);
    } catch (PDOException $e) {
        sh_log_excecao($e, 'carregar a assinatura vigente');
        return $cache = null;
    }
}

/**
 * Situação de um recurso limitado pelo plano.
 *
 * @param string $recurso  'times', 'modalidades' ou 'arbitros'
 * @return array{limite:?int, usados:int, restantes:?int, plano:?string,
 *               ilimitado:bool, atingido:bool, alerta:bool}
 */
function sh_limite_plano(PDO $pdo, $recurso) {
    $contagens = [
        'times'       => "SELECT COUNT(*) FROM times",
        'modalidades' => "SELECT COUNT(*) FROM modalidades",
        'arbitros'    => "SELECT COUNT(*) FROM usuarios WHERE tipo = 'arbitro' AND status <> 'anonimizado'",
    ];

    $vazio = ['limite' => null, 'usados' => 0, 'restantes' => null, 'plano' => null,
              'ilimitado' => true, 'atingido' => false, 'alerta' => false];

    if (!isset($contagens[$recurso])) return $vazio;

    try {
        $usados = (int)$pdo->query($contagens[$recurso])->fetchColumn();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'contar ' . $recurso);
        return $vazio;
    }

    $assinatura = sh_assinatura_vigente($pdo);
    $limite = $assinatura ? $assinatura['limite_' . $recurso] : null;

    if ($assinatura === null || $limite === null || $limite === '') {
        $vazio['usados'] = $usados;
        $vazio['plano']  = $assinatura['plano_nome'] ?? null;
        return $vazio;
    }

    $limite    = (int)$limite;
    $restantes = max(0, $limite - $usados);

    return [
        'limite'    => $limite,
        'usados'    => $usados,
        'restantes' => $restantes,
        'plano'     => $assinatura['plano_nome'],
        'ilimitado' => false,
        'atingido'  => $usados >= $limite,
        // "Falta pouco": um a dois lugares, ou os últimos 15% do teto.
        'alerta'    => $restantes > 0 && ($restantes <= 2 || $restantes <= (int)ceil($limite * 0.15)),
    ];
}

/** Atalho: ainda cabe mais um deste recurso? */
function sh_pode_criar(PDO $pdo, $recurso) {
    $s = sh_limite_plano($pdo, $recurso);
    return $s['ilimitado'] || !$s['atingido'];
}

/** Frase pronta para o alerta na tela, ou '' quando não há o que avisar. */
function sh_aviso_limite(PDO $pdo, $recurso, $rotulo) {
    $s = sh_limite_plano($pdo, $recurso);
    if ($s['ilimitado']) return '';

    if ($s['atingido']) {
        return sprintf(
            'Limite do plano %s atingido: %d de %d %s. Para cadastrar mais, '
          . 'mude de plano — nada do que já existe é removido.',
            $s['plano'], $s['usados'], $s['limite'], $rotulo
        );
    }
    if ($s['alerta']) {
        return sprintf(
            'Plano %s: %d de %d %s em uso. Resta%s %d.',
            $s['plano'], $s['usados'], $s['limite'], $rotulo,
            $s['restantes'] === 1 ? '' : 'm', $s['restantes']
        );
    }
    return '';
}

/* ══ Feeds públicos por token (SH-70) ════════════════════════════════════
   O calendário .ics precisa ser lido por Google Agenda, Outlook e pelo app de
   calendário do celular — nenhum deles faz login. A alternativa a "URL aberta"
   é uma URL com token opaco: quem tem o link lê, quem não tem recebe 403, e o
   link pode ser invalidado de uma vez trocando o segredo.

   Não é autenticação, e não pretende ser: o conteúdo do feed é o calendário
   público do interclasse (times, horário e local), o mesmo que já aparece no
   mural da escola. Nenhum dado pessoal de aluno entra aqui.

   ── De onde vem o segredo (SH-84) ────────────────────────────────────────
   O valor de fábrica ficava escrito aqui, no repositório: qualquer pessoa que
   lesse este arquivo conseguia montar a URL do calendário de qualquer
   instalação. Depender de alguém lembrar de trocá-lo no dia da publicação é
   o mesmo que não ter segredo.

   Agora a origem é procurada nesta ordem:

   1. `SH_SEGREDO_FEED` definido em `includes/config.local.php` (produção);
   2. variável de ambiente `SPORTHUB_SEGREDO_FEED`;
   3. `logs/segredo_feed.txt`, gerado sozinho com 32 bytes aleatórios na
      primeira execução — pasta bloqueada pelo .htaccess, fora do alcance da
      web. É o caminho normal do XAMPP: ninguém precisa configurar nada e,
      ainda assim, cada instalação nasce com um segredo diferente;
   4. só se nada acima funcionar (pasta sem permissão de escrita), um valor
      derivado do caminho absoluto do projeto — imprevisível para quem está
      fora do servidor, mas registrado como pendência no painel.

   Apagar o arquivo do item 3 invalida todos os endereços de uma vez, que é
   exatamente o botão de pânico que se espera aqui.                        */
function sh_segredo_feed() {
    static $segredo = null;
    if ($segredo !== null) return $segredo;

    if (defined('SH_SEGREDO_FEED') && SH_SEGREDO_FEED !== '') {
        return $segredo = SH_SEGREDO_FEED;
    }

    $ambiente = getenv('SPORTHUB_SEGREDO_FEED');
    if ($ambiente !== false && $ambiente !== '') {
        return $segredo = $ambiente;
    }

    $arquivo = dirname(__DIR__) . '/logs/segredo_feed.txt';
    if (is_file($arquivo)) {
        $lido = trim((string)@file_get_contents($arquivo));
        if (strlen($lido) >= 32) return $segredo = $lido;
    }

    $dir = dirname($arquivo);
    if (is_dir($dir) && is_writable($dir)) {
        try {
            $novo = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $novo = bin2hex(openssl_random_pseudo_bytes(32));
        }
        if (@file_put_contents($arquivo, $novo, LOCK_EX) !== false) {
            @chmod($arquivo, 0600);
            return $segredo = $novo;
        }
    }

    // Último recurso: previsível apenas para quem já está dentro do servidor.
    return $segredo = hash('sha256', 'sporthub-feed|' . __DIR__ . '|' . PHP_VERSION);
}

/** O segredo do feed ainda é o de último recurso (não persistido)? */
function sh_segredo_feed_pendente() {
    if (defined('SH_SEGREDO_FEED') && SH_SEGREDO_FEED !== '') return false;
    $ambiente = getenv('SPORTHUB_SEGREDO_FEED');
    if ($ambiente !== false && $ambiente !== '') return false;
    return !is_file(dirname(__DIR__) . '/logs/segredo_feed.txt');
}

/** Token opaco de 32 caracteres para um escopo de feed (ex.: 'agenda:3'). */
function sh_feed_token($escopo) {
    return substr(hash_hmac('sha256', 'feed:' . $escopo, sh_segredo_feed()), 0, 32);
}

/** Comparação em tempo constante — evita descobrir o token por tentativa. */
function sh_feed_token_valido($escopo, $token) {
    return hash_equals(sh_feed_token($escopo), (string)$token);
}

/* ══ Isolamento por escola (SH-68) ═══════════════════════════════════════
   A tabela `escolas` existia desde a v2 e a assinatura já apontava para ela,
   mas nenhuma consulta filtrava por instituição: duas escolas no mesmo banco
   enxergariam os times uma da outra. Vender para a segunda escola nesse
   estado seria vazamento de dado por construção.

   O desenho aqui é deliberadamente conservador, porque a esmagadora maioria
   das instalações tem UMA escola e não deve pagar nenhum preço por isso:

   · Com zero ou uma escola cadastrada, sh_multi_escola() devolve false e os
     filtros somem — as consultas ficam exatamente como eram.
   · Com duas ou mais, cada consulta passa a exigir `escola_id`, e registro
     antigo com escola_id NULL continua visível para todos (é o legado, e
     escondê-lo quebraria o histórico de quem migrou).
   · O admin vê a escola vinculada à sua conta (`usuarios.escola_id`); quando
     a conta não tem vínculo, ele vê tudo — é o administrador da rede.       */

/** Há mais de uma escola cadastrada nesta instalação? */
function sh_multi_escola(PDO $pdo) {
    static $multi = null;
    if ($multi !== null) return $multi;
    if (!sh_tabela_existe($pdo, 'escolas')) return $multi = false;
    try {
        return $multi = ((int)$pdo->query('SELECT COUNT(*) FROM escolas')->fetchColumn() > 1);
    } catch (PDOException $e) {
        sh_log_excecao($e, 'contar escolas');
        return $multi = false;
    }
}

/**
 * Escola do usuário da sessão, ou null quando ele enxerga tudo.
 * Null também é o valor normal numa instalação de escola única.
 */
function sh_escola_atual(PDO $pdo) {
    static $escola = false;
    if ($escola !== false) return $escola;

    if (!sh_multi_escola($pdo) || !isset($_SESSION['usuario_id'])) {
        return $escola = null;
    }
    if (array_key_exists('escola_id', $_SESSION)) {
        return $escola = $_SESSION['escola_id'];
    }
    try {
        $stmt = $pdo->prepare('SELECT escola_id FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['usuario_id']]);
        $id = $stmt->fetchColumn();
        $_SESSION['escola_id'] = ($id !== false && $id !== null) ? (int)$id : null;
        return $escola = $_SESSION['escola_id'];
    } catch (PDOException $e) {
        sh_log_excecao($e, 'identificar a escola do usuário');
        return $escola = null;
    }
}

/**
 * Trecho de SQL que restringe uma consulta à escola atual.
 *
 *     $f = sh_filtro_escola($pdo, 't');
 *     $sql = "SELECT ... FROM times t WHERE 1 = 1 {$f['sql']}";
 *     $stmt->execute(array_merge($outros, $f['params']));
 *
 * Devolve sql vazio quando não há isolamento a aplicar, de modo que a
 * consulta de quem tem uma escola só continua idêntica à de antes.
 *
 * @return array{sql:string, params:array}
 */
function sh_filtro_escola(PDO $pdo, $alias = '') {
    $escola = sh_escola_atual($pdo);
    if ($escola === null) return ['sql' => '', 'params' => []];

    $col = ($alias !== '' ? $alias . '.' : '') . 'escola_id';
    // O IS NULL preserva o que foi cadastrado antes da v3.
    return ['sql' => " AND ($col = ? OR $col IS NULL)", 'params' => [(int)$escola]];
}

/** Valor de escola_id a gravar num INSERT feito pelo usuário atual. */
function sh_escola_para_insert(PDO $pdo) {
    return sh_escola_atual($pdo);
}

/* ══ Política de senha e troca obrigatória (SH-48) ═══════════════════════
   O seed trazia "admin1234" em texto puro na coluna `password`, e o login
   aceitava a comparação direta com esse texto — para sempre, não só na
   primeira vez. Quem lesse o repositório (o arquivo bd.sql está nele)
   entrava como coordenação em qualquer instalação que nunca tivesse trocado
   a senha. E não havia nada obrigando a trocar.

   Três peças fecham isso:

   1. `bd.sql` grava hash bcrypt, não texto puro, e o login perdeu o desvio
      que comparava texto (veja login.php).
   2. Toda conta de fábrica nasce com `senha_provisoria = 1`.
   3. `sh_guardar_senha_provisoria()`, abaixo, roda em toda requisição
      autenticada e não deixa nenhuma tela abrir enquanto a troca não
      acontecer. Não é um aviso que dá para fechar: é um desvio.

   A regra de força é modesta de propósito — 8 caracteres, com letra e
   número. Exigir símbolo e maiúscula leva professor a escrever a senha num
   post-it colado no monitor da sala dos professores, o que é pior do que
   uma senha de 8 dígitos que ele lembra.                                   */

const SH_SENHA_MINIMA = 8;

/* As senhas que acompanham o seed e estão publicadas no repositório. Ficam
   numa constante porque três lugares precisam da mesma lista: a política de
   senha (recusa escolhê-las), o login (detecta quem ainda usa uma) e o
   checklist de produção (audita o banco antes de publicar). */
const SH_SENHAS_FABRICA = ['admin1234', 'arbitro1234', 'professor1234', 'aluno1234'];

/**
 * A senha digitada é uma das publicadas no repositório?
 *
 * Existe porque a migration só marcava `senha_provisoria` nas contas que
 * ainda guardavam a senha em texto puro. Numa instalação que já tinha bcrypt
 * — o caso normal — nenhuma conta era marcada, e o painel informava que não
 * havia senha de fábrica enquanto `admin`/`admin1234` continuava entrando.
 * Como o bcrypt tem sal, não dá para reconhecer a senha pelo hash: ou se tem
 * a senha em mãos (o login tem), ou se verifica uma a uma (o checklist faz,
 * porque lá o custo não importa).
 */
function sh_senha_de_fabrica($senha) {
    return in_array((string)$senha, SH_SENHAS_FABRICA, true);
}

/**
 * Contas cujo hash confere com alguma senha de fábrica.
 *
 * Custa um bcrypt por par conta/senha — tranquilo num script de linha de
 * comando, caro demais para uma página. Não use em tela.
 *
 * @return array<int, array{id:int, username:string, senha:string}>
 */
function sh_contas_senha_fabrica(PDO $pdo) {
    $achadas = [];
    try {
        $stmt = $pdo->query('SELECT id, username, password FROM usuarios');
        foreach ($stmt->fetchAll() as $conta) {
            foreach (SH_SENHAS_FABRICA as $senha) {
                if (password_verify($senha, (string)$conta['password'])) {
                    $achadas[] = [
                        'id'       => (int)$conta['id'],
                        'username' => (string)$conta['username'],
                        'senha'    => $senha,
                    ];
                    break;
                }
            }
        }
    } catch (PDOException $e) {
        sh_log_excecao($e, 'auditar senhas de fábrica');
    }
    return $achadas;
}

/**
 * Valida a força de uma senha nova.
 * @return string mensagem de erro, ou '' quando a senha serve.
 */
function sh_senha_politica($senha, $usuario = '') {
    $senha = (string)$senha;

    if (mb_strlen($senha) < SH_SENHA_MINIMA) {
        return 'A senha precisa ter pelo menos ' . SH_SENHA_MINIMA . ' caracteres.';
    }
    if (mb_strlen($senha) > 200) {
        return 'A senha é longa demais (máximo 200 caracteres).';
    }
    if (!preg_match('/\p{L}/u', $senha) || !preg_match('/\d/', $senha)) {
        return 'A senha precisa misturar pelo menos uma letra e um número.';
    }
    if ($usuario !== '' && mb_stripos($senha, $usuario) !== false) {
        return 'A senha não pode conter o nome de usuário.';
    }

    /* As senhas de fábrica e as campeãs de vazamento ficam de fora
       explicitamente: são as primeiras que qualquer ataque tenta. */
    $proibidas = array_merge(SH_SENHAS_FABRICA,
                 ['senha1234', '12345678', '123456789', 'password',
                  'sporthub1', 'interclasse1']);
    if (in_array(mb_strtolower($senha), $proibidas, true)) {
        return 'Esta senha é conhecida demais. Escolha outra.';
    }
    return '';
}

/**
 * Grava a senha nova e encerra o estado provisório.
 * @return bool
 */
function sh_definir_senha(PDO $pdo, $usuario_id, $senha) {
    try {
        $pdo->prepare(
            'UPDATE usuarios
                SET password = ?, senha_provisoria = 0, senha_alterada_em = NOW()
              WHERE id = ?'
        )->execute([password_hash($senha, PASSWORD_DEFAULT), (int)$usuario_id]);

        unset($_SESSION['senha_provisoria']);
        sh_auditar($pdo, 'senha_alterada', 'usuarios', (int)$usuario_id);
        return true;
    } catch (PDOException $e) {
        /* Instalação anterior à migration v3: as colunas novas não existem.
           Ainda assim a senha precisa ser gravada. */
        try {
            $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
                ->execute([password_hash($senha, PASSWORD_DEFAULT), (int)$usuario_id]);
            unset($_SESSION['senha_provisoria']);
            return true;
        } catch (PDOException $e2) {
            sh_log_excecao($e2, 'gravar a senha nova');
            return false;
        }
    }
}

/**
 * Desvia para a troca de senha enquanto a conta estiver com senha de fábrica.
 * Chamada uma vez, logo abaixo — vale para toda página que inclui o config.
 */
function sh_guardar_senha_provisoria() {
    if (PHP_SAPI === 'cli' || empty($_SESSION['senha_provisoria'])) return;

    $atual = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    $livres = ['trocar_senha.php', 'logout.php', 'login.php'];
    if (in_array($atual, $livres, true)) return;

    // Requisição de dados (AJAX) responde 403 em vez de devolver HTML de redirecionamento.
    if (strpos($atual, 'ajax_') === 0 || strpos($atual, 'api') === 0) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['erro' => 'Troque a senha provisória antes de continuar.']));
    }

    header('Location: ' . sh_url('trocar_senha.php'));
    exit();
}
sh_guardar_senha_provisoria();

/* ══ Aplicativo instalável — PWA (SH-69) ═════════════════════════════════
   O manifesto faz o navegador oferecer "Adicionar à tela de início"; o
   service worker guarda CSS, JS e ícones para a segunda visita abrir rápido
   e mostra uma página decente quando a rede cai.

   O registro é condicionado a HTTPS (ou localhost) porque service worker não
   roda em http:// — não é escolha nossa, é regra do navegador. Enquanto o
   sistema estiver publicado em http, estas duas linhas simplesmente não têm
   efeito, e nada quebra. É mais um motivo para o SH-43.                    */
function sh_tags_pwa() {
    return '<link rel="manifest" href="' . e(sh_url('manifest.webmanifest')) . '">'
         . '<link rel="apple-touch-icon" href="' . e(sh_asset('img/Logo.png')) . '">'
         . '<meta name="apple-mobile-web-app-capable" content="yes">'
         . '<meta name="apple-mobile-web-app-title" content="SportHub">';
}

/** Script de registro do service worker, já com o nonce da CSP. */
function sh_registro_service_worker() {
    return '<script' . sh_nonce_attr() . '>'
         . 'if("serviceWorker" in navigator&&(location.protocol==="https:"'
         . '||location.hostname==="localhost"||location.hostname==="127.0.0.1")){'
         . 'window.addEventListener("load",function(){'
         . 'navigator.serviceWorker.register(' . json_encode(sh_url('sw.js'), JSON_UNESCAPED_SLASHES) . ','
         . '{scope:' . json_encode(sh_url(''), JSON_UNESCAPED_SLASHES) . '})'
         . '.catch(function(e){if(window.console)console.warn("SportHub: PWA indisponível —",e);});'
         . '});}'
         . '</script>';
}
