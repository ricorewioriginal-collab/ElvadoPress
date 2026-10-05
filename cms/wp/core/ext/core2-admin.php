<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 7): Datenschutzerklärung, Website-Icon, Verwaltungs-Hilfsklassen, Website-Zustand (Site Health), Debug-Daten, Community-Veranstaltungen.
// Eigenständig umgesetzt; reine Oberflächenklassen (Zeiger, Kopf-/Hintergrundbild-Seiten) sind schlanke No-ops. Auswertungen laufen nur beim Aufruf.

if(!class_exists('_WP_Editors',false)&&is_file(__DIR__.'/../class-wp-editor.php'))require_once __DIR__.'/../class-wp-editor.php';   // schlanker Editor der Schicht (nur eine Klassendatei, keine Ausgabe)

/* ───────── Datenschutzerklärung ───────── */
if(!class_exists('WP_Privacy_Policy_Content')){
class WP_Privacy_Policy_Content {
    private static $policy_content=[];
    private function __construct() {}
    public static function add($plugin_name, $policy_text) {
        if(empty($plugin_name)||empty($policy_text))return;
        $d=['plugin_name'=>$plugin_name,'policy_text'=>$policy_text];
        if(!in_array($d,self::$policy_content,true))self::$policy_content[]=$d;
    }
    public static function get_suggested_policy_text() {
        $c=self::$policy_content;
        $c[]=['plugin_name'=>'Website','policy_text'=>self::get_default_content(false,false)];
        $c=apply_filters('wp_privacy_policy_content_items',$c);
        usort($c,fn($a,$b)=>($a['plugin_name']==='Website'?-1:($b['plugin_name']==='Website'?1:strcasecmp($a['plugin_name'],$b['plugin_name']))));
        return $c;
    }
    public static function get_default_content($description=false, $blocks=true) {
        $intro='Dieser Text ist ein Vorschlag und ersetzt keine Rechtsberatung. Bitte passen Sie ihn an Ihre Website an.';
        if($description)return $intro;
        $s=['Wer wir sind'=>'Die Adresse unserer Website ist: '.home_url().'.','Kommentare'=>'Wenn Besucher Kommentare auf der Website schreiben, sammeln wir die im Kommentar-Formular angezeigten Daten sowie IP-Adresse und User-Agent.','Medien'=>'Wenn Sie Bilder hochladen, vermeiden Sie Dateien mit eingebetteten Standortdaten (EXIF GPS).','Cookies'=>'Wenn Sie einen Kommentar schreiben, können Sie Namen, E-Mail-Adresse und Website in Cookies speichern lassen.','Eingebettete Inhalte von anderen Websites'=>'Beiträge können eingebettete Inhalte (z. B. Videos) enthalten, die sich wie ein Besuch der anderen Website verhalten.','Mit wem wir Ihre Daten teilen'=>'Wenn Sie ein Zurücksetzen des Passworts anfordern, wird Ihre IP-Adresse in der E-Mail zum Zurücksetzen enthalten sein.','Wie lange wir Ihre Daten speichern'=>'Kommentare und deren Metadaten werden unbegrenzt gespeichert.','Welche Rechte Sie an Ihren Daten haben'=>'Sie können einen Export oder die Löschung Ihrer personenbezogenen Daten anfordern.'];
        $o='';foreach($s as $h=>$t)$o.=$blocks?"<!-- wp:heading -->\n<h2>".esc_html($h)."</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>".esc_html($t)."</p>\n<!-- /wp:paragraph -->\n\n":'<h2>'.esc_html($h).'</h2><p>'.esc_html($t)."</p>\n";
        return $o;
    }
    private static function hash() { return md5(serialize(self::get_suggested_policy_text())); }
    public static function _policy_page_updated($post_id) {   // Stand des Vorschlags an der Datenschutzseite merken
        if((int)$post_id!==(int)get_option('wp_page_for_privacy_policy'))return;
        update_post_meta((int)$post_id,'_rrw_privacy_suggested_hash',self::hash());
    }
    public static function text_change_check() {
        $id=(int)get_option('wp_page_for_privacy_policy');if(!$id)return false;
        $old=get_post_meta($id,'_rrw_privacy_suggested_hash',true);
        return $old!==''&&$old!==self::hash();
    }
    public static function policy_text_changed_notice() {
        if(!self::text_change_check()||!(current_user_can('manage_privacy_options')||current_user_can('manage_options')))return;
        echo '<div class="policy-text-updated notice notice-warning is-dismissible"><p>Der Vorschlag für die Datenschutzerklärung hat sich geändert. Bitte prüfen Sie Ihre Datenschutzseite.</p></div>';
    }
    public static function notice($post=null) {
        $post=get_post($post);if(!$post||(int)$post->ID!==(int)get_option('wp_page_for_privacy_policy'))return;
        echo '<div class="notice notice-warning inline"><p>Hinweis: Dies ist Ihre Datenschutzseite. Vorschläge finden Sie im Leitfaden zur Datenschutzerklärung.</p></div>';
    }
    public static function privacy_policy_guide() {
        echo '<div class="privacy-settings-body"><p>'.esc_html(self::get_default_content(true)).'</p>';
        foreach(self::get_suggested_policy_text() as $i)echo '<div class="privacy-text-section"><h3>'.esc_html($i['plugin_name']).'</h3><div class="policy-text">'.wp_kses_post($i['policy_text']).'</div></div>';
        echo '</div>';
    }
}
}

