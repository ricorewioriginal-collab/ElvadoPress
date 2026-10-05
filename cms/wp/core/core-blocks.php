<?php
// Serverseitig gerenderte Kern-Blöcke (core/*) für Block-Themes: Vorlagenteile, Muster, Seiten-/Beitragsfelder, Query-Loop, Navigation, Suche, Kommentare …

/** Hilfsfunktionen */
function rrw_wp_blk_wrap(string $inner, string $tag='div', array $extra=[]): string {
    $a=get_block_wrapper_attributes($extra);return '<'.$tag.($a!==''?' '.$a:'').'>'.$inner.'</'.$tag.'>';
}
function rrw_wp_blk_post($block): ?WP_Post {
    $id=(int)($block->context['postId']??0);
    if($id>0){ $p=get_post($id);if($p instanceof WP_Post)return $p; }
    $p=get_post();return $p instanceof WP_Post?$p:null;
}
function rrw_wp_blk_svg(string $name): string {
    static $s=[
        'open'=>'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="7.5" width="16" height="1.5" /><rect x="4" y="15" width="16" height="1.5" /></svg>',
        'close'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"></path></svg>',
        'sub'=>'<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false"><path d="M1.50002 4L6.00002 8L10.5 4" stroke-width="1.5"></path></svg>',
    ];
    return $s[$name]??'';
}

/* ───────── Vorlagenteile & Muster ───────── */
/** Blockmarkup mit dem Kontext (postId, queryId …) des umgebenden Blocks rendern. */
function rrw_wp_blk_render_ctx(string $html, $block): string {
    $o='';foreach(parse_blocks($html) as $b){ $pre=apply_filters('pre_render_block',null,$b,null);$o.=$pre!==null?(string)$pre:(empty($b['blockName'])?(string)$b['innerHTML']:(new WP_Block($b,$block->available_context))->render()); }
    return $o;
}
function rrw_wp_blk_template_part($attrs, $content, $block) {
    $slug=(string)($attrs['slug']??'');if($slug==='')return '';
    $f=rrw_wp_block_template_file($slug,'wp_template_part');if($f==='')return '';
    $html=(string)file_get_contents($f);
    static $depth=0;if($depth>12)return '';$depth++;
    try{ $inner=rrw_wp_blk_render_ctx($html,$block); } finally { $depth--; }
    $tag=preg_match('/^[a-z0-9]+$/',(string)($attrs['tagName']??''))?$attrs['tagName']:'div';
    $area=(string)($attrs['area']??'');if($area==='' )$area=in_array($slug,['header','footer'],true)?$slug:'';
    if(in_array($tag,['div'],true)&&$area==='header')$tag='header';
    $a=get_block_wrapper_attributes();
    return '<'.$tag.($a!==''?' '.$a:'').'>'.$inner.'</'.$tag.'>';
}
function rrw_wp_blk_pattern($attrs, $content, $block) {
    $slug=(string)($attrs['slug']??'');$c=rrw_wp_pattern_content($slug);if($c===null||$c==='')return '';
    static $depth=0;if($depth>12)return '';$depth++;
    try{ return rrw_wp_blk_render_ctx($c,$block); } finally { $depth--; }
}

/* ───────── Website-Felder ───────── */
function rrw_wp_blk_site_title($attrs, $content, $block) {
    $lvl=(int)($attrs['level']??1);$tag=$lvl>0&&$lvl<7?'h'.$lvl:'p';$t=get_bloginfo('name');if($t==='')return '';
    $inner=($attrs['isLink']??true)?'<a href="'.esc_url(home_url('/')).'" target="'.esc_attr($attrs['linkTarget']??'_self').'" rel="home">'.esc_html($t).'</a>':esc_html($t);
    return rrw_wp_blk_wrap($inner,$tag);
}
function rrw_wp_blk_site_tagline($attrs, $content, $block) { $t=get_bloginfo('description');if($t==='')return '';$lvl=(int)($attrs['level']??0);return rrw_wp_blk_wrap($t,$lvl>0&&$lvl<7?'h'.$lvl:'p'); }
function rrw_wp_blk_site_logo($attrs, $content, $block) {
    $logo=function_exists('get_custom_logo')?get_custom_logo():'';if($logo==='')return '';
    $w=(int)($attrs['width']??120);
    $logo=preg_replace('/<img /','<img style="width:'.$w.'px;height:auto" ',$logo,1);
    return rrw_wp_blk_wrap(preg_replace('/^<a /','<a ',$logo));
}

