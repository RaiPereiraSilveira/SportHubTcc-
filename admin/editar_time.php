<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');
$sucesso = '';
$erro = '';

// Buscar dados do time se estiver editando
$time = null;
$jogadores = [];
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM times WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $time = $stmt->fetch();
    
    if (!$time) {
        $erro = "Time não encontrado!";
    } else {
        // Buscar jogadores do time
        $stmt = $pdo->prepare("SELECT * FROM jogadores WHERE time_id = ? ORDER BY numero_camisa");
        $stmt->execute([$_GET['id']]);
        $jogadores = $stmt->fetchAll();
    }
}

// Processar formulário do time
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_time'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $nome   = trim((string)($_POST['nome'] ?? ''));
        $sala   = trim((string)($_POST['sala'] ?? ''));
        $genero = (string)($_POST['genero'] ?? '');
    }

    // Validação no servidor (o "required" do HTML não protege contra
    // requisições feitas fora do formulário).
    if ($erro === '') {
        if ($nome === '' || $sala === '') {
            $erro = 'Preencha o nome do time e a sala.';
        } elseif (mb_strlen($nome) > 100 || mb_strlen($sala) > 20) {
            $erro = 'Nome (100) ou sala (20) excedem o tamanho permitido.';
        } elseif (!in_array($genero, ['masculino', 'feminino', 'misto'], true)) {
            $erro = 'Selecione um gênero válido.';
        }
    }

    if ($erro === '') {
        try {
            if (isset($_GET['id'])) {
                // Atualizar time existente
                $stmt = $pdo->prepare("UPDATE times SET nome = ?, sala = ?, genero = ? WHERE id = ?");
                $stmt->execute([$nome, $sala, $genero, $_GET['id']]);
                $sucesso = "Time atualizado com sucesso!";
            } else {
                // Criar novo time
                $stmt = $pdo->prepare("INSERT INTO times (nome, sala, genero) VALUES (?, ?, ?)");
                $stmt->execute([$nome, $sala, $genero]);
                
                // Redirecionar para a lista de times com mensagem de sucesso
                $_SESSION['sucesso'] = "Time cadastrado com sucesso!";
                header('Location: times.php');
                exit();
            }
        } catch(PDOException $e) {
            error_log('Erro ao salvar time: ' . $e->getMessage());
            $erro = 'Erro ao salvar time. Contate o administrador.';
        }
    }
}

// Processar adição de jogador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_jogador']) && isset($_GET['id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $nome_jogador  = trim((string)($_POST['nome_jogador'] ?? ''));
        $numero_camisa = ($_POST['numero_camisa'] ?? '') !== '' ? (int)$_POST['numero_camisa'] : null;

        if ($nome_jogador === '') {
            $erro = 'Informe o nome do jogador.';
        } elseif (mb_strlen($nome_jogador) > 100) {
            $erro = 'O nome do jogador deve ter no máximo 100 caracteres.';
        } elseif ($numero_camisa !== null && ($numero_camisa < 0 || $numero_camisa > 999)) {
            $erro = 'Número de camisa inválido.';
        }
    }

    if ($erro === '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO jogadores (time_id, nome, numero_camisa) VALUES (?, ?, ?)");
            $stmt->execute([(int)$_GET['id'], $nome_jogador, $numero_camisa]);
            $sucesso = "Jogador adicionado com sucesso!";
            
            // Recarregar lista de jogadores
            $stmt = $pdo->prepare("SELECT * FROM jogadores WHERE time_id = ? ORDER BY numero_camisa");
            $stmt->execute([$_GET['id']]);
            $jogadores = $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log('Erro ao adicionar jogador: ' . $e->getMessage());
            $erro = 'Erro ao adicionar jogador. Contate o administrador.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">
            <?= isset($_GET['id']) ? 'Editar Time' : 'Cadastrar Novo Time' ?>
        </h2>
        
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &gt; 
            <a href="times.php">Times</a> &gt; 
            <span><?= isset($_GET['id']) ? 'Editar' : 'Novo' ?></span>
        </div>

        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <?= e($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <?= e($erro) ?>
            </div>
        <?php endif; ?>

        <div class="form-panel">
            <form method="POST">
                <input type="hidden" name="salvar_time" value="1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                
                <div class="form-section">
                    <h3>Informações do Time</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome do Time *</label>
                            <input type="text" id="nome" name="nome" 
                                value="<?= $time ? htmlspecialchars($time['nome']) : '' ?>" 
                                placeholder="Ex: Leões do 9ºA" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="sala">Sala/Turma *</label>
                            <input type="text" id="sala" name="sala" 
                                value="<?= $time ? htmlspecialchars($time['sala']) : '' ?>" 
                                placeholder="Ex: 9º Ano A" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="genero">Gênero *</label>
                        <select id="genero" name="genero" required>
                            <option value="">Selecione o gênero</option>
                            <option value="masculino" <?= ($time && $time['genero'] == 'masculino') ? 'selected' : '' ?>>Masculino</option>
                            <option value="feminino" <?= ($time && $time['genero'] == 'feminino') ? 'selected' : '' ?>>Feminino</option>
                            <option value="misto" <?= ($time && $time['genero'] == 'misto') ? 'selected' : '' ?>>Misto</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?= isset($_GET['id']) ? 'Atualizar Time' : 'Cadastrar Time' ?>
                    </button>
                    <a href="times.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar para Lista
                    </a>
                </div>
            </form>
        </div>

        <?php if (isset($_GET['id'])): ?>
        <div class="players-section">
            <h3>Jogadores do Time</h3>
            
            <div class="add-player-form">
                <form method="POST" class="inline-form">
                    <input type="hidden" name="adicionar_jogador" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome_jogador">Nome do Jogador</label>
                            <input type="text" id="nome_jogador" name="nome_jogador" 
                                placeholder="Nome completo do jogador" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_camisa">Número da Camisa</label>
                            <input type="number" id="numero_camisa" name="numero_camisa" 
                                min="1" max="99" placeholder="Número" required>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-accent">
                                <i class="fas fa-plus"></i>
                                Adicionar Jogador
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (count($jogadores) > 0): ?>
                <div class="players-list">
                    <table class="players-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Nome</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jogadores as $jogador): ?>
                            <tr>
                                <td class="player-number"><?= e($jogador['numero_camisa']) ?></td>
                                <td class="player-name"><?= htmlspecialchars($jogador['nome']) ?></td>
                                <td class="player-actions">
                                                <a href="excluir_jogador.php?id=<?= $jogador['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" 
                                       class="btn-small btn-danger"
                                       onclick="return confirm('Tem certeza que deseja excluir este jogador?')">
                                        <i class="fas fa-trash"></i>
                                        Excluir
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-players">
                    <p>Nenhum jogador cadastrado neste time.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>


<?php include '../includes/footer.php'; ?>