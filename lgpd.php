<?php
/**
 * lgpd.php — Portal do titular de dados.
 * Formulário de requisição (art. 18 da LGPD) com protocolo e prazo de resposta.
 */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Portal LGPD — exerça seus direitos | SportHub';
$page_desc  = 'Solicite acesso, correção, portabilidade, anonimização ou eliminação dos seus dados pessoais no SportHub. Pedido com protocolo e resposta em até 15 dias corridos.';
$active     = 'privacidade';

$tipos = [
    'acesso'                      => ['Confirmação e acesso', 'Quero saber se vocês tratam dados meus e receber uma cópia deles.'],
    'correcao'                    => ['Correção', 'Meus dados estão incompletos, inexatos ou desatualizados.'],
    'anonimizacao'                => ['Anonimização ou bloqueio', 'Quero que dados desnecessários ou excessivos deixem de me identificar.'],
    'eliminacao'                  => ['Eliminação', 'Quero que meus dados sejam apagados.'],
    'portabilidade'               => ['Portabilidade', 'Quero receber meus dados em formato estruturado.'],
    'revogacao'                   => ['Revogação de consentimento', 'Quero voltar atrás em uma autorização que dei.'],
    'informacao_compartilhamento' => ['Informação sobre compartilhamento', 'Quero saber com quem meus dados foram compartilhados.'],
    'oposicao'                    => ['Oposição ao tratamento', 'Discordo de um tratamento feito com base em legítimo interesse.'],
];

$vinculos = [
    'aluno'       => 'Sou aluno(a)',
    'responsavel' => 'Sou responsável por um(a) aluno(a)',
    'arbitro'     => 'Sou árbitro(a) / profissional aplicador',
    'professor'   => 'Sou professor(a) ou da coordenação',
    'outro'       => 'Outro vínculo',
];

$erros    = [];
$sucesso  = null;
$consulta = null;
$dados    = ['nome' => '', 'email' => '', 'vinculo' => 'aluno', 'tipo' => 'acesso', 'descricao' => ''];

