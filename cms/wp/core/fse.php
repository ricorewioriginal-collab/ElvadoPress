<?php
// Block-Themes (Full Site Editing): theme.json → Global Styles, Layout-/Block-Stile, Muster (Patterns), Template-Auflösung.
// Die Blöcke selbst rendert core-blocks.php.

function wp_is_block_theme() {
    static $cache=[];$k=get_stylesheet();
    if(!isset($cache[$k])){ $t=wp_get_theme();$cache[$k]=(bool)$t->is_block_theme()||($t->parent()&&$t->parent()->is_block_theme()&&is_dir(get_stylesheet_directory().'/templates')); }
    return $cache[$k];
}
function wp_theme_has_theme_json() { return is_file(get_stylesheet_directory().'/theme.json')||is_file(get_template_directory().'/theme.json'); }
function wp_get_theme_directory_pattern_slugs() { return array_keys(elvado_wp_theme_patterns()); }

/* ───────── theme.json ───────── */
function elvado_wp_json_file(string $f): array { if(!is_file($f))return [];$j=json_decode((string)file_get_contents($f),true);return is_array($j)?$j:[]; }
/** Rekursives Zusammenführen; Listen (Presets) werden ersetzt. */
function elvado_wp_tj_merge(array $a, array $b): array {
    foreach($b as $k=>$v){ if(is_array($v)&&isset($a[$k])&&is_array($a[$k])&&!array_is_list($v)&&!array_is_list($a[$k]))$a[$k]=elvado_wp_tj_merge($a[$k],$v);else $a[$k]=$v; }
    return $a;
}
/** Zusammengeführte theme.json: Kern → Eltern-Theme → Theme. Presets bleiben je Herkunft getrennt ('default' | 'theme'). */
function elvado_wp_theme_json(bool $reset=false): array {
    static $cache=[];if($reset)$cache=[];
    $k=get_stylesheet().'|'.(is_child_theme()?get_template():'');
    if(isset($cache[$k]))return $cache[$k];
    $core=elvado_wp_json_file(__DIR__.'/../assets/core-theme.json');
    $theme=[];
    if(is_child_theme())$theme=elvado_wp_json_file(get_template_directory().'/theme.json');
    $theme=elvado_wp_tj_merge($theme,elvado_wp_json_file(get_stylesheet_directory().'/theme.json'));
    $theme=apply_filters('wp_theme_json_data_theme',$theme);
    // Style-Variation (Standard) des Child-Themes bleibt unberücksichtigt; ausgewählte Variation aus der Option global_styles
    $user=get_option('elvado_wp_global_styles',[]);if(is_array($user)&&$user)$theme=elvado_wp_tj_merge($theme,$user);
    return $cache[$k]=['core'=>$core,'theme'=>$theme];
}
/** Effektive Einstellung (theme überschreibt core). */
function elvado_wp_tj_setting(string $path, $default=null) {
    $tj=elvado_wp_theme_json();$cur=$tj['theme']['settings']??[];
    foreach(explode('.',$path) as $p){ if(!is_array($cur)||!array_key_exists($p,$cur)){ $cur=null;break; } $cur=$cur[$p]; }
    if($cur!==null)return $cur;
    $cur=$tj['core']['settings']??[];
    foreach(explode('.',$path) as $p){ if(!is_array($cur)||!array_key_exists($p,$cur))return $default; $cur=$cur[$p]; }
    return $cur;
}
/** Preset-Liste mit Herkunft: ['default'=>[...],'theme'=>[...]]. */
function elvado_wp_tj_presets(string $type): array {
    static $map=['color.palette'=>'defaultPalette','color.gradients'=>'defaultGradients','color.duotone'=>'defaultDuotone','typography.fontSizes'=>'defaultFontSizes','spacing.spacingSizes'=>'defaultSpacingSizes','shadow.presets'=>'defaultPresets'];
    $tj=elvado_wp_theme_json();
    $get=function(array $src) use($type){ $cur=$src['settings']??[];foreach(explode('.',$type) as $p){ if(!is_array($cur)||!isset($cur[$p]))return [];$cur=$cur[$p]; } return is_array($cur)?$cur:[]; };
    $theme=$get($tj['theme']);
    if($theme&&!array_is_list($theme))$theme=$theme['theme']??[];
    $flag=$map[$type]??null;$useDefault=true;
    if($flag){ $parts=explode('.',$type);$group=$parts[0];$v=elvado_wp_tj_setting($group.'.'.$flag,true);
        if($type==='shadow.presets')$v=elvado_wp_tj_setting('shadow.defaultPresets',true);
        $useDefault=$v!==false; }
    return ['default'=>$useDefault?array_values($get($tj['core'])):[],'theme'=>array_values($theme)];
}
function elvado_wp_kebab(string $s): string { return strtolower(preg_replace(['/([a-z])([A-Z0-9])/','/([0-9])([A-Za-z])/','/[\s_]+/'],['$1-$2','$1-$2','-'],$s)); }

