<?php
// Website-Editor für Block-Themes: Vorlagen und Vorlagenteile mit dem WordPress-Blockeditor bearbeiten, Globale Stile (Farben) anpassen.
// Änderungen liegen als Überschreibungen in cms/data/.wp/site-editor/<theme>/ – die Dateien des Themes bleiben unverändert und
// lassen sich je Vorlage zurücksetzen. Der Editor selbst (rrw-site-editor.js) läuft in der abgeschotteten Plugin-Seite.

function rrw_wp_se_dir(string $sub=''): string { return RRW_WP_DATA.'/site-editor/'.preg_replace('/[^a-z0-9_-]/i','-',get_stylesheet()).($sub!==''?'/'.$sub:''); }
function rrw_wp_se_clean(string $s): string { return preg_replace('/[^a-z0-9_-]/i','',$s); }
function rrw_wp_se_sub(string $kind): string { return $kind==='part'?'parts':'templates'; }

/** Alle Vorlagen/Teile des Themes (Eltern + Kind) mit Hinweis, ob eine eigene Fassung existiert. */
function rrw_wp_se_list(): array {
    $out=['template'=>[],'part'=>[]];
    foreach(['template'=>'templates','part'=>'parts'] as $kind=>$sub){
        $seen=[];
        foreach(array_unique([get_template_directory(),get_stylesheet_directory()]) as $d)foreach(array_merge(glob($d.'/'.$sub.'/*.html')?:[],glob($d.'/block-'.$sub.'/*.html')?:[]) as $f)$seen[basename($f,'.html')]=true;
        foreach(glob(rrw_wp_se_dir($sub).'/*.html')?:[] as $f)$seen[basename($f,'.html')]=true;
        ksort($seen);
        foreach(array_keys($seen) as $slug)$out[$kind][]=['slug'=>$slug,'title'=>ucwords(str_replace(['-','_'],' ',$slug)),'custom'=>is_file(rrw_wp_se_dir($sub).'/'.$slug.'.html')];
    }
    return $out;
}
function rrw_wp_se_content(string $kind, string $slug): ?string {
    $slug=rrw_wp_se_clean($slug);if($slug==='')return null;
    $f=rrw_wp_block_template_file($slug,$kind==='part'?'wp_template_part':'wp_template');
    if($f==='')return null;
    $c=(string)file_get_contents($f);
    // Muster-Verweise werden für die Bearbeitung durch ihren Inhalt ersetzt (der Editor kennt den Block „Muster“ nicht)
    for($i=0;$i<3&&str_contains($c,'<!-- wp:pattern');$i++)
        $c=preg_replace_callback('/<!--\s+wp:pattern\s+(\{.*?\})\s+\/-->/s',function($m){ $a=json_decode($m[1],true);$p=is_array($a)?rrw_wp_pattern_content((string)($a['slug']??'')):null;return $p!==null?trim($p):$m[0]; },$c);
    return $c;
}
function rrw_wp_se_save(string $kind, string $slug, string $content): bool {
    $slug=rrw_wp_se_clean($slug);if($slug===''||strlen($content)>1048576)return false;
    $dir=rrw_wp_se_dir(rrw_wp_se_sub($kind));if(!is_dir($dir)&&!@mkdir($dir,0775,true))return false;
    $ok=file_put_contents($dir.'/'.$slug.'.html',$content)!==false;
    if($ok)rrw_wp_theme_json(true);
    return $ok;
}
function rrw_wp_se_reset(string $kind, string $slug): bool {
    $f=rrw_wp_se_dir(rrw_wp_se_sub($kind)).'/'.rrw_wp_se_clean($slug).'.html';
    return is_file($f)?@unlink($f):true;
}

