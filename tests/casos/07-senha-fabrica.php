<?php
/**
 * tests/casos/07-senha-fabrica.php — detecção da senha de fábrica (SH-48).
 *
 * Este caso existe por causa de um bug que passou despercebido por três
 * sprints. A `migration_v3.sql` marcava `senha_provisoria = 1` apenas nas
 * contas que ainda guardavam a senha em TEXTO PURO na coluna `password`.
 * Numa instalação que já tinha convertido para bcrypt — o caso normal — o
 * UPDATE não casava com nenhuma linha, ninguém era marcado, e tanto o painel
 * quanto o `preparar_producao.php` afirmavam "nenhuma conta com senha de
 * fábrica" enquanto `admin` / `admin1234` continuava entrando normalmente.
 *
 * O sinalizador respondia à pergunta errada. Ele diz quem JÁ foi flagrado;
 * a pergunta que importa antes de publicar é quem AINDA entra com a senha
 * publicada no repositório. Como o bcrypt tem sal, essa segunda pergunta só
 * se responde verificando hash por hash.
 *
 * Os hashes daqui usam custo 4 de propósito: o teste exercita a lógica, não
 * a lentidão do bcrypt.
 */

grupo('Senha de fábrica — reconhecimento direto (sh_senha_de_fabrica)');

verificar('reconhece admin1234',      sh_senha_de_fabrica('admin1234'));
verificar('reconhece arbitro1234',    sh_senha_de_fabrica('arbitro1234'));
verificar('reconhece professor1234',  sh_senha_de_fabrica('professor1234'));
verificar('reconhece aluno1234',      sh_senha_de_fabrica('aluno1234'));

verificar('não acusa senha própria',  !sh_senha_de_fabrica('interclasse2026'));
verificar('não acusa string vazia',   !sh_senha_de_fabrica(''));
verificar('compara exatamente, sem variar maiúscula',
    !sh_senha_de_fabrica('Admin1234'));

igual('a lista tem as quatro contas do seed', 4, count(SH_SENHAS_FABRICA));

grupo('Senha de fábrica — política recusa todas');

foreach (SH_SENHAS_FABRICA as $senha) {
    verificar('a política recusa ' . $senha, sh_senha_politica($senha) !== '');
}

grupo('Senha de fábrica — auditoria do banco (sh_contas_senha_fabrica)');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    verificar('driver sqlite disponível para o teste de auditoria', false,
        'sem pdo_sqlite não há como montar o banco de mentira');
} else {
    $banco = new PDO('sqlite::memory:');
    $banco->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $banco->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $banco->exec('CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY,
        username TEXT,
        password TEXT,
        senha_provisoria INTEGER DEFAULT 0
    )');

    $rapido = ['cost' => 4];
    $inserir = $banco->prepare(
        'INSERT INTO usuarios (id, username, password, senha_provisoria) VALUES (?, ?, ?, ?)'
    );

    /* O caso do bug: hash bcrypt legítimo da senha de fábrica, e o
       sinalizador zerado — que é como a migration deixava o banco. */
    $inserir->execute([1, 'admin', password_hash('admin1234', PASSWORD_BCRYPT, $rapido), 0]);
    /* Conta saudável: senha própria, forte. */
    $inserir->execute([2, 'coordenacao', password_hash('interclasse2026', PASSWORD_BCRYPT, $rapido), 0]);
    /* Instalação antiga: texto puro. Não deve derrubar a varredura. */
    $inserir->execute([3, 'antigo', 'aluno1234', 0]);
    /* Outra de fábrica, esta já marcada. */
    $inserir->execute([4, 'arbitro', password_hash('arbitro1234', PASSWORD_BCRYPT, $rapido), 1]);

    $achadas = sh_contas_senha_fabrica($banco);
    $nomes   = array_column($achadas, 'username');
    sort($nomes);

    igual('acha as duas contas com hash de senha de fábrica',
        ['admin', 'arbitro'], $nomes);

    verificar('acha a conta de fábrica mesmo com senha_provisoria = 0',
        in_array('admin', $nomes, true));
    verificar('não acusa a conta com senha própria',
        !in_array('coordenacao', $nomes, true));
    verificar('senha em texto puro não vira falso positivo',
        !in_array('antigo', $nomes, true));

    $por_conta = [];
    foreach ($achadas as $c) { $por_conta[$c['username']] = $c['senha']; }
    igual('informa qual senha abre a conta admin',   'admin1234',   $por_conta['admin'] ?? null);
    igual('informa qual senha abre a conta arbitro', 'arbitro1234', $por_conta['arbitro'] ?? null);

    $banco->exec("UPDATE usuarios SET password = '"
        . password_hash('interclasse2026', PASSWORD_BCRYPT, $rapido) . "' WHERE id IN (1, 4)");
    igual('banco limpo não devolve nenhuma conta', 0, count(sh_contas_senha_fabrica($banco)));
}
