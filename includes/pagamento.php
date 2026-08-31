<?php
/**
 * includes/pagamento.php — cobrança da assinatura (SH-41)
 *
 * O cartão estava em "Bloqueado" há três sprints com o motivo certo: integrar
 * um gateway exige conta aprovada em adquirente, chave de API e webhook
 * público em HTTPS. Nada disso existe num TCC rodando em XAMPP, e inventar
 * uma "integração" que não conversa com ninguém seria pior do que não ter.
 *
 * O que ESTAVA no caminho, e agora está resolvido, é outra coisa: o fluxo de
 * contratação registrava a assinatura e não gerava cobrança nenhuma. Não havia
 * valor a receber, vencimento, nem baixa de pagamento. A escola contratava e
 * o sistema esquecia.
 *
 * Três modos, e a diferença entre eles é honesta:
 *
 *   manual    A cobrança é registrada com valor e vencimento; a escola paga
 *             por fora (boleto do financeiro, transferência) e a coordenação
 *             dá baixa à mão. Funciona hoje, sem contrato com ninguém.
 *
 *   pix       Mesma coisa, mas o sistema monta o "copia e cola" do Pix — o
 *             BR Code do padrão EMV®QRCPS do Banco Central — a partir da
 *             chave da escola. NÃO exige gateway: é o mesmo código que o app
 *             do banco gera. A confirmação continua manual, porque conferir
 *             pagamento automaticamente é justamente o que exige integração.
 *
 *   gateway   Estrutura pronta (referência externa, webhook com assinatura
 *             HMAC) e DESLIGADA até existir credencial. `sh_gateway_pronto()`
 *             diz se há o que usar; sem credencial, o sistema cai em 'manual'
 *             em vez de fingir.
 *
 * Nenhum modo coleta dado de cartão. Nem número, nem CVV, nem token — o que
 * mantém o sistema fora do escopo do PCI-DSS e a escola longe de guardar o que
 * não sabe proteger.
 */

require_once __DIR__ . '/config.php';

sh_padrao('SH_PAGAMENTO_MODO',   'manual');   // 'manual', 'pix' ou 'gateway'
sh_padrao('SH_PIX_CHAVE',        '');
sh_padrao('SH_PIX_BENEFICIARIO', '');
sh_padrao('SH_PIX_CIDADE',       '');
sh_padrao('SH_GATEWAY_NOME',     '');
sh_padrao('SH_GATEWAY_CHAVE',    '');
sh_padrao('SH_GATEWAY_WEBHOOK_SEGREDO', '');

/** Modo efetivo — cai para 'manual' quando o modo escolhido não tem o que precisa. */
function sh_pagamento_modo() {
    if (SH_PAGAMENTO_MODO === 'pix' && SH_PIX_CHAVE !== '') return 'pix';
    if (SH_PAGAMENTO_MODO === 'gateway' && sh_gateway_pronto()) return 'gateway';
    return 'manual';
}

/** Há credencial de gateway configurada? */
function sh_gateway_pronto() {
    return SH_GATEWAY_CHAVE !== '' && SH_GATEWAY_NOME !== '';
}

/** O que falta para o modo configurado funcionar (para o aviso no painel). */
function sh_pagamento_pendencias() {
    $faltas = [];
    if (SH_PAGAMENTO_MODO === 'pix' && SH_PIX_CHAVE === '') {
        $faltas[] = 'SH_PIX_CHAVE não está definida em includes/config.local.php.';
    }
    if (SH_PAGAMENTO_MODO === 'pix' && SH_PIX_BENEFICIARIO === '') {
        $faltas[] = 'SH_PIX_BENEFICIARIO não está definido (o nome que aparece no app do pagador).';
    }
    if (SH_PAGAMENTO_MODO === 'gateway' && !sh_gateway_pronto()) {
        $faltas[] = 'O modo gateway exige SH_GATEWAY_NOME e SH_GATEWAY_CHAVE; '
                  . 'enquanto não houver, a cobrança é gerada no modo manual.';
    }
    return $faltas;
}

/* ══ Cobranças ═══════════════════════════════════════════════════════════ */

/**
 * Cria a cobrança de uma assinatura.
 *
 * @return array{ok:bool, mensagem:string, cobranca_id:?int, codigo:?string}
 */