/* ───────── Website-Icon ───────── */
if(!class_exists('WP_Site_Icon')){
class WP_Site_Icon {
    public $min_size=512;public $page_crop=512;public $site_icon_sizes=[512,192,180,32];
    public function __construct() { add_action('delete_attachment',[$this,'delete_attachment_data']);add_filter('get_post_metadata',[$this,'get_post_metadata'],10,4); }
    public function create_attachment_object($cropped, $parent_attachment_id) {
        $parent=get_post($parent_attachment_id);$url=$parent?($parent->guid?:wp_get_attachment_url($parent_attachment_id)):'';
        return ['ID'=>0,'post_title'=>basename((string)$cropped),'post_content'=>(string)$url,'post_mime_type'=>$parent?$parent->post_mime_type:'image/png','guid'=>(string)$url,'context'=>'site-icon'];
    }
    public function insert_attachment($attachment, $file) {
        $id=wp_insert_attachment($attachment,$file);
        if($id&&!is_wp_error($id)&&function_exists('wp_generate_attachment_metadata')){ $m=wp_generate_attachment_metadata($id,$file);if($m)wp_update_attachment_metadata($id,apply_filters('wp_create_file_in_uploads',$m,$id)); }
        return $id;
    }
    public function additional_sizes($sizes=[]) {
        foreach($this->site_icon_sizes as $s)$sizes['site_icon-'.$s]=['width'=>$s,'height'=>$s,'crop'=>true];
        return $sizes;
    }
    public function intermediate_image_sizes($sizes=[]) {
        foreach($this->site_icon_sizes as $s)$sizes[]='site_icon-'.$s;
        return $sizes;
    }
    public function delete_attachment_data($post_id) { if((int)get_option('site_icon')===(int)$post_id)delete_option('site_icon'); }
    public function get_post_metadata($value, $post_id, $meta_key, $single) {
        if($single&&'_wp_attachment_context'===$meta_key&&(int)get_option('site_icon')===(int)$post_id)return 'site-icon';
        return $value;
    }
}
}

