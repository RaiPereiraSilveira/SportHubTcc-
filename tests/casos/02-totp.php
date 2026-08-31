<?php
/**
 * tests/casos/02-totp.php — segundo fator (SH-65)
 *
 * Os vetores abaixo são os do próprio RFC 6238, apêndice B: chave
 * "12345678901234567890" (que em Base32 é GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ),
 * SHA-1, 8 dígitos. Truncados para 6, que é o que este sistema usa.
 *
 * Testar contra o RFC, e não contra a própria saída, é o que dá confiança de
 * que o Google Authenticator vai gerar o mesmo número — não há como conferir
 * isso de outro jeito sem um celular na mão.
 */

grupo('TOTP — vetores oficiais do RFC 6238');

const CHAVE_RFC = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

igual('T = 59',          '287082', sh_totp_codigo(CHAVE_RFC, 59));
igual('T = 1111111109',  '081804', sh_totp_codigo(CHAVE_RFC, 1111111109));
igual('T = 1111111111',  '050471', sh_totp_codigo(CHAVE_RFC, 1111111111));
igual('T = 1234567890',  '005924', sh_totp_codigo(CHAVE_RFC, 1234567890));
igual('T = 2000000000',  '279037', sh_totp_codigo(CHAVE_RFC, 2000000000));

grupo('TOTP — Base32');

$segredo = sh_totp_segredo();
verificar('segredo gerado tem 32 caracteres', strlen($segredo) === 32, 'obtido ' . strlen($segredo));
verificar('segredo usa só o alfabeto Base32',
    preg_match('/^[A-Z2-7]+$/', $segredo) === 1, $segredo);
verificar('dois segredos seguidos são diferentes', sh_totp_segredo() !== $segredo);

igual('decodifica a chave do RFC de volta para o texto original',
    '12345678901234567890', sh_base32_decode(CHAVE_RFC));
igual('Base32 inválido devolve vazio', '', sh_base32_decode('!!!!'));

grupo('TOTP — validação e tolerância');

$agora = time();
$codigo_atual = sh_totp_codigo($segredo, $agora);

verificar('aceita o código do instante atual', sh_totp_valido($segredo, $codigo_atual));
verificar('aceita o código do intervalo anterior (relógio atrasado)',
    sh_totp_valido($segredo, sh_totp_codigo($segredo, $agora - SH_TOTP_INTERVALO)));
verificar('aceita o código do intervalo seguinte (relógio adiantado)',
    sh_totp_valido($segredo, sh_totp_codigo($segredo, $agora + SH_TOTP_INTERVALO)));

verificar('recusa código de dois intervalos atrás',
    !sh_totp_valido($segredo, sh_totp_codigo($segredo, $agora - (3 * SH_TOTP_INTERVALO))));
verificar('recusa código de outro segredo',
    !sh_totp_valido($segredo, sh_totp_codigo(sh_totp_segredo(), $agora)));
verificar('recusa código com menos de 6 dígitos', !sh_totp_valido($segredo, '1234'));
verificar('recusa código vazio',                  !sh_totp_valido($segredo, ''));
verificar('recusa texto no lugar do código',      !sh_totp_valido($segredo, 'abcdef'));

grupo('TOTP — URI do aplicativo autenticador');

$uri = sh_totp_uri($segredo, 'coordenacao');
verificar('começa com otpauth://totp/', strpos($uri, 'otpauth://totp/') === 0, $uri);
verificar('leva o segredo',   strpos($uri, 'secret=' . $segredo) !== false);
verificar('declara 6 dígitos', strpos($uri, 'digits=6') !== false);
verificar('declara período de 30 s', strpos($uri, 'period=30') !== false);
verificar('leva o nome da conta', strpos($uri, 'coordenacao') !== false);

igual('segredo legível em blocos de 4',
    'ABCD EFGH', sh_totp_segredo_legivel('ABCDEFGH'));
