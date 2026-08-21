<?php
/** planos.php — catálogo de assinaturas anuais. */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Planos e preços — SportHub';
$page_desc  = 'Assinatura anual do SportHub para escolas: Essencial (R$ 1.188/ano), Pro (R$ 2.388/ano) e Institucional (R$ 4.788/ano). 30 dias de teste gratuito, sem cartão de crédito.';
$active     = 'planos';

$planos = sh_planos($pdo);

/** Matriz de recursos exibida na tabela comparativa. */
$comparativo = [
    'Campeonato' => [
        ['Times inscritos',                'limite_times'],
        ['Modalidades simultâneas',        'limite_modalidades'],
        ['Árbitros credenciados',          'limite_arbitros'],
        ['Alunos e jogos',                 ['Ilimitado', 'Ilimitado', 'Ilimitado']],
        ['Fases de grupo e mata-mata',     [true, true, true]],
    ],
    'Operação do jogo' => [
        ['Súmula digital no celular',      [true, true, true]],
        ['Placar ao vivo',                 [true, true, true]],
        ['Gols, cartões e substituições',  [true, true, true]],
        ['Estatísticas por partida',       [false, true, true]],
        ['Escudos e identidade dos times', [true, true, true]],
    ],
    'Gestão e dados' => [
        ['Classificação automática',       [true, true, true]],
        ['Artilharia e fair play',         [false, true, true]],
        ['Relatório final da edição',      [false, true, true]],
        ['Exportação de dados (CSV)',      [false, true, true]],
        ['Histórico de edições anteriores',['1 edição', '5 edições', 'Ilimitado']],
    ],
    'Instituição' => [
        ['Múltiplas unidades',             [false, false, true]],
        ['Gestor de rede',                 [false, false, true]],
        ['Treinamento da equipe',          [false, 'Online', 'Presencial']],
        ['Suporte',                        ['E-mail', 'E-mail e WhatsApp', 'Dedicado']],
        ['Relatório de conformidade LGPD', [false, true, true]],
    ],
];

/** Renderiza uma célula do comparativo. */
function celula($valor) {
    if ($valor === true)  return '<td class="yes"><i class="fas fa-check"></i><span class="sr-only">Incluído</span></td>';
    if ($valor === false) return '<td class="no"><i class="fas fa-minus"></i><span class="sr-only">Não incluído</span></td>';
    return '<td>' . e($valor) . '</td>';
}

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><span class="dot"></span> Assinatura anual</span>
        <h1>Um preço por ano letivo. Todo o campeonato incluído.</h1>
        <p>
            Sem cobrança por aluno, sem cobrança por jogo e sem taxa de implantação. Você escolhe o
            porte da escola e usa a plataforma durante toda a temporada — começando por 30 dias de
            teste gratuito, sem cartão de crédito.
        </p>
        <div class="legal-meta">
            <span class="tag on-dark"><i class="fas fa-check"></i> 30 dias de teste</span>
            <span class="tag on-dark"><i class="fas fa-credit-card"></i> Boleto, Pix, cartão ou empenho</span>
            <span class="tag on-dark"><i class="fas fa-ban"></i> Sem fidelidade</span>
        </div>
    </div>
</section>

