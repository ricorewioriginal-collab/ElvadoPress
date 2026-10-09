<?php
// Ergänzende Block-Unterstützungen (supports): Registrierung der Attribute (wp_register_*_support) und Ermittlung von Klassen/Stilen
// (wp_apply_*_support bzw. wp_render_*_support), Style-Engine (CSS-Regeln, Speicher je Kontext), Global-Styles-Hilfen, Muster,
// Stil-Variationen und Block-Bindungen. Die serverseitige Standardausgabe der Schicht liegt in core/block-supports.php und core/fse.php;
// diese Funktionen sind die dokumentierten Einzelbausteine (werden nicht automatisch an Filter gehängt).

/* ───────── gemeinsame Helfer ───────── */
/** Attribut eines Blocktyps ergänzen, wenn es fehlt. */
if(!function_exists('elvado_wp_bs_add_attr')){
function elvado_wp_bs_add_attr($block_type, string $name, array $def): void {
    if(!is_array($block_type->attributes))$block_type->attributes=[];
    if(!array_key_exists($name,$block_type->attributes))$block_type->attributes[$name]=$def;
}}
/** Ergebnis wie bei wp_apply_*_support: nur nicht-leere Schlüssel class und style. */
if(!function_exists('elvado_wp_bs_out')){
function elvado_wp_bs_out(array $classes, array $decl): array {
    $a=[];$c=implode(' ',array_unique(array_filter($classes)));$s=elvado_wp_decl_css($decl);
    if($c!=='')$a['class']=$c;if($s!=='')$a['style']=$s;
    return $a;
}}
/** Voreinstellung (slug) oder eigener Wert als Stilwert, z. B. var:preset|color|primary. */
if(!function_exists('elvado_wp_bs_preset')){
function elvado_wp_bs_preset(array $attrs, string $attr, string $type, $custom) { return array_key_exists($attr,$attrs)&&$attrs[$attr]!==''&&$attrs[$attr]!==null?"var:preset|$type|{$attrs[$attr]}":$custom; }
}
/** Erstes Start-Tag eines HTML-Teils bearbeiten. $fn($tagProcessor) */
if(!function_exists('elvado_wp_bs_first_tag')){
function elvado_wp_bs_first_tag($html, callable $fn): string {
    $t=new WP_HTML_Tag_Processor((string)$html);
    if($t->next_tag())$fn($t);
    return (string)$t->get_updated_html();
}}

/* ───────── Style-Engine ───────── */
if(!function_exists('elvado_wp_sty_clean')){
/** Deklarationen prüfen: nur sichere Eigenschaften und Werte (kein ; { } < > Kommentar). */
function elvado_wp_sty_clean(array $decl): array {
    $o=[];
    foreach($decl as $p=>$v){
        if($v===null||$v===''||!is_scalar($v)||!preg_match('/^(--)?[a-z][a-z0-9-]*$/i',(string)$p))continue;
        $v=trim((string)$v);
        if(preg_match('/[;{}<>]|\/\*|expression\s*\(|javascript:|\\\\/i',$v))continue;
        $o[(string)$p]=$v;
    }
    return $o;
}}
if(!function_exists('elvado_wp_sty_compile')){
/** Regeln [[Selektor, Deklarationen]] zu CSS; gleiche Selektoren werden zusammengefasst. */
function elvado_wp_sty_compile(array $rules, array $options=[]): string {
    $pretty=array_key_exists('prettify',$options)?(bool)$options['prettify']:(defined('SCRIPT_DEBUG')&&SCRIPT_DEBUG);$by=[];
    foreach($rules as [$sel,$decl]){ $sel=trim(wp_strip_all_tags((string)$sel));if($sel==='')continue;$by[$sel]=array_merge($by[$sel]??[],$decl); }
    $o='';
    foreach($by as $sel=>$decl){
        if($pretty){ $o.=$sel." {\n";foreach($decl as $p=>$v)$o.="\t$p: $v;\n";$o.="}\n"; }
        else{ $o.=$sel.'{';foreach($decl as $p=>$v)$o.="$p:$v;";$o.='}'; }
    }
    return $o;
}}
if(!function_exists('elvado_wp_style_store')){
/** Regel im Speicher des Kontexts ablegen; Kontext „block-supports“ erscheint zusätzlich im Footer (core-block-supports-inline-css). */
function elvado_wp_style_store(string $context, string $selector, array $decl): void {
    $decl=elvado_wp_sty_clean($decl);if(!$decl||$selector==='')return;
    $GLOBALS['elvado_wp_style_engine_store'][$context][$selector]=array_merge($GLOBALS['elvado_wp_style_engine_store'][$context][$selector]??[],$decl);
    if($context==='block-supports')$GLOBALS['elvado_wp_block_support_css']['se:'.$selector]=elvado_wp_sty_compile([[$selector,$GLOBALS['elvado_wp_style_engine_store'][$context][$selector]]],['prettify'=>false]);
}}
if(!function_exists('wp_style_engine_get_stylesheet_from_css_rules')){
function wp_style_engine_get_stylesheet_from_css_rules($css_rules, $options=[]) {
    $rules=[];
    foreach((array)$css_rules as $r){
        if(empty($r['selector'])||empty($r['declarations'])||!is_array($r['declarations']))continue;
        $d=elvado_wp_sty_clean($r['declarations']);if(!$d)continue;
        if(!empty($options['context']))elvado_wp_style_store((string)$options['context'],(string)$r['selector'],$d);
        $rules[]=[$r['selector'],$d];
    }
    return elvado_wp_sty_compile($rules,(array)$options);
}}
if(!function_exists('wp_style_engine_get_stylesheet_from_context')){
function wp_style_engine_get_stylesheet_from_context($context, $options=[]) {
    $rules=[];foreach((array)($GLOBALS['elvado_wp_style_engine_store'][$context]??[]) as $sel=>$d)$rules[]=[$sel,$d];
    return elvado_wp_sty_compile($rules,(array)$options);
}}
if(!function_exists('wp_should_skip_block_supports_serialization')){
function wp_should_skip_block_supports_serialization($block_type, $feature_set, $feature=null) {
    if(!is_object($block_type)||!isset($block_type->supports[$feature_set]))return false;
    $skip=_wp_array_get((array)$block_type->supports,[$feature_set,'__experimentalSkipSerialization'],false);
    return is_array($skip)?in_array($feature,$skip,true):$skip;
}}

