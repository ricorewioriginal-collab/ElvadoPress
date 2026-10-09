<?php
// Schreibbrücke zwischen der WordPress-Schicht und den CMS-Inhalten (Stufe 12: Einheitliche Inhalte).
// Mit eingeschalteter Brücke schreiben wp_insert_post/wp_update_post/wp_trash_post/wp_delete_post, update_option, set_post_thumbnail,
// wp_set_post_terms und wp_update_nav_menu_item in die CMS-Dateien (news.json, site.json) – über dieselben Wege wie das CMS selbst:
// Sperrdatei .site.lock, atomares Schreiben, Beitrags-Revisionen, Seiten-Verlauf, Aktivitätsprotokoll, elvado_publish().
// Geändert werden nur die betroffenen Felder; alle übrigen (auch unbekannte Zusatzfelder) bleiben unverändert.
// Standard: AUS. Einschalten pro Bereich über die WordPress-Option „elvado_cms_bridge“ (Liste aus options, posts, pages, media, menus).

const ELVADO_WP_BRIDGE_PARTS=['options','posts','pages','media','menus'];
/** WordPress-Option → [CMS-Bereich, Feld, Höchstlänge] in site.json */
const ELVADO_WP_BRIDGE_OPTS=['blogname'=>['portal','site_name',80],'blogdescription'=>['portal','tagline',200],'admin_email'=>['legal','email',200]];

/** WordPress-Optionen mit eigener Umrechnung → site.json: Kommentare (comments.enabled / .require_approval), Feed-Länge (rss.max_items), Sichtbarkeit für Suchmaschinen (seo.robots) */
const ELVADO_WP_BRIDGE_TYPED=['default_comment_status'=>1,'comment_moderation'=>1,'posts_per_rss'=>1,'blog_public'=>1];
/** WordPress-/CMS-Wert → WordPress-Form (aus site.json gelesen). */
function elvado_wp_bridge_typed_get(string $option, array $site) {
    switch($option){
        case 'default_comment_status': return !empty($site['comments']['enabled'])?'open':'closed';
        case 'comment_moderation': return !array_key_exists('require_approval',(array)($site['comments']??[]))||!empty($site['comments']['require_approval'])?1:0;
        case 'posts_per_rss': return max(5,min(100,(int)($site['rss']['max_items']??50)));
        case 'blog_public': return str_starts_with(strtolower(trim((string)($site['seo']['robots']??'index,follow'))),'noindex')?0:1;
    }
    return null;
}
/** Normalisierter Wert oder null (ungültig). */
function elvado_wp_bridge_typed_norm(string $option, $value): ?string {
    $v=trim((string)(is_scalar($value)?$value:''));
    switch($option){
        case 'default_comment_status': return in_array($v,['open','closed'],true)?$v:null;
        case 'comment_moderation': case 'blog_public': return in_array($v,['0','1'],true)?$v:null;
        case 'posts_per_rss': return ctype_digit($v)&&(int)$v>=5&&(int)$v<=100?(string)(int)$v:null;
    }
    return null;
}
/** Wert in site.json setzen; true = etwas geändert. */
function elvado_wp_bridge_typed_apply(string $option, string $v, array &$site): bool {
    $old=(string)elvado_wp_bridge_typed_get($option,$site);
    switch($option){
        case 'default_comment_status': if(!is_array($site['comments']??null))$site['comments']=['require_approval'=>true];$site['comments']['enabled']=$v==='open';break;
        case 'comment_moderation': if(!is_array($site['comments']??null))$site['comments']=['enabled'=>false];$site['comments']['require_approval']=$v==='1';break;
        case 'posts_per_rss': if(!is_array($site['rss']??null))$site['rss']=[];$site['rss']['max_items']=(int)$v;break;
        case 'blog_public': if(!is_array($site['seo']??null))$site['seo']=[];if((string)elvado_wp_bridge_typed_get($option,$site)!==$v)$site['seo']['robots']=$v==='1'?'index,follow':'noindex,nofollow';break;
    }
    return (string)elvado_wp_bridge_typed_get($option,$site)!==$old;
}

/** WordPress-Option → Feld in system.local.json (Zeitzone, Sprache) */
const ELVADO_WP_BRIDGE_SYS=['timezone_string'=>'timezone','WPLANG'=>'language'];

function elvado_wp_bridge(string $part='posts'): bool {
    if(defined('ELVADO_WP_SANDBOX'))return false;   // Sandbox schreibt nie in CMS-Daten
    [$f,$v]=elvado_wp_opts_get_raw('elvado_cms_bridge');
    return $f&&is_array($v)&&in_array($part,$v,true);
}
function elvado_wp_bridge_parts(): array { [$f,$v]=elvado_wp_opts_get_raw('elvado_cms_bridge');return $f&&is_array($v)?array_values(array_intersect(ELVADO_WP_BRIDGE_PARTS,$v)):[]; }
function elvado_wp_bridge_set(array $parts): void { update_option('elvado_cms_bridge',array_values(array_intersect(ELVADO_WP_BRIDGE_PARTS,array_map('strval',$parts))),'yes'); }
/** Gehört die ID zu einem CMS-Inhalt, den die Brücke gerade schreiben darf? */
function elvado_wp_cms_bridged_id(int $id): bool {
    return (elvado_wp_cms_id_is_news($id)&&elvado_wp_bridge('posts'))||(elvado_wp_cms_id_is_page($id)&&elvado_wp_bridge('pages'));
}

