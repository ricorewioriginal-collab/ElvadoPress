<?php
// Seitenzahlen/Blättern, Walker-Klassen und Navigationsmenüs (wp_nav_menu) – Menüs stammen aus den CMS-Menüs (oben/unten) oder der Seitenliste.

/* ───────── Seitenzahlen ───────── */
function get_pagenum_link($pagenum=1, $escape=true) {
    $r=$GLOBALS['elvado_wp_request']??['path'=>'/','query'=>[]];$base=home_url(rtrim($r['path'],'/').'/');
    $url=(int)$pagenum>1?$base.'page/'.(int)$pagenum.'/':$base;
    $qs=$r['query'];unset($qs['paged'],$qs['page']);if($qs)$url.='?'.http_build_query($qs);
    return $escape?esc_url($url):esc_url_raw($url);
}
function paginate_links($args='') {
    global $wp_query;
    $a=wp_parse_args($args,['base'=>'%_%','format'=>'?paged=%#%','total'=>$wp_query?(int)$wp_query->max_num_pages:1,'current'=>max(1,(int)get_query_var('paged')),'aria_current'=>'page','show_all'=>false,'prev_next'=>true,'prev_text'=>'&laquo; Zurück','next_text'=>'Weiter &raquo;','end_size'=>1,'mid_size'=>2,'type'=>'plain','add_args'=>[],'add_fragment'=>'','before_page_number'=>'','after_page_number'=>'']);
    $total=max(1,(int)$a['total']);$cur=(int)$a['current'];$end=max(1,(int)$a['end_size']);$mid=max(0,(int)$a['mid_size']);$links=[];
    $mk=function($n) use($a){ $l=$a['base']==='%_%'?get_pagenum_link($n,false):str_replace(['%_%','%#%'],[$n>1?str_replace('%#%',(string)$n,$a['format']):'',(string)$n],$a['base']); if($a['base']!=='%_%'&&$n<=1)$l=str_replace('%_%','',$a['base']); if($a['add_args'])$l=add_query_arg($a['add_args'],$l); return esc_url(apply_filters('paginate_links',$l.$a['add_fragment'])); };
    if($a['prev_next']&&$cur&&$cur>1)$links[]='<a class="prev page-numbers" href="'.$mk($cur-1).'">'.$a['prev_text'].'</a>';
    $dots=false;
    for($n=1;$n<=$total;$n++){
        if($n===$cur){ $links[]='<span aria-current="'.esc_attr($a['aria_current']).'" class="page-numbers current">'.$a['before_page_number'].number_format_i18n($n).$a['after_page_number'].'</span>';$dots=true; }
        elseif($a['show_all']||($n<=$end||($cur&&$n>=$cur-$mid&&$n<=$cur+$mid)||$n>$total-$end)){ $links[]='<a class="page-numbers" href="'.$mk($n).'">'.$a['before_page_number'].number_format_i18n($n).$a['after_page_number'].'</a>';$dots=true; }
        elseif($dots&&!$a['show_all']){ $links[]='<span class="page-numbers dots">&hellip;</span>';$dots=false; }
    }
    if($a['prev_next']&&$cur&&$cur<$total)$links[]='<a class="next page-numbers" href="'.$mk($cur+1).'">'.$a['next_text'].'</a>';
    if($a['type']==='array')return $links;
    if($a['type']==='list')return "<ul class='page-numbers'>\n\t<li>".implode("</li>\n\t<li>",$links)."</li>\n</ul>\n";
    return implode("\n",$links);
}
function get_the_posts_pagination($args=[]) {
    global $wp_query;if(!$wp_query||$wp_query->max_num_pages<2)return '';
    $a=wp_parse_args($args,['mid_size'=>1,'prev_text'=>'Zurück','next_text'=>'Weiter','screen_reader_text'=>'Beitrags-Navigation','aria_label'=>'Beiträge','class'=>'']);
    $links=paginate_links(['type'=>'list','mid_size'=>$a['mid_size'],'prev_text'=>$a['prev_text'],'next_text'=>$a['next_text']]);
    return '<nav class="navigation pagination" aria-label="'.esc_attr($a['aria_label']).'"><h2 class="screen-reader-text">'.esc_html($a['screen_reader_text']).'</h2><div class="nav-links">'.$links.'</div></nav>';
}
function the_posts_pagination($args=[]) { echo get_the_posts_pagination($args); }
function get_next_posts_page_link($max_page=0) { global $wp_query;$paged=max(1,(int)get_query_var('paged'));$max=$max_page?:(int)$wp_query->max_num_pages;return $paged<$max?get_pagenum_link($paged+1):null; }
function get_previous_posts_page_link() { $paged=max(1,(int)get_query_var('paged'));return $paged>1?get_pagenum_link($paged-1):null; }
function get_next_posts_link($label=null, $max_page=0) { $u=get_next_posts_page_link($max_page);return $u?'<a href="'.esc_url($u).'" '.apply_filters('next_posts_link_attributes','').'>'.($label??'Ältere Beiträge &raquo;').'</a>':null; }
function next_posts_link($label=null, $max_page=0) { echo get_next_posts_link($label,$max_page); }
function get_previous_posts_link($label=null) { $u=get_previous_posts_page_link();return $u?'<a href="'.esc_url($u).'" '.apply_filters('previous_posts_link_attributes','').'>'.($label??'&laquo; Neuere Beiträge').'</a>':null; }
function previous_posts_link($label=null) { echo get_previous_posts_link($label); }
function next_posts($max_page=0, $display=true) { $u=get_next_posts_page_link($max_page);if($display)echo esc_url((string)$u);return $u; }
function previous_posts($display=true) { $u=get_previous_posts_page_link();if($display)echo esc_url((string)$u);return $u; }
function get_the_posts_navigation($args=[]) {
    global $wp_query;if(!$wp_query||$wp_query->max_num_pages<2)return '';
    $a=wp_parse_args($args,['prev_text'=>'Ältere Beiträge','next_text'=>'Neuere Beiträge','screen_reader_text'=>'Beitrags-Navigation','aria_label'=>'Beiträge']);
    $nav='';if($p=get_next_posts_link($a['prev_text']))$nav.='<div class="nav-previous">'.$p.'</div>';if($n=get_previous_posts_link($a['next_text']))$nav.='<div class="nav-next">'.$n.'</div>';
    return $nav?'<nav class="navigation posts-navigation" aria-label="'.esc_attr($a['aria_label']).'"><h2 class="screen-reader-text">'.esc_html($a['screen_reader_text']).'</h2><div class="nav-links">'.$nav.'</div></nav>':'';
}
function the_posts_navigation($args=[]) { echo get_the_posts_navigation($args); }
function posts_nav_link($sep='', $prelabel='', $nxtlabel='') { echo get_previous_posts_link($prelabel?:null).($sep?:' &#8212; ').get_next_posts_link($nxtlabel?:null); }
function get_adjacent_post($in_same_term=false, $excluded_terms='', $previous=true, $taxonomy='category') {
    $p=get_post();if(!$p||$p->post_type==='page')return '';
    $q=new WP_Query(['post_type'=>$p->post_type,'posts_per_page'=>-1,'orderby'=>'date','order'=>'ASC','no_found_rows'=>true,'ignore_sticky_posts'=>true]);$list=$q->posts;
    if($in_same_term){ $ids=wp_get_object_terms($p->ID,$taxonomy,['fields'=>'ids']);$list=array_values(array_filter($list,fn($x)=>(bool)array_intersect($ids,wp_get_object_terms($x->ID,$taxonomy,['fields'=>'ids'])))); }
    foreach($list as $i=>$x)if((int)$x->ID===(int)$p->ID)return $previous?($list[$i-1]??''):($list[$i+1]??'');
    return '';
}
function get_previous_post($in_same_term=false, $excluded_terms='', $taxonomy='category') { return get_adjacent_post($in_same_term,$excluded_terms,true,$taxonomy); }
function get_next_post($in_same_term=false, $excluded_terms='', $taxonomy='category') { return get_adjacent_post($in_same_term,$excluded_terms,false,$taxonomy); }
function get_adjacent_post_link($format, $link, $in_same_term, $excluded_terms, $previous, $taxonomy) {
    $post=get_adjacent_post($in_same_term,$excluded_terms,$previous,$taxonomy);if(!$post)return '';
    $title=$post->post_title?:($previous?'Vorheriger Beitrag':'Nächster Beitrag');$rel=$previous?'prev':'next';
    $string='<a href="'.esc_url(get_permalink($post)).'" rel="'.$rel.'">';$inlink=str_replace('%title',$title,$link);$inlink=str_replace('%date',get_the_date('',$post),$inlink);$out=$string.$inlink.'</a>';
    return str_replace('%link',$out,$format);
}
function previous_post_link($format='&laquo; %link', $link='%title', $in_same_term=false, $excluded_terms='', $taxonomy='category') { echo get_adjacent_post_link($format,$link,$in_same_term,$excluded_terms,true,$taxonomy); }
function next_post_link($format='%link &raquo;', $link='%title', $in_same_term=false, $excluded_terms='', $taxonomy='category') { echo get_adjacent_post_link($format,$link,$in_same_term,$excluded_terms,false,$taxonomy); }
function get_the_post_navigation($args=[]) {
    $a=wp_parse_args($args,['prev_text'=>'%title','next_text'=>'%title','in_same_term'=>false,'excluded_terms'=>'','taxonomy'=>'category','screen_reader_text'=>'Beitrags-Navigation','aria_label'=>'Beiträge']);
    $nav='';$pl=get_adjacent_post_link('<div class="nav-previous">%link</div>',$a['prev_text'],$a['in_same_term'],$a['excluded_terms'],true,$a['taxonomy']);$nl=get_adjacent_post_link('<div class="nav-next">%link</div>',$a['next_text'],$a['in_same_term'],$a['excluded_terms'],false,$a['taxonomy']);
    $nav=$pl.$nl;return $nav?'<nav class="navigation post-navigation" aria-label="'.esc_attr($a['aria_label']).'"><h2 class="screen-reader-text">'.esc_html($a['screen_reader_text']).'</h2><div class="nav-links">'.$nav.'</div></nav>':'';
}
function the_post_navigation($args=[]) { echo get_the_post_navigation($args); }
function the_comments_pagination($args=[]) {}
function the_comments_navigation($args=[]) {}
function paginate_comments_links($args=[]) {}
function previous_comments_link($label='') {}
function next_comments_link($label='', $max=0) {}
function get_comments_pagenum_link($p=1,$max=0) { return get_pagenum_link($p); }

