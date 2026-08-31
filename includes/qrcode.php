<?php
/**
 * includes/qrcode.php — gerador de QR Code em SVG (apoio ao SH-65)
 *
 * Por que escrever isto em vez de usar uma biblioteca:
 *
 * A ativação do segundo fator precisa mostrar um QR Code para o aplicativo
 * autenticador ler. As saídas usuais seriam (a) carregar um gerador
 * JavaScript de um CDN — que a Content-Security-Policy deste projeto bloqueia
 * desde o SH-37, e com razão; (b) mandar o segredo para a API de gráficos do
 * Google, o que significaria ENTREGAR A CHAVE DO 2FA A UM TERCEIRO, o oposto
 * do que a funcionalidade existe para fazer; ou (c) vendorizar uma biblioteca
 * inteira num projeto que se propõe a não ter dependências.
 *
 * Sobrou escrever o codificador. São ~300 linhas porque QR Code é um formato
 * fechado e bem especificado (ISO/IEC 18004): fluxo de bits, correção de erro
 * Reed-Solomon sobre GF(256), desenho dos padrões e escolha da máscara.
 *
 * Escopo deliberadamente estreito, porque é tudo de que o projeto precisa:
 *
 *   · modo BYTE (UTF-8), que é o que uma URI otpauth:// é;
 *   · nível de correção M (~15%), o recomendado para tela;
 *   · versões 1 a 10 (até 213 bytes) — a URI otpauth deste sistema tem ~140.
 *
 * A saída é SVG: nítido em qualquer tamanho, sem GD, sem arquivo temporário,
 * e embutível direto no HTML.
 *
 * IMPORTANTE: a tela de ativação sempre mostra também o segredo em texto. Se
 * a câmera não colaborar — ou se este codificador tiver algum defeito — a
 * ativação continua possível pela digitação manual. O QR é conveniência.
 */

/* ── Tabelas do formato ──────────────────────────────────────────────────
   Uma linha por versão (1 a 10), nível de correção M.
     dados        codewords de dados no total
     ecc_bloco    codewords de correção por bloco
     g1 / g1_tam  blocos do grupo 1 e quantos dados cada um leva
     g2 / g2_tam  idem para o grupo 2 (0 quando não existe)
     alinhamento  centros dos padrões de alinhamento                        */
const SH_QR_TABELA = [
    1  => ['dados' => 16,  'ecc_bloco' => 10, 'g1' => 1, 'g1_tam' => 16, 'g2' => 0, 'g2_tam' => 0,  'alinhamento' => []],
    2  => ['dados' => 28,  'ecc_bloco' => 16, 'g1' => 1, 'g1_tam' => 28, 'g2' => 0, 'g2_tam' => 0,  'alinhamento' => [6, 18]],
    3  => ['dados' => 44,  'ecc_bloco' => 26, 'g1' => 1, 'g1_tam' => 44, 'g2' => 0, 'g2_tam' => 0,  'alinhamento' => [6, 22]],
    4  => ['dados' => 64,  'ecc_bloco' => 18, 'g1' => 2, 'g1_tam' => 32, 'g2' => 0, 'g2_tam' => 0,  'alinhamento' => [6, 26]],
    5  => ['dados' => 86,  'ecc_bloco' => 24, 'g1' => 2, 'g1_tam' => 43, 'g2' => 0, 'g2_tam' => 0,  'alinhamento' => [6, 30]],
    6  => ['dados' => 108, 'ecc_bloco' => 16, 'g1' => 4, 'g1_tam' => 27, 'g2' => 0, 'g2_tam' => 0,  'alinhamento' => [6, 34]],
    7  => ['dados' => 124, 'ecc_bloco' => 18, 'g1' => 4, 'g1_tam' => 31, 'g2' => 0, 'g2_tam' => 0,  'alinhamento' => [6, 22, 38]],
    8  => ['dados' => 154, 'ecc_bloco' => 22, 'g1' => 2, 'g1_tam' => 38, 'g2' => 2, 'g2_tam' => 39, 'alinhamento' => [6, 24, 42]],
    9  => ['dados' => 182, 'ecc_bloco' => 22, 'g1' => 3, 'g1_tam' => 36, 'g2' => 2, 'g2_tam' => 37, 'alinhamento' => [6, 26, 46]],
    10 => ['dados' => 216, 'ecc_bloco' => 26, 'g1' => 4, 'g1_tam' => 43, 'g2' => 1, 'g2_tam' => 44, 'alinhamento' => [6, 28, 50]],
];

