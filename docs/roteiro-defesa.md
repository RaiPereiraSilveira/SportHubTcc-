# Roteiro da defesa — slides e fala

> Cartão SH-74. Estrutura de 14 slides para 20 minutos de apresentação,
> com o que dizer em cada um e o que **não** dizer.
>
> Regra que vale para todos: **um slide, uma ideia.** O slide é apoio; quem
> apresenta é você. Se o slide tem parágrafo, a banca lê em vez de ouvir.

---

## Antes

- [ ] Rodar `scripts\backup.bat` — se algo quebrar no dia, você restaura.
- [ ] Rodar `C:\xampp\php\php.exe tests\executar.php` e **tirar print** dos
      156 testes passando. Serve de prova se a demonstração ao vivo falhar.
- [ ] Percorrer `docs/roteiro-teste-aceitacao.md` inteiro.
- [ ] Deixar o banco com dados **plausíveis**: nomes de turma reais
      ("9º Ano A"), 3 modalidades, uns 12 jogos, metade encerrada. Banco vazio
      não demonstra nada; banco com "teste123" desmerece o trabalho.
- [ ] Ter o 2FA já ativado numa conta e o celular com o autenticador na mão.
- [ ] Abrir as abas na ordem da demonstração **antes** de começar.
- [ ] Salvar uma súmula em PDF no desktop, como reserva.

---

## Slide 1 — Capa

Título, seu nome, orientador, instituição, ano. Logo do SportHub.

**Fale (20 s):** apresente-se e diga a frase que resume o trabalho — *"um
sistema web para escolas organizarem campeonatos interclasse do início ao fim,
com tratamento de dados pessoais em conformidade com a LGPD."*

---

## Slide 2 — O problema

Uma foto ou ilustração: planilha, cartaz no mural, grupo de mensagens.

**Fale (1 min):** o interclasse existe em quase toda escola e é organizado com
o que estiver à mão. Cite dois problemas concretos, não abstratos:

- tabela de classificação somada à mão, com erro descoberto na rodada seguinte;
- mesmo árbitro escalado para dois jogos no mesmo horário, descoberto no dia.

**Não diga** "não existe sistema para isso". Existe. Diga que os existentes são
caros ou feitos para federação, não para escola.

---

## Slide 3 — Por que virou também um problema de LGPD

Três caixas: **nome do aluno**, **CPF do árbitro**, **foto da partida**.
Embaixo: *Lei 13.709/2018, art. 14 — dado de criança e adolescente.*

**Fale (1 min):** organizar interclasse é tratar dado pessoal de menor de
idade. Isso deslocou a conformidade de "seria bom ter" para requisito
estruturante, presente desde a modelagem do banco. É o diferencial do
trabalho — sustente-o.

---

## Slide 4 — Os três perfis

Diagrama simples: Coordenação, Árbitro, Aluno — e o que cada um enxerga.

**Fale (1 min):** o desenho de permissões não é decorativo. O árbitro vê
**apenas** as partidas dele; o aluno não vê registro disciplinar nem súmula em
PDF. Diga que isso é verificado no servidor, não escondido na tela.

---

## Slide 5 — Arquitetura

Diagrama de camadas: navegador → PHP 8 → MySQL. Ao lado, a lista de módulos.

**Fale (1 min):** PHP e MySQL sem framework, porque o alvo é hospedagem
compartilhada barata e XAMPP — os dois ambientes onde Composer e `vendor/` não
existem. Diga que a escolha teve custo e que você o assumiu (o slide 11 volta
a isso).

---

## Slide 6 — Método

O quadro Kanban, com as oito listas. Destaque **84 cartões** e o limite de
4 em "Em progresso".

**Fale (1,5 min):** três pontos:

1. sprints de duas semanas, quadro com código estável por cartão (`SH-38`), e
   esse código aparece nos comentários do código-fonte — é a rastreabilidade
   entre decisão e linha;
2. o limite de trabalho em progresso existe porque, sozinho, a tentação é
   abrir cinco frentes e não fechar nenhuma;
3. a lista "Bloqueado" é de primeira classe: o que depende de contrato com
   terceiro fica visível em vez de sumir no backlog.

---

## Slide 7 — DEMONSTRAÇÃO (a parte que importa)

Slide só com a palavra **Demonstração**. O resto é a tela.

**8 minutos, nesta ordem, sem improviso:**

1. **Landing page** — 15 s. Só para mostrar que existe um site.
2. **Entrar como coordenação** — mostre a exigência de trocar a senha de
   fábrica. *"O sistema não deixa ficar no ar com admin1234."*
3. **Regras do campeonato** — mude a vitória de 3 para 2 pontos e mostre a
   prévia da tabela mudando na hora. Volte para 3.
4. **Escala de arbitragem** — escale um árbitro. Depois tente escalar **o
   mesmo árbitro no mesmo horário**: o sistema recusa e diz qual é o conflito.
   *Este é o momento mais convincente da demonstração.*
5. **Chaveamento** — gere a chave, mostre a árvore.
6. **Entrar como árbitro** (outra aba, já aberta) — preencha a súmula com dois
   gols nomeados. Gere o **PDF** e abra.
7. **Entrar como aluno** — classificação, e em **Atletas** mostre que os gols
   lançados há 30 segundos já estão na artilharia.
8. **Segundo fator** — saia, entre de novo e mostre o código do celular.

**Se algo travar:** não tente consertar ao vivo. Diga "vou seguir pelo material
gravado" e vá para o vídeo. Ensaie essa frase.

---

## Slide 8 — LGPD na prática

