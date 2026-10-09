<?php
// Ergänzende WordPress-Funktionen (Bereich System, Teil 3): Verwaltung – Rewrite-Marker, Dateibäume, Heartbeat, Einstellungsseiten-Skripte, Export, Plugin-Verwaltung.
// Reine Oberflächen-Funktionen geben kompakte, funktionsfähige Ausgaben oder Standardwerte zurück (jeweils vermerkt).

/* ───────── Rewrite-Regeln und Marker-Blöcke ───────── */
if(!function_exists('got_mod_rewrite')){ function got_mod_rewrite() { return (bool)apply_filters('got_rewrite',apache_mod_loaded('mod_rewrite',true)); } }
if(!function_exists('got_url_rewrite')){ function got_url_rewrite() { return (bool)apply_filters('got_url_rewrite',got_mod_rewrite()||iis7_supports_permalinks()||!empty($GLOBALS['is_nginx'])); } }
if(!function_exists('extract_from_markers')){ function extract_from_markers($filename, $marker) {
    $out=[];if(!is_file($filename))return $out;$in=false;
    foreach(preg_split('/\r\n|\r|\n/',(string)file_get_contents($filename)) as $l){
        if(str_starts_with($l,'# END '.$marker)){ $in=false;continue; }
        if($in)$out[]=$l;
        if(str_starts_with($l,'# BEGIN '.$marker))$in=true;
    }
    return $out;
} }
if(!function_exists('insert_with_markers')){ function insert_with_markers($filename, $marker, $insertion) {
    if(!file_exists($filename)){ if(!is_writable(dirname($filename))||@file_put_contents($filename,'')===false)return false; }
    elseif(!is_writable($filename))return false;
    $ins=is_array($insertion)?$insertion:explode("\n",(string)$insertion);
    $block="# BEGIN $marker\n".implode("\n",$ins)."\n# END $marker";
    $c=(string)file_get_contents($filename);
    $re='/^# BEGIN '.preg_quote($marker,'/').'[ \t]*\r?\n.*?^# END '.preg_quote($marker,'/').'[ \t]*$/ms';
    $n=preg_match($re,$c)?preg_replace_callback($re,fn()=>$block,$c,1):rtrim($c,"\r\n").($c!==''?"\n\n":'').$block."\n";
    return file_put_contents($filename,$n,LOCK_EX)!==false;
} }
if(!function_exists('save_mod_rewrite_rules')){ function save_mod_rewrite_rules() {
    global $wp_rewrite;
    if(is_multisite()||!is_object($wp_rewrite)||!method_exists($wp_rewrite,'mod_rewrite_rules'))return false;
    $rules=trim((string)$wp_rewrite->mod_rewrite_rules());
    if($rules===''||!got_mod_rewrite())return false;   // ohne Regeln nichts schreiben (die .htaccess des CMS bleibt unberührt)
    $f=get_home_path().'.htaccess';
    return insert_with_markers($f,'WordPress',explode("\n",$rules));
} }
if(!function_exists('iis7_save_url_rewrite_rules')){ function iis7_save_url_rewrite_rules() { return false; } }   // IIS wird nicht unterstützt
if(!function_exists('iis7_rewrite_rule_exists')){ function iis7_rewrite_rule_exists($filename) { return false; } }
if(!function_exists('iis7_delete_rewrite_rule')){ function iis7_delete_rewrite_rule($filename) { return false; } }
if(!function_exists('iis7_add_rewrite_rule')){ function iis7_add_rewrite_rule($filename, $rewrite_rule) { return false; } }
if(!function_exists('saveDomDocument')){ function saveDomDocument($doc, $filename) {
    $doc->formatOutput=true;$x=$doc->saveXML();if($x===false)return false;
    return file_put_contents($filename,str_replace("\r\n","\n",$x))!==false;
} }
if(!function_exists('update_home_siteurl')){ function update_home_siteurl($old_value, $value) { if(wp_installing())return;flush_rewrite_rules(); } }

