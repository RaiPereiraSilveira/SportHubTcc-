<?php
/**
 * cadastro-arbitro.php — credenciamento do profissional aplicador (juiz).
 *
 * A solicitação entra com status "recebida" e só vira um acesso de árbitro
 * depois do parecer da coordenação (admin/solicitacoes_arbitros.php).
 */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Credenciamento de árbitros — SportHub';
$page_desc  = 'Cadastre-se como profissional aplicador (árbitro) do SportHub: envie sua formação, registro profissional e modalidades para análise da coordenação.';
$active     = 'arbitros';

$modalidades_disponiveis = sh_modalidades_arbitragem();

$formacoes = [
    'educacao_fisica'     => 'Professor(a) de Educação Física',
    'arbitragem_federada' => 'Árbitro(a) federado(a)',
    'tecnico_esportivo'   => 'Técnico(a) esportivo(a)',
    'estudante'           => 'Estudante de licenciatura em Ed. Física',
    'outro'               => 'Outra formação',
];

$erros   = [];
$sucesso = null;
$consulta = null;

$dados = [
    'nome' => '', 'email' => '', 'telefone' => '', 'cpf' => '', 'data_nascimento' => '',
    'cidade' => '', 'uf' => '', 'escola_vinculo' => '', 'formacao' => 'educacao_fisica',
    'registro_orgao' => '', 'registro_numero' => '', 'anos_experiencia' => '',
    'disponibilidade' => '', 'apresentacao' => '', 'username_sugerido' => '',
];
$modalidades_marcadas = [];