function sh_criar_cobranca(PDO $pdo, $assinatura_id, $valor, $vencimento = null, $observacao = null) {
    if (!sh_tabela_existe($pdo, 'cobrancas')) {
        return ['ok' => false, 'mensagem' => 'Rode scripts/migration_v3.sql para habilitar as cobranças.',
                'cobranca_id' => null, 'codigo' => null];
    }

    $valor = round((float)$valor, 2);
    if ($valor <= 0) {
        return ['ok' => false, 'mensagem' => 'O valor da cobrança precisa ser maior que zero.',
                'cobranca_id' => null, 'codigo' => null];
    }

    $modo       = sh_pagamento_modo();
    $meio       = ($modo === 'pix') ? 'pix' : (($modo === 'gateway') ? 'gateway' : 'manual');
    $codigo     = sh_protocolo($pdo, 'cobrancas', 'codigo', 'COB');
    $vencimento = $vencimento ?: date('Y-m-d', strtotime('+7 days'));

    $payload = null;
    if ($modo === 'pix') {
        $payload = sh_pix_payload($valor, $codigo);
    }

    try {
        $pdo->prepare(
            'INSERT INTO cobrancas (assinatura_id, codigo, valor, meio, vencimento, payload_pix, observacao)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int)$assinatura_id, $codigo, $valor, $meio, $vencimento, $payload, $observacao,
        ]);
        $id = (int)$pdo->lastInsertId();
        sh_auditar($pdo, 'cobranca_criada', 'cobrancas', $id, $codigo . ' · R$ ' . sh_money($valor));

        return ['ok' => true, 'mensagem' => 'Cobrança ' . $codigo . ' criada.',
                'cobranca_id' => $id, 'codigo' => $codigo];
    } catch (PDOException $e) {
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'criar a cobrança'),
                'cobranca_id' => null, 'codigo' => null];
    }
}

/**
 * Dá baixa numa cobrança.
 * Quem confirma fica registrado: dinheiro sem responsável é problema.
 */
function sh_baixar_cobranca(PDO $pdo, $cobranca_id, $referencia = null) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM cobrancas WHERE id = ?");
        $stmt->execute([(int)$cobranca_id]);
        $cobranca = $stmt->fetch();
        if (!$cobranca) return ['ok' => false, 'mensagem' => 'Cobrança não encontrada.'];
        if ($cobranca['status'] === 'paga') return ['ok' => false, 'mensagem' => 'Esta cobrança já estava paga.'];

        $pdo->prepare(
            "UPDATE cobrancas
                SET status = 'paga', paga_em = NOW(), baixa_por = ?, referencia_externa = ?
              WHERE id = ?"
        )->execute([
            $_SESSION['usuario_id'] ?? null,
            $referencia !== null ? mb_substr($referencia, 0, 120) : null,
            (int)$cobranca_id,
        ]);

        /* Pagamento confirmado ativa a assinatura: era o passo que faltava
           entre "a escola contratou" e "a escola é cliente". */
        if (sh_tabela_existe($pdo, 'assinaturas')) {
            $pdo->prepare(
                "UPDATE assinaturas SET status = 'ativa' WHERE id = ? AND status IN ('trial','pendente')"
            )->execute([(int)$cobranca['assinatura_id']]);
        }

        sh_auditar($pdo, 'cobranca_baixada', 'cobrancas', (int)$cobranca_id, $cobranca['codigo']);
        return ['ok' => true, 'mensagem' => 'Pagamento confirmado e assinatura ativada.'];
    } catch (PDOException $e) {
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'dar baixa na cobrança')];
    }
}