/* ───────── Hilfen für Verwaltungsseiten ───────── */
if(!function_exists('update_recently_edited')){ function update_recently_edited($file) {
    $old=(array)get_option('recently_edited');
    if($old){ array_unshift($old,$file);$old=array_values(array_unique($old));if(count($old)>5)array_pop($old); } else $old[]=$file;
    update_option('recently_edited',$old);
} }
if(!function_exists('elvado_ext_file_tree')){ function elvado_ext_file_tree($files) {   // Pfadliste (oder relativ=>voll) in verschachtelten Baum umwandeln
    $tree=[];
    foreach((array)$files as $k=>$v){
        $rel=is_string($k)?$k:(string)$v;$parts=explode('/',$rel);$ref=&$tree;
        foreach($parts as $i=>$p){ if($i===count($parts)-1){ $ref[$p]=$rel; } else { if(!isset($ref[$p])||!is_array($ref[$p]))$ref[$p]=[];$ref=&$ref[$p]; } }
        unset($ref);
    }
    return $tree;
} }
if(!function_exists('elvado_ext_print_tree')){ function elvado_ext_print_tree($tree, $page, $qkey, $qval, $current) {
    foreach($tree as $label=>$node){
        if(is_array($node)){ echo '<li role="treeitem" aria-expanded="true"><span class="folder-label">'.esc_html($label).'</span><ul role="group" class="tree-folder">';elvado_ext_print_tree($node,$page,$qkey,$qval,$current);echo '</ul></li>';continue; }
        $url=add_query_arg(['file'=>rawurlencode($node),$qkey=>rawurlencode($qval)],self_admin_url($page));
        echo '<li role="none" class="'.($node===$current?'current-file':'').'"><a role="treeitem" href="'.esc_url($url).'">'.esc_html($label).'</a></li>';
    }
} }
if(!function_exists('wp_make_theme_file_tree')){ function wp_make_theme_file_tree($allowed_files) { return elvado_ext_file_tree($allowed_files); } }
if(!function_exists('wp_make_plugin_file_tree')){ function wp_make_plugin_file_tree($plugin_editable_files) { return elvado_ext_file_tree($plugin_editable_files); } }
if(!function_exists('wp_print_theme_file_tree')){ function wp_print_theme_file_tree($tree, $level=2, $size=1, $index=1) {
    global $relative_file,$stylesheet;if(!is_array($tree))return;
    elvado_ext_print_tree($tree,'theme-editor.php','theme',(string)($stylesheet??get_stylesheet()),(string)($relative_file??''));
} }
if(!function_exists('wp_print_plugin_file_tree')){ function wp_print_plugin_file_tree($tree, $label='', $level=2, $size=1, $index=1) {
    global $file,$plugin;if(!is_array($tree))return;
    elvado_ext_print_tree($tree,'plugin-editor.php','plugin',(string)($plugin??''),(string)($file??''));
} }
if(!function_exists('wp_reset_vars')){ function wp_reset_vars($vars) {
    foreach((array)$vars as $v)$GLOBALS[$v]=!empty($_POST[$v])?$_POST[$v]:(!empty($_GET[$v])?$_GET[$v]:'');
} }
if(!function_exists('show_message')){ function show_message($message) {   // Fortschrittsmeldung ausgeben (ohne offene Ausgabepuffer zu schließen)
    if(is_wp_error($message)){ $d=$message->get_error_data();$message=$message->get_error_message().($d&&is_string($d)?': '.$d:''); }
    echo '<p>'.$message."</p>\n";@flush();
} }
if(!function_exists('wp_doc_link_parse')){ function wp_doc_link_parse($content) {
    if(!is_string($content)||$content===''||!function_exists('token_get_all'))return [];
    $tok=array_values(array_filter(@token_get_all($content),fn($t)=>!is_array($t)||!in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)));
    $fn=[];$ign=[];
    for($i=0,$n=count($tok);$i<$n-1;$i++){
        if(!is_array($tok[$i])||$tok[$i][0]!==T_STRING||$tok[$i+1]!=='(')continue;
        $prev=$tok[$i-1]??null;$name=$tok[$i][1];
        if(is_array($prev)&&in_array($prev[0],[T_FUNCTION,T_NEW,T_OBJECT_OPERATOR,T_DOUBLE_COLON,T_NULLSAFE_OBJECT_OPERATOR],true))$ign[]=$name;   // selbst definiert / Methode: keine Dokumentation
        $fn[]=$name;
    }
    $ign=array_unique((array)apply_filters('documentation_ignore_functions',$ign));$fn=array_unique($fn);sort($fn);
    return array_values(array_filter($fn,fn($f)=>!in_array($f,$ign,true)));
} }
if(!function_exists('admin_color_scheme_picker')){ function admin_color_scheme_picker($user_id) {
    global $_wp_admin_css_colors;if(empty($_wp_admin_css_colors)||!is_array($_wp_admin_css_colors))return;
    $cur=get_user_option('admin_color',$user_id)?:'fresh';
    echo '<fieldset id="color-picker" class="scheme-list"><legend class="screen-reader-text"><span>'.esc_html__('Administration Color Scheme').'</span></legend>';
    foreach($_wp_admin_css_colors as $key=>$c){
        echo '<div class="color-option'.($key===$cur?' selected':'').'"><input name="admin_color" id="admin_color_'.esc_attr($key).'" type="radio" value="'.esc_attr($key).'" class="tog"'.checked($key,$cur,false).' /><label for="admin_color_'.esc_attr($key).'">'.esc_html($c->name??$key).'</label><div class="color-palette">';
        foreach((array)($c->colors??[]) as $col)echo '<div class="color-palette-shade" style="background-color:'.esc_attr($col).'">&nbsp;</div>';
        echo '</div></div>';
    }
    echo '</fieldset>';
} }
if(!function_exists('wp_color_scheme_settings')){ function wp_color_scheme_settings() {
    global $_wp_admin_css_colors;$s=get_user_option('admin_color')?:'fresh';
    $icons=isset($_wp_admin_css_colors[$s]->icon_colors)?$_wp_admin_css_colors[$s]->icon_colors:['base'=>'#a7aaad','focus'=>'#72aee6','current'=>'#fff'];
    echo '<script>var _wpColorScheme = '.wp_json_encode(['icons'=>$icons]).";</script>\n";
} }
if(!function_exists('wp_admin_viewport_meta')){ function wp_admin_viewport_meta() { $v=apply_filters('admin_viewport_meta','width=device-width,initial-scale=1.0');if(empty($v))return;echo '<meta name="viewport" content="'.esc_attr($v).'">'."\n"; } }
if(!function_exists('_customizer_mobile_viewport_meta')){ function _customizer_mobile_viewport_meta($viewport_meta) { return trim((string)$viewport_meta,',').',minimum-scale=0.5,maximum-scale=1.2'; } }
if(!function_exists('wp_admin_canonical_url')){ function wp_admin_canonical_url() {
    $rm=array_merge(wp_removable_query_args(),['_wp_http_referer','_wpnonce']);
    $cur=(is_ssl()?'https://':'http://').($_SERVER['HTTP_HOST']??'').($_SERVER['REQUEST_URI']??'');
    $new=remove_query_arg($rm,$cur);if($new===$cur)return;
    echo '<link id="wp-admin-canonical" rel="canonical" href="'.esc_url($new).'" />'."\n".'<script>if(window.history.replaceState){window.history.replaceState(null,null,document.getElementById("wp-admin-canonical").href+window.location.hash);}</script>'."\n";
} }
if(!function_exists('wp_page_reload_on_back_button_js')){ function wp_page_reload_on_back_button_js() {
    echo '<script>if(typeof performance!=="undefined"&&performance.navigation&&performance.navigation.type===2){document.location.reload(true);}</script>'."\n";
} }
if(!function_exists('update_option_new_admin_email')){ function update_option_new_admin_email($old_value, $value) {
    if(get_option('admin_email')===$value||!is_email($value))return;
    $hash=md5($value.time().wp_rand());update_option('adminhash',['hash'=>$hash,'newemail'=>$value]);
    $url=esc_url(self_admin_url('options.php?adminhash='.$hash));$site=wp_specialchars_decode((string)get_option('blogname'),ENT_QUOTES);
    $c=apply_filters('new_admin_email_content',sprintf(__("Howdy ###USERNAME###,\n\nSomeone with administrator capabilities recently requested to have the\nadministration email address changed on this site:\n###SITEURL###\n\nTo confirm this change, please click on the following link:\n###ADMIN_URL###\n\nYou can safely ignore and delete this email if you do not want to\ntake this action.\n\nThis email has been sent to ###EMAIL###\n\nRegards,\nAll at ###SITENAME###\n###SITEURL###")),$hash,['hash'=>$hash,'newemail'=>$value]);
    $u=wp_get_current_user();
    $c=str_replace(['###USERNAME###','###ADMIN_URL###','###EMAIL###','###SITENAME###','###SITEURL###'],[$u->user_login??'',$url,$value,$site,home_url()],$c);
    wp_mail($value,sprintf(__('[%s] New Admin Email Address'),$site),$c);
} }
if(!function_exists('_wp_privacy_settings_filter_draft_page_titles')){ function _wp_privacy_settings_filter_draft_page_titles($title, $page) { return $page->post_status!=='publish'&&$page->post_status!==''?sprintf(__('%s (Draft)'),$title):$title; } }
if(!function_exists('wp_check_php_version')){ function wp_check_php_version() {   // ohne Netzabruf: feste Richtwerte
    $min='7.4';$rec='8.3';$sec='8.1';
    return ['recommended_version'=>$rec,'minimum_version'=>$min,'is_supported'=>version_compare(PHP_VERSION,$sec,'>='),'is_secure'=>version_compare(PHP_VERSION,$sec,'>='),'is_acceptable'=>version_compare(PHP_VERSION,$min,'>='),'is_lower_than_future_minimum'=>version_compare(PHP_VERSION,'7.4','<')];
} }

