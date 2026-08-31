<?php
/** como-funciona.php — guia completo de uso da plataforma. */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Como funciona o SportHub — guia completo do interclasse digital';
$page_desc  = 'Passo a passo de como organizar um campeonato interclasse no SportHub: da criação das modalidades ao pódio, com o que faz a coordenação, o árbitro credenciado e o aluno.';
$active     = 'como-funciona';

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><span class="dot"></span> Guia de uso</span>
        <h1>Como funciona o SportHub</h1>
        <p>
            Um campeonato interclasse tem sempre as mesmas etapas: inscrever, sortear, jogar, apurar.
            Esta página mostra como cada uma delas acontece na plataforma — e quem faz o quê.
        </p>
        <div class="legal-meta">
            <span class="tag on-dark"><i class="fas fa-clock"></i> Leitura de 6 minutos</span>
            <span class="tag on-dark"><i class="fas fa-users"></i> Para coordenação, arbitragem e alunos</span>
        </div>
    </div>
</section>

<!-- ═══════════ VISÃO GERAL ═══════════ -->
<section class="section-sm">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Visão geral</span>
            <h2>O ciclo completo de um campeonato</h2>
            <p>Sete etapas, do primeiro acesso ao relatório final. Nenhuma delas exige conhecimento técnico.</p>
        </div>

        <div class="timeline">
            <div class="timeline-item accent reveal">
                <div class="timeline-dot">1</div>
                <div class="timeline-body">
                    <h3>A escola assina e recebe o acesso</h3>
                    <p>
                        A coordenação escolhe um plano anual e preenche os dados da instituição.
                        O acesso de administrador é liberado na hora, com 30 dias de teste antes de
                        qualquer cobrança. É esse perfil que comanda todo o resto.
                    </p>
                    <div class="timeline-tags">
                        <span class="tag tag-ember"><i class="fas fa-user-gear"></i> Coordenação</span>
                        <span class="tag"><i class="fas fa-clock"></i> ~5 minutos</span>
                    </div>
                </div>
            </div>

            <div class="timeline-item reveal">
                <div class="timeline-dot">2</div>
                <div class="timeline-body">
                    <h3>Cadastro das modalidades</h3>
                    <p>
                        Futsal masculino, vôlei feminino, handebol misto, xadrez… Cada modalidade é
                        criada com nome e gênero, e passa a ter seu próprio calendário e sua própria
                        tabela de classificação. Um mesmo interclasse pode rodar várias em paralelo.
                    </p>
                    <div class="timeline-tags">
                        <span class="tag tag-ember"><i class="fas fa-user-gear"></i> Coordenação</span>
                        <span class="tag"><i class="fas fa-location-dot"></i> Menu Modalidades</span>
                    </div>
                </div>
            </div>

            <div class="timeline-item reveal">
                <div class="timeline-dot">3</div>
                <div class="timeline-body">
                    <h3>Inscrição dos times e dos elencos</h3>
                    <p>
                        Cada turma vira um time, com sala, série e gênero. Dentro do time entram os
                        jogadores com nome e número de camisa, e a turma pode subir seu próprio
                        escudo. É esse elenco que aparece depois para o árbitro na hora da súmula.
                    </p>
                    <div class="timeline-tags">
                        <span class="tag tag-ember"><i class="fas fa-user-gear"></i> Coordenação</span>
                        <span class="tag"><i class="fas fa-location-dot"></i> Menu Times · Logos</span>
                    </div>
                </div>
            </div>

            <div class="timeline-item reveal">
                <div class="timeline-dot">4</div>
                <div class="timeline-body">
                    <h3>Credenciamento da arbitragem</h3>
                    <p>
                        O profissional aplicador se cadastra pelo site informando formação, registro
                        profissional e modalidades que domina. A coordenação analisa a solicitação,
                        emite o parecer e — se aprovada — o sistema cria o acesso de árbitro
                        automaticamente, com usuário e senha provisória.
                    </p>
                    <div class="timeline-tags">
                        <span class="tag tag-ember"><i class="fas fa-user-gear"></i> Coordenação</span>
                        <span class="tag tag-blue"><i class="fas fa-bullhorn"></i> Árbitro</span>
                        <span class="tag"><i class="fas fa-location-dot"></i> Credenciar-se</span>
                    </div>
                </div>
            </div>

            <div class="timeline-item reveal">
                <div class="timeline-dot">5</div>
                <div class="timeline-body">
                    <h3>Agendamento dos jogos</h3>
                    <p>
                        Com times e árbitros prontos, a coordenação monta o calendário: modalidade,
                        confronto, data, horário, local e fase (grupo, quartas, semifinal, final).
                        Cada partida recebe um árbitro responsável — e é só ele que poderá lançar
                        aquele resultado.
                    </p>
                    <div class="timeline-tags">
                        <span class="tag tag-ember"><i class="fas fa-user-gear"></i> Coordenação</span>
                        <span class="tag"><i class="fas fa-location-dot"></i> Menu Jogos · Designar</span>
                    </div>
                </div>
            </div>

            <div class="timeline-item reveal">
                <div class="timeline-dot">6</div>
                <div class="timeline-body">
                    <h3>O jogo e a súmula digital</h3>
                    <p>
                        Na quadra, o árbitro abre o painel no celular e vê apenas as partidas dele.
                        Registra o placar, os gols com autor e minuto, cartões amarelos e vermelhos,
                        substituições e estatísticas. Ao encerrar, a partida muda para
                        <em>finalizada</em> e a súmula fica arquivada.
                    </p>
                    <div class="timeline-tags">
                        <span class="tag tag-blue"><i class="fas fa-bullhorn"></i> Árbitro</span>
                        <span class="tag"><i class="fas fa-mobile-screen"></i> Funciona no celular</span>
                    </div>
                </div>
            </div>

            <div class="timeline-item accent reveal">
                <div class="timeline-dot">7</div>
                <div class="timeline-body">
                    <h3>Classificação, artilharia e histórico</h3>
                    <p>
                        No instante em que a súmula é encerrada, a tabela é recalculada: pontos,
                        vitórias, saldo de gols e critérios de desempate. Alunos e famílias veem o
                        resultado no mesmo momento. Ao fim da temporada, tudo fica arquivado como
                        memória daquela edição.
                    </p>
                    <div class="timeline-tags">
                        <span class="tag tag-green"><i class="fas fa-graduation-cap"></i> Alunos e comunidade</span>
                        <span class="tag"><i class="fas fa-bolt"></i> Automático</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ POR PERFIL ═══════════ -->
