<?php
/**
 * tests/casos/06-pdf-email.php — súmula em PDF (SH-45) e e-mail (SH-42)
 *
 * PDF quebrado não abre e não diz por quê: o leitor mostra "arquivo
 * danificado". A parte que quebra é sempre a mesma — a tabela de referências
 * cruzadas, que guarda a posição em bytes de cada objeto. Um byte a mais em
 * qualquer lugar desloca tudo. Por isso o teste confere se cada deslocamento
 * aponta mesmo para o objeto que promete.
 */

grupo('PDF — estrutura do arquivo');

$pdf = new ShPdf('Súmula de teste — acentuação');
$pdf->pagina();
$pdf->fonte('F2', 18)->texto(40, 60, 'Súmula — ação, coração, ônibus');
$pdf->fonte('F1', 10)->paragrafo(40, 100, 500,
    'Parágrafo longo o suficiente para exigir mais de uma linha e exercitar a '
  . 'quebra automática de texto dentro da largura informada.');
$pdf->linha(40, 160, 555, 160);
$pdf->retangulo(40, 170, 200, 30, 'f');
$pdf->pagina();
$pdf->fonte('F2', 14)->texto_centro(40, 555, 60, 'Segunda pagina');

$saida = $pdf->saida();

verificar('começa com o cabeçalho %PDF-1.4', strpos($saida, '%PDF-1.4') === 0);
verificar('termina com %%EOF', substr(rtrim($saida), -5) === '%%EOF');
igual('gerou duas páginas', 2, preg_match_all('/\/Type \/Page[^s]/', $saida));

/* startxref precisa apontar para o byte exato onde começa a tabela. */
verificar('tem a marca startxref', preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $saida, $m) === 1);
if (!empty($m[1])) {
    igual('startxref aponta para a palavra "xref"', 'xref', substr($saida, (int)$m[1], 4));
}

/* Cada entrada da xref tem de cair exatamente no "N 0 obj" correspondente. */
$xref_ok = true;
$entradas = 0;
if (preg_match('/xref\s+0\s+(\d+)\s+(.*?)trailer/s', $saida, $mx)) {
    preg_match_all('/(\d{10}) (\d{5}) ([nf])/', $mx[2], $ent, PREG_SET_ORDER);
    $entradas = count($ent);
    if ($entradas !== (int)$mx[1]) $xref_ok = false;
    for ($i = 1; $i < $entradas; $i++) {
        $esperado = $i . ' 0 obj';
        if (substr($saida, (int)$ent[$i][1], strlen($esperado)) !== $esperado) $xref_ok = false;
    }
} else {
    $xref_ok = false;
}
verificar('todos os deslocamentos da xref apontam para o objeto certo',
    $xref_ok, $entradas . ' entradas conferidas');

verificar('o trailer aponta para um catálogo válido',
    preg_match('/\/Root (\d+) 0 R/', $saida, $mr) === 1
    && preg_match('/' . $mr[1] . ' 0 obj\s*<< \/Type \/Catalog/', $saida) === 1);

grupo('PDF — texto e acentuação');

/* As fontes padrão do PDF usam WinAnsiEncoding: o acento tem de estar
   convertido, senão sai caractere trocado no papel. */
verificar('declara WinAnsiEncoding nas fontes',
    strpos($saida, '/Encoding /WinAnsiEncoding') !== false);
verificar('nenhum byte de UTF-8 multibyte sobrou no fluxo',
    preg_match('/\(([^)]*\xC3[^)]*)\) Tj/', $saida) === 0);

// Extrai os literais para conferir que o texto chegou legível.
preg_match_all('/\((.*?)\) Tj/', $saida, $mt);
$textos = array_map(function ($t) {
    return iconv('Windows-1252', 'UTF-8//TRANSLIT', str_replace(['\\(', '\\)'], ['(', ')'], $t));
}, $mt[1]);

verificar('o título com acento aparece legível',
    in_array('Súmula — ação, coração, ônibus', $textos, true),
    implode(' | ', array_slice($textos, 0, 3)));

/* A quebra é medida pelo avanço vertical que `paragrafo()` devolve, e não
   pela contagem de trechos do arquivo — contar trechos misturaria o título e
   a segunda página no mesmo número. */
$medidor = new ShPdf('Medida');
$medidor->pagina();
$medidor->fonte('F1', 10);
$y_inicial = 100;
$y_final = $medidor->paragrafo(40, $y_inicial, 500,
    'Parágrafo longo o suficiente para exigir mais de uma linha e exercitar a '
  . 'quebra automática de texto dentro da largura informada.');
$entrelinha = 10 * 1.45;

verificar('o parágrafo ocupou mais de uma linha',
    ($y_final - $y_inicial) >= ($entrelinha * 2) - 0.01,
    'avanço de ' . round($y_final - $y_inicial, 1) . ' pt, uma linha tem ' . round($entrelinha, 1));

$y_curto = $medidor->paragrafo(40, 300, 500, 'Linha curta.');
verificar('texto curto ocupa uma linha só',
    ($y_curto - 300) < ($entrelinha * 2),
    'avanço de ' . round($y_curto - 300, 1) . ' pt');


grupo('PDF — escape de caracteres do formato');

$p2 = new ShPdf('Escape');
$p2->pagina();
$p2->texto(10, 10, 'parenteses ( ) e contrabarra \\ no meio');
$s2 = $p2->saida();
verificar('parênteses e contrabarra foram escapados',
    strpos($s2, 'parenteses \\( \\) e contrabarra \\\\ no meio') !== false);

grupo('E-mail — cabeçalhos');

igual('assunto só com ASCII passa direto',
    'Protocolo ARB-2026-0001', sh_email_cabecalho_assunto('Protocolo ARB-2026-0001'));
verificar('assunto com acento é codificado em RFC 2047',
    strpos(sh_email_cabecalho_assunto('Credenciamento aprovado — ação'), '=?UTF-8?B?') === 0);

/* Quebra de linha no assunto é o vetor clássico de injeção de cabeçalho:
   permitiria acrescentar um Bcc: à mensagem. */
$injetado = sh_email_cabecalho_assunto("Assunto\r\nBcc: invasor@exemplo.test");
verificar('quebra de linha no assunto é neutralizada',
    strpos($injetado, "\r") === false && strpos($injetado, "\n") === false);

verificar('nome do remetente com acento é codificado',
    strpos(sh_email_cabecalho_nome('Coordenação'), '=?UTF-8?B?') === 0);
igual('nome sem acento fica entre aspas',
    '"SportHub"', sh_email_cabecalho_nome('SportHub'));

grupo('E-mail — modo de entrega');

verificar('sem SMTP configurado, o modo é "registro"',
    sh_email_modo() === 'registro', sh_email_modo());
verificar('modo registro não é considerado entrega real',
    sh_email_entrega_real() === false);

$r = sh_mail('nao-e-email', 'Teste', 'corpo');
verificar('destinatário inválido é recusado antes de qualquer envio',
    $r['ok'] === false && $r['modo'] === 'invalido');

grupo('E-mail — modelo HTML');

$html = sh_email_modelo('Título do aviso', ['Primeiro parágrafo.', 'Segundo parágrafo.']);
verificar('gera documento HTML completo', strpos($html, '<!doctype html>') === 0);
verificar('inclui os dois parágrafos',
    substr_count($html, '<p style=') >= 2);
verificar('escapa o título', strpos($html, 'Título do aviso') !== false);
