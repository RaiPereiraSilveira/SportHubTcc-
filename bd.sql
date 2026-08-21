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
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE times (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    nome       VARCHAR(100) NOT NULL,
    sala       VARCHAR(20)  NOT NULL,
    genero     ENUM('masculino','feminino','misto') NOT NULL,
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
    id     INT PRIMARY KEY AUTO_INCREMENT,
    nome   VARCHAR(50) NOT NULL,
    genero ENUM('masculino','feminino','misto') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jogos (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    modalidade_id INT,
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

-- Usuários de demonstração.
-- As senhas abaixo estão em texto puro apenas para facilitar o primeiro acesso:
-- o login converte automaticamente cada uma em hash no primeiro uso.
-- TROQUE ESTAS SENHAS ANTES DE COLOCAR O SISTEMA EM PRODUÇÃO.
INSERT INTO usuarios (username, password, tipo, nome, email) VALUES
 ('admin',      'admin1234',      'admin',   'Administrador',   NULL),
 ('arbitro',    'arbitro1234',    'arbitro', 'Prof. Carlos',    NULL),
 ('professor',  'professor1234',  'arbitro', 'Professor Silva', NULL),
 ('aluno',      'aluno1234',      'aluno',   'João Silva',      NULL);

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
