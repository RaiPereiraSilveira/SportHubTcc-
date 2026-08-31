<?php
/**
 * includes/totp.php — segundo fator por aplicativo autenticador (SH-65)
 *
 * A conta de coordenação controla o campeonato inteiro e enxerga dado pessoal
 * de aluno e de árbitro: CPF, documento de credenciamento, requisição de
 * titular. Uma senha só, por melhor que seja, é um segredo que já vazou em
 * outro site metade das vezes.
 *
 * TOTP (RFC 6238) é o padrão que Google Authenticator, Authy, Microsoft
 * Authenticator e o gerenciador de senhas do celular já falam. O servidor e o
 * aplicativo compartilham um segredo; os dois calculam, do relógio, o mesmo
 * código de seis dígitos a cada 30 segundos. Nada trafega, nada depende de
 * SMS, e não há custo nem cadastro em serviço de terceiro.
 *
 * Implementado à mão, em ~120 linhas, porque o algoritmo é um HMAC-SHA1 com
 * truncagem — trazer uma biblioteca inteira para isso seria desproporcional.
 *
 * Decisões que valem registrar:
 *
 * · Janela de tolerância de ±1 intervalo (30 s antes, 30 s depois). Relógio
 *   de celular atrasado é a causa nº 1 de "meu código não funciona"; aceitar
 *   três intervalos em vez de um resolve isso sem abrir a porta de verdade.
 * · Códigos de recuperação de uso único, entregues UMA vez na ativação e
 *   guardados só como hash. Sem eles, perder o celular significa perder a
 *   conta que administra o campeonato — e não há "esqueci meu segundo fator"
 *   possível numa instalação de escola.
 * · O QR Code é desenhado no navegador a partir da URI otpauth://; o segredo
 *   em texto também aparece, para quem digita à mão.
 */

require_once __DIR__ . '/config.php';

const SH_TOTP_INTERVALO   = 30;   // segundos por código (padrão do RFC)
const SH_TOTP_DIGITOS     = 6;
const SH_TOTP_TOLERANCIA  = 1;    // intervalos aceitos para trás e para frente
const SH_TOTP_RECUPERACAO = 8;    // quantos códigos de recuperação gerar

const SH_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Segredo novo, em Base32 (o alfabeto que os autenticadores leem). */
function sh_totp_segredo($bytes = 20) {
    $bruto  = random_bytes($bytes);
    $bits   = '';
    for ($i = 0; $i < strlen($bruto); $i++) {
        $bits .= str_pad(decbin(ord($bruto[$i])), 8, '0', STR_PAD_LEFT);
    }
    $segredo = '';
    foreach (str_split($bits, 5) as $pedaco) {
        $pedaco = str_pad($pedaco, 5, '0', STR_PAD_RIGHT);
        $segredo .= SH_BASE32[bindec($pedaco)];
    }
    return $segredo;
}

/** Base32 -> binário. Devolve '' quando o segredo tem caractere inválido. */
function sh_base32_decode($segredo) {
    $segredo = strtoupper(preg_replace('/[^A-Z2-7]/i', '', (string)$segredo));
    if ($segredo === '') return '';

    $bits = '';
    for ($i = 0; $i < strlen($segredo); $i++) {
        $pos = strpos(SH_BASE32, $segredo[$i]);
        if ($pos === false) return '';
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $bruto = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $bruto .= chr(bindec($byte));
    }
    return $bruto;
}

/**
 * Código de 6 dígitos para um instante.
 * É o algoritmo do RFC 6238: HMAC-SHA1 do contador, truncagem dinâmica.
 */
function sh_totp_codigo($segredo, $instante = null) {
    $chave = sh_base32_decode($segredo);
    if ($chave === '') return '';

    $instante = $instante ?? time();
    $contador = (int)floor($instante / SH_TOTP_INTERVALO);

    // Contador em 8 bytes, big-endian.
    $binario = pack('N*', 0, $contador);
    $hash    = hash_hmac('sha1', $binario, $chave, true);

    // Truncagem dinâmica: o último nibble diz onde começar a ler.
    $inicio = ord($hash[19]) & 0x0F;
    $trecho = ((ord($hash[$inicio])     & 0x7F) << 24)
            | ((ord($hash[$inicio + 1]) & 0xFF) << 16)
            | ((ord($hash[$inicio + 2]) & 0xFF) << 8)
            |  (ord($hash[$inicio + 3]) & 0xFF);

    return str_pad((string)($trecho % (10 ** SH_TOTP_DIGITOS)),
                   SH_TOTP_DIGITOS, '0', STR_PAD_LEFT);
}

