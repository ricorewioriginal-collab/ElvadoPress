; Multi-Brand: ISCC /DAppName="Meine App" /DOutBase=Meine-App-Setup-x64 /DPublishDir=publish
#ifndef AppName
#define AppName "ElvadoPress App"
#endif
#ifndef OutBase
#define OutBase "ElvadoPress-App-Setup-x64"
#endif
#ifndef AppVersion
#define AppVersion "2.0.0"
#endif
#ifndef PublishDir
#define PublishDir "publish"
#endif
[Setup]
AppName={#AppName}
AppVersion={#AppVersion}
DefaultDirName={autopf}\{#AppName}
DefaultGroupName={#AppName}
OutputBaseFilename={#OutBase}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=lowest
UninstallDisplayName={#AppName}

[Files]
Source: "{#PublishDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autoprograms}\{#AppName}"; Filename: "{app}\{#AppName}.exe"
Name: "{autodesktop}\{#AppName}"; Filename: "{app}\{#AppName}.exe"

[Run]
Filename: "{app}\{#AppName}.exe"; Description: "{#AppName} starten"; Flags: nowait postinstall
