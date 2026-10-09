<?php
declare(strict_types=1);
// Band-/Musiker-Konfiguration für das Theme „ElvadoPress Band“ (cms/themes/elvado-band): Auftritt, Konzerte, Veröffentlichungen,
// Videos, Mitglieder, Galerie, Booking, Anzeige. Schema → Menü „Band“ (erscheint nur bei aktivem Theme), Editor und Bereinigung
// erzeugt cms/lib/themeconf.php. Neutral: keine Marken- oder Beispieldaten.
const ELVADO_BAND_THEME='elvado-band';
const ELVADO_BAND_LINK_TYPES=['spotify'=>'Spotify','apple'=>'Apple Music','youtube'=>'YouTube','bandcamp'=>'Bandcamp','soundcloud'=>'SoundCloud','instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','website'=>'Website','other'=>'Sonstiges'];
const ELVADO_BAND_SHOW_STATUS=['onsale'=>'Tickets im Vorverkauf','soldout'=>'Ausverkauft','free'=>'Eintritt frei','announced'=>'Vorverkauf folgt','cancelled'=>'Abgesagt'];
function elvado_band_registration(): array {
    return ['theme'=>ELVADO_BAND_THEME,'title'=>'Band & Musik','menu'=>'Band','icon'=>'fa-guitar','schema'=>'elvado_band_schema',
        'hint'=>'Alles für den Auftritt deiner Band oder deines Projekts: Texte, Konzerttermine, Veröffentlichungen, Videos, Mitglieder, Galerie und Booking. Die Startseite des Themes „ElvadoPress Band“ baut sich daraus auf.'];
}
function elvado_band_schema(): array {
    $t=fn($k,$l,$max=200,$extra=[])=>['k'=>$k,'label'=>$l,'type'=>'text','max'=>$max]+$extra;
    $show=fn($k,$l)=>['k'=>$k,'label'=>$l,'type'=>'checkbox','default'=>true];
    return [
        ['id'=>'band','title'=>'Auftritt','icon'=>'fa-star','kind'=>'form','hint'=>'Name, Slogan und Biografie erscheinen im Kopfbereich und im Abschnitt „Über uns“.','fields'=>[
            $t('name','Name der Band / des Künstlers',80),$t('tagline','Slogan (eine Zeile)',160),$t('genre','Genre',80),$t('origin','Herkunft (Stadt/Land)',80),
            ['k'=>'hero_image','label'=>'Titelbild (Querformat, ab ca. 1600 px)','type'=>'image'],['k'=>'press_photo','label'=>'Pressefoto (Hochformat oder quadratisch)','type'=>'image'],
            ['k'=>'bio','label'=>'Biografie (Absätze mit Leerzeile)','type'=>'textarea','max'=>4000,'wide'=>true]]],
        ['id'=>'shows','title'=>'Konzerte','icon'=>'fa-ticket','kind'=>'list','max'=>200,'required'=>'date','item_label'=>'venue','hint'=>'Vergangene Termine blendet das Theme automatisch aus (oder zeigt sie als Archiv, siehe „Anzeige“). Die Termine erscheinen auch als strukturierte Daten für Suchmaschinen.','fields'=>[
            ['k'=>'date','label'=>'Datum','type'=>'date'],['k'=>'time','label'=>'Beginn','type'=>'time'],$t('venue','Veranstaltungsort / Club',120),$t('city','Stadt',80),
            ['k'=>'status','label'=>'Status','type'=>'select','options'=>ELVADO_BAND_SHOW_STATUS,'default'=>'onsale'],['k'=>'ticket_url','label'=>'Ticket-Link','type'=>'url'],$t('note','Hinweis (z. B. „mit Support: …“)',160,['wide'=>true])]],
        ['id'=>'releases','title'=>'Veröffentlichungen','icon'=>'fa-compact-disc','kind'=>'list','max'=>40,'required'=>'title','item_label'=>'title','hint'=>'Alben, EPs, Singles. Mit einem YouTube- oder Spotify-Link im Feld „Player“ erscheint ein Player, der erst nach Klick lädt (kein Tracking vorher).','fields'=>[
            $t('title','Titel',120),['k'=>'type','label'=>'Art','type'=>'select','options'=>['album'=>'Album','ep'=>'EP','single'=>'Single','live'=>'Live-Album'],'default'=>'album'],['k'=>'date','label'=>'Erscheinungsdatum','type'=>'date'],['k'=>'cover','label'=>'Cover (quadratisch)','type'=>'image'],
            ['k'=>'description','label'=>'Beschreibung','type'=>'textarea','max'=>600,'wide'=>true],
            ['k'=>'spotify','label'=>'Spotify','type'=>'url'],['k'=>'apple','label'=>'Apple Music','type'=>'url'],['k'=>'bandcamp','label'=>'Bandcamp','type'=>'url'],['k'=>'youtube','label'=>'YouTube','type'=>'url'],
            ['k'=>'embed','label'=>'Player (YouTube- oder Spotify-Link)','type'=>'url','wide'=>true]]],
        ['id'=>'videos','title'=>'Videos','icon'=>'fa-circle-play','kind'=>'list','max'=>20,'required'=>'url','item_label'=>'title','hint'=>'YouTube- oder Vimeo-Links. Der Player lädt erst nach Klick.','fields'=>[$t('title','Titel',120),['k'=>'url','label'=>'Video-Link','type'=>'url']]],
        ['id'=>'members','title'=>'Mitglieder','icon'=>'fa-users','kind'=>'list','max'=>20,'required'=>'name','item_label'=>'name','fields'=>[
            $t('name','Name',80),$t('role','Rolle / Instrument',80),['k'=>'photo','label'=>'Foto','type'=>'image'],['k'=>'bio','label'=>'Kurztext','type'=>'textarea','max'=>500]]],
        ['id'=>'gallery','title'=>'Galerie','icon'=>'fa-images','kind'=>'list','max'=>40,'required'=>'image','item_label'=>'caption','fields'=>[['k'=>'image','label'=>'Bild','type'=>'image'],$t('caption','Bildunterschrift',120)]],
        ['id'=>'links','title'=>'Links & Social','icon'=>'fa-link','kind'=>'list','max'=>16,'required'=>'url','item_label'=>'label','hint'=>'Streaming-Dienste und Social-Media – erscheinen im Kopf, im Fuß und als Verknüpfungen für Suchmaschinen.','fields'=>[
            ['k'=>'type','label'=>'Dienst','type'=>'select','options'=>ELVADO_BAND_LINK_TYPES,'default'=>'spotify'],$t('label','Beschriftung (leer = Dienstname)',40),['k'=>'url','label'=>'Adresse','type'=>'url']]],
        ['id'=>'booking','title'=>'Booking & Presse','icon'=>'fa-envelope','kind'=>'form','fields'=>[
            $t('heading','Überschrift',80),['k'=>'text','label'=>'Text','type'=>'textarea','max'=>1000,'wide'=>true],['k'=>'email','label'=>'Booking-E-Mail','type'=>'email'],$t('phone','Telefon',40),$t('agency','Agentur / Management',120),
            ['k'=>'presskit_url','label'=>'Presskit (Download-Link)','type'=>'url'],['k'=>'rider_url','label'=>'Technical Rider (Download-Link)','type'=>'url']]],
        ['id'=>'display','title'=>'Anzeige','icon'=>'fa-sliders','kind'=>'form','hint'=>'Welche Abschnitte die Startseite zeigt. Abschnitte ohne Inhalt blendet das Theme von selbst aus.','fields'=>[
            $show('show_shows','Konzerte'),$show('show_music','Musik'),$show('show_videos','Videos'),$show('show_band','Über uns / Mitglieder'),$show('show_gallery','Galerie'),$show('show_news','News'),$show('show_booking','Booking & Presse'),$show('show_newsletter','Newsletter (Shortcode [newsletter])'),
            ['k'=>'past_shows','label'=>'Vergangene Konzerte als Archiv anzeigen','type'=>'checkbox','default'=>false]]],
    ];
}
