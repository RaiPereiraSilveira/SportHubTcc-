<?php
/**
 * includes/email.php — envio de e-mail (SH-42)
 *
 * Até aqui o sistema mostrava o protocolo na tela e torcia para o usuário
 * anotar. Quem fechasse a aba perdia o número; a árbitra aprovada não recebia
 * a senha provisória; a recuperação de senha (SH-64) sequer podia existir,
 * porque não havia como entregar o link.
 *
 * Este arquivo resolve a entrega sem trazer biblioteca externa: um cliente
 * SMTP escrito sobre sockets, com STARTTLS, SSL implícito e AUTH LOGIN /
 * PLAIN — que é tudo o que Gmail, Outlook, Zoho e a hospedagem da escola
 * pedem. São ~200 linhas porque SMTP é um protocolo de linha simples; a
 * alternativa seria arrastar um PHPMailer inteiro para dois formulários.
 *
 * Três modos, decididos pela configuração:
 *
 *   smtp      SH_SMTP_HOST preenchido em config.local.php → entrega real.
 *   mail      SH_EMAIL_MODO = 'mail' → função mail() do PHP (funciona em
 *             hospedagem compartilhada, não funciona no XAMPP de fábrica).
 *   registro  padrão em desenvolvimento: nada sai da máquina. A mensagem
 *             inteira vai para logs/emails/ e para a tabela emails_enviados,
 *             e a tela continua exibindo o protocolo como antes. É o que
 *             permite testar todo o fluxo no XAMPP sem servidor de e-mail.
 *
 * Nenhum modo interrompe o fluxo do usuário: se a entrega falhar, a falha é
 * registrada e a página segue. O protocolo na tela nunca deixou de existir —
 * o e-mail é reforço, não é a única via.
 */

require_once __DIR__ . '/config.php';

sh_padrao('SH_EMAIL_MODO',      '');      // 'smtp', 'mail', 'registro' ou '' (automático)
sh_padrao('SH_SMTP_HOST',       '');
sh_padrao('SH_SMTP_PORTA',      587);
sh_padrao('SH_SMTP_USUARIO',    '');
sh_padrao('SH_SMTP_SENHA',      '');
sh_padrao('SH_SMTP_SEGURANCA',  'tls');   // 'tls' (STARTTLS), 'ssl' ou 'nenhuma'
sh_padrao('SH_SMTP_TIMEOUT',    15);
sh_padrao('SH_EMAIL_REMETENTE', SH_EMAIL);
sh_padrao('SH_EMAIL_NOME',      SH_NOME);

/** Modo de entrega efetivo desta instalação. */
function sh_email_modo() {
    if (in_array(SH_EMAIL_MODO, ['smtp', 'mail', 'registro'], true)) return SH_EMAIL_MODO;
    if (SH_SMTP_HOST !== '') return 'smtp';
    return 'registro';
}

/** Há entrega real configurada? Usado para decidir o texto na tela. */
function sh_email_entrega_real() {
    return sh_email_modo() !== 'registro';
}

/**
 * Envia uma mensagem.
 *
 * @param string $para    destinatário
 * @param string $assunto assunto (texto puro)
 * @param string $texto   corpo em texto puro — é o corpo real da mensagem
 * @param array  $opcoes  ['html' => string, 'responder_para' => string,
 *                         'contexto' => string]
 * @return array{ok:bool, modo:string, erro:?string}
 */
function sh_mail($para, $assunto, $texto, array $opcoes = []) {
    $para = trim((string)$para);
    if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'modo' => 'invalido', 'erro' => 'Destinatário inválido.'];
    }

    $modo     = sh_email_modo();
    $contexto = (string)($opcoes['contexto'] ?? 'mensagem');
    $html     = $opcoes['html'] ?? null;

    $limite     = '=_sporthub_' . bin2hex(random_bytes(8));
    $cabecalhos = [
        'From: ' . sh_email_cabecalho_nome(SH_EMAIL_NOME) . ' <' . SH_EMAIL_REMETENTE . '>',
        'MIME-Version: 1.0',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . sh_email_dominio() . '>',
        'X-Mailer: SportHub',
    ];
    if (!empty($opcoes['responder_para'])) {
        $cabecalhos[] = 'Reply-To: ' . str_replace(["\r", "\n"], '', $opcoes['responder_para']);
    }

    if ($html !== null) {
        $cabecalhos[] = 'Content-Type: multipart/alternative; boundary="' . $limite . '"';
        $corpo = '--' . $limite . "\r\n"
               . "Content-Type: text/plain; charset=UTF-8\r\n"
               . "Content-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($texto)) . "\r\n"
               . '--' . $limite . "\r\n"
               . "Content-Type: text/html; charset=UTF-8\r\n"
               . "Content-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($html)) . "\r\n"
               . '--' . $limite . "--\r\n";
    } else {
        $cabecalhos[] = 'Content-Type: text/plain; charset=UTF-8';
        $cabecalhos[] = 'Content-Transfer-Encoding: base64';
        $corpo = chunk_split(base64_encode($texto));
    }

    $assunto_mime = sh_email_cabecalho_assunto($assunto);
    $erro = null;
    $ok   = false;

    try {
        if ($modo === 'smtp') {
            $ok = sh_smtp_entregar($para, $assunto_mime, $corpo, $cabecalhos, $erro);
        } elseif ($modo === 'mail') {
            $extra = array_values(array_filter($cabecalhos, function ($h) {
                return stripos($h, 'Date:') !== 0;    // o mail() escreve a sua
            }));
            $ok = @mail($para, $assunto_mime, $corpo, implode("\r\n", $extra));
            if (!$ok) $erro = 'A função mail() do PHP recusou a mensagem.';
        } else {
            $ok = sh_email_registrar_arquivo($para, $assunto, $texto, $html);
            if (!$ok) $erro = 'Pasta logs/emails/ indisponível para escrita.';
        }
    } catch (Throwable $e) {
        $erro = 'Falha ao entregar: referência ' . sh_log_excecao($e, 'enviar e-mail (' . $contexto . ')');
    }

    sh_email_registrar_banco($para, $assunto, $contexto, $modo, $ok, $erro);

    if (!$ok && $erro !== null) {
        error_log('[SportHub] e-mail não entregue | para=' . $para . ' | ctx=' . $contexto . ' | ' . $erro);
    }
    return ['ok' => (bool)$ok, 'modo' => $modo, 'erro' => $erro];
}

