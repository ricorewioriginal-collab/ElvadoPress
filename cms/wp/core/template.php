<?php
// Vorlagen-Funktionen (Template Tags) von WordPress: Titel, Inhalt, Auszug, Links, Datum, Autor, Kategorien, CSS-Klassen, Paginierung,
// Kopf/Fuß/Seitenleiste laden, wp_head/wp_footer, Titelzeile.

/* ───────── Beitragsdaten ───────── */
function get_the_ID() { $p=get_post();return $p?(int)$p->ID:false; }
function the_ID() { echo get_the_ID(); }
function get_the_title($post=0) { $p=get_post($post);$t=isset($p->post_title)?$p->post_title:'';$id=isset($p->ID)?$p->ID:0;return apply_filters('the_title',$t,$id); }
function the_title($before='', $after='', $display=true) { $t=get_the_title();if($t==='')return;$o=$before.$t.$after;if($display)echo $o;else return $o; }
function the_title_attribute($args='') { $a=wp_parse_args($args,['before'=>'','after'=>'','echo'=>true,'post'=>get_post()]);$t=strip_tags(get_the_title($a['post']));$t=$a['before'].esc_attr($t).$a['after'];if($a['echo'])echo $t;else return $t; }
function rrw_wp_is_cms_html(?WP_Post $p): bool { return $p&&in_array($p->rrw_source,['news','page'],true); }
function get_the_content($more_link_text=null, $strip_teaser=false, $post=null) {
    $p=get_post($post);if(!$p)return '';$c=$p->post_content;
    if(!rrw_wp_is_cms_html($p)&&preg_match('/<!--more(.*?)?-->/',$c,$m)){ $parts=preg_split('/<!--more(.*?)?-->/',$c,2);$c=$parts[0].(is_singular()?'':'<a href="'.esc_url(get_permalink($p)).'" class="more-link">'.esc_html($more_link_text??'Weiterlesen').'</a>'); }
    return $c;
}
function the_content($more_link_text=null, $strip_teaser=false) {
    $c=get_the_content($more_link_text,$strip_teaser);
    $c=apply_filters('the_content',$c);$c=str_replace(']]>',']]&gt;',$c);echo $c;
}
function post_password_required($post=null) { return false; }
function has_excerpt($post=0) { $p=get_post($post);return !empty($p->post_excerpt); }
function get_the_excerpt($post=null) {
    $p=get_post($post);if(!$p)return '';$e=$p->post_excerpt;
    if($e==='')$e=wp_trim_words(strip_shortcodes($p->post_content),(int)apply_filters('excerpt_length',55),(string)apply_filters('excerpt_more',' [&hellip;]'));
    return apply_filters('get_the_excerpt',$e,$p);
}
function wp_trim_excerpt($text='', $post=null) { return $text!==''?$text:get_the_excerpt($post); }
function the_excerpt() { echo apply_filters('the_excerpt',get_the_excerpt()); }
function get_post_gallery($post=0, $html=true) { return false; }
function has_block($block_name, $post=null) { $c=is_string($post)?$post:(get_post($post)->post_content??'');return str_contains($c,'<!-- wp:'.str_replace('core/','',$block_name)); }
function has_blocks($post=null) { $c=is_string($post)?$post:(get_post($post)->post_content??'');return str_contains($c,'<!-- wp:'); }
function get_media_embedded_in_content($content, $types=null) { return []; }
function get_the_password_form($post=0) { return ''; }
function wp_link_pages($args='') { return ''; }
function edit_post_link($text=null, $before='', $after='', $post=0, $css_class='post-edit-link') {}
function get_edit_post_link($post=0, $context='display') { return null; }
function edit_comment_link($text=null, $before='', $after='') {}
function get_edit_comment_link($c=0) { return ''; }
function wp_get_post_parent_id($post=null) { $p=get_post($post);return $p?(int)$p->post_parent:0; }
function attachment_url_to_postid($url) { foreach((array)($GLOBALS['rrw_wp_cms_attachments']??[]) as $id=>$u)if($u===$url)return (int)$id;return 0; }
function is_page_template($template='') { return false; }
function get_page_template_slug($post=null) { return (string)get_post_meta((int)(get_post($post)->ID??0),'_wp_page_template',true); }
function tag_escape($t) { return strtolower(preg_replace('/[^%a-zA-Z0-9_-]/','',(string)$t)); }
function the_privacy_policy_link($before='', $after='') {}
function get_the_privacy_policy_link($before='', $after='') { return ''; }
function wp_installing($v=null) { return false; }
function wp_print_inline_script_tag($js, $attrs=[]) { echo '<script>'.str_replace('</script','<\/script',(string)$js)."</script>\n"; }
function wp_get_inline_script_tag($js, $attrs=[]) { return '<script>'.str_replace('</script','<\/script',(string)$js)."</script>\n"; }
function wp_style_add_data($handle, $key, $value) { return true; }
function wp_script_add_data($handle, $key, $value) { return true; }
function add_editor_style($s='') {}
function add_theme_support_dummy() {}
function register_rest_route($ns, $route, $args=[], $override=false) {
    $key='/'.trim((string)$ns,'/').'/'.trim((string)$route,'/');$eps=isset($args['callback'])||isset($args['methods'])?[$args]:array_values(array_filter((array)$args,fn($v,$k)=>is_int($k)&&is_array($v),ARRAY_FILTER_USE_BOTH));
    foreach($eps as &$e)if(is_array($e)&&isset($args['args'])&&!isset($e['args'])&&$e===$args)$e['args']=$args['args'];unset($e);
    $GLOBALS['rrw_wp_rest_routes'][$key]=$override?$eps:array_merge($GLOBALS['rrw_wp_rest_routes'][$key]??[],$eps);return true;
}
function rest_ensure_response($response) { if(is_wp_error($response)||$response instanceof WP_REST_Response)return $response;return new WP_REST_Response($response,200); }
function rest_url($path='') { return home_url('/wp-json/'.ltrim($path,'/')); }
function get_rest_url($b=null,$path='') { return rest_url($path); }

