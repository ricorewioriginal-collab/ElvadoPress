<?php
declare(strict_types=1);
// Creator-/Influencer-Konfiguration für das Theme „ElvadoPress Creator“ (cms/themes/elvado-creator): Profil, Zahlen, Plattformen, Link-in-Bio,
// Highlights, Feed, Videos, Empfehlungen (mit Rabattcodes und Werbekennzeichnung), Drops, Kooperationen, Mediakit, FAQ, Kontakt, Anzeige.
// Schema → Menü „Creator“ (nur bei aktivem Theme), Editor und Bereinigung erzeugt cms/lib/themeconf.php. Neutral: keine Marken- oder Beispieldaten.
const ELVADO_CREATOR_THEME='elvado-creator';
const ELVADO_CREATOR_PLATFORMS=['instagram'=>'Instagram','tiktok'=>'TikTok','youtube'=>'YouTube','twitch'=>'Twitch','x'=>'X','threads'=>'Threads','pinterest'=>'Pinterest','snapchat'=>'Snapchat','linkedin'=>'LinkedIn','facebook'=>'Facebook','podcast'=>'Podcast','newsletter'=>'Newsletter','website'=>'Website','other'=>'Sonstiges'];
const ELVADO_CREATOR_ICONS=['link'=>'Link','star'=>'Stern','shop'=>'Shop','cart'=>'Warenkorb','video'=>'Video','music'=>'Musik','mail'=>'E-Mail','download'=>'Download','calendar'=>'Termin','heart'=>'Herz'];
function elvado_creator_registration(): array {
    return ['theme'=>ELVADO_CREATOR_THEME,'title'=>'Creator-Auftritt','menu'=>'Creator','icon'=>'fa-wand-magic-sparkles','schema'=>'elvado_creator_schema',
        'hint'=>'Alles für deinen Auftritt als Creator: Profil, Zahlen, Plattformen, Link-in-Bio, Highlights, Feed, Videos, Empfehlungen mit Rabattcodes, Drops, Kooperationen, Mediakit, FAQ und Kontakt. Die Startseite und die Link-Seite des Themes „ElvadoPress Creator“ bauen sich daraus auf.'];
}
function elvado_creator_schema(): array {
    $t=fn($k,$l,$max=120,$extra=[])=>['k'=>$k,'label'=>$l,'type'=>'text','max'=>$max]+$extra;
    $u=fn($k,$l,$extra=[])=>['k'=>$k,'label'=>$l,'type'=>'url']+$extra;
    $img=fn($k,$l,$extra=[])=>['k'=>$k,'label'=>$l,'type'=>'image']+$extra;
    $on=fn($k,$l,$d=true)=>['k'=>$k,'label'=>$l,'type'=>'checkbox','default'=>$d];
    $plat=['k'=>'platform','label'=>'Plattform','type'=>'select','options'=>ELVADO_CREATOR_PLATFORMS,'default'=>'instagram'];
    return [
        ['id'=>'profile','title'=>'Profil','icon'=>'fa-user','kind'=>'form','hint'=>'Name, Kurzvorstellung und Bild erscheinen im Kopfbereich, auf der Link-Seite und in den Suchmaschinen-Daten.','fields'=>[
            $t('name','Name / Künstlername',80),$t('handle','Benutzername (z. B. @name)',40),$t('tagline','Slogan (eine Zeile)',160,['wide'=>true]),['k'=>'bio','label'=>'Über mich (Absätze mit Leerzeile)','type'=>'textarea','max'=>1200,'wide'=>true],
            $t('niches','Themen (mit Komma trennen, z. B. Reisen, Mode, Fitness)',160,['wide'=>true]),$t('location','Ort',80),$img('avatar','Profilbild (quadratisch)'),$img('cover','Titelbild (breit, optional)'),
            $t('cta_label','Haupt-Knopf: Text (z. B. „Zusammenarbeit anfragen“)',40),$u('cta_url','Haupt-Knopf: Ziel (leer = Kontakt-Abschnitt)')]],
        ['id'=>'stats','title'=>'Zahlen','icon'=>'fa-chart-simple','kind'=>'list','max'=>8,'required'=>'value','item_label'=>'label','hint'=>'Kennzahlen als Text, z. B. „1,2 Mio.“ / „Follower“. Tipp: nur Zahlen, die du belegen kannst.','fields'=>[$t('value','Wert',20),$t('label','Beschriftung',40)]],
        ['id'=>'platforms','title'=>'Plattformen','icon'=>'fa-share-nodes','kind'=>'list','max'=>14,'required'=>'url','item_label'=>'handle','fields'=>[$plat,$t('handle','Benutzername',60),$t('followers','Reichweite (z. B. 250K)',20),$u('url','Profil-Adresse')]],
        ['id'=>'links','title'=>'Link-in-Bio','icon'=>'fa-link','kind'=>'list','max'=>30,'required'=>'url','item_label'=>'title','hint'=>'Die Knopfliste der Seite „Links“ (Adresse /links/) und des Abschnitts „Meine Links“. Hervorgehobene Links stehen groß oben; Zeitfenster blenden Aktionen automatisch ein und aus.','fields'=>[
            $t('title','Titel',80),$t('subtitle','Untertitel',120),$u('url','Ziel-Adresse'),['k'=>'icon','label'=>'Symbol','type'=>'select','options'=>ELVADO_CREATOR_ICONS,'default'=>'link'],$t('badge','Etikett (z. B. NEU, −20 %)',20),
            $on('highlight','Hervorheben (groß)',false),$on('sponsored','Werbung / Affiliate (wird gekennzeichnet)',false),['k'=>'from','label'=>'Sichtbar ab','type'=>'date'],['k'=>'until','label'=>'Sichtbar bis','type'=>'date']]],
        ['id'=>'highlights','title'=>'Highlights','icon'=>'fa-circle-dot','kind'=>'list','max'=>12,'required'=>'image','item_label'=>'title','hint'=>'Runde Story-Bilder wie bei Instagram – jedes führt zu einem Ziel.','fields'=>[$t('title','Titel',24),$img('image','Bild'),$u('url','Ziel-Adresse')]],
        ['id'=>'feed','title'=>'Feed','icon'=>'fa-images','kind'=>'list','max'=>18,'required'=>'image','item_label'=>'caption','hint'=>'Handverlesene Beiträge als Bilderraster; ein Klick öffnet den Beitrag auf der Plattform (keine Einbettung, kein Tracking). Bilder auch aus „Freie Bilder“.','fields'=>[
            $img('image','Bild'),$u('url','Beitrags-Adresse'),['k'=>'kind','label'=>'Art','type'=>'select','options'=>['photo'=>'Foto','video'=>'Video','reel'=>'Reel / Short'],'default'=>'photo'],$plat,$t('caption','Bildunterschrift',120,['wide'=>true])]],
        ['id'=>'videos','title'=>'Videos','icon'=>'fa-circle-play','kind'=>'list','max'=>12,'required'=>'url','item_label'=>'title','hint'=>'YouTube- oder Vimeo-Links. Der Player lädt erst nach Klick.','fields'=>[$t('title','Titel',120),$u('url','Video-Link')]],
        ['id'=>'favorites','title'=>'Empfehlungen','icon'=>'fa-bag-shopping','kind'=>'list','max'=>24,'required'=>'title','item_label'=>'title','hint'=>'Produkte, die du empfiehlst – mit Rabattcode. Als Werbung markierte Einträge bekommen automatisch das Etikett „Anzeige“ und rel="sponsored".','fields'=>[
            $t('title','Produkt',100),$t('brand','Marke',60),$img('image','Bild'),$u('url','Link (ggf. Affiliate)'),$t('code','Rabattcode',30),$t('discount','Vorteil (z. B. −15 %)',40),$t('category','Kategorie',40),$on('sponsored','Werbung / Affiliate',true),$t('note','Warum ich es mag',160,['wide'=>true])]],
        ['id'=>'drops','title'=>'Drops & Termine','icon'=>'fa-calendar-days','kind'=>'list','max'=>20,'required'=>'date','item_label'=>'title','hint'=>'Launches, Livestreams, Events. Der nächste Termin erscheint mit Countdown.','fields'=>[$t('title','Titel',100),['k'=>'date','label'=>'Datum','type'=>'date'],['k'=>'time','label'=>'Uhrzeit','type'=>'time'],$u('url','Link (Erinnerung, Livestream, Shop)'),['k'=>'description','label'=>'Beschreibung','type'=>'textarea','max'=>300,'wide'=>true]]],
        ['id'=>'collabs','title'=>'Kooperationen','icon'=>'fa-handshake','kind'=>'list','max'=>24,'required'=>'name','item_label'=>'name','hint'=>'Marken, mit denen du gearbeitet hast (nur mit deren Einverständnis).','fields'=>[$t('name','Marke',60),$img('logo','Logo (optional)'),$u('url','Website')]],
        ['id'=>'mediakit','title'=>'Mediakit','icon'=>'fa-file-lines','kind'=>'form','hint'=>'Für Marken und Agenturen: Zielgruppe in Zahlen und Download.','fields'=>[
            $t('heading','Überschrift',80),['k'=>'intro','label'=>'Einleitung','type'=>'textarea','max'=>800,'wide'=>true],['k'=>'audience','label'=>'Zielgruppe – eine Zeile je Wert, z. B. „Alter 18–34 · 68 %“','type'=>'textarea','max'=>800,'wide'=>true],$u('download_url','Mediakit-Download (PDF)')]],
        ['id'=>'packages','title'=>'Pakete','icon'=>'fa-box','kind'=>'list','max'=>8,'required'=>'title','item_label'=>'title','hint'=>'Optional: Kooperationspakete mit Preisangabe („ab 500 €“ oder „auf Anfrage“).','fields'=>[$t('title','Paket',80),$t('price','Preis',40),['k'=>'description','label'=>'Leistungen','type'=>'textarea','max'=>400,'wide'=>true]]],
        ['id'=>'faq','title'=>'Fragen & Antworten','icon'=>'fa-circle-question','kind'=>'list','max'=>20,'required'=>'q','item_label'=>'q','fields'=>[$t('q','Frage',160,['wide'=>true]),['k'=>'a','label'=>'Antwort','type'=>'textarea','max'=>800,'wide'=>true]]],
        ['id'=>'contact','title'=>'Kontakt','icon'=>'fa-envelope','kind'=>'form','fields'=>[
            $t('heading','Überschrift',80),['k'=>'text','label'=>'Text','type'=>'textarea','max'=>800,'wide'=>true],['k'=>'email','label'=>'Geschäftliche E-Mail','type'=>'email'],$t('agency','Agentur / Management',120),
            ['k'=>'disclosure','label'=>'Hinweis zu Werbung und Affiliate-Links (erscheint bei Empfehlungen und Links)','type'=>'textarea','max'=>400,'wide'=>true,'default'=>'Enthält Werbung und Affiliate-Links: Wenn du über diese Links kaufst, erhalte ich ggf. eine Provision. Für dich ändert sich am Preis nichts.']]],
        ['id'=>'display','title'=>'Anzeige','icon'=>'fa-sliders','kind'=>'form','hint'=>'Welche Abschnitte die Startseite zeigt. Abschnitte ohne Inhalt blendet das Theme von selbst aus.','fields'=>[
            $on('show_highlights','Highlights'),$on('show_links','Meine Links'),$on('show_feed','Feed'),$on('show_videos','Videos'),$on('show_favorites','Empfehlungen'),$on('show_drops','Drops & Termine'),$on('show_collabs','Kooperationen'),
            $on('show_mediakit','Mediakit & Pakete'),$on('show_faq','Fragen & Antworten'),$on('show_news','News / Blog'),$on('show_newsletter','Newsletter (Shortcode [newsletter])'),$on('show_contact','Kontakt'),$on('mobile_nav','Navigationsleiste am unteren Rand (Handy)')]],
    ];
}