/* ── Aritmética em GF(256) ───────────────────────────────────────────────
   Reed-Solomon trabalha num corpo finito de 256 elementos, onde multiplicar
   vira somar expoentes. As duas tabelas abaixo são o log e o antilog desse
   corpo, geradas uma vez com o polinômio primitivo 0x11D.                  */
function sh_qr_gf() {
    static $tabelas = null;
    if ($tabelas !== null) return $tabelas;

    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);

    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11D;      // reduz pelo polinômio primitivo
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }
    return $tabelas = ['exp' => $exp, 'log' => $log];
}

function sh_qr_mult($a, $b) {
    if ($a === 0 || $b === 0) return 0;
    $gf = sh_qr_gf();
    return $gf['exp'][$gf['log'][$a] + $gf['log'][$b]];
}

/** Polinômio gerador de grau $grau, usado na divisão que produz o ECC. */
function sh_qr_gerador($grau) {
    static $cache = [];
    if (isset($cache[$grau])) return $cache[$grau];

    $gf   = sh_qr_gf();
    $poli = [1];
    for ($i = 0; $i < $grau; $i++) {
        $novo = array_fill(0, count($poli) + 1, 0);
        foreach ($poli as $j => $coef) {
            $novo[$j]     ^= $coef;
            $novo[$j + 1] ^= sh_qr_mult($coef, $gf['exp'][$i]);
        }
        $poli = $novo;
    }
    return $cache[$grau] = $poli;
}

/** Codewords de correção de um bloco de dados. */
function sh_qr_ecc(array $dados, $quantos) {
    $gerador = sh_qr_gerador($quantos);
    $resto   = array_merge($dados, array_fill(0, $quantos, 0));

    for ($i = 0; $i < count($dados); $i++) {
        $coef = $resto[$i];
        if ($coef === 0) continue;
        for ($j = 0; $j < count($gerador); $j++) {
            $resto[$i + $j] ^= sh_qr_mult($gerador[$j], $coef);
        }
    }
    return array_slice($resto, count($dados));
}

/* ── Montagem do fluxo de bits ───────────────────────────────────────────── */

/** Menor versão que comporta $tamanho bytes no nível M. */
function sh_qr_versao($tamanho) {
    foreach (SH_QR_TABELA as $v => $t) {
        // 4 bits de modo + contador (8 bits até a v9, 16 da v10 em diante)
        $cabecalho = ($v < 10) ? 12 : 20;
        if (($tamanho * 8 + $cabecalho) <= $t['dados'] * 8) return $v;
    }
    return 0;   // não cabe no escopo suportado
}