/* ───────── Links ───────── */
/* ───────── Link-Form (Einstellungen → Permalinks) ───────── */
const RRW_WP_LINK_TOKENS=['%year%'=>'year','%monthnum%'=>'monthnum','%day%'=>'day','%hour%'=>'hour','%minute%'=>'minute','%second%'=>'second','%post_id%'=>'post_id','%postname%'=>'postname','%category%'=>'category','%author%'=>'author'];
/** Aufbau der Beitrags-Adressen: '' = einfach (?p=123), '/%postname%/' = Standard, sonst Struktur mit Platzhaltern (/%year%/%monthnum%/%postname%/, /blog/%postname%/ …). */
function rrw_wp_link_structure(): string { $s=get_option('permalink_structure','/%postname%/');return is_string($s)?$s:'/%postname%/'; }
/** Struktur mit Platzhaltern über den Standard hinaus? (Beiträge werden dann über die Struktur gefunden und ausgeliefert.) */
function rrw_wp_link_custom(): bool { $s=rrw_wp_link_structure();return $s!==''&&$s!=='/%postname%/'&&str_contains($s,'%'); }
/** Hash-Form: Adressen als /#/seite (die Startseite leitet im Browser auf den Pfad weiter). */
function rrw_wp_link_hash(): bool { return (bool)get_option('rrw_link_hash',0); }
function rrw_wp_link_wrap(string $url): string {
    if(!rrw_wp_link_hash())return $url;
    $home=home_url('/');if(!str_starts_with($url,$home)||str_contains($url,'?')||str_contains($url,'#'))return $url;
    $rest=substr($url,strlen($home));if($rest===''||str_contains($rest,'.')||preg_match('~^(wp-|cms/)~',$rest))return $url;   // Dateien und Systempfade bleiben unverändert
    return $home.'#/'.$rest;
}
function rrw_wp_structure_url(WP_Post $p, string $st): string {
    $t=strtotime((string)$p->post_date)?:time();$cat='uncategorized';
    if(str_contains($st,'%category%')&&function_exists('get_the_category')){ $c=get_the_category($p->ID);if($c&&!empty($c[0]->slug))$cat=(string)$c[0]->slug; }
    $au='';if(str_contains($st,'%author%')){ $u=get_userdata((int)$p->post_author);$au=$u?sanitize_title((string)($u->user_nicename?:$u->user_login)):'autor'; }
    $rep=['%year%'=>date('Y',$t),'%monthnum%'=>date('m',$t),'%day%'=>date('d',$t),'%hour%'=>date('H',$t),'%minute%'=>date('i',$t),'%second%'=>date('s',$t),'%post_id%'=>(string)$p->ID,'%postname%'=>$p->post_name!==''?$p->post_name:(string)$p->ID,'%category%'=>$cat,'%author%'=>$au];
    $path=strtr($st,array_map('rawurlencode',$rep)+[]);$path=preg_replace('#/+#','/',$path);
    return home_url('/'.ltrim((string)$path,'/'));
}
/** Passt der Pfad zur Link-Struktur? Gibt die Platzhalter-Werte zurück (year, monthnum, day, post_id, postname, category, author …) oder null. */
function rrw_wp_structure_match(string $path): ?array {
    if(!rrw_wp_link_custom())return null;
    $re=preg_quote(trim(rrw_wp_link_structure(),'/'),'#');
    foreach(RRW_WP_LINK_TOKENS as $tok=>$name){ $q=preg_quote($tok,'#');$pat=match($name){'year'=>'\d{4}','monthnum','day','hour','minute','second'=>'\d{1,2}','post_id'=>'\d+','category'=>'.+?',default=>'[^/]+'};$re=str_replace($q,'(?P<'.$name.'>'.$pat.')',$re); }
    return preg_match('#^'.$re.'/?$#',trim($path,'/'),$m)?array_filter($m,'is_string',ARRAY_FILTER_USE_KEY):null;
}
/** Hash-Form: /#/seite → /seite (läuft nur auf der Startseite, ohne Neuladen-Schleife). */
function rrw_wp_link_hash_script() {
    if(!rrw_wp_link_hash())return;
    $base=(string)(parse_url(home_url('/'),PHP_URL_PATH)?:'/');
    echo '<script>(function(){var h=location.hash,b='.wp_json_encode($base).';if(h.indexOf("#/")===0&&location.pathname===b&&!location.search)location.replace(b+h.slice(2))})();</script>'."\n";
}
/** Schreibt eine alte oder abweichende Beitrags-Adresse auf die eingestellte Form um (nur bei eigener Struktur; sonst null). */
function rrw_wp_canonical_redirect(string $uri): ?string {
    global $wp_query;if(!$wp_query||!rrw_wp_link_custom()||!$wp_query->is_single||str_contains($uri,'?'))return null;
    $o=$wp_query->queried_object;if(!($o instanceof WP_Post)||$o->post_type!=='post'||$o->post_status!=='publish')return null;
    $want=(string)parse_url(rrw_wp_permalink_core($o),PHP_URL_PATH);$have=(string)parse_url($uri,PHP_URL_PATH);
    return rtrim(rawurldecode($want),'/')!==rtrim(rawurldecode($have),'/')?$want:null;
}
function rrw_wp_permalink_for(WP_Post $p): string { return rrw_wp_link_wrap(rrw_wp_permalink_core($p)); }
function rrw_wp_permalink_core(WP_Post $p): string {
    $st=rrw_wp_link_structure();
    if($st===''&&$p->post_type!=='attachment'&&in_array($p->post_type,['post','page'],true)&&$p->post_status==='publish')return home_url('/?'.($p->post_type==='page'?'page_id=':'p=').$p->ID);   // einfach
    if($p->post_type==='post'&&$p->post_status==='publish'&&rrw_wp_link_custom())return rrw_wp_structure_url($p,$st);
    return rrw_wp_permalink_default($p);
}
function rrw_wp_permalink_default(WP_Post $p): string {
    if($p->rrw_source!=='news'&&$p->rrw_source!=='page'&&$p->post_status!=='publish'&&in_array($p->post_status,['draft','pending','auto-draft','future'],true))return home_url('/?'.($p->post_type==='page'?'page_id=':'p=').$p->ID.($p->post_type==='page'||$p->post_type==='post'?'':'&post_type='.rawurlencode($p->post_type)));
    if($p->rrw_source==='news')return home_url('/'.rawurlencode($p->post_name).'/');
    if($p->rrw_source==='page')return home_url('/'.rawurlencode($p->post_name).'/');
    if($p->post_type==='page')return home_url('/'.get_page_uri($p).'/');
    if($p->post_type==='attachment')return $p->guid?:home_url('/?attachment_id='.$p->ID);
    if($p->post_type==='post')return home_url('/'.rawurlencode($p->post_name?:(string)$p->ID).'/');
    $o=get_post_type_object($p->post_type);
    if($o&&$o->public&&$p->post_name!=='')return home_url('/'.rawurlencode($p->post_type).'/'.rawurlencode($p->post_name).'/');
    return home_url('/?p='.$p->ID.'&post_type='.rawurlencode($p->post_type));
}
function get_permalink($post=0, $leavename=false) { $p=get_post($post);if(!$p)return false;return apply_filters($p->post_type==='page'?'page_link':'post_link',rrw_wp_permalink_for($p),$p,$leavename); }
function get_the_permalink($post=0, $leavename=false) { return get_permalink($post,$leavename); }
function the_permalink($post=0) { echo esc_url(apply_filters('the_permalink',get_permalink($post),$post)); }
function get_page_link($post=false, $leavename=false, $sample=false) { return get_permalink($post); }
function get_post_permalink($post=0, $leavename=false, $sample=false) { return get_permalink($post); }
function post_permalink($post=0) { return get_permalink($post); }
function get_post_type_archive_link($post_type) { $o=get_post_type_object($post_type);return $o&&$o->has_archive?home_url('/'.(is_string($o->has_archive)?$o->has_archive:$post_type).'/'):false; }
function get_year_link($y) { return home_url('/'.(int)$y.'/'); }
function get_month_link($y,$m) { return home_url(sprintf('/%d/%02d/',$y,$m)); }
function get_day_link($y,$m,$d) { return home_url(sprintf('/%d/%02d/%02d/',$y,$m,$d)); }
function get_author_posts_url($author_id, $author_nicename='') { $u=get_userdata((int)$author_id);return home_url('/author/'.rawurlencode($u?sanitize_title($u->user_login):(string)$author_nicename).'/'); }
function get_search_link($query='') { return home_url('/?s='.rawurlencode((string)$query)); }
function get_feed_link($feed='') { return home_url('/rss.xml'); }
function get_post_comments_feed_link($id=0,$feed='') { return home_url('/rss.xml'); }
function get_home_template_dummy() {}
function get_shortlink($id=0) { return home_url('/?p='.(int)($id?:get_the_ID())); }

