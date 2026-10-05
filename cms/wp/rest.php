<?php
// REST-API unter /wp-json/: Plugin-Routen (register_rest_route) und die wichtigsten Kernrouten (wp/v2/posts, pages, categories, tags).
// Besucher gelten als nicht angemeldet; Routen mit permission_callback, der Rechte verlangt, antworten 401/403.

function rrw_wp_rest_core_routes(): void {
    $pub=['permission_callback'=>'__return_true'];
    $list=function(string $type){
        return function(WP_REST_Request $r) use($type){
            $per=max(1,min(100,(int)($r->get_param('per_page')?:10)));$page=max(1,(int)($r->get_param('page')?:1));
            $args=['post_type'=>$type,'post_status'=>'publish','posts_per_page'=>$per,'paged'=>$page,'orderby'=>'date','order'=>strtolower((string)$r->get_param('order'))==='asc'?'ASC':'DESC'];
            if($s=(string)$r->get_param('search'))$args['s']=$s;
            if($sl=(string)$r->get_param('slug'))$args['name']=$sl;
            $q=new WP_Query($args);$out=array_map('rrw_wp_rest_post',$q->posts);
            $res=new WP_REST_Response($out,200);$res->header('X-WP-Total',(string)$q->found_posts);$res->header('X-WP-TotalPages',(string)max(1,(int)$q->max_num_pages));
            return $res;
        };
    };
    $one=function(string $type){
        return function(WP_REST_Request $r) use($type){
            $p=get_post((int)$r->get_param('id'));
            if(!$p||$p->post_type!==$type||$p->post_status!=='publish')return new WP_Error('rest_post_invalid_id','Ungültige Beitrags-ID.',['status'=>404]);
            return rrw_wp_rest_post($p);
        };
    };
    foreach(['posts'=>'post','pages'=>'page'] as $base=>$type){
        register_rest_route('wp/v2','/'.$base,['methods'=>'GET','callback'=>$list($type)]+$pub);
        register_rest_route('wp/v2','/'.$base.'/(?P<id>[\d]+)',['methods'=>'GET','callback'=>$one($type)]+$pub);
    }
    // Aktueller Benutzer und Einstellungen (von React-Verwaltungsseiten genutzt; Zugriff nur für angemeldete Administration)
    register_rest_route('wp/v2','/users/me',['methods'=>'GET','callback'=>function(WP_REST_Request $r){ return (new WP_REST_Users_Controller())->get_current_item($r); },'permission_callback'=>fn()=>is_user_logged_in()]);
    $settingsGet=function(){
        $o=['title'=>get_option('blogname'),'description'=>get_option('blogdescription'),'url'=>home_url(),'email'=>get_option('admin_email'),'timezone'=>(string)get_option('timezone_string',''),'date_format'=>get_option('date_format','j. F Y'),'time_format'=>get_option('time_format','H:i'),'start_of_week'=>(int)get_option('start_of_week',1),'language'=>get_locale(),'use_smilies'=>false,'default_category'=>1,'default_post_format'=>'0','posts_per_page'=>(int)get_option('posts_per_page',10),'default_ping_status'=>'closed','default_comment_status'=>get_option('default_comment_status','open')];
        foreach((array)get_registered_settings() as $n=>$a){ if(!empty($a['show_in_rest']))$o[is_array($a['show_in_rest'])&&!empty($a['show_in_rest']['name'])?$a['show_in_rest']['name']:$n]=get_option($n,$a['default']??null); }
        return $o;
    };
    register_rest_route('wp/v2','/settings',['methods'=>'GET,POST,PUT,PATCH','callback'=>function(WP_REST_Request $r) use($settingsGet){
        if(in_array($r->get_method(),['POST','PUT','PATCH'],true)){
            $map=['title'=>'blogname','description'=>'blogdescription','email'=>'admin_email','timezone'=>'timezone_string','date_format'=>'date_format','time_format'=>'time_format','start_of_week'=>'start_of_week','posts_per_page'=>'posts_per_page','default_comment_status'=>'default_comment_status'];
            foreach((array)get_registered_settings() as $n=>$a)if(!empty($a['show_in_rest']))$map[is_array($a['show_in_rest'])&&!empty($a['show_in_rest']['name'])?$a['show_in_rest']['name']:$n]=$n;
            foreach($map as $k=>$opt)if($r->has_param($k))update_option($opt,$r->get_param($k));
        }
        return $settingsGet();
    },'permission_callback'=>fn()=>current_user_can('manage_options')]);
    foreach(['categories'=>'category','tags'=>'post_tag'] as $base=>$tax){
        register_rest_route('wp/v2','/'.$base,['methods'=>'GET','callback'=>function(WP_REST_Request $r) use($tax){
            $terms=get_terms(['taxonomy'=>$tax,'hide_empty'=>false]);if(is_wp_error($terms))return [];
            return array_map(fn($t)=>['id'=>(int)$t->term_id,'count'=>(int)$t->count,'description'=>(string)$t->description,'link'=>get_term_link($t),'name'=>$t->name,'slug'=>$t->slug,'taxonomy'=>$tax],$terms);
        }]+$pub);
    }
    if(function_exists('rrw_wp_bridge_parts')&&rrw_wp_bridge_parts())rrw_wp_rest_bridge_routes();
}
/**
 * Schreibende Routen (nur mit eingeschalteter Schreibbrücke): Beiträge/Seiten anlegen, ändern, löschen; Medien lesen und hochladen.
 * Die Daten landen über wp_insert_post & Co. in den CMS-Dateien bzw. der Medienbibliothek.
 */