/* ───────── Beitragsfelder ───────── */
function rrw_wp_blk_post_title($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';$t=get_the_title($p);if($t==='')return '';
    $lvl=(int)($attrs['level']??2);$tag=$lvl>0&&$lvl<7?'h'.$lvl:'p';
    if(!empty($attrs['isLink']))$t='<a href="'.esc_url(get_permalink($p)).'" target="'.esc_attr($attrs['linkTarget']??'_self').'" rel="'.esc_attr($attrs['rel']??'').'">'.$t.'</a>';
    return rrw_wp_blk_wrap($t,$tag);
}
function rrw_wp_blk_post_content($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';
    static $seen=[];if(isset($seen[$p->ID]))return '';$seen[$p->ID]=true;
    try{
        $old=$GLOBALS['post']??null;$GLOBALS['post']=$p;setup_postdata($p);
        $html=apply_filters('the_content',str_replace(']]>',']]&gt;',get_the_content(null,false,$p)));
        $GLOBALS['post']=$old;if($old)setup_postdata($old);
    } finally { unset($seen[$p->ID]); }
    if(trim($html)==='')return '';
    return rrw_wp_blk_wrap($html,'div',['class'=>'entry-content']);
}
function rrw_wp_blk_post_excerpt($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';
    $len=(int)($attrs['excerptLength']??55);$ex=has_excerpt($p)?$p->post_excerpt:wp_trim_words(strip_shortcodes(strip_tags(do_blocks($p->post_content))),$len,'…');
    if($ex==='')return '';
    $more=$attrs['moreText']??'';$link=$more!==''?'<a class="wp-block-post-excerpt__more-link" href="'.esc_url(get_permalink($p)).'">'.wp_kses_post($more).'</a>':'';
    $inner='<p class="wp-block-post-excerpt__excerpt">'.esc_html($ex).(empty($attrs['showMoreOnNewLine'])?' '.$link:'').'</p>'.(!empty($attrs['showMoreOnNewLine'])&&$link?'<p class="wp-block-post-excerpt__more-text">'.$link.'</p>':'');
    return rrw_wp_blk_wrap($inner);
}
function rrw_wp_blk_post_date($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';
    $mod=($attrs['displayType']??'date')==='modified';$fmt=(string)($attrs['format']??'')?:get_option('date_format','j. F Y');
    $ts=strtotime($mod?$p->post_modified_gmt.' UTC':$p->post_date_gmt.' UTC')?:strtotime($mod?$p->post_modified:$p->post_date);
    $txt=wp_date($fmt,$ts);$iso=gmdate('c',$ts);
    $inner='<time datetime="'.esc_attr($iso).'">'.(!empty($attrs['isLink'])?'<a href="'.esc_url(get_permalink($p)).'">'.esc_html($txt).'</a>':esc_html($txt)).'</time>';
    return rrw_wp_blk_wrap($inner);
}
function rrw_wp_blk_post_featured_image($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';
    $id=(int)get_post_thumbnail_id($p);if(!$id)return '';
    $size=(string)($attrs['sizeSlug']??'post-thumbnail');
    $img=get_the_post_thumbnail($p,$size);if($img==='')return '';
    $st='';if(!empty($attrs['aspectRatio']))$st.='aspect-ratio:'.$attrs['aspectRatio'].';';if(!empty($attrs['width']))$st.='width:'.$attrs['width'].';';if(!empty($attrs['height']))$st.='height:'.$attrs['height'].';';
    if(!empty($attrs['scale'])&&$st!=='')$st.='object-fit:'.$attrs['scale'].';';
    if($st!=='')$img=preg_match('/ style="/',$img)?preg_replace('/ style="/',' style="'.$st,$img,1):preg_replace('/<img /','<img style="'.esc_attr($st).'" ',$img,1);
    if(!empty($attrs['isLink']))$img='<a href="'.esc_url(get_permalink($p)).'" target="'.esc_attr($attrs['linkTarget']??'_self').'">'.$img.'</a>';
    return rrw_wp_blk_wrap($img,'figure');
}
function rrw_wp_blk_post_terms($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);$tax=(string)($attrs['term']??'');if(!$p||$tax==='')return '';
    $terms=get_the_terms($p,$tax);if(!$terms||is_wp_error($terms))return '';
    $sep=(string)($attrs['separator']??', ');$links=[];
    foreach($terms as $t){ $l=get_term_link($t);if(is_wp_error($l))continue;$links[]='<a href="'.esc_url($l).'" rel="tag">'.esc_html($t->name).'</a>'; }
    if(!$links)return '';
    $pre=!empty($attrs['prefix'])?'<span class="wp-block-post-terms__prefix">'.$attrs['prefix'].'</span>':'';$suf=!empty($attrs['suffix'])?'<span class="wp-block-post-terms__suffix">'.$attrs['suffix'].'</span>':'';
    $a=get_block_wrapper_attributes(['class'=>'taxonomy-'.$tax]);
    return '<div '.$a.'>'.$pre.implode('<span class="wp-block-post-terms__separator">'.esc_html($sep).'</span>',$links).$suf.'</div>';
}
function rrw_wp_blk_post_author($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';$u=get_userdata((int)$p->post_author);if(!$u)return '';
    $av=!empty($attrs['showAvatar'])||!isset($attrs['showAvatar'])?'<div class="wp-block-post-author__avatar">'.get_avatar($u->ID,(int)($attrs['avatarSize']??48)).'</div>':'';
    $by=!empty($attrs['byline'])?'<p class="wp-block-post-author__byline">'.esc_html($attrs['byline']).'</p>':'';
    $name='<p class="wp-block-post-author__name">'.(!empty($attrs['isLink'])?'<a href="'.esc_url(get_author_posts_url($u->ID)).'" target="_self">'.esc_html($u->display_name).'</a>':esc_html($u->display_name)).'</p>';
    $bio=!empty($attrs['showBio'])?'<p class="wp-block-post-author__bio">'.esc_html(get_the_author_meta('description',$u->ID)).'</p>':'';
    return rrw_wp_blk_wrap($av.'<div class="wp-block-post-author__content">'.$by.$name.$bio.'</div>');
}
function rrw_wp_blk_post_author_name($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';$u=get_userdata((int)$p->post_author);if(!$u)return '';
    $n=esc_html($u->display_name);if(!empty($attrs['isLink']))$n='<a href="'.esc_url(get_author_posts_url($u->ID)).'" target="'.esc_attr($attrs['linkTarget']??'_self').'" class="wp-block-post-author-name__link">'.$n.'</a>';
    return rrw_wp_blk_wrap($n);
}
function rrw_wp_blk_read_more($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';$txt=(string)($attrs['content']??'')?:'Weiterlesen';
    $a=get_block_wrapper_attributes(['class'=>'wp-element-button']);
    return '<a '.$a.' href="'.esc_url(get_permalink($p)).'" target="'.esc_attr($attrs['linkTarget']??'_self').'">'.wp_kses_post($txt).'<span class="screen-reader-text">: '.esc_html(get_the_title($p)).'</span></a>';
}
function rrw_wp_blk_post_nav_link($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p||!function_exists('get_adjacent_post'))return '';
    $next=($attrs['type']??'next')!=='previous';$adj=get_adjacent_post(false,'',!$next);if(!$adj)return '';
    $label=(string)($attrs['label']??'')?:($next?'Nächster Beitrag':'Vorheriger Beitrag');
    $txt='<span class="post-navigation-link__label">'.esc_html($label).'</span>';
    if(!empty($attrs['showTitle']))$txt.=' <span class="post-navigation-link__title">'.esc_html(get_the_title($adj)).'</span>';
    $arrow=($attrs['linkLabel']??false)?'':'';
    $a=get_block_wrapper_attributes(['class'=>'post-navigation-link-'.($next?'next':'previous')]);
    return '<div '.$a.'><a href="'.esc_url(get_permalink($adj)).'" rel="'.($next?'next':'prev').'">'.$txt.'</a></div>';
}
function rrw_wp_blk_avatar($attrs, $content, $block) {
    $size=(int)($attrs['size']??96);$id=(int)($block->context['commentId']??0);
    if($id&&function_exists('get_comment')){ $c=get_comment($id);$img=$c?get_avatar($c,$size):''; } else { $p=rrw_wp_blk_post($block);$img=$p?get_avatar((int)$p->post_author,$size):''; }
    return $img!==''?rrw_wp_blk_wrap($img):'';
}
function rrw_wp_blk_term_description($attrs, $content, $block) {
    $o=get_queried_object();$d=is_object($o)&&isset($o->description)?(string)$o->description:'';
    return $d!==''?rrw_wp_blk_wrap(wpautop($d)):'';
}

