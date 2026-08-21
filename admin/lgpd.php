<?php
/**
 * admin/lgpd.php
 * Atendimento às requisições de titulares (art. 18 da LGPD) e consulta
 * ao registro de consentimentos.
 */
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');

$modulo_ok = sh_tabela_existe($pdo, 'lgpd_solicitacoes');
$aviso = null;
$erro  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modulo_ok) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Recarregue a página e tente de novo.';
    } else {
        $id      = (int)($_POST['id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        $resposta= trim((string)($_POST['resposta'] ?? ''));

        if (!in_array($status, ['recebida', 'em_analise', 'atendida', 'recusada'], true)) {
            $erro = 'Situação inválida.';
        } elseif (in_array($status, ['atendida', 'recusada'], true) && $resposta === '') {
            $erro = 'Escreva a resposta ao titular antes de concluir a solicitação.';
        } else {
            $respondido = in_array($status, ['atendida', 'recusada'], true) ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("UPDATE lgpd_solicitacoes SET status = ?, resposta = ?, respondido_em = ? WHERE id = ?")
                ->execute([$status, $resposta !== '' ? $resposta : null, $respondido, $id]);
            sh_auditar($pdo, 'lgpd_solicitacao_atualizada', 'lgpd_solicitacoes', $id, $status);
            $aviso = 'Solicitação atualizada. O titular já vê a resposta na consulta de protocolo.';
        }
    }
}

$solicitacoes = [];
$contagens = ['recebida' => 0, 'em_analise' => 0, 'atendida' => 0, 'recusada' => 0];
$vencendo = 0;

if ($modulo_ok) {
    try {
        foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM lgpd_solicitacoes GROUP BY status") as $l) {
            $contagens[$l['status']] = (int)$l['total'];
        }
        $vencendo = (int)$pdo->query(
            "SELECT COUNT(*) FROM lgpd_solicitacoes
             WHERE status IN ('recebida','em_analise')
               AND prazo_em IS NOT NULL
               AND prazo_em <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
        )->fetchColumn();

        $solicitacoes = $pdo->query(
            "SELECT * FROM lgpd_solicitacoes
             ORDER BY FIELD(status,'recebida','em_analise','atendida','recusada'), prazo_em ASC"
        )->fetchAll();
    } catch (PDOException $ex) {
        error_log('Erro ao listar solicitações LGPD: ' . $ex->getMessage());
        $erro = 'Erro ao carregar as solicitações.';
    }
}

$consentimentos = [];
if (sh_tabela_existe($pdo, 'lgpd_consentimentos')) {
    try {
        $consentimentos = $pdo->query(
            "SELECT finalidade, concedido, versao_texto, COUNT(*) AS total, MAX(created_at) AS ultimo
             FROM lgpd_consentimentos
             GROUP BY finalidade, concedido, versao_texto
             ORDER BY finalidade, concedido DESC"
        )->fetchAll();
    } catch (PDOException $ex) {
        error_log('Erro ao agregar consentimentos: ' . $ex->getMessage());
    }
}