/* ───────── Grundlagen: Bibliotheken, Pfade, Sperre, sicheres Lesen ───────── */
function elvado_wp_cms_lib(): void {
    static $ok=false;if($ok)return;
    $l=dirname(__DIR__,2).'/lib';foreach(['publish','feeds','seo','content','media'] as $f)require_once $l.'/'.$f.'.php';$ok=true;   // wie cms/rebuild.php
}
function elvado_wp_cms_root(): string { return defined('ELVADO_WP_CMS_ROOT')?rtrim((string)ELVADO_WP_CMS_ROOT,'/'):dirname(elvado_wp_cms_dir(),2); }
function elvado_wp_cms_actor(): array {
    $g=$GLOBALS['elvado_wp_user']??null;$role=(string)($g['role']??'administrator');
    return ['display_name'=>(string)($g['name']??'')!==''?(string)$g['name']:'WordPress','user'=>(string)($g['login']??''),'role'=>in_array($role,['author','autor'],true)?'autor':'admin'];
}
/** Führt $fn unter der Sperre des CMS aus (gleiche Datei wie elvado_site_lock in api.php); höchstens 5 Sekunden Wartezeit. */
function elvado_wp_cms_locked(callable $fn) {
    static $depth=0;if($depth>0)return $fn();
    $d=elvado_wp_cms_dir();if(!is_dir($d))@mkdir($d,0775,true);
    $h=@fopen($d.'/.site.lock','c');
    if($h){ $t=microtime(true);while(!@flock($h,LOCK_EX|LOCK_NB)){ if(microtime(true)-$t>5){@fclose($h);throw new RuntimeException('Die CMS-Daten sind gerade gesperrt. Bitte gleich noch einmal versuchen.');}usleep(40000); } }
    $depth++;
    try{ return $fn(); } finally { $depth--;if($h){@flock($h,LOCK_UN);@fclose($h);} }
}
/** JSON lesen; ist die Datei vorhanden, aber nicht lesbar, wird abgebrochen statt mit einer leeren Liste zu überschreiben. */
function elvado_wp_cms_json_strict(string $file, array $fallback): array {
    if(!is_file($file))return $fallback;
    $raw=(string)@file_get_contents($file);if(trim($raw)==='')return $fallback;
    $j=json_decode($raw,true);if(!is_array($j))throw new RuntimeException(basename($file).' ist nicht lesbar – es wurde nichts geschrieben.');
    return $j;
}
function elvado_wp_cms_log(string $action, string $summary): void {
    try{ elvado_log_activity(elvado_wp_cms_dir().'/activity-log.json',elvado_wp_cms_actor(),$action,$summary); }catch(Throwable $e){}
}
/** Nach jeder Änderung: Zwischenspeicher der Laufzeit verwerfen (CMS-Daten, Beiträge, Standard-Optionen). */
function elvado_wp_cms_after_write(?array $site=null): void {
    elvado_wp_cms_reset();elvado_wp_post_cache_clear();
    if($site!==null&&isset($GLOBALS['ELVADO_SITE']))$GLOBALS['ELVADO_SITE']=$site;
}
/**
 * news.json unter Sperre ändern. $fn(array &$news): ['ret'=>…, 'changed'=>bool, 'rev'=>[id,Vorher-Stand], 'log'=>[Aktion,Text]].
 * Wie news_save in api.php: erst Revision, dann atomar schreiben, RSS neu, Aktivitätsprotokoll.
 */
