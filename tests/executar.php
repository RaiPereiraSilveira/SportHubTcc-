<?php
/**
 * tests/executar.php — executor de testes (SH-63)
 *
 * Uso:
 *     C:\xampp\php\php.exe tests\executar.php
 *     C:\xampp\php\php.exe tests\executar.php totp        (só um arquivo)
 *
 * Por que um executor próprio, e não PHPUnit: o projeto se propõe a rodar num
 * XAMPP recém-instalado, sem Composer, sem `vendor/`. Trazer uma dependência
 * de 30 MB para exercitar vinte funções puras seria desproporcional — e a
 * primeira coisa a quebrar na máquina de outra pessoa.
 *
 * O que é testado: as funções que dão resposta errada em silêncio. Um
 * dígito verificador de CPF, um código TOTP fora de sincronia, um CRC de Pix
 * errado ou um desempate na ordem trocada não emitem aviso nenhum — só
 * produzem um resultado que ninguém confere até alguém reclamar.
 *
 * O que NÃO é testado: telas e consultas ao banco. Isso é o roteiro de teste
 * de aceitação (SH-53, em docs/roteiro-teste-aceitacao.md), feito à mão antes
 * de cada entrega. Automatizar navegador exigiria outra pilha inteira.
 *
 * Os testes rodam SEM MySQL ligado — de propósito. Um teste que só passa com
 * o ambiente inteiro no ar é um teste que ninguém roda.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este arquivo roda apenas pela linha de comando.');
}

/* ── Mini-framework ──────────────────────────────────────────────────────── */

$GLOBALS['sh_testes'] = ['ok' => 0, 'falhas' => [], 'grupo' => '', 'total' => 0];

function grupo($nome) {
    $GLOBALS['sh_testes']['grupo'] = $nome;
    echo "\n\033[1m" . $nome . "\033[0m\n";
}

function verificar($descricao, $condicao, $detalhe = '') {
    $t = &$GLOBALS['sh_testes'];
    $t['total']++;
    if ($condicao) {
        $t['ok']++;
        echo "  \033[32m✓\033[0m " . $descricao . "\n";
    } else {
        $t['falhas'][] = $t['grupo'] . ' → ' . $descricao . ($detalhe !== '' ? ' (' . $detalhe . ')' : '');
        echo "  \033[31m✗ " . $descricao . "\033[0m"
           . ($detalhe !== '' ? "\n      " . $detalhe : '') . "\n";
    }
}

function igual($descricao, $esperado, $obtido) {
    verificar(
        $descricao,
        $esperado === $obtido,
        'esperado ' . var_export($esperado, true) . ', obtido ' . var_export($obtido, true)
    );
}

/* ── Carga ───────────────────────────────────────────────────────────────── */

// Valores de teste precisam existir antes de pagamento.php fixar os padrões.
define('SH_PIX_CHAVE',        'interclasse@escola.edu.br');
define('SH_PIX_BENEFICIARIO', 'COLEGIO EXEMPLO LTDA');
define('SH_PIX_CIDADE',       'CAMPINAS');

$raiz = dirname(__DIR__);
require_once $raiz . '/includes/config.php';
require_once $raiz . '/includes/consultas.php';
require_once $raiz . '/includes/campeonato.php';
require_once $raiz . '/includes/listagem.php';
require_once $raiz . '/includes/totp.php';
require_once $raiz . '/includes/qrcode.php';
require_once $raiz . '/includes/pdf.php';
require_once $raiz . '/includes/pagamento.php';
require_once $raiz . '/includes/email.php';

echo "\n" . str_repeat('═', 68) . "\n";
echo " SportHub — testes das funções puras (SH-63)\n";
echo " PHP " . PHP_VERSION . ($pdo === null ? " · sem banco (esperado)" : " · com banco") . "\n";
echo str_repeat('═', 68) . "\n";

$filtro = $argv[1] ?? '';
$arquivos = glob(__DIR__ . '/casos/*.php');
sort($arquivos);

foreach ($arquivos as $arquivo) {
    if ($filtro !== '' && strpos(basename($arquivo), $filtro) === false) continue;
    require $arquivo;
}

/* ── Resultado ───────────────────────────────────────────────────────────── */

$t = $GLOBALS['sh_testes'];
echo "\n" . str_repeat('─', 68) . "\n";

if (!$t['falhas']) {
    echo "\033[32m" . $t['ok'] . " de " . $t['total'] . " verificações passaram.\033[0m\n\n";
    exit(0);
}

echo "\033[31m" . count($t['falhas']) . " falha(s) em " . $t['total'] . " verificações:\033[0m\n";
foreach ($t['falhas'] as $f) {
    echo "  · " . $f . "\n";
}
echo "\n";
exit(1);
