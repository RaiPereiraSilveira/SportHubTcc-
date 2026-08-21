<?php
/** cookies.php — Política de Cookies. */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Política de Cookies — SportHub';
$page_desc  = 'Quais cookies o SportHub utiliza, para que servem, quanto tempo duram e como gerenciar seu consentimento.';
$active     = 'privacidade';

$secoes = [
    'o-que-sao'   => 'O que são cookies',
    'necessarios' => 'Cookies necessários',
    'analiticos'  => 'Cookies analíticos',
    'nao-usamos'  => 'O que não usamos',
    'gerenciar'   => 'Como gerenciar sua escolha',
    'navegador'   => 'Configuração pelo navegador',
    'alteracoes'  => 'Alterações desta política',
];

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><i class="fas fa-cookie-bite"></i> Documento legal</span>
        <h1>Política de Cookies</h1>
        <p>
            Cookies são pequenos arquivos que o site guarda no seu navegador. Alguns são
            indispensáveis para você conseguir entrar na plataforma; outros só existem se você
            autorizar. Aqui está a lista completa, sem letra miúda.
        </p>
        <div class="legal-meta">
            <span class="tag on-dark"><i class="fas fa-file-lines"></i> Versão <?= e(SH_VERSAO_POLITICA) ?></span>
            <span class="tag on-dark"><i class="fas fa-calendar"></i> Vigente desde <?= e(SH_POLITICA_DATA) ?></span>
        </div>
    </div>
</section>

