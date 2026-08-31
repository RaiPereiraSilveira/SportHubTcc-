<?php
/**
 * scripts/preparar_producao.php — checklist executável (SH-48, SH-44, SH-49, SH-84)
 *
 * Uso:
 *     C:\xampp\php\php.exe scripts\preparar_producao.php            (só verifica)
 *     C:\xampp\php\php.exe scripts\preparar_producao.php --aplicar  (executa)
 *
 * A lista "antes de colocar em produção" do README existia há três sprints e
 * dependia de alguém lembrar de cada item no dia da publicação. Uma lista que
 * depende de memória é uma lista que falha.
 *
 * Sem argumento, o script apenas VERIFICA e mostra o que está pendente — é
 * seguro rodar a qualquer momento. Com `--aplicar`, ele remove as contas de
 * demonstração e define uma senha nova para a coordenação.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script roda apenas pela linha de comando.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/pagamento.php';

$aplicar = in_array('--aplicar', $argv, true);

function titulo($texto) {
    echo "\n\033[1m" . $texto . "\033[0m\n" . str_repeat('─', 68) . "\n";
}
function ok($texto)      { echo "  \033[32m✓\033[0m " . $texto . "\n"; }
function pendente($texto){ echo "  \033[33m!\033[0m " . $texto . "\n"; }
function grave($texto)   { echo "  \033[31m✗\033[0m " . $texto . "\n"; }

$pendencias = 0;
$graves     = 0;

echo "\n" . str_repeat('═', 68) . "\n";
echo " SportHub — preparação para produção\n";
echo ' ' . ($aplicar ? "MODO APLICAR — alterações serão gravadas"
                     : "modo verificação — nada será alterado (use --aplicar)") . "\n";
echo str_repeat('═', 68) . "\n";

/* ── 1. Configuração local ──────────────────────────────────────────────── */
titulo('1. Configuração local');

if (is_file(__DIR__ . '/../includes/config.local.php')) {
    ok('includes/config.local.php existe.');
} else {
    $graves++;
    grave('includes/config.local.php NÃO existe.');
    echo "      Copie includes/config.local.example.php e preencha.\n";
}

/* ── 2. Banco de dados ──────────────────────────────────────────────────── */
titulo('2. Banco de dados (SH-49)');

if ($pdo === null) {
    $graves++;
    grave('Sem conexão com o banco. Nada além disto pode ser verificado.');
} else {
    if (SH_DB_USUARIO === 'root') {
        $graves++;
        grave('A aplicação conecta como root.');
        echo "      Rode scripts/usuario_banco.sql e preencha SH_DB_USUARIO/SH_DB_SENHA.\n";
    } else {
        ok('Usuário de banco dedicado: ' . SH_DB_USUARIO);
    }

    if (SH_DB_SENHA === '') {
        $graves++;
        grave('O usuário do banco está sem senha.');
    } else {
        ok('O usuário do banco tem senha definida.');
    }

    // A migração v3 precisa ter rodado, senão metade das telas não existe.
    $faltando = [];
    foreach (['config_campeonato', 'chaveamento_fases', 'senha_tokens',
              'totp_codigos', 'emails_enviados', 'ocorrencias',
              'jogo_fotos', 'cobrancas'] as $tabela) {
        if (!sh_tabela_existe($pdo, $tabela)) $faltando[] = $tabela;
    }
    if ($faltando) {
        $graves++;
        grave('A migração v3 não foi aplicada. Faltam: ' . implode(', ', $faltando));
        echo "      mysql -u root olimpiasp < scripts/migration_v3.sql\n";
    } else {
        ok('Migração v3 aplicada (todas as tabelas presentes).');
    }
}

/* ── 3. Contas (SH-48) ──────────────────────────────────────────────────── */
titulo('3. Senhas e contas de demonstração (SH-48)');

