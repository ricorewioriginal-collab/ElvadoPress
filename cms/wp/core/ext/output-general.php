<?php
// Ergänzende Funktionen der Seitenausgabe (Bereich Ausgabe): Anmeldeformular, Datumsausgaben, Robots-Meta, Shortcode-Hilfen, Navigationsmenü-Hilfen, Admin-Farbschemata.
// Reine Verwaltungsoberflächen (TinyMCE, Code-Editor, Heartbeat) liefern sinnvolle Standardwerte.

/* ───────── Anmelden / Seite ───────── */
if(!function_exists('wp_registration_url')){ function wp_registration_url() { return apply_filters('register_url',site_url('wp-login.php?action=register','login')); } }
if(!function_exists('wp_login_form')){
    function wp_login_form($args=[]) {
        $req=(is_ssl()?'https://':'http://').($_SERVER['HTTP_HOST']??'localhost').($_SERVER['REQUEST_URI']??'/');
        $d=['echo'=>true,'redirect'=>$req,'form_id'=>'loginform','label_username'=>'Benutzername oder E-Mail-Adresse','label_password'=>'Passwort','label_remember'=>'Angemeldet bleiben','label_log_in'=>'Anmelden','id_username'=>'user_login','id_password'=>'user_pass','id_remember'=>'rememberme','id_submit'=>'wp-submit','remember'=>true,'value_username'=>'','value_remember'=>false];
        $a=wp_parse_args($args,apply_filters('login_form_defaults',$d));
        $top=apply_filters('login_form_top','',$a);$mid=apply_filters('login_form_middle','',$a);$bot=apply_filters('login_form_bottom','',$a);
        $f=sprintf('<form name="%1$s" id="%1$s" action="%2$s" method="post">',esc_attr($a['form_id']),esc_url(site_url('wp-login.php','login_post'))).$top
            .sprintf('<p class="login-username"><label for="%1$s">%2$s</label><input type="text" name="log" id="%1$s" autocomplete="username" class="input" value="%3$s" size="20" /></p>',esc_attr($a['id_username']),esc_html($a['label_username']),esc_attr($a['value_username']))
            .sprintf('<p class="login-password"><label for="%1$s">%2$s</label><input type="password" name="pwd" id="%1$s" autocomplete="current-password" spellcheck="false" class="input" value="" size="20" /></p>',esc_attr($a['id_password']),esc_html($a['label_password']))
            .$mid
            .($a['remember']?sprintf('<p class="login-remember"><label><input name="rememberme" type="checkbox" id="%1$s" value="forever"%2$s /> %3$s</label></p>',esc_attr($a['id_remember']),$a['value_remember']?' checked="checked"':'',esc_html($a['label_remember'])):'')
            .sprintf('<p class="login-submit"><input type="submit" name="wp-submit" id="%1$s" class="button button-primary" value="%2$s" /><input type="hidden" name="redirect_to" value="%3$s" /></p>',esc_attr($a['id_submit']),esc_attr($a['label_log_in']),esc_url($a['redirect']))
            .$bot.'</form>';
        if($a['echo'])echo $f;else return $f;
    }
}
if(!function_exists('site_icon_url')){ function site_icon_url($size=512, $url='', $blog_id=0) { echo esc_url(get_site_icon_url($size,$url,$blog_id)); } }
if(!function_exists('get_the_post_type_description')){
    function get_the_post_type_description() {
        $pt=get_query_var('post_type');if(is_array($pt))$pt=reset($pt);
        $o=$pt?get_post_type_object($pt):null;$desc=$o->description??'';
        return apply_filters('get_the_post_type_description',$desc,$o);
    }
}
if(!function_exists('the_search_query')){ function the_search_query() { echo esc_attr(apply_filters('the_search_query',get_search_query(false))); } }
if(!function_exists('the_generator')){ function the_generator($type='') { echo apply_filters('the_generator',get_the_generator($type),$type); } }
if(!function_exists('wp_strict_cross_origin_referrer')){ function wp_strict_cross_origin_referrer() { echo "<meta name='referrer' content='strict-origin-when-cross-origin' />\n"; } }
if(!function_exists('wp_preload_resources')){
    /** Gibt <link rel="preload"> für die per Filter „wp_preload_resources“ gelieferten Ressourcen aus (href und as sind Pflicht). */
    function wp_preload_resources() {
        $as_ok=['audio','document','embed','fetch','font','image','object','script','style','track','video','worker'];$attrs=['as','crossorigin','href','imagesrcset','imagesizes','type','media'];
        foreach((array)apply_filters('wp_preload_resources',[]) as $r){
            if(!is_array($r)||empty($r['href'])||empty($r['as'])||!in_array($r['as'],$as_ok,true))continue;
            $h="<link rel='preload'";
            foreach($r as $k=>$v){ if(!in_array($k,$attrs,true)||$v===''||$v===null)continue;$h.=' '.$k."='".($k==='href'?esc_url($v):esc_attr($v))."'"; }
            echo $h." />\n";
        }
    }
}
if(!function_exists('wp_dependencies_unique_hosts')){
    /** Fremde Hosts der eingereihten Skripte/Stile (für dns-prefetch). */
    function wp_dependencies_unique_hosts() {
        $hosts=[];$own=$_SERVER['SERVER_NAME']??(string)wp_parse_url(home_url(),PHP_URL_HOST);
        foreach(['elvado_wp_scripts','elvado_wp_styles'] as $k)foreach(array_keys($GLOBALS[$k]['queue']??[]) as $h){
            $src=$GLOBALS[$k]['reg'][$h]['src']??'';$host=is_string($src)?wp_parse_url($src,PHP_URL_HOST):'';
            if($host&&$host!==$own&&!in_array($host,$hosts,true))$hosts[]=$host;
        }
        return $hosts;
    }
}
if(!function_exists('wp_required_field_indicator')){ function wp_required_field_indicator() { return apply_filters('wp_required_field_indicator','<span class="required">'.esc_html('*').'</span>'); } }
if(!function_exists('wp_required_field_message')){
    function wp_required_field_message() { return apply_filters('wp_required_field_message',sprintf('<span class="required-field-message">%s</span>',sprintf('Pflichtfelder sind mit %s markiert',wp_required_field_indicator()))); }
}
if(!function_exists('wp_heartbeat_settings')){
    function wp_heartbeat_settings($settings) {
        if(!is_admin())$settings['ajaxurl']=admin_url('admin-ajax.php','relative');
        if(is_user_logged_in())$settings['nonce']=wp_create_nonce('heartbeat-nonce');
        return $settings;
    }
}

