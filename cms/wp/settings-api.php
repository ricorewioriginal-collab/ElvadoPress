<?php
// WordPress-Einstellungen im CMS (Einstellungen → Allgemein, Schreiben, Lesen, Diskussion, Medien).
// Gespeichert werden ausschließlich WordPress-Optionen (get_option/update_option); Titel, Untertitel, E-Mail, Kommentar-Standard,
// Feed-Länge, Suchmaschinen-Sichtbarkeit, Zeitzone und Sprache laufen dabei über die Schreibbrücke ins CMS (siehe core/cms-write.php).
// Aufruf aus cms/api.php (wp_settings, wp_settings_save).

/** Felder je Bereich: type text|email|int|bool|enum, label, help, min/max (int), options (enum), default. */
function rrw_wps_schema(): array {
    $tz=['Europe/Berlin'=>'Berlin (Europa/Berlin)'];foreach(DateTimeZone::listIdentifiers() as $z)$tz[$z]=$z;
    $dates=['d.m.Y'=>'05.10.2026','j. F Y'=>'5. Oktober 2026','Y-m-d'=>'2026-10-05','D, d.m.Y'=>'Mo, 05.10.2026'];
    $times=['H:i'=>'14:30','H:i:s'=>'14:30:00','g:i a'=>'2:30 pm'];
    return [
        'general'=>['title'=>'Allgemein','fields'=>[
            'blogname'=>['type'=>'text','label'=>'Titel der Website','max'=>80],
            'blogdescription'=>['type'=>'text','label'=>'Untertitel','help'=>'Eine kurze Beschreibung, worum es auf der Website geht.','max'=>200],
            'admin_email'=>['type'=>'email','label'=>'Administrator-E-Mail-Adresse','help'=>'Für Benachrichtigungen und Wiederherstellung.','max'=>200],
            'WPLANG'=>['type'=>'enum','label'=>'Sprache der Website','options'=>['de_DE'=>'Deutsch','en_US'=>'English (US)','en_GB'=>'English (UK)','fr_FR'=>'Français','es_ES'=>'Español','it_IT'=>'Italiano','nl_NL'=>'Nederlands','pl_PL'=>'Polski','tr_TR'=>'Türkçe']],
            'timezone_string'=>['type'=>'enum','label'=>'Zeitzone','options'=>$tz],
            'date_format'=>['type'=>'enum','label'=>'Datumsformat','options'=>$dates],
            'time_format'=>['type'=>'enum','label'=>'Zeitformat','options'=>$times],
            'start_of_week'=>['type'=>'enum','label'=>'Woche beginnt am','options'=>['1'=>'Montag','2'=>'Dienstag','3'=>'Mittwoch','4'=>'Donnerstag','5'=>'Freitag','6'=>'Samstag','0'=>'Sonntag']],
        ]],
        'writing'=>['title'=>'Schreiben','fields'=>[
            'rrw_default_category'=>['type'=>'text','label'=>'Standardkategorie','help'=>'Für Beiträge, die ohne Kategorie angelegt werden (z. B. Import, Schnellentwurf).','max'=>80,'default'=>'News'],
            'rrw_default_status'=>['type'=>'enum','label'=>'Standardstatus neuer Beiträge','options'=>['draft'=>'Entwurf','published'=>'Veröffentlicht'],'default'=>'draft','help'=>'Gilt für Beiträge, die über Import oder Schnellentwurf entstehen.'],
        ]],
        'reading'=>['title'=>'Lesen','fields'=>[
            'posts_per_page'=>['type'=>'int','label'=>'Beiträge pro Seite','min'=>1,'max'=>100],
            'posts_per_rss'=>['type'=>'int','label'=>'Beiträge im Feed','min'=>5,'max'=>100],
            'blog_public'=>['type'=>'bool','label'=>'Suchmaschinen','check'=>'Suchmaschinen dürfen diese Website indexieren','help'=>'Ausgeschaltet bittet Suchmaschinen, die Website nicht zu indexieren.'],
        ]],
        'discussion'=>['title'=>'Diskussion','fields'=>[
            'default_comment_status'=>['type'=>'bool','label'=>'Standard für neue Beiträge','check'=>'Kommentare auf neuen Beiträgen erlauben','on'=>'open','off'=>'closed'],
            'comment_moderation'=>['type'=>'bool','label'=>'Vor dem Erscheinen','check'=>'Kommentar muss manuell freigegeben werden'],
            'comment_registration'=>['type'=>'bool','label'=>'Angemeldete Nutzer','check'=>'Nur angemeldete Nutzer dürfen kommentieren'],
            'thread_comments'=>['type'=>'bool','label'=>'Verschachtelte Kommentare','check'=>'Antworten auf Kommentare einrücken'],
            'comments_per_page'=>['type'=>'int','label'=>'Kommentare pro Seite','min'=>1,'max'=>200],
        ]],
        'media'=>['title'=>'Medien','fields'=>[
            'thumbnail_size_w'=>['type'=>'int','label'=>'Vorschaubild – Breite','min'=>0,'max'=>4000,'default'=>150],
            'thumbnail_size_h'=>['type'=>'int','label'=>'Vorschaubild – Höhe','min'=>0,'max'=>4000,'default'=>150],
            'medium_size_w'=>['type'=>'int','label'=>'Mittel – maximale Breite','min'=>0,'max'=>4000,'default'=>300],
            'medium_size_h'=>['type'=>'int','label'=>'Mittel – maximale Höhe','min'=>0,'max'=>4000,'default'=>300],
            'large_size_w'=>['type'=>'int','label'=>'Groß – maximale Breite','min'=>0,'max'=>8000,'default'=>1024],
            'large_size_h'=>['type'=>'int','label'=>'Groß – maximale Höhe','min'=>0,'max'=>8000,'default'=>1024],
        ]],
    ];
}