/** Werte wie var:preset|color|primary auflösen. */
function elvado_wp_tj_value($v) {
    if(is_array($v)){ if(isset($v['ref']))return null;return null; }
    $v=(string)$v;
    if(preg_match('/^var:preset\|([a-z-]+)\|(.+)$/i',$v,$m))return 'var(--wp--preset--'.$m[1].'--'.elvado_wp_kebab($m[2]).')';
    if(preg_match('/^var:custom\|(.+)$/i',$v,$m))return 'var(--wp--custom--'.implode('--',array_map('elvado_wp_kebab',explode('|',$m[1]))).')';
    return preg_replace_callback('/var:(preset|custom)\|([a-z0-9|_-]+)/i',fn($m)=>$m[1]==='preset'?(function($p){ $p=explode('|',$p);return 'var(--wp--preset--'.$p[0].'--'.elvado_wp_kebab($p[1]??'').')'; })($m[2]):'var(--wp--custom--'.implode('--',array_map('elvado_wp_kebab',explode('|',$m[2]))).')',$v);
}
/** Fluide Schriftgröße (clamp) nach den WordPress-Regeln. */
function elvado_wp_fluid_size(array $fs): string {
    $size=(string)($fs['size']??'');$fluid=$fs['fluid']??null;
    $typoFluid=elvado_wp_tj_setting('typography.fluid',false);
    if($fluid===false||(!$typoFluid&&!is_array($fluid)))return $size;
    if(!preg_match('/^([\d.]+)(px|rem|em)$/',$size,$m))return $size;
    $toRem=fn($n,$u)=>$u==='px'?$n/16:$n;
    $max=$toRem((float)$m[1],$m[2]);
    if(is_array($fluid)&&isset($fluid['min'],$fluid['max'])){
        if(!preg_match('/^([\d.]+)(px|rem|em)$/',(string)$fluid['min'],$a)||!preg_match('/^([\d.]+)(px|rem|em)$/',(string)$fluid['max'],$b))return $size;
        $min=$toRem((float)$a[1],$a[2]);$max=$toRem((float)$b[1],$b[2]);
    } else {
        if($max*16<14)return $size;   // unterhalb der Mindestgröße nicht fluid
        $min=max($max*0.75,14/16);if($min>=$max)return $size;
    }
    $minVp=20.0;$maxVp=80.0;   // 320px … 1280px
    $lin=round(100*($max-$min)/($maxVp-$minVp),3);
    $f=fn($x)=>rtrim(rtrim(number_format($x,3,'.',''),'0'),'.');
    return 'clamp('.$f($min).'rem, '.$f($min).'rem + ((1vw - '.$f($minVp/100).'rem) * '.$f($lin).'), '.$f($max).'rem)';
}

