#!/usr/bin/env bash
# =====================================================================
#  Respaldo del portal Cargas REM (T-F6-10).
#
#  Para el servidor Linux. Copia la base y los archivos subidos en una
#  carpeta con la fecha y borra los respaldos viejos.
#
#  Uso:  ./backup.sh
#        DB_USER=portal ./backup.sh
#
#  Cron sugerido (diario a las 2:15):
#    15 2 * * * /var/www/cargas/01-TOOLS/backup/backup.sh >> /var/log/cargas-backup.log 2>&1
# =====================================================================

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PORTAL="$RAIZ/portal"
DESTINO="${DESTINO:-$RAIZ/backup}"
DB="${DB_NAME:-cargas_rem}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DIAS="${DIAS_CONSERVAR:-30}"

FECHA="$(date +%F_%H%M)"

mkdir -p "$DESTINO"

echo
echo "=== Respaldo de Cargas REM ==="
echo "Destino: $DESTINO"
echo

# --- Base de datos ---------------------------------------------------
ARCHIVO_DB="$DESTINO/db_$FECHA.sql"

echo "[1/2] Base $DB..."

if [ -n "$DB_PASS" ]; then
    MYSQL_PWD="$DB_PASS" mysqldump -u "$DB_USER" --single-transaction --routines --triggers \
        --default-character-set=utf8mb4 "$DB" > "$ARCHIVO_DB"
else
    mysqldump -u "$DB_USER" --single-transaction --routines --triggers \
        --default-character-set=utf8mb4 "$DB" > "$ARCHIVO_DB"
fi

if [ ! -s "$ARCHIVO_DB" ]; then
    echo "ERROR: el respaldo de la base quedó vacío." >&2
    exit 1
fi

echo "      $ARCHIVO_DB ($(du -h "$ARCHIVO_DB" | cut -f1))"

# --- Archivos subidos ------------------------------------------------
ARCHIVO_STORAGE="$DESTINO/storage_$FECHA.tar.gz"
PRIVADO="$PORTAL/storage/app/private"

echo "[2/2] Archivos subidos..."

if [ -d "$PRIVADO" ]; then
    tar -czf "$ARCHIVO_STORAGE" -C "$PRIVADO" .
    echo "      $ARCHIVO_STORAGE ($(du -h "$ARCHIVO_STORAGE" | cut -f1))"
else
    echo "      No hay archivos privados todavía."
fi

# --- Limpieza --------------------------------------------------------
VIEJOS="$(find "$DESTINO" -maxdepth 1 -type f -mtime "+$DIAS" | wc -l)"

if [ "$VIEJOS" -gt 0 ]; then
    echo
    echo "Borrando $VIEJOS respaldos de más de $DIAS días..."
    find "$DESTINO" -maxdepth 1 -type f -mtime "+$DIAS" -delete
fi

echo
echo "Respaldo terminado."
echo
echo "Para restaurar:"
echo "  mysql -u $DB_USER -e \"CREATE DATABASE $DB\""
echo "  mysql -u $DB_USER $DB < $ARCHIVO_DB"
echo "  tar -xzf $ARCHIVO_STORAGE -C portal/storage/app/private"
echo
