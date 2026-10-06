#!/usr/bin/env bash
# Erzeugt den stabilen Signaturschlüssel für die Android-APKs deiner Apps (einmalig).
#
# Aufruf:   bash scripts/create-developer-keystore.sh [besitzer/repository] [--set-secrets]
#
# - Der Schlüssel und zufällige Passwörter entstehen NUR lokal in  ~/app-signing/
# - Mit --set-secrets (und installiertem, angemeldetem GitHub-CLI "gh") werden die 4 Secrets direkt im Repository gesetzt;
#   die Werte erscheinen dabei nicht auf dem Bildschirm. Ohne --set-secrets stehen sie in vier Textdateien zum Einfügen.
#
# Den Ordner ~/app-signing/ SICHER sichern (Passwortmanager + Offline-Kopie) und niemals ins Git legen.
# Geht der Schlüssel verloren, können bereits installierte Apps nicht mehr aktualisiert werden.
set -euo pipefail

REPO=""; SET_SECRETS=0
for a in "$@"; do
  case "$a" in
    --set-secrets) SET_SECRETS=1 ;;
    */*) REPO="$a" ;;
    *) echo "Unbekannte Angabe: $a" >&2; exit 1 ;;
  esac
done
if [[ $SET_SECRETS -eq 1 && -z "$REPO" ]]; then echo "Mit --set-secrets bitte das Repository angeben, z. B.: bash scripts/create-developer-keystore.sh besitzer/meine-apps --set-secrets" >&2; exit 1; fi

OUT_DIR="${SIGNING_DIR:-$HOME/app-signing}"
KEYSTORE="$OUT_DIR/app-developer.jks"
ALIAS="app-developer"

command -v keytool >/dev/null 2>&1 || { echo "keytool fehlt. Installiere ein JDK 17 oder neuer (z. B. 'sudo apt install default-jdk-headless')." >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "openssl fehlt." >&2; exit 1; }
command -v base64  >/dev/null 2>&1 || { echo "base64 fehlt." >&2; exit 1; }

if [[ -e "$KEYSTORE" ]]; then
  echo "Es gibt schon einen Schlüssel: $KEYSTORE" >&2
  echo "Er wird NICHT überschrieben. Willst du wirklich einen neuen, verschiebe/lösche den alten bewusst selbst." >&2
  exit 1
fi

umask 077
mkdir -p "$OUT_DIR"

STORE_PASS="$(openssl rand -hex 16)"
KEY_PASS="$STORE_PASS"   # PKCS12 verwendet für Keystore und Schlüssel dasselbe Passwort

keytool -genkeypair -keystore "$KEYSTORE" -storetype PKCS12 -alias "$ALIAS" \
  -keyalg RSA -keysize 4096 -validity 10000 \
  -storepass "$STORE_PASS" -keypass "$KEY_PASS" \
  -dname "CN=App Developer, OU=Apps" >/dev/null 2>&1

B64="$(base64 < "$KEYSTORE" | tr -d '\n')"   # einzeilig, wie der Build es erwartet
printf '%s' "$B64"        > "$OUT_DIR/ANDROID_DEVELOPER_KEYSTORE_BASE64.txt"
printf '%s' "$STORE_PASS" > "$OUT_DIR/ANDROID_DEVELOPER_KEYSTORE_PASSWORD.txt"
printf '%s' "$ALIAS"      > "$OUT_DIR/ANDROID_DEVELOPER_KEY_ALIAS.txt"
printf '%s' "$KEY_PASS"   > "$OUT_DIR/ANDROID_DEVELOPER_KEY_PASSWORD.txt"

FPR="$(keytool -list -v -keystore "$KEYSTORE" -alias "$ALIAS" -storepass "$STORE_PASS" 2>/dev/null | awk -F': ' '/SHA256:/{print $2; exit}')"
echo
echo "Schlüssel erzeugt:  $KEYSTORE"
echo "Zertifikat SHA-256: $FPR"
echo

if [[ $SET_SECRETS -eq 1 ]]; then
  command -v gh >/dev/null 2>&1 || { echo "--set-secrets braucht das GitHub-CLI 'gh' (https://cli.github.com), angemeldet mit 'gh auth login'." >&2; exit 1; }
  for name in ANDROID_DEVELOPER_KEYSTORE_BASE64 ANDROID_DEVELOPER_KEYSTORE_PASSWORD ANDROID_DEVELOPER_KEY_ALIAS ANDROID_DEVELOPER_KEY_PASSWORD; do
    gh secret set "$name" --repo "$REPO" < "$OUT_DIR/$name.txt"
    echo "Secret gesetzt: $name"
  done
  echo
  echo "Fertig. Der nächste Android-Build signiert mit diesem Schlüssel."
else
  echo "Lege jetzt in GitHub unter  ${REPO:-<dein Repository>} -> Settings -> Secrets and variables -> Actions -> New repository secret"
  echo "diese vier Secrets an. Jeder Wert steht in einer eigenen Datei in $OUT_DIR :"
  echo
  echo "  ANDROID_DEVELOPER_KEYSTORE_BASE64     <- ANDROID_DEVELOPER_KEYSTORE_BASE64.txt   (langer Text, komplett kopieren)"
  echo "  ANDROID_DEVELOPER_KEYSTORE_PASSWORD   <- ANDROID_DEVELOPER_KEYSTORE_PASSWORD.txt"
  echo "  ANDROID_DEVELOPER_KEY_ALIAS           <- ANDROID_DEVELOPER_KEY_ALIAS.txt"
  echo "  ANDROID_DEVELOPER_KEY_PASSWORD        <- ANDROID_DEVELOPER_KEY_PASSWORD.txt"
fi

echo
echo "WICHTIG: Sichere den Ordner $OUT_DIR (Passwortmanager/Offline-Kopie). Lösche die vier .txt-Dateien erst,"
echo "wenn die Secrets gesetzt sind und du den Ordner gesichert hast. Gib Schlüssel und Passwörter niemals weiter."