/** Fluxo de dados completo (dados + ECC, já intercalados). */
function sh_qr_codewords($texto, $versao) {
    $t     = SH_QR_TABELA[$versao];
    $bytes = array_values(unpack('C*', $texto));
    $bits  = '';

    $bits .= '0100';                                         // modo BYTE
    $bits .= str_pad(decbin(count($bytes)), $versao < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    foreach ($bytes as $b) {
        $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    }

    // Terminador de até 4 zeros, depois completa o último byte.
    $capacidade = $t['dados'] * 8;
    $bits .= str_repeat('0', min(4, $capacidade - strlen($bits)));
    if (strlen($bits) % 8 !== 0) {
        $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
    }

    // Preenchimento alternado 0xEC / 0x11 até encher a capacidade.
    $enchimento = [0xEC, 0x11];
    $i = 0;
    while (strlen($bits) < $capacidade) {
        $bits .= str_pad(decbin($enchimento[$i++ % 2]), 8, '0', STR_PAD_LEFT);
    }

    $codewords = [];
    foreach (str_split($bits, 8) as $byte) {
        $codewords[] = bindec($byte);
    }

    /* Divisão em blocos. Cada bloco recebe seu próprio ECC; depois os blocos
       são intercalados coluna a coluna, que é como o padrão espalha o dano de
       um borrão por vários blocos em vez de destruir um só. */
    $blocos = [];
    $pos    = 0;
    for ($i = 0; $i < $t['g1']; $i++) {
        $blocos[] = array_slice($codewords, $pos, $t['g1_tam']);
        $pos += $t['g1_tam'];
    }
    for ($i = 0; $i < $t['g2']; $i++) {
        $blocos[] = array_slice($codewords, $pos, $t['g2_tam']);
        $pos += $t['g2_tam'];
    }

    $eccs = [];
    foreach ($blocos as $bloco) {
        $eccs[] = sh_qr_ecc($bloco, $t['ecc_bloco']);
    }

    $saida = [];
    $maior = max(array_map('count', $blocos));
    for ($c = 0; $c < $maior; $c++) {
        foreach ($blocos as $bloco) {
            if (isset($bloco[$c])) $saida[] = $bloco[$c];
        }
    }
    for ($c = 0; $c < $t['ecc_bloco']; $c++) {
        foreach ($eccs as $ecc) {
            if (isset($ecc[$c])) $saida[] = $ecc[$c];
        }
    }
    return $saida;
}

/* ── Desenho da matriz ───────────────────────────────────────────────────── */

/** Matriz com os padrões fixos; $reservado marca o que não recebe dado. */
function sh_qr_matriz_base($versao, &$reservado) {
    $n = $versao * 4 + 17;
    $m = array_fill(0, $n, array_fill(0, $n, 0));
    $reservado = array_fill(0, $n, array_fill(0, $n, false));

    // Localizadores nos três cantos, com separador de um módulo claro.
    foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$lin, $col]) {
        for ($i = -1; $i <= 7; $i++) {
            for ($j = -1; $j <= 7; $j++) {
                $y = $lin + $i; $x = $col + $j;
                if ($y < 0 || $x < 0 || $y >= $n || $x >= $n) continue;
                $borda  = ($i === 0 || $i === 6 || $j === 0 || $j === 6);
                $centro = ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                $m[$y][$x] = ($borda || $centro) ? 1 : 0;
                $reservado[$y][$x] = true;
            }
        }
    }

    // Linhas de sincronismo (timing), alternando escuro/claro.
    for ($i = 8; $i < $n - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;
        $m[6][$i] = $bit; $reservado[6][$i] = true;
        $m[$i][6] = $bit; $reservado[$i][6] = true;
    }

    // Padrões de alinhamento, exceto onde colidiriam com um localizador.
    $centros = SH_QR_TABELA[$versao]['alinhamento'];
    foreach ($centros as $cy) {
        foreach ($centros as $cx) {
            $canto = ($cy <= 8 && $cx <= 8)
                  || ($cy <= 8 && $cx >= $n - 9)
                  || ($cy >= $n - 9 && $cx <= 8);
            if ($canto) continue;
            for ($i = -2; $i <= 2; $i++) {
                for ($j = -2; $j <= 2; $j++) {
                    $borda = (abs($i) === 2 || abs($j) === 2 || ($i === 0 && $j === 0));
                    $m[$cy + $i][$cx + $j] = $borda ? 1 : 0;
                    $reservado[$cy + $i][$cx + $j] = true;
                }
            }
        }
    }

    // Módulo sempre escuro, exigido pelo padrão.
    $m[$n - 8][8] = 1;
    $reservado[$n - 8][8] = true;

    // Área reservada da informação de formato.
    for ($i = 0; $i <= 8; $i++) {
        if ($i !== 6) { $reservado[8][$i] = true; $reservado[$i][8] = true; }
    }
    for ($i = 0; $i < 8; $i++) {
        $reservado[8][$n - 1 - $i] = true;
        $reservado[$n - 1 - $i][8] = true;
    }

    // Informação de versão (só a partir da versão 7).
    if ($versao >= 7) {
        $info = sh_qr_info_versao($versao);
        for ($i = 0; $i < 18; $i++) {
            $bit = ($info >> $i) & 1;
            $lin = (int)($i / 3);
            $col = $i % 3;
            $m[$lin][$n - 11 + $col] = $bit; $reservado[$lin][$n - 11 + $col] = true;
            $m[$n - 11 + $col][$lin] = $bit; $reservado[$n - 11 + $col][$lin] = true;
        }
    }
    return $m;
}

