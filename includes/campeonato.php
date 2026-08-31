<?php
/**
 * includes/campeonato.php — regras do campeonato que a escola configura.
 *
 * Reúne três coisas que a classificação sozinha não dava conta:
 *
 *   SH-56  Critérios de pontuação e de desempate deixam de ser constantes
 *          escritas dentro do SQL e passam a ser dado, editável no painel.
 *   SH-55  Chaveamento de mata-mata: monta a árvore a partir da classificação
 *          e faz o vencedor subir sozinho a cada partida encerrada.
 *   SH-67  Estatística individual do atleta, calculada de `eventos_jogo`.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/consultas.php';


/* ══ Estatística individual do atleta (SH-67) ════════════════════════════
   Artilharia, cartões e presença por jogador, a partir de `eventos_jogo`.

   Limite deliberado de LGPD: só entram jogadores que já apareceram numa
   súmula — ou seja, dado que a partida tornou público de qualquer forma.
   Nada de nota, frequência escolar ou observação sobre o aluno. A finalidade
   é a mesma da tabela de classificação: divulgar o resultado esportivo.    */
function sh_estatisticas_jogadores(PDO $pdo, $modalidade_id = null, $time_id = null, $limite = 100) {
    $filtros = ["j.status = 'finalizado'"];
    $params  = [];

    if ($modalidade_id !== null && (int)$modalidade_id > 0) {
        $filtros[] = 'j.modalidade_id = ?';
        $params[]  = (int)$modalidade_id;
    }
    if ($time_id !== null && (int)$time_id > 0) {
        $filtros[] = 'ev.time_id = ?';
        $params[]  = (int)$time_id;
    }
    $filtro_escola = sh_filtro_escola($pdo, 'j');
    $where = implode(' AND ', $filtros) . $filtro_escola['sql'];
    $params = array_merge($params, $filtro_escola['params']);

    $limite = max(1, min(500, (int)$limite));   // valor interno, nunca da URL

    try {
        $stmt = $pdo->prepare("
            SELECT ev.jogador AS nome,
                   ev.time_id,
                   t.nome  AS time_nome,
                   COUNT(DISTINCT ev.jogo_id) AS jogos,
                   SUM(CASE WHEN ev.tipo = 'gol'             THEN 1 ELSE 0 END) AS gols,
                   SUM(CASE WHEN ev.tipo = 'cartao_amarelo'  THEN 1 ELSE 0 END) AS amarelos,
                   SUM(CASE WHEN ev.tipo = 'cartao_vermelho' THEN 1 ELSE 0 END) AS vermelhos,
                   SUM(CASE WHEN ev.tipo = 'substituicao'    THEN 1 ELSE 0 END) AS substituicoes
              FROM eventos_jogo ev
              JOIN jogos j ON j.id = ev.jogo_id
              JOIN times t ON t.id = ev.time_id
             WHERE $where AND ev.jogador IS NOT NULL AND ev.jogador <> ''
          GROUP BY ev.jogador, ev.time_id, t.nome
          ORDER BY gols DESC, jogos DESC, ev.jogador
             LIMIT $limite
        ");
        $stmt->execute($params);
        $linhas = $stmt->fetchAll();
    } catch (PDOException $e) {
        sh_log_excecao($e, 'montar a estatística individual');
        return [];
    }

    foreach ($linhas as &$l) {
        foreach (['jogos', 'gols', 'amarelos', 'vermelhos', 'substituicoes'] as $c) {
            $l[$c] = (int)$l[$c];
        }
        $l['media_gols'] = $l['jogos'] > 0 ? round($l['gols'] / $l['jogos'], 2) : 0.0;
        // Disciplina: amarelo pesa 1, vermelho pesa 3 (regra do fair play da CBF).
        $l['pontos_disciplina'] = $l['amarelos'] + ($l['vermelhos'] * 3);
    }
    unset($l);
    return $linhas;
}

/* ══ Chaveamento de mata-mata (SH-55) ════════════════════════════════════
   A escola termina a fase de grupos e precisa montar as quartas. Fazia isso
   à mão, criando jogo por jogo — e refazendo tudo quando um resultado mudava.

   O algoritmo é o de sempre no esporte: os N melhores da classificação entram
   numa chave em que o 1º pega o último classificado, o 2º pega o penúltimo, e
   assim por diante. Quem passa sobe para a posição correspondente da fase
   seguinte, já calculada na criação da árvore.

   Quando o número de classificados não é potência de 2, os melhores recebem
   BYE — passam direto para a fase seguinte, que é o que a tabela de qualquer
   campeonato faz.                                                          */

/** Nome consagrado da fase pelo número de times que entram nela. */
function sh_nome_fase($times_na_fase) {
    $nomes = [2 => 'Final', 4 => 'Semifinal', 8 => 'Quartas de final',
              16 => 'Oitavas de final', 32 => '16 avos de final'];
    return $nomes[$times_na_fase] ?? ($times_na_fase . ' avos de final');
}

/**
 * Monta (ou refaz) o chaveamento de uma modalidade.
 *
 * @param int $quantos  quantos times classificados entram na chave (2 a 32)
 * @return array{ok:bool, mensagem:string, fases:int}
 */
function sh_gerar_chaveamento(PDO $pdo, $modalidade_id, $quantos = 8) {
    $modalidade_id = (int)$modalidade_id;
    $quantos       = max(2, min(32, (int)$quantos));

    if (!sh_tabela_existe($pdo, 'chaveamento_fases')) {
        return ['ok' => false, 'mensagem' => 'Rode scripts/migration_v3.sql antes de gerar o chaveamento.', 'fases' => 0];
    }

    $classificacao = sh_classificacao($pdo, $modalidade_id);
    $classificados = array_slice($classificacao, 0, $quantos);
    if (count($classificados) < 2) {
        return ['ok' => false, 'mensagem' => 'São necessários pelo menos dois times com jogos encerrados nesta modalidade.', 'fases' => 0];
    }

    // Tamanho da chave: a potência de 2 imediatamente acima do nº de times.
    $n = count($classificados);
    $tamanho = 2;
    while ($tamanho < $n) $tamanho *= 2;

    $escola = sh_escola_para_insert($pdo);

    try {
        $pdo->beginTransaction();

        // Refazer a chave apaga a anterior desta modalidade. As partidas já
        // criadas em `jogos` NÃO são apagadas: resultado registrado é história.
        $antigas = $pdo->prepare('SELECT id FROM chaveamento_fases WHERE modalidade_id = ?');
        $antigas->execute([$modalidade_id]);
        $ids = $antigas->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM chaveamento_jogos WHERE fase_id IN ($marcas)")->execute($ids);
            $pdo->prepare("DELETE FROM chaveamento_fases WHERE id IN ($marcas)")->execute($ids);
        }

        // Cria as fases, da primeira rodada até a final.
        $fases = [];
        $ordem = 1;
        for ($t = $tamanho; $t >= 2; $t = (int)($t / 2)) {
            $stmt = $pdo->prepare(
                'INSERT INTO chaveamento_fases (modalidade_id, escola_id, nome, ordem, times_na_fase)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$modalidade_id, $escola, sh_nome_fase($t), $ordem, $t]);
            $fases[$ordem] = ['id' => (int)$pdo->lastInsertId(), 'times' => $t];
            $ordem++;
        }
        $total_fases = count($fases);

        // Confrontos da primeira rodada: 1º x último, 2º x penúltimo...
        $ins = $pdo->prepare(
            'INSERT INTO chaveamento_jogos
                (fase_id, posicao, time1_id, time2_id, proxima_fase_id, proxima_posicao, proxima_vaga)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $jogos_primeira = (int)($tamanho / 2);
        for ($p = 1; $p <= $jogos_primeira; $p++) {
            $indice_a = $p - 1;                 // cabeça de chave
            $indice_b = $tamanho - $p;          // adversário simétrico
            $time_a = $classificados[$indice_a]['id'] ?? null;
            $time_b = $classificados[$indice_b]['id'] ?? null;   // null = BYE

            $proxima = $total_fases > 1 ? $fases[2]['id'] : null;
            $ins->execute([
                $fases[1]['id'], $p, $time_a, $time_b,
                $proxima,
                $proxima !== null ? (int)ceil($p / 2) : null,
                $proxima !== null ? (($p % 2 === 1) ? 1 : 2) : null,
            ]);
        }

        // Posições vazias das fases seguintes, já apontando para a próxima.
        for ($f = 2; $f <= $total_fases; $f++) {
            $jogos_da_fase = (int)($fases[$f]['times'] / 2);
            for ($p = 1; $p <= $jogos_da_fase; $p++) {
                $proxima = ($f < $total_fases) ? $fases[$f + 1]['id'] : null;
                $ins->execute([
                    $fases[$f]['id'], $p, null, null,
                    $proxima,
                    $proxima !== null ? (int)ceil($p / 2) : null,
                    $proxima !== null ? (($p % 2 === 1) ? 1 : 2) : null,
                ]);
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'gerar o chaveamento'), 'fases' => 0];
    }

    // BYE: quem não teve adversário já está classificado para a fase seguinte.
    sh_propagar_byes($pdo, $modalidade_id);

    sh_auditar($pdo, 'chaveamento_gerado', 'modalidades', $modalidade_id,
               $n . ' times, ' . $total_fases . ' fases');

    return ['ok' => true, 'fases' => $total_fases, 'mensagem' =>
        'Chaveamento criado: ' . $n . ' classificados em ' . $total_fases . ' fase(s), a partir de '
        . sh_nome_fase($tamanho) . '.'];
}

/** Times sem adversário sobem sozinhos para a fase seguinte. */
function sh_propagar_byes(PDO $pdo, $modalidade_id) {
    try {
        $stmt = $pdo->prepare(
            'SELECT cj.* FROM chaveamento_jogos cj
               JOIN chaveamento_fases cf ON cf.id = cj.fase_id
              WHERE cf.modalidade_id = ? AND cj.vencedor_id IS NULL
                AND ((cj.time1_id IS NOT NULL AND cj.time2_id IS NULL)
                  OR (cj.time1_id IS NULL AND cj.time2_id IS NOT NULL))'
        );
        $stmt->execute([(int)$modalidade_id]);
        foreach ($stmt->fetchAll() as $chave) {
            $vencedor = $chave['time1_id'] ?? $chave['time2_id'];
            sh_registrar_vencedor_chave($pdo, (int)$chave['id'], (int)$vencedor);
        }
    } catch (PDOException $e) {
        sh_log_excecao($e, 'propagar byes do chaveamento');
    }
}

/**
 * Marca o vencedor de uma posição e o promove para a fase seguinte.
 * Chamada pelo árbitro (ao encerrar a partida) e pela propagação de BYE.
 */
function sh_registrar_vencedor_chave(PDO $pdo, $chave_id, $vencedor_id) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM chaveamento_jogos WHERE id = ?');
        $stmt->execute([(int)$chave_id]);
        $chave = $stmt->fetch();
        if (!$chave) return false;

        $pdo->prepare('UPDATE chaveamento_jogos SET vencedor_id = ? WHERE id = ?')
            ->execute([(int)$vencedor_id, (int)$chave_id]);

        if ($chave['proxima_fase_id'] === null) return true;   // era a final

        $coluna = ((int)$chave['proxima_vaga'] === 2) ? 'time2_id' : 'time1_id';
        $pdo->prepare(
            "UPDATE chaveamento_jogos SET $coluna = ?
              WHERE fase_id = ? AND posicao = ?"
        )->execute([(int)$vencedor_id, (int)$chave['proxima_fase_id'], (int)$chave['proxima_posicao']]);
        return true;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'promover vencedor no chaveamento');
        return false;
    }
}

