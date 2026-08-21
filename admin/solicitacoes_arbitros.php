<?php
/**
 * admin/solicitacoes_arbitros.php
 * Análise das solicitações de credenciamento de profissionais aplicadores.
 * Aprovar cria automaticamente o acesso de árbitro com senha provisória.
 */
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');

$modulo_ok = sh_tabela_existe($pdo, 'arbitro_solicitacoes');
$aviso = null;
$erro  = null;
$credencial_gerada = null;

/* ── Ações ───────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modulo_ok) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Recarregue a página e tente de novo.';
    } else {
        $id     = (int)($_POST['id'] ?? 0);
        $acao   = $_POST['acao'] ?? '';
        $parecer= trim((string)($_POST['parecer'] ?? ''));

        $stmt = $pdo->prepare("SELECT * FROM arbitro_solicitacoes WHERE id = ?");
        $stmt->execute([$id]);
        $sol = $stmt->fetch();

        if (!$sol) {
            $erro = 'Solicitação não encontrada.';
        } elseif ($acao === 'em_analise') {
            $pdo->prepare("UPDATE arbitro_solicitacoes SET status = 'em_analise' WHERE id = ?")->execute([$id]);
            sh_auditar($pdo, 'credenciamento_em_analise', 'arbitro_solicitacoes', $id, $sol['protocolo']);
            $aviso = 'Solicitação ' . $sol['protocolo'] . ' marcada como em análise.';

        } elseif ($acao === 'recusar') {
            if ($parecer === '') {
                $erro = 'Escreva o parecer explicando o motivo da recusa — ele é enviado ao solicitante.';
            } else {
                $pdo->prepare("UPDATE arbitro_solicitacoes
                               SET status = 'recusada', parecer = ?, analisado_por = ?, analisado_em = NOW()
                               WHERE id = ?")
                    ->execute([$parecer, $_SESSION['usuario_id'], $id]);

                // Finalidade encerrada: o documento comprobatório é eliminado.
                if (!empty($sol['documento_arquivo'])) {
                    $caminho = dirname(__DIR__) . '/' . $sol['documento_arquivo'];
                    if (is_file($caminho)) {
                        @unlink($caminho);
                        $pdo->prepare("UPDATE arbitro_solicitacoes SET documento_arquivo = NULL WHERE id = ?")
                            ->execute([$id]);
                    }
                }
                sh_auditar($pdo, 'credenciamento_recusado', 'arbitro_solicitacoes', $id, $sol['protocolo']);
                $aviso = 'Solicitação ' . $sol['protocolo'] . ' recusada e documento eliminado.';
            }

        } elseif ($acao === 'aprovar') {
            if ($sol['status'] === 'aprovada') {
                $erro = 'Esta solicitação já foi aprovada.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Nome de usuário: o sugerido, ou derivado do nome. Garante unicidade.
                    $base = $sol['username_sugerido'];
                    if (!$base) {
                        $partes = preg_split('/\s+/', mb_strtolower($sol['nome']));
                        $base = preg_replace('/[^a-z0-9.]/', '',
                            iconv('UTF-8', 'ASCII//TRANSLIT', $partes[0] . '.' . end($partes)));
                    }
                    $base = substr($base, 0, 40) ?: 'arbitro';
                    $username = $base;
                    $sufixo = 1;
                    $checa = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = ?");
                    $checa->execute([$username]);
                    while ((int)$checa->fetchColumn() > 0) {
                        $username = $base . $sufixo++;
                        $checa->execute([$username]);
                    }

                    // Senha provisória — exibida uma única vez à coordenação.
                    $senha_provisoria = strtoupper(bin2hex(random_bytes(4)));

                    $ins = $pdo->prepare(
                        "INSERT INTO usuarios (username, password, tipo, nome, email, telefone, cpf,
                                               status, aceite_termos_em, aceite_privacidade_em)
                         VALUES (?, ?, 'arbitro', ?, ?, ?, ?, 'ativo', NOW(), NOW())"
                    );
                    $ins->execute([
                        $username,
                        password_hash($senha_provisoria, PASSWORD_DEFAULT),
                        $sol['nome'], $sol['email'], $sol['telefone'], $sol['cpf'],
                    ]);
                    $usuario_id = (int)$pdo->lastInsertId();

                    if (sh_tabela_existe($pdo, 'arbitro_perfil')) {
                        $pdo->prepare(
                            "INSERT INTO arbitro_perfil (usuario_id, registro_orgao, registro_numero,
                                                         modalidades, anos_experiencia, cidade, uf,
                                                         credenciado_em, credencial_valida_ate)
                             VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))"
                        )->execute([
                            $usuario_id, $sol['registro_orgao'], $sol['registro_numero'],
                            $sol['modalidades'], (int)$sol['anos_experiencia'],
                            $sol['cidade'], $sol['uf'],
                        ]);
                    }

                    $pdo->prepare("UPDATE arbitro_solicitacoes
                                   SET status = 'aprovada', parecer = ?, usuario_id = ?,
                                       analisado_por = ?, analisado_em = NOW()
                                   WHERE id = ?")
                        ->execute([
                            $parecer !== '' ? $parecer : 'Credenciamento aprovado.',
                            $usuario_id, $_SESSION['usuario_id'], $id,
                        ]);

                    $pdo->commit();
                    sh_auditar($pdo, 'credenciamento_aprovado', 'arbitro_solicitacoes', $id,
                               $sol['protocolo'] . ' · usuario ' . $username);

                    $credencial_gerada = [
                        'nome'     => $sol['nome'],
                        'email'    => $sol['email'],
                        'username' => $username,
                        'senha'    => $senha_provisoria,
                    ];
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Erro ao aprovar credenciamento: ' . $ex->getMessage());
                    $erro = 'Não foi possível aprovar agora. Verifique se já existe usuário com este e-mail.';
                }
            }
        }
    }
}

/* ── Consulta ────────────────────────────────────────────────────────────── */
$filtro = $_GET['status'] ?? 'pendentes';
$solicitacoes = [];
$contagens = ['recebida' => 0, 'em_analise' => 0, 'aprovada' => 0, 'recusada' => 0];