/* ───────── Query-Loop ───────── */
function rrw_wp_blk_query_for(array $ctx): WP_Query {
    static $cache=[];
    $q=(array)($ctx['query']??[]);$id=(int)($ctx['queryId']??0);
    if(!empty($q['inherit'])){ $m=$GLOBALS['wp_query']??null;if($m instanceof WP_Query)return $m; }
    $key=md5(json_encode([$q,$id,$_GET["query-$id-page"]??1]));
    if(isset($cache[$key]))return $cache[$key];
    $page=max(1,(int)($_GET["query-$id-page"]??1));$per=max(1,(int)($q['perPage']??10));
    $args=['post_type'=>$q['postType']??'post','posts_per_page'=>$per,'paged'=>$page,'post_status'=>'publish','ignore_sticky_posts'=>true,
        'order'=>strtoupper((string)($q['order']??'DESC'))==='ASC'?'ASC':'DESC','orderby'=>match((string)($q['orderBy']??'date')){'title'=>'title','modified'=>'modified','rand'=>'rand','menu_order'=>'menu_order','comment_count'=>'comment_count',default=>'date'}];
    if(!empty($q['offset']))$args['offset']=(int)$q['offset']+($page-1)*$per;
    if(!empty($q['search']))$args['s']=(string)$q['search'];
    if(!empty($q['exclude']))$args['post__not_in']=array_map('intval',(array)$q['exclude']);
    if(!empty($q['author']))$args['author']=(int)$q['author'];
    if(!empty($q['taxQuery'])&&is_array($q['taxQuery'])){ foreach($q['taxQuery'] as $tax=>$ids){ if($tax==='category'&&$ids)$args['category__in']=array_map('intval',(array)$ids);elseif($tax==='post_tag'&&$ids)$args['tag__in']=array_map('intval',(array)$ids); } }
    if(!empty($q['sticky'])&&$q['sticky']==='only')$args['post__in']=array_map('intval',(array)get_option('sticky_posts',[]))?:[0];
    return $cache[$key]=new WP_Query($args);
}
function rrw_wp_blk_query($attrs, $content, $block) {
    return $content;   // der Wrapper steht im gespeicherten Markup
}
function rrw_wp_blk_post_template($attrs, $content, $block) {
    $q=rrw_wp_blk_query_for($block->context);
    if(!$q->have_posts())return '';
    $inherit=!empty(($block->context['query']??[])['inherit']);
    $cls=['wp-block-post-template'];
    $dl=(array)($block->context['displayLayout']??[]);
    $extra=['class'=>'wp-block-post-template'];
    if(($dl['type']??'')==='flex'){ $extra['class'].=' is-flex-container columns-'.(int)($dl['columns']??3); }
    $out='';$old=$GLOBALS['post']??null;
    $q->rewind_posts();
    while($q->have_posts()){
        $q->the_post();$p=$GLOBALS['post'];
        $ctx=$block->available_context+['postId'=>$p->ID,'postType'=>$p->post_type];
        $inner='';foreach($block->parsed_block['innerBlocks'] as $ib)$inner.=(new WP_Block($ib,$ctx))->render();
        $pc=implode(' ',get_post_class('wp-block-post',$p->ID));
        $out.='<li class="'.esc_attr($pc).'">'.$inner.'</li>';
    }
    if($old){ $GLOBALS['post']=$old;setup_postdata($old); } else wp_reset_postdata();
    $a=get_block_wrapper_attributes($extra);
    return '<ul '.$a.'>'.$out.'</ul>';
}
function rrw_wp_blk_query_no_results($attrs, $content, $block) {
    $q=rrw_wp_blk_query_for($block->context);if($q->have_posts())return '';
    return rrw_wp_blk_wrap($content);
}
function rrw_wp_blk_query_title($attrs, $content, $block) {
    $lvl=(int)($attrs['level']??1);$tag=$lvl>0&&$lvl<7?'h'.$lvl:'h1';
    if(($attrs['type']??'archive')==='search'){ $t=is_search()?sprintf('Suchergebnisse für: „%s“',esc_html(get_search_query())):''; }
    else{ $t=function_exists('get_the_archive_title')?get_the_archive_title():''; if(!empty($attrs['showPrefix'])===false&&$t)$t=preg_replace('/^[^:]+:\s*/u','',$t); }
    return $t!==''?rrw_wp_blk_wrap($t,$tag):'';
}
function rrw_wp_blk_query_total($attrs, $content, $block) { $q=rrw_wp_blk_query_for($block->context);return rrw_wp_blk_wrap((string)(int)$q->found_posts); }
function rrw_wp_blk_page_link(int $page, array $ctx): string {
    $q=(array)($ctx['query']??[]);
    if(!empty($q['inherit'])){ return function_exists('get_pagenum_link')?(string)get_pagenum_link($page):add_query_arg('paged',$page); }
    return (string)add_query_arg('query-'.(int)($ctx['queryId']??0).'-page',$page);
}
function rrw_wp_blk_pagination_next($attrs, $content, $block) {
    $q=rrw_wp_blk_query_for($block->context);$page=max(1,(int)($q->get('paged')?:1));if($page>=(int)$q->max_num_pages)return '';
    $label=(string)($attrs['label']??'')?:'Nächste Seite';$arrow=($block->context['paginationArrow']??'none');$ar=$arrow==='arrow'?'<span class="wp-block-query-pagination-next-arrow is-arrow-arrow" aria-hidden="true">→</span>':($arrow==='chevron'?'<span class="wp-block-query-pagination-next-arrow is-arrow-chevron" aria-hidden="true">»</span>':'');
    $a=get_block_wrapper_attributes();
    return '<a href="'.esc_url(rrw_wp_blk_page_link($page+1,$block->context)).'" '.$a.'>'.$label.$ar.'</a>';
}
function rrw_wp_blk_pagination_previous($attrs, $content, $block) {
    $q=rrw_wp_blk_query_for($block->context);$page=max(1,(int)($q->get('paged')?:1));if($page<=1)return '';
    $label=(string)($attrs['label']??'')?:'Vorherige Seite';$arrow=($block->context['paginationArrow']??'none');$ar=$arrow==='arrow'?'<span class="wp-block-query-pagination-previous-arrow is-arrow-arrow" aria-hidden="true">←</span>':($arrow==='chevron'?'<span class="wp-block-query-pagination-previous-arrow is-arrow-chevron" aria-hidden="true">«</span>':'');
    $a=get_block_wrapper_attributes();
    return '<a href="'.esc_url(rrw_wp_blk_page_link($page-1,$block->context)).'" '.$a.'>'.$ar.$label.'</a>';
}
function rrw_wp_blk_pagination_numbers($attrs, $content, $block) {
    $q=rrw_wp_blk_query_for($block->context);$total=(int)$q->max_num_pages;if($total<2)return '';
    $page=max(1,(int)($q->get('paged')?:1));$mid=(int)($attrs['midSize']??2);$o='';$last=0;
    for($i=1;$i<=$total;$i++){
        if($i!==1&&$i!==$total&&abs($i-$page)>$mid){ if($last!==-1){ $o.='<span class="page-numbers dots">…</span>';$last=-1; } continue; }
        $last=$i;
        $o.=$i===$page?'<span aria-current="page" class="page-numbers current">'.number_format_i18n($i).'</span>':'<a class="page-numbers" href="'.esc_url(rrw_wp_blk_page_link($i,$block->context)).'">'.number_format_i18n($i).'</a>';
    }
    return rrw_wp_blk_wrap($o);
}
function rrw_wp_blk_query_pagination($attrs, $content, $block) {
    if(trim($content)==='')return '';
    $a=get_block_wrapper_attributes(['aria-label'=>'Seitennummerierung']);
    return '<nav '.$a.'>'.$content.'</nav>';
}

