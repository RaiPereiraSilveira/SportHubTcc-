<?php
/**
 * includes/lgpd_conta.php — eliminação e anonimização de conta (SH-60)
 *
 * O portal do titular já recebia o pedido de eliminação (art. 18, VI) e
 * gerava protocolo. O que não existia era a execução: alguém tinha que abrir
 * o phpMyAdmin e apagar linhas à mão, torcendo para não quebrar uma chave
 * estrangeira no meio.
 *
 * ── Por que anonimizar em vez de apagar ─────────────────────────────────
 *
 * A LGPD não obriga a apagar tudo. O art. 16 permite (e às vezes exige) a
 * conservação para cumprimento de obrigação legal, e o art. 12 diz que dado
 * anonimizado deixa de ser dado pessoal. Aqui isso importa muito:
 *
 * · A súmula de uma partida é registro do campeonato. Apagar o árbitro que a
 *   assinou destruiria a integridade do resultado — e o resultado interessa a
 *   todos os outros times, não só a quem pediu a exclusão.
 * · `jogos.arbitro_id` e `auditoria.usuario_id` apontam para `usuarios`.
 *   DELETE puro quebraria a referência ou apagaria a trilha de prestação de
 *   contas, que é justamente a prova de que a escola cumpre a lei.
 *
 * Então a conta é DESPERSONALIZADA: nome vira "Usuário anonimizado #id",
 * e-mail, CPF, telefone e foto somem de verdade, a senha vira um valor
 * aleatório impossível de adivinhar, e o status passa a `anonimizado` — o
 * login já recusa esse status desde o SH-27. O que fica é a casca sem
 * pessoa dentro: o suficiente para a súmula continuar íntegra, insuficiente
 * para identificar alguém.
 *
 * A eliminação real (DELETE) existe e é oferecida para o caso em que o
 * titular insiste e não há vínculo nenhum a preservar — conta de aluno que
 * nunca entrou em súmula. A função avisa quando não dá.
 */

require_once __DIR__ . '/config.php';

/**
 * O que impede a eliminação total desta conta.
 * Lista vazia = pode apagar de verdade.
 */
function sh_vinculos_da_conta(PDO $pdo, $usuario_id) {
    $usuario_id = (int)$usuario_id;
    $vinculos   = [];

    $checagens = [
        ['jogos', "SELECT COUNT(*) FROM jogos WHERE arbitro_id = ?",
         'partida(s) arbitrada(s) — a súmula deixaria de ter responsável'],
        ['auditoria', "SELECT COUNT(*) FROM auditoria WHERE usuario_id = ?",
         'registro(s) na trilha de auditoria — é a prova de conformidade da escola'],
        ['ocorrencias', "SELECT COUNT(*) FROM ocorrencias WHERE registrada_por = ?",
         'ocorrência(s) disciplinar(es) registrada(s)'],
    ];

    foreach ($checagens as [$tabela, $sql, $texto]) {
        if (!sh_tabela_existe($pdo, $tabela)) continue;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id]);
            $n = (int)$stmt->fetchColumn();
            if ($n > 0) $vinculos[] = $n . ' ' . $texto;
        } catch (PDOException $e) {
            sh_log_excecao($e, 'verificar vínculos da conta ' . $usuario_id);
        }
    }
    return $vinculos;
}

/**
 * Despersonaliza a conta, preservando as referências do campeonato.
 *
 * @return array{ok:bool, mensagem:string}
 */
