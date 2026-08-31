<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/listagem.php';
verificarLogin();

if (getUsuarioTipo() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Cadastrar árbitro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $senha    = (string)($_POST['password'] ?? '');
        $nome     = trim($_POST['nome'] ?? '');

        /* Validação no servidor: o `required` do HTML só vale para quem usa o
           formulário. Sem isto, um POST direto criava um árbitro com nome
           vazio e senha vazia — que é uma senha válida e funcional.        */
        if ($username === '' || $nome === '' || $senha === '') {
            $erro = 'Preencha usuário, nome e senha.';
        } elseif (mb_strlen($username) > 50 || mb_strlen($nome) > 100) {
            $erro = 'Usuário ou nome excedem o tamanho permitido.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $erro = 'O usuário aceita apenas letras, números, ponto, hífen e sublinhado.';
        } elseif (mb_strlen($senha) < 8) {
            $erro = 'A senha precisa ter ao menos 8 caracteres.';
        } elseif (!sh_pode_criar($pdo, 'arbitros')) {
            // Limite do plano (SH-57).
            $erro = 'O limite de árbitros do plano contratado foi atingido. '
                  . 'Nenhum árbitro existente foi removido — só não é possível cadastrar mais.';
        } else {
            try {
                $duplicado = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = ?");
                $duplicado->execute([$username]);

                if ((int)$duplicado->fetchColumn() > 0) {
                    $erro = 'Já existe uma conta com esse usuário.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, tipo, nome) VALUES (?, ?, 'arbitro', ?)");
                    $stmt->execute([$username, password_hash($senha, PASSWORD_DEFAULT), $nome]);
                    sh_auditar($pdo, 'arbitro_criado_manualmente', 'usuarios', (int)$pdo->lastInsertId(), $username);
                    header('Location: arbitros.php?sucesso=1');
                    exit();
                }
            } catch (PDOException $e) {
                sh_log_excecao($e, 'cadastrar arbitro');
                $erro = 'Erro interno. Contate o administrador.';
            }
        }
    }
}

/* Busca e paginação (SH-83). Contas anonimizadas a pedido do titular
   (SH-60) continuam na lista, com o estado à mostra: sumir com elas faria
   parecer que a conta foi apagada quando não foi. */
$lista_arbitros = sh_listar($pdo, [
    'contar' => "SELECT COUNT(*) FROM usuarios u",
    'buscar' => "SELECT u.* FROM usuarios u",
    'onde'   => "u.tipo = ?",
    'params' => ['arbitro'],
    'campos' => ['u.nome', 'u.username', 'u.email'],
    'ordem'  => 'u.nome',
    'por_pagina' => 15,
    'base_url'   => 'arbitros.php',
]);
$arbitros = $lista_arbitros['linhas'];

// Situação do plano (SH-57).
$limite_arbitros = sh_limite_plano($pdo, 'arbitros');
$aviso_plano     = sh_aviso_limite($pdo, 'arbitros', 'árbitros');

// Buscar total de jogos por árbitro
$jogos_por_arbitro = $pdo->query("
    SELECT 
        u.id,
        u.nome AS arbitro_nome,
        COUNT(j.id) AS total_jogos
    FROM usuarios u
    LEFT JOIN jogos j ON u.id = j.arbitro_id
    WHERE u.tipo = 'arbitro'
    GROUP BY u.id
    ORDER BY u.nome
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Gerenciar Árbitros</h2>
        
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert-success">Árbitro cadastrado com sucesso!</div>
        <?php endif; ?>

        <?php /* A mensagem de erro do cadastro existia como variável, mas nunca
                 chegava à tela: o formulário simplesmente voltava em branco. */ ?>
        <?php if (!empty($erro)): ?>
            <div class="alert-danger"><?= e($erro) ?></div>
        <?php endif; ?>

        <?php if ($aviso_plano !== ''): ?>
            <div class="<?= $limite_arbitros['atingido'] ? 'alert-danger' : 'alert-warning' ?>">
                <i class="fas fa-layer-group" aria-hidden="true"></i> <?= e($aviso_plano) ?>
                <a href="<?= e(sh_url('planos.php')) ?>" target="_blank" rel="noopener">Ver planos</a>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" data-tab="arbitros">Árbitros</div>
            <div class="tab" data-tab="cadastrar">Cadastrar Árbitro</div>
            <div class="tab" data-tab="designacoes">Designações</div>
        </div>
        
        <div class="tab-content active" id="arbitros">
            <?= sh_barra_busca($lista_arbitros, 'Buscar árbitro por nome, usuário ou e-mail', 'árbitro') ?>

            <div class="tabela-rolavel">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Situação</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($arbitros as $arbitro): ?>
                    <tr>
                        <td><?= htmlspecialchars($arbitro['nome']) ?></td>
                        <td><?= htmlspecialchars($arbitro['username']) ?></td>
                        <td><span class="selo selo--<?= e($arbitro['status'] ?? 'ativo') ?>"><?= e($arbitro['status'] ?? 'ativo') ?></span></td>
                        <td><?= date('d/m/Y', strtotime($arbitro['created_at'])) ?></td>
                        <td>
                            <a href="editar_arbitro.php?id=<?= $arbitro['id'] ?>" class="btn-small btn-accent">Editar</a>
                            <a href="excluir_arbitro.php?id=<?= $arbitro['id'] ?>&csrf_token=<?= e(generate_csrf_token()) ?>" class="btn-small btn-danger"
                               data-confirmar="Deseja excluir este árbitro?">
                               Excluir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$arbitros): ?>
                        <tr><td colspan="5" class="u-color-muted">
                            <?= e(sh_vazio_listagem($lista_arbitros, 'Nenhum árbitro cadastrado ainda.', 'Nenhum árbitro bate com “%s”.')) ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>

            <?= sh_navegacao_paginas($lista_arbitros, 'Paginação dos árbitros') ?>
        </div>
        
        <div class="tab-content" id="cadastrar">
            <form method="POST" class="form-panel">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required minlength="8"
                           autocomplete="new-password" aria-describedby="dica-senha">
                    <small id="dica-senha" class="form-hint">Mínimo de 8 caracteres.</small>
                </div>
                <button type="submit" class="btn-secondary"
                    <?= $limite_arbitros['atingido'] ? 'disabled' : '' ?>>Cadastrar Árbitro</button>
            </form>
        </div>
        
        <div class="tab-content" id="designacoes">
            <table>
                <thead>
                    <tr>
                        <th>Árbitro</th>
                        <th>Jogos Designados</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jogos_por_arbitro as $designacao): ?>
                    <tr>
                        <td><?= htmlspecialchars($designacao['arbitro_nome']) ?></td>
                        <td><?= $designacao['total_jogos'] ?> jogos</td>
                        <td>
                            <a href="designar_jogos.php?arbitro_id=<?= $designacao['id'] ?>" class="btn-small">
                                Designar Jogos
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