/* ───────── Reine Oberflächenklassen (No-ops) ───────── */
if(!class_exists('WP_Internal_Pointers')){
class WP_Internal_Pointers {
    public static function enqueue_scripts($hook_suffix) {}   // Hinweis-Zeiger gibt es in der CMS-Verwaltung nicht
    public static function print_js($pointer_id, $selector, $args) {
        if(empty($pointer_id)||empty($selector)||empty($args['content']))return;
        echo '<script>/* Zeiger '.esc_js($pointer_id).' */</script>';
    }
    public static function pointer_wp330_toolbar() {} public static function pointer_wp330_media_uploader() {} public static function pointer_wp330_saving_widgets() {} public static function pointer_wp340_customize_current_theme_link() {}
    public static function pointer_wp340_choose_image_from_library() {} public static function pointer_wp350_media() {} public static function pointer_wp360_revisions() {} public static function pointer_wp390_widgets() {} public static function pointer_wp410_dfw() {}
    public static function pointer_wp496_privacy() {}
}
}
if(!class_exists('Custom_Background')){
class Custom_Background {
    public $admin_header_callback;public $admin_image_div_callback;public $page='';
    public function __construct($admin_header_callback='', $admin_image_div_callback='') { $this->admin_header_callback=$admin_header_callback;$this->admin_image_div_callback=$admin_image_div_callback; }
    public function init() {} public function admin_load() {} public function take_action() {} public function admin_page() {} public function handle_upload() {} public function wp_set_background_image() {}
    public function admin_head() { if(is_callable($this->admin_header_callback))call_user_func($this->admin_header_callback); }
}
}
if(!class_exists('Custom_Image_Header')){
class Custom_Image_Header {
    public $admin_header_callback;public $admin_image_div_callback;public $default_headers=[];public $page='';
    public function __construct($admin_header_callback, $admin_image_div_callback='') { $this->admin_header_callback=$admin_header_callback;$this->admin_image_div_callback=$admin_image_div_callback; }
    public function init() {} public function help() {} public function admin_page() {} public function take_action() {} public function process_default_headers() {} public function show_header_selector($type='default') {}
    public function step_1() {} public function step_2() {} public function step_3() {} public function js() {} public function css() {} public function admin_load() {}
    public function get_default_header_images() { global $_wp_default_headers;return (array)$_wp_default_headers; }
}
}

