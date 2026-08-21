<?php
/** index.php — página inicial pública do SportHub. */
require_once __DIR__ . '/includes/config.php';

$page_title = 'SportHub — O interclasse da sua escola, organizado do início ao fim';
$page_desc  = 'Software de gestão de campeonatos interclasse: inscrição de times, súmula digital para árbitros credenciados, placar ao vivo, classificação automática e conformidade com a LGPD. Assinatura anual a partir de R$ 99/mês.';
$active     = '';

$planos = sh_planos($pdo);

include __DIR__ . '/includes/site_header.php';
?>

<!-- ══════════════════ HERO ══════════════════ -->
<section class="hero">
    <div class="wrap">
        <div class="hero-grid">
            <div>
                <span class="pill"><span class="dot"></span> Plataforma completa para escolas</span>
                <h1>O interclasse da sua escola, <em>organizado do início ao fim</em>.</h1>
                <p class="hero-desc">
                    Chega de tabela no papel, placar no grupo de WhatsApp e resultado contestado.
                    O SportHub cuida da inscrição dos times, da escala de árbitros credenciados,
                    da súmula digital e da classificação — que se atualiza sozinha ao apito final.
                </p>

                <div class="btn-group hero-actions">
                    <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-primary btn-lg">
                        Começar teste de 30 dias <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="<?= e(sh_url('como-funciona.php')) ?>" class="btn btn-outline btn-lg">
                        <i class="fas fa-circle-play"></i> Ver como funciona
                    </a>
                </div>

                <div class="hero-meta">
                    <div class="hero-meta-item">
                        <span class="hero-meta-num">3</span>
                        <span class="hero-meta-label">Perfis de acesso</span>
                    </div>
                    <div class="hero-meta-item">
                        <span class="hero-meta-num">&lt; 1 min</span>
                        <span class="hero-meta-label">Para lançar uma súmula</span>
                    </div>
                    <div class="hero-meta-item">
                        <span class="hero-meta-num">100%</span>
                        <span class="hero-meta-label">Conforme a LGPD</span>
                    </div>
                </div>
            </div>

            <div class="scoreboard-stack">
                <div class="scoreboard">
                    <div class="sb-top">
                        <span class="sb-label">Semifinal · Futsal masculino</span>
                        <span class="sb-live"><span class="dot"></span> Ao vivo</span>
                    </div>

                    <div class="sb-match">
                        <div class="sb-team">
                            <div class="sb-crest">3A</div>
                            <span class="sb-team-name">3º Ano A</span>
                        </div>
                        <div class="sb-score">
                            <div class="sb-score-value"><span>2</span><span class="x">×</span><span>1</span></div>
                            <span class="sb-clock">2º TEMPO · 18:42</span>
                        </div>
                        <div class="sb-team">
                            <div class="sb-crest">2C</div>
                            <span class="sb-team-name">2º Ano C</span>
                        </div>
                    </div>

                    <div class="sb-stats">
                        <div class="sb-stat"><div class="sb-stat-num">12</div><div class="sb-stat-label">Finalizações</div></div>
                        <div class="sb-stat"><div class="sb-stat-num">4</div><div class="sb-stat-label">Faltas</div></div>
                        <div class="sb-stat"><div class="sb-stat-num">7</div><div class="sb-stat-label">Escanteios</div></div>
                    </div>

                    <div class="sb-foot">
                        <i class="fas fa-user-shield"></i>
                        Súmula lançada por <strong style="color:rgba(255,255,255,.82)">árbitro credenciado</strong> · Quadra Poliesportiva
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════ FAIXA DE CONFIANÇA ══════════════════ -->
<section class="trust-band" aria-label="Recursos incluídos">
    <p class="trust-title">Tudo o que um campeonato escolar precisa, em um lugar só</p>
    <div class="marquee" aria-hidden="true">
        <?php
        $itens = ['Inscrição de times', 'Sorteio de chaves', 'Súmula digital', 'Placar ao vivo',
                  'Classificação automática', 'Artilharia', 'Escala de árbitros', 'Cartões e disciplina',
                  'Relatório final', 'Histórico por edição'];
        // Duplicado para o loop contínuo da esteira.
        foreach ([1, 2] as $volta):
            foreach ($itens as $item): ?>
                <span class="marquee-item"><i class="fas fa-circle" style="font-size:5px"></i> <?= e($item) ?></span>
            <?php endforeach;
        endforeach; ?>
    </div>
