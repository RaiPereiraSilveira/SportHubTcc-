<?php
// Retorna estatísticas e informações de times para um jogo (JSON)
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Endpoint interno do painel: só responde a quem está autenticado.
if (!isLogado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$jogo_id = isset($_GET['jogo_id']) ? (int) $_GET['jogo_id'] : 0;
if (!$jogo_id) {
    echo json_encode(['success' => false, 'error' => 'ID de jogo inválido']);
    exit;
}

try {
    // Buscar times do jogo
    $stmt = $pdo->prepare("SELECT j.time1_id, j.time2_id, t1.nome as time1_nome, t2.nome as time2_nome, j.placar_time1, j.placar_time2, j.status
        FROM jogos j
        JOIN times t1 ON j.time1_id = t1.id
        JOIN times t2 ON j.time2_id = t2.id
        WHERE j.id = ?");
    $stmt->execute([$jogo_id]);
    $j = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$j) {
        echo json_encode(['success' => false, 'error' => 'Jogo não encontrado']);
        exit;
    }

    $teams = [];
    foreach (['time1', 'time2'] as $pos) {
        $id = (int) $j[ $pos . '_id' ];
        $name = $j[ $pos . '_nome' ];
        // Local de imagens: procurar por várias extensões (png, jpg, jpeg, gif, webp)
        $found = false;
        $exts = ['png','jpg','jpeg','gif','webp'];
        $url = '../img/times.png';
        foreach ($exts as $e) {
            $relPath = '../img/times/' . $id . '.' . $e;
            $absPath = __DIR__ . '/../img/times/' . $id . '.' . $e;
            if (file_exists($absPath)) {
                $url = $relPath . '?v=' . filemtime($absPath);
                $found = true;
                break;
            }
        }

        $teams[] = [
            'id' => $id,
            'name' => $name,
            'logo' => $url,
            'score' => $pos === 'time1' ? (int)$j['placar_time1'] : (int)$j['placar_time2'],
            'stats' => [
                'cartao_amarelo' => 0,
                'cartao_vermelho' => 0,
                'gols' => 0,
                'substituicoes' => 0,
                'faltas' => 0,
                'escanteios' => 0,
            ],
        ];
    }

    // Agregar eventos (eventos_jogo)
    $ev = $pdo->prepare("SELECT time_id, tipo, COUNT(*) AS cnt FROM eventos_jogo WHERE jogo_id = ? GROUP BY time_id, tipo");
    $ev->execute([$jogo_id]);
    while ($r = $ev->fetch(PDO::FETCH_ASSOC)) {
        foreach ($teams as &$t) {
            if ($t['id'] == $r['time_id']) {
                if ($r['tipo'] === 'cartao_amarelo') $t['stats']['cartao_amarelo'] = (int)$r['cnt'];
                if ($r['tipo'] === 'cartao_vermelho') $t['stats']['cartao_vermelho'] = (int)$r['cnt'];
                if ($r['tipo'] === 'gol') $t['stats']['gols'] = (int)$r['cnt'];
                if ($r['tipo'] === 'substituicao') $t['stats']['substituicoes'] = (int)$r['cnt'];
            }
        }
        unset($t);
    }

    // Agregar estatísticas numéricas (estatisticas_jogo) — tipos: faltas, escanteios, etc.
    $st = $pdo->prepare("SELECT time_id, tipo, valor FROM estatisticas_jogo WHERE jogo_id = ?");
    $st->execute([$jogo_id]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        foreach ($teams as &$t) {
            if ($t['id'] == $r['time_id']) {
                $tipo = strtolower($r['tipo']);
                $val = is_numeric($r['valor']) ? (int)$r['valor'] : $r['valor'];
                if ($tipo === 'faltas') $t['stats']['faltas'] = $val;
                if ($tipo === 'escanteios' || $tipo === 'escanteio') $t['stats']['escanteios'] = $val;
            }
        }
        unset($t);
    }

    echo json_encode(['success' => true, 'data' => ['jogo_id' => $jogo_id, 'status' => $j['status'], 'teams' => $teams]]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro no servidor']);
    exit;
}

?>