/* ───────── Datum / Autor ───────── */
function get_the_date($format='', $post=null) { $p=get_post($post);if(!$p)return false;$f=$format!==''?$format:get_option('date_format','d.m.Y');return apply_filters('get_the_date',mysql2date($f,$p->post_date),$format,$p); }
function the_date($format='', $before='', $after='', $display=true) { static $last='';$d=get_the_date($format);if($d===$last)return '';$last=(string)$d;$o=$before.$d.$after;if($display)echo $o;else return $o; }
function get_the_time($format='', $post=null) { $p=get_post($post);if(!$p)return false;$f=$format!==''?$format:get_option('time_format','H:i');return apply_filters('get_the_time',mysql2date($f,$p->post_date),$format,$p); }
function the_time($format='') { echo apply_filters('the_time',get_the_time($format),$format); }
function get_post_time($format='U', $gmt=false, $post=null, $translate=false) { $p=get_post($post);if(!$p)return false;return mysql2date($format,$gmt?$p->post_date_gmt:$p->post_date,$translate); }
function get_the_modified_date($format='', $post=null) { $p=get_post($post);if(!$p)return false;$f=$format!==''?$format:get_option('date_format','d.m.Y');return apply_filters('get_the_modified_date',mysql2date($f,$p->post_modified),$format,$p); }
function the_modified_date($format='', $before='', $after='', $display=true) { $d=get_the_modified_date($format);$o=$before.$d.$after;if($display)echo $o;else return $o; }
function get_the_modified_time($format='', $post=null) { $p=get_post($post);if(!$p)return false;$f=$format!==''?$format:get_option('time_format','H:i');return mysql2date($f,$p->post_modified); }
function the_modified_time($format='') { echo get_the_modified_time($format); }
function get_post_modified_time($format='U', $gmt=false, $post=null, $translate=false) { $p=get_post($post);return $p?mysql2date($format,$gmt?$p->post_modified_gmt:$p->post_modified,$translate):false; }
function get_the_author($deprecated='') { global $authordata;$p=get_post();if($p&&$p->rrw_source==='news'&&!empty($p->rrw_data['author']))return apply_filters('the_author',(string)$p->rrw_data['author']);return apply_filters('the_author',is_object($authordata)?(string)$authordata->display_name:''); }
function the_author($deprecated='', $deprecated_echo=true) { $a=get_the_author();if($deprecated_echo)echo $a;return $a; }
function get_the_author_meta($field='', $user_id=false) {
    $u=get_userdata($user_id?:(int)(get_post()->post_author??0));if(!$u)return '';
    $f=strtolower((string)$field);$m=match($f){'id'=>$u->ID,'user_login'=>$u->user_login,'display_name'=>$u->display_name,'user_email'=>$u->user_email,'user_url'=>$u->user_url,'user_nicename'=>$u->user_nicename,'nickname'=>$u->display_name,'description'=>(string)get_user_meta($u->ID,'description',true),default=>get_user_meta($u->ID,$f,true)};
    return apply_filters("get_the_author_{$field}",$m,$user_id);
}
function the_author_meta($field='', $user_id=false) { echo apply_filters("the_author_{$field}",get_the_author_meta($field,$user_id),$user_id); }
function get_the_author_link() { return esc_html(get_the_author()); }
function the_author_link() { echo get_the_author_link(); }
function get_the_author_posts_link() { return '<a href="'.esc_url(get_author_posts_url((int)(get_post()->post_author??0))).'" title="" rel="author">'.esc_html(get_the_author()).'</a>'; }
function the_author_posts_link($deprecated='') { echo get_the_author_posts_link(); }
function get_the_author_posts() { $p=get_post();if(!$p)return 0;return count(get_posts(['author'=>(int)$p->post_author,'numberposts'=>-1,'fields'=>'ids']));  }
function the_author_posts() { echo get_the_author_posts(); }
function is_multi_author() { return count(array_unique(array_map(fn($p)=>$p->post_author,rrw_wp_cms_posts())))>1; }