</section>

<!-- ══════════════════ COMO FUNCIONA ══════════════════ -->
<section class="section" id="como-funciona">
    <div class="wrap">
        <div class="section-head center reveal">
            <span class="eyebrow">Como funciona</span>
            <h2>Do primeiro cadastro ao pódio, em quatro etapas</h2>
            <p>
                Você não precisa ser da área de tecnologia. Se sua escola já sabe montar um interclasse
                no papel, sabe usar o SportHub — a diferença é que aqui nada se perde e todo mundo
                acompanha em tempo real.
            </p>
        </div>

        <div class="steps grid grid-4">
            <article class="step reveal">
                <div class="step-num">1</div>
                <h3>Monte o campeonato</h3>
                <p>A coordenação cria as modalidades, cadastra os times por turma e define as chaves.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check"></i> Times por sala, série e gênero</li>
                    <li><i class="fas fa-check"></i> Elenco com número de camisa</li>
                    <li><i class="fas fa-check"></i> Escudo e identidade de cada turma</li>
                </ul>
            </article>

            <article class="step reveal">
                <div class="step-num">2</div>
                <h3>Agende os jogos</h3>
                <p>Data, horário, local e fase. Cada partida recebe um árbitro credenciado responsável.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check"></i> Calendário por modalidade</li>
                    <li><i class="fas fa-check"></i> Designação de arbitragem</li>
                    <li><i class="fas fa-check"></i> Grupos, mata-mata e final</li>
                </ul>
            </article>

            <article class="step reveal">
                <div class="step-num">3</div>
                <h3>Apite e registre</h3>
                <p>Na quadra, o árbitro abre a súmula no celular e lança tudo enquanto o jogo acontece.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check"></i> Gols, cartões e substituições</li>
                    <li><i class="fas fa-check"></i> Estatísticas da partida</li>
                    <li><i class="fas fa-check"></i> Observações da arbitragem</li>
                </ul>
            </article>

            <article class="step reveal">
                <div class="step-num">4</div>
                <h3>Acompanhe o resultado</h3>
                <p>Ao encerrar, a classificação, a artilharia e o histórico se atualizam sozinhos.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check"></i> Tabela com critérios de desempate</li>
                    <li><i class="fas fa-check"></i> Resultados visíveis a todos</li>
                    <li><i class="fas fa-check"></i> Edições anteriores arquivadas</li>
                </ul>
            </article>
        </div>

        <div class="center mt-4 reveal">
            <a href="<?= e(sh_url('como-funciona.php')) ?>" class="btn btn-dark">
                Ver o guia completo, tela por tela <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════ RECURSOS ══════════════════ -->