/** Farbpalette des Themes ohne eigene Änderungen. */
function rrw_wp_se_base_palette(): array {
    $t=rrw_wp_json_file(get_stylesheet_directory().'/theme.json');$p=$t['settings']['color']['palette']??null;
    if($p===null&&is_child_theme()){ $t=rrw_wp_json_file(get_template_directory().'/theme.json');$p=$t['settings']['color']['palette']??null; }
    if(is_array($p)&&!array_is_list($p))$p=$p['theme']??[];
    return array_values(array_filter((array)$p,fn($c)=>is_array($c)&&isset($c['slug'],$c['color'])));
}
/** Farbpalette (Theme + eigene Änderungen) für das Formular „Globale Stile“. */
function rrw_wp_se_palette(): array {
    $base=rrw_wp_se_base_palette();$user=(array)get_option('rrw_wp_global_styles',[]);
    $over=[];foreach((array)($user['settings']['color']['palette']['theme']??($user['settings']['color']['palette']??[])) as $c)if(is_array($c)&&isset($c['slug']))$over[$c['slug']]=$c['color']??'';
    $out=[];foreach($base as $c)$out[]=['slug'=>$c['slug'],'name'=>$c['name']??$c['slug'],'color'=>$over[$c['slug']]??$c['color'],'default'=>$c['color']];
    return $out;
}
function rrw_wp_se_save_palette(array $colors): void {
    $user=(array)get_option('rrw_wp_global_styles',[]);$base=rrw_wp_se_base_palette();$list=[];
    foreach($base as $c){ $v=$colors[$c['slug']]??$c['color'];if(!preg_match('/^#[0-9a-f]{3,8}$/i',(string)$v))$v=$c['color'];$list[]=['slug'=>$c['slug'],'name'=>$c['name']??$c['slug'],'color'=>$v]; }
    $user['settings']['color']['palette']=$list;
    update_option('rrw_wp_global_styles',$user);rrw_wp_theme_json(true);
}

function rrw_wp_se_id_slug(string $id): string { $id=rawurldecode($id);return rrw_wp_se_clean(str_contains($id,'//')?explode('//',$id,2)[1]:$id); }
function rrw_wp_se_record(string $kind, string $slug): ?array {
    $c=rrw_wp_se_content($kind,$slug);if($c===null)return null;
    $area=$kind==='part'?(in_array($slug,['header','footer'],true)?$slug:(str_starts_with($slug,'header')?'header':(str_starts_with($slug,'footer')?'footer':'uncategorized'))):null;
    $custom=is_file(rrw_wp_se_dir(rrw_wp_se_sub($kind)).'/'.$slug.'.html');
    $r=['id'=>get_stylesheet().'//'.$slug,'theme'=>get_stylesheet(),'slug'=>$slug,'type'=>$kind==='part'?'wp_template_part':'wp_template','source'=>$custom?'custom':'theme','origin'=>$custom?'theme':null,'has_theme_file'=>true,'is_custom'=>false,'status'=>'publish','wp_id'=>null,'description'=>'','author'=>1,
        'title'=>['raw'=>ucwords(str_replace(['-','_'],' ',$slug)),'rendered'=>ucwords(str_replace(['-','_'],' ',$slug))],'content'=>['raw'=>$c,'block_version'=>1],'modified'=>gmdate('c'),'is_wp_suggestion'=>false];
    if($area!==null)$r['area']=$area;
    return $r;
}
/** Block im Editor rendern (wp.serverSideRender). */
function rrw_wp_se_render_block(string $name, array $attrs, array $ctx): string {
    $old=$GLOBALS['post']??null;
    if(!$old){ $q=new WP_Query(['posts_per_page'=>1,'post_status'=>'publish']);if($q->posts){ $GLOBALS['post']=$q->posts[0];setup_postdata($q->posts[0]); } }
    try{
        $c=['postId'=>(int)($ctx['postId']??($GLOBALS['post']->ID??0)),'postType'=>(string)($ctx['postType']??($GLOBALS['post']->post_type??'post'))]+$ctx;
        $b=new WP_Block(['blockName'=>$name,'attrs'=>$attrs,'innerBlocks'=>[],'innerHTML'=>'','innerContent'=>[]],$c);
        return $b->render();
    } finally { $GLOBALS['post']=$old; }
}