/* ── Consulta de protocolo ───────────────────────────────────────────────── */
$protocolo_busca = trim((string)($_GET['protocolo'] ?? ''));
if ($protocolo_busca !== '' && sh_tabela_existe($pdo, 'arbitro_solicitacoes')) {
    try {
        $stmt = $pdo->prepare("SELECT protocolo, nome, status, parecer, created_at, analisado_em
                               FROM arbitro_solicitacoes WHERE protocolo = ?");
        $stmt->execute([$protocolo_busca]);
        $consulta = $stmt->fetch() ?: 'nao_encontrado';
    } catch (PDOException $e) {
        error_log('Erro ao consultar protocolo: ' . $e->getMessage());
        $consulta = 'nao_encontrado';
    }
}

/* ── Envio do formulário ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dados as $campo => $_) {
        $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $modalidades_marcadas = array_values(array_intersect(
        (array)($_POST['modalidades'] ?? []),
        $modalidades_disponiveis
    ));

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Sua sessão expirou. Recarregue a página e envie novamente.';
    }
    if ($dados['nome'] === '' || mb_strlen($dados['nome']) < 5) {
        $erros[] = 'Informe seu nome completo.';
    }
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido — é por ele que você receberá o resultado.';
    }
    if ($dados['telefone'] === '') {
        $erros[] = 'Informe um telefone de contato.';
    }
    if (!sh_cpf_valido($dados['cpf'])) {
        $erros[] = 'O CPF informado não é válido.';
    }
    if (!isset($formacoes[$dados['formacao']])) {
        $erros[] = 'Selecione sua formação.';
    }
    if (!$modalidades_marcadas) {
        $erros[] = 'Selecione ao menos uma modalidade que você está apto a apitar.';
    }
    if ($dados['anos_experiencia'] === '' || !is_numeric($dados['anos_experiencia']) || (int)$dados['anos_experiencia'] < 0) {
        $erros[] = 'Informe seus anos de experiência (use 0 se estiver começando).';
    }
    if ($dados['username_sugerido'] !== '' && !preg_match('/^[a-z0-9._-]{4,30}$/i', $dados['username_sugerido'])) {
        $erros[] = 'O usuário sugerido deve ter de 4 a 30 caracteres, sem espaços ou acentos.';
    }
    foreach (['aceite_termos' => 'os Termos de Uso',
              'aceite_privacidade' => 'a Política de Privacidade',
              'aceite_conduta' => 'o Código de Conduta da arbitragem'] as $campo => $rotulo) {
        if (empty($_POST[$campo])) {
            $erros[] = 'É obrigatório aceitar ' . $rotulo . '.';
        }
    }

    /* Upload do documento comprobatório */
    $documento_salvo = null;
    if (!empty($_FILES['documento']['name'])) {
        $arquivo = $_FILES['documento'];

        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            $erros[] = 'Falha ao enviar o documento. Tente novamente com um arquivo menor.';
        } elseif ($arquivo['size'] > 5 * 1024 * 1024) {
            $erros[] = 'O documento deve ter no máximo 5 MB.';
        } else {
            $permitidos = [
                'application/pdf' => 'pdf',
                'image/jpeg'      => 'jpg',
                'image/png'       => 'png',
            ];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($arquivo['tmp_name']);

            if (!isset($permitidos[$mime])) {
                $erros[] = 'O documento precisa ser PDF, JPG ou PNG.';
            } else {
                $destino_dir = __DIR__ . '/uploads/credenciamento';
                if (!is_dir($destino_dir)) {
                    @mkdir($destino_dir, 0775, true);
                }
                try {
                    $nome_arquivo = 'doc_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
                } catch (Exception $ex) {
                    $nome_arquivo = 'doc_' . date('Ymd') . '_' . uniqid('', true) . '.' . $permitidos[$mime];
                }
                if (!$erros && move_uploaded_file($arquivo['tmp_name'], $destino_dir . '/' . $nome_arquivo)) {
                    $documento_salvo = 'uploads/credenciamento/' . $nome_arquivo;
                } elseif (!$erros) {
                    $erros[] = 'Não foi possível salvar o documento no servidor.';
                }
            }
        }
    }

    if (!$erros) {
        if (!sh_tabela_existe($pdo, 'arbitro_solicitacoes')) {
            $erros[] = 'O módulo de credenciamento ainda não foi instalado neste servidor. '
                     . 'Execute scripts/migration_v2.sql e tente novamente.';
        } else {
            try {
                // Evita duplicidade de solicitação em análise para o mesmo CPF.
                $cpf_digitos = preg_replace('/\D/', '', $dados['cpf']);
                $stmt = $pdo->prepare("SELECT protocolo FROM arbitro_solicitacoes
                                       WHERE REPLACE(REPLACE(cpf,'.',''),'-','') = ?
                                         AND status IN ('recebida','em_analise')");
                $stmt->execute([$cpf_digitos]);
                $existente = $stmt->fetchColumn();

                if ($existente) {
                    $erros[] = 'Já existe uma solicitação em análise para este CPF (protocolo '
                             . $existente . '). Aguarde o parecer da coordenação.';
                } else {
                    $protocolo = sh_protocolo($pdo, 'arbitro_solicitacoes', 'protocolo', 'ARB');

                    $stmt = $pdo->prepare(
                        "INSERT INTO arbitro_solicitacoes
                            (protocolo, nome, email, telefone, cpf, data_nascimento, cidade, uf,
                             escola_vinculo, formacao, registro_orgao, registro_numero,
                             anos_experiencia, modalidades, disponibilidade, apresentacao,
                             documento_arquivo, username_sugerido,
                             aceite_termos, aceite_privacidade, aceite_conduta, ip_aceite, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, ?, 'recebida')"
                    );
                    $stmt->execute([
                        $protocolo,
                        $dados['nome'],
                        $dados['email'],
                        $dados['telefone'],
                        $cpf_digitos,
                        $dados['data_nascimento'] !== '' ? $dados['data_nascimento'] : null,
                        $dados['cidade'] !== '' ? $dados['cidade'] : null,
                        $dados['uf'] !== '' ? strtoupper($dados['uf']) : null,
                        $dados['escola_vinculo'] !== '' ? $dados['escola_vinculo'] : null,
                        $dados['formacao'],
                        $dados['registro_orgao'] !== '' ? $dados['registro_orgao'] : null,
                        $dados['registro_numero'] !== '' ? $dados['registro_numero'] : null,
                        (int)$dados['anos_experiencia'],
                        implode(', ', $modalidades_marcadas),
                        $dados['disponibilidade'] !== '' ? $dados['disponibilidade'] : null,
                        $dados['apresentacao'] !== '' ? $dados['apresentacao'] : null,
                        $documento_salvo,
                        $dados['username_sugerido'] !== '' ? strtolower($dados['username_sugerido']) : null,
                        sh_ip(),
                    ]);

                    $solicitacao_id = (int)$pdo->lastInsertId();
                    sh_registrar_consentimento($pdo, 'termos', $dados['email'], true);
                    sh_auditar($pdo, 'credenciamento_solicitado', 'arbitro_solicitacoes',
                               $solicitacao_id, $protocolo);

                    $sucesso = ['protocolo' => $protocolo, 'email' => $dados['email']];
                }
            } catch (PDOException $e) {
                error_log('Erro ao registrar credenciamento: ' . $e->getMessage());
                $erros[] = 'Não foi possível registrar sua solicitação agora. Tente novamente em instantes.';
            }
        }
    }
}