/** BCH(18,6) da informação de versão. */
function sh_qr_info_versao($versao) {
    $resto = $versao;
    for ($i = 0; $i < 12; $i++) {
        $resto <<= 1;
        if ($resto & (1 << 18)) $resto ^= 0x1F25;
    }
    return ($versao << 12) | $resto;
}

/** BCH(15,5) da informação de formato — nível M (bits 00) + máscara. */
function sh_qr_info_formato($mascara) {
    $dados = (0b00 << 3) | $mascara;
    $resto = $dados << 10;
    for ($i = 14; $i >= 10; $i--) {
        if ($resto & (1 << $i)) $resto ^= 0x537 << ($i - 10);
    }
    return (($dados << 10) | $resto) ^ 0x5412;
}

/** Preenche a matriz com os codewords, em ziguezague de baixo para cima. */
function sh_qr_preencher(array &$m, array $reservado, array $codewords) {
    $n    = count($m);
    $bits = '';
    foreach ($codewords as $c) {
        $bits .= str_pad(decbin($c), 8, '0', STR_PAD_LEFT);
    }

    $pos    = 0;
    $subindo = true;
    for ($col = $n - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col--;                 // pula a coluna de sincronismo
        for ($k = 0; $k < $n; $k++) {
            $lin = $subindo ? ($n - 1 - $k) : $k;
            foreach ([0, 1] as $d) {
                $c = $col - $d;
                if ($reservado[$lin][$c]) continue;
                $m[$lin][$c] = ($pos < strlen($bits)) ? (int)$bits[$pos] : 0;
                $pos++;
            }
        }
        $subindo = !$subindo;
    }
}

/** A condição de cada uma das oito máscaras. */
function sh_qr_mascara_bit($mascara, $i, $j) {
    switch ($mascara) {
        case 0: return ($i + $j) % 2 === 0;
        case 1: return $i % 2 === 0;
        case 2: return $j % 3 === 0;
        case 3: return ($i + $j) % 3 === 0;
        case 4: return ((int)($i / 2) + (int)($j / 3)) % 2 === 0;
        case 5: return (($i * $j) % 2) + (($i * $j) % 3) === 0;
        case 6: return ((($i * $j) % 2) + (($i * $j) % 3)) % 2 === 0;
        default: return (((($i + $j) % 2) + (($i * $j) % 3)) % 2) === 0;
    }
}

/**
 * Penalidade de uma matriz mascarada (regras 1 a 4 do padrão).
 * Quanto menor, mais fácil de ler — é o critério para escolher a máscara.
 */
function sh_qr_penalidade(array $m) {
    $n = count($m);
    $p = 0;

    // Regra 1: sequências de 5 ou mais módulos iguais, em linha e em coluna.
    for ($passo = 0; $passo < 2; $passo++) {
        for ($a = 0; $a < $n; $a++) {
            $atual = -1; $conta = 0;
            for ($b = 0; $b < $n; $b++) {
                $v = $passo === 0 ? $m[$a][$b] : $m[$b][$a];
                if ($v === $atual) {
                    $conta++;
                } else {
                    if ($conta >= 5) $p += 3 + ($conta - 5);
                    $atual = $v; $conta = 1;
                }
            }
            if ($conta >= 5) $p += 3 + ($conta - 5);
        }
    }

    // Regra 2: blocos 2x2 de cor uniforme.
    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = 0; $j < $n - 1; $j++) {
            $v = $m[$i][$j];
            if ($v === $m[$i][$j + 1] && $v === $m[$i + 1][$j] && $v === $m[$i + 1][$j + 1]) {
                $p += 3;
            }
        }
    }

    // Regra 3: padrão parecido com localizador (1011101 com 4 claros ao lado).
    $alvo1 = [1,0,1,1,1,0,1,0,0,0,0];
    $alvo2 = [0,0,0,0,1,0,1,1,1,0,1];
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j <= $n - 11; $j++) {
            $linha = []; $coluna = [];
            for ($k = 0; $k < 11; $k++) {
                $linha[]  = $m[$i][$j + $k];
                $coluna[] = $m[$j + $k][$i];
            }
            if ($linha  === $alvo1 || $linha  === $alvo2) $p += 40;
            if ($coluna === $alvo1 || $coluna === $alvo2) $p += 40;
        }
    }

    // Regra 4: desequilíbrio entre módulos escuros e claros.
    $escuros = 0;
    foreach ($m as $linha) $escuros += array_sum($linha);
    $proporcao = ($escuros * 100) / ($n * $n);
    $p += (int)(abs($proporcao - 50) / 5) * 10;

    return $p;
}