<section class="section dark" id="recursos">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow on-dark">Recursos</span>
            <h2>Feito para o dia a dia de quem organiza esporte escolar</h2>
            <p>Cada função nasceu de um problema real de interclasse: a súmula que sumiu, o placar que ninguém sabia, a tabela que o professor refazia à mão toda semana.</p>
        </div>

        <div class="grid grid-3">
            <article class="card on-dark reveal">
                <div class="card-icon"><i class="fas fa-users"></i></div>
                <h3>Times e elencos</h3>
                <p>Inscrição digital por turma, com controle de jogadores, número de camisa e escudo próprio. Sem lista solta no caderno.</p>
            </article>
            <article class="card on-dark reveal">
                <div class="card-icon"><i class="fas fa-calendar-days"></i></div>
                <h3>Calendário e chaves</h3>
                <p>Agende partidas por modalidade e fase, defina local e horário e publique tudo de uma vez para a comunidade escolar.</p>
            </article>
            <article class="card on-dark reveal">
                <div class="card-icon"><i class="fas fa-clipboard-check"></i></div>
                <h3>Súmula digital</h3>
                <p>O árbitro registra gols, cartões e substituições direto do celular. A súmula fica assinada e arquivada, sem rasura.</p>
            </article>
            <article class="card on-dark reveal">
                <div class="card-icon"><i class="fas fa-bolt"></i></div>
                <h3>Placar ao vivo</h3>
                <p>Alunos, professores e famílias acompanham o andamento da partida pelo próprio celular, sem precisar estar na quadra.</p>
            </article>
            <article class="card on-dark reveal">
                <div class="card-icon"><i class="fas fa-ranking-star"></i></div>
                <h3>Classificação automática</h3>
                <p>Pontos, saldo e critérios de desempate calculados na hora. Ninguém mais discute quem passou de fase.</p>
            </article>
            <article class="card on-dark reveal">
                <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Relatórios e histórico</h3>
                <p>Artilharia, disciplina e desempenho por turma. Cada edição fica salva, construindo a memória esportiva da escola.</p>
            </article>
        </div>
    </div>
</section>

<!-- ══════════════════ PERFIS DE ACESSO ══════════════════ -->
<section class="section" id="perfis">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Perfis de acesso</span>
            <h2>Cada pessoa vê exatamente o que precisa</h2>
            <p>Três níveis de permissão, definidos por quem organiza. Ninguém altera um resultado por engano e nenhum dado de aluno fica exposto.</p>
        </div>

        <div class="grid grid-3">
            <article class="role-card reveal">
                <div class="role-head">
                    <div class="role-avatar"><i class="fas fa-user-gear"></i></div>
                    <div>
                        <div class="role-name">Coordenação</div>
                        <div class="role-sub">Administrador</div>
                    </div>
                </div>
                <p class="small">Quem monta e comanda o campeonato inteiro.</p>
                <ul class="role-list mt-2">
                    <li><i class="fas fa-check"></i> Cria modalidades, times e jogadores</li>
                    <li><i class="fas fa-check"></i> Agenda jogos e designa árbitros</li>
                    <li><i class="fas fa-check"></i> Aprova o credenciamento da arbitragem</li>
                    <li><i class="fas fa-check"></i> Acompanha dashboard e relatórios</li>
                    <li><i class="fas fa-check"></i> Responde às solicitações de LGPD</li>
                </ul>
            </article>

            <article class="role-card reveal">
                <div class="role-head">
                    <div class="role-avatar"><i class="fas fa-bullhorn"></i></div>
                    <div>
                        <div class="role-name">Arbitragem</div>
                        <div class="role-sub">Profissional aplicador</div>
                    </div>
                </div>
                <p class="small">O juiz credenciado que conduz e registra a partida.</p>
                <ul class="role-list mt-2">
                    <li><i class="fas fa-check"></i> Vê apenas os jogos sob sua responsabilidade</li>
                    <li><i class="fas fa-check"></i> Abre e fecha a súmula digital</li>
                    <li><i class="fas fa-check"></i> Lança gols, cartões e substituições</li>
                    <li><i class="fas fa-check"></i> Registra ocorrências disciplinares</li>
                    <li><i class="fas fa-check"></i> Credencial com validade e modalidades</li>
                </ul>
                <a href="<?= e(sh_url('cadastro-arbitro.php')) ?>" class="btn btn-outline btn-sm mt-3">
                    Quero me credenciar <i class="fas fa-arrow-right"></i>
                </a>
            </article>

            <article class="role-card reveal">
                <div class="role-head">
                    <div class="role-avatar"><i class="fas fa-graduation-cap"></i></div>
                    <div>
                        <div class="role-name">Alunos e comunidade</div>
                        <div class="role-sub">Consulta</div>
                    </div>
                </div>
                <p class="small">Quem joga, torce e quer saber o resultado agora.</p>
                <ul class="role-list mt-2">
                    <li><i class="fas fa-check"></i> Consulta a tabela de jogos</li>
                    <li><i class="fas fa-check"></i> Acompanha placares ao vivo</li>
                    <li><i class="fas fa-check"></i> Vê classificação e artilharia</li>
                    <li><i class="fas fa-check"></i> Revisa resultados anteriores</li>
                    <li><i class="fas fa-check"></i> Não altera nenhum dado do campeonato</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<!-- ══════════════════ TRANSPARÊNCIA ══════════════════ -->
