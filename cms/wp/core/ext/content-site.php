<?php
// Ergänzende Funktionen für Cron, Rewrite, Abfrage-Hilfen und kanonische Weiterleitungen (cron.php, rewrite.php, query.php, canonical.php).
// Rewrite-Regeln gibt es nicht (immer /%postname%/): die Funktionen dazu sind schlank bzw. leer.

/* ───────── Cron ───────── */
if(!function_exists('_get_cron_array')){
    function _get_cron_array() { $c=get_option('cron');if(!is_array($c))return false;unset($c['version']);return $c; }
}
if(!function_exists('_set_cron_array')){
    function _set_cron_array($cron, $wp_error=false) { unset($cron['version']);_elvado_wp_cron_set((array)$cron);return true; }
}
if(!function_exists('_upgrade_cron_array')){
    // Wandelt das alte Format (ohne Argument-Schlüssel) um; Version 2 wird nur im Ergebnis vermerkt, nicht gespeichert (die Schicht kennt kein „version“-Feld).
    function _upgrade_cron_array($cron) {
        if(isset($cron['version'])&&2==$cron['version'])return $cron;
        $new=[];foreach((array)$cron as $ts=>$hooks)foreach((array)$hooks as $hook=>$a)$new[$ts][$hook][md5(serialize($a['args']??[]))]=$a;
        $new['version']=2;return $new;
    }
}
if(!function_exists('_wp_cron')){
    // Führt fällige Aufgaben direkt aus (statt einen Hintergrundaufruf zu starten); Rückgabe: 0 = nichts fällig, 1 = ausgeführt.
    function _wp_cron() { if(!wp_get_ready_cron_jobs())return 0;elvado_wp_run_cron(false);return 1; }
}
if(!function_exists('wp_reschedule_event')){
    function wp_reschedule_event($timestamp, $recurrence, $hook, $args=[], $wp_error=false) {
        $s=wp_get_schedules();$interval=isset($s[$recurrence])?(int)$s[$recurrence]['interval']:0;
        if(0===$interval){ $e=wp_get_scheduled_event($hook,$args,$timestamp);if($e&&isset($e->interval))$interval=(int)$e->interval; }
        $event=(object)['hook'=>$hook,'timestamp'=>$timestamp,'schedule'=>$recurrence,'args'=>$args,'interval'=>$interval];
        $pre=apply_filters('pre_reschedule_event',null,$event,$wp_error);if(null!==$pre)return $pre;
        if(0===$interval)return $wp_error?new WP_Error('invalid_schedule','Ungültiger Zeitplan.'):false;
        $now=time();$timestamp=$timestamp>=$now?$now+$interval:$now+($interval-(($now-$timestamp)%$interval));
        return wp_schedule_event($timestamp,$recurrence,$hook,$args,$wp_error);
    }
}

/* ───────── Rewrite ───────── */
if(!function_exists('remove_rewrite_tag')){ function remove_rewrite_tag($tag) {} }       // Es gibt keine Rewrite-Tags
if(!function_exists('remove_permastruct')){ function remove_permastruct($name) {} }
if(!function_exists('add_feed')){
    function add_feed($feedname, $callback) {
        global $wp_rewrite;if(!in_array($feedname,$wp_rewrite->feeds,true))$wp_rewrite->feeds[]=$feedname;
        $hook='do_feed_'.$feedname;remove_action($hook,$hook);add_action($hook,$callback,10,2);return $hook;
    }
}
if(!function_exists('_wp_filter_taxonomy_base')){
    function _wp_filter_taxonomy_base($base) { if(!empty($base)){ $base=trim(preg_replace('|^/index\.php/|','',$base),'/'); }return $base; }
}
if(!function_exists('wp_resolve_numeric_slug_conflicts')){
    // Eine Seite/ein Beitrag mit rein numerischem Namen („2024“) gewinnt gegen ein leeres Datums-Archiv an gleicher Stelle der Adresse.
    function wp_resolve_numeric_slug_conflicts($query_vars=[]) {
        if(!isset($query_vars['year'])&&!isset($query_vars['monthnum'])&&!isset($query_vars['day']))return $query_vars;
        $ps=array_values(array_filter(explode('/',(string)get_option('permalink_structure'))));$i=array_search('%postname%',$ps,true);
        if(false===$i)return $query_vars;
        $prev=$ps[$i-1]??null;
        $cmp=(0===$i||'%year%'===$prev)?'year':('%monthnum%'===$prev?'monthnum':('%day%'===$prev?'day':null));
        $val=$cmp&&!empty($query_vars[$cmp])?$query_vars[$cmp]:'';
        $post=get_page_by_path((string)$val,OBJECT,'post');
        if(!($post instanceof WP_Post))return $query_vars;
        $q=new WP_Query($query_vars);if($q->have_posts())return $query_vars;
        unset($query_vars['year'],$query_vars['monthnum'],$query_vars['day']);$query_vars['name']=$post->post_name;
        return $query_vars;
    }
}

