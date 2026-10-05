<?php
// Block-Unterstützungen: Farben, Typografie, Abstände, Rahmen, Ausrichtung und Layout (flow/constrained/flex/grid) für gerenderte Blöcke.

/** Standard-Layout je Blocktyp, wenn das Attribut layout fehlt. */
function rrw_wp_block_layout_defaults(): array {
    return ['core/group'=>['type'=>'default'],'core/post-content'=>['type'=>'default'],'core/query'=>['type'=>'default'],'core/post-template'=>['type'=>'default'],'core/column'=>['type'=>'default'],'core/comments'=>['type'=>'default'],
        'core/columns'=>['type'=>'flex','flexWrap'=>'nowrap'],'core/buttons'=>['type'=>'flex'],'core/social-links'=>['type'=>'flex'],'core/navigation'=>['type'=>'flex'],'core/query-pagination'=>['type'=>'flex'],'core/comments-pagination'=>['type'=>'flex'],'core/page-list'=>['type'=>'flex']];
}
function rrw_wp_slug_class(string $s): string { return trim(preg_replace('/[^a-z0-9_-]+/','-',strtolower($s)),'-'); }

/** Klassen und Inline-Stile aus den Attributen eines Blocks. @return array{class:array,style:array<string,string>,id:string} */
function rrw_wp_block_supports($block): array {
    $a=(array)($block->parsed_block['attrs']??[]);$cls=[];$style=[];$id='';
    $cs=(array)($a['style']??[]);
    if(!empty($a['align'])&&is_string($a['align'])&&$a['align']!=='none')$cls[]='align'.$a['align'];
    if(!empty($a['textColor'])){ $cls[]='has-text-color';$cls[]='has-'.rrw_wp_kebab((string)$a['textColor']).'-color'; }
    elseif(!empty($cs['color']['text']))$cls[]='has-text-color';
    if(!empty($a['backgroundColor'])){ $cls[]='has-background';$cls[]='has-'.rrw_wp_kebab((string)$a['backgroundColor']).'-background-color'; }
    elseif(!empty($cs['color']['background']))$cls[]='has-background';
    if(!empty($a['gradient'])){ $cls[]='has-background';$cls[]='has-'.rrw_wp_kebab((string)$a['gradient']).'-gradient-background'; }
    elseif(!empty($cs['color']['gradient']))$cls[]='has-background';
    if(!empty($a['borderColor'])){ $cls[]='has-border-color';$cls[]='has-'.rrw_wp_kebab((string)$a['borderColor']).'-border-color'; }
    elseif(!empty($cs['border']['color']))$cls[]='has-border-color';
    if(!empty($a['fontSize'])&&is_string($a['fontSize']))$cls[]='has-'.rrw_wp_kebab($a['fontSize']).'-font-size';
    if(!empty($a['fontFamily'])&&is_string($a['fontFamily']))$cls[]='has-'.rrw_wp_kebab($a['fontFamily']).'-font-family';
    if(!empty($a['textAlign'])&&is_string($a['textAlign']))$cls[]='has-text-align-'.$a['textAlign'];
    if(!empty($cs['elements']['link']['color']['text']))$cls[]='has-link-color';
    $style=rrw_wp_tj_declarations($cs);
    if(!empty($cs['elements'])){
        $rules='';
        foreach(['link'=>'a:where(:not(.wp-element-button))','heading'=>'h1,h2,h3,h4,h5,h6','button'=>'.wp-element-button, .wp-block-button__link'] as $el=>$sel){
            if(empty($cs['elements'][$el]))continue;$d=rrw_wp_tj_declarations((array)$cs['elements'][$el]);if(!$d)continue;
            $h='wp-elements-'.substr(md5(json_encode($cs['elements'])),0,12);if(!in_array($h,$cls,true))$cls[]=$h;
            $rules.=implode(',',array_map(fn($p)=>'.'.$h.' '.trim($p),explode(',',$sel))).'{'.rrw_wp_decl_css($d).'}';
            if(!empty($cs['elements'][$el][':hover'])){ $hd=rrw_wp_tj_declarations((array)$cs['elements'][$el][':hover']);if($hd)$rules.=implode(',',array_map(fn($p)=>'.'.$h.' '.trim($p).':hover',explode(',',$sel))).'{'.rrw_wp_decl_css($hd).'}'; }
            $GLOBALS['rrw_wp_block_support_css'][$h.$el]=$rules;$rules='';
        }
    }
    if(!empty($a['className']))$cls[]=(string)$a['className'];
    if(!empty($a['anchor']))$id=(string)$a['anchor'];
    return ['class'=>$cls,'style'=>$style,'id'=>$id];
}

function rrw_wp_layout_justify(string $v): string { return match($v){'left'=>'flex-start','right'=>'flex-end','center'=>'center','space-between'=>'space-between','stretch'=>'stretch',default=>'flex-start'}; }

