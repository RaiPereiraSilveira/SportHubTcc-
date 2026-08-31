<?php
/**
 * assinar.php — contratação da assinatura anual.
 *
 * Não coletamos dados de cartão nesta tela: a escola escolhe a forma de
 * pagamento e recebe o link seguro por e-mail depois do período de teste.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/pagamento.php';
require_once __DIR__ . '/includes/email.php';

$planos = sh_planos($pdo);
$slug   = $_GET['plano'] ?? ($_POST['plano'] ?? 'pro');

$plano_escolhido = null;
foreach ($planos as $p) {
    if ($p['slug'] === $slug) { $plano_escolhido = $p; break; }
}
if (!$plano_escolhido) {
    $plano_escolhido = $planos[1] ?? $planos[0];
    $slug = $plano_escolhido['slug'];
}

$page_title = 'Contratar o plano ' . $plano_escolhido['nome'] . ' — SportHub';
$page_desc  = 'Contrate a assinatura anual do SportHub com 30 dias de teste gratuito. Sem cartão de crédito e sem taxa de implantação.';
$active     = 'planos';

$erros    = [];
$sucesso  = null;
$dados    = [
    'escola' => '', 'cnpj' => '', 'cidade' => '', 'uf' => '',
    'responsavel' => '', 'cargo' => '', 'email' => '', 'telefone' => '',
    'forma_pagamento' => 'boleto', 'observacoes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dados as $campo => $_) {
        $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Sua sessão expirou. Recarregue a página e envie novamente.';
    }
    if ($dados['escola'] === '')       $erros[] = 'Informe o nome da escola ou instituição.';
    if ($dados['responsavel'] === '')  $erros[] = 'Informe o nome do responsável pela contratação.';
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail institucional válido.';
    if ($dados['telefone'] === '')     $erros[] = 'Informe um telefone para contato.';
    if (!in_array($dados['forma_pagamento'], ['boleto', 'pix', 'cartao', 'empenho'], true)) {
        $erros[] = 'Escolha uma forma de pagamento.';
    }
    if (empty($_POST['aceite_termos'])) {
        $erros[] = 'É preciso aceitar os Termos de Uso e a Política de Privacidade para contratar.';
    }
    if ($dados['cnpj'] !== '' && strlen(preg_replace('/\D/', '', $dados['cnpj'])) !== 14) {
        $erros[] = 'O CNPJ informado não tem 14 dígitos.';
    }

    if (!$erros) {
        if (!sh_tabela_existe($pdo, 'assinaturas')) {
            $erros[] = 'O módulo de assinaturas ainda não foi instalado neste servidor. '
                     . 'Execute scripts/migration_v2.sql e tente novamente.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO escolas (nome, cnpj, cidade, uf, responsavel, email, telefone)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $dados['escola'],
                    $dados['cnpj']   !== '' ? $dados['cnpj']   : null,
                    $dados['cidade'] !== '' ? $dados['cidade'] : null,
                    $dados['uf']     !== '' ? strtoupper($dados['uf']) : null,
                    $dados['responsavel'],
                    $dados['email'],
                    $dados['telefone'],
                ]);
                $escola_id = (int)$pdo->lastInsertId();

                $codigo = sh_protocolo($pdo, 'assinaturas', 'codigo', 'SH');
                $inicio = new DateTimeImmutable('today');
                $fim    = $inicio->modify('+1 year');

                $plano_id = (int)$plano_escolhido['id'];
                if ($plano_id === 0) {
                    $q = $pdo->prepare("SELECT id FROM planos WHERE slug = ?");
                    $q->execute([$slug]);
                    $plano_id = (int)$q->fetchColumn();
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO assinaturas
                        (codigo, escola_id, plano_id, responsavel, email, telefone, cargo,
                         forma_pagamento, valor, status, inicio_em, expira_em, observacoes,
                         aceite_termos, ip_aceite)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'trial', ?, ?, ?, 1, ?)"
                );
                $stmt->execute([
                    $codigo, $escola_id, $plano_id,
                    $dados['responsavel'], $dados['email'], $dados['telefone'],
                    $dados['cargo'] !== '' ? $dados['cargo'] : null,
                    $dados['forma_pagamento'],
                    $plano_escolhido['preco_anual'],
                    $inicio->format('Y-m-d'),
                    $fim->format('Y-m-d'),
                    $dados['observacoes'] !== '' ? $dados['observacoes'] : null,
                    sh_ip(),
                ]);

                $assinatura_id = (int)$pdo->lastInsertId();
                $pdo->commit();

                sh_registrar_consentimento($pdo, 'termos', $dados['email'], true);
                if (!empty($_POST['aceite_comunicacoes'])) {
                    sh_registrar_consentimento($pdo, 'comunicacoes', $dados['email'], true);
                }
                sh_auditar($pdo, 'assinatura_criada', 'assinaturas', $assinatura_id,
                           $codigo . ' · plano ' . $slug);

                /* Cobrança do primeiro ano (SH-41).
                   A contratação registrava a assinatura e parava ali: não havia
                   valor a receber, vencimento nem baixa. A cobrança nasce com
                   vencimento no fim do teste de 30 dias — que é quando a escola
                   de fato decide continuar. */
                $cobranca = sh_criar_cobranca(
                    $pdo,
                    $assinatura_id,
                    $plano_escolhido['preco_anual'],
                    (clone $inicio)->modify('+30 days')->format('Y-m-d'),
                    'Assinatura anual ' . $plano_escolhido['nome'] . ' — ' . $codigo
                );

                /* Confirmação por e-mail (SH-42). O protocolo continua na tela:
                   se o envio falhar, ninguém fica sem o número. */
                if ($dados['email'] !== '') {
                    $texto_email = "Contratação registrada.\n\n"
                        . "Código: {$codigo}\n"
                        . "Plano: {$plano_escolhido['nome']}\n"
                        . "Valor anual: R$ " . sh_money($plano_escolhido['preco_anual']) . "\n"
                        . "Teste gratuito até " . $inicio->format('d/m/Y') . "\n\n"
                        . "Guarde o código: é por ele que a coordenação localiza a contratação.\n\n"
                        . SH_NOME;
                    sh_mail($dados['email'], 'Contratação registrada — ' . $codigo, $texto_email, [
                        'html' => sh_email_modelo('Contratação registrada', [
                            'A contratação do plano <strong>' . e($plano_escolhido['nome'])
                                . '</strong> foi registrada.',
                            'Código: <strong>' . e($codigo) . '</strong>',
                            'Valor anual: R$ ' . sh_money($plano_escolhido['preco_anual'])
                                . ' — com 30 dias de teste gratuito antes da primeira cobrança.',
                            'Guarde este código: é por ele que a coordenação localiza a contratação.',
                        ]),
                        'contexto' => 'assinatura_criada',
                    ]);
                }

                $sucesso = [
                    'codigo'      => $codigo,
                    'plano'       => $plano_escolhido['nome'],
                    'valor'       => $plano_escolhido['preco_anual'],
                    'trial_fim'   => $inicio->modify('+30 days'),
                    'vigencia_fim'=> $fim,
                    'email'       => $dados['email'],
                ];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                sh_log_excecao($e, 'registrar assinatura');
                $erros[] = 'Não foi possível registrar a contratação agora. Tente novamente em instantes '
                         . 'ou fale com a equipe pelo WhatsApp.';
            }
        }
    }
}

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><span class="dot"></span> Contratação</span>
        <h1><?= $sucesso ? 'Contratação registrada' : 'Contratar o plano ' . e($plano_escolhido['nome']) ?></h1>
        <p>
            <?php if ($sucesso): ?>
                Guarde o código abaixo — ele identifica a assinatura da sua escola em qualquer atendimento.
            <?php else: ?>
                Preencha os dados da instituição para liberar o acesso. O período de teste de 30 dias
                começa hoje e nenhuma cobrança é feita antes da sua confirmação.
            <?php endif; ?>
        </p>
    </div>
