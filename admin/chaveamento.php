<?php
/**
 * admin/chaveamento.php — mata-mata (SH-55)
 *
 * A fase de grupos já era gerada por `editar_jogo.php` (todos contra todos).
 * O que faltava era o depois: montar as quartas com os classificados e ir
 * empurrando quem vence para a fase seguinte. Isso era feito à mão, jogo por
 * jogo — e refeito inteiro sempre que um resultado mudava.
 *
 * A árvore é criada de uma vez por `sh_gerar_chaveamento()`; cada posição já
 * nasce sabendo para onde manda o vencedor. Quando o árbitro encerra uma
 * partida da chave, `sh_chaveamento_ao_encerrar_jogo()` promove o vencedor
 * sozinho — esta tela não precisa recalcular nada, só desenhar.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/campeonato.php';
exigirPerfil('admin', '../login.php');

$erro    = '';
$sucesso = '';

$modalidades = sh_lista_modalidades($pdo);
$modalidade_id = (int)($_GET['modalidade'] ?? $_POST['modalidade_id'] ?? 0);
if ($modalidade_id === 0 && $modalidades) {
    $modalidade_id = (int)$modalidades[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'gerar') {
        $quantos = (int)($_POST['quantos'] ?? 8);
        $r = sh_gerar_chaveamento($pdo, $modalidade_id, $quantos);
        if ($r['ok']) $sucesso = $r['mensagem'];
        else          $erro    = $r['mensagem'];

    } elseif ($acao === 'agendar') {
        $r = sh_agendar_jogo_da_chave(
            $pdo,
            (int)($_POST['chave_id'] ?? 0),
            trim((string)($_POST['data_jogo'] ?? '')) ?: null,
            trim((string)($_POST['hora'] ?? '')) ?: null,
            mb_substr(trim((string)($_POST['local'] ?? '')), 0, 100) ?: null
        );
        if ($r['ok']) $sucesso = $r['mensagem'];
        else          $erro    = $r['mensagem'];
    }
}

$fases = $modalidade_id > 0 ? sh_chaveamento($pdo, $modalidade_id) : [];
$classificacao = $modalidade_id > 0 ? sh_classificacao($pdo, $modalidade_id) : [];
$times_com_jogo = count(array_filter($classificacao, function ($t) { return $t['jogos'] > 0; }));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

    <div class="admin-panel">
        <h2 class="panel-title">
            <i class="fas fa-sitemap" aria-hidden="true"></i>
            Chaveamento de mata-mata
        </h2>
        <p class="panel-sub">
            Monta a fase eliminatória a partir da classificação: o 1º pega o último
            classificado, o 2º pega o penúltimo, e assim por diante. Quem vence sobe
            de fase sozinho quando o árbitro encerra a partida.
        </p>

        <?php if ($erro !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status"><?= e($sucesso) ?></div>
        <?php endif; ?>

        <form method="GET" class="filtro-linha">
            <label for="modalidade">Modalidade</label>
            <select id="modalidade" name="modalidade" data-auto-submit>
                <?php foreach ($modalidades as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $modalidade_id === (int)$m['id'] ? 'selected' : '' ?>>
                        <?= e($m['nome']) ?> (<?= e($m['genero']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-small">Ver</button>
        </form>

        <?php if ($modalidade_id > 0): ?>
        <form method="POST" class="form-panel u-margin-top-20px">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="gerar">
            <input type="hidden" name="modalidade_id" value="<?= $modalidade_id ?>">

            <div class="form-linha">
                <div class="form-group">
                    <label for="quantos">Quantos times entram na chave</label>
                    <select id="quantos" name="quantos">
                        <?php foreach ([2, 4, 8, 16, 32] as $q): ?>
                            <option value="<?= $q ?>" <?= $q === 8 ? 'selected' : '' ?>>
                                <?= $q ?> melhores<?= $q > $times_com_jogo ? ' (só há ' . $times_com_jogo . ' com jogos)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">
                        Quando não houver classificados suficientes, os melhores recebem
                        passagem direta (BYE) para a fase seguinte.
                    </small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"
                    data-confirmar="Gerar a chave desta modalidade? A chave anterior é substituída (as partidas já jogadas continuam no histórico).">
                <?= $fases ? 'Refazer chaveamento' : 'Gerar chaveamento' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($fases): ?>
    <div class="admin-panel u-margin-top-30px">
        <h2 class="panel-title">Chave</h2>

        <div class="chave-quadro">
            <?php foreach ($fases as $fase): ?>
                <div class="chave-fase">
                    <h3 class="chave-fase__nome"><?= e($fase['nome']) ?></h3>

                    <?php foreach ($fase['jogos'] as $chave): ?>
                        <?php
                            $definido = $chave['time1_id'] && $chave['time2_id'];
                            $bye      = ($chave['time1_id'] xor $chave['time2_id']);
                            $venc     = (int)($chave['vencedor_id'] ?? 0);
                        ?>
                        <article class="chave-jogo<?= $venc ? ' is-resolvido' : '' ?>">
                            <div class="chave-lado<?= $venc && $venc === (int)$chave['time1_id'] ? ' is-vencedor' : '' ?>">
                                <span class="chave-time"><?= e($chave['time1_nome'] ?? 'a definir') ?></span>
                                <?php if ($chave['jogo_status'] === 'finalizado'): ?>
                                    <span class="chave-placar"><?= (int)$chave['placar_time1'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="chave-lado<?= $venc && $venc === (int)$chave['time2_id'] ? ' is-vencedor' : '' ?>">
                                <span class="chave-time">
                                    <?= $bye ? '<em>passagem direta</em>' : e($chave['time2_nome'] ?? 'a definir') ?>
                                </span>
                                <?php if ($chave['jogo_status'] === 'finalizado'): ?>
                                    <span class="chave-placar"><?= (int)$chave['placar_time2'] ?></span>
                                <?php endif; ?>
                            </div>

                            <footer class="chave-rodape">
                                <?php if ($chave['jogo_id']): ?>
                                    <span>
                                        <?= !empty($chave['data_jogo'])
                                            ? date('d/m', strtotime($chave['data_jogo'])) : 'sem data' ?>
                                        <?= !empty($chave['hora']) ? substr($chave['hora'], 0, 5) : '' ?>
                                        · <?= e($chave['local'] ?? '') ?>
                                    </span>
                                    <?php if ($chave['jogo_status'] === 'finalizado'
                                              && (int)$chave['placar_time1'] === (int)$chave['placar_time2']): ?>
                                        <span class="chave-aviso">
                                            Empate: defina o classificado editando o placar.
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($definido): ?>
                                    <details>
                                        <summary>Agendar partida</summary>
                                        <form method="POST" class="chave-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="acao" value="agendar">
                                            <input type="hidden" name="modalidade_id" value="<?= $modalidade_id ?>">
                                            <input type="hidden" name="chave_id" value="<?= (int)$chave['id'] ?>">
                                            <label class="sr-only" for="d<?= (int)$chave['id'] ?>">Data</label>
                                            <input type="date" id="d<?= (int)$chave['id'] ?>" name="data_jogo" required>
                                            <label class="sr-only" for="h<?= (int)$chave['id'] ?>">Hora</label>
                                            <input type="time" id="h<?= (int)$chave['id'] ?>" name="hora" required>
                                            <label class="sr-only" for="l<?= (int)$chave['id'] ?>">Local</label>
                                            <input type="text" id="l<?= (int)$chave['id'] ?>" name="local"
                                                   placeholder="Local" maxlength="100">
                                            <button type="submit" class="btn-small btn-accent">Criar</button>
                                        </form>
                                    </details>
                                <?php elseif ($bye): ?>
                                    <span>Classificado sem jogar.</span>
                                <?php else: ?>
                                    <span>Aguardando a fase anterior.</span>
                                <?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="panel-sub u-margin-top-20px">
            Empate no mata-mata não promove ninguém de propósito: a regra de
            desempate (prorrogação, pênaltis, melhor campanha) é decisão da
            coordenação. Registre o resultado final editando o placar da partida
            e o vencedor sobe.
        </p>
    </div>
    <?php elseif ($modalidade_id > 0): ?>
    <div class="admin-panel u-margin-top-30px">
        <p class="panel-sub">
            Nenhuma chave montada para esta modalidade.
            <?php if ($times_com_jogo < 2): ?>
                É preciso pelo menos dois times com partidas encerradas — hoje há
                <?= $times_com_jogo ?>.
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