/** Layout-Klassen (und Container-CSS) in den ersten Tag des gerenderten Blocks einfügen (render_block-Filter). */
function rrw_wp_layout_filter($content, $block, $instance=null) {
    $name=(string)($block['blockName']??'');if($name==='')return $content;
    $defs=rrw_wp_block_layout_defaults();
    $attrs=(array)($block['attrs']??[]);
    $layout=isset($attrs['layout'])&&is_array($attrs['layout'])?$attrs['layout']:($defs[$name]??null);
    if($layout===null||$content==='')return $content;
    if($name==='core/cover'||trim((string)$content)==='')return $content;
    $type=(string)($layout['type']??'default');if($type==='default'||$type==='')$type='flow';
    $short=rrw_wp_slug_class(strip_core_block_namespace($name));
    $cls=['is-layout-'.$type,'wp-block-'.$short.'-is-layout-'.$type];
    $css='';$hash=substr(md5($name.json_encode($layout).json_encode($attrs['style']['spacing']['blockGap']??null)),0,8);$cont='wp-container-'.$short.'-is-layout-'.$hash;$sel='.'.$cont;
    $gapRaw=$attrs['style']['spacing']['blockGap']??null;
    $gap=null;if(is_string($gapRaw)&&$gapRaw!=='')$gap=rrw_wp_tj_value($gapRaw);elseif(is_array($gapRaw)){ $r=rrw_wp_tj_value((string)($gapRaw['top']??''));$c=rrw_wp_tj_value((string)($gapRaw['left']??''));$gap=trim(($r?:'0').' '.($c?:$r?:'0')); }
    if($type==='flex'){
        $d=[];$vert=($layout['orientation']??'horizontal')==='vertical';
        if(($layout['flexWrap']??'')==='nowrap')$d[]='flex-wrap:nowrap';
        $jc=(string)($layout['justifyContent']??'');$va=(string)($layout['verticalAlignment']??'');
        if($vert){ $d[]='flex-direction:column'; $d[]='align-items:'.($jc!==''?rrw_wp_layout_justify($jc):'flex-start'); if($va!=='')$d[]='justify-content:'.rrw_wp_layout_justify($va==='top'?'left':($va==='bottom'?'right':$va)); }
        else{ if($jc!=='')$d[]='justify-content:'.rrw_wp_layout_justify($jc); if($va!=='')$d[]='align-items:'.($va==='top'?'flex-start':($va==='bottom'?'flex-end':$va)); }
        if($gap!==null)$d[]='gap:'.$gap;
        if($d){ $cls[]=$cont;$css=$sel.'{'.implode(';',$d).';}'; }
        if($jc==='space-between')$cls[]='is-content-justification-space-between';elseif($jc!=='')$cls[]='is-content-justification-'.$jc;
        if($vert)$cls[]='is-vertical';
        if(($layout['flexWrap']??'')==='nowrap')$cls[]='is-nowrap';
    } elseif($type==='constrained'){
        $cs=(string)($layout['contentSize']??'');$ws=(string)($layout['wideSize']??'');
        if($cs!==''||$ws!==''){ $cls[]=$cont;$css=$sel.' > :where(:not(.alignleft):not(.alignright):not(.alignfull)){'.($cs!==''?'max-width:'.$cs.';':'').'margin-left:auto !important;margin-right:auto !important;}'.($ws!==''?$sel.' > .alignwide{max-width:'.$ws.';}':''); }
        if($gap!==null){ if(!in_array($cont,$cls,true))$cls[]=$cont;$css.=':root :where('.$sel.') > *{margin-block-start:0;margin-block-end:0;}:root :where('.$sel.') > * + *{margin-block-start:'.$gap.';margin-block-end:0;}'; }
        if(($layout['justifyContent']??'')==='left')$cls[]='is-content-justification-left';
        if(rrw_wp_tj_setting('useRootPaddingAwareAlignments',false))$cls[]='has-global-padding';
    } elseif($type==='flow'){
        if($gap!==null){ $cls[]=$cont;$css=':root :where('.$sel.') > *{margin-block-start:0;margin-block-end:0;}:root :where('.$sel.') > * + *{margin-block-start:'.$gap.';margin-block-end:0;}'; }
    } elseif($type==='grid'){
        $d=[];
        if(!empty($layout['columnCount']))$d[]='grid-template-columns:repeat('.(int)$layout['columnCount'].', minmax(0, 1fr))';
        else $d[]='grid-template-columns:repeat(auto-fill, minmax(min('.($layout['minimumColumnWidth']??'12rem').', 100%), 1fr))';
        if($gap!==null)$d[]='gap:'.$gap;
        $cls[]=$cont;$css=$sel.'{'.implode(';',$d).';}';
    }
    if($css!=='')$GLOBALS['rrw_wp_block_support_css'][$cont]=$css;
    $add=implode(' ',array_unique($cls));
    if(preg_match('/^(\s*<[a-zA-Z][a-zA-Z0-9-]*)([^>]*)>/s',(string)$content,$m)){
        $tag=$m[1];$rest=$m[2];
        if(preg_match('/\sclass=(["\'])(.*?)\1/s',$rest,$cm)){ $new=preg_replace('/\sclass=(["\'])(.*?)\1/s',' class="'.$cm[2].' '.$add.'"',$rest,1); }
        else $new=$rest.' class="'.$add.'"';
        return $tag.$new.'>'.substr((string)$content,strlen($m[0]));
    }
    return $content;
}
add_filter('render_block','rrw_wp_layout_filter',10,3);