function rrw_wp_se_routes(): void {
    $adm=fn()=>current_user_can('edit_theme_options');
    register_rest_route('wp/v2','/block-renderer/(?P<name>[a-z0-9-]+/[a-z0-9-]+)',['methods'=>'GET,POST','callback'=>function(WP_REST_Request $r){
        $attrs=$r->get_param('attributes');$attrs=is_array($attrs)?$attrs:(is_string($attrs)?(json_decode($attrs,true)?:[]):[]);
        $ctx=$r->get_param('context');$ctx=is_array($ctx)?$ctx:[];
        return ['rendered'=>rrw_wp_se_render_block((string)$r->get_param('name'),$attrs,$ctx)];
    },'permission_callback'=>$adm]);
    register_rest_route('wp/v2','/types',['methods'=>'GET','callback'=>function(){ $o=[];foreach(['post'=>'posts','page'=>'pages','wp_template'=>'templates','wp_template_part'=>'template-parts'] as $t=>$base)$o[$t]=['slug'=>$t,'name'=>ucfirst(str_replace('_',' ',$t)),'rest_base'=>$base,'rest_namespace'=>'wp/v2','taxonomies'=>$t==='post'?['category','post_tag']:[],'supports'=>['title'=>true,'editor'=>true],'viewable'=>in_array($t,['post','page'],true),'hierarchical'=>$t==='page'];return $o; },'permission_callback'=>'__return_true']);
    // Vorlagen und Vorlagenteile als Datensätze, damit der Block „Vorlagenteil“ seinen Inhalt im Editor zeigt
    foreach(['templates'=>'template','template-parts'=>'part'] as $base=>$kind){
        register_rest_route('wp/v2','/'.$base,['methods'=>'GET','callback'=>function() use($kind){ $o=[];foreach(rrw_wp_se_list()[$kind] as $it){ $r=rrw_wp_se_record($kind,$it['slug']);if($r)$o[]=$r; }return $o; },'permission_callback'=>$adm]);
        register_rest_route('wp/v2','/'.$base.'/(?P<id>[A-Za-z0-9_-]+(?:%2F%2F|//)[A-Za-z0-9_-]+)',[
            ['methods'=>'GET','callback'=>function(WP_REST_Request $r) use($kind){ $x=rrw_wp_se_record($kind,rrw_wp_se_id_slug((string)$r['id']));return $x?:new WP_Error('rest_not_found','Nicht gefunden',['status'=>404]); },'permission_callback'=>$adm],
            ['methods'=>'POST,PUT,PATCH','callback'=>function(WP_REST_Request $r) use($kind){ $slug=rrw_wp_se_id_slug((string)$r['id']);$c=$r->get_param('content');$raw=is_array($c)?(string)($c['raw']??''):(string)$c;if($raw!==''||is_string($c))rrw_wp_se_save($kind,$slug,$raw);return rrw_wp_se_record($kind,$slug); },'permission_callback'=>$adm],
        ]);
    }
    register_rest_route('wp/v2','/taxonomies',['methods'=>'GET','callback'=>fn()=>['category'=>['slug'=>'category','name'=>'Kategorien','rest_base'=>'categories','rest_namespace'=>'wp/v2','types'=>['post']],'post_tag'=>['slug'=>'post_tag','name'=>'Schlagwörter','rest_base'=>'tags','rest_namespace'=>'wp/v2','types'=>['post']]],'permission_callback'=>'__return_true']);
    register_rest_route('wp/v2','/themes',['methods'=>'GET','callback'=>function(){ $t=wp_get_theme();return [['stylesheet'=>get_stylesheet(),'template'=>get_template(),'status'=>'active','name'=>['raw'=>$t->get('Name'),'rendered'=>$t->get('Name')],'version'=>$t->get('Version'),'theme_supports'=>['post-thumbnails'=>true,'responsive-embeds'=>true,'align-wide'=>true,'editor-color-palette'=>[],'editor-font-sizes'=>[],'wp-block-styles'=>true],'is_block_theme'=>true]]; },'permission_callback'=>'__return_true']);
    foreach(['media','comments','block-patterns/patterns','block-patterns/categories','block-directory/search','navigation','menus','users','widgets','sidebars'] as $b)
        register_rest_route('wp/v2','/'.$b,['methods'=>'GET','callback'=>fn()=>new WP_REST_Response([],200),'permission_callback'=>$adm]);
    register_rest_route('rrw/v1','/site-editor/(?P<kind>template|part)/(?P<slug>[a-z0-9_-]+)',[
        ['methods'=>'GET','callback'=>function(WP_REST_Request $r){ $c=rrw_wp_se_content((string)$r['kind'],(string)$r['slug']);return $c===null?new WP_Error('not_found','Nicht gefunden',['status'=>404]):['content'=>$c,'custom'=>is_file(rrw_wp_se_dir(rrw_wp_se_sub((string)$r['kind'])).'/'.rrw_wp_se_clean((string)$r['slug']).'.html')]; },'permission_callback'=>$adm],
        ['methods'=>'POST,PUT','callback'=>function(WP_REST_Request $r){ return rrw_wp_se_save((string)$r['kind'],(string)$r['slug'],(string)$r->get_param('content'))?['saved'=>true]:new WP_Error('save_failed','Speichern fehlgeschlagen',['status'=>500]); },'permission_callback'=>$adm],
        ['methods'=>'DELETE','callback'=>function(WP_REST_Request $r){ return ['reset'=>rrw_wp_se_reset((string)$r['kind'],(string)$r['slug'])]; },'permission_callback'=>$adm],
    ]);
    register_rest_route('rrw/v1','/site-editor/palette',[
        ['methods'=>'GET','callback'=>fn()=>rrw_wp_se_palette(),'permission_callback'=>$adm],
        ['methods'=>'POST,PUT','callback'=>function(WP_REST_Request $r){ rrw_wp_se_save_palette((array)$r->get_param('colors'));return rrw_wp_se_palette(); },'permission_callback'=>$adm],
        ['methods'=>'DELETE','callback'=>function(){ $u=(array)get_option('rrw_wp_global_styles',[]);unset($u['settings']['color']['palette']);update_option('rrw_wp_global_styles',$u);rrw_wp_theme_json(true);return rrw_wp_se_palette(); },'permission_callback'=>$adm],
    ]);
}
add_action('rest_api_init','rrw_wp_se_routes');

