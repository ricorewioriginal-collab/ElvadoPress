<?php
// WP_Query und die Schleife (The Loop). Es wird kein SQL gegen wp_posts zusammengebaut: Kandidaten (CMS-Beiträge/-Seiten + Zeilen aus wp_posts)
// werden gesammelt und in PHP gefiltert, sortiert und seitenweise ausgegeben. Alle gängigen Abfrage-Parameter sind umgesetzt.
// Nicht möglich: Plugins, die SQL-Klauseln über posts_where/posts_join/posts_orderby filtern (es gibt kein SQL für diese Abfrage).

$GLOBALS['wp_query']=$GLOBALS['wp_query']??null;$GLOBALS['wp_the_query']=$GLOBALS['wp_the_query']??null;

if(!class_exists('WP_Query')){
#[AllowDynamicProperties]
class WP_Query {
    public $query;public $query_vars=[];public $queried_object;public $queried_object_id;
    public $posts;public $post_count=0;public $current_post=-1;public $in_the_loop=false;public $post;public $comments;public $comment_count=0;public $current_comment=-1;public $comment;
    public $found_posts=0;public $max_num_pages=0;public $max_num_comment_pages=0;
    public $is_single=false;public $is_preview=false;public $is_page=false;public $is_archive=false;public $is_date=false;public $is_year=false;public $is_month=false;public $is_day=false;public $is_time=false;
    public $is_author=false;public $is_category=false;public $is_tag=false;public $is_tax=false;public $is_search=false;public $is_feed=false;public $is_comment_feed=false;public $is_trackback=false;
    public $is_home=false;public $is_privacy_policy=false;public $is_404=false;public $is_embed=false;public $is_paged=false;public $is_admin=false;public $is_attachment=false;public $is_singular=false;
    public $is_front_page=false;public $is_robots=false;public $is_favicon=false;public $is_posts_page=false;public $is_post_type_archive=false;
    public $query_vars_hash;public $query_vars_changed=true;
    public function __construct($query='') { if(!empty($query))$this->query($query); }
    public function init() { $this->posts=null;$this->post_count=0;$this->current_post=-1;$this->in_the_loop=false;$this->found_posts=0;$this->max_num_pages=0;foreach(get_object_vars($this) as $k=>$v)if(str_starts_with($k,'is_'))$this->$k=false; }
    public function fill_query_vars($q) {
        foreach(['error','m','p','post_parent','subpost','subpost_id','attachment','attachment_id','name','pagename','page_id','second','minute','hour','day','monthnum','year','w','category_name','tag','cat','tag_id','author','author_name','feed','tb','paged','meta_key','meta_value','preview','s','sentence','title','fields','menu_order','embed'] as $k)if(!isset($q[$k]))$q[$k]='';
        foreach(['category__in','category__not_in','category__and','post__in','post__not_in','post_name__in','tag__in','tag__not_in','tag__and','tag_slug__in','tag_slug__and','post_parent__in','post_parent__not_in','author__in','author__not_in'] as $k)if(!isset($q[$k]))$q[$k]=[];
        return $q;
    }
    public function parse_query($query='') {
        if(!empty($query)){ $this->init();$this->query=$this->query_vars=wp_parse_args($query); }
        elseif(!isset($this->query))$this->query=$this->query_vars;
        $this->query_vars=$this->fill_query_vars($this->query_vars);$q=&$this->query_vars;
        if($q['p']!=='')$q['p']=absint($q['p']);
        $this->is_single=$q['p']||$q['name']!==''||$q['attachment_id']||($q['page_id']&&false);
        if($q['page_id'])$this->is_page=true;if($q['pagename']!=='')$this->is_page=true;
        if($q['s']!=='')$this->is_search=true;
        if($q['cat']||$q['category_name']!==''||$q['category__in'])$this->is_category=true;
        if($q['tag']!==''||$q['tag_id']||$q['tag_slug__in'])$this->is_tag=true;
        if($q['author']!==''||$q['author_name']!=='')$this->is_author=true;
        if($q['year']||$q['monthnum']||$q['day'])$this->is_date=true;if($q['year'])$this->is_year=true;if($q['monthnum'])$this->is_month=true;if($q['day'])$this->is_day=true;
        if(!empty($q['attachment'])||!empty($q['attachment_id']))$this->is_attachment=true;
        if(!empty($q['post_type'])&&!is_array($q['post_type'])&&$q['post_type']!=='post'&&$q['post_type']!=='page'&&$q['post_type']!=='any'&&!$this->is_single&&!$this->is_page&&!$this->is_search)$this->is_post_type_archive=true;
        $this->is_singular=$this->is_single||$this->is_page||$this->is_attachment;
        if(!$this->is_singular&&($this->is_category||$this->is_tag||$this->is_author||$this->is_date||$this->is_tax||$this->is_post_type_archive))$this->is_archive=true;
        if(!$this->is_singular&&!$this->is_archive&&!$this->is_search&&!$this->is_404&&!$this->is_feed)$this->is_home=true;
        if($q['paged']||!empty($q['page']))$this->is_paged=true;
        do_action_ref_array('parse_query',[&$this]);
    }
    public function set_404() { $this->init();$this->is_404=true; }
    /** is_home(), is_archive() … als Methoden (WooCommerce & Co. rufen sie am Query-Objekt auf). */
    public function __call($name,$args) { if(str_starts_with($name,'is_')&&property_exists($this,$name))return (bool)$this->$name; trigger_error('Undefined method WP_Query::'.$name,E_USER_WARNING);return null; }
    public function get($query_var, $default_value='') { return $this->query_vars[$query_var]??$default_value; }
    public function set($query_var, $value) { $this->query_vars[$query_var]=$value; }
    public function query($query) { $this->init();$this->query=$this->query_vars=wp_parse_args($query);return $this->get_posts(); }

    private function match_status(string $st, array $want, bool $any): bool { return $any?!in_array($st,['trash','auto-draft'],true):in_array($st,$want,true); }
    public function get_posts() {
        $this->parse_query();do_action_ref_array('pre_get_posts',[&$this]);
        $q=&$this->query_vars;$q=$this->fill_query_vars($q);
        $pre=apply_filters_ref_array('posts_pre_query',[null,&$this]);
        if($pre!==null&&is_array($pre)){ $this->posts=$pre;$this->post_count=count($pre);$this->found_posts=$this->post_count;$this->max_num_pages=1;return $this->posts; }
        // Typen
        $pt=$q['post_type']??'';if($pt===''||$pt===null)$pt=($this->is_attachment?'attachment':($this->is_page?'page':'post'));
        if($pt==='any'){ $pt=array_values(get_post_types(['exclude_from_search'=>false])); if(!$pt)$pt=['post','page']; }
        $types=array_values(array_filter((array)$pt));
        $st=$q['post_status']??'';$anyStatus=false;
        if($st==='any'||(is_array($st)&&in_array('any',$st,true))){$anyStatus=true;$want=[];}
        else{ $want=$st===''||$st===null?['publish']:array_values(array_filter(array_map('trim',is_array($st)?$st:explode(',',(string)$st)))); if(!$st&&current_user_can('read_private_posts'))$want[]='private';
            // Angemeldete Redakteure sehen Entwürfe über ihre Adresse (Vorschau, z. B. im Elementor-Editor)
            if(!$st&&!empty($GLOBALS['elvado_wp_sess_user'])&&($q['page_id']||$q['p']))$want=array_merge($want,['draft','pending','future','private']); }
        // Kandidaten sammeln
        $cands=[];
        if(in_array('post',$types,true)&&$this->match_status('publish',$want,$anyStatus))foreach(elvado_wp_cms_posts() as $p)$cands[]=$p;
        if(in_array('page',$types,true)&&$this->match_status('publish',$want,$anyStatus))foreach(elvado_wp_cms_pages() as $p)$cands[]=$p;
        // Schreibbrücke: Entwürfe/geplante/gelöschte CMS-Beiträge, deaktivierte Seiten und Medien der CMS-Bibliothek
        if(function_exists('elvado_wp_bridge')){
            $others=array_values(array_diff($anyStatus?['draft','pending','private','future']:$want,['publish']));
            if($others||$anyStatus){
                if(in_array('post',$types,true)&&elvado_wp_bridge('posts'))foreach(elvado_wp_cms_posts_any() as $p)if($p->post_status!=='publish'&&in_array($p->post_status,$anyStatus?['draft','pending','private','future']:$want,true))$cands[]=$p;
                if(in_array('page',$types,true)&&elvado_wp_bridge('pages'))foreach(elvado_wp_cms_pages_any() as $p)if($p->post_status!=='publish'&&($anyStatus||in_array($p->post_status,$want,true)))$cands[]=$p;
            }
            if(in_array('attachment',$types,true)&&($anyStatus||in_array('inherit',$want,true)))foreach(elvado_wp_cms_media_posts() as $p)$cands[]=$p;
        }
        global $wpdb;
        if($wpdb&&$wpdb->ready&&elvado_wp_db_ready()){
            $in=implode(',',array_map(fn($t)=>"'".$wpdb->_real_escape($t)."'",$types));
            $sql="SELECT * FROM {$wpdb->posts} WHERE post_type IN ($in)";
            if(!$anyStatus&&$want)$sql.=" AND post_status IN (".implode(',',array_map(fn($t)=>"'".$wpdb->_real_escape($t)."'",$want)).")";elseif($anyStatus)$sql.=" AND post_status NOT IN ('trash','auto-draft')";
            if(!empty($q['p']))$sql.=" AND ID = ".(int)$q['p'];
            $rows=$wpdb->get_results($sql);foreach((array)$rows as $r){$r->filter='raw';$p=new WP_Post($r);elvado_wp_post_cache_set((int)$p->ID,$p);$cands[]=$p;}
        }
        // Filter
        $res=array_values(array_filter($cands,fn($p)=>$this->matches($p,$q)));
        $res=$this->sort($res,$q);
        if($this->is_home&&empty($q['ignore_sticky_posts'])&&(int)($q['paged']?:1)<=1&&in_array('post',$types,true)){
            $sticky=array_map('intval',(array)get_option('sticky_posts',[]));
            if($sticky){ $st1=[];$rest=[];foreach($res as $p){ if(in_array((int)$p->ID,$sticky,true))$st1[]=$p;else $rest[]=$p; } $res=array_merge($st1,$rest); }
        }
        // Seiten
        $ppp=isset($q['posts_per_page'])&&$q['posts_per_page']!==''?(int)$q['posts_per_page']:(int)get_option('posts_per_page',10);
        if(!empty($q['nopaging']))$ppp=-1;
        $total=count($res);
        if($ppp>0){ $paged=max(1,(int)($q['paged']?:($q['page']??1)));$off=isset($q['offset'])&&$q['offset']!==''?(int)$q['offset']:($paged-1)*$ppp;$page=array_slice($res,$off,$ppp); }
        else $page=$res;
        if(empty($q['no_found_rows'])){ $this->found_posts=$total;$this->max_num_pages=$ppp>0?(int)ceil($total/$ppp):1; }
        // fields
        $f=$q['fields']??'';
        if($f==='ids'){ $this->posts=array_map(fn($p)=>(int)$p->ID,$page); }
        elseif($f==='id=>parent'){ $o=[];foreach($page as $p)$o[(int)$p->ID]=(int)$p->post_parent;$this->posts=$o; }
        else{ $this->posts=apply_filters_ref_array('the_posts',[$page,&$this]); }
        $this->post_count=count($this->posts);
        if($f===''||$f==='all'){ if($this->post_count)$this->post=reset($this->posts); }
        if($this->is_singular&&$this->post_count===1)$this->queried_object=$this->post;
        elseif($this->is_category||$this->is_tag){ $this->queried_object=null; }
        return $this->posts;
    }
    private function author_ok(WP_Post $p, $q): bool {
        $aid=(int)$p->post_author;
        if($q['author']!==''&&$q['author']!==0){ $ids=array_map('intval',explode(',',(string)$q['author']));$neg=array_filter($ids,fn($i)=>$i<0);$pos=array_filter($ids,fn($i)=>$i>0);
            if($pos&&!in_array($aid,$pos,true))return false;if($neg&&in_array(-$aid,$neg,true))return false; }
        if($q['author__in']&&!in_array($aid,array_map('intval',$q['author__in']),true))return false;
        if($q['author__not_in']&&in_array($aid,array_map('intval',$q['author__not_in']),true))return false;
        if($q['author_name']!==''){ $u=get_user_by('slug',(string)$q['author_name']);if(!$u||(int)$u->ID!==$aid)return false; }
        return true;
    }
    private function matches(WP_Post $p, array $q): bool {
        $id=(int)$p->ID;
        if(!empty($q['p'])&&$id!==(int)$q['p'])return false;
        if(!empty($q['page_id'])&&$id!==(int)$q['page_id'])return false;
        if($q['name']!==''&&$p->post_name!==$q['name'])return false;
        if($q['pagename']!==''){ $pn=trim((string)$q['pagename'],'/');if(basename($pn)!==$p->post_name)return false;if(str_contains($pn,'/')&&get_page_uri($p)!==$pn)return false; }
        if($q['post_name__in']&&!in_array($p->post_name,(array)$q['post_name__in'],true))return false;
        if($q['post__in']&&!in_array($id,array_map('intval',(array)$q['post__in']),true))return false;
        if($q['post__not_in']&&in_array($id,array_map('intval',(array)$q['post__not_in']),true))return false;
        if($q['post_parent']!==''&&$q['post_parent']!==null&&(string)$q['post_parent']!==''&&(int)$p->post_parent!==(int)$q['post_parent'])return false;
        if($q['post_parent__in']&&!in_array((int)$p->post_parent,array_map('intval',(array)$q['post_parent__in']),true))return false;
        if($q['post_parent__not_in']&&in_array((int)$p->post_parent,array_map('intval',(array)$q['post_parent__not_in']),true))return false;
        if(!$this->author_ok($p,$q))return false;
        if($q['menu_order']!==''&&(int)$p->menu_order!==(int)$q['menu_order'])return false;
        if(!empty($q['post_mime_type'])&&strpos((string)$p->post_mime_type,(string)$q['post_mime_type'])!==0)return false;
        // Suche
        if($q['s']!==''){ if(!$this->search_ok($p,(string)$q['s'],!empty($q['exact']),$q['sentence']!==''))return false; }
        // Datum
        $d=strtotime($p->post_date);
        if($q['year']&&(int)date('Y',$d)!==(int)$q['year'])return false;if($q['monthnum']&&(int)date('n',$d)!==(int)$q['monthnum'])return false;if($q['day']&&(int)date('j',$d)!==(int)$q['day'])return false;
        if($q['hour']!==''&&(int)date('G',$d)!==(int)$q['hour'])return false;if($q['minute']!==''&&(int)date('i',$d)!==(int)$q['minute'])return false;
        if($q['m']!==''){ $m=(string)$q['m'];if(!str_starts_with(date('YmdHis',$d),$m))return false; }
        if($q['w']!==''&&(int)date('W',$d)!==(int)$q['w'])return false;
        if(!empty($q['date_query'])&&!$this->date_ok($d,$q['date_query']))return false;
        // Taxonomien
        if(!$this->tax_ok($p,$q))return false;
        // Meta
        if(!$this->meta_ok($p,$q))return false;
        return apply_filters('elvado_wp_query_matches',true,$p,$q);
    }
    private function search_ok(WP_Post $p, string $s, bool $exact, bool $sentence): bool {
        $hay=mb_strtolower($p->post_title."\n".$p->post_excerpt."\n".wp_strip_all_tags($p->post_content));
        if($exact||$sentence)return str_contains($hay,mb_strtolower(trim($s)));
        preg_match_all('/(?<!\\\\)"(.*?)"|(\S+)/u',$s,$m,PREG_SET_ORDER);
        foreach($m as $t){ $term=mb_strtolower(trim($t[1]!==''?$t[1]:$t[2]));if($term==='')continue; if($term[0]==='-'&&strlen($term)>1){ if(str_contains($hay,substr($term,1)))return false; } elseif(!str_contains($hay,$term))return false; }
        return true;
    }
    private function date_ok(int $ts, $dq): bool {
        $rel=strtoupper((string)($dq['relation']??'AND'));$res=[];
        foreach((array)$dq as $k=>$c){ if(!is_array($c))continue; $ok=true;
            foreach(['year'=>'Y','month'=>'n','day'=>'j','hour'=>'G','minute'=>'i','second'=>'s','week'=>'W','dayofweek'=>'w'] as $f=>$fmt)if(isset($c[$f]) && (int)date($fmt,$ts)!==(int)$c[$f])$ok=false;
            $inc=!empty($c['inclusive']);
            if(isset($c['after'])){ $a=is_array($c['after'])?strtotime(sprintf('%04d-%02d-%02d',$c['after']['year']??1970,$c['after']['month']??1,$c['after']['day']??1)):strtotime((string)$c['after']);if($a!==false&&!($inc?$ts>=$a:$ts>$a))$ok=false; }
            if(isset($c['before'])){ $b=is_array($c['before'])?strtotime(sprintf('%04d-%02d-%02d',$c['before']['year']??2100,$c['before']['month']??12,$c['before']['day']??31)):strtotime((string)$c['before']);if($b!==false&&!($inc?$ts<=$b:$ts<$b))$ok=false; }
            $res[]=$ok; }
        if(!$res)return true;return $rel==='OR'?in_array(true,$res,true):!in_array(false,$res,true);
    }
    private function term_match(WP_Post $p, $taxonomy, $field, $terms, $op, $children=false): bool {
        $have=wp_get_object_terms($p->ID,$taxonomy);$terms=(array)$terms;
        $ids=array_map(fn($t)=>match($field){'slug'=>$t->slug,'name'=>$t->name,'term_taxonomy_id'=>(int)$t->term_taxonomy_id,default=>(int)$t->term_id},$have);
        $want=array_map(fn($x)=>$field==='slug'||$field==='name'?(string)$x:(int)$x,$terms);
        $hit=count(array_intersect($ids,$want));
        return match(strtoupper((string)$op)){'NOT IN'=>$hit===0,'AND'=>$hit===count($want),'EXISTS'=>(bool)$ids,'NOT EXISTS'=>!$ids,default=>$hit>0};
    }
    private function tax_ok(WP_Post $p, array $q): bool {
        if($q['cat']!==''&&$q['cat']!==0){ $ids=array_filter(array_map('intval',preg_split('/[,\s]+/',(string)$q['cat'])));$pos=array_filter($ids,fn($i)=>$i>0);$neg=array_map('abs',array_filter($ids,fn($i)=>$i<0));
            if($pos&&!$this->term_match($p,'category','term_id',$pos,'IN'))return false;if($neg&&!$this->term_match($p,'category','term_id',$neg,'NOT IN'))return false; }
        if($q['category_name']!==''){ $sl=array_filter(array_map('trim',explode(',',str_replace('+',',',(string)$q['category_name']))));if(!$this->term_match($p,'category','slug',array_map('sanitize_title',$sl),str_contains((string)$q['category_name'],'+')?'AND':'IN'))return false; }
        if($q['category__in']&&!$this->term_match($p,'category','term_id',$q['category__in'],'IN'))return false;
        if($q['category__not_in']&&!$this->term_match($p,'category','term_id',$q['category__not_in'],'NOT IN'))return false;
        if($q['category__and']&&!$this->term_match($p,'category','term_id',$q['category__and'],'AND'))return false;
        if($q['tag']!==''){ $sl=array_filter(array_map('trim',preg_split('/[,+]/',(string)$q['tag'])));if(!$this->term_match($p,'post_tag','slug',array_map('sanitize_title',$sl),str_contains((string)$q['tag'],'+')?'AND':'IN'))return false; }
        if($q['tag_id']&&!$this->term_match($p,'post_tag','term_id',[(int)$q['tag_id']],'IN'))return false;
        if($q['tag__in']&&!$this->term_match($p,'post_tag','term_id',$q['tag__in'],'IN'))return false;
        if($q['tag__not_in']&&!$this->term_match($p,'post_tag','term_id',$q['tag__not_in'],'NOT IN'))return false;
        if($q['tag__and']&&!$this->term_match($p,'post_tag','term_id',$q['tag__and'],'AND'))return false;
        if($q['tag_slug__in']&&!$this->term_match($p,'post_tag','slug',$q['tag_slug__in'],'IN'))return false;
        if($q['tag_slug__and']&&!$this->term_match($p,'post_tag','slug',$q['tag_slug__and'],'AND'))return false;
        foreach(get_taxonomies(['_builtin'=>false],'objects') as $tx){ if($tx->query_var&&!empty($q[$tx->query_var])){ if(!$this->term_match($p,$tx->name,'slug',array_filter(array_map('trim',explode(',',(string)$q[$tx->query_var]))),'IN'))return false; } }
        if(!empty($q['taxonomy'])&&!empty($q['term'])&&!$this->term_match($p,(string)$q['taxonomy'],'slug',[(string)$q['term']],'IN'))return false;
        if(!empty($q['tax_query'])){
            $rel=strtoupper((string)($q['tax_query']['relation']??'AND'));$rs=[];
            foreach($q['tax_query'] as $k=>$c){ if(!is_array($c))continue; if(isset($c['taxonomy']))$rs[]=$this->term_match($p,$c['taxonomy'],$c['field']??'term_id',$c['terms']??[],$c['operator']??'IN'); }
            if($rs){ $ok=$rel==='OR'?in_array(true,$rs,true):!in_array(false,$rs,true);if(!$ok)return false; }
        }
        return true;
    }
    private function meta_cmp($have, $value, string $cmp, string $type): bool {
        $conv=function($v) use($type){ return match(strtoupper($type)){'NUMERIC','SIGNED','UNSIGNED','DECIMAL'=>(float)$v,'DATE','DATETIME'=>strtotime((string)$v),default=>(string)$v}; };
        $cmp=strtoupper(trim($cmp));
        if($cmp==='EXISTS')return $have!==null;if($cmp==='NOT EXISTS')return $have===null;
        if($have===null)return in_array($cmp,['!=','NOT IN','NOT LIKE'],true);
        $h=$conv(is_array($have)||is_object($have)?serialize($have):$have);
        switch($cmp){
            case '=':return $h==$conv($value);case '!=':return $h!=$conv($value);case '>':return $h>$conv($value);case '>=':return $h>=$conv($value);case '<':return $h<$conv($value);case '<=':return $h<=$conv($value);
            case 'LIKE':return stripos((string)$h,trim((string)$value,'%'))!==false;case 'NOT LIKE':return stripos((string)$h,trim((string)$value,'%'))===false;
            case 'IN':return in_array($h,array_map($conv,(array)$value),false);case 'NOT IN':return !in_array($h,array_map($conv,(array)$value),false);
            case 'BETWEEN':$v=(array)$value;return $h>=$conv($v[0]??0)&&$h<=$conv($v[1]??0);case 'NOT BETWEEN':$v=(array)$value;return !($h>=$conv($v[0]??0)&&$h<=$conv($v[1]??0));
            case 'REGEXP':return (bool)@preg_match('~'.str_replace('~','\~',(string)$value).'~i',(string)$h);case 'NOT REGEXP':return !@preg_match('~'.str_replace('~','\~',(string)$value).'~i',(string)$h);
        }
        return false;
    }
    private function meta_clause_ok(WP_Post $p, array $c): bool {
        if(isset($c['relation'])||!isset($c['key'])&&!isset($c['value'])&&is_array(reset($c))){ // verschachtelt
            $rel=strtoupper((string)($c['relation']??'AND'));$rs=[];foreach($c as $k=>$x)if(is_array($x))$rs[]=$this->meta_clause_ok($p,$x);
            return !$rs||($rel==='OR'?in_array(true,$rs,true):!in_array(false,$rs,true));
        }
        $key=$c['key']??'';$cmp=$c['compare']??(isset($c['value'])&&is_array($c['value'])?'IN':'=');if(!isset($c['value'])&&!isset($c['compare']))$cmp='EXISTS';
        $vals=$key===''?[]:get_post_meta($p->ID,$key,false);$vals=is_array($vals)?$vals:[];
        if(!$vals)return $this->meta_cmp(null,$c['value']??'',(string)$cmp,(string)($c['type']??'CHAR'));
        foreach($vals as $v)if($this->meta_cmp($v,$c['value']??'',(string)$cmp,(string)($c['type']??'CHAR')))return true;
        return false;
    }
    private function meta_ok(WP_Post $p, array $q): bool {
        if(($q['meta_key']??'')!==''){ $c=['key'=>$q['meta_key']];if(($q['meta_value']??'')!==''||isset($q['meta_value_num'])){ $c['value']=$q['meta_value']!==''?$q['meta_value']:$q['meta_value_num'];$c['compare']=$q['meta_compare']??'='; }else $c['compare']='EXISTS'; if(!$this->meta_clause_ok($p,$c))return false; }
        if(!empty($q['meta_query'])&&is_array($q['meta_query'])&&!$this->meta_clause_ok($p,$q['meta_query']))return false;
        return true;
    }
    private function sort(array $posts, array $q): array {
        $ob=$q['orderby']??'';$order=strtoupper((string)($q['order']??'DESC'))==='ASC'?'ASC':'DESC';
        if($ob===''||$ob===null){ $ob='date'; }
        if(!empty($q['post__in'])&&($ob==='post__in')){ $pos=array_flip(array_map('intval',(array)$q['post__in']));usort($posts,fn($a,$b)=>($pos[(int)$a->ID]??0)<=>($pos[(int)$b->ID]??0));return $posts; }
        $keys=[];
        if(is_array($ob)){ foreach($ob as $k=>$v){ if(is_int($k))$keys[]=[$v,$order]; else $keys[]=[$k,strtoupper((string)$v)==='ASC'?'ASC':'DESC']; } }
        else foreach(preg_split('/\s+/',trim((string)$ob)) as $k)if($k!=='')$keys[]=[$k,$order];
        if(in_array('rand',array_column($keys,0),true)){ shuffle($posts);return $posts; }
        $metaKey=(string)($q['meta_key']??'');
        $val=function(WP_Post $p,string $k) use($metaKey,$q){
            return match($k){'title'=>mb_strtolower($p->post_title),'name'=>$p->post_name,'modified'=>$p->post_modified,'ID','id'=>(int)$p->ID,'parent'=>(int)$p->post_parent,'menu_order'=>(int)$p->menu_order,'author'=>(int)$p->post_author,'comment_count'=>(int)$p->comment_count,
                'type'=>$p->post_type,'meta_value'=>(string)get_post_meta($p->ID,$metaKey,true),'meta_value_num'=>(float)get_post_meta($p->ID,$metaKey,true),'relevance'=>0,default=>$p->post_date};
        };
        usort($posts,function($a,$b) use($keys,$val){
            foreach($keys as [$k,$o]){ $x=$val($a,$k);$y=$val($b,$k);if($x==$y)continue;$r=$x<=>$y;return $o==='ASC'?$r:-$r; }
            return ((int)$b->ID<=>(int)$a->ID);
        });
        return $posts;
    }
    public function next_post() { $this->current_post++;$this->post=$this->posts[$this->current_post];return $this->post; }
    public function the_post() { global $post;$this->in_the_loop=true;if($this->current_post===-1)do_action_ref_array('loop_start',[&$this]);$post=$this->next_post();setup_postdata($post); }
    public function have_posts() { if($this->current_post+1<$this->post_count)return true;if($this->current_post+1===$this->post_count&&$this->post_count>0){ do_action_ref_array('loop_end',[&$this]);$this->rewind_posts(); }$this->in_the_loop=false;return false; }
    public function rewind_posts() { $this->current_post=-1;if($this->post_count>0)$this->post=$this->posts[0]; }
    public function reset_postdata() { if(!empty($this->post)){ $GLOBALS['post']=$this->post;setup_postdata($this->post); } }
    public function get_queried_object() { return $this->queried_object; }
    public function get_queried_object_id() { return (int)($this->queried_object->ID??($this->queried_object->term_id??0)); }
    public function is_main_query() { return $this===($GLOBALS['wp_the_query']??null); }
    public function get_search_query() { return (string)($this->query_vars['s']??''); }
    public function is_single($p='') { return $this->is_single; }
    public function is_page($p='') { return $this->is_page; }
}
}

