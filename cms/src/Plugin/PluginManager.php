<?php
declare(strict_types=1);
// cms/src/Plugin/PluginManager.php – Verwaltung der nativen (offiziellen) ElvadoPress-Plugins.
//
// Offizielle Plugins liegen als Paketbibliothek im Release (cms/official-plugins/<id>/ + catalog.json mit Prüfsummen). "Installieren" kopiert ein geprüftes Plugin
// nach cms/plugins/<id>/, "Aktivieren" lädt es (plugin.php). Server-PHP führt nur ein Plugin aus, das offiziell UND unverändert ist: Seine Prüfsumme muss zum Katalog des Releases
// passen und seit der Installation gleich geblieben sein. Beliebige hochgeladene Plugins bleiben reine Frontend-/Admin-Plugins (JavaScript) ohne Server-PHP.
// Zustand: cms/data/.plugins/state.json (installiert/aktiv), settings/<id>.json (Einstellungen, auch Geheimnisse), data/<id>/ (Plugin-Daten), backup/ (vorherige Fassungen).

namespace Elvado\Plugin;

final class PluginManager
{
    private const ROLE = ['public' => 0, 'editor' => 1, 'admin' => 2];

    private string $libDir;
    private string $pluginsDir;
    private string $stateDir;
    /** @var array<string,bool> */
    private array $loaded = [];
    /** @var array<string,array<string,array{cb:callable,access:string}>> */
    private array $api = [];
    private bool $booted = false;

    public function __construct(private readonly string $cmsDir, string $dataDir, private readonly string $cmsVersion, ?string $libDir = null, ?string $pluginsDir = null)
    {
        $this->libDir = rtrim($libDir ?? $cmsDir . '/official-plugins', '/');
        $this->pluginsDir = rtrim($pluginsDir ?? $cmsDir . '/plugins', '/');
        $this->stateDir = rtrim($dataDir, '/') . '/.plugins';
    }

    private static function gt(string $a, string $b): bool { return \Elvado\Update\Semver::compare($a, $b) > 0; }

    public function cmsDir(): string { return $this->cmsDir; }
    public function cmsVersion(): string { return $this->cmsVersion; }
    public function stateDir(): string { return $this->stateDir; }

    // ------------------------------------------------------------ Katalog und Vertrauen

    /** @return array<string,array> Katalogeinträge des Releases (id => Eintrag) */
    public function catalog(): array
    {
        $d = Fs::readJson($this->libDir . '/catalog.json');
        $out = [];
        foreach ((array)($d['plugins'] ?? []) as $p) {
            if (is_array($p) && preg_match(Manifest::ID_RE, (string)($p['id'] ?? ''))) {
                $out[(string)$p['id']] = $p + ['status' => 'available', 'hash' => '', 'recommended' => false, 'category' => '', 'name' => $p['id'], 'description' => '', 'icon' => 'fa-plug'];
            }
        }
        return $out;
    }

    /** Ist die ID für offizielle Plugins reserviert? (Dritt-Pakete dürfen sie nicht verwenden.) */
    public function reservedId(string $id): bool { return isset($this->catalog()[$id]); }

    /** @return array{0:?array,1:list<string>} Manifest aus der Paketbibliothek */
    public function libManifest(string $id): array
    {
        $f = $this->libDir . '/' . $id . '/plugin.json';
        if (!preg_match(Manifest::ID_RE, $id) || !is_file($f)) {
            return [null, ['Das Paket ist nicht vorhanden.']];
        }
        return Manifest::normalize(json_decode((string)file_get_contents($f), true), $id);
    }

    /** @return array{0:?array,1:list<string>} Manifest der installierten Fassung */
    public function installedManifest(string $id): array
    {
        $f = $this->pluginsDir . '/' . $id . '/plugin.json';
        if (!preg_match(Manifest::ID_RE, $id) || !is_file($f)) {
            return [null, ['Nicht installiert.']];
        }
        return Manifest::normalize(json_decode((string)file_get_contents($f), true), $id);
    }

    /** Paket der Bibliothek stimmt mit dem Katalog des Releases überein? */
    public function libraryVerified(string $id): bool
    {
        $c = $this->catalog()[$id] ?? null;
        return $c !== null && $c['status'] === 'available' && $c['hash'] !== '' && is_dir($this->libDir . '/' . $id) && hash_equals((string)$c['hash'], Fs::treeHash($this->libDir . '/' . $id));
    }

    /** Installierte Fassung ist offiziell: im Katalog, bei der Installation geprüft und seither unverändert. */
    public function installedOfficial(string $id): bool
    {
        $st = $this->state()['installed'][$id] ?? null;
        $dir = $this->pluginsDir . '/' . $id;
        return $st !== null && isset($this->catalog()[$id]) && !empty($st['hash']) && !empty($st['official']) && is_dir($dir) && hash_equals((string)$st['hash'], Fs::treeHash($dir));
    }

    // ------------------------------------------------------------ Zustand

