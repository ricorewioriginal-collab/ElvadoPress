<?php
declare(strict_types=1);
// cms/src/Wp/Adapter/ExtensionAdapter.php – Schnittstelle für Plugins und Themes im echten WordPress (Zustand: installiert/aktiv). Dateien installiert der ExtensionInstaller.
// Plugin: id = Plugin-Datei (z. B. „akismet/akismet.php“), slug = Ordner. Theme: id = slug = Ordnername.

namespace Elvado\Wp\Adapter;

interface ExtensionAdapter
{
    /** @return list<array{id:string,slug:string,name:string,version:string,author:string,description:string,uri:string,active:bool,requires_php:string,requires_wp:string}> */
    public function plugins(): array;

    /** @return list<array{id:string,slug:string,name:string,version:string,author:string,description:string,active:bool,parent:string,block_theme:bool,error:string}> */
    public function themes(): array;

    /** Aktivieren (WordPress prüft den Code in einer Sandbox). Wirft bei Fehlern. */
    public function activatePlugin(string $id): void;

    public function deactivatePlugin(string $id): void;

    /** Aufräumen der Plugin-Daten (uninstall.php / Uninstall-Hook). */
    public function uninstallPlugin(string $id): void;

    /** @return array{template:string,stylesheet:string} */
    public function activeTheme(): array;

    public function switchTheme(string $slug): void;
}