/* ───────── Kategorien / Schlagwörter ───────── */
function get_the_term_list($post, $taxonomy, $before='', $sep='', $after='') {
    $terms=get_the_terms($post,$taxonomy);if(!$terms)return false;$links=[];
    foreach($terms as $t){ $l=get_term_link($t);if(!is_wp_error($l))$links[]='<a href="'.esc_url($l).'" rel="tag">'.esc_html($t->name).'</a>'; }
    return $before.implode($sep,apply_filters("term_links-{$taxonomy}",$links)).$after;
}
function the_terms($post, $taxonomy, $before='', $sep=', ', $after='') { $l=get_the_term_list($post,$taxonomy,$before,$sep,$after);if($l)echo $l; }
function get_the_category_list($separator='', $parents='', $post_id=false) { $l=get_the_term_list($post_id?:get_the_ID(),'category','',$separator?:', ','');return apply_filters('the_category',$l?:'',$separator,$parents); }
function the_category($separator='', $parents='', $post_id=false) { echo get_the_category_list($separator,$parents,$post_id); }
function get_the_tag_list($before='', $sep='', $after='', $post_id=0) { return apply_filters('the_tags',get_the_term_list($post_id?:get_the_ID(),'post_tag',$before,$sep,$after)); }
function the_tags($before=null, $sep=', ', $after='') { $l=get_the_tag_list($before??'Schlagwörter: ',$sep,$after);if($l)echo $l; }
function single_cat_title($prefix='', $display=true) { $o=get_queried_object();$t=$o instanceof WP_Term?$o->name:'';if($display)echo $prefix.$t;else return $prefix.$t; }
function single_tag_title($prefix='', $display=true) { return single_cat_title($prefix,$display); }
function single_term_title($prefix='', $display=true) { return single_cat_title($prefix,$display); }
function single_post_title($prefix='', $display=true) { $o=get_queried_object();$t=$o instanceof WP_Post?$o->post_title:'';if($display)echo $prefix.$t;else return $prefix.$t; }
function post_type_archive_title($prefix='', $display=true) { $pt=get_query_var('post_type');$o=get_post_type_object(is_array($pt)?reset($pt):$pt);$t=$o?$o->labels->name:'';if($display)echo $prefix.$t;else return $prefix.$t; }
function get_the_archive_title() {
    $t='Archive';
    if(is_category())$t='Kategorie: '.single_cat_title('',false);elseif(is_tag())$t='Schlagwort: '.single_tag_title('',false);elseif(is_author()){ $o=get_queried_object();$t='Autor: '.esc_html($o->display_name??''); }
    elseif(is_year())$t='Jahr: '.get_the_date('Y');elseif(is_month())$t='Monat: '.get_the_date('F Y');elseif(is_day())$t='Tag: '.get_the_date();elseif(is_post_type_archive())$t=post_type_archive_title('',false);elseif(is_tax()){ $o=get_queried_object();$t=($o->name??''); }
    return apply_filters('get_the_archive_title',$t);
}
function the_archive_title($before='', $after='') { $t=get_the_archive_title();if($t)echo $before.$t.$after; }
function get_the_archive_description() { $o=get_queried_object();return apply_filters('get_the_archive_description',$o instanceof WP_Term?(string)$o->description:''); }
function the_archive_description($before='', $after='') { $d=get_the_archive_description();if($d)echo $before.$d.$after; }

