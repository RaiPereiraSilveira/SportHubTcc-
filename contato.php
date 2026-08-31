<?php
/** contato.php — canal comercial e de suporte. */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Contato — SportHub';
$page_desc  = 'Fale com a equipe do SportHub: demonstração da plataforma, dúvidas sobre planos, suporte técnico, credenciamento de árbitros e imprensa.';
$active     = 'contato';

$assuntos = [
    'demonstracao' => 'Quero uma demonstração',
    'planos'       => 'Dúvida sobre planos e preços',
    'suporte'      => 'Suporte técnico',
    'arbitragem'   => 'Credenciamento de arbitragem',
    'imprensa'     => 'Imprensa e parcerias',
    'outro'        => 'Outro assunto',
];

$erros   = [];
$sucesso = false;
$dados   = ['nome' => '', 'email' => '', 'telefone' => '', 'escola' => '', 'assunto' => 'demonstracao', 'mensagem' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dados as $campo => $_) {
        $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Sua sessão expirou. Recarregue a página e envie novamente.';
    }
    // Campo-armadilha: preenchido só por robôs.
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $erros[] = 'Não foi possível enviar sua mensagem.';
    }
    if ($dados['nome'] === '')  $erros[] = 'Informe seu nome.';
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail válido.';
    if (mb_strlen($dados['mensagem']) < 10) $erros[] = 'Escreva uma mensagem com pelo menos 10 caracteres.';
    if (!isset($assuntos[$dados['assunto']])) $erros[] = 'Selecione o assunto.';
    if (empty($_POST['aceite_privacidade'])) {
        $erros[] = 'É preciso autorizar o uso dos seus dados para respondermos ao contato.';
    }

    if (!$erros) {
        if (!sh_tabela_existe($pdo, 'contatos')) {
            $erros[] = 'O formulário ainda não foi instalado neste servidor. Escreva diretamente para ' . SH_EMAIL . '.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO contatos (nome, email, telefone, escola, assunto, mensagem, ip)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $dados['nome'], $dados['email'],
                    $dados['telefone'] !== '' ? $dados['telefone'] : null,
                    $dados['escola']   !== '' ? $dados['escola']   : null,
                    $dados['assunto'], $dados['mensagem'], sh_ip(),
                ]);
                sh_registrar_consentimento($pdo, 'termos', $dados['email'], true);
                $sucesso = true;
                $dados = array_map(fn($v) => '', $dados);
                $dados['assunto'] = 'demonstracao';
            } catch (PDOException $e) {
                sh_log_excecao($e, 'registrar contato');
                $erros[] = 'Não foi possível enviar agora. Tente pelo WhatsApp ou escreva para ' . SH_EMAIL . '.';
            }
        }
    }
}

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><span class="dot"></span> Fale com a gente</span>
        <h1>Contato</h1>
        <p>
            Quer ver a plataforma funcionando antes de decidir? Tem dúvida sobre qual plano cabe na
            sua escola? Escreva — respondemos em até um dia útil.
        </p>
    </div>
</section>

