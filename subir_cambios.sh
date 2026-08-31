#!/usr/bin/env bash
# Vigila el proyecto y sube por rsync cualquier cambio al servidor.
# Pensado para dejarlo corriendo en una terminal aparte:
#   ./subir_cambios.sh
# Se detiene con Ctrl+C.

set -uo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST="192.168.100.11::piano_tracker"
POLL_INTERVAL=2   # segundos entre comprobaciones (solo modo sondeo)
QUIET_SECONDS=2   # espera tras el ultimo cambio antes de sincronizar (agrupa varios guardados seguidos)

EXCLUDES=(
  --exclude=".git/"
  --exclude=".venv/"
  --exclude="__pycache__/"
  --exclude="*.pyc"
  --exclude=".pytest_cache/"
  --exclude="*.db"
  --exclude=".env"
  --exclude="*.log"
)

INOTIFY_EXCLUDE='(^|/)(\.git|\.venv|__pycache__|\.pytest_cache)(/|$)|\.db$|\.log$|(^|/)\.env$'

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

do_sync() {
  log "Cambios detectados, sincronizando con $DEST ..."
  if rsync -avz --delete "${EXCLUDES[@]}" "$PROJECT_DIR"/ "$DEST"/; then
    log "Sincronización completada."
  else
    log "ERROR al sincronizar (¿está arrancado el rsync daemon en el servidor?)"
  fi
}

trap 'log "Detenido."; exit 0' INT TERM

log "Vigilando cambios en: $PROJECT_DIR"
log "Destino: $DEST"
log "(se ignoran .git/ .venv/ __pycache__/ .pytest_cache/ *.db .env *.log)"

# Sincronizacion inicial, para subir cambios hechos antes de arrancar el script.
do_sync

if command -v inotifywait >/dev/null 2>&1; then
  log "Modo: eventos (inotifywait)"
  while true; do
    inotifywait -r -e modify,create,delete,move,attrib \
      --exclude "$INOTIFY_EXCLUDE" -qq "$PROJECT_DIR"

    # Si llegan mas cambios seguidos (p.ej. guardar varios ficheros a la vez),
    # espera a que se calmen antes de sincronizar una sola vez.
    while inotifywait -r -e modify,create,delete,move,attrib \
      --exclude "$INOTIFY_EXCLUDE" -qq -t "$QUIET_SECONDS" "$PROJECT_DIR"; do
      :
    done

    do_sync
  done
else
  log "Aviso: 'inotifywait' no esta instalado, se usara sondeo cada ${POLL_INTERVAL}s."
  log "Para un modo mas eficiente: sudo apt install inotify-tools"

  last_snapshot=""
  first_run=true
  while true; do
    snapshot=$(find "$PROJECT_DIR" -type f \
      ! -path "*/.git/*" ! -path "*/.venv/*" ! -path "*/__pycache__/*" \
      ! -path "*/.pytest_cache/*" ! -name "*.db" ! -name ".env" ! -name "*.log" \
      -printf '%T@ %p\n' 2>/dev/null | sort | sha256sum)

    if [[ "$snapshot" != "$last_snapshot" ]]; then
      if [[ "$first_run" == false ]]; then
        do_sync
      fi
      last_snapshot="$snapshot"
      first_run=false
    fi
    sleep "$POLL_INTERVAL"
  done
fi
