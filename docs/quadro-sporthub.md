# Quadro SportHub — conteúdo para o Trello

Gerado em 30 de agosto de 2026, ao fim da sprint 6. **106 cartões** em oito listas, 83 deles concluídos (78% do escopo).

A versão visual do mesmo quadro está publicada em
<https://claude.ai/code/artifact/21d388b8-3c56-4f8b-b0db-9638eda73f55>.

## Como usar este arquivo

Cada lista traz duas coisas:

1. **Bloco de criação rápida** — o Trello aceita várias linhas coladas de uma vez no
   campo *Adicionar um cartão*: ele pergunta se você quer criar um cartão por linha.
   Responda que sim e a lista inteira nasce de uma vez, na ordem certa.
2. **Descrição de cada cartão** — abra o cartão recém-criado, cole o texto no campo
   de descrição e marque as etiquetas indicadas.

As etiquetas do quadro são de dois tipos. **Dificuldade** (uma por cartão, cor sólida):
Fácil é meio dia de trabalho, Média é de um a três dias, Difícil passa disso ou tem
risco técnico. **Área** (uma ou mais): Back-end, Front-end, Documentação, Banco de
dados, Segurança & LGPD, Infra / Deploy e Testes / QA.

O código `SH-00` é o identificador fixo do cartão — use-o na mensagem de commit e ao
citar o item na documentação do TCC.

---

## Índice das listas

| Lista | Cartões | O que a lista significa |
|---|---:|---|
| Backlog | 4 | ideias ainda não refinadas |
| Refinados | 3 | com critério de aceite, prontos para entrar em sprint |
| Sprint Backlog | 4 | Sprint 7 · publicação e defesa |
| Em progresso | 4 | limite do quadro: 4 cartões |
| Revisão de código | 3 | aguardando leitura de outra pessoa |
| Bloqueado | 5 | depende de decisão ou contratação externa |
| Concluído | 31 | Sprint 6 · o que faltava do produto, testes e documentação |
| Arquivo | 52 | arquivo das sprints encerradas |

---

## Lista: Backlog

*ideias ainda não refinadas · 4 cartões*

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
SH-103 · Push de início de partida
SH-104 · Boletim da rodada em PDF
SH-105 · Histórico de temporadas
SH-106 · Inscrição do time pelo próprio professor
```

### Descrição de cada cartão

#### SH-103 · Push de início de partida

**Etiquetas:** Difícil · Front-end, Infra / Deploy

O service worker já está instalado e guardando o casco das telas. Falta a parte que avisa: chave VAPID, assinatura por usuário e o disparo no momento em que o árbitro abre a partida. Depende de HTTPS (SH-43).

#### SH-104 · Boletim da rodada em PDF

**Etiquetas:** Média · Back-end

O gerador de PDF já existe para a súmula. Reaproveitá-lo num boletim com classificação, resultados e próximos jogos daria à escola o documento que hoje é remontado à mão no editor de texto para colar no mural.

#### SH-105 · Histórico de temporadas

**Etiquetas:** Média · Banco de dados, Back-end

Hoje o campeonato é um só: encerrar o interclasse e começar o do ano seguinte mistura as duas tabelas. Uma temporada com data de início e fim permitiria arquivar o ano anterior sem apagá-lo.

#### SH-106 · Inscrição do time pelo próprio professor

**Etiquetas:** Média · Back-end, Segurança & LGPD

Toda a montagem passa pela coordenação, que vira gargalo na semana da inscrição. Deixar o professor inscrever a turma e o elenco exige um perfil próprio, hoje inexistente: professor entra como árbitro.

---

## Lista: Refinados

*com critério de aceite, prontos para entrar em sprint · 3 cartões*

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
SH-100 · Migrar admin/times.php para o padrão de listagem
SH-101 · Avisar no painel sobre senha de fábrica sem pagar bcrypt
SH-102 · Esconder da lista o aluno que já está no time
```

### Descrição de cada cartão

#### SH-100 · Migrar admin/times.php para o padrão de listagem

**Etiquetas:** Média · Back-end, Front-end

É a última tela com busca e paginação escritas à mão, herdadas do SH-52. Aceite: comportamento visível idêntico, sh_listar() por baixo e o bloco duplicado apagado — para a próxima correção do escape valer em todo lugar de uma vez.

#### SH-101 · Avisar no painel sobre senha de fábrica sem pagar bcrypt

**Etiquetas:** Média · Segurança & LGPD, Front-end

O checklist de linha de comando verifica conta por conta, mas o painel não pode: seriam quatro bcrypt a cada carregamento. Aceite: guardar o resultado da última verificação com a data e exibi-lo, em vez de recalcular na abertura da tela.

#### SH-102 · Esconder da lista o aluno que já está no time

**Etiquetas:** Fácil · Back-end

A lista de alunos disponíveis mostra também quem já foi escalado, e adicionar de novo só falha quando o número da camisa repete. Aceite: quem já tem jogador no time sai da lista, e a contagem passa a refletir quem realmente pode entrar.

---

## Lista: Sprint Backlog

