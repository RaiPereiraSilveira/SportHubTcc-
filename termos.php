<?php
/** termos.php — Termos de Uso da plataforma. */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Termos de Uso — SportHub';
$page_desc  = 'Condições de uso da plataforma SportHub: assinatura anual, obrigações da escola, regras para árbitros credenciados, pagamento, cancelamento e responsabilidades.';
$active     = 'privacidade';

$secoes = [
    'objeto'         => 'Objeto e aceitação',
    'cadastro'       => 'Cadastro e contas de acesso',
    'perfis'         => 'Perfis e permissões',
    'arbitros'       => 'Regras do profissional aplicador',
    'assinatura'     => 'Assinatura, preço e pagamento',
    'teste'          => 'Período de teste e arrependimento',
    'cancelamento'   => 'Cancelamento e renovação',
    'obrigacoes'     => 'Obrigações da escola contratante',
    'condutas'       => 'Condutas proibidas',
    'conteudo'       => 'Conteúdo e propriedade intelectual',
    'dados'          => 'Proteção de dados',
    'disponibilidade'=> 'Disponibilidade e suporte',
    'responsabilidade'=> 'Limitação de responsabilidade',
    'alteracoes'     => 'Alterações dos termos',
    'foro'           => 'Legislação e foro',
];

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><i class="fas fa-file-contract"></i> Documento legal</span>
        <h1>Termos de Uso</h1>
        <p>
            As regras do jogo fora de quadra. Este documento define o que a escola contrata,
            o que o SportHub entrega e o que se espera de cada pessoa que usa a plataforma.
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

        <section id="objeto">
            <h2><span class="num">01</span> Objeto e aceitação</h2>
            <p>
                O SportHub é um software como serviço (SaaS) para gestão de campeonatos esportivos
                escolares, disponibilizado por assinatura anual a instituições de ensino. Ele permite
                cadastrar modalidades, times e jogadores, agendar partidas, credenciar profissionais
                de arbitragem, registrar súmulas digitais e apurar classificação automaticamente.
            </p>
            <p>
                Ao contratar a assinatura, criar uma conta ou utilizar qualquer funcionalidade da
                plataforma, você declara que leu, compreendeu e aceita integralmente estes Termos e a
                <a href="<?= e(sh_url('privacidade.php')) ?>">Política de Privacidade</a>. Se você não
                concorda com alguma cláusula, não utilize o serviço.
            </p>
            <div class="callout">
                <i class="fas fa-circle-info"></i>
                <div>
                    <strong>Quem pode contratar</strong>
                    <p>A assinatura só pode ser contratada por pessoa maior de 18 anos com poderes de representação da instituição de ensino. Alunos menores de idade usam a plataforma apenas no perfil de consulta, sob responsabilidade da escola.</p>
                </div>
            </div>
        </section>

        <section id="cadastro">
            <h2><span class="num">02</span> Cadastro e contas de acesso</h2>
            <ul>
                <li>As informações fornecidas no cadastro devem ser verdadeiras, completas e atualizadas.</li>
                <li>A conta é pessoal e intransferível. Compartilhar credenciais é proibido e transfere a você a responsabilidade por tudo que for feito com elas.</li>
                <li>A senha é armazenada apenas como <em>hash</em> — não temos como recuperá-la, apenas redefini-la.</li>
                <li>Suspeitou de acesso indevido? Troque a senha imediatamente e avise em <a href="mailto:<?= e(SH_EMAIL) ?>"><?= e(SH_EMAIL) ?></a>.</li>
                <li>Podemos suspender contas com indício de fraude, uso indevido ou violação destes Termos, com comunicação ao titular.</li>
            </ul>
        </section>

        <section id="perfis">
            <h2><span class="num">03</span> Perfis e permissões</h2>
            <div class="data-table-wrap">
                <table class="data">
                    <thead><tr><th class="u-width-190px">Perfil</th><th>Pode fazer</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Administrador</strong><br><span class="small muted">Coordenação</span></td><td>Gerenciar modalidades, times, jogadores, jogos e árbitros; aprovar credenciamentos; corrigir resultados; responder solicitações de titulares.</td></tr>
                        <tr><td><strong>Árbitro</strong><br><span class="small muted">Profissional aplicador</span></td><td>Visualizar apenas as partidas designadas a si; abrir, preencher e encerrar a súmula digital dessas partidas.</td></tr>
                        <tr><td><strong>Aluno</strong><br><span class="small muted">Consulta</span></td><td>Consultar calendário, placares, classificação, artilharia e resultados; gerenciar o próprio perfil.</td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                Tentar acessar funcionalidade de perfil superior ao seu é violação destes Termos e
                pode caracterizar o crime de invasão de dispositivo informático (art. 154-A do Código Penal).
            </p>
        </section>

        <section id="arbitros">
            <h2><span class="num">04</span> Regras do profissional aplicador</h2>
            <p>
                O credenciamento é gratuito para o árbitro, individual e sujeito a análise. Ao se
                credenciar, o profissional aplicador assume os seguintes compromissos:
            </p>
            <ol>
                <li><strong>Veracidade.</strong> Todas as informações de formação, registro e experiência são verdadeiras, e o documento anexado é autêntico. Falsidade implica cancelamento imediato da credencial.</li>
                <li><strong>Imparcialidade.</strong> Conduzir a partida sem favorecer qualquer time, turma ou aluno, abstendo-se quando houver conflito de interesse.</li>
                <li><strong>Fidedignidade da súmula.</strong> Registrar apenas fatos efetivamente ocorridos em quadra. A súmula encerrada é documento da competição.</li>
                <li><strong>Sigilo.</strong> Manter confidencialidade sobre os dados de alunos a que tiver acesso, usando-os exclusivamente para a operação da partida.</li>
                <li><strong>Respeito.</strong> Tratar alunos, professores e demais participantes com urbanidade, sem qualquer forma de discriminação, assédio ou constrangimento.</li>
                <li><strong>Responsabilidade pelo acesso.</strong> Não permitir que terceiros lancem súmulas em seu nome.</li>
            </ol>
            <p>
                A credencial tem validade determinada e pode ser suspensa ou cancelada pela coordenação
                da escola ou pelo SportHub em caso de descumprimento, mediante comunicação
                fundamentada. O árbitro poderá apresentar defesa pelo canal de contato.
            </p>
            <p>
                O credenciamento <strong>não cria vínculo empregatício</strong> entre o profissional
                aplicador e o SportHub. A relação de trabalho ou prestação de serviço, quando houver,
                é estabelecida diretamente entre o árbitro e a instituição de ensino.
            </p>
        </section>

        <section id="assinatura">
            <h2><span class="num">05</span> Assinatura, preço e pagamento</h2>
            <ul>
                <li>A assinatura é <strong>anual</strong>, contratada por instituição, e cobre a temporada letiva contratada.</li>
                <li>Os valores vigentes são os publicados na página de <a href="<?= e(sh_url('planos.php')) ?>">Planos</a> no momento da contratação e permanecem fixos durante toda a vigência.</li>
                <li>Não há taxa de implantação, taxa por aluno, por jogo ou por súmula registrada.</li>
                <li>Formas de pagamento aceitas: boleto bancário, Pix, cartão de crédito (à vista ou parcelado em até 12 vezes, sem alteração do valor total) e empenho, para instituições da rede pública.</li>
                <li><strong>Nenhum dado de cartão é coletado pelo site.</strong> O pagamento é processado por instituição financeira parceira, por link enviado ao responsável.</li>
                <li>O atraso superior a 30 dias autoriza a suspensão do acesso, precedida de aviso, preservados os dados por 90 dias para regularização.</li>
                <li>A nota fiscal é emitida em nome da instituição informada na contratação.</li>
            </ul>
            <p>
                Exceder os limites do plano contratado (times, modalidades ou árbitros credenciados)
                exige migração para plano superior, cobrando-se apenas a diferença proporcional ao
                tempo restante da vigência.
            </p>
        </section>

        <section id="teste">
            <h2><span class="num">06</span> Período de teste e direito de arrependimento</h2>
            <ul>
                <li>Toda nova contratação começa com <strong>30 dias de teste gratuito</strong>, com acesso integral aos recursos do plano escolhido.</li>
                <li>Nenhum dado de pagamento é exigido para iniciar o teste e nenhuma cobrança é gerada automaticamente ao fim dele.</li>
                <li>A cobrança só ocorre após confirmação expressa da escola.</li>
                <li>Nos termos do art. 49 do Código de Defesa do Consumidor, a contratação pode ser desfeita em até <strong>7 dias corridos</strong> a contar do pagamento, com reembolso integral.</li>
            </ul>
        </section>

        <section id="cancelamento">
            <h2><span class="num">07</span> Cancelamento e renovação</h2>
            <ul>
                <li>O cancelamento pode ser solicitado a qualquer momento, sem multa e sem fidelidade, pelo canal de <a href="<?= e(sh_url('contato.php')) ?>">contato</a>.</li>
                <li>Cancelada a assinatura, o acesso permanece ativo até o fim do período já pago.</li>
                <li>Antes do encerramento, a escola pode exportar todo o histórico do campeonato — direito de portabilidade previsto no art. 18, V da LGPD.</li>
                <li>Após 90 dias do encerramento, os dados são eliminados ou anonimizados, ressalvados os registros de guarda obrigatória descritos na <a href="<?= e(sh_url('privacidade.php') . '#retencao') ?>">Política de Privacidade</a>.</li>
                <li><strong>Não há renovação automática silenciosa.</strong> Avisamos por e-mail 30 dias antes do vencimento, e a renovação depende de confirmação da escola.</li>
            </ul>
        </section>

        <section id="obrigacoes">
            <h2><span class="num">08</span> Obrigações da escola contratante</h2>
            <p>
                A escola é <strong>controladora</strong> dos dados que insere na plataforma. Cabe a ela:
            </p>
            <ul>
                <li>Obter, quando necessário, o consentimento dos responsáveis legais pelos alunos participantes.</li>
                <li>Manter os dados cadastrais de times e jogadores corretos e atualizados.</li>
                <li>Conceder e revogar acessos internos com critério, removendo contas de pessoas que deixarem a instituição.</li>
                <li>Utilizar a plataforma exclusivamente para fins educacionais e esportivos.</li>
                <li>Responder, na qualidade de controladora, às solicitações de titulares relativas aos dados do campeonato — com o nosso apoio operacional.</li>
            </ul>
        </section>

        <section id="condutas">
            <h2><span class="num">09</span> Condutas proibidas</h2>
            <p>É vedado a qualquer usuário:</p>
            <ul>
                <li>Inserir dados falsos, adulterar resultados ou manipular súmulas encerradas.</li>
                <li>Usar a conta de outra pessoa ou permitir que usem a sua.</li>
                <li>Tentar burlar controles de acesso, explorar vulnerabilidades ou realizar engenharia reversa do software.</li>
                <li>Publicar conteúdo ofensivo, discriminatório, de assédio ou que viole direitos de terceiros.</li>
                <li>Extrair dados em massa por meios automatizados sem autorização escrita.</li>
                <li>Usar a plataforma para finalidade comercial estranha à gestão do campeonato.</li>
            </ul>
            <p>
                A constatação de qualquer dessas condutas autoriza a suspensão imediata do acesso, sem
                prejuízo das medidas cíveis e criminais cabíveis.
            </p>
        </section>

        <section id="conteudo">
            <h2><span class="num">10</span> Conteúdo e propriedade intelectual</h2>
            <p>
                O software, a marca, o design, o código-fonte e a documentação do SportHub são de sua
                titularidade exclusiva e protegidos pela Lei nº 9.610/1998 e pela Lei nº 9.609/1998.
                A assinatura concede à escola uma <strong>licença de uso não exclusiva, intransferível
                e limitada</strong> ao período contratado — não há transferência de propriedade.
            </p>
            <p>
                Os dados do campeonato — times, jogadores, súmulas, resultados e escudos enviados —
                permanecem de titularidade da escola e dos respectivos titulares. Ao enviar escudos,
                fotos ou logotipos, a escola declara possuir os direitos necessários e nos autoriza a
                exibi-los dentro da plataforma para operação do campeonato.
            </p>
        </section>

        <section id="dados">
            <h2><span class="num">11</span> Proteção de dados</h2>
            <p>
                O tratamento de dados pessoais é regido pela
                <a href="<?= e(sh_url('privacidade.php')) ?>">Política de Privacidade</a>, que integra
                estes Termos para todos os efeitos. Nas operações em que atuamos como operador,
                tratamos os dados apenas conforme as instruções documentadas da escola controladora,
                nos termos do art. 39 da LGPD.
            </p>
            <p>
                Os direitos do titular podem ser exercidos pelo
                <a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD</a>, com protocolo e prazo de
                resposta de 15 dias corridos.
            </p>
        </section>

        <section id="disponibilidade">
            <h2><span class="num">12</span> Disponibilidade e suporte</h2>
            <ul>
                <li>Empenhamo-nos em manter a plataforma disponível de forma contínua, com meta de <strong>99% de disponibilidade mensal</strong>, excluídas as janelas de manutenção programada.</li>
                <li>Manutenções programadas são avisadas com pelo menos 48 horas de antecedência e realizadas preferencialmente fora do horário escolar.</li>
                <li>O suporte varia conforme o plano: por e-mail no Essencial; por e-mail e WhatsApp no Pro; dedicado no Institucional.</li>
                <li>Interrupções causadas por falha de internet da escola, indisponibilidade de terceiros ou caso fortuito não configuram descumprimento contratual.</li>
            </ul>
        </section>

        <section id="responsabilidade">
            <h2><span class="num">13</span> Limitação de responsabilidade</h2>
            <p>
                O SportHub é uma ferramenta de registro e apuração. As decisões técnicas e disciplinares
                do campeonato — validade de um gol, aplicação de cartão, resultado de uma partida — são
                de responsabilidade da arbitragem e da comissão organizadora da escola.
            </p>
            <p>
                Não respondemos por danos decorrentes de: informação incorreta inserida por usuário;
                uso indevido de credenciais; decisões de arbitragem; indisponibilidade de terceiros;
                ou eventos de força maior. Nossa responsabilidade, em qualquer hipótese, limita-se ao
                valor efetivamente pago pela escola nos 12 meses anteriores ao fato gerador.
            </p>
        </section>

        <section id="alteracoes">
            <h2><span class="num">14</span> Alterações dos termos</h2>
            <p>
                Podemos alterar estes Termos para refletir mudanças no serviço ou na legislação.
                Alterações relevantes são comunicadas por e-mail e por aviso na plataforma com
                <strong>30 dias</strong> de antecedência. Se você não concordar, poderá cancelar a
                assinatura com reembolso proporcional ao período não utilizado.
            </p>
            <p>Versão atual: <strong><?= e(SH_VERSAO_POLITICA) ?></strong>, vigente desde <?= e(SH_POLITICA_DATA) ?>.</p>
        </section>

        <section id="foro">
            <h2><span class="num">15</span> Legislação e foro</h2>
            <p>
                Estes Termos são regidos pelas leis da República Federativa do Brasil, em especial o
                Código Civil, o Código de Defesa do Consumidor (Lei nº 8.078/1990), o Marco Civil da
                Internet (Lei nº 12.965/2014) e a LGPD (Lei nº 13.709/2018).
            </p>
            <p>
                Fica eleito o foro da comarca do domicílio do consumidor ou da instituição contratante
                para dirimir controvérsias, renunciando as partes a qualquer outro, por mais
                privilegiado que seja. Antes disso, comprometemo-nos a buscar solução amigável pelo
                canal <a href="mailto:<?= e(SH_EMAIL) ?>"><?= e(SH_EMAIL) ?></a>.
            </p>

            <div class="btn-group mt-3">
                <a href="<?= e(sh_url('planos.php')) ?>" class="btn btn-primary">Ver planos <i class="fas fa-arrow-right"></i></a>
                <a href="<?= e(sh_url('privacidade.php')) ?>" class="btn btn-outline">Política de Privacidade</a>
            </div>
        </section>

    </article>
</div>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