/* ───────── Website-Zustand (Site Health) ───────── */
if(!class_exists('WP_Site_Health')){
class WP_Site_Health {
    private static $instance=null;
    public $health_check_mysql_required_version='5.5';public $health_check_mysql_rec_version='8.0';public $php_memory_limit;
    public static function get_instance() { return self::$instance??(self::$instance=new self()); }
    public function __construct() { $this->php_memory_limit=ini_get('memory_limit'); }
    public function enqueue_scripts() {}
    private function res($test, $label, $status, $desc, $cat='Leistung', $color='blue', $actions='') {
        return ['label'=>$label,'status'=>$status,'badge'=>['label'=>$cat,'color'=>$color],'description'=>'<p>'.$desc.'</p>','actions'=>$actions,'test'=>$test];
    }
    public function get_test_wordpress_version() {
        $u=function_exists('get_core_updates')?get_core_updates():[];$new=false;foreach((array)$u as $x)if(is_object($x)&&($x->response??'')==='upgrade'){ $new=true; }
        return $new?$this->res('wordpress_version','Eine neue WordPress-Version ist verfügbar','critical','Bitte aktualisieren Sie die Software.','Sicherheit','blue'):$this->res('wordpress_version','Die Software ist aktuell','good','Die installierte Version ist '.esc_html(wp_get_wp_version()).'.','Sicherheit');
    }
    public function get_test_plugin_version() {
        $p=get_plugins();$a=count(array_filter(array_keys($p),'is_plugin_active'));$u=function_exists('get_plugin_updates')?count((array)get_plugin_updates()):0;
        $s=$u?'recommended':'good';
        return $this->res('plugin_version',$u?'Für Plugins sind Updates verfügbar':'Alle Plugins sind aktuell',$s,sprintf('%d Plugins installiert, %d aktiv, %d mit Updates.',count($p),$a,$u),'Sicherheit');
    }
    public function get_test_theme_version() {
        $u=function_exists('get_theme_updates')?count((array)get_theme_updates()):0;
        return $this->res('theme_version',$u?'Für Themes sind Updates verfügbar':'Alle Themes sind aktuell',$u?'recommended':'good',sprintf('%d Themes installiert, %d mit Updates.',count(wp_get_themes()),$u),'Sicherheit');
    }
    public function get_test_php_version() {
        $v=PHP_VERSION;$s=version_compare($v,'7.4','<')?'critical':(version_compare($v,'8.1','<')?'recommended':'good');
        return $this->res('php_version',$s==='good'?'Ihre Website läuft mit einer aktuellen PHP-Version':'Ihre Website läuft mit einer veralteten PHP-Version',$s,'PHP-Version: '.esc_html($v).'.','Leistung');
    }
    public function get_test_php_extensions() {
        $req=['json','hash'];$rec=['curl','dom','exif','fileinfo','mbstring','openssl','xml','zip','gd'];$miss=[];$crit=false;
        foreach($req as $e)if(!extension_loaded($e)){ $miss[]=$e;$crit=true; }
        foreach($rec as $e)if(!extension_loaded($e))$miss[]=$e;
        $s=$crit?'critical':($miss?'recommended':'good');
        return $this->res('php_extensions',$miss?'Es fehlen PHP-Erweiterungen':'Alle erforderlichen PHP-Erweiterungen sind vorhanden',$s,$miss?'Fehlend: '.esc_html(implode(', ',$miss)).'.':'Keine Erweiterung fehlt.','Leistung');
    }
    public function get_test_php_default_timezone() {
        $ok='UTC'===date_default_timezone_get();
        return $this->res('php_default_timezone',$ok?'Die PHP-Standardzeitzone ist UTC':'Die PHP-Standardzeitzone ist nicht UTC',$ok?'good':'critical','Standardzeitzone: '.esc_html(date_default_timezone_get()).'.','Leistung');
    }
    public function get_test_php_sessions() {
        $act=function_exists('session_status')&&session_status()===PHP_SESSION_ACTIVE;
        return $this->res('php_sessions',$act?'Es ist eine PHP-Sitzung aktiv':'Es ist keine PHP-Sitzung aktiv',$act?'recommended':'good',$act?'Sitzungen können Zwischenspeicher stören.':'Keine aktive Sitzung gefunden.','Leistung');
    }
    public function get_test_sql_server() {
        global $wpdb;$v=method_exists($wpdb,'db_version')?(string)$wpdb->db_version():'';$ok=$v===''||version_compare($v,$this->health_check_mysql_required_version,'>=');
        return $this->res('sql_server',$ok?'Der SQL-Server ist ausreichend aktuell':'Der SQL-Server ist veraltet',$ok?'good':'critical','Version: '.esc_html($v?:'unbekannt').'.','Leistung');
    }
    public function get_test_utf8mb4_support() {
        global $wpdb;$cs=method_exists($wpdb,'has_cap')?$wpdb->has_cap('utf8mb4'):true;
        return $this->res('utf8mb4_support',$cs?'Die Datenbank unterstützt utf8mb4':'Die Datenbank unterstützt utf8mb4 nicht',$cs?'good':'recommended','Für Emoji und seltene Zeichen wird utf8mb4 benötigt.','Leistung');
    }
    public function get_test_is_in_debug_mode() {
        $d=defined('WP_DEBUG')&&WP_DEBUG;$disp=$d&&defined('WP_DEBUG_DISPLAY')&&WP_DEBUG_DISPLAY;
        return $this->res('is_in_debug_mode',$d?'Ihre Website läuft im Debug-Modus':'Ihre Website läuft nicht im Debug-Modus',$disp?'recommended':'good',$d?'WP_DEBUG ist aktiv.':'WP_DEBUG ist aus.','Sicherheit');
    }
    public function get_test_https_status() {
        $https=wp_is_using_https();
        return $this->res('https_status',$https?'Ihre Website verwendet HTTPS':'Ihre Website verwendet kein HTTPS',$https?'good':'recommended','Adresse: '.esc_html(home_url()).'.','Sicherheit');
    }
    public function get_test_file_uploads() {
        $fu=(bool)ini_get('file_uploads');$u=(int)wp_convert_hr_to_bytes((string)ini_get('upload_max_filesize'));$p=(int)wp_convert_hr_to_bytes((string)ini_get('post_max_size'));
        return $this->res('file_uploads',$fu?'Datei-Uploads sind aktiviert':'Datei-Uploads sind deaktiviert',$fu?'good':'critical','upload_max_filesize: '.esc_html(size_format($u)).', post_max_size: '.esc_html(size_format($p)).'.','Leistung');
    }
    public function get_test_scheduled_events() {
        $jobs=_get_cron_array();$late=0;$now=time();
        foreach(is_array($jobs)?$jobs:[] as $ts=>$h)if(is_int($ts)&&$ts<$now-600)$late+=is_array($h)?count($h):0;
        return $this->res('scheduled_events',$late?'Geplante Ereignisse sind überfällig':'Geplante Ereignisse laufen',$late?'recommended':'good',$late?$late.' überfällige Ereignisse.':'Keine überfälligen Ereignisse.','Leistung');
    }
    public function get_test_persistent_object_cache() {
        $e=wp_using_ext_object_cache();
        return $this->res('persistent_object_cache',$e?'Ein persistenter Objekt-Cache wird verwendet':'Ein persistenter Objekt-Cache wird nicht verwendet',$e?'good':'recommended','Ein Objekt-Cache beschleunigt Datenbankabfragen.','Leistung');
    }
    public function get_test_dotorg_communication() {   // Netzwerkzugriff nur beim Aufruf
        $r=wp_remote_get('https://api.wordpress.org/core/version-check/1.7/',['timeout'=>5]);
        $ok=!is_wp_error($r)&&(int)wp_remote_retrieve_response_code($r)===200;
        return $this->res('dotorg_communication',$ok?'Verbindung zu WordPress.org ist möglich':'Verbindung zu WordPress.org ist nicht möglich',$ok?'good':'recommended',$ok?'Updates können abgerufen werden.':esc_html(is_wp_error($r)?$r->get_error_message():'Unerwarteter Status.'),'Sicherheit');
    }
    public function get_tests() {
        $t=['direct'=>[],'async'=>[]];
        foreach(['wordpress_version'=>'Software-Version','plugin_version'=>'Plugin-Versionen','theme_version'=>'Theme-Versionen','php_version'=>'PHP-Version','php_extensions'=>'PHP-Erweiterungen','php_default_timezone'=>'PHP-Zeitzone','php_sessions'=>'PHP-Sitzungen','sql_server'=>'SQL-Server','utf8mb4_support'=>'utf8mb4','is_in_debug_mode'=>'Debug-Modus','https_status'=>'HTTPS','file_uploads'=>'Datei-Uploads','scheduled_events'=>'Geplante Ereignisse','persistent_object_cache'=>'Objekt-Cache'] as $k=>$l)
            $t['direct'][$k]=['label'=>$l,'test'=>[$this,'get_test_'.$k]];
        $t['async']['dotorg_communication']=['label'=>'Verbindung zu WordPress.org','test'=>[$this,'get_test_dotorg_communication'],'has_rest'=>false,'async_direct_test'=>[$this,'get_test_dotorg_communication']];
        return apply_filters('site_status_tests',$t);
    }
    public function perform_test($callback) { return apply_filters('site_status_test_result',call_user_func($callback)); }
    public function is_development_environment() { return in_array(wp_get_environment_type(),['development','local'],true); }
    public function maybe_create_scheduled_event() {
        if(!wp_next_scheduled('wp_site_health_scheduled_check')&&!wp_installing())wp_schedule_event(time()+DAY_IN_SECONDS,'weekly','wp_site_health_scheduled_check');
    }
    public function wp_cron_scheduled_check() {
        $c=['good'=>0,'recommended'=>0,'critical'=>0];
        foreach($this->get_tests()['direct'] as $t){ $r=$this->perform_test($t['test']);if(isset($r['status'],$c[$r['status']]))$c[$r['status']]++; }
        update_option('health-check-site-status-result',wp_json_encode($c));
        return $c;
    }
    public function admin_body_class($body_class) { return $body_class; }
    public function get_site_health_status_label($status) { return ['good'=>'Gut','recommended'=>'Empfohlen','critical'=>'Kritisch'][$status]??$status; }
}
}
if(!class_exists('WP_Site_Health_Auto_Updates')){
class WP_Site_Health_Auto_Updates {
    public function run_tests() {
        $out=[];
        foreach(['constants','wp_version_check_attached','filters_automatic_updater_disabled','wp_automatic_updates_disabled','if_failed_update','vcs_abspath','check_wp_filesystem_method','all_files_writable','accepts_dev_updates','accepts_minor_updates'] as $t){
            $r=$this->{'test_'.$t}();if($r)$out[]=$r+['test'=>$t];
        }
        return $out;
    }
    public function test_constants() {
        $bad=[];foreach(['DISALLOW_FILE_MODS','AUTOMATIC_UPDATER_DISABLED','WP_AUTO_UPDATE_CORE'] as $c)if(defined($c)&&(($c==='WP_AUTO_UPDATE_CORE')?constant($c)===false:constant($c)))$bad[]=$c;
        return $bad?['description'=>'Konstanten verhindern automatische Updates: '.implode(', ',$bad).'.','severity'=>'fail']:['description'=>'Keine Konstante verhindert automatische Updates.','severity'=>'pass'];
    }
    public function test_wp_version_check_attached() {
        if((!is_multisite()||is_main_site())&&!has_filter('wp_version_check','wp_version_check')&&!has_action('wp_version_check','wp_version_check')&&!defined('RRW_WP_VERSION_CHECK_EXTERNAL'))
            return ['description'=>'Die Versionsprüfung ist nicht an den Zeitplan angehängt.','severity'=>'warning'];
        return false;
    }
    public function test_filters_automatic_updater_disabled() {
        if(apply_filters('automatic_updater_disabled',defined('AUTOMATIC_UPDATER_DISABLED')&&AUTOMATIC_UPDATER_DISABLED))return ['description'=>'Der Filter „automatic_updater_disabled“ schaltet automatische Updates ab.','severity'=>'fail'];
        return ['description'=>'Der automatische Updater ist nicht per Filter abgeschaltet.','severity'=>'pass'];
    }
    public function test_wp_automatic_updates_disabled() {
        if(!class_exists('WP_Automatic_Updater'))return false;
        $off=defined('AUTOMATIC_UPDATER_DISABLED')&&AUTOMATIC_UPDATER_DISABLED;
        return $off?['description'=>'Automatische Updates sind abgeschaltet.','severity'=>'fail']:false;
    }
    public function test_if_failed_update() {
        $f=get_site_option('auto_core_update_failed');if(!$f)return false;
        return ['description'=>'Ein früheres automatisches Update ist fehlgeschlagen.','severity'=>'fail'];
    }
    public function test_vcs_abspath() {
        foreach(['.svn','.git','.hg','.bzr'] as $d)if(is_dir(rtrim(ABSPATH,'/').'/'.$d))return ['description'=>'Ein Versionsverwaltungs-Ordner ('.$d.') wurde gefunden; automatische Updates sind eingeschränkt.','severity'=>'info'];
        return ['description'=>'Es wurde kein Versionsverwaltungs-Ordner gefunden.','severity'=>'pass'];
    }
    public function test_check_wp_filesystem_method() {
        $m=function_exists('get_filesystem_method')?get_filesystem_method([],ABSPATH):'direct';
        return $m==='direct'?['description'=>'Dateien können direkt geschrieben werden.','severity'=>'pass']:['description'=>'Das Dateisystem erlaubt kein direktes Schreiben.','severity'=>'fail'];
    }
    public function test_all_files_writable() {
        $w=is_writable(ABSPATH);
        return $w?['description'=>'Das Hauptverzeichnis ist beschreibbar.','severity'=>'pass']:['description'=>'Das Hauptverzeichnis ist nicht beschreibbar.','severity'=>'fail'];
    }
    public function test_accepts_dev_updates() {
        $v=wp_get_wp_version();if(!preg_match('/[a-z]/i',$v))return false;
        return defined('WP_AUTO_UPDATE_CORE')&&WP_AUTO_UPDATE_CORE===true?['description'=>'Entwicklungs-Updates werden automatisch eingespielt.','severity'=>'pass']:['description'=>'Entwicklungs-Updates werden nicht automatisch eingespielt.','severity'=>'fail'];
    }
    public function test_accepts_minor_updates() {
        if(defined('WP_AUTO_UPDATE_CORE')&&false===WP_AUTO_UPDATE_CORE)return ['description'=>'Kleine Updates werden nicht automatisch eingespielt.','severity'=>'fail'];
        return ['description'=>'Kleine Updates werden automatisch eingespielt.','severity'=>'pass'];
    }
}
}