/* ── Consulta de protocolo ───────────────────────────────────────────────── */
$protocolo_busca = trim((string)($_GET['protocolo'] ?? ''));
if ($protocolo_busca !== '' && sh_tabela_existe($pdo, 'lgpd_solicitacoes')) {
    try {
        $stmt = $pdo->prepare("SELECT protocolo, tipo, status, resposta, prazo_em, created_at, respondido_em
                               FROM lgpd_solicitacoes WHERE protocolo = ?");
        $stmt->execute([$protocolo_busca]);
        $consulta = $stmt->fetch() ?: 'nao_encontrado';
    } catch (PDOException $e) {
        sh_log_excecao($e, 'consultar protocolo LGPD');
        $consulta = 'nao_encontrado';
    }
}

/* ── Envio ───────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dados as $campo => $_) {
        $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Sua sessão expirou. Recarregue a página e envie novamente.';
    }
    if ($dados['nome'] === '' || mb_strlen($dados['nome']) < 5) {
        $erros[] = 'Informe seu nome completo — precisamos dele para confirmar sua identidade.';
    }
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido: é por ele que enviaremos a resposta.';
    }
    if (!isset($vinculos[$dados['vinculo']])) {
        $erros[] = 'Selecione o seu vínculo com a plataforma.';
    }
    if (!isset($tipos[$dados['tipo']])) {
        $erros[] = 'Selecione o tipo de solicitação.';
    }
    if (empty($_POST['declaracao'])) {
        $erros[] = 'É preciso declarar que as informações prestadas são verdadeiras.';
    }

    if (!$erros) {
        if (!sh_tabela_existe($pdo, 'lgpd_solicitacoes')) {
            $erros[] = 'O portal ainda não foi instalado neste servidor. Execute scripts/migration_v2.sql '
                     . 'ou escreva para ' . SH_EMAIL_DPO . '.';
        } else {
            try {
                $protocolo = sh_protocolo($pdo, 'lgpd_solicitacoes', 'protocolo', 'LGPD');
                $prazo     = (new DateTimeImmutable('today'))->modify('+15 days');

                $stmt = $pdo->prepare(
                    "INSERT INTO lgpd_solicitacoes
                        (protocolo, nome, email, vinculo, tipo, descricao, status, prazo_em, ip)
                     VALUES (?, ?, ?, ?, ?, ?, 'recebida', ?, ?)"
                );
                $stmt->execute([
                    $protocolo, $dados['nome'], $dados['email'], $dados['vinculo'], $dados['tipo'],
                    $dados['descricao'] !== '' ? $dados['descricao'] : null,
                    $prazo->format('Y-m-d'), sh_ip(),
                ]);

                sh_auditar($pdo, 'lgpd_solicitacao_recebida', 'lgpd_solicitacoes',
                           (int)$pdo->lastInsertId(), $protocolo . ' · ' . $dados['tipo']);

                $sucesso = [
                    'protocolo' => $protocolo,
                    'prazo'     => $prazo,
                    'email'     => $dados['email'],
                    'tipo'      => $tipos[$dados['tipo']][0],
                ];
            } catch (PDOException $e) {
                sh_log_excecao($e, 'registrar solicitação LGPD');
                $erros[] = 'Não foi possível registrar sua solicitação agora. Escreva para '
                         . SH_EMAIL_DPO . ' que atenderemos pelo mesmo prazo legal.';
            }
        }
    }
}

$status_rotulo = [
    'recebida'   => ['Recebida',      'tag-blue',  'fa-inbox'],
    'em_analise' => ['Em análise',    'tag-amber', 'fa-hourglass-half'],
    'atendida'   => ['Atendida',      'tag-green', 'fa-circle-check'],
    'recusada'   => ['Não atendida',  'tag-red',   'fa-circle-xmark'],
];

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><i class="fas fa-user-shield"></i> Direitos do titular</span>
        <h1>Portal LGPD</h1>
        <p>
            A Lei nº 13.709/2018 garante que você mande nos seus próprios dados. Aqui você exerce
            esse direito de forma direta: preenche o pedido, recebe um protocolo e acompanha a
            resposta — dentro do prazo legal de 15 dias corridos.
        </p>
        <div class="legal-meta">
            <span class="tag on-dark"><i class="fas fa-clock"></i> Resposta em até 15 dias</span>
            <span class="tag on-dark"><i class="fas fa-money-bill"></i> Gratuito</span>
            <span class="tag on-dark"><i class="fas fa-receipt"></i> Com protocolo</span>
        </div>
    </div>
</section>

<?php if ($sucesso): ?>
<!-- ═══════════ CONFIRMAÇÃO ═══════════ -->
<section class="section-sm">
    <div class="wrap-narrow">
        <div class="form-card">
            <div class="alert alert-success">
                <i class="fas fa-circle-check"></i>
                <div>
                    <strong>Solicitação registrada</strong>
                    Uma cópia deste protocolo foi enviada para <?= e($sucesso['email']) ?>.
                </div>
            </div>

            <div class="protocol-box">
                <span class="protocol-label">Protocolo da solicitação</span>
                <span class="protocol-code"><?= e($sucesso['protocolo']) ?></span>
            </div>

            <div class="data-table-wrap">
                <table class="data">
                    <tbody>
                        <tr><td class="u-width-200px">Tipo de pedido</td><td><strong><?= e($sucesso['tipo']) ?></strong></td></tr>
                        <tr><td class="u-color-muted">Recebido em</td><td><?= e(date('d/m/Y \à\s H:i')) ?></td></tr>
                        <tr><td class="u-color-muted">Prazo legal de resposta</td><td><strong><?= e($sucesso['prazo']->format('d/m/Y')) ?></strong> (art. 19, II da LGPD)</td></tr>
                        <tr><td class="u-color-muted">Situação</td><td><span class="tag tag-blue"><i class="fas fa-inbox"></i> Recebida</span></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="callout">
                <i class="fas fa-id-card"></i>
                <div>
                    <strong>Podemos pedir confirmação de identidade</strong>
                    <p>Antes de entregar ou apagar dados, precisamos ter certeza de que o pedido é seu. Se necessário, o encarregado entrará em contato pelo e-mail informado solicitando informação adicional — nunca pedimos senha.</p>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= e(sh_url('lgpd.php?protocolo=' . urlencode($sucesso['protocolo']))) ?>" class="btn btn-primary">Acompanhar protocolo</a>
                <a href="<?= e(sh_url('privacidade.php')) ?>" class="btn btn-ghost">Ler a Política de Privacidade</a>
            </div>
        </div>
    </div>
</section>

<?php else: ?>

<!-- ═══════════ CONSULTA ═══════════ -->
<?php if ($consulta !== null): ?>
<section class="section-sm u-padding-bottom-0">
    <div class="wrap-narrow">
        <?php if ($consulta === 'nao_encontrado'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-magnifying-glass"></i>
                <div>
                    <strong>Protocolo não encontrado</strong>
                    Confira se digitou exatamente como recebeu (ex.: LGPD-<?= date('Y') ?>-0001).
                </div>
            </div>
        <?php else:
            [$rotulo, $classe, $icone] = $status_rotulo[$consulta['status']] ?? ['Em processamento', 'tag', 'fa-clock']; ?>
            <div class="form-card mb-3">
                <div class="row-between">
                    <div>
                        <div class="role-sub">Protocolo <?= e($consulta['protocolo']) ?></div>
                        <h3 class="mt-1"><?= e($tipos[$consulta['tipo']][0] ?? 'Solicitação') ?></h3>
                        <p class="small mt-1">
                            Recebida em <?= e(date('d/m/Y', strtotime($consulta['created_at']))) ?>
                            <?php if ($consulta['prazo_em']): ?>
                                · prazo até <?= e(date('d/m/Y', strtotime($consulta['prazo_em']))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="tag <?= e($classe) ?>"><i class="fas <?= e($icone) ?>"></i> <?= e($rotulo) ?></span>
                </div>
                <?php if (!empty($consulta['resposta'])): ?>
                    <div class="callout mt-3 u-margin-bottom-0">
                        <i class="fas fa-comment-dots"></i>
                        <div>
                            <strong>Resposta do encarregado</strong>
                            <p><?= nl2br(e($consulta['resposta'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════ SEUS DIREITOS ═══════════ -->
<section class="section-sm">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Art. 18 da LGPD</span>
            <h2>O que você pode pedir</h2>
            <p>Todos os pedidos abaixo são gratuitos e não exigem justificativa. Escolha o que se aplica ao seu caso no formulário.</p>
        </div>

        <div class="grid grid-4">
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-eye"></i></div>
                <h3 class="u-font-size-1rem">Acesso</h3>
                <p class="small">Saber quais dados temos sobre você e receber uma cópia deles.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-pen-to-square"></i></div>
                <h3 class="u-font-size-1rem">Correção</h3>
                <p class="small">Corrigir informação errada, incompleta ou desatualizada.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-eraser"></i></div>
                <h3 class="u-font-size-1rem">Eliminação</h3>
                <p class="small">Apagar dados tratados com base no seu consentimento.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-file-export"></i></div>
                <h3 class="u-font-size-1rem">Portabilidade</h3>
                <p class="small">Receber seus dados em formato estruturado para levar a outro serviço.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-user-secret"></i></div>
                <h3 class="u-font-size-1rem">Anonimização</h3>
                <p class="small">Fazer com que dados desnecessários deixem de identificar você.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-rotate-left"></i></div>
                <h3 class="u-font-size-1rem">Revogação</h3>
                <p class="small">Voltar atrás em qualquer consentimento que tenha dado.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-share-nodes"></i></div>
                <h3 class="u-font-size-1rem">Compartilhamento</h3>
                <p class="small">Saber com quais entidades seus dados foram compartilhados.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-hand"></i></div>
                <h3 class="u-font-size-1rem">Oposição</h3>
                <p class="small">Discordar de tratamento feito com base em legítimo interesse.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ FORMULÁRIO ═══════════ -->
<section class="section-sm u-background-surface-2">
    <div class="wrap-narrow">
        <div class="section-head reveal">
            <span class="eyebrow">Requisição do titular</span>
            <h2>Faça sua solicitação</h2>
            <p>Leva menos de dois minutos. Você recebe um protocolo na hora e a resposta por e-mail.</p>
        </div>

        <div class="form-card">
            <?php if ($erros): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <div>
                        <strong>Revise os campos abaixo</strong>
                        <ul><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= csrf_field() ?>

                <fieldset class="fieldset">
                    <legend><i class="fas fa-user"></i> Identificação</legend>
                    <p class="fieldset-hint">Precisamos confirmar que o pedido é seu — é o que impede que alguém peça seus dados no seu lugar.</p>

                    <div class="field-row">
                        <div class="field">
                            <label for="nome">Nome completo <span class="req">*</span></label>
                            <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" autocomplete="name" required>
                        </div>
                        <div class="field">
                            <label for="email">E-mail para resposta <span class="req">*</span></label>
                            <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="vinculo">Seu vínculo com a plataforma <span class="req">*</span></label>
                        <select id="vinculo" name="vinculo" required>
                            <?php foreach ($vinculos as $valor => $rotulo): ?>
                                <option value="<?= e($valor) ?>"<?= $dados['vinculo'] === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Se você é responsável por um menor de idade, informe no campo de detalhes o nome do aluno.</span>
                    </div>
                </fieldset>

                <fieldset class="fieldset">
                    <legend><i class="fas fa-list-check"></i> O que você quer solicitar</legend>

                    <div class="radio-cards">
                        <?php foreach ($tipos as $valor => [$titulo, $sub]): ?>
                            <label class="radio-card">
                                <input type="radio" name="tipo" value="<?= e($valor) ?>" <?= $dados['tipo'] === $valor ? 'checked' : '' ?>>
                                <span>
                                    <span class="radio-card-title"><?= e($titulo) ?></span>
                                    <span class="radio-card-sub"><?= e($sub) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="field mt-3">
                        <label for="descricao">Detalhes do pedido</label>
                        <textarea id="descricao" name="descricao"
                                  placeholder="Descreva o que precisa. Se souber, informe a escola, a turma ou o período do campeonato — isso acelera a localização dos dados."><?= e($dados['descricao']) ?></textarea>
                    </div>
                </fieldset>

                <fieldset class="fieldset">
                    <legend><i class="fas fa-file-signature"></i> Declaração</legend>
                    <label class="check">
                        <input type="checkbox" name="declaracao" value="1" required <?= !empty($_POST['declaracao']) ? 'checked' : '' ?>>
                        <span class="check-text">
                            <strong>Declaro que as informações são verdadeiras</strong>
                            Sou o titular dos dados ou seu representante legal, e estou ciente de que o
                            SportHub pode solicitar comprovação adicional de identidade antes de atender
                            ao pedido, conforme o art. 18, §5º da LGPD.
                        </span>
                    </label>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Enviar solicitação <i class="fas fa-paper-plane"></i>
                    </button>
                    <a href="mailto:<?= e(SH_EMAIL_DPO) ?>" class="btn btn-ghost">Prefiro escrever ao DPO</a>
                </div>

                <p class="form-legal">
                    Os dados enviados neste formulário são usados exclusivamente para processar sua
                    solicitação e comprovar o atendimento ao prazo legal. Guardamos o registro por 5 anos,
                    conforme descrito na <a href="<?= e(sh_url('privacidade.php') . '#retencao') ?>">Política de Privacidade</a>.
                </p>
            </form>
        </div>

        <!-- Consulta de protocolo -->
        <div class="form-card mt-3">
            <h3><i class="fas fa-magnifying-glass u-color-ember-2"></i> Acompanhar uma solicitação</h3>
            <p class="small mt-1">Informe o protocolo recebido para ver a situação atual do seu pedido.</p>
            <form method="GET" class="row mt-2 u-gap-10px-2">
                <div class="field u-flex-1">
                    <label class="sr-only" for="protocolo">Protocolo</label>
                    <input type="text" id="protocolo" name="protocolo" value="<?= e($protocolo_busca) ?>"
                           placeholder="LGPD-<?= date('Y') ?>-0001" required>
                </div>
                <button type="submit" class="btn btn-outline">Consultar</button>
            </form>
        </div>
    </div>
</section>

<!-- ═══════════ DOCUMENTOS ═══════════ -->
<section class="section-sm">
    <div class="wrap">
        <div class="section-head center reveal">
            <span class="eyebrow">Transparência</span>
            <h2>Documentos e canais oficiais</h2>
        </div>
        <div class="grid grid-4">
            <a href="<?= e(sh_url('privacidade.php')) ?>" class="card reveal">
                <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                <h3 class="u-font-size-1rem">Política de Privacidade</h3>
                <p class="small">Quais dados tratamos, por quê e com qual base legal.</p>
            </a>
            <a href="<?= e(sh_url('termos.php')) ?>" class="card reveal">
                <div class="card-icon"><i class="fas fa-file-contract"></i></div>
                <h3 class="u-font-size-1rem">Termos de Uso</h3>
                <p class="small">Regras da assinatura, dos perfis e da arbitragem.</p>
            </a>
            <a href="<?= e(sh_url('cookies.php')) ?>" class="card reveal">
                <div class="card-icon"><i class="fas fa-cookie-bite"></i></div>
                <h3 class="u-font-size-1rem">Política de Cookies</h3>
                <p class="small">Cada cookie usado, sua finalidade e sua duração.</p>
            </a>
            <a href="mailto:<?= e(SH_EMAIL_DPO) ?>" class="card reveal">
                <div class="card-icon"><i class="fas fa-envelope-open-text"></i></div>
                <h3 class="u-font-size-1rem">Encarregado (DPO)</h3>
                <p class="small"><?= e(SH_EMAIL_DPO) ?></p>
            </a>
        </div>

        <div class="callout reveal mt-3">
            <i class="fas fa-landmark"></i>
            <div>
                <strong>Não ficou satisfeito com a resposta?</strong>
                <p>Você pode apresentar reclamação à Autoridade Nacional de Proteção de Dados (ANPD) pelos canais oficiais do órgão, conforme o art. 18, §1º da LGPD.</p>
            </div>
        </div>
    </div>
</section>

<?php endif; ?>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
