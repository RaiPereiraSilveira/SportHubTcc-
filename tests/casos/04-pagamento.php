<?php
/**
 * tests/casos/04-pagamento.php — Pix copia e cola (SH-41)
 *
 * Um BR Code com CRC errado é recusado pelo aplicativo do banco com uma
 * mensagem genérica ("código inválido"), sem dizer o que está errado. E o
 * erro é fácil de cometer: o CRC-16 do padrão é o CCITT-FALSE, que difere do
 * CRC-16 mais comum no valor inicial.
 *
 * Por isso o teste começa pelo vetor de referência do próprio algoritmo:
 * CRC-16/CCITT-FALSE de "123456789" é 0x29B1.
 */

grupo('Pix — CRC-16/CCITT-FALSE');

igual('vetor de referência "123456789"', '29B1', sh_pix_crc('123456789'));
igual('string vazia devolve o valor inicial', 'FFFF', sh_pix_crc(''));
verificar('entradas diferentes dão CRCs diferentes',
    sh_pix_crc('SPORTHUB') !== sh_pix_crc('SPORTHUC'));

grupo('Pix — montagem dos campos');

igual('campo com tamanho de dois dígitos', '0002BR', sh_pix_campo('00', 'BR'));
igual('campo mais longo',  '0014br.gov.bcb.pix', sh_pix_campo('00', 'br.gov.bcb.pix'));

grupo('Pix — payload completo');

$payload = sh_pix_payload(1188.00, 'COB-2026-0001');

verificar('payload foi gerado', $payload !== null && $payload !== '');
verificar('começa com a versão do formato', strpos($payload, '000201') === 0, substr((string)$payload, 0, 12));
verificar('declara o arranjo Pix do Banco Central',
    strpos($payload, 'br.gov.bcb.pix') !== false);
verificar('leva a chave configurada',
    strpos($payload, 'interclasse@escola.edu.br') !== false);
verificar('declara a moeda 986 (real)', strpos($payload, '5303986') !== false);
verificar('leva o valor com duas casas', strpos($payload, '54071188.00') !== false);
verificar('declara o país BR', strpos($payload, '5802BR') !== false);

// O CRC ocupa os quatro últimos caracteres e cobre tudo o que vem antes,
// incluindo o próprio cabeçalho "6304".
$sem_crc = substr($payload, 0, -4);
igual('o CRC no fim confere com o recalculado',
    substr($payload, -4), sh_pix_crc($sem_crc));
verificar('o cabeçalho do CRC está presente',
    substr($sem_crc, -4) === '6304', substr($sem_crc, -6));

grupo('Pix — normalização de texto');

/* O padrão só aceita ASCII nos campos de nome e cidade: acento faz o
   aplicativo do banco recusar o código inteiro. */
verificar('nenhum caractere fora do ASCII no payload',
    preg_match('/[^\x20-\x7E]/', $payload) === 0);

verificar('payload cabe no limite prático de 512 caracteres',
    strlen($payload) < 512, strlen($payload) . ' caracteres');

grupo('Pagamento — modo efetivo');

igual('com chave Pix configurada, o modo cai em pix ou manual',
    true, in_array(sh_pagamento_modo(), ['pix', 'manual'], true));
verificar('sem credencial, o gateway não é considerado pronto',
    sh_gateway_pronto() === false);

grupo('Gateway — assinatura do webhook');

verificar('sem segredo configurado, nenhuma assinatura é aceita',
    sh_gateway_assinatura_valida('{"evento":"pago"}', 'qualquer-coisa') === false);
