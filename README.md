# SportHub — Sistema de Gestão de Campeonatos Interclasse

Plataforma web para escolas organizarem campeonatos interclasse do início ao fim:
inscrição de times, credenciamento de árbitros, agendamento de jogos, súmula
digital, placar ao vivo e classificação automática — com tratamento de dados
pessoais em conformidade com a LGPD (Lei nº 13.709/2018).

Projeto de TCC. Stack: **PHP 8 + MySQL/MariaDB + HTML/CSS/JS** (sem frameworks),
rodando em **XAMPP**.

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
- [Tema claro e escuro](#tema-claro-e-escuro)
- [Antes de colocar em produção](#antes-de-colocar-em-produção)

---

## Instalação

### 1. Requisitos

- XAMPP com PHP 8.0+ e MySQL/MariaDB 10.4+
- Extensões PHP: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`

### 2. Arquivos

Copie o projeto para `C:\xampp\htdocs\sporthub_tcc02`.

### 3. Banco de dados

**Instalação limpa** (apaga e recria o banco `olimpiasp`):

```bash
C:\xampp\mysql\bin\mysql.exe -u root < bd.sql
```

**Atualizando uma instalação que já existe** (preserva os dados):

```bash
C:\xampp\mysql\bin\mysql.exe -u root olimpiasp < scripts/migration_v2.sql
```

Pelo phpMyAdmin: selecione o banco `olimpiasp` e importe o arquivo desejado.

### 4. Configuração

As credenciais do banco ficam em `includes/config.php`. O padrão do XAMPP
(`root` sem senha) já vem configurado.

### 5. Acesso

Inicie Apache e MySQL no painel do XAMPP e abra:

```
http://localhost/sporthub_tcc02/
```

> A diretiva `ErrorDocument` do `.htaccess` aponta para `/sporthub_tcc02/404.php`.
> Se você renomear a pasta do projeto, ajuste esse caminho.

---

## Contas de demonstração

| Perfil        | Usuário     | Senha           |
|---------------|-------------|-----------------|
| Administrador | `admin`     | `admin1234`     |
| Árbitro       | `arbitro`   | `arbitro1234`   |
| Árbitro       | `professor` | `professor1234` |
| Aluno         | `aluno`     | `aluno1234`     |

As senhas do seed estão em texto puro apenas para facilitar o primeiro acesso.
No primeiro login válido, cada uma é convertida automaticamente em hash
(`password_hash`). **Troque todas antes de qualquer uso real.**

---

## Estrutura do projeto

```
sporthub_tcc02/
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
├── login.php                  Autenticação e cadastro de aluno
├── logout.php                 Encerramento de sessão
├── perfil.php                 Perfil do usuário autenticado
│
├── admin/                     Painel da coordenação
│   ├── dashboard.php              Visão geral e pendências
│   ├── times.php                  Times, elencos e jogadores
│   ├── team_logos.php             Escudos das turmas
│   ├── modalidades.php            Modalidades esportivas
│   ├── jogos.php                  Agendamento de partidas
│   ├── arbitros.php               Cadastro manual e designação
│   ├── designar_jogos.php         Escala de arbitragem
│   ├── solicitacoes_arbitros.php  Análise dos credenciamentos
│   ├── documento_arbitro.php      Entrega segura do documento anexado
│   ├── assinaturas.php            Contratações e mensagens do site
│   └── lgpd.php                   Requisições de titulares e consentimentos
│
├── arbitro/                   Painel da arbitragem (súmula digital)
├── aluno/                     Consulta de jogos, classificação e resultados
│
├── api/
│   └── consentimento.php      Registro do consentimento de cookies
│
├── includes/
│   ├── config.php             Conexão, sessão, CSRF e helpers compartilhados
│   ├── site_header.php        Cabeçalho das páginas públicas
│   ├── site_footer.php        Rodapé público + banner LGPD
│   ├── header.php             Cabeçalho do painel autenticado
│   └── footer.php             Rodapé do painel autenticado
│
├── css/
│   ├── site.css               Design system do site público
│   └── style.css              Design system do painel
│
├── scripts/
│   └── migration_v2.sql       Migração incremental (credenciamento, assinaturas, LGPD)
│
├── img/                       Imagens e escudos dos times (execução bloqueada)
│
├── uploads/                   Arquivos enviados (execução de scripts bloqueada)
│   ├── profile_photos/
│   └── credenciamento/        Documentos dos árbitros (acesso só via PHP)
│
└── bd.sql                     Schema completo para instalação limpa
```

---

## Perfis de acesso

| Perfil | Enxerga | Pode alterar |
|---|---|---|
| **Administrador** (coordenação) | Todo o campeonato | Modalidades, times, jogadores, jogos, árbitros, credenciamentos, assinaturas, LGPD |
| **Árbitro** (profissional aplicador) | Apenas os jogos designados a ele | Súmula das próprias partidas: placar, gols, cartões, substituições |
| **Aluno** (consulta) | Calendário, placares, classificação, resultados | Somente o próprio perfil |

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

---

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
| Direitos do titular (art. 18) | `lgpd.php` e `admin/lgpd.php` |
| Prazo de resposta de 15 dias (art. 19, II) | Campo `prazo_em` com alerta no painel |
| Proteção reforçada a menores (art. 14) | `privacidade.php`, seção 5; coleta mínima de alunos |
| Prestação de contas (art. 6º, X) | Tabela `auditoria` |
| Encarregado (DPO) identificado (art. 41) | Constante `SH_EMAIL_DPO` em `includes/config.php` |
| Eliminação após fim da finalidade | Documento apagado na recusa do credenciamento |
| Cookies com consentimento granular | Banner em `includes/site_footer.php` + `cookies.php` |

Versão dos documentos legais: constante `SH_VERSAO_POLITICA` em
`includes/config.php`. Ao alterar um texto legal, incremente a versão — o banner
de consentimento volta a ser exibido para todos.

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

> A CSP ainda precisa de `'unsafe-inline'` porque o projeto usa `<script>` e
> `style=""` embutidos no HTML. Mesmo assim, nenhum domínio externo além dos
> declarados (Google Fonts e Font Awesome) consegue injetar código.

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

## Antes de colocar em produção

Este projeto foi construído para rodar em XAMPP local. Para uso real:

1. **Trocar todas as senhas do seed** e remover as contas de demonstração.
2. **Criar um usuário de banco dedicado**, sem privilégios de administrador,
   e definir uma senha em `includes/config.php`.
3. **Servir por HTTPS** — o cookie de sessão já vira `Secure` sozinho e o
   `Strict-Transport-Security` passa a ser enviado.
4. **Configurar envio real de e-mail** — hoje as confirmações de protocolo são
   exibidas em tela, mas não disparadas por SMTP.
5. **Integrar um gateway de pagamento** para as assinaturas (o fluxo atual
   registra a contratação, mas não processa cobrança).
6. **Preencher os dados reais** do controlador e do encarregado nos documentos
   legais e nas constantes de `includes/config.php`.
7. **Configurar rotina de backup** do banco e da pasta `uploads/`.
8. Desativar a exibição de erros do PHP (`display_errors = Off`) e manter
   `error_log` ativo.

---

## Licença e créditos

Projeto acadêmico desenvolvido como Trabalho de Conclusão de Curso.
Tipografia: Space Grotesk e DM Sans (Google Fonts). Ícones: Font Awesome 6.
