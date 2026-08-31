<?php
/**
 * includes/consultas.php — consultas do campeonato usadas por mais de uma tela.
 *
 * Classificação, resultados e agenda apareciam escritos à mão em cada página
 * (painel do aluno, exportação CSV, feed .ics, telão). Quando a regra de
 * pontuação muda, uma cópia sempre fica para trás. Aqui há uma só.
 *
 * Nota sobre a correção da classificação (SH-40): a versão anterior contava
 * vitórias, empates e derrotas SEM filtrar por status. Como placar_time1 e
 * placar_time2 nascem em 0, todo jogo apenas agendado entrava na conta como
 * empate para os dois times — e a tabela mostrava pontos de partidas que ainda
 * nem tinham acontecido. O filtro `status = 'finalizado'` mora agora na
 * própria junção, junto do critério de desempate por saldo de gols.
 */

require_once __DIR__ . '/config.php';

/* ══ Configuração do campeonato (SH-56) ══════════════════════════════════
   A regra "vitória vale 3, empate vale 1, desempate por saldo" estava
   escrita dentro da consulta da classificação. Funciona — até a escola
   decidir que no vôlei a vitória vale 2, ou que o desempate do interclasse
   é confronto direto antes de saldo. Aí não havia onde mexer sem editar PHP.

   Os critérios aceitos e o que cada um significa:

     saldo             gols marcados menos gols sofridos
     gols_pro          total de gols marcados
     gols_contra       menos gols sofridos (invertido: menos é melhor)
     vitorias          número de vitórias
     confronto_direto  quem venceu o jogo entre os dois times empatados
     menos_cartoes     fair play: menos cartões recebidos
     nome              ordem alfabética — desempate final, sempre estável

   `nome` é acrescentado automaticamente ao fim de qualquer lista, para que
   dois carregamentos da mesma tabela nunca troquem a ordem de dois times
   rigorosamente iguais.                                                    */

const SH_CRITERIOS_VALIDOS = [
    'saldo'            => 'Saldo de gols',
    'gols_pro'         => 'Gols marcados',
    'gols_contra'      => 'Menos gols sofridos',
    'vitorias'         => 'Número de vitórias',
    'confronto_direto' => 'Confronto direto',
    'menos_cartoes'    => 'Fair play (menos cartões)',
    'nome'             => 'Ordem alfabética',
];

const SH_CONFIG_PADRAO = [
    'pontos_vitoria'      => '3',
    'pontos_empate'       => '1',
    'pontos_derrota'      => '0',
    'criterios_desempate' => 'saldo,gols_pro,vitorias,confronto_direto,nome',
    'duracao_partida_min' => '60',
];

/** Configuração vigente, com os padrões preenchendo o que faltar. */
function sh_config_campeonato(PDO $pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;

    $config = SH_CONFIG_PADRAO;
    if (!sh_tabela_existe($pdo, 'config_campeonato')) return $cache = $config;

    $escola = sh_escola_atual($pdo);
    try {
        // A configuração da escola vence a geral (escola_id NULL).
        $stmt = $pdo->prepare(
            'SELECT chave, valor FROM config_campeonato
              WHERE escola_id IS NULL OR escola_id = ?
              ORDER BY escola_id IS NULL DESC'
        );
        $stmt->execute([$escola]);
        foreach ($stmt->fetchAll() as $linha) {
            $config[$linha['chave']] = $linha['valor'];
        }
    } catch (PDOException $e) {
        sh_log_excecao($e, 'carregar a configuração do campeonato');
    }
    return $cache = $config;
}

/** Grava uma configuração. Devolve true quando gravou. */
function sh_salvar_config_campeonato(PDO $pdo, array $valores) {
    if (!sh_tabela_existe($pdo, 'config_campeonato')) return false;
    $escola = sh_escola_atual($pdo);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO config_campeonato (escola_id, chave, valor) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        foreach ($valores as $chave => $valor) {
            if (!array_key_exists($chave, SH_CONFIG_PADRAO)) continue;
            $stmt->execute([$escola, $chave, (string)$valor]);
        }
        return true;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'salvar a configuração do campeonato');
        return false;
    }
}