/* ───────── Navigation ───────── */
function rrw_wp_blk_nav_item(string $label, string $url, array $attrs, string $classes='', string $sub=''): string {
    $cur=rtrim((string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH),'/')===rtrim((string)parse_url($url,PHP_URL_PATH),'/')&&$url!==''&&!str_starts_with($url,'#');
    $a=($cur?' aria-current="page"':'');
    $rel=!empty($attrs['rel'])?' rel="'.esc_attr($attrs['rel']).'"':'';$tg=!empty($attrs['opensInNewTab'])?' target="_blank"':'';
    $inner=$url!==''?'<a class="wp-block-navigation-item__content"'.$rel.$tg.$a.' href="'.esc_url($url).'"><span class="wp-block-navigation-item__label">'.wp_kses_post($label).'</span></a>':'<span class="wp-block-navigation-item__content"><span class="wp-block-navigation-item__label">'.wp_kses_post($label).'</span></span>';
    return '<li class="wp-block-navigation-item'.($cur?' current-menu-item':'').' '.$classes.($sub!==''?' has-child':'').'">'.$inner.($sub!==''?'<span class="wp-block-navigation__submenu-icon">'.rrw_wp_blk_svg('sub').'</span><ul class="wp-block-navigation__submenu-container">'.$sub.'</ul>':'').'</li>';
}
function rrw_wp_blk_navigation_link($attrs, $content, $block) {
    $label=(string)($attrs['label']??'');if($label==='')return '';
    $url=(string)($attrs['url']??'');
    if(($attrs['kind']??'')==='post-type'&&!empty($attrs['id'])){ $l=get_permalink((int)$attrs['id']);if($l)$url=$l; }
    return rrw_wp_blk_nav_item($label,$url,$attrs,'wp-block-navigation-link');
}
function rrw_wp_blk_navigation_submenu($attrs, $content, $block) {
    $label=(string)($attrs['label']??'');if($label==='')return '';
    return rrw_wp_blk_nav_item($label,(string)($attrs['url']??''),$attrs,'wp-block-navigation-submenu',$content);
}
/** Menü aus dem CMS (Hauptmenü/Fußmenü) als Navigationseinträge. */
function rrw_wp_blk_cms_menu(string $name): string {
    $items=function_exists('rrw_wp_cms_menu_items')?rrw_wp_cms_menu_items($name):[];if(!$items)return '';
    $by=[];foreach($items as $it)$by[(int)$it->menu_item_parent][]=$it;
    $walk=function(int $parent) use(&$walk,&$by){ $o='';foreach($by[$parent]??[] as $it){ $sub=isset($by[(int)$it->db_id])?$walk((int)$it->db_id):'';$o.=rrw_wp_blk_nav_item((string)$it->title,(string)$it->url,[],'wp-block-navigation-link',$sub); } return $o; };
    return $walk(0);
}
function rrw_wp_blk_home_link($attrs, $content, $block) { return rrw_wp_blk_nav_item((string)($attrs['label']??'')?:'Startseite',home_url('/'),$attrs,'wp-block-home-link'); }
function rrw_wp_blk_page_list($attrs, $content, $block) {
    $pages=get_pages(['sort_column'=>'menu_order,post_title'])?:[];$by=[];foreach($pages as $p)$by[(int)$p->post_parent][]=$p;
    $f=home_url('/');$walk=function(int $parent) use(&$walk,&$by){ $o='';foreach($by[$parent]??[] as $p){ $kids=isset($by[(int)$p->ID])?'<ul class="wp-block-navigation__submenu-container">'.$walk((int)$p->ID).'</ul>':'';
        $o.='<li class="wp-block-navigation-item wp-block-pages-list__item'.($kids?' has-child':'').'"><a class="wp-block-pages-list__item__link wp-block-navigation-item__content" href="'.esc_url(get_permalink($p)).'"><span class="wp-block-navigation-item__label">'.esc_html(get_the_title($p)).'</span></a>'.($kids?'<span class="wp-block-navigation__submenu-icon">'.rrw_wp_blk_svg('sub').'</span>'.$kids:'').'</li>'; } return $o; };
    $items=$walk(0);if($items==='')return '';
    return ($GLOBALS['rrw_wp_in_navigation']??false)?$items:'<ul '.get_block_wrapper_attributes().'>'.$items.'</ul>';
}
function rrw_wp_blk_navigation($attrs, $content, $block) {
    $GLOBALS['rrw_wp_in_navigation']=true;
    try{
        $items=$content;
        if(trim($items)===''){
            $items=rrw_wp_blk_cms_menu('top');
            if(trim($items)==='')$items=rrw_wp_blk_page_list([],'',$block);
        }
    } finally { $GLOBALS['rrw_wp_in_navigation']=false; }
    if(trim($items)==='')return '';
    static $n=0;$n++;$id='modal-'.$n;
    $overlay=(string)($attrs['overlayMenu']??'mobile');
    $label=(string)($attrs['ariaLabel']??'')?:'Navigation';
    $cls=['wp-block-navigation__container'];
    $ul='<ul class="wp-block-navigation__container '.($overlay!=='never'?'is-responsive ':'').'wp-block-navigation">'.$items.'</ul>';
    $just=(string)($attrs['layout']['justifyContent']??'');
    $extra=['class'=>($overlay!=='never'?'is-responsive ':'').'items-justified-'.($just?:'left').' '.($overlay==='always'?'':'')];
    if($overlay==='never')$body=$ul;
    else $body='<button aria-haspopup="dialog" aria-label="Menü öffnen" class="wp-block-navigation__responsive-container-open'.($overlay==='always'?' always-shown':'').'" data-rrw-nav="open" aria-controls="'.$id.'">'.rrw_wp_blk_svg('open').'</button>'
        .'<div class="wp-block-navigation__responsive-container'.($overlay==='always'?' hidden-by-default':'').'" id="'.$id.'"><div class="wp-block-navigation__responsive-close" tabindex="-1"><div class="wp-block-navigation__responsive-dialog" role="dialog" aria-modal="true" aria-label="Menü"><button aria-label="Menü schließen" class="wp-block-navigation__responsive-container-close" data-rrw-nav="close">'.rrw_wp_blk_svg('close').'</button><div class="wp-block-navigation__responsive-container-content" id="'.$id.'-content">'.$ul.'</div></div></div></div>';
    $GLOBALS['rrw_wp_nav_js']=true;
    $a=get_block_wrapper_attributes($extra+['aria-label'=>$label]);
    return '<nav '.$a.'>'.$body.'</nav>';
}
function rrw_wp_blk_nav_js(): void {
    if(empty($GLOBALS['rrw_wp_nav_js']))return;
    echo '<script>document.addEventListener("click",function(e){var b=e.target.closest&&e.target.closest("[data-rrw-nav]");if(!b)return;var nav=b.closest("nav"),c=nav&&nav.querySelector(".wp-block-navigation__responsive-container");if(!c)return;var open=b.getAttribute("data-rrw-nav")==="open";c.classList.toggle("is-menu-open",open);c.classList.toggle("has-modal-open",open);document.documentElement.classList.toggle("has-modal-open",open)});</script>';
}
add_action('wp_footer','rrw_wp_blk_nav_js',50);