/* ───────── Heartbeat ───────── */
if(!function_exists('wp_check_locked_posts')){ function wp_check_locked_posts($response, $data, $screen_id) {
    $checked=[];
    if(array_key_exists('wp-check-locked-posts',$data)&&is_array($data['wp-check-locked-posts'])){
        foreach($data['wp-check-locked-posts'] as $key){
            $post_id=absint(substr((string)$key,5));if(!$post_id)continue;
            $uid=wp_check_post_lock($post_id);$user=$uid?get_userdata($uid):false;
            if($user&&current_user_can('edit_post',$post_id)){
                $send=['name'=>$user->display_name,'text'=>sprintf(__('%s is currently editing'),$user->display_name)];
                $checked[$key]=apply_filters('wp_check_post_lock_window',$send,$post_id,$user);   // Filter wie bei WordPress
            }
        }
    }
    if($checked)$response['wp-check-locked-posts']=$checked;
    return $response;
} }
if(!function_exists('wp_refresh_post_lock')){ function wp_refresh_post_lock($response, $data, $screen_id) {
    if(!array_key_exists('wp-refresh-post-lock',$data))return $response;
    $rec=$data['wp-refresh-post-lock'];$send=[];$post_id=absint($rec['post_id']??0);
    if(!$post_id||!current_user_can('edit_post',$post_id))return $response;
    $uid=wp_check_post_lock($post_id);$user=$uid?get_userdata($uid):false;
    if($user)$send['lock_error']=['name'=>$user->display_name];
    else{ $new=wp_set_post_lock($post_id);if($new)$send['new_lock']=implode(':',$new); }
    $response['wp-refresh-post-lock']=$send;return $response;
} }
if(!function_exists('wp_refresh_post_nonces')){ function wp_refresh_post_nonces($response, $data, $screen_id) {
    if(!array_key_exists('wp-refresh-post-nonces',$data))return $response;
    $rec=$data['wp-refresh-post-nonces'];$response['wp-refresh-post-nonces']=['check'=>1];
    $post_id=absint($rec['post_id']??0);if(!$post_id||!current_user_can('edit_post',$post_id))return $response;
    $response['wp-refresh-post-nonces']=['replace'=>['getpermalinknonce'=>wp_create_nonce('getpermalink'),'samplepermalinknonce'=>wp_create_nonce('samplepermalink'),'closedpostboxesnonce'=>wp_create_nonce('closedpostboxes'),'_ajax_linking_nonce'=>wp_create_nonce('internal-linking'),'_wpnonce'=>wp_create_nonce('update-post_'.$post_id)],'heartbeatNonce'=>wp_create_nonce('heartbeat-nonce')];
    return $response;
} }
if(!function_exists('wp_refresh_metabox_loader_nonces')){ function wp_refresh_metabox_loader_nonces($response, $data) {
    if(empty($data['wp-refresh-metabox-loader-nonces']))return $response;
    $post_id=absint($data['wp-refresh-metabox-loader-nonces']['post_id']??0);
    if(!$post_id||!current_user_can('edit_post',$post_id))return $response;
    $response['wp-refresh-metabox-loader-nonces']=['replace'=>['metabox_loader_nonce'=>wp_create_nonce('meta-box-loader'),'_wpnonce'=>wp_create_nonce('update-post_'.$post_id)]];
    return $response;
} }
if(!function_exists('wp_refresh_heartbeat_nonces')){ function wp_refresh_heartbeat_nonces($response) { $response['rest_nonce']=wp_create_nonce('wp_rest');$response['heartbeat_nonce']=wp_create_nonce('heartbeat-nonce');return $response; } }
if(!function_exists('wp_heartbeat_set_suspension')){ function wp_heartbeat_set_suspension($settings) { global $pagenow;if($pagenow==='post.php'||$pagenow==='post-new.php')$settings['suspension']='disable';return $settings; } }
if(!function_exists('heartbeat_autosave')){ function heartbeat_autosave($response, $data) {   // Entwürfe speichern; veröffentlichte Beiträge werden nicht still überschrieben
    if(empty($data['wp_autosave']))return $response;
    $p=$data['wp_autosave'];$id=absint($p['post_id']??0);$saved=false;
    if($id&&current_user_can('edit_post',$id)){
        $post=get_post($id);
        if($post&&in_array($post->post_status,['draft','auto-draft','pending'],true)){
            $upd=['ID'=>$id];foreach(['post_title','post_content','post_excerpt'] as $f)if(isset($p[$f]))$upd[$f]=wp_unslash($p[$f]);
            if($post->post_status==='auto-draft')$upd['post_status']='draft';
            $r=wp_update_post($upd,true);$saved=!is_wp_error($r)&&$r;
        }
    }
    $response['wp_autosaved']=$saved;
    if($saved)$response['wp_autosave']=['success'=>true,'message'=>sprintf(__('Draft saved at %s.'),date_i18n(__('g:i:s a')))];
    return $response;
} }