/* ───────── CSS-Klassen ───────── */
function get_post_class($class='', $post=null) {
    $p=get_post($post);$classes=[];if($class)$classes=array_merge($classes,preg_split('#\s+#',is_array($class)?implode(' ',$class):$class));
    if(!$p)return array_map('esc_attr',$classes);
    $classes[]='post-'.$p->ID;$classes[]=$p->post_type;$classes[]='type-'.$p->post_type;$classes[]='status-'.$p->post_status;
    if(has_post_thumbnail($p))$classes[]='has-post-thumbnail';
    if($p->post_type==='post'){ foreach((array)get_the_category($p->ID) as $c)$classes[]='category-'.sanitize_html_class($c->slug,$c->term_id);$tg=get_the_tags($p->ID);foreach($tg?:[] as $t)$classes[]='tag-'.sanitize_html_class($t->slug,$t->term_id); }
    $classes[]='hentry';
    return array_map('esc_attr',array_unique(apply_filters('post_class',$classes,$class?(array)$class:[],$p->ID)));
}
function post_class($class='', $post=null) { echo 'class="'.esc_attr(implode(' ',get_post_class($class,$post))).'"'; }
function get_body_class($class='') {
    $c=[];
    if(is_rtl())$c[]='rtl';if(is_front_page())$c[]='home';if(is_home())$c[]='blog';if(is_privacy_policy())$c[]='privacy-policy';if(is_archive())$c[]='archive';if(is_date())$c[]='date';if(is_search())$c[]='search';if(is_paged())$c[]='paged';
    if(is_attachment())$c[]='attachment';if(is_404())$c[]='error404';
    if(is_singular()){ $o=get_queried_object();if($o instanceof WP_Post){ $c[]=$o->post_type.'-template-default';$c[]='single';$c[]='single-'.sanitize_html_class($o->post_type);$c[]='postid-'.$o->ID; if($o->post_type==='page'){ $c[]='page';$c[]='page-id-'.$o->ID; } } }
    elseif(is_category())$c[]='category';elseif(is_tag())$c[]='tag';
    if(is_user_logged_in())$c[]='logged-in';if(is_admin_bar_showing())$c[]='admin-bar';
    if(current_theme_supports('custom-background'))$c[]='custom-background';
    if($class)$c=array_merge($c,preg_split('#\s+#',is_array($class)?implode(' ',$class):$class));
    return array_unique(array_map('esc_attr',apply_filters('body_class',$c,$class?(array)$class:[])));
}
function body_class($class='') { echo 'class="'.esc_attr(implode(' ',get_body_class($class))).'"'; }
function is_admin_bar_showing() { return false; }
function show_admin_bar($f=false) {}
function language_attributes($doctype='html') { echo get_language_attributes($doctype); }
function get_language_attributes($doctype='html') { $a=['lang="'.esc_attr(get_bloginfo_locale()).'"'];return apply_filters('language_attributes',implode(' ',$a),$doctype); }
function get_the_post_thumbnail_caption($post=null) { return ''; }
function post_thumbnail_html_dummy() {}
function the_post_thumbnail($size='post-thumbnail', $attr='') { echo get_the_post_thumbnail(null,$size,$attr); }
function the_post_thumbnail_url($size='post-thumbnail') { echo esc_url((string)get_the_post_thumbnail_url(null,$size)); }