/* ───────── Debug-Daten ───────── */
if(!class_exists('WP_Debug_Data')){
class WP_Debug_Data {
    public static function check_for_requested_update() {}
    public static function get_database_size() {
        global $wpdb;$size=0;
        try{ $rows=$wpdb->get_results("SHOW TABLE STATUS",ARRAY_A);
            foreach(is_array($rows)?$rows:[] as $r)if(strpos((string)($r['Name']??''),$wpdb->prefix)===0)$size+=(int)($r['Data_length']??0)+(int)($r['Index_length']??0);
        }catch(Throwable $e){ $size=0; }
        return $size;
    }
    public static function get_sizes() {
        $d=[];$u=wp_upload_dir(null,false);
        foreach(['wordpress'=>ABSPATH,'themes'=>get_theme_root(),'plugins'=>WP_PLUGIN_DIR,'uploads'=>$u['basedir']] as $k=>$p){
            $b=is_dir($p)?get_dirsize($p):0;$d[$k.'_size']=['path'=>$p,'size'=>size_format((int)$b),'debug'=>size_format((int)$b)];$d[$k.'_size']['raw']=(int)$b;
        }
        $db=self::get_database_size();$d['database_size']=['path'=>'','size'=>size_format($db),'debug'=>size_format($db),'raw'=>$db];
        $t=array_sum(array_column($d,'raw'));$d['total_size']=['path'=>'','size'=>size_format($t),'debug'=>size_format($t),'raw'=>$t];
        return $d;
    }
    public static function debug_data() {
        global $wpdb;$theme=wp_get_theme();$plugins=get_plugins();$active=array_filter(array_keys($plugins),'is_plugin_active');
        $f=fn($label,$value,$private=false)=>['label'=>$label,'value'=>$value,'debug'=>$value,'private'=>$private];
        $i=[];
        $i['wp-core']=['label'=>'WordPress','fields'=>['version'=>$f('Version',wp_get_wp_version()),'site_language'=>$f('Website-Sprache',get_locale()),'timezone'=>$f('Zeitzone',wp_timezone_string()),'home_url'=>$f('Startseiten-Adresse',get_bloginfo('url'),true),'site_url'=>$f('Website-Adresse',get_bloginfo('wpurl'),true),'permalink'=>$f('Permalink-Struktur',get_option('permalink_structure')?:'Standard'),'https_status'=>$f('HTTPS-Status',wp_is_using_https()?'Ja':'Nein'),'user_registration'=>$f('Registrierung',get_option('users_can_register')?'Aktiviert':'Deaktiviert'),'environment_type'=>$f('Umgebungstyp',wp_get_environment_type())]];
        $i['wp-active-theme']=['label'=>'Aktives Theme','fields'=>['name'=>$f('Name',$theme->get('Name')),'version'=>$f('Version',$theme->get('Version')),'parent_theme'=>$f('Eltern-Theme',$theme->parent()?$theme->parent()->get('Name'):'Keines'),'theme_path'=>$f('Verzeichnis',get_stylesheet_directory(),true)]];
        $i['wp-plugins-active']=['label'=>'Aktive Plugins','fields'=>[]];
        foreach($active as $p)$i['wp-plugins-active']['fields'][sanitize_key($p)]=$f($plugins[$p]['Name']??$p,'Version '.($plugins[$p]['Version']??''));
        $i['wp-plugins-inactive']=['label'=>'Inaktive Plugins','fields'=>[]];
        foreach(array_diff(array_keys($plugins),$active) as $p)$i['wp-plugins-inactive']['fields'][sanitize_key($p)]=$f($plugins[$p]['Name']??$p,'Version '.($plugins[$p]['Version']??''));
        $i['wp-media']=['label'=>'Medien-Verarbeitung','fields'=>['image_editor'=>$f('Aktiver Bild-Editor',function_exists('_wp_image_editor_choose')?(string)(_wp_image_editor_choose()?:'Keiner'):'Unbekannt'),'gd_version'=>$f('GD-Version',function_exists('gd_info')?(gd_info()['GD Version']??'GD'):'Nicht installiert'),'max_effective_size'=>$f('Maximale Upload-Größe',size_format(wp_max_upload_size()))]];
        $i['wp-server']=['label'=>'Server','fields'=>['server_architecture'=>$f('Server-Architektur',php_uname('s').' '.php_uname('r').' '.php_uname('m')),'httpd_software'=>$f('Webserver',$_SERVER['SERVER_SOFTWARE']??'Unbekannt'),'php_version'=>$f('PHP-Version',PHP_VERSION.' '.(PHP_INT_SIZE*8).'-Bit'),'php_memory_limit'=>$f('PHP-Speicherlimit',ini_get('memory_limit')),'max_execution_time'=>$f('Maximale Ausführungszeit',ini_get('max_execution_time')),'upload_max_filesize'=>$f('Upload-Größe',ini_get('upload_max_filesize')),'curl_version'=>$f('cURL',function_exists('curl_version')?curl_version()['version']:'Nicht installiert')]];
        $i['wp-database']=['label'=>'Datenbank','fields'=>['extension'=>$f('Erweiterung',get_class($wpdb)),'server_version'=>$f('Server-Version',method_exists($wpdb,'db_version')?(string)$wpdb->db_version():''),'database_prefix'=>$f('Tabellenpräfix',$wpdb->prefix,true)]];
        $c=[];foreach(['ABSPATH','WP_CONTENT_DIR','WP_DEBUG','WP_DEBUG_LOG','WP_DEBUG_DISPLAY','SCRIPT_DEBUG','WP_CACHE','CONCATENATE_SCRIPTS','COMPRESS_SCRIPTS','COMPRESS_CSS','WP_ENVIRONMENT_TYPE','DB_CHARSET'] as $k)
            $c[$k]=$f($k,defined($k)?(is_bool(constant($k))?(constant($k)?'true':'false'):(string)constant($k)):'Nicht definiert',in_array($k,['ABSPATH','WP_CONTENT_DIR'],true));
        $i['wp-constants']=['label'=>'WordPress-Konstanten','fields'=>$c];
        $i['wp-filesystem']=['label'=>'Dateisystem-Berechtigungen','fields'=>['wordpress'=>$f('Hauptverzeichnis',is_writable(ABSPATH)?'Beschreibbar':'Nicht beschreibbar'),'wp-content'=>$f('Inhaltsordner',is_writable(WP_CONTENT_DIR)?'Beschreibbar':'Nicht beschreibbar'),'uploads'=>$f('Upload-Ordner',is_writable(wp_upload_dir(null,false)['basedir'])?'Beschreibbar':'Nicht beschreibbar')]];
        return apply_filters('debug_information',$i);
    }
    public static function format($info_array, $data_type) {
        $r="`\n";$data_type=strtolower((string)$data_type);
        foreach((array)$info_array as $section=>$details){
            if(empty($details['fields']))continue;
            $r.="### ".wp_strip_all_tags((string)($details['label']??$section))." ###\n\n";
            foreach($details['fields'] as $k=>$f){
                $v=$data_type==='debug'&&isset($f['debug'])?$f['debug']:($f['value']??'');
                if(is_array($v))$v=implode(', ',array_map('strval',$v));elseif(is_bool($v))$v=$v?'true':'false';
                if(!empty($f['private'])&&$data_type==='debug')$v='***';
                $r.=wp_strip_all_tags((string)($f['label']??$k)).': '.wp_strip_all_tags((string)$v)."\n";
            }
            $r.="\n";
        }
        return rtrim($r)."\n`";
    }
}
}