$status_rotulo = [
    'recebida'   => ['Recebida',    'tag-blue',  'fa-inbox'],
    'em_analise' => ['Em análise',  'tag-amber', 'fa-hourglass-half'],
    'aprovada'   => ['Aprovada',    'tag-green', 'fa-circle-check'],
    'recusada'   => ['Não aprovada','tag-red',   'fa-circle-xmark'],
];

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><span class="dot"></span> Profissional aplicador</span>
        <h1>Credenciamento de arbitragem</h1>
        <p>
            O árbitro é quem garante que o resultado seja justo — e, no SportHub, é ele quem assina
            a súmula digital. Por isso o acesso não é automático: você se cadastra, comprova formação
            e a coordenação analisa antes de liberar.
        </p>
        <div class="legal-meta">
            <span class="tag on-dark"><i class="fas fa-clock"></i> Análise em até 5 dias úteis</span>
            <span class="tag on-dark"><i class="fas fa-file-shield"></i> Documento tratado conforme a LGPD</span>
            <span class="tag on-dark"><i class="fas fa-money-bill-wave"></i> Gratuito para o árbitro</span>
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
                    <strong>Solicitação enviada com sucesso</strong>
                    Enviamos a confirmação para <?= e($sucesso['email']) ?>. O resultado da análise
                    chega no mesmo endereço.
                </div>
            </div>

            <div class="protocol-box">
                <span class="protocol-label">Protocolo de credenciamento</span>
                <span class="protocol-code"><?= e($sucesso['protocolo']) ?></span>
            </div>

            <h3>O que acontece agora</h3>
            <ol class="mt-1" style="display:grid;gap:12px">
                <li class="small">A coordenação confere seus dados e o documento enviado.</li>
                <li class="small">Se algo estiver faltando, entramos em contato pelo e-mail informado.</li>
                <li class="small">Aprovado o credenciamento, o sistema cria seu acesso de árbitro e envia usuário e senha provisória.</li>
                <li class="small">No primeiro acesso você troca a senha e já pode receber jogos.</li>
            </ol>

            <div class="form-actions">
                <a href="<?= e(sh_url('cadastro-arbitro.php?protocolo=' . urlencode($sucesso['protocolo']))) ?>" class="btn btn-primary">
                    Acompanhar meu protocolo
                </a>
                <a href="<?= e(sh_url('index.php')) ?>" class="btn btn-ghost">Voltar ao início</a>
            </div>
        </div>
    </div>
</section>

<?php else: ?>

