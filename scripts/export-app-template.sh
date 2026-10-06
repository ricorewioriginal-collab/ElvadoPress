#!/usr/bin/env bash
# Exportiert die App-Vorlage (Ordner app-template/) für ein eigenes GitHub-Repository: als Ordner oder als ZIP (z. B. für die Release-Datei app-template.zip).
# Aufruf: scripts/export-app-template.sh [Zielordner] [--zip=<Datei.zip>]     (Standard-Zielordner: app-template-export)
set -euo pipefail
cd "$(dirname "$0")/.."
OUT=""; ZIP=""
for a in "$@"; do
  case "$a" in
    --zip=*) ZIP="${a#--zip=}" ;;
    -*) echo "Unbekannte Option: $a" >&2; exit 1 ;;
    *) OUT="$a" ;;
  esac
done
[[ -n "$OUT" || -n "$ZIP" ]] || OUT="app-template-export"
TMP=""; trap '[[ -n "$TMP" ]] && rm -rf "$TMP"' EXIT
if [[ -z "$OUT" ]]; then TMP="$(mktemp -d)"; OUT="$TMP/app-template"; fi
rm -rf "$OUT"; mkdir -p "$OUT"
# ohne Build-Ausgaben, Schlüssel und lokale Dateien
tar -C app-template --exclude='./android/app/build' --exclude='./android/.gradle' --exclude='./android/build' --exclude='./windows-native/bin' --exclude='./windows-native/obj' \
    --exclude='./windows-native/tests/RuntimeCheck/bin' --exclude='./windows-native/tests/RuntimeCheck/obj' --exclude='local.properties' --exclude='*.jks' --exclude='*.keystore' -cf - . | tar -C "$OUT" -xf -
echo "App-Vorlage exportiert: $OUT ($(find "$OUT" -type f | wc -l) Dateien)"
if [[ -n "$ZIP" ]]; then
  command -v zip >/dev/null 2>&1 || { echo "zip fehlt (z. B. 'sudo apt install zip')." >&2; exit 1; }
  rm -f "$ZIP"; ZIPABS="$(cd "$(dirname "$ZIP")" && pwd)/$(basename "$ZIP")"
  (cd "$OUT" && zip -qr "$ZIPABS" . -x '.git/*')
  echo "ZIP: $ZIP ($(du -k "$ZIP" | cut -f1) KB)"
fi
