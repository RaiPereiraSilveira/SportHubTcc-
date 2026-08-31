# Metodologia

> Capítulo de metodologia do TCC (cartão SH-47).
> Texto-base para a monografia: os dados citados vêm do quadro do projeto e do
> próprio repositório, e podem ser conferidos um a um.

---

## 1. Natureza da pesquisa

Este trabalho é uma **pesquisa aplicada de natureza tecnológica**, com
abordagem **qualitativa** e objetivo **descritivo-construtivo**. Não se
propõe a testar uma hipótese estatística; propõe-se a construir um artefato de
software funcional para um problema concreto — a organização de campeonatos
interclasse em escolas de ensino básico — e a descrever, com fundamentação, as
decisões técnicas tomadas durante essa construção.

O procedimento é o do **Design Science Research** (Hevner et al., 2004): a
pesquisa produz um artefato, o artefato é avaliado contra os requisitos que o
motivaram, e o conhecimento gerado está tanto no artefato quanto no registro
das decisões que levaram a ele. É por isso que o histórico do quadro de tarefas
e os comentários no código-fonte são tratados aqui como dado de pesquisa, e não
como documentação acessória.

---

## 2. Delimitação do problema

O interclasse é uma atividade recorrente na educação básica brasileira e, na
maioria das escolas, é organizado com as ferramentas que estiverem à mão:
planilha, grupo de mensagens, cartaz no mural e caderno do professor de
Educação Física.

Os problemas observados nesse arranjo, e que delimitaram o escopo do sistema:

| Problema | Consequência prática |
|---|---|
| Tabela de classificação calculada à mão | Erro de soma descoberto na rodada seguinte; contestação de resultado |
| Súmula em papel | Extravio; placar divergente entre a súmula e o cartaz |
| Escala de arbitragem em planilha | Mesmo árbitro marcado em dois jogos no mesmo horário |
| Divulgação por grupo de mensagens | Aluno que entra depois não encontra a informação |
| Dados pessoais em planilha compartilhada | Nome, CPF e documento de árbitro sem controle de acesso |

O último item deixou de ser detalhe em 2020, com a entrada em vigor da Lei
13.709/2018 (LGPD). Uma escola que organiza interclasse trata dado pessoal de
menores de idade — o que aciona o art. 14 e seu regime reforçado. Isso deslocou
a conformidade legal de "requisito não funcional desejável" para **requisito
estruturante**, presente desde a modelagem do banco.

---

## 3. Método de desenvolvimento

### 3.1 Escolha do processo

Optou-se por um processo **ágil, incremental e iterativo**, inspirado em Scrum,
com adaptações necessárias ao contexto de um TCC:

- **equipe de uma pessoa**, o que elimina reuniões de sincronização e torna a
  revisão por pares parcialmente inviável (contornada por uma lista de
  verificação e por revisão diferida, descrita em 3.4);
- **sprints de duas semanas**, delimitadas pelo calendário de orientação;
- **quadro Kanban** como artefato central de planejamento e rastreabilidade.

A escolha do processo ágil sobre o modelo em cascata foi deliberada. O escopo
de um sistema de campeonato parece estável à primeira vista, mas cada
funcionalidade entregue revelou requisitos que não estavam no levantamento
inicial. Dois exemplos documentados no quadro:

- ao implementar a súmula digital (SH-11), ficou claro que registrar apenas o
  placar era insuficiente: sem os eventos por jogador não havia artilharia,
  fair play nem base para o registro disciplinar — o que originou os cartões
  SH-67 e SH-73, inexistentes no planejamento inicial;
- ao implementar os limites de plano (SH-57), descobriu-se que a coluna de
  limite existia na tabela `planos` desde a primeira modelagem e **nunca havia
  sido verificada em lugar nenhum** — uma funcionalidade que existia no
  banco e não existia no sistema.

Em cascata, ambos apareceriam apenas na fase de testes.

### 3.2 O quadro Kanban como instrumento