/** Lista de critérios de desempate já validada e terminada em `nome`. */
function sh_criterios_desempate(PDO $pdo) {
    $config    = sh_config_campeonato($pdo);
    $criterios = array_filter(array_map('trim', explode(',', $config['criterios_desempate'])));
    $criterios = array_values(array_intersect($criterios, array_keys(SH_CRITERIOS_VALIDOS)));

    if (!$criterios) $criterios = ['saldo', 'gols_pro', 'vitorias'];
    if (end($criterios) !== 'nome') $criterios[] = 'nome';
    return $criterios;
}

/** Cartões por time — alimenta o critério de desempate `menos_cartoes`. */
function sh_cartoes_por_time(PDO $pdo, $modalidade_id = null) {
    $filtros = ["j.status = 'finalizado'"];
    $params  = [];
    if ($modalidade_id !== null && (int)$modalidade_id > 0) {
        $filtros[] = 'j.modalidade_id = ?';
        $params[]  = (int)$modalidade_id;
    }

    try {
        $stmt = $pdo->prepare('
            SELECT ev.time_id,
                   SUM(CASE WHEN ev.tipo = \'cartao_amarelo\'  THEN 1 ELSE 0 END)
                 + SUM(CASE WHEN ev.tipo = \'cartao_vermelho\' THEN 3 ELSE 0 END) AS pontos
              FROM eventos_jogo ev
              JOIN jogos j ON j.id = ev.jogo_id
             WHERE ' . implode(' AND ', $filtros) . '
          GROUP BY ev.time_id
        ');
        $stmt->execute($params);
        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[(int)$l['time_id']] = (int)$l['pontos'];
        }
        return $mapa;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'contar cartões por time');
        return [];
    }
}

/**
 * Confrontos diretos encerrados: [timeA][timeB] => saldo de A contra B.
 * Alimenta o critério `confronto_direto`.
 */
function sh_confrontos_diretos(PDO $pdo, $modalidade_id = null) {
    $filtros = ["status = 'finalizado'"];
    $params  = [];
    if ($modalidade_id !== null && (int)$modalidade_id > 0) {
        $filtros[] = 'modalidade_id = ?';
        $params[]  = (int)$modalidade_id;
    }

    try {
        $stmt = $pdo->prepare('SELECT time1_id, time2_id, placar_time1, placar_time2
                                 FROM jogos WHERE ' . implode(' AND ', $filtros));
        $stmt->execute($params);
        $mapa = [];
        foreach ($stmt->fetchAll() as $j) {
            $a = (int)$j['time1_id'];
            $b = (int)$j['time2_id'];
            $d = (int)$j['placar_time1'] - (int)$j['placar_time2'];
            $mapa[$a][$b] = ($mapa[$a][$b] ?? 0) + $d;
            $mapa[$b][$a] = ($mapa[$b][$a] ?? 0) - $d;
        }
        return $mapa;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'apurar confrontos diretos');
        return [];
    }
}

/**
 * Classificação geral, já ordenada.
 *
 * A pontuação e a ordem de desempate vêm de `config_campeonato` (SH-56); os
 * padrões — vitória 3, empate 1, desempate por saldo, gols marcados, vitórias
 * e confronto direto — reproduzem exatamente o comportamento anterior, então
 * quem não configurar nada não vê diferença nenhuma.
 *
 * `nome` é sempre o último critério, para que a ordem seja estável entre dois
 * carregamentos quando dois times forem rigorosamente iguais.
 *
 * @param int|null $modalidade_id  restringe a uma modalidade; null = todas
 * @return array<int, array<string, mixed>>
 */
function sh_classificacao(PDO $pdo, $modalidade_id = null) {
    $filtro = '';
    $params = [];
    if ($modalidade_id !== null && (int)$modalidade_id > 0) {
        $filtro   = ' AND j.modalidade_id = ?';
        $params[] = (int)$modalidade_id;
    }

    // Isolamento multi-escola (SH-68): vazio quando há uma escola só.
    $escola = sh_filtro_escola($pdo, 't');

    $sql = "
        SELECT t.id, t.nome, t.sala, t.genero,
               COUNT(j.id) AS jogos,
               COALESCE(SUM(CASE WHEN (j.time1_id = t.id AND j.placar_time1 > j.placar_time2)
                                   OR (j.time2_id = t.id AND j.placar_time2 > j.placar_time1)
                                 THEN 1 ELSE 0 END), 0) AS vitorias,
               COALESCE(SUM(CASE WHEN j.placar_time1 = j.placar_time2
                                 THEN 1 ELSE 0 END), 0) AS empates,
               COALESCE(SUM(CASE WHEN (j.time1_id = t.id AND j.placar_time1 < j.placar_time2)
                                   OR (j.time2_id = t.id AND j.placar_time2 < j.placar_time1)
                                 THEN 1 ELSE 0 END), 0) AS derrotas,
               COALESCE(SUM(CASE WHEN j.time1_id = t.id THEN j.placar_time1
                                 ELSE j.placar_time2 END), 0) AS gols_pro,
               COALESCE(SUM(CASE WHEN j.time1_id = t.id THEN j.placar_time2
                                 ELSE j.placar_time1 END), 0) AS gols_contra
          FROM times t
          LEFT JOIN jogos j
                 ON (j.time1_id = t.id OR j.time2_id = t.id)
                AND j.status = 'finalizado'
                $filtro
         WHERE 1 = 1 {$escola['sql']}
         GROUP BY t.id, t.nome, t.sala, t.genero
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, $escola['params']));
        $linhas = $stmt->fetchAll();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'montar a classificação');
        return [];
    }

    $config      = sh_config_campeonato($pdo);
    $por_vitoria = (int)$config['pontos_vitoria'];
    $por_empate  = (int)$config['pontos_empate'];
    $por_derrota = (int)$config['pontos_derrota'];
    $criterios   = sh_criterios_desempate($pdo);

    // Só consulta o que os critérios escolhidos realmente pedem.
    $cartoes    = in_array('menos_cartoes', $criterios, true)
        ? sh_cartoes_por_time($pdo, $modalidade_id) : [];
    $confrontos = in_array('confronto_direto', $criterios, true)
        ? sh_confrontos_diretos($pdo, $modalidade_id) : [];

    foreach ($linhas as &$linha) {
        foreach (['jogos', 'vitorias', 'empates', 'derrotas', 'gols_pro', 'gols_contra'] as $c) {
            $linha[$c] = (int)$linha[$c];
        }
        $linha['saldo']    = $linha['gols_pro'] - $linha['gols_contra'];
        $linha['pontos']   = $linha['vitorias'] * $por_vitoria
                           + $linha['empates']  * $por_empate
                           + $linha['derrotas'] * $por_derrota;
        $linha['cartoes']  = $cartoes[(int)$linha['id']] ?? 0;
    }
    unset($linha);

    usort($linhas, function ($a, $b) use ($criterios, $confrontos) {
        if ($a['pontos'] !== $b['pontos']) return $b['pontos'] <=> $a['pontos'];

        foreach ($criterios as $criterio) {
            $r = sh_comparar_criterio($criterio, $a, $b, $confrontos);
            if ($r !== 0) return $r;
        }
        return 0;
    });

    return $linhas;
}