/** Style-Objekt (color, typography, spacing …) → CSS-Deklarationen. @return array<string,string> */
function elvado_wp_tj_declarations(array $s, bool $root=false): array {
    $d=[];$val=function($v){ return is_scalar($v)?elvado_wp_tj_value($v):null; };
    $set=function(string $prop,$v) use(&$d,$val){ $x=$val($v);if($x!==null&&$x!=='')$d[$prop]=$x; };
    foreach(['text'=>'color','background'=>'background-color'] as $k=>$p)if(isset($s['color'][$k]))$set($p,$s['color'][$k]);
    if(isset($s['color']['gradient']))$set('background',$s['color']['gradient']);
    if(isset($s['background'])){ $bg=$s['background'];
        if(isset($bg['backgroundImage'])){ $bi=$bg['backgroundImage'];$u=is_array($bi)?($bi['url']??''):$bi;if($u!==''){ if(is_string($u)&&str_starts_with($u,'file:./'))$u=get_theme_file_uri(substr($u,7));$d['background-image']=preg_match('/^(url|linear|radial|conic)/',$u)?$u:'url('.esc_url($u).')'; } }
        foreach(['backgroundSize'=>'background-size','backgroundPosition'=>'background-position','backgroundRepeat'=>'background-repeat','backgroundAttachment'=>'background-attachment'] as $k=>$p)if(isset($bg[$k]))$set($p,$bg[$k]); }
    foreach(['fontFamily'=>'font-family','fontSize'=>'font-size','fontStyle'=>'font-style','fontWeight'=>'font-weight','letterSpacing'=>'letter-spacing','lineHeight'=>'line-height','textDecoration'=>'text-decoration','textTransform'=>'text-transform','writingMode'=>'writing-mode','textIndent'=>'text-indent'] as $k=>$p){
        if(!isset($s['typography'][$k]))continue;$v=$s['typography'][$k];
        if($k==='fontSize'&&is_scalar($v)&&preg_match('/^var:preset\|font-size\|(.+)$/',(string)$v,$m)){ $d[$p]='var(--wp--preset--font-size--'.elvado_wp_kebab($m[1]).')';continue; }
        $set($p,$v);
    }
    foreach(['padding','margin'] as $prop){
        $v=$s['spacing'][$prop]??null;if($v===null)continue;
        if(is_array($v)){ foreach(['top','right','bottom','left'] as $side)if(isset($v[$side]))$set($prop.'-'.$side,$v[$side]); } else $set($prop,$v);
    }
    if(isset($s['dimensions'])){ foreach(['minHeight'=>'min-height','minWidth'=>'min-width','width'=>'width','height'=>'height','aspectRatio'=>'aspect-ratio','maxWidth'=>'max-width','maxHeight'=>'max-height'] as $k=>$p)if(isset($s['dimensions'][$k]))$set($p,$s['dimensions'][$k]); }
    if(isset($s['border'])){ $b=$s['border'];
        $sides=['top','right','bottom','left'];
        foreach(['color'=>'border-color','style'=>'border-style','width'=>'border-width'] as $k=>$p)if(isset($b[$k]))$set($p,$b[$k]);
        if(isset($b['radius'])){ if(is_array($b['radius'])){ foreach(['topLeft'=>'top-left','topRight'=>'top-right','bottomLeft'=>'bottom-left','bottomRight'=>'bottom-right'] as $k=>$c)if(isset($b['radius'][$k]))$set('border-'.$c.'-radius',$b['radius'][$k]); } else $set('border-radius',$b['radius']); }
        foreach($sides as $sd)if(isset($b[$sd])&&is_array($b[$sd]))foreach(['color','style','width'] as $k)if(isset($b[$sd][$k]))$set('border-'.$sd.'-'.$k,$b[$sd][$k]);
    }
    if(isset($s['shadow']))$set('box-shadow',$s['shadow']);
    if(isset($s['outline'])){ foreach(['color'=>'outline-color','offset'=>'outline-offset','style'=>'outline-style','width'=>'outline-width'] as $k=>$p)if(isset($s['outline'][$k]))$set($p,$s['outline'][$k]); }
    if(isset($s['position']['sticky'])&&$s['position']['sticky'])$d['position']='sticky';
    return $d;
}
function elvado_wp_decl_css(array $d, string $sep=''): string { $o='';foreach($d as $p=>$v)$o.=$p.':'.$v.';'.$sep;return $o; }

/** Selektoren der Elemente. */
function elvado_wp_element_selector(string $el): string {
    return match($el){
        'link'=>'a:where(:not(.wp-element-button))','heading'=>'h1,h2,h3,h4,h5,h6','button'=>'.wp-element-button, .wp-block-button__link',
        'caption'=>':where(.wp-element-caption, .wp-block-audio figcaption, .wp-block-embed figcaption, .wp-block-gallery figcaption, .wp-block-image figcaption, .wp-block-table figcaption, .wp-block-video figcaption)',
        'cite'=>'cite','h1','h2','h3','h4','h5','h6'=>$el,default=>$el };
}
function elvado_wp_block_selector(string $name): string {
    static $map=['core/paragraph'=>'p','core/heading'=>'h1,h2,h3,h4,h5,h6','core/button'=>'.wp-block-button .wp-block-button__link','core/image'=>'.wp-block-image img, .wp-block-image .wp-block-image__crop-area, .wp-block-image .components-placeholder','core/list'=>'ol, ul','core/list-item'=>'li','core/quote'=>'.wp-block-quote','core/navigation'=>'.wp-block-navigation','core/post-featured-image'=>'.wp-block-post-featured-image img, .wp-block-post-featured-image .block-editor-media-placeholder, .wp-block-post-featured-image .wp-block-post-featured-image__overlay.has-background-dim','core/site-logo'=>'.wp-block-site-logo','core/separator'=>'.wp-block-separator','core/table'=>'.wp-block-table'];
    if(isset($map[$name]))return $map[$name];
    return '.wp-block-'.str_replace('/','-',str_starts_with($name,'core/')?substr($name,5):$name);
}
/** Regeln für einen Stilbaum (root/Block): Basis + Elemente + css-Zusatz. */
function elvado_wp_style_rules(array $node, string $sel, bool $root=false): string {
    $o='';$decl=elvado_wp_tj_declarations($node,$root);
    if($root)unset($decl['padding-top'],$decl['padding-right'],$decl['padding-bottom'],$decl['padding-left'],$decl['padding']);
    if($decl)$o.=':root :where('.$sel.'){'.elvado_wp_decl_css($decl).'}';
    foreach((array)($node['elements']??[]) as $el=>$es){
        if(!is_array($es))continue;$esel=elvado_wp_element_selector((string)$el);
        $parts=array_map('trim',explode(',',$esel));
        $full=implode(',',array_map(fn($p)=>($sel===''||$root?'':$sel.' ').$p,$parts));
        $ed=elvado_wp_tj_declarations($es);
        if($ed)$o.=':root :where('.$full.'){'.elvado_wp_decl_css($ed).'}';
        foreach($es as $k=>$sv){ if(is_string($k)&&$k!==''&&$k[0]===':'&&is_array($sv)){ $pd=elvado_wp_tj_declarations($sv);if($pd)$o.=':root :where('.implode(',',array_map(fn($p)=>(($sel===''||$root)?'':$sel.' ').$p.$k,$parts)).'){'.elvado_wp_decl_css($pd).'}'; } }
        if(!empty($es['css']))$o.=':root :where('.$full.'){'.$es['css'].'}';
    }
    foreach($node as $k=>$sv){ if(is_string($k)&&$k!==''&&$k[0]===':'&&is_array($sv)){ $pd=elvado_wp_tj_declarations($sv);if($pd)$o.=':root :where('.$sel.')'.$k.'{'.elvado_wp_decl_css($pd).'}'; } }
    if(!empty($node['css'])){ $css=(string)$node['css'];$o.=str_contains($css,'&')?str_replace('&',':root :where('.$sel.')',$css):':root :where('.$sel.'){'.$css.'}'; }
    return $o;
}