*Sprint 7 · publicação e defesa · 4 cartões*

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
SH-98 · Aplicar a migração v3 em toda instalação
SH-99 · Trocar a senha das quatro contas de fábrica
SH-49 · Usuário de banco dedicado
SH-97 · Extensão GD desligada no PHP
```

### Descrição de cada cartão

#### SH-98 · Aplicar a migração v3 em toda instalação

**Etiquetas:** Fácil · Banco de dados, Infra / Deploy

Uma base parada na v2 degrada em silêncio: o painel deixa de enxergar contas de fábrica e a tela de titulares perde a coluna de anonimização — cada uma registrando a falha no log e seguindo em frente. Rodar migration_v3.sql é o que liga metade da sprint 6.

#### SH-99 · Trocar a senha das quatro contas de fábrica

**Etiquetas:** Fácil · Segurança & LGPD

O código já obriga a troca no próximo login e o checklist aponta quem ainda entra com a senha publicada. Falta o ato: entrar uma vez com cada conta, ou redefinir pelo painel, e remover as de demonstração com preparar_producao.php --aplicar.

#### SH-49 · Usuário de banco dedicado

**Etiquetas:** Média · Banco de dados, Infra / Deploy

Sair do root sem senha do XAMPP: criar usuário com permissão apenas no banco olimpiasp, sem privilégio administrativo, e configurar a credencial em includes/config.php.

#### SH-97 · Extensão GD desligada no PHP

**Etiquetas:** Fácil · Infra / Deploy

Sem GD, o escudo do time é guardado como veio — sem o reencode que descarta código embutido em imagem — e a galeria recusa qualquer envio, porque não há como remover o EXIF, que traz a localização da foto. Correção: tirar o ponto e vírgula de ;extension=gd no php.ini e reiniciar o Apache.

---

## Lista: Em progresso

*limite do quadro: 4 cartões · 4 cartões*

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
SH-53 · Roteiro de teste de aceitação por perfil
SH-62 · Acessibilidade: contraste, foco e ARIA
SH-68 · Multi-escola de verdade
SH-75 · Vídeo demonstrativo de 3 minutos
```

### Descrição de cada cartão

#### SH-53 · Roteiro de teste de aceitação por perfil

**Etiquetas:** Média · Testes / QA

docs/roteiro-teste-aceitacao.md está escrito: as três jornadas em tabela de passo, resultado esperado e caixa de conferência. Falta executar e guardar o print de cada passo para anexar ao trabalho — é trabalho de tela, que nenhum teste automatizado substitui.

#### SH-62 · Acessibilidade: contraste, foco e ARIA

**Etiquetas:** Média · Front-end

Foco visível em todo controle navegado por teclado, rótulo ARIA nos filtros e botões novos, contraste do vidro calibrado para AA nos dois temas e as tabelas empilhadas do SH-46 com rótulo por célula. Falta a auditoria com ferramenta nas telas públicas e no login.

#### SH-68 · Multi-escola de verdade

**Etiquetas:** Difícil · Banco de dados, Back-end

escola_id entrou em todas as tabelas na migração v3 e sh_filtro_escola() já isola oito frentes: designação, galeria, indicadores, ocorrências, estatísticas e as três consultas do campeonato. Falta o restante das telas e a administração de escolas. Até lá o IS NULL no filtro preserva o que foi cadastrado antes da v3.

#### SH-75 · Vídeo demonstrativo de 3 minutos

**Etiquetas:** Fácil · Documentação

docs/roteiro-video.md traz o roteiro cena a cena, com o que aparece na tela e o que se fala em cada uma. Falta gravar — é trabalho de captura, não de código.

---

## Lista: Revisão de código

*aguardando leitura de outra pessoa · 3 cartões*

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
SH-94 · Senha de fábrica reconhecida por verificação, não por sinalizador
SH-95 · Alunos disponíveis com busca e paginação
SH-96 · “Limpar” derrubava o contexto da URL
```

### Descrição de cada cartão

#### SH-94 · Senha de fábrica reconhecida por verificação, não por sinalizador

**Etiquetas:** Difícil · Segurança & LGPD, Back-end

A migração marcava senha_provisoria só nas contas que ainda guardavam a senha em texto puro. Num banco já convertido para bcrypt ninguém era marcado: admin entrava com admin1234 e o painel afirmava não haver nenhuma conta de fábrica. Como o bcrypt tem sal, a senha não se reconhece pelo hash. Agora o login compara a senha digitada e exige a troca na hora, e o checklist verifica conta por conta. 19 verificações novas cobrem o caso.

#### SH-95 · Alunos disponíveis com busca e paginação

**Etiquetas:** Média · Back-end, Front-end

Última tela que ainda carregava a tabela inteira: admin/ver_jogadores.php lia todos os alunos e desenhava um formulário para cada um — numa escola de mil alunos, mil formulários numa página só. Passou para o sh_listar(), 12 por página, com busca por nome. Fecha o aceite do SH-83.

#### SH-96 · “Limpar” derrubava o contexto da URL

**Etiquetas:** Fácil · Front-end

O botão apontava para o arquivo puro, sem query. Em ver_jogadores.php isso descartava o time_id e a tela desviava para a lista; em jogos.php zerava o filtro de status recém-escolhido. Agora limpa o termo e a página, e preserva o resto do endereço.

---

## Lista: Bloqueado

*depende de decisão ou contratação externa · 5 cartões*

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
SH-41 · Integrar gateway de pagamento
SH-42 · Envio real de e-mail (SMTP)
SH-43 · HTTPS, domínio e hospedagem
SH-44 · Dados reais do controlador e do DPO
SH-64 · Recuperação de senha por e-mail
```

### Descrição de cada cartão

#### SH-41 · Integrar gateway de pagamento

**Etiquetas:** Difícil · Back-end

Impedido por: falta definir o gateway e a conta PJ da instituição. O fluxo de assinar.php já registra a contratação, o plano e o aceite dos termos, mas nenhuma cobrança é processada. Nada de cartão deve trafegar pelo sistema.

#### SH-42 · Envio real de e-mail (SMTP)

**Etiquetas:** Média · Infra / Deploy, Back-end

Impedido por: domínio e caixa de e-mail ainda não contratados. Hoje os protocolos ARB, SH e LGPD só aparecem na tela — quem fechar a página perde o número. Trava também SH-58 e SH-64.

#### SH-43 · HTTPS, domínio e hospedagem

**Etiquetas:** Média · Infra / Deploy

Impedido por: contratação da hospedagem. O código já está pronto — o cookie de sessão vira Secure sozinho e o Strict-Transport-Security passa a ser enviado assim que o acesso for por HTTPS. Trava também SH-69.

#### SH-44 · Dados reais do controlador e do DPO

**Etiquetas:** Fácil · Documentação, Segurança & LGPD

Impedido por: definição jurídica de quem é o controlador e quem responde como encarregado. Preencher SH_EMAIL_DPO e os dados do controlador em includes/config.php, em privacidade.php e nos termos.