/** Optionen, die in den CMS-Daten liegen (site.json / Betrieb), kommen von dort – alle anderen aus der WordPress-Ablage. */
function rrw_wps_bridged(string $name): bool { return isset(RRW_WP_BRIDGE_OPTS[$name])||isset(RRW_WP_BRIDGE_TYPED[$name])||isset(RRW_WP_BRIDGE_SYS[$name]); }
function rrw_wps_read(string $name, $def) {
    if(rrw_wps_bridged($name)){
        $site=rrw_wp_cms_data()['site']??[];
        if(isset(RRW_WP_BRIDGE_OPTS[$name])){ [$g,$k]=RRW_WP_BRIDGE_OPTS[$name];return (string)($site[$g][$k]??''); }
        if(isset(RRW_WP_BRIDGE_TYPED[$name]))return rrw_wp_bridge_typed_get($name,$site);
        $v=rrw_wp_bridge_sys_read($name);return $v!==''?$v:($name==='WPLANG'?'de_DE':'Europe/Berlin');
    }
    return get_option($name,$def);
}

/** Wert eines Feldes im Klartext (bool als 0/1). */
function rrw_wps_value(string $name, array $f) {
    $def=$f['default']??false;$v=rrw_wps_read($name,$def);
    switch($f['type']){
        case 'bool': return isset($f['on'])?($v===$f['on']?1:0):((int)$v?1:0);
        case 'int': return (int)$v;
        case 'enum': $v=(string)$v;if($name==='start_of_week')$v=(string)(int)$v;return $v!==''?$v:(string)($def!==false?$def:array_key_first($f['options']));
        default: return (string)$v;
    }
}

