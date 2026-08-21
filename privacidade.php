<?php
/** privacidade.php — Política de Privacidade (LGPD, Lei nº 13.709/2018). */
require_once __DIR__ . '/includes/config.php';

$page_title = 'Política de Privacidade — SportHub';
$page_desc  = 'Como o SportHub coleta, usa, compartilha e protege dados pessoais de escolas, árbitros, alunos e responsáveis, conforme a Lei nº 13.709/2018 (LGPD).';
$active     = 'privacidade';

$secoes = [
    'quem-somos'      => 'Quem trata seus dados',
    'definicoes'      => 'Definições importantes',
    'dados-coletados' => 'Dados que coletamos',
    'finalidades'     => 'Para que usamos e com qual base legal',
    'criancas'        => 'Dados de crianças e adolescentes',
    'compartilhamento'=> 'Com quem compartilhamos',
    'cookies'         => 'Cookies e tecnologias similares',
    'seguranca'       => 'Segurança da informação',
    'retencao'        => 'Por quanto tempo guardamos',
    'direitos'        => 'Seus direitos como titular',
    'exercer'         => 'Como exercer seus direitos',
    'internacional'   => 'Transferência internacional',
    'incidentes'      => 'Incidentes de segurança',
    'alteracoes'      => 'Alterações desta política',
    'contato'         => 'Fale com o encarregado',
];

include __DIR__ . '/includes/site_header.php';
?>