<section class="section dark">
    <div class="wrap">
        <div class="split">
            <div class="reveal">
                <span class="eyebrow on-dark">Transparência</span>
                <h2>A mesma informação para toda a comunidade escolar</h2>
                <p class="mt-2">
                    Quando o placar mora no caderno de um professor, cada pessoa recebe uma versão
                    diferente da história. No SportHub existe uma única fonte de verdade — e ela é
                    pública para quem faz parte do campeonato.
                </p>

                <ul class="checklist">
                    <li>
                        <span class="ck-icon"><i class="fas fa-calendar-check"></i></span>
                        <div>
                            <h4>Calendário sempre atualizado</h4>
                            <p>Mudou o horário da final? Todo mundo vê a alteração no mesmo instante.</p>
                        </div>
                    </li>
                    <li>
                        <span class="ck-icon"><i class="fas fa-bolt"></i></span>
                        <div>
                            <h4>Resultado no apito final</h4>
                            <p>O árbitro encerra a súmula e a tabela já nasce recalculada, sem intermediário.</p>
                        </div>
                    </li>
                    <li>
                        <span class="ck-icon"><i class="fas fa-box-archive"></i></span>
                        <div>
                            <h4>Histórico que não se perde</h4>
                            <p>Cada edição fica arquivada com jogos, súmulas e campeões daquele ano.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="reveal">
                <div class="mini-table">
                    <div class="mini-table-head">
                        <span class="mini-table-title">Classificação · Futsal</span>
                        <span class="tag on-dark"><i class="fas fa-rotate"></i> Atualizada agora</span>
                    </div>
                    <div class="mini-rows">
                        <div class="mini-row leader">
                            <span class="mini-pos">1</span><span class="mini-team">3º Ano A</span><span class="mini-pts">18 PTS</span>
                        </div>
                        <div class="mini-row">
                            <span class="mini-pos">2</span><span class="mini-team">2º Ano C</span><span class="mini-pts">15 PTS</span>
                        </div>
                        <div class="mini-row">
                            <span class="mini-pos">3</span><span class="mini-team">1º Ano B</span><span class="mini-pts">13 PTS</span>
                        </div>
                        <div class="mini-row">
                            <span class="mini-pos">4</span><span class="mini-team">3º Ano D</span><span class="mini-pts">10 PTS</span>
                        </div>
                        <div class="mini-row">
                            <span class="mini-pos">5</span><span class="mini-team">2º Ano A</span><span class="mini-pts">7 PTS</span>
                        </div>
                    </div>
                    <p class="small mt-2" style="color:rgba(255,255,255,.42)">
                        Critérios de desempate: pontos › saldo de gols › confronto direto.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════ ÁRBITROS ══════════════════ -->
