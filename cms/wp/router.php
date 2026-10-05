<?php
// Anfrage → WP_Query → Vorlage (Template-Hierarchie von WordPress) → HTML. Aufruf über cms/wp-front.php oder direkt in Tests:
//   $r = rrw_wp_dispatch('/kategorie/…/', 'GET', $query, $post);  →  ['status'=>200,'headers'=>[…],'body'=>'<html>…']
// Die Laufzeit (cms/wp/load.php) muss mit rrw_wp_boot(['theme'=>true]) gestartet sein.

function rrw_wp_request_state(string $path, array $query): void { $GLOBALS['rrw_wp_request']=['path'=>$path,'query'=>$query]; }

/** Ist die Seite die „Beitragsseite“ (Einstellungen → Lesen, bei statischer Startseite)? Dort steht die Beitragsübersicht. */
function rrw_wp_is_posts_page(int $id): bool { return $id>0&&get_option('show_on_front')==='page'&&(int)get_option('page_for_posts')===$id&&(int)get_option('page_on_front')!==$id; }

/** Pfad und Abfrage in WP_Query-Variablen übersetzen. @return array{0:array,1:array} [vars, info] */
function rrw_wp_route(string $path, array $q): array {
    $path='/'.trim($path,'/');$vars=[];$info=['type'=>'home'];
    // /page/N/ am Ende
    if(preg_match('#^(.*)/page/(\d+)$#',$path,$m)){ $path=$m[1]===''?'/':$m[1];$vars['paged']=max(1,(int)$m[2]); }
    if(isset($q['paged']))$vars['paged']=max(1,(int)$q['paged']);
    $seg=array_values(array_filter(explode('/',trim($path,'/')),fn($s)=>$s!==''));
    $seg=array_map('rawurldecode',$seg);
    // explizite Abfrage-Parameter
    if(isset($q['s'])){ $vars['s']=(string)$q['s'];$info['type']='search';return [$vars,$info]; }
    if(!empty($q['p'])){ $vars['p']=(int)$q['p'];$vars['post_type']=!empty($q['post_type'])?(string)$q['post_type']:'any';$info['type']='single';return [$vars,$info]; }
    if(!empty($q['page_id'])){ if(rrw_wp_is_posts_page((int)$q['page_id'])){ $vars['post_type']='post';$info=['type'=>'posts_page','page'=>(int)$q['page_id']];return [$vars,$info]; }$vars['page_id']=(int)$q['page_id'];$vars['post_type']='page';$info['type']='page';return [$vars,$info]; }
    if(!empty($q['cat'])&&ctype_digit((string)$q['cat'])){ $t=get_term((int)$q['cat'],'category');if($t&&!is_wp_error($t)){ $vars['category_name']=$t->slug;$vars['post_type']='post';$info=['type'=>'category','slug'=>$t->slug];return [$vars,$info]; } }
    if(!empty($q['tag'])&&is_string($q['tag'])){ $vars['tag']=$q['tag'];$vars['post_type']='post';$info=['type'=>'tag','slug'=>$q['tag']];return [$vars,$info]; }
    if(!empty($q['author'])&&ctype_digit((string)$q['author'])){ $u=get_userdata((int)$q['author']);if($u){ $vars['author']=(int)$u->ID;$vars['post_type']='post';$info=['type'=>'author','login'=>sanitize_title((string)$u->user_login)];return [$vars,$info]; } }
    if(!empty($q['attachment_id'])){ $vars['p']=(int)$q['attachment_id'];$vars['post_type']='attachment';$info['type']='attachment';return [$vars,$info]; }
    if(!$seg){
        $info['type']='home';
        if(get_option('show_on_front')==='page'&&(int)get_option('page_on_front')){ $vars['page_id']=(int)get_option('page_on_front');$vars['post_type']='page';$info['type']='front_page'; }
        else $vars['post_type']='post';
        return [$vars,$info];
    }
    $first=$seg[0];
    // Beitrag über die eingestellte Link-Struktur (z. B. /2026/10/beitragsname/ oder /blog/beitragsname/)
    if(($sm=rrw_wp_structure_match($path))!==null){
        $post=null;
        if(isset($sm['post_id'])){ $c=get_post((int)$sm['post_id']);if($c&&$c->post_type==='post'&&$c->post_status==='publish')$post=$c; }
        elseif(isset($sm['postname'])){ $c=get_posts(['name'=>$sm['postname'],'post_type'=>'post','post_status'=>'publish','numberposts'=>5,'suppress_filters'=>true]);
            foreach($c as $x){ $t=strtotime((string)$x->post_date);$okd=true;foreach(['year'=>'Y','monthnum'=>'n','day'=>'j'] as $k=>$f)if(isset($sm[$k])&&(int)$sm[$k]!==(int)date($f,$t))$okd=false;if($okd){ $post=$x;break; } } }
        if($post){ $vars['p']=(int)$post->ID;$vars['post_type']='post';$info['type']='single';return [$vars,$info]; }
    }
    $catBase=trim((string)get_option('category_base',''),'/')?:'category';$tagBase=trim((string)get_option('tag_base',''),'/')?:'tag';
    if($first!=='category'&&$catBase!=='category'&&$first===explode('/',$catBase)[0]&&str_starts_with(implode('/',$seg).'/',$catBase.'/')){ $seg=array_merge(['category'],array_slice($seg,count(explode('/',$catBase))));$first='category'; }
    if($first!=='tag'&&$tagBase!=='tag'&&$first===explode('/',$tagBase)[0]&&str_starts_with(implode('/',$seg).'/',$tagBase.'/')){ $seg=array_merge(['tag'],array_slice($seg,count(explode('/',$tagBase))));$first='tag'; }
    if($first==='category'&&isset($seg[1])){ $vars['category_name']=end($seg);$vars['post_type']='post';$info=['type'=>'category','slug'=>end($seg)];return [$vars,$info]; }
    if($first==='tag'&&isset($seg[1])){ $vars['tag']=$seg[1];$vars['post_type']='post';$info=['type'=>'tag','slug'=>$seg[1]];return [$vars,$info]; }
    if($first==='author'&&isset($seg[1])){ $u=get_user_by('slug',$seg[1]);$vars['author']=$u?(int)$u->ID:-1;$vars['post_type']='post';$info=['type'=>'author','login'=>$seg[1]];return [$vars,$info]; }
    if($first==='search'&&isset($seg[1])){ $vars['s']=$seg[1];$info['type']='search';return [$vars,$info]; }
    if(preg_match('/^\d{4}$/',$first)){
        $vars['year']=(int)$first;if(isset($seg[1])&&preg_match('/^\d{1,2}$/',$seg[1]))$vars['monthnum']=(int)$seg[1];if(isset($seg[2])&&preg_match('/^\d{1,2}$/',$seg[2]))$vars['day']=(int)$seg[2];
        if(count($seg)<=3&&(count($seg)===1||isset($vars['monthnum']))){ $vars['post_type']='post';$info['type']='date';return [$vars,$info]; }
        unset($vars['year'],$vars['monthnum'],$vars['day']);
    }
    // eigene Taxonomien: /<taxonomie>/<begriff>/
    foreach(get_taxonomies(['_builtin'=>false],'objects') as $tx){
        $base=is_array($tx->rewrite)&&!empty($tx->rewrite['slug'])?$tx->rewrite['slug']:$tx->name;
        if($tx->public&&$first===$base&&isset($seg[1])){ $vars['taxonomy']=$tx->name;$vars['term']=end($seg);$vars['post_type']=$tx->object_type?:'any';$info=['type'=>'tax','taxonomy'=>$tx->name,'slug'=>end($seg)];return [$vars,$info]; }
    }
    // eigene Beitragstypen: /<typ>/ (Archiv) und /<typ>/<slug>/
    foreach(get_post_types(['_builtin'=>false,'public'=>true],'objects') as $pt){
        $base=is_array($pt->rewrite)&&!empty($pt->rewrite['slug'])?$pt->rewrite['slug']:$pt->name;
        if($first===$base){ if(isset($seg[1])){ $vars['post_type']=$pt->name;$vars['name']=end($seg);$info=['type'=>'single','post_type'=>$pt->name];return [$vars,$info]; }
            if($pt->has_archive){ $vars['post_type']=$pt->name;$info=['type'=>'post_type_archive','post_type'=>$pt->name];return [$vars,$info]; } }
    }
    // Seite (auch verschachtelt) oder Beitrag über den Slug
    $seg[count($seg)-1]=preg_replace('/\.html$/','',(string)end($seg));
    $slugPath=implode('/',$seg);$last=end($seg);
    $page=get_page_by_path($slugPath,OBJECT,'page');
    if($page){ if(rrw_wp_is_posts_page((int)$page->ID)){ $vars['post_type']='post';$info=['type'=>'posts_page','page'=>(int)$page->ID];return [$vars,$info]; }$vars['page_id']=(int)$page->ID;$vars['post_type']='page';$info['type']='page';return [$vars,$info]; }
    if(count($seg)===1||preg_match('#^\d{4}/\d{2}/#',$slugPath)){
        $post=get_posts(['name'=>$last,'post_type'=>'post','post_status'=>'publish','numberposts'=>1,'suppress_filters'=>true]);
        if($post){ $vars['p']=(int)$post[0]->ID;$vars['post_type']='post';$info['type']='single';return [$vars,$info]; }
    }
    $info['type']='404';return [$vars,$info];
}