function rrw_wp_rest_bridge_routes(): void {
    $fields=function(WP_REST_Request $r,string $type): array {
        $a=['post_type'=>$type];
        foreach(['title'=>'post_title','content'=>'post_content','excerpt'=>'post_excerpt','status'=>'post_status','slug'=>'post_name','date'=>'post_date','author'=>'post_author'] as $k=>$f){
            if(!$r->has_param($k))continue;$v=$r->get_param($k);if(is_array($v))$v=$v['raw']??($v['rendered']??'');
            $a[$f]=$k==='date'?str_replace('T',' ',(string)$v):$v;
        }
        if($r->has_param('categories'))$a['post_category']=array_map('intval',(array)$r->get_param('categories'));
        if($r->has_param('tags'))$a['tags_input']=array_map('intval',(array)$r->get_param('tags'));
        if($r->has_param('featured_media'))$a['meta_input']=['_thumbnail_id'=>(int)$r->get_param('featured_media')];
        return $a;
    };
    $notFound=fn()=>new WP_Error('rest_post_invalid_id','Ungültige Beitrags-ID.',['status'=>404]);
    foreach(['posts'=>['post','posts','edit_posts'],'pages'=>['page','pages','edit_pages']] as $base=>[$type,,$cap]){
        $perm=fn()=>current_user_can($cap);
        register_rest_route('wp/v2','/'.$base,['methods'=>'POST','permission_callback'=>$perm,'callback'=>function(WP_REST_Request $r) use($fields,$type){
            $a=$fields($r,$type);   // neue Beiträge → news.json, neue Seiten → Tabelle wp_posts
            $id=wp_insert_post($a,true);if(is_wp_error($id))return new WP_Error($id->get_error_code(),$id->get_error_message(),['status'=>400]);
            return new WP_REST_Response(rrw_wp_rest_post(get_post($id)),201);
        }]);
        register_rest_route('wp/v2','/'.$base.'/(?P<id>[\d]+)',['methods'=>'POST,PUT,PATCH','permission_callback'=>$perm,'callback'=>function(WP_REST_Request $r) use($fields,$type,$notFound){
            $id=(int)$r->get_param('id');$p=get_post($id);if(!$p||$p->post_type!==$type)return $notFound();
            $a=$fields($r,$type)+['ID'=>$id];$res=wp_update_post($a,true);if(is_wp_error($res))return new WP_Error($res->get_error_code(),$res->get_error_message(),['status'=>400]);
            return rrw_wp_rest_post(get_post($id));
        }]);
        register_rest_route('wp/v2','/'.$base.'/(?P<id>[\d]+)',['methods'=>'DELETE','permission_callback'=>fn()=>current_user_can($cap),'callback'=>function(WP_REST_Request $r) use($type,$notFound){
            $id=(int)$r->get_param('id');$p=get_post($id);if(!$p||$p->post_type!==$type)return $notFound();
            $force=in_array($r->get_param('force'),[true,'true','1',1],true);
            if($force){ $prev=rrw_wp_rest_post($p);$ok=wp_delete_post($id,true);if(!$ok)return new WP_Error('rest_cannot_delete','Der Eintrag lässt sich hier nicht endgültig löschen.',['status'=>501]);return ['deleted'=>true,'previous'=>$prev]; }
            $ok=wp_trash_post($id);if(!$ok)return new WP_Error('rest_cannot_delete','Der Eintrag lässt sich hier nicht in den Papierkorb legen.',['status'=>501]);
            return rrw_wp_rest_post(get_post($id));
        }]);
    }
    if(rrw_wp_bridge('media')){
        $mediaPerm=fn()=>current_user_can('upload_files');
        register_rest_route('wp/v2','/media',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(WP_REST_Request $r){
            $per=max(1,min(100,(int)($r->get_param('per_page')?:10)));$page=max(1,(int)($r->get_param('page')?:1));$ids=array_keys(rrw_wp_cms_media_map());
            $res=new WP_REST_Response(array_map('rrw_wp_cms_media_rest',array_slice($ids,($page-1)*$per,$per)),200);$res->header('X-WP-Total',(string)count($ids));$res->header('X-WP-TotalPages',(string)max(1,(int)ceil(count($ids)/$per)));return $res;
        }]);
        register_rest_route('wp/v2','/media/(?P<id>[\d]+)',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(WP_REST_Request $r){
            $id=(int)$r->get_param('id');return rrw_wp_cms_media_post($id)?rrw_wp_cms_media_rest($id):new WP_Error('rest_post_invalid_id','Ungültige Beitrags-ID.',['status'=>404]);
        }]);
        register_rest_route('wp/v2','/media',['methods'=>'POST','permission_callback'=>$mediaPerm,'callback'=>function(WP_REST_Request $r){
            $f=$_FILES['file']??null;if(!is_array($f))return new WP_Error('rest_upload_no_data','Keine Daten übermittelt.',['status'=>400]);
            $id=rrw_wp_cms_media_store($f);if(is_wp_error($id))return $id;
            return new WP_REST_Response(rrw_wp_cms_media_rest($id),201);
        }]);
    }
}
function rrw_wp_rest_post(WP_Post $p): array {
    $old=$GLOBALS['post']??null;$GLOBALS['post']=$p;
    try{
        $content=apply_filters('the_content',$p->post_content);
        $cats=array_map(fn($t)=>(int)$t->term_id,(array)(get_the_category($p->ID)?:[]));
        $tg=get_the_tags($p->ID);$tags=array_map(fn($t)=>(int)$t->term_id,$tg?:[]);
        $d=[
            'id'=>(int)$p->ID,'date'=>str_replace(' ','T',(string)$p->post_date),'date_gmt'=>str_replace(' ','T',(string)$p->post_date_gmt),'guid'=>['rendered'=>get_permalink($p)],
            'modified'=>str_replace(' ','T',(string)$p->post_modified),'slug'=>$p->post_name,'status'=>$p->post_status,'type'=>$p->post_type,'link'=>get_permalink($p),
            'title'=>['rendered'=>get_the_title($p)],'content'=>['rendered'=>$content,'protected'=>false],'excerpt'=>['rendered'=>get_the_excerpt($p),'protected'=>false],
            'author'=>(int)$p->post_author,'featured_media'=>(int)get_post_thumbnail_id($p),'comment_status'=>$p->comment_status,'categories'=>$cats,'tags'=>$tags,
        ];
    } finally { $GLOBALS['post']=$old; }
    return $d;
}