<!-- ═══════════ CARDS DE PLANO ═══════════ -->
<section class="section-sm" style="padding-top:clamp(48px,6vw,72px)">
    <div class="wrap">
        <div class="pricing-grid">
            <?php foreach ($planos as $plano):
                $destaque = !empty($plano['destaque']);
                $limite = function ($v, $sufixo) {
                    return $v === null ? 'Ilimitado' : ((int)$v . ' ' . $sufixo);
                };
            ?>
            <article class="plan reveal<?= $destaque ? ' featured' : '' ?>">
                <?php if ($destaque): ?><span class="plan-flag">Mais escolhido</span><?php endif; ?>
                <div class="plan-name"><?= e($plano['nome']) ?></div>
                <p class="plan-desc"><?= e($plano['descricao']) ?></p>

                <div class="plan-price">
                    <span class="plan-currency">R$</span>
                    <span class="plan-value"><?= e(sh_money($plano['preco_anual'])) ?></span>
                </div>
                <div class="plan-period">por ano letivo · pagamento único</div>
                <span class="plan-equiv">
                    <i class="fas fa-tag"></i> equivale a R$ <?= e(sh_money($plano['preco_mensal_equivalente'])) ?>/mês
                </span>

                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> <?= e($limite($plano['limite_times'], 'times inscritos')) ?></li>
                    <li><i class="fas fa-check"></i> <?= e($limite($plano['limite_modalidades'], 'modalidades')) ?></li>
                    <li><i class="fas fa-check"></i> <?= e($limite($plano['limite_arbitros'], 'árbitros credenciados')) ?></li>
                    <li><i class="fas fa-check"></i> Alunos, jogos e súmulas ilimitados</li>
                    <li><i class="fas fa-check"></i> Placar ao vivo e classificação automática</li>
                    <?php if ($plano['slug'] === 'essencial'): ?>
                        <li class="off"><i class="fas fa-minus"></i> Relatórios e exportação</li>
                        <li class="off"><i class="fas fa-minus"></i> Múltiplas unidades</li>
                    <?php elseif ($plano['slug'] === 'pro'): ?>
                        <li><i class="fas fa-check"></i> Relatórios, artilharia e exportação CSV</li>
                        <li><i class="fas fa-check"></i> Suporte por e-mail e WhatsApp</li>
                    <?php else: ?>
                        <li><i class="fas fa-check"></i> Múltiplas unidades e gestor de rede</li>
                        <li><i class="fas fa-check"></i> Treinamento presencial e suporte dedicado</li>
                    <?php endif; ?>
                </ul>

                <a href="<?= e(sh_url('assinar.php?plano=' . urlencode($plano['slug']))) ?>"
                   class="btn <?= $destaque ? 'btn-primary' : 'btn-dark' ?> btn-block">
                    Contratar o <?= e($plano['nome']) ?>
                </a>
                <p class="plan-note">Teste 30 dias antes da primeira cobrança</p>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="callout reveal mt-4" style="max-width:820px;margin-inline:auto">
            <i class="fas fa-school-flag"></i>
            <div>
                <strong>Escola pública ou projeto social?</strong>
                <p>
                    Trabalhamos com condição especial para instituições da rede pública e projetos
                    socioesportivos, incluindo pagamento por empenho.
                    <a href="<?= e(sh_url('contato.php')) ?>">Fale com a equipe</a> antes de contratar.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ COMPARATIVO ═══════════ -->