<section class="legal-hero">
    <div class="wrap">
        <span class="pill on-dark"><i class="fas fa-shield-halved"></i> Documento legal</span>
        <h1>Política de Privacidade</h1>
        <p>
            Este documento explica, em português claro, quais dados pessoais o SportHub trata,
            por que os trata e o que você pode exigir de nós. Ele vale para escolas contratantes,
            profissionais de arbitragem, alunos, responsáveis e visitantes do site.
        </p>
        <div class="legal-meta">
            <span class="tag on-dark"><i class="fas fa-file-lines"></i> Versão <?= e(SH_VERSAO_POLITICA) ?></span>
            <span class="tag on-dark"><i class="fas fa-calendar"></i> Vigente desde <?= e(SH_POLITICA_DATA) ?></span>
            <span class="tag on-dark"><i class="fas fa-gavel"></i> Lei nº 13.709/2018</span>
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

        <div class="callout">
            <i class="fas fa-lightbulb"></i>
            <div>
                <strong>Em uma frase</strong>
                <p>
                    Coletamos o mínimo necessário para organizar um campeonato escolar, nunca vendemos
                    dados, protegemos com atenção redobrada as informações de menores de idade e
                    devolvemos a você o controle sobre tudo o que guardamos.
                </p>
            </div>
        </div>

        <section id="quem-somos">
            <h2><span class="num">01</span> Quem trata seus dados</h2>
            <p>
                O <strong>SportHub</strong> é uma plataforma de gestão de campeonatos interclasse.
                Na relação com as escolas contratantes, atuamos em dois papéis distintos previstos
                na LGPD:
            </p>
            <ul>
                <li><strong>Controlador</strong> — quanto aos dados de quem se relaciona diretamente conosco: responsáveis pela contratação, profissionais de arbitragem que se credenciam e visitantes do site. Nesses casos, somos nós que decidimos as finalidades do tratamento.</li>
                <li><strong>Operador</strong> — quanto aos dados de alunos e times inseridos pela escola dentro da plataforma. Aqui a escola é a controladora: ela decide o que cadastrar, e nós apenas processamos conforme a instrução dela e o contrato firmado.</li>
            </ul>
            <p>
                Essa distinção importa: se você é aluno ou responsável e quer exercer um direito sobre
                dados do campeonato, o pedido pode ser feito tanto à escola quanto a nós — e, neste
                segundo caso, encaminhamos à instituição responsável, acompanhando o atendimento.
            </p>
        </section>

        <section id="definicoes">
            <h2><span class="num">02</span> Definições importantes</h2>
            <div class="data-table-wrap">
                <table class="data">
                    <thead><tr><th style="width:180px">Termo</th><th>O que significa aqui</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Dado pessoal</strong></td><td>Qualquer informação que identifique ou possa identificar uma pessoa — nome, e-mail, CPF, foto.</td></tr>
                        <tr><td><strong>Titular</strong></td><td>A pessoa a quem os dados se referem: você.</td></tr>
                        <tr><td><strong>Tratamento</strong></td><td>Qualquer operação com dados: coletar, guardar, usar, compartilhar, eliminar.</td></tr>
                        <tr><td><strong>Controlador</strong></td><td>Quem decide como e por que os dados são tratados.</td></tr>
                        <tr><td><strong>Operador</strong></td><td>Quem trata os dados seguindo instruções do controlador.</td></tr>
                        <tr><td><strong>Encarregado (DPO)</strong></td><td>A pessoa que faz a ponte entre você, nós e a ANPD.</td></tr>
                        <tr><td><strong>ANPD</strong></td><td>Autoridade Nacional de Proteção de Dados, o órgão fiscalizador.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="dados-coletados">
            <h2><span class="num">03</span> Dados que coletamos</h2>
            <p>Coletamos conjuntos diferentes conforme o seu papel na plataforma. Nada além do que está listado abaixo é solicitado.</p>

            <h3>Responsável pela contratação (escola)</h3>
            <ul>
                <li>Nome completo, cargo, e-mail institucional e telefone.</li>
                <li>Dados da instituição: razão social, CNPJ, cidade e estado.</li>
                <li>Forma de pagamento escolhida e registro do aceite contratual (data, hora, IP).</li>
            </ul>
            <div class="callout">
                <i class="fas fa-credit-card"></i>
                <div>
                    <strong>Não armazenamos dados de cartão</strong>
                    <p>Nenhum número de cartão, CVV ou senha bancária trafega ou é guardado pelo SportHub. O pagamento é processado por instituição financeira parceira, com link enviado diretamente ao responsável.</p>
                </div>
            </div>

            <h3>Profissional aplicador (árbitro)</h3>
            <ul>
                <li>Nome completo, CPF, data de nascimento, e-mail e telefone.</li>
                <li>Cidade, estado e escola de vínculo, quando informados.</li>
                <li>Formação, órgão e número de registro profissional, tempo de experiência e modalidades.</li>
                <li>Documento comprobatório anexado (carteira do CREF, certificado, diploma ou declaração).</li>
                <li>Registro dos aceites de termos, privacidade e código de conduta, com data, hora e IP.</li>
            </ul>

            <h3>Aluno atleta e times</h3>
            <ul>
                <li>Nome, turma/sala, número de camisa e time a que pertence.</li>
                <li>Desempenho esportivo: participação em partidas, gols, cartões e substituições.</li>
                <li>Quando o aluno cria uma conta de consulta: nome de usuário, senha (armazenada apenas como <em>hash</em>) e, opcionalmente, foto de perfil.</li>
            </ul>
            <p>
                <strong>Não coletamos de alunos</strong> endereço residencial, documentos de identificação,
                dados de saúde, biometria, origem racial, convicção religiosa ou qualquer outro dado
                classificado como sensível pelo art. 5º, II da LGPD.
            </p>

            <h3>Visitante do site</h3>
            <ul>
                <li>Registro de consentimento de cookies: finalidade aceita ou recusada, versão do texto, data, IP e navegador.</li>
                <li>Dados que você mesmo enviar pelo formulário de contato.</li>
            </ul>
        </section>

        <section id="finalidades">
            <h2><span class="num">04</span> Para que usamos e com qual base legal</h2>
            <p>
                A LGPD exige que todo tratamento tenha uma base legal declarada. Não usamos
                "consentimento genérico" como carta branca — cada finalidade tem a sua.
            </p>
            <div class="data-table-wrap">
                <table class="data">
                    <thead><tr><th>Finalidade</th><th style="width:230px">Base legal (LGPD)</th><th style="width:150px">Dados usados</th></tr></thead>
                    <tbody>
                        <tr><td>Criar e manter contas de acesso</td><td>Execução de contrato — art. 7º, V</td><td>Cadastro e credenciais</td></tr>
                        <tr><td>Operar o campeonato: jogos, súmulas e classificação</td><td>Execução de contrato — art. 7º, V</td><td>Times, jogadores, partidas</td></tr>
                        <tr><td>Analisar o credenciamento de árbitros</td><td>Execução de contrato e procedimentos preliminares — art. 7º, V</td><td>Qualificação e documento</td></tr>
                        <tr><td>Emitir nota fiscal e cumprir obrigações fiscais</td><td>Obrigação legal — art. 7º, II</td><td>Dados da instituição</td></tr>
                        <tr><td>Prevenir fraude e garantir segurança do acesso</td><td>Legítimo interesse — art. 7º, IX</td><td>Logs, IP e auditoria</td></tr>
                        <tr><td>Cookies analíticos e métricas de uso</td><td>Consentimento — art. 7º, I</td><td>Dados de navegação</td></tr>
                        <tr><td>Envio de novidades e comunicados</td><td>Consentimento — art. 7º, I</td><td>Nome e e-mail</td></tr>
                        <tr><td>Responder solicitações de titulares</td><td>Obrigação legal — art. 7º, II c/c art. 18</td><td>Dados do pedido</td></tr>
                        <tr><td>Defesa em processo judicial ou administrativo</td><td>Exercício regular de direitos — art. 7º, VI</td><td>Registros pertinentes</td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                Quando a base legal for o <strong>consentimento</strong>, você pode revogá-lo a qualquer
                momento, sem custo e sem justificativa — e isso não afeta a legalidade do que foi tratado
                antes da revogação.
            </p>
        </section>

        <section id="criancas">
            <h2><span class="num">05</span> Dados de crianças e adolescentes</h2>
            <p>
                Campeonatos interclasse envolvem, por natureza, menores de idade. O art. 14 da LGPD
                exige que esses dados sejam tratados sempre no <strong>melhor interesse</strong> da
                criança e do adolescente, e nós levamos isso a sério em cada decisão de produto.
            </p>
            <ul>
                <li><strong>Coleta mínima.</strong> De um aluno atleta guardamos essencialmente nome, turma e desempenho em quadra. Nada mais é necessário para organizar um campeonato.</li>
                <li><strong>Sem publicidade.</strong> Dados de alunos nunca são usados para publicidade, criação de perfil comportamental ou compartilhamento com terceiros com fins comerciais.</li>
                <li><strong>Consentimento dos responsáveis.</strong> O tratamento de dados de crianças (até 12 anos incompletos) depende de consentimento específico e destacado de ao menos um dos pais ou responsável legal, colhido pela escola no momento da inscrição no campeonato.</li>
                <li><strong>Visibilidade contida.</strong> Placares e classificação são públicos para a comunidade escolar; dados cadastrais não são. Nenhum perfil de aluno é indexado por buscadores.</li>
                <li><strong>Foto é opcional.</strong> A foto de perfil nunca é obrigatória e pode ser removida a qualquer momento pelo próprio aluno ou pela escola.</li>
            </ul>
            <div class="callout">
                <i class="fas fa-user-shield"></i>
                <div>
                    <strong>É responsável por um aluno?</strong>
                    <p>Você pode solicitar acesso, correção ou eliminação dos dados da criança ou adolescente sob sua responsabilidade pelo <a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD</a>, indicando o vínculo no formulário.</p>
                </div>
            </div>
        </section>

        <section id="compartilhamento">
            <h2><span class="num">06</span> Com quem compartilhamos</h2>
            <p><strong>Nós não vendemos dados pessoais.</strong> O compartilhamento acontece apenas nas situações abaixo, sempre limitado ao necessário:</p>
            <ul>
                <li><strong>Com a escola contratante</strong> — que é a controladora dos dados do campeonato e precisa deles para conduzir a competição.</li>
                <li><strong>Com prestadores de serviço</strong> — hospedagem, envio de e-mail e processamento de pagamento, todos contratualmente obrigados a tratar os dados apenas conforme nossas instruções.</li>
                <li><strong>Com autoridades públicas</strong> — mediante requisição legal, ordem judicial ou determinação da ANPD.</li>
                <li><strong>Em reorganização societária</strong> — em caso de fusão ou aquisição, com comunicação prévia aos titulares e manutenção das mesmas garantias.</li>
            </ul>
            <p>
                Dentro da plataforma, a visibilidade é controlada por perfil: o árbitro vê apenas as
                partidas designadas a ele; o aluno vê jogos, placares e classificação, sem acesso a
                dados cadastrais de terceiros.
            </p>
        </section>

        <section id="cookies">
            <h2><span class="num">07</span> Cookies e tecnologias similares</h2>
            <p>
                Usamos dois grupos de cookies. Os <strong>necessários</strong> mantêm sua sessão ativa
                e protegem os formulários contra fraude — sem eles a plataforma não funciona, e por
                isso não dependem de consentimento. Os <strong>analíticos</strong> só são ativados se
                você aceitar no banner.
            </p>
            <p>
                O detalhamento de cada cookie, sua finalidade e seu prazo de validade está na
                <a href="<?= e(sh_url('cookies.php')) ?>">Política de Cookies</a>. Você pode
                <a href="#" data-abrir-cookies>rever suas escolhas a qualquer momento</a>.
            </p>
        </section>

        <section id="seguranca">
            <h2><span class="num">08</span> Segurança da informação</h2>
            <p>Adotamos medidas técnicas e administrativas proporcionais ao risco do tratamento, entre elas:</p>
            <ul>
                <li>Senhas armazenadas exclusivamente como <em>hash</em> criptográfico — nem a nossa equipe consegue lê-las.</li>
                <li>Controle de acesso por perfil, com o princípio do menor privilégio.</li>
                <li>Proteção contra injeção de SQL por meio de consultas parametrizadas e contra falsificação de requisição (CSRF) em todos os formulários.</li>
                <li>Documentos de credenciamento em área restrita, sem acesso direto pela web e servidos apenas a usuários autenticados.</li>
                <li>Trilha de auditoria de ações sensíveis, com autor, data e endereço IP.</li>
                <li>Backups periódicos e restrição de acesso à base de dados.</li>
            </ul>
            <p>
                Nenhum sistema é infalível. Se identificar uma vulnerabilidade, escreva para
                <a href="mailto:<?= e(SH_EMAIL_DPO) ?>"><?= e(SH_EMAIL_DPO) ?></a> — analisamos toda
                comunicação responsável e não tomamos medidas contra quem reporta de boa-fé.
            </p>
        </section>

        <section id="retencao">
            <h2><span class="num">09</span> Por quanto tempo guardamos</h2>
            <div class="data-table-wrap">
                <table class="data">
                    <thead><tr><th>Categoria</th><th style="width:220px">Prazo de guarda</th><th>Motivo</th></tr></thead>
                    <tbody>
                        <tr><td>Dados de conta ativa</td><td>Enquanto durar a assinatura</td><td>Execução do contrato</td></tr>
                        <tr><td>Histórico de campeonatos</td><td>Conforme o plano contratado</td><td>Memória esportiva da escola</td></tr>
                        <tr><td>Documento de credenciamento recusado</td><td>Eliminado em até 30 dias</td><td>Finalidade encerrada</td></tr>
                        <tr><td>Documento de credenciamento aprovado</td><td>Enquanto a credencial estiver ativa</td><td>Comprovação de habilitação</td></tr>
                        <tr><td>Registros fiscais da assinatura</td><td>5 anos</td><td>Obrigação legal tributária</td></tr>
                        <tr><td>Logs de acesso e auditoria</td><td>6 meses</td><td>Art. 15 do Marco Civil da Internet</td></tr>
                        <tr><td>Registros de consentimento</td><td>5 anos após a revogação</td><td>Prova de conformidade (art. 8º, §1º)</td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                Encerrado o prazo, os dados são eliminados ou anonimizados de forma irreversível. Dados
                anonimizados — como estatísticas agregadas de uso da plataforma — deixam de ser dados
                pessoais e podem ser mantidos indefinidamente.
            </p>
        </section>

        <section id="direitos">
            <h2><span class="num">10</span> Seus direitos como titular</h2>
            <p>O art. 18 da LGPD garante a você, gratuitamente e a qualquer momento:</p>
            <ol>
                <li><strong>Confirmação e acesso</strong> — saber se tratamos dados seus e obter cópia deles.</li>
                <li><strong>Correção</strong> — corrigir dados incompletos, inexatos ou desatualizados.</li>
                <li><strong>Anonimização, bloqueio ou eliminação</strong> — de dados desnecessários, excessivos ou tratados em desconformidade com a lei.</li>
                <li><strong>Portabilidade</strong> — receber seus dados em formato estruturado para levá-los a outro fornecedor.</li>
                <li><strong>Eliminação</strong> — apagar dados tratados com base no seu consentimento, ressalvadas as hipóteses de guarda obrigatória.</li>
                <li><strong>Informação sobre compartilhamento</strong> — saber com quais entidades públicas e privadas compartilhamos seus dados.</li>
                <li><strong>Informação sobre a recusa</strong> — ser informado sobre a possibilidade de não consentir e as consequências disso.</li>
                <li><strong>Revogação do consentimento</strong> — voltar atrás em qualquer autorização dada.</li>
                <li><strong>Oposição</strong> — opor-se a tratamento feito com base em legítimo interesse.</li>
                <li><strong>Revisão de decisões automatizadas</strong> — no SportHub, nenhuma decisão que afete você é tomada exclusivamente por algoritmo.</li>
            </ol>
        </section>

        <section id="exercer">
            <h2><span class="num">11</span> Como exercer seus direitos</h2>
            <p>Há dois caminhos, e ambos geram protocolo:</p>
            <ul>
                <li>Pelo <a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD</a>, preenchendo o formulário de requisição do titular.</li>
                <li>Por e-mail, escrevendo para <a href="mailto:<?= e(SH_EMAIL_DPO) ?>"><?= e(SH_EMAIL_DPO) ?></a>.</li>
            </ul>
            <p>
                Respondemos em até <strong>15 dias corridos</strong>, conforme o art. 19, II da LGPD.
                Se o pedido for complexo, informamos o motivo e um novo prazo. Podemos solicitar
                informação adicional para confirmar sua identidade — é uma proteção contra alguém
                pedir seus dados se passando por você.
            </p>
            <p>
                Se o pedido envolver dados do campeonato inseridos pela escola, encaminhamos à
                instituição controladora e acompanhamos o atendimento até a conclusão.
            </p>
        </section>

        <section id="internacional">
            <h2><span class="num">12</span> Transferência internacional</h2>
            <p>
                A infraestrutura principal do SportHub fica no Brasil. Se algum prestador de serviço
                exigir processamento no exterior — por exemplo, um provedor de envio de e-mail —,
                a transferência só ocorre para países com grau de proteção adequado ou mediante
                cláusulas contratuais específicas, na forma dos arts. 33 a 36 da LGPD.
            </p>
        </section>

        <section id="incidentes">
            <h2><span class="num">13</span> Incidentes de segurança</h2>
            <p>
                Se ocorrer um incidente que possa acarretar risco ou dano relevante aos titulares,
                comunicaremos a <strong>ANPD</strong> e os titulares afetados em prazo razoável,
                informando: a natureza dos dados envolvidos, os titulares atingidos, as medidas
                técnicas adotadas, os riscos identificados e as providências em curso — conforme
                exige o art. 48 da LGPD.
            </p>
        </section>

        <section id="alteracoes">
            <h2><span class="num">14</span> Alterações desta política</h2>
            <p>
                Esta política pode ser atualizada para refletir mudanças na plataforma ou na legislação.
                Toda versão recebe um número e uma data de vigência, exibidos no topo desta página.
                Alterações relevantes são comunicadas por e-mail às escolas contratantes e por aviso
                dentro da plataforma, com <strong>30 dias</strong> de antecedência quando exigirem
                novo consentimento.
            </p>
            <p>Versão atual: <strong><?= e(SH_VERSAO_POLITICA) ?></strong>, vigente desde <?= e(SH_POLITICA_DATA) ?>.</p>
        </section>

        <section id="contato">
            <h2><span class="num">15</span> Fale com o encarregado</h2>
            <p>
                O <strong>Encarregado pelo Tratamento de Dados Pessoais (DPO)</strong> é o canal oficial
                entre você, o SportHub e a ANPD, conforme o art. 41 da LGPD.
            </p>
            <div class="data-table-wrap">
                <table class="data">
                    <tbody>
                        <tr><td style="width:200px;color:var(--muted)">E-mail do encarregado</td><td><a href="mailto:<?= e(SH_EMAIL_DPO) ?>"><?= e(SH_EMAIL_DPO) ?></a></td></tr>
                        <tr><td style="color:var(--muted)">Canal com protocolo</td><td><a href="<?= e(sh_url('lgpd.php')) ?>">Portal LGPD do SportHub</a></td></tr>
                        <tr><td style="color:var(--muted)">Atendimento geral</td><td><a href="mailto:<?= e(SH_EMAIL) ?>"><?= e(SH_EMAIL) ?></a></td></tr>
                        <tr><td style="color:var(--muted)">Prazo de resposta</td><td>Até 15 dias corridos</td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                Se não ficar satisfeito com a nossa resposta, você pode apresentar reclamação à
                <strong>Autoridade Nacional de Proteção de Dados (ANPD)</strong> pelos canais oficiais
                do órgão.
            </p>

            <div class="btn-group mt-3">
                <a href="<?= e(sh_url('lgpd.php')) ?>" class="btn btn-primary">Fazer uma solicitação <i class="fas fa-arrow-right"></i></a>
                <a href="<?= e(sh_url('termos.php')) ?>" class="btn btn-outline">Ler os Termos de Uso</a>
            </div>
        </section>

    </article>
</div>

<?php include __DIR__ . '/includes/site_footer.php'; ?>
