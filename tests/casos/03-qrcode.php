<?php
/**
 * tests/casos/03-qrcode.php — gerador de QR Code (apoio ao SH-65)
 *
 * Não há como "olhar" um QR num teste automatizado, e um QR errado falha em
 * silêncio: a imagem parece certa e o celular simplesmente não lê. Então o
 * teste ataca as três coisas que podem estar erradas por dentro:
 *
 *   1. a matemática de Reed-Solomon — se os codewords de correção estiverem
 *      errados, as síndromes não zeram;
 *   2. o posicionamento e a máscara — lendo a matriz de volta, em ziguezague,
 *      os codewords têm de reaparecer idênticos;
 *   3. a informação de formato — decodificá-la tem de devolver o nível M e a
 *      máscara que foi de fato aplicada.
 *
 * Juntos, os três cobrem o caminho inteiro entre o texto e os módulos pretos.
 */

grupo('QR Code — Reed-Solomon sobre GF(256)');

/** Avalia o polinômio (coeficientes do maior grau para o menor) em x. */
function sh_teste_avaliar(array $poli, $x) {
    $r = 0;
    foreach ($poli as $coef) {
        $r = sh_qr_mult($r, $x) ^ $coef;
    }
    return $r;
}

$gf = sh_qr_gf();
igual('α^0 = 1',            1, $gf['exp'][0]);
igual('log(1) = 0',         0, $gf['log'][1]);
igual('α^255 volta a α^0',  $gf['exp'][0], $gf['exp'][255]);
igual('multiplicação por zero',  0, sh_qr_mult(0, 123));
igual('multiplicação neutra',  123, sh_qr_mult(1, 123));

/* Um bloco codificado corretamente é divisível pelo polinômio gerador — o que
   equivale a dizer que todas as síndromes são zero. É a mesma verificação que
   o leitor de QR faz para saber se leu certo. */
$falhas_rs = 0;
foreach (array_keys(SH_QR_TABELA) as $versao) {
    $t = SH_QR_TABELA[$versao];
    $dados = [];
    for ($i = 0; $i < $t['g1_tam']; $i++) $dados[] = ($i * 7 + 13) % 256;

    $completo = array_merge($dados, sh_qr_ecc($dados, $t['ecc_bloco']));
    for ($i = 0; $i < $t['ecc_bloco']; $i++) {
        if (sh_teste_avaliar($completo, $gf['exp'][$i]) !== 0) $falhas_rs++;
    }
}
igual('todas as síndromes zeram, da versão 1 à 10', 0, $falhas_rs);

grupo('QR Code — escolha de versão');

igual('20 bytes cabem na versão 2',   2, sh_qr_versao(20));
igual('100 bytes cabem na versão 6',  6, sh_qr_versao(100));
verificar('texto grande demais devolve 0', sh_qr_versao(5000) === 0);

grupo('QR Code — estrutura da matriz');

$texto  = 'otpauth://totp/Teste:coordenacao?secret=JBSWY3DPEHPK3PXP&issuer=Teste&digits=6&period=30';
$versao = sh_qr_versao(strlen($texto));
$m      = sh_qr_matriz($texto);
$n      = count($m);

igual('lado = 4 × versão + 17', 4 * $versao + 17, $n);

$localizador = [
    [1,1,1,1,1,1,1], [1,0,0,0,0,0,1], [1,0,1,1,1,0,1], [1,0,1,1,1,0,1],
    [1,0,1,1,1,0,1], [1,0,0,0,0,0,1], [1,1,1,1,1,1,1],
];
$loc_ok = true;
foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$ly, $lx]) {
    for ($i = 0; $i < 7; $i++) {
        for ($j = 0; $j < 7; $j++) {
            if ($m[$ly + $i][$lx + $j] !== $localizador[$i][$j]) $loc_ok = false;
        }
    }
}
verificar('os três localizadores estão corretos', $loc_ok);

$timing_ok = true;
for ($i = 8; $i < $n - 8; $i++) {
    $esperado = ($i % 2 === 0) ? 1 : 0;
    if ($m[6][$i] !== $esperado || $m[$i][6] !== $esperado) $timing_ok = false;
}
verificar('as linhas de sincronismo alternam', $timing_ok);
verificar('o módulo obrigatoriamente escuro está no lugar', $m[$n - 8][8] === 1);

grupo('QR Code — formato e leitura de volta');

// Decodifica a informação de formato gravada na cópia do canto.
$bruto = 0;
for ($i = 0; $i < 15; $i++) {
    $bit = ($i < 8) ? $m[8][$n - 1 - $i] : $m[$n - 15 + $i][8];
    $bruto |= ($bit << $i);
}
$formato = $bruto ^ 0x5412;
$nivel   = ($formato >> 13) & 0b11;
$mascara = ($formato >> 10) & 0b111;

igual('nível de correção gravado é M', 0b00, $nivel);
verificar('máscara gravada está entre 0 e 7', $mascara >= 0 && $mascara <= 7, 'máscara ' . $mascara);

/* Round-trip: desfaz a máscara e lê os módulos na mesma ordem em ziguezague
   com que foram escritos. Se placement e máscara estiverem certos, sai
   exatamente o que entrou. */
$reservado = null;
sh_qr_matriz_base($versao, $reservado);
for ($i = 0; $i < $n; $i++) {
    for ($j = 0; $j < $n; $j++) {
        if (!$reservado[$i][$j] && sh_qr_mascara_bit($mascara, $i, $j)) $m[$i][$j] ^= 1;
    }
}

$bits = '';
$subindo = true;
for ($col = $n - 1; $col > 0; $col -= 2) {
    if ($col === 6) $col--;
    for ($k = 0; $k < $n; $k++) {
        $lin = $subindo ? ($n - 1 - $k) : $k;
        foreach ([0, 1] as $d) {
            $c = $col - $d;
            if ($reservado[$lin][$c]) continue;
            $bits .= $m[$lin][$c];
        }
    }
    $subindo = !$subindo;
}

$esperados = sh_qr_codewords($texto, $versao);
$lidos = [];
foreach (str_split(substr($bits, 0, count($esperados) * 8), 8) as $byte) {
    $lidos[] = bindec($byte);
}

verificar('os codewords lidos de volta são idênticos aos gravados',
    $lidos === $esperados,
    'gravados ' . count($esperados) . ', lidos ' . count($lidos));

grupo('QR Code — saída em SVG');

$svg = sh_qrcode_svg('https://exemplo.test/sporthub', 200, 'QR de teste');
verificar('devolve um elemento svg',   strpos($svg, '<svg') === 0);
verificar('declara papel de imagem',   strpos($svg, 'role="img"') !== false);
verificar('tem título acessível',      strpos($svg, '<title') !== false);
verificar('tem fundo branco explícito', strpos($svg, 'fill="#ffffff"') !== false);
igual('texto grande demais devolve vazio', '', sh_qrcode_svg(str_repeat('x', 5000)));
