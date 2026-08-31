# Manual do usuário — SportHub

> Cartão SH-54. Para a coordenação, a arbitragem e os alunos.
> Escrito para quem vai **usar** o sistema, não para quem vai mexer no código.

## Sumário

- [Antes de começar](#antes-de-começar)
- [Para a coordenação](#para-a-coordenação)
- [Para o árbitro](#para-o-árbitro)
- [Para o aluno](#para-o-aluno)
- [Privacidade: o que a escola precisa saber](#privacidade-o-que-a-escola-precisa-saber)
- [Backup e restauração](#backup-e-restauração)
- [Quando algo dá errado](#quando-algo-dá-errado)

---

## Antes de começar

### Como entrar

Abra o endereço do sistema e clique em **Entrar**. Cada pessoa tem um usuário,
e o que você vê depende do seu perfil:

| Perfil | Quem é | O que faz |
|---|---|---|
| **Coordenação** | Professor(a) responsável pelo interclasse | Organiza tudo |
| **Árbitro** | Quem apita as partidas | Preenche a súmula dos jogos dele |
| **Aluno** | Estudante | Consulta jogos, tabela e resultados |

### A primeira senha

Na primeira vez que você entra com uma senha de instalação, o sistema **exige**
que você escolha outra. Não é possível pular essa tela.

A senha precisa ter pelo menos 8 caracteres e misturar letra e número. Não
precisa de símbolo nem de letra maiúscula: senha complicada demais acaba
escrita num papel colado no monitor, o que é pior.

### Esqueci minha senha

Clique em **Esqueci minha senha** na tela de entrada e informe o e-mail do seu
perfil. Chega um link que vale 30 minutos e serve uma vez só.

> Conta sem e-mail cadastrado não consegue recuperar sozinha — procure a
> coordenação. Vale cadastrar o e-mail em **Meu Perfil** antes que precise.

### Proteção em duas etapas (recomendado para a coordenação)

A conta de coordenação vê o campeonato inteiro e dados pessoais de alunos e de
árbitros. Vale protegê-la com um segundo fator: além da senha, entrar passa a
pedir um código de seis dígitos gerado no celular.

1. Menu do seu nome → **Verificação em duas etapas**.
2. Instale um aplicativo autenticador (Google Authenticator, Microsoft
   Authenticator, Authy — qualquer um serve).
3. Aponte a câmera para o QR Code. Sem câmera, digite a chave que aparece
   embaixo dele.
4. Digite o código de seis dígitos que apareceu no aplicativo.

**Guarde os 8 códigos de recuperação que aparecem depois.** Eles não voltam a
ser exibidos. Cada um serve uma vez e substitui o código do aplicativo quando
você estiver sem o celular. Imprima ou salve no gerenciador de senhas — nunca
no mesmo celular que gera os códigos.

> Perdeu o celular e os códigos? Outro administrador desliga o segundo fator
> pela conta dele. Se não houver outro administrador, só com acesso ao banco.

---

## Para a coordenação

A ordem abaixo é a que funciona. Pular etapas cria trabalho depois.

### 1. Modalidades

**Modalidades** → cadastre cada esporte com o gênero (masculino, feminino ou
misto). O gênero define quais times podem ser sorteados nela.

### 2. Times

**Times** → cadastre uma turma por vez: nome do time, sala e gênero.

Em **Escudos**, envie a imagem de cada time. O sistema converte para PNG
automaticamente — pode mandar JPG do celular.

Dentro de cada time, cadastre os jogadores com número de camisa. Número
repetido no mesmo time é recusado. É esse nome que o árbitro seleciona na
súmula e que aparece na artilharia.

### 3. Regras do campeonato

**Gestão → Regras do campeonato.** Aqui você define:

- quantos pontos valem vitória, empate e derrota;
- a ordem dos critérios de desempate;
- quantos minutos dura uma partida.

Os padrões (3, 1, 0, desempate por saldo) servem para futebol. No vôlei, a
vitória costuma valer 2. Em muitos interclasses o desempate é **confronto
direto** antes do saldo.

A prévia da tabela embaixo do formulário mostra o efeito na hora.

> A duração da partida não é enfeite: é a janela que o sistema usa para
> detectar dois jogos no mesmo horário.

### 4. Árbitros

Duas formas:

**Cadastro direto** (**Árbitros**): você cria o usuário e define a senha. Serve
para o professor de Educação Física da própria escola.

**Credenciamento** (**Gestão → Credenciamentos**): o profissional se inscreve
sozinho pelo site, anexa o documento e recebe um protocolo. Você analisa e:

- **Aprova** → o sistema cria o usuário, gera uma senha provisória (mostrada
  uma vez só) e emite a credencial com validade de um ano;
- **Recusa** → o documento anexado é apagado do servidor na hora, porque a
  finalidade do tratamento acabou.

### 5. Confrontos

**Jogos → Gerar Chaveamento**: escolha a modalidade e o sistema cria todos os
confrontos de todos contra todos entre os times compatíveis. Eles nascem **sem
data**.

### 6. Escala: data, hora, local e árbitro

**Gestão → Escala de arbitragem.** Escolha a partida, o árbitro, a data, a hora
e o local. Grave.

O sistema confere antes de gravar:

| Situação | O que acontece |
|---|---|
| O árbitro já tem jogo naquele horário | **Recusa** e diz qual é o outro jogo |
| Um dos times já joga naquele horário | **Recusa** |
| Já há partida no mesmo local e horário | **Avisa** e deixa você confirmar |

O último é só aviso porque "Quadra 1" às vezes é o nome de um ginásio inteiro,
onde cabem duas partidas.

> Partida sem data e hora **não aparece** no calendário nem no telão. A
> própria tela lista quais ainda estão assim.

### 7. Mata-mata

Terminada a fase de grupos, vá em **Chaveamento**:

1. escolha a modalidade e quantos times entram na chave (2, 4, 8, 16 ou 32);
2. **Gerar chaveamento**.

O 1º colocado enfrenta o último classificado, o 2º pega o penúltimo, e assim
por diante. Sobrando vaga, os melhores passam direto (passagem direta).

Em cada confronto, **Agendar partida** cria o jogo de verdade. Quando o árbitro
encerra, o vencedor sobe sozinho para a fase seguinte.

> **Empate no mata-mata não promove ninguém**, de propósito. A regra de
> desempate — prorrogação, pênaltis, melhor campanha — é decisão da escola.
> Depois de decidir, edite o placar da partida e o vencedor sobe.

### 8. Durante o campeonato

- **Indicadores** — o retrato para levar à direção: quanto do calendário saiu,
  participação por turma, modalidade mais disputada. Sem nome de aluno.
- **Registro disciplinar** — advertências, suspensões e providências.
  Visível só para a coordenação.
- **Galeria** — fotos das partidas. Leia a seção de privacidade antes de usar.
- **Comunicações** — o que o sistema tentou enviar por e-mail e se chegou.
- **Cobranças** — o financeiro da assinatura.

### 9. Refazer algo

- **Placar errado** → o árbitro reabre a súmula e corrige, ou a coordenação
  edita a partida.
- **Chave errada** → gerar de novo substitui a anterior. As partidas já jogadas
  continuam no histórico.
- **Time no lugar errado na tabela** → confira as Regras do campeonato; quase
  sempre é a ordem de desempate.

---

## Para o árbitro

### Meus jogos

Ao entrar, você vê **apenas** as partidas designadas a você. Não é possível
abrir a súmula de partida alheia, mesmo digitando o endereço.

### Preencher a súmula

Clique em **Registrar Resultado** na partida.

1. **Placar final** — os dois números.
2. **Eventos** — uma linha por gol, cartão ou substituição, com o nome do
   jogador e o minuto. Use **+ Adicionar Evento** para mais linhas.
3. **Estatísticas** — posse, finalizações, escanteios (opcional).
4. **Observações** — incidentes, atrasos, o que mais precisar registrar.

> O nome do jogador nos eventos é o que alimenta a artilharia e o fair play
> que os alunos veem. Sem o nome, o gol conta no placar e não conta para
> ninguém.

Pode gravar e voltar depois: o que você lançou continua lá, e gravar de novo
não duplica nada.

### Súmula em PDF

Depois de registrar, **Súmula em PDF** gera o documento para imprimir,
assinar e arquivar — com placar, eventos, observações e as linhas de
assinatura do árbitro e da coordenação.

---

## Para o aluno

| Tela | O que mostra |
|---|---|
| **Início** | Próximos jogos e o resumo do campeonato |
| **Jogos** | Calendário completo e o placar ao vivo |
| **Classificação** | A tabela, atualizada a cada resultado |
| **Resultados** | Partidas encerradas |
| **Atletas** | Artilharia e fair play |
| **Mural** | Fotos das partidas |

### Levar o calendário para o celular

Na tela **Jogos**, copie o endereço do calendário e assine no Google Agenda,
no Outlook ou no aplicativo de agenda do celular. Os jogos aparecem junto com
seus compromissos e continuam lá mesmo sem internet.

### Instalar como aplicativo

No celular, o navegador oferece **"Adicionar à tela de início"**. O SportHub
passa a abrir com ícone próprio, sem a barra do navegador.

> Só funciona quando o site está publicado em endereço seguro (`https://`). No
> XAMPP local, o navegador não oferece.

---

## Privacidade: o que a escola precisa saber

O interclasse trata dado pessoal de menores de idade. A LGPD (Lei 13.709/2018)
tem regras específicas para isso, e o sistema foi feito para ajudar a
cumpri-las — mas **quem responde pelos dados é a escola**, não o software.

### Fotos (a parte mais delicada)

O art. 14 exige **consentimento específico e destacado** de um dos pais ou do
responsável legal para tratar dado de criança. Foto é dado pessoal.

Na galeria:

- foto enviada **sem** marcar o consentimento fica **restrita**: só a
  coordenação vê. Continua servindo para o arquivo interno;
- marcar o consentimento registra data, versão do texto e IP — é a comprovação
  que a lei exige. **Só marque se a autorização por escrito estiver
  arquivada na secretaria**;
- toda foto tem prazo de guarda. Vencido o prazo, ela sai do mural sozinha e
  aparece na lista do que já pode ser eliminado;
- evite escrever nome de aluno na legenda: nome ao lado de rosto amplia
  bastante o alcance do dado.

### Pedidos dos titulares

Um aluno, um responsável ou um árbitro pode pedir acesso, correção,
portabilidade ou exclusão dos dados dele em **Portal do titular**, no rodapé
do site. Cada pedido gera protocolo e um **prazo de 15 dias** que o painel
acompanha.

Para executar: **Gestão → Contas e titulares**.

- **Exportar dados (JSON)** — atende ao pedido de acesso e de portabilidade.
- **Anonimizar** — remove nome, e-mail, CPF, telefone e foto, e a conta deixa
  de entrar. As súmulas que a pessoa assinou continuam íntegras, agora sem
  identificar ninguém.
- **Eliminar** — apaga a conta de vez. Só é permitido quando não há súmula nem
  registro de auditoria ligado a ela.

> **Anonimizar não é o mesmo que apagar, e quase sempre é o certo.** Apagar o
> árbitro destruiria a integridade das súmulas dele — documentos que
> interessam a todos os outros times. A lei prevê isso: o art. 16 autoriza
> conservar para cumprir obrigação legal, e o art. 12 diz que dado anonimizado
> deixa de ser dado pessoal.

### Antes de usar de verdade

Preencha os dados do controlador e do encarregado em
`includes/config.local.php`. Enquanto forem os de fábrica, os documentos legais
mostram "a preencher" — que é honesto, mas não serve para valer.

---

## Backup e restauração

**Backup que nunca foi restaurado não é backup.**

### Diário, automático

No Prompt de Comando **como administrador**:

```bash
schtasks /create /tn "SportHub Backup" /tr "C:\xampp\htdocs\sporthub_tcc1\scripts\backup.bat" /sc daily /st 02:00
```

Copia o banco e a pasta `uploads/` para `C:\backups\sporthub`, guarda 30 dias e
escreve o resultado em `backup.log`.

### Manual

```bash
scripts\backup.bat
```

### Teste de restauração (faça uma vez por bimestre)

Restaure num banco **de teste**, nunca no de produção:

```bash
scripts\restaurar.bat C:\backups\sporthub\2026-08-26_0200 olimpiasp_teste
```

O script pede a palavra `RESTAURAR` e, ao final, mostra as contagens de
usuários, times e jogos. Confira se batem com o esperado.

---

## Quando algo dá errado

### "Não foi possível conectar ao banco de dados"

O MySQL não está no ar. Abra o painel do XAMPP e inicie o MySQL.

### A tabela está com o time errado em primeiro

Quase sempre é a ordem de desempate. **Gestão → Regras do campeonato**, confira
os critérios e veja a prévia.

### O jogo não aparece no calendário

Falta data e hora. **Gestão → Escala de arbitragem** lista as partidas nessa
situação.

### O árbitro não vê a partida

Ela não foi designada a ele. Confira em **Escala de arbitragem**.

### "Erro interno. Informe a referência ABC123"

Anote a referência e procure quem cuida do sistema. Com ela, a linha exata do
problema é encontrada em `logs/php-error.log` com um comando de busca.

### O e-mail não chegou

**Gestão → Comunicações** mostra o que o sistema tentou enviar e o motivo da
falha. Se aparecer "nenhum servidor de e-mail configurado", é o esperado numa
instalação local: as mensagens estão em `logs/emails/`.

### O aluno diz que o sistema pede login o tempo todo

A sessão expira após 2 horas sem atividade. É proposital: computador de
laboratório costuma ficar aberto.
