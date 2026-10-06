<?php
declare(strict_types=1);
// Inhalt der ElvadoPress-Demo: Die Demo-Website IST die Produkt-Homepage von ElvadoPress, gebaut mit ElvadoPress selbst
// (Theme „Baukasten“ mit Homepage-Baukasten-Layout, Seiten, Menü, Beiträge). Wird bei jedem Zurücksetzen der Demo neu angelegt (lib/demo.php).
// Nur Produkttexte – neutral, ohne RicoReWi-Bezug.

/** Abschnitte der Startseite (Format des Homepage-Baukastens, Option elvado_bk_layout). */
function rrw_demo_layout(array $c): array {
    $u=htmlspecialchars($c['user'],ENT_QUOTES);$p=htmlspecialchars($c['password'],ENT_QUOTES);$min=(int)$c['minutes'];
    return [
        ['id'=>'hero','type'=>'hero','props'=>['title'=>'ElvadoPress','text'=>'Das erweiterbare CMS für Websites aller Art: WordPress-kompatibel, mit eigenem Homepage-Baukasten, fünf Themes, freien Bildquellen, KI-Assistent und Updates mit automatischem Rückschritt.','overlay'=>false,'btn_label'=>'Verwaltung live ausprobieren','btn_url'=>'/cms/?demo=1','height'=>520]],
        ['id'=>'features','type'=>'features','props'=>['title'=>'Alles drin, was eine Website braucht','columns'=>'3','bg'=>'default','items'=>[
            ['title'=>'Homepage-Baukasten','text'=>'Startseiten aus frei sortierbaren Abschnitten bauen – per Drag & Drop direkt in der Verwaltung, Farben, Schrift und Breiten im Customizer.'],
            ['title'=>'WordPress-kompatibel','text'=>'Hooks, Shortcodes, Blöcke, Customizer und viele klassische Themes und Plugins laufen weiter – ohne WordPress-Installation.'],
            ['title'=>'Fünf Themes','text'=>'Baukasten, Radio, Band, Creator und Klassisch – jedes mit eigenem Look und eigenem Konfigurationsmenü, das nur beim aktiven Theme erscheint.'],
            ['title'=>'Freie Bilder','text'=>'Pixabay, Pexels, Unsplash, Openverse und Wikimedia Commons direkt in der Mediathek durchsuchen – mit Bildnachweis automatisch übernommen.'],
            ['title'=>'KI-Assistent','text'=>'Texte, Übersetzungen und Layouts mit EvoLink, OpenAI, Anthropic, Google, OpenRouter oder DeepSeek – mit deinem eigenen Schlüssel.'],
            ['title'=>'Updates mit Rückschritt','text'=>'Neue Versionen aus GitHub einspielen: mit Sicherung, Gesundheitsprüfung und automatischem Zurückspielen, falls etwas schiefgeht.']]]],
        ['id'=>'builder','type'=>'image_text','props'=>['title'=>'Dein Design, deine Regeln','text'=>'<p>Der <strong>Homepage-Baukasten</strong> setzt Startseiten aus Abschnitten zusammen: Hero, Karten, Bild + Text, Beiträge, Aufruf, eigenes HTML. Per Drag & Drop sortieren, ausblenden, mit Live-Vorschau speichern – ohne eine Zeile Code. Farben, Schrift, Breiten und die Seitenleiste stellst du im Customizer ein.</p><p>Wer lieber programmiert, nutzt Plugin-Haken, Shortcodes und eigene Themes.</p>','image'=>'/cms/assets/demo/homepage-builder.svg','reverse'=>false,'btn_label'=>'Themes ansehen','btn_url'=>'/themes/','bg'=>'default']],
        ['id'=>'try','type'=>'text','props'=>['title'=>'Probier es selbst aus','align'=>'center','bg'=>'alt','body'=>'<p>Diese Website ist eine <strong>Live-Demo</strong> – und sie ist selbst mit ElvadoPress gebaut. Nahezu alle Funktionen sind freigeschaltet; nach <strong>'.$min.' Minuten</strong> wird alles automatisch auf den Ausgangszustand zurückgesetzt, du kannst also nichts kaputt machen.</p><p><a class="btn" href="/cms/?demo=1">Mit einem Klick in die Verwaltung</a></p><p>Zugang (falls manuell): Benutzer <code>'.$u.'</code> · Passwort <code>'.$p.'</code></p><p>Tipp: Unter <em>Design → Homepage-Baukasten</em> kannst du diese Startseite sofort umbauen.</p>']],
        ['id'=>'uses','type'=>'features','props'=>['title'=>'Für jeden Zweck das passende Design','columns'=>'2','bg'=>'default','items'=>[
            ['title'=>'Radio & Podcast','text'=>'Player, Jetzt läuft, Sendeplan; laut.fm, Icecast oder Shoutcast; Alexa-Skill und App-Baukasten.'],
            ['title'=>'Bands & Musiker','text'=>'Tourdaten, Releases, Videos, Presse-Kit und Booking – im Poster-Stil.'],
            ['title'=>'Creator & Influencer','text'=>'Link-in-Bio, Empfehlungen mit Rabattcodes, Drops mit Countdown und Mediakit.'],
            ['title'=>'Blogs & Firmen','text'=>'Beiträge, Seiten, Formulare, Kommentare, Community und SEO – alles eingebaut.']]]],
        ['id'=>'dev','type'=>'features','props'=>['title'=>'Für Entwickler gebaut','columns'=>'3','bg'=>'alt','items'=>[
            ['title'=>'Dateien oder Datenbank','text'=>'Läuft mit reinen Dateien, optional mit MySQL/MariaDB oder SQLite-Spiegel – ohne Build-Schritt, PHP 8.1+.'],
            ['title'=>'React & Lovable','text'=>'Lovable-Widgets per React-Bridge einbinden, GitHub-Repositories automatisch synchronisieren.'],
            ['title'=>'Erweiterbar','text'=>'Plugin-Haken in allen Themes, Shortcodes, JSON-API und freie Lizenz (GPL-2.0-or-later).']]]],
        ['id'=>'posts','type'=>'posts','props'=>['title'=>'Neues aus der Entwicklung','count'=>3,'category'=>'','all_label'=>'Alle Beiträge','bg'=>'default']],
        ['id'=>'cta','type'=>'cta','props'=>['title'=>'Selbst betreiben – frei und kostenlos','text'=>'Code, Installation und Dokumentation auf GitHub.','btn_label'=>'ElvadoPress auf GitHub','btn_url'=>'https://github.com/ricorewioriginal-collab/ElvadoPress','bg'=>'dark']],
    ];
}

