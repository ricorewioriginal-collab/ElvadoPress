# Erzeugt den stabilen Signaturschlüssel für die Android-APKs deiner Apps (einmalig, Windows/PowerShell).
# Aufruf:  powershell -ExecutionPolicy Bypass -File scripts\create-developer-keystore.ps1
# Der Schlüssel entsteht nur lokal auf diesem PC. Die vier ausgegebenen Werte legst du in GitHub als Repository-Secrets an
# (Settings -> Secrets and variables -> Actions). Den Schlüssel sicher sichern und niemals ins Git legen.
param(
    [string]$Alias = "app-developer",
    [string]$Keystore = "$env:USERPROFILE\app-signing\app-developer.jks"
)
$ErrorActionPreference = "Stop"

if (-not (Get-Command keytool -ErrorAction SilentlyContinue)) {
    throw "keytool wurde nicht gefunden. Installiere ein JDK 17 oder neuer und starte PowerShell danach neu."
}
$dir = Split-Path -Parent $Keystore
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
if (Test-Path $Keystore) { throw "Keystore existiert bereits: $Keystore" }

$secure = Read-Host "Passwort für Keystore und Schlüssel eingeben" -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $pass = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    & keytool -genkeypair -keystore $Keystore -storetype PKCS12 -alias $Alias -keyalg RSA -keysize 4096 -validity 10000 `
        -storepass $pass -keypass $pass -dname "CN=App Developer, OU=Apps"
    if ($LASTEXITCODE -ne 0) { throw "keytool ist mit Exit-Code $LASTEXITCODE fehlgeschlagen." }

    $base64 = [Convert]::ToBase64String([System.IO.File]::ReadAllBytes($Keystore))
    Write-Host ""
    Write-Host "FERTIG. Keystore: $Keystore" -ForegroundColor Green
    Write-Host "Lege in GitHub diese vier Repository-Secrets an:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "ANDROID_DEVELOPER_KEYSTORE_BASE64";   Write-Host $base64; Write-Host ""
    Write-Host "ANDROID_DEVELOPER_KEYSTORE_PASSWORD"; Write-Host $pass;   Write-Host ""
    Write-Host "ANDROID_DEVELOPER_KEY_ALIAS";         Write-Host $Alias;  Write-Host ""
    Write-Host "ANDROID_DEVELOPER_KEY_PASSWORD";      Write-Host $pass;   Write-Host ""
    Write-Host "WICHTIG: Die Keystore-Datei sicher sichern (Passwortmanager/Offline-Kopie) und niemals ins Git committen." -ForegroundColor Red
}
finally {
    if ($ptr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
}