function wp_get_global_settings($path=[],$context=[]) {
    $tj=elvado_wp_theme_json();$s=elvado_wp_tj_merge((array)($tj['core']['settings']??[]),(array)($tj['theme']['settings']??[]));
    foreach((array)$path as $p){ if(!is_array($s)||!isset($s[$p]))return null; $s=$s[$p]; }
    return $s;
}
function wp_get_global_styles($path=[],$context=[]) {
    $tj=elvado_wp_theme_json();$s=elvado_wp_tj_merge((array)($tj['core']['styles']??[]),(array)($tj['theme']['styles']??[]));
    foreach((array)$path as $p){ if(!is_array($s)||!isset($s[$p]))return null; $s=$s[$p]; }
    return $s;
}
function wp_get_global_styles_custom_css() { return (string)(wp_get_global_styles(['css'])??''); }

/** Das komplette Global-Styles-Stylesheet. $types: variables, presets, styles, base-layout-styles */
function wp_get_global_stylesheet($types=[]) {
    static $cache=[];$key=get_stylesheet().'|'.json_encode($types).'|'.md5(json_encode(elvado_wp_theme_json()));
    if(isset($cache[$key]))return $cache[$key];
    $tj=elvado_wp_theme_json();$all=!$types;$want=fn($t)=>$all||in_array($t,(array)$types,true);
    $vars=[];$classes='';
    // Presets
    $mk=[['color.palette','color','color'],['color.gradients','gradient','background'],['color.duotone','duotone',''],['typography.fontFamilies','font-family',''],['typography.fontSizes','font-size','font-size'],['spacing.spacingSizes','spacing',''],['shadow.presets','shadow','']];
    foreach($mk as [$path,$type,$cls]){
        $pr=elvado_wp_tj_presets($path);
        if($path==='typography.fontFamilies'){
            $t=elvado_wp_tj_setting('typography.fontFamilies',[]);$list=is_array($t)&&!array_is_list($t)?($t['theme']??[]):(array)$t;$pr=['default'=>[],'theme'=>$list];
            if(!$list){ $c=(array)($tj['core']['settings']['typography']['fontFamilies']??[]);$pr['default']=$c; }
        }
        $seen=[];
        foreach(['default','theme'] as $org)foreach($pr[$org] as $p){
            if(!is_array($p)||!isset($p['slug']))continue;$slug=elvado_wp_kebab((string)$p['slug']);
            $v=match($type){'color'=>$p['color']??null,'gradient'=>$p['gradient']??null,'font-family'=>$p['fontFamily']??null,'font-size'=>elvado_wp_fluid_size($p),'spacing'=>$p['size']??null,'shadow'=>$p['shadow']??null,'duotone'=>null,default=>null};
            if($v===null||$v==='')continue;
            $vars['--wp--preset--'.$type.'--'.$slug]=$v;
            if($type==='color'){ $classes.='.has-'.$slug.'-color{color:var(--wp--preset--color--'.$slug.') !important;}.has-'.$slug.'-background-color{background-color:var(--wp--preset--color--'.$slug.') !important;}.has-'.$slug.'-border-color{border-color:var(--wp--preset--color--'.$slug.') !important;}'; }
            elseif($type==='gradient')$classes.='.has-'.$slug.'-gradient-background{background:var(--wp--preset--gradient--'.$slug.') !important;}';
            elseif($type==='font-size')$classes.='.has-'.$slug.'-font-size{font-size:var(--wp--preset--font-size--'.$slug.') !important;}';
            elseif($type==='font-family')$classes.='.has-'.$slug.'-font-family{font-family:var(--wp--preset--font-family--'.$slug.') !important;}';
        }
    }
    // Eigene Werte (settings.custom)
    $walk=function($a,$prefix) use(&$walk,&$vars){ foreach($a as $k=>$v){ $n=$prefix.'--'.elvado_wp_kebab((string)$k);if(is_array($v))$walk($v,$n);elseif(is_scalar($v))$vars[$n]=elvado_wp_tj_value($v); } };
    $walk((array)elvado_wp_tj_setting('custom',[]),'--wp--custom');
    $content=elvado_wp_tj_setting('layout.contentSize');$wide=elvado_wp_tj_setting('layout.wideSize');
    $gap=wp_get_global_styles(['spacing','blockGap']);
    $out='';
    if($want('variables')||$want('presets')){
        $out.=':root{';foreach($vars as $k=>$v)$out.=$k.':'.$v.';';$out.='}';
    }
    $styles=elvado_wp_tj_merge((array)($tj['core']['styles']??[]),(array)($tj['theme']['styles']??[]));
    $rootAware=(bool)elvado_wp_tj_setting('useRootPaddingAwareAlignments',false);
    $rootVars='';
    if($want('styles')){
        $pad=$styles['spacing']['padding']??null;
        if($rootAware&&is_array($pad)){ foreach(['top','right','bottom','left'] as $sd)if(isset($pad[$sd]))$rootVars.='--wp--style--root--padding-'.$sd.':'.elvado_wp_tj_value($pad[$sd]).';'; }
        elseif(!$rootAware&&$pad){ /* Polster bleibt auf body */ }
        $out.=':root{'.($content?'--wp--style--global--content-size:'.$content.';':'').($wide?'--wp--style--global--wide-size:'.$wide.';':'').($gap?'--wp--style--block-gap:'.elvado_wp_tj_value($gap).';':'').$rootVars.'}';
        $out.='body{margin:0;}';
        // body-Stile
        $bodyDecl=elvado_wp_tj_declarations($styles);
        if($rootAware){ foreach(['padding-top','padding-right','padding-bottom','padding-left','padding'] as $x)unset($bodyDecl[$x]); }
        if($rootAware)$out.='body{padding-top:var(--wp--style--root--padding-top);padding-right:0;padding-bottom:var(--wp--style--root--padding-bottom);padding-left:0;}';
        elseif(is_array($pad))foreach(['top','right','bottom','left'] as $sd)if(isset($pad[$sd]))$bodyDecl['padding-'.$sd]=elvado_wp_tj_value($pad[$sd]);
        if($bodyDecl)$out.=':root :where(body){'.elvado_wp_decl_css($bodyDecl).'}';
        $copy=$styles;unset($copy['color'],$copy['typography'],$copy['spacing'],$copy['dimensions'],$copy['border'],$copy['shadow'],$copy['outline'],$copy['background'],$copy['position']);
        $out.=elvado_wp_style_rules(['elements'=>$styles['elements']??[]],'',true);
        foreach((array)($styles['blocks']??[]) as $bn=>$bs){ if(!is_array($bs))continue;$out.=elvado_wp_style_rules($bs,elvado_wp_block_selector((string)$bn)); }
        if(!empty($styles['css']))$out.=$styles['css'];
    }
    if($want('base-layout-styles')||$want('styles')){
        $g='var(--wp--style--block-gap, 1.5em)';
        $out.='.wp-site-blocks > .alignleft{float:left;margin-right:2em;}.wp-site-blocks > .alignright{float:right;margin-left:2em;}.wp-site-blocks > .aligncenter{justify-content:center;margin-left:auto;margin-right:auto;}';
        $out.='.is-layout-flow > .alignleft{float:left;margin-inline-start:0;margin-inline-end:2em;}.is-layout-flow > .alignright{float:right;margin-inline-start:2em;margin-inline-end:0;}.is-layout-flow > .aligncenter{margin-left:auto !important;margin-right:auto !important;}';
        $out.='.is-layout-constrained > .alignleft{float:left;margin-inline-start:0;margin-inline-end:2em;}.is-layout-constrained > .alignright{float:right;margin-inline-start:2em;margin-inline-end:0;}.is-layout-constrained > .aligncenter{margin-left:auto !important;margin-right:auto !important;}';
        $cs=$content?:'var(--wp--style--global--content-size, 100%)';$ws=$wide?:$cs;
        $out.='.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width:var(--wp--style--global--content-size, '.($content?:'none').');margin-left:auto !important;margin-right:auto !important;}.is-layout-constrained > .alignwide{max-width:var(--wp--style--global--wide-size, '.($ws?:'none').');}';
        $out.='.is-layout-flex{display:flex;}.is-layout-flex{flex-wrap:wrap;align-items:center;}.is-layout-flex > :is(*, div){margin:0;}.is-layout-grid{display:grid;}.is-layout-grid > :is(*, div){margin:0;}';
        $out.=':root :where(.is-layout-flow) > :first-child{margin-block-start:0;}:root :where(.is-layout-flow) > :last-child{margin-block-end:0;}:root :where(.is-layout-flow) > *{margin-block-start:'.$g.';margin-block-end:0;}';
        $out.=':root :where(.is-layout-constrained) > :first-child{margin-block-start:0;}:root :where(.is-layout-constrained) > :last-child{margin-block-end:0;}:root :where(.is-layout-constrained) > *{margin-block-start:'.$g.';margin-block-end:0;}';
        $out.=':root :where(.is-layout-flex){gap:'.$g.';}:root :where(.is-layout-grid){gap:'.$g.';}';
        if($rootAware){
            $out.='.has-global-padding{padding-right:var(--wp--style--root--padding-right);padding-left:var(--wp--style--root--padding-left);}.has-global-padding > .alignfull{margin-right:calc(var(--wp--style--root--padding-right) * -1);margin-left:calc(var(--wp--style--root--padding-left) * -1);}.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)){padding-right:0;padding-left:0;}.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) > .alignfull{margin-left:0;margin-right:0;}';
        }
    }
    if($want('presets'))$out.=$classes;
    $out.=elvado_wp_font_faces();
    return $cache[$key]=apply_filters('elvado_wp_global_stylesheet',$out);
}
function elvado_wp_font_faces(): string {
    $t=elvado_wp_tj_setting('typography.fontFamilies',[]);$list=is_array($t)&&!array_is_list($t)?($t['theme']??[]):(array)$t;$o='';
    foreach($list as $fam)foreach((array)($fam['fontFace']??[]) as $ff){
        $src=[];foreach((array)($ff['src']??[]) as $s){ $s=(string)$s;if(str_starts_with($s,'file:./'))$s=get_theme_file_uri(substr($s,7));$fmt=preg_match('/\.(woff2|woff|ttf|otf)(\?|$)/i',$s,$m)?strtolower($m[1]):'';$src[]='url("'.esc_url_raw($s).'")'.($fmt?' format("'.($fmt==='ttf'?'truetype':($fmt==='otf'?'opentype':$fmt)).'")':''); }
        if(!$src)continue;
        $o.='@font-face{font-family:'.(str_contains((string)($ff['fontFamily']??$fam['name']??''),' ')&&!str_contains((string)($ff['fontFamily']??''),'"')?'"'.($ff['fontFamily']??$fam['name']).'"':($ff['fontFamily']??$fam['name']??'')).';font-style:'.($ff['fontStyle']??'normal').';font-weight:'.($ff['fontWeight']??'400').';font-display:'.($ff['fontDisplay']??'fallback').';src:'.implode(', ',$src).';'.(isset($ff['fontStretch'])?'font-stretch:'.$ff['fontStretch'].';':'').'}';
    }
    return $o;
}