/* ───────── Editor (kein TinyMCE in der Schicht) ───────── */
if(!function_exists('user_can_richedit')){ function user_can_richedit() { return (bool)apply_filters('user_can_richedit',false); } }   // Standardwert: Textbereich statt TinyMCE
if(!function_exists('wp_default_editor')){ function wp_default_editor() { return apply_filters('wp_default_editor',user_can_richedit()?'tinymce':'html'); } }
if(!function_exists('wp_get_code_editor_settings')){
    /** Einstellungen für einen Code-Editor (CodeMirror) nach Dateityp; ohne Typ/Mime false. Das Skript selbst liefert die Schicht nicht. */
    function wp_get_code_editor_settings($args) {
        $args=wp_parse_args($args,['type'=>'','file'=>null,'theme'=>null,'codemirror'=>[]]);
        $m=['css'=>'text/css','html'=>'text/html','js'=>'text/javascript','javascript'=>'text/javascript','json'=>'application/json','php'=>'application/x-httpd-php','xml'=>'application/xml','svg'=>'application/xml','md'=>'text/x-markdown','txt'=>'text/plain'];
        $type=(string)$args['type'];if($type===''&&is_string($args['file']))$type=strtolower(pathinfo($args['file'],PATHINFO_EXTENSION));
        $mime=str_contains($type,'/')?$type:($m[$type]??'');if($mime==='')return false;
        $s=['codemirror'=>array_merge(['mode'=>$mime,'indentUnit'=>4,'indentWithTabs'=>true,'inputStyle'=>'contenteditable','lineNumbers'=>true,'lineWrapping'=>true,'styleActiveLine'=>true,'continueComments'=>true,'direction'=>'ltr','gutters'=>[]],(array)$args['codemirror']),'csslint'=>['errors'=>true,'box-model'=>true,'display-property-grouping'=>true,'duplicate-properties'=>true,'known-properties'=>true,'outline-none'=>true],'jshint'=>['esversion'=>11,'module'=>false],'htmlhint'=>['tagname-lowercase'=>true,'attr-lowercase'=>true,'attr-value-double-quotes'=>false,'doctype-first'=>false,'tag-pair'=>true,'spec-char-escape'=>true,'id-unique'=>true]];
        return apply_filters('wp_code_editor_settings',$s,$args);
    }
}