    public function state(): array
    {
        $s = Fs::readJson($this->stateDir . '/state.json');
        $s += ['schema' => 1, 'installed' => [], 'active' => [], 'errors' => [], 'mode' => '', 'migrated' => ''];
        foreach (['installed', 'errors'] as $k) {
            $s[$k] = is_array($s[$k]) ? $s[$k] : [];
        }
        $s['active'] = array_values(array_filter(array_map('strval', (array)$s['active']), static fn($x) => isset($s['installed'][$x])));
        return $s;
    }

    private function saveState(array $s): void
    {
        $this->protect($this->stateDir);
        Fs::writeJson($this->stateDir . '/state.json', $s);
    }

    private function protect(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Der Datenordner ist nicht beschreibbar.');
        }
        $f = $dir . '/.htaccess';
        if (!is_file($f)) {
            @file_put_contents($f, "# Plugin-Daten: nie öffentlich abrufbar\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
    }

    public function isInstalled(string $id): bool { return isset($this->state()['installed'][$id]); }
    public function isActive(string $id): bool { return in_array($id, $this->state()['active'], true); }
    public function setMode(string $mode): void { $s = $this->state(); $s['mode'] = $mode; $this->saveState($s); }

    public function pluginDataDir(string $id, string $sub = ''): string
    {
        $d = $this->stateDir . '/data/' . $id . ($sub !== '' ? '/' . trim(str_replace(['..', '\\'], '', $sub), '/') : '');
        $this->protect($this->stateDir . '/data');
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d;
    }

    public function log(string $id, string $line): void
    {
        try {
            $this->protect($this->stateDir);
            $f = $this->stateDir . '/log.txt';
            if (is_file($f) && filesize($f) > 200000) {
                @rename($f, $f . '.1');
            }
            @file_put_contents($f, date('Y-m-d H:i:s') . ' [' . $id . '] ' . mb_substr((string)preg_replace('/[\x00-\x1f]+/', ' ', $line), 0, 300) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }

    // ------------------------------------------------------------ Kompatibilität und Abhängigkeiten

    /** @return array{ok:bool,messages:list<string>} */
    public function compat(array $m): array
    {
        $msg = [];
        if (!Version::satisfies($this->cmsVersion, $m['requires']['elvadopress'])) {
            $msg[] = 'Benötigt ElvadoPress ' . $m['requires']['elvadopress'] . ' (installiert: ' . $this->cmsVersion . ').';
        }
        if (!Version::satisfies(PHP_VERSION, $m['requires']['php'])) {
            $msg[] = 'Benötigt PHP ' . $m['requires']['php'] . ' (Server: ' . PHP_VERSION . ').';
        }
        return ['ok' => !$msg, 'messages' => $msg];
    }

    /** Aktive Plugins, die $id benötigen. */
    public function dependents(string $id, bool $onlyActive = true): array
    {
        $s = $this->state();
        $out = [];
        foreach ($onlyActive ? $s['active'] : array_keys($s['installed']) as $other) {
            if ($other === $id) {
                continue;
            }
            [$m] = $this->installedManifest($other);
            if ($m !== null && isset($m['requires']['plugins'][$id])) {
                $out[] = $other;
            }
        }
        return $out;
    }

    /**
     * Reihenfolge, in der $ids (samt benötigter Plugins) installiert werden müssen.
     * @return array{order:list<string>,errors:list<string>}
     */
    public function plan(array $ids): array
    {
        $order = [];
        $errors = [];
        $visiting = [];
        $visit = function (string $id, string $needBy = '') use (&$visit, &$order, &$errors, &$visiting): void {
            if (in_array($id, $order, true)) {
                return;
            }
            if (isset($visiting[$id])) {
                $errors[] = 'Zyklische Abhängigkeit bei „' . $id . '“.';
                return;
            }
            $cat = $this->catalog()[$id] ?? null;
            if ($cat === null) {
                $errors[] = 'Unbekanntes Plugin „' . $id . '“' . ($needBy !== '' ? ' (benötigt von ' . $needBy . ')' : '') . '.';
                return;
            }
            if ($cat['status'] !== 'available') {
                $errors[] = '„' . $cat['name'] . '“ ist noch nicht verfügbar' . ($needBy !== '' ? ' (benötigt von ' . $needBy . ')' : '') . '.';
                return;
            }
            $visiting[$id] = true;
            [$m, $errs] = $this->libManifest($id);
            if ($m === null) {
                $errors[] = '„' . $id . '“: ' . implode(' ', $errs);
            } else {
                foreach ($m['requires']['plugins'] as $dep => $c) {
                    $depCat = $this->catalog()[$dep] ?? null;
                    if ($this->isInstalled($dep)) {
                        [$im] = $this->installedManifest($dep);
                        if ($im === null || !Version::satisfies($im['version'], $c)) {
                            $errors[] = '„' . $m['name'] . '“ benötigt ' . ($depCat['name'] ?? $dep) . ' ' . $c . ' (installiert: ' . ($im['version'] ?? '?') . ') – bitte zuerst aktualisieren.';
                        }
                        continue;
                    }
                    $visit($dep, $m['name']);
                }
            }
            unset($visiting[$id]);
            if (!$errors) {
                $order[] = $id;
            }
        };
        foreach ($ids as $id) {
            $visit((string)$id);
        }
        return ['order' => $errors ? [] : $order, 'errors' => array_values(array_unique($errors))];
    }

    // ------------------------------------------------------------ Übersicht

    /** Zeilen für die Plugin-Verwaltung. */
    public function rows(): array
    {
        $s = $this->state();
        $rows = [];
        foreach ($this->catalog() as $id => $c) {
            $inst = $s['installed'][$id] ?? null;
            $active = in_array($id, $s['active'], true);
            [$lib] = $c['status'] === 'available' ? $this->libManifest($id) : [null];
            [$im] = $inst ? $this->installedManifest($id) : [null];
            $m = $im ?? $lib;
            $official = $inst ? $this->installedOfficial($id) : ($lib !== null && $this->libraryVerified($id));
            $compat = $m ? $this->compat($m) : ['ok' => true, 'messages' => []];
            $deps = [];
            foreach (($m['requires']['plugins'] ?? []) as $dep => $con) {
                $deps[] = ['id' => $dep, 'name' => $this->catalog()[$dep]['name'] ?? $dep, 'constraint' => $con, 'installed' => $this->isInstalled($dep), 'active' => $this->isActive($dep)];
            }
            $upd = $inst && $lib !== null && self::gt($lib['version'], $inst['version'] ?? '0.0.0');
            $rows[] = [
                'id' => $id, 'name' => $m['name'] ?? $c['name'], 'description' => $m['description'] ?? $c['description'], 'icon' => $m['icon'] ?? $c['icon'],
                'author' => $m['author'] ?? '', 'homepage' => $m['homepage'] ?? '', 'license' => $m['license'] ?? '', 'category' => $c['category'], 'recommended' => !empty($c['recommended']),
                'version' => $inst['version'] ?? '', 'available_version' => $lib['version'] ?? '', 'update_available' => $upd,
                'status' => $c['status'] !== 'available' ? 'planned' : ($active ? 'active' : ($inst ? 'installed' : 'available')),
                'official' => $official, 'compat' => $compat, 'dependencies' => $deps,
                'optional' => array_keys($m['optional']['plugins'] ?? []), 'dependents' => $inst ? $this->dependents($id, false) : [],
                'capabilities' => $m['capabilities'] ?? [], 'update_source' => $m['update']['source'] ?? 'bundled',
                'tested_up_to' => $m['tested_up_to'] ?? '', 'requires' => $m['requires'] ?? null,
                'has_settings' => !empty($m['settings_page']), 'settings' => $active ? $this->settingsSchema($id) : [], 'actions' => $active ? ($m['actions'] ?? []) : [],
                'error' => (string)($s['errors'][$id] ?? ''),
            ];
        }
        return $rows;
    }

    // ------------------------------------------------------------ Installieren / Aktualisieren / Deinstallieren

    /** @return array{ok:bool,message:string,installed:list<string>} */
    public function install(string $id, bool $withDeps = false): array
    {
        $plan = $this->plan([$id]);
        if ($plan['errors']) {
            return ['ok' => false, 'message' => implode(' ', $plan['errors']), 'installed' => []];
        }
        $todo = array_values(array_filter($plan['order'], fn($x) => !$this->isInstalled($x)));
        if (count($todo) > 1 && !$withDeps) {
            $names = array_map(fn($x) => $this->catalog()[$x]['name'] ?? $x, array_diff($todo, [$id]));
            return ['ok' => false, 'message' => 'Dieses Plugin benötigt zusätzlich: ' . implode(', ', $names) . '.', 'installed' => [], 'needs' => array_values(array_diff($todo, [$id]))];
        }
        $done = [];
        foreach ($todo as $x) {
            $r = $this->installOne($x);
            if (!$r['ok']) {
                foreach (array_reverse($done) as $d) {   // Teilinstallation zurücknehmen
                    $this->uninstall($d, false);
                }
                return ['ok' => false, 'message' => $r['message'], 'installed' => []];
            }
            $done[] = $x;
        }
        return ['ok' => true, 'message' => $done ? 'Installiert: ' . implode(', ', array_map(fn($x) => $this->catalog()[$x]['name'] ?? $x, $done)) . '.' : 'Bereits installiert.', 'installed' => $done];
    }

    private function installOne(string $id): array
    {
        if (!$this->libraryVerified($id)) {
            return ['ok' => false, 'message' => 'Das Paket „' . $id . '“ ist beschädigt oder wurde verändert und wird nicht installiert.'];
        }
        [$m, $errs] = $this->libManifest($id);
        if ($m === null) {
            return ['ok' => false, 'message' => 'Ungültiges Manifest („' . $id . '“): ' . implode(' ', $errs)];
        }
        $c = $this->compat($m);
        if (!$c['ok']) {
            return ['ok' => false, 'message' => '„' . $m['name'] . '“ ist nicht kompatibel: ' . implode(' ', $c['messages'])];
        }
        $lint = Fs::lint($this->libDir . '/' . $id);
        if ($lint) {
            return ['ok' => false, 'message' => 'PHP-Fehler im Paket „' . $m['name'] . '“: ' . $lint[0]];
        }
        $dst = $this->pluginsDir . '/' . $id;
        $tmp = $this->pluginsDir . '/.tmp-' . $id . '-' . bin2hex(random_bytes(3));
        try {
            Fs::copyTree($this->libDir . '/' . $id, $tmp);
            if (!hash_equals((string)$this->catalog()[$id]['hash'], Fs::treeHash($tmp))) {
                throw new \RuntimeException('Die Kopie stimmt nicht mit dem Paket überein.');
            }
            if (is_dir($dst)) {
                Fs::rmTree($dst);   // Überrest einer früheren Installation (nicht im Zustand geführt)
            }
            if (!@rename($tmp, $dst)) {
                throw new \RuntimeException('Der Plugin-Ordner ist nicht beschreibbar.');
            }
            $s = $this->state();
            $s['installed'][$id] = ['version' => $m['version'], 'hash' => (string)$this->catalog()[$id]['hash'], 'official' => true, 'installed_at' => date(DATE_ATOM)];
            unset($s['errors'][$id]);
            $this->saveState($s);
        } catch (\Throwable $e) {
            Fs::rmTree($tmp);
            return ['ok' => false, 'message' => 'Installation von „' . $m['name'] . '“ fehlgeschlagen: ' . $e->getMessage()];
        }
        $this->log($id, 'installiert ' . $m['version']);
        return ['ok' => true, 'message' => ''];
    }

    /** @return array{ok:bool,message:string,activated:list<string>} */
    public function activate(string $id, bool $withDeps = false): array
    {
        if (!$this->isInstalled($id)) {
            if (!$withDeps) {
                return ['ok' => false, 'message' => 'Das Plugin ist nicht installiert.', 'activated' => []];
            }
            $r = $this->install($id, true);
            if (!$r['ok']) {
                return ['ok' => false, 'message' => $r['message'], 'activated' => []];
            }
        }
        $done = [];
        $chain = [];
        $collect = function (string $x) use (&$collect, &$chain, $withDeps, &$err): bool {
            if (in_array($x, $chain, true) || $this->isActive($x)) {
                return true;
            }
            [$m, $errs] = $this->installedManifest($x);
            if ($m === null) {
                $err = 'Das Manifest von „' . $x . '“ ist ungültig: ' . implode(' ', $errs);
                return false;
            }
            foreach ($m['requires']['plugins'] as $dep => $con) {
                if (!$this->isInstalled($dep)) {
                    if (!$withDeps) {
                        $err = '„' . $m['name'] . '“ benötigt „' . ($this->catalog()[$dep]['name'] ?? $dep) . '“ (nicht installiert).';
                        return false;
                    }
                    $ri = $this->install($dep, true);
                    if (!$ri['ok']) {
                        $err = $ri['message'];
                        return false;
                    }
                }
                [$dm] = $this->installedManifest($dep);
                if ($dm === null || !Version::satisfies($dm['version'], $con)) {
                    $err = '„' . $m['name'] . '“ benötigt ' . ($this->catalog()[$dep]['name'] ?? $dep) . ' ' . $con . '.';
                    return false;
                }
                if (!$this->isActive($dep)) {
                    if (!$withDeps) {
                        $err = '„' . $m['name'] . '“ benötigt, dass „' . ($this->catalog()[$dep]['name'] ?? $dep) . '“ aktiv ist.';
                        return false;
                    }
                    if (!$collect($dep)) {
                        return false;
                    }
                }
            }
            $chain[] = $x;
            return true;
        };
        $err = '';
        if (!$collect($id)) {
            return ['ok' => false, 'message' => $err, 'activated' => []];
        }
        foreach ($chain as $x) {
            $r = $this->activateOne($x);
            if (!$r['ok']) {
                foreach (array_reverse($done) as $d) {
                    $this->deactivateOne($d);
                }
                return ['ok' => false, 'message' => $r['message'], 'activated' => []];
            }
            $done[] = $x;
        }
        return ['ok' => true, 'message' => $done ? 'Aktiviert: ' . implode(', ', array_map(fn($x) => $this->catalog()[$x]['name'] ?? $x, $done)) . '.' : 'Bereits aktiv.', 'activated' => $done];
    }

    private function activateOne(string $id): array
    {
        [$m, $errs] = $this->installedManifest($id);
        if ($m === null) {
            return ['ok' => false, 'message' => 'Ungültiges Manifest: ' . implode(' ', $errs)];
        }
        if (!$this->installedOfficial($id)) {
            return ['ok' => false, 'message' => '„' . $m['name'] . '“ wurde seit der Installation verändert oder ist nicht offiziell und wird nicht ausgeführt. Bitte neu installieren.'];
        }
        $c = $this->compat($m);
        if (!$c['ok']) {
            return ['ok' => false, 'message' => '„' . $m['name'] . '“ ist nicht kompatibel: ' . implode(' ', $c['messages'])];
        }
        $snap = Hooks::snapshot();
        $apiSnap = $this->api;
        $r = $this->load($id, $m);   // Probelauf: Fehler beim Laden verhindern die Aktivierung
        if ($r !== null) {
            Hooks::restore($snap);
            $this->api = $apiSnap;
            unset($this->loaded[$id]);
            $s = $this->state();
            $s['errors'][$id] = $r;
            $this->saveState($s);
            $this->log($id, 'Aktivierung fehlgeschlagen: ' . $r);
            return ['ok' => false, 'message' => 'Aktivierung von „' . $m['name'] . '“ fehlgeschlagen: ' . $r];
        }
        $s = $this->state();
        $s['active'][] = $id;
        $s['active'] = array_values(array_unique($s['active']));
        unset($s['errors'][$id]);
        $this->saveState($s);
        $this->log($id, 'aktiviert');
        return ['ok' => true, 'message' => ''];
    }

    /** @return array{ok:bool,message:string,deactivated:list<string>} */
    public function deactivate(string $id, bool $cascade = false): array
    {
        if (!$this->isActive($id)) {
            return ['ok' => true, 'message' => 'Bereits inaktiv.', 'deactivated' => []];
        }
        $deps = $this->dependents($id, true);
        if ($deps && !$cascade) {
            $names = array_map(fn($x) => $this->catalog()[$x]['name'] ?? $x, $deps);
            return ['ok' => false, 'message' => 'Folgende aktive Plugins benötigen dieses Plugin: ' . implode(', ', $names) . '. Sie müssen zuerst deaktiviert werden.', 'deactivated' => [], 'dependents' => $deps];
        }
        $done = [];
        foreach (array_merge($deps, [$id]) as $x) {   // erst die abhängigen
            if ($this->isActive($x)) {
                $this->deactivateOne($x);
                $done[] = $x;
            }
        }
        return ['ok' => true, 'message' => 'Deaktiviert: ' . implode(', ', array_map(fn($x) => $this->catalog()[$x]['name'] ?? $x, $done)) . '.', 'deactivated' => $done];
    }

    private function deactivateOne(string $id): void
    {
        $s = $this->state();
        $s['active'] = array_values(array_diff($s['active'], [$id]));
        $this->saveState($s);
        $this->log($id, 'deaktiviert');
    }

    /** Aktualisiert ein installiertes Plugin auf die Fassung der Paketbibliothek (mit Sicherung und Rückgängig bei Fehlern). */
    public function update(string $id): array
    {
        $s = $this->state();
        $inst = $s['installed'][$id] ?? null;
        if ($inst === null) {
            return ['ok' => false, 'message' => 'Das Plugin ist nicht installiert.'];
        }
        if (!$this->libraryVerified($id)) {
            return ['ok' => false, 'message' => 'Das Update-Paket ist beschädigt oder wurde verändert. Der bisherige Stand bleibt erhalten.'];
        }
        [$new, $errs] = $this->libManifest($id);
        if ($new === null) {
            return ['ok' => false, 'message' => 'Ungültiges Manifest im Update: ' . implode(' ', $errs) . ' Der bisherige Stand bleibt erhalten.'];
        }
        if (!self::gt($new['version'], (string)$inst['version'])) {
            return ['ok' => true, 'message' => 'Das Plugin ist aktuell (' . $inst['version'] . ').'];
        }
        $c = $this->compat($new);
        if (!$c['ok']) {
            return ['ok' => false, 'message' => 'Das Update ist nicht kompatibel: ' . implode(' ', $c['messages']) . ' Der bisherige Stand bleibt erhalten.'];
        }
        foreach ($new['requires']['plugins'] as $dep => $con) {   // neue/geänderte Abhängigkeiten des Updates
            [$dm] = $this->installedManifest($dep);
            if ($dm === null || !Version::satisfies($dm['version'], $con)) {
                return ['ok' => false, 'message' => 'Das Update benötigt ' . ($this->catalog()[$dep]['name'] ?? $dep) . ' ' . $con . ' (' . ($dm ? 'installiert: ' . $dm['version'] : 'nicht installiert') . '). Der bisherige Stand bleibt erhalten.'];
            }
        }
        foreach ($this->dependents($id, false) as $other) {   // andere Plugins dürfen nicht unauflösbar werden
            [$om] = $this->installedManifest($other);
            if ($om !== null && !Version::satisfies($new['version'], $om['requires']['plugins'][$id] ?? '*')) {
                return ['ok' => false, 'message' => 'Das Update würde „' . $om['name'] . '“ unbrauchbar machen (benötigt ' . $id . ' ' . $om['requires']['plugins'][$id] . '). Der bisherige Stand bleibt erhalten.'];
            }
        }
        $lint = Fs::lint($this->libDir . '/' . $id);
        if ($lint) {
            return ['ok' => false, 'message' => 'PHP-Fehler im Update: ' . $lint[0] . ' Der bisherige Stand bleibt erhalten.'];
        }
        $dst = $this->pluginsDir . '/' . $id;
        $tmp = $this->pluginsDir . '/.tmp-' . $id . '-' . bin2hex(random_bytes(3));
        $old = $this->pluginsDir . '/.old-' . $id . '-' . bin2hex(random_bytes(3));
        $backup = $this->stateDir . '/backup/' . $id . '-' . $inst['version'] . '-' . date('Ymd-His');
        try {
            if (is_dir($dst)) {
                $this->protect($this->stateDir . '/backup');
                Fs::copyTree($dst, $backup);
            }
            Fs::copyTree($this->libDir . '/' . $id, $tmp);
            if (!hash_equals((string)$this->catalog()[$id]['hash'], Fs::treeHash($tmp))) {
                throw new \RuntimeException('Die Kopie stimmt nicht mit dem Paket überein.');
            }
            if (is_dir($dst) && !@rename($dst, $old)) {
                throw new \RuntimeException('Der Plugin-Ordner ist nicht beschreibbar.');
            }
            if (!@rename($tmp, $dst)) {
                @rename($old, $dst);
                throw new \RuntimeException('Der neue Stand konnte nicht eingesetzt werden.');
            }
        } catch (\Throwable $e) {
            Fs::rmTree($tmp);
            return ['ok' => false, 'message' => 'Update fehlgeschlagen: ' . $e->getMessage() . ' Der bisherige Stand bleibt erhalten.'];
        }
        $restore = function (string $why) use ($dst, $old, $id): array {
            Fs::rmTree($dst);
            @rename($old, $dst);
            $this->log($id, 'Update zurückgenommen: ' . $why);
            return ['ok' => false, 'message' => 'Update fehlgeschlagen und zurückgenommen: ' . $why];
        };
        if ($this->isActive($id)) {   // Probelauf der neuen Fassung (Plugins werden bei Verwaltungsaktionen nicht vorab geladen)
            $snap = Hooks::snapshot();
            $apiSnap = $this->api;
            unset($this->loaded[$id]);
            $s2 = $this->state();
            $s2['installed'][$id] = ['version' => $new['version'], 'hash' => (string)$this->catalog()[$id]['hash'], 'official' => true] + $inst;
            $this->saveState($s2);
            $r = $this->load($id, $new);
            Hooks::restore($snap);
            $this->api = $apiSnap;
            unset($this->loaded[$id]);
            if ($r !== null) {
                $this->saveState($s);   // Zustand wie vorher
                return $restore($r);
            }
        }
        $s = $this->state();
        $s['installed'][$id] = ['version' => $new['version'], 'hash' => (string)$this->catalog()[$id]['hash'], 'official' => true, 'installed_at' => $inst['installed_at'] ?? date(DATE_ATOM), 'updated_at' => date(DATE_ATOM)];
        unset($s['errors'][$id]);
        $this->saveState($s);
        Fs::rmTree($old);
        $this->log($id, 'aktualisiert ' . $inst['version'] . ' → ' . $new['version']);
        return ['ok' => true, 'message' => '„' . $new['name'] . '“ wurde auf ' . $new['version'] . ' aktualisiert.'];
    }

    /** Deinstalliert ein Plugin (muss inaktiv sein, keine installierten Plugins dürfen es benötigen). $purge löscht auch Einstellungen und Plugin-Daten. */
    public function uninstall(string $id, bool $purge = false): array
    {
        if (!$this->isInstalled($id)) {
            return ['ok' => true, 'message' => 'Nicht installiert.'];
        }
        if ($this->isActive($id)) {
            return ['ok' => false, 'message' => 'Das Plugin ist aktiv. Bitte zuerst deaktivieren.'];
        }
        $deps = $this->dependents($id, false);
        if ($deps) {
            return ['ok' => false, 'message' => 'Folgende installierte Plugins benötigen dieses Plugin: ' . implode(', ', array_map(fn($x) => $this->catalog()[$x]['name'] ?? $x, $deps)) . '. Sie müssen zuerst deinstalliert werden.'];
        }
        $dir = $this->pluginsDir . '/' . $id;
        $s = $this->state();
        $ver = $s['installed'][$id]['version'] ?? 'x';
        if (is_dir($dir)) {   // vor dem Löschen sichern
            try {
                $this->protect($this->stateDir . '/backup');
                Fs::copyTree($dir, $this->stateDir . '/backup/' . $id . '-' . $ver . '-' . date('Ymd-His') . '-entfernt');
            } catch (\Throwable) {
            }
            Fs::rmTree($dir);
        }
        unset($s['installed'][$id], $s['errors'][$id]);
        $s['active'] = array_values(array_diff($s['active'], [$id]));
        $this->saveState($s);
        if ($purge) {
            @unlink($this->stateDir . '/settings/' . $id . '.json');
            Fs::rmTree($this->stateDir . '/data/' . $id);
        }
        $this->log($id, 'deinstalliert' . ($purge ? ' (Daten gelöscht)' : ''));
        return ['ok' => true, 'message' => 'Deinstalliert' . ($purge ? ' (Einstellungen und Daten gelöscht)' : ' (Einstellungen und Daten bleiben erhalten)') . '.'];
    }

    /**
     * Auswahl aus dem Installer: installiert (und aktiviert) die Plugins samt Abhängigkeiten; ein fehlgeschlagenes Plugin überspringt nur sich selbst und seine Abhängigen.
     * @return array{installed:list<string>,activated:list<string>,failed:array<string,string>}
     */
    public function installSelection(array $ids, bool $activate = true): array
    {
        $res = ['installed' => [], 'activated' => [], 'failed' => []];
        $plan = $this->plan($ids);
        $order = $plan['order'];
        if ($plan['errors']) {   // einzeln planen, damit ein kaputtes Plugin die anderen nicht blockiert
            $order = [];
            foreach ($ids as $id) {
                $p = $this->plan([(string)$id]);
                if ($p['errors']) {
                    $res['failed'][(string)$id] = implode(' ', $p['errors']);
                } else {
                    $order = array_merge($order, $p['order']);
                }
            }
            $order = array_values(array_unique($order));
        }
        foreach ($order as $id) {
            if (!$this->isInstalled($id)) {
                $r = $this->installOne($id);
                if (!$r['ok']) {
                    $res['failed'][$id] = $r['message'];
                    continue;
                }
                $res['installed'][] = $id;
            }
            if ($activate && !$this->isActive($id)) {
                $r = $this->activate($id, false);
                if ($r['ok']) {
                    $res['activated'] = array_merge($res['activated'], $r['activated']);
                } else {
                    $res['failed'][$id] = $r['message'];
                }
            }
        }
        return $res;
    }

    // ------------------------------------------------------------ Laden

    /** Aktive, unveränderte offizielle Plugins laden. $only: nur diese (und ihre Abhängigkeiten). Fehler deaktivieren das Plugin und werden notiert; die Seite läuft weiter. */
    public function boot(?array $only = null): void
    {
        if ($this->booted && $only === null) {
            return;
        }
        $this->booted = $only === null;
        $s = $this->state();
        if (!$s['active']) {
            return;
        }
        static $shutdown = false;
        if (!$shutdown) {
            $shutdown = true;
            register_shutdown_function(function (): void {   // fataler Fehler in Plugin-Code: Plugin abschalten, damit die nächste Anfrage wieder läuft
                $e = error_get_last();
                if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                    $real = realpath((string)$e['file']) ?: (string)$e['file'];
                    $base = (realpath($this->pluginsDir) ?: $this->pluginsDir) . '/';
                    if (str_starts_with($real, $base) && preg_match('~^([a-z0-9-]+)/~', substr($real, strlen($base)), $m)) {
                        $st = $this->state();
                        $st['active'] = array_values(array_diff($st['active'], [$m[1]]));
                        $st['errors'][$m[1]] = 'Fataler Fehler: ' . mb_substr((string)$e['message'], 0, 200) . ' – automatisch deaktiviert.';
                        try {
                            $this->saveState($st);
                            $this->log($m[1], 'automatisch deaktiviert: ' . mb_substr((string)$e['message'], 0, 200));
                        } catch (\Throwable) {
                        }
                    }
                }
            });
        }
        foreach ($s['active'] as $id) {
            if ($only !== null && !in_array($id, $only, true)) {
                continue;
            }
            if (isset($this->loaded[$id])) {
                continue;
            }
            [$m] = $this->installedManifest($id);
            $why = '';
            if ($m === null) {
                $why = 'Ungültiges Manifest.';
            } elseif (!$this->installedOfficial($id)) {
                $why = 'Das Plugin wurde verändert oder ist nicht offiziell und wird nicht ausgeführt.';
            } elseif (!$this->compat($m)['ok']) {
                $why = implode(' ', $this->compat($m)['messages']);
            } else {
                foreach ($m['requires']['plugins'] as $dep => $con) {
                    if (!isset($this->loaded[$dep]) && !$this->isActive($dep)) {
                        $why = 'Benötigtes Plugin „' . $dep . '“ ist nicht aktiv.';
                    }
                }
                if ($why === '') {
                    $why = (string)$this->load($id, $m);
                }
            }
            if ($why !== '') {
                $st = $this->state();
                $st['active'] = array_values(array_diff($st['active'], [$id]));
                $st['errors'][$id] = $why;
                try {
                    $this->saveState($st);
                    $this->log($id, 'beim Laden deaktiviert: ' . $why);
                } catch (\Throwable) {
                }
            }
        }
    }

    /** @return ?string Fehlertext oder null */
    private function load(string $id, array $m): ?string
    {
        $this->loaded[$id] = true;
        $entry = $m['entry'] !== '' ? $m['entry'] : 'plugin.php';
        $file = $this->pluginsDir . '/' . $id . '/' . $entry;
        if (!is_file($file)) {
            return $m['entry'] === '' ? null : 'Die Einstiegsdatei fehlt.';   // Plugins ohne PHP sind erlaubt
        }
        try {
            $np = new Context($this, $m, $this->pluginsDir . '/' . $id);
            (static function (Context $np, string $__file): void { require $__file; })($np, $file);
        } catch (\Throwable $e) {
            return get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 200);
        }
        return null;
    }

    // ------------------------------------------------------------ Einstellungen

    public function settingsSchema(string $id): array
    {
        [$m] = $this->installedManifest($id);
        return $m['settings'] ?? [];
    }

    /** Vorgaben + gespeicherte Werte. @param bool $withSecrets Geheimnisse mitliefern (nur serverintern!) */
    public function settings(string $id, bool $withSecrets = false): array
    {
        $out = [];
        $saved = Fs::readJson($this->stateDir . '/settings/' . $id . '.json');
        foreach ($this->settingsSchema($id) as $f) {
            $v = array_key_exists($f['key'], $saved) ? $saved[$f['key']] : $f['default'];
            if ($f['type'] === 'secret' && !$withSecrets) {
                $out[$f['key']] = '';
                $out[$f['key'] . '_set'] = (string)$v !== '';
            } else {
                $out[$f['key']] = $v;
            }
        }
        return $out;
    }

    /** @return array{ok:bool,message:string,settings:array} */
    public function saveSettings(string $id, array $in): array
    {
        if (!$this->isInstalled($id)) {
            return ['ok' => false, 'message' => 'Das Plugin ist nicht installiert.', 'settings' => []];
        }
        $old = Fs::readJson($this->stateDir . '/settings/' . $id . '.json');
        $new = [];
        $errors = [];
        foreach ($this->settingsSchema($id) as $f) {
            $k = $f['key'];
            $has = array_key_exists($k, $in);
            $cur = array_key_exists($k, $old) ? $old[$k] : $f['default'];
            if (!$has) {
                $new[$k] = $cur;
                continue;
            }
            $v = $in[$k];
            switch ($f['type']) {
                case 'toggle':
                    $new[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'number':
                    if (!is_numeric($v)) {
                        $errors[] = $f['label'] . ': Bitte eine Zahl eingeben.';
                        $new[$k] = $cur;
                        break;
                    }
                    $n = (float)$v;
                    $n = isset($f['min']) ? max($f['min'], $n) : $n;
                    $n = isset($f['max']) ? min($f['max'], $n) : $n;
                    $new[$k] = floor($n) == $n ? (int)$n : $n;
                    break;
                case 'select':
                    $vals = array_column($f['options'], 'value');
                    if (!in_array((string)$v, $vals, true)) {
                        $errors[] = $f['label'] . ': Ungültige Auswahl.';
                        $new[$k] = $cur;
                    } else {
                        $new[$k] = (string)$v;
                    }
                    break;
                case 'url':
                    $v = trim((string)$v);
                    if ($v !== '' && !preg_match('~^https://[^\s"\'<>]{3,300}$~', $v)) {
                        $errors[] = $f['label'] . ': Bitte eine https-Adresse eingeben.';
                        $new[$k] = $cur;
                    } else {
                        $new[$k] = $v;
                    }
                    break;
                case 'email':
                    $v = trim((string)$v);
                    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = $f['label'] . ': Ungültige E-Mail-Adresse.';
                        $new[$k] = $cur;
                    } else {
                        $new[$k] = mb_substr($v, 0, 200);
                    }
                    break;
                case 'secret':
                    $v = (string)$v;
                    $new[$k] = $v === '' ? $cur : ($v === '__clear__' ? '' : mb_substr($v, 0, 500));   // leer = unverändert lassen
                    break;
                case 'textarea':
                    $new[$k] = mb_substr(str_replace("\r", '', (string)$v), 0, 5000);
                    break;
                default:
                    $new[$k] = mb_substr(trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', (string)$v) ?? ''), 0, 500);
            }
        }
        if ($errors) {
            return ['ok' => false, 'message' => implode(' ', $errors), 'settings' => $this->settings($id)];
        }
        try {
            $this->protect($this->stateDir . '/settings');
            Fs::writeJson($this->stateDir . '/settings/' . $id . '.json', $new);
            @chmod($this->stateDir . '/settings/' . $id . '.json', 0600);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Speichern fehlgeschlagen: ' . $e->getMessage(), 'settings' => $this->settings($id)];
        }
        Hooks::run('plugin_settings_saved', $id, $this->settings($id, true));
        return ['ok' => true, 'message' => 'Gespeichert.', 'settings' => $this->settings($id)];
    }

    // ------------------------------------------------------------ Plugin-API

    public function registerApi(string $id, string $name, callable $cb, string $access): void
    {
        $this->api[$id][$name] = ['cb' => $cb, 'access' => $access];
    }

    /**
     * Eine API-Aktion eines aktiven Plugins aufrufen.
     * @param string $role Rolle des Aufrufers: admin | editor | public
     */
    public function callApi(string $id, string $name, array $args, string $role): array
    {
        if (!$this->isActive($id)) {
            return ['status' => 'error', 'message' => 'Das Plugin ist nicht aktiv.', 'code' => 404];
        }
        $this->boot([$id]);
        $h = $this->api[$id][$name] ?? null;
        if ($h === null) {
            return ['status' => 'error', 'message' => 'Unbekannte Plugin-Aktion.', 'code' => 404];
        }
        if ((self::ROLE[$role] ?? 0) < self::ROLE[$h['access']]) {
            return ['status' => 'error', 'message' => 'Dafür fehlt die Berechtigung.', 'code' => 403];
        }
        try {
            $r = ($h['cb'])($args, $role);
            return is_array($r) ? $r + ['status' => 'ok'] : ['status' => 'ok'];
        } catch (\Throwable $e) {
            $this->log($id, 'API ' . $name . ': ' . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 200));
            return ['status' => 'error', 'message' => 'Fehler im Plugin: ' . mb_substr($e->getMessage(), 0, 200), 'code' => 500];
        }
    }
}

