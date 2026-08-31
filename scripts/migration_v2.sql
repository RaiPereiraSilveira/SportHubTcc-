-- ===========================================================================
--  SPORTHUB - Migration v2
--  Adiciona: credenciamento de arbitros, assinaturas anuais, LGPD e contato.
--
--  Como aplicar:
--      mysql -u root olimpiasp < scripts/migration_v2.sql
--  ou importe este arquivo pelo phpMyAdmin com o banco `olimpiasp` selecionado.
--
--  Requer MySQL 8.0+ / MariaDB 10.4+ (usa ADD COLUMN IF NOT EXISTS).
-- ===========================================================================

USE olimpiasp;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
--  1. USUARIOS - campos de perfil, LGPD e ciclo de vida da conta
-- ---------------------------------------------------------------------------
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS email            VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS foto_perfil      VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS telefone         VARCHAR(20)  NULL,
    ADD COLUMN IF NOT EXISTS cpf              VARCHAR(14)  NULL,
    ADD COLUMN IF NOT EXISTS escola_id        INT          NULL,
    ADD COLUMN IF NOT EXISTS status           ENUM('ativo','pendente','suspenso','anonimizado')
                                              NOT NULL DEFAULT 'ativo',
    ADD COLUMN IF NOT EXISTS aceite_termos_em      DATETIME NULL,
    ADD COLUMN IF NOT EXISTS aceite_privacidade_em DATETIME NULL,
    ADD COLUMN IF NOT EXISTS ultimo_acesso    DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS updated_at       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ---------------------------------------------------------------------------
--  2. ESCOLAS - cada assinatura pertence a uma instituicao
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS escolas (
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

-- ---------------------------------------------------------------------------
--  3. PLANOS - catalogo de assinaturas anuais
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS planos (
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

INSERT INTO planos (slug, nome, descricao, preco_anual, preco_mensal_equivalente,
                    limite_times, limite_modalidades, limite_arbitros, destaque, ordem)
VALUES
 ('essencial', 'Essencial',
  'Para a escola que quer tirar o interclasse do papel e do grupo de WhatsApp.',
  1188.00,  99.00,  12,    3,    5,  0, 1),
 ('pro', 'Pro',
  'Para quem organiza um campeonato inteiro por ano, com varias modalidades.',
  2388.00, 199.00,  40, NULL,   20,  1, 2),
 ('institucional', 'Institucional',
  'Para redes de ensino e multiplas unidades, com suporte dedicado.',
  4788.00, 399.00, NULL, NULL, NULL,  0, 3)
ON DUPLICATE KEY UPDATE
    nome                     = VALUES(nome),
    descricao                = VALUES(descricao),
    preco_anual              = VALUES(preco_anual),
    preco_mensal_equivalente = VALUES(preco_mensal_equivalente),
    limite_times             = VALUES(limite_times),
    limite_modalidades       = VALUES(limite_modalidades),
    limite_arbitros          = VALUES(limite_arbitros),
    destaque                 = VALUES(destaque),
    ordem                    = VALUES(ordem);

-- ---------------------------------------------------------------------------
--  4. ASSINATURAS - contratacao anual feita pela escola
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS assinaturas (
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
--  5. CREDENCIAMENTO DE ARBITROS (profissional aplicador / juiz)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS arbitro_solicitacoes (
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

-- Ficha profissional do arbitro ja credenciado
CREATE TABLE IF NOT EXISTS arbitro_perfil (
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
--  6. LGPD - registro de consentimento e requisicoes do titular
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lgpd_consentimentos (
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

CREATE TABLE IF NOT EXISTS lgpd_solicitacoes (
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

-- ---------------------------------------------------------------------------
--  7. CONTATO comercial
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contatos (
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

-- ---------------------------------------------------------------------------
--  8. AUDITORIA - trilha de acoes sensiveis (accountability, art. 6, X, LGPD)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
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