/* ───────── Titelzeile ───────── */
function wp_get_document_title() {
    $t=['title'=>'','page'=>'','tagline'=>'','site'=>get_bloginfo('name','display')];
    if(is_front_page()||is_home()){ $t['title']=get_bloginfo('name','display');$t['tagline']=get_bloginfo('description','display');$t['site']=''; }
    elseif(is_404())$t['title']='Seite nicht gefunden';elseif(is_search())$t['title']=sprintf('Suchergebnisse für „%s“',get_search_query());
    elseif(is_singular()){ $o=get_queried_object();$t['title']=$o?$o->post_title:''; }
    elseif(is_category()||is_tag()||is_tax())$t['title']=single_term_title('',false);elseif(is_author()){ $o=get_queried_object();$t['title']=$o->display_name??''; }
    elseif(is_archive())$t['title']=wp_strip_all_tags(get_the_archive_title());
    if(is_paged())$t['page']='Seite '.max(2,(int)get_query_var('paged'));
    $t=apply_filters('document_title_parts',$t);$sep=apply_filters('document_title_separator','–');
    return apply_filters('document_title',esc_html(implode(" $sep ",array_filter($t))));
}
function _wp_render_title_tag() { if(!current_theme_supports('title-tag'))return;echo '<title>'.wp_get_document_title()."</title>\n"; }
function wp_title($sep='&raquo;', $display=true, $seplocation='') { $t=wp_get_document_title();if($display)echo $t;else return $t; }
function document_title_dummy() {}