/* ───────── Einstellungsseiten (Skripte) ───────── */
if(!function_exists('options_general_add_js')){ function options_general_add_js() {
    echo '<script>(function(){["date_format","time_format"].forEach(function(n){var c=document.querySelector("input[name="+n+"_custom]"),r=document.querySelectorAll("input[name="+n+"]");if(!c)return;r.forEach(function(x){x.addEventListener("click",function(){if(x.value!=="custom")c.value=x.value})});c.addEventListener("input",function(){var cu=document.getElementById(n+"_custom_radio");if(cu)cu.checked=true})})})();</script>'."\n";
} }
if(!function_exists('options_reading_add_js')){ function options_reading_add_js() {
    echo '<script>(function(){var s=document.querySelectorAll("input[name=show_on_front]");function u(){var p=document.querySelector("input[name=show_on_front]:checked"),on=p&&p.value==="page";["page_on_front","page_for_posts"].forEach(function(i){var e=document.getElementById(i);if(e)e.disabled=!on})}s.forEach(function(x){x.addEventListener("change",u)});u()})();</script>'."\n";
} }
if(!function_exists('options_discussion_add_js')){ function options_discussion_add_js() {
    echo '<script>(function(){function t(a,b){var x=document.getElementById(a),y=document.getElementById(b);if(!x||!y)return;function u(){y.disabled=!x.checked}x.addEventListener("change",u);u()}t("thread_comments","thread_comments_depth");t("page_comments","comments_per_page");t("comment_moderation","comment_max_links")})();</script>'."\n";
} }
if(!function_exists('options_reading_blog_charset')){ function options_reading_blog_charset() { echo '<input name="blog_charset" type="hidden" id="blog_charset" value="'.esc_attr((string)get_option('blog_charset')).'" />'; } }