/* ───────── Walker ───────── */
if(!class_exists('Walker')){
#[AllowDynamicProperties]
class Walker {
    public $tree_type;public $db_fields=[];public $max_pages=1;public $has_children;
    public function start_lvl(&$output, $depth=0, $args=[]) {} public function end_lvl(&$output, $depth=0, $args=[]) {}
    public function start_el(&$output, $data_object, $depth=0, $args=[], $current_object_id=0) {} public function end_el(&$output, $data_object, $depth=0, $args=[]) {}
    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output) {
        if(!$element)return;$id_field=$this->db_fields['id'];$id=$element->$id_field;
        $this->has_children=!empty($children_elements[$id]);
        if(isset($args[0])&&is_array($args[0]))$args[0]['has_children']=$this->has_children;
        $this->start_el($output,$element,$depth,...array_values($args));
        if(($max_depth==0||$max_depth>$depth+1)&&isset($children_elements[$id])){
            $first=true;
            foreach($children_elements[$id] as $child){ if($first){ $this->start_lvl($output,$depth,...array_values($args));$first=false; } $this->display_element($child,$children_elements,$max_depth,$depth+1,$args,$output); }
            unset($children_elements[$id]);
            if(!$first)$this->end_lvl($output,$depth,...array_values($args));
        }
        $this->end_el($output,$element,$depth,...array_values($args));
    }
    public function walk($elements, $max_depth, ...$args) {
        $output='';if($max_depth<-1||empty($elements))return $output;
        $parent_field=$this->db_fields['parent'];$children=[];$top=[];
        foreach($elements as $e){ if(empty($e->$parent_field))$top[]=$e;else $children[$e->$parent_field][]=$e; }
        if(!$top){ $ids=[];foreach($elements as $e)$ids[$e->{$this->db_fields['id']}]=1;foreach($elements as $e)if(!isset($ids[$e->$parent_field]))$top[]=$e; }
        foreach($top as $e)$this->display_element($e,$children,$max_depth,0,$args,$output);
        return $output;
    }
    public function paged_walk($elements, $max_depth, $page_num, $per_page, ...$args) { return $this->walk($elements,$max_depth,...$args); }
    public function get_number_of_root_elements($elements) { $n=0;foreach($elements as $e)if(empty($e->{$this->db_fields['parent']}))$n++;return $n; }
    public function unset_children($e, &$children) {}
}
class Walker_Nav_Menu extends Walker {
    public $tree_type=['post_type','taxonomy','custom'];public $db_fields=['parent'=>'menu_item_parent','id'=>'db_id'];
    public function start_lvl(&$output, $depth=0, $args=null) { $i=str_repeat("\t",$depth);$output.="\n$i<ul class=\"sub-menu\">\n"; }
    public function end_lvl(&$output, $depth=0, $args=null) { $i=str_repeat("\t",$depth);$output.="$i</ul>\n"; }
    public function start_el(&$output, $data_object, $depth=0, $args=null, $current_object_id=0) {
        $item=$data_object;$args=(object)(is_array($args)?$args:(array)$args);$classes=empty($item->classes)?[]:(array)$item->classes;$classes[]='menu-item-'.$item->ID;
        $classes=array_filter(apply_filters('nav_menu_css_class',$classes,$item,$args,$depth));
        $class=$classes?' class="'.esc_attr(implode(' ',$classes)).'"':'';$id=apply_filters('nav_menu_item_id','menu-item-'.$item->ID,$item,$args,$depth);$id=$id?' id="'.esc_attr($id).'"':'';
        $output.=str_repeat("\t",$depth).'<li'.$id.$class.'>';
        $atts=['title'=>$item->attr_title??'','target'=>$item->target??'','rel'=>$item->xfn??'','href'=>$item->url??''];
        if(!empty($item->current))$atts['aria-current']='page';$atts=apply_filters('nav_menu_link_attributes',$atts,$item,$args,$depth);
        $attr='';foreach($atts as $k=>$v)if($v!==''&&$v!==null)$attr.=' '.$k.'="'.esc_attr($k==='href'?esc_url($v):$v).'"';
        $title=apply_filters('nav_menu_item_title',apply_filters('the_title',$item->title,$item->ID),$item,$args,$depth);
        $out=($args->before??'').'<a'.$attr.'>'.($args->link_before??'').$title.($args->link_after??'').'</a>'.($args->after??'');
        $output.=apply_filters('walker_nav_menu_start_el',$out,$item,$depth,$args);
    }
    public function end_el(&$output, $data_object, $depth=0, $args=null) { $output.="</li>\n"; }
}
class Walker_Page extends Walker_Nav_Menu {}
class Walker_Category extends Walker {}
class Walker_Comment extends Walker {
    public $tree_type='comment';public $db_fields=['parent'=>'comment_parent','id'=>'comment_ID'];
    public function start_lvl(&$output, $depth=0, $args=[]) { $output.='<ol class="children">'."\n"; }
    public function end_lvl(&$output, $depth=0, $args=[]) { $output.="</ol><!-- .children -->\n"; }
    public function start_el(&$output, $data_object, $depth=0, $args=[], $current_object_id=0) {
        $GLOBALS['comment']=$data_object;$GLOBALS['comment_depth']=$depth+1;$args=is_array($args)?$args:(array)$args;
        if(!empty($args['callback'])){ ob_start();call_user_func($args['callback'],$data_object,$args,$depth+1);$output.=ob_get_clean();return; }
        ob_start();elvado_wp_default_comment($data_object,$args,$depth+1);$output.=ob_get_clean();
    }
    public function end_el(&$output, $data_object, $depth=0, $args=[]) { $args=is_array($args)?$args:(array)$args;if(!empty($args['end-callback'])){ob_start();call_user_func($args['end-callback'],$data_object,$args,$depth+1);$output.=ob_get_clean();return;}$output.="</li><!-- #comment-## -->\n"; }
}
}