/**
 * Compara dois times por um critério de desempate.
 * Devolve <0 quando $a fica à frente, >0 quando $b fica, 0 quando empatam.
 */
function sh_comparar_criterio($criterio, array $a, array $b, array $confrontos) {
    switch ($criterio) {
        case 'saldo':         return $b['saldo']       <=> $a['saldo'];
        case 'gols_pro':      return $b['gols_pro']    <=> $a['gols_pro'];
        case 'gols_contra':   return $a['gols_contra'] <=> $b['gols_contra'];   // menos é melhor
        case 'vitorias':      return $b['vitorias']    <=> $a['vitorias'];
        case 'menos_cartoes': return $a['cartoes']     <=> $b['cartoes'];       // menos é melhor

        case 'confronto_direto':
            /* Só decide quando os dois se enfrentaram e houve vencedor no
               agregado. Num triplo empate o confronto direto entre A e B não
               ordena C, e é por isso que ele nunca é o último critério: a
               lista sempre termina em `nome`. */
            $saldo = $confrontos[(int)$a['id']][(int)$b['id']] ?? 0;
            return $saldo === 0 ? 0 : ($saldo > 0 ? -1 : 1);

        case 'nome':
        default:
            return sh_comparar_nome($a['nome'], $b['nome']);
    }
}