/* ───────── Typografie ───────── */
if(!function_exists('wp_register_typography_support')){
function wp_register_typography_support($block_type) {
    $ts=is_array($block_type->supports)?($block_type->supports['typography']??false):false;if(!$ts)return;
    elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']);
    if(!empty($ts['fontSize']))elvado_wp_bs_add_attr($block_type,'fontSize',['type'=>'string']);
    if(!empty($ts['__experimentalFontFamily']))elvado_wp_bs_add_attr($block_type,'fontFamily',['type'=>'string']);
}}
if(!function_exists('wp_typography_get_preset_inline_style_value')){
function wp_typography_get_preset_inline_style_value($style_value, $css_property) {
    if(empty($style_value)||!str_contains($style_value,"var:preset|{$css_property}|"))return $style_value;
    $slug=_wp_to_kebab_case(substr($style_value,strrpos($style_value,'|')+1));
    return sprintf('var(--wp--preset--%s--%s);',$css_property,$slug);
}}
if(!function_exists('wp_get_typography_value_and_unit')){
function wp_get_typography_value_and_unit($raw_value, $options=[]) {
    if(!is_string($raw_value)&&!is_int($raw_value)&&!is_float($raw_value)){ _doing_it_wrong(__FUNCTION__,'Der Wert muss eine Zeichenkette oder Zahl sein.','6.1.0');return null; }
    if(empty($raw_value))return null;
    $o=wp_parse_args($options,['coerce_to'=>'','root_size_value'=>16,'acceptable_units'=>['rem','px','em']]);
    if(!preg_match('/^(\d*\.?\d+)('.implode('|',$o['acceptable_units']).'){1,1}$/',(string)$raw_value,$m))return null;
    $value=(float)$m[1];$unit=$m[2];
    if(!empty($o['coerce_to'])&&$unit!==$o['coerce_to']&&is_numeric($o['root_size_value'])){
        if($o['coerce_to']==='px'&&in_array($unit,['em','rem'],true)){ $value*=$o['root_size_value'];$unit='px'; }
        elseif(in_array($o['coerce_to'],['em','rem'],true)&&$unit==='px'){ $value/=$o['root_size_value'];$unit=$o['coerce_to']; }
        elseif(in_array($o['coerce_to'],['em','rem'],true)&&in_array($unit,['em','rem'],true))$unit=$o['coerce_to'];
    }
    return ['value'=>round($value,3),'unit'=>$unit];
}}
if(!function_exists('wp_get_computed_fluid_typography_value')){
function wp_get_computed_fluid_typography_value($args=[]) {
    $maxVpRaw=$args['maximum_viewport_width']??'1600px';$minVpRaw=$args['minimum_viewport_width']??'320px';
    $minRaw=$args['minimum_font_size']??null;$maxRaw=$args['maximum_font_size']??null;$scale=$args['scale_factor']??1;
    $min=wp_get_typography_value_and_unit($minRaw);$unit=$min['unit']??'rem';
    $max=wp_get_typography_value_and_unit($maxRaw,['coerce_to'=>$unit]);
    if(!$max||!$min)return null;
    $minRem=wp_get_typography_value_and_unit($minRaw,['coerce_to'=>'rem']);
    $maxVp=wp_get_typography_value_and_unit($maxVpRaw,['coerce_to'=>$unit]);$minVp=wp_get_typography_value_and_unit($minVpRaw,['coerce_to'=>$unit]);
    if(!$maxVp||!$minVp||$maxVp['value']==$minVp['value'])return null;
    $offset=round($minVp['value']/100,3).$unit;
    $factor=100*(($max['value']-$min['value'])/($maxVp['value']-$minVp['value']));
    $scaled=round($factor*$scale,3);$scaled=empty($scaled)?1:$scaled;
    return "clamp($minRaw, ".implode('',$minRem)." + ((1vw - $offset) * $scaled), $maxRaw)";
}}
if(!function_exists('wp_get_typography_font_size_value')){
function wp_get_typography_font_size_value($preset, $settings=[]) {
    if(!isset($preset['size']))return '';
    if(empty($preset['size']))return $preset['size'];
    if(is_bool($settings))$settings=['typography'=>['fluid'=>$settings]];
    elseif(empty($settings))$settings=['typography'=>['fluid'=>elvado_wp_tj_setting('typography.fluid',false)]];
    $fluid=$settings['typography']['fluid']??false;if(empty($fluid))return $preset['size'];
    $fs=is_array($fluid)?$fluid:[];$ps=$preset['fluid']??null;
    if($ps===false)return $preset['size'];
    $pref=wp_get_typography_value_and_unit($preset['size']);if(empty($pref['unit']))return $preset['size'];
    $minRaw=is_array($ps)?($ps['min']??null):null;$maxRaw=is_array($ps)?($ps['max']??null):null;
    $limitRaw=$fs['minFontSize']??'14px';$limit=$limitRaw?wp_get_typography_value_and_unit($limitRaw,['coerce_to'=>$pref['unit']]):null;
    if($limit&&!$minRaw&&$pref['value']<=$limit['value'])return $preset['size'];   // zu klein für fluide Größe
    if(!$maxRaw)$maxRaw=$preset['size'];
    if(!$minRaw){
        $calc=round($pref['value']*0.75,3);
        $minRaw=$limit&&$calc<=$limit['value']?$limit['value'].$limit['unit']:$calc.$pref['unit'];
    }
    $v=wp_get_computed_fluid_typography_value(['minimum_viewport_width'=>$fs['minViewportWidth']??'320px','maximum_viewport_width'=>$fs['maxViewportWidth']??'1600px','minimum_font_size'=>$minRaw,'maximum_font_size'=>$maxRaw,'size'=>$preset['size'],'scale_factor'=>1]);
    return !empty($v)?$v:$preset['size'];
}}
if(!function_exists('wp_apply_typography_support')){
function wp_apply_typography_support($block_type, $block_attributes) {
    if(!$block_type instanceof WP_Block_Type)return [];
    $ts=(array)$block_type->supports;$ts=$ts['typography']??false;if(!$ts||wp_should_skip_block_supports_serialization($block_type,'typography'))return [];
    $ts=is_array($ts)?$ts:[];$a=(array)$block_attributes;$classes=[];$typo=[];
    $style=$a['style']['typography']??[];
    $legacy=['fontFamily'=>'__experimentalFontFamily','fontStyle'=>'__experimentalFontStyle','fontWeight'=>'__experimentalFontWeight','letterSpacing'=>'__experimentalLetterSpacing','textDecoration'=>'__experimentalTextDecoration','textTransform'=>'__experimentalTextTransform','writingMode'=>'__experimentalWritingMode'];
    foreach(['fontSize','fontFamily','fontStyle','fontWeight','lineHeight','letterSpacing','textDecoration','textTransform','writingMode','textIndent'] as $k){
        $on=!empty($ts[$k])||(isset($legacy[$k])&&!empty($ts[$legacy[$k]]));
        if(!$on||wp_should_skip_block_supports_serialization($block_type,'typography',$k))continue;
        if(($k==='fontSize'||$k==='fontFamily')&&!empty($a[$k]))$classes[]='has-'._wp_to_kebab_case((string)$a[$k]).'-'.($k==='fontSize'?'font-size':'font-family');
        if(isset($style[$k]))$typo[$k]=$k==='fontSize'?(wp_get_typography_font_size_value(['size'=>$style[$k]])?:$style[$k]):$style[$k];
    }
    if(!empty($ts['textAlign'])&&!empty($a['textAlign'])&&!wp_should_skip_block_supports_serialization($block_type,'typography','textAlign'))$classes[]='has-text-align-'.$a['textAlign'];
    return elvado_wp_bs_out($classes,elvado_wp_tj_declarations(['typography'=>$typo]));
}}
if(!function_exists('wp_render_typography_support')){
function wp_render_typography_support($block_content, $block) {
    if(!isset($block['attrs']['style']['typography']['fontSize']))return $block_content;
    $custom=$block['attrs']['style']['typography']['fontSize'];$fluid=wp_get_typography_font_size_value(['size'=>$custom]);
    if(!$fluid||$fluid===$custom)return $block_content;
    // nur im style-Attribut des ersten Elements ersetzen
    return preg_replace_callback('/^\s*<[a-zA-Z][^>]*>/',fn($m)=>preg_replace_callback('/(style="[^"]*?font-size:\s*)'.preg_quote((string)$custom,'/').'/',fn($x)=>$x[1].$fluid,$m[0],1),(string)$block_content,1);
}}