/** Seite „Website-Editor“ (Administration, nur bei Block-Themes). */
function rrw_wp_site_editor_page(): void {
    if(!wp_is_block_theme()){ echo '<div class="wrap"><h1>Website-Editor</h1><p>Das aktive Theme ist kein Block-Theme. Aktiviere unter „WordPress-Themes“ ein Block-Theme (z. B. Twenty Twenty-Five).</p></div>';return; }
    if(!rrw_wp_core_ready()){ echo '<div class="wrap"><h1>Website-Editor</h1><p>Der Editor braucht die WordPress-Kernressourcen. Lade sie im CMS unter Plugins → Einstellungen („Jetzt laden“) und öffne diese Seite erneut.</p></div>';return; }
    foreach(['wp-block-editor','wp-blocks','wp-block-library','wp-components','wp-data','wp-element','wp-api-fetch','wp-server-side-render','wp-format-library','wp-core-data','wp-keyboard-shortcuts','wp-i18n','wp-notices','wp-compose','wp-hooks','wp-url','wp-html-entities','lodash','moment'] as $h)wp_enqueue_script($h);
    foreach(['wp-components','wp-block-editor','wp-block-library','wp-block-library-theme','wp-format-library'] as $h)wp_enqueue_style($h);
    $css='';foreach([__DIR__.'/assets/block-library.css',__DIR__.'/assets/block-library-theme.css',rrw_wp_core_dir().'/css/dist/block-library/editor.min.css'] as $f)if(is_file($f))$css.=file_get_contents($f)."\n";
    $css.=wp_get_global_stylesheet()."\n";
    foreach(array_unique([get_template_directory(),get_stylesheet_directory()]) as $d){ foreach(['style.min.css','style.css'] as $sf)if(is_file($d.'/'.$sf)){ $css.=preg_replace('#/\*.*?\*/#s','',(string)file_get_contents($d.'/'.$sf))."\n";break; } }
    $css=preg_replace_callback('#url\(\s*([\'"]?)(?!data:|https?:|/)([^)\'"]+)\1\s*\)#',fn($m)=>'url("'.get_stylesheet_directory_uri().'/'.ltrim($m[2],'./').'")',$css);
    $data=['list'=>rrw_wp_se_list(),'palette'=>rrw_wp_se_palette(),'styles'=>[['css'=>$css]],'settings'=>wp_get_global_settings(),'theme'=>get_stylesheet(),'home'=>home_url('/'),'themeName'=>wp_get_theme()->get('Name')];
    wp_add_inline_script('wp-block-library','window.rrwSiteEditor='.wp_json_encode($data,JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP).';','before');
    $src=home_url('/cms/assets/rrw-site-editor.js');
    wp_enqueue_script('rrw-site-editor',$src,['wp-block-editor','wp-blocks','wp-block-library','wp-components','wp-data','wp-element','wp-api-fetch','wp-server-side-render','wp-format-library','wp-core-data','wp-i18n'],'1',true);
    wp_enqueue_style('rrw-site-editor',home_url('/cms/assets/rrw-site-editor.css'),['wp-components','wp-block-editor'],'1');
    echo '<div id="rrw-se-root"><div class="rrw-se-loading">Editor wird geladen …</div></div>';
}