/**
 * Matriz final do QR Code: 0 = claro, 1 = escuro.
 * Devolve [] quando o texto não cabe nas versões suportadas.
 */
function sh_qr_matriz($texto) {
    $versao = sh_qr_versao(strlen($texto));
    if ($versao === 0) return [];

    $codewords = sh_qr_codewords($texto, $versao);

    $melhor = null;
    $melhor_penalidade = PHP_INT_MAX;

    for ($mascara = 0; $mascara < 8; $mascara++) {
        $reservado = null;
        $m = sh_qr_matriz_base($versao, $reservado);
        sh_qr_preencher($m, $reservado, $codewords);

        $n = count($m);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (!$reservado[$i][$j] && sh_qr_mascara_bit($mascara, $i, $j)) {
                    $m[$i][$j] ^= 1;
                }
            }
        }

        // Informação de formato, nas duas cópias que o padrão exige.
        $formato = sh_qr_info_formato($mascara);
        for ($i = 0; $i < 15; $i++) {
            $bit = ($formato >> $i) & 1;
            if ($i < 6)       { $m[$i][8] = $bit; }
            elseif ($i === 6) { $m[7][8] = $bit; }
            elseif ($i === 7) { $m[8][8] = $bit; }
            elseif ($i === 8) { $m[8][7] = $bit; }
            else              { $m[8][14 - $i] = $bit; }

            if ($i < 8) { $m[8][$n - 1 - $i] = $bit; }
            else        { $m[$n - 15 + $i][8] = $bit; }
        }
        $m[$n - 8][8] = 1;   // módulo sempre escuro

        $penalidade = sh_qr_penalidade($m);
        if ($penalidade < $melhor_penalidade) {
            $melhor_penalidade = $penalidade;
            $melhor = $m;
        }
    }
    return $melhor ?? [];
}

/**
 * QR Code pronto para embutir no HTML, em SVG.
 *
 * @param string $texto  conteúdo a codificar
 * @param int    $lado   largura em pixels
 * @param string $rotulo texto alternativo (acessibilidade)
 * @return string SVG, ou '' quando o texto não couber
 */
function sh_qrcode_svg($texto, $lado = 220, $rotulo = 'QR Code') {
    $m = sh_qr_matriz($texto);
    if (!$m) return '';

    $n     = count($m);
    $borda = 4;                       // zona de silêncio exigida pelo padrão
    $total = $n + $borda * 2;

    /* Um <rect> por módulo escuro. Poderia ser um único <path>, mas o SVG
       resultante já é pequeno e assim continua legível ao inspecionar. */
    $modulos = '';
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if ($m[$i][$j] === 1) {
                $modulos .= '<rect x="' . ($j + $borda) . '" y="' . ($i + $borda)
                          . '" width="1" height="1"/>';
            }
        }
    }

    $id = 'qr' . substr(md5($texto), 0, 6);
    return '<svg class="qrcode" xmlns="http://www.w3.org/2000/svg" '
         . 'viewBox="0 0 ' . $total . ' ' . $total . '" '
         . 'width="' . (int)$lado . '" height="' . (int)$lado . '" '
         . 'role="img" aria-labelledby="' . $id . '" shape-rendering="crispEdges">'
         . '<title id="' . $id . '">' . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</title>'
         . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
         . '<g fill="#000000">' . $modulos . '</g>'
         . '</svg>';
}
