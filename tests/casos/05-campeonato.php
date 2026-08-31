<?php
/**
 * tests/casos/05-campeonato.php — desempate e chaveamento (SH-55, SH-56)
 *
 * A ordem da classificação é o resultado mais visível do sistema inteiro, e
 * um erro nela não dispara aviso nenhum: a tabela aparece, só que com o time
 * errado em primeiro. É exatamente o tipo de coisa que precisa de teste.
 */

grupo('Desempate — comparação por critério (sh_comparar_criterio)');

$a = ['id' => 1, 'nome' => 'Águias',   'saldo' => 5, 'gols_pro' => 12, 'gols_contra' => 7,
      'vitorias' => 3, 'cartoes' => 4];
$b = ['id' => 2, 'nome' => 'Panteras', 'saldo' => 2, 'gols_pro' => 15, 'gols_contra' => 13,
      'vitorias' => 3, 'cartoes' => 1];

verificar('saldo maior fica à frente',
    sh_comparar_criterio('saldo', $a, $b, []) < 0);
verificar('mais gols marcados fica à frente',
    sh_comparar_criterio('gols_pro', $a, $b, []) > 0);
verificar('menos gols sofridos fica à frente',
    sh_comparar_criterio('gols_contra', $a, $b, []) < 0);
verificar('menos cartões fica à frente no fair play',
    sh_comparar_criterio('menos_cartoes', $a, $b, []) > 0);
verificar('mesmo número de vitórias não decide',
    sh_comparar_criterio('vitorias', $a, $b, []) === 0);
verificar('ordem alfabética decide por último',
    sh_comparar_criterio('nome', $a, $b, []) < 0);

grupo('Desempate — confronto direto');

$confrontos = [1 => [2 => 3], 2 => [1 => -3]];   // time 1 venceu o 2 por 3
verificar('quem venceu o confronto direto fica à frente',
    sh_comparar_criterio('confronto_direto', $a, $b, $confrontos) < 0);
verificar('o perdedor do confronto fica atrás',
    sh_comparar_criterio('confronto_direto', $b, $a, $confrontos) > 0);
verificar('sem confronto entre os dois, o critério não decide',
    sh_comparar_criterio('confronto_direto', $a, $b, []) === 0);
verificar('confronto empatado no agregado não decide',
    sh_comparar_criterio('confronto_direto', $a, $b, [1 => [2 => 0]]) === 0);

grupo('Desempate — ordenação completa');

/* Reproduz o usort de sh_classificacao(): pontos primeiro, depois a lista de
   critérios na ordem configurada. */
function sh_teste_ordenar(array $times, array $criterios, array $confrontos = []) {
    usort($times, function ($x, $y) use ($criterios, $confrontos) {
        if ($x['pontos'] !== $y['pontos']) return $y['pontos'] <=> $x['pontos'];
        foreach ($criterios as $c) {
            $r = sh_comparar_criterio($c, $x, $y, $confrontos);
            if ($r !== 0) return $r;
        }
        return 0;
    });
    return array_column($times, 'nome');
}

$base = [
    ['nome' => 'Beta',  'id' => 1, 'pontos' => 6, 'saldo' => 1, 'gols_pro' => 5,
     'gols_contra' => 4, 'vitorias' => 2, 'cartoes' => 0],
    ['nome' => 'Alfa',  'id' => 2, 'pontos' => 6, 'saldo' => 3, 'gols_pro' => 4,
     'gols_contra' => 1, 'vitorias' => 2, 'cartoes' => 5],
    ['nome' => 'Gama',  'id' => 3, 'pontos' => 9, 'saldo' => 0, 'gols_pro' => 3,
     'gols_contra' => 3, 'vitorias' => 3, 'cartoes' => 2],
];

igual('pontos vencem qualquer critério de desempate',
    ['Gama', 'Alfa', 'Beta'],
    sh_teste_ordenar($base, ['saldo', 'gols_pro', 'nome']));

igual('trocar o desempate para gols marcados muda a ordem',
    ['Gama', 'Beta', 'Alfa'],
    sh_teste_ordenar($base, ['gols_pro', 'nome']));

igual('fair play como primeiro critério inverte o empate',
    ['Gama', 'Beta', 'Alfa'],
    sh_teste_ordenar($base, ['menos_cartoes', 'nome']));

$iguais = [
    ['nome' => 'Zulu',  'id' => 1, 'pontos' => 3, 'saldo' => 0, 'gols_pro' => 2,
     'gols_contra' => 2, 'vitorias' => 1, 'cartoes' => 0],
    ['nome' => 'Alfa',  'id' => 2, 'pontos' => 3, 'saldo' => 0, 'gols_pro' => 2,
     'gols_contra' => 2, 'vitorias' => 1, 'cartoes' => 0],
];
igual('times idênticos saem sempre na mesma ordem (alfabética)',
    ['Alfa', 'Zulu'],
    sh_teste_ordenar($iguais, ['saldo', 'gols_pro', 'vitorias', 'nome']));

