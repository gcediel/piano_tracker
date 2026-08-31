#!/bin/bash
# Instala un acceso directo de Piano Tracker en el menú de aplicaciones (Linux).
set -e

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DESKTOP_DIR="$HOME/.local/share/applications"
ELECTRON_BIN="$APP_DIR/node_modules/.bin/electron"

if [ ! -x "$ELECTRON_BIN" ]; then
  echo "No se encuentra $ELECTRON_BIN — ejecuta 'npm install' dentro de $APP_DIR primero."
  exit 1
fi

mkdir -p "$DESKTOP_DIR"

cat > "$DESKTOP_DIR/piano-tracker.desktop" <<EOF
[Desktop Entry]
Type=Application
Name=Piano Tracker
Comment=Seguimiento de práctica de piano con control MIDI del Roland GO:KEYS
Exec=$ELECTRON_BIN $APP_DIR
Icon=$APP_DIR/assets/img/icon.png
Terminal=false
Categories=AudioVideo;Audio;Music;
StartupWMClass=Piano Tracker
EOF

chmod +x "$DESKTOP_DIR/piano-tracker.desktop"
update-desktop-database "$DESKTOP_DIR" 2>/dev/null || true

echo "✓ Acceso directo instalado. Búscalo como 'Piano Tracker' en tu menú de aplicaciones."