/** Abfrage ausführen und globale Variablen setzen. */
function rrw_wp_setup_query(string $path, array $query): WP_Query {
    rrw_wp_request_state($path,$query);
    [$vars,$info]=rrw_wp_route($path,$query);
    $q=new WP_Query();$GLOBALS['wp_the_query']=$GLOBALS['wp_query']=$q;
    if($info['type']==='404'){ $q->init();$q->set_404();$q->query_vars=$q->fill_query_vars([]);$q->posts=[];return $q; }
    $q->query($vars);
    switch($info['type']){
        case 'category': $q->is_category=true;$q->is_archive=true;$q->is_home=false;$t=get_term_by('slug',$info['slug'],'category');if(!$t){ $q->set_404();return $q; }$q->queried_object=$t;$q->queried_object_id=(int)$t->term_id;break;
        case 'tag': $q->is_tag=true;$q->is_archive=true;$q->is_home=false;$t=get_term_by('slug',$info['slug'],'post_tag');if(!$t){ $q->set_404();return $q; }$q->queried_object=$t;$q->queried_object_id=(int)$t->term_id;break;
        case 'tax': $q->is_tax=true;$q->is_archive=true;$q->is_home=false;$t=get_term_by('slug',$info['slug'],$info['taxonomy']);if(!$t){ $q->set_404();return $q; }$q->queried_object=$t;$q->queried_object_id=(int)$t->term_id;break;
        case 'author': $q->is_author=true;$q->is_archive=true;$q->is_home=false;$u=get_user_by('slug',$info['login']);if(!$u){ $q->set_404();return $q; }$q->queried_object=$u;$q->queried_object_id=(int)$u->ID;break;
        case 'date': $q->is_date=true;$q->is_archive=true;$q->is_home=false;$q->is_year=isset($vars['year']);$q->is_month=isset($vars['monthnum']);$q->is_day=isset($vars['day']);break;
        case 'post_type_archive': $q->is_post_type_archive=true;$q->is_archive=true;$q->is_home=false;break;
        case 'search': $q->is_search=true;$q->is_home=false;$q->is_archive=false;break;
        case 'single': case 'page': case 'front_page': case 'attachment':
            if($q->post_count<1){ $q->set_404();return $q; }
            $o=$q->posts[0];$q->queried_object=$o;$q->queried_object_id=(int)$o->ID;$q->is_home=false;$q->is_singular=true;
            if($o->post_type==='page'){ $q->is_page=true;$q->is_single=false; }elseif($o->post_type==='attachment'){ $q->is_attachment=true; }else{ $q->is_single=true;$q->is_page=false; }
            if($info['type']==='front_page'){ $q->is_front_page=true; }
            break;
        case 'posts_page': $pg=get_post((int)$info['page']);$q->is_home=true;$q->is_front_page=false;$q->is_archive=false;$q->is_singular=false;if($pg){ $q->queried_object=$pg;$q->queried_object_id=(int)$pg->ID; }break;
        case 'home':
            $q->is_home=true;$q->is_front_page=true;$q->is_archive=false;break;
    }
    if($info['type']==='front_page')$q->is_front_page=true;
    if($info['type']==='home')$q->is_front_page=true;
    $GLOBALS['rrw_wp_route_info']=$info;
    if(!empty($vars['paged'])&&$q->max_num_pages&&$vars['paged']>$q->max_num_pages&&!$q->is_singular){ $q->set_404(); }
    do_action('wp');
    return $q;
}