<section class="section-sm">
    <div class="wrap">
        <div class="contato-layout u-display-grid">
            <style<?= sh_nonce_attr() ?>>
                @media (min-width: 980px) {
                    .contato-layout { grid-template-columns: minmax(0,1fr) 330px; gap: 32px !important; }
                }
            </style>

            <div class="form-card">
                <?php if ($sucesso): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-circle-check"></i>
                        <div>
                            <strong>Mensagem enviada</strong>
                            Recebemos seu contato e respondemos em até um dia útil no e-mail informado.
                        </div>
                    </div>
                <?php endif; ?>

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
                    <div class="u-position-absolute" aria-hidden="true">
                        <label for="website">Não preencha este campo</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset class="fieldset">
                        <legend><i class="fas fa-comment-dots"></i> Sua mensagem</legend>

                        <div class="field-row">
                            <div class="field">
                                <label for="nome">Nome <span class="req">*</span></label>
                                <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" autocomplete="name" required>
                            </div>
                            <div class="field">
                                <label for="email">E-mail <span class="req">*</span></label>
                                <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>" autocomplete="email" required>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label for="telefone">Telefone / WhatsApp</label>
                                <input type="tel" id="telefone" name="telefone" value="<?= e($dados['telefone']) ?>" placeholder="(00) 00000-0000">
                            </div>
                            <div class="field">
                                <label for="escola">Escola ou instituição</label>
                                <input type="text" id="escola" name="escola" value="<?= e($dados['escola']) ?>">
                            </div>
                        </div>

                        <div class="field">
                            <label for="assunto">Assunto <span class="req">*</span></label>
                            <select id="assunto" name="assunto" required>
                                <?php foreach ($assuntos as $valor => $rotulo): ?>
                                    <option value="<?= e($valor) ?>"<?= $dados['assunto'] === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="mensagem">Mensagem <span class="req">*</span></label>
                            <textarea id="mensagem" name="mensagem" required
                                      placeholder="Conte quantas turmas e modalidades sua escola pretende colocar em quadra — assim já indicamos o plano certo."><?= e($dados['mensagem']) ?></textarea>
                        </div>

                        <label class="check">
                            <input type="checkbox" name="aceite_privacidade" value="1" required <?= !empty($_POST['aceite_privacidade']) ? 'checked' : '' ?>>
                            <span class="check-text">
                                <strong>Autorizo o uso dos meus dados para esta resposta</strong>
                                Seus dados serão usados apenas para responder a este contato, conforme a
                                <a href="<?= e(sh_url('privacidade.php')) ?>" target="_blank">Política de Privacidade</a>.
                            </span>
                        </label>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Enviar mensagem <i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            </div>

            <aside>
                <div class="card u-padding-26px">
                    <h3 class="u-font-size-1-05rem">Canais diretos</h3>
                    <ul class="stack mt-2">
                        <li><a href="https://wa.me/<?= e(SH_WHATSAPP) ?>" target="_blank" rel="noopener" class="row u-gap-10px">
                            <i class="fab fa-whatsapp u-color-25d366"></i> WhatsApp comercial
                        </a></li>
                        <li><a href="mailto:<?= e(SH_EMAIL) ?>" class="row u-gap-10px">
                            <i class="fas fa-envelope u-color-ember-5"></i> <?= e(SH_EMAIL) ?>
                        </a></li>
                        <li><a href="mailto:<?= e(SH_EMAIL_DPO) ?>" class="row u-gap-10px">
                            <i class="fas fa-user-shield u-color-blue"></i> <?= e(SH_EMAIL_DPO) ?>
                        </a></li>
                        <li><a href="<?= e(SH_INSTAGRAM) ?>" target="_blank" rel="noopener" class="row u-gap-10px">
                            <i class="fab fa-instagram u-color-c13584"></i> @sporthubgg
                        </a></li>
                    </ul>
                </div>

                <div class="card mt-2 u-padding-26px">
                    <h3 class="u-font-size-1-05rem">Atalhos úteis</h3>
                    <ul class="stack mt-2">
                        <li><a href="<?= e(sh_url('planos.php')) ?>" class="small u-color-ember">Ver planos e preços →</a></li>
                        <li><a href="<?= e(sh_url('como-funciona.php')) ?>" class="small u-color-ember">Como funciona a plataforma →</a></li>
                        <li><a href="<?= e(sh_url('cadastro-arbitro.php')) ?>" class="small u-color-ember">Credenciamento de árbitros →</a></li>
                        <li><a href="<?= e(sh_url('lgpd.php')) ?>" class="small u-color-ember">Portal LGPD →</a></li>
                    </ul>
                </div>

                <div class="alert alert-info mt-2">
                    <i class="fas fa-clock"></i>
                    <div><strong>Tempo de resposta</strong> Até 1 dia útil no comercial. Suporte de clientes ativos tem prioridade.</div>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