function sh_anonimizar_conta(PDO $pdo, $usuario_id, $motivo = 'pedido do titular (art. 18, VI)') {
    $usuario_id = (int)$usuario_id;

    try {
        $stmt = $pdo->prepare('SELECT id, username, tipo, nome, status, foto_perfil FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
    } catch (PDOException $e) {
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'carregar a conta')];
    }

    if (!$usuario) return ['ok' => false, 'mensagem' => 'Conta não encontrada.'];
    if ($usuario['status'] === 'anonimizado') {
        return ['ok' => false, 'mensagem' => 'Esta conta já está anonimizada.'];
    }

    /* A última conta de administração não pode ser anonimizada: o sistema
       ficaria sem ninguém capaz de administrá-lo, e recuperar isso exigiria
       acesso direto ao banco. */
    if ($usuario['tipo'] === 'admin') {
        try {
            $restantes = (int)$pdo->query(
                "SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin' AND status = 'ativo'"
            )->fetchColumn();
            if ($restantes <= 1) {
                return ['ok' => false, 'mensagem' =>
                    'Esta é a última conta de coordenação ativa. Crie outra antes de anonimizar '
                  . 'esta, ou o sistema ficaria sem administrador.'];
            }
        } catch (PDOException $e) {
            sh_log_excecao($e, 'contar administradores');
        }
    }

    $apelido = 'Usuário anonimizado #' . $usuario_id;
    $login   = 'anon_' . $usuario_id . '_' . substr(bin2hex(random_bytes(4)), 0, 6);

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            "UPDATE usuarios
                SET nome = ?, username = ?, email = NULL, telefone = NULL, cpf = NULL,
                    foto_perfil = NULL, password = ?, status = 'anonimizado',
                    totp_segredo = NULL, totp_ativado_em = NULL,
                    anonimizado_em = NOW()
              WHERE id = ?"
        )->execute([
            $apelido, $login,
            // Senha aleatória e descartada: ninguém, nem o administrador, entra nesta conta.
            password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            $usuario_id,
        ]);

        // Dados de credenciamento do árbitro: some tudo o que identifica.
        if (sh_tabela_existe($pdo, 'arbitro_perfil')) {
            $pdo->prepare('DELETE FROM arbitro_perfil WHERE usuario_id = ?')->execute([$usuario_id]);
        }
        if (sh_tabela_existe($pdo, 'senha_tokens')) {
            $pdo->prepare('DELETE FROM senha_tokens WHERE usuario_id = ?')->execute([$usuario_id]);
        }
        if (sh_tabela_existe($pdo, 'totp_codigos')) {
            $pdo->prepare('DELETE FROM totp_codigos WHERE usuario_id = ?')->execute([$usuario_id]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'anonimizar a conta')];
    }

    // A foto de perfil é arquivo, não linha de tabela: precisa sumir do disco.
    if (!empty($usuario['foto_perfil'])) {
        sh_remover_arquivo_do_usuario($usuario['foto_perfil']);
    }

    sh_auditar($pdo, 'conta_anonimizada', 'usuarios', $usuario_id, $motivo);

    return ['ok' => true, 'mensagem' =>
        'Conta anonimizada. Nome, e-mail, CPF, telefone e foto foram removidos; '
      . 'as súmulas e a trilha de auditoria continuam íntegras, agora sem identificar ninguém.'];
}

/**
 * Elimina a conta de verdade (DELETE), quando não há vínculo a preservar.
 *
 * @return array{ok:bool, mensagem:string}
 */
function sh_eliminar_conta(PDO $pdo, $usuario_id, $motivo = 'pedido do titular (art. 18, VI)') {
    $usuario_id = (int)$usuario_id;
    $vinculos   = sh_vinculos_da_conta($pdo, $usuario_id);

    if ($vinculos) {
        return ['ok' => false, 'mensagem' =>
            'Esta conta não pode ser apagada porque tem ' . implode('; ', $vinculos)
          . '. Use a anonimização: ela remove todo dado pessoal e preserva o que a '
          . 'lei manda conservar (art. 16 da LGPD).'];
    }

    try {
        $stmt = $pdo->prepare('SELECT username, tipo, foto_perfil FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        if (!$usuario) return ['ok' => false, 'mensagem' => 'Conta não encontrada.'];

        if ($usuario['tipo'] === 'admin') {
            $restantes = (int)$pdo->query(
                "SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin' AND status = 'ativo'"
            )->fetchColumn();
            if ($restantes <= 1) {
                return ['ok' => false, 'mensagem' =>
                    'Esta é a última conta de coordenação ativa e não pode ser apagada.'];
            }
        }

        $pdo->beginTransaction();
        foreach (['senha_tokens', 'totp_codigos', 'arbitro_perfil'] as $t) {
            if (sh_tabela_existe($pdo, $t)) {
                $coluna = ($t === 'arbitro_perfil') ? 'usuario_id' : 'usuario_id';
                $pdo->prepare("DELETE FROM $t WHERE $coluna = ?")->execute([$usuario_id]);
            }
        }
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$usuario_id]);
        $pdo->commit();

        if (!empty($usuario['foto_perfil'])) {
            sh_remover_arquivo_do_usuario($usuario['foto_perfil']);
        }

        /* A auditoria registra a eliminação SEM o identificador da conta que
           deixou de existir — registrar o id de uma linha apagada não ajuda
           ninguém e o nome não deve sobreviver ao pedido. */
        sh_auditar($pdo, 'conta_eliminada', 'usuarios', null, $motivo);

        return ['ok' => true, 'mensagem' => 'Conta eliminada definitivamente.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'eliminar a conta')];
    }
}

