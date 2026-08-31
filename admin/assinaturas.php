<?php
/**
 * admin/assinaturas.php
 * Acompanhamento das assinaturas contratadas e das mensagens do site.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/listagem.php';
exigirPerfil('admin', '../login.php');

$tem_assinaturas = sh_tabela_existe($pdo, 'assinaturas');
$tem_contatos    = sh_tabela_existe($pdo, 'contatos');
$aviso = null;
$erro  = null;

/* ── Ações ───────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Recarregue a página e tente de novo.';
    } elseif (($_POST['acao'] ?? '') === 'status_assinatura' && $tem_assinaturas) {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['trial', 'pendente', 'ativa', 'cancelada', 'expirada'], true)) {
            $pdo->prepare("UPDATE assinaturas SET status = ? WHERE id = ?")->execute([$status, $id]);
            sh_auditar($pdo, 'assinatura_status_alterado', 'assinaturas', $id, $status);
            $aviso = 'Situação da assinatura atualizada.';
        }
    } elseif (($_POST['acao'] ?? '') === 'marcar_lido' && $tem_contatos) {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE contatos SET lido = 1 WHERE id = ?")->execute([$id]);
        $aviso = 'Mensagem marcada como lida.';
    }
}

/* ── Dados ───────────────────────────────────────────────────────────────── */
$assinaturas = [];
$lista_assinaturas = null;
$resumo = ['total' => 0, 'ativa' => 0, 'trial' => 0, 'receita' => 0.0];

if ($tem_assinaturas) {
    /* O resumo é calculado por agregação, e não somando as linhas exibidas
       (SH-83): com a listagem paginada, contar em PHP passaria a mostrar
       "3 contratações" quando existem 40 — o número certo da página errada. */
    try {
        $agregado = $pdo->query(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'ativa') AS ativa,
                    SUM(status = 'trial') AS trial,
                    COALESCE(SUM(CASE WHEN status = 'ativa' THEN valor ELSE 0 END), 0) AS receita
               FROM assinaturas"
        )->fetch();
        if ($agregado) {
            $resumo = [
                'total'   => (int)$agregado['total'],
                'ativa'   => (int)$agregado['ativa'],
                'trial'   => (int)$agregado['trial'],
                'receita' => (float)$agregado['receita'],
            ];
        }
    } catch (PDOException $ex) {
        sh_log_excecao($ex, 'resumir assinaturas');
    }

    /* Busca e paginação (SH-83). O termo procura no código da contratação,
       no nome da escola e no do responsável — que é como a coordenação
       lembra de uma contratação quando alguém liga perguntando por ela. */
    $lista_assinaturas = sh_listar($pdo, [
        'contar' => 'SELECT COUNT(*)
                       FROM assinaturas a
                  LEFT JOIN planos  p ON p.id = a.plano_id
                  LEFT JOIN escolas e ON e.id = a.escola_id',
        'buscar' => 'SELECT a.*, p.nome AS plano_nome, e.nome AS escola_nome, e.cidade, e.uf
                       FROM assinaturas a
                  LEFT JOIN planos  p ON p.id = a.plano_id
                  LEFT JOIN escolas e ON e.id = a.escola_id',
        'campos' => ['a.codigo', 'a.responsavel', 'a.email', 'e.nome', 'p.nome'],
        'ordem'  => 'a.created_at DESC',
        'por_pagina' => 15,
        'base_url'   => 'assinaturas.php',
    ]);
    $assinaturas = $lista_assinaturas['linhas'];
    if ($lista_assinaturas['erro'] !== null) {
        $erro = $lista_assinaturas['erro'];
    }
}

$mensagens = [];
$nao_lidas = 0;
if ($tem_contatos) {
    try {
        $mensagens = $pdo->query("SELECT * FROM contatos ORDER BY lido ASC, created_at DESC LIMIT 60")->fetchAll();
        $nao_lidas = (int)$pdo->query("SELECT COUNT(*) FROM contatos WHERE lido = 0")->fetchColumn();
    } catch (PDOException $ex) {
        sh_log_excecao($ex, 'listar contatos');
    }
}

