<?php
declare(strict_types=1);
// Sichtbarkeits-Check: erklärt, warum Seiten, Beiträge und Menüpunkte auf der Website (nicht) erscheinen – aus site.json und news.json, ohne etwas zu ändern.
// Genutzt von der Verwaltung (api.php: visibility_report), vom Skript scripts/visibility-report.php und für Entwickler-Werkzeuge.

/**
 * @param array<string,mixed> $site  site.json
 * @param list<array<string,mixed>> $news  news.json
 * @return array{summary:array<string,int>,items:list<array{kind:string,id:string,title:string,level:string,problem:string,hint:string}>}
 */
function elvado_visibility_report(array $site, array $news, ?int $now = null): array
{
    $now ??= time();
    $items = [];
    $add = static function (string $kind, string $id, string $title, string $level, string $problem, string $hint) use (&$items): void {
        $items[] = ['kind' => $kind, 'id' => $id, 'title' => mb_substr($title, 0, 120), 'level' => $level, 'problem' => $problem, 'hint' => $hint];
    };
    $pages = is_array($site['pages'] ?? null) ? $site['pages'] : [];
    $menus = is_array($site['menus'] ?? null) ? $site['menus'] : [];
    $isOn = static fn(array $m): bool => !array_key_exists('enabled', $m) || !empty($m['enabled']);
    // Menüpunkte, die wirklich angezeigt werden (Punkt und übergeordneter Punkt aktiv)
    $shown = [];
    foreach (['top' => 'Hauptmenü', 'bottom' => 'Mobiles Menü'] as $which => $menuLabel) {
        $list = array_values(array_filter((array)($menus[$which] ?? []), 'is_array'));
        $byId = [];
        foreach ($list as $m) {
            $byId[(string)($m['id'] ?? '')] = $m;
        }
        foreach ($list as $m) {
            $parent = (string)($m['parent_id'] ?? '');
            $on = $isOn($m) && ($parent === '' || (isset($byId[$parent]) && $isOn($byId[$parent])));
            $target = (string)($m['target'] ?? '');
            if ($on) {
                $shown[$target] = true;
            } elseif ($target !== '' && !str_starts_with($target, 'action:')) {
                $add('menu', (string)($m['id'] ?? ''), (string)($m['label'] ?? $target), 'info', $menuLabel . ': Menüpunkt ist ' . ($isOn($m) ? 'wegen eines ausgeblendeten Obermenüs nicht' : 'ausgeblendet und wird nicht') . ' angezeigt', 'Unter „Menüs & Navigation“ einblenden.');
            }
        }
    }
    $pageIds = [];
    foreach ($pages as $p) {
        if (!is_array($p)) {
            continue;
        }
        $id = (string)($p['id'] ?? '');
        $slug = (string)($p['slug'] ?? '');
        $title = (string)($p['title'] ?? $slug);
        $sys = ($p['type'] ?? 'custom') === 'system';
        $pageIds[$id] = $pageIds[$slug] = $p;
        if (array_key_exists('enabled', $p) && empty($p['enabled'])) {
            $add('page', $id, $title, 'blocker', 'Seite ist deaktiviert – sie wird auf der Website nicht ausgeliefert', 'Unter „Seiten“ aktivieren.');
            continue;
        }
        $at = (string)($p['publish_at'] ?? '');
        if (!$sys && $at !== '' && ($ts = strtotime($at)) !== false && $ts > $now) {
            $add('page', $id, $title, 'blocker', 'Seite ist geplant für ' . date('d.m.Y H:i', $ts) . ' und noch nicht sichtbar', 'Zeitplan unter „Seiten“ entfernen oder abwarten.');
            continue;
        }
        $inMenu = isset($shown['page:' . $id]) || ($slug !== '' && isset($shown['page:' . $slug])) || ($sys && isset($shown['system:' . (string)($p['system_target'] ?? $slug)]));
        if (!$inMenu) {
            $add('page', $id, $title, 'info', 'Seite ist erreichbar, steht aber in keinem sichtbaren Menü', 'Unter „Menüs & Navigation“ zum Menü hinzufügen (sonst nur per Direktlink).');
        }
    }
    foreach (['top', 'bottom'] as $which) {
        foreach ((array)($menus[$which] ?? []) as $m) {
            if (!is_array($m) || !$isOn($m) || !str_starts_with((string)($m['target'] ?? ''), 'page:')) {
                continue;
            }
            $ref = substr((string)$m['target'], 5);
            $p = $pageIds[$ref] ?? null;
            if ($p === null) {
                $add('menu', (string)($m['id'] ?? ''), (string)($m['label'] ?? $ref), 'blocker', 'Menüpunkt zeigt auf eine Seite, die es nicht gibt', 'Seite anlegen oder den Menüpunkt löschen.');
            } elseif (array_key_exists('enabled', $p) && empty($p['enabled'])) {
                $add('menu', (string)($m['id'] ?? ''), (string)($m['label'] ?? $ref), 'blocker', 'Menüpunkt führt zu einer deaktivierten Seite', 'Seite aktivieren oder Menüpunkt ausblenden.');
            }
        }
    }
    foreach ($news as $a) {
        if (!is_array($a)) {
            continue;
        }
        $id = (string)($a['id'] ?? '');
        $title = (string)($a['title'] ?? $id);
        if (!empty($a['deleted_at'])) {
            $add('post', $id, $title, 'info', 'Beitrag liegt im Papierkorb', 'Unter „Beiträge“ wiederherstellen.');
        } elseif (($a['status'] ?? 'draft') !== 'published') {
            $add('post', $id, $title, 'blocker', 'Beitrag ist ein Entwurf (Status „' . (string)($a['status'] ?? 'draft') . '“) und nicht öffentlich', 'Unter „Beiträge“ veröffentlichen.');
        } elseif (($pa = trim((string)($a['published_at'] ?? ''))) !== '' && ($ts = strtotime($pa)) !== false && $ts > $now) {
            $add('post', $id, $title, 'blocker', 'Beitrag ist für ' . date('d.m.Y H:i', $ts) . ' geplant und noch nicht sichtbar', 'Veröffentlichungsdatum anpassen oder abwarten.');
        }
    }
    $sum = ['blocker' => 0, 'info' => 0];
    foreach ($items as $i) {
        $sum[$i['level']]++;
    }
    return ['summary' => $sum + ['pages' => count($pages), 'posts' => count($news)], 'items' => array_slice($items, 0, 300)];
}