function elvado_wp_cms_news_mutate(callable $fn) {
    elvado_wp_cms_lib();
    return elvado_wp_cms_locked(function() use($fn){
        $d=elvado_wp_cms_dir();$news=elvado_wp_cms_json_strict($d.'/news.json',[]);
        $r=$fn($news);if(!is_array($r)||empty($r['changed']))return $r['ret']??null;
        if(!empty($r['rev']))elvado_news_save_revision($d.'/news-revisions.json',(int)$r['rev'][0],(array)$r['rev'][1]);
        elvado_write_atomic($d.'/news.json',json_encode(array_values($news),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        try{ $site=elvado_read_json($d.'/site.json',[]);if(!isset($site['rss']['enabled'])||!empty($site['rss']['enabled']))elvado_write_atomic(elvado_wp_cms_root().'/rss.xml',elvado_rss_xml($site)); }catch(Throwable $e){}
        if(!empty($r['log']))elvado_wp_cms_log($r['log'][0],$r['log'][1]);
        elvado_wp_cms_after_write();
        return $r['ret']??null;
    });
}
/**
 * site.json unter Sperre ändern (wie der Speichern-Weg in api.php: Standardwerte ergänzen, ändern, elvado_publish). Ohne Änderung wird nichts geschrieben.
 * $fn(array &$site): ['ret'=>…, 'log'=>[Aktion,Text], 'pages_before'=>Seitenliste für den Verlauf].
 */
function elvado_wp_cms_site_mutate(callable $fn) {
    elvado_wp_cms_lib();
    return elvado_wp_cms_locked(function() use($fn){
        $d=elvado_wp_cms_dir();$file=$d.'/site.json';
        $site=elvado_ensure_site_defaults(elvado_wp_cms_json_strict($file,[]));
        $before=$site;   // nur das Geänderte wird geschrieben; Vorgaben wie theme.active ergänzt das CMS selbst beim Speichern
        $r=$fn($site);
        if($site===$before)return is_array($r)?($r['ret']??null):null;
        if(is_array($r)&&isset($r['pages_before'])){ $a=elvado_wp_cms_actor();elvado_page_revisions_record($d,$r['pages_before'],$site['pages']??[],(string)$a['display_name']); }
        elvado_publish($site,$file,dirname($d).'/generated',elvado_wp_cms_root());
        if(is_array($r)&&!empty($r['log']))elvado_wp_cms_log($r['log'][0],$r['log'][1]);
        elvado_wp_cms_after_write($site);
        return is_array($r)?($r['ret']??null):null;
    });
}

/* ───────── Optionen: blogname, blogdescription, admin_email ↔ site.json ───────── */
function elvado_wp_bridge_option_value(string $option, $value): ?string {
    $v=trim(wp_strip_all_tags((string)(is_scalar($value)?$value:'')));
    if(isset(ELVADO_WP_BRIDGE_TYPED[$option]))return elvado_wp_bridge_typed_norm($option,$value);
    if(isset(ELVADO_WP_BRIDGE_SYS[$option])){
        if($option==='timezone_string')return $v===''||in_array($v,DateTimeZone::listIdentifiers(),true)?$v:null;
        return $v===''||preg_match('/^[a-z]{2}(_[A-Z]{2})?$/',$v)?$v:null;   // WPLANG: "de_DE"; leer = Standard
    }
    if($option==='admin_email'&&$v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))return null;
    return mb_substr($v,0,ELVADO_WP_BRIDGE_OPTS[$option][2]);
}
/** Zeitzone/Sprache aus system.local.json im WordPress-Format ('' = nicht gesetzt). */
function elvado_wp_bridge_sys_read(string $option): string {
    $l=dirname(__DIR__,2).'/lib/system.php';if(!is_file($l))return '';require_once $l;
    $c=elvado_system_config();$v=(string)($c[ELVADO_WP_BRIDGE_SYS[$option]]??'');
    if($option==='WPLANG'&&$v!==''&&!str_contains($v,'_'))$v=strtolower($v).'_'.($v==='en'?'US':strtoupper($v));   // "de" → "de_DE"
    return $v;
}
/** true = in site.json geschrieben, false = abgelehnt. Eine alte Fassung in der WordPress-Ablage wird dabei entfernt. */
function elvado_wp_bridge_option_write(string $option, $value): bool {
    $v=elvado_wp_bridge_option_value($option,$value);if($v===null)return false;
    if(isset(ELVADO_WP_BRIDGE_SYS[$option])){
        try{
            require_once dirname(__DIR__,2).'/lib/system.php';elvado_system_save([ELVADO_WP_BRIDGE_SYS[$option]=>$v]);
            elvado_wp_cms_log('settings_update','Einstellung „'.$option.'“ über WordPress geändert (Betrieb)');
        }catch(Throwable $e){ elvado_wp_log('Schreibbrücke '.$option.': '.$e->getMessage());return false; }
        [$legacy]=elvado_wp_opts_get_raw($option);if($legacy)elvado_wp_opts_mutate(function(&$all) use($option){ unset($all[$option]); });
        return true;
    }
    if(isset(ELVADO_WP_BRIDGE_TYPED[$option])){
        try{
            elvado_wp_cms_site_mutate(function(array &$site) use($option,$v){
                if(!elvado_wp_bridge_typed_apply($option,$v,$site))return null;
                return ['log'=>['settings_update','Einstellung „'.$option.'“ über WordPress geändert']];
            });
        }catch(Throwable $e){ elvado_wp_log('Schreibbrücke '.$option.': '.$e->getMessage());return false; }
        [$legacy]=elvado_wp_opts_get_raw($option);if($legacy)elvado_wp_opts_mutate(function(&$all) use($option){ unset($all[$option]); });
        return true;
    }
    [$sec,$key]=ELVADO_WP_BRIDGE_OPTS[$option];
    try{
        elvado_wp_cms_site_mutate(function(array &$site) use($sec,$key,$v,$option){
            if(!is_array($site[$sec]??null))$site[$sec]=[];
            if(($site[$sec][$key]??null)===$v)return null;
            $site[$sec][$key]=$v;
            return ['log'=>['settings_update','Einstellung „'.$option.'“ über WordPress geändert ('.$sec.'.'.$key.')']];
        });
    }catch(Throwable $e){ elvado_wp_log('Schreibbrücke '.$option.': '.$e->getMessage());return false; }
    [$legacy]=elvado_wp_opts_get_raw($option);if($legacy)elvado_wp_opts_mutate(function(&$all) use($option){ unset($all[$option]); });
    return true;
}

/* ───────── Hilfen für Beiträge und Seiten ───────── */
/** HTML aus WordPress: mit Recht „unfiltered_html“ unverändert, sonst wie im CMS bereinigt (elvado_safe_html). null = zu lang. */
function elvado_wp_cms_filter_html(string $html, int $max=50000): ?string {
    if(current_user_can('unfiltered_html'))return strlen($html)>500000?null:$html;
    $s=elvado_safe_html($html);return strlen($html)>$max&&strlen($s)>=$max?null:$s;
}
function elvado_wp_cms_text(string $s, int $max): string { return mb_substr(trim(htmlspecialchars_decode(wp_strip_all_tags($s),ENT_QUOTES)),0,$max); }
function elvado_wp_cms_term_names($terms, string $taxonomy): array {
    $out=[];foreach(is_array($terms)?$terms:array_map('trim',explode(',',(string)$terms)) as $t){
        if(is_numeric($t)){ $x=(int)$t>0?get_term((int)$t,$taxonomy):null;if($x&&!is_wp_error($x))$out[]=(string)$x->name; }
        else{ $n=trim((string)$t);if($n!=='')$out[]=$n; }
    }
    return array_values(array_unique(array_map(fn($n)=>trim(str_replace(',',' ',$n)),$out)));
}
function elvado_wp_cms_unique_slug(array $taken, string $slug, string $fallback): string {
    $slug=elvado_slug($slug!==''?$slug:$fallback);$base=substr($slug,0,60);$n=2;
    while(isset($taken[$slug]))$slug=$base.'-'.$n++;
    return $slug;
}
function elvado_wp_cms_fire_save_hooks(int $id, bool $update, ?WP_Post $before, string $type): void {
    $post=get_post($id);if(!$post)return;$old=$before?$before->post_status:'new';
    if($old!==$post->post_status)wp_transition_post_status($post->post_status,$old,$post);
    do_action("edit_post_{$type}",$id,$post);do_action('edit_post',$id,$post);
    if($update)do_action('post_updated',$id,$post,$before);
    do_action("save_post_{$type}",$id,$post,$update);do_action('save_post',$id,$post,$update);do_action('wp_insert_post',$id,$post,$update);
}

/* ───────── Beiträge ↔ news.json ───────── */
/** Einstieg aus wp_insert_post: null = nicht zuständig (normaler Weg über die Datenbank). */
function elvado_wp_cms_try_insert(array $in, bool $wp_error) {
    $id=(int)($in['ID']??0);$type=(string)($in['post_type']??'post');
    if($id>0){
        if(elvado_wp_cms_id_is_news($id)&&elvado_wp_bridge('posts'))return elvado_wp_cms_news_save($in,true,$wp_error);
        if(elvado_wp_cms_id_is_page($id)&&elvado_wp_bridge('pages'))return elvado_wp_cms_page_save($in,$wp_error);
        return null;
    }
    $st=(string)($in['post_status']??'draft');
    if($type==='post'&&elvado_wp_bridge('posts')&&!in_array($st,['auto-draft','inherit'],true))return elvado_wp_cms_news_save($in,false,$wp_error);
    return null;
}
function elvado_wp_cms_news_save(array $in, bool $update, bool $wp_error) {
    $fail=fn(string $c,string $m)=>$wp_error?new WP_Error($c,$m):0;
    $id=$update?(int)$in['ID']:0;$before=null;$cur=[];
    if($update){ $before=elvado_wp_cms_find_any($id);if(!$before||$before->post_type!=='post')return $fail('invalid_post','Ungültige Beitrags-ID.');$cur=$before->to_array(); }
    $d=['post_author'=>0,'post_date'=>'','post_date_gmt'=>'','post_content'=>'','post_excerpt'=>'','post_title'=>'','post_status'=>'draft','post_name'=>''];
    $a=array_merge($d,$cur,array_intersect_key($in,$d));
    if($a['post_status']==='')$a['post_status']='draft';
    if(!$update&&(string)$a['post_title']===''&&(string)$a['post_content']===''&&(string)$a['post_excerpt']===''&&!apply_filters('wp_insert_post_empty_content',false,$a))return $fail('empty_content','Inhalt, Titel und Auszug sind leer.');
    $now=current_time('mysql');
    if(empty($a['post_date']))$a['post_date']=$now;
    if(empty($a['post_date_gmt']))$a['post_date_gmt']=get_gmt_from_date($a['post_date'])?:current_time('mysql',1);
    if($a['post_status']==='publish'&&strtotime($a['post_date_gmt'].' UTC')>time()+60)$a['post_status']='future';
    $a=apply_filters('wp_insert_post_data',$a,$in,[],$update);
    if(!in_array($a['post_status'],['publish','future','draft','pending','private','trash'],true))$a['post_status']='draft';
    $ch=fn(string $k)=>!$update||!array_key_exists($k,$cur)||(string)$a[$k]!==(string)$cur[$k];
    $set=[];$unset=[];
    if($ch('post_title')){ $t=elvado_wp_cms_text((string)$a['post_title'],255);$set['title']=$t!==''?$t:'Ohne Titel'; }
    if($ch('post_content')){ $h=elvado_wp_cms_filter_html((string)$a['post_content']);if($h===null)return $fail('content_too_long','Der Inhalt ist zu lang für einen CMS-Beitrag.');$set['body_html']=$h; }
    if($ch('post_excerpt'))$set['excerpt']=elvado_wp_cms_text((string)$a['post_excerpt'],600);
    if($ch('post_status')){
        $s=(string)$a['post_status'];
        if($s==='trash')$set['deleted_at']=$now;
        else{ $unset[]='deleted_at';$set['status']=in_array($s,['publish','future'],true)?'published':'draft';if(in_array($s,['pending','private'],true))$set['wp_status']=$s;else $unset[]='wp_status'; }
    }
    if($ch('post_date')){ $pd=(string)$a['post_date'];$set['published_at']=preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$pd)?$pd:$now; }
    $names=fn($v,$tx)=>elvado_wp_cms_term_names($v,$tx);
    $cats=null;$tags=null;
    if(!empty($in['post_category']))$cats=$names(array_filter((array)$in['post_category']),'category');
    if(!empty($in['tax_input']['category']))$cats=$names((array)$in['tax_input']['category'],'category');
    if(!empty($in['tags_input']))$tags=$names($in['tags_input'],'post_tag');
    if(!empty($in['tax_input']['post_tag']))$tags=$names((array)$in['tax_input']['post_tag'],'post_tag');
    $authorName=null;
    if($ch('post_author')||(!$update&&isset($in['post_author']))){ $u=get_userdata((int)$a['post_author']);if($u&&$u->display_name!=='')$authorName=(string)$u->display_name; }
    $actor=elvado_wp_cms_actor();$wantSlug=(string)$a['post_name'];$slugChanged=$ch('post_name');
    try{
        $res=elvado_wp_cms_news_mutate(function(array &$news) use($update,$id,$set,$unset,$cats,$tags,$authorName,$actor,$wantSlug,$slugChanged,$now,$a){
            $idx=null;foreach($news as $i=>$r)if(is_array($r)&&(int)($r['id']??0)===$id&&$update){$idx=$i;break;}
            if($update&&$idx===null)return ['ret'=>null,'changed'=>false];
            $taken=[];foreach($news as $r)if(is_array($r)&&(int)($r['id']??0)!==$id)$taken[(string)($r['slug']??'')]=true;
            if($update){ $row=$news[$idx];$snap=$row; }
            else{
                $nid=1;foreach($news as $r)if(is_array($r))$nid=max($nid,(int)($r['id']??0)+1);
                if($nid>=10000000)return ['ret'=>null,'changed'=>false];
                $row=['id'=>$nid,'slug'=>'','title'=>'Ohne Titel','category'=>'News','excerpt'=>'','image_url'=>'','image_mode'=>'thumbnail','external_url'=>'','video_url'=>'','tags'=>'','embed_html'=>'','status'=>'draft','featured'=>0,'published_at'=>$now,'body_html'=>'','author'=>$actor['display_name'],'author_user'=>$actor['user'],'seo_title'=>'','seo_description'=>'','updated_at'=>$now,'created_at'=>$now];
                $snap=null;
            }
            if(!$update||($slugChanged&&$wantSlug!==''))$set['slug']=elvado_wp_cms_unique_slug($taken,$wantSlug!==''?$wantSlug:(string)($set['title']??$row['title']),'beitrag');
            elseif($slugChanged&&$wantSlug==='')$set['slug']=elvado_wp_cms_unique_slug($taken,(string)($set['title']??$row['title']),'beitrag');
            if($cats&&mb_substr((string)$cats[0],0,80)!==(string)($row['category']??''))$set['category']=mb_substr((string)$cats[0],0,80);
            if($tags!==null&&mb_substr(implode(',',$tags),0,800)!==(string)($row['tags']??''))$set['tags']=mb_substr(implode(',',$tags),0,800);
            if($authorName!==null&&$authorName!==(string)($row['author']??''))$set['author']=$authorName;
            $new=$row;foreach($set as $k=>$v)$new[$k]=$v;foreach($unset as $k)unset($new[$k]);
            if($update&&$new==$row)return ['ret'=>(int)$row['id'],'changed'=>false];
            $new['updated_at']=$now;
            $rid=(int)$new['id'];$title=(string)($new['title']??'');
            if($update){ $news[$idx]=$new;
                $act='news_update';$txt='Beitrag „'.$title.'“ bearbeitet (WordPress)';
                if(isset($set['deleted_at'])){$act='news_trash';$txt='„'.$title.'“ in den Papierkorb verschoben (WordPress)';}
                elseif(isset($row['deleted_at'])&&!isset($new['deleted_at'])){$act='news_restore';$txt='„'.$title.'“ aus dem Papierkorb wiederhergestellt (WordPress)';}
                return ['ret'=>$rid,'changed'=>true,'rev'=>[$rid,$snap],'log'=>[$act,$txt]]; }
            $news[]=$new;return ['ret'=>$rid,'changed'=>true,'log'=>['news_create','Neuer Beitrag „'.$title.'“ angelegt (WordPress)']];
        });
    }catch(Throwable $e){ return $fail('cms_write',$e->getMessage()); }
    if(!$res)return $fail($update?'invalid_post':'cms_write',$update?'Ungültige Beitrags-ID.':'Der Beitrag konnte nicht gespeichert werden.');
    $id=(int)$res;
    foreach((array)($in['meta_input']??[]) as $k=>$v)update_post_meta($id,$k,$v);
    do_action('pre_post_update',$update?$id:0,$a);
    elvado_wp_cms_fire_save_hooks($id,$update,$before,'post');
    return $id;
}
/** Endgültig löschen (wie news_delete_permanent): Zeile aus news.json; Beitragsmeta und Begriffsbeziehungen dieser ID werden mitgelöscht. */
function elvado_wp_cms_news_delete(WP_Post $post) {
    global $wpdb;$id=(int)$post->ID;
    $pre=apply_filters('pre_delete_post',null,$post,true);if(null!==$pre)return $pre;
    do_action('before_delete_post',$id,$post);
    try{
        $ok=elvado_wp_cms_news_mutate(function(array &$news) use($id){
            $keep=array_values(array_filter($news,fn($r)=>!is_array($r)||(int)($r['id']??0)!==$id));
            if(count($keep)===count($news))return ['ret'=>false,'changed'=>false];
            $title='';foreach($news as $r)if(is_array($r)&&(int)($r['id']??0)===$id)$title=(string)($r['title']??'');
            $news=$keep;return ['ret'=>true,'changed'=>true,'log'=>['news_delete_permanent','„'.$title.'“ endgültig gelöscht (WordPress)']];
        });
    }catch(Throwable $e){ return false; }
    if(!$ok)return false;
    if($wpdb&&$wpdb->ready){ $wpdb->delete($wpdb->postmeta,['post_id'=>$id]);$wpdb->delete($wpdb->term_relationships,['object_id'=>$id]); }
    do_action('delete_post',$id,$post);do_action('deleted_post',$id,$post);do_action('after_delete_post',$id,$post);
    return $post;
}
/** Kategorie/Schlagwörter eines CMS-Beitrags setzen (wp_set_object_terms). null = nicht zuständig. */
function elvado_wp_cms_try_set_terms(int $id, $terms, string $taxonomy, bool $append) {
    if(!elvado_wp_cms_id_is_news($id)||!elvado_wp_bridge('posts')||!in_array($taxonomy,['category','post_tag'],true))return null;
    $post=elvado_wp_cms_find_any($id);if(!$post)return null;
    $names=elvado_wp_cms_term_names(is_array($terms)?array_values($terms):$terms,$taxonomy);
    try{
        elvado_wp_cms_news_mutate(function(array &$news) use($id,$names,$taxonomy,$append){
            foreach($news as $i=>$r){ if(!is_array($r)||(int)($r['id']??0)!==$id)continue;
                if($taxonomy==='category'){ if($append&&trim((string)($r['category']??''))!=='')return ['changed'=>false];$val=$names?mb_substr($names[0],0,80):(string)($r['category']??'News');$key='category'; }
                else{ $old=elvado_news_tag_list((string)($r['tags']??''));$all=$append?array_merge($old,$names):$names;$u=[];foreach($all as $n){$u[mb_strtolower($n)]=$u[mb_strtolower($n)]??$n;}$val=mb_substr(implode(',',array_values($u)),0,800);$key='tags'; }
                if((string)($r[$key]??'')===$val)return ['changed'=>false];
                $snap=$r;$news[$i][$key]=$val;$news[$i]['updated_at']=date('Y-m-d H:i:s');
                return ['changed'=>true,'rev'=>[$id,$snap],'log'=>['news_update','Beitrag „'.(string)($r['title']??'').'“: '.($key==='tags'?'Schlagwörter':'Kategorie').' über WordPress geändert']]; }
            return ['changed'=>false];
        });
    }catch(Throwable $e){ return new WP_Error('cms_write',$e->getMessage()); }
    $out=[];foreach($names as $n)$out[]=elvado_wp_cms_term($n,$taxonomy)->term_id;
    do_action('set_object_terms',$id,$names,$out,$taxonomy,$append,[]);
    return $out;
}
/** Titelbild (Meta _thumbnail_id) eines CMS-Beitrags ↔ Feld image_url. */
function elvado_wp_cms_news_set_thumb(int $id, int $attachment): bool {
    $url=$attachment>0?(string)wp_get_attachment_url($attachment):'';
    if($attachment>0&&$url==='')return false;
    try{
        return (bool)elvado_wp_cms_news_mutate(function(array &$news) use($id,$url){
            foreach($news as $i=>$r){ if(!is_array($r)||(int)($r['id']??0)!==$id)continue;
                if((string)($r['image_url']??'')===$url)return ['ret'=>true,'changed'=>false];
                $snap=$r;$news[$i]['image_url']=mb_substr($url,0,1200);if($url!==''&&($r['image_mode']??'thumbnail')==='none')$news[$i]['image_mode']='thumbnail';$news[$i]['updated_at']=date('Y-m-d H:i:s');
                return ['ret'=>true,'changed'=>true,'rev'=>[$id,$snap],'log'=>['news_update','Beitrag „'.(string)($r['title']??'').'“: Titelbild über WordPress '.($url===''?'entfernt':'gesetzt')]]; }
            return ['ret'=>false,'changed'=>false];
        });
    }catch(Throwable $e){ return false; }
}
add_filter('update_post_metadata',function($check,$id,$key,$value){ return $key==='_thumbnail_id'&&elvado_wp_cms_id_is_news((int)$id)&&elvado_wp_bridge('posts')&&elvado_wp_cms_find_any((int)$id)?elvado_wp_cms_news_set_thumb((int)$id,(int)$value):$check; },10,4);
add_filter('add_post_metadata',function($check,$id,$key,$value){ return $key==='_thumbnail_id'&&elvado_wp_cms_id_is_news((int)$id)&&elvado_wp_bridge('posts')&&elvado_wp_cms_find_any((int)$id)?elvado_wp_cms_news_set_thumb((int)$id,(int)$value):$check; },10,4);
add_filter('delete_post_metadata',function($check,$id,$key){ return $key==='_thumbnail_id'&&elvado_wp_cms_id_is_news((int)$id)&&elvado_wp_bridge('posts')&&elvado_wp_cms_find_any((int)$id)?elvado_wp_cms_news_set_thumb((int)$id,0):$check; },10,3);
add_filter('get_post_metadata',function($check,$id,$key){
    if($key!=='_thumbnail_id'||!elvado_wp_cms_id_is_news((int)$id)||!elvado_wp_bridge('posts'))return $check;
    $p=elvado_wp_cms_find_any((int)$id);if(!$p)return $check;$u=(string)($p->elvado_data['image_url']??'');
    return $u!==''&&($p->elvado_data['image_mode']??'thumbnail')!=='none'?[elvado_wp_cms_attachment_id($u)]:[];
},10,3);