/** Nome de exibição do remetente, codificado quando tiver acento. */
function sh_email_cabecalho_nome($nome) {
    return preg_match('/[^\x20-\x7E]/', $nome)
        ? '=?UTF-8?B?' . base64_encode($nome) . '?='
        : '"' . str_replace('"', '', $nome) . '"';
}

/** Assunto codificado em RFC 2047 quando houver caractere fora do ASCII. */
function sh_email_cabecalho_assunto($assunto) {
    $assunto = str_replace(["\r", "\n"], ' ', (string)$assunto);   // anti-injeção
    return preg_match('/[^\x20-\x7E]/', $assunto)
        ? '=?UTF-8?B?' . base64_encode($assunto) . '?='
        : $assunto;
}

/** Domínio usado no Message-ID. */
function sh_email_dominio() {
    $arroba = strrchr(SH_EMAIL_REMETENTE, '@');
    return $arroba !== false ? substr($arroba, 1) : 'sporthub.local';
}

/* ── Modo "registro": grava em logs/emails/ ─────────────────────────────── */
function sh_email_registrar_arquivo($para, $assunto, $texto, $html) {
    $dir = dirname(__DIR__) . '/logs/emails';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (!is_dir($dir) || !is_writable($dir)) return false;

    $arquivo  = $dir . '/' . date('Ymd-His') . '-' . substr(md5($para . microtime()), 0, 6) . '.txt';
    $conteudo = 'Para: ' . $para . "\n" . 'Assunto: ' . $assunto . "\n" . 'Data: ' . date('r') . "\n"
              . str_repeat('-', 60) . "\n" . $texto . "\n";
    if ($html !== null) {
        $conteudo .= str_repeat('-', 60) . "\n" . '[versão HTML]' . "\n" . $html . "\n";
    }
    return @file_put_contents($arquivo, $conteudo) !== false;
}

/* ── Registro no banco ───────────────────────────────────────────────────
   Serve a duas coisas: a coordenação vê em admin/emails.php se a mensagem
   saiu, e a prestação de contas da LGPD (art. 6º, X) passa a registrar também
   as comunicações feitas ao titular. O corpo NÃO é gravado — só destinatário,
   assunto e resultado. */
function sh_email_registrar_banco($para, $assunto, $contexto, $modo, $ok, $erro) {
    global $pdo;
    if (!($pdo instanceof PDO) || !sh_tabela_existe($pdo, 'emails_enviados')) return;
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO emails_enviados (destinatario, assunto, contexto, modo, status, erro)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            mb_substr($para, 0, 255),
            mb_substr($assunto, 0, 200),
            mb_substr($contexto, 0, 60),
            $modo,
            $ok ? 'enviado' : 'falhou',
            $erro !== null ? mb_substr($erro, 0, 255) : null,
        ]);
    } catch (PDOException $e) {
        sh_log_excecao($e, 'registrar e-mail enviado');
    }
}

/* ══ Cliente SMTP ════════════════════════════════════════════════════════
   Conversa de linha: o servidor responde "250 ..." a cada comando aceito.
   Qualquer código que não comece pelo esperado aborta a entrega.          */