O quadro do projeto tem **84 cartões**, numerados de `SH-01` a `SH-84`,
distribuídos em oito listas (Backlog, Refinados, Sprint Backlog, Em progresso,
Revisão de código, Bloqueado, Concluído e Arquivo). Cada cartão carrega:

- um **código estável**, que é citado nos comentários do código-fonte e nas
  mensagens de commit — é o que liga a decisão registrada no quadro à linha
  que a implementa;
- uma **etiqueta de dificuldade** (Fácil, Média, Difícil), atribuída antes da
  execução e usada para calibrar a estimativa das sprints seguintes;
- uma ou mais **etiquetas de área** (Back-end, Front-end, Banco de dados,
  Segurança & LGPD, Infra/Deploy, Testes/QA, Documentação).

Duas regras deram ao quadro valor metodológico além do organizacional:

**Limite de trabalho em progresso.** A lista "Em progresso" aceita no máximo
quatro cartões. O limite não é burocracia: com uma pessoa só, a tentação de
abrir cinco frentes e não fechar nenhuma é a principal causa de sprint sem
entrega. O limite força o fechamento antes da abertura.

**A lista "Bloqueado" é uma lista de primeira classe.** Cartões que dependem de
decisão ou contratação externa — gateway de pagamento, servidor de e-mail,
hospedagem com HTTPS, dados jurídicos reais do controlador — ficam visíveis em
vez de dissolvidos no backlog. Isso evita duas distorções comuns em TCC:
apresentar como concluído o que não está, e apresentar como falha do
desenvolvedor o que é dependência de terceiro.

### 3.3 Definição de pronto

Um cartão só sai de "Em progresso" quando satisfaz, cumulativamente:

1. a funcionalidade opera nos três perfis a que se aplica (coordenação,
   arbitragem, aluno);
2. toda entrada de usuário é validada **no servidor**, não apenas no HTML;
3. toda consulta ao banco é parametrizada e toda saída é escapada;
4. o comportamento em caso de erro é definido e registrado no `error_log`;
5. a decisão técnica não óbvia está comentada no código, explicando **por que**
   e não o que;
6. quando há dado pessoal envolvido, a base legal e a finalidade estão
   declaradas.

O critério 5 é o que sustenta este capítulo. O código do projeto é comentado
em português e no nível da decisão — por exemplo, `includes/qrcode.php` abre
explicando por que o gerador de QR Code foi escrito à mão em vez de usar uma
biblioteca ou a API de gráficos do Google (a segunda opção implicaria enviar o
segredo do segundo fator a um terceiro, anulando a própria funcionalidade).

### 3.4 Revisão de código sem par

A ausência de um segundo desenvolvedor foi tratada por **revisão diferida**: a
lista "Revisão de código" do quadro retém o cartão por, no mínimo, uma sprint
antes da conclusão. A releitura com distância temporal encontrou defeitos reais
que a leitura imediata não encontrou. Casos documentados:

- **SH-40** — a consulta da classificação contava vitórias e empates sem
  filtrar pelo status da partida. Como os placares nascem em zero, todo jogo
  apenas agendado entrava na conta como empate para os dois times, e a tabela
  exibia pontos de partidas que ainda não tinham acontecido;
- **SH-77** — o nome do time vencedor era impresso sem escape numa das telas,
  configurando XSS armazenado;
- **SH-78** — a diretiva `ErrorDocument` apontava para o nome antigo da pasta
  do projeto, e o servidor devolvia um 404 de corpo vazio em vez da página de
  erro do sistema.

### 3.5 Testes

A verificação combina três instrumentos, escolhidos por custo-benefício:

**Testes automatizados de funções puras** (`tests/`, cartão SH-63). Um
executor próprio, sem dependência de Composer, exercita **156 verificações**
sobre as funções que erram em silêncio: validação de CPF, política de senha,
escape de curingas do LIKE, geração de código TOTP, correção de erro
Reed-Solomon do QR Code, CRC do Pix, integridade estrutural do PDF gerado e
ordem dos critérios de desempate.