/**
 * Apaga um arquivo enviado pelo usuário, com verificação de caminho.
 * O realpath impede que um valor manipulado no banco aponte para fora de
 * uploads/ e leve o DELETE para um arquivo do sistema.
 */
function sh_remover_arquivo_do_usuario($caminho_relativo) {
    $base = realpath(dirname(__DIR__) . '/uploads');
    if ($base === false) return false;

    $alvo = realpath(dirname(__DIR__) . '/' . ltrim((string)$caminho_relativo, '/'));
    if ($alvo === false) return false;
    if (strpos($alvo, $base) !== 0) return false;   // fora de uploads/: recusa

    return @unlink($alvo);
}

/**
 * Portabilidade e acesso (art. 18, II e V): tudo o que o sistema guarda
 * sobre um usuário, em JSON legível.
 *
 * O array é montado explicitamente — nada de `SELECT *` — para que uma coluna
 * nova acrescentada no futuro não vaze para a exportação sem alguém decidir.
 */
function sh_exportar_dados_titular(PDO $pdo, $usuario_id) {
    $usuario_id = (int)$usuario_id;
    $saida = ['gerado_em' => date('c'), 'sistema' => SH_NOME];

    try {
        $stmt = $pdo->prepare(
            'SELECT id, username, tipo, status, nome, email, telefone, cpf,
                    aceite_termos_em, aceite_privacidade_em, ultimo_acesso, created_at
               FROM usuarios WHERE id = ?'
        );
        $stmt->execute([$usuario_id]);
        $saida['conta'] = $stmt->fetch() ?: null;

        if (sh_tabela_existe($pdo, 'lgpd_consentimentos')) {
            $stmt = $pdo->prepare(
                'SELECT finalidade, concedido, versao_texto, created_at
                   FROM lgpd_consentimentos WHERE usuario_id = ? ORDER BY created_at'
            );
            $stmt->execute([$usuario_id]);
            $saida['consentimentos'] = $stmt->fetchAll();
        }

        if (sh_tabela_existe($pdo, 'arbitro_perfil')) {
            $stmt = $pdo->prepare('SELECT * FROM arbitro_perfil WHERE usuario_id = ?');
            $stmt->execute([$usuario_id]);
            $saida['perfil_arbitragem'] = $stmt->fetchAll();
        }

        $stmt = $pdo->prepare(
            "SELECT j.id, j.data_jogo, j.hora, j.local, j.placar_time1, j.placar_time2,
                    m.nome AS modalidade
               FROM jogos j JOIN modalidades m ON m.id = j.modalidade_id
              WHERE j.arbitro_id = ? ORDER BY j.data_jogo"
        );
        $stmt->execute([$usuario_id]);
        $saida['partidas_arbitradas'] = $stmt->fetchAll();

        if (sh_tabela_existe($pdo, 'auditoria')) {
            $stmt = $pdo->prepare(
                'SELECT acao, entidade, entidade_id, created_at
                   FROM auditoria WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 500'
            );
            $stmt->execute([$usuario_id]);
            $saida['registros_de_atividade'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        sh_log_excecao($e, 'exportar dados do titular');
        $saida['erro'] = 'Parte dos dados não pôde ser lida; procure a coordenação.';
    }

    sh_auditar($pdo, 'dados_titular_exportados', 'usuarios', $usuario_id);
    return $saida;
}