/* ───────── Kopf / Fuß / Teile laden ───────── */
function locate_template($template_names, $load=false, $load_once=true, $args=[]) {
    $located='';
    foreach((array)$template_names as $tn){ if(!$tn)continue; foreach([get_stylesheet_directory(),get_template_directory()] as $dir){ if(is_file($dir.'/'.$tn)){$located=$dir.'/'.$tn;break 2;} } }
    if($load&&$located!=='')load_template($located,$load_once,$args);
    return $located;
}
function load_template($_template_file, $load_once=true, $args=[]) {
    global $posts,$post,$wp_did_header,$wp_query,$wp_rewrite,$wpdb,$wp_version,$wp,$id,$comment,$comments,$user_ID;
    if(is_array($wp_query->query_vars??null))extract($wp_query->query_vars,EXTR_SKIP);
    if(isset($s))$s=esc_attr($s);
    // „einmal je Seitenaufruf“: dispatch() setzt die Liste zurück, damit mehrere Aufrufe im selben Prozess (Tests, Vorschau) funktionieren
    if($load_once){ if(isset($GLOBALS['rrw_wp_tpl_loaded'][$_template_file]))return; $GLOBALS['rrw_wp_tpl_loaded'][$_template_file]=true; }
    require $_template_file;
}
function get_template_part($slug, $name=null, $args=[]) {
    do_action("get_template_part_{$slug}",$slug,$name,$args);$t=[];$name=(string)$name;
    if($name!=='')$t[]="{$slug}-{$name}.php";$t[]="{$slug}.php";
    do_action('get_template_part',$slug,$name,$t,$args);
    return locate_template($t,true,false,$args)!==''?null:false;
}
function get_header($name=null, $args=[]) {
    do_action('get_header',$name,$args);$t=[];$name=(string)$name;if($name!=='')$t[]="header-{$name}.php";$t[]='header.php';
    if(locate_template($t,true,true,$args)==='')rrw_wp_default_header();
}
function get_footer($name=null, $args=[]) {
    do_action('get_footer',$name,$args);$t=[];$name=(string)$name;if($name!=='')$t[]="footer-{$name}.php";$t[]='footer.php';
    if(locate_template($t,true,true,$args)==='')rrw_wp_default_footer();
}
function get_sidebar($name=null, $args=[]) {
    do_action('get_sidebar',$name,$args);$t=[];$name=(string)$name;if($name!=='')$t[]="sidebar-{$name}.php";$t[]='sidebar.php';
    if(locate_template($t,true,true,$args)==='')dynamic_sidebar('sidebar-1');
}
function rrw_wp_default_header() { echo '<!doctype html><html '.get_language_attributes().'><head><meta charset="'.esc_attr(get_bloginfo('charset')).'"><meta name="viewport" content="width=device-width, initial-scale=1">';wp_head();echo '</head><body '.get_body_class_attr().'>';wp_body_open(); }
function rrw_wp_default_footer() { wp_footer();echo '</body></html>'; }
function get_body_class_attr() { return 'class="'.esc_attr(implode(' ',get_body_class())).'"'; }
function wp_head() { do_action('wp_head'); }
function wp_footer() { do_action('wp_footer'); }
function wp_body_open() { do_action('wp_body_open'); }
function wp_enqueue_scripts_dummy() {}
function feed_links($args=[]) { echo '<link rel="alternate" type="application/rss+xml" title="'.esc_attr(get_bloginfo('name')).' &raquo; Feed" href="'.esc_url(get_feed_link()).'" />'."\n"; }
function feed_links_extra($args=[]) {}
function rsd_link() {}
function wlwmanifest_link() {}
function wp_generator() { echo '<meta name="generator" content="WordPress '.esc_attr(get_bloginfo('version')).'" />'."\n"; }
function rel_canonical() {
    if(!is_singular())return;$o=get_queried_object();if(!($o instanceof WP_Post))return;
    $c=($o->rrw_source??'')==='news'?(string)($o->rrw_data['canonical_url']??''):'';   // eigene kanonische Adresse des Beitrags (Zweitveröffentlichung)
    echo '<link rel="canonical" href="'.esc_url($c!==''?$c:get_permalink($o)).'" />'."\n";
    if(($o->rrw_source??'')==='news'&&!empty($o->rrw_data['noindex']))echo '<meta name="robots" content="noindex,follow" />'."\n";
}
function wp_resource_hints() {}
function wp_robots() {}
function wp_site_icon() {}
function wp_shortlink_wp_head() {}
function wp_oembed_add_discovery_links() {}
function noindex() {}
function _admin_bar_bump_cb() {}
function get_search_form($args=[]) {
    $args=wp_parse_args($args,['echo'=>true,'aria_label'=>'']);$id=wp_unique_id('search-form-');
    $f='<form role="search" method="get" class="search-form" action="'.esc_url(home_url('/')).'"><label for="'.esc_attr($id).'"><span class="screen-reader-text">Suche nach:</span><input type="search" id="'.esc_attr($id).'" class="search-field" placeholder="Suchen …" value="'.get_search_query().'" name="s" /></label><input type="submit" class="search-submit" value="Suchen" /></form>';
    $f=apply_filters('get_search_form',$f,$args);if($args['echo'])echo $f;else return $f;
}