O critério de seleção foi explícito: automatizar o que **falha sem avisar**.
Um dígito verificador errado, um código TOTP fora de sincronia ou uma tabela
de classificação com o desempate na ordem trocada não emitem erro nenhum —
produzem um resultado plausível e incorreto. Já um botão que não abre a página
é encontrado no primeiro uso, e automatizar sua verificação exigiria uma pilha
de testes de navegador desproporcional ao projeto.

Os testes usam **vetores de referência externos** sempre que existem: os
códigos TOTP são conferidos contra os valores publicados no apêndice B do
RFC 6238, e o CRC do Pix contra o vetor canônico do CRC-16/CCITT-FALSE. Testar
contra a própria saída do sistema apenas confirmaria que ele é consistente com
o próprio erro.

Um achado do processo merece registro: a suíte, ao ser escrita, revelou que a
ordenação alfabética usava `strcmp`, que compara bytes. Em UTF-8, todo nome
acentuado começa por um byte alto — de modo que "Águias" e "Leões do 9ºA"
apareciam **depois** de "Zulu" na tabela. Num interclasse brasileiro, isso é a
regra e não a exceção. O defeito estava em produção e não havia sido percebido
em nenhuma inspeção visual.

**Teste de aceitação por perfil** (SH-53). Roteiro manual, em
`docs/roteiro-teste-aceitacao.md`, percorrido antes de cada entrega, com um
caminho completo para cada um dos três perfis.

**Verificação de segurança dirigida.** Lista de verificação aplicada a cada
tela que recebe entrada: injeção de SQL, XSS refletido e armazenado, CSRF,
falha de autorização por acesso direto à URL e exposição de dado pessoal a
perfil não autorizado.

---

## 4. Tecnologias e justificativa das escolhas

| Camada | Escolha | Justificativa |
|---|---|---|
| Linguagem | PHP 8 | Disponível em qualquer hospedagem compartilhada de baixo custo, que é o cenário real de uma escola pública |
| Banco | MySQL/MariaDB | Mesma razão; e o modelo relacional é adequado a dados fortemente estruturados como partida, time e súmula |
| Interface | HTML, CSS e JavaScript sem framework | Ver 4.1 |
| Ambiente | XAMPP | Reprodutível na máquina de qualquer avaliador sem etapa de instalação |

### 4.1 A decisão de não usar framework

É a escolha mais questionável do projeto e por isso merece justificativa
explícita.

**A favor de um framework** (Laravel, Symfony): ORM, migrações, roteamento,
proteção contra CSRF e escape automático de saída viriam prontos e testados.

**Contra**: um framework moderno exige Composer, um diretório `vendor/` de
dezenas de megabytes e uma configuração de servidor que aponte o *document
root* para uma subpasta `public/`. Nenhuma dessas três condições é satisfeita
por hospedagem compartilhada barata nem por um XAMPP recém-instalado — que é
exatamente o ambiente da escola-alvo e o do avaliador deste trabalho.

Há ainda uma razão pedagógica: implementar à mão o token CSRF, o
endurecimento da sessão, a política de segurança de conteúdo e o hash de senha
obriga a **entender** cada mecanismo. Um `@csrf` no template protege sem
ensinar.

O custo dessa escolha foi assumido e é mensurável: cada proteção precisou ser
implementada, e uma delas ficou incompleta por três sprints — a política de
segurança de conteúdo dependia de `'unsafe-inline'` para funcionar, o que
anulava boa parte de sua utilidade. A correção (SH-37) exigiu converter todos
os estilos e manipuladores de evento embutidos do projeto para classes CSS e
atributos declarativos: **174 substituições em 25 arquivos**. Um framework
teria evitado esse débito.

### 4.2 Componentes escritos à mão

Três componentes foram implementados do zero em vez de importados, e a
justificativa é a mesma em todos: a dependência custaria mais do que o
componente.

| Componente | Arquivo | Linhas | Alternativa descartada |
|---|---|---|---|
| Cliente SMTP | `includes/email.php` | ~200 | PHPMailer inteiro para dois formulários |
| Gerador de QR Code | `includes/qrcode.php` | ~300 | Biblioteca via CDN (bloqueada pela CSP) ou API do Google (enviaria o segredo do 2FA a terceiro) |
| Escritor de PDF | `includes/pdf.php` | ~250 | TCPDF/FPDF para um documento de uma página |