/** Seiten der Demo-Website (Rohformat für rrw_clean_section('pages')). */
function rrw_demo_pages(array $c): array {
    $u=htmlspecialchars($c['user'],ENT_QUOTES);$p=htmlspecialchars($c['password'],ENT_QUOTES);$min=(int)$c['minutes'];
    $page=fn(string $id,string $title,string $html)=>['id'=>$id,'slug'=>$id,'title'=>$title,'type'=>'custom','enabled'=>true,'blocks_before'=>[['type'=>'html','html'=>$html]],'blocks_after'=>[]];
    return [
        $page('funktionen','Funktionen','<h2>Inhalte</h2><ul><li>Beiträge mit Kategorien, Schlagwörtern, Entwürfen, Planung und Revisionen</li><li>Seiten mit Blöcken, Menüs, Widgets und Kommentaren</li><li>Mediathek mit Upload und <strong>freien Bildquellen</strong> (Pixabay, Pexels, Unsplash, Openverse, Wikimedia Commons) inklusive Bildnachweis</li><li>Formulare, Umfragen, Forum und Community</li></ul>'
            .'<h2>Design</h2><ul><li><strong>Homepage-Baukasten</strong>: Abschnitte hinzufügen, sortieren, ausblenden – mit Live-Vorschau</li><li>Customizer für Farben, Schrift, Breiten, Kopf- und Fußbereich</li><li>Fünf mitgelieferte Themes, dazu klassische WordPress-Themes</li></ul>'
            .'<h2>WordPress-Kompatibilität</h2><ul><li>Hooks, Shortcodes, <code>WP_Query</code>, Blöcke, Block-Themes</li><li>Viele bekannte Plugins laufen (z. B. Contact Form 7, Yoast SEO)</li></ul>'
            .'<h2>KI, Entwicklung, Betrieb</h2><ul><li>KI-Assistent mit mehreren Anbietern für Texte, Übersetzungen und Layouts</li><li>Lovable-Bridge und GitHub-Synchronisation für React-Oberflächen</li><li>Datenbank-Spiegel (MySQL/MariaDB/SQLite), Backups, SEO, Sitemap</li><li><strong>CMS-Update über GitHub</strong> mit Sicherung, Gesundheitsprüfung, automatischem Rückschritt und Downgrade</li></ul>'),
        $page('themes','Themes','<p>Jedes Theme sieht anders aus und bringt – sobald es aktiv ist – sein eigenes Konfigurationsmenü in die Verwaltung mit. Wechsle unter <em>Design → Themes</em> und sieh dir die Startseite an.</p>'
            .'<h3>Baukasten</h3><p>Das freie Theme: Startseite aus beliebigen Abschnitten (Hero, Karten, Bild + Text, Beiträge, Aufruf …). Genau damit ist diese Website gebaut.</p>'
            .'<h3>Radio</h3><p>Für Webradios: Player-Leiste, „Jetzt läuft“, Sendeplan, Sender mit laut.fm, Icecast oder Shoutcast – inklusive Alexa-Anbindung.</p>'
            .'<h3>Band</h3><p>Für Musiker und Bands: Poster-Optik, Tourdaten, Releases, Videos, Presse und Booking.</p>'
            .'<h3>Creator</h3><p>Für Influencer: Profil mit Zahlen, Link-in-Bio-Seite, Empfehlungen mit Werbekennzeichnung, Drops mit Countdown, Mediakit.</p>'
            .'<h3>Klassisch</h3><p>Das schlichte Standard-Theme für Blogs und Magazine.</p>'),
        $page('demo','Demo-Anleitung','<h2>So testest du</h2><ol><li>Öffne die <a href="/cms/?demo=1">Verwaltung</a> (Anmeldung automatisch, sonst Benutzer <code>'.$u.'</code>, Passwort <code>'.$p.'</code>).</li><li>Baue die Startseite um: <em>Design → Homepage-Baukasten</em>.</li><li>Wechsle das Theme: <em>Design → Themes</em> – im Menü erscheinen die passenden Einstellungen.</li><li>Schreibe einen Beitrag und füge ein freies Bild aus der Mediathek ein.</li><li>Probiere den KI-Assistenten (mit eigenem Schlüssel) und die übrigen Menüs.</li></ol>'
            .'<h2>Zurücksetzen</h2><p>Alle '.$min.' Minuten stellt die Demo den Ausgangszustand wieder her – Beiträge, Seiten, Einstellungen, Benutzer und Passwörter. Ändere ruhig alles.</p>'
            .'<h2>Was in der Demo nicht geht</h2><p>Zum Schutz des gemeinsam genutzten Servers sind nur wenige Dinge gesperrt: das Installieren fremden Programmcodes (Plugin-/Theme-Upload und -Installation), externe Datenbank-Verbindungen, Mailversand sowie das Einspielen von Updates und Dritt-Dienste mit Zugangsdaten. In einer eigenen Installation ist alles verfügbar.</p>'),
        $page('selbst-betreiben','Selbst betreiben','<h2>Voraussetzungen</h2><p>PHP 8.1 oder neuer, Erweiterungen <code>curl</code>, <code>zip</code>, <code>mbstring</code>. Ein Build-Schritt ist nicht nötig; die Daten liegen in Dateien, optional zusätzlich in einer Datenbank.</p>'
            .'<h2>Installation</h2><ol><li>Paket von GitHub laden und auf den Webserver kopieren.</li><li>Die Website aufrufen – der Einrichtungsassistent legt Administrator und Website an.</li></ol>'
            .'<h2>Aktuell bleiben</h2><p>Unter <em>System → Version &amp; Update</em> zeigt das CMS immer die installierte und die neueste Version, sucht automatisch (Zeitplan, GitHub-Webhook oder Cron) und spielt Updates bei Bedarf automatisch ein – mit Sicherung vorher und automatischem Rückschritt bei Fehlern.</p>'
            .'<p><a class="btn" href="https://github.com/ricorewioriginal-collab/ElvadoPress">ElvadoPress auf GitHub</a></p>'),
    ];
}