if ($modulo_ok) {
    try {
        foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM arbitro_solicitacoes GROUP BY status") as $linha) {
            $contagens[$linha['status']] = (int)$linha['total'];
        }

        if ($filtro === 'pendentes') {
            $stmt = $pdo->query("SELECT * FROM arbitro_solicitacoes
                                 WHERE status IN ('recebida','em_analise')
                                 ORDER BY created_at ASC");
        } elseif (in_array($filtro, ['aprovada', 'recusada'], true)) {
            $stmt = $pdo->prepare("SELECT * FROM arbitro_solicitacoes WHERE status = ? ORDER BY analisado_em DESC");
            $stmt->execute([$filtro]);
        } else {
            $stmt = $pdo->query("SELECT * FROM arbitro_solicitacoes ORDER BY created_at DESC");
        }
        $solicitacoes = $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log('Erro ao listar credenciamentos: ' . $ex->getMessage());
        $erro = 'Erro ao carregar as solicitações.';
    }
}

$formacoes = [
    'educacao_fisica'     => 'Professor(a) de Educação Física',
    'arbitragem_federada' => 'Árbitro(a) federado(a)',
    'tecnico_esportivo'   => 'Técnico(a) esportivo(a)',
    'estudante'           => 'Estudante de licenciatura',
    'outro'               => 'Outra formação',
];
$badges = [
    'recebida'   => ['Recebida',     'agendado'],
    'em_analise' => ['Em análise',   'em_andamento'],
    'aprovada'   => ['Aprovada',     'finalizado'],
    'recusada'   => ['Não aprovada', 'cancelado'],
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="page-hero-inner">
        <span class="page-hero-badge">Arbitragem</span>
        <h2>Credenciamento de árbitros</h2>
        <p>Analise as solicitações de profissionais aplicadores. Aprovar cria o acesso automaticamente.</p>
    </div>
</div>

<div class="container">

    <?php if (!$modulo_ok): ?>
        <div class="alert alert-warning">
            <strong>Módulo não instalado.</strong> Importe <code>scripts/migration_v2.sql</code> no banco
            <code>olimpiasp</code> para habilitar o credenciamento de árbitros.
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endif; ?>
    <?php if ($aviso): ?>
        <div class="alert alert-success"><?= e($aviso) ?></div>
    <?php endif; ?>

    <?php if ($credencial_gerada): ?>
        <div class="admin-panel" style="border-color:var(--green);background:var(--green-bg)">
            <h3 class="panel-title" style="color:var(--green)">
                <i class="fas fa-circle-check"></i> Credencial criada para <?= e($credencial_gerada['nome']) ?>
            </h3>
            <p style="color:var(--ink-soft)">
                Anote e repasse ao árbitro — <strong>a senha provisória não será exibida novamente</strong>.
                Ela deve ser trocada no primeiro acesso.
            </p>
            <table style="margin-top:14px">
                <tbody>
                    <tr><th style="width:180px">Usuário</th><td><strong><?= e($credencial_gerada['username']) ?></strong></td></tr>
                    <tr><th>Senha provisória</th><td><strong style="font-family:var(--font-display);letter-spacing:.1em"><?= e($credencial_gerada['senha']) ?></strong></td></tr>
                    <tr><th>Enviar para</th><td><?= e($credencial_gerada['email']) ?></td></tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="admin-panel">
        <div class="panel-header">
            <h2 class="panel-title"><i class="fas fa-id-card"></i> Solicitações</h2>
            <div class="panel-actions">
                <a href="?status=pendentes" class="btn-small <?= $filtro === 'pendentes' ? '' : 'btn-secondary' ?>">
                    Pendentes (<?= $contagens['recebida'] + $contagens['em_analise'] ?>)
                </a>
                <a href="?status=aprovada" class="btn-small btn-secondary">Aprovadas (<?= $contagens['aprovada'] ?>)</a>
                <a href="?status=recusada" class="btn-small btn-secondary">Recusadas (<?= $contagens['recusada'] ?>)</a>
                <a href="?status=todas" class="btn-small btn-secondary">Todas</a>
            </div>
        </div>

        <?php if (!$solicitacoes): ?>
            <div class="no-data">
                <i class="fas fa-inbox no-games-icon"></i>
                Nenhuma solicitação <?= $filtro === 'pendentes' ? 'pendente' : 'nesta situação' ?> no momento.
            </div>
        <?php else: ?>
            <div class="games-list">
                <?php foreach ($solicitacoes as $sol):
                    [$rotulo, $classe] = $badges[$sol['status']] ?? ['—', ''];
                    $pendente = in_array($sol['status'], ['recebida', 'em_analise'], true);
                ?>
                <article class="game-card">
                    <div class="game-header">
                        <div>
                            <span class="game-sport"><?= e($sol['nome']) ?></span>
                            <div class="detail-label" style="margin-top:4px">Protocolo <?= e($sol['protocolo']) ?></div>
                        </div>
                        <span class="status-badge <?= e($classe) ?>"><?= e($rotulo) ?></span>
                    </div>

                    <div class="game-details">
                        <div class="detail-item">
                            <span class="detail-label">Formação</span>
                            <span><?= e($formacoes[$sol['formacao']] ?? $sol['formacao']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Registro</span>
                            <span><?= $sol['registro_numero'] ? e($sol['registro_orgao'] . ' ' . $sol['registro_numero']) : '<em class="not-assigned">não informado</em>' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Experiência</span>
                            <span><?= (int)$sol['anos_experiencia'] ?> ano(s)</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Modalidades</span>
                            <span><?= e($sol['modalidades']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contato</span>
                            <span><?= e($sol['email']) ?><br><?= e($sol['telefone']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">CPF</span>
                            <span><?= e(sh_mascarar_cpf($sol['cpf'])) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Localidade</span>
                            <span><?= $sol['cidade'] ? e($sol['cidade'] . '/' . $sol['uf']) : '—' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Enviada em</span>
                            <span><?= e(date('d/m/Y H:i', strtotime($sol['created_at']))) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($sol['apresentacao'])): ?>
                        <p class="observations" style="margin-top:16px"><strong>Apresentação:</strong> <?= nl2br(e($sol['apresentacao'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($sol['disponibilidade'])): ?>
                        <p class="observations"><strong>Disponibilidade:</strong> <?= e($sol['disponibilidade']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($sol['parecer'])): ?>
                        <p class="observations"><strong>Parecer:</strong> <?= nl2br(e($sol['parecer'])) ?></p>
                    <?php endif; ?>

                    <div class="game-actions">
                        <?php if (!empty($sol['documento_arquivo'])): ?>
                            <a href="documento_arbitro.php?id=<?= (int)$sol['id'] ?>" target="_blank" class="btn-small btn-secondary">
                                <i class="fas fa-file-arrow-down"></i> Ver documento
                            </a>
                        <?php else: ?>
                            <span class="not-assigned">Sem documento anexado</span>
                        <?php endif; ?>

                        <?php if ($pendente && $modulo_ok): ?>
                            <button type="button" class="btn-small open-modal" data-alvo="modal-<?= (int)$sol['id'] ?>">
                                <i class="fas fa-gavel"></i> Emitir parecer
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($pendente && $modulo_ok): ?>
                    <div class="modal" id="modal-<?= (int)$sol['id'] ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>Parecer — <?= e($sol['protocolo']) ?></h3>
                                <button type="button" class="close-modal">Fechar</button>
                            </div>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">

                                <div class="form-group">
                                    <label for="parecer-<?= (int)$sol['id'] ?>">Parecer da coordenação</label>
                                    <textarea id="parecer-<?= (int)$sol['id'] ?>" name="parecer" rows="4"
                                              placeholder="Obrigatório na recusa. Este texto fica visível ao solicitante na consulta de protocolo."></textarea>
                                </div>

                                <p class="subtitle">
                                    Ao aprovar, o sistema cria o usuário de árbitro com senha provisória
                                    e libera as modalidades informadas.
                                </p>

                                <div class="modal-actions">
                                    <button type="submit" name="acao" value="em_analise" class="btn-secondary">Marcar em análise</button>
                                    <button type="submit" name="acao" value="recusar" class="btn-danger">Recusar</button>
                                    <button type="submit" name="acao" value="aprovar" class="btn-accent">Aprovar e criar acesso</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.open-modal[data-alvo]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var alvo = document.getElementById(btn.dataset.alvo);
        if (alvo) alvo.classList.add('active');
    });
});
document.querySelectorAll('.modal .close-modal').forEach(function (btn) {
    btn.addEventListener('click', function () { btn.closest('.modal').classList.remove('active'); });
});
document.querySelectorAll('.modal').forEach(function (m) {
    m.addEventListener('click', function (ev) { if (ev.target === m) m.classList.remove('active'); });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
