<?php
/**
 * arbitro/sumula_pdf.php — súmula da partida em PDF (SH-45)
 *
 * É o documento que a coordenação imprime, o árbitro assina e a escola
 * arquiva. Imprimir a tela pelo navegador não servia: sai com o menu do
 * painel, a URL no rodapé e a paginação que o Chrome decidir.
 *
 * Quem pode baixar:
 *   · o árbitro designado para a partida;
 *   · qualquer administrador.
 *
 * Aluno não baixa. A súmula traz nome de jogador ligado a cartão e a
 * ocorrência disciplinar — informação sobre menor de idade cuja finalidade é
 * a organização do campeonato, não a divulgação. O placar e os gols, que são
 * públicos, o aluno já vê em Resultados.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pdf.php';

verificarLogin();

$jogo_id = (int)($_GET['jogo_id'] ?? 0);
$eh_admin = isAdmin();

if (!$eh_admin && !isArbitro()) {
    http_response_code(403);
    exit('Sem permissão para acessar esta súmula.');
}

try {
    $sql = "SELECT j.*, m.nome AS modalidade,
                   t1.nome AS time1_nome, t1.sala AS time1_sala,
                   t2.nome AS time2_nome, t2.sala AS time2_sala,
                   u.nome  AS arbitro_nome
              FROM jogos j
              JOIN modalidades m ON m.id = j.modalidade_id
              JOIN times t1      ON t1.id = j.time1_id
              JOIN times t2      ON t2.id = j.time2_id
         LEFT JOIN usuarios u    ON u.id  = j.arbitro_id
             WHERE j.id = ?";
    $params = [$jogo_id];

    // O árbitro só emite a súmula das partidas dele.
    if (!$eh_admin) {
        $sql .= ' AND j.arbitro_id = ?';
        $params[] = (int)$_SESSION['usuario_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jogo = $stmt->fetch();
} catch (PDOException $e) {
    $ref = sh_log_excecao($e, 'carregar a partida para a súmula em PDF');
    http_response_code(500);
    exit('Não foi possível gerar a súmula (referência ' . $ref . ').');
}

if (!$jogo) {
    http_response_code(404);
    exit('Partida não encontrada, ou você não é o árbitro designado para ela.');
}

/* Eventos e estatísticas registrados. */
$eventos = [];
$stats   = [];
$ocorrencias = [];
try {
    $stmt = $pdo->prepare("SELECT ev.*, t.nome AS time_nome
                             FROM eventos_jogo ev
                             JOIN times t ON t.id = ev.time_id
                            WHERE ev.jogo_id = ?
                         ORDER BY ev.minuto IS NULL, ev.minuto, ev.id");
    $stmt->execute([$jogo_id]);
    $eventos = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT time_id, tipo, valor FROM estatisticas_jogo WHERE jogo_id = ?');
    $stmt->execute([$jogo_id]);
    foreach ($stmt->fetchAll() as $s) {
        $stats[(int)$s['time_id']][$s['tipo']] = $s['valor'];
    }

    if (sh_tabela_existe($pdo, 'ocorrencias')) {
        $stmt = $pdo->prepare('SELECT * FROM ocorrencias WHERE jogo_id = ? ORDER BY id');
        $stmt->execute([$jogo_id]);
        $ocorrencias = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    sh_log_excecao($e, 'carregar eventos para a súmula em PDF');
}

sh_auditar($pdo, 'sumula_pdf_emitida', 'jogos', $jogo_id);

/* ── Montagem do documento ──────────────────────────────────────────────── */

$rotulos_evento = [
    'gol'             => 'Gol',
    'cartao_amarelo'  => 'Cartão amarelo',
    'cartao_vermelho' => 'Cartão vermelho',
    'substituicao'    => 'Substituição',
];
$rotulos_stat = [
    'posse_bola'   => 'Posse de bola (%)',
    'finalizacoes' => 'Finalizações',
    'escanteios'   => 'Escanteios',
    'faltas'       => 'Faltas',
];

$pdf = new ShPdf('Súmula — ' . $jogo['time1_nome'] . ' x ' . $jogo['time2_nome']);
$pdf->pagina();

$M   = 45;                       // margem esquerda
$D   = ShPdf::LARGURA - 45;      // margem direita
$y   = 55;

/* Cabeçalho */
$pdf->cor(15, 122, 85)->retangulo($M, $y - 22, $D - $M, 3, 'f');
$pdf->cor(20, 32, 27)->fonte('F2', 20)->texto($M, $y, 'SÚMULA DA PARTIDA');
$pdf->fonte('F1', 9)->cor(90, 105, 98)
    ->texto_direita($D, $y - 8, SH_NOME . ' — Interclasse')
    ->texto_direita($D, $y + 4, 'Emitida em ' . date('d/m/Y \à\s H:i'));

$y += 22;
$pdf->cor_traco(200, 210, 203)->linha($M, $y, $D, $y);

/* Identificação */
$y += 22;
$pdf->cor(20, 32, 27)->fonte('F2', 11)->texto($M, $y, mb_strtoupper($jogo['modalidade'], 'UTF-8')
    . ($jogo['fase'] ? '  ·  ' . $jogo['fase'] : ''));

$y += 20;
$pdf->fonte('F1', 9)->cor(90, 105, 98);
$identificacao = [
    ['Data',    !empty($jogo['data_jogo']) ? date('d/m/Y', strtotime($jogo['data_jogo'])) : 'não definida'],
    ['Horário', !empty($jogo['hora']) ? substr($jogo['hora'], 0, 5) : 'não definido'],
    ['Local',   $jogo['local'] ?: 'não informado'],
    ['Árbitro', $jogo['arbitro_nome'] ?: 'não designado'],
    ['Situação', ['agendado' => 'Agendada', 'em_andamento' => 'Em andamento',
                  'finalizado' => 'Encerrada'][$jogo['status']] ?? $jogo['status']],
];
$coluna = $M;
foreach ($identificacao as [$rotulo, $valor]) {
    $pdf->cor(130, 145, 138)->fonte('F1', 7.5)->texto($coluna, $y, mb_strtoupper($rotulo, 'UTF-8'));
    $pdf->cor(20, 32, 27)->fonte('F1', 9.5)->texto($coluna, $y + 12, $valor);
    $coluna += ($D - $M) / count($identificacao);
}

/* Placar */
$y += 40;
$pdf->cor(245, 248, 243)->retangulo($M, $y, $D - $M, 66, 'f');
$meio = $M + ($D - $M) / 2;

$pdf->cor(20, 32, 27)->fonte('F2', 13);
$pdf->texto_centro($M + 10, $meio - 40, $y + 26, $jogo['time1_nome']);
$pdf->texto_centro($meio + 40, $D - 10, $y + 26, $jogo['time2_nome']);

$pdf->fonte('F1', 8)->cor(120, 135, 128);
$pdf->texto_centro($M + 10, $meio - 40, $y + 42, $jogo['time1_sala'] ?: '');
$pdf->texto_centro($meio + 40, $D - 10, $y + 42, $jogo['time2_sala'] ?: '');

$pdf->cor(15, 122, 85)->fonte('F2', 28);
$pdf->texto_centro($meio - 70, $meio - 10, $y + 44, (string)(int)$jogo['placar_time1']);
$pdf->texto_centro($meio + 10, $meio + 70, $y + 44, (string)(int)$jogo['placar_time2']);
$pdf->cor(160, 175, 168)->fonte('F1', 14)->texto_centro($meio - 8, $meio + 8, $y + 42, 'x');

$y += 88;

/* Eventos */
$pdf->cor(20, 32, 27)->fonte('F2', 11)->texto($M, $y, 'EVENTOS DA PARTIDA');
$y += 8;
$pdf->cor_traco(220, 228, 222)->linha($M, $y, $D, $y);
$y += 18;

if ($eventos) {
    $pdf->fonte('F2', 8)->cor(130, 145, 138);
    $pdf->texto($M, $y, 'MIN');
    $pdf->texto($M + 40, $y, 'OCORRÊNCIA');
    $pdf->texto($M + 175, $y, 'ATLETA');
    $pdf->texto($M + 330, $y, 'TIME');
    $y += 6;
    $pdf->cor_traco(235, 240, 236)->linha($M, $y, $D, $y);
    $y += 14;

    foreach ($eventos as $i => $ev) {
        if ($y > ShPdf::ALTURA - 110) {           // cabe mais uma linha?
            $pdf->pagina();
            $y = 55;
            $pdf->cor(20, 32, 27)->fonte('F2', 11)->texto($M, $y, 'EVENTOS DA PARTIDA (continuação)');
            $y += 24;
        }
        if ($i % 2 === 1) {
            $pdf->cor(248, 250, 247)->retangulo($M - 4, $y - 10, $D - $M + 8, 16, 'f');
        }
        $pdf->cor(20, 32, 27)->fonte('F1', 9);
        $pdf->texto($M, $y, $ev['minuto'] !== null ? $ev['minuto'] . "'" : '—');
        $pdf->texto($M + 40, $y, $rotulos_evento[$ev['tipo']] ?? $ev['tipo']);
        $pdf->texto($M + 175, $y, mb_substr((string)($ev['jogador'] ?: 'não informado'), 0, 28));
        $pdf->cor(90, 105, 98)->texto($M + 330, $y, mb_substr($ev['time_nome'], 0, 26));
        $y += 16;
    }
} else {
    $pdf->fonte('F3', 9)->cor(130, 145, 138)
        ->texto($M, $y, 'Nenhum gol, cartão ou substituição foi registrado nesta partida.');
    $y += 16;
}

/* Estatísticas por time, quando houver */
if ($stats) {
    $y += 16;
    $pdf->cor(20, 32, 27)->fonte('F2', 11)->texto($M, $y, 'ESTATÍSTICAS');
    $y += 8;
    $pdf->cor_traco(220, 228, 222)->linha($M, $y, $D, $y);
    $y += 18;

    foreach ($rotulos_stat as $chave => $rotulo) {
        $v1 = $stats[(int)$jogo['time1_id']][$chave] ?? null;
        $v2 = $stats[(int)$jogo['time2_id']][$chave] ?? null;
        if ($v1 === null && $v2 === null) continue;

        $pdf->cor(20, 32, 27)->fonte('F1', 9);
        $pdf->texto($M, $y, (string)($v1 ?? '—'));
        $pdf->cor(90, 105, 98)->texto_centro($M + 60, $D - 60, $y, $rotulo);
        $pdf->cor(20, 32, 27)->texto_direita($D, $y, (string)($v2 ?? '—'));
        $y += 15;
    }
}

/* Ocorrências disciplinares (SH-73), quando houver */
if ($ocorrencias) {
    $y += 16;
    $pdf->cor(20, 32, 27)->fonte('F2', 11)->texto($M, $y, 'OCORRÊNCIAS DISCIPLINARES');
    $y += 8;
    $pdf->cor_traco(220, 228, 222)->linha($M, $y, $D, $y);
    $y += 18;

    foreach ($ocorrencias as $o) {
        if ($y > ShPdf::ALTURA - 130) { $pdf->pagina(); $y = 55; }
        $pdf->cor(20, 32, 27)->fonte('F2', 9)
            ->texto($M, $y, mb_strtoupper($o['tipo'], 'UTF-8') . ' — ' . ($o['jogador_nome'] ?: 'time'));
        $y += 13;
        $pdf->cor(70, 85, 78)->fonte('F1', 9);
        $y = $pdf->paragrafo($M, $y, $D - $M, $o['descricao']);
        $y += 6;
    }
}

/* Observações do árbitro */
if (!empty($jogo['observacoes'])) {
    $y += 16;
    if ($y > ShPdf::ALTURA - 160) { $pdf->pagina(); $y = 55; }
    $pdf->cor(20, 32, 27)->fonte('F2', 11)->texto($M, $y, 'OBSERVAÇÕES DO ÁRBITRO');
    $y += 8;
    $pdf->cor_traco(220, 228, 222)->linha($M, $y, $D, $y);
    $y += 18;
    $pdf->cor(70, 85, 78)->fonte('F1', 9.5);
    $y = $pdf->paragrafo($M, $y, $D - $M, $jogo['observacoes']);
}

/* Assinaturas — a razão de o documento existir em papel. */
if ($y > ShPdf::ALTURA - 150) { $pdf->pagina(); $y = 55; }
$y = max($y + 50, ShPdf::ALTURA - 130);

$largura_linha = ($D - $M - 40) / 2;
$pdf->cor_traco(150, 165, 158);
$pdf->linha($M, $y, $M + $largura_linha, $y);
$pdf->linha($D - $largura_linha, $y, $D, $y);

$pdf->cor(90, 105, 98)->fonte('F1', 8);
$pdf->texto_centro($M, $M + $largura_linha, $y + 14,
    'Árbitro — ' . ($jogo['arbitro_nome'] ?: 'nome e assinatura'));
$pdf->texto_centro($D - $largura_linha, $D, $y + 14, 'Coordenação — nome e assinatura');

/* Rodapé */
$pdf->cor(150, 165, 158)->fonte('F1', 7.5);
$pdf->texto($M, ShPdf::ALTURA - 40,
    'Documento gerado eletronicamente pelo ' . SH_NOME . '. Partida nº ' . $jogo_id . '.');
$pdf->texto($M, ShPdf::ALTURA - 30,
    'Contém dado pessoal de participante: uso restrito à organização do campeonato (LGPD, art. 7º e 14).');

$pdf->enviar('sumula-' . $jogo_id . '-' . date('Ymd') . '.pdf', true);