if ($pdo !== null) {
    try {
        $texto_puro = (int)$pdo->query(
            "SELECT COUNT(*) FROM usuarios WHERE password NOT LIKE '\$2y\$%'"
        )->fetchColumn();
        if ($texto_puro > 0) {
            $graves++;
            grave($texto_puro . ' conta(s) com senha fora do formato bcrypt.');
        } else {
            ok('Todas as senhas estão em hash bcrypt.');
        }

        /* Duas perguntas diferentes, e só a segunda vale sozinha: o
           sinalizador diz quem JÁ foi flagrado, a verificação diz quem ainda
           entra com a senha publicada. Um banco que nasceu com bcrypt tem o
           sinalizador zerado e a senha de fábrica valendo. */
        $provisorias = (int)$pdo->query(
            'SELECT COUNT(*) FROM usuarios WHERE senha_provisoria = 1'
        )->fetchColumn();

        $de_fabrica = sh_contas_senha_fabrica($pdo);
        if ($de_fabrica) {
            $graves++;
            grave(count($de_fabrica) . ' conta(s) ENTRAM com a senha publicada no repositório: '
                . implode(', ', array_map(function ($c) {
                      return $c['username'] . ' / ' . $c['senha'];
                  }, $de_fabrica)));
            echo "      Troque pelo painel, ou entre uma vez com cada uma: o login\n";
            echo "      reconhece a senha de fábrica e exige a troca na hora.\n";
        } elseif ($provisorias > 0) {
            $pendencias++;
            pendente($provisorias . ' conta(s) devem trocar a senha no próximo acesso.');
            echo "      Nenhuma delas entra com senha de fábrica — o login já barrou.\n";
        } else {
            ok('Nenhuma conta com senha de fábrica.');
        }

        $demo = $pdo->query("SELECT id, username, nome FROM usuarios WHERE demo = 1")->fetchAll();
        if ($demo) {
            if ($aplicar) {
                $removidas = 0;
                foreach ($demo as $conta) {
                    // Conta que já arbitrou não pode ser apagada sem quebrar a súmula.
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM jogos WHERE arbitro_id = ?');
                    $stmt->execute([$conta['id']]);
                    if ((int)$stmt->fetchColumn() > 0) {
                        pendente('"' . $conta['username'] . '" tem partidas arbitradas: '
                               . 'anonimize pelo painel em vez de apagar.');
                        continue;
                    }
                    $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$conta['id']]);
                    $removidas++;
                }
                ok($removidas . ' conta(s) de demonstração removida(s).');
            } else {
                $pendencias++;
                pendente(count($demo) . ' conta(s) de demonstração ainda existem: '
                       . implode(', ', array_column($demo, 'username')));
                echo "      Rode com --aplicar para removê-las.\n";
            }
        } else {
            ok('Nenhuma conta de demonstração.');
        }

        $admins = (int)$pdo->query(
            "SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin' AND status = 'ativo'"
        )->fetchColumn();
        if ($admins === 0) {
            $graves++;
            grave('Não há nenhuma conta de coordenação ativa.');
        } else {
            ok($admins . ' conta(s) de coordenação ativa(s).');
        }

        $com_2fa = (int)$pdo->query(
            "SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin' AND totp_ativado_em IS NOT NULL"
        )->fetchColumn();
        if ($com_2fa === 0) {
            $pendencias++;
            pendente('Nenhuma conta de coordenação usa verificação em duas etapas (SH-65).');
            echo "      Recomendado: a conta vê dado pessoal de aluno e de árbitro.\n";
        } else {
            ok($com_2fa . ' conta(s) de coordenação com segundo fator.');
        }
    } catch (PDOException $e) {
        $graves++;
        grave('Falha ao verificar as contas: ' . sh_log_excecao($e, 'verificar contas'));
    }
}

/* ── 4. Senha nova para a coordenação ───────────────────────────────────── */
if ($aplicar && $pdo !== null) {
    titulo('4. Senha da coordenação');

    echo "  Digite a nova senha da coordenação (mínimo " . SH_SENHA_MINIMA
       . " caracteres, com letra e número).\n";
    echo "  Deixe em branco para pular: ";

    // A senha é lida com o eco desligado quando o sistema permite.
    $senha = '';
    if (DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        shell_exec('stty -echo 2>/dev/null');
        $senha = trim((string)fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        echo "\n";
    } else {
        // No Windows não há como desligar o eco de forma portátil.
        echo "\n  (o que você digitar ficará visível na tela)\n  > ";
        $senha = trim((string)fgets(STDIN));
    }

    if ($senha === '') {
        pendente('Senha não alterada.');
    } elseif (($problema = sh_senha_politica($senha, 'admin')) !== '') {
        grave($problema);
        $graves++;
    } else {
        try {
            $stmt = $pdo->query("SELECT id FROM usuarios WHERE tipo = 'admin' ORDER BY id LIMIT 1");
            $id = (int)$stmt->fetchColumn();
            if ($id > 0 && sh_definir_senha($pdo, $id, $senha)) {
                ok('Senha da coordenação atualizada.');
            } else {
                $graves++;
                grave('Não foi possível gravar a senha.');
            }
        } catch (PDOException $e) {
            $graves++;
            grave('Falha ao gravar: referência ' . sh_log_excecao($e, 'gravar senha em produção'));
        }
    }
}

/* ── 5. Segredo do calendário (SH-84) ───────────────────────────────────── */
titulo('5. Segredo do calendário (SH-84)');

if (sh_segredo_feed_pendente()) {
    $pendencias++;
    pendente('O segredo do feed ainda não foi persistido.');
    echo "      Ele é gerado sozinho no primeiro acesso pela web, em\n";
    echo "      logs/segredo_feed.txt. Confirme que a pasta logs/ tem escrita.\n";
} else {
    ok('Segredo do calendário definido e persistido.');
}

/* ── 6. E-mail (SH-42) ──────────────────────────────────────────────────── */
titulo('6. Envio de e-mail (SH-42)');

$modo = sh_email_modo();
if ($modo === 'registro') {
    $pendencias++;
    pendente('Sem servidor de e-mail: as mensagens ficam em logs/emails/.');
    echo "      Protocolo, senha provisória e recuperação de senha não chegam\n";
    echo "      ao destinatário. Preencha SH_SMTP_* em config.local.php.\n";
} else {
    ok('Entrega por ' . $modo . ($modo === 'smtp' ? ' (' . SH_SMTP_HOST . ')' : '') . '.');
}

/* ── 7. Controlador e encarregado (SH-44) ───────────────────────────────── */
titulo('7. Controlador e encarregado (SH-44)');

if (sh_controlador_pendente()) {
    $pendencias++;
    pendente('Os dados do controlador ainda são os de fábrica.');
    echo "      A LGPD (arts. 9º, I e 41) exige identificação e contato reais.\n";
    echo "      Preencha SH_CONTROLADOR_* e SH_DPO_* em config.local.php.\n";
} else {
    ok('Controlador: ' . SH_CONTROLADOR_NOME);
    ok('Encarregado: ' . SH_DPO_NOME . ' <' . SH_EMAIL_DPO . '>');
}

/* ── 8. Cobrança (SH-41) ────────────────────────────────────────────────── */
titulo('8. Cobrança das assinaturas (SH-41)');

$faltas = sh_pagamento_pendencias();
if ($faltas) {
    $pendencias++;
    foreach ($faltas as $f) pendente($f);
} else {
    ok('Modo de cobrança: ' . sh_pagamento_modo() . '.');
}

/* ── 9. Ambiente ────────────────────────────────────────────────────────── */
titulo('9. Ambiente');

foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'openssl', 'iconv'] as $ext) {
    if (extension_loaded($ext)) {
        ok('Extensão ' . $ext . ' carregada.');
    } else {
        $graves++;
        grave('Extensão ' . $ext . ' AUSENTE — o sistema não funciona sem ela.');
    }
}