/** Hauptmenü der Demo-Website (Rohformat für rrw_clean_section('menus')). */
function rrw_demo_menu(): array {
    $i=0;$m=function(string $label,string $target)use(&$i){ $i++;return ['id'=>'m'.$i,'label'=>$label,'target'=>$target,'enabled'=>true,'parent_id'=>'']; };
    return ['top'=>[$m('Start','system:start'),$m('Funktionen','page:funktionen'),$m('Themes','page:themes'),$m('Demo-Anleitung','page:demo'),$m('Selbst betreiben','page:selbst-betreiben'),$m('Verwaltung','/cms/?demo=1')],'bottom'=>[]];
}

/** Beispielbeiträge: [Titel, Kategorie, Auszug, HTML, Schlagwörter]. */
function rrw_demo_posts(): array {
    return [
        ['Neu: CMS-Update über GitHub mit Rückschritt','Entwicklung','Neue Versionen suchen, einspielen und bei Problemen automatisch zurückrollen.','<p>Unter <strong>System → Version &amp; Update</strong> zeigt ElvadoPress immer die installierte und die neueste Version. Updates kommen aus GitHub (Release, Beta oder Branch), werden vor dem Einspielen geprüft und gesichert – und wenn die Gesundheitsprüfung fehlschlägt, spielt das CMS die alte Version automatisch zurück.</p>','Updates, GitHub, Sicherheit'],
        ['Freie Bilder direkt in der Mediathek','Medien','Pixabay, Pexels, Unsplash, Openverse und Wikimedia Commons – mit Bildnachweis.','<p>Die Mediathek durchsucht fünf freie Bildquellen. Ein Klick übernimmt das Bild in deine Mediathek; Urheber, Quelle und Lizenz werden automatisch als Bildnachweis gespeichert und beim Einfügen als Bildunterschrift gesetzt.</p>','Medien, Bilder, Lizenzen'],
        ['Fünf Themes – und keines sieht aus wie das andere','Design','Baukasten, Radio, Band, Creator und Klassisch mit passenden Konfigurationsmenüs.','<p>Jedes mitgelieferte Theme hat einen eigenen Stil und bringt – nur solange es aktiv ist – sein eigenes Menü in der Verwaltung mit: Sender und Sendeplan für Radio, Tourdaten für Bands, Link-in-Bio und Drops für Creator.</p>','Themes, Baukasten, Design'],
        ['KI-Assistent: Texte, Übersetzungen, Layouts','KI','Mit deinem eigenen Schlüssel bei EvoLink, OpenAI, Anthropic, Google, OpenRouter oder DeepSeek.','<p>Der Assistent schreibt Beiträge, übersetzt Texte und schlägt Layouts für den Homepage-Baukasten vor. Schlüssel liegen nur auf dem Server und werden nie angezeigt.</p>','KI, Texte, Layouts'],
        ['Die Demo ist die Homepage','News','Diese Website ist selbst mit ElvadoPress und dem Homepage-Baukasten gebaut.','<p>Du siehst hier keine Attrappe, sondern ein laufendes ElvadoPress. Baue die Startseite unter <em>Design → Homepage-Baukasten</em> um – nach einigen Minuten ist wieder alles im Ausgangszustand.</p>','Demo, Baukasten, Homepage'],
    ];
}