/* ───────── Ausgabe im Head / Footer ───────── */
function elvado_wp_fse_head(): void {
    if(!wp_is_block_theme())return;
    echo '<link rel="stylesheet" id="wp-block-library-css" href="'.esc_url(home_url('/wp-includes/css/dist/block-library/style.min.css')).'?ver='.ELVADO_WP_VERSION.'" media="all">';
    echo '<link rel="stylesheet" id="wp-block-library-theme-css" href="'.esc_url(home_url('/wp-includes/css/dist/block-library/theme.min.css')).'?ver='.ELVADO_WP_VERSION.'" media="all">';
    echo '<style id="global-styles-inline-css">'.str_replace('</style','<\/style',wp_get_global_stylesheet()).'</style>';
    foreach((array)($GLOBALS['elvado_wp_block_style_inline']??[]) as $css)echo '<style>'.str_replace('</style','<\/style',$css).'</style>';
    $custom=wp_get_global_styles_custom_css();if($custom!=='')echo '<style id="wp-custom-css">'.str_replace('</style','<\/style',$custom).'</style>';
}
function elvado_wp_fse_footer(): void {
    $css=(array)($GLOBALS['elvado_wp_block_support_css']??[]);
    if($css)echo '<style id="core-block-supports-inline-css">'.str_replace('</style','<\/style',implode('',array_values($css))).'</style>';
}
add_action('wp_head','elvado_wp_fse_head',8);
add_action('wp_footer','elvado_wp_fse_footer',1);

