-- ===========================================================================
--  SPORTHUB — usuário de banco dedicado (SH-49)
--
--  O projeto conecta como `root` sem senha porque é assim que o XAMPP vem de
--  fábrica. Isso é aceitável na máquina do desenvolvedor e inaceitável em
--  produção: se uma falha da aplicação permitir executar SQL arbitrário, o
--  atacante herda um usuário que pode APAGAR QUALQUER BANCO do servidor,
--  criar novos usuários e ler o `mysql.user`.
--
--  Este script cria um usuário que:
--    · só enxerga o banco `olimpiasp`;
--    · só faz SELECT, INSERT, UPDATE e DELETE — as quatro operações que a
--      aplicação realmente usa em tempo de execução;
--    · NÃO pode DROP, CREATE, ALTER, GRANT nem FILE.
--
--  O `information_schema` continua legível para todos por padrão no MySQL, o
--  que basta para `sh_tabela_existe()` funcionar.
--
--  Uso:
--      1. Troque a senha na linha marcada (use algo longo e aleatório:
--         php -r "echo bin2hex(random_bytes(16));")
--      2. mysql -u root -p < scripts/usuario_banco.sql
--      3. Copie includes/config.local.example.php para config.local.php e
--         preencha SH_DB_USUARIO e SH_DB_SENHA.
--
--  Migrações (bd.sql, migration_v2.sql, migration_v3.sql) continuam sendo
--  rodadas com o usuário administrativo, à mão — não é a aplicação que
--  altera o schema.
-- ===========================================================================

-- ── 1. Criação ────────────────────────────────────────────────────────────
--  TROQUE A SENHA ABAIXO ANTES DE EXECUTAR.
CREATE USER IF NOT EXISTS 'sporthub_app'@'localhost'
    IDENTIFIED BY 'troque-por-uma-senha-longa-e-aleatoria';

-- Se o usuário já existia com outra senha, esta linha a atualiza.
ALTER USER 'sporthub_app'@'localhost'
    IDENTIFIED BY 'troque-por-uma-senha-longa-e-aleatoria';

-- ── 2. Privilégios mínimos ────────────────────────────────────────────────
--  Revoga tudo antes de conceder: evita herdar sobra de uma execução antiga.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'sporthub_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON olimpiasp.*
    TO 'sporthub_app'@'localhost';

FLUSH PRIVILEGES;

-- ── 3. Conferência ────────────────────────────────────────────────────────
--  Deve listar apenas o GRANT acima e o USAGE padrão.
SHOW GRANTS FOR 'sporthub_app'@'localhost';

-- ── 4. Endurecimento do servidor (opcional, recomendado) ──────────────────
--  Em produção, o `root` do MySQL não deveria estar sem senha nem acessível
--  de fora da máquina. Descomente e ajuste:
--
-- ALTER USER 'root'@'localhost' IDENTIFIED BY 'senha-forte-do-root';
-- DROP USER IF EXISTS ''@'localhost';          -- usuário anônimo do MySQL
-- DROP DATABASE IF EXISTS test;                -- banco de exemplo
-- FLUSH PRIVILEGES;