/**
 * Compara dois nomes em ordem alfabética de português.
 *
 * `strcmp` compara byte a byte. Em UTF-8, "Á" começa com 0xC3 e "Z" com 0x5A,
 * então strcmp joga TODO nome acentuado para depois de todo nome sem acento:
 * "Águias" e "Órion" apareciam atrás de "Zulu" na tabela. Num interclasse
 * brasileiro isso não é caso raro — o próprio banco de exemplo tem
 * "Leões do 9ºA".
 *
 * A saída é comparar a forma sem acento (á→a, ç→c) em minúsculas. Empate
 * nessa forma cai no strcmp original, para que a ordem continue determinística
 * entre dois carregamentos.
 *
 * Isto vale para a ordenação feita em PHP. O `ORDER BY` do MySQL já acerta
 * sozinho, porque as tabelas usam a colação utf8mb4_unicode_ci.
 */
function sh_comparar_nome($a, $b) {
    $normalizar = function ($texto) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$texto);
        if ($ascii === false) $ascii = (string)$texto;
        // O //TRANSLIT do iconv às vezes devolve "a'" ou "~a": fica só a letra.
        $ascii = preg_replace('/[^A-Za-z0-9 ]/', '', $ascii);
        return mb_strtolower(trim($ascii));
    };

    $na = $normalizar($a);
    $nb = $normalizar($b);

    $r = strcmp($na, $nb);
    return $r !== 0 ? $r : strcmp((string)$a, (string)$b);
}

/**
 * Jogos já encerrados, do mais recente para o mais antigo.
 *
 * @param int|null $modalidade_id  restringe a uma modalidade; null = todas
 */
function sh_resultados(PDO $pdo, $modalidade_id = null) {
    $filtro = '';
    $params = [];
    if ($modalidade_id !== null && (int)$modalidade_id > 0) {
        $filtro   = ' AND j.modalidade_id = ?';
        $params[] = (int)$modalidade_id;
    }

    // Isolamento multi-escola (SH-68): vazio quando há uma escola só.
    $escola  = sh_filtro_escola($pdo, 'j');
    $filtro .= $escola['sql'];
    $params  = array_merge($params, $escola['params']);

    try {
        $stmt = $pdo->prepare("
            SELECT j.*, m.nome AS modalidade,
                   t1.nome AS time1_nome, t2.nome AS time2_nome,
                   u.nome  AS arbitro_nome
              FROM jogos j
              JOIN modalidades m ON j.modalidade_id = m.id
              JOIN times t1      ON j.time1_id = t1.id
              JOIN times t2      ON j.time2_id = t2.id
         LEFT JOIN usuarios u    ON j.arbitro_id = u.id
             WHERE j.status = 'finalizado' $filtro
          ORDER BY j.data_jogo DESC, j.hora DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'carregar os resultados');
        return [];
    }
}

/**
 * Agenda de partidas — usada pelo calendário .ics e pelo telão.
 *
 * @param int|null $modalidade_id  restringe a uma modalidade; null = todas
 * @param bool     $so_futuros     descarta o que já passou de ontem
 */