/* ───────── Seiten ↔ site.json (pages[], type=custom) ───────── */
/** Seiteninhalt (WordPress: ein Feld) auf die Blöcke einer CMS-Seite abbilden – nur, wenn das eindeutig geht. null = übernommen, sonst Grund. */
function elvado_wp_cms_page_content_set(array &$pg, string $content): ?string {
    if($content===elvado_wp_cms_page_html($pg))return null;
    $all=elvado_wp_cms_page_slots($pg);$slots=[];$other=0;
    foreach($all as [$k,$i,$b])if(($b['type']??'')==='html')$slots[]=[$k,$i];else $other++;
    $intro=(string)($pg['intro']??'');$prefix=$intro!==''?'<p>'.esc_html($intro).'</p>':'';
    if($prefix!==''){ if(!str_starts_with($content,$prefix))return 'Der Einleitungstext der Seite lässt sich nur im CMS bearbeiten.';$content=substr($content,strlen($prefix)); }
    if(count($all)>1){   // mehrteilige Seite: jeder Block steht zwischen Markern; Reihenfolge und Anzahl müssen erhalten bleiben
        $re='#<!--elvado:block ([A-Za-z0-9_-]+)-->(.*?)<!--/elvado:block-->#s';
        if(!preg_match_all($re,$content,$m,PREG_SET_ORDER)||trim((string)preg_replace($re,'',$content))!=='')return 'Die Seite besteht aus mehreren Blöcken; Inhalt außerhalb der Block-Marker lässt sich nicht übernehmen.';
        if(array_column($m,1)!==array_column($all,3))return 'Die Blöcke der Seite wurden hinzugefügt, entfernt oder umgestellt; das geht nur im CMS.';
        $new=[];
        foreach($all as $n=>[$k,$i,$b]){
            $part=$m[$n][2];
            if(($b['type']??'')==='html'){ $h=elvado_wp_cms_filter_html($part);if($h===null)return 'Der Inhalt ist zu lang für eine CMS-Seite.';$new[$n]=$h; }
            else{
                if(!preg_match('#^<p>([^<]*)</p>$#s',$part,$q))return 'Ein Textblock der Seite enthält Formatierung und lässt sich nur im CMS bearbeiten.';
                $new[$n]=mb_substr(htmlspecialchars_decode($q[1],ENT_QUOTES),0,15000);
            }
        }
        foreach($all as $n=>[$k,$i,$b])$pg[$k][$i][($b['type']??'')==='html'?'html':'text']=$new[$n];
        return null;
    }
    if($other>0)return 'Ein Textblock der Seite lässt sich nur im CMS bearbeiten.';
    $html=elvado_wp_cms_filter_html($content);if($html===null)return 'Der Inhalt ist zu lang für eine CMS-Seite.';
    if($slots){ [$k,$i]=$slots[0];$pg[$k][$i]['html']=$html; }
    elseif($html!==''){ $pg['blocks_before']=array_merge(is_array($pg['blocks_before']??null)?$pg['blocks_before']:[],[['id'=>'blk_'.bin2hex(random_bytes(4)),'type'=>'html','enabled'=>true,'html'=>$html]]); }
    return null;
}
function elvado_wp_cms_page_save(array $in, bool $wp_error) {
    $fail=fn(string $c,string $m)=>$wp_error?new WP_Error($c,$m):0;
    $id=(int)$in['ID'];$before=elvado_wp_cms_find_any($id);if(!$before||$before->post_type!=='page')return $fail('invalid_post','Ungültige Seiten-ID.');
    $cur=$before->to_array();$a=array_merge($cur,array_intersect_key($in,['post_title'=>1,'post_content'=>1,'post_status'=>1,'post_name'=>1]));
    $a=apply_filters('wp_insert_post_data',$a,$in,[],true);
    $ch=fn(string $k)=>!array_key_exists($k,$cur)||(string)$a[$k]!==(string)$cur[$k];
    $root=elvado_wp_cms_root();$err=null;
    try{
        $res=elvado_wp_cms_site_mutate(function(array &$site) use($id,$a,$ch,$root,&$err){
            $idx=null;foreach((array)($site['pages']??[]) as $i=>$pg)if(is_array($pg)&&($pg['type']??'')==='custom'&&elvado_wp_hash_id((string)($pg['id']??$pg['slug']??''),ELVADO_WP_ID_PAGE_BASE)===$id){$idx=$i;break;}
            if($idx===null){ $err=['invalid_post','Ungültige Seiten-ID.'];return null; }
            $old=$site['pages'];$pg=$site['pages'][$idx];
            if($ch('post_title')){ $t=elvado_wp_cms_text((string)$a['post_title'],260);if($t==='')$t=(string)($pg['title']??'Seite');
                if((string)($pg['headline']??'')!=='')$pg['headline']=$t;else $pg['title']=mb_substr($t,0,160); }
            if($ch('post_content')){ $e=elvado_wp_cms_page_content_set($pg,(string)$a['post_content']);if($e!==null){ $err=['cms_unmappable',$e];return null; } }
            if($ch('post_status'))$pg['enabled']=(string)$a['post_status']==='publish';
            if($ch('post_name')&&(string)$a['post_name']!==''){
                $slug=elvado_slug((string)$a['post_name']);$oldSlug=(string)($pg['slug']??'');
                if($slug!==$oldSlug){
                    foreach($site['pages'] as $j=>$o)if($j!==$idx&&is_array($o)&&((string)($o['slug']??'')===$slug||($o['type']??'')==='system'&&(string)($o['system_target']??'')===$slug)){ $err=['slug_taken','Die Adresse „'.$slug.'“ ist schon vergeben.'];return null; }
                    $f=$root.'/'.$slug.'.html';
                    if(is_file($f)&&!str_contains((string)@file_get_contents($f,false,null,0,128),ELVADO_CMS_MARKER)){ $err=['slug_reserved','Die Adresse „'.$slug.'“ ist durch eine feste Seite der Website belegt.'];return null; }
                    $pg['slug']=$slug;
                    foreach(['top','bottom'] as $m)foreach((array)($site['menus'][$m]??[]) as $k=>$it)if(is_array($it)&&(string)($it['target']??'')==='page:'.$oldSlug)$site['menus'][$m][$k]['target']='page:'.$slug;   // Menüpunkte folgen der neuen Adresse
                }
            }
            if($pg==$site['pages'][$idx])return null;
            $site['pages'][$idx]=$pg;
            return ['ret'=>true,'pages_before'=>$old,'log'=>['page_update','Seite „'.(string)($pg['title']??'').'“ bearbeitet (WordPress)']];
        });
    }catch(Throwable $e){ return $fail('cms_write',$e->getMessage()); }
    if($err)return $fail($err[0],$err[1]);
    do_action('pre_post_update',$id,$a);
    elvado_wp_cms_fire_save_hooks($id,true,$before,'page');
    return $id;
}

