# SportHub — Sistema de Gestão de Campeonatos Interclasse

Plataforma web para escolas organizarem campeonatos interclasse do início ao fim:
inscrição de times, credenciamento de árbitros, agendamento de jogos, súmula
digital, placar ao vivo e classificação automática — com tratamento de dados
pessoais em conformidade com a LGPD (Lei nº 13.709/2018).

Projeto de TCC. Stack: **PHP 8 + MySQL/MariaDB + HTML/CSS/JS** (sem frameworks,
sem Composer, sem `vendor/`), rodando em **XAMPP**.

---

## Sumário

- [Instalação](#instalação)
- [Contas de demonstração](#contas-de-demonstração)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Perfis de acesso](#perfis-de-acesso)
- [Módulos](#módulos)
- [Modelo de assinatura](#modelo-de-assinatura)
- [Conformidade com a LGPD](#conformidade-com-a-lgpd)
- [Segurança](#segurança)
- [Testes](#testes)
- [Tema claro e escuro](#tema-claro-e-escuro)
- [Camada visual "liquid glass"](#camada-visual-liquid-glass)
- [Antes de colocar em produção](#antes-de-colocar-em-produção)
- [Documentação](#documentação)

---

## Instalação

### 1. Requisitos

- XAMPP com PHP 8.0+ e MySQL/MariaDB 10.4+
- Extensões PHP: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`, `iconv`, **`gd`**

> **A `gd` costuma vir desligada no XAMPP.** Sem ela, o escudo do time é
> guardado exatamente como veio (sem o reencode que descarta código embutido em
> imagem) e a galeria recusa envios, porque não há como remover os metadados
> EXIF da foto. Para ligar: no `php.ini`, tire o ponto e vírgula da linha
> `;extension=gd` e reinicie o Apache.

### 2. Arquivos

Copie o projeto para `C:\xampp\htdocs\sporthub_tcc1`.

### 3. Banco de dados

**Instalação limpa** (apaga e recria o banco `olimpiasp`):

```bash
C:\xampp\mysql\bin\mysql.exe -u root < bd.sql
```

**Atualizando uma instalação existente** (preserva os dados), na ordem:

```bash
C:\xampp\mysql\bin\mysql.exe -u root olimpiasp < scripts/migration_v2.sql
C:\xampp\mysql\bin\mysql.exe -u root olimpiasp < scripts/migration_v3.sql
```

Pelo phpMyAdmin: selecione o banco `olimpiasp` e importe o arquivo desejado.

### 4. Configuração

O padrão do XAMPP (`root` sem senha) já funciona sem nenhum ajuste.

Para produção, copie `includes/config.local.example.php` para
`includes/config.local.php` e preencha o que for usar — senha do banco,
servidor de e-mail, dados do controlador. **O arquivo copiado não vai para o
repositório**, e uma atualização do código nunca sobrescreve a configuração
de produção.

### 5. Acesso

Inicie Apache e MySQL no painel do XAMPP e abra:

```
http://localhost/sporthub_tcc1/
```

> A diretiva `ErrorDocument` do `.htaccess` aponta para `/sporthub_tcc1/404.php`.
> Se você renomear a pasta do projeto, ajuste esse caminho.

### 6. Conferir a instalação

```bash
C:\xampp\php\php.exe tests\executar.php
C:\xampp\php\php.exe scripts\preparar_producao.php
```

O primeiro roda 175 verificações automatizadas. O segundo lista, item por
item, o que ainda falta para o sistema sair do ambiente local.

---

## Contas de demonstração

| Perfil        | Usuário     | Senha           |
|---------------|-------------|-----------------|
| Administrador | `admin`     | `admin1234`     |
| Árbitro       | `arbitro`   | `arbitro1234`   |
| Árbitro       | `professor` | `professor1234` |
| Aluno         | `aluno`     | `aluno1234`     |

As senhas são gravadas no banco como **hash bcrypt** — não mais em texto puro,
como na versão anterior. E todas as contas nascem marcadas como provisórias:
**no primeiro login o sistema exige uma senha nova antes de liberar qualquer
tela** (SH-48). Não há como manter a instalação no ar com a senha de fábrica.

Para produção, `php scripts/preparar_producao.php --aplicar` remove as contas
de demonstração e define uma senha nova para a coordenação.

---
## Estrutura do projeto

```
sporthub_tcc1/
├── index.php                  Landing page pública
├── como-funciona.php          Guia completo de uso, etapa por etapa
├── planos.php                 Planos de assinatura anual e comparativo
├── assinar.php                Contratação da assinatura (com teste de 30 dias)
├── cadastro-arbitro.php       Credenciamento do profissional aplicador
├── contato.php                Formulário comercial e de suporte
├── privacidade.php            Política de Privacidade (LGPD)
├── termos.php                 Termos de Uso
├── cookies.php                Política de Cookies
├── lgpd.php                   Portal do titular (direitos do art. 18)
├── 404.php                    Página de erro
├── offline.html               Página servida pelo PWA quando a rede cai
├── manifest.webmanifest       Manifesto do aplicativo instalável
├── sw.js                      Service worker (cache e modo offline)
│
├── login.php                  Autenticação e cadastro de aluno
├── logout.php                 Encerramento de sessão
├── trocar_senha.php           Troca obrigatória da senha de fábrica
├── recuperar_senha.php        Pedido de redefinição por e-mail
├── redefinir_senha.php        Consumo do link de recuperação
├── verificar_2fa.php          Segunda etapa do login
├── seguranca.php              Ativação do segundo fator (QR Code + códigos)
├── perfil.php                 Perfil do usuário autenticado
│
├── admin/                     Painel da coordenação
│   ├── dashboard.php              Visão geral, pendências e preparação para produção
│   ├── times.php                  Times, elencos e jogadores
│   ├── team_logos.php             Escudos das turmas
│   ├── modalidades.php            Modalidades esportivas
│   ├── jogos.php                  Partidas (com busca e paginação)
│   ├── editar_jogo.php            Geração da fase de grupos
│   ├── chaveamento.php            Mata-mata: monta a chave e promove o vencedor
│   ├── arbitros.php               Cadastro manual de árbitros
│   ├── designar_jogos.php         Escala: horário, local e árbitro, com detecção de conflito
│   ├── solicitacoes_arbitros.php  Análise dos credenciamentos
│   ├── documento_arbitro.php      Entrega segura do documento anexado
│   ├── configuracao.php           Pontuação, desempate e duração da partida
│   ├── indicadores.php            Retrato do campeonato para a direção
│   ├── ocorrencias.php            Registro disciplinar (restrito à coordenação)
│   ├── galeria.php                Fotos das partidas, com consentimento e retenção
│   ├── assinaturas.php            Contratações e mensagens do site
│   ├── cobrancas.php              Financeiro das assinaturas (Pix copia e cola)
│   ├── emails.php                 O que o sistema tentou enviar, e se chegou
│   ├── lgpd.php                   Requisições de titulares e consentimentos
│   └── contas_lgpd.php            Exportar, anonimizar e eliminar contas
│
├── arbitro/                   Painel da arbitragem
│   ├── painel.php                 Partidas designadas
│   ├── registrar_resultado.php    Súmula digital (placar, eventos e estatísticas)
│   ├── resultados.php             Histórico
│   └── sumula_pdf.php             Súmula em PDF, pronta para assinar
│
├── aluno/                     Consulta
│   ├── painel.php                 Início
│   ├── jogos.php                  Calendário e placar ao vivo
│   ├── classificacao.php          Tabela de classificação
│   ├── resultados.php             Partidas encerradas
│   ├── estatisticas.php           Artilharia e fair play por atleta
│   ├── galeria.php                Mural de fotos (só as consentidas e no prazo)
│   ├── exportar.php               Exportação em CSV
│   ├── telao.php                  Modo telão para projetor
│   ├── ajax_jogo_stats.php        Estatísticas de uma partida (JSON)
│   └── ajax_telao.php             Dados do telão (JSON)
│
├── api/
│   ├── consentimento.php      Registro do consentimento de cookies
│   └── calendario.php         Agenda em iCalendar (.ics), protegida por token
│
├── includes/
│   ├── config.php             Conexão, sessão, CSRF, CSP com nonce e helpers
│   ├── config.local.example.php  Modelo da configuração de produção
│   ├── consultas.php          Classificação, resultados, agenda e conflitos
│   ├── campeonato.php         Chaveamento e estatística individual
│   ├── listagem.php           Busca e paginação reaproveitáveis
│   ├── email.php              Cliente SMTP próprio (STARTTLS, AUTH LOGIN/PLAIN)
│   ├── totp.php               Segundo fator (RFC 6238)
│   ├── qrcode.php             Gerador de QR Code em SVG (ISO/IEC 18004)
│   ├── pdf.php                Escritor de PDF mínimo
│   ├── pagamento.php          Cobrança e Pix copia e cola (BR Code)
│   ├── lgpd_conta.php         Anonimização, eliminação e portabilidade
│   ├── auth_layout.php        Moldura das telas de credencial
│   ├── site_header.php        Cabeçalho das páginas públicas
│   ├── site_footer.php        Rodapé público + banner LGPD
│   ├── header.php             Cabeçalho do painel autenticado
│   └── footer.php             Rodapé do painel autenticado
│
├── css/
│   ├── site.css               Design system do site público
│   ├── style.css              Design system do painel
│   ├── glass.css              Camada "liquid glass" (aditiva)
│   └── u.css                  Classes utilitárias (substituem os style="" removidos)
│
├── js/
│   ├── sporthub-ui.js             Brilho, reflexo e revelação (enfeite)
│   └── sporthub-comportamento.js  Confirmação, filtros e barras (comportamento)
│
├── tests/
│   ├── executar.php           Executor dos testes, sem dependência externa
│   └── casos/                 175 verificações de funções puras
│
├── docs/
│   ├── metodologia-tcc.md         Capítulo de metodologia
│   ├── manual-do-usuario.md       Manual da escola
│   ├── roteiro-teste-aceitacao.md Roteiro manual por perfil
│   ├── roteiro-defesa.md          Estrutura dos slides e da fala
│   ├── roteiro-video.md           Roteiro do vídeo de 3 minutos
│   ├── publicacao.md              Passo a passo para produção
│   └── quadro-sporthub.md         Quadro do projeto, pronto para o Trello
│
├── logs/                      error_log e e-mails do modo de desenvolvimento
│
├── scripts/
│   ├── migration_v2.sql       Credenciamento, assinaturas e LGPD
│   ├── migration_v3.sql       Chaveamento, 2FA, cobrança, galeria, disciplinar
│   ├── usuario_banco.sql      Usuário de banco com privilégio mínimo
│   ├── preparar_producao.php  Checklist executável antes de publicar
│   ├── backup.bat / .sh       Rotina de backup
│   └── restaurar.bat          Restauração e teste de restauração
│
├── img/                       Imagens e escudos dos times (execução bloqueada)
│
├── uploads/                   Arquivos enviados (execução de scripts bloqueada)
│   ├── profile_photos/
│   ├── credenciamento/        Documentos dos árbitros (acesso só via PHP)
│   └── galeria/               Fotos das partidas
│
└── bd.sql                     Schema completo para instalação limpa
```

---
## Perfis de acesso

| Perfil | Enxerga | Pode alterar |
|---|---|---|
| **Administrador** (coordenação) | Todo o campeonato | Modalidades, times, jogadores, jogos, chaveamento, escala, regras, árbitros, credenciamentos, assinaturas, cobranças, galeria, registro disciplinar e LGPD |
| **Árbitro** (profissional aplicador) | Apenas os jogos designados a ele | Súmula das próprias partidas: placar, gols, cartões, substituições |
| **Aluno** (consulta) | Calendário, placares, classificação, resultados, artilharia e mural de fotos | Somente o próprio perfil |

---

## Módulos

### Credenciamento de árbitros

O profissional aplicador se cadastra em `cadastro-arbitro.php` informando
formação, registro profissional (CREF ou federação), experiência, modalidades
que domina e um documento comprobatório.

Fluxo:

1. Solicitação registrada com **protocolo** (`ARB-AAAA-NNNN`) e status `recebida`.
2. Coordenação analisa em `admin/solicitacoes_arbitros.php`.
3. **Aprovada** → o sistema cria o usuário de árbitro automaticamente, gera senha
   provisória (exibida uma única vez) e emite a credencial com validade de 1 ano.
4. **Recusada** → o documento anexado é eliminado do servidor, já que a
   finalidade do tratamento se encerrou.

O solicitante acompanha o andamento pelo protocolo, sem precisar de login.

Proteções: validação de dígitos do CPF, bloqueio de solicitação duplicada em
análise, verificação do MIME real do arquivo enviado, limite de 5 MB e nome de
arquivo aleatório.

### Assinaturas

Contratação em `assinar.php`, acompanhamento em `admin/assinaturas.php`.
Cada contratação gera um código (`SH-AAAA-NNNN`), inicia com status `trial` por
30 dias e registra o aceite dos termos com data, hora e IP.

**Nenhum dado de cartão é coletado pelo sistema** — a escola apenas escolhe a
forma de pagamento preferida.

### Portal LGPD

`lgpd.php` recebe requisições de titulares (art. 18), gera protocolo
(`LGPD-AAAA-NNNN`) e calcula o prazo legal de 15 dias corridos.
`admin/lgpd.php` mostra as requisições ordenadas por prazo, alerta sobre as que
vencem em até 3 dias e consolida o registro de consentimentos.

### Limites do plano contratado

Os planos sempre trouxeram `limite_times`, `limite_modalidades` e
`limite_arbitros` na tabela `planos`, mas nada era verificado. Agora
`sh_pode_criar()` (em `includes/config.php`) trava a criação nos três pontos em
que ela acontece: cadastro de time, de modalidade, de árbitro pelo painel e
aprovação de um credenciamento.

Três regras:

- `NULL` significa **ilimitado** — é assim que a tabela já modelava o plano
  Institucional. Nunca zero.
- **Sem assinatura vigente, não há limite.** Uma instalação nova precisa
  funcionar antes de alguém contratar um plano.
- O limite trava a criação e **nunca remove nem esconde** o que já existe. Se a
  escola rebaixar o plano, os times excedentes continuam no ar; apenas não é
  possível criar mais até a contagem voltar ao teto.

O painel avisa quando restam poucos lugares (dois, ou os últimos 15% do teto) e
bloqueia o botão quando o limite chega.

### Exportação e calendário

- **CSV** (`aluno/exportar.php`): classificação e resultados, com separador
  ponto e vírgula e BOM UTF-8 — é o formato que o Excel em português abre sem
  embaralhar colunas nem acentos. Texto que comece por `=`, `+`, `-` ou `@`
  recebe um apóstrofo à frente, para não ser lido como fórmula.
- **iCalendar** (`api/calendario.php`): feed `.ics` assinável pelo Google
  Agenda, Outlook ou pelo celular. Nenhum desses aplicativos sabe fazer login,
  então o endereço não exige sessão — é protegido por um token opaco derivado
  por HMAC de `SH_SEGREDO_FEED`. O conteúdo é o mesmo calendário que vai para o
  mural da escola (times, horário e local); nenhum dado pessoal de aluno.
  Trocar `SH_SEGREDO_FEED` invalida todos os endereços de uma vez.

### Modo telão

`aluno/telao.php` é a tela para o projetor do ginásio: sem menu, alto
contraste, tema escuro fixo e tipografia em `vw`. Alterna sozinha entre as
partidas do momento e a classificação (14 s cada), busca os dados a cada 15 s e
esconde o cursor depois de 4 s parado. `espaço` pausa, `←` `→` trocam de cena,
`F` entra em tela cheia. Quando não há nada agendado, passa a exibir os últimos
resultados em vez de uma tela vazia.

---

### Escala de arbitragem, com detecção de conflito

`admin/designar_jogos.php` define **árbitro, data, hora e local** de uma
partida — as partidas nascem sem horário, porque o gerador de confrontos cria
só os pares.

Antes de gravar, o sistema procura choques de agenda, e separa por gravidade:

| Situação | O que acontece |
|---|---|
| O mesmo árbitro já tem partida no horário | **Impedimento**: recusa, nomeando o outro jogo |
| Um dos times já joga no horário | **Impedimento**: recusa |
| Já há partida no mesmo local e horário | **Aviso**: permite confirmar |

O último é apenas aviso porque "Quadra 1" às vezes é o nome de um ginásio
inteiro, onde duas partidas convivem. A janela de sobreposição usa a duração da
partida configurada em **Regras do campeonato**.

### Regras do campeonato

`admin/configuracao.php` transforma em dado o que eram constantes dentro do SQL:

- pontos por vitória, empate e derrota;
- a **ordem** dos critérios de desempate — saldo, gols marcados, menos gols
  sofridos, vitórias, confronto direto, fair play (menos cartões);
- a duração da partida, usada pela detecção de conflito.

Vale para toda tela que mostra classificação, porque todas passam pela mesma
`sh_classificacao()`. A ordem alfabética entra sempre por último, para que a
tabela não troque sozinha entre dois carregamentos.

### Chaveamento de mata-mata

`admin/chaveamento.php` monta a fase eliminatória a partir da classificação: o
1º enfrenta o último classificado, o 2º pega o penúltimo. Quando o número de
classificados não é potência de 2, os melhores recebem **passagem direta**.

A árvore inteira é criada de uma vez, e cada posição já nasce sabendo para onde
manda o vencedor. Ao encerrar a súmula, o vencedor sobe sozinho.

**Empate no mata-mata não promove ninguém**, de propósito: a regra de desempate
(prorrogação, pênaltis, melhor campanha) é decisão da escola, não do software.

### Súmula digital e PDF

A súmula registra placar, **eventos por jogador** (gol, cartão amarelo, cartão
vermelho, substituição, com minuto) e as estatísticas do time. Reabrir a súmula
mostra tudo o que já foi lançado; gravar de novo não duplica nada.

`arbitro/sumula_pdf.php` gera o documento para imprimir, assinar e arquivar,
com placar, eventos, ocorrências disciplinares, observações e as linhas de
assinatura. O PDF é escrito por `includes/pdf.php`, sem biblioteca externa.

### Estatísticas dos atletas

`aluno/estatisticas.php` calcula artilharia e fair play a partir dos eventos da
súmula — nenhuma coleta nova de dado. Limite deliberado: mostra desempenho
**esportivo**, nunca frequência, nota ou observação de comportamento. A
ocorrência disciplinar fica restrita à coordenação exatamente por isso.

### Indicadores para a direção

`admin/indicadores.php` responde a outra pergunta que o dashboard: "o
interclasse valeu a pena?". Percentual do calendário realizado, participação
por turma, modalidade mais disputada, ritmo por mês e uso do plano contratado.
Tudo agregado — nenhum nome de aluno aparece.

### Registro disciplinar

`admin/ocorrencias.php` registra advertência, suspensão e a providência tomada.
O cartão vermelho já existia como evento da súmula, mas não havia onde
registrar a consequência.

Acesso restrito à coordenação: é informação disciplinar sobre menor de idade,
com finalidade estreita. O aluno vê cartões (que aconteceram em quadra à vista
de todos), nunca o que a escola decidiu a respeito.

### Galeria das partidas

`admin/galeria.php` e `aluno/galeria.php`. Foto de aluno é dado pessoal, e a
maioria dos participantes é menor de idade (LGPD, art. 14). Por isso:

- nenhuma foto nasce pública; sem consentimento registrado ela fica restrita à
  coordenação;
- marcar o consentimento grava data, versão do texto e IP em
  `lgpd_consentimentos` — a comprovação que o art. 8º, §1º exige;
- toda foto tem **prazo de guarda**. Vencido, ela sai do mural sozinha;
- a imagem é reencodada em PNG, o que descarta os metadados EXIF — inclusive a
  localização de onde a foto foi tirada.

### Comunicação por e-mail

`includes/email.php` é um cliente SMTP escrito sobre sockets, com STARTTLS, SSL
implícito e AUTH LOGIN/PLAIN. Três modos:

| Modo | Quando | O que faz |
|---|---|---|
| `smtp` | `SH_SMTP_HOST` preenchido | Entrega de verdade |
| `mail` | `SH_EMAIL_MODO = 'mail'` | Usa a função `mail()` do PHP |
| `registro` | **padrão em desenvolvimento** | Grava em `logs/emails/`; nada sai da máquina |

O modo `registro` é o que mantém todo o fluxo testável num XAMPP sem servidor
de e-mail — inclusive a recuperação de senha. `admin/emails.php` mostra o que o
sistema tentou enviar e o motivo de cada falha. **O corpo não é gravado**:
guardar o texto de uma senha provisória anularia o cuidado de tê-la feito de
uso único.

### Verificação em duas etapas

`seguranca.php`. TOTP (RFC 6238), implementado em `includes/totp.php` e
conferido nos testes contra os vetores oficiais do RFC. O QR Code é desenhado
por `includes/qrcode.php` — escrito à mão porque as alternativas seriam
carregar uma biblioteca de CDN (que a própria CSP bloqueia) ou enviar o segredo
do 2FA à API de gráficos do Google, anulando o que a funcionalidade protege.

Oito **códigos de recuperação** são entregues uma única vez na ativação, e
guardados apenas como hash. Sem eles, perder o celular significa perder a conta
que administra o campeonato.

### Recuperação de senha

`recuperar_senha.php` e `redefinir_senha.php`. O banco guarda o **hash** do
token, nunca o token: um vazamento não entrega os links em aberto. Validade de
30 minutos, uso único, e a resposta na tela é **a mesma** para e-mail existente
e inexistente — senão a tela viraria um verificador de contas.

### Direitos do titular

`admin/contas_lgpd.php` executa o que `lgpd.php` apenas recebe:

- **exportar** os dados em JSON (acesso e portabilidade, art. 18, II e V);
- **anonimizar** — remove nome, e-mail, CPF, telefone e foto, e a conta deixa
  de entrar;
- **eliminar** — só quando não há súmula nem auditoria ligada à conta.

Anonimizar é o caminho normal, e não uma concessão: apagar o árbitro destruiria
a integridade das súmulas que ele assinou, que interessam a todos os outros
times. O art. 16 autoriza conservar para cumprir obrigação legal, e o art. 12
diz que dado anonimizado deixa de ser dado pessoal.

### Cobrança das assinaturas

`admin/cobrancas.php` e `includes/pagamento.php`. A contratação passou a gerar
uma cobrança com valor, vencimento e baixa registrada — antes ela registrava a
assinatura e parava ali.

Três modos: `manual`, `pix` (monta o copia e cola no padrão BR Code do Banco
Central, **sem exigir gateway**) e `gateway` (estrutura pronta, ligada quando
houver credencial). **Nenhum dado de cartão** passa pelo sistema.

### Aplicativo instalável (PWA)

`manifest.webmanifest` e `sw.js`. No celular, o navegador oferece "Adicionar à
tela de início"; os arquivos de aparência vêm do cache, e `offline.html`
aparece quando a rede cai.

Duas coisas de fora, de propósito: **notificação push** (exige servidor VAPID) e
**cache de página** (placar e classificação mudam durante o jogo; servir versão
guardada seria pior do que não abrir). E nada disso é registrado em `http://` —
o navegador recusa. É mais um motivo para o HTTPS.


## Modelo de assinatura

Cobrança **anual**, por instituição, com 30 dias de teste gratuito e sem
exigência de cartão de crédito para começar.

| Plano | Valor anual | Equivalente mensal | Times | Modalidades | Árbitros |
|---|---|---|---|---|---|
| Essencial | R$ 1.188,00 | R$ 99,00 | 12 | 3 | 5 |
| **Pro** | **R$ 2.388,00** | **R$ 199,00** | 40 | Ilimitadas | 20 |
| Institucional | R$ 4.788,00 | R$ 399,00 | Ilimitados | Ilimitadas | Ilimitados |

Alunos, jogos e súmulas são ilimitados em todos os planos.

Os preços vivem na tabela `planos` — para alterá-los, edite o registro no banco;
as páginas de planos e contratação leem de lá automaticamente.

---

## Conformidade com a LGPD

| Exigência | Onde está implementada |
|---|---|
| Base legal declarada por finalidade | `privacidade.php`, seção 4 |
| Consentimento comprovável (art. 8º, §1º) | Tabela `lgpd_consentimentos` + `api/consentimento.php` |
| Direitos do titular (art. 18) | `lgpd.php` (pedido) e `admin/contas_lgpd.php` (execução) |
| Acesso e portabilidade (art. 18, II e V) | Exportação em JSON, em `admin/contas_lgpd.php` |
| Anonimização e eliminação (arts. 12 e 16) | `includes/lgpd_conta.php` |
| Prazo de resposta de 15 dias (art. 19, II) | Campo `prazo_em` com alerta no painel |
| Proteção reforçada a menores (art. 14) | Coleta mínima; galeria exige consentimento específico e prazo de guarda |
| Prestação de contas (art. 6º, X) | Tabela `auditoria` + registro de comunicações em `emails_enviados` |
| Encarregado (DPO) identificado (art. 41) | `SH_DPO_NOME` e `SH_EMAIL_DPO` em `config.local.php` |
| Controlador identificado (art. 9º, I) | `SH_CONTROLADOR_*`, com aviso no painel enquanto não preenchido |
| Eliminação após fim da finalidade | Documento apagado na recusa; foto eliminada no fim do prazo |
| Cookies com consentimento granular | Banner em `includes/site_footer.php` + `cookies.php` |

Versão dos documentos legais: constante `SH_VERSAO_POLITICA`. Ao alterar um
texto legal, incremente a versão — o banner de consentimento volta a ser
exibido para todos.

> **Enquanto os dados do controlador forem os de fábrica**, o painel da
> coordenação mostra a pendência e as páginas legais exibem "a preencher" em
> vez de um dado inventado. Lacuna declarada é melhor do que dado falso.
---

## Segurança

### Autenticação e sessão

- Senhas com `password_hash()` / `password_verify()` (bcrypt).
- Cookie de sessão com `HttpOnly` (invisível ao JavaScript), `SameSite=Lax`
  (não é enviado em requisições de outro site — base do CSRF) e `Secure`
  automático quando o acesso é por HTTPS.
- `session.use_strict_mode` ligado: o PHP recusa identificadores de sessão
  inventados pelo atacante (fixação de sessão).
- `session_regenerate_id()` na autenticação, a cada 30 minutos de uso e no
  encerramento por inatividade; sessão e cookie destruídos no logout.
- Expiração automática após 2 h sem atividade, com aviso na tela de login.
- Bloqueio após 5 tentativas erradas na mesma sessão **e** 15 tentativas por IP
  em 15 minutos (contadas na tabela `auditoria`, então apagar os cookies não
  zera o contador).
- Contas com status diferente de `ativo` (suspensa, anonimizada) não entram,
  mesmo com a senha correta.

### Autorização

- Perfil verificado por `exigirPerfil()` **antes de qualquer saída HTML** — se o
  cabeçalho fosse impresso primeiro, o `header('Location: ...')` não teria
  efeito e a página continuaria visível para quem não tem permissão.
- Endpoints internos (`aluno/ajax_jogo_stats.php`) respondem `401` sem sessão.
- Na súmula, o `arbitro_id` entra no `WHERE` do `UPDATE`: um árbitro não grava
  resultado de jogo que não é dele.

### Entrada e saída de dados

- Consultas exclusivamente **parametrizadas** (PDO, `EMULATE_PREPARES = false`).
- Saída escapada com o helper `e()`; identificadores vindos da URL convertidos
  para inteiro antes de voltarem impressos no HTML.
- Validação no servidor (nome, tamanho, gênero, número de camisa) — o `required`
  do HTML só protege quem usa o formulário.
- Token **CSRF** obrigatório em todos os formulários e nas exclusões por link.
- Mensagens de exceção nunca vão para a tela: vão para o `error_log`, e o
  usuário recebe um texto genérico.

### Uploads e arquivos

- Tipo real verificado com `getimagesize()`/`finfo` (nunca a extensão enviada),
  limite de 2 MB, nome de arquivo definido pelo servidor.
- Escudos são reencodados em PNG — o que descarta código embutido em imagem.
- Execução de scripts bloqueada por `.htaccess` em `uploads/` e em `img/`.
- Documentos de credenciamento só são servidos por `admin/documento_arbitro.php`,
  com verificação de `realpath` contra path traversal.

### Cabeçalhos HTTP

Enviados pelo PHP em `sh_headers_seguranca()` (e reforçados no `.htaccess`), de
modo que valem mesmo sem o `mod_headers` do Apache:

| Cabeçalho | Para quê |
|---|---|
| `Content-Security-Policy` | Impede o carregamento de scripts de origens não autorizadas — é o que evita que um XSS vire roubo de sessão |
| `X-Content-Type-Options: nosniff` | O navegador não "adivinha" o tipo do arquivo |
| `X-Frame-Options: SAMEORIGIN` | Bloqueia clickjacking |
| `Referrer-Policy` | Não vaza a URL interna para sites externos |
| `Permissions-Policy` | Desliga câmera, microfone e geolocalização |
| `Strict-Transport-Security` | Só em HTTPS: obriga o navegador a manter a conexão segura |

### Content Security Policy sem `unsafe-inline` (SH-37)

Durante três sprints a política dizia `script-src 'self' 'unsafe-inline'`, o
que significa: o navegador executava **qualquer** `<script>` que aparecesse no
HTML — inclusive um injetado por XSS. É o buraco que anula boa parte do valor
de ter CSP.

A correção usa **nonce**: um valor aleatório, novo a cada resposta, presente no
cabeçalho e no atributo de cada bloco embutido que nós mesmos escrevemos. O
atacante não tem como adivinhá-lo, porque injeta o HTML antes de o nonce
existir. E ao declarar um nonce, o navegador passa a **ignorar**
`'unsafe-inline'` — por isso ele pôde sair.

Nonce, porém, não cobre atributo. Tirar `'unsafe-inline'` também de `style-src`
exigiu eliminar todo `style=""` e todo `onclick=""` do projeto:

| Antes | Depois | Quantidade |
|---|---|---|
| `style="..."` no HTML | Classes utilitárias em `css/u.css` | 174 substituições, 25 arquivos |
| `onclick`, `onchange`, `onfocus`, `onerror` | Atributos `data-*` + delegação em `js/sporthub-comportamento.js` | 16 |
| `<script>` e `<style>` embutidos | Mesmas tags, com `nonce` | 15 |

Larguras que dependem de dado (as barras dos indicadores) são escritas pelo
CSSOM a partir de `data-largura`: estilo aplicado por script já autorizado não
passa pela política de `style-src`. Sem JavaScript, a barra fica vazia e o
número ao lado dela continua dizendo tudo.

Como conferir: abra o console do navegador em qualquer tela. **Zero** violações
de CSP, e o cabeçalho traz `nonce-` sem `unsafe-inline`.
---
## Testes

```bash
C:\xampp\php\php.exe tests\executar.php          # tudo
C:\xampp\php\php.exe tests\executar.php totp     # um arquivo só
```

**175 verificações**, sem PHPUnit e sem Composer — o projeto se propõe a rodar
num XAMPP recém-instalado, e uma dependência de 30 MB seria a primeira coisa a
quebrar na máquina de outra pessoa. Os testes rodam **sem MySQL ligado**, de
propósito: teste que só passa com o ambiente inteiro no ar é teste que ninguém
roda.

### O que é testado, e por quê

O critério é: **automatizar o que falha em silêncio.** Um botão quebrado
aparece no primeiro uso. Um dígito verificador de CPF errado, um código TOTP
fora de sincronia ou um desempate na ordem trocada não avisam nada — produzem
um resultado plausível e incorreto.

| Arquivo | Cobre |
|---|---|
| `01-validacao.php` | CPF, política de senha, escape de curingas do LIKE, formatação de valores |
| `02-totp.php` | Segundo fator, contra os **vetores oficiais do RFC 6238** |
| `03-qrcode.php` | Reed-Solomon, posicionamento, máscara e informação de formato |
| `04-pagamento.php` | CRC do Pix contra o vetor canônico, e a montagem do BR Code |
| `05-campeonato.php` | Ordem dos critérios de desempate e o desenho da chave |
| `06-pdf-email.php` | Integridade da tabela xref do PDF e codificação dos cabeçalhos de e-mail |

Onde existe vetor de referência externo, ele é usado. Testar contra a própria
saída do sistema apenas confirmaria que ele é consistente com o próprio erro.

### Um achado

Ao escrever a suíte, apareceu um defeito que estava em produção: a ordenação
alfabética usava `strcmp`, que compara bytes. Em UTF-8, todo nome acentuado
começa com um byte alto — de modo que **"Águias" e "Leões do 9ºA" apareciam
depois de "Zulu"** na tabela de classificação. Num interclasse brasileiro isso
é a regra, não a exceção, e nenhuma inspeção visual tinha percebido.

A correção está em `sh_comparar_nome()`, com teste de regressão em
`05-campeonato.php`.

### Teste manual

O que depende de banco, sessão e navegador está em
`docs/roteiro-teste-aceitacao.md`: um roteiro por perfil, percorrido antes de
cada entrega. Leva cerca de 40 minutos.

---


## Tema claro e escuro

Todas as telas — site público, tela de login e painel — funcionam nos dois
temas, com a mesma paleta.

- **Onde trocar:** botão de sol/lua na barra do site público (e no menu mobile),
  botão fixo no canto da tela de login e item "Tema escuro/claro" no menu do
  usuário dentro do painel.
- **Como é decidido:** preferência salva pelo usuário → se não houver, o tema do
  sistema operacional (`prefers-color-scheme`).
- **Onde fica guardado:** `localStorage` (chave `sporthub-tema`) espelhado num
  cookie de mesmo nome. O cookie existe para o PHP já montar o HTML no tema
  certo (`sh_tema_attr()`), evitando o "flash" branco antes do JavaScript rodar.
  É preferência de exibição, não dado pessoal — por isso não depende do banner
  de consentimento.
- **Como é implementado:** todas as cores são variáveis CSS declaradas em três
  blocos — `:root` (forma e tipografia, iguais nos dois temas),
  `:root, [data-theme="light"]` e `[data-theme="dark"]`. Trocar o tema é trocar
  um atributo no `<html>`; nenhuma regra de layout é duplicada.
- Helpers em `includes/config.php`: `sh_tema()`, `sh_tema_attr()`,
  `sh_tema_boot()` (script anti-flash no `<head>`) e `sh_tema_botao()`.

Blocos que já eram escuros no tema claro (rodapé, placar do hero, CTA, banner
LGPD) continuam escuros no tema escuro, só que um tom **acima** do fundo da
página — assim continuam se lendo como painéis elevados em vez de sumirem.

---

## Camada visual "liquid glass"

`css/glass.css` é carregado **depois** de `site.css` e de `style.css` e troca a
matéria das superfícies: de papel opaco para vidro translúcido. Não redefine
layout nenhum — só fundo, borda, sombra e movimento.

| Peça | Onde |
|---|---|
| Tokens de vidro (preenchimento, aresta, desfoque) por tema | `:root` e `[data-theme="dark"]` em `glass.css` |
| Aurora animada + malha técnica do fundo | `body::before` e `body::after` |
| Brilho que acompanha o ponteiro | `--gx` / `--gy`, escritos por `js/sporthub-ui.js` |
| Reflexo que cruza o cartão no hover | `.sh-sheen` + `.sh-sheen-bar` |
| Ondulação do clique nos botões | `.sh-ripple` |
| Contagem dos números ao entrarem na tela | `.stat-number`, `.hero-meta-num` |

Três garantias:

1. **Degradação limpa.** Toda a translucidez mora dentro de
   `@supports (backdrop-filter: …)`. Onde o navegador não suportar, os cartões
   continuam sólidos exatamente como antes.
2. **Legibilidade acima do efeito.** As opacidades foram escolhidas para manter
   contraste AA nos dois temas; o vidro nunca fica transparente a ponto de o
   texto disputar com o fundo. Em telas pequenas o desfoque cai e o
   preenchimento sobe, o que também alivia o custo do `backdrop-filter`.
3. **Movimento opcional.** Tudo é `transform`/`opacity` (composto na GPU) e
   some inteiro em `prefers-reduced-motion: reduce`.

`js/sporthub-ui.js` é enfeite sobre um HTML já completo: roda inteiro dentro de
um `try/catch` e todo efeito começa desligado. Se o script não carregar, a
página continua como o servidor entregou.

Ele também observa o atributo `data-theme` do `<html>` e desliga as transições
durante a troca de tema. Sem isso, as cores levavam meio segundo para virar e,
nos blocos fora da área visível, o navegador congelava a interpolação pela
metade — cartões ficavam com a borda do tema anterior até que se rolasse até
eles.

---

## Antes de colocar em produção

A lista abaixo **não depende mais de memória**. Rode:

```bash
C:\xampp\php\php.exe scripts\preparar_producao.php
```

Sem argumento, o script apenas verifica e mostra o que falta — é seguro rodar a
qualquer momento. Com `--aplicar`, ele remove as contas de demonstração e define
uma senha nova para a coordenação.

O painel da coordenação mostra as mesmas pendências num cartão que **some
sozinho** conforme cada item é resolvido.

### O que o script verifica

| # | Item | Cartão | Estado |
|---|---|---|---|
| 1 | `includes/config.local.php` existe | SH-44/49 | Resolvido pela configuração |
| 2 | Usuário de banco dedicado, com senha | SH-49 | `scripts/usuario_banco.sql` pronto |
| 3 | Migração v3 aplicada | — | `scripts/migration_v3.sql` |
| 4 | Nenhuma senha fora do bcrypt | SH-48 | Resolvido no código |
| 5 | Contas de demonstração removidas | SH-48 | `--aplicar` remove |
| 6 | Segundo fator na coordenação | SH-65 | Recomendado, não obrigatório |
| 7 | Segredo do calendário persistido | SH-84 | Gerado sozinho na 1ª execução |
| 8 | Servidor de e-mail configurado | SH-42 | Depende de credencial |
| 9 | Dados do controlador e do DPO | SH-44 | Depende da escola |
| 10 | Modo de cobrança configurado | SH-41 | `pix` funciona sem contrato |
| 11 | Extensões do PHP, com destaque para a `gd` | — | Uma linha no `php.ini` |
| 12 | Pastas graváveis | — | `logs/`, `uploads/`, `img/times/` |
| 13 | Backup registrado | SH-51 | `scripts/backup.bat` / `.sh` |

Falta um item que o script não tem como verificar de dentro: **servir por
HTTPS** (SH-43). O `.htaccess` traz o bloco de redirecionamento pronto, apenas
comentado — descomente ao publicar num domínio com certificado. Sem HTTPS, o
cookie de sessão não é marcado como `Secure`, o `Strict-Transport-Security` não
é enviado e o navegador **recusa** registrar o service worker do PWA.

O passo a passo completo da publicação está em **`docs/publicacao.md`**.

### O que continua dependendo de terceiros

Registrado com franqueza, porque são os cartões que permanecem em "Bloqueado":

- **Gateway de pagamento (SH-41).** O código está pronto — criação de cobrança,
  baixa com registro de quem confirmou, verificação de assinatura HMAC do
  webhook. Falta conta aprovada em adquirente e endereço público em HTTPS.
  Enquanto isso, o modo `pix` funciona hoje e sem contrato nenhum.
- **Notificação push (SH-69).** O PWA instala e funciona offline. A notificação
  de início de partida exige servidor VAPID — é um serviço à parte, não uma
  linha de código faltando.
- **Hospedagem com HTTPS (SH-43).** Configuração pronta; falta contratar.
- **Dados jurídicos do controlador (SH-44).** Precisam vir da escola.

### Exibição de erros

Já é automática: `config.php` trata como desenvolvimento apenas o acesso local
(`localhost`, `127.0.0.1`, `.local`, `.test`, CLI) e desliga `display_errors` em
qualquer outro host, mantendo `log_errors` sempre ligado. Para forçar um dos
modos, defina `SH_DEBUG` antes de incluir o `config.php`, ou a variável de
ambiente `SPORTHUB_DEBUG` (`1` liga, `0` desliga). Sem destino configurado no
servidor, o log vai para `logs/php-error.log` — pasta bloqueada por `.htaccess`.

Todo `catch` do projeto passa por `sh_log_excecao()`, que grava sempre os mesmos
campos e devolve uma **referência curta** que também aparece na tela do usuário.
Quando alguém diz "deu erro, referência 7F3A21", a linha exata do log é
encontrada com um `grep`.

---

## Documentação

| Arquivo | Para quem |
|---|---|
| `docs/manual-do-usuario.md` | Coordenação, arbitragem e alunos |
| `docs/roteiro-teste-aceitacao.md` | Quem verifica antes de entregar |
| `docs/publicacao.md` | Quem vai publicar em servidor |
| `docs/metodologia-tcc.md` | Capítulo de metodologia da monografia |
| `docs/roteiro-defesa.md` | Estrutura dos slides e da apresentação |
| `docs/roteiro-video.md` | Roteiro do vídeo de 3 minutos |
| `docs/quadro-sporthub.md` | Quem mantém o quadro do projeto no Trello |

---

## Licença e créditos

Projeto acadêmico desenvolvido como Trabalho de Conclusão de Curso.
Tipografia: Space Grotesk e DM Sans (Google Fonts). Ícones: Font Awesome 6.
