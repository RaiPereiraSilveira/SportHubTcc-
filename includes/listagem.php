<?php
/**
 * includes/listagem.php — busca e paginação reaproveitáveis (SH-83)
 *
 * O SH-52 resolveu a listagem de times: busca por nome, 15 por página, termo
 * escapado. Funcionou, e o cartão seguinte pedia o mesmo para modalidades,
 * jogos, árbitros, jogadores, assinaturas e credenciamentos.
 *
 * Copiar aquele bloco seis vezes seria a saída mais rápida e a pior: seriam
 * sete lugares para corrigir quando alguém descobrisse que o `%` digitado
 * pelo usuário não estava escapado, ou que a última página quebrava com zero
 * resultados. Então o padrão virou este arquivo, e `admin/times.php`
 * continua como estava — reescrevê-lo não traria nada e arriscaria uma tela
 * que já funciona.
 *
 * Uso típico:
 *
 *     $lista = sh_listar($pdo, [
 *         'contar'   => 'SELECT COUNT(*) FROM modalidades m',
 *         'buscar'   => 'SELECT m.* FROM modalidades m',
 *         'campos'   => ['m.nome'],
 *         'ordem'    => 'm.nome',
 *         'por_pagina' => 15,
 *     ]);
 *     foreach ($lista['linhas'] as $linha) { ... }
 *     echo sh_navegacao_paginas($lista);
 */

require_once __DIR__ . '/config.php';

/**
 * Prepara o termo para um LIKE.
 *
 * Escapar `%` e `_` importa: sem isso, um usuário que digitasse "%" receberia
 * a tabela inteira, e "_" casaria com qualquer caractere. Não é falha de
 * segurança (o termo vai por parâmetro), é resultado errado.
 */
function sh_termo_like($texto) {
    $texto = trim((string)$texto);
    if ($texto === '') return '';
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto) . '%';
}

/**
 * Executa uma listagem paginada com busca.
 *
 * @param array $opcoes
 *   contar     SQL do COUNT, sem WHERE
 *   buscar     SQL do SELECT, sem WHERE / ORDER / LIMIT
 *   campos     colunas onde a busca procura (ex.: ['t.nome', 't.sala'])
 *   ordem      ORDER BY (sem a palavra ORDER BY)
 *   onde       condições fixas, já com placeholders (ex.: "j.status = ?")
 *   params     parâmetros das condições fixas
 *   por_pagina padrão 15
 *   param_q    nome do parâmetro de busca na URL (padrão 'q')
 *   param_p    nome do parâmetro de página na URL (padrão 'pagina')
 *   base_url   arquivo para onde os links de página apontam
 *
 * @return array{linhas:array, total:int, pagina:int, paginas:int,
 *               busca:string, por_pagina:int, param_q:string, param_p:string,
 *               base_url:string, erro:?string}
 */
function sh_listar(PDO $pdo, array $opcoes) {
    $por_pagina = max(1, min(200, (int)($opcoes['por_pagina'] ?? 15)));
    $param_q    = (string)($opcoes['param_q'] ?? 'q');
    $param_p    = (string)($opcoes['param_p'] ?? 'pagina');
    $base_url   = (string)($opcoes['base_url'] ?? basename($_SERVER['PHP_SELF'] ?? ''));

    $busca  = trim((string)($_GET[$param_q] ?? ''));
    $pagina = max(1, (int)($_GET[$param_p] ?? 1));

    $condicoes = [];
    $params    = [];

    if (!empty($opcoes['onde'])) {
        $condicoes[] = '(' . $opcoes['onde'] . ')';
        $params      = array_merge($params, (array)($opcoes['params'] ?? []));
    }

    $campos = (array)($opcoes['campos'] ?? []);
    if ($busca !== '' && $campos) {
        $termo = sh_termo_like($busca);
        $ors   = [];
        foreach ($campos as $campo) {
            $ors[]    = $campo . ' LIKE ?';
            $params[] = $termo;
        }
        $condicoes[] = '(' . implode(' OR ', $ors) . ')';
    }

    $where = $condicoes ? ' WHERE ' . implode(' AND ', $condicoes) : '';

    $resultado = [
        'linhas' => [], 'total' => 0, 'pagina' => 1, 'paginas' => 1,
        'busca' => $busca, 'por_pagina' => $por_pagina,
        'param_q' => $param_q, 'param_p' => $param_p,
        'base_url' => $base_url, 'erro' => null,
    ];

    try {
        $stmt = $pdo->prepare($opcoes['contar'] . $where);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $paginas = max(1, (int)ceil($total / $por_pagina));
        $pagina  = min($pagina, $paginas);
        $offset  = ($pagina - 1) * $por_pagina;

        $ordem = !empty($opcoes['ordem']) ? ' ORDER BY ' . $opcoes['ordem'] : '';

        /* LIMIT e OFFSET vão interpolados porque o MySQL não aceita parâmetro
           nessa posição com EMULATE_PREPARES desligado. São inteiros já
           saneados acima — nunca texto vindo da URL. */
        $stmt = $pdo->prepare($opcoes['buscar'] . $where . $ordem
                            . ' LIMIT ' . $por_pagina . ' OFFSET ' . $offset);
        $stmt->execute($params);

        $resultado['linhas']  = $stmt->fetchAll();
        $resultado['total']   = $total;
        $resultado['pagina']  = $pagina;
        $resultado['paginas'] = $paginas;
    } catch (PDOException $e) {
        $resultado['erro'] = sh_erro_usuario($e, 'carregar a lista');
    }

    return $resultado;
}