/* ───────── Suche, Konto, Verzeichnisse ───────── */
function rrw_wp_blk_search($attrs, $content, $block) {
    static $n=0;$n++;$id='wp-block-search__input-'.$n;
    $label=(string)($attrs['label']??'')?:'Suchen';$btn=(string)($attrs['buttonText']??'')?:'Suchen';$ph=(string)($attrs['placeholder']??'');
    $pos=(string)($attrs['buttonPosition']??'button-outside');$noBtn=$pos==='no-button';$icon=!empty($attrs['buttonUseIcon']);
    $showLabel=$attrs['showLabel']??true;
    $w=!empty($attrs['width'])?'width:'.$attrs['width'].($attrs['widthUnit']??'%').';':'';
    $classes=['wp-block-search__'.($noBtn?'no-button':$pos),$icon?'wp-block-search__icon-button':'wp-block-search__text-button'];
    $lab='<label class="wp-block-search__label'.($showLabel?'':' screen-reader-text').'" for="'.$id.'">'.wp_kses_post($label).'</label>';
    $input='<input class="wp-block-search__input" id="'.$id.'" placeholder="'.esc_attr($ph).'" value="'.esc_attr(get_search_query()).'" type="search" name="s" required />';
    $bt=$noBtn?'':'<button aria-label="'.esc_attr($btn).'" class="wp-block-search__button wp-element-button'.($icon?' has-icon':'').'" type="submit">'.($icon?'<svg class="search-icon" viewBox="0 0 24 24" width="24" height="24"><path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z"></path></svg>':esc_html($btn)).'</button>';
    $inside='<div class="wp-block-search__inside-wrapper"'.($w?' style="'.esc_attr($w).'"':'').'>'.$input.$bt.'</div>';
    $a=get_block_wrapper_attributes(['class'=>implode(' ',$classes),'role'=>'search','method'=>'get','action'=>esc_url(home_url('/'))]);
    return '<form '.$a.'>'.$lab.$inside.'</form>';
}
function rrw_wp_blk_loginout($attrs, $content, $block) { $o=wp_loginout(!empty($attrs['redirectToCurrent'])?'':'',false);return rrw_wp_blk_wrap($o,'div',['class'=>'logged-'.(is_user_logged_in()?'in':'out')]); }
function rrw_wp_blk_archives($attrs, $content, $block) { $l=wp_get_archives(['type'=>$attrs['type']??'monthly','show_post_count'=>!empty($attrs['showPostCount']),'echo'=>0,'format'=>'html']);return $l?'<ul '.get_block_wrapper_attributes().'>'.$l.'</ul>':''; }
function rrw_wp_blk_categories($attrs, $content, $block) { $l=wp_list_categories(['echo'=>0,'title_li'=>'','show_count'=>!empty($attrs['showPostCounts']),'hierarchical'=>!empty($attrs['showHierarchy'])]);return $l?'<ul '.get_block_wrapper_attributes().'>'.$l.'</ul>':''; }
function rrw_wp_blk_tag_cloud($attrs, $content, $block) { $l=wp_tag_cloud(['echo'=>0,'taxonomy'=>$attrs['taxonomy']??'post_tag','show_count'=>!empty($attrs['showTagCounts'])]);return $l?rrw_wp_blk_wrap($l,'p'):''; }
function rrw_wp_blk_calendar($attrs, $content, $block) { return rrw_wp_blk_wrap((string)get_calendar(true,false)); }
function rrw_wp_blk_shortcode($attrs, $content, $block) { return rrw_wp_blk_wrap(do_shortcode(trim($content)),'div'); }
function rrw_wp_blk_latest_posts($attrs, $content, $block) {
    $q=new WP_Query(['posts_per_page'=>(int)($attrs['postsToShow']??5),'order'=>strtoupper((string)($attrs['order']??'DESC')),'orderby'=>(string)($attrs['orderBy']??'date'),'post_status'=>'publish','ignore_sticky_posts'=>true]);
    if(!$q->have_posts())return '';$o='';
    foreach($q->posts as $p){
        $o.='<li><a class="wp-block-latest-posts__post-title" href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a>'.(!empty($attrs['displayPostDate'])?'<time datetime="'.esc_attr(get_the_date('c',$p)).'" class="wp-block-latest-posts__post-date">'.esc_html(get_the_date('',$p)).'</time>':'').'</li>';
    }
    return '<ul '.get_block_wrapper_attributes().'>'.$o.'</ul>';
}