<section class="section" id="arbitragem">
    <div class="wrap">
        <div class="split reverse">
            <div class="reveal">
                <div class="card" style="padding:32px">
                    <div class="row-between mb-3">
                        <div>
                            <div class="role-sub">Credencial de arbitragem</div>
                            <div style="font-family:var(--font-display);font-size:1.3rem;font-weight:600;letter-spacing:-.03em;color:var(--ink)">Prof. Carlos Menezes</div>
                        </div>
                        <span class="tag tag-green"><i class="fas fa-circle-check"></i> Ativa</span>
                    </div>
                    <div class="data-table-wrap" style="margin:0">
                        <table class="data" style="min-width:0">
                            <tbody>
                                <tr><td style="color:var(--muted)">Registro</td><td><strong>CREF 012345-G/SP</strong></td></tr>
                                <tr><td style="color:var(--muted)">Modalidades</td><td>Futsal · Vôlei · Handebol</td></tr>
                                <tr><td style="color:var(--muted)">Experiência</td><td>8 anos</td></tr>
                                <tr><td style="color:var(--muted)">Jogos apitados</td><td>147 partidas</td></tr>
                                <tr><td style="color:var(--muted)">Validade</td><td>31/12/<?= date('Y') + 1 ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="small mt-3" style="color:var(--muted-2)">
                        Cada credencial é analisada e aprovada pela coordenação antes do primeiro jogo.
                    </p>
                </div>
            </div>

            <div class="reveal">
                <span class="eyebrow">Profissional aplicador</span>
                <h2>Arbitragem credenciada, não improvisada</h2>
                <p class="mt-2">
                    O profissional aplicador — o juiz da partida — se cadastra pelo próprio site,
                    informa formação, registro profissional e modalidades que domina, e anexa o
                    comprovante. A coordenação analisa e aprova. Só depois disso ele recebe acesso
                    para lançar súmulas.
                </p>

                <ul class="checklist">
                    <li>
                        <span class="ck-icon"><i class="fas fa-id-card"></i></span>
                        <div>
                            <h4>Cadastro com verificação</h4>
                            <p>Formação, CREF ou registro de federação, experiência e documento comprobatório.</p>
                        </div>
                    </li>
                    <li>
                        <span class="ck-icon"><i class="fas fa-user-check"></i></span>
                        <div>
                            <h4>Aprovação pela coordenação</h4>
                            <p>Cada solicitação recebe um protocolo e passa por parecer antes da liberação.</p>
                        </div>
                    </li>
                    <li>
                        <span class="ck-icon"><i class="fas fa-scale-balanced"></i></span>
                        <div>
                            <h4>Código de conduta assinado</h4>
                            <p>Imparcialidade, sigilo dos dados dos alunos e responsabilidade sobre a súmula.</p>
                        </div>
                    </li>
                </ul>

                <div class="btn-group mt-3">
                    <a href="<?= e(sh_url('cadastro-arbitro.php')) ?>" class="btn btn-primary">
                        Fazer meu credenciamento <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="<?= e(sh_url('cadastro-arbitro.php#requisitos')) ?>" class="btn btn-ghost">Ver requisitos</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════ PLANOS ══════════════════ -->
<section class="section" id="planos" style="background:var(--surface-2);border-block:1px solid var(--line)">
    <div class="wrap">
        <div class="section-head center reveal">
            <span class="eyebrow">Planos</span>
            <h2>Assinatura anual, sem surpresa no meio do campeonato</h2>
            <p>
                Um único pagamento por ano letivo cobre todas as modalidades, todos os jogos e
                todos os alunos da escola. Comece com <strong>30 dias de teste gratuito</strong>,
                sem cartão de crédito.
            </p>
        </div>

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
                    <li><i class="fas fa-check"></i> Alunos e jogos ilimitados</li>
                    <li><i class="fas fa-check"></i> Súmula digital e placar ao vivo</li>
                    <?php if ($plano['slug'] !== 'essencial'): ?>
                        <li><i class="fas fa-check"></i> Relatórios e exportação de dados</li>
                    <?php endif; ?>
                    <?php if ($plano['slug'] === 'institucional'): ?>
                        <li><i class="fas fa-check"></i> Múltiplas unidades e gestor de rede</li>
                        <li><i class="fas fa-check"></i> Suporte dedicado e treinamento</li>
                    <?php endif; ?>
                </ul>

                <a href="<?= e(sh_url('assinar.php?plano=' . urlencode($plano['slug']))) ?>"
                   class="btn <?= $destaque ? 'btn-primary' : 'btn-dark' ?> btn-block">
                    Assinar o <?= e($plano['nome']) ?>
                </a>
                <p class="plan-note">30 dias de teste antes da primeira cobrança</p>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="center mt-4 reveal">
            <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-outline">
                Comparar planos em detalhe <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════ LGPD / SEGURANÇA ══════════════════ -->