<section class="section dark" id="perfis">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow on-dark">Na prática</span>
            <h2>O que cada pessoa faz no dia do jogo</h2>
            <p>As mesmas telas, três experiências diferentes — cada perfil enxerga só o que é dele.</p>
        </div>

        <div class="grid grid-3">
            <article class="card on-dark reveal" id="coordenacao">
                <div class="card-icon"><i class="fas fa-user-gear"></i></div>
                <h3>Coordenação</h3>
                <p class="small">Perfil administrador</p>
                <ul class="role-list mt-2">
                    <li><i class="fas fa-arrow-right"></i> Confere o calendário do dia no dashboard</li>
                    <li><i class="fas fa-arrow-right"></i> Ajusta horário ou local se algo mudar</li>
                    <li><i class="fas fa-arrow-right"></i> Reatribui o árbitro em caso de falta</li>
                    <li><i class="fas fa-arrow-right"></i> Acompanha as súmulas sendo fechadas</li>
                    <li><i class="fas fa-arrow-right"></i> Corrige um lançamento equivocado</li>
                </ul>
            </article>

            <article class="card on-dark reveal" id="arbitro">
                <div class="card-icon"><i class="fas fa-bullhorn"></i></div>
                <h3>Árbitro credenciado</h3>
                <p class="small">Profissional aplicador</p>
                <ul class="role-list mt-2">
                    <li><i class="fas fa-arrow-right"></i> Entra e vê "Meus jogos" de hoje</li>
                    <li><i class="fas fa-arrow-right"></i> Abre a partida e confere os elencos</li>
                    <li><i class="fas fa-arrow-right"></i> Lança gols com autor e minuto</li>
                    <li><i class="fas fa-arrow-right"></i> Registra cartões e ocorrências</li>
                    <li><i class="fas fa-arrow-right"></i> Encerra e assina a súmula</li>
                </ul>
            </article>

            <article class="card on-dark reveal" id="aluno">
                <div class="card-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3>Aluno e comunidade</h3>
                <p class="small">Perfil de consulta</p>
                <ul class="role-list mt-2">
                    <li><i class="fas fa-arrow-right"></i> Vê onde e quando sua turma joga</li>
                    <li><i class="fas fa-arrow-right"></i> Acompanha o placar em tempo real</li>
                    <li><i class="fas fa-arrow-right"></i> Confere a classificação atualizada</li>
                    <li><i class="fas fa-arrow-right"></i> Consulta a artilharia da modalidade</li>
                    <li><i class="fas fa-arrow-right"></i> Revisita resultados anteriores</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<!-- ═══════════ PRIMEIROS 7 DIAS ═══════════ -->
