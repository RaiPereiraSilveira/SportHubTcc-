<?php
/**
 * includes/config.local.example.php — modelo da configuração de produção
 *
 * COPIE este arquivo para `includes/config.local.php` e preencha o que for
 * usar. O arquivo copiado NÃO deve ir para o repositório: ele guarda a senha
 * do banco, o segredo do calendário e a senha do servidor de e-mail.
 *
 * Regras:
 *
 * · Tudo aqui é opcional. O que você não definir continua com o valor de
 *   fábrica de `includes/config.php` — que é o suficiente para rodar no XAMPP.
 * · Este arquivo é lido ANTES dos padrões, então qualquer constante definida
 *   aqui vence.
 * · Nunca deixe uma linha `define()` sem valor real "para preencher depois":
 *   o painel da coordenação avisa em vermelho o que ainda está pendente.
 *
 * Depois de copiar, confira em `admin/dashboard.php` o cartão
 * "Preparação para produção": ele lista item por item o que falta.
 */

/* ── Banco de dados (SH-49) ──────────────────────────────────────────────
   Em produção, NUNCA use `root`. Rode `scripts/usuario_banco.sql` (troque a
   senha de exemplo lá dentro) para criar um usuário que só enxerga o banco
   `olimpiasp` e só pode SELECT, INSERT, UPDATE e DELETE — sem DROP, sem GRANT,
   sem acesso aos outros bancos do servidor. Se o site for invadido por uma
   falha de aplicação, o estrago fica contido nesse banco.                 */
// define('SH_DB_HOST',    'localhost');
// define('SH_DB_NOME',    'olimpiasp');
// define('SH_DB_USUARIO', 'sporthub_app');
// define('SH_DB_SENHA',   'troque-por-uma-senha-longa-e-aleatoria');

/* ── Segredo do calendário .ics (SH-84) ──────────────────────────────────
   Assina os endereços do feed público. Trocar este valor invalida, de uma
   vez, todos os links de calendário já distribuídos.

   Se você não definir nada aqui, o sistema gera sozinho um segredo aleatório
   em `logs/segredo_feed.txt` na primeira execução — o que já é seguro. Defina
   abaixo apenas quando houver mais de um servidor servindo o mesmo site e os
   dois precisarem gerar o mesmo token.

   Para gerar um valor:  php -r "echo bin2hex(random_bytes(32));"          */
// define('SH_SEGREDO_FEED', 'cole-aqui-64-caracteres-hexadecimais');

/* ── Envio de e-mail (SH-42) ─────────────────────────────────────────────
   Sem SH_SMTP_HOST, o sistema opera em modo "registro": nada sai da máquina,
   as mensagens ficam em `logs/emails/` e a tela continua mostrando protocolo
   e senha provisória. É o modo certo para desenvolvimento e para a defesa
   do TCC.

   Com SMTP configurado, passam a ser entregues de verdade: protocolo de
   credenciamento, senha provisória do árbitro, aviso de designação,
   recuperação de senha (SH-64) e resposta de requisição LGPD.

   Gmail e Google Workspace exigem uma "senha de app" (a senha da conta não
   funciona). Porta 587 com 'tls' é o padrão; 465 pede 'ssl'.             */
// define('SH_SMTP_HOST',       'smtp.gmail.com');
// define('SH_SMTP_PORTA',      587);
// define('SH_SMTP_USUARIO',    'interclasse@suaescola.edu.br');
// define('SH_SMTP_SENHA',      'senha-de-app-de-16-letras');
// define('SH_SMTP_SEGURANCA',  'tls');           // 'tls', 'ssl' ou 'nenhuma'
// define('SH_EMAIL_REMETENTE', 'interclasse@suaescola.edu.br');
// define('SH_EMAIL_NOME',      'Interclasse — Escola Exemplo');
// define('SH_EMAIL_MODO',      'smtp');          // força o modo; '' = automático

/* ── Identificação do controlador e do encarregado (SH-44) ───────────────
   A LGPD exige que o titular saiba quem trata os dados dele (art. 9º, I) e a
   quem reclamar (art. 41). Enquanto estes valores forem os de fábrica, as
   páginas legais exibem "a preencher" em vez de um dado inventado, e o painel
   marca a pendência.

   O controlador é a ESCOLA que opera o campeonato, não o desenvolvedor do
   sistema — é ela quem decide as finalidades do tratamento.               */
// define('SH_CONTROLADOR_NOME',     'Colégio Exemplo Ltda.');
// define('SH_CONTROLADOR_CNPJ',     '12.345.678/0001-90');
// define('SH_CONTROLADOR_ENDERECO', 'Rua das Palmeiras, 120 — Centro');
// define('SH_CONTROLADOR_CIDADE',   'Campinas/SP');
// define('SH_DPO_NOME',             'Maria Souza');
// define('SH_EMAIL_DPO',            'dpo@suaescola.edu.br');
// define('SH_DPO_TELEFONE',         '(19) 3000-0000');

/* ── Identidade do produto ───────────────────────────────────────────────── */
// define('SH_NOME',     'SportHub');
// define('SH_EMAIL',    'contato@suaescola.edu.br');
// define('SH_WHATSAPP', '5519999999999');

/* ── Cobrança das assinaturas (SH-41) ────────────────────────────────────
   'manual'  → a contratação gera uma cobrança que a escola paga por Pix ou
               boleto emitido fora do sistema, e a coordenação dá baixa à mão.
               É o modo padrão e o que a maioria das escolas públicas usa.
   'pix'     → mesma coisa, mas o sistema monta o payload do Pix copia-e-cola
               (BR Code, padrão do Banco Central) a partir da chave abaixo.
               Não exige contrato com gateway nenhum.
   'gateway' → integração com adquirente. Exige conta aprovada, chave de API e
               endpoint de webhook público em HTTPS.                       */
// define('SH_PAGAMENTO_MODO',   'pix');
// define('SH_PIX_CHAVE',        'interclasse@suaescola.edu.br');
// define('SH_PIX_BENEFICIARIO', 'COLEGIO EXEMPLO LTDA');
// define('SH_PIX_CIDADE',       'CAMPINAS');
// define('SH_GATEWAY_NOME',     'Mercado Pago');
// define('SH_GATEWAY_CHAVE',    '');
// define('SH_GATEWAY_WEBHOOK_SEGREDO', '');

/* ── Depuração ───────────────────────────────────────────────────────────
   Normalmente não precisa: `config.php` só liga a exibição de erros em acesso
   local. Defina para forçar um dos modos em ambiente de homologação.      */
// define('SH_DEBUG', false);