/* ───────── Menüs ↔ site.json menus.top / menus.bottom ───────── */
function elvado_wp_cms_menu_item_hash(string $mid): int { return crc32($mid)%900000+3000000; }
/** wp_update_nav_menu_item: Menüpunkt anlegen/ändern. Nur Menü 1 (oben) und 2 (unten); Ziele: eigene Adresse, Startseite/System, CMS-Seite. */
function elvado_wp_cms_menu_item_save(int $menuId, int $itemId, array $args) {
    $menu=[1=>'top',2=>'bottom'][$menuId]??null;if($menu===null)return 0;
    $type=(string)($args['menu-item-type']??'custom');$title=trim(wp_strip_all_tags((string)($args['menu-item-title']??'')));
    $url=trim((string)($args['menu-item-url']??''));$target=null;
    if($type==='custom'){
        $home=rtrim(home_url('/'),'/');
        if($url===''||$url==='#')return 0;
        elseif(rtrim($url,'/')===$home)$target='system:start';
        elseif(str_starts_with($url,$home.'/#')&&preg_match('/^[a-z0-9_-]+$/i',substr($url,strlen($home)+2)))$target='system:'.substr($url,strlen($home)+2);
        else $target='url:'.mb_substr($url,0,1200);
    }elseif($type==='post_type'&&($args['menu-item-object']??'')==='page'){
        foreach(elvado_wp_cms_pages_any() as $p)if((int)$p->ID===(int)($args['menu-item-object-id']??0)){ $target='page:'.$p->post_name;break; }
    }
    $parent=(int)($args['menu-item-parent-id']??0);$status=(string)($args['menu-item-status']??'publish');$pos=(int)($args['menu-item-position']??0);
    $ret=0;
    try{
        $hasParent=array_key_exists('menu-item-parent-id',$args);
        elvado_wp_cms_site_mutate(function(array &$site) use($menu,$itemId,$target,$title,$parent,$hasParent,$status,$pos,&$ret){
            $items=is_array($site['menus'][$menu]??null)?$site['menus'][$menu]:[];$idx=null;
            if($itemId>0){ foreach($items as $i=>$m)if(is_array($m)&&elvado_wp_cms_menu_item_hash((string)($m['id']??''))===$itemId){$idx=$i;break;} if($idx===null)return null; }
            $pid='';if($parent>0){ foreach($items as $m)if(is_array($m)&&elvado_wp_cms_menu_item_hash((string)($m['id']??''))===$parent){$pid=(string)$m['id'];break;} }
            if($idx===null&&$target===null)return null;
            if($idx!==null){ $m=$items[$idx];if($target!==null)$m['target']=$target;if($title!=='')$m['label']=mb_substr($title,0,100);if($hasParent&&($pid!==''||$parent===0))$m['parent_id']=$pid;$m['enabled']=$status!=='draft';$items[$idx]=$m;$ret=$itemId; }
            else{ $mid=($menu==='top'?'m-':'b-').bin2hex(random_bytes(4));$m=['id'=>$mid,'label'=>mb_substr($title!==''?$title:'Menüpunkt',0,100),'target'=>$target,'icon'=>'fa-circle','parent_id'=>$pid,'enabled'=>$status!=='draft'];
                if($pos>0&&$pos<=count($items))array_splice($items,$pos-1,0,[$m]);else $items[]=$m;$ret=elvado_wp_cms_menu_item_hash($mid); }
            $site['menus'][$menu]=array_values($items);
            return ['log'=>['menus_update','Menü „'.($menu==='top'?'Hauptmenü':'Fußmenü').'“ über WordPress geändert']];
        });
    }catch(Throwable $e){ return 0; }
    return $ret;
}
/** Menüpunkt entfernen (wp_delete_post auf eine Menüpunkt-ID). null = keine CMS-Menüpunkt-ID. */
function elvado_wp_cms_menu_item_delete(int $itemId) {
    if($itemId<3000000||$itemId>=3900000||!elvado_wp_bridge('menus'))return null;
    $hit=false;
    try{
        elvado_wp_cms_site_mutate(function(array &$site) use($itemId,&$hit){
            foreach(['top','bottom'] as $menu){ $items=array_values((array)($site['menus'][$menu]??[]));
                foreach($items as $i=>$m){ if(!is_array($m)||elvado_wp_cms_menu_item_hash((string)($m['id']??''))!==$itemId)continue;
                    $hit=true;$mid=(string)$m['id'];$up=(string)($m['parent_id']??'');unset($items[$i]);
                    foreach($items as $k=>$c)if(is_array($c)&&(string)($c['parent_id']??'')===$mid)$items[$k]['parent_id']=$up;   // Unterpunkte rücken nach oben
                    $site['menus'][$menu]=array_values($items);return ['log'=>['menus_update','Menüpunkt „'.(string)($m['label']??'').'“ über WordPress entfernt']]; } }
            return null;
        });
    }catch(Throwable $e){ return false; }
    return $hit?(object)['ID'=>$itemId,'post_type'=>'nav_menu_item']:null;
}