/* ───────── Kommentare ───────── */
function rrw_wp_blk_comments($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';
    if(!comments_open($p)&&!(int)get_comments_number($p))return '';
    return $content;
}
function rrw_wp_blk_comments_title($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p)return '';$n=(int)get_comments_number($p);$lvl=(int)($attrs['level']??2);$tag='h'.max(1,min(6,$lvl));
    $t=$n?sprintf(_n('%1$s Kommentar zu „%2$s“','%1$s Kommentare zu „%2$s“',$n),number_format_i18n($n),get_the_title($p)):'Antworten';
    return rrw_wp_blk_wrap(esc_html($t),$tag);
}
function rrw_wp_blk_comment_template($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p||!function_exists('get_comments'))return '';
    $cs=get_comments(['post_id'=>$p->ID,'status'=>'approve','order'=>'ASC']);if(!$cs)return '';
    $o='';
    foreach($cs as $c){
        $ctx=$block->available_context+['commentId'=>(int)$c->comment_ID];
        $inner='';foreach($block->parsed_block['innerBlocks'] as $ib)$inner.=(new WP_Block($ib,$ctx))->render();
        $o.='<li id="comment-'.(int)$c->comment_ID.'" class="comment byuser comment-author-'.esc_attr($c->comment_author).' even thread-even depth-1">'.$inner.'</li>';
    }
    return '<ol '.get_block_wrapper_attributes().'>'.$o.'</ol>';
}
function rrw_wp_blk_comment_author_name($attrs, $content, $block) { $c=get_comment((int)($block->context['commentId']??0));return $c?rrw_wp_blk_wrap(esc_html($c->comment_author)):''; }
function rrw_wp_blk_comment_date($attrs, $content, $block) { $c=get_comment((int)($block->context['commentId']??0));if(!$c)return '';$f=(string)($attrs['format']??'')?:get_option('date_format','j. F Y');return rrw_wp_blk_wrap('<time datetime="'.esc_attr(gmdate('c',strtotime($c->comment_date_gmt.' UTC'))).'">'.esc_html(wp_date($f,strtotime($c->comment_date_gmt.' UTC'))).'</time>'); }
function rrw_wp_blk_comment_content($attrs, $content, $block) { $c=get_comment((int)($block->context['commentId']??0));return $c?rrw_wp_blk_wrap(wpautop(esc_html($c->comment_content))):''; }
function rrw_wp_blk_post_comments_form($attrs, $content, $block) {
    $p=rrw_wp_blk_post($block);if(!$p||!comments_open($p))return '';
    ob_start();comment_form([],$p->ID);return rrw_wp_blk_wrap((string)ob_get_clean(),'div',['class'=>'wp-block-post-comments-form']);
}
function rrw_wp_blk_empty($attrs, $content, $block) { return ''; }