<section class="section" style="background:var(--surface-2);border-block:1px solid var(--line)">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Comparativo</span>
            <h2>O que muda de um plano para o outro</h2>
            <p>Todos os planos incluem a plataforma completa de operação do jogo. A diferença está no porte da escola e nos recursos de gestão.</p>
        </div>

        <div class="compare-wrap reveal">
            <table class="compare">
                <thead>
                    <tr>
                        <th style="min-width:240px">Recurso</th>
                        <?php foreach ($planos as $plano): ?>
                            <th style="text-align:center"><?= e($plano['nome']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <?php foreach ($comparativo as $grupo => $linhas): ?>
                <tbody>
                    <tr>
                        <th colspan="<?= count($planos) + 1 ?>"
                            style="background:var(--surface-2);font-family:var(--font-display);font-size:.78rem;letter-spacing:.1em;text-transform:uppercase;color:var(--ember)">
                            <?= e($grupo) ?>
                        </th>
                    </tr>
                    <?php foreach ($linhas as [$rotulo, $valores]): ?>
                    <tr>
                        <th><?= e($rotulo) ?></th>
                        <?php
                        foreach ($planos as $i => $plano) {
                            if (is_string($valores)) {
                                // Nome de coluna do banco: null significa ilimitado.
                                $v = $plano[$valores] ?? null;
                                echo celula($v === null ? 'Ilimitado' : (string)(int)$v);
                            } else {
                                echo celula($valores[$i] ?? false);
                            }
                        }
                        ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</section>

<!-- ═══════════ INCLUSO EM TODOS ═══════════ -->
<section class="section">
    <div class="wrap">
        <div class="section-head center reveal">
            <span class="eyebrow">Sempre incluído</span>
            <h2>Independente do plano que você escolher</h2>
        </div>
        <div class="grid grid-4">
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-rocket"></i></div>
                <h3 style="font-size:1rem">Implantação sem custo</h3>
                <p class="small">Nenhuma taxa de setup. O acesso é liberado assim que a contratação é registrada.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-arrows-rotate"></i></div>
                <h3 style="font-size:1rem">Atualizações incluídas</h3>
                <p class="small">Toda melhoria lançada durante a vigência chega automaticamente à sua escola.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                <h3 style="font-size:1rem">Conformidade LGPD</h3>
                <p class="small">Contrato de tratamento de dados, canal do DPO e atendimento aos direitos do titular.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon"><i class="fas fa-file-export"></i></div>
                <h3 style="font-size:1rem">Seus dados são seus</h3>
                <p class="small">Ao encerrar, você exporta todo o histórico do campeonato antes da exclusão.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ FAQ COMERCIAL ═══════════ -->
<section class="section" style="background:var(--surface-2);border-block:1px solid var(--line)">
    <div class="wrap-narrow">
        <div class="section-head center reveal">
            <span class="eyebrow">Pagamento e contrato</span>
            <h2>Perguntas sobre a assinatura</h2>
        </div>

        <div class="faq reveal">
            <details class="faq-item">
                <summary>Por que a cobrança é anual e não mensal?</summary>
                <div class="faq-body">
                    <p>Porque o interclasse é um evento com temporada, não um serviço de uso contínuo. Uma escola usa a plataforma intensamente em alguns meses do ano e quase nada nos outros — cobrar mensalmente faria você pagar pelos meses ociosos.</p>
                    <p>O valor anual pode ser parcelado no cartão em até 12 vezes, sem que isso mude o preço total.</p>
                </div>
            </details>
            <details class="faq-item">
                <summary>O teste de 30 dias exige cartão de crédito?</summary>
                <div class="faq-body"><p>Não. Você preenche os dados da escola, escolhe o plano e o acesso é liberado com status de teste. A cobrança só é gerada depois que a escola confirma a continuidade — e se não confirmar, nada é cobrado.</p></div>
            </details>
            <details class="faq-item">
                <summary>Quais formas de pagamento vocês aceitam?</summary>
                <div class="faq-body"><p>Boleto bancário, Pix, cartão de crédito (à vista ou parcelado) e empenho, no caso de escolas da rede pública. A forma escolhida é registrada no momento da contratação e pode ser alterada depois.</p></div>
            </details>
            <details class="faq-item">
                <summary>Posso trocar de plano no meio do ano?</summary>
                <div class="faq-body"><p>Pode. Na migração para um plano superior, cobramos apenas a diferença proporcional ao tempo restante da vigência. Na redução de plano, o novo valor passa a valer na renovação seguinte.</p></div>
            </details>
            <details class="faq-item">
                <summary>Existe fidelidade ou multa por cancelamento?</summary>
                <div class="faq-body"><p>Não há fidelidade nem multa. Ao cancelar, a escola mantém o acesso até o fim do período já pago e pode exportar todo o histórico. Cancelamentos solicitados em até 7 dias da contratação são reembolsados integralmente, conforme o Código de Defesa do Consumidor.</p></div>
            </details>
            <details class="faq-item">
                <summary>Como é a renovação?</summary>
                <div class="faq-body"><p>Avisamos por e-mail 30 dias antes do vencimento com o valor da renovação. Ela só acontece mediante confirmação da escola — não há renovação automática silenciosa.</p></div>
            </details>
            <details class="faq-item">
                <summary>O preço muda se a escola crescer?</summary>
                <div class="faq-body"><p>Só se ultrapassar os limites de times, modalidades ou árbitros do plano contratado. Número de alunos, de jogos e de súmulas nunca afeta o preço.</p></div>
            </details>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="cta-box reveal">
            <h2>Ainda em dúvida sobre qual plano?</h2>
            <p>Conte quantas turmas e quantas modalidades sua escola pretende colocar em quadra — a gente indica o plano certo, sem empurrar o mais caro.</p>
            <div class="btn-group">
                <a href="<?= e(sh_url('contato.php')) ?>" class="btn btn-primary btn-lg"><i class="fas fa-comments"></i> Falar com a equipe</a>
                <a href="<?= e(sh_url('como-funciona.php')) ?>" class="btn btn-on-dark btn-lg">Ver como funciona</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