/** Vorlagen-Kandidaten in der Reihenfolge der WordPress-Template-Hierarchie. */
function rrw_wp_template_candidates(WP_Query $q): array {
    $c=[];
    if($q->is_404)return ['404.php','index.php'];
    if($q->is_search)return ['search.php','index.php'];
    if($q->is_front_page&&!$q->is_singular)$c[]='front-page.php';
    elseif($q->is_front_page&&$q->is_singular)$c[]='front-page.php';
    if($q->is_singular){
        $o=$q->queried_object;
        if($o instanceof WP_Post){
            $tpl=get_page_template_slug($o);if($tpl)$c[]=$tpl;
            if($o->post_type==='page'){ $c[]='page-'.$o->post_name.'.php';$c[]='page-'.$o->ID.'.php';$c[]='page.php'; }
            elseif($o->post_type==='attachment'){ $c[]='attachment.php'; }
            else{ $c[]='single-'.$o->post_type.'-'.$o->post_name.'.php';$c[]='single-'.$o->post_type.'.php';$c[]='single.php'; }
        }
        $c[]='singular.php';
    }
    elseif($q->is_category){ $o=$q->queried_object;if($o){ $c[]='category-'.$o->slug.'.php';$c[]='category-'.$o->term_id.'.php'; }$c[]='category.php';$c[]='archive.php'; }
    elseif($q->is_tag){ $o=$q->queried_object;if($o){ $c[]='tag-'.$o->slug.'.php';$c[]='tag-'.$o->term_id.'.php'; }$c[]='tag.php';$c[]='archive.php'; }
    elseif($q->is_tax){ $o=$q->queried_object;if($o){ $c[]='taxonomy-'.$o->taxonomy.'-'.$o->slug.'.php';$c[]='taxonomy-'.$o->taxonomy.'.php'; }$c[]='taxonomy.php';$c[]='archive.php'; }
    elseif($q->is_author){ $o=$q->queried_object;if($o){ $c[]='author-'.$o->user_nicename.'.php';$c[]='author-'.$o->ID.'.php'; }$c[]='author.php';$c[]='archive.php'; }
    elseif($q->is_date){ if($q->is_day)$c[]='day.php';elseif($q->is_month)$c[]='month.php';elseif($q->is_year)$c[]='year.php';$c[]='date.php';$c[]='archive.php'; }
    elseif($q->is_post_type_archive){ $pt=$q->get('post_type');$pt=is_array($pt)?reset($pt):$pt;$c[]='archive-'.$pt.'.php';$c[]='archive.php'; }
    elseif($q->is_home){ $c[]='home.php'; }
    $c[]='index.php';
    return apply_filters('rrw_wp_template_candidates',array_values(array_unique($c)));
}
function rrw_wp_find_template(WP_Query $q): string {
    $t=locate_template(rrw_wp_template_candidates($q));
    if($t==='')$t=RRW_WP_NATIVE_THEMES.'/rrw-classic/index.php';
    return (string)apply_filters('template_include',$t);
}