/** Alle Routen mit Endpunkten (rest_api_init wird einmal ausgelöst). */
function rrw_wp_rest_routes(): array {
    static $init=false;
    if(!$init){ $init=true;rrw_wp_rest_core_routes();do_action('rest_api_init'); }
    return $GLOBALS['rrw_wp_rest_routes']??[];
}

function rrw_wp_rest_arg_check(array $argDefs, WP_REST_Request $req): ?WP_Error {
    foreach($argDefs as $name=>$def){
        if(!is_array($def))continue;
        $has=$req->has_param($name);
        if(!$has&&array_key_exists('default',$def)){ $req->set_param($name,$def['default']);$has=true; }
        if(!$has){ if(!empty($def['required']))return new WP_Error('rest_missing_callback_param','Fehlender Parameter: '.$name,['status'=>400]);continue; }
        $v=$req->get_param($name);
        if(isset($def['type'])&&$v!==null){
            $ty=(array)$def['type'];$ok=false;
            foreach($ty as $t)$ok=$ok||match($t){'integer'=>is_numeric($v)&&(string)(int)$v===(string)$v,'number'=>is_numeric($v),'boolean'=>is_bool($v)||in_array($v,['true','false','0','1',0,1],true),'array'=>is_array($v),'object'=>is_array($v)||is_object($v),'string'=>is_scalar($v),default=>true};
            if(!$ok)return new WP_Error('rest_invalid_param','Ungültiger Parameter: '.$name,['status'=>400]);
            if(in_array('integer',$ty,true))$req->set_param($name,(int)$v);
            elseif(in_array('boolean',$ty,true))$req->set_param($name,in_array($v,[true,'true','1',1],true));
        }
        if(isset($def['enum'])&&is_array($def['enum'])&&!in_array($req->get_param($name),$def['enum'],true))return new WP_Error('rest_invalid_param','Ungültiger Wert für '.$name,['status'=>400]);
        if(isset($def['validate_callback'])&&is_callable($def['validate_callback'])){
            $r=call_user_func($def['validate_callback'],$req->get_param($name),$req,$name);
            if(is_wp_error($r))return new WP_Error('rest_invalid_param',$r->get_error_message(),['status'=>400]);
            if($r===false)return new WP_Error('rest_invalid_param','Ungültiger Parameter: '.$name,['status'=>400]);
        }
        if(isset($def['sanitize_callback'])&&is_callable($def['sanitize_callback']))$req->set_param($name,call_user_func($def['sanitize_callback'],$req->get_param($name),$req,$name));
    }
    return null;
}

