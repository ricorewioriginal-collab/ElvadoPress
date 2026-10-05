<?php
declare(strict_types=1);

// RRW_CONTENT_DIR (Konstante) verlegt den Ordner – nur für Tests.
function rrw_content_root(): string { $d=defined('RRW_CONTENT_DIR')?(string)RRW_CONTENT_DIR:__DIR__.'/../content/pages';if(!is_dir($d))@mkdir($d,0755,true);return $d; }
function rrw_content_frontmatter(array $p): string {
    $data=['title'=>(string)($p['title']??''),'slug'=>(string)($p['slug']??''),'enabled'=>!empty($p['enabled']),'headline'=>(string)($p['headline']??''),'intro'=>(string)($p['intro']??'')];
    $yaml="---\n";foreach($data as $k=>$v){if(is_bool($v))$yaml.=$k.': '.($v?'true':'false')."\n";else $yaml.=$k.': '.json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}return $yaml."---\n\n";
}
function rrw_content_markdown_body(array $p): string {
    $out='';foreach(array_merge((array)($p['blocks_before']??[]),(array)($p['blocks_after']??[])) as $b){if(empty($b['enabled']))continue;$t=$b['type']??'text';if($t==='heading')$out.=str_repeat('#',max(2,min(4,(int)($b['level']??2)))).' '.($b['text']??'')."\n\n";elseif($t==='text')$out.=($b['text']??'')."\n\n";elseif($t==='quote')$out.='> '.str_replace("\n","\n> ",(string)($b['text']??''))."\n\n";elseif($t==='divider')$out.="---\n\n";elseif($t==='html')$out.=($b['html']??'')."\n\n";}return $out;
}
function rrw_content_sync_from_site(array $site): array {
    $root=rrw_content_root();$written=[];
    foreach((array)($site['pages']??[]) as $p){if(($p['type']??'')!=='custom')continue;$slug=rrw_slug((string)($p['slug']??$p['title']??'seite'));$dir=$root.'/'.$slug;if(!is_dir($dir))@mkdir($dir,0755,true);$file=$dir.'/page.md';rrw_write_atomic($file,rrw_content_frontmatter($p).rrw_content_markdown_body($p));$written[]='cms/content/pages/'.$slug.'/page.md';}
    return $written;
}
function rrw_content_scan(): array {
    $out=[];foreach(glob(rrw_content_root().'/*/page.md')?:[] as $file){$raw=(string)file_get_contents($file);$title='';$slug=basename(dirname($file));if(preg_match('/^---\s*(.*?)\s*---/s',$raw,$m)){foreach(preg_split('/\R/',$m[1]) as $line){if(!str_contains($line,':'))continue;[$k,$v]=array_map('trim',explode(':',$line,2));$v=trim($v," \t\n\r\0\x0B\"'");if($k==='title')$title=$v;if($k==='slug'&&$v!=='')$slug=$v;}}$out[]=['slug'=>$slug,'title'=>$title?:$slug,'file'=>'cms/content/pages/'.basename(dirname($file)).'/page.md','mtime'=>date(DATE_ATOM,filemtime($file)?:time())];}return $out;
}

function rrw_content_parse_frontmatter(string $raw): array {
    $meta=[];$body=$raw;
    if(preg_match('/^---\s*\R(.*?)\R---\s*\R?/s',$raw,$m)){
        $body=substr($raw,strlen($m[0]));
        foreach(preg_split('/\R/',$m[1]) as $line){
            if(!str_contains($line,':'))continue;
            [$k,$v]=array_map('trim',explode(':',$line,2));
            if($k==='')continue;
            if($v==='true'||$v==='false')$meta[$k]=$v==='true';
            else {
                $j=json_decode($v,true);
                $meta[$k]=$j===null&&strtolower($v)!=='null'?trim($v," \t\n\r\0\x0B\"'"):$j;
            }
        }
    }
    return ['meta'=>$meta,'body'=>$body];
}
function rrw_content_markdown_blocks(string $body): array {
    $body=str_replace(["\r\n","\r"],"\n",$body);$lines=explode("\n",$body);$blocks=[];$paragraph=[];
    $flush=function() use (&$paragraph,&$blocks){
        $text=trim(implode("\n",$paragraph));$paragraph=[];
        if($text!=='')$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'text','enabled'=>true,'text'=>$text];
    };
    foreach($lines as $line){
        if(preg_match('/^(#{2,4})\s+(.+)$/',$line,$m)){
            $flush();$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'heading','enabled'=>true,'level'=>strlen($m[1]),'text'=>trim($m[2])];continue;
        }
        if(preg_match('/^>\s?(.*)$/',$line,$m)){
            $flush();$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'quote','enabled'=>true,'text'=>trim($m[1])];continue;
        }
        if(trim($line)==='---'){$flush();$blocks[]=['id'=>'md_'.bin2hex(random_bytes(4)),'type'=>'divider','enabled'=>true];continue;}
        if(trim($line)===''){$flush();continue;}
        $paragraph[]=$line;
    }
    $flush();return $blocks;
}
function rrw_content_import_to_site(array $site,?string $onlySlug=null): array {
    $pages=is_array($site['pages']??null)?$site['pages']:[];
    $imported=[];
    foreach(glob(rrw_content_root().'/*/page.md')?:[] as $file){
        $folder=basename(dirname($file));if($onlySlug!==null&&$onlySlug!==''&&$folder!==$onlySlug)continue;
        $parsed=rrw_content_parse_frontmatter((string)file_get_contents($file));$m=$parsed['meta'];
        $slug=rrw_slug((string)($m['slug']??$folder));$title=trim((string)($m['title']??$slug));if($title==='')$title=$slug;
        $idx=null;foreach($pages as $i=>$p)if(($p['type']??'')==='custom'&&($p['slug']??'')===$slug){$idx=$i;break;}
        $page=$idx!==null?$pages[$idx]:['id'=>'page_'.bin2hex(random_bytes(5)),'type'=>'custom','system_target'=>'','native_enabled'=>false,'text_overrides'=>[],'blocks_after'=>[]];
        $page['slug']=$slug;$page['title']=$title;$page['enabled']=!array_key_exists('enabled',$m)||!empty($m['enabled']);
        $page['headline']=trim((string)($m['headline']??$page['headline']??''));$page['intro']=trim((string)($m['intro']??$page['intro']??''));
        $page['blocks_before']=rrw_content_markdown_blocks((string)$parsed['body']);
        if($idx!==null)$pages[$idx]=$page;else $pages[]=$page;
        $imported[]=['slug'=>$slug,'title'=>$title,'file'=>'cms/content/pages/'.$folder.'/page.md'];
    }
    $site['pages']=$pages;return ['site'=>$site,'imported'=>$imported];
}