function sh_smtp_entregar($para, $assunto, $corpo, array $cabecalhos, &$erro) {
    $seguranca = strtolower(SH_SMTP_SEGURANCA);
    $host      = ($seguranca === 'ssl' ? 'ssl://' : '') . SH_SMTP_HOST;

    $ctx    = stream_context_create(['ssl' => ['SNI_enabled' => true]]);
    $socket = @stream_socket_client(
        $host . ':' . (int)SH_SMTP_PORTA,
        $codigo, $mensagem, (int)SH_SMTP_TIMEOUT,
        STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$socket) {
        $erro = 'Não foi possível conectar ao servidor SMTP (' . $mensagem . ').';
        return false;
    }
    stream_set_timeout($socket, (int)SH_SMTP_TIMEOUT);

    $ehlo = 'sporthub.' . sh_email_dominio();
    try {
        sh_smtp_esperar($socket, '220');
        sh_smtp_comando($socket, 'EHLO ' . $ehlo, '250');

        if ($seguranca === 'tls') {
            sh_smtp_comando($socket, 'STARTTLS', '220');
            $cripto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cripto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($socket, true, $cripto)) {
                throw new RuntimeException('O servidor aceitou STARTTLS mas a negociação TLS falhou.');
            }
            sh_smtp_comando($socket, 'EHLO ' . $ehlo, '250');
        }

        if (SH_SMTP_USUARIO !== '') {
            sh_smtp_autenticar($socket);
        }

        sh_smtp_comando($socket, 'MAIL FROM:<' . SH_EMAIL_REMETENTE . '>', '250');
        sh_smtp_comando($socket, 'RCPT TO:<' . $para . '>', '25');
        sh_smtp_comando($socket, 'DATA', '354');

        $mensagem_completa = 'To: ' . $para . "\r\n"
                           . 'Subject: ' . $assunto . "\r\n"
                           . implode("\r\n", $cabecalhos) . "\r\n\r\n"
                           . $corpo;
        // Ponto sozinho no início da linha encerra o DATA: precisa ser duplicado.
        $mensagem_completa = preg_replace('/^\./m', '..', $mensagem_completa);

        fwrite($socket, $mensagem_completa . "\r\n.\r\n");
        sh_smtp_esperar($socket, '250');
        sh_smtp_comando($socket, 'QUIT', '221');
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
        return false;
    }
}

/** AUTH LOGIN quando o servidor oferece; PLAIN como alternativa. */
function sh_smtp_autenticar($socket) {
    try {
        sh_smtp_comando($socket, 'AUTH LOGIN', '334');
        sh_smtp_comando($socket, base64_encode(SH_SMTP_USUARIO), '334');
        sh_smtp_comando($socket, base64_encode(SH_SMTP_SENHA), '235');
    } catch (RuntimeException $e) {
        $plain = base64_encode("\0" . SH_SMTP_USUARIO . "\0" . SH_SMTP_SENHA);
        sh_smtp_comando($socket, 'AUTH PLAIN ' . $plain, '235');
    }
}

/** Envia um comando e confere o código de resposta. */
function sh_smtp_comando($socket, $comando, $esperado) {
    fwrite($socket, $comando . "\r\n");
    return sh_smtp_esperar($socket, $esperado, $comando);
}

/**
 * Lê a resposta (inclusive multilinha "250-...") e valida o prefixo.
 * A senha nunca entra na exceção: o comando é omitido quando é AUTH.
 */
function sh_smtp_esperar($socket, $esperado, $comando = '') {
    $resposta = '';
    while (($linha = fgets($socket, 515)) !== false) {
        $resposta .= $linha;
        if (strlen($linha) < 4 || $linha[3] !== '-') break;
    }
    if (strpos($resposta, $esperado) !== 0) {
        $rotulo = (stripos($comando, 'AUTH') === 0 || $comando === '')
            ? 'a autenticação' : $comando;
        throw new RuntimeException(
            'SMTP recusou ' . $rotulo . ': ' . trim(substr($resposta, 0, 120))
        );
    }
    return $resposta;
}

/* ── Modelo visual das mensagens ─────────────────────────────────────────
   HTML mínimo, em tabela, porque cliente de e-mail não entende grid nem
   variável CSS — e é o único lugar do projeto onde style="" continua sendo
   a forma correta de escrever, já que não há CSP no Gmail. O texto puro
   continua sendo a versão de verdade.                                     */
function sh_email_modelo($titulo, $paragrafos, $rodape = null) {
    $corpo = '';
    foreach ((array)$paragrafos as $p) {
        $corpo .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1c2b24">' . $p . '</p>';
    }
    $rodape = $rodape ?? (SH_NOME . ' · ' . SH_TAGLINE);

    return '<!doctype html><html lang="pt-BR"><body style="margin:0;background:#eef1ed;padding:24px;'
         . 'font-family:Segoe UI,Arial,sans-serif">'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
         . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" '
         . 'style="max-width:560px;background:#fff;border-radius:14px;border:1px solid #d7ded8">'
         . '<tr><td style="padding:22px 26px;border-bottom:1px solid #e6ebe6">'
         . '<span style="font-size:18px;font-weight:700;color:#0f7a55">' . e(SH_NOME) . '</span></td></tr>'
         . '<tr><td style="padding:26px">'
         . '<h1 style="margin:0 0 16px;font-size:19px;color:#14201b">' . e($titulo) . '</h1>'
         . $corpo . '</td></tr>'
         . '<tr><td style="padding:16px 26px;border-top:1px solid #e6ebe6;font-size:12px;color:#6b7a72">'
         . e($rodape) . '</td></tr>'
         . '</table></td></tr></table></body></html>';
}
