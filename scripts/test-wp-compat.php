<?php
// Regressionstest für Plugin-Kompatibilität der WordPress-Schicht, gefunden beim Installieren bekannter Plugins (Contact Form 7, WooCommerce, Elementor, Yoast SEO):
// Typen und Sichtbarkeiten wie in WordPress (WP_Screen, WP_List_Table, globales $wp), LIKE-Maskierung bei SHOW TABLES, Standardwerte von register_rest_field.
// Aufruf: php scripts/test-wp-compat.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-compat-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/wp-content/themes');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');define('RRW_WP_TEST',1);$_SERVER['HTTP_HOST']='example.test';$_SERVER['REQUEST_URI']='/shop/produkt/?a=1';
file_put_contents($tmp.'/cms/news.json','[]');file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';require __DIR__.'/../cms/wp/router.php';require __DIR__.'/../cms/wp/admin.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);

// Contact Form 7: WPCF7_Help_Tabs::__construct(WP_Screen $screen)
t('Bildschirm-Objekt ist ein WP_Screen (Typangaben von Plugins)',new RRW_WP_Screen() instanceof WP_Screen);
t('get_current_screen() liefert ein WP_Screen',(function(){ rrw_wp_admin_init();return get_current_screen() instanceof WP_Screen; })());

// Contact Form 7: Listentabelle erweitert WP_List_Table und deklariert get_sortable_columns() als protected, nutzt get_items_per_page()
class RRW_T_List extends WP_List_Table {
    public function __construct() { parent::__construct(['singular'=>'item','plural'=>'items']); }
    public function get_columns() { return ['title'=>'Titel']; }
    protected function get_sortable_columns() { return ['title'=>['title',false]]; }
    public function per_page(): int { return $this->get_items_per_page('rrw_t_per_page',7); }
    public function actions(): string { return $this->row_actions(['edit'=>'<a href="#">Bearbeiten</a>','trash'=>'<a href="#">Löschen</a>']); }
    public function classes(): array { return $this->get_table_classes(); }
}
t('WP_List_Table: Unterklasse mit protected get_sortable_columns() ist möglich',class_exists('RRW_T_List'));
$lt=new RRW_T_List();
t('WP_List_Table: get_items_per_page() mit Standardwert',$lt->per_page()===7);
t('WP_List_Table: row_actions() und get_table_classes()',str_contains($lt->actions(),'row-actions')&&str_contains($lt->actions(),'Löschen')&&in_array('widefat',$lt->classes(),true));
t('WP_List_Table: single_row() und print_column_headers() vorhanden',method_exists($lt,'single_row')&&method_exists($lt,'print_column_headers'));

// Elementor: SHOW TABLES LIKE mit esc_like() (Unterstrich mit Rückstrich maskiert) muss die Tabelle finden, sonst wird sie bei jeder Anfrage neu angelegt
global $wpdb;$wpdb->query('CREATE TABLE '.$wpdb->prefix.'e_events (id INTEGER PRIMARY KEY, event_data TEXT)');
$q=$wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.'e_events'));
t('SHOW TABLES LIKE mit esc_like() findet die Tabelle',$wpdb->get_var($q)===$wpdb->prefix.'e_events','Ergebnis: '.var_export($wpdb->get_var($q),true));
t('SHOW TABLES LIKE ohne Treffer bleibt leer',$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.'gibt_es_nicht')))===null);

// WooCommerce: register_rest_field-Optionen immer mit schema, WCAdminHelper::get_url_from_wp(WP $wp) braucht das globale $wp
register_rest_field('post','rrw_feld',['get_callback'=>fn()=>1]);
$f=$GLOBALS['wp_rest_additional_fields']['post']['rrw_feld']??[];
t('register_rest_field: Standardwerte (schema, update_callback)',array_key_exists('schema',$f)&&array_key_exists('update_callback',$f)&&$f['schema']===null&&is_callable($f['get_callback']));
t('Globales $wp ist ein WP-Objekt',($GLOBALS['wp']??null) instanceof WP);
t('Globales $wp kennt den Anfragepfad',($GLOBALS['wp']->request??'')==='shop/produkt');

echo "\n".($n-$fail)." von $n Prüfungen bestanden\n";
exec('rm -rf '.escapeshellarg($tmp));
exit($fail?1:0);