---

## 5. Conformidade legal como requisito de projeto

A LGPD foi tratada como requisito funcional, com implementação verificável, e
não como texto de política publicado no rodapé.

| Exigência legal | Implementação | Verificação |
|---|---|---|
| Base legal por finalidade (art. 7º) | `privacidade.php`, seção 4 | Inspeção documental |
| Consentimento comprovável (art. 8º, §1º) | Tabela `lgpd_consentimentos` com data, versão do texto, IP e agente | Consulta ao banco |
| Direitos do titular (art. 18) | `lgpd.php` e `admin/contas_lgpd.php` | Roteiro de aceitação |
| Prazo de 15 dias (art. 19, II) | Campo `prazo_em` com alerta no painel | Roteiro de aceitação |
| Proteção reforçada a menores (art. 14) | Coleta mínima; galeria exige consentimento específico | Inspeção de código |
| Prestação de contas (art. 6º, X) | Tabela `auditoria` | Consulta ao banco |
| Eliminação e anonimização (arts. 12 e 16) | `includes/lgpd_conta.php` | Roteiro de aceitação |
| Encarregado identificado (art. 41) | Constantes de configuração local | Inspeção documental |

A decisão de projeto mais relevante desse conjunto é a **anonimização em vez
de exclusão**. Apagar a conta de um árbitro destruiria a integridade das
súmulas que ele assinou — documentos que interessam a todos os outros
participantes, e não apenas a quem pediu a exclusão. A implementação
despersonaliza a conta (remove nome, e-mail, CPF, telefone e foto, e substitui
a senha por valor aleatório descartado) e preserva a referência, amparada no
art. 16, que autoriza a conservação para cumprimento de obrigação legal, e no
art. 12, segundo o qual dado anonimizado deixa de ser dado pessoal.

---

## 6. Limitações do método

Registradas por honestidade metodológica:

1. **Ausência de validação com usuários reais.** O sistema não foi aplicado a
   um interclasse em andamento. Os requisitos vieram de observação do processo
   manual e de literatura, não de entrevista sistemática. É a limitação mais
   séria do trabalho.
2. **Equipe de uma pessoa.** A revisão diferida mitiga, mas não substitui, a
   revisão por par: quem escreveu o código carrega as mesmas premissas ao
   relê-lo.
3. **Cobertura de teste parcial.** A automação cobre funções puras. Fluxos que
   dependem do banco e da sessão são verificados manualmente, o que os torna
   sujeitos a esquecimento.
4. **Ambiente de execução local.** Comportamento sob concorrência real,
   latência de rede e carga simultânea não foi medido.
5. **Dependências externas não contratadas.** Gateway de pagamento, servidor
   de e-mail em produção e hospedagem com HTTPS permanecem como integração
   preparada e não exercitada em ambiente real.

---

## 7. Referências do capítulo

BRASIL. **Lei nº 13.709, de 14 de agosto de 2018.** Lei Geral de Proteção de
Dados Pessoais (LGPD). Brasília, 2018.

HEVNER, A. R.; MARCH, S. T.; PARK, J.; RAM, S. Design Science in Information
Systems Research. **MIS Quarterly**, v. 28, n. 1, p. 75–105, 2004.

M'RAIHI, D.; MACHANI, S.; PEI, M.; RYDELL, J. **RFC 6238: TOTP — Time-Based
One-Time Password Algorithm.** IETF, 2011.

ISO/IEC. **ISO/IEC 18004: Information technology — Automatic identification and
data capture techniques — QR Code bar code symbology specification.** 2015.

BANCO CENTRAL DO BRASIL. **Manual de Padrões para Iniciação do Pix.** Brasília.

OWASP FOUNDATION. **OWASP Top 10.** Disponível em owasp.org.

W3C. **Content Security Policy Level 3.** W3C Working Draft.

W3C. **Web Content Accessibility Guidelines (WCAG) 2.1.** W3C Recommendation,
2018.