/* ───────── Navigationsmenüs ───────── */
$GLOBALS['_wp_registered_nav_menus']=$GLOBALS['_wp_registered_nav_menus']??[];
function register_nav_menus($locations=[]) { $GLOBALS['_wp_registered_nav_menus']=array_merge($GLOBALS['_wp_registered_nav_menus'],(array)$locations);add_theme_support('menus'); }
function register_nav_menu($location, $description) { register_nav_menus([$location=>$description]); }
function unregister_nav_menu($location) { unset($GLOBALS['_wp_registered_nav_menus'][$location]);return true; }
function get_registered_nav_menus() { return $GLOBALS['_wp_registered_nav_menus']; }
/** Position → CMS-Menü: erste Position = oben, Positionen mit footer/bottom/unten im Namen (oder die zweite) = unten. */
function get_nav_menu_locations() {
    $saved=get_theme_mod('nav_menu_locations',[]);$saved=is_array($saved)?$saved:[];
    $out=[];$i=0;foreach(array_keys($GLOBALS['_wp_registered_nav_menus']) as $loc){ $out[$loc]=(preg_match('/foot|bottom|unten|social/i',$loc)||$i===1)?'bottom':'top';$i++; }
    foreach($saved as $loc=>$m)if(isset($out[$loc])&&in_array($m,['top','bottom',''],true))$out[$loc]=$m;   // gespeicherte Zuordnung ('' = kein Menü) hat Vorrang
    return $out;
}
function elvado_wp_cms_menu_items(string $menu): array {
    $site=elvado_wp_cms_data()['site'];$raw=(array)($site['menus'][$menu]??[]);$items=[];$pageUrl=[];
    foreach(elvado_wp_cms_pages() as $pg)$pageUrl[(string)($pg->elvado_data['id']??'')]=$pg;
    if(elvado_wp_bridge('menus'))foreach(elvado_wp_cms_pages() as $pg)$pageUrl[$pg->post_name]=$pageUrl[$pg->post_name]??$pg;   // Menüziele „page:<Adresse>“ wie im CMS-Menü-Editor
    $i=0;
    foreach($raw as $m){
        if(!is_array($m)||empty($m['enabled']))continue;$t=(string)($m['target']??'');[$kind,$val]=array_pad(explode(':',$t,2),2,'');$url='#';$type='custom';$object='custom';$oid=0;
        if($kind==='system'){ $url=$val==='start'?home_url('/'):elvado_wp_system_url($val); }
        elseif($kind==='page'&&isset($pageUrl[$val])){ $url=get_permalink($pageUrl[$val]);$type='post_type';$object='page';$oid=(int)$pageUrl[$val]->ID; }
        elseif($kind==='url'||$kind==='action'||preg_match('~^https?://~',$t)){ $url=$kind==='url'?$val:(preg_match('~^https?://~',$t)?$t:'#'); }
        $id=crc32((string)($m['id']??$i))%900000+3000000;
        $items[]=(object)['ID'=>$id,'db_id'=>$id,'menu_item_parent'=>(string)($m['parent_id']??'')!==''?(crc32((string)$m['parent_id'])%900000+3000000):0,'menu_order'=>++$i,'title'=>(string)($m['label']??''),'url'=>$url,'target'=>'','attr_title'=>'','description'=>'','classes'=>[''],'xfn'=>'','type'=>$type,'object'=>$object,'object_id'=>$oid,'post_type'=>'nav_menu_item','post_parent'=>0,'current'=>false,'current_item_ancestor'=>false,'current_item_parent'=>false,'type_label'=>''];
    }
    return $items;
}
/** Adresse eines Portal-Bereichs (Sender, Sendeplan, News …): die vorhandene Seite der Website (z. B. /sender.html), sonst wie bisher der Anker (/#sender) – im Theme führt ein Anker ins Leere. */
function elvado_wp_system_url(string $target): string {
    $sl=elvado_wp_system_slug($target);if($sl!==''){ foreach(elvado_wp_cms_pages() as $pg)if($pg->post_name===$sl&&(($pg->elvado_data['type']??'')==='system'))return get_permalink($pg); }   // eigene Theme-Seite des Portal-Bereichs
    if($target==='news'){ $pp=(int)get_option('page_for_posts');if(get_option('show_on_front')==='page'&&$pp&&($pg=get_post($pp)))return get_permalink($pg);return home_url('/'); }   // die Beitragsübersicht des Themes
    static $map=null;if($map===null){ $f=dirname(__DIR__,2).'/lib/seo.php';$map=[];if(is_file($f)){ if(!function_exists('elvado_seo_system_map'))require_once $f;$map=elvado_seo_system_map(); } }
    $file=$map[$target]??'';
    if($file!==''&&preg_match('/^[a-z0-9_-]+$/',$file)&&is_file(elvado_wp_cms_root().'/'.$file.'.html'))return home_url('/'.$file.'.html');
    return home_url('/#'.$target);
}
function wp_get_nav_menu_items($menu, $args=[]) { $name=is_object($menu)?$menu->slug:(string)$menu;if(!in_array($name,['top','bottom'],true))return false;return elvado_wp_cms_menu_items($name); }
function wp_get_nav_menu_object($menu) { $n=is_object($menu)?$menu->slug:(string)$menu;return in_array($n,['top','bottom'],true)?(object)['term_id'=>$n==='top'?1:2,'name'=>$n==='top'?'Hauptmenü':'Fußmenü','slug'=>$n,'count'=>count(elvado_wp_cms_menu_items($n))]:false; }
function wp_get_nav_menus($args=[]) { return [wp_get_nav_menu_object('top'),wp_get_nav_menu_object('bottom')]; }
function is_nav_menu($menu) { return (bool)wp_get_nav_menu_object($menu); }
function has_nav_menu($location) { $l=get_nav_menu_locations();return isset($l[$location],$GLOBALS['_wp_registered_nav_menus'][$location])&&(bool)elvado_wp_cms_menu_items((string)$l[$location]); }
function wp_nav_menu($args=[]) {
    $d=['menu'=>'','menu_class'=>'menu','menu_id'=>'','container'=>'div','container_class'=>'','container_id'=>'','container_aria_label'=>'','fallback_cb'=>'wp_page_menu','before'=>'','after'=>'','link_before'=>'','link_after'=>'','echo'=>true,'depth'=>0,'walker'=>'','theme_location'=>'','items_wrap'=>'<ul id="%1$s" class="%2$s">%3$s</ul>','item_spacing'=>'preserve'];
    $a=(object)apply_filters('wp_nav_menu_args',wp_parse_args($args,$d));
    $name='';$loc=(string)$a->theme_location;
    if($loc!==''){ $locs=get_nav_menu_locations();$name=(string)($locs[$loc]??''); } elseif($a->menu!=='')$name=is_object($a->menu)?$a->menu->slug:(string)$a->menu;
    $items=$name!==''?wp_get_nav_menu_items($name):false;
    if(!$items){ if(is_callable($a->fallback_cb)&&$a->fallback_cb!==''){ if(!$a->echo){ ob_start();call_user_func($a->fallback_cb,(array)$a);return ob_get_clean(); } return call_user_func($a->fallback_cb,(array)$a); } return false; }
    // aktuelle Seite markieren
    $cur=rtrim((string)strtok(home_url(($GLOBALS['elvado_wp_request']['path']??'/')),'?'),'/');
    foreach($items as $it){ $it->classes=array_values(array_filter(array_merge((array)$it->classes,['menu-item','menu-item-type-'.$it->type,'menu-item-object-'.$it->object])));
        if(rtrim((string)strtok($it->url,'?'),'/')===$cur&&$it->url!=='#'){ $it->current=true;$it->classes[]='current-menu-item';$it->classes[]='current_page_item'; } }
    foreach($items as $it)if(!empty($it->current)&&$it->menu_item_parent){ foreach($items as $p)if($p->db_id==$it->menu_item_parent){ $p->current_item_parent=true;$p->classes[]='current-menu-parent'; } }
    $items=apply_filters('wp_nav_menu_objects',$items,$a);
    $walker=$a->walker?:new Walker_Nav_Menu();
    $inner=$walker->walk($items,(int)$a->depth,$a);
    $inner=apply_filters('wp_nav_menu_items',$inner,$a);
    $id=$a->menu_id?:'menu-'.sanitize_title($name?:'top');
    $nav=sprintf($a->items_wrap,esc_attr($id),esc_attr($a->menu_class),$inner);
    if($a->container){ $nav='<'.$a->container.($a->container_id?' id="'.esc_attr($a->container_id).'"':'').($a->container_class?' class="'.esc_attr($a->container_class).'"':'').($a->container_aria_label?' aria-label="'.esc_attr($a->container_aria_label).'"':'').'>'.$nav.'</'.$a->container.'>'; }
    $nav=apply_filters('wp_nav_menu',$nav,$a);
    if($a->echo)echo $nav;else return $nav;
}
function wp_list_pages($args='') {
    $a=wp_parse_args($args,['depth'=>0,'show_date'=>'','title_li'=>'Seiten','echo'=>1,'sort_column'=>'menu_order, post_title','link_before'=>'','link_after'=>'','post_type'=>'page','walker'=>'']);
    $pages=get_pages($a);$o='';foreach($pages as $p)$o.='<li class="page_item page-item-'.$p->ID.'"><a href="'.esc_url(get_permalink($p)).'">'.$a['link_before'].esc_html($p->post_title).$a['link_after'].'</a></li>';
    if($a['title_li']&&$o)$o='<li class="pagenav">'.$a['title_li'].'<ul>'.$o.'</ul></li>';$o=apply_filters('wp_list_pages',$o,$a);if($a['echo'])echo $o;else return $o;
}
function wp_page_menu($args=[]) {
    $a=wp_parse_args($args,['sort_column'=>'menu_order, post_title','menu_id'=>'','menu_class'=>'menu','container'=>'div','echo'=>true,'show_home'=>false,'before'=>'<ul>','after'=>'</ul>']);
    $m='';if($a['show_home'])$m.='<li class="'.(is_front_page()?'current_page_item':'').'"><a href="'.esc_url(home_url('/')).'">'.esc_html(is_string($a['show_home'])?$a['show_home']:'Startseite').'</a></li>';
    $m.=wp_list_pages(['echo'=>0,'title_li'=>'']);
    $m=$m?$a['before'].$m.$a['after']:'';$m=$a['container']&&$m?'<'.$a['container'].' class="'.esc_attr($a['menu_class']).'">'.$m.'</'.$a['container'].'>':$m;
    $m=apply_filters('wp_page_menu',$m,$a);if($a['echo'])echo $m;else return $m;
}
function wp_get_archives($args='') {
    $a=wp_parse_args($args,['type'=>'monthly','limit'=>'','format'=>'html','before'=>'','after'=>'','echo'=>1,'show_post_count'=>false]);$months=[];
    foreach(get_posts(['numberposts'=>-1,'fields'=>'all']) as $p){ $k=substr($p->post_date,0,7);$months[$k]=($months[$k]??0)+1; }
    krsort($months);$o='';$n=0;foreach($months as $k=>$c){ if($a['limit']&&++$n>(int)$a['limit'])break;[$y,$m]=explode('-',$k);$o.=$a['before'].'<li><a href="'.esc_url(get_month_link((int)$y,(int)$m)).'">'.esc_html(wp_date('F Y',mktime(0,0,0,(int)$m,1,(int)$y))).'</a>'.($a['show_post_count']?'&nbsp;('.$c.')':'').'</li>'.$a['after']; }
    if($a['echo'])echo $o;else return $o;
}
function get_calendar($initial=true, $display=true) { return ''; }
function wp_loginout($redirect='', $display=true) { return ''; }
function wp_register($before='<li>', $after='</li>', $display=true) { return ''; }
function wp_meta() { do_action('wp_meta'); }
