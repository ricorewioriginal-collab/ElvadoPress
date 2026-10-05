<?php
// WordPress-Datenbankschema und dbDelta (Tabellen anlegen/ergänzen). Läuft auf MySQL und – übersetzt – auf SQLite.

function wp_get_db_schema($scope='all') {
    global $wpdb;$p=$wpdb->prefix;$cc=$wpdb->get_charset_collate();$mk=191;
    return "CREATE TABLE {$p}users (
 ID bigint(20) unsigned NOT NULL auto_increment,
 user_login varchar(60) NOT NULL default '',
 user_pass varchar(255) NOT NULL default '',
 user_nicename varchar(50) NOT NULL default '',
 user_email varchar(100) NOT NULL default '',
 user_url varchar(100) NOT NULL default '',
 user_registered datetime NOT NULL default '0000-00-00 00:00:00',
 user_activation_key varchar(255) NOT NULL default '',
 user_status int(11) NOT NULL default '0',
 display_name varchar(250) NOT NULL default '',
 PRIMARY KEY  (ID),
 KEY user_login_key (user_login),
 KEY user_nicename (user_nicename),
 KEY user_email (user_email)
) $cc;
CREATE TABLE {$p}usermeta (
 umeta_id bigint(20) unsigned NOT NULL auto_increment,
 user_id bigint(20) unsigned NOT NULL default '0',
 meta_key varchar(255) default NULL,
 meta_value longtext,
 PRIMARY KEY  (umeta_id),
 KEY user_id (user_id),
 KEY meta_key (meta_key($mk))
) $cc;
CREATE TABLE {$p}termmeta (
 meta_id bigint(20) unsigned NOT NULL auto_increment,
 term_id bigint(20) unsigned NOT NULL default '0',
 meta_key varchar(255) default NULL,
 meta_value longtext,
 PRIMARY KEY  (meta_id),
 KEY term_id (term_id),
 KEY meta_key (meta_key($mk))
) $cc;
CREATE TABLE {$p}terms (
 term_id bigint(20) unsigned NOT NULL auto_increment,
 name varchar(200) NOT NULL default '',
 slug varchar(200) NOT NULL default '',
 term_group bigint(10) NOT NULL default 0,
 PRIMARY KEY  (term_id),
 KEY slug (slug($mk)),
 KEY name (name($mk))
) $cc;
CREATE TABLE {$p}term_taxonomy (
 term_taxonomy_id bigint(20) unsigned NOT NULL auto_increment,
 term_id bigint(20) unsigned NOT NULL default 0,
 taxonomy varchar(32) NOT NULL default '',
 description longtext NOT NULL,
 parent bigint(20) unsigned NOT NULL default 0,
 count bigint(20) NOT NULL default 0,
 PRIMARY KEY  (term_taxonomy_id),
 UNIQUE KEY term_id_taxonomy (term_id,taxonomy),
 KEY taxonomy (taxonomy)
) $cc;
CREATE TABLE {$p}term_relationships (
 object_id bigint(20) unsigned NOT NULL default 0,
 term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
 term_order int(11) NOT NULL default 0,
 PRIMARY KEY  (object_id,term_taxonomy_id),
 KEY term_taxonomy_id (term_taxonomy_id)
) $cc;
CREATE TABLE {$p}commentmeta (
 meta_id bigint(20) unsigned NOT NULL auto_increment,
 comment_id bigint(20) unsigned NOT NULL default '0',
 meta_key varchar(255) default NULL,
 meta_value longtext,
 PRIMARY KEY  (meta_id),
 KEY comment_id (comment_id),
 KEY meta_key (meta_key($mk))
) $cc;
CREATE TABLE {$p}comments (
 comment_ID bigint(20) unsigned NOT NULL auto_increment,
 comment_post_ID bigint(20) unsigned NOT NULL default '0',
 comment_author tinytext NOT NULL,
 comment_author_email varchar(100) NOT NULL default '',
 comment_author_url varchar(200) NOT NULL default '',
 comment_author_IP varchar(100) NOT NULL default '',
 comment_date datetime NOT NULL default '0000-00-00 00:00:00',
 comment_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
 comment_content text NOT NULL,
 comment_karma int(11) NOT NULL default '0',
 comment_approved varchar(20) NOT NULL default '1',
 comment_agent varchar(255) NOT NULL default '',
 comment_type varchar(20) NOT NULL default 'comment',
 comment_parent bigint(20) unsigned NOT NULL default '0',
 user_id bigint(20) unsigned NOT NULL default '0',
 PRIMARY KEY  (comment_ID),
 KEY comment_post_ID (comment_post_ID),
 KEY comment_approved_date_gmt (comment_approved,comment_date_gmt),
 KEY comment_date_gmt (comment_date_gmt),
 KEY comment_parent (comment_parent),
 KEY comment_author_email (comment_author_email(10))
) $cc;
CREATE TABLE {$p}links (
 link_id bigint(20) unsigned NOT NULL auto_increment,
 link_url varchar(255) NOT NULL default '',
 link_name varchar(255) NOT NULL default '',
 link_image varchar(255) NOT NULL default '',
 link_target varchar(25) NOT NULL default '',
 link_description varchar(255) NOT NULL default '',
 link_visible varchar(20) NOT NULL default 'Y',
 link_owner bigint(20) unsigned NOT NULL default '1',
 link_rating int(11) NOT NULL default '0',
 link_updated datetime NOT NULL default '0000-00-00 00:00:00',
 link_rel varchar(255) NOT NULL default '',
 link_notes mediumtext NOT NULL,
 link_rss varchar(255) NOT NULL default '',
 PRIMARY KEY  (link_id),
 KEY link_visible (link_visible)
) $cc;
CREATE TABLE {$p}postmeta (
 meta_id bigint(20) unsigned NOT NULL auto_increment,
 post_id bigint(20) unsigned NOT NULL default '0',
 meta_key varchar(255) default NULL,
 meta_value longtext,
 PRIMARY KEY  (meta_id),
 KEY post_id (post_id),
 KEY meta_key (meta_key($mk))
) $cc;
CREATE TABLE {$p}posts (
 ID bigint(20) unsigned NOT NULL auto_increment,
 post_author bigint(20) unsigned NOT NULL default '0',
 post_date datetime NOT NULL default '0000-00-00 00:00:00',
 post_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
 post_content longtext NOT NULL,
 post_title text NOT NULL,
 post_excerpt text NOT NULL,
 post_status varchar(20) NOT NULL default 'publish',
 comment_status varchar(20) NOT NULL default 'open',
 ping_status varchar(20) NOT NULL default 'open',
 post_password varchar(255) NOT NULL default '',
 post_name varchar(200) NOT NULL default '',
 to_ping text NOT NULL,
 pinged text NOT NULL,
 post_modified datetime NOT NULL default '0000-00-00 00:00:00',
 post_modified_gmt datetime NOT NULL default '0000-00-00 00:00:00',
 post_content_filtered longtext NOT NULL,
 post_parent bigint(20) unsigned NOT NULL default '0',
 guid varchar(255) NOT NULL default '',
 menu_order int(11) NOT NULL default '0',
 post_type varchar(20) NOT NULL default 'post',
 post_mime_type varchar(100) NOT NULL default '',
 comment_count bigint(20) NOT NULL default '0',
 PRIMARY KEY  (ID),
 KEY post_name (post_name($mk)),
 KEY type_status_date (post_type,post_status,post_date,ID),
 KEY post_parent (post_parent),
 KEY post_author (post_author)
) $cc;
CREATE TABLE {$p}options (
 option_id bigint(20) unsigned NOT NULL auto_increment,
 option_name varchar(191) NOT NULL default '',
 option_value longtext NOT NULL,
 autoload varchar(20) NOT NULL default 'yes',
 PRIMARY KEY  (option_id),
 UNIQUE KEY option_name (option_name),
 KEY autoload (autoload)
) $cc;";
}

