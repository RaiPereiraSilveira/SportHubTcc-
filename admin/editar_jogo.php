<?php
// A autorizacao roda ANTES de qualquer saida HTML: se o cabecalho fosse
// impresso primeiro, o header('Location: ...') nao teria efeito e quem
// nao tem permissao continuaria vendo a pagina.
require_once __DIR__ . '/../includes/config.php';
exigirPerfil('admin', '../login.php');
$mensagem = "";
$erro = "";

// Buscar modalidades
$modalidades = $pdo->query("SELECT * FROM modalidades ORDER BY nome")->fetchAll();

// Ao gerar o chaveamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $modalidade_id = $_POST['modalidade_id'] ?? null;

        if (!$modalidade_id) {
            $erro = "Selecione uma modalidade!";
        } else {

            try {
            // Dados da modalidade
            $stmt = $pdo->prepare("SELECT * FROM modalidades WHERE id = ?");
            $stmt->execute([$modalidade_id]);
            $modalidade = $stmt->fetch();

            if (!$modalidade) {
                throw new Exception("Modalidade não encontrada.");
            }

            // Buscar times compatíveis (pelo gênero)
            $sql = "
                SELECT * FROM times 
                WHERE genero = ? OR genero = 'misto'
                ORDER BY nome
            ";
            $stmtTimes = $pdo->prepare($sql);
            $stmtTimes->execute([$modalidade['genero']]);
            $times = $stmtTimes->fetchAll();

            if (count($times) < 2) {
                throw new Exception("Não há times suficientes para gerar confrontos nesta modalidade.");
            }

            // Gerar todos contra todos
            $totalCriados = 0;

            for ($i = 0; $i < count($times); $i++) {
                for ($j = $i + 1; $j < count($times); $j++) {

                    $time1 = $times[$i]['id'];
                    $time2 = $times[$j]['id'];

                    // Verificar se já existe jogo
                    $check = $pdo->prepare("
                        SELECT COUNT(*) FROM jogos
                        WHERE modalidade_id = ?
                        AND (
                            (time1_id = ? AND time2_id = ?) OR
                            (time1_id = ? AND time2_id = ?)
                        )
                    ");
                    $check->execute([$modalidade_id, $time1, $time2, $time2, $time1]);

                    if ($check->fetchColumn() == 0) {

                        // Criar novo jogo sem data/local
                        $insert = $pdo->prepare("
                            INSERT INTO jogos 
                            (modalidade_id, time1_id, time2_id, fase, status)
                            VALUES (?, ?, ?, 'Fase de grupos', 'agendado')
                        ");
                        $insert->execute([$modalidade_id, $time1, $time2]);

                        $totalCriados++;
                    }
                }
            }

            $mensagem = "Chaveamento gerado com sucesso! Total de jogos criados: <b>$totalCriados</b>.";

            } catch (Exception $e) {
                error_log('Erro ao gerar chaveamento: ' . $e->getMessage());
                $erro = 'Erro ao gerar chaveamento. Contate o administrador.';
            }
    }
}

}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-panel">
        <h2 class="panel-title">Gerar Chaveamento de Modalidade</h2>

        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &gt; 
            <a href="jogos.php">Jogos</a> &gt; 
            <span>Gerar Chaveamento</span>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-success">✅ <?= $mensagem ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-error">❌ <?= e($erro) ?></div>
        <?php endif; ?>

        <div class="form-panel">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                <div class="form-group">
                    <label for="modalidade_id">Escolha a modalidade</label>
                    <select id="modalidade_id" name="modalidade_id" required>
                        <option value="">Selecione...</option>

                        <?php foreach ($modalidades as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= htmlspecialchars($m['nome']) ?> (<?= $m['genero'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:20px;">
                    🔄 Gerar todos os confrontos desta modalidade
                </button>

                <a href="jogos.php" class="btn btn-secondary" style="margin-left:10px;">
                    ⬅ Voltar
                </a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