function sh_agenda(PDO $pdo, $modalidade_id = null, $so_futuros = false) {
    $filtros = ["j.status <> 'finalizado'"];
    $params  = [];

    if ($modalidade_id !== null && (int)$modalidade_id > 0) {
        $filtros[] = 'j.modalidade_id = ?';
        $params[]  = (int)$modalidade_id;
    }
    if ($so_futuros) {
        $filtros[] = 'j.data_jogo >= CURDATE()';
    }

    // Isolamento multi-escola (SH-68): vazio quando há uma escola só.
    $escola = sh_filtro_escola($pdo, 'j');
    $sufixo = $escola['sql'];
    $params = array_merge($params, $escola['params']);

    try {
        $stmt = $pdo->prepare("
            SELECT j.*, m.nome AS modalidade,
                   t1.nome AS time1_nome, t2.nome AS time2_nome
              FROM jogos j
              JOIN modalidades m ON j.modalidade_id = m.id
              JOIN times t1      ON j.time1_id = t1.id
              JOIN times t2      ON j.time2_id = t2.id
             WHERE " . implode(' AND ', $filtros) . $sufixo . "
          ORDER BY j.data_jogo, j.hora
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'carregar a agenda');
        return [];
    }
}

/**
 * Caminho web do escudo de um time, com cache-busting pelo mtime.
 *
 * Os escudos são gravados como img/times/{id}.{ext}. Quando não há escudo,
 * devolve a imagem genérica — a tela nunca fica com um quadrado quebrado.
 */
function sh_escudo_time($time_id) {
    $time_id = (int)$time_id;
    foreach (['png', 'webp', 'jpg', 'jpeg', 'gif'] as $ext) {
        $absoluto = dirname(__DIR__) . '/img/times/' . $time_id . '.' . $ext;
        if (is_file($absoluto)) {
            return sh_url('img/times/' . $time_id . '.' . $ext) . '?v=' . filemtime($absoluto);
        }
    }
    return sh_url('img/times.png');
}

/** Modalidades cadastradas, para os seletores de filtro. */
function sh_lista_modalidades(PDO $pdo) {
    try {
        return $pdo->query("SELECT id, nome, genero FROM modalidades ORDER BY nome")->fetchAll();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'listar modalidades');
        return [];
    }
}

/* ══ Conflito de horário na escala (SH-38) ═══════════════════════════════
   A coordenação designava um árbitro para dois jogos às 14h do mesmo sábado
   e só descobria no dia, com as duas quadras esperando. O sistema aceitava
   sem dizer nada — a designação era um UPDATE solto, sem nenhuma verificação.

   São três choques diferentes, e vale separar porque a gravidade não é a
   mesma:

     arbitro  a mesma pessoa em duas partidas sobrepostas. É impedimento:
              ninguém apita dois jogos ao mesmo tempo.
     time     o mesmo time em duas partidas sobrepostas. Também impedimento,
              pelo mesmo motivo.
     local    duas partidas na mesma quadra no mesmo horário. É AVISO, não
              impedimento: "Quadra 1" pode ser o nome que a escola deu para
              um ginásio com duas quadras, e o xadrez e o tênis de mesa
              convivem no mesmo salão sem problema.

   A janela de sobreposição usa `duracao_partida_min` da configuração do
   campeonato (SH-56) — 60 minutos por padrão. Dois jogos colidem quando o
   começo de um cai dentro da duração do outro.
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Procura choques de agenda para uma partida.
 *
 * @param int    $jogo_id    a partida sendo agendada (excluída da busca)
 * @param string $data       'AAAA-MM-DD'
 * @param string $hora       'HH:MM' ou 'HH:MM:SS'
 * @param array  $opcoes     ['arbitro_id'=>int|null, 'local'=>string|null,
 *                            'time1_id'=>int|null, 'time2_id'=>int|null]
 * @return array{impedimentos: string[], avisos: string[]}
 */