function setup_postdata($post) {
    global $id,$authordata,$currentday,$currentmonth,$page,$pages,$multipage,$more,$numpages;
    $post=get_post($post);if(!$post)return false;
    $GLOBALS['post']=$post;$id=(int)$post->ID;$authordata=get_userdata((int)$post->post_author);
    $currentday=mysql2date('d.m.y',$post->post_date,false);$currentmonth=mysql2date('m',$post->post_date,false);$numpages=1;$multipage=0;$page=1;$more=1;$pages=[$post->post_content];
    do_action_ref_array('the_post',[&$post,&$GLOBALS['wp_query']]);return true;
}
function have_posts() { global $wp_query;return $wp_query?$wp_query->have_posts():false; }
function the_post() { global $wp_query;if($wp_query)$wp_query->the_post(); }
function rewind_posts() { global $wp_query;if($wp_query)$wp_query->rewind_posts(); }
function wp_reset_postdata() { global $wp_query;if(isset($wp_query))$wp_query->reset_postdata(); }
function wp_reset_query() { $GLOBALS['wp_query']=$GLOBALS['wp_the_query'];wp_reset_postdata(); }
function query_posts($query) { $GLOBALS['wp_query']=new WP_Query();return $GLOBALS['wp_query']->query($query); }
function in_the_loop() { global $wp_query;return $wp_query&&$wp_query->in_the_loop; }
function is_main_query() { return $GLOBALS['wp_query']===$GLOBALS['wp_the_query']; }
function get_queried_object() { global $wp_query;return $wp_query?$wp_query->get_queried_object():null; }
function get_queried_object_id() { global $wp_query;return $wp_query?$wp_query->get_queried_object_id():0; }
function get_query_var($var, $default_value='') { global $wp_query;return $wp_query?$wp_query->get($var,$default_value):$default_value; }
function set_query_var($var, $value) { global $wp_query;if($wp_query)$wp_query->set($var,$value); }
function get_search_query($escaped=true) { $q=get_query_var('s');return $escaped?esc_attr($q):$q; }
function elvado_wp_flag(string $f): bool { global $wp_query;return $wp_query&&!empty($wp_query->$f); }
function is_home() { return elvado_wp_flag('is_home'); }
function is_front_page() { global $wp_query;if(!$wp_query)return false;return 'posts'===get_option('show_on_front')?is_home():elvado_wp_flag('is_front_page'); }
function is_single($post='') { global $wp_query;if(!elvado_wp_flag('is_single'))return false;if($post==='')return true;$o=get_queried_object();return $o&&(in_array($o->ID,(array)$post)||in_array($o->post_name,(array)$post)||in_array($o->post_title,(array)$post)); }
function is_page($page='') { global $wp_query;if(!elvado_wp_flag('is_page'))return false;if($page==='')return true;$o=get_queried_object();return $o&&(in_array($o->ID,(array)$page)||in_array($o->post_name,(array)$page)||in_array($o->post_title,(array)$page)); }
function is_singular($post_types='') { if(!elvado_wp_flag('is_singular'))return false;if($post_types==='')return true;$o=get_queried_object();return $o&&in_array($o->post_type,(array)$post_types,true); }
function is_attachment($p='') { return elvado_wp_flag('is_attachment'); }
function is_archive() { return elvado_wp_flag('is_archive'); }
function is_category($c='') { return elvado_wp_flag('is_category'); }
function is_tag($t='') { return elvado_wp_flag('is_tag'); }
function is_tax($t='', $term='') { return elvado_wp_flag('is_tax'); }
function is_author($a='') { return elvado_wp_flag('is_author'); }
function is_date() { return elvado_wp_flag('is_date'); }
function is_year() { return elvado_wp_flag('is_year'); }
function is_month() { return elvado_wp_flag('is_month'); }
function is_day() { return elvado_wp_flag('is_day'); }
function is_time() { return elvado_wp_flag('is_time'); }
function is_search() { return elvado_wp_flag('is_search'); }
function is_404() { return elvado_wp_flag('is_404'); }
function is_feed($f='') { return elvado_wp_flag('is_feed'); }
function is_paged() { return elvado_wp_flag('is_paged'); }
function is_preview() { return elvado_wp_flag('is_preview'); }
function is_post_type_archive($pt='') { return elvado_wp_flag('is_post_type_archive'); }
function is_comments_popup() { return false; }
function is_embed() { return false; }
function is_trackback() { return false; }
function is_customize_preview_dummy() { return false; }
function is_privacy_policy() { return false; }
function is_robots() { return false; }
function wp_old_slug_redirect() {}