/** Registrierung aller Kern-Blöcke. */
function rrw_wp_register_core_blocks(): void {
    $reg=WP_Block_Type_Registry::get_instance();
    $post=['postId','postType'];
    $defs=[
        'template-part'=>['rrw_wp_blk_template_part'],'pattern'=>['rrw_wp_blk_pattern'],
        'site-title'=>['rrw_wp_blk_site_title'],'site-tagline'=>['rrw_wp_blk_site_tagline'],'site-logo'=>['rrw_wp_blk_site_logo'],
        'post-title'=>['rrw_wp_blk_post_title',$post],'post-content'=>['rrw_wp_blk_post_content',$post],'post-excerpt'=>['rrw_wp_blk_post_excerpt',$post],'post-date'=>['rrw_wp_blk_post_date',$post],
        'post-featured-image'=>['rrw_wp_blk_post_featured_image',$post],'post-terms'=>['rrw_wp_blk_post_terms',$post],'post-author'=>['rrw_wp_blk_post_author',$post],'post-author-name'=>['rrw_wp_blk_post_author_name',$post],
        'read-more'=>['rrw_wp_blk_read_more',$post],'post-navigation-link'=>['rrw_wp_blk_post_nav_link',$post],'avatar'=>['rrw_wp_blk_avatar',['postId','commentId']],'term-description'=>['rrw_wp_blk_term_description'],
        'query'=>['rrw_wp_blk_query',[],['queryId'=>'queryId','query'=>'query','displayLayout'=>'displayLayout']],
        'post-template'=>['rrw_wp_blk_post_template',['queryId','query','displayLayout']],'query-no-results'=>['rrw_wp_blk_query_no_results',['queryId','query']],'query-title'=>['rrw_wp_blk_query_title'],'query-total'=>['rrw_wp_blk_query_total',['queryId','query']],
        'query-pagination'=>['rrw_wp_blk_query_pagination',[],['paginationArrow'=>'paginationArrow']],'query-pagination-next'=>['rrw_wp_blk_pagination_next',['queryId','query','paginationArrow']],'query-pagination-previous'=>['rrw_wp_blk_pagination_previous',['queryId','query','paginationArrow']],'query-pagination-numbers'=>['rrw_wp_blk_pagination_numbers',['queryId','query']],
        'navigation'=>['rrw_wp_blk_navigation'],'navigation-link'=>['rrw_wp_blk_navigation_link'],'navigation-submenu'=>['rrw_wp_blk_navigation_submenu'],'home-link'=>['rrw_wp_blk_home_link'],'page-list'=>['rrw_wp_blk_page_list'],
        'search'=>['rrw_wp_blk_search'],'loginout'=>['rrw_wp_blk_loginout'],'archives'=>['rrw_wp_blk_archives'],'categories'=>['rrw_wp_blk_categories'],'tag-cloud'=>['rrw_wp_blk_tag_cloud'],'calendar'=>['rrw_wp_blk_calendar'],'shortcode'=>['rrw_wp_blk_shortcode'],'latest-posts'=>['rrw_wp_blk_latest_posts'],
        'comments'=>['rrw_wp_blk_comments',$post],'comments-title'=>['rrw_wp_blk_comments_title',$post],'comment-template'=>['rrw_wp_blk_comment_template',$post,[]],'comment-author-name'=>['rrw_wp_blk_comment_author_name',['commentId']],'comment-date'=>['rrw_wp_blk_comment_date',['commentId']],'comment-content'=>['rrw_wp_blk_comment_content',['commentId']],
        'post-comments-form'=>['rrw_wp_blk_post_comments_form',$post],'comments-pagination'=>['rrw_wp_blk_empty'],'comment-reply-link'=>['rrw_wp_blk_empty'],'comment-edit-link'=>['rrw_wp_blk_empty'],'post-comments-count'=>['rrw_wp_blk_empty'],'post-comments-link'=>['rrw_wp_blk_empty'],
    ];
    foreach($defs as $n=>$d){
        $reg->register('core/'.$n,['render_callback'=>$d[0],'uses_context'=>$d[1]??[],'provides_context'=>$d[2]??null,'api_version'=>3]);
    }
}
rrw_wp_register_core_blocks();
