<?php
// Ergänzende Feed-Funktionen (Bereich Ausgabe): Hilfen für RSS-/Atom-Vorlagen (Titel, Inhalt, Kategorien, Anhänge, Kommentare) und fetch_feed().

if(!function_exists('get_default_feed')){ function get_default_feed() { $f=apply_filters('default_feed','rss2');return $f==='rss'?'rss2':$f; } }
if(!function_exists('feed_content_type')){
    function feed_content_type($type='') {
        if(empty($type))$type=get_default_feed();
        $t=['rss'=>'application/rss+xml','rss2'=>'application/rss+xml','rss-http'=>'text/xml','atom'=>'application/atom+xml','rdf'=>'application/rdf+xml'];
        return apply_filters('feed_content_type',!empty($t[$type])?$t[$type]:'application/octet-stream',$type);
    }
}
if(!function_exists('html_type_rss')){ function html_type_rss() { echo str_contains((string)get_bloginfo('html_type'),'xhtml')?'xhtml':'html'; } }
if(!function_exists('bloginfo_rss')){ function bloginfo_rss($show='') { echo apply_filters('bloginfo_rss',get_bloginfo_rss($show),$show); } }
if(!function_exists('get_wp_title_rss')){ function get_wp_title_rss($deprecated='&#8211;') { return apply_filters('get_wp_title_rss',wp_get_document_title(),$deprecated); } }
if(!function_exists('wp_title_rss')){ function wp_title_rss($deprecated='&#8211;') { echo apply_filters('wp_title_rss',get_wp_title_rss($deprecated)); } }
if(!function_exists('get_the_title_rss')){ function get_the_title_rss() { return apply_filters('the_title_rss',get_the_title()); } }
if(!function_exists('the_title_rss')){ function the_title_rss() { echo get_the_title_rss(); } }
if(!function_exists('get_the_content_feed')){
    function get_the_content_feed($feed_type=null) {
        if(!$feed_type)$feed_type=get_default_feed();
        $c=str_replace(']]>',']]&gt;',apply_filters('the_content',get_the_content()));
        return apply_filters('the_content_feed',$c,$feed_type);
    }
}
if(!function_exists('the_content_feed')){ function the_content_feed($feed_type=null) { echo get_the_content_feed($feed_type); } }
if(!function_exists('the_excerpt_rss')){ function the_excerpt_rss() { echo apply_filters('the_excerpt_rss',get_the_excerpt()); } }
if(!function_exists('the_permalink_rss')){ function the_permalink_rss() { echo esc_url(apply_filters('the_permalink_rss',get_permalink())); } }
if(!function_exists('comments_link_feed')){ function comments_link_feed() { echo esc_url(get_comments_link()); } }
if(!function_exists('get_comment_guid')){
    function get_comment_guid($comment_id=null) {
        $c=get_comment($comment_id);if(!is_object($c))return false;
        $pid=(int)$c->comment_post_ID;$g=function_exists('get_the_guid')?get_the_guid($pid):(string)(get_post_field('guid',$pid)?:get_permalink($pid));
        return $g.'#comment-'.$c->comment_ID;
    }
}
if(!function_exists('comment_guid')){ function comment_guid($comment_id=null) { echo esc_url((string)get_comment_guid($comment_id)); } }
if(!function_exists('comment_link')){ function comment_link() { echo esc_url(get_comment_link()); } }
if(!function_exists('get_comment_author_rss')){ function get_comment_author_rss() { return apply_filters('comment_author_rss',get_comment_author()); } }
if(!function_exists('comment_author_rss')){ function comment_author_rss() { echo get_comment_author_rss(); } }
if(!function_exists('comment_text_rss')){ function comment_text_rss() { echo apply_filters('comment_text_rss',get_comment_text()); } }
if(!function_exists('get_the_category_rss')){
    function get_the_category_rss($type=null) {
        if(empty($type))$type=get_default_feed();
        $names=[];foreach(array_merge((array)(get_the_category()?:[]),(array)(get_the_tags()?:[])) as $t)if(is_object($t)&&isset($t->name))$names[]=$type==='atom'?$t->name:strip_tags($t->name);
        $list='';
        foreach(array_unique($names) as $n){
            if($type==='rdf')$list.="\t\t<dc:subject><![CDATA[$n]]></dc:subject>\n";
            elseif($type==='atom')$list.=sprintf('<category scheme="%1$s" term="%2$s" />',esc_attr(get_bloginfo_rss('url')),esc_attr($n));
            else $list.="\t\t<category><![CDATA[".html_entity_decode($n,ENT_COMPAT,'UTF-8')."]]></category>\n";
        }
        return apply_filters('the_category_rss',$list,$type);
    }
}
if(!function_exists('the_category_rss')){ function the_category_rss($type=null) { echo get_the_category_rss($type); } }
if(!function_exists('rrw_ext_enclosures')){
    /** Anhänge aus dem Beitrags-Meta „enclosure“ (je Eintrag: URL⏎Länge⏎Typ) als [url,length,type]. */
    function rrw_ext_enclosures() {
        $p=get_post();$o=[];if(!$p||post_password_required())return $o;
        foreach((array)get_post_meta($p->ID,'enclosure',false) as $enc){ $e=explode("\n",(string)$enc);if(count($e)<3)continue;$t=preg_split('/[ \t]/',trim($e[2]));$o[]=[trim($e[0]),absint(trim($e[1])),$t[0]]; }
        return $o;
    }
}
if(!function_exists('rss_enclosure')){
    function rss_enclosure() { foreach(rrw_ext_enclosures() as [$u,$l,$t])echo apply_filters('rss_enclosure','<enclosure url="'.esc_url($u).'" length="'.$l.'" type="'.esc_attr($t).'" />'."\n"); }
}
if(!function_exists('atom_enclosure')){
    function atom_enclosure() { foreach(rrw_ext_enclosures() as [$u,$l,$t])echo apply_filters('atom_enclosure','<link href="'.esc_url($u).'" rel="enclosure" length="'.$l.'" type="'.esc_attr($t).'" />'."\n"); }
}
if(!function_exists('prep_atom_text_construct')){
    /** Art („text“, „xhtml“, „html“) und passend verpackter Inhalt für Atom-Felder. */
    function prep_atom_text_construct($data) {
        $data=(string)$data;
        if(!str_contains($data,'<')&&!str_contains($data,'&'))return ['text',$data];
        $valid=false;
        if(function_exists('xml_parser_create')){ $p=xml_parser_create();$valid=@xml_parse($p,'<div>'.$data.'</div>',true)===1&&!xml_get_error_code($p);xml_parser_free($p); }
        if($valid)return str_contains($data,'<')?['xhtml',"<div xmlns='http://www.w3.org/1999/xhtml'>$data</div>"]:['text',$data];
        return !str_contains($data,']]>')?['html',"<![CDATA[$data]]>"]:['html',htmlspecialchars($data)];
    }
}
if(!function_exists('atom_site_icon')){ function atom_site_icon() { $u=get_site_icon_url(32);if($u)echo '<icon>'.convert_chars($u)."</icon>\n"; } }
if(!function_exists('rss2_site_icon')){
    function rss2_site_icon() {
        $t=get_wp_title_rss();if(empty($t))$t=get_bloginfo_rss('name');$u=get_site_icon_url(32);
        if($u)echo '<image><url>'.convert_chars($u).'</url><title>'.$t.'</title><link>'.get_bloginfo_rss('url').'</link><width>32</width><height>32</height></image>'."\n";
    }
}
if(!function_exists('get_self_link')){
    function get_self_link() { $h=parse_url(home_url());return set_url_scheme('http://'.($h['host']??'localhost').wp_unslash($_SERVER['REQUEST_URI']??'/')); }
}
if(!function_exists('self_link')){ function self_link() { echo esc_url(apply_filters('self_link',get_self_link())); } }
if(!function_exists('get_feed_build_date')){
    /** Zeitpunkt der letzten Änderung der Beiträge der aktuellen Abfrage (sonst jetzt) im Format $format. */
    function get_feed_build_date($format) {
        global $wp_query;$t=[];
        if($wp_query&&!empty($wp_query->posts))$t=array_filter(wp_list_pluck($wp_query->posts,'post_modified_gmt'));
        $dt=$t?max($t):gmdate('Y-m-d H:i:s');
        return mysql2date($format,$dt,false);
    }
}

