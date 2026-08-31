-- ===========================================================================
--  SPORTHUB - Migration v3
--
--  Fecha os cartoes que faltavam no quadro do projeto:
--
--    SH-42  emails_enviados          registro de entrega de e-mail
--    SH-48  usuarios.senha_provisoria troca obrigatoria da senha de fabrica
--    SH-55  chaveamento_fases/_jogos  mata-mata automatico
--    SH-56  config_campeonato         criterios de desempate configuraveis
--    SH-60  usuarios.anonimizado_em   eliminacao e anonimizacao de conta
--    SH-64  senha_tokens              recuperacao de senha por e-mail
--    SH-65  usuarios.totp_*           dois fatores da coordenacao
--    SH-67  (usa eventos_jogo)        indices para estatistica individual
--    SH-68  escola_id em todo lugar   isolamento multi-escola
--    SH-71  jogo_fotos                galeria por partida
--    SH-73  ocorrencias               registro disciplinar
--    SH-41  cobrancas                 cobranca da assinatura
--
--  Como aplicar:
--      mysql -u root olimpiasp < scripts/migration_v3.sql
--  ou importe pelo phpMyAdmin com o banco `olimpiasp` selecionado.
--
--  Requer MySQL 8.0+ / MariaDB 10.4+ (usa ADD COLUMN IF NOT EXISTS).
--  Rode a migration_v2.sql antes desta, se ainda nao tiver rodado.
-- ===========================================================================

USE olimpiasp;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
--  1. USUARIOS - ciclo de vida da senha, 2FA e anonimizacao
-- ---------------------------------------------------------------------------
--  senha_provisoria (SH-48): 1 obriga a troca no proximo login, antes de
--  liberar qualquer tela. E o que impede uma instalacao de ficar no ar com
--  "admin1234".
--
--  demo (SH-48): marca as contas de demonstracao para que
--  scripts/preparar_producao.php saiba exatamente o que remover.
--
--  totp_* (SH-65): segredo base32 do autenticador e data de ativacao.
--
--  anonimizado_em (SH-60): quando a conta foi despersonalizada a pedido do
--  titular. A linha permanece para nao quebrar o historico das sumulas.
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS senha_provisoria TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS demo             TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS senha_alterada_em DATETIME  NULL,
    ADD COLUMN IF NOT EXISTS totp_segredo     VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS totp_ativado_em  DATETIME    NULL,
    ADD COLUMN IF NOT EXISTS anonimizado_em   DATETIME    NULL;

-- ---------------------------------------------------------------------------
--  2. MULTI-ESCOLA (SH-68)
-- ---------------------------------------------------------------------------
--  A tabela `escolas` existia desde a v2 e a assinatura ja apontava para ela,
--  mas nenhuma consulta filtrava por instituicao: duas escolas no mesmo banco
--  enxergariam os times uma da outra. As colunas abaixo dao a cada registro do
--  campeonato um dono.
--
--  NULL continua valendo como "de todo mundo": e o estado de uma instalacao de
--  escola unica, que e o caso normal. sh_escola_atual() so passa a filtrar
--  quando existe mais de uma escola cadastrada.
ALTER TABLE times        ADD COLUMN IF NOT EXISTS escola_id INT NULL;
ALTER TABLE modalidades  ADD COLUMN IF NOT EXISTS escola_id INT NULL;
ALTER TABLE jogos        ADD COLUMN IF NOT EXISTS escola_id INT NULL;