Tabela de três colunas: **exigência da lei** · **onde está implementada** ·
**como se verifica**. Quatro linhas bastam.

**Fale (1,5 min):** escolha **uma** decisão e explique bem — a melhor é
anonimizar em vez de apagar:

> *"Apagar a conta do árbitro destruiria as súmulas que ele assinou, que
> interessam a todos os outros times. Então o sistema despersonaliza: tira
> nome, e-mail, CPF, telefone e foto, e mantém a referência. O art. 16 autoriza
> conservar para cumprir obrigação legal, e o art. 12 diz que dado anonimizado
> deixa de ser dado pessoal."*

---

## Slide 9 — Segurança

Antes/depois do cabeçalho CSP: `script-src 'self' 'unsafe-inline'` riscado,
`script-src 'self' 'nonce-...'` no lugar.

**Fale (1,5 min):** enquanto a política aceitava script embutido, ela permitia
justamente o que deveria bloquear. Tirar isso exigiu converter **174 estilos e
manipuladores de evento embutidos, em 25 arquivos**, para classes CSS e
atributos declarativos.

Mencione o resto em uma frase: senha em bcrypt, CSRF em todo formulário,
consultas parametrizadas, sessão endurecida, trava de força bruta por IP.

---

## Slide 10 — Testes

Print do executor: **156 de 156 verificações passaram**.

**Fale (1,5 min):** o critério de seleção é o argumento:

> *"Automatizei o que falha em silêncio. Um botão quebrado aparece no primeiro
> uso. Um dígito verificador de CPF errado, um código TOTP fora de sincronia
> ou um desempate na ordem trocada não avisam nada — produzem um resultado
> plausível e errado."*

Diga que os códigos TOTP são conferidos contra os vetores do RFC 6238 e o CRC
do Pix contra o vetor canônico. Testar contra a própria saída só confirmaria
consistência com o próprio erro.

**Termine com o achado:** a suíte revelou que a ordenação usava `strcmp`, que
compara bytes — e em UTF-8 todo nome acentuado começa com byte alto. "Águias" e
"Leões do 9ºA" apareciam **depois de "Zulu"**. Estava em produção e nenhuma
inspeção visual tinha pegado.

> É o slide que mais impressiona banca. Não corra nele.

---

## Slide 11 — O que ficou de fora, e por quê

Quatro itens, com o motivo ao lado:

| Item | Por que não |
|---|---|
| Gateway de pagamento | Exige conta aprovada em adquirente e webhook público |
| Notificação push | Exige servidor VAPID e HTTPS |
| Validação com usuários reais | O sistema não rodou um interclasse de verdade |
| Teste automatizado de tela | Exigiria outra pilha inteira |

**Fale (1 min):** o código da integração existe e está pronto; falta a
credencial. Diga isso com naturalidade — banca respeita limite reconhecido
muito mais do que limite escondido, e **vai perguntar de qualquer forma**.

A ausência de validação com usuário real é a limitação mais séria. Assuma-a
antes de perguntarem.

---

## Slide 12 — Números

Seis números grandes, sem parágrafo:

- **84** cartões no quadro
- **156** verificações automatizadas
- **25** tabelas no banco
- **3** perfis de acesso
- **8** exigências da LGPD implementadas
- **0** dependências externas

---

## Slide 13 — Trabalhos futuros

Quatro itens, curtos: aplicar num interclasse real e medir; integrar gateway;
notificação push; multi-escola em produção.

---

## Slide 14 — Encerramento

"Obrigado" + seus contatos + endereço do repositório.

---

## Perguntas que a banca faz

**"Por que não usou um framework?"**
Hospedagem compartilhada e XAMPP não têm Composer nem permitem apontar o
*document root* para `public/`. E implementar CSRF, sessão e CSP à mão obrigou
a entender cada mecanismo. O custo foi real: a CSP ficou incompleta por três
sprints.

**"Isso aguenta uma escola inteira?"**
Não foi medido sob carga — está nas limitações. O que foi feito: índices nas
colunas de busca, paginação em todas as listas, e a classificação numa
consulta agregada só. Um interclasse de escola tem dezenas de times e centenas
de partidas por ano, não milhões de linhas.

**"Como garante que o aluno não vê dado de outro aluno?"**
A verificação de perfil roda **antes de qualquer saída HTML** — se o cabeçalho
fosse impresso primeiro, o redirecionamento não teria efeito e a página
continuaria visível. Na súmula, o `arbitro_id` entra no `WHERE` do `UPDATE`.
E o roteiro de aceitação tem uma seção só para tentar acessar o que não pode.

**"E se o servidor for invadido?"**
O script `usuario_banco.sql` cria um usuário de banco que só faz SELECT,
INSERT, UPDATE e DELETE no banco do projeto — sem DROP, sem GRANT, sem acesso
a outros bancos. Uploads não executam código. Senhas em bcrypt. O estrago fica
contido.

**"Por que escreveu o gerador de QR Code à mão?"**
As alternativas eram carregar uma biblioteca de CDN — que a própria CSP do
projeto bloqueia — ou usar a API de gráficos do Google, o que significaria
**enviar o segredo do segundo fator a um terceiro**, anulando exatamente o que
a funcionalidade protege.

**"Qual foi a parte mais difícil?"**
Responda com honestidade e com um exemplo concreto. Uma boa resposta: tirar o
`'unsafe-inline'` da CSP, porque não era escrever código novo — era encontrar
e converter 174 ocorrências espalhadas por 25 arquivos sem quebrar nenhuma
tela, e depois provar que não tinha quebrado.