/* A GD merece tratamento à parte: sem ela o sistema sobe, mas duas proteções
   somem em silêncio — o reencode do escudo (que descarta código embutido em
   imagem) e o da foto da galeria (que descarta os metadados EXIF, inclusive a
   localização de onde a foto foi tirada). */
if (extension_loaded('gd')) {
    ok('Extensão gd carregada (reencode de imagem ativo).');
} else {
    $graves++;
    grave('Extensão gd AUSENTE.');
    echo '      Sem ela, o escudo do time é guardado como veio, sem o reencode' . PHP_EOL;
    echo '      que descarta código embutido em imagem — e a galeria recusa' . PHP_EOL;
    echo '      qualquer envio, porque não há como remover os metadados EXIF' . PHP_EOL;
    echo '      da foto (que incluem a localização de onde ela foi tirada).' . PHP_EOL;
    echo '      Correção: no php.ini (pasta php\ do XAMPP), tire o ponto e' . PHP_EOL;
    echo '      vírgula da linha ";extension=gd" e reinicie o Apache.' . PHP_EOL;
}

foreach (['logs', 'logs/emails', 'uploads', 'uploads/galeria', 'img/times'] as $dir) {
    $caminho = dirname(__DIR__) . '/' . $dir;
    if (!is_dir($caminho)) {
        $pendencias++;
        pendente('Pasta ' . $dir . '/ não existe.');
    } elseif (!is_writable($caminho)) {
        $graves++;
        grave('Pasta ' . $dir . '/ sem permissão de escrita.');
    } else {
        ok('Pasta ' . $dir . '/ gravável.');
    }
}

/* ── 10. Backup (SH-51) ─────────────────────────────────────────────────── */
titulo('10. Backup (SH-51)');

$historico = (DIRECTORY_SEPARATOR === '\\')
    ? 'C:/backups/sporthub/backup.log'
    : '/var/backups/sporthub/backup.log';

if (is_file($historico)) {
    $linhas = @file($historico, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $ultima = end($linhas);
    if ($ultima !== false && strpos($ultima, 'OK') !== false) {
        ok('Último backup registrado: ' . $ultima);
    } else {
        $pendencias++;
        pendente('O último registro de backup não terminou em OK: ' . $ultima);
    }
} else {
    $pendencias++;
    pendente('Nenhum backup registrado ainda.');
    echo "      Agende scripts/backup.bat (Windows) ou scripts/backup.sh (Linux).\n";
}

/* ── Resultado ──────────────────────────────────────────────────────────── */
echo "\n" . str_repeat('═', 68) . "\n";

if ($graves === 0 && $pendencias === 0) {
    echo "\033[32m Tudo pronto para produção.\033[0m\n";
} else {
    if ($graves > 0) {
        echo "\033[31m " . $graves . " item(ns) impeditivo(s).\033[0m";
    }
    if ($pendencias > 0) {
        echo ($graves > 0 ? ' · ' : ' ') . "\033[33m" . $pendencias . " pendência(s).\033[0m";
    }
    echo "\n Passo a passo completo em docs/publicacao.md\n";
}
echo str_repeat('═', 68) . "\n\n";

exit($graves > 0 ? 1 : 0);