#### SH-64 · Recuperação de senha por e-mail

**Etiquetas:** Média · Back-end

recuperar_senha.php e redefinir_senha.php estão prontos, com token de uso único e validade curta na tabela senha_tokens, invalidado após a troca. Impedido por: sem SMTP (SH-42) o link para no registro de e-mails e nunca chega ao titular.

---

## Lista: Concluído

*Sprint 6 · o que faltava do produto, testes e documentação · 31 cartões*

> Esta lista é dividida por separadores. No Trello, crie o separador como um
> cartão comum de título `───── Sprint 1 · Fundação ─────` (ou use a cor de
> capa cinza) para manter a mesma leitura do quadro visual.

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
───── O produto · 13 cartões ─────
SH-45 · Exportar súmula em PDF
SH-55 · Chaveamento de mata-mata automático
SH-56 · Critérios de desempate configuráveis
SH-60 · Eliminação e anonimização de conta
SH-65 · Dois fatores para a coordenação
SH-67 · Estatísticas individuais do atleta
SH-69 · PWA com aviso de partida
SH-71 · Galeria de fotos por partida
SH-72 · Painel de indicadores para a direção
SH-73 · Registro de ocorrência disciplinar
SH-92 · Cobrança da assinatura em modo manual
SH-93 · Registro de e-mails enviados
SH-46 · Responsividade do painel no celular
───── Qualidade e segurança · 9 cartões ─────
SH-37 · Tirar unsafe-inline da CSP
SH-38 · Conflito de horário na escala de arbitragem
SH-39 · Padronizar exceção e error_log
SH-48 · Trocar senhas do seed e remover contas demo
SH-63 · Testes automatizados dos helpers
SH-83 · Estender busca e paginação às demais listas
SH-84 · Trocar SH_SEGREDO_FEED antes de publicar
SH-86 · Padrão único de busca e paginação
SH-91 · Ordenação alfabética com acento
───── Base, publicação e documentação · 9 cartões ─────
SH-85 · Migração incremental v3
SH-87 · Checklist executável de produção
SH-88 · Aviso de pendências no painel da coordenação
SH-89 · Passo a passo de publicação
SH-90 · README reescrito para o sistema atual
SH-51 · Rotina de backup do banco e de uploads/
SH-47 · Capítulo de metodologia do TCC
SH-54 · Manual do usuário da escola
SH-74 · Slides e roteiro da defesa
```

### Descrição de cada cartão

#### SH-45 · Exportar súmula em PDF

**Etiquetas:** Difícil · Back-end

includes/pdf.php monta o arquivo byte a byte, sem biblioteca externa, com a tabela xref conferida por teste. Fontes em WinAnsiEncoding para o acento sair legível; parênteses e contrabarra escapados. Baixa quem arbitrou a partida e a coordenação — aluno não, porque a súmula liga nome de jogador a cartão e a ocorrência disciplinar.

#### SH-55 · Chaveamento de mata-mata automático

**Etiquetas:** Difícil · Back-end

sh_gerar_chaveamento() monta a árvore de uma vez e cada posição já nasce sabendo para onde manda o vencedor; sh_chaveamento_ao_encerrar_jogo() promove sozinho quando o árbitro encerra a partida. admin/chaveamento.php só desenha — não recalcula nada.

#### SH-56 · Critérios de desempate configuráveis

**Etiquetas:** Média · Back-end, Banco de dados

A tabela config_campeonato guarda os pontos por vitória e a ordem de desempate; sh_criterios_desempate() valida a lista e sempre a termina em nome, para a ordenação não variar entre dois carregamentos. admin/configuracao.php é a tela.

#### SH-60 · Eliminação e anonimização de conta

**Etiquetas:** Difícil · Segurança & LGPD, Banco de dados

includes/lgpd_conta.php anonimiza o titular e preserva o histórico agregado do campeonato — o placar não some. A operação vai para a auditoria e o status impede novo login. admin/contas_lgpd.php é a tela da coordenação.

#### SH-65 · Dois fatores para a coordenação

**Etiquetas:** Difícil · Segurança & LGPD, Back-end

includes/totp.php implementa o código de 6 dígitos com janela de tolerância, e verificar_2fa.php é a segunda etapa: o usuario_id só entra na sessão depois dela, então fechar a tela do código não leva ninguém ao painel. Códigos de recuperação de uso único ficam em totp_codigos.

#### SH-67 · Estatísticas individuais do atleta

**Etiquetas:** Média · Back-end

aluno/estatisticas.php: artilharia, cartões e presença por jogador, calculados de eventos_jogo. Nenhum dado pessoal além do que a súmula da partida já mostra publicamente.

#### SH-69 · PWA com aviso de partida

**Etiquetas:** Difícil · Front-end

manifest.webmanifest, sw.js e offline.html: instala no celular, guarda o casco das telas e mostra a página offline quando a rede cai. O registro do service worker só acontece em https ou em localhost. O aviso de partida em si — o push — virou o SH-103 e continua dependendo do SH-43.

#### SH-71 · Galeria de fotos por partida

**Etiquetas:** Fácil · Front-end, Segurança & LGPD

admin/galeria.php e aluno/galeria.php sobre a tabela jogo_fotos. Cada foto carrega o consentimento que a autorizou e uma data de retenção, e o EXIF é removido no envio — ele traz a localização de onde a foto foi tirada, e a maioria dos retratados é menor de idade.

#### SH-72 · Painel de indicadores para a direção

**Etiquetas:** Média · Front-end

admin/indicadores.php: participação por turma, modalidade mais disputada e percentual de jogos concluídos. É o material que a escola usa para justificar a renovação da assinatura.

#### SH-73 · Registro de ocorrência disciplinar

**Etiquetas:** Média · Back-end, Segurança & LGPD

admin/ocorrencias.php sobre a tabela ocorrencias: o árbitro relata dentro da súmula e só a coordenação lê. Acesso registrado em auditoria e prazo de eliminação declarado, porque é dado sensível de menor.

#### SH-92 · Cobrança da assinatura em modo manual

**Etiquetas:** Média · Back-end

Enquanto o gateway não existe (SH-41), a tabela cobrancas registra o que foi cobrado e admin/cobrancas.php dá baixa. O código Pix é gerado pelo próprio sistema, com o CRC conferido por teste. Nenhum dado de cartão trafega pelo sistema.

#### SH-93 · Registro de e-mails enviados

**Etiquetas:** Média · Back-end, Infra / Deploy

Sem SMTP (SH-42), toda mensagem vai para a tabela emails_enviados e para logs/emails/, e admin/emails.php mostra o que teria sido enviado. É o que permite recuperar um protocolo que o usuário perdeu, em vez de encolher os ombros.

#### SH-46 · Responsividade do painel no celular

**Etiquetas:** Média · Front-end

Abaixo de 768px, toda célula com data-rotulo vira uma linha rotulada e a tabela empilha em cartões. Sem tabela paralela e sem duplicar regra de layout: o mesmo HTML serve aos dois tamanhos.

#### SH-37 · Tirar unsafe-inline da CSP

**Etiquetas:** Difícil · Segurança & LGPD, Front-end

sh_nonce() gera um valor novo a cada resposta e ele acompanha o cabeçalho e cada bloco inline da página. Declarar um nonce faz o navegador ignorar 'unsafe-inline', que saiu da política: script-src e style-src aceitam agora só 'self' mais o nonce daquela requisição. HTML injetado não tem como adivinhá-lo.

#### SH-38 · Conflito de horário na escala de arbitragem

**Etiquetas:** Média · Back-end

sh_conflitos_agenda() procura choques antes de gravar: árbitro ou time em duas partidas sobrepostas é impedimento e bloqueia a escala; mesmo local no mesmo horário sai como aviso. A tela diz qual partida está em conflito, em vez de apenas recusar.

#### SH-39 · Padronizar exceção e error_log

**Etiquetas:** Média · Back-end

Varredura nas 122 capturas de PDOException do projeto. Três devolviam silêncio: a rota AJAX do placar, a leitura de colunas do perfil e a geração de protocolo — que voltava para o número 1 sem avisar e reemitiria um ARB já existente. As seis restantes são degradação deliberada (coluna que ainda não existe, foto de perfil opcional) e ficaram documentadas como tal. Nenhuma imprime a exceção na tela.

#### SH-48 · Trocar senhas do seed e remover contas demo

**Etiquetas:** Fácil · Segurança & LGPD

A senha de fábrica deixou de valer para sempre: bd.sql grava hash, a política recusa admin1234 e companhia, e o login desvia toda tela para a troca enquanto a senha for provisória. preparar_producao.php --aplicar remove as contas de demonstração. A detecção precisou ser refeita — SH-94.

#### SH-63 · Testes automatizados dos helpers

**Etiquetas:** Difícil · Testes / QA, Back-end

tests/executar.php com executor próprio, sem Composer: 175 verificações sobre CPF, política de senha, escape do LIKE, TOTP, QR Code, Pix, classificação, PDF e e-mail — rodando sem MySQL ligado, porque teste que exige o ambiente inteiro no ar é teste que ninguém roda. A suíte já se pagou: encontrou o SH-91.

#### SH-83 · Estender busca e paginação às demais listas

**Etiquetas:** Média · Back-end, Front-end

includes/listagem.php virou o padrão: LIKE parametrizado com os curingas escapados, paginação no servidor e o termo preservado na URL. Aplicado a jogos, árbitros, modalidades, assinaturas, credenciamentos e — por último — à lista de alunos disponíveis (SH-95). admin/times.php segue com a implementação própria do SH-52, e sair dela é o SH-100.

#### SH-84 · Trocar SH_SEGREDO_FEED antes de publicar

**Etiquetas:** Fácil · Segurança & LGPD

Deixou de existir valor de fábrica: sh_segredo_feed() sorteia 32 bytes no primeiro uso e os guarda em logs/segredo_feed.txt, pasta bloqueada por .htaccess. Definir SH_SEGREDO_FEED em config.local.php continua tendo precedência, e trocá-lo invalida os endereços antigos de uma vez.

#### SH-86 · Padrão único de busca e paginação

**Etiquetas:** Média · Back-end

includes/listagem.php reúne sh_listar(), a barra de busca e a navegação de páginas. Copiar o bloco do SH-52 em seis telas daria seis lugares para corrigir no dia em que alguém descobrisse que o % digitado pelo usuário não estava escapado.

#### SH-91 · Ordenação alfabética com acento

**Etiquetas:** Média · Back-end

strcmp compara byte a byte: em UTF-8 o Á começa com 0xC3 e o Z com 0x5A, então “Águias” e “Leões do 9ºA” caíam depois de “Zulu” na classificação. sh_comparar_nome() compara a forma sem acento e recorre ao strcmp original só para desempatar, mantendo a ordem estável. Achado pela suíte do SH-63, não por reclamação de usuário.

#### SH-85 · Migração incremental v3

**Etiquetas:** Difícil · Banco de dados

scripts/migration_v3.sql aplica sobre uma base v2 já em uso: senha provisória e 2FA, chaveamento, configuração do campeonato, tokens de senha, fotos, ocorrências, cobranças, registro de e-mails e escola_id em todo lugar. Tudo com IF NOT EXISTS, sem derrubar campeonato em andamento.

#### SH-87 · Checklist executável de produção

**Etiquetas:** Média · Infra / Deploy, Segurança & LGPD

scripts/preparar_producao.php confere dez frentes — configuração local, usuário do banco, senhas, segredo do feed, SMTP, controlador, cobrança, extensões do PHP, pastas graváveis e backup — e com --aplicar remove as contas de demonstração. Uma lista que depende de alguém lembrar é uma lista que falha.

#### SH-88 · Aviso de pendências no painel da coordenação

**Etiquetas:** Fácil · Front-end, Infra / Deploy

admin/dashboard.php mostra o que impede a publicação, separando impeditivo de pendência e dizendo onde resolver cada um. O cartão só aparece quando há pendência: painel que avisa sempre vira paisagem.

#### SH-89 · Passo a passo de publicação

**Etiquetas:** Fácil · Documentação, Infra / Deploy

docs/publicacao.md separa o que o código já resolve do que depende de contratação, e explica por que HTTPS não é preferência: três funcionalidades simplesmente não funcionam em http.

#### SH-90 · README reescrito para o sistema atual

**Etiquetas:** Fácil · Documentação

Instalação, estrutura de pastas, perfis, módulos, decisões de segurança e o mapa da LGPD, com o checklist de produção apontando para o preparar_producao.php em vez de pedir memória a quem publica.

#### SH-51 · Rotina de backup do banco e de uploads/

**Etiquetas:** Média · Infra / Deploy

scripts/backup.bat e backup.sh fazem o dump do olimpiasp e a cópia de uploads/, com retenção definida; restaurar.bat faz o caminho de volta. O checklist de produção avisa enquanto nenhum backup tiver sido registrado — backup que nunca foi restaurado não é backup.

#### SH-47 · Capítulo de metodologia do TCC

**Etiquetas:** Média · Documentação

docs/metodologia-tcc.md: por que PHP sem framework, como o banco foi modelado, quais decisões de segurança foram tomadas e o mapa de cada exigência da LGPD para a tela ou a tabela que a cumpre.

#### SH-54 · Manual do usuário da escola

**Etiquetas:** Fácil · Documentação

docs/manual-do-usuario.md, escrito para quem opera e não para quem instala: coordenação e árbitro, passo a passo, separado do README.

#### SH-74 · Slides e roteiro da defesa

**Etiquetas:** Fácil · Documentação

docs/roteiro-defesa.md: os slides na ordem, o que dizer em cada um, a demonstração ao vivo com plano B para o caso de a rede falhar, e as perguntas que a banca costuma fazer sobre segurança e LGPD já respondidas.

---

## Lista: Arquivo

*arquivo das sprints encerradas · 52 cartões*

> Esta lista é dividida por separadores. No Trello, crie o separador como um
> cartão comum de título `───── Sprint 1 · Fundação ─────` (ou use a cor de
> capa cinza) para manter a mesma leitura do quadro visual.

### Criação rápida — cole este bloco inteiro em *Adicionar um cartão*

```text
───── Sprint 1 · Fundação · 8 cartões · encerrada ─────
SH-01 · Modelagem do banco de dados
SH-02 · Conexão, sessão e helpers compartilhados
SH-03 · Login, logout e hash de senha
SH-04 · Controle de perfil por página
SH-05 · Design system do painel
SH-06 · CRUD de modalidades
SH-07 · CRUD de times e elencos
SH-08 · Upload de escudo com reencode PNG
───── Sprint 2 · O campeonato · 7 cartões · encerrada ─────
SH-09 · Agendamento de jogos
SH-10 · Cadastro manual e designação de árbitros
SH-11 · Súmula digital do árbitro
SH-12 · Placar ao vivo por AJAX
SH-13 · Classificação automática
SH-14 · Painel do aluno
SH-15 · Dashboard da coordenação
───── Sprint 3 · Site, assinaturas e LGPD · 15 cartões · encerrada ─────
SH-16 · Landing page e design system público
SH-17 · Página Como funciona
SH-18 · Planos lidos da tabela planos
SH-19 · Contratação com teste de 30 dias
SH-20 · Credenciamento do árbitro com protocolo
SH-21 · Análise e aprovação do credenciamento
SH-22 · Validação de CPF e MIME real no upload
SH-23 · Portal LGPD do titular
SH-24 · Banner de cookies com consentimento granular
SH-25 · Privacidade, Termos e Cookies
SH-26 · Trilha de auditoria
SH-27 · Cabeçalhos de segurança e trava de força bruta
SH-28 · Tema claro e escuro sem flash
SH-29 · Migração incremental v2
SH-30 · README de instalação e arquitetura
───── Sprint 4 · Site, contato e perfil · 6 cartões · encerrada ─────
SH-31 · Formulário de contato comercial
SH-32 · Perfil do usuário com foto
SH-33 · Entrega segura do documento do árbitro
SH-34 · Exclusões por link protegidas por CSRF
SH-35 · Página 404 e regras do .htaccess
SH-36 · robots.txt e metadados das páginas públicas
───── Sprint 5 · Endurecimento e camada visual · 16 cartões · encerrada ─────
SH-40 · Consultas da classificação corrigidas
SH-50 · display_errors decidido pelo ambiente
SH-52 · Busca e paginação na lista de times
SH-57 · Limites do plano aplicados no sistema
SH-58 · Aviso de designação para o árbitro
SH-59 · Exportar classificação e resultados em CSV
SH-61 · Registrar acesso a documento sensível
SH-66 · Modo telão: placar para projetor
SH-70 · Calendário exportável (.ics)
SH-76 · Camada visual liquid glass
SH-77 · XSS armazenado no nome do time vencedor
SH-78 · ErrorDocument apontando para pasta inexistente
SH-79 · Placar e selos de status na tabela de jogos
SH-80 · Validação do cadastro manual de árbitro
SH-81 · Alinhamento do dashboard e das abas do login
SH-82 · README alinhado ao que existe
```

### Descrição de cada cartão

#### SH-01 · Modelagem do banco de dados

**Etiquetas:** Difícil · Banco de dados

Schema completo em bd.sql com 16 tabelas: usuários, times, jogadores, modalidades, jogos, estatísticas e eventos de jogo, escolas, planos, assinaturas, credenciamento, consentimentos, solicitações LGPD, contatos e auditoria — com chaves estrangeiras e índices já na criação.

#### SH-02 · Conexão, sessão e helpers compartilhados

**Etiquetas:** Média · Back-end

includes/config.php: PDO com EMULATE_PREPARES desligado, cookie de sessão HttpOnly e SameSite=Lax, Secure automático em HTTPS, e os helpers usados por todo o projeto — e(), sh_url(), sh_money(), csrf_field().

#### SH-03 · Login, logout e hash de senha

**Etiquetas:** Difícil · Segurança & LGPD, Back-end

Autenticação com password_hash e password_verify (bcrypt). As senhas do seed em texto puro são convertidas em hash no primeiro login válido. O logout destrói sessão e cookie.

#### SH-04 · Controle de perfil por página

**Etiquetas:** Média · Back-end, Segurança & LGPD

exigirPerfil() roda antes de qualquer saída HTML — se o cabeçalho fosse impresso primeiro, o header(Location) não teria efeito e a página continuaria visível para quem não tem permissão.

#### SH-05 · Design system do painel

**Etiquetas:** Média · Front-end

css/style.css com variáveis de cor, tipografia e espaçamento, e componentes de tabela, formulário, card e botão reaproveitados por todas as telas internas.

#### SH-06 · CRUD de modalidades

**Etiquetas:** Fácil · Back-end

Cadastro, edição e exclusão de modalidades esportivas em admin/modalidades.php, com validação no servidor e exclusão protegida por token.

#### SH-07 · CRUD de times e elencos

**Etiquetas:** Média · Back-end

admin/times.php, editar_time.php e ver_jogadores.php: turma, elenco, número de camisa e gênero, com validação no servidor além do required do HTML — que só protege quem usa o formulário.

#### SH-08 · Upload de escudo com reencode PNG

**Etiquetas:** Média · Segurança & LGPD

admin/team_logos.php confere o tipo real com getimagesize, limita a 2 MB, renomeia o arquivo no servidor e reencoda em PNG — o que descarta qualquer código embutido na imagem.

#### SH-09 · Agendamento de jogos

**Etiquetas:** Média · Back-end

admin/jogos.php e editar_jogo.php: data, hora, local, modalidade e times mandante e visitante, com exclusão protegida por CSRF.

#### SH-10 · Cadastro manual e designação de árbitros

**Etiquetas:** Média · Back-end

admin/arbitros.php e designar_jogos.php montam a escala de arbitragem gravando o arbitro_id na partida — base do controle de acesso da súmula.

#### SH-11 · Súmula digital do árbitro

**Etiquetas:** Difícil · Back-end

arbitro/registrar_resultado.php grava placar, gols, cartões e substituições. O arbitro_id entra no WHERE do UPDATE: ninguém registra resultado de jogo que não é seu, mesmo trocando o id na URL.

#### SH-12 · Placar ao vivo por AJAX

**Etiquetas:** Média · Front-end, Back-end

aluno/ajax_jogo_stats.php devolve placar e eventos em JSON e a tela do aluno atualiza sem recarregar. Sem sessão, o endpoint responde 401 em vez de página de login.

#### SH-13 · Classificação automática

**Etiquetas:** Difícil · Back-end

aluno/classificacao.php calcula pontos, vitórias, empates, derrotas, gols pró e contra e saldo direto das partidas encerradas — sem tabela paralela que possa ficar dessincronizada.

#### SH-14 · Painel do aluno

**Etiquetas:** Média · Front-end

Calendário de partidas, resultados encerrados e tabela de classificação, em leitura apenas. O aluno só altera o próprio perfil.

#### SH-15 · Dashboard da coordenação

**Etiquetas:** Média · Front-end

admin/dashboard.php reúne os contadores do campeonato e, principalmente, as pendências que precisam de ação: credenciamentos em análise e requisições de titular em aberto.

#### SH-16 · Landing page e design system público

**Etiquetas:** Média · Front-end

index.php com hero em formato de placar, seções de recurso e CTA, sobre css/site.css — sistema visual separado do painel, para o site poder mudar sem quebrar tela interna.

#### SH-17 · Página Como funciona

**Etiquetas:** Fácil · Documentação, Front-end

Guia etapa por etapa do uso real: do cadastro dos times à publicação da classificação, escrito para a coordenação decidir se o sistema serve para a escola.

#### SH-18 · Planos lidos da tabela planos

**Etiquetas:** Média · Back-end, Banco de dados

planos.php e assinar.php leem preço e limites do banco por sh_planos(). Mudar valor é editar um registro, não o código — e as duas páginas nunca discordam.

#### SH-19 · Contratação com teste de 30 dias

**Etiquetas:** Difícil · Back-end

assinar.php gera o código SH-AAAA-NNNN, inicia em status trial por 30 dias e registra o aceite dos termos com data, hora e IP. Nenhum dado de cartão é coletado: a escola só indica a forma de pagamento preferida.

#### SH-20 · Credenciamento do árbitro com protocolo

**Etiquetas:** Difícil · Back-end

cadastro-arbitro.php registra formação, registro profissional (CREF ou federação), experiência, modalidades e documento comprobatório, e gera o protocolo ARB-AAAA-NNNN, consultável sem login.

#### SH-21 · Análise e aprovação do credenciamento

**Etiquetas:** Difícil · Back-end, Segurança & LGPD

admin/solicitacoes_arbitros.php: aprovar cria o usuário árbitro, gera senha provisória exibida uma única vez e emite credencial de 1 ano; recusar elimina o documento do servidor, porque a finalidade do tratamento se encerrou.

#### SH-22 · Validação de CPF e MIME real no upload

**Etiquetas:** Média · Segurança & LGPD

sh_cpf_valido() confere os dígitos verificadores; o documento tem o tipo real lido por finfo (nunca a extensão enviada), limite de 5 MB e nome aleatório. Solicitação duplicada em análise é bloqueada.

#### SH-23 · Portal LGPD do titular

**Etiquetas:** Difícil · Segurança & LGPD, Back-end

lgpd.php recebe a requisição do art. 18, gera protocolo LGPD-AAAA-NNNN e calcula o prazo de 15 dias corridos do art. 19. admin/lgpd.php ordena por prazo e alerta o que vence em até 3 dias.

#### SH-24 · Banner de cookies com consentimento granular

**Etiquetas:** Média · Segurança & LGPD, Front-end

O banner de includes/site_footer.php e api/consentimento.php gravam o consentimento por finalidade em lgpd_consentimentos — a prova exigida pelo art. 8º, § 1º, que sem registro simplesmente não existe.

#### SH-25 · Privacidade, Termos e Cookies

**Etiquetas:** Média · Documentação, Segurança & LGPD

privacidade.php declara base legal por finalidade e a proteção reforçada a menores do art. 14. A constante SH_VERSAO_POLITICA reexibe o banner para todo mundo quando o texto legal muda.

#### SH-26 · Trilha de auditoria

**Etiquetas:** Média · Segurança & LGPD, Banco de dados

sh_auditar() grava ação, entidade, usuário e IP na tabela auditoria — prestação de contas do art. 6º, X, e também a fonte do contador de tentativas de login por IP.

#### SH-27 · Cabeçalhos de segurança e trava de força bruta

**Etiquetas:** Média · Segurança & LGPD

sh_headers_seguranca() envia CSP, nosniff, SAMEORIGIN, Referrer-Policy, Permissions-Policy e HSTS pelo próprio PHP, valendo mesmo sem mod_headers. Login trava após 5 erros na sessão e 15 por IP em 15 minutos — contados no banco, então limpar cookie não zera.

#### SH-28 · Tema claro e escuro sem flash

**Etiquetas:** Média · Front-end

Preferência em localStorage espelhada em cookie: sh_tema_attr() já monta o HTML no tema certo e sh_tema_boot() evita o flash branco antes do JS rodar. Trocar tema é trocar um atributo no html, sem duplicar regra de layout.

#### SH-29 · Migração incremental v2

**Etiquetas:** Média · Banco de dados

scripts/migration_v2.sql aplica credenciamento, assinaturas e LGPD sobre uma base que já existe, com CREATE TABLE IF NOT EXISTS e ALTER TABLE — sem apagar o campeonato em andamento.

#### SH-30 · README de instalação e arquitetura

**Etiquetas:** Fácil · Documentação

Requisitos, instalação limpa contra atualização, estrutura de pastas, perfis de acesso, módulos, decisões de segurança, mapa da LGPD e o checklist do que falta antes da produção.

#### SH-31 · Formulário de contato comercial

**Etiquetas:** Fácil · Back-end, Front-end

contato.php grava a mensagem na tabela contatos com IP e data; a coordenação lê em admin/assinaturas.php, junto das contratações. Protegido por CSRF e com validação no servidor.

#### SH-32 · Perfil do usuário com foto

**Etiquetas:** Média · Back-end

perfil.php troca dados básicos e foto. O arquivo vai para uploads/profile_photos/, pasta com execução de script bloqueada por .htaccess, e o nome é definido pelo servidor.

#### SH-33 · Entrega segura do documento do árbitro

**Etiquetas:** Média · Segurança & LGPD

admin/documento_arbitro.php serve o anexo do credenciamento apenas para a coordenação autenticada, conferindo realpath contra path traversal. A pasta uploads/credenciamento/ não é alcançável pela URL.

#### SH-34 · Exclusões por link protegidas por CSRF

**Etiquetas:** Fácil · Segurança & LGPD

excluir_time, excluir_jogador, excluir_jogo, excluir_modalidade e excluir_arbitro passaram a exigir token válido — fecha o furo clássico do link destrutivo disparado de fora do site.

#### SH-35 · Página 404 e regras do .htaccess

**Etiquetas:** Fácil · Infra / Deploy

ErrorDocument aponta para 404.php e o .htaccess bloqueia execução de script em uploads/, img/, includes/, scripts/ e api/. Renomear a pasta do projeto exige ajustar o caminho do ErrorDocument.

#### SH-36 · robots.txt e metadados das páginas públicas

**Etiquetas:** Fácil · Front-end

robots.txt libera o site institucional e bloqueia admin/, aluno/, arbitro/ e uploads/. Título e descrição revisados em index, planos, como-funciona e cadastro-arbitro.

#### SH-40 · Consultas da classificação corrigidas

**Etiquetas:** Média · Banco de dados, Back-end

A tabela somava vitórias e empates sem olhar o status do jogo: como o placar nasce 0 a 0, toda partida ainda por jogar entrava como empate para os dois times. O filtro de status foi para dentro da junção, o desempate passou a considerar saldo de gols, e as três consultas do campeonato foram para includes/consultas.php.

#### SH-50 · display_errors decidido pelo ambiente

**Etiquetas:** Fácil · Infra / Deploy

config.php trata como desenvolvimento apenas o acesso local (localhost, .local, .test, CLI) e desliga a exibição em qualquer outro host, mantendo log_errors sempre ligado. Sem destino configurado no servidor, o log vai para logs/php-error.log — pasta bloqueada por .htaccess.

#### SH-52 · Busca e paginação na lista de times

**Etiquetas:** Média · Back-end, Front-end

admin/times.php ganhou busca por nome e sala, com LIKE parametrizado e os curingas escapados, e paginação de 15 por página que preserva o termo na URL. As demais listas ficaram no SH-83.

#### SH-57 · Limites do plano aplicados no sistema

**Etiquetas:** Difícil · Back-end

sh_pode_criar() trava a criação de time, modalidade e árbitro — inclusive na aprovação de um credenciamento, que é onde nasce a maioria das contas de árbitro. NULL continua significando ilimitado, sem assinatura não há limite, e nada do que já existe é removido: rebaixar o plano só impede criar mais.

#### SH-58 · Aviso de designação para o árbitro

**Etiquetas:** Média · Back-end, Front-end

O painel abre com a próxima designação em destaque — modalidade, fase, data, hora e local — e separa as partidas em andamento, de hoje, futuras e encerradas. A versão por e-mail continua esperando o SH-42.

#### SH-59 · Exportar classificação e resultados em CSV

**Etiquetas:** Fácil · Back-end

Botão nas duas telas. Separador ponto e vírgula e BOM UTF-8, que é o que o Excel em português abre sem embaralhar coluna nem acento; texto começando por = + - @ recebe apóstrofo, para não ser lido como fórmula ao abrir a planilha.

#### SH-61 · Registrar acesso a documento sensível

**Etiquetas:** Média · Segurança & LGPD

Já estava implementado: admin/documento_arbitro.php grava na auditoria quem abriu, qual protocolo e quando, antes de entregar o arquivo. Confirmado em revisão — o cartão é que estava desatualizado.

#### SH-66 · Modo telão: placar para projetor

**Etiquetas:** Média · Front-end

aluno/telao.php: sem menu, tema escuro fixo, tipografia em vw. Alterna sozinho entre partidas e classificação a cada 14 s, busca dados a cada 15 s e esconde o cursor após 4 s parado. Espaço pausa, setas trocam de cena, F entra em tela cheia. Sem nada agendado, passa a exibir os últimos resultados.

#### SH-70 · Calendário exportável (.ics)

**Etiquetas:** Fácil · Back-end

api/calendario.php entrega um feed iCalendar assinável pelo Google Agenda, Outlook ou pelo celular. Como nenhum deles sabe fazer login, o acesso é por token opaco derivado por HMAC de SH_SEGREDO_FEED. O feed traz só times, horário e local — nenhum dado pessoal de aluno.

#### SH-76 · Camada visual liquid glass

**Etiquetas:** Difícil · Front-end

css/glass.css entra depois de site.css e de style.css e troca a matéria das superfícies sem mexer em layout: vidro translúcido com aresta especular, aurora animada no fundo, brilho que segue o ponteiro e reflexo no hover. Tudo dentro de @supports (backdrop-filter) e desligado inteiro em prefers-reduced-motion.

#### SH-77 · XSS armazenado no nome do time vencedor

**Etiquetas:** Fácil · Segurança & LGPD, Front-end

aluno/resultados.php imprimia o nome do vencedor sem passar por e(). Como o nome vem do banco, bastava cadastrar um time com script no nome para executá-lo na tela de todo aluno que abrisse os resultados. Corrigido, junto de outras saídas não escapadas da mesma página.

#### SH-78 · ErrorDocument apontando para pasta inexistente

**Etiquetas:** Fácil · Infra / Deploy

O .htaccess mandava o Apache buscar /sporthub_tcc02/404.php, nome antigo do diretório. Resultado: nenhum erro 404 do sistema aparecia — o servidor devolvia uma resposta de corpo vazio. Caminho corrigido e o README realinhado com o nome real da pasta.

#### SH-79 · Placar e selos de status na tabela de jogos

**Etiquetas:** Fácil · Front-end

O placar aparecia como texto solto sob os nomes dos times, dentro da coluna Times, mostrando 0 - 0 até em partida não realizada. Passou a ter coluna própria com forma de placar, o perdedor em tom discreto e um traço no lugar do número enquanto o jogo não começa. Os selos de status usavam uma classe que não existe no CSS e saíam sem cor.

#### SH-80 · Validação do cadastro manual de árbitro

**Etiquetas:** Fácil · Segurança & LGPD, Back-end

admin/arbitros.php aceitava POST com nome e senha vazios — e senha vazia gera um hash perfeitamente válido, que autentica. Entraram validação do formato do usuário, mínimo de 8 caracteres, checagem de duplicidade e registro em auditoria. A mensagem de erro existia como variável e nunca chegava à tela.

#### SH-81 · Alinhamento do dashboard e das abas do login

**Etiquetas:** Fácil · Front-end

Os quatro ícones do dashboard eram imagens de proporções diferentes forçadas em 40x40, o que achatava umas e esticava outras, e cada número começava numa altura. Agora cada ícone mora num quadrado fixo com object-fit e o cartão é uma coluna centrada. As abas Entrar / Cadastrar-se ficaram centralizadas e do mesmo tamanho.

#### SH-82 · README alinhado ao que existe

**Etiquetas:** Fácil · Documentação

Estrutura de pastas, módulos novos (CSV, calendário, telão, limites de plano) e a camada de vidro documentados; o nome antigo do diretório substituído em todas as ocorrências; e a lista de pendências antes de publicar atualizada, com a troca de SH_SEGREDO_FEED entrando no lugar do display_errors, já resolvido.

---

## Cartões que entraram nesta atualização

Nada foi removido do quadro anterior: os 84 cartões continuam lá, alguns em outra
lista e com a descrição atualizada para o que o código realmente faz hoje. Os 22
cartões abaixo são novos.

| Código | Cartão | Lista |
|---|---|---|
| `SH-85` | Migração incremental v3 | Concluído |
| `SH-86` | Padrão único de busca e paginação | Concluído |
| `SH-87` | Checklist executável de produção | Concluído |
| `SH-88` | Aviso de pendências no painel da coordenação | Concluído |
| `SH-89` | Passo a passo de publicação | Concluído |
| `SH-90` | README reescrito para o sistema atual | Concluído |
| `SH-91` | Ordenação alfabética com acento | Concluído |
| `SH-92` | Cobrança da assinatura em modo manual | Concluído |
| `SH-93` | Registro de e-mails enviados | Concluído |
| `SH-94` | Senha de fábrica reconhecida por verificação, não por sinalizador | Revisão de código |
| `SH-95` | Alunos disponíveis com busca e paginação | Revisão de código |
| `SH-96` | “Limpar” derrubava o contexto da URL | Revisão de código |
| `SH-97` | Extensão GD desligada no PHP | Sprint Backlog |
| `SH-98` | Aplicar a migração v3 em toda instalação | Sprint Backlog |
| `SH-99` | Trocar a senha das quatro contas de fábrica | Sprint Backlog |
| `SH-100` | Migrar admin/times.php para o padrão de listagem | Refinados |
| `SH-101` | Avisar no painel sobre senha de fábrica sem pagar bcrypt | Refinados |
| `SH-102` | Esconder da lista o aluno que já está no time | Refinados |
| `SH-103` | Push de início de partida | Backlog |
| `SH-104` | Boletim da rodada em PDF | Backlog |
| `SH-105` | Histórico de temporadas | Backlog |
| `SH-106` | Inscrição do time pelo próprio professor | Backlog |