CREATE INDEX IF NOT EXISTS idx_times_escola       ON times (escola_id);
CREATE INDEX IF NOT EXISTS idx_modalidades_escola ON modalidades (escola_id);
CREATE INDEX IF NOT EXISTS idx_jogos_escola       ON jogos (escola_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_escola    ON usuarios (escola_id);

--  Instalacao existente: tudo que ja esta la pertence a primeira escola.
UPDATE times       SET escola_id = (SELECT MIN(id) FROM escolas) WHERE escola_id IS NULL;
UPDATE modalidades SET escola_id = (SELECT MIN(id) FROM escolas) WHERE escola_id IS NULL;
UPDATE jogos       SET escola_id = (SELECT MIN(id) FROM escolas) WHERE escola_id IS NULL;

-- ---------------------------------------------------------------------------
--  3. CONFIGURACAO DO CAMPEONATO (SH-56)
-- ---------------------------------------------------------------------------
--  Chave/valor simples. A regra de pontuacao e a ordem de desempate eram
--  constantes escritas dentro da consulta da classificacao; agora sao dado.
--  Vale para toda a instalacao (ou por escola, quando ha mais de uma).
CREATE TABLE IF NOT EXISTS config_campeonato (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    escola_id  INT          NULL,
    chave      VARCHAR(60)  NOT NULL,
    valor      VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_escola_chave (escola_id, chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO config_campeonato (escola_id, chave, valor) VALUES
 (NULL, 'pontos_vitoria',   '3'),
 (NULL, 'pontos_empate',    '1'),
 (NULL, 'pontos_derrota',   '0'),
 (NULL, 'criterios_desempate', 'saldo,gols_pro,vitorias,confronto_direto,nome');

-- ---------------------------------------------------------------------------
--  4. CHAVEAMENTO DE MATA-MATA (SH-55)
-- ---------------------------------------------------------------------------
--  Uma fase e uma rodada eliminatoria (oitavas, quartas, semi, final). Cada
--  posicao da chave aponta para o jogo que a resolve; quando o jogo termina, o
--  vencedor sobe para a posicao correspondente da fase seguinte.
--
--  proxima_posicao guarda para onde o vencedor vai: e o que permite montar a
--  arvore inteira de uma vez e ir preenchendo conforme os jogos acontecem, sem
--  recalcular nada.
CREATE TABLE IF NOT EXISTS chaveamento_fases (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    modalidade_id INT NOT NULL,
    escola_id     INT NULL,
    nome          VARCHAR(40) NOT NULL,       -- 'Oitavas', 'Quartas', 'Semifinal', 'Final'
    ordem         INT NOT NULL,               -- 1 = primeira rodada da chave
    times_na_fase INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fase_modalidade (modalidade_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chaveamento_jogos (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    fase_id          INT NOT NULL,
    posicao          INT NOT NULL,            -- 1..n dentro da fase
    time1_id         INT NULL,                -- NULL enquanto o classificado nao e conhecido
    time2_id         INT NULL,
    jogo_id          INT NULL,                -- partida real criada em `jogos`
    vencedor_id      INT NULL,
    proxima_fase_id  INT NULL,
    proxima_posicao  INT NULL,
    proxima_vaga     TINYINT NULL,            -- 1 = entra como time1; 2 = como time2
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fase_posicao (fase_id, posicao),
    INDEX idx_chave_jogo (jogo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  5. RECUPERACAO DE SENHA (SH-64)
-- ---------------------------------------------------------------------------
--  Guarda o HASH do token, nunca o token: se o banco vazar, os links em aberto
--  continuam inuteis. Validade curta e uso unico - `usado_em` preenchido
--  invalida o link mesmo dentro do prazo.
CREATE TABLE IF NOT EXISTS senha_tokens (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id  INT NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expira_em   DATETIME NOT NULL,
    usado_em    DATETIME NULL,
    ip          VARCHAR(45) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_token_hash (token_hash),
    INDEX idx_token_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  6. CODIGOS DE RECUPERACAO DO 2FA (SH-65)
-- ---------------------------------------------------------------------------
--  Entregues uma unica vez na ativacao. Sem eles, perder o celular significa
--  perder a conta que administra o campeonato inteiro.
CREATE TABLE IF NOT EXISTS totp_codigos (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    codigo_hash CHAR(64) NOT NULL,
    usado_em   DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_totp_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  7. E-MAILS ENVIADOS (SH-42)
-- ---------------------------------------------------------------------------
--  Destinatario, assunto e resultado. O CORPO NAO E GRAVADO: guardar o texto
--  de uma senha provisoria ou de um link de recuperacao anularia o cuidado de
--  te-los feito de uso unico.
CREATE TABLE IF NOT EXISTS emails_enviados (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    destinatario VARCHAR(255) NOT NULL,
    assunto      VARCHAR(200) NOT NULL,
    contexto     VARCHAR(60)  NULL,
    modo         VARCHAR(20)  NOT NULL,
    status       ENUM('enviado','falhou') NOT NULL DEFAULT 'enviado',
    erro         VARCHAR(255) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_status (status),
    INDEX idx_email_data (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  8. OCORRENCIAS DISCIPLINARES (SH-73)
-- ---------------------------------------------------------------------------
--  O cartao vermelho ja existia como evento da sumula, mas nao havia onde
--  registrar a consequencia: suspensao, advertencia, conversa com a familia.
--
--  Cuidado de LGPD deliberado: `descricao` e visivel apenas para a coordenacao
--  e para o arbitro que a escreveu - nunca para a tela publica do aluno. E
--  informacao disciplinar sobre menor de idade, com finalidade restrita a
--  organizacao do campeonato.
CREATE TABLE IF NOT EXISTS ocorrencias (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    jogo_id      INT NULL,
    time_id      INT NULL,
    jogador_id   INT NULL,
    jogador_nome VARCHAR(100) NULL,
    tipo         ENUM('advertencia','suspensao','expulsao','conduta','outro') NOT NULL DEFAULT 'advertencia',
    gravidade    ENUM('leve','media','grave') NOT NULL DEFAULT 'leve',
    jogos_suspensao INT NOT NULL DEFAULT 0,
    descricao    TEXT NOT NULL,
    providencia  TEXT NULL,
    status       ENUM('aberta','cumprindo','encerrada') NOT NULL DEFAULT 'aberta',
    registrada_por INT NULL,
    escola_id    INT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ocorrencia_jogo (jogo_id),
    INDEX idx_ocorrencia_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  9. GALERIA DE FOTOS POR PARTIDA (SH-71)
-- ---------------------------------------------------------------------------
--  Foto de aluno e dado pessoal, e a maioria dos participantes e menor de
--  idade (LGPD, art. 14). Por isso a tabela guarda, junto do arquivo:
--
--    consentimento_id  o registro do consentimento especifico que autorizou;
--    retencao_ate      a data em que a foto deve ser eliminada;
--    publica           se pode aparecer para alunos ou so para a coordenacao.
--
--  Sem consentimento registrado, a foto entra como NAO publica.
CREATE TABLE IF NOT EXISTS jogo_fotos (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    jogo_id          INT NOT NULL,
    arquivo          VARCHAR(255) NOT NULL,
    legenda          VARCHAR(200) NULL,
    publica          TINYINT(1) NOT NULL DEFAULT 0,
    consentimento_id INT NULL,
    retencao_ate     DATE NULL,
    enviada_por      INT NULL,
    escola_id        INT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_foto_jogo (jogo_id),
    INDEX idx_foto_retencao (retencao_ate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 10. COBRANCAS DA ASSINATURA (SH-41)
-- ---------------------------------------------------------------------------
--  O fluxo de contratacao registrava a assinatura e parava ali. Esta tabela e
--  o registro financeiro: quanto, por qual meio, em que estado.
--
--  NENHUM dado de cartao entra aqui - nem numero, nem CVV, nem token de
--  cartao. Pix e boleto sao identificados por codigo; gateway e identificado
--  pela referencia externa que ele devolve.
CREATE TABLE IF NOT EXISTS cobrancas (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    assinatura_id  INT NOT NULL,
    codigo         VARCHAR(30) NOT NULL,
    valor          DECIMAL(10,2) NOT NULL,
    meio           ENUM('pix','boleto','transferencia','gateway','manual') NOT NULL DEFAULT 'manual',
    status         ENUM('pendente','paga','cancelada','estornada') NOT NULL DEFAULT 'pendente',
    vencimento     DATE NULL,
    paga_em        DATETIME NULL,
    referencia_externa VARCHAR(120) NULL,
    payload_pix    TEXT NULL,
    baixa_por      INT NULL,
    observacao     VARCHAR(255) NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cobranca_codigo (codigo),
    INDEX idx_cobranca_assinatura (assinatura_id),
    INDEX idx_cobranca_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 11. INDICES DE APOIO (SH-67, SH-72, SH-83)
-- ---------------------------------------------------------------------------
--  A estatistica individual e o painel da direcao varrem eventos_jogo por
--  jogador e por tipo; a busca das listas varre nome. Sem indice, cada
--  carregamento vira varredura de tabela inteira.
CREATE INDEX IF NOT EXISTS idx_evento_jogador ON eventos_jogo (jogador);
CREATE INDEX IF NOT EXISTS idx_evento_tipo    ON eventos_jogo (tipo);
CREATE INDEX IF NOT EXISTS idx_jogos_status   ON jogos (status, data_jogo);
CREATE INDEX IF NOT EXISTS idx_times_nome     ON times (nome);
CREATE INDEX IF NOT EXISTS idx_jogadores_nome ON jogadores (nome);

-- ---------------------------------------------------------------------------
-- 12. SENHAS DO SEED (SH-48)
-- ---------------------------------------------------------------------------
--  Instalacao antiga: as senhas de demonstracao estavam em texto puro na
--  coluna `password`. Converte cada uma para hash bcrypt e marca a conta como
--  provisoria, para que a troca seja exigida no proximo login.
--
--  Os hashes abaixo correspondem as MESMAS senhas de sempre (admin1234 etc.):
--  ninguem perde o acesso ao aplicar a migracao.
UPDATE usuarios SET password = '$2y$12$QXkNcbPx59CJ7RDZT86.luR.NsMg51qkbRLvCBJA9oLZSF6geQcsq',
                    senha_provisoria = 1
 WHERE username = 'admin'     AND password = 'admin1234';
UPDATE usuarios SET password = '$2y$12$ZoeTS2rxzS/5G/SyFyiz9e2teFxAXWbc8cCYl3Mfm4cImRAVabRNO',
                    senha_provisoria = 1, demo = 1
 WHERE username = 'arbitro'   AND password = 'arbitro1234';
UPDATE usuarios SET password = '$2y$12$9aXX3qUaLxCv74jer7X2PuhFeGD29JP3Vk/OX4NoAc1p6llz3UDYa',
                    senha_provisoria = 1, demo = 1
 WHERE username = 'professor' AND password = 'professor1234';
UPDATE usuarios SET password = '$2y$12$cI8wSFZVrlS7ZZyPpsGyxuVZ8NMCQSKWnVbz2.Qj7iohuxodPiCjC',
                    senha_provisoria = 1, demo = 1
 WHERE username = 'aluno'     AND password = 'aluno1234';

--  Qualquer outra senha que ainda esteja em texto puro (nao comeca por $2y$)
--  perde a validade: a conta e marcada como provisoria e a coordenacao
--  redefine pelo painel. Melhor um acesso a restaurar do que uma senha legivel
--  no banco.
UPDATE usuarios SET senha_provisoria = 1 WHERE password NOT LIKE '$2y$%';

-- ===========================================================================
--  Conferencia
-- ===========================================================================
SELECT 'usuarios com senha em texto puro' AS verificacao,
       COUNT(*) AS quantidade FROM usuarios WHERE password NOT LIKE '$2y$%'
UNION ALL
SELECT 'contas de demonstracao', COUNT(*) FROM usuarios WHERE demo = 1
UNION ALL
SELECT 'chaves de configuracao do campeonato', COUNT(*) FROM config_campeonato;