function sh_conflitos_agenda(PDO $pdo, $jogo_id, $data, $hora, array $opcoes = []) {
    $vazio = ['impedimentos' => [], 'avisos' => []];

    $data = trim((string)$data);
    $hora = trim((string)$hora);
    if ($data === '' || $hora === '') return $vazio;   // sem horário não há choque
    if (strlen($hora) === 5) $hora .= ':00';

    $config   = sh_config_campeonato($pdo);
    $duracao  = max(10, min(300, (int)$config['duracao_partida_min']));

    /* Dois jogos se sobrepõem quando a diferença entre os inícios é menor
       que a duração. Comparar em minutos evita depender de o MySQL saber
       somar INTERVAL a um TIME que pode ser nulo. */
    try {
        $stmt = $pdo->prepare("
            SELECT j.id, j.hora, j.local, j.arbitro_id, j.time1_id, j.time2_id,
                   m.nome  AS modalidade,
                   t1.nome AS time1_nome, t2.nome AS time2_nome,
                   u.nome  AS arbitro_nome
              FROM jogos j
              JOIN modalidades m ON m.id = j.modalidade_id
              JOIN times t1      ON t1.id = j.time1_id
              JOIN times t2      ON t2.id = j.time2_id
         LEFT JOIN usuarios u    ON u.id  = j.arbitro_id
             WHERE j.data_jogo = ?
               AND j.hora IS NOT NULL
               AND j.id <> ?
               AND j.status <> 'finalizado'
               AND ABS(TIME_TO_SEC(j.hora) - TIME_TO_SEC(?)) < ?
        ");
        $stmt->execute([$data, (int)$jogo_id, $hora, $duracao * 60]);
        $vizinhos = $stmt->fetchAll();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'procurar conflitos de agenda');
        return $vazio;      // na dúvida, não trava o trabalho da coordenação
    }
    if (!$vizinhos) return $vazio;

    $arbitro = isset($opcoes['arbitro_id']) ? (int)$opcoes['arbitro_id'] : 0;
    $local   = trim((string)($opcoes['local'] ?? ''));
    $times   = array_filter([(int)($opcoes['time1_id'] ?? 0), (int)($opcoes['time2_id'] ?? 0)]);

    $impedimentos = [];
    $avisos       = [];

    foreach ($vizinhos as $v) {
        $quando  = substr($v['hora'], 0, 5);
        $partida = $v['time1_nome'] . ' × ' . $v['time2_nome']
                 . ' (' . $v['modalidade'] . ', ' . $quando . ')';

        if ($arbitro > 0 && (int)$v['arbitro_id'] === $arbitro) {
            $impedimentos[] = 'O árbitro ' . $v['arbitro_nome'] . ' já está escalado para '
                            . $partida . '. Ninguém apita duas partidas ao mesmo tempo.';
        }

        foreach ($times as $t) {
            if ((int)$v['time1_id'] === $t || (int)$v['time2_id'] === $t) {
                $nome = ((int)$v['time1_id'] === $t) ? $v['time1_nome'] : $v['time2_nome'];
                $impedimentos[] = 'O time ' . $nome . ' já joga em ' . $partida
                                . ' neste horário.';
            }
        }

        if ($local !== '' && strcasecmp(trim((string)$v['local']), $local) === 0) {
            $avisos[] = 'Já existe partida marcada em "' . $local . '" neste horário: '
                      . $partida . '. Confirme se o espaço comporta as duas.';
        }
    }

    return [
        'impedimentos' => array_values(array_unique($impedimentos)),
        'avisos'       => array_values(array_unique($avisos)),
    ];
}

/** Partidas que ainda não têm data ou horário definidos. */
function sh_jogos_sem_horario(PDO $pdo) {
    $escola = sh_filtro_escola($pdo, 'j');
    try {
        $stmt = $pdo->prepare("
            SELECT j.*, m.nome AS modalidade,
                   t1.nome AS time1_nome, t2.nome AS time2_nome
              FROM jogos j
              JOIN modalidades m ON m.id = j.modalidade_id
              JOIN times t1      ON t1.id = j.time1_id
              JOIN times t2      ON t2.id = j.time2_id
             WHERE (j.data_jogo IS NULL OR j.hora IS NULL)
               AND j.status <> 'finalizado'
                   {$escola['sql']}
          ORDER BY m.nome, t1.nome
        ");
        $stmt->execute($escola['params']);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'listar partidas sem horário');
        return [];
    }
}