/* ───────── Kalender / Datum ───────── */
if(!function_exists('calendar_week_mod')){ function calendar_week_mod($num) { return $num-7*floor($num/7); } }
if(!function_exists('delete_get_calendar_cache')){ function delete_get_calendar_cache() { wp_cache_delete('get_calendar','calendar'); } }
if(!function_exists('allowed_tags')){
    /** Erlaubte Tags/Attribute (Kommentare) als Text, wie sie im Formular stehen. */
    function allowed_tags() {
        global $allowedtags;$t=$allowedtags?:wp_kses_allowed_html('data');$out='';
        foreach((array)$t as $tag=>$attrs){ $out.='<'.$tag;foreach((array)$attrs as $a=>$l)$out.=' '.$a.'=""';$out.='> '; }
        return htmlentities($out);
    }
}
if(!function_exists('the_date_xml')){ function the_date_xml() { echo mysql2date('Y-m-d',get_post()->post_date,false); } }
if(!function_exists('the_weekday')){ function the_weekday() { echo apply_filters('the_weekday',mysql2date('l',get_post()->post_date)); } }
if(!function_exists('the_weekday_date')){
    function the_weekday_date($before='', $after='') {
        $p=get_post();if(!$p)return;
        $day=mysql2date('d.m.y',$p->post_date,false);$out='';
        if($day!==($GLOBALS['previousweekday']??null)){ $out=$before.mysql2date('l',$p->post_date).$after;$GLOBALS['previousweekday']=$day; }
        echo apply_filters('the_weekday_date',$out,$before,$after);
    }
}

/* ───────── Admin-Farbschemata ───────── */
if(!function_exists('wp_admin_css_color')){
    function wp_admin_css_color($key, $name, $url, $colors=[], $icons=[]) {
        global $_wp_admin_css_colors;if(!isset($_wp_admin_css_colors))$_wp_admin_css_colors=[];
        $_wp_admin_css_colors[$key]=(object)['name'=>$name,'url'=>$url,'colors'=>$colors,'icon_colors'=>$icons];
    }
}
if(!function_exists('register_admin_color_schemes')){
    function register_admin_color_schemes() {
        $u=fn($k)=>admin_url("css/colors/$k/colors.css");$ic=fn($b,$f,$c)=>['icons'=>['base'=>$b,'focus'=>$f,'current'=>$c]];
        wp_admin_css_color('fresh','Standard',false,['#1d2327','#2c3338','#2271b1','#72aee6'],$ic('#a7aaad','#72aee6','#fff'));
        wp_admin_css_color('light','Hell',$u('light'),['#e5e5e5','#999','#d64e07','#04a4cc'],$ic('#999','#ccc','#ccc'));
        wp_admin_css_color('modern','Modern',$u('modern'),['#1e1e1e','#3858e9','#33f078'],$ic('#f3f1f1','#fff','#fff'));
        wp_admin_css_color('blue','Blau',$u('blue'),['#096484','#4796b3','#52accc','#74B6CE'],$ic('#e5f8ff','#fff','#fff'));
        wp_admin_css_color('midnight','Mitternacht',$u('midnight'),['#25282b','#363b3f','#69a8bb','#e14d43'],$ic('#f1f2f3','#fff','#fff'));
        wp_admin_css_color('sunrise','Sonnenaufgang',$u('sunrise'),['#b43c38','#cf4944','#dd823b','#ccaf0b'],$ic('#f3f1f1','#fff','#fff'));
        wp_admin_css_color('ectoplasm','Ektoplasma',$u('ectoplasm'),['#413256','#523f6d','#a3b745','#d46f15'],$ic('#ece6f6','#fff','#fff'));
        wp_admin_css_color('ocean','Ozean',$u('ocean'),['#627c83','#738e96','#9ebaa0','#aa9d88'],$ic('#f2fcff','#fff','#fff'));
        wp_admin_css_color('coffee','Kaffee',$u('coffee'),['#46403c','#59524c','#c7a589','#9ea476'],$ic('#f3f2f1','#fff','#fff'));
    }
}
if(!function_exists('wp_admin_css_uri')){
    function wp_admin_css_uri($file='wp-admin', $deprecated='') {
        $f=defined('WP_INSTALLING')?"./$file.css":admin_url("$file.css");
        return apply_filters('wp_admin_css_uri',add_query_arg('version',get_bloginfo('version'),$f),$file);
    }
}

/* ───────── Robots-Meta (Filter für wp_robots) ───────── */
if(!function_exists('wp_robots_no_robots')){
    function wp_robots_no_robots($robots) { $robots['noindex']=true;if(get_option('blog_public'))$robots['follow']=true;else $robots['nofollow']=true;return $robots; }
}
if(!function_exists('wp_robots_noindex')){ function wp_robots_noindex($robots) { return get_option('blog_public')?$robots:wp_robots_no_robots($robots); } }
if(!function_exists('wp_robots_noindex_embeds')){ function wp_robots_noindex_embeds($robots) { return is_embed()?wp_robots_no_robots($robots):$robots; } }
if(!function_exists('wp_robots_noindex_search')){ function wp_robots_noindex_search($robots) { return is_search()?wp_robots_no_robots($robots):$robots; } }
if(!function_exists('wp_robots_sensitive_page')){ function wp_robots_sensitive_page($robots) { $robots['noindex']=true;$robots['noarchive']=true;return $robots; } }
if(!function_exists('wp_robots_max_image_preview_large')){ function wp_robots_max_image_preview_large($robots) { if(get_option('blog_public'))$robots['max-image-preview']='large';return $robots; } }