/* ───────── Abfrage ───────── */
if(!function_exists('is_comment_feed')){ function is_comment_feed() { return elvado_wp_flag('is_comment_feed'); } }
if(!function_exists('is_favicon')){ function is_favicon() { return elvado_wp_flag('is_favicon'); } }
if(!function_exists('the_comment')){
    // Schleife über $wp_query->comments: setzt $comment auf den nächsten Kommentar.
    function the_comment() {
        global $wp_query,$comment;if(!$wp_query||empty($wp_query->comments))return;
        $wp_query->current_comment++;$comment=$wp_query->comments[$wp_query->current_comment]??null;$wp_query->comment=$comment;
        if($comment)do_action_ref_array('comment_loop_start',[]);
    }
}
if(!function_exists('generate_postdata')){
    function generate_postdata($post) {
        $post=get_post($post);if(!$post)return false;
        $id=(int)$post->ID;$authordata=get_userdata($post->post_author);
        $currentday=mysql2date('d.m.y',$post->post_date,false);$currentmonth=mysql2date('m',$post->post_date,false);
        $page=get_query_var('page');if(!$page)$page=1;
        $more=(get_queried_object_id()===$id&&(is_page()||is_single()))||is_feed()?1:0;
        $content=$post->post_content;
        if(str_contains($content,'<!--nextpage-->')){
            $content=str_replace(["\n<!--nextpage-->\n","\n<!--nextpage-->","<!--nextpage-->\n"],'<!--nextpage-->',$content);
            if(str_starts_with($content,'<!--nextpage-->'))$content=substr($content,15);
            $pages=explode('<!--nextpage-->',$content);
        }else $pages=[$content];
        $pages=apply_filters('content_pagination',$pages,$post);$numpages=count($pages);$multipage=$numpages>1?1:0;
        if($page>$numpages)$page=$numpages;
        return compact('id','authordata','currentday','currentmonth','page','pages','multipage','more','numpages');
    }
}
if(!function_exists('elvado_wp_x_date_like')){
    /** LIKE-Muster „JJJJ-MM-TT%“ aus den Abfrage-Variablen year/monthnum/day (fehlende Teile als Platzhalter), sonst null. */
    function elvado_wp_x_date_like(): ?string {
        $y=(int)get_query_var('year');$m=(int)get_query_var('monthnum');$d=(int)get_query_var('day');if(!$y&&!$m&&!$d)return null;
        return ($y?sprintf('%04d',$y):'____').'-'.($m?sprintf('%02d',$m):'__').'-'.($d?sprintf('%02d',$d):'__').'%';
    }
}
if(!function_exists('_find_post_by_old_slug')){
    function _find_post_by_old_slug($post_type) {
        global $wpdb;if(!$wpdb||!elvado_wp_db_ready())return 0;
        $q=$wpdb->prepare("SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = %s AND m.meta_key = '_wp_old_slug' AND m.meta_value = %s",$post_type,get_query_var('name'));
        if($like=elvado_wp_x_date_like())$q.=$wpdb->prepare(' AND p.post_date LIKE %s',$like);
        return (int)$wpdb->get_var($q);
    }
}
if(!function_exists('_find_post_by_old_date')){
    function _find_post_by_old_date($post_type) {
        global $wpdb;if(!$wpdb||!elvado_wp_db_ready()||!($like=elvado_wp_x_date_like()))return false;
        $id=(int)$wpdb->get_var($wpdb->prepare("SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = %s AND m.meta_key = '_wp_old_date' AND p.post_name = %s AND m.meta_value LIKE %s",$post_type,get_query_var('name'),$like));
        return $id?:false;
    }
}

