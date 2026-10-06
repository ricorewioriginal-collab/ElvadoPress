#!/usr/bin/env bash
# Baut die App-Vorlage (app-template/) in einer temporären Kopie mit drei Beispiel-Apps (Website-, Baukasten- und Radio-App) und führt die Tests der Vorlage aus.
# Voraussetzungen: Android: JDK 17, Android-SDK (ANDROID_HOME, Plattform 36, Build-Tools 36.0.0), Gradle 9.6 oder neuer (Befehl "gradle" oder GRADLE=…);
# Windows-Teil: .NET-SDK 8 (Befehl "dotnet") – gebaut wird nur zur Kompilierprüfung (EnableWindowsTargeting), die Pakete entstehen im Build-Workflow.
# Aufruf: scripts/verify-app-template.sh [--android-only | --windows-only]
set -euo pipefail
cd "$(dirname "$0")/.."
MODE="${1:-all}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
tar -C app-template --exclude='./android/app/build' --exclude='./android/.gradle' --exclude='./windows-native/bin' --exclude='./windows-native/obj' -cf - . | tar -C "$WORK" -xf -

if [[ "$MODE" != "--windows-only" ]]; then
  : "${ANDROID_HOME:?ANDROID_HOME zeigt auf das Android-SDK (Plattform 36, Build-Tools 36.0.0)}"
  GRADLE="${GRADLE:-gradle}"
  cat > "$WORK/android/brands.json" <<'J'
[
  {"id":"demoweb","applicationId":"de.example.demoweb","appName":"Demo Web","launchUrl":"https://example.org/","site":"https://example.org","filePrefix":"Demo-Web","type":"web","themeColor":"#112233"},
  {"id":"democontent","applicationId":"de.example.democontent","appName":"Demo Inhalte","launchUrl":"https://example.org/","site":"https://example.org","filePrefix":"Demo-Inhalte","type":"content","themeColor":"#223344"},
  {"id":"demoradio","applicationId":"de.example.demoradio","appName":"Demo Radio","launchUrl":"https://example.org/","site":"https://example.org","filePrefix":"Demo-Radio",
   "radio":{"podcast":true,"communityBase":"https://example.org/community/","shops":[{"title":"Shop \"A\" – Größe","desc":"Beispiel","url":"https://shop.example.org/"}]}}
]
J
  for b in demoweb democontent demoradio; do
    mkdir -p "$WORK/android/app/src/$b/res/drawable-nodpi"
    cp "$WORK/icon-512.png" "$WORK/android/app/src/$b/res/drawable-nodpi/app_logo.png"
  done
  echo "== Android: Kompilieren, Paketieren (Entwickler-APK) und Tests =="
  "$GRADLE" -p "$WORK/android" --no-daemon -q assembleDemowebDeveloper assembleDemocontentDeveloper assembleDemoradioDeveloper testDemowebDebugUnitTest testDemocontentDebugUnitTest testDemoradioDebugUnitTest
  for b in demoweb democontent demoradio; do test -f "$WORK/android/app/build/outputs/apk/$b/developer/app-$b-developer.apk" || { echo "APK für $b fehlt"; exit 1; }; done
  echo "Android: OK (drei APKs gebaut, Tests bestanden)"
fi

if [[ "$MODE" != "--android-only" ]]; then
  if command -v dotnet >/dev/null 2>&1; then
    echo "== Windows: Kompilieren (ohne Paket) =="
    (cd "$WORK/windows-native" && dotnet build ElvadoPress.App.Windows.csproj -p:EnableWindowsTargeting=true -r win-x64 -o "$WORK/winout" --nologo -v q)
    (cd "$WORK/windows-native" && dotnet run --project tests/RuntimeCheck -v q)
    echo "Windows: OK (kompiliert, Auswertung der CMS-Antwort geprüft)"
  else
    echo "Windows: übersprungen (dotnet nicht gefunden)"
  fi
fi