/* ───────── Import/Export ───────── */
if(!function_exists('get_cli_args')){ function get_cli_args($param, $required=false) {
    $args=$_SERVER['argv']??[];$out=[];$last=null;
    for($i=1,$n=count($args);$i<$n;$i++){
        $a=(string)$args[$i];
        if(preg_match('/^--([^=]+)(?:=(.*))?$/s',$a,$m)){ $k=preg_replace('/[^a-z0-9]+/i','',$m[1]);if(isset($m[2])){ $out[$k]=$m[2];$last=null; } else { $out[$k]=true;$last=$k; } }
        elseif(preg_match('/^-([a-zA-Z0-9]+)$/',$a,$m)){ foreach(str_split($m[1]) as $c)$out[$c]=true;$last=substr($m[1],-1); }
        elseif($last!==null){ $out[$last]=$a;$last=null; }
    }
    if(isset($out[$param]))return $out[$param];
    if($required){ echo "\"$param\" parameter is required but was not specified\n";if(!empty($GLOBALS['elvado_wp_die_throws']))throw new ELVADO_WP_Die('cli',1);exit(1); }
    return null;
} }
if(!function_exists('export_wp')){ function export_wp($args=[]) {
    $a=wp_parse_args($args,['content'=>'all','author'=>false,'category'=>false,'start_date'=>false,'end_date'=>false,'status'=>false]);
    $a=apply_filters('export_args',$a);do_action('export_wp_start',$a);
    $x=fn($s)=>htmlspecialchars((string)$s,ENT_XML1|ENT_QUOTES,'UTF-8');$cd=fn($s)=>'<![CDATA['.str_replace(']]>',']]]]><![CDATA[>',(string)$s).']]>';
    $types=$a['content']==='all'?array_values(array_diff(get_post_types(['public'=>true]),['attachment'])):($a['content']?[(string)$a['content']]:['post']);
    if($a['content']==='all')$types=array_values(array_unique(array_merge($types,['post','page'])));
    if(!headers_sent()){ header('Content-Description: File Transfer');header('Content-Disposition: attachment; filename='.sanitize_file_name(get_bloginfo('name')?:'export').'.WordPress.'.gmdate('Y-m-d').'.xml');header('Content-Type: text/xml; charset='.get_option('blog_charset','UTF-8'),true); }
    $posts=[];
    foreach((array)get_posts(['post_type'=>$types,'post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','suppress_filters'=>true]) as $p){
        if($a['status']&&$p->post_status!==$a['status'])continue;
        if($a['author']&&(int)$p->post_author!==(int)$a['author'])continue;
        if($a['start_date']&&strtotime($p->post_date)<strtotime((string)$a['start_date']))continue;
        if($a['end_date']&&strtotime($p->post_date)>strtotime((string)$a['end_date'].' 23:59:59'))continue;
        if($a['category']){ $t=term_exists($a['category'],'category');if($t&&!has_term(is_array($t)?$t['term_id']:$t,'category',$p))continue; }
        $posts[]=$p;
    }
    $ids=[];foreach($posts as $p)$ids[(int)$p->post_author]=1;
    echo '<?xml version="1.0" encoding="'.$x(get_option('blog_charset','UTF-8')).'" ?>'."\n";
    echo '<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">'."\n<channel>\n";
    echo '<title>'.$x(get_bloginfo('name')).'</title><link>'.$x(get_bloginfo('url')).'</link><description>'.$x(get_bloginfo('description')).'</description><pubDate>'.gmdate('D, d M Y H:i:s +0000').'</pubDate><language>'.$x(get_bloginfo('language')).'</language>'."\n";
    echo '<wp:wxr_version>1.2</wp:wxr_version><wp:base_site_url>'.$x(site_url()).'</wp:base_site_url><wp:base_blog_url>'.$x(home_url()).'</wp:base_blog_url>'."\n";
    foreach(array_keys($ids) as $uid){ $u=get_userdata($uid);if(!$u)continue;
        echo '<wp:author><wp:author_id>'.(int)$u->ID.'</wp:author_id><wp:author_login>'.$cd($u->user_login).'</wp:author_login><wp:author_email>'.$cd($u->user_email).'</wp:author_email><wp:author_display_name>'.$cd($u->display_name).'</wp:author_display_name></wp:author>'."\n"; }
    if($a['content']==='all'||$a['content']==='post'){
        foreach((array)get_terms(['taxonomy'=>'category','hide_empty'=>false]) as $t){ if(!is_object($t))continue;echo '<wp:category><wp:term_id>'.(int)$t->term_id.'</wp:term_id><wp:category_nicename>'.$cd($t->slug).'</wp:category_nicename><wp:category_parent>'.$cd('').'</wp:category_parent><wp:cat_name>'.$cd($t->name).'</wp:cat_name></wp:category>'."\n"; }
        foreach((array)get_terms(['taxonomy'=>'post_tag','hide_empty'=>false]) as $t){ if(!is_object($t))continue;echo '<wp:tag><wp:term_id>'.(int)$t->term_id.'</wp:term_id><wp:tag_slug>'.$cd($t->slug).'</wp:tag_slug><wp:tag_name>'.$cd($t->name).'</wp:tag_name></wp:tag>'."\n"; }
    }
    echo '<generator>'.$x(get_bloginfo('url')).'?v='.$x(get_bloginfo('version')).'</generator>'."\n";
    foreach($posts as $p){
        $u=get_userdata((int)$p->post_author);
        echo "<item>\n<title>".$x(apply_filters('the_title_rss',$p->post_title)).'</title><link>'.$x(get_permalink($p)).'</link><pubDate>'.$x(mysql2date('D, d M Y H:i:s +0000',$p->post_date_gmt?:$p->post_date,false)).'</pubDate><dc:creator>'.$cd($u?$u->user_login:'').'</dc:creator><guid isPermaLink="false">'.$x(get_the_guid($p)).'</guid><description></description><content:encoded>'.$cd($p->post_content).'</content:encoded><excerpt:encoded>'.$cd($p->post_excerpt).'</excerpt:encoded>';
        echo '<wp:post_id>'.(int)$p->ID.'</wp:post_id><wp:post_date>'.$cd($p->post_date).'</wp:post_date><wp:post_date_gmt>'.$cd($p->post_date_gmt).'</wp:post_date_gmt><wp:post_modified>'.$cd($p->post_modified).'</wp:post_modified><wp:post_modified_gmt>'.$cd($p->post_modified_gmt).'</wp:post_modified_gmt><wp:comment_status>'.$cd($p->comment_status).'</wp:comment_status><wp:ping_status>'.$cd($p->ping_status).'</wp:ping_status><wp:post_name>'.$cd($p->post_name).'</wp:post_name><wp:status>'.$cd($p->post_status).'</wp:status><wp:post_parent>'.(int)$p->post_parent.'</wp:post_parent><wp:menu_order>'.(int)$p->menu_order.'</wp:menu_order><wp:post_type>'.$cd($p->post_type).'</wp:post_type><wp:post_password>'.$cd($p->post_password).'</wp:post_password><wp:is_sticky>'.(function_exists('is_sticky')&&is_sticky($p->ID)?1:0).'</wp:is_sticky>'."\n";
        foreach(get_object_taxonomies($p->post_type) as $tax)foreach((array)wp_get_object_terms($p->ID,$tax) as $t){ if(!is_object($t))continue;echo '<category domain="'.$x($tax).'" nicename="'.$x($t->slug).'">'.$cd($t->name).'</category>'."\n"; }
        foreach((array)get_post_custom($p->ID) as $k=>$vals){ if(is_protected_meta($k,'post')&&$k!=='_thumbnail_id')continue;foreach((array)$vals as $v)echo '<wp:postmeta><wp:meta_key>'.$cd($k).'</wp:meta_key><wp:meta_value>'.$cd($v).'</wp:meta_value></wp:postmeta>'."\n"; }
        echo "</item>\n";
    }
    echo "</channel>\n</rss>\n";
} }
if(!function_exists('get_the_guid')){ function get_the_guid($post=0) { $p=get_post($post);return $p?(string)apply_filters('get_the_guid',$p->guid?:home_url('/?p='.$p->ID),$p->ID):''; } }