/* ───────── Layout ───────── */
if(!function_exists('wp_get_layout_definitions')){
function wp_get_layout_definitions() {
    $float=[['selector'=>' > .alignleft','rules'=>['float'=>'left','margin-inline-start'=>'0','margin-inline-end'=>'2em']],['selector'=>' > .alignright','rules'=>['float'=>'right','margin-inline-start'=>'2em','margin-inline-end'=>'0']],['selector'=>' > .aligncenter','rules'=>['margin-left'=>'auto !important','margin-right'=>'auto !important']]];
    $space=[['selector'=>' > :first-child','rules'=>['margin-block-start'=>'0']],['selector'=>' > :last-child','rules'=>['margin-block-end'=>'0']],['selector'=>' > *','rules'=>['margin-block-start'=>null,'margin-block-end'=>'0']]];
    return [
        'default'=>['name'=>'default','slug'=>'flow','className'=>'is-layout-flow','baseStyles'=>$float,'spacingStyles'=>$space],
        'constrained'=>['name'=>'constrained','slug'=>'constrained','className'=>'is-layout-constrained','baseStyles'=>array_merge($float,[['selector'=>' > :where(:not(.alignleft):not(.alignright):not(.alignfull))','rules'=>['max-width'=>'var(--wp--style--global--content-size)','margin-left'=>'auto !important','margin-right'=>'auto !important']],['selector'=>' > .alignwide','rules'=>['max-width'=>'var(--wp--style--global--wide-size)']]]),'spacingStyles'=>$space],
        'flex'=>['name'=>'flex','slug'=>'flex','className'=>'is-layout-flex','displayMode'=>'flex','baseStyles'=>[['selector'=>'','rules'=>['flex-wrap'=>'wrap','align-items'=>'center']],['selector'=>' > :is(*, div)','rules'=>['margin'=>'0']]],'spacingStyles'=>[['selector'=>'','rules'=>['gap'=>null]]],
            'orientations'=>['horizontal'=>['justifyContent'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end','space-between'=>'space-between'],'verticalAlignment'=>['top'=>'flex-start','center'=>'center','bottom'=>'flex-end','stretch'=>'stretch']],
                'vertical'=>['justifyContent'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end','stretch'=>'stretch'],'verticalAlignment'=>['top'=>'flex-start','center'=>'center','bottom'=>'flex-end','space-between'=>'space-between']]]],
        'grid'=>['name'=>'grid','slug'=>'grid','className'=>'is-layout-grid','displayMode'=>'grid','baseStyles'=>[['selector'=>' > :is(*, div)','rules'=>['margin'=>'0']]],'spacingStyles'=>[['selector'=>'','rules'=>['gap'=>null]]]],
    ];
}}
if(!function_exists('wp_register_layout_support')){
function wp_register_layout_support($block_type) {
    if(block_has_support($block_type,'layout',false)||block_has_support($block_type,'__experimentalLayout',false))elvado_wp_bs_add_attr($block_type,'layout',['type'=>'object']);
}}
/** Abstandswert: Voreinstellung var:preset|spacing|x → var(--wp--preset--spacing--x). */
if(!function_exists('elvado_wp_bs_gap')){
function elvado_wp_bs_gap($v) { if(is_string($v)&&str_contains($v,'var:preset|spacing|'))return 'var(--wp--preset--spacing--'._wp_to_kebab_case(substr($v,strrpos($v,'|')+1)).')';return $v; }
}
if(!function_exists('wp_get_layout_style')){
function wp_get_layout_style($selector, $layout, $has_block_gap_support=false, $gap_value=null, $should_skip_gap_serialization=false, $fallback_gap_value='0.5em', $block_spacing=null) {
    $type=$layout['type']??'default';$s=[];
    $flow=function($gap) use($selector,&$s){ $s[]=['selector'=>"$selector > *",'declarations'=>['margin-block-start'=>'0','margin-block-end'=>'0']];$s[]=['selector'=>"$selector > * + *",'declarations'=>['margin-block-start'=>$gap,'margin-block-end'=>'0']]; };
    $gapOf=function($g) use($fallback_gap_value){   // Einzelwert oder „Zeile Spalte“
        $sides=is_array($g)?['top','left']:['top'];$out='';
        foreach($sides as $side){ $v=is_string($g)?$g:_wp_array_get($g,[$side],$fallback_gap_value);$out.=elvado_wp_bs_gap($v).' '; }
        return trim($out);
    };
    if($type==='default'){
        if($has_block_gap_support){ if(is_array($gap_value))$gap_value=$gap_value['top']??null;if($gap_value!==null&&!$should_skip_gap_serialization)$flow(elvado_wp_bs_gap($gap_value)); }
    } elseif($type==='constrained'){
        $content=$layout['contentSize']??'';$wide=$layout['wideSize']??'';$just=$layout['justifyContent']??'center';
        $all=$content?:$wide;$wideMax=$wide?:$content;
        $ml=$just==='left'?'0 !important':'auto !important';$mr=$just==='right'?'0 !important':'auto !important';
        if($content||$wide){
            $s[]=['selector'=>"$selector > :where(:not(.alignleft):not(.alignright):not(.alignfull))",'declarations'=>['max-width'=>$all,'margin-left'=>$ml,'margin-right'=>$mr]];
            $s[]=['selector'=>"$selector > .alignwide",'declarations'=>['max-width'=>$wideMax]];
            $s[]=['selector'=>"$selector .alignfull",'declarations'=>['max-width'=>'none']];
        }
        if($has_block_gap_support){ if(is_array($gap_value))$gap_value=$gap_value['top']??null;if($gap_value!==null&&!$should_skip_gap_serialization)$flow(elvado_wp_bs_gap($gap_value)); }
    } elseif($type==='flex'){
        $orient=$layout['orientation']??'horizontal';$jc=['left'=>'flex-start','right'=>'flex-end','center'=>'center'];$va=['top'=>'flex-start','center'=>'center','bottom'=>'flex-end'];
        if($orient==='horizontal'){ $jc['space-between']='space-between';$va['stretch']='stretch'; } else $jc['stretch']='stretch';
        if(($layout['flexWrap']??'')==='nowrap')$s[]=['selector'=>$selector,'declarations'=>['flex-wrap'=>'nowrap']];
        if($has_block_gap_support&&isset($gap_value)&&!$should_skip_gap_serialization)$s[]=['selector'=>$selector,'declarations'=>['gap'=>$gapOf($gap_value)]];
        if($orient==='horizontal'){
            if(!empty($layout['justifyContent'])&&isset($jc[$layout['justifyContent']]))$s[]=['selector'=>$selector,'declarations'=>['justify-content'=>$jc[$layout['justifyContent']]]];
            if(!empty($layout['verticalAlignment'])&&isset($va[$layout['verticalAlignment']]))$s[]=['selector'=>$selector,'declarations'=>['align-items'=>$va[$layout['verticalAlignment']]]];
        } else {
            $s[]=['selector'=>$selector,'declarations'=>['flex-direction'=>'column']];
            $s[]=['selector'=>$selector,'declarations'=>['align-items'=>!empty($layout['justifyContent'])&&isset($jc[$layout['justifyContent']])?$jc[$layout['justifyContent']]:'flex-start']];
            if(!empty($layout['verticalAlignment'])&&isset($va[$layout['verticalAlignment']]))$s[]=['selector'=>$selector,'declarations'=>['justify-content'=>$va[$layout['verticalAlignment']]]];
        }
    } elseif($type==='grid'){
        if(!empty($layout['columnCount']))$s[]=['selector'=>$selector,'declarations'=>['grid-template-columns'=>'repeat('.(int)$layout['columnCount'].', minmax(0, 1fr))']];
        else $s[]=['selector'=>$selector,'declarations'=>['grid-template-columns'=>'repeat(auto-fill, minmax(min('.(!empty($layout['minimumColumnWidth'])?$layout['minimumColumnWidth']:'12rem').', 100%), 1fr))','container-type'=>'inline-size']];
        if($has_block_gap_support&&isset($gap_value)&&!$should_skip_gap_serialization)$s[]=['selector'=>$selector,'declarations'=>['gap'=>$gapOf($gap_value)]];
    }
    return $s?wp_style_engine_get_stylesheet_from_css_rules($s,['context'=>'block-supports','prettify'=>false]):'';
}}
if(!function_exists('wp_render_layout_support_flag')){
function wp_render_layout_support_flag($block_content, $block) {
    $bt=WP_Block_Type_Registry::get_instance()->get_registered((string)($block['blockName']??''));
    if(!block_has_support($bt,'layout',false)&&!block_has_support($bt,'__experimentalLayout',false))return $block_content;
    $sup=(array)$bt->supports;$fallback=$sup['layout']['default']??($sup['__experimentalLayout']['default']??[]);
    $used=$block['attrs']['layout']??$fallback;$defs=wp_get_layout_definitions();$global=wp_get_global_settings();$classes=[];
    if(!empty($used['inherit'])||!empty($used['contentSize']))$used['type']='constrained';
    if(!empty($global['useRootPaddingAwareAlignments'])&&($used['type']??'')==='constrained')$classes[]='has-global-padding';
    $def=$defs[$used['type']??'default']??$defs['default'];
    $classes[]=sanitize_title($def['className']);
    $short=wp_get_block_default_classname($block['blockName']);$classes[]=$short.'-is-layout-'.$def['slug'];
    if(!current_theme_supports('disable-layout-styles')){
        $gap=$block['attrs']['style']['spacing']['blockGap']??null;
        $safe=fn($v)=>$v&&preg_match('%[\\\\(&=}]|/\*%',(string)$v)?null:$v;   // unsichere Zeichen verwerfen
        $gap=is_array($gap)?array_map($safe,$gap):$safe($gap);
        $fallbackGap=$sup['spacing']['blockGap']['__experimentalDefault']??'0.5em';
        $skip=wp_should_skip_block_supports_serialization($bt,'spacing','blockGap');
        $hasGap=isset($global['spacing']['blockGap']);
        $container=wp_unique_prefixed_id('wp-container-'.sanitize_title($block['blockName']).'-is-layout-');
        $style=wp_get_layout_style('.'.$container,$used,$hasGap,$gap,$skip,$fallbackGap,$block['attrs']['style']['spacing']??null);
        if(!empty($style))$classes[]=$container;
    }
    return elvado_wp_bs_first_tag($block_content,function($t) use($classes){ foreach($classes as $c)$t->add_class($c); });
}}
if(!function_exists('wp_add_parent_layout_to_parsed_block')){
function wp_add_parent_layout_to_parsed_block($parsed_block, $source_block, $parent_block) {
    if(!$parent_block)return $parsed_block;
    $l=$parent_block->attributes['layout']??($parent_block->parsed_block['attrs']['layout']??null);
    if($l===null)return $parsed_block;
    $parsed_block['parentLayout']=$l;return $parsed_block;
}}
if(!function_exists('wp_restore_group_inner_container')){
function wp_restore_group_inner_container($block_content, $block) {
    $tag=$block['attrs']['tagName']??'div';
    $has=sprintf('/(^\s*<%1$s\b[^>]*wp-block-group(\s|")[^>]*>)(\s*<div\b[^>]*wp-block-group__inner-container(\s|")[^>]*>)((.|\S|\s)*)/U',preg_quote($tag,'/'));
    if(($block['blockName']??'')!=='core/group'||wp_theme_has_theme_json()||preg_match($has,(string)$block_content)===1)return $block_content;
    $rep=sprintf('/(^\s*<%1$s\b[^>]*wp-block-group[^>]*>)(.*)(<\/%1$s>\s*$)/ms',preg_quote($tag,'/'));
    return preg_replace_callback($rep,static fn($m)=>$m[1].'<div class="wp-block-group__inner-container">'.$m[2].'</div>'.$m[3],(string)$block_content);
}}
if(!function_exists('wp_restore_image_outer_container')){
function wp_restore_image_outer_container($block_content, $block) {
    $re="/(^\s*<figure\b[^>]*\bclass=[\"'])([^\"']*\bwp-block-image\b[^\"']*\b(?:alignleft|alignright|aligncenter)\b[^\"']*)([\"'][^>]*>.*<\/figure>)/iUs";
    if(($block['blockName']??'')!=='core/image'||wp_theme_has_theme_json()||preg_match($re,(string)$block_content,$m)!==1)return $block_content;
    $wrap=['wp-block-image'];
    if(!empty($block['attrs']['className']))$wrap=array_merge($wrap,explode(' ',$block['attrs']['className']));
    return '<div class="'.implode(' ',$wrap).'">'.$m[1].implode(' ',array_diff(explode(' ',$m[2]),$wrap)).$m[3].'</div>';
}}

/* ───────── Global Styles und Settings ───────── */
if(!function_exists('wp_get_block_name_from_theme_json_path')){
function wp_get_block_name_from_theme_json_path($path) {
    $path=(array)$path;$i=array_search('blocks',$path,true);
    return $i!==false&&isset($path[$i+1])&&is_string($path[$i+1])?$path[$i+1]:'';
}}
if(!function_exists('wp_clean_theme_json_cache')){
function wp_clean_theme_json_cache() {
    foreach(['wp_get_global_stylesheet','wp_get_global_styles_svg_filters','wp_theme_has_theme_json'] as $k)wp_cache_delete($k,'theme_json');
    elvado_wp_theme_json(true);
}}
if(!function_exists('wp_add_global_styles_for_blocks')){
/** Stile je Block (theme.json styles.blocks) an das Stylesheet „global-styles“ hängen. Block-Themes haben sie schon in wp_get_global_stylesheet(). */
function wp_add_global_styles_for_blocks() {
    if(wp_is_block_theme())return;
    $tj=elvado_wp_theme_json();$styles=elvado_wp_tj_merge((array)($tj['core']['styles']??[]),(array)($tj['theme']['styles']??[]));$css='';
    foreach((array)($styles['blocks']??[]) as $name=>$bs)if(is_array($bs))$css.=elvado_wp_style_rules($bs,elvado_wp_block_selector((string)$name));
    if($css==='')return;
    if(!wp_style_is('global-styles','registered'))wp_register_style('global-styles',false);
    wp_add_inline_style('global-styles',$css);
}}
/** Selektor „Wurzel“ eines Selektors unter einen Bereich setzen: „.a, .b“ + „.x“ → „.a .x, .b .x“. */
if(!function_exists('elvado_wp_scope_selector')){
function elvado_wp_scope_selector(string $scope, string $selector): string {
    $o=[];foreach(explode(',',$scope) as $sc)foreach(explode(',',$selector) as $sel)$o[]=trim($sc).' '.trim($sel);
    return implode(', ',$o);
}}
if(!function_exists('wp_get_block_css_selector')){
function wp_get_block_css_selector($block_type, $target='root', $fallback=false) {
    if(empty($target))return null;
    $sel=!empty($block_type->selectors)?(array)$block_type->selectors:[];$sup=(array)$block_type->supports;
    if(isset($sel['root']))$root=$sel['root'];
    elseif(isset($sup['__experimentalSelector'])&&is_string($sup['__experimentalSelector']))$root=$sup['__experimentalSelector'];
    else $root='.wp-block-'.str_replace('/','-',str_replace('core/','',$block_type->name));
    if($target==='root')return $root;
    if(is_string($target))$target=explode('.',$target);
    if(count($target)===1){
        $fb=$fallback?$root:null;
        if($sel){
            $f=_wp_array_get($sel,[current($target),'root'],null);if($f)return $f;
            $f=_wp_array_get($sel,$target,null);if(is_string($f))return $f;
        }
        $f=_wp_array_get($sup,[current($target),'__experimentalSelector'],null);
        return $f===null?$fb:elvado_wp_scope_selector($root,$f);
    }
    $sub=$sel?_wp_array_get($sel,$target,null):null;if($sub)return $sub;
    return $fallback?wp_get_block_css_selector($block_type,$target[0],$fallback):null;
}}

/* ───────── Muster ───────── */
if(!function_exists('_register_core_block_patterns_and_categories')){
function _register_core_block_patterns_and_categories() {
    $cats=['featured'=>'Hervorgehoben','posts'=>'Beiträge','text'=>'Text','gallery'=>'Galerie','call-to-action'=>'Handlungsaufforderung','banner'=>'Banner','header'=>'Kopfbereiche','footer'=>'Fußbereiche','about'=>'Über uns','portfolio'=>'Portfolio','services'=>'Leistungen','team'=>'Team','testimonials'=>'Stimmen','contact'=>'Kontakt','events'=>'Veranstaltungen','media'=>'Medien','query'=>'Abfrage','buttons'=>'Buttons','columns'=>'Spalten'];
    foreach($cats as $n=>$l)if(!isset($GLOBALS['elvado_wp_pattern_categories'][$n]))register_block_pattern_category($n,['label'=>$l]);   // Kern-Muster-Dateien liefert die Schicht nicht
}}
if(!function_exists('wp_normalize_remote_block_pattern')){
function wp_normalize_remote_block_pattern($pattern) {
    $title=$pattern['title'];if(is_array($title))$title=$title['rendered']??$title['raw']??'';
    $meta=(array)($pattern['meta']??[]);
    $kw=$meta['wpop_keywords']??($pattern['keywords']??[]);if(is_string($kw))$kw=array_values(array_filter(array_map('trim',explode(',',$kw))));
    $bt=$meta['wpop_block_types']??($pattern['block_types']??[]);
    return array_filter([
        'title'=>$title,'content'=>$pattern['pattern_content']??($pattern['content']['raw']??($pattern['content']??'')),
        'categories'=>array_values((array)($pattern['category_slugs']??($pattern['categories']??[]))),'viewportWidth'=>(int)($meta['wpop_viewport_width']??($pattern['viewport_width']??0)),
        'description'=>(string)($meta['wpop_description']??($pattern['description']??'')),'keywords'=>$kw,'blockTypes'=>(array)$bt,
    ],fn($v)=>$v!==''&&$v!==[]&&$v!==0);
}}
if(!function_exists('_load_remote_block_patterns')){
/** Registriert die übergebenen Muster der Muster-Verzeichnis-API; ohne Übergabe wird (kein Netzabruf) nichts geladen. */
function _load_remote_block_patterns($patterns=null) {
    if(!apply_filters('should_load_remote_block_patterns',true)||empty($patterns))return;
    foreach((array)$patterns as $p){ $n=wp_normalize_remote_block_pattern((array)$p);if(empty($n['title']))continue;register_block_pattern('core/'.sanitize_title($n['title']),$n); }
}}
if(!function_exists('_load_remote_featured_patterns')){
function _load_remote_featured_patterns() { /* Standardwert: kein Abruf von api.wordpress.org; Muster kommen aus Themes und Plugins. */ }
}
if(!function_exists('_register_remote_theme_patterns')){
function _register_remote_theme_patterns() { /* Standardwert: Muster des Verzeichnisses aus theme.json werden nicht nachgeladen (kein Netzabruf). */ }
}
if(!function_exists('_register_theme_block_patterns')){
function _register_theme_block_patterns() {
    foreach(elvado_wp_theme_patterns() as $slug=>$p){
        if(WP_Block_Patterns_Registry::get_instance()->is_registered($slug))continue;
        $h=get_file_data($p['file'],['description'=>'Description','categories'=>'Categories','keywords'=>'Keywords','blockTypes'=>'Block Types','postTypes'=>'Post Types','templateTypes'=>'Template Types','viewportWidth'=>'Viewport Width']);
        $list=fn($s)=>array_values(array_filter(array_map('trim',explode(',',(string)$s))));
        $content=elvado_wp_pattern_content($slug);if($content===null)continue;
        register_block_pattern($slug,array_filter(['title'=>$p['title'],'description'=>$h['description'],'content'=>$content,'filePath'=>$p['file'],'categories'=>$list($h['categories']),'keywords'=>$list($h['keywords']),'blockTypes'=>$list($h['blockTypes']),'postTypes'=>$list($h['postTypes']),'templateTypes'=>$list($h['templateTypes']),'viewportWidth'=>(int)$h['viewportWidth'],'inserter'=>$p['inserter']],fn($v)=>$v!==''&&$v!==[]&&$v!==0));
    }
}}

/* ───────── Stil-Variationen ───────── */
if(!class_exists('WP_Block_Styles_Registry')){
class WP_Block_Styles_Registry {
    private static $i;public static function get_instance() { return self::$i??=new self(); }
    public function register($block_name,$style_properties) { return register_block_style($block_name,$style_properties); }
    public function unregister($block_name,$name) { if(!$this->is_registered($block_name,$name))return false;unregister_block_style($block_name,$name);return true; }
    public function is_registered($block_name,$name) { return isset($GLOBALS['elvado_wp_block_styles'][$block_name][$name]); }
    public function get_registered($block_name,$name) { return $GLOBALS['elvado_wp_block_styles'][$block_name][$name]??null; }
    public function get_all_registered() { return (array)($GLOBALS['elvado_wp_block_styles']??[]); }
    public function get_registered_styles_for_block($block_name) { return (array)($GLOBALS['elvado_wp_block_styles'][$block_name]??[]); }
}
}
if(!function_exists('wp_get_block_style_variation_name_from_class')){
function wp_get_block_style_variation_name_from_class($class_string) {
    if(!is_string($class_string))return null;
    preg_match('/\bis-style-(?!default)(\S+)\b/',$class_string,$m);return $m[1]??null;
}}
if(!function_exists('wp_resolve_block_style_variation_ref_values')){
function wp_resolve_block_style_variation_ref_values($variation_data, $theme_json) {
    if(!is_array($variation_data))return $variation_data;
    foreach($variation_data as $k=>$v){
        if(is_array($v)&&isset($v['ref'])&&is_string($v['ref'])){
            $r=_wp_array_get((array)$theme_json,explode('.',$v['ref']),null);
            if(empty($r))unset($variation_data[$k]);else $variation_data[$k]=$r;
        } elseif(is_array($v))$variation_data[$k]=wp_resolve_block_style_variation_ref_values($v,$theme_json);
    }
    return $variation_data;
}}
if(!function_exists('wp_render_block_style_variation_support_styles')){
function wp_render_block_style_variation_support_styles($parsed_block) {
    $classes=$parsed_block['attrs']['className']??null;$var=wp_get_block_style_variation_name_from_class($classes);if(!$var)return $parsed_block;
    $tj=elvado_wp_theme_json();$merged=elvado_wp_tj_merge($tj['core'],$tj['theme']);
    $data=$merged['styles']['blocks'][$parsed_block['blockName']]['variations'][$var]??[];if(empty($data))return $parsed_block;
    $data=wp_resolve_block_style_variation_ref_values($data,$merged);
    $inst=wp_unique_prefixed_id($var.'--');$cls="is-style-$inst";
    $parsed_block['attrs']['className']=preg_replace('/\bis-style-'.preg_quote($var,'/').'\b/',$cls,$parsed_block['attrs']['className']);
    // Selektor des Blocks, die Instanz-Klasse hinter dem ersten Teil: „.wp-block-button .x“ → „.wp-block-button.cls .x“
    $sel=implode(',',array_map(function($p) use($cls){ $p=trim($p);$sp=strpos($p,' ');return $sp===false?$p.'.'.$cls:substr($p,0,$sp).'.'.$cls.substr($p,$sp); },explode(',',elvado_wp_block_selector((string)$parsed_block['blockName']))));
    $css=elvado_wp_style_rules($data,$sel);
    if($css!=='')$GLOBALS['elvado_wp_style_engine_raw']['block-style-variation-styles'][$inst]=$css;
    return $parsed_block;
}}
if(!function_exists('wp_render_block_style_variation_class_name')){
function wp_render_block_style_variation_class_name($block_content, $block) {
    if(!$block_content||empty($block['attrs']['className']))return $block_content;
    if(!preg_match('/\bis-style-(\S+?--\d+)\b/',$block['attrs']['className'],$m))return $block_content;
    return elvado_wp_bs_first_tag($block_content,function($t) use($m){ $t->add_class($m[0]); });
}}
if(!function_exists('wp_enqueue_block_style_variation_styles')){
function wp_enqueue_block_style_variation_styles() {
    $css=implode('',(array)($GLOBALS['elvado_wp_style_engine_raw']['block-style-variation-styles']??[]));if($css==='')return;
    if(!wp_style_is('block-style-variation-styles','registered'))wp_register_style('block-style-variation-styles',false);
    wp_add_inline_style('block-style-variation-styles',$css);wp_enqueue_style('block-style-variation-styles');
}}
if(!function_exists('wp_register_block_style_variations_from_theme_json_partials')){
function wp_register_block_style_variations_from_theme_json_partials($variations) {
    if(empty($variations))return;
    $reg=WP_Block_Styles_Registry::get_instance();$named=!wp_is_numeric_array($variations);
    foreach($variations as $key=>$v){
        $name=$named?$key:($v['slug']??_wp_to_kebab_case((string)($v['title']??'')));$label=$v['title']??$name;
        if(!$name)continue;
        foreach((array)($v['blockTypes']??[]) as $bt)if(!array_key_exists($name,$reg->get_registered_styles_for_block($bt)))register_block_style($bt,['name'=>$name,'label'=>$label]);
    }
}}

/* ───────── Elemente (Link, Button, Überschrift …) ───────── */
if(!function_exists('wp_get_elements_class_name')){
function wp_get_elements_class_name($block) { return 'wp-elements-'.md5(serialize($block)); }
}
if(!function_exists('wp_should_add_elements_class_name')){
/** $options['skip']: Elementnamen, die nicht zählen. */
function wp_should_add_elements_class_name($block, $options=[]) {
    $el=$block['attrs']['style']['elements']??null;if(!$el)return false;
    $bt=WP_Block_Type_Registry::get_instance()->get_registered((string)($block['blockName']??''));
    if(!$bt||!block_has_support($bt,'color',false))return false;
    $skip=(array)($options['skip']??[]);
    foreach(['button','link','heading','h1','h2','h3','h4','h5','h6','caption','cite'] as $e){
        if(in_array($e,$skip,true))continue;
        foreach([[$e,'color','text'],[$e,'color','background'],[$e,'color','gradient'],[$e,':hover','color','text']] as $path)if(_wp_array_get($el,$path,null)!==null)return true;
    }
    return false;
}}
if(!function_exists('wp_render_elements_support_styles')){
function wp_render_elements_support_styles($parsed_block) {
    $bt=WP_Block_Type_Registry::get_instance()->get_registered((string)($parsed_block['blockName']??''));
    $el=$parsed_block['attrs']['style']['elements']??null;if(!$el)return $parsed_block;
    $types=['button'=>['.wp-element-button','.wp-block-button__link'],'link'=>['a:where(:not(.wp-element-button))'],'heading'=>['h1','h2','h3','h4','h5','h6'],'caption'=>[':where(.wp-element-caption)'],'cite'=>['cite']];
    foreach(['h1','h2','h3','h4','h5','h6'] as $h)$types[$h]=[$h];
    $skip=[];foreach(['button','link','heading'] as $e)$skip[$e]=$bt&&wp_should_skip_block_supports_serialization($bt,'color',$e);
    if($skip['button']&&$skip['link']&&$skip['heading'])return $parsed_block;
    $cls=wp_get_elements_class_name($parsed_block);
    $parsed_block['attrs']['className']=isset($parsed_block['attrs']['className'])?$parsed_block['attrs']['className'].' '.$cls:$cls;
    foreach($types as $e=>$sels){
        if(!empty($skip[$e])||empty($el[$e]))continue;
        $mk=fn($suffix='')=>implode(', ',array_map(fn($s)=>".$cls $s$suffix",$sels));
        elvado_wp_style_store('block-supports',$mk(),elvado_wp_tj_declarations((array)$el[$e]));
        if(isset($el[$e][':hover']))elvado_wp_style_store('block-supports',$mk(':hover'),elvado_wp_tj_declarations((array)$el[$e][':hover']));
    }
    return $parsed_block;
}}
if(!function_exists('wp_render_elements_class_name')){
function wp_render_elements_class_name($block_content, $block) {
    if(!preg_match('/\bwp-elements-\S+\b/',(string)($block['attrs']['className']??''),$m))return $block_content;
    return elvado_wp_bs_first_tag($block_content,function($t) use($m){ $t->add_class($m[0]); });
}}

/* ───────── Voreinstellungen auf Blockebene (settings) ───────── */
if(!function_exists('_wp_get_presets_class_name')){
function _wp_get_presets_class_name($block) { return 'wp-settings-'.md5(serialize($block)); }
}
if(!function_exists('_wp_add_block_level_presets_class')){
function _wp_add_block_level_presets_class($block_content, $block) {
    if(!$block_content||empty($block['innerBlocks'])||empty($block['attrs']['settings']))return $block_content;
    return elvado_wp_bs_first_tag($block_content,function($t) use($block){ $t->add_class(_wp_get_presets_class_name($block)); });
}}
if(!function_exists('_wp_add_block_level_preset_styles')){
/** pre_render_block-Filter: Variablen und Klassen der Voreinstellungen eines Blocks (attrs.settings) in den Footer-Stil legen. */
function _wp_add_block_level_preset_styles($pre_render, $block) {
    if($pre_render!==null||empty($block['attrs']['settings']))return null;
    $s=(array)$block['attrs']['settings'];$cls='.'._wp_get_presets_class_name($block);$vars=[];$rules='';
    $list=function($path) use($s){ $v=_wp_array_get($s,explode('.',$path),[]);if(is_array($v)&&!array_is_list($v))$v=array_merge((array)($v['default']??[]),(array)($v['theme']??[]),(array)($v['custom']??[]));return is_array($v)?$v:[]; };
    foreach([['color.palette','color','color'],['color.gradients','gradient','gradient'],['typography.fontSizes','font-size','size'],['typography.fontFamilies','font-family','fontFamily'],['spacing.spacingSizes','spacing','size'],['shadow.presets','shadow','shadow']] as [$path,$type,$key])
        foreach($list($path) as $p){
            if(!is_array($p)||!isset($p['slug'],$p[$key]))continue;$slug=elvado_wp_kebab((string)$p['slug']);
            $vars["--wp--preset--$type--$slug"]=$p[$key];
            if($type==='color')$rules.="$cls .has-$slug-color,$cls.has-$slug-color{color:var(--wp--preset--color--$slug) !important;}$cls .has-$slug-background-color,$cls.has-$slug-background-color{background-color:var(--wp--preset--color--$slug) !important;}";
            elseif($type==='font-size')$rules.="$cls .has-$slug-font-size,$cls.has-$slug-font-size{font-size:var(--wp--preset--font-size--$slug) !important;}";
        }
    if($vars){ $d=elvado_wp_sty_clean($vars);if($d)$GLOBALS['elvado_wp_block_support_css']['preset:'.md5($cls)]="$cls,$cls *{".elvado_wp_decl_css($d).'}'.$rules; }
    return null;
}}

/* ───────── Abmessungen ───────── */
if(!function_exists('wp_register_dimensions_support')){
function wp_register_dimensions_support($block_type) {
    foreach(['aspectRatio','height','minHeight','width'] as $f)if(block_has_support($block_type,['dimensions',$f],false)){ elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']);return; }
}}
if(!function_exists('wp_apply_dimensions_support')){
function wp_apply_dimensions_support($block_type, $block_attributes) {
    if(wp_should_skip_block_supports_serialization($block_type,'dimensions'))return [];
    $d=[];$src=$block_attributes['style']['dimensions']??[];
    foreach(['aspectRatio','height','minHeight','width'] as $f)if(block_has_support($block_type,['dimensions',$f],false)&&!wp_should_skip_block_supports_serialization($block_type,'dimensions',$f)&&isset($src[$f]))$d[$f]=$src[$f];
    if(!empty($d['aspectRatio']))$d['minHeight']='unset';   // Seitenverhältnis hat Vorrang vor der Mindesthöhe
    $css=elvado_wp_decl_css(elvado_wp_tj_declarations(['dimensions'=>$d]));
    return $css!==''?['style'=>$css]:[];
}}
if(!function_exists('wp_render_dimensions_support')){
function wp_render_dimensions_support($block_content, $block) {
    $bt=WP_Block_Type_Registry::get_instance()->get_registered((string)($block['blockName']??''));
    if(!block_has_support($bt,['dimensions','aspectRatio'],false)||wp_should_skip_block_supports_serialization($bt,'dimensions','aspectRatio')||empty($block['attrs']['style']['dimensions']['aspectRatio']))return $block_content;
    return elvado_wp_bs_first_tag($block_content,function($t){ $t->add_class('has-aspect-ratio');$st=(string)$t->get_attribute('style');$t->set_attribute('style',rtrim($st,'; ').($st!==''?';':'').'min-height:unset;'); });
}}

/* ───────── Rahmen ───────── */
if(!function_exists('wp_has_border_feature_support')){
function wp_has_border_feature_support($block_type, $feature, $default_value=false) {
    if($block_type instanceof WP_Block_Type){
        foreach(['__experimentalBorder','border']as $k)if(isset($block_type->supports[$k])&&$block_type->supports[$k]===true)return true;
    }
    return block_has_support($block_type,['__experimentalBorder',$feature],$default_value)||block_has_support($block_type,['border',$feature],$default_value);
}}
if(!function_exists('wp_register_border_support')){
function wp_register_border_support($block_type) {
    if(wp_has_border_feature_support($block_type,'radius')||wp_has_border_feature_support($block_type,'color')||wp_has_border_feature_support($block_type,'width')||wp_has_border_feature_support($block_type,'style'))elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']);
    if(wp_has_border_feature_support($block_type,'color'))elvado_wp_bs_add_attr($block_type,'borderColor',['type'=>'string']);
}}
if(!function_exists('wp_apply_border_support')){
function wp_apply_border_support($block_type, $block_attributes) {
    if(wp_should_skip_block_supports_serialization($block_type,'__experimentalBorder')||wp_should_skip_block_supports_serialization($block_type,'border'))return [];
    $b=[];$src=$block_attributes['style']['border']??[];$classes=[];
    $px=fn($v)=>is_numeric($v)?$v.'px':$v;
    if(wp_has_border_feature_support($block_type,'radius')&&isset($src['radius']))$b['radius']=is_array($src['radius'])?array_map($px,$src['radius']):$px($src['radius']);
    if(wp_has_border_feature_support($block_type,'style')&&isset($src['style']))$b['style']=$src['style'];
    if(wp_has_border_feature_support($block_type,'width')&&isset($src['width']))$b['width']=$px($src['width']);
    if(wp_has_border_feature_support($block_type,'color')){
        $preset=array_key_exists('borderColor',(array)$block_attributes)?"var:preset|color|{$block_attributes['borderColor']}":null;
        $color=$preset?:($src['color']??null);if($color)$b['color']=$color;
        if(!empty($block_attributes['borderColor']))$classes=['has-border-color','has-'._wp_to_kebab_case((string)$block_attributes['borderColor']).'-border-color'];elseif(!empty($src['color']))$classes=['has-border-color'];
    }
    foreach(['top','right','bottom','left'] as $side)if(isset($src[$side])&&is_array($src[$side]))$b[$side]=array_map(fn($v)=>is_numeric($v)?$v.'px':$v,$src[$side]);
    return elvado_wp_bs_out($classes,elvado_wp_tj_declarations(['border'=>$b]));
}}

/* ───────── Klassennamen ───────── */
if(!function_exists('wp_get_block_default_classname')){
function wp_get_block_default_classname($block_name) {
    $c='wp-block-'.preg_replace('/^core-/','',str_replace('/','-',(string)$block_name));
    return apply_filters('block_default_classname',$c,$block_name);
}}
if(!function_exists('wp_apply_generated_classname_support')){
function wp_apply_generated_classname_support($block_type) {
    $a=[];
    if(block_has_support($block_type,'className',true)){ $c=wp_get_block_default_classname($block_type->name);if($c!=='')$a['class']=$c; }
    return $a;
}}
if(!function_exists('wp_register_custom_classname_support')){
function wp_register_custom_classname_support($block_type) { if(block_has_support($block_type,'customClassName',true))elvado_wp_bs_add_attr($block_type,'className',['type'=>'string']); }
}
if(!function_exists('wp_apply_custom_classname_support')){
function wp_apply_custom_classname_support($block_type, $block_attributes) {
    $a=[];if(block_has_support($block_type,'customClassName',true)&&isset($block_attributes['className']))$a['class']=$block_attributes['className'];
    return $a;
}}

/* ───────── Schatten, Position, Abstände, ARIA ───────── */
if(!function_exists('wp_register_shadow_support')){
function wp_register_shadow_support($block_type) {
    if(!block_has_support($block_type,'shadow',false))return;
    elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']);elvado_wp_bs_add_attr($block_type,'shadow',['type'=>'string']);
}}
if(!function_exists('wp_apply_shadow_support')){
function wp_apply_shadow_support($block_type, $block_attributes) {
    if(!block_has_support($block_type,'shadow',false))return [];
    $v=elvado_wp_bs_preset((array)$block_attributes,'shadow','shadow',$block_attributes['style']['shadow']??null);
    $css=elvado_wp_decl_css(elvado_wp_tj_declarations(['shadow'=>$v]));
    return $css!==''?['style'=>$css]:[];
}}
if(!function_exists('wp_register_position_support')){
function wp_register_position_support($block_type) { if(block_has_support($block_type,'position',false))elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']); }
}
if(!function_exists('wp_render_position_support')){
function wp_render_position_support($block_content, $block) {
    $bt=WP_Block_Type_Registry::get_instance()->get_registered((string)($block['blockName']??''));
    if(!block_has_support($bt,'position',false)||empty($block['attrs']['style']['position']))return $block_content;
    $g=wp_get_global_settings();$allowed=[];
    if(($g['position']['sticky']??false)===true)$allowed[]='sticky';if(($g['position']['fixed']??false)===true)$allowed[]='fixed';
    $pos=$block['attrs']['style']['position'];$type=$pos['type']??'';
    if(!in_array($type,$allowed,true))return $block_content;
    $cls=wp_unique_prefixed_id('wp-container-');$sel='.'.$cls;$rules=[];
    foreach(['top','right','bottom','left'] as $side){
        if(!isset($pos[$side]))continue;$v=$pos[$side];
        if($side==='top'&&($type==='fixed'||$type==='sticky'))$v="calc($v + var(--wp-admin--admin-bar--position-offset, 0px))";   // Platz für die Admin-Leiste
        $rules[]=['selector'=>$sel,'declarations'=>[$side=>$v]];
    }
    $rules[]=['selector'=>$sel,'declarations'=>['position'=>$type,'z-index'=>'10']];
    wp_style_engine_get_stylesheet_from_css_rules($rules,['context'=>'block-supports','prettify'=>false]);
    return elvado_wp_bs_first_tag($block_content,function($t) use($type,$cls){ $t->add_class('is-position-'.$type);$t->add_class($cls); });
}}
if(!function_exists('wp_register_spacing_support')){
function wp_register_spacing_support($block_type) { if(block_has_support($block_type,'spacing',false))elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']); }
}
if(!function_exists('wp_apply_spacing_support')){
function wp_apply_spacing_support($block_type, $block_attributes) {
    if(wp_should_skip_block_supports_serialization($block_type,'spacing'))return [];
    $sp=[];
    foreach(['padding','margin'] as $f)if(block_has_support($block_type,['spacing',$f],false)&&!wp_should_skip_block_supports_serialization($block_type,'spacing',$f)){ $v=_wp_array_get((array)$block_attributes,['style','spacing',$f],null);if($v!==null)$sp[$f]=$v; }
    $css=elvado_wp_decl_css(elvado_wp_tj_declarations(['spacing'=>$sp]));
    return $css!==''?['style'=>$css]:[];
}}
if(!function_exists('wp_register_aria_label_support')){
function wp_register_aria_label_support($block_type) { if(block_has_support($block_type,'ariaLabel',false))elvado_wp_bs_add_attr($block_type,'ariaLabel',['type'=>'string']); }
}
if(!function_exists('wp_apply_aria_label_support')){
function wp_apply_aria_label_support($block_type, $block_attributes) {
    if(!$block_attributes||!block_has_support($block_type,'ariaLabel',false))return [];
    return array_key_exists('ariaLabel',$block_attributes)?['aria-label'=>$block_attributes['ariaLabel']]:[];
}}

/* ───────── Hintergrund, Farben, Ausrichtung ───────── */
if(!function_exists('wp_register_background_support')){
function wp_register_background_support($block_type) { if(block_has_support($block_type,'background',false))elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']); }
}
if(!function_exists('wp_render_background_support')){
function wp_render_background_support($block_content, $block) {
    $bt=WP_Block_Type_Registry::get_instance()->get_registered((string)($block['blockName']??''));$bg=$block['attrs']['style']['background']??null;
    if(!block_has_support($bt,['background','backgroundImage'],false)||wp_should_skip_block_supports_serialization($bt,'background','backgroundImage')||!is_array($bg))return $block_content;
    $s=['backgroundImage'=>$bg['backgroundImage']??null,'backgroundSize'=>$bg['backgroundSize']??null,'backgroundPosition'=>$bg['backgroundPosition']??null,'backgroundRepeat'=>$bg['backgroundRepeat']??null,'backgroundAttachment'=>$bg['backgroundAttachment']??null];
    if(!empty($s['backgroundImage'])){ $s['backgroundSize']=$s['backgroundSize']??'cover';if($s['backgroundSize']==='contain'&&!$s['backgroundPosition'])$s['backgroundPosition']='50% 50%'; }
    $css=elvado_wp_decl_css(elvado_wp_tj_declarations(['background'=>array_filter($s,fn($v)=>$v!==null)]));
    if($css==='')return $block_content;
    return elvado_wp_bs_first_tag($block_content,function($t) use($css){ $st=(string)$t->get_attribute('style');if($st!==''&&!str_ends_with($st,';'))$st.=';';$t->set_attribute('style',$st.$css);$t->add_class('has-background'); });
}}
if(!function_exists('elvado_wp_bs_color_flags')){
/** [Text, Hintergrund, Verlauf] aus supports.color. */
function elvado_wp_bs_color_flags($block_type): array {
    $c=is_array($block_type->supports)?($block_type->supports['color']??false):false;
    $on=fn($k,$d)=>$c===true||(is_array($c)&&_wp_array_get($c,[$k],$d));
    return [$on('text',true),$on('background',true),is_array($c)&&_wp_array_get($c,['gradients'],false),$c];
}}
if(!function_exists('wp_register_colors_support')){
function wp_register_colors_support($block_type) {
    [$text,$bg,$grad,$c]=elvado_wp_bs_color_flags($block_type);
    $other=is_array($c)&&(_wp_array_get($c,['link'],false)||_wp_array_get($c,['button'],false)||_wp_array_get($c,['heading'],false));
    if(!($text||$bg||$grad||$other)||!$c)return;
    elvado_wp_bs_add_attr($block_type,'style',['type'=>'object']);
    if($bg)elvado_wp_bs_add_attr($block_type,'backgroundColor',['type'=>'string']);if($text)elvado_wp_bs_add_attr($block_type,'textColor',['type'=>'string']);if($grad)elvado_wp_bs_add_attr($block_type,'gradient',['type'=>'string']);
}}
if(!function_exists('wp_apply_colors_support')){
function wp_apply_colors_support($block_type, $block_attributes) {
    [$text,$bg,$grad,$c]=elvado_wp_bs_color_flags($block_type);if(!$c)return [];
    if(is_array($c)&&wp_should_skip_block_supports_serialization($block_type,'color'))return [];
    $a=(array)$block_attributes;$st=$a['style']['color']??[];$colors=[];$classes=[];
    if($text&&!wp_should_skip_block_supports_serialization($block_type,'color','text')){
        $colors['text']=elvado_wp_bs_preset($a,'textColor','color',$st['text']??null);
        if(!empty($a['textColor']))array_push($classes,'has-text-color','has-'._wp_to_kebab_case((string)$a['textColor']).'-color');elseif(!empty($st['text']))$classes[]='has-text-color';
    }
    if($grad&&!wp_should_skip_block_supports_serialization($block_type,'color','gradients')){
        $colors['gradient']=elvado_wp_bs_preset($a,'gradient','gradient',$st['gradient']??null);
        if(!empty($a['gradient']))array_push($classes,'has-background','has-'._wp_to_kebab_case((string)$a['gradient']).'-gradient-background');elseif(!empty($st['gradient']))$classes[]='has-background';
    }
    if($bg&&!wp_should_skip_block_supports_serialization($block_type,'color','background')){
        $colors['background']=elvado_wp_bs_preset($a,'backgroundColor','color',$st['background']??null);
        if(!empty($a['backgroundColor']))array_push($classes,'has-background','has-'._wp_to_kebab_case((string)$a['backgroundColor']).'-background-color');elseif(!empty($st['background']))$classes[]='has-background';
    }
    return elvado_wp_bs_out($classes,elvado_wp_tj_declarations(['color'=>array_filter($colors,fn($v)=>$v!==null&&$v!=='')]));
}}
if(!function_exists('wp_register_alignment_support')){
function wp_register_alignment_support($block_type) {
    if(is_array($block_type->supports)&&!empty($block_type->supports['align']))elvado_wp_bs_add_attr($block_type,'align',['type'=>'string','enum'=>['left','center','right','wide','full','']]);
}}
if(!function_exists('wp_apply_alignment_support')){
function wp_apply_alignment_support($block_type, $block_attributes) {
    $a=[];
    if(is_array($block_type->supports)&&!empty($block_type->supports['align'])&&array_key_exists('align',(array)$block_attributes))$a['class']=sprintf('align%s',$block_attributes['align']);
    return $a;
}}

/* ───────── Block-Bindings ───────── */
if(!function_exists('_block_bindings_post_meta_get_value')){
/** Wert eines benutzerdefinierten Felds. Geschützte Felder und nicht lesbare Beiträge liefern null (die Meta-Registrierung ist in der Schicht ein No-op, show_in_rest wird daher nicht geprüft). */
function _block_bindings_post_meta_get_value(array $source_args, $block_instance, string $attribute_name) {
    if(empty($source_args['key'])||empty($block_instance->context['postId']))return null;
    $id=$block_instance->context['postId'];$post=get_post($id);if(!$post)return null;
    $viewable=function_exists('is_post_publicly_viewable')?is_post_publicly_viewable($post):$post->post_status==='publish';
    if((!$viewable&&!current_user_can('read_post',$id))||post_password_required($post))return null;
    if(is_protected_meta($source_args['key'],'post'))return null;
    return get_post_meta($id,$source_args['key'],true);
}}
if(!function_exists('_register_block_bindings_post_meta_source')){
function _register_block_bindings_post_meta_source() { register_block_bindings_source('core/post-meta',['label'=>'Beitrags-Metadaten','get_value_callback'=>'_block_bindings_post_meta_get_value','uses_context'=>['postId','postType']]); }
}
if(!function_exists('_block_bindings_pattern_overrides_get_value')){
function _block_bindings_pattern_overrides_get_value(array $source_args, $block_instance, string $attribute_name) {
    if(empty($block_instance->attributes['metadata']['name']))return null;
    return _wp_array_get((array)$block_instance->context,['pattern/overrides',$block_instance->attributes['metadata']['name'],$attribute_name],null);
}}
if(!function_exists('_register_block_bindings_pattern_overrides_source')){
function _register_block_bindings_pattern_overrides_source() { register_block_bindings_source('core/pattern-overrides',['label'=>'Muster-Überschreibungen','get_value_callback'=>'_block_bindings_pattern_overrides_get_value','uses_context'=>['pattern/overrides']]); }
}