function rrw_wps_get(string $group): ?array {
    $s=rrw_wps_schema();if(!isset($s[$group]))return null;$out=[];
    foreach($s[$group]['fields'] as $name=>$f){
        $row=['name'=>$name,'type'=>$f['type'],'label'=>$f['label'],'value'=>rrw_wps_value($name,$f)];
        foreach(['help','check','min','max'] as $k)if(isset($f[$k]))$row[$k]=$f[$k];
        if(isset($f['options'])){ $o=[];foreach($f['options'] as $k=>$l)$o[]=['value'=>(string)$k,'label'=>(string)$l];$row['options']=$o; }
        if($f['type']==='enum'&&isset($f['options'])&&!isset($f['options'][$row['value']])&&$row['value']!==''){ $row['options'][]=['value'=>(string)$row['value'],'label'=>(string)$row['value']]; }
        $out[]=$row;
    }
    return ['group'=>$group,'title'=>$s[$group]['title'],'fields'=>$out];
}

/** Speichert die übergebenen Felder des Bereichs (unbekannte Felder werden ignoriert). Rückgabe ['ok'=>bool,'message'=>…]. */
function rrw_wps_save(string $group, array $in): array {
    $s=rrw_wps_schema();if(!isset($s[$group]))return ['ok'=>false,'message'=>'Unbekannter Einstellungsbereich.'];
    $set=[];
    foreach($s[$group]['fields'] as $name=>$f){
        if(!array_key_exists($name,$in))continue;$v=$in[$name];
        switch($f['type']){
            case 'text':
                $v=trim(preg_replace('/\s+/u',' ',strip_tags((string)$v)));
                if(mb_strlen($v)>($f['max']??200))return ['ok'=>false,'message'=>$f['label'].': zu lang.'];
                if($name==='blogname'&&$v==='')return ['ok'=>false,'message'=>'Der Titel der Website darf nicht leer sein.'];
                if($v===''&&isset($f['default']))$v=(string)$f['default'];
                break;
            case 'email':
                $v=trim((string)$v);
                if($v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))return ['ok'=>false,'message'=>'Bitte eine gültige E-Mail-Adresse eingeben.'];
                break;
            case 'int':
                if(!is_int($v)&&!preg_match('/^-?\d{1,9}$/',(string)$v))return ['ok'=>false,'message'=>$f['label'].': bitte eine ganze Zahl eingeben.'];
                $v=(int)$v;if($v<$f['min']||$v>$f['max'])return ['ok'=>false,'message'=>$f['label'].': erlaubt sind '.$f['min'].' bis '.$f['max'].'.'];
                break;
            case 'bool':
                $b=$v===true||$v===1||$v==='1'||$v==='true'||$v==='on';$v=isset($f['on'])?($b?$f['on']:$f['off']):($b?1:0);
                break;
            case 'enum':
                $v=(string)$v;if(!isset($f['options'][$v]))return ['ok'=>false,'message'=>$f['label'].': ungültige Auswahl.'];
                if($name==='start_of_week')$v=(int)$v;
                break;
        }
        $set[$name]=$v;
    }
    foreach($set as $k=>$v){
        if(rrw_wps_bridged($k)){ if(!rrw_wp_bridge_option_write($k,$v))return ['ok'=>false,'message'=>'„'.$k.'“ konnte nicht gespeichert werden.']; }
        else update_option($k,$v);
    }
    return ['ok'=>true]+(rrw_wps_get($group)??[]);
}

/** Standardwerte für Beiträge ohne Kategorie bzw. Status – liest die Option direkt (auch ohne gestartete WordPress-Schicht). */
function rrw_wps_default(string $name, string $fallback, ?string $optionsFile=null): string {
    $file=$optionsFile??(defined('RRW_WP_DATA')?RRW_WP_DATA:__DIR__.'/../data/.wp').'/options.json';
    $all=is_file($file)?json_decode((string)@file_get_contents($file),true):null;
    if(is_array($all)&&isset($all[$name]['v'])){ $v=@unserialize((string)$all[$name]['v']);if(is_string($v)&&$v!=='')return $v; }
    return $fallback;
}