</section>

<section class="section-sm">
    <div class="wrap">

    <?php if ($sucesso): ?>
        <!-- ═══════════ CONFIRMAÇÃO ═══════════ -->
        <div class="form-card u-max-width-760px">
            <div class="alert alert-success">
                <i class="fas fa-circle-check"></i>
                <div>
                    <strong>Acesso liberado em modo de teste</strong>
                    Enviamos as instruções de primeiro acesso para <?= e($sucesso['email']) ?>.
                </div>
            </div>

            <div class="protocol-box">
                <span class="protocol-label">Código da assinatura</span>
                <span class="protocol-code"><?= e($sucesso['codigo']) ?></span>
            </div>

            <div class="data-table-wrap">
                <table class="data">
                    <tbody>
                        <tr><td class="u-color-muted">Plano contratado</td><td><strong><?= e($sucesso['plano']) ?></strong></td></tr>
                        <tr><td class="u-color-muted">Valor anual</td><td><strong>R$ <?= e(sh_money($sucesso['valor'])) ?></strong></td></tr>
                        <tr><td class="u-color-muted">Teste gratuito até</td><td><?= e($sucesso['trial_fim']->format('d/m/Y')) ?></td></tr>
                        <tr><td class="u-color-muted">Vigência da assinatura</td><td>até <?= e($sucesso['vigencia_fim']->format('d/m/Y')) ?></td></tr>
                        <tr><td class="u-color-muted">Situação</td><td><span class="tag tag-amber"><i class="fas fa-hourglass-half"></i> Em teste</span></td></tr>
                    </tbody>
                </table>
            </div>

            <h3 class="mt-3">Próximos passos</h3>
            <ol class="mt-1 u-display-grid-3">
                <li class="small">Acesse a plataforma com o usuário administrador enviado por e-mail.</li>
                <li class="small">Cadastre as modalidades e inscreva os times por turma.</li>
                <li class="small">Envie o link de credenciamento aos árbitros e aprove as solicitações.</li>
                <li class="small">Antes do 30º dia, confirme a continuidade para gerar a cobrança na forma escolhida.</li>
            </ol>

            <div class="form-actions">
                <a href="<?= e(sh_url('login.php')) ?>" class="btn btn-primary">Ir para a plataforma <i class="fas fa-arrow-right"></i></a>
                <a href="<?= e(sh_url('como-funciona.php')) ?>" class="btn btn-outline">Ler o guia de implantação</a>
            </div>

            <p class="form-legal">
                Esta contratação foi registrada em <?= e(date('d/m/Y \à\s H:i')) ?> com aceite dos
                <a href="<?= e(sh_url('termos.php')) ?>">Termos de Uso</a> e da
                <a href="<?= e(sh_url('privacidade.php')) ?>">Política de Privacidade</a> (versão <?= e(SH_VERSAO_POLITICA) ?>).
            </p>
        </div>

    <?php else: ?>
        <!-- ═══════════ FORMULÁRIO ═══════════ -->
        <div class="assinar-layout u-display-grid">
            <style<?= sh_nonce_attr() ?>>
                @media (min-width: 980px) {
                    .assinar-layout { grid-template-columns: minmax(0,1fr) 340px; gap: 32px !important; }
                    .assinar-resumo { position: sticky; top: calc(var(--nav-h) + 22px); }
                }
            </style>

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
                    <input type="hidden" name="plano" value="<?= e($slug) ?>">

                    <fieldset class="fieldset">
                        <legend><i class="fas fa-school"></i> Dados da instituição</legend>
                        <p class="fieldset-hint">Usamos essas informações para emitir a nota fiscal e identificar o campeonato.</p>

                        <div class="field">
                            <label for="escola">Nome da escola ou instituição <span class="req">*</span></label>
                            <input type="text" id="escola" name="escola" value="<?= e($dados['escola']) ?>" required>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label for="cnpj">CNPJ</label>
                                <input type="text" id="cnpj" name="cnpj" value="<?= e($dados['cnpj']) ?>"
                                       placeholder="00.000.000/0000-00" inputmode="numeric">
                                <span class="hint">Opcional agora — necessário só para a emissão da nota.</span>
                            </div>
                            <div class="field">
                                <label for="cidade">Cidade</label>
                                <input type="text" id="cidade" name="cidade" value="<?= e($dados['cidade']) ?>">
                            </div>
                            <div class="field">
                                <label for="uf">UF</label>
                                <select id="uf" name="uf">
                                    <option value="">—</option>
                                    <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                        <option value="<?= e($uf) ?>"<?= $dados['uf'] === $uf ? ' selected' : '' ?>><?= e($uf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend><i class="fas fa-user-tie"></i> Responsável pela contratação</legend>
                        <p class="fieldset-hint">Esta pessoa receberá o acesso de administrador do campeonato.</p>

                        <div class="field-row">
                            <div class="field">
                                <label for="responsavel">Nome completo <span class="req">*</span></label>
                                <input type="text" id="responsavel" name="responsavel" value="<?= e($dados['responsavel']) ?>" required>
                            </div>
                            <div class="field">
                                <label for="cargo">Cargo</label>
                                <input type="text" id="cargo" name="cargo" value="<?= e($dados['cargo']) ?>"
                                       placeholder="Coordenação, direção, professor…">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label for="email">E-mail institucional <span class="req">*</span></label>
                                <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>" required>
                            </div>
                            <div class="field">
                                <label for="telefone">Telefone / WhatsApp <span class="req">*</span></label>
                                <input type="tel" id="telefone" name="telefone" value="<?= e($dados['telefone']) ?>"
                                       placeholder="(00) 00000-0000" required>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend><i class="fas fa-receipt"></i> Forma de pagamento</legend>
                        <p class="fieldset-hint">
                            Nenhum dado de cartão é pedido aqui. Depois do teste de 30 dias, enviamos
                            o link seguro ou o boleto para o e-mail informado acima.
                        </p>

                        <div class="radio-cards">
                            <?php
                            $formas = [
                                'boleto'  => ['Boleto bancário', 'Vencimento em 10 dias'],
                                'pix'     => ['Pix',             'Confirmação imediata'],
                                'cartao'  => ['Cartão',          'Em até 12x sem alterar o total'],
                                'empenho' => ['Empenho',         'Para escolas da rede pública'],
                            ];
                            foreach ($formas as $valor => [$titulo, $sub]): ?>
                                <label class="radio-card">
                                    <input type="radio" name="forma_pagamento" value="<?= e($valor) ?>"
                                           <?= $dados['forma_pagamento'] === $valor ? 'checked' : '' ?>>
                                    <span>
                                        <span class="radio-card-title"><?= e($titulo) ?></span>
                                        <span class="radio-card-sub"><?= e($sub) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="field mt-3">
                            <label for="observacoes">Alguma observação sobre o campeonato?</label>
                            <textarea id="observacoes" name="observacoes" placeholder="Ex.: interclasse em setembro, 18 turmas, 4 modalidades…"><?= e($dados['observacoes']) ?></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend><i class="fas fa-file-signature"></i> Aceite</legend>
                        <label class="check">
                            <input type="checkbox" name="aceite_termos" value="1" required <?= !empty($_POST['aceite_termos']) ? 'checked' : '' ?>>
                            <span class="check-text">
                                <strong>Li e aceito os termos da contratação</strong>
                                Declaro que li os <a href="<?= e(sh_url('termos.php')) ?>" target="_blank">Termos de Uso</a>
                                e a <a href="<?= e(sh_url('privacidade.php')) ?>" target="_blank">Política de Privacidade</a>,
                                e que tenho poderes para contratar em nome da instituição informada.
                            </span>
                        </label>

                        <label class="check mt-2">
                            <input type="checkbox" name="aceite_comunicacoes" value="1">
                            <span class="check-text">
                                <strong>Quero receber novidades da plataforma <span class="muted">(opcional)</span></strong>
                                Comunicados sobre novos recursos e boas práticas de organização esportiva.
                                Você pode revogar esse consentimento quando quiser.
                            </span>
                        </label>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Liberar acesso e iniciar o teste <i class="fas fa-arrow-right"></i>
                        </button>
                        <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-ghost">Voltar aos planos</a>
                    </div>

                    <p class="form-legal">
                        O registro do aceite guarda data, hora, endereço IP e a versão <?= e(SH_VERSAO_POLITICA) ?>
                        dos documentos — comprovação exigida pelo art. 8º, §1º da LGPD. Você pode solicitar
                        a exclusão desses dados a qualquer momento pelo <a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD</a>.
                    </p>
                </form>
            </div>

            <!-- Resumo do plano -->
            <aside class="assinar-resumo">
                <div class="card u-padding-26px">
                    <div class="role-sub">Plano selecionado</div>
                    <h3 class="mt-1 u-font-size-1-4rem"><?= e($plano_escolhido['nome']) ?></h3>
                    <p class="small mt-1"><?= e($plano_escolhido['descricao']) ?></p>

                    <div class="plan-price u-margin-20px04px">
                        <span class="plan-currency">R$</span>
                        <span class="plan-value u-font-size-2-3rem"><?= e(sh_money($plano_escolhido['preco_anual'])) ?></span>
                    </div>
                    <div class="plan-period">por ano letivo</div>
                    <span class="plan-equiv"><i class="fas fa-tag"></i> R$ <?= e(sh_money($plano_escolhido['preco_mensal_equivalente'])) ?>/mês equivalente</span>

                    <hr class="divider u-margin-22px0">

                    <ul class="plan-features u-margin-0-2">
                        <li><i class="fas fa-check"></i> <?= $plano_escolhido['limite_times'] === null ? 'Times ilimitados' : e((int)$plano_escolhido['limite_times']) . ' times inscritos' ?></li>
                        <li><i class="fas fa-check"></i> <?= $plano_escolhido['limite_modalidades'] === null ? 'Modalidades ilimitadas' : e((int)$plano_escolhido['limite_modalidades']) . ' modalidades' ?></li>
                        <li><i class="fas fa-check"></i> <?= $plano_escolhido['limite_arbitros'] === null ? 'Árbitros ilimitados' : e((int)$plano_escolhido['limite_arbitros']) . ' árbitros credenciados' ?></li>
                        <li><i class="fas fa-check"></i> Alunos, jogos e súmulas ilimitados</li>
                    </ul>

                    <div class="alert alert-info mt-3 u-margin-bottom-0">
                        <i class="fas fa-circle-info"></i>
                        <div><strong>Hoje você paga R$ 0,00</strong> A cobrança só é gerada após os 30 dias de teste e mediante sua confirmação.</div>
                    </div>
                </div>

                <div class="row mt-2 u-justify-content-center-2">
                    <?php foreach ($planos as $p): if ($p['slug'] === $slug) continue; ?>
                        <a href="<?= e(sh_url('assinar.php?plano=' . urlencode($p['slug']))) ?>" class="small u-color-ember-3">
                            Trocar para <?= e($p['nome']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