/* ───────── fetch_feed ───────── */
if(!class_exists('RRW_WP_Feed_Item')){
    /** Eintrag eines gelesenen Feeds (SimplePie-ähnliche Getter). */
    class RRW_WP_Feed_Item {
        public $d;public function __construct(array $d) { $this->d=$d; }
        public function get_title() { return $this->d['title']; } public function get_permalink() { return $this->d['link']; } public function get_link() { return $this->d['link']; }
        public function get_description() { return $this->d['desc']; } public function get_content() { return $this->d['content']?:$this->d['desc']; } public function get_id() { return $this->d['id']?:$this->d['link']; }
        public function get_date($f='U') { $t=strtotime($this->d['date']);return $t?($f==='U'?(string)$t:gmdate($f,$t)):''; }
    }
}
if(!class_exists('RRW_WP_Feed')){
    /** Gelesener Feed (RSS 2.0 / Atom) mit den üblichen SimplePie-Gettern. */
    class RRW_WP_Feed {
        public $title='';public $link='';public $desc='';public $items=[];
        public function get_title() { return $this->title; } public function get_permalink() { return $this->link; } public function get_link() { return $this->link; } public function get_description() { return $this->desc; }
        public function get_items($start=0, $length=0) { return $length?array_slice($this->items,$start,$length):array_slice($this->items,$start); }
        public function get_item_quantity($max=0) { $n=count($this->items);return $max?min($max,$n):$n; }
        public function get_item($i=0) { return $this->items[$i]??null; } public function error() { return false; }
        public static function parse($xml) {
            $x=@simplexml_load_string((string)$xml,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);if(!$x)return null;
            $f=new self();$ns=$x->getNamespaces(true);
            if(isset($x->channel)){   // RSS
                $c=$x->channel;$f->title=(string)$c->title;$f->link=(string)$c->link;$f->desc=(string)$c->description;
                foreach($c->item as $i){ $ce=isset($ns['content'])?(string)$i->children($ns['content'])->encoded:'';$f->items[]=new RRW_WP_Feed_Item(['title'=>(string)$i->title,'link'=>(string)$i->link,'desc'=>(string)$i->description,'content'=>$ce,'id'=>(string)$i->guid,'date'=>(string)$i->pubDate]); }
            } elseif($x->getName()==='feed'){   // Atom
                $f->title=(string)$x->title;$f->desc=(string)$x->subtitle;foreach($x->link as $l)if(!isset($l['rel'])||(string)$l['rel']==='alternate'){ $f->link=(string)$l['href'];break; }
                foreach($x->entry as $e){ $lk='';foreach($e->link as $l)if(!isset($l['rel'])||(string)$l['rel']==='alternate'){ $lk=(string)$l['href'];break; }
                    $f->items[]=new RRW_WP_Feed_Item(['title'=>(string)$e->title,'link'=>$lk,'desc'=>(string)$e->summary,'content'=>(string)$e->content,'id'=>(string)$e->id,'date'=>(string)($e->updated?:$e->published)]); }
            } else return null;
            return $f;
        }
    }
}
if(!function_exists('fetch_feed')){
    /** Feed holen und lesen (RSS/Atom); statt SimplePie liefert die Schicht ein RRW_WP_Feed-Objekt mit gleichen Gettern. Fehler als WP_Error. */
    function fetch_feed($url) {
        $pre=apply_filters('pre_fetch_feed',null,$url);if($pre!==null)return $pre;
        if(!is_string($url)||!preg_match('#^https?://#i',$url))return new WP_Error('simplepie-error','Ungültige Feed-Adresse.');
        $r=wp_remote_get($url,['timeout'=>10]);if(is_wp_error($r))return new WP_Error('simplepie-error',$r->get_error_message());
        if((int)wp_remote_retrieve_response_code($r)>=400)return new WP_Error('simplepie-error','Feed nicht erreichbar.');
        $f=RRW_WP_Feed::parse(wp_remote_retrieve_body($r));
        return $f?:new WP_Error('simplepie-error','Der Feed konnte nicht gelesen werden.');
    }
}