<!-- ═══════════ CONSULTA DE PROTOCOLO ═══════════ -->
<?php if ($consulta !== null): ?>
<section class="section-sm" style="padding-bottom:0">
    <div class="wrap-narrow">
        <?php if ($consulta === 'nao_encontrado'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-magnifying-glass"></i>
                <div>
                    <strong>Protocolo não encontrado</strong>
                    Confira se digitou exatamente como no e-mail de confirmação (ex.: ARB-<?= date('Y') ?>-0001).
                </div>
            </div>
        <?php else:
            [$rotulo, $classe, $icone] = $status_rotulo[$consulta['status']] ?? ['Em processamento', 'tag', 'fa-clock']; ?>
            <div class="form-card mb-3">
                <div class="row-between">
                    <div>
                        <div class="role-sub">Protocolo <?= e($consulta['protocolo']) ?></div>
                        <h3 class="mt-1"><?= e($consulta['nome']) ?></h3>
                        <p class="small mt-1">
                            Enviado em <?= e(date('d/m/Y', strtotime($consulta['created_at']))) ?>
                            <?php if ($consulta['analisado_em']): ?>
                                · analisado em <?= e(date('d/m/Y', strtotime($consulta['analisado_em']))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="tag <?= e($classe) ?>"><i class="fas <?= e($icone) ?>"></i> <?= e($rotulo) ?></span>
                </div>
                <?php if (!empty($consulta['parecer'])): ?>
                    <div class="callout mt-3" style="margin-bottom:0">
                        <i class="fas fa-comment-dots"></i>
                        <div>
                            <strong>Parecer da coordenação</strong>
                            <p><?= nl2br(e($consulta['parecer'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($consulta['status'] === 'aprovada'): ?>
                    <a href="<?= e(sh_url('login.php')) ?>" class="btn btn-primary mt-3">Entrar na plataforma <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════ REQUISITOS ═══════════ -->
<section class="section-sm" id="requisitos">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Antes de começar</span>
            <h2>Quem pode se credenciar</h2>
            <p>Aceitamos perfis diferentes de profissional aplicador. O que não varia é a exigência de comprovação.</p>
        </div>

        <div class="grid grid-4">
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-person-chalkboard"></i></div>
                <h3 style="font-size:1rem">Professor de Ed. Física</h3>
                <p class="small">Com registro no CREF ativo. É o perfil mais comum nas escolas.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-award"></i></div>
                <h3 style="font-size:1rem">Árbitro federado</h3>
                <p class="small">Vinculado a federação ou liga, com carteira ou certificado válido.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-clipboard-user"></i></div>
                <h3 style="font-size:1rem">Técnico esportivo</h3>
                <p class="small">Com curso técnico ou certificação em arbitragem da modalidade.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                <h3 style="font-size:1rem">Estudante de licenciatura</h3>
                <p class="small">A partir do 4º semestre, com declaração de matrícula e supervisão.</p>
            </div>
        </div>

        <div class="callout reveal mt-3">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <strong>Documento obrigatório</strong>
                <p>
                    Anexe um comprovante: carteira do CREF, certificado de arbitragem, diploma ou
                    declaração de matrícula. Sem ele a solicitação fica pendente e não avança para
                    aprovação. Aceitamos PDF, JPG ou PNG de até 5 MB.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ FORMULÁRIO ═══════════ -->
<section class="section-sm" style="background:var(--surface-2);border-block:1px solid var(--line)">
    <div class="wrap-narrow">
        <div class="section-head reveal">
            <span class="eyebrow">Formulário</span>
            <h2>Solicitação de credenciamento</h2>
            <p>Leva cerca de 5 minutos. Campos marcados com <span style="color:var(--ember)">*</span> são obrigatórios.</p>
        </div>

        <div class="form-card">
            <?php if ($erros): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <div>
                        <strong>Revise os pontos abaixo</strong>
                        <ul><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>

                <fieldset class="fieldset">
                    <legend><i class="fas fa-id-card"></i> Identificação</legend>
                    <p class="fieldset-hint">Precisamos desses dados para emitir sua credencial e vincular as súmulas que você assinar.</p>

                    <div class="field">
                        <label for="nome">Nome completo <span class="req">*</span></label>
                        <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" autocomplete="name" required>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="cpf">CPF <span class="req">*</span></label>
                            <input type="text" id="cpf" name="cpf" value="<?= e($dados['cpf']) ?>"
                                   placeholder="000.000.000-00" inputmode="numeric" required>
                            <span class="hint">Usado apenas para conferir identidade. Nunca é exibido publicamente.</span>
                        </div>
                        <div class="field">
                            <label for="data_nascimento">Data de nascimento</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" value="<?= e($dados['data_nascimento']) ?>">
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="email">E-mail <span class="req">*</span></label>
                            <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>" autocomplete="email" required>
                        </div>
                        <div class="field">
                            <label for="telefone">Telefone / WhatsApp <span class="req">*</span></label>
                            <input type="tel" id="telefone" name="telefone" value="<?= e($dados['telefone']) ?>"
                                   placeholder="(00) 00000-0000" autocomplete="tel" required>
                        </div>
                    </div>

                    <div class="field-row">
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
                        <div class="field">
                            <label for="escola_vinculo">Escola em que atua</label>
                            <input type="text" id="escola_vinculo" name="escola_vinculo" value="<?= e($dados['escola_vinculo']) ?>"
                                   placeholder="Se já tiver vínculo">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="fieldset">
                    <legend><i class="fas fa-graduation-cap"></i> Qualificação profissional</legend>
                    <p class="fieldset-hint">É esta seção que a coordenação analisa para aprovar seu credenciamento.</p>

                    <div class="field">
                        <label for="formacao">Formação principal <span class="req">*</span></label>
                        <select id="formacao" name="formacao" required>
                            <?php foreach ($formacoes as $valor => $rotulo): ?>
                                <option value="<?= e($valor) ?>"<?= $dados['formacao'] === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="registro_orgao">Órgão de registro</label>
                            <input type="text" id="registro_orgao" name="registro_orgao" value="<?= e($dados['registro_orgao']) ?>"
                                   placeholder="CREF, federação, liga…">
                        </div>
                        <div class="field">
                            <label for="registro_numero">Número do registro</label>
                            <input type="text" id="registro_numero" name="registro_numero" value="<?= e($dados['registro_numero']) ?>"
                                   placeholder="Ex.: 012345-G/SP">
                        </div>
                        <div class="field">
                            <label for="anos_experiencia">Anos de experiência <span class="req">*</span></label>
                            <input type="number" id="anos_experiencia" name="anos_experiencia" min="0" max="60"
                                   value="<?= e($dados['anos_experiencia']) ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>Modalidades que você está apto a apitar <span class="req">*</span></label>
                        <div class="check-grid">
                            <?php foreach ($modalidades_disponiveis as $modalidade): ?>
                                <label class="check-chip">
                                    <input type="checkbox" name="modalidades[]" value="<?= e($modalidade) ?>"
                                           <?= in_array($modalidade, $modalidades_marcadas, true) ? 'checked' : '' ?>>
                                    <?= e($modalidade) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <span class="hint">Você só será designado para jogos das modalidades marcadas aqui.</span>
                    </div>

                    <div class="field">
                        <label for="disponibilidade">Disponibilidade</label>
                        <input type="text" id="disponibilidade" name="disponibilidade" value="<?= e($dados['disponibilidade']) ?>"
                               placeholder="Ex.: manhãs de terça e quinta, sábados o dia todo">
                    </div>

                    <div class="field">
                        <label for="apresentacao">Apresentação</label>
                        <textarea id="apresentacao" name="apresentacao"
                                  placeholder="Conte brevemente sua trajetória na arbitragem: competições que já apitou, cursos, experiência com esporte escolar…"><?= e($dados['apresentacao']) ?></textarea>
                    </div>

                    <div class="field">
                        <label for="documento">Documento comprobatório</label>
                        <input type="file" id="documento" name="documento" accept=".pdf,.jpg,.jpeg,.png">
                        <span class="hint">Carteira do CREF, certificado de arbitragem, diploma ou declaração de matrícula. PDF, JPG ou PNG, até 5 MB.</span>
                    </div>
                </fieldset>

                <fieldset class="fieldset">
                    <legend><i class="fas fa-key"></i> Acesso à plataforma</legend>
                    <p class="fieldset-hint">Se o credenciamento for aprovado, criamos seu usuário e enviamos uma senha provisória por e-mail.</p>

                    <div class="field">
                        <label for="username_sugerido">Nome de usuário desejado</label>
                        <input type="text" id="username_sugerido" name="username_sugerido" value="<?= e($dados['username_sugerido']) ?>"
                               placeholder="ex.: carlos.menezes" autocomplete="username">
                        <span class="hint">Letras, números, ponto, hífen ou sublinhado. Se já estiver em uso, sugerimos outro.</span>
                    </div>

                    <div class="alert alert-info" style="margin-bottom:0">
                        <i class="fas fa-lock"></i>
                        <div><strong>Nunca pedimos sua senha por aqui</strong> Você a define no primeiro acesso, dentro da plataforma.</div>
                    </div>
                </fieldset>

                <fieldset class="fieldset" id="conduta">
                    <legend><i class="fas fa-scale-balanced"></i> Termos e código de conduta</legend>
                    <p class="fieldset-hint">Os três aceites abaixo são obrigatórios para o credenciamento.</p>

                    <div class="stack">
                        <label class="check">
                            <input type="checkbox" name="aceite_termos" value="1" required <?= !empty($_POST['aceite_termos']) ? 'checked' : '' ?>>
                            <span class="check-text">
                                <strong>Termos de Uso</strong>
                                Li e aceito os <a href="<?= e(sh_url('termos.php')) ?>" target="_blank">Termos de Uso</a> da plataforma
                                e declaro que todas as informações prestadas são verdadeiras.
                            </span>
                        </label>

                        <label class="check">
                            <input type="checkbox" name="aceite_privacidade" value="1" required <?= !empty($_POST['aceite_privacidade']) ? 'checked' : '' ?>>
                            <span class="check-text">
                                <strong>Tratamento dos meus dados</strong>
                                Autorizo o tratamento dos meus dados pessoais e do documento enviado para fins de
                                credenciamento e emissão de credencial, conforme a
                                <a href="<?= e(sh_url('privacidade.php')) ?>" target="_blank">Política de Privacidade</a>
                                e a Lei nº 13.709/2018.
                            </span>
                        </label>

                        <label class="check">
                            <input type="checkbox" name="aceite_conduta" value="1" required <?= !empty($_POST['aceite_conduta']) ? 'checked' : '' ?>>
                            <span class="check-text">
                                <strong>Código de Conduta da arbitragem</strong>
                                Comprometo-me a atuar com imparcialidade, respeitar alunos e professores, manter sigilo
                                sobre os dados a que tiver acesso e registrar na súmula apenas fatos ocorridos em quadra.
                                Estou ciente de que o descumprimento leva à suspensão da credencial.
                            </span>
                        </label>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Enviar solicitação <i class="fas fa-paper-plane"></i>
                    </button>
                    <a href="<?= e(sh_url('index.php')) ?>" class="btn btn-ghost">Cancelar</a>
                </div>

                <p class="form-legal">
                    Seus dados são usados exclusivamente para a análise do credenciamento e para a operação
                    das partidas que você apitar. O documento enviado fica em área restrita, acessível apenas
                    à coordenação, e é eliminado se a solicitação for recusada.
                    Consulte seus direitos no <a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD</a>.
                </p>
            </form>
        </div>

        <!-- Consulta de protocolo -->
        <div class="form-card mt-3">
            <h3><i class="fas fa-magnifying-glass" style="color:var(--ember);margin-right:8px"></i> Já enviei — quero acompanhar</h3>
            <p class="small mt-1">Digite o protocolo recebido por e-mail para ver o andamento da sua análise.</p>
            <form method="GET" class="row mt-2" style="gap:10px">
                <div class="field" style="flex:1;min-width:220px;margin:0">
                    <label class="sr-only" for="protocolo">Protocolo</label>
                    <input type="text" id="protocolo" name="protocolo" value="<?= e($protocolo_busca) ?>"
                           placeholder="ARB-<?= date('Y') ?>-0001" required>
                </div>
                <button type="submit" class="btn btn-outline">Consultar</button>
            </form>
        </div>
    </div>
</section>

<!-- ═══════════ COMO É A ROTINA ═══════════ -->
<section class="section-sm">
    <div class="wrap">
        <div class="section-head center reveal">
            <span class="eyebrow">Depois da aprovação</span>
            <h2>Como é apitar com o SportHub</h2>
        </div>
        <div class="steps grid grid-4">
            <div class="step reveal">
                <div class="step-num">1</div>
                <h3>Receba a escala</h3>
                <p class="small">A coordenação designa você para as partidas das modalidades que você domina.</p>
            </div>
            <div class="step reveal">
                <div class="step-num">2</div>
                <h3>Abra no celular</h3>
                <p class="small">No painel "Meus jogos" aparecem apenas as partidas sob sua responsabilidade.</p>
            </div>
            <div class="step reveal">
                <div class="step-num">3</div>
                <h3>Registre em quadra</h3>
                <p class="small">Placar, gols com autor e minuto, cartões, substituições e ocorrências.</p>
            </div>
            <div class="step reveal">
                <div class="step-num">4</div>
                <h3>Encerre a súmula</h3>
                <p class="small">A partida é finalizada, a súmula fica arquivada e a tabela se atualiza sozinha.</p>
            </div>
        </div>
    </div>
</section>

<?php endif; ?>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
