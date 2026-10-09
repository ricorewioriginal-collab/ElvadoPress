<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 3): Installation und Datenbank-Aktualisierung (upgrade.php, schema.php), Kern-Update (update-core.php),
// PclZip-Hilfen, Danksagungen, Sprachpakete, Importer, Bildschirm-/Menü-/Spaltenhilfen.
// Die Stufen upgrade_NNN migrieren Altdaten früherer WordPress-Versionen; das CMS legt Tabellen frisch über dbDelta an, daher sind sie bewusst ohne Wirkung (Standardwert).
// Multisite wird nicht unterstützt (siehe Kommentare an den Netzwerk-Funktionen).

/* ───────── Optionen und Hilfen ───────── */
if(!function_exists('__get_option')){ function __get_option($setting) {   // Option direkt aus der Datenbank (ohne Cache)
    elvado_wp_opts_load(true);[$ok,$v]=elvado_wp_opts_get_raw((string)$setting);   // Optionen liegen in options.json, nicht in der Datenbank
    if('home'===$setting&&(!$ok||$v==='')){ [$ok,$v]=elvado_wp_opts_get_raw('siteurl'); }
    if(!$ok)return false;
    return in_array($setting,['home','siteurl'],true)?untrailingslashit((string)$v):$v;
} }
if(!function_exists('get_alloptions_110')){ function get_alloptions_110() {   // alle Optionen als name=>Wert (ohne Entserialisierung)
    $o=[];
    foreach(array_keys(elvado_wp_opts_load(true)) as $k){ [$ok,$v]=elvado_wp_opts_get_raw($k);if($ok)$o[$k]=is_scalar($v)?(string)$v:serialize($v); }
    return $o;
} }
if(!function_exists('deslash')){ function deslash($content) { return stripslashes((string)$content); } }
if(!function_exists('translate_level_to_role')){ function translate_level_to_role($level) {   // alte Benutzerstufe 0–10 in eine Rolle
    $l=(int)$level;
    return match(true){ $l>=8=>'administrator',$l>=5=>'editor',$l>=2=>'author',$l===1=>'contributor',default=>'subscriber' };
} }
if(!function_exists('drop_index')){ function drop_index($table, $index) {
    global $wpdb;$wpdb->hide_errors();
    $wpdb->query("ALTER TABLE `$table` DROP INDEX `$index`");
    for($i=0;$i<25;$i++)$wpdb->query("ALTER TABLE `$table` DROP INDEX `{$index}_$i`");   // zusätzlich entstandene Duplikate
    $wpdb->show_errors();return true;
} }
if(!function_exists('add_clean_index')){ function add_clean_index($table, $index) {
    global $wpdb;drop_index($table,$index);
    $wpdb->query("ALTER TABLE `$table` ADD INDEX `$index` (`$index`)");return true;
} }
if(!function_exists('wp_check_mysql_version')){ function wp_check_mysql_version() {
    global $wpdb;$v=(string)$wpdb->db_version();
    if($v!==''&&version_compare($v,'5.5.5','<'))wp_die(sprintf('<strong>Fehler:</strong> WordPress benötigt MySQL 5.5.5 oder neuer; installiert ist %s.',esc_html($v)));
} }
if(!function_exists('wp_should_upgrade_global_tables')){ function wp_should_upgrade_global_tables() {   // Einzelseite: globale Tabellen sind die Tabellen der Seite
    return !defined('DO_NOT_UPGRADE_GLOBAL_TABLES');
} }
if(!function_exists('pre_schema_upgrade')){ function pre_schema_upgrade() {} }   // Vorarbeiten für Schemaänderungen: das Schema ist stabil, nichts zu tun
if(!function_exists('make_db_current_silent')){ function make_db_current_silent($tables='all') { return dbDelta(wp_get_db_schema($tables)); } }
if(!function_exists('make_db_current')){ function make_db_current($tables='all') {   // Tabellen anlegen/anpassen und die Meldungen als Liste ausgeben
    $r=make_db_current_silent($tables);
    if($r){ echo '<ol>';foreach((array)$r as $m)echo '<li>'.esc_html((string)$m).'</li>';echo '</ol>'; }
} }
if(!function_exists('maybe_disable_automattic_widgets')){ function maybe_disable_automattic_widgets() {   // das alte Plugin „widgets.php“ abschalten (ist seit 2.2 im Kern)
    $p=(array)__get_option('active_plugins');$k=false;
    foreach($p as $i=>$f)if('widgets.php'===basename((string)$f)){ array_splice($p,$i,1);$k=true; }
    if($k)update_option('active_plugins',array_values($p));
} }
if(!function_exists('maybe_disable_link_manager')){ function maybe_disable_link_manager() {   // Linkverwaltung abschalten, wenn keine Links vorhanden sind
    global $wpdb,$wp_current_db_version;
    if((int)$wp_current_db_version>=22006&&get_option('link_manager_enabled')&&!(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->links} WHERE link_visible = 'Y'"))update_option('link_manager_enabled',0);
} }
if(!function_exists('make_site_theme_from_oldschool')){ function make_site_theme_from_oldschool($theme_name, $template) { return false; } }   // Umbau alter Vorlagen entfällt
if(!function_exists('make_site_theme_from_default')){ function make_site_theme_from_default($theme_name, $template) { return false; } }
if(!function_exists('make_site_theme')){ function make_site_theme() { return false; } }

/* ───────── Aktualisierung ───────── */
if(!function_exists('wp_upgrade')){ function wp_upgrade() {   // Datenbank auf den Stand der Schicht bringen
    global $wp_current_db_version,$wp_db_version;
    $wp_current_db_version=(int)__get_option('db_version');
    if((int)$wp_db_version===$wp_current_db_version)return;
    upgrade_all();do_action('wp_upgrade',$wp_db_version,$wp_current_db_version);
} }
if(!function_exists('upgrade_all')){ function upgrade_all() {
    global $wp_current_db_version,$wp_db_version;
    $wp_current_db_version=(int)__get_option('db_version');
    if(!$wp_current_db_version)$wp_current_db_version=0;
    if((int)$wp_db_version===$wp_current_db_version)return;
    wp_check_mysql_version();pre_schema_upgrade();make_db_current_silent();
    foreach([3506=>'upgrade_210',4772=>'upgrade_230',7796=>'upgrade_250',8201=>'upgrade_260',9872=>'upgrade_270',11548=>'upgrade_280',12329=>'upgrade_290',15260=>'upgrade_300',19389=>'upgrade_330',20596=>'upgrade_340',
        22442=>'upgrade_350',25824=>'upgrade_370',26148=>'upgrade_372',26691=>'upgrade_380',29630=>'upgrade_400',31351=>'upgrade_420',33055=>'upgrade_430',33056=>'upgrade_431',34030=>'upgrade_440',36686=>'upgrade_450',
        37965=>'upgrade_460',43764=>'upgrade_500',44719=>'upgrade_510',45805=>'upgrade_530',48121=>'upgrade_550',49752=>'upgrade_560',53011=>'upgrade_590',53496=>'upgrade_600',55853=>'upgrade_630',56657=>'upgrade_640',
        57155=>'upgrade_650',58975=>'upgrade_670',59490=>'upgrade_682'] as $v=>$fn)
        if($wp_current_db_version<$v&&$v<=$wp_db_version&&function_exists($fn))$fn($wp_current_db_version);
    maybe_disable_link_manager();maybe_disable_automattic_widgets();
    update_option('db_version',$wp_db_version);update_option('db_upgraded',true);
} }
if(!function_exists('upgrade_network')){ function upgrade_network() {} }   // kein Netzwerk

/* ───────── Migrationsstufen früherer Versionen (ohne Wirkung: keine Altdaten im CMS) ───────── */
if(!function_exists('upgrade_100')){ function upgrade_100() {} } if(!function_exists('upgrade_101')){ function upgrade_101() {} } if(!function_exists('upgrade_110')){ function upgrade_110() {} }
if(!function_exists('upgrade_130')){ function upgrade_130() {} } if(!function_exists('upgrade_160')){ function upgrade_160() {} } if(!function_exists('upgrade_210')){ function upgrade_210() {} }
if(!function_exists('upgrade_230')){ function upgrade_230() {} } if(!function_exists('upgrade_230_options_table')){ function upgrade_230_options_table() {} }
if(!function_exists('upgrade_230_old_tables')){ function upgrade_230_old_tables() {} } if(!function_exists('upgrade_old_slugs')){ function upgrade_old_slugs() {} }
if(!function_exists('upgrade_250')){ function upgrade_250() {} } if(!function_exists('upgrade_252')){ function upgrade_252() {} } if(!function_exists('upgrade_260')){ function upgrade_260() {} }
if(!function_exists('upgrade_270')){ function upgrade_270() {} } if(!function_exists('upgrade_280')){ function upgrade_280() {} } if(!function_exists('upgrade_290')){ function upgrade_290() {} }
if(!function_exists('upgrade_300')){ function upgrade_300() {} } if(!function_exists('upgrade_330')){ function upgrade_330() {} } if(!function_exists('upgrade_340')){ function upgrade_340() {} }
if(!function_exists('upgrade_350')){ function upgrade_350() {} } if(!function_exists('upgrade_370')){ function upgrade_370() {} } if(!function_exists('upgrade_372')){ function upgrade_372() {} }
if(!function_exists('upgrade_380')){ function upgrade_380() {} } if(!function_exists('upgrade_400')){ function upgrade_400() {} } if(!function_exists('upgrade_420')){ function upgrade_420() {} }
if(!function_exists('upgrade_430')){ function upgrade_430() {} } if(!function_exists('upgrade_430_fix_comments')){ function upgrade_430_fix_comments() {} }
if(!function_exists('upgrade_431')){ function upgrade_431() {} } if(!function_exists('upgrade_440')){ function upgrade_440() {} } if(!function_exists('upgrade_450')){ function upgrade_450() {} }
if(!function_exists('upgrade_460')){ function upgrade_460() {} } if(!function_exists('upgrade_500')){ function upgrade_500() {} } if(!function_exists('upgrade_510')){ function upgrade_510() {} }
if(!function_exists('upgrade_530')){ function upgrade_530() {} } if(!function_exists('upgrade_550')){ function upgrade_550() {} } if(!function_exists('upgrade_560')){ function upgrade_560() {} }
if(!function_exists('upgrade_590')){ function upgrade_590() {} } if(!function_exists('upgrade_600')){ function upgrade_600() {} } if(!function_exists('upgrade_630')){ function upgrade_630() {} }
if(!function_exists('upgrade_640')){ function upgrade_640() {} } if(!function_exists('upgrade_650')){ function upgrade_650() {} } if(!function_exists('upgrade_670')){ function upgrade_670() {} }
if(!function_exists('upgrade_682')){ function upgrade_682() {} }