function register_block_style($block_name, $style_properties) {
    foreach((array)$block_name as $b){ if(!empty($style_properties['name'])){ $GLOBALS['elvado_wp_block_styles'][$b][$style_properties['name']]=$style_properties;
        if(!empty($style_properties['inline_style']))$GLOBALS['elvado_wp_block_style_inline'][$b.'/'.$style_properties['name']]=(string)$style_properties['inline_style'];
        if(!empty($style_properties['style_data']))$GLOBALS['elvado_wp_block_style_inline'][$b.'/'.$style_properties['name'].'-d']=(string)wp_json_encode($style_properties['style_data']); } }
    return true;
}
function unregister_block_style($b,$n) { unset($GLOBALS['elvado_wp_block_styles'][$b][$n],$GLOBALS['elvado_wp_block_style_inline'][$b.'/'.$n]);return true; }
function wp_enqueue_block_style($block_name, $args) {
    $a=wp_parse_args($args,['handle'=>'','src'=>'','deps'=>[],'ver'=>false,'media'=>'all']);
    if($a['handle']==='')return;
    add_action('wp_enqueue_scripts',function() use($a){ if($a['src']!==''){ wp_register_style($a['handle'],$a['src'],$a['deps'],$a['ver'],$a['media']);wp_enqueue_style($a['handle']);if(!empty($a['path']))wp_style_add_data($a['handle'],'path',$a['path']); } },20);
}
function wp_should_load_separate_core_block_assets() { return false; }