/**
 * Chamada quando uma partida é encerrada: se ela pertence a uma chave,
 * o vencedor sobe. Empate no mata-mata não promove ninguém — a coordenação
 * decide o critério (prorrogação, pênaltis) e edita o placar.
 */
function sh_chaveamento_ao_encerrar_jogo(PDO $pdo, $jogo_id) {
    if (!sh_tabela_existe($pdo, 'chaveamento_jogos')) return;
    try {
        $stmt = $pdo->prepare('SELECT * FROM chaveamento_jogos WHERE jogo_id = ?');
        $stmt->execute([(int)$jogo_id]);
        $chave = $stmt->fetch();
        if (!$chave) return;

        $stmt = $pdo->prepare('SELECT time1_id, time2_id, placar_time1, placar_time2, status
                                 FROM jogos WHERE id = ?');
        $stmt->execute([(int)$jogo_id]);
        $jogo = $stmt->fetch();
        if (!$jogo || $jogo['status'] !== 'finalizado') return;

        if ((int)$jogo['placar_time1'] === (int)$jogo['placar_time2']) return;  // empate: sem promoção

        $vencedor = (int)$jogo['placar_time1'] > (int)$jogo['placar_time2']
            ? (int)$jogo['time1_id'] : (int)$jogo['time2_id'];

        sh_registrar_vencedor_chave($pdo, (int)$chave['id'], $vencedor);
    } catch (PDOException $e) {
        sh_log_excecao($e, 'atualizar o chaveamento ao encerrar o jogo');
    }
}

/** Árvore completa de uma modalidade, pronta para desenhar na tela. */
function sh_chaveamento(PDO $pdo, $modalidade_id) {
    if (!sh_tabela_existe($pdo, 'chaveamento_fases')) return [];
    try {
        $stmt = $pdo->prepare('SELECT * FROM chaveamento_fases WHERE modalidade_id = ? ORDER BY ordem');
        $stmt->execute([(int)$modalidade_id]);
        $fases = $stmt->fetchAll();
        if (!$fases) return [];

        $stmt = $pdo->prepare('
            SELECT cj.*,
                   t1.nome AS time1_nome, t2.nome AS time2_nome,
                   j.placar_time1, j.placar_time2, j.status AS jogo_status,
                   j.data_jogo, j.hora, j.local
              FROM chaveamento_jogos cj
         LEFT JOIN times t1 ON t1.id = cj.time1_id
         LEFT JOIN times t2 ON t2.id = cj.time2_id
         LEFT JOIN jogos j  ON j.id  = cj.jogo_id
             WHERE cj.fase_id = ?
          ORDER BY cj.posicao
        ');
        foreach ($fases as &$fase) {
            $stmt->execute([(int)$fase['id']]);
            $fase['jogos'] = $stmt->fetchAll();
        }
        unset($fase);
        return $fases;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'carregar o chaveamento');
        return [];
    }
}

/**
 * Cria em `jogos` a partida real de uma posição da chave.
 * Só funciona quando os dois times já são conhecidos.
 */
function sh_agendar_jogo_da_chave(PDO $pdo, $chave_id, $data, $hora, $local) {
    try {
        $stmt = $pdo->prepare(
            'SELECT cj.*, cf.modalidade_id, cf.nome AS fase_nome, cf.escola_id
               FROM chaveamento_jogos cj
               JOIN chaveamento_fases cf ON cf.id = cj.fase_id
              WHERE cj.id = ?'
        );
        $stmt->execute([(int)$chave_id]);
        $chave = $stmt->fetch();

        if (!$chave)                       return ['ok' => false, 'mensagem' => 'Posição da chave não encontrada.'];
        if ($chave['jogo_id'] !== null)    return ['ok' => false, 'mensagem' => 'Esta partida já foi agendada.'];
        if (!$chave['time1_id'] || !$chave['time2_id']) {
            return ['ok' => false, 'mensagem' => 'Os dois times ainda não estão definidos nesta posição.'];
        }

        $pdo->prepare(
            'INSERT INTO jogos (modalidade_id, escola_id, time1_id, time2_id, data_jogo, hora, local, fase, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int)$chave['modalidade_id'], $chave['escola_id'],
            (int)$chave['time1_id'], (int)$chave['time2_id'],
            $data, $hora, $local, $chave['fase_nome'], 'agendado',
        ]);
        $jogo_id = (int)$pdo->lastInsertId();

        $pdo->prepare('UPDATE chaveamento_jogos SET jogo_id = ? WHERE id = ?')
            ->execute([$jogo_id, (int)$chave_id]);

        sh_auditar($pdo, 'jogo_da_chave_agendado', 'jogos', $jogo_id, $chave['fase_nome']);
        return ['ok' => true, 'mensagem' => 'Partida agendada.', 'jogo_id' => $jogo_id];
    } catch (PDOException $e) {
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'agendar a partida da chave')];
    }
}
