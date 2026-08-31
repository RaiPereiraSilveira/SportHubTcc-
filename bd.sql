-- ===========================================================================
--  SPORTHUB — Banco de dados completo (instalação limpa)
--
--  Uso:
--      mysql -u root < bd.sql
--  ou importe pelo phpMyAdmin.
--
--  ATENÇÃO: este arquivo APAGA e recria o banco `olimpiasp`.
--  Para atualizar uma instalação existente sem perder dados, use
--  scripts/migration_v2.sql.
-- ===========================================================================

DROP DATABASE IF EXISTS olimpiasp;
CREATE DATABASE olimpiasp DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE olimpiasp;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
--  NÚCLEO — usuários e campeonato
-- ---------------------------------------------------------------------------
CREATE TABLE usuarios (
    id                    INT PRIMARY KEY AUTO_INCREMENT,
    username              VARCHAR(50) UNIQUE NOT NULL,
    password              VARCHAR(255) NOT NULL,
    tipo                  ENUM('admin','arbitro','aluno') NOT NULL,
    status                ENUM('ativo','pendente','suspenso','anonimizado') NOT NULL DEFAULT 'ativo',
    nome                  VARCHAR(100) NOT NULL,
    email                 VARCHAR(255) NULL,
    telefone              VARCHAR(20)  NULL,
    cpf                   VARCHAR(14)  NULL,
    escola_id             INT          NULL,
    foto_perfil           VARCHAR(255) NULL,
    aceite_termos_em      DATETIME NULL,
    aceite_privacidade_em DATETIME NULL,
    ultimo_acesso         DATETIME NULL,
    senha_provisoria      TINYINT(1) NOT NULL DEFAULT 0,
    demo                  TINYINT(1) NOT NULL DEFAULT 0,
    senha_alterada_em     DATETIME NULL,
    totp_segredo          VARCHAR(64) NULL,
    totp_ativado_em       DATETIME NULL,
    anonimizado_em        DATETIME NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE times (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    nome       VARCHAR(100) NOT NULL,
    sala       VARCHAR(20)  NOT NULL,
    genero     ENUM('masculino','feminino','misto') NOT NULL,
    escola_id  INT          NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jogadores (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    time_id        INT,
    nome           VARCHAR(100) NOT NULL,
    numero_camisa  INT,
    FOREIGN KEY (time_id) REFERENCES times(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE modalidades (
    id        INT PRIMARY KEY AUTO_INCREMENT,
    nome      VARCHAR(50) NOT NULL,
    genero    ENUM('masculino','feminino','misto') NOT NULL,
    escola_id INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jogos (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    modalidade_id INT,
    escola_id     INT NULL,
    time1_id      INT,
    time2_id      INT,
    data_jogo     DATE,
    hora          TIME,
    local         VARCHAR(100),
    fase          VARCHAR(50),
    arbitro_id    INT,
    placar_time1  INT DEFAULT 0,
    placar_time2  INT DEFAULT 0,
    status        ENUM('agendado','em_andamento','finalizado') DEFAULT 'agendado',
    observacoes   TEXT NULL,
    FOREIGN KEY (modalidade_id) REFERENCES modalidades(id),
    FOREIGN KEY (time1_id) REFERENCES times(id),
    FOREIGN KEY (time2_id) REFERENCES times(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estatisticas_jogo (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    jogo_id    INT,
    time_id    INT,
    tipo       VARCHAR(50),
    valor      VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogo_id) REFERENCES jogos(id),
    FOREIGN KEY (time_id) REFERENCES times(id),
    UNIQUE KEY unique_estatistica (jogo_id, time_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE eventos_jogo (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    jogo_id    INT,
    time_id    INT,
    jogador    VARCHAR(100),
    tipo       ENUM('gol','cartao_amarelo','cartao_vermelho','substituicao'),
    minuto     INT,
    descricao  TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogo_id) REFERENCES jogos(id),
    FOREIGN KEY (time_id) REFERENCES times(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  INSTITUCIONAL — escolas, planos e assinaturas anuais
-- ---------------------------------------------------------------------------
CREATE TABLE escolas (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    nome        VARCHAR(150) NOT NULL,
    cnpj        VARCHAR(18)  NULL,
    cidade      VARCHAR(100) NULL,
    uf          CHAR(2)      NULL,
    responsavel VARCHAR(120) NULL,
    email       VARCHAR(255) NULL,
    telefone    VARCHAR(20)  NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE planos (
    id                       INT PRIMARY KEY AUTO_INCREMENT,
    slug                     VARCHAR(40)  UNIQUE NOT NULL,
    nome                     VARCHAR(60)  NOT NULL,
    descricao                VARCHAR(255) NULL,
    preco_anual              DECIMAL(10,2) NOT NULL,
    preco_mensal_equivalente DECIMAL(10,2) NOT NULL,
    limite_times             INT NULL,
    limite_modalidades       INT NULL,
    limite_arbitros          INT NULL,
    destaque                 TINYINT(1) NOT NULL DEFAULT 0,
    ativo                    TINYINT(1) NOT NULL DEFAULT 1,
    ordem                    INT NOT NULL DEFAULT 0,
    created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE assinaturas (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    codigo          VARCHAR(20) UNIQUE NOT NULL,
    escola_id       INT NULL,
    plano_id        INT NOT NULL,
    responsavel     VARCHAR(120) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    telefone        VARCHAR(20)  NULL,
    cargo           VARCHAR(80)  NULL,
    forma_pagamento ENUM('boleto','pix','cartao','empenho') NOT NULL DEFAULT 'boleto',
    valor           DECIMAL(10,2) NOT NULL,
    status          ENUM('trial','pendente','ativa','cancelada','expirada') NOT NULL DEFAULT 'pendente',
    inicio_em       DATE NULL,
    expira_em       DATE NULL,
    observacoes     TEXT NULL,
    aceite_termos   TINYINT(1) NOT NULL DEFAULT 0,
    ip_aceite       VARCHAR(45) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plano_id)  REFERENCES planos(id),
    FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE SET NULL,
    INDEX idx_assinatura_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  ARBITRAGEM — credenciamento do profissional aplicador
-- ---------------------------------------------------------------------------
CREATE TABLE arbitro_solicitacoes (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    protocolo          VARCHAR(20) UNIQUE NOT NULL,
    nome               VARCHAR(120) NOT NULL,
    email              VARCHAR(255) NOT NULL,
    telefone           VARCHAR(20)  NOT NULL,
    cpf                VARCHAR(14)  NOT NULL,
    data_nascimento    DATE NULL,
    cidade             VARCHAR(100) NULL,
    uf                 CHAR(2) NULL,
    escola_vinculo     VARCHAR(150) NULL,
    formacao           ENUM('educacao_fisica','arbitragem_federada','tecnico_esportivo','estudante','outro')
                       NOT NULL DEFAULT 'outro',
    registro_orgao     VARCHAR(40) NULL,
    registro_numero    VARCHAR(40) NULL,
    anos_experiencia   INT NOT NULL DEFAULT 0,
    modalidades        VARCHAR(255) NOT NULL,
    disponibilidade    VARCHAR(120) NULL,
    apresentacao       TEXT NULL,
    documento_arquivo  VARCHAR(255) NULL,
    username_sugerido  VARCHAR(50) NULL,
    aceite_termos      TINYINT(1) NOT NULL DEFAULT 0,
    aceite_privacidade TINYINT(1) NOT NULL DEFAULT 0,
    aceite_conduta     TINYINT(1) NOT NULL DEFAULT 0,
    ip_aceite          VARCHAR(45) NULL,
    status             ENUM('recebida','em_analise','aprovada','recusada') NOT NULL DEFAULT 'recebida',
    parecer            TEXT NULL,
    usuario_id         INT NULL,
    analisado_por      INT NULL,
    analisado_em       DATETIME NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_arb_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE arbitro_perfil (
    usuario_id            INT PRIMARY KEY,
    registro_orgao        VARCHAR(40) NULL,
    registro_numero       VARCHAR(40) NULL,
    modalidades           VARCHAR(255) NULL,
    anos_experiencia      INT NOT NULL DEFAULT 0,
    cidade                VARCHAR(100) NULL,
    uf                    CHAR(2) NULL,
    credenciado_em        DATE NULL,
    credencial_valida_ate DATE NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
--  LGPD — consentimentos, requisições de titular e auditoria
-- ---------------------------------------------------------------------------
CREATE TABLE lgpd_consentimentos (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id    INT NULL,
    identificador VARCHAR(120) NULL,
    finalidade    VARCHAR(80) NOT NULL,
    concedido     TINYINT(1) NOT NULL DEFAULT 1,
    versao_texto  VARCHAR(20) NOT NULL DEFAULT '1.0',
    ip            VARCHAR(45) NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_consent_finalidade (finalidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lgpd_solicitacoes (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    protocolo     VARCHAR(20) UNIQUE NOT NULL,
    nome          VARCHAR(120) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    vinculo       ENUM('aluno','responsavel','arbitro','professor','outro') NOT NULL DEFAULT 'outro',
    tipo          ENUM('acesso','correcao','anonimizacao','eliminacao','portabilidade',
                       'revogacao','informacao_compartilhamento','oposicao') NOT NULL,
    descricao     TEXT NULL,
    status        ENUM('recebida','em_analise','atendida','recusada') NOT NULL DEFAULT 'recebida',
    resposta      TEXT NULL,
    prazo_em      DATE NULL,
    respondido_em DATETIME NULL,
    ip            VARCHAR(45) NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lgpd_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contatos (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    nome       VARCHAR(120) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    telefone   VARCHAR(20)  NULL,
    escola     VARCHAR(150) NULL,
    assunto    ENUM('demonstracao','planos','suporte','arbitragem','imprensa','outro') NOT NULL DEFAULT 'outro',
    mensagem   TEXT NOT NULL,
    lido       TINYINT(1) NOT NULL DEFAULT 0,
    ip         VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auditoria (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id  INT NULL,
    acao        VARCHAR(80) NOT NULL,
    entidade    VARCHAR(60) NULL,
    entidade_id INT NULL,
    detalhe     VARCHAR(255) NULL,
    ip          VARCHAR(45) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditoria_acao (acao),
    INDEX idx_auditoria_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------------
--  MODULOS DA v3 - chaveamento, configuracao, LGPD, comunicacao e cobranca
--  (o mesmo DDL de scripts/migration_v3.sql, para a instalacao limpa)
-- ---------------------------------------------------------------------------
--  3. CONFIGURACAO DO CAMPEONATO (SH-56)
-- ---------------------------------------------------------------------------
--  Chave/valor simples. A regra de pontuacao e a ordem de desempate eram
--  constantes escritas dentro da consulta da classificacao; agora sao dado.
--  Vale para toda a instalacao (ou por escola, quando ha mais de uma).
CREATE TABLE config_campeonato (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    escola_id  INT          NULL,
    chave      VARCHAR(60)  NOT NULL,
    valor      VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_escola_chave (escola_id, chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO config_campeonato (escola_id, chave, valor) VALUES
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
CREATE TABLE chaveamento_fases (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    modalidade_id INT NOT NULL,
    escola_id     INT NULL,
    nome          VARCHAR(40) NOT NULL,       -- 'Oitavas', 'Quartas', 'Semifinal', 'Final'
    ordem         INT NOT NULL,               -- 1 = primeira rodada da chave
    times_na_fase INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fase_modalidade (modalidade_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chaveamento_jogos (
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
CREATE TABLE senha_tokens (
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
CREATE TABLE totp_codigos (
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
CREATE TABLE emails_enviados (
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
CREATE TABLE ocorrencias (
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
CREATE TABLE jogo_fotos (
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
CREATE TABLE cobrancas (
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
CREATE INDEX idx_evento_jogador ON eventos_jogo (jogador);
CREATE INDEX idx_evento_tipo    ON eventos_jogo (tipo);
CREATE INDEX idx_jogos_status   ON jogos (status, data_jogo);
CREATE INDEX idx_times_nome     ON times (nome);
CREATE INDEX idx_jogadores_nome ON jogadores (nome);
CREATE INDEX idx_times_escola       ON times (escola_id);
CREATE INDEX idx_modalidades_escola ON modalidades (escola_id);
CREATE INDEX idx_jogos_escola       ON jogos (escola_id);
CREATE INDEX idx_usuarios_escola    ON usuarios (escola_id);

-- ===========================================================================
--  DADOS INICIAIS
-- ===========================================================================

-- Planos de assinatura anual
INSERT INTO planos (slug, nome, descricao, preco_anual, preco_mensal_equivalente,
                    limite_times, limite_modalidades, limite_arbitros, destaque, ordem) VALUES
 ('essencial', 'Essencial',
  'Para a escola que quer tirar o interclasse do papel e do grupo de WhatsApp.',
  1188.00,  99.00,  12,    3,    5,  0, 1),
 ('pro', 'Pro',
  'Para quem organiza um campeonato inteiro por ano, com várias modalidades.',
  2388.00, 199.00,  40, NULL,   20,  1, 2),
 ('institucional', 'Institucional',
  'Para redes de ensino e múltiplas unidades, com suporte dedicado.',
  4788.00, 399.00, NULL, NULL, NULL,  0, 3);

-- Usuários de demonstração (SH-48).
--
-- As senhas abaixo estão gravadas como HASH bcrypt, não mais em texto puro.
-- A versão anterior guardava "admin1234" legível na coluna `password` e o
-- login aceitava a comparação direta — ou seja, quem lesse o banco (ou este
-- arquivo, que está no repositório) entrava como coordenação. Pior: o
-- fallback de texto puro continuava valendo para sempre, mesmo depois de
-- alguém trocar a senha pelo painel.
--
-- As contas continuam existindo com as mesmas senhas de sempre, para que a
-- demonstração e a banca do TCC funcionem sem configuração:
--
--     admin / admin1234   ·   arbitro / arbitro1234
--     professor / professor1234   ·   aluno / aluno1234
--
-- Mas todas nascem com `senha_provisoria = 1`: no primeiro login o sistema
-- OBRIGA a troca antes de liberar qualquer tela. Uma instalação real não
-- consegue ficar no ar com a senha de fábrica sem alguém decidir isso.
--
-- Para produção, rode `php scripts/preparar_producao.php`: ele remove as três
-- contas de demonstração, define uma senha nova para o admin e lista o que
-- ainda falta configurar.
INSERT INTO usuarios (username, password, tipo, nome, email, senha_provisoria, demo) VALUES
 ('admin',     '$2y$12$QXkNcbPx59CJ7RDZT86.luR.NsMg51qkbRLvCBJA9oLZSF6geQcsq', 'admin',   'Administrador',   NULL, 1, 0),
 ('arbitro',   '$2y$12$ZoeTS2rxzS/5G/SyFyiz9e2teFxAXWbc8cCYl3Mfm4cImRAVabRNO', 'arbitro', 'Prof. Carlos',    NULL, 1, 1),
 ('professor', '$2y$12$9aXX3qUaLxCv74jer7X2PuhFeGD29JP3Vk/OX4NoAc1p6llz3UDYa', 'arbitro', 'Professor Silva', NULL, 1, 1),
 ('aluno',     '$2y$12$cI8wSFZVrlS7ZZyPpsGyxuVZ8NMCQSKWnVbz2.Qj7iohuxodPiCjC', 'aluno',   'João Silva',      NULL, 1, 1);

-- Modalidades
INSERT INTO modalidades (nome, genero) VALUES
 ('Futebol',  'masculino'),
 ('Vôlei',    'feminino'),
 ('Basquete', 'misto'),
 ('Handebol', 'masculino');

-- Times
INSERT INTO times (nome, sala, genero) VALUES
 ('Leões do 9ºA',    '9º Ano A', 'masculino'),
 ('Águias do 9ºB',   '9º Ano B', 'masculino'),
 ('Panteras do 8ºA', '8º Ano A', 'feminino');

-- Jogos finalizados (para demonstrar a classificação automática)
INSERT INTO jogos (modalidade_id, time1_id, time2_id, data_jogo, hora, local, fase,
                   arbitro_id, placar_time1, placar_time2, status) VALUES
 (1, 1, 2, '2024-01-10', '14:00:00', 'Campo Principal', 'Grupo A', 2, 3, 1, 'finalizado'),
 (1, 2, 3, '2024-01-11', '10:00:00', 'Campo 2',         'Grupo A', 2, 2, 2, 'finalizado'),
 (1, 3, 1, '2024-01-12', '16:00:00', 'Campo Principal', 'Grupo A', 2, 1, 4, 'finalizado'),
 (2, 1, 3, '2024-01-13', '09:00:00', 'Quadra 1',        'Grupo B', 2, 3, 0, 'finalizado');
