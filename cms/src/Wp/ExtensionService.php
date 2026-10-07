<?php
declare(strict_types=1);
// cms/src/Wp/ExtensionService.php – Dienstschicht für Plugins und Themes: Rechte (nur Administratoren), Eingabeprüfung, Installation (wordpress.org oder ZIP), Aktivieren mit Absturzschutz, Entfernen.
// Aktivieren/Wechseln setzen vorher einen Wächter (Engine::guardSet): stürzt WordPress danach ab, startet der nächste Lauf abgesichert und macht die Änderung rückgängig (Bridge::recover).

namespace Elvado\Wp;

use Elvado\Wp\Adapter\ExtensionAdapter;

final class ExtensionService
{
    public function __construct(
        private readonly ExtensionAdapter $adapter,
        private readonly ExtensionInstaller $installer,
        private readonly Engine $engine,
        private readonly ?Actor $actor = null
    ) {
    }

    private function guard(): void
    {
        if (!($this->actor ?? new Actor('', 'admin'))->can('engine')) {
            throw new PermissionException('Plugins und Themes verwalten nur Administratoren.');
        }
    }

    private function kind(string $k): string
    {
        if (!ExtensionSource::validKind($k)) {
            throw new \InvalidArgumentException('Unbekannte Art (plugin oder theme).');
        }
        return $k;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $kind): array
    {
        $this->guard();
        return $this->kind($kind) === 'plugin' ? $this->adapter->plugins() : $this->adapter->themes();
    }

    /** Verzeichnissuche (wordpress.org); bereits installierte sind markiert. */
    public function search(string $kind, string $query, int $page): array
    {
        $this->guard();
        $r = ExtensionSource::search($this->kind($kind), $query, $page);
        if ($r['ok']) {
            $have = array_column($this->list($kind), 'slug');
            foreach ($r['items'] as &$i) {
                $i['installed'] = in_array($i['slug'], $have, true);
            }
            unset($i);
        }
        return $r;
    }

    /** Aus dem offiziellen Verzeichnis installieren oder (mit $update) auf die aktuelle Version bringen. @return array<string,mixed> */
    public function installFromDirectory(string $kind, string $slug, bool $update = false): array
    {
        $this->guard();
        $this->kind($kind);
        if (!ExtensionSource::validSlug($slug)) {
            throw new \InvalidArgumentException('Ungültige Kennung.');
        }
        $info = ExtensionSource::info($kind, $slug);
        if (!$info['ok']) {
            throw new \RuntimeException($info['message']);
        }
        $this->engine->protect();
        $tmp = $this->engine->stateDir() . '/dl-' . bin2hex(random_bytes(6)) . '.zip';
        try {
            $d = ExtensionSource::download($kind, $slug, $info['version'], $tmp);
            if (!$d['ok']) {
                throw new \RuntimeException($d['message']);
            }
            $r = $this->installer->install($kind, $tmp, $update);
        } finally {
            @unlink($tmp);
        }
        if (!$r['ok']) {
            throw new \RuntimeException($r['message']);
        }
        if ($r['slug'] !== $slug) {
            $this->installer->remove($kind, $r['slug']);
            throw new \RuntimeException('Das Paket enthält einen anderen Ordner als angefragt – es wurde verworfen.');
        }
        return $r;
    }

    /** Hochgeladenes ZIP installieren. @return array<string,mixed> */
    public function installUpload(string $kind, string $tmpZip, bool $update = false): array
    {
        $this->guard();
        $this->kind($kind);
        $r = $this->installer->install($kind, $tmpZip, $update);
        if (!$r['ok']) {
            throw new \RuntimeException($r['message']);
        }
        return $r;
    }

    public function activate(string $kind, string $id): void
    {
        $this->guard();
        $id = $this->known($kind, $id);
        if ($kind === 'plugin') {
            $this->engine->guardSet('plugin', $id);
            $this->adapter->activatePlugin($id);
        } else {
            $prev = $this->adapter->activeTheme();
            if ($prev['stylesheet'] === $id) {
                return;
            }
            $this->engine->guardSet('theme', $id, $prev);
            $this->adapter->switchTheme($id);
        }
    }

    public function deactivate(string $id): void
    {
        $this->guard();
        $this->adapter->deactivatePlugin($this->known('plugin', $id));
    }

    /** Entfernen (Dateien werden vorher gesichert). $purge löscht zusätzlich die Daten des Plugins (Uninstall-Hook). */
    public function delete(string $kind, string $id, bool $purge = false): void
    {
        $this->guard();
        $id = $this->known($kind, $id);
        if ($kind === 'plugin') {
            if (!str_contains($id, '/')) {
                throw new \RuntimeException('Einzeldatei-Plugins löscht die Verwaltung nicht automatisch – bitte die Datei auf dem Server entfernen.');
            }
            $slug = (string)strstr($id, '/', true);
            $this->adapter->deactivatePlugin($id);
            if ($purge) {
                $this->adapter->uninstallPlugin($id);
            }
        } else {
            $themes = $this->adapter->themes();
            foreach ($themes as $t) {
                if ($t['id'] === $id && $t['active']) {
                    throw new \RuntimeException('Das aktive Theme lässt sich nicht löschen.');
                }
                if ($t['parent'] === $id && $t['active']) {
                    throw new \RuntimeException('Dieses Theme ist das Eltern-Theme des aktiven Themes.');
                }
            }
            $slug = $id;
        }
        if (!ExtensionSource::validSlug($slug) || !$this->installer->remove($kind, $slug)) {
            throw new \RuntimeException('Der Ordner konnte nicht entfernt werden.');
        }
    }

    public function setSafe(bool $on): void
    {
        $this->guard();
        $this->engine->setSafe($on);
    }

    private function known(string $kind, string $id): string
    {
        $this->kind($kind);
        if (preg_match('~^[A-Za-z0-9._-]{1,100}(/[A-Za-z0-9._-]{1,100})?$~', $id) !== 1 || str_contains($id, '..')) {
            throw new \InvalidArgumentException('Ungültige Kennung.');
        }
        foreach ($kind === 'plugin' ? $this->adapter->plugins() : $this->adapter->themes() as $x) {
            if ($x['id'] === $id) {
                return $id;
            }
        }
        throw new \RuntimeException('Nicht gefunden (nicht installiert).');
    }
}