<section class="section" id="privacidade">
    <div class="wrap">
        <div class="split">
            <div class="reveal">
                <span class="eyebrow">Privacidade e segurança</span>
                <h2>Dados de aluno pedem cuidado — e a lei concorda</h2>
                <p class="mt-2">
                    Um campeonato escolar trata dados de crianças e adolescentes, e a LGPD dá a eles
                    proteção reforçada (art. 14). O SportHub foi desenhado para coletar o mínimo
                    necessário, registrar cada consentimento e devolver o controle a quem é dono
                    da informação.
                </p>

                <ul class="checklist">
                    <li>
                        <span class="ck-icon"><i class="fas fa-minimize"></i></span>
                        <div>
                            <h4>Minimização de dados</h4>
                            <p>Coletamos nome, turma e o essencial do jogo. Nada de endereço, documento ou dado sensível de aluno.</p>
                        </div>
                    </li>
                    <li>
                        <span class="ck-icon"><i class="fas fa-file-signature"></i></span>
                        <div>
                            <h4>Consentimento registrado</h4>
                            <p>Cada aceite guarda data, versão do texto e origem — prova exigida pelo art. 8º, §1º.</p>
                        </div>
                    </li>
                    <li>
                        <span class="ck-icon"><i class="fas fa-lock"></i></span>
                        <div>
                            <h4>Senhas com hash e acesso por perfil</h4>
                            <p>Nenhuma senha é armazenada em texto puro e cada perfil enxerga só o que lhe cabe.</p>
                        </div>
                    </li>
                    <li>
                        <span class="ck-icon"><i class="fas fa-user-shield"></i></span>
                        <div>
                            <h4>Direitos do titular em um portal</h4>
                            <p>Acesso, correção, portabilidade ou eliminação: pedido com protocolo e prazo de resposta.</p>
                        </div>
                    </li>
                </ul>

                <div class="btn-group mt-3">
                    <a href="<?= e(sh_url('lgpd.php')) ?>" class="btn btn-dark">Portal LGPD</a>
                    <a href="<?= e(sh_url('privacidade.php')) ?>" class="btn btn-ghost">Ler a Política de Privacidade</a>
                </div>
            </div>

            <div class="reveal">
                <div class="grid grid-2" style="gap:16px">
                    <div class="card" style="padding:24px">
                        <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                        <h3 style="font-size:1rem">Base legal declarada</h3>
                        <p class="small">Cada finalidade de tratamento tem sua base legal escrita na política — nada de "aceite genérico".</p>
                    </div>
                    <div class="card" style="padding:24px">
                        <div class="card-icon"><i class="fas fa-clock-rotate-left"></i></div>
                        <h3 style="font-size:1rem">Prazo de retenção</h3>
                        <p class="small">Dados de campeonato são anonimizados depois do período de guarda definido em contrato.</p>
                    </div>
                    <div class="card" style="padding:24px">
                        <div class="card-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h3 style="font-size:1rem">Trilha de auditoria</h3>
                        <p class="small">Ações sensíveis ficam registradas com autor, data e IP, atendendo ao princípio da prestação de contas.</p>
                    </div>
                    <div class="card" style="padding:24px">
                        <div class="card-icon"><i class="fas fa-envelope-open-text"></i></div>
                        <h3 style="font-size:1rem">Canal do DPO</h3>
                        <p class="small">Encarregado de dados identificado e acessível por e-mail e pelo formulário do portal.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════ FAQ ══════════════════ -->