/**
 * Eine Anfrage bedienen. @return array{status:int,headers:array,body:string}
 * Fehler im Theme/Plugin führen nie zu einer weißen Seite: es erscheint eine kurze Fehlerseite (Details im Protokoll).
 */
function rrw_wp_dispatch(string $uri, string $method='GET', array $query=[], array $post=[]): array {
    $path=(string)parse_url($uri,PHP_URL_PATH);if($path===''||$path==='/index.php'||$path==='/index.html')$path='/';
    if(!$query){ parse_str((string)parse_url($uri,PHP_URL_QUERY),$query); }
    $status=200;$headers=['Content-Type'=>'text/html; charset=utf-8'];
    // Sonderpfade
    if($method==='POST'&&rtrim($path,'/')==='/wp-comments-post.php'){
        $r=rrw_wp_submit_comment($post);
        if(is_wp_error($r))return ['status'=>400,'headers'=>$headers,'body'=>'<!doctype html><meta charset="utf-8"><body style="font:16px system-ui;max-width:600px;margin:10vh auto"><p>'.esc_html($r->get_error_message()).'</p><p><a href="javascript:history.back()">Zurück</a></p>'];
        $p=rrw_wp_cms_find_post((int)($post['comment_post_ID']??0));$back=$p?get_permalink($p):home_url('/');
        return ['status'=>303,'headers'=>['Location'=>$back.(!empty($r['pending'])?'?comment=pending':'').'#comments'],'body'=>''];
    }
    if(preg_match('#^/(feed|rss)(/|\.xml)?$#',$path)||$path==='/rss.xml')return ['status'=>302,'headers'=>['Location'=>home_url('/rss.xml')],'body'=>''];
    // REST-API (/wp-json/… oder ?rest_route=/…)
    if(preg_match('#^/wp-json(/.*)?$#',$path,$rm)||(isset($query['rest_route'])&&is_string($query['rest_route']))){
        require_once __DIR__.'/rest.php';
        $route=isset($rm[0])?($rm[1]??'/'):(string)$query['rest_route'];unset($query['rest_route']);
        if($method==='OPTIONS')return ['status'=>204,'headers'=>['Allow'=>'GET, POST, PUT, PATCH, DELETE'],'body'=>''];
        // Cookie-Sitzung zählt bei REST nur mit gültigem Nonce (wie in WordPress)
        if(!empty($GLOBALS['rrw_wp_sess_user'])&&!rrw_wp_sess_rest_nonce_ok((array)($GLOBALS['rrw_wp_req_headers']??[]),$query)){ unset($GLOBALS['rrw_wp_user']);$GLOBALS['rrw_wp_sess_user']=false; }
        $GLOBALS['rrw_wp_die_throws']=true;$GLOBALS['rrw_wp_serving_rest']=true;if($post){ $_POST=wp_slash($post);$_REQUEST=array_merge((array)$_REQUEST,$_POST); }
        try{ return rrw_wp_rest_dispatch($method,$route,$query,(string)($GLOBALS['rrw_wp_raw_body']??''),(array)($GLOBALS['rrw_wp_req_headers']??[])); } finally { $GLOBALS['rrw_wp_serving_rest']=false; }
    }
    // admin-ajax.php / admin-post.php für Besucher (wp_ajax_nopriv_* bzw. admin_post_nopriv_*)
    if(preg_match('#^/wp-admin/(admin-ajax|admin-post)\.php$#',$path,$am)){
        require_once __DIR__.'/admin.php';
        if($am[1]==='admin-ajax'){
            $raw=$method==='POST'?($post?http_build_query($post):(string)($GLOBALS['rrw_wp_raw_body']??'')):'';
            $r=rrw_wp_ajax($method,http_build_query($query),$raw,!empty($GLOBALS['rrw_wp_sess_user']));
            return ['status'=>$r['status'],'headers'=>['Content-Type'=>$r['type'].'; charset=utf-8','X-Robots-Tag'=>'noindex'],'body'=>$r['text']];
        }
        $in=array_merge($query,$post);$act=preg_replace('/[^A-Za-z0-9_\-]/','',(string)($in['action']??''));
        if($act===''||!has_action('admin_post_nopriv_'.$act))return ['status'=>400,'headers'=>$headers,'body'=>'Ungültige Anfrage'];
        $_GET=wp_slash($query);$_POST=wp_slash($post);$_REQUEST=array_merge($_GET,$_POST);
        rrw_wp_capture_redirects();$GLOBALS['rrw_wp_admin_redirect']='';
        $GLOBALS['rrw_wp_die_throws']=true;$lv=ob_get_level();ob_start();
        try{ do_action('admin_post_nopriv_'.$act);$out=(string)ob_get_clean(); }
        catch(RRW_WP_Die $d){ $out=(string)ob_get_clean(); }
        catch(Throwable $e){ while(ob_get_level()>$lv)ob_end_clean();rrw_wp_log('admin-post '.$act.': '.$e->getMessage());return ['status'=>500,'headers'=>$headers,'body'=>'Fehler']; }
        $red=(string)$GLOBALS['rrw_wp_admin_redirect'];
        return $red!==''?['status'=>302,'headers'=>['Location'=>$red],'body'=>'']:['status'=>200,'headers'=>$headers,'body'=>$out];
    }
    if(preg_match('#^/wp-includes/css/dist/block-library/(style|theme)\.min\.css$#',$path,$bm)){
        $f=__DIR__.'/assets/block-library'.($bm[1]==='theme'?'-theme':'').'.css';
        return ['status'=>200,'headers'=>['Content-Type'=>'text/css; charset=utf-8','Cache-Control'=>'public, max-age=86400'],'body'=>(string)@file_get_contents($f)];
    }
    // Native Verwaltungsseiten im eigenen Tab (nur mit Sitzung): post.php?action=…, admin.php?page=…
    if(!empty($GLOBALS['rrw_wp_sess_user'])&&preg_match('#^/wp-admin/(post|post-new|admin|index|edit)\.php$#',$path,$nm))return rrw_wp_admin_native($nm[1].'.php',$query,$post,$method);
    if(preg_match('#^/(wp-login\.php|wp-admin)#',$path))return ['status'=>302,'headers'=>['Location'=>home_url('/cms/')],'body'=>''];
    // Angemeldete Redakteure: Kern-Skripte (backbone, wp-i18n …) wie bei WordPress, damit Editor-Vorschauen (Elementor) laufen
    if(!empty($GLOBALS['rrw_wp_sess_user'])){ require_once __DIR__.'/coreassets.php';rrw_wp_core_register(); }
    $level=ob_get_level();ob_start();$GLOBALS['rrw_wp_die_throws']=true;$GLOBALS['rrw_wp_tpl_loaded']=[];foreach(['rrw_wp_scripts','rrw_wp_styles'] as $_k)$GLOBALS[$_k]['done']=[];
    try{
        $q=rrw_wp_setup_query($path,$query);
        if($q->is_404)$status=(int)apply_filters('rrw_wp_404_status',404,$path);   // Themes mit eigenen virtuellen Seiten (z. B. /links/) können hier 200 melden
        // Wie WP::register_globals: bei Einzelseiten ist der Beitrag schon vor template_redirect der aktuelle (Elementor prüft das)
        if($q->is_singular&&!empty($q->posts[0])){ $GLOBALS['post']=$q->posts[0]; }
        do_action('template_redirect');
        if(wp_is_block_theme()){
            if($q->is_singular&&$q->have_posts())$q->the_post();
            rrw_wp_render_block_template($q);
        } else {
            $tpl=rrw_wp_find_template($q);
            load_template($tpl,false);
        }
        $body=ob_get_clean();
    }catch(RRW_WP_Die $d){
        $body=ob_get_level()>$level?(string)ob_get_clean():'';while(ob_get_level()>$level)ob_end_clean();$status=$d->getCode()?:200;
    }catch(Throwable $e){
        while(ob_get_level()>$level)ob_end_clean();
        rrw_wp_log('Seite '.$path.': '.get_class($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
        $status=500;$body='<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fehler</title><body style="font:16px system-ui;max-width:640px;margin:12vh auto;padding:0 20px"><h1>Die Seite konnte nicht erstellt werden</h1><p>Das aktive Theme oder ein Plugin hat einen Fehler verursacht. Die Administration findet Details im Protokoll (<code>cms/data/.wp/debug.log</code>) und kann im CMS ein anderes Theme aktivieren.</p></body></html>';
    }
    return ['status'=>$status,'headers'=>$headers,'body'=>$body];
}

/** Verwaltungsseite im Vollbild (eigener Tab, gleiche Herkunft wie die Website): Aktionen von Plugins wie Elementor laufen hier. */
function rrw_wp_admin_native(string $file, array $query, array $post, string $method): array {
    require_once __DIR__.'/admin.php';rrw_wp_admin_init();
    require_once __DIR__.'/coreassets.php';rrw_wp_core_register();
    $h=['Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-store','X-Robots-Tag'=>'noindex'];
    $GLOBALS['pagenow']=$file;$_GET=wp_slash($query);$_POST=wp_slash($post);$_REQUEST=array_merge($_GET,$_POST);$_SERVER['REQUEST_METHOD']=$method;
    $GLOBALS['rrw_wp_die_throws']=true;$GLOBALS['rrw_wp_native_admin']=true;$GLOBALS['rrw_wp_admin_redirect']='';
    $lv=ob_get_level();ob_start();
    try{
        if($file==='admin.php'&&!empty($query['page'])){
            ob_end_clean();
            $r=rrw_wp_admin_page(['page'=>(string)$query['page'],'method'=>$method,'body'=>http_build_query($post),'query'=>http_build_query(array_diff_key($query,['page'=>1]))]);
            if(!empty($r['redirect']))return ['status'=>302,'headers'=>['Location'=>(string)$r['redirect']],'body'=>''];
            return ['status'=>!empty($r['ok'])?200:404,'headers'=>$h,'body'=>(string)($r['html']??'Seite nicht gefunden')];
        }
        $action=preg_replace('/[^A-Za-z0-9_\-]/','',(string)($query['action']??($post['action']??($file==='post-new.php'?'':''))));
        if($action===''||!has_action('admin_action_'.$action)){ ob_end_clean();return ['status'=>302,'headers'=>['Location'=>home_url('/cms/')],'body'=>'']; }
        do_action('admin_action_'.$action);
        $out=(string)ob_get_clean();
    }catch(RRW_WP_Die $d){ $out=ob_get_level()>$lv?(string)ob_get_clean():'';while(ob_get_level()>$lv)ob_end_clean();if($out==='')$out=$d->getMessage(); }
    catch(Throwable $e){ while(ob_get_level()>$lv)ob_end_clean();rrw_wp_log('Admin '.$file.': '.get_class($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');return ['status'=>500,'headers'=>$h,'body'=>'Fehler: '.esc_html($e->getMessage())]; }
    if(($red=(string)$GLOBALS['rrw_wp_admin_redirect'])!=='')return ['status'=>302,'headers'=>['Location'=>$red],'body'=>''];
    return ['status'=>200,'headers'=>$h,'body'=>$out];
}