grupo('Configuração — critérios válidos');

verificar('a lista de critérios válidos inclui confronto direto',
    array_key_exists('confronto_direto', SH_CRITERIOS_VALIDOS));
verificar('a lista inclui fair play',
    array_key_exists('menos_cartoes', SH_CRITERIOS_VALIDOS));
igual('o padrão de vitória são 3 pontos', '3', SH_CONFIG_PADRAO['pontos_vitoria']);
igual('o padrão de empate é 1 ponto',     '1', SH_CONFIG_PADRAO['pontos_empate']);
verificar('a duração padrão da partida está definida',
    isset(SH_CONFIG_PADRAO['duracao_partida_min']));

grupo('Chaveamento — nome das fases (sh_nome_fase)');

igual('2 times',  'Final',             sh_nome_fase(2));
igual('4 times',  'Semifinal',         sh_nome_fase(4));
igual('8 times',  'Quartas de final',  sh_nome_fase(8));
igual('16 times', 'Oitavas de final',  sh_nome_fase(16));
igual('32 times', '16 avos de final',  sh_nome_fase(32));

grupo('Chaveamento — desenho da chave');

/* Reproduz o cálculo de sh_gerar_chaveamento(): tamanho da chave, pares
   simétricos e para onde vai cada vencedor. */
function sh_teste_chave($n_times) {
    $tamanho = 2;
    while ($tamanho < $n_times) $tamanho *= 2;

    $pares = [];
    for ($p = 1; $p <= $tamanho / 2; $p++) {
        $pares[] = [
            'a'      => $p - 1,
            'b'      => $tamanho - $p,
            'destino'=> (int)ceil($p / 2),
            'vaga'   => ($p % 2 === 1) ? 1 : 2,
        ];
    }
    return ['tamanho' => $tamanho, 'pares' => $pares];
}

$c8 = sh_teste_chave(8);
igual('8 classificados fazem uma chave de 8', 8, $c8['tamanho']);
igual('8 classificados geram 4 confrontos',   4, count($c8['pares']));
igual('o 1º enfrenta o 8º',  ['a' => 0, 'b' => 7, 'destino' => 1, 'vaga' => 1], $c8['pares'][0]);
igual('o 2º enfrenta o 7º',  ['a' => 1, 'b' => 6, 'destino' => 1, 'vaga' => 2], $c8['pares'][1]);
igual('o 4º enfrenta o 5º',  ['a' => 3, 'b' => 4, 'destino' => 2, 'vaga' => 2], $c8['pares'][3]);

$c6 = sh_teste_chave(6);
igual('6 classificados sobem para uma chave de 8', 8, $c6['tamanho']);
verificar('os dois melhores recebem passagem direta (o adversário não existe)',
    $c6['pares'][0]['b'] === 7 && $c6['pares'][1]['b'] === 6);

$c5 = sh_teste_chave(5);
igual('5 classificados também sobem para 8', 8, $c5['tamanho']);
$c2 = sh_teste_chave(2);
igual('2 classificados fazem só a final', 2, $c2['tamanho']);
igual('a final tem um único confronto',   1, count($c2['pares']));

/* Duas posições consecutivas sempre desaguam na mesma posição da fase
   seguinte, uma como time1 e outra como time2 — é o que faz a árvore fechar. */
$c16 = sh_teste_chave(16);
$fecha = true;
for ($i = 0; $i < count($c16['pares']); $i += 2) {
    if ($c16['pares'][$i]['destino'] !== $c16['pares'][$i + 1]['destino']) $fecha = false;
    if ($c16['pares'][$i]['vaga'] === $c16['pares'][$i + 1]['vaga']) $fecha = false;
}
verificar('cada par de confrontos alimenta a mesma posição seguinte', $fecha);

grupo('Desempate — ordem alfabética com acento (sh_comparar_nome)');

/* Regressão: `strcmp` compara bytes, e em UTF-8 todo nome acentuado começa
   com 0xC3 — maior que qualquer letra ASCII. "Águias" e "Órion" apareciam
   depois de "Zulu" na tabela. Num interclasse brasileiro isso é a regra, não
   a exceção: o banco de exemplo tem "Leões do 9ºA". */
$nomes = ['Zulu', 'Águias', 'Beta', 'Órion', 'Alfa', 'Leões do 9ºA'];
usort($nomes, 'sh_comparar_nome');

igual('nomes acentuados entram na posição alfabética certa',
    ['Águias', 'Alfa', 'Beta', 'Leões do 9ºA', 'Órion', 'Zulu'],
    $nomes);

verificar('Á vem antes de B', sh_comparar_nome('Águias', 'Beta') < 0);
verificar('Ç é tratado como C', sh_comparar_nome('Ação', 'Adão') < 0);
verificar('nomes idênticos empatam', sh_comparar_nome('Alfa', 'Alfa') === 0);
verificar('a comparação é estável quando a forma sem acento empata',
    sh_comparar_nome('Órion', 'Orion') !== 0);