<div class="wrap legal-layout">
    <aside class="legal-toc">
        <h4>Nesta página</h4>
        <ol>
            <?php foreach ($secoes as $id => $titulo): ?>
                <li><a href="#<?= e($id) ?>"><?= e($titulo) ?></a></li>
            <?php endforeach; ?>
        </ol>
    </aside>

    <article class="legal-body">

        <section id="o-que-sao">
            <h2><span class="num">01</span> O que são cookies</h2>
            <p>
                Cookie é um arquivo de texto que um site grava no seu navegador para lembrar de algo
                entre uma página e outra — que você já fez login, por exemplo. Também usamos o
                <em>localStorage</em>, um mecanismo parecido, para guardar sua resposta ao banner de
                privacidade.
            </p>
            <p>
                Dividimos o que usamos em dois grupos: os <strong>necessários</strong>, sem os quais
                a plataforma simplesmente não funciona, e os <strong>analíticos</strong>, que só são
                ativados mediante o seu consentimento, conforme o art. 7º, I da LGPD.
            </p>
        </section>

        <section id="necessarios">
            <h2><span class="num">02</span> Cookies necessários</h2>
            <p>
                Estes não podem ser desativados porque sustentam funções básicas de acesso e
                segurança. Por não servirem a nenhuma finalidade além disso, dispensam consentimento.
            </p>
            <div class="data-table-wrap">
                <table class="data">
                    <thead>
                        <tr><th style="width:170px">Nome</th><th>Finalidade</th><th style="width:150px">Duração</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>PHPSESSID</strong></td>
                            <td>Identifica sua sessão no servidor. É o que mantém você logado enquanto navega entre as páginas.</td>
                            <td>Até fechar o navegador</td>
                        </tr>
                        <tr>
                            <td><strong>csrf_token</strong><br><span class="small muted">em sessão</span></td>
                            <td>Token que protege os formulários contra falsificação de requisição (CSRF), impedindo que outro site envie dados em seu nome.</td>
                            <td>Duração da sessão</td>
                        </tr>
                        <tr>
                            <td><strong>sporthub_consentimento</strong><br><span class="small muted">localStorage</span></td>
                            <td>Guarda a sua resposta ao banner de privacidade para não perguntarmos de novo a cada visita.</td>
                            <td>12 meses</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="analiticos">
            <h2><span class="num">03</span> Cookies analíticos</h2>
            <p>
                Ajudam a entender quais páginas são mais acessadas e onde as pessoas têm dificuldade,
                para melhorarmos a plataforma. <strong>Só são ativados se você clicar em
                "Aceitar todos"</strong> no banner — e, se recusar, nada muda no funcionamento do site.
            </p>
            <div class="data-table-wrap">
                <table class="data">
                    <thead>
                        <tr><th style="width:170px">Nome</th><th>Finalidade</th><th style="width:150px">Duração</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>sh_metrica</strong></td>
                            <td>Contagem agregada de visitas por página, sem identificação pessoal.</td>
                            <td>6 meses</td>
                        </tr>
                        <tr>
                            <td><strong>sh_origem</strong></td>
                            <td>Registra de onde você chegou ao site (busca, link direto, rede social) para avaliar canais de divulgação.</td>
                            <td>30 dias</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="callout">
                <i class="fas fa-fingerprint"></i>
                <div>
                    <strong>Registramos o seu consentimento</strong>
                    <p>Quando você responde ao banner, guardamos a finalidade escolhida, a versão do texto, a data, o endereço IP e o navegador — comprovação exigida pelo art. 8º, §1º da LGPD. Esse registro usa um identificador anônimo de sessão, não o seu nome.</p>
                </div>
            </div>
        </section>

        <section id="nao-usamos">
            <h2><span class="num">04</span> O que não usamos</h2>
            <p>Para deixar explícito, o SportHub <strong>não utiliza</strong>:</p>
            <ul>
                <li>Cookies de publicidade ou de redes de anúncios.</li>
                <li>Pixels de rastreamento de redes sociais.</li>
                <li>Rastreamento entre sites (<em>cross-site tracking</em>).</li>
                <li>Criação de perfil comportamental para fins comerciais.</li>
                <li>Venda ou cessão de dados de navegação a terceiros.</li>
            </ul>
        </section>

        <section id="gerenciar">
            <h2><span class="num">05</span> Como gerenciar sua escolha</h2>
            <p>
                Você pode revisar sua decisão quando quiser — mudar de ideia é um direito, não uma
                exceção. Ao reabrir o banner, sua escolha anterior é substituída pela nova.
            </p>
            <p>
                <a href="#" data-abrir-cookies class="btn btn-primary" style="text-decoration:none">
                    <i class="fas fa-sliders"></i> Rever minhas preferências de cookies
                </a>
            </p>
            <p class="mt-2">
                Se preferir apagar o registro que fizemos do seu consentimento, envie um pedido de
                eliminação pelo <a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD</a>.
            </p>
        </section>

        <section id="navegador">
            <h2><span class="num">06</span> Configuração pelo navegador</h2>
            <p>
                Independentemente das nossas opções, todo navegador permite bloquear ou apagar cookies.
                Vale lembrar: bloquear os cookies necessários impedirá o login na plataforma.
            </p>
            <ul>
                <li><strong>Chrome</strong> — Configurações › Privacidade e segurança › Cookies e outros dados do site.</li>
                <li><strong>Firefox</strong> — Configurações › Privacidade e Segurança › Cookies e dados de sites.</li>
                <li><strong>Edge</strong> — Configurações › Cookies e permissões de site.</li>
                <li><strong>Safari</strong> — Preferências › Privacidade › Gerenciar dados de sites.</li>
            </ul>
        </section>

        <section id="alteracoes">
            <h2><span class="num">07</span> Alterações desta política</h2>
            <p>
                Se passarmos a usar um novo cookie, esta lista será atualizada e a versão do documento
                mudará. Quando a novidade depender de consentimento, o banner reaparece para você
                decidir de novo.
            </p>
            <p>Versão atual: <strong><?= e(SH_VERSAO_POLITICA) ?></strong>, vigente desde <?= e(SH_POLITICA_DATA) ?>.</p>

            <div class="btn-group mt-3">
                <a href="<?= e(sh_url('privacidade.php')) ?>" class="btn btn-outline">Política de Privacidade</a>
                <a href="<?= e(sh_url('lgpd.php')) ?>" class="btn btn-outline">Portal LGPD</a>
            </div>
        </section>

    </article>
</div>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