/* ───────── Muster (Patterns) ───────── */
function register_block_pattern($name, $props) { $GLOBALS['elvado_wp_patterns'][(string)$name]=(array)$props+['name'=>$name];return true; }
function unregister_block_pattern($name) { unset($GLOBALS['elvado_wp_patterns'][$name]);return true; }
function register_block_pattern_category($name, $props) { $GLOBALS['elvado_wp_pattern_categories'][$name]=(array)$props;return true; }
function unregister_block_pattern_category($name) { unset($GLOBALS['elvado_wp_pattern_categories'][$name]);return true; }
class WP_Block_Patterns_Registry {
    private static $i; public static function get_instance() { return self::$i??=new self(); }
    public function register($n,$p) { return register_block_pattern($n,$p); } public function unregister($n) { return unregister_block_pattern($n); }
    public function get_registered($n) { return $GLOBALS['elvado_wp_patterns'][$n]??null; } public function get_all_registered() { return array_values($GLOBALS['elvado_wp_patterns']??[]); } public function is_registered($n) { return isset($GLOBALS['elvado_wp_patterns'][$n]); }
}
/** patterns/*.php des Themes (und Eltern-Themes) einlesen. @return array<string,array> */
function elvado_wp_theme_patterns(): array {
    static $cache=[];$k=get_stylesheet();
    if(isset($cache[$k]))return $cache[$k];
    $out=[];
    foreach(array_unique([get_template_directory(),get_stylesheet_directory()]) as $dir){
        foreach(glob($dir.'/patterns/*.php')?:[] as $f){
            $h=get_file_data($f,['title'=>'Title','slug'=>'Slug','description'=>'Description','categories'=>'Categories','inserter'=>'Inserter','blockTypes'=>'Block Types','postTypes'=>'Post Types','templateTypes'=>'Template Types','viewportWidth'=>'Viewport Width','keywords'=>'Keywords']);
            if($h['slug']===''||$h['title']==='')continue;
            $out[$h['slug']]=['file'=>$f,'title'=>$h['title'],'slug'=>$h['slug'],'inserter'=>strtolower(trim($h['inserter']))!=='no'];
        }
    }
    return $cache[$k]=$out;
}
/** Muster-Inhalt (Blockmarkup) holen. */
function elvado_wp_pattern_content(string $slug): ?string {
    $p=elvado_wp_theme_patterns()[$slug]??null;
    if($p){ ob_start();try{ include $p['file']; }catch(Throwable $e){ ob_end_clean();elvado_wp_log('Muster '.$slug.': '.$e->getMessage());return ''; } return (string)ob_get_clean(); }
    $r=$GLOBALS['elvado_wp_patterns'][$slug]??null;
    return $r?(string)($r['content']??''):null;
}