/** Cancela uma cobrança em aberto. */
function sh_cancelar_cobranca(PDO $pdo, $cobranca_id, $motivo = null) {
    try {
        $pdo->prepare("UPDATE cobrancas SET status = 'cancelada', observacao = ?
                        WHERE id = ? AND status = 'pendente'")
            ->execute([$motivo !== null ? mb_substr($motivo, 0, 255) : null, (int)$cobranca_id]);
        sh_auditar($pdo, 'cobranca_cancelada', 'cobrancas', (int)$cobranca_id, $motivo);
        return ['ok' => true, 'mensagem' => 'Cobrança cancelada.'];
    } catch (PDOException $e) {
        return ['ok' => false, 'mensagem' => sh_erro_usuario($e, 'cancelar a cobrança')];
    }
}

/* ══ Pix copia e cola (BR Code) ══════════════════════════════════════════
   Formato EMV®QRCPS, o mesmo que o aplicativo do banco gera: campos
   "ID + tamanho + valor" concatenados, com um CRC de 16 bits no fim.

   O código estático abaixo NÃO passa por gateway nenhum — é a chave Pix da
   escola escrita no padrão que qualquer banco brasileiro lê. Por isso serve
   sem nenhuma contratação; e por isso também não avisa ninguém quando é pago,
   o que mantém a baixa manual.                                             */

/** Um campo do BR Code: identificador, tamanho em dois dígitos, conteúdo. */
function sh_pix_campo($id, $valor) {
    return $id . str_pad((string)strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
}

/** CRC-16/CCITT-FALSE, polinômio 0x1021, valor inicial 0xFFFF. */
function sh_pix_crc($payload) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($payload); $i++) {
        $crc ^= (ord($payload[$i]) << 8);
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/**
 * Monta o "copia e cola" do Pix.
 *
 * @param float  $valor       em reais
 * @param string $referencia  identificador que volta no extrato (o código da cobrança)
 * @return string|null        null quando não há chave configurada
 */
function sh_pix_payload($valor, $referencia = '***') {
    if (SH_PIX_CHAVE === '') return null;

    /* O padrão aceita só ASCII maiúsculo nesses campos, e limita o tamanho.
       Nome com acento faz o app do banco recusar o código. */
    $normaliza = function ($texto, $limite) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', (string)$texto);
        if ($t === false) $t = (string)$texto;
        $t = preg_replace('/[^A-Za-z0-9 ]/', '', $t);
        return mb_strtoupper(trim(mb_substr($t, 0, $limite)));
    };

    $beneficiario = $normaliza(SH_PIX_BENEFICIARIO ?: SH_NOME, 25);
    $cidade       = $normaliza(SH_PIX_CIDADE ?: 'BRASIL', 15);
    $referencia   = $normaliza($referencia, 25) ?: '***';

    $conta = sh_pix_campo('00', 'br.gov.bcb.pix') . sh_pix_campo('01', SH_PIX_CHAVE);

    $payload = sh_pix_campo('00', '01')                       // versão do formato
             . sh_pix_campo('26', $conta)                     // conta do recebedor
             . sh_pix_campo('52', '0000')                     // categoria do comerciante
             . sh_pix_campo('53', '986')                      // moeda: BRL
             . sh_pix_campo('54', number_format((float)$valor, 2, '.', ''))
             . sh_pix_campo('58', 'BR')                       // país
             . sh_pix_campo('59', $beneficiario)
             . sh_pix_campo('60', $cidade)
             . sh_pix_campo('62', sh_pix_campo('05', $referencia));

    $payload .= '6304';                                       // cabeçalho do CRC
    return $payload . sh_pix_crc($payload);
}

/* ══ Webhook do gateway (estrutura pronta, desligada) ════════════════════
   Deixado escrito porque a integração inteira depende só de credencial: o
   dia em que a escola fechar contrato com uma adquirente, o que falta é
   preencher config.local.php e apontar o endpoint. A verificação de
   assinatura HMAC já está aqui — é o que impede alguém de mandar um "pago"
   forjado para o endereço público do webhook.                              */

/** A notificação recebida foi mesmo assinada pelo gateway? */
function sh_gateway_assinatura_valida($corpo_bruto, $assinatura_recebida) {
    if (SH_GATEWAY_WEBHOOK_SEGREDO === '') return false;
    $esperada = hash_hmac('sha256', (string)$corpo_bruto, SH_GATEWAY_WEBHOOK_SEGREDO);
    return hash_equals($esperada, (string)$assinatura_recebida);
}

/** Registra a referência externa devolvida pelo gateway. */
function sh_gateway_vincular(PDO $pdo, $cobranca_id, $referencia_externa) {
    try {
        $pdo->prepare('UPDATE cobrancas SET referencia_externa = ? WHERE id = ?')
            ->execute([mb_substr((string)$referencia_externa, 0, 120), (int)$cobranca_id]);
        return true;
    } catch (PDOException $e) {
        sh_log_excecao($e, 'vincular referência do gateway');
        return false;
    }
}