/* ───────── Shortcode-Hilfen ───────── */
if(!function_exists('apply_shortcodes')){ function apply_shortcodes($content, $ignore_html=false) { return do_shortcode($content,$ignore_html); } }
if(!function_exists('get_shortcode_atts_regex')){
    function get_shortcode_atts_regex() { return '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/'; }
}
if(!function_exists('get_shortcode_tags_in_content')){
    function get_shortcode_tags_in_content($content) {
        $content=(string)$content;if(!str_contains($content,'['))return [];
        preg_match_all('/'.get_shortcode_regex().'/',$content,$m,PREG_SET_ORDER);$tags=[];
        foreach($m as $sc){ $tags[]=$sc[2];if(!empty($sc[5]))$tags=array_merge($tags,get_shortcode_tags_in_content($sc[5])); }
        return $tags;
    }
}
if(!function_exists('_filter_do_shortcode_context')){ function _filter_do_shortcode_context() { return $GLOBALS['wp_current_filter'][0]??''; } }
if(!function_exists('unescape_invalid_shortcodes')){ function unescape_invalid_shortcodes($content) { return strtr((string)$content,['&#91;'=>'[','&#93;'=>']']); } }
if(!function_exists('strip_shortcode_tag')){
    function strip_shortcode_tag($m) { if($m[1]==='['&&$m[6]===']')return substr($m[0],1,-1);return $m[1].$m[6]; }
}

/* ───────── Navigationsmenü-Hilfen ───────── */
if(!function_exists('walk_nav_menu_tree')){ function walk_nav_menu_tree($items, $depth, $r) { $w=empty($r->walker)?new Walker_Nav_Menu():$r->walker;return $w->walk($items,$depth,$r); } }
if(!function_exists('_nav_menu_item_id_use_once')){
    function _nav_menu_item_id_use_once($id, $item) { static $used=[];if(in_array($item->ID,$used,true))return false;$used[]=$item->ID;return $id; }
}
if(!function_exists('wp_nav_menu_remove_menu_item_has_children_class')){
    /** Entfernt „menu-item-has-children“ bei Einträgen der tiefsten erlaubten Ebene. */
    function wp_nav_menu_remove_menu_item_has_children_class($classes, $menu_item, $args, $depth=0) {
        $max=isset($args->depth)?(int)$args->depth:0;
        return ($max>0&&$depth+1>=$max)?array_values(array_diff((array)$classes,['menu-item-has-children'])):$classes;
    }
}
if(!function_exists('_wp_menu_item_classes_by_context')){
    /** Setzt „current-menu-item“, „current-menu-parent/-ancestor“ u. ä. nach dem aktuell abgefragten Objekt bzw. der Adresse. */
    function _wp_menu_item_classes_by_context(&$menu_items) {
        $q=get_queried_object();$cur=rtrim((string)strtok(home_url($GLOBALS['elvado_wp_request']['path']??'/'),'?'),'/');$byId=[];
        foreach($menu_items as $it){ $it->current=false;$it->current_item_parent=false;$it->current_item_ancestor=false;$it->classes=array_values(array_diff((array)$it->classes,['current-menu-item','current_page_item','current-menu-parent','current_page_parent','current-menu-ancestor','current_page_ancestor']));$byId[$it->db_id??$it->ID]=$it; }
        $add=function($it,array $c) { foreach($c as $x)if(!in_array($x,$it->classes,true))$it->classes[]=$x; };
        foreach($menu_items as $it){
            $is=false;
            if($q instanceof WP_Post&&$it->type==='post_type'&&(int)$it->object_id===(int)$q->ID)$is=true;
            elseif($q instanceof WP_Term&&$it->type==='taxonomy'&&(int)$it->object_id===(int)$q->term_id&&$it->object===$q->taxonomy)$is=true;
            elseif($it->type==='custom'&&$it->url&&$it->url!=='#'&&rtrim((string)strtok($it->url,'?'),'/')===$cur)$is=true;
            if(!$is)continue;
            $it->current=true;$add($it,['current-menu-item']);if($it->type==='post_type'&&$it->object==='page')$add($it,['current_page_item']);
            for($p=$byId[$it->menu_item_parent]??null,$lvl=0;$p&&$lvl<20;$p=$byId[$p->menu_item_parent]??null,$lvl++){
                if($lvl===0){ $p->current_item_parent=true;$add($p,['current-menu-parent','current_page_parent']); }
                $p->current_item_ancestor=true;$add($p,['current-menu-ancestor','current_page_ancestor']);
            }
        }
    }
}