/* ───────── Community-Veranstaltungen ───────── */
if(!class_exists('WP_Community_Events')){
class WP_Community_Events {
    protected $user_id=0;protected $user_location=false;
    public function __construct($user_id, $user_location=false) { $this->user_id=absint($user_id);$this->user_location=$user_location; }
    // Veranstaltungskalender von WordPress.org gibt es im CMS nicht: leere Liste, kein Netzwerkzugriff
    public function get_events($location_search='', $timezone='') { return ['location'=>$this->user_location?:null,'events'=>[]]; }
    public function get_events_transient_key($location) {
        $k=false;
        if(isset($location['ip'])&&$location['ip'])$k=md5((string)$location['ip']);
        elseif(isset($location['latitude'],$location['longitude']))$k=md5($location['latitude'].$location['longitude']);
        elseif(isset($location['description']))$k=md5((string)$location['description']);
        return $k?'community-events-'.$k:false;
    }
    public function cache_events($events, $expiration=false) {
        $k=$this->get_events_transient_key($events['location']??[]);if(!$k)return false;
        $e=$expiration?:(int)apply_filters('community_events_cache_expiration',12*HOUR_IN_SECONDS,$events);
        return set_transient($k,$events,$e);
    }
    protected function trim_events(array $events) {
        $max=3;$out=array_slice($events,0,$max);
        return $out;
    }
    protected function get_unsafe_client_ip() {
        $ip=false;
        foreach(['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED','HTTP_X_CLUSTER_CLIENT_IP','HTTP_FORWARDED_FOR','HTTP_FORWARDED','REMOTE_ADDR'] as $k){
            if(!empty($_SERVER[$k])){ $c=trim(explode(',',(string)$_SERVER[$k])[0]);if(filter_var($c,FILTER_VALIDATE_IP)){ $ip=$c;break; } }
        }
        return $ip?wp_privacy_anonymize_ip($ip):false;
    }
    protected function format_event_data_time($response_body) { return $response_body; }
    protected function maybe_log_events_response($message, $response) {}
}
}