/* ───────── Kanonische Adressen ───────── */
if(!function_exists('strip_fragment_from_url')){
    function strip_fragment_from_url($url) { $h=strpos((string)$url,'#');return false===$h?$url:substr($url,0,$h); }
}
if(!function_exists('_remove_qs_args_if_not_in_url')){
    function _remove_qs_args_if_not_in_url($query_string, array $args_to_check, $url) {
        $p=parse_url($url);
        if(!empty($p['query'])){ parse_str($p['query'],$pq);foreach($args_to_check as $qv)if(!isset($pq[$qv]))$query_string=remove_query_arg($qv,$query_string); }
        else $query_string=remove_query_arg($args_to_check,$query_string);
        return $query_string;
    }
}
if(!function_exists('redirect_guess_404_permalink')){
    /** Rät die Adresse zu einer nicht gefundenen Seite: Beitrag/Seite, deren Name mit dem gesuchten beginnt (auch CMS-Inhalte). */
    function redirect_guess_404_permalink() {
        global $wpdb;$pre=apply_filters('pre_redirect_guess_404_permalink',null);if(is_string($pre))return $pre;
        $name=(string)get_query_var('name');if($name==='')return false;
        $types=array_filter(get_post_types(['exclude_from_search'=>false]),'is_post_type_viewable');$id=0;
        if($wpdb&&elvado_wp_db_ready()&&$types)$id=(int)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE %s AND post_status = 'publish' AND post_type IN (".implode(',',array_map(fn($t)=>"'".esc_sql($t)."'",$types)).") ORDER BY ID ASC LIMIT 1",$wpdb->esc_like($name).'%'));
        if(!$id)foreach(array_merge(elvado_wp_cms_posts(),elvado_wp_cms_pages()) as $p)if(str_starts_with((string)$p->post_name,$name)){ $id=(int)$p->ID;break; }
        if(!$id)return false;
        $l=get_permalink($id);return apply_filters('redirect_guess_404_permalink',$l?:false,$id);
    }
}
if(!function_exists('redirect_canonical')){
    /** Kanonische Adresse für Einzelseiten und Begriffsarchive (Pfad, abschließender Schrägstrich, Abfrage-Parameter bleiben erhalten); 404 → Vorschlag aus redirect_guess_404_permalink(). */
    function redirect_canonical($requested_url=null, $do_redirect=true) {
        global $wp_query;
        if(isset($_SERVER['REQUEST_METHOD'])&&!in_array(strtoupper((string)$_SERVER['REQUEST_METHOD']),['GET','HEAD'],true))return;
        if(is_admin()||is_search()||is_preview()||is_trackback()||is_favicon()||is_robots()||is_embed()||!$wp_query)return;
        if(!$requested_url&&isset($_SERVER['HTTP_HOST'],$_SERVER['REQUEST_URI']))$requested_url=(is_ssl()?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        if(!$requested_url)return;
        $orig=parse_url($requested_url);if(false===$orig)return;
        $redirect=$orig;$url=false;
        if(is_404()){ $url=redirect_guess_404_permalink(); }
        elseif(is_singular()){
            $o=get_queried_object();
            if($o instanceof WP_Post&&!is_front_page()){ $c=get_permalink($o);$p=$c?parse_url($c):null;
                if($p&&!empty($p['path'])){ $redirect['path']=$p['path'];$redirect['query']=ltrim((string)remove_query_arg(['p','page_id','attachment_id','name','pagename','preview'],'?'.($orig['query']??'')),'?'); } }
        }elseif(is_category()||is_tag()||is_tax()){
            $o=get_queried_object();
            if($o&&isset($o->term_id)){ $c=get_term_link($o);$p=!is_wp_error($c)?parse_url($c):null;if($p&&!empty($p['path']))$redirect['path']=$p['path']; }
        }
        if(!$url){
            if(isset($redirect['path'])){
                $redirect['path']=preg_replace('#/+#','/',$redirect['path']);
                $redirect['path']=preg_replace('#/index\.php/?$#','/',$redirect['path']);
                $ps=(string)get_option('permalink_structure');
                if($ps!==''&&!str_contains(basename($redirect['path']),'.'))$redirect['path']=user_trailingslashit($redirect['path']);
            }
            if(isset($redirect['query'])&&$redirect['query']==='')unset($redirect['query']);
            if($redirect===$orig)return false;
            $url=(!empty($redirect['scheme'])?$redirect['scheme'].'://':'//').($redirect['host']??'').(!empty($redirect['port'])?':'.$redirect['port']:'').($redirect['path']??'/').(!empty($redirect['query'])?'?'.$redirect['query']:'');
        }
        $url=apply_filters('redirect_canonical',$url,$requested_url);
        if(!$url||strip_fragment_from_url($url)===strip_fragment_from_url($requested_url))return false;
        if(!$do_redirect)return $url;
        wp_redirect($url,301,'WordPress');exit;
    }
}
if(!function_exists('wp_redirect_admin_locations')){
    function wp_redirect_admin_locations() {
        global $wp_rewrite;if(!(is_404()&&$wp_rewrite->using_permalinks()))return;
        $req=untrailingslashit((string)($_SERVER['REQUEST_URI']??''));
        $adm=[home_url('wp-admin','relative'),home_url('dashboard','relative'),home_url('admin','relative'),site_url('dashboard','relative'),site_url('admin','relative')];
        if(in_array($req,$adm,true)){ wp_redirect(admin_url());exit; }
        $log=[home_url('wp-login.php','relative'),home_url('login','relative'),site_url('login','relative')];
        if(in_array($req,$log,true)){ wp_redirect(wp_login_url());exit; }
    }
}