/** Tabellen anlegen bzw. fehlende Spalten/Indizes ergänzen. Rückgabe: Liste der Meldungen (wie WordPress). */
function dbDelta($queries='', $execute=true) {
    global $wpdb;
    if(is_string($queries))$queries=array_filter(array_map('trim',preg_split('/;\s*(?=CREATE|INSERT|UPDATE|ALTER|DELETE|\z)/i',$queries)));
    $out=[];
    foreach((array)$queries as $q){
        $q=rtrim(trim((string)$q),';');if($q==='')continue;
        if(preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i',$q,$m)){
            $table=$m[1];
            if(!$wpdb->table_exists($table)){ if($execute)$wpdb->query($q);$out[$table]="Created table $table";continue; }
            // Fehlende Spalten ergänzen
            $existing=[];foreach((array)$wpdb->get_results($wpdb->is_mysql()?"SHOW COLUMNS FROM `$table`":"SELECT name AS Field FROM pragma_table_info('$table')") as $c)$existing[strtolower($c->Field)]=1;
            if(preg_match('/\((.*)\)[^()]*$/s',$q,$bm)){
                foreach(preg_split('/,\s*(?![^()]*\))/',$bm[1]) as $line){
                    $line=trim($line);
                    if($line===''||preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|FULLTEXT|CONSTRAINT)\b/i',$line))continue;
                    if(preg_match('/^`?(\w+)`?\s+(.*)$/s',$line,$cm)&&!isset($existing[strtolower($cm[1])])){
                        $def=$wpdb->is_mysql()?$cm[2]:(new RRW_SQL_Translator($wpdb->pdo()))->convert_column($cm[1],$cm[2])[0];
                        $sql=$wpdb->is_mysql()?"ALTER TABLE `$table` ADD COLUMN `{$cm[1]}` $def":"ALTER TABLE `$table` ADD COLUMN $def";
                        if($execute)$wpdb->query($sql);$out[$table.'.'.$cm[1]]="Added column $table.{$cm[1]}";
                    }
                }
            }
            continue;
        }
        if($execute)$wpdb->query($q);$out[]=$q;
    }
    return $out;
}
function maybe_create_table($table_name, $create_ddl) { global $wpdb;if($wpdb->table_exists($table_name))return true;$wpdb->query($create_ddl);return $wpdb->table_exists($table_name); }
function maybe_add_column($table_name, $column_name, $create_ddl) {
    global $wpdb;$cols=$wpdb->get_col($wpdb->is_mysql()?"DESC `$table_name`":"SELECT name FROM pragma_table_info('$table_name')");
    if(in_array($column_name,(array)$cols,true))return true;$wpdb->query($create_ddl);return in_array($column_name,(array)$wpdb->get_col($wpdb->is_mysql()?"DESC `$table_name`":"SELECT name FROM pragma_table_info('$table_name')"),true);
}
function maybe_convert_table_to_utf8mb4($t) { return true; }

/** Kern-Tabellen beim ersten Zugriff anlegen (nur wenn eine Datenbank vorhanden ist). */
function rrw_wp_install_schema(): bool {
    global $wpdb;if(!$wpdb||!$wpdb->ready)return false;
    $ver=(string)get_option('rrw_wp_db_version','');
    if($ver==='1'&&$wpdb->table_exists($wpdb->posts))return true;
    dbDelta(wp_get_db_schema());
    // IDs der Datenbank-Inhalte beginnen bei 100 000 000, damit sie nie mit den IDs der CMS-Inhalte kollidieren
    foreach([$wpdb->posts,$wpdb->users] as $tb){
        if($wpdb->is_mysql())$wpdb->query("ALTER TABLE `$tb` AUTO_INCREMENT = ".RRW_WP_ID_DB_MIN);
        elseif(!$wpdb->get_var($wpdb->prepare("SELECT seq FROM sqlite_sequence WHERE name = %s",$tb)))$wpdb->query($wpdb->prepare("INSERT INTO sqlite_sequence (name, seq) VALUES (%s, %d)",$tb,RRW_WP_ID_DB_MIN-1));
    }
    update_option('rrw_wp_db_version','1');
    return $wpdb->table_exists($wpdb->posts);
}