$tipos = [
    'acesso' => 'Confirmação e acesso', 'correcao' => 'Correção',
    'anonimizacao' => 'Anonimização ou bloqueio', 'eliminacao' => 'Eliminação',
    'portabilidade' => 'Portabilidade', 'revogacao' => 'Revogação de consentimento',
    'informacao_compartilhamento' => 'Informação sobre compartilhamento', 'oposicao' => 'Oposição',
];
$vinculos = [
    'aluno' => 'Aluno(a)', 'responsavel' => 'Responsável', 'arbitro' => 'Árbitro(a)',
    'professor' => 'Professor(a)', 'outro' => 'Outro',
];
$badges = [
    'recebida'   => ['Recebida',     'agendado'],
    'em_analise' => ['Em análise',   'em_andamento'],
    'atendida'   => ['Atendida',     'finalizado'],
    'recusada'   => ['Não atendida', 'cancelado'],
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="page-hero-inner">
        <span class="page-hero-badge">Conformidade</span>
        <h2>Portal LGPD — atendimento ao titular</h2>
        <p>Requisições recebidas pelo site, com prazo legal de 15 dias corridos (art. 19, II da LGPD).</p>
    </div>
</div>

<div class="container">

    <?php if (!$modulo_ok): ?>
        <div class="alert alert-warning">
            <strong>Módulo não instalado.</strong> Importe <code>scripts/migration_v2.sql</code> para
            habilitar o atendimento de requisições de titulares.
        </div>
    <?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($aviso): ?><div class="alert alert-success"><?= e($aviso) ?></div><?php endif; ?>
    <?php if ($vencendo > 0): ?>
        <div class="alert alert-warning">
            <strong><?= (int)$vencendo ?> solicitação(ões) com prazo vencendo em até 3 dias.</strong>
            Responder fora do prazo expõe a instituição a sanção administrativa.
        </div>
    <?php endif; ?>

    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number"><?= $contagens['recebida'] + $contagens['em_analise'] ?></div>
            <div class="stat-label">Em aberto</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)$contagens['atendida'] ?></div>
            <div class="stat-label">Atendidas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)$contagens['recusada'] ?></div>
            <div class="stat-label">Não atendidas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)$vencendo ?></div>
            <div class="stat-label">Prazo em 3 dias</div>
        </div>
    </div>

    <div class="admin-panel">
        <div class="tabs">
            <div class="tab active" data-tab="tab-solicitacoes">Solicitações</div>
            <div class="tab" data-tab="tab-consentimentos">Registro de consentimentos</div>
        </div>

        <div class="tab-content active" id="tab-solicitacoes">
            <?php if (!$solicitacoes): ?>
                <div class="no-data"><i class="fas fa-user-shield no-games-icon"></i> Nenhuma requisição de titular recebida até agora.</div>
            <?php else: ?>
                <div class="games-list">
                    <?php foreach ($solicitacoes as $s):
                        [$rotulo, $classe] = $badges[$s['status']] ?? ['—', ''];
                        $aberta = in_array($s['status'], ['recebida', 'em_analise'], true);
                        $atrasada = $aberta && $s['prazo_em'] && strtotime($s['prazo_em']) < strtotime('today');
                    ?>
                    <article class="game-card" style="<?= $atrasada ? 'border-color:var(--red)' : '' ?>">
                        <div class="game-header">
                            <div>
                                <span class="game-sport"><?= e($tipos[$s['tipo']] ?? $s['tipo']) ?></span>
                                <div class="detail-label" style="margin-top:4px">Protocolo <?= e($s['protocolo']) ?></div>
                            </div>
                            <span class="status-badge <?= e($classe) ?>"><?= e($rotulo) ?></span>
                        </div>

                        <div class="game-details">
                            <div class="detail-item"><span class="detail-label">Titular</span><span><?= e($s['nome']) ?></span></div>
                            <div class="detail-item"><span class="detail-label">E-mail</span><span><?= e($s['email']) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Vínculo</span><span><?= e($vinculos[$s['vinculo']] ?? $s['vinculo']) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Recebida em</span><span><?= e(date('d/m/Y', strtotime($s['created_at']))) ?></span></div>
                            <div class="detail-item">
                                <span class="detail-label">Prazo legal</span>
                                <span style="<?= $atrasada ? 'color:var(--red);font-weight:600' : '' ?>">
                                    <?= $s['prazo_em'] ? e(date('d/m/Y', strtotime($s['prazo_em']))) : '—' ?>
                                    <?= $atrasada ? ' (vencido)' : '' ?>
                                </span>
                            </div>
                            <?php if ($s['respondido_em']): ?>
                            <div class="detail-item"><span class="detail-label">Respondida em</span><span><?= e(date('d/m/Y', strtotime($s['respondido_em']))) ?></span></div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($s['descricao'])): ?>
                            <p class="observations" style="margin-top:16px"><strong>Pedido:</strong> <?= nl2br(e($s['descricao'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($s['resposta'])): ?>
                            <p class="observations"><strong>Resposta enviada:</strong> <?= nl2br(e($s['resposta'])) ?></p>
                        <?php endif; ?>

                        <?php if ($modulo_ok): ?>
                        <form method="POST" style="margin-top:18px;padding-top:18px;border-top:1px solid var(--line)">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <div class="form-group">
                                <label for="resposta-<?= (int)$s['id'] ?>">Resposta ao titular</label>
                                <textarea id="resposta-<?= (int)$s['id'] ?>" name="resposta" rows="3"
                                          placeholder="Descreva o que foi feito. Este texto fica visível na consulta de protocolo."><?= e($s['resposta'] ?? '') ?></textarea>
                            </div>
                            <div class="form-actions">
                                <select name="status" style="max-width:200px">
                                    <?php foreach ($badges as $valor => [$texto, $_]): ?>
                                        <option value="<?= e($valor) ?>"<?= $s['status'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-small btn-accent">Salvar atendimento</button>
                                <a href="mailto:<?= e($s['email']) ?>?subject=<?= e(rawurlencode('Sua solicitação ' . $s['protocolo'] . ' — SportHub')) ?>"
                                   class="btn-small btn-secondary"><i class="fas fa-envelope"></i> Enviar por e-mail</a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-content" id="tab-consentimentos">
            <p class="subtitle" style="margin-bottom:18px">
                Registro exigido pelo art. 8º, §1º da LGPD: o controlador precisa ser capaz de
                comprovar que obteve o consentimento do titular.
            </p>
            <?php if (!$consentimentos): ?>
                <div class="no-data"><i class="fas fa-file-signature no-games-icon"></i> Nenhum consentimento registrado ainda.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Finalidade</th><th>Decisão</th><th>Versão do texto</th><th>Registros</th><th>Último registro</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consentimentos as $c): ?>
                        <tr>
                            <td><?= e($c['finalidade']) ?></td>
                            <td>
                                <span class="status-badge <?= $c['concedido'] ? 'finalizado' : 'cancelado' ?>">
                                    <?= $c['concedido'] ? 'Concedido' : 'Recusado' ?>
                                </span>
                            </td>
                            <td><?= e($c['versao_texto']) ?></td>
                            <td><?= (int)$c['total'] ?></td>
                            <td><?= e(date('d/m/Y H:i', strtotime($c['ultimo']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