/* ───────── Template-Auflösung ───────── */
function elvado_wp_block_template_file(string $name, string $type='wp_template'): string {
    $dirs=array_unique([get_stylesheet_directory(),get_template_directory()]);
    $sub=$type==='wp_template_part'?'parts':'templates';
    if(preg_match('/^[a-z0-9_-]+$/i',$name)&&is_file($o=ELVADO_WP_DATA.'/site-editor/'.preg_replace('/[^a-z0-9_-]/i','-',get_stylesheet()).'/'.$sub.'/'.$name.'.html'))return $o;   // eigene Fassung aus dem Website-Editor
    foreach($dirs as $d)foreach([$sub,'block-'.$sub] as $s)if(is_file($f=$d.'/'.$s.'/'.$name.'.html'))return $f;
    return '';
}
/** Kandidatenliste (ohne .php) → erste vorhandene HTML-Vorlage des Themes. @return array{slug:string,file:string} */
function elvado_wp_resolve_block_template(WP_Query $q): array {
    $c=array_map(fn($x)=>preg_replace('/\.php$/','',(string)$x),elvado_wp_template_candidates($q));
    $c=apply_filters('elvado_wp_block_template_candidates',$c);
    // Eigene Vorlagen aus dem Kern (Fallback-Reihenfolge von WordPress)
    if(is_front_page())array_unshift($c,'front-page');
    foreach($c as $n){ $f=elvado_wp_block_template_file($n);if($f!=='')return ['slug'=>$n,'file'=>$f]; }
    return ['slug'=>'index','file'=>elvado_wp_block_template_file('index')];
}
function get_block_file_template($id=null,$type='wp_template') { $slug=is_string($id)&&str_contains($id,'//')?explode('//',$id,2)[1]:(string)$id;$f=elvado_wp_block_template_file($slug,$type);if($f==='')return null;$o=new stdClass;$o->slug=$slug;$o->id=get_stylesheet().'//'.$slug;$o->content=(string)file_get_contents($f);$o->type=$type;$o->theme=get_stylesheet();$o->source='theme';$o->title=$slug;return $o; }
function get_block_template($id,$type='wp_template') { return get_block_file_template($id,$type); }
function get_block_templates($q=[],$type='wp_template') { $o=[];$sub=$type==='wp_template_part'?'parts':'templates';foreach(array_unique([get_template_directory(),get_stylesheet_directory()]) as $d)foreach(glob($d.'/'.$sub.'/*.html')?:[] as $f)$o[]=get_block_file_template(basename($f,'.html'),$type);return array_values(array_filter($o)); }
function register_block_template($name,$args=[]) { $GLOBALS['elvado_wp_registered_block_templates'][$name]=(array)$args;return true; }
function get_the_block_template_html() { return (string)($GLOBALS['elvado_wp_block_template_html']??''); }

/** Wird vom Router aufgerufen: komplette HTML-Seite für ein Block-Theme. */
function elvado_wp_render_block_template(WP_Query $q): void {
    $t=elvado_wp_resolve_block_template($q);
    $html=$t['file']!==''?(string)file_get_contents($t['file']):'';
    $GLOBALS['elvado_wp_block_template_slug']=$t['slug'];
    $content=do_blocks(apply_filters('elvado_wp_block_template_content',$html,$t['slug']));
    $content=apply_filters('render_block_template',$content);
    ob_start();wp_head();$head=ob_get_clean();
    ob_start();wp_body_open();$bodyOpen=ob_get_clean();
    ob_start();wp_footer();$foot=ob_get_clean();
    $bc=implode(' ',get_body_class());
    echo '<!DOCTYPE html><html '.get_language_attributes().'><head><meta charset="'.esc_attr(get_bloginfo('charset')?:'UTF-8').'"><meta name="viewport" content="width=device-width, initial-scale=1">'.$head.'</head><body class="'.esc_attr($bc).'">'.$bodyOpen
        .'<div class="wp-site-blocks">'.$content.'</div>'.$foot.'</body></html>';
}

/* ───────── Block-Bindings (Platzhalter) ───────── */
function register_block_bindings_source($name,$props=[]) { $GLOBALS['elvado_wp_bindings'][$name]=(array)$props;return true; }
function unregister_block_bindings_source($name) { unset($GLOBALS['elvado_wp_bindings'][$name]);return true; }
function get_all_registered_block_bindings_sources() { return (array)($GLOBALS['elvado_wp_bindings']??[]); }
function get_block_bindings_source($name) { return $GLOBALS['elvado_wp_bindings'][$name]??null; }
function _wp_to_kebab_case($input_string) { return elvado_wp_kebab(preg_replace('/[^A-Za-z0-9]+/',' ',(string)$input_string)); }
