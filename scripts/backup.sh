#!/usr/bin/env bash
# ===========================================================================
#  SPORTHUB — rotina de backup (SH-51) — Linux / hospedagem
#
#  Mesma rotina do backup.bat, para quando o sistema sair do XAMPP e for para
#  um servidor de verdade. Copia o banco e a pasta uploads/, aplica retenção
#  e escreve no histórico.
#
#  Uso manual:
#      bash scripts/backup.sh
#
#  Agendamento diário às 02h00 (crontab -e):
#      0 2 * * * /var/www/sporthub/scripts/backup.sh >> /var/log/sporthub-backup.log 2>&1
# ===========================================================================
set -euo pipefail

PROJETO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTINO="${SPORTHUB_BACKUP_DIR:-/var/backups/sporthub}"
DB_NOME="${SPORTHUB_DB_NOME:-olimpiasp}"
DB_USUARIO="${SPORTHUB_DB_USUARIO:-root}"
DB_SENHA="${SPORTHUB_DB_SENHA:-}"
RETENCAO_DIAS="${SPORTHUB_RETENCAO:-30}"

CARIMBO="$(date +%Y-%m-%d_%H%M)"
PASTA="$DESTINO/$CARIMBO"

echo "=== SportHub — backup $CARIMBO ==="
mkdir -p "$PASTA"

# ── 1. Banco de dados ─────────────────────────────────────────────────────
# --single-transaction tira o retrato sem travar a escrita das tabelas InnoDB:
# o backup pode rodar com o site no ar.
echo "[1/4] Exportando o banco $DB_NOME..."
if [ -n "$DB_SENHA" ]; then
    MYSQL_PWD="$DB_SENHA" mysqldump -u "$DB_USUARIO" --single-transaction --routines --events \
        --default-character-set=utf8mb4 "$DB_NOME" > "$PASTA/$DB_NOME.sql"
else
    mysqldump -u "$DB_USUARIO" --single-transaction --routines --events \
        --default-character-set=utf8mb4 "$DB_NOME" > "$PASTA/$DB_NOME.sql"
fi

# ── 2. Arquivos enviados ──────────────────────────────────────────────────
echo "[2/4] Copiando uploads/ e img/times/..."
[ -d "$PROJETO/uploads" ]   && cp -a "$PROJETO/uploads"   "$PASTA/uploads"
[ -d "$PROJETO/img/times" ] && cp -a "$PROJETO/img/times" "$PASTA/img_times"

# ── 3. Configuração local ─────────────────────────────────────────────────
[ -f "$PROJETO/includes/config.local.php" ] && cp -a "$PROJETO/includes/config.local.php" "$PASTA/"
[ -f "$PROJETO/logs/segredo_feed.txt" ]     && cp -a "$PROJETO/logs/segredo_feed.txt"     "$PASTA/"

# ── 4. Compactação, permissão e retenção ──────────────────────────────────
echo "[3/4] Compactando e aplicando retenção de $RETENCAO_DIAS dias..."
tar -czf "$PASTA.tar.gz" -C "$DESTINO" "$CARIMBO"
rm -rf "$PASTA"
chmod 600 "$PASTA.tar.gz"          # contém senha de banco e de SMTP
find "$DESTINO" -maxdepth 1 -name '*.tar.gz' -mtime "+$RETENCAO_DIAS" -delete

TAMANHO="$(stat -c%s "$PASTA.tar.gz" 2>/dev/null || stat -f%z "$PASTA.tar.gz")"
if [ "$TAMANHO" -lt 1024 ]; then
    echo "$CARIMBO ERRO arquivo suspeito de vazio ($TAMANHO bytes)" >> "$DESTINO/backup.log"
    echo "[ERRO] O arquivo saiu com $TAMANHO bytes. Confira o mysqldump."
    exit 1
fi

echo "[4/4] Concluído."
echo "$CARIMBO OK  $TAMANHO bytes  $PASTA.tar.gz" >> "$DESTINO/backup.log"
echo "Arquivo: $PASTA.tar.gz ($TAMANHO bytes)"
echo
echo "LEMBRETE: backup que nunca foi restaurado não é backup."
echo "Teste a restauração uma vez por bimestre — o passo a passo está em"
echo "docs/manual-do-usuario.md, seção 'Backup e restauração'."