/**
 * Eine REST-Anfrage ausführen. $route beginnt mit „/“ (z. B. /wp/v2/posts).
 * @return array{status:int,headers:array,body:string}
 */
function rrw_wp_rest_dispatch(string $method, string $route, array $query=[], string $rawBody='', array $headers=[]): array {
    $method=strtoupper($method);$h=['Content-Type'=>'application/json; charset=UTF-8','X-Robots-Tag'=>'noindex','X-Content-Type-Options'=>'nosniff'];
    $json=fn($data,int $st,array $extra=[])=>['status'=>$st,'headers'=>$extra+$h,'body'=>(string)wp_json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
    $route='/'.trim($route,'/');
    $routes=rrw_wp_rest_routes();
    if($route==='/')
        return $json(['name'=>get_bloginfo('name'),'description'=>get_bloginfo('description'),'url'=>home_url(),'home'=>home_url(),'namespaces'=>array_values(array_unique(array_map(fn($k)=>preg_match('#^/([^/]+(?:/v\d+)?)/#',$k.'/',$m)?$m[1]:trim($k,'/'),array_keys($routes)))),'routes'=>(object)array_map(fn($k)=>['namespace'=>explode('/',trim($k,'/'))[0],'_links'=>['self'=>[['href'=>rest_url(ltrim($k,'/'))]]]],array_combine(array_keys($routes),array_keys($routes)))],200);
    // Plugins laden Namespaces erst bei Bedarf (rest_pre_dispatch, z. B. WooCommerce wc-analytics)
    try{ apply_filters('rest_pre_dispatch',null,rest_get_server(),new WP_REST_Request($method,$route));$routes=rrw_wp_rest_routes(); }catch(Throwable $e){ rrw_wp_log('REST pre_dispatch: '.$e->getMessage()); }
    $match=null;$params=[];$allowed=[];
    foreach($routes as $key=>$eps){
        if(!@preg_match('#^'.str_replace('#','\#',$key).'/?$#i',$route,$m))continue;
        foreach($eps as $ep){
            $ms=array_map('trim',explode(',',strtoupper(is_array($ep['methods']??null)?implode(',',array_keys(array_filter($ep['methods']))):(string)($ep['methods']??'GET'))));
            foreach($ms as $mm)$allowed[$mm]=true;
            if(in_array($method,$ms,true)&&!$match){ $match=$ep;foreach($m as $k=>$v)if(is_string($k))$params[$k]=$v; }
        }
        if($match)break;
    }
    if($method==='OPTIONS'&&$allowed){
        // wie rest_send_allow_header(): nur Methoden, für die die Berechtigung vorhanden ist (core-data fragt so ab, was der Benutzer darf)
        $ok=[];
        foreach($routes as $key=>$eps){
            if(!@preg_match('#^'.str_replace('#','\\#',$key).'/?$#i',$route,$m2))continue;
            foreach($eps as $ep){
                $ms=array_map('trim',explode(',',strtoupper(is_array($ep['methods']??null)?implode(',',array_keys(array_filter($ep['methods']))):(string)($ep['methods']??'GET'))));
                $pc=$ep['permission_callback']??null;$pr=new WP_REST_Request($ms[0],$route,$ep);
                try{ $pass=$pc===null||(is_callable($pc)&&call_user_func($pc,$pr)===true); }catch(Throwable $e){ $pass=false; }
                if($pass)foreach($ms as $mm)$ok[$mm]=true;
            }
            break;
        }
        return ['status'=>200,'headers'=>['Allow'=>implode(', ',array_keys($ok)),'Content-Type'=>'application/json; charset=UTF-8'],'body'=>'{}'];
    }
    if(!$match)return $allowed?$json(['code'=>'rest_no_route','message'=>'Für diese Methode gibt es keine Route.','data'=>['status'=>405]],405,['Allow'=>implode(', ',array_keys($allowed))]):$json(['code'=>'rest_no_route','message'=>'Es wurde keine Route gefunden, die zur URL und Anfragemethode passt.','data'=>['status'=>404]],404);
    $req=new WP_REST_Request($method,$route,$match);
    $req->set_url_params($params);$req->set_query_params($query);$req->set_headers($headers);$req->set_body($rawBody);
    if($rawBody!==''){
        if($req->is_json_content_type()||($rawBody[0]??'')==='{'||($rawBody[0]??'')==='['){ $j=json_decode($rawBody,true);if(is_array($j))$req->set_json_params($j);elseif($req->is_json_content_type())return $json(['code'=>'rest_invalid_json','message'=>'Ungültiges JSON-Format.','data'=>['status'=>400]],400); }
        else { $b=[];parse_str($rawBody,$b);$req->set_body_params($b); }
    }
    if($rawBody===''&&!empty($_POST))$req->set_body_params(wp_unslash($_POST));   // Formularfelder (multipart/urlencoded)
    try{
        $err=rrw_wp_rest_arg_check((array)($match['args']??[]),$req);
        if(!$err&&isset($match['permission_callback'])){
            $ok=is_callable($match['permission_callback'])?call_user_func($match['permission_callback'],$req):false;
            if(is_wp_error($ok))$err=$ok;
            elseif(!$ok)$err=new WP_Error('rest_forbidden','Du darfst das nicht tun.',['status'=>is_user_logged_in()?403:401]);
        }
        if($err)return $json(['code'=>$err->get_error_code(),'message'=>$err->get_error_message(),'data'=>(array)$err->get_error_data()+['status'=>500]],(int)(($err->get_error_data()['status']??500)));
        if(!is_callable($match['callback']??null))return $json(['code'=>'rest_invalid_handler','message'=>'Der Handler ist ungültig.','data'=>['status'=>500]],500);
        $res=call_user_func($match['callback'],$req);
        $res=apply_filters('rest_post_dispatch',$res,null,$req);
    }catch(RRW_WP_Die $d){ return $json(['code'=>'rest_die','message'=>$d->getMessage(),'data'=>['status'=>$d->getCode()?:500]],$d->getCode()?:500);
    }catch(Throwable $e){ rrw_wp_log('REST '.$route.': '.get_class($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');return $json(['code'=>'internal_server_error','message'=>'Interner Fehler.','data'=>['status'=>500]],500); }
    if(is_wp_error($res)){ $st=(int)(((array)$res->get_error_data())['status']??500);return $json(['code'=>$res->get_error_code(),'message'=>$res->get_error_message(),'data'=>(array)$res->get_error_data()+['status'=>$st]],$st); }
    if($res instanceof WP_REST_Response){ return $json($res->get_data(),$res->get_status(),array_map('strval',$res->get_headers())); }
    return $json($res,200);
}