/**
 * O código digitado confere?
 * Aceita a janela de tolerância e compara em tempo constante.
 */
function sh_totp_valido($segredo, $codigo) {
    $codigo = preg_replace('/\D/', '', (string)$codigo);
    if (strlen($codigo) !== SH_TOTP_DIGITOS) return false;

    $agora = time();
    for ($d = -SH_TOTP_TOLERANCIA; $d <= SH_TOTP_TOLERANCIA; $d++) {
        $esperado = sh_totp_codigo($segredo, $agora + ($d * SH_TOTP_INTERVALO));
        if ($esperado !== '' && hash_equals($esperado, $codigo)) return true;
    }
    return false;
}

/**
 * URI otpauth:// que o aplicativo autenticador lê pelo QR Code.
 * O rótulo aparece na lista do aplicativo, então leva o nome da instalação.
 */
function sh_totp_uri($segredo, $usuario) {
    $emissor = rawurlencode(SH_NOME . ' — Interclasse');
    $conta   = rawurlencode($usuario);
    return 'otpauth://totp/' . $emissor . ':' . $conta
         . '?secret=' . $segredo
         . '&issuer=' . $emissor
         . '&algorithm=SHA1'
         . '&digits=' . SH_TOTP_DIGITOS
         . '&period=' . SH_TOTP_INTERVALO;
}

/** Segredo formatado em blocos de 4, para quem digita à mão. */
function sh_totp_segredo_legivel($segredo) {
    return trim(chunk_split($segredo, 4, ' '));
}

/* ══ Códigos de recuperação ══════════════════════════════════════════════ */

/**
 * Gera, grava (como hash) e devolve os códigos em texto.
 * O texto puro existe só nesta chamada: é a única vez que ele aparece.
 */
function sh_totp_gerar_recuperacao(PDO $pdo, $usuario_id) {
    if (!sh_tabela_existe($pdo, 'totp_codigos')) return [];

    $codigos = [];
    for ($i = 0; $i < SH_TOTP_RECUPERACAO; $i++) {
        // 10 caracteres em dois blocos: legível de ler em voz alta.
        $bruto = strtoupper(bin2hex(random_bytes(5)));
        $codigos[] = substr($bruto, 0, 5) . '-' . substr($bruto, 5, 5);
    }

    try {
        $pdo->prepare('DELETE FROM totp_codigos WHERE usuario_id = ?')->execute([(int)$usuario_id]);
        $ins = $pdo->prepare('INSERT INTO totp_codigos (usuario_id, codigo_hash) VALUES (?, ?)');
        foreach ($codigos as $c) {
            $ins->execute([(int)$usuario_id, hash('sha256', $c)]);
        }
    } catch (PDOException $e) {
        sh_log_excecao($e, 'gerar códigos de recuperação do 2FA');
        return [];
    }
    return $codigos;
}

/**
 * Consome um código de recuperação. Uso único: ao acertar, ele é queimado.
 */
function sh_totp_usar_recuperacao(PDO $pdo, $usuario_id, $codigo) {
    if (!sh_tabela_existe($pdo, 'totp_codigos')) return false;

    $codigo = strtoupper(trim((string)$codigo));
    if ($codigo === '') return false;

    try {
        $stmt = $pdo->prepare(
            'SELECT id FROM totp_codigos
              WHERE usuario_id = ? AND codigo_hash = ? AND usado_em IS NULL'
        );
        $stmt->execute([(int)$usuario_id, hash('sha256', $codigo)]);
        $id = $stmt->fetchColumn();
        if (!$id) return false;

        $pdo->prepare('UPDATE totp_codigos SET usado_em = NOW() WHERE id = ?')->execute([$id]);
        sh_auditar($pdo, '2fa_codigo_recuperacao_usado', 'usuarios', (int)$usuario_id);
        return true;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'validar código de recuperação do 2FA');
        return false;
    }
}

/** Quantos códigos de recuperação ainda restam. */
function sh_totp_recuperacao_restantes(PDO $pdo, $usuario_id) {
    if (!sh_tabela_existe($pdo, 'totp_codigos')) return 0;
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM totp_codigos WHERE usuario_id = ? AND usado_em IS NULL'
        );
        $stmt->execute([(int)$usuario_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'contar códigos de recuperação');
        return 0;
    }
}

/** O usuário tem segundo fator ativo? */
function sh_totp_ativo(array $usuario) {
    return !empty($usuario['totp_segredo']) && !empty($usuario['totp_ativado_em']);
}