/* ───────── Verwaltung: einheitliche Liste „Alle Inhalte“ ───────── */
function elvado_wp_cms_content_overview(int $limit=300): array {
    $items=[];
    foreach(elvado_wp_cms_posts_any() as $p){
        $items[]=['key'=>'news:'.$p->ID,'source'=>'cms-news','id'=>(int)$p->ID,'type'=>'post','title'=>html_entity_decode((string)$p->post_title,ENT_QUOTES),'status'=>$p->post_status,'modified'=>$p->post_modified,'url'=>$p->guid,'author'=>(string)($p->elvado_data['author']??''),'elementor'=>false];
    }
    foreach(elvado_wp_cms_pages_any() as $p){
        $items[]=['key'=>'page:'.$p->ID,'source'=>'cms-page','id'=>(int)$p->ID,'cms_id'=>(string)($p->elvado_data['id']??''),'type'=>'page','title'=>html_entity_decode((string)$p->post_title,ENT_QUOTES),'status'=>$p->post_status,'modified'=>$p->post_modified,'url'=>$p->guid,'author'=>'','elementor'=>false];
    }
    global $wpdb;
    if($wpdb&&$wpdb->ready&&elvado_wp_db_ready()){
        $rows=$wpdb->get_results("SELECT ID,post_title,post_status,post_type,post_modified,post_author FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status IN ('publish','draft','private','pending','future') ORDER BY ID DESC LIMIT 200");
        foreach((array)$rows as $r)$items[]=['key'=>'wp:'.$r->ID,'source'=>'wp','id'=>(int)$r->ID,'type'=>(string)$r->post_type,'title'=>(string)$r->post_title,'status'=>(string)$r->post_status,'modified'=>(string)$r->post_modified,'url'=>get_permalink((int)$r->ID),'author'=>'','elementor'=>get_post_meta((int)$r->ID,'_elementor_edit_mode',true)==='builder'];
    }
    usort($items,fn($a,$b)=>strcmp((string)$b['modified'],(string)$a['modified'])?:($b['id']<=>$a['id']));
    return array_slice($items,0,$limit);
}