$status_badge = [
    'trial'     => ['Em teste',   'em_andamento'],
    'pendente'  => ['Pendente',   'agendado'],
    'ativa'     => ['Ativa',      'finalizado'],
    'cancelada' => ['Cancelada',  'cancelado'],
    'expirada'  => ['Expirada',   'cancelado'],
];
$formas = ['boleto' => 'Boleto', 'pix' => 'Pix', 'cartao' => 'Cartão', 'empenho' => 'Empenho'];
$assuntos = [
    'demonstracao' => 'Demonstração', 'planos' => 'Planos', 'suporte' => 'Suporte',
    'arbitragem' => 'Arbitragem', 'imprensa' => 'Imprensa', 'outro' => 'Outro',
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="page-hero-inner">
        <span class="page-hero-badge">Comercial</span>
        <h2>Assinaturas e mensagens</h2>
        <p>Contratações registradas pelo site e contatos recebidos pelo formulário público.</p>
    </div>
</div>

<div class="container">

    <?php if (!$tem_assinaturas): ?>
        <div class="alert alert-warning">
            <strong>Módulo não instalado.</strong> Importe <code>scripts/migration_v2.sql</code> para
            habilitar o controle de assinaturas.
        </div>
    <?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($aviso): ?><div class="alert alert-success"><?= e($aviso) ?></div><?php endif; ?>

    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number"><?= (int)$resumo['total'] ?></div>
            <div class="stat-label">Contratações</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)$resumo['ativa'] ?></div>
            <div class="stat-label">Assinaturas ativas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)$resumo['trial'] ?></div>
            <div class="stat-label">Em período de teste</div>
        </div>
        <div class="stat-card">
            <div class="stat-number u-font-size-1-6rem">R$ <?= e(sh_money($resumo['receita'])) ?></div>
            <div class="stat-label">Receita anual contratada</div>
        </div>
    </div>

    <div class="admin-panel">
        <div class="tabs">
            <div class="tab active" data-tab="tab-assinaturas">Assinaturas</div>
            <div class="tab" data-tab="tab-mensagens">Mensagens<?= $nao_lidas ? ' (' . $nao_lidas . ')' : '' ?></div>
        </div>

        <!-- ── Assinaturas ── -->
        <div class="tab-content active" id="tab-assinaturas">
            <?php if ($lista_assinaturas !== null): ?>
                <?= sh_barra_busca($lista_assinaturas, 'Buscar por código, escola, responsável ou plano', 'contratação') ?>
            <?php endif; ?>

            <?php if (!$assinaturas): ?>
                <div class="no-data">
                    <i class="fas fa-file-invoice-dollar no-games-icon"></i>
                    <?= e($lista_assinaturas !== null
                        ? sh_vazio_listagem($lista_assinaturas, 'Nenhuma contratação registrada ainda.', 'Nenhuma contratação bate com “%s”.')
                        : 'Nenhuma contratação registrada ainda.') ?>
                </div>
            <?php else: ?>
                <div class="tabela-rolavel">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th><th>Escola</th><th>Plano</th><th>Responsável</th>
                            <th>Pagamento</th><th>Vigência</th><th>Situação</th><th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assinaturas as $a):
                        [$rotulo, $classe] = $status_badge[$a['status']] ?? ['—', '']; ?>
                        <tr>
                            <td><strong><?= e($a['codigo']) ?></strong></td>
                            <td>
                                <?= e($a['escola_nome'] ?? '—') ?>
                                <?php if ($a['cidade']): ?><br><small class="not-assigned"><?= e($a['cidade'] . '/' . $a['uf']) ?></small><?php endif; ?>
                            </td>
                            <td><?= e($a['plano_nome'] ?? '—') ?><br><small>R$ <?= e(sh_money($a['valor'])) ?>/ano</small></td>
                            <td><?= e($a['responsavel']) ?><br><small class="not-assigned"><?= e($a['email']) ?></small></td>
                            <td><?= e($formas[$a['forma_pagamento']] ?? $a['forma_pagamento']) ?></td>
                            <td>
                                <?= $a['inicio_em'] ? e(date('d/m/Y', strtotime($a['inicio_em']))) : '—' ?>
                                <?= $a['expira_em'] ? '<br><small>até ' . e(date('d/m/Y', strtotime($a['expira_em']))) . '</small>' : '' ?>
                            </td>
                            <td><span class="status-badge <?= e($classe) ?>"><?= e($rotulo) ?></span></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="status_assinatura">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <select class="u-max-width-150px" name="status" data-auto-submit>
                                        <?php foreach ($status_badge as $valor => [$texto, $_]): ?>
                                            <option value="<?= e($valor) ?>"<?= $a['status'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php if (!empty($a['observacoes'])): ?>
                            <tr><td colspan="8" class="observations"><strong>Observações:</strong> <?= e($a['observacoes']) ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?= sh_navegacao_paginas($lista_assinaturas, 'Paginação das contratações') ?>
            <?php endif; ?>
        </div>

        <!-- ── Mensagens ── -->
        <div class="tab-content" id="tab-mensagens">
            <?php if (!$mensagens): ?>
                <div class="no-data"><i class="fas fa-envelope no-games-icon"></i> Nenhuma mensagem recebida pelo formulário do site.</div>
            <?php else: ?>
                <div class="games-list">
                    <?php foreach ($mensagens as $m): ?>
                    <article class="game-card<?= $m['lido'] ? '' : ' is-nao-lido' ?>">
                        <div class="game-header">
                            <div>
                                <span class="game-sport"><?= e($m['nome']) ?></span>
                                <div class="detail-label u-margin-top-4px"><?= e($m['email']) ?><?= $m['telefone'] ? ' · ' . e($m['telefone']) : '' ?></div>
                            </div>
                            <span class="status-badge <?= $m['lido'] ? 'finalizado' : 'em_andamento' ?>">
                                <?= e($assuntos[$m['assunto']] ?? $m['assunto']) ?>
                            </span>
                        </div>
                        <p class="observations u-margin-top-14px"><?= nl2br(e($m['mensagem'])) ?></p>
                        <div class="game-details">
                            <div class="detail-item"><span class="detail-label">Escola</span><span><?= e($m['escola'] ?: '—') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Recebida em</span><span><?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></span></div>
                        </div>
                        <div class="game-actions">
                            <a href="mailto:<?= e($m['email']) ?>" class="btn-small btn-secondary"><i class="fas fa-reply"></i> Responder</a>
                            <?php if (!$m['lido']): ?>
                                <form method="POST" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="marcar_lido">
                                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                    <button type="submit" class="btn-small">Marcar como lida</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