<section class="section">
    <div class="wrap">
        <div class="section-head center reveal">
            <span class="eyebrow">Implantação</span>
            <h2>Seus primeiros sete dias</h2>
            <p>Um roteiro sugerido para sair do zero até o primeiro jogo apitado dentro da plataforma.</p>
        </div>

        <div class="grid grid-2 reveal">
            <div class="card">
                <span class="tag tag-ember mb-2"><i class="fas fa-calendar-day"></i> Dias 1 e 2</span>
                <h3>Configuração da base</h3>
                <p class="small mt-1">
                    Assine o plano, acesse com o perfil de coordenação e cadastre as modalidades da
                    edição. Em seguida inscreva os times por turma. Reserve uma hora — é a etapa mais
                    trabalhosa, e só se faz uma vez por ano.
                </p>
            </div>
            <div class="card">
                <span class="tag tag-ember mb-2"><i class="fas fa-calendar-day"></i> Dias 3 e 4</span>
                <h3>Elencos e arbitragem</h3>
                <p class="small mt-1">
                    Peça a cada turma a lista de jogadores com número de camisa e o escudo. Ao mesmo
                    tempo, envie o link de credenciamento aos professores de Educação Física e
                    árbitros convidados, e aprove as solicitações que chegarem.
                </p>
            </div>
            <div class="card">
                <span class="tag tag-ember mb-2"><i class="fas fa-calendar-day"></i> Dia 5</span>
                <h3>Calendário publicado</h3>
                <p class="small mt-1">
                    Monte as chaves e agende as partidas da primeira rodada, designando o árbitro de
                    cada uma. Assim que salvar, os alunos já conseguem ver onde e quando a turma
                    deles joga.
                </p>
            </div>
            <div class="card">
                <span class="tag tag-ember mb-2"><i class="fas fa-calendar-day"></i> Dias 6 e 7</span>
                <h3>Ensaio e primeira rodada</h3>
                <p class="small mt-1">
                    Faça um jogo-teste com um árbitro para ele conhecer a súmula sem pressão — é
                    possível apagar o resultado depois. Na sequência, rode a primeira rodada real e
                    veja a classificação nascer sozinha.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ REGRAS DE APURAÇÃO ═══════════ -->
<section class="section u-background-surface-2">
    <div class="wrap-narrow">
        <div class="section-head reveal">
            <span class="eyebrow">Regulamento</span>
            <h2>Como a classificação é calculada</h2>
            <p>O cálculo é o mesmo usado em competições oficiais e fica visível para todos, evitando discussão depois da rodada.</p>
        </div>

        <div class="data-table-wrap reveal">
            <table class="data">
                <thead>
                    <tr><th>Situação</th><th>Pontuação</th><th>Observação</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Vitória</strong></td><td>3 pontos</td><td>Padrão para modalidades coletivas</td></tr>
                    <tr><td><strong>Empate</strong></td><td>1 ponto</td><td>Não se aplica a fases eliminatórias</td></tr>
                    <tr><td><strong>Derrota</strong></td><td>0 ponto</td><td>Saldo de gols continua contando</td></tr>
                    <tr><td><strong>W.O.</strong></td><td>0 ponto</td><td>Registrado pela coordenação nas observações</td></tr>
                </tbody>
            </table>
        </div>

        <div class="callout reveal">
            <i class="fas fa-list-ol"></i>
            <div>
                <strong>Critérios de desempate, nesta ordem</strong>
                <p>1. Maior número de pontos · 2. Maior número de vitórias · 3. Melhor saldo de gols ou pontos · 4. Maior número de gols/pontos marcados · 5. Confronto direto · 6. Menor número de cartões (fair play) · 7. Sorteio.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ CTA ═══════════ -->
<section class="section">
    <div class="wrap">
        <div class="cta-box reveal">
            <h2>Pronto para montar o seu?</h2>
            <p>Escolha um plano e comece pelo teste de 30 dias. Se ainda restou dúvida, a gente conversa antes.</p>
            <div class="btn-group">
                <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-primary btn-lg">Ver planos <i class="fas fa-arrow-right"></i></a>
                <a href="<?= e(sh_url('contato.php')) ?>" class="btn btn-on-dark btn-lg"><i class="fas fa-comments"></i> Falar com a equipe</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
