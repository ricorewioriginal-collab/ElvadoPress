<?php
declare(strict_types=1);
// cms/src/Plugin/Context.php – das Objekt $np, das plugin.php eines nativen Plugins erhält: Hooks, Einstellungen, eigene API-Aktionen, eigener Datenordner.

namespace Elvado\Plugin;

final class Context
{
    /** @var array<string,array{cb:callable,access:string}> */
    private array $api = [];

    public function __construct(private readonly PluginManager $mgr, private readonly array $manifest, private readonly string $dir) {}

    public function id(): string { return $this->manifest['id']; }
    public function version(): string { return $this->manifest['version']; }
    public function dir(): string { return $this->dir; }
    public function manifest(): array { return $this->manifest; }

    /** Aktion oder Filter registrieren (siehe cms/docs/PLUGIN-ENTWICKLUNG.md für die Hook-Liste). */
    public function on(string $hook, callable $cb, int $priority = 10): void
    {
        Hooks::on($hook, $cb, $priority);
    }

    /** Einstellungen dieses Plugins (Vorgaben aus dem Manifest, überschrieben durch gespeicherte Werte). */
    public function settings(): array { return $this->mgr->settings($this->id(), true); }
    public function setting(string $key, mixed $default = null): mixed { return $this->settings()[$key] ?? $default; }

    /** Eigene API-Aktion: erreichbar über np_call (angemeldet) bzw. np_public (access = public). access: admin | editor | public */
    public function api(string $name, callable $cb, string $access = 'admin'): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,40}$/', $name) && in_array($access, ['admin', 'editor', 'public'], true)) {
            $this->api[$name] = ['cb' => $cb, 'access' => $access];
            $this->mgr->registerApi($this->id(), $name, $cb, $access);
        }
    }

    /** Eigener, per .htaccess gesperrter Datenordner (cms/data/.plugins/data/<id>/). */
    public function dataDir(string $sub = ''): string { return $this->mgr->pluginDataDir($this->id(), $sub); }

    /** Eine Zeile ins Plugin-Protokoll (keine Passwörter, Schlüssel oder personenbezogenen Daten schreiben). */
    public function log(string $line): void { $this->mgr->log($this->id(), $line); }

    public function cmsDir(): string { return $this->mgr->cmsDir(); }
    public function rootDir(): string { return dirname($this->mgr->cmsDir()); }
    public function cmsVersion(): string { return $this->mgr->cmsVersion(); }

    /** Ist ein anderes Plugin aktiv? (für optionale Zusammenarbeit) */
    public function pluginActive(string $id): bool { return $this->mgr->isActive($id); }
}