/* Logo / Kopfbild */
function has_custom_logo($blog_id=0) { return (bool)get_theme_mod('custom_logo')||(bool)rrw_wp_site_logo(); }
function rrw_wp_site_logo(): string { $s=$GLOBALS['RRW_SITE']??[];return (string)($s['branding']['portal_logo']??''); }
function get_custom_logo($blog_id=0) {
    $u=rrw_wp_site_logo();if($u==='')return '';
    $html=sprintf('<a href="%1$s" class="custom-logo-link" rel="home"><img src="%2$s" class="custom-logo" alt="%3$s" decoding="async" /></a>',esc_url(home_url('/')),esc_url(home_url($u)),esc_attr(get_bloginfo('name')));
    return apply_filters('get_custom_logo',$html,$blog_id);
}
function the_custom_logo($blog_id=0) { echo get_custom_logo($blog_id); }
function get_header_image() { return get_theme_mod('header_image',false)?:false; }
function has_header_image() { return (bool)get_header_image(); }
function the_header_image_tag($attr=[]) { return ''; }
function get_header_textcolor() { return get_theme_mod('header_textcolor','000000'); }
function header_textcolor() { echo get_header_textcolor(); }
function display_header_text() { return 'blank'!==get_header_textcolor(); }
function get_background_color() { return get_theme_mod('background_color','ffffff'); }
function get_background_image() { return get_theme_mod('background_image',''); }
function get_custom_header() { return (object)['url'=>'','width'=>0,'height'=>0]; }
function get_theme_support_dummy() {}
function _custom_background_cb() {}
function wp_get_attachment_image_url($id,$size='thumbnail',$icon=false) { return wp_get_attachment_url($id); }