/** Widgets der Seitenleiste (WordPress-Widget-Optionen) und ihre Reihenfolge: [ 'sidebars'=>[…ids], 'options'=>[ 'widget_text'=>[…], … ] ]. */
function rrw_demo_widgets(array $c): array {
    $u=htmlspecialchars($c['user'],ENT_QUOTES);$p=htmlspecialchars($c['password'],ENT_QUOTES);$min=(int)$c['minutes'];
    $text=fn(string $title,string $html)=>['title'=>$title,'text'=>$html,'filter'=>false];
    $texts=[
        1=>$text('','<p style="text-align:center;margin:0 0 8px"><img src="/cms/assets/brand/elvadopress-logo.png" alt="ElvadoPress" style="max-width:100%;height:auto"></p><p style="text-align:center;margin:0">Das erweiterbare CMS für Websites aller Art.</p>'),
        2=>$text('Live-Demo','<p>Nahezu alle Funktionen sind freigeschaltet. Nach <strong>'.$min.' Minuten</strong> wird alles zurückgesetzt.</p><p>Benutzer <code>'.$u.'</code><br>Passwort <code>'.$p.'</code></p><p><a class="btn" href="/cms/?demo=1">Verwaltung öffnen</a></p>'),
        3=>$text('Das kann ElvadoPress','<ul><li><a href="/funktionen/">Beiträge, Seiten, Menüs, Widgets</a></li><li><a href="/themes/">Fünf Themes + Baukasten</a></li><li>Freie Bilder in der Mediathek</li><li>KI-Assistent mit sechs Anbietern</li><li>Lovable &amp; GitHub-Sync</li><li>WordPress-Themes und -Plugins</li><li><a href="/selbst-betreiben/">Updates mit Rückschritt</a></li></ul>'),
        4=>$text('Mitmachen','<p>ElvadoPress ist freie Software (GPL-2.0-or-later).</p><p><a class="btn" href="https://github.com/ricorewioriginal-collab/ElvadoPress">Auf GitHub ansehen</a></p>'),
    ];
    $texts['_multiwidget']=1;
    return [
        'sidebars'=>['text-1','text-2','search-1','text-3','recent-posts-1','categories-1','tag_cloud-1','pages-1','custom_html-1','archives-1','text-4'],
        'options'=>[
            'widget_text'=>$texts,
            'widget_search'=>[1=>['title'=>'Suche'],'_multiwidget'=>1],
            'widget_recent-posts'=>[1=>['title'=>'Neueste Beiträge','number'=>5,'show_date'=>true],'_multiwidget'=>1],
            'widget_categories'=>[1=>['title'=>'Kategorien','count'=>true],'_multiwidget'=>1],
            'widget_tag_cloud'=>[1=>['title'=>'Schlagwörter'],'_multiwidget'=>1],
            'widget_pages'=>[1=>['title'=>'Seiten'],'_multiwidget'=>1],
            'widget_custom_html'=>[1=>['title'=>'Fünf Themes','content'=>'<p><span class="btn btn-ghost">Baukasten</span> <span class="btn btn-ghost">Radio</span> <span class="btn btn-ghost">Band</span> <span class="btn btn-ghost">Creator</span> <span class="btn btn-ghost">Klassisch</span></p><p>Wechsle unter <em>Design → Themes</em> – jedes Theme bringt sein eigenes Menü mit.</p>'],'_multiwidget'=>1],
            'widget_archives'=>[1=>['title'=>'Archiv','count'=>true],'_multiwidget'=>1],
        ],
    ];
}