<section class="section" style="background:var(--surface-2);border-block:1px solid var(--line)">
    <div class="wrap-narrow">
        <div class="section-head center reveal">
            <span class="eyebrow">Dúvidas frequentes</span>
            <h2>O que as escolas perguntam antes de assinar</h2>
        </div>

        <div class="faq reveal">
            <details class="faq-item">
                <summary>Preciso instalar alguma coisa nos computadores da escola?</summary>
                <div class="faq-body"><p>Não. O SportHub roda no navegador, em qualquer computador, tablet ou celular com internet. Os árbitros usam o próprio telefone na quadra e a coordenação acessa do computador da secretaria.</p></div>
            </details>
            <details class="faq-item">
                <summary>Como funciona o teste de 30 dias?</summary>
                <div class="faq-body"><p>Você contrata o plano desejado e o acesso é liberado imediatamente, sem cobrança. Durante 30 dias a escola usa todos os recursos do plano escolhido. A cobrança só acontece se você confirmar a continuidade — e nenhum cartão é exigido para começar.</p></div>
            </details>
            <details class="faq-item">
                <summary>A assinatura é mesmo anual? Existe plano mensal?</summary>
                <div class="faq-body">
                    <p>A assinatura acompanha o ano letivo e é cobrada uma vez por ano. Foi uma escolha deliberada: interclasse não é um serviço de uso contínuo, é um evento com temporada — cobrar mensalmente encareceria a escola justamente nos meses em que ela não usa a plataforma.</p>
                    <p>O valor anual pode ser parcelado no cartão ou pago por boleto, Pix ou empenho, no caso de escolas públicas.</p>
                </div>
            </details>
            <details class="faq-item">
                <summary>Quem pode ser árbitro na plataforma?</summary>
                <div class="faq-body"><p>Professores de Educação Física, árbitros federados, técnicos esportivos e estudantes de licenciatura em Educação Física — todos passam pelo credenciamento, com envio de documento e aprovação da coordenação da escola. <a href="<?= e(sh_url('cadastro-arbitro.php#requisitos')) ?>">Veja os requisitos completos</a>.</p></div>
            </details>
            <details class="faq-item">
                <summary>Os dados dos alunos ficam expostos?</summary>
                <div class="faq-body"><p>Não. O perfil de consulta mostra nome, turma e desempenho esportivo — nada além disso. Documentos, contatos e dados sensíveis não são coletados de alunos. O detalhamento está na <a href="<?= e(sh_url('privacidade.php')) ?>">Política de Privacidade</a>.</p></div>
            </details>
            <details class="faq-item">
                <summary>E se a escola quiser cancelar?</summary>
                <div class="faq-body"><p>O cancelamento pode ser solicitado a qualquer momento pelo canal de contato. A escola mantém o acesso até o fim do período já pago e pode exportar todo o histórico do campeonato antes do encerramento — portabilidade prevista no art. 18 da LGPD.</p></div>
            </details>
            <details class="faq-item">
                <summary>Dá para usar em mais de uma unidade?</summary>
                <div class="faq-body"><p>Sim, com o plano Institucional. Ele foi pensado para redes de ensino que rodam interclasses simultâneos em unidades diferentes, com um gestor de rede acompanhando todas.</p></div>
            </details>
        </div>
    </div>
</section>

<!-- ══════════════════ CTA FINAL ══════════════════ -->
<section class="section">
    <div class="wrap">
        <div class="cta-box reveal">
            <span class="pill on-dark"><span class="dot"></span> Comece pelo teste gratuito</span>
            <h2>Seu próximo interclasse pode começar hoje</h2>
            <p>
                Cadastre a escola, monte a primeira modalidade e veja a tabela nascer sozinha.
                Trinta dias para decidir, sem cartão e sem compromisso.
            </p>
            <div class="btn-group">
                <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-primary btn-lg">
                    Escolher um plano <i class="fas fa-arrow-right"></i>
                </a>
                <a href="<?= e(sh_url('contato.php')) ?>" class="btn btn-on-dark btn-lg">
                    <i class="fas fa-comments"></i> Falar com a equipe
                </a>
            </div>
            <p class="cta-fine">Já tem conta? <a href="<?= e(sh_url('login.php')) ?>" style="color:var(--ember-light);text-decoration:underline">Entrar na plataforma</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