/** URL preservando busca e demais filtros ao trocar de página. */
function sh_url_listagem(array $lista, array $extra = []) {
    $atual = $_GET;
    unset($atual[$lista['param_p']]);
    $query = array_filter(array_merge($atual, $extra), function ($v) {
        return $v !== '' && $v !== null;
    });
    return $lista['base_url'] . ($query ? '?' . http_build_query($query) : '');
}

/**
 * Barra de busca.
 *
 * GET, e não POST, de propósito: o resultado de uma busca é um endereço que a
 * coordenação pode guardar nos favoritos ou mandar para outra pessoa.
 */
function sh_barra_busca(array $lista, $rotulo, $substantivo, $extras_html = '') {
    $id  = 'busca-' . preg_replace('/[^a-z0-9]/i', '', $lista['param_q']);
    $n   = (int)$lista['total'];
    $plural = ($n === 1) ? '' : 's';

    ob_start(); ?>
    <form method="GET" action="<?= e($lista['base_url']) ?>" class="filtro-bar" role="search">
        <label class="sr-only" for="<?= e($id) ?>"><?= e($rotulo) ?></label>
        <input type="search" id="<?= e($id) ?>" name="<?= e($lista['param_q']) ?>"
               value="<?= e($lista['busca']) ?>" placeholder="<?= e($rotulo) ?>"
               autocomplete="off">
        <?php
        /* Os demais filtros da tela seguem junto, senão buscar zeraria o
           filtro de status que a pessoa tinha acabado de escolher. */
        foreach ($_GET as $chave => $valor) {
            if ($chave === $lista['param_q'] || $chave === $lista['param_p']) continue;
            if (is_array($valor)) continue;
            echo '<input type="hidden" name="' . e($chave) . '" value="' . e($valor) . '">';
        }
        ?>
        <button type="submit" class="btn btn-secondary">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i> Buscar
        </button>
        <?php if ($lista['busca'] !== ''): ?>
            <?php /* Limpar tira o termo e volta para a página 1, mas mantém o
                     resto da URL. Apontar para base_url puro derrubava o
                     `time_id` de ver_jogadores.php — a tela perdia o time e
                     desviava para a lista — e zerava o filtro de status que a
                     pessoa tinha acabado de escolher em jogos.php. */ ?>
            <a href="<?= e(sh_url_listagem($lista, [$lista['param_q'] => ''])) ?>"
               class="btn btn-ghost">Limpar</a>
        <?php endif; ?>
        <span class="filtro-contagem">
            <?= $n ?> <?= e($substantivo) ?><?= $plural ?>
            <?= $lista['busca'] !== '' ? 'encontrado' . $plural : 'no total' ?>
        </span>
        <?= $extras_html ?>
    </form>
    <?php
    return ob_get_clean();
}

/** Navegação entre páginas. Devolve '' quando só há uma. */
function sh_navegacao_paginas(array $lista, $rotulo = 'Paginação') {
    if ((int)$lista['paginas'] <= 1) return '';

    $pagina  = (int)$lista['pagina'];
    $paginas = (int)$lista['paginas'];

    ob_start(); ?>
    <nav class="paginacao" aria-label="<?= e($rotulo) ?>">
        <?php if ($pagina > 1): ?>
            <a class="paginacao-passo"
               href="<?= e(sh_url_listagem($lista, [$lista['param_p'] => $pagina - 1])) ?>">
                <i class="fas fa-chevron-left" aria-hidden="true"></i> Anterior
            </a>
        <?php else: ?>
            <span class="paginacao-passo is-disabled" aria-disabled="true">
                <i class="fas fa-chevron-left" aria-hidden="true"></i> Anterior
            </span>
        <?php endif; ?>

        <span class="paginacao-info">Página <?= $pagina ?> de <?= $paginas ?></span>

        <?php if ($pagina < $paginas): ?>
            <a class="paginacao-passo"
               href="<?= e(sh_url_listagem($lista, [$lista['param_p'] => $pagina + 1])) ?>">
                Próxima <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
        <?php else: ?>
            <span class="paginacao-passo is-disabled" aria-disabled="true">
                Próxima <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </span>
        <?php endif; ?>
    </nav>
    <?php
    return ob_get_clean();
}

/** Frase para a linha "nada encontrado". */
function sh_vazio_listagem(array $lista, $vazio, $sem_resultado = null) {
    if ($lista['busca'] === '') return $vazio;
    $sem_resultado = $sem_resultado ?? 'Nada corresponde a “%s”.';
    return sprintf($sem_resultado, e($lista['busca']));
}
