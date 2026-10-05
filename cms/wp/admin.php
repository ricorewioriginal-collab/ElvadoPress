<?php
// Plugin-Verwaltungsseiten (Stufe 4): Menüs, Seiten, Einstellungs-API (options.php), admin-post, admin-ajax.
// Die Seiten laufen im CMS in einem abgeschotteten iframe (srcdoc, sandbox ohne same-origin); Formulare, Links und Ajax-Aufrufe
// werden über eine kleine Brücke (postMessage) an die API des CMS geleitet. Nur Superadmins.

/** admin_menu/admin_init einmal auslösen. */
require_once __DIR__.'/site-editor.php';
// Von WP_Screen abgeleitet: Plugins schreiben den Typ vor (z. B. Contact Form 7: WPCF7_Help_Tabs::__construct(WP_Screen $screen))
class RRW_WP_Screen extends WP_Screen {
    public $id='';public $base='';public $post_type='';public $action='';public $taxonomy='';public $parent_base='';public $parent_file='';public $is_network=false;public $is_user=false;public $in_admin='site';public $columns=0;public $is_block_editor=false;
    public function is_block_editor() { return false; } public function in_admin($admin=null) { return true; }
    public function add_help_tab($a) { return $this; } public function remove_help_tab($id) { return $this; } public function remove_help_tabs() {} public function get_help_tabs() { return []; } public function get_help_tab($id) { return null; } public function set_help_sidebar($c) {} public function get_help_sidebar() { return ''; }
    public function get_option($option,$key=false) { return null; } public function get_options() { return []; } public function add_option($o,$v=true) {} public function remove_options() {} public function remove_option($o) {}
    public function get_columns() { return 0; } public $rrw_sr=[]; public function set_screen_reader_content($c=[]) { $this->rrw_sr=array_merge($this->rrw_sr,(array)$c); } public function get_screen_reader_content() { return $this->rrw_sr; } public function remove_screen_reader_content() { $this->rrw_sr=[]; }
    public function render_screen_reader_content($key='',$tag='h2') { if(empty($this->rrw_sr[$key]))return; echo '<'.$tag.' class="screen-reader-text">'.esc_html((string)$this->rrw_sr[$key]).'</'.$tag.'>'; } public function render_screen_meta() {} public function render_screen_options($o=[]) {} public function show_screen_options() { return false; } public function render_per_page_options() {} public function get_screen_reader_text($k) { return ''; }
    public static function get($hook_name='') { return $GLOBALS['rrw_wp_screen']??new self(); } public static function get_current() { return $GLOBALS['rrw_wp_screen']??null; }
}
function rrw_wp_admin_init(): void {
    static $done=false;if($done)return;$done=true;
    require_once __DIR__.'/coreassets.php';rrw_wp_core_register();
    $GLOBALS['pagenow']='admin.php';$GLOBALS['rrw_wp_is_admin']=true;
    $GLOBALS['rrw_wp_screen']=new RRW_WP_Screen();
    $GLOBALS['rrw_wp_menu']['rrw-widgets']=['title'=>'Widgets','menu'=>'Widgets','cap'=>'edit_theme_options','cb'=>'rrw_wp_widgets_page','parent'=>'themes.php','plugin'=>'','hook'=>'appearance_page_rrw-widgets'];
    $GLOBALS['rrw_wp_menu']['rrw-menus']=['title'=>'Menüs','menu'=>'Menüs','cap'=>'edit_theme_options','cb'=>'rrw_wp_menus_page','parent'=>'themes.php','plugin'=>'','hook'=>'appearance_page_rrw-menus'];
    $GLOBALS['rrw_wp_menu']['rrw-customize']=['title'=>'Anpassen','menu'=>'Anpassen','cap'=>'edit_theme_options','cb'=>'rrw_wp_customize_page','parent'=>'themes.php','plugin'=>'','hook'=>'appearance_page_rrw-customize'];
    if(function_exists('wp_is_block_theme')&&wp_is_block_theme())$GLOBALS['rrw_wp_menu']['rrw-site-editor']=['title'=>'Website-Editor','menu'=>'Website-Editor','cap'=>'edit_theme_options','cb'=>'rrw_wp_site_editor_page','parent'=>'themes.php','plugin'=>'','hook'=>'appearance_page_rrw-site-editor'];
    try{ do_action('admin_menu'); do_action('admin_init'); }catch(RRW_WP_Die $d){}
}
/** wp_redirect() nicht senden, sondern in $GLOBALS['rrw_wp_admin_redirect'] merken (vor jedem Aufruf zurücksetzen). */
function rrw_wp_capture_redirects(): void {
    static $hooked=false;if($hooked)return;$hooked=true;
    add_filter('wp_redirect',function($l){ if(is_string($l)&&$l!=='')$GLOBALS['rrw_wp_admin_redirect']=$l;return false; },999);
}
function rrw_wp_admin_cb($cb): ?callable { return is_callable($cb)?$cb:null; }

/** Menübaum: [{label, plugin, items:[{slug,title}]}] – nur Seiten mit aufrufbarem Callback. */
/** Zu welchem Plugin (Ordner bzw. Datei unter wp-content/plugins) gehört eine Menüseite? Aus der Datei der Seiten-Funktion; '' bei CMS-eigenen Seiten. */
function rrw_wp_menu_plugin($it): string {
    $own=(string)($it['plugin']??'');if($own!=='')return explode('/',$own)[0];
    $cb=$it['cb']??null;$f='';
    try{
        if(is_array($cb)&&isset($cb[0],$cb[1]))$f=(string)(new ReflectionMethod($cb[0],(string)$cb[1]))->getFileName();
        elseif($cb instanceof Closure||(is_string($cb)&&function_exists($cb)))$f=(string)(new ReflectionFunction($cb))->getFileName();
    }catch(Throwable $e){ return ''; }
    if($f==='')return '';
    $root=rtrim(wp_normalize_path(WP_PLUGIN_DIR),'/').'/';$f=wp_normalize_path($f);
    return str_starts_with($f,$root)?explode('/',substr($f,strlen($root)))[0]:'';
}
function rrw_wp_admin_menu_tree(): array {
    rrw_wp_admin_init();
    $labels=['options-general.php'=>'Einstellungen','tools.php'=>'Werkzeuge','themes.php'=>'Design','plugins.php'=>'Plugins','users.php'=>'Benutzer','index.php'=>'Dashboard','edit.php'=>'Beiträge','upload.php'=>'Medien','edit.php?post_type=page'=>'Seiten'];
    $groups=[];$menu=$GLOBALS['rrw_wp_menu']??[];
    foreach($menu as $slug=>$it){
        $key=$it['parent']!==''?$it['parent']:$slug;
        if(!isset($groups[$key])){
            $top=$menu[$key]??null;
            $label=$labels[$key]??trim(strip_tags(html_entity_decode((string)($top['menu']??$key))));
            $groups[$key]=['label'=>$label!==''?$label:$key,'plugin'=>(string)($top['plugin']??$it['plugin']),'items'=>[]];
        }
        if(!rrw_wp_admin_cb($it['cb'])||!empty($it['hidden']))continue;
        $pl=rrw_wp_menu_plugin($it);
        $groups[$key]['items'][]=['slug'=>(string)$slug,'title'=>trim(html_entity_decode(strip_tags((string)($it['title']!==''?$it['title']:$it['menu'])),ENT_QUOTES,'UTF-8')),'plugin'=>$pl,'top'=>$it['parent']===''];
    }
    return array_values(array_filter($groups,fn($g)=>$g['items']));
}

/** Gespeicherte Daten aus einem urlencodierten Text lesen (Feldnamen wie name[]=a). */
function rrw_wp_admin_parse(string $body): array { $o=[];parse_str($body,$o);return $o; }

function rrw_wp_admin_css(): string {
    return 'body{margin:0;padding:14px 18px;font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;color:#1d2327;background:#fff}.wrap{max-width:1100px}h1{font-size:22px;margin:0 0 14px}h2{font-size:17px;margin:22px 0 8px}h3{font-size:15px}a{color:#2271b1}'
        .'.form-table{width:100%;border-collapse:collapse}.form-table th{width:220px;text-align:left;vertical-align:top;padding:12px 10px 12px 0;font-weight:600}.form-table td{padding:8px 0}'
        .'input[type=text],input[type=email],input[type=url],input[type=password],input[type=number],input[type=search],select,textarea{border:1px solid #8c8f94;border-radius:4px;padding:6px 8px;font:inherit;max-width:100%;box-sizing:border-box}textarea{width:100%}.regular-text{width:25em}.large-text{width:100%}.small-text{width:6em}'
        .'.button,.button-primary,input[type=submit]{display:inline-block;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;padding:5px 12px;font:inherit;cursor:pointer}.button-primary{background:#2271b1;color:#fff}'
        .'.notice{border:1px solid #c3c4c7;border-left:4px solid #72aee6;background:#fff;padding:4px 12px;margin:10px 0}.notice-success,.updated{border-left-color:#00a32a}.notice-error,.error{border-left-color:#d63638}.notice-warning{border-left-color:#dba617}'
        .'.widefat{width:100%;border-collapse:collapse;border:1px solid #c3c4c7}.widefat th,.widefat td{padding:8px 10px;text-align:left;border-bottom:1px solid #e0e0e0}.widefat thead th{background:#f6f7f7}'
        .'.description{color:#646970;font-size:13px;margin:4px 0}.postbox,.card{border:1px solid #c3c4c7;background:#fff;padding:8px 14px;margin:12px 0}.hidden{display:none}.screen-reader-text{position:absolute;left:-9999px}'
        .'@media (prefers-color-scheme:dark){body{background:#101418;color:#dde2e6}a{color:#72aee6}.button{background:#1d2327}.notice,.postbox,.card{background:#161b20;border-color:#3c434a}input,select,textarea{background:#1d2327;color:#dde2e6}.widefat th,.widefat td{border-color:#2c3338}.widefat thead th{background:#1d2327}}';
}

/** Brücke im iframe: Formulare, Links, Ajax (fetch/XHR zu admin-ajax.php) laufen über das Eltern-Fenster. */
function rrw_wp_admin_bridge(string $slug): string {
    $js=<<<'JS'
(function(){
/* Der Rahmen ist abgeschottet (kein allow-same-origin): Speicher und Cookies gibt es nur im Arbeitsspeicher dieser Seite. */
function mem(){var d={};return {getItem:function(k){return Object.prototype.hasOwnProperty.call(d,k)?d[k]:null},setItem:function(k,v){d[k]=String(v)},removeItem:function(k){delete d[k]},clear:function(){d={}},key:function(i){return Object.keys(d)[i]||null},get length(){return Object.keys(d).length}}}
['localStorage','sessionStorage'].forEach(function(n){try{void window[n]}catch(e){try{Object.defineProperty(window,n,{value:mem(),configurable:true})}catch(e2){}}});
try{void document.cookie}catch(e){var ck={};try{Object.defineProperty(document,'cookie',{configurable:true,get:function(){return Object.keys(ck).map(function(k){return k+'='+ck[k]}).join('; ')},set:function(v){var p=String(v).split(';')[0].split('=');ck[p[0].trim()]=p.slice(1).join('=')}})}catch(e2){}}
var pid=0,pend={};
window.addEventListener('message',function(e){var d=e.data;if(d&&d.rrw==='res'&&pend[d.id]){pend[d.id](d);delete pend[d.id]}});
function ask(kind,payload){return new Promise(function(res){var id=++pid;pend[id]=res;payload.rrw=kind;payload.id=id;parent.postMessage(payload,'*')})}
function enc(fd){var p=[];fd.forEach(function(v,k){if(typeof v==='string')p.push(encodeURIComponent(k)+'='+encodeURIComponent(v))});return p.join('&')}
document.addEventListener('submit',function(ev){var f=ev.target;if(!f||!f.tagName||ev.defaultPrevented)return;ev.preventDefault();
 var fd=new FormData(f);if(ev.submitter&&ev.submitter.name)fd.append(ev.submitter.name,ev.submitter.value||'');
 parent.postMessage({rrw:'submit',method:(f.getAttribute('method')||'get').toLowerCase(),action:f.getAttribute('action')||'',body:enc(fd)},'*')},true);
document.addEventListener('click',function(ev){var a=ev.target.closest&&ev.target.closest('a[href]');if(!a||ev.defaultPrevented)return;var h=a.getAttribute('href')||'';
 if(h===''||h.charAt(0)==='#'||/^javascript:/i.test(h))return;ev.preventDefault();
 if(/^https?:\/\//i.test(h)&&!/[?&]page=/.test(h)){window.open(h,'_blank','noopener');return}
 parent.postMessage({rrw:'nav',href:h},'*')},true);
function isAjax(u){return /admin-ajax\.php/.test(String(u))}
var oo=XMLHttpRequest.prototype.open,os=XMLHttpRequest.prototype.send,oh=XMLHttpRequest.prototype.setRequestHeader;
XMLHttpRequest.prototype.open=function(m,u){this._rb=isAjax(u)?{m:(m||'GET').toUpperCase(),u:u,h:{}}:null;return oo.apply(this,arguments)};
XMLHttpRequest.prototype.setRequestHeader=function(k,v){if(this._rb)this._rb.h[k]=v;else oh.apply(this,arguments)};
XMLHttpRequest.prototype.send=function(b){var x=this,r=x._rb;if(!r)return os.apply(x,arguments);
 var body=(typeof b==='string')?b:(b instanceof FormData?enc(b):'');
 ask('ajax',{method:r.m,url:r.u,body:body,json:/json/i.test(r.h['Content-Type']||'')}).then(function(d){
  var def=function(k,v){Object.defineProperty(x,k,{value:v,configurable:true})};
  def('readyState',4);def('status',d.status||200);def('statusText','OK');def('responseText',d.text||'');def('response',x.responseType==='json'?(function(){try{return JSON.parse(d.text)}catch(e){return null}})():(d.text||''));
  x.getAllResponseHeaders=function(){return 'content-type: '+(d.type||'text/html')+'\r\n'};x.getResponseHeader=function(k){return /content-type/i.test(k)?(d.type||'text/html'):null};
  try{x.onreadystatechange&&x.onreadystatechange()}catch(e){}try{x.onload&&x.onload()}catch(e){}x.dispatchEvent(new Event('readystatechange'));x.dispatchEvent(new Event('load'));x.dispatchEvent(new Event('loadend'))})};
['pushState','replaceState'].forEach(function(n){var o=history[n];history[n]=function(st,t,u){try{return o.apply(history,arguments)}catch(e){if(u!=null&&n==='pushState')parent.postMessage({rrw:'nav',href:String(u)},'*')}}});
function isRest(u){return /\/wp-json\/|[?&]rest_route=/.test(String(u))}
function restCall(method,path,headers,body){return ask('rest',{method:method,path:path,headers:headers||{},body:body||''})}
window.rrwRestFetch=function(o){var path=o.path||String(o.url||'').replace(/^.*?\/wp-json/,'');var h=o.headers||{};var body=o.data!==undefined?JSON.stringify(o.data):(typeof o.body==='string'?o.body:(o.body instanceof FormData?enc(o.body):''));
 if(o.data!==undefined&&!h['Content-Type'])h['Content-Type']='application/json';
 return restCall((o.method||'GET').toUpperCase(),path,h,body).then(function(d){var ok=d.status>=200&&d.status<300;
  if(o.parse===false)return new Response(d.text||'',{status:d.status||200,headers:Object.assign({'Content-Type':d.type||'application/json'},d.headers||{})});
  var j=null;try{j=d.text?JSON.parse(d.text):null}catch(e){j=null}
  if(ok)return j;return Promise.reject(j||{code:'invalid_json',message:'Ungültige Antwort'})})};
var of=window.fetch;window.fetch=function(u,o){var uu=u&&u.url?u.url:u;if(isRest(uu)){o=o||{};var hd={};try{(new Headers(o.headers||{})).forEach(function(v,k){hd[k]=v})}catch(e){}var bd=typeof o.body==='string'?o.body:(o.body instanceof FormData?enc(o.body):'');return restCall((o.method||'GET').toUpperCase(),String(uu),hd,bd).then(function(d){return new Response(d.text||'',{status:d.status||200,headers:Object.assign({'Content-Type':d.type||'application/json'},d.headers||{})})})}
if(!isAjax(u&&u.url?u.url:u))return of.apply(this,arguments);o=o||{};var b=o.body;b=typeof b==='string'?b:(b instanceof FormData?enc(b):(b&&b.toString?b.toString():''));
 return ask('ajax',{method:(o.method||'GET').toUpperCase(),url:String(u&&u.url?u.url:u),body:b}).then(function(d){return new Response(d.text||'',{status:d.status||200,headers:{'Content-Type':d.type||'text/html'}})})};
})();
JS;
    return '<script>var ajaxurl='.json_encode(admin_url('admin-ajax.php')).';var pagenow='.json_encode($slug).';var wpApiSettings='.json_encode(['root'=>esc_url_raw(rest_url()),'nonce'=>'rrw','versionString'=>'wp/v2/']).';var userSettings={url:"/",uid:"1",time:"1",secure:""};</script><script>'.$js.'</script>';
}

/**
 * Eine Verwaltungsseite ausführen. $req: page, method (GET|POST), body (urlencodiert), query (urlencodiert).
 * @return array{ok:bool,html?:string,title?:string,message?:string,redirect?:string,notice?:string}
 */
function rrw_wp_admin_page(array $req): array {
    rrw_wp_admin_init();
    $slug=(string)($req['page']??'');$method=strtoupper((string)($req['method']??'GET'));
    $menu=$GLOBALS['rrw_wp_menu'][$slug]??null;
    if(!$menu||!rrw_wp_admin_cb($menu['cb']))return ['ok'=>false,'message'=>'Seite nicht gefunden'];
    $get=rrw_wp_admin_parse((string)($req['query']??''));$get['page']=$slug;if(str_contains($slug,'&')){ [$pb,$rest]=explode('&',$slug,2);$extra=rrw_wp_admin_parse($rest);$get=$extra+$get;$get['page']=$pb; }
    $post=$method==='POST'?rrw_wp_admin_parse((string)($req['body']??'')):[];
    $_GET=wp_slash($get);$_POST=wp_slash($post);$_REQUEST=array_merge($_GET,$_POST);$_SERVER['REQUEST_METHOD']=$method;
    $GLOBALS['plugin_page']=$slug;$GLOBALS['title']=(string)$menu['title'];$GLOBALS['hook_suffix']=(string)($menu['hook']??'');
    $_SERVER['REQUEST_URI']='/cms/admin.php?page='.rawurlencode($slug);
    $GLOBALS['rrw_wp_die_throws']=true;    $notice='';$GLOBALS['rrw_wp_admin_redirect']='';
    rrw_wp_capture_redirects();
    try{
        if($method==='POST'){
            if(isset($post['option_page'])){
                $r=rrw_wp_save_settings($post);
                if(is_wp_error($r))return ['ok'=>true,'html'=>rrw_wp_admin_doc($slug,'<div class="notice notice-error"><p>'.esc_html($r->get_error_message()).'</p></div>',false),'title'=>$menu['title'],'notice'=>$r->get_error_message(),'error'=>true];
                $_GET['settings-updated']='true';$notice='Einstellungen gespeichert.';
            } elseif(!empty($post['action'])&&has_action('admin_post_'.$post['action'])){
                ob_start();
                try{ do_action('admin_post_'.$post['action']); }catch(RRW_WP_Die $d){}
                ob_end_clean();
                if(($redirect=$GLOBALS['rrw_wp_admin_redirect'])!=='')return ['ok'=>true,'redirect'=>$redirect];
            }
        }
        $hs=(string)($menu['hook']??'');
        $scr=$GLOBALS['rrw_wp_screen'];$scr->id=$hs;$scr->base=$hs;$scr->parent_base=(string)($menu['parent']??'');
        if($hs!==''){ rrw_wp_admin_capture(function() use($hs){ do_action('current_screen',$GLOBALS['rrw_wp_screen']);do_action('load-'.$hs);do_action('admin_xml_ns'); }); }
        $html=rrw_wp_admin_capture(function() use($menu){ call_user_func($menu['cb']); });
        if(($redirect=$GLOBALS['rrw_wp_admin_redirect'])!=='')return ['ok'=>true,'redirect'=>$redirect];
    }catch(RRW_WP_Die $d){
        return ['ok'=>true,'html'=>rrw_wp_admin_doc($slug,'<div class="notice notice-error"><p>'.wp_kses_post($d->getMessage()).'</p></div>',false),'title'=>$menu['title'],'error'=>true];
    }catch(Throwable $e){
        rrw_wp_log('Admin-Seite '.$slug.': '.get_class($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
        return ['ok'=>true,'html'=>rrw_wp_admin_doc($slug,'<div class="notice notice-error"><p>Die Seite des Plugins hat einen Fehler verursacht: '.esc_html($e->getMessage()).'</p></div>',false),'title'=>$menu['title'],'error'=>true];
    }
    return ['ok'=>true,'html'=>rrw_wp_admin_doc($slug,$html,true),'title'=>$menu['title'],'notice'=>$notice,'frame_query'=>http_build_query(array_map(fn($v)=>is_array($v)?'':stripslashes((string)$v),$get))];
}
function rrw_wp_admin_capture(callable $fn): string {
    $lv=ob_get_level();ob_start();
    try{ $fn(); $out=(string)ob_get_clean(); }
    catch(Throwable $e){ while(ob_get_level()>$lv)ob_end_clean(); throw $e; }
    return $out;
}
/** Speichert ein Rahmen-Dokument unter einem zufälligen Schlüssel und liefert die Adresse (relativ zu cms/). Alte Dateien werden entfernt. */
function rrw_wp_admin_frame_store(string $html): string {
    $dir=RRW_WP_DATA.'/frames';if(!is_dir($dir))@mkdir($dir,0775,true);
    foreach(glob($dir.'/*.html')?:[] as $f)if(filemtime($f)<time()-900)@unlink($f);
    $id=bin2hex(random_bytes(16));file_put_contents($dir.'/'.$id.'.html',$html);
    return 'wp/frame.php?f='.$id;
}
/** Vollständiges Dokument für den iframe. */
function rrw_wp_admin_doc(string $slug, string $body, bool $full): string {
    $head='';$foot='';
    try{
        // Wie in WordPress: Skripte und Stile werden von Standard-Hooks gedruckt, damit Plugins davor noch Daten anhängen können.
        if(!has_action('admin_print_styles','wp_print_styles'))add_action('admin_print_styles','wp_print_styles',20);
        if(!has_action('admin_print_scripts','wp_print_head_scripts'))add_action('admin_print_scripts','wp_print_head_scripts',20);
        if(!has_action('admin_print_footer_scripts','wp_print_footer_scripts'))add_action('admin_print_footer_scripts','wp_print_footer_scripts',10);
        $head=rrw_wp_admin_capture(function(){ $hs=$GLOBALS['hook_suffix']??'';do_action('admin_enqueue_scripts',$hs);do_action('admin_print_styles-'.$hs);do_action('admin_print_styles');do_action('admin_print_scripts-'.$hs);do_action('admin_print_scripts');do_action('admin_head'); });
        $foot=rrw_wp_admin_capture(function(){ $hs=$GLOBALS['hook_suffix']??'';do_action('admin_footer',$hs);do_action('admin_print_footer_scripts-'.$hs);do_action('admin_print_footer_scripts'); });
    }catch(Throwable $e){ rrw_wp_log('Admin-Dokument: '.$e->getMessage()); }
    $bridge=!empty($GLOBALS['rrw_wp_native_admin'])?'<script>var ajaxurl='.json_encode(admin_url('admin-ajax.php')).';var pagenow='.json_encode($slug).';var wpApiSettings='.json_encode(['root'=>esc_url_raw(rest_url()),'nonce'=>wp_create_nonce('wp_rest'),'versionString'=>'wp/v2/']).';</script>':rrw_wp_admin_bridge($slug);
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_self"><style>'.rrw_wp_admin_css().'</style>'.$bridge.$head
        .'</head><body class="wp-admin wp-core-ui"><a class="screen-reader-shortcut" href="#wpbody-content" style="position:absolute;left:-9999px">Zum Inhalt springen</a><a class="screen-reader-shortcut" href="#wp-toolbar" style="position:absolute;left:-9999px">Zur Werkzeugleiste</a><div id="adminmenumain" style="display:none"><ul id="adminmenu"></ul></div><div id="wpadminbar" style="display:none"></div>'.($full?'<div id="wpbody-content">'.$body.'</div>':$body).$foot.'</body></html>';
}

/** admin-ajax.php: führt wp_ajax_{action} (angemeldet) bzw. wp_ajax_nopriv_{action} (Besucher) aus. @return array{status:int,type:string,text:string} */
function rrw_wp_ajax(string $method, string $query, string $body, bool $loggedIn): array {
    try{ return rrw_wp_ajax_run($method,$query,$body,$loggedIn); } finally { $GLOBALS['rrw_wp_doing_ajax']=false; }
}
function rrw_wp_ajax_run(string $method, string $query, string $body, bool $loggedIn): array {
    $in=rrw_wp_admin_parse($query);if(strtoupper($method)==='POST')$in=array_merge($in,rrw_wp_admin_parse($body));
    $_GET=wp_slash(rrw_wp_admin_parse($query));$_POST=strtoupper($method)==='POST'?wp_slash(rrw_wp_admin_parse($body)):[];$_REQUEST=array_merge($_GET,$_POST);
    $_SERVER['REQUEST_METHOD']=strtoupper($method);
    $GLOBALS['rrw_wp_doing_ajax']=true;$GLOBALS['rrw_wp_die_throws']=true;
    $action=preg_replace('/[^A-Za-z0-9_\-]/','',(string)($in['action']??''));
    if($action==='')return ['status'=>400,'type'=>'text/plain','text'=>'0'];
    $hook=$loggedIn&&has_action('wp_ajax_'.$action)?'wp_ajax_'.$action:(has_action('wp_ajax_nopriv_'.$action)?'wp_ajax_nopriv_'.$action:'');
    if($hook==='')return ['status'=>400,'type'=>'text/plain','text'=>'0'];
    $status=200;
    $lv=ob_get_level();ob_start();
    try{ do_action($hook); $out=(string)ob_get_clean(); $out=$out===''?'0':$out; }
    catch(RRW_WP_Die $d){ $out=(string)ob_get_clean(); $status=$d->getCode()?:200; if($d->getMessage()!=='json'&&$out==='')$out=$d->getMessage(); }
    catch(Throwable $e){ while(ob_get_level()>$lv)ob_end_clean(); rrw_wp_log('Ajax '.$action.': '.$e->getMessage());return ['status'=>500,'type'=>'text/plain','text'=>'0']; }
    $json=is_string($out)&&$out!==''&&($out[0]==='{'||$out[0]==='[')&&json_decode($out)!==null;
    return ['status'=>$status,'type'=>$json?'application/json':'text/html','text'=>$out];
}

/* ───────── Widgets (Seitenleisten des aktiven WordPress-Themes) ───────── */
/** Eingebaute Seite „Widgets“ unter Design: Widgets pro Seitenleiste hinzufügen, bearbeiten (WP_Widget::form/update), sortieren, entfernen. */
function rrw_wp_widgets_page(): void {
    $F=fn($s)=>esc_html((string)$s);
    $sidebars=$GLOBALS['wp_registered_sidebars']??[];$factory=$GLOBALS['wp_widget_factory']->widgets??[];
    $byBase=[];foreach($factory as $w)$byBase[$w->id_base]=$w;
    $msg='';$err='';
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))==='POST'){
        $p=wp_unslash($_POST);
        if(!wp_verify_nonce($p['_wpnonce']??'','rrw-widgets'))$err='Der Sicherheitscode ist abgelaufen – bitte Seite neu laden.';
        else{
            $sw=wp_get_sidebars_widgets();$op=(string)($p['rrw_op']??'');$sb=(string)($p['sidebar']??'');$wid=(string)($p['widget_id']??'');
            if($op==='reset'){ delete_option('sidebars_widgets');$msg='Standard-Widgets wiederhergestellt.'; }
            elseif($op==='add'&&isset($sidebars[$sb])&&isset($byBase[$p['base']??''])){
                $base=(string)$p['base'];$opts=get_option('widget_'.$base,[]);$opts=is_array($opts)?$opts:[];$n=0;foreach(array_keys($opts) as $k)if(is_int($k))$n=max($n,$k);$n++;
                $opts[$n]=rrw_wp_default_widget_instance($base)?:[];$opts['_multiwidget']=1;update_option('widget_'.$base,$opts);
                $sw[$sb][]=$base.'-'.$n;wp_set_sidebars_widgets($sw);$msg='Widget hinzugefügt.';
            } elseif(preg_match('/^(.+)-(\d+)$/',$wid,$m)&&isset($byBase[$m[1]])){
                [$_,$base,$n]=$m;$n=(int)$n;$w=$byBase[$base];$opts=get_option('widget_'.$base,[]);$opts=is_array($opts)?$opts:[];
                if($op==='save'){
                    $new=(array)($p['widget-'.$base][$n]??[]);$w->_set($n);
                    $inst=$w->update($new,$opts[$n]??[]);if($inst!==false){ $opts[$n]=$inst;$opts['_multiwidget']=1;update_option('widget_'.$base,$opts);$msg='Widget gespeichert.'; }
                } elseif($op==='delete'){
                    foreach($sw as $k=>$list)$sw[$k]=array_values(array_diff((array)$list,[$wid]));wp_set_sidebars_widgets($sw);unset($opts[$n]);update_option('widget_'.$base,$opts);$msg='Widget entfernt.';
                } elseif(in_array($op,['up','down'],true)&&isset($sw[$sb])){
                    $l=array_values((array)$sw[$sb]);$i=array_search($wid,$l,true);
                    if($i!==false){ $j=$op==='up'?$i-1:$i+1;if(isset($l[$j])){ [$l[$i],$l[$j]]=[$l[$j],$l[$i]];$sw[$sb]=$l;wp_set_sidebars_widgets($sw); } }
                }
            }
        }
    }
    $sw=wp_get_sidebars_widgets();
    echo '<div class="wrap"><h1>Widgets</h1>';
    if($msg)echo '<div class="notice notice-success"><p>'.$F($msg).'</p></div>';
    if($err)echo '<div class="notice notice-error"><p>'.$F($err).'</p></div>';
    if(!$sidebars){ echo '<p>Das aktive Theme hat keine Seitenleisten (Widget-Bereiche).</p></div>';return; }
    $nonce=wp_nonce_field('rrw-widgets','_wpnonce',false,false);
    foreach($sidebars as $id=>$sb){
        echo '<div class="postbox"><h2>'.$F($sb['name']).'</h2>';
        if(!empty($sb['description']))echo '<p class="description">'.$F($sb['description']).'</p>';
        $list=(array)($sw[$id]??[]);
        if(!$list)echo '<p class="description">Noch keine Widgets.</p>';
        foreach($list as $wid){
            if(!preg_match('/^(.+)-(\d+)$/',(string)$wid,$m)||!isset($byBase[$m[1]]))continue;
            $w=$byBase[$m[1]];$n=(int)$m[2];$opts=get_option('widget_'.$w->id_base,[]);$inst=is_array($opts)&&isset($opts[$n])?$opts[$n]:rrw_wp_default_widget_instance($w->id_base);
            $w->_set($n);$title=trim((string)($inst['title']??''));
            echo '<details style="border:1px solid #c3c4c7;border-radius:4px;margin:6px 0;padding:6px 10px"><summary><b>'.$F($w->name).'</b>'.($title!==''?' – '.$F($title):'').'</summary><form method="post" action="" style="margin-top:8px">'.$nonce
                .'<input type="hidden" name="widget_id" value="'.esc_attr($wid).'"><input type="hidden" name="sidebar" value="'.esc_attr($id).'">';
            ob_start();$w->form(is_array($inst)?$inst:[]);echo ob_get_clean();
            echo '<p><button class="button button-primary" name="rrw_op" value="save">Speichern</button> <button class="button" name="rrw_op" value="up" title="Nach oben">↑</button> <button class="button" name="rrw_op" value="down" title="Nach unten">↓</button> <button class="button" name="rrw_op" value="delete" style="color:#b32d2e">Entfernen</button></p></form></details>';
        }
        echo '<form method="post" action="" style="margin:8px 0">'.$nonce.'<input type="hidden" name="sidebar" value="'.esc_attr($id).'"><select name="base">';
        foreach($byBase as $base=>$w)echo '<option value="'.esc_attr($base).'">'.$F($w->name).'</option>';
        echo '</select> <button class="button" name="rrw_op" value="add">Widget hinzufügen</button></form></div>';
    }
    echo '<form method="post" action="">'.$nonce.'<button class="button" name="rrw_op" value="reset" onclick="return confirm(\'Alle Widgets auf den Standard zurücksetzen?\')">Standard wiederherstellen</button></form></div>';
}

/* ───────── Menü-Standorte ───────── */
/** Eingebaute Seite „Menüs“: ordnet die Menüpositionen des Themes den CMS-Menüs (Hauptmenü/Fußmenü) zu. */
function rrw_wp_menus_page(): void {
    $F=fn($s)=>esc_html((string)$s);$locs=get_registered_nav_menus();$msg='';$err='';
    $choices=['top'=>'Hauptmenü (CMS)','bottom'=>'Fußmenü (CMS)','' => 'Kein Menü'];
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))==='POST'){
        $p=wp_unslash($_POST);
        if(!wp_verify_nonce($p['_wpnonce']??'','rrw-menus'))$err='Der Sicherheitscode ist abgelaufen – bitte Seite neu laden.';
        else{ $new=[];foreach($locs as $loc=>$_){ $v=(string)($p['loc'][$loc]??'');if(array_key_exists($v,$choices))$new[$loc]=$v; }set_theme_mod('nav_menu_locations',$new);$msg='Menü-Standorte gespeichert.'; }
    }
    echo '<div class="wrap"><h1>Menüs</h1>';
    if($msg)echo '<div class="notice notice-success"><p>'.$F($msg).'</p></div>';
    if($err)echo '<div class="notice notice-error"><p>'.$F($err).'</p></div>';
    echo '<p class="description">Die Menüeinträge pflegst du im CMS unter „Menüs“. Hier legst du fest, welches CMS-Menü an welcher Stelle des WordPress-Themes erscheint.</p>';
    if(!$locs){ echo '<p>Das aktive Theme hat keine Menüpositionen.</p></div>';return; }
    $cur=get_nav_menu_locations();
    echo '<form method="post" action="">'.wp_nonce_field('rrw-menus','_wpnonce',false,false).'<table class="form-table" role="presentation">';
    foreach($locs as $loc=>$label){
        echo '<tr><th scope="row">'.$F($label).'</th><td><select name="loc['.esc_attr($loc).']">';
        foreach($choices as $v=>$t)echo '<option value="'.esc_attr((string)$v).'"'.selected((string)($cur[$loc]??''),(string)$v,false).'>'.$F($t).'</option>';
        echo '</select></td></tr>';
    }
    echo '</table>';submit_button();echo '</form></div>';
}

/* ───────── Customizer (vereinfacht) ───────── */
function rrw_wp_customize_field(string $id): string { return 'c_'.substr(md5($id),0,12); }
/** Eingebaute Seite „Anpassen“: Einstellungen, die das Theme per customize_register anbietet. */
function rrw_wp_customize_page(): void {
    $F=fn($s)=>esc_html((string)$s);$m=rrw_wp_customizer();$msg='';$err='';
    $skip=['custom_logo','blogname','blogdescription','site_icon','header_image','background_image','background_color','header_textcolor','nav_menu_locations'];
    $ctrls=array_filter($m->controls,function($c) use($m,$skip){ $s=$c->setting_obj();return $s&&!in_array($s->id,$skip,true)&&!str_starts_with((string)$s->id,'nav_menu')&&($c->type!=='hidden'); });
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))==='POST'){
        $p=wp_unslash($_POST);
        if(!wp_verify_nonce($p['_wpnonce']??'','rrw-customize'))$err='Der Sicherheitscode ist abgelaufen – bitte Seite neu laden.';
        else{
            foreach($ctrls as $c){
                $s=$c->setting_obj();$f=rrw_wp_customize_field($s->id);
                $v=$c->type==='checkbox'?(isset($p[$f])&&$p[$f]!==''&&$p[$f]!=='0'?1:0):($p[$f]??'');
                if(is_string($v))$v=trim($v);
                if(is_callable($s->sanitize_callback))$v=call_user_func($s->sanitize_callback,$v,$s);
                $v=apply_filters('customize_sanitize_'.$s->id,$v,$s);
                if($s->type==='option')update_option($s->id,$v);else set_theme_mod($s->id,$v);
            }
            do_action('customize_save_after',$m);$msg='Gespeichert. Ansehen: im Bereich WordPress-Themes die Vorschau öffnen.';
        }
    }
    echo '<div class="wrap"><h1>Anpassen</h1>';
    if($msg)echo '<div class="notice notice-success"><p>'.$F($msg).'</p></div>';
    if($err)echo '<div class="notice notice-error"><p>'.$F($err).'</p></div>';
    if(!$ctrls){ echo '<p>Das aktive Theme bietet keine Einstellungen an, die hier bearbeitet werden können.</p></div>';return; }
    $secs=[];foreach($ctrls as $c)$secs[$c->section][]=$c;
    uksort($secs,fn($a,$b)=>((int)($m->sections[$a]->priority??160))<=>((int)($m->sections[$b]->priority??160)));
    echo '<form method="post" action="">'.wp_nonce_field('rrw-customize','_wpnonce',false,false);
    foreach($secs as $sid=>$list){
        $title=$m->sections[$sid]->title??$sid;usort($list,fn($a,$b)=>((int)$a->priority)<=>((int)$b->priority));
        echo '<div class="postbox"><h2>'.$F($title).'</h2><table class="form-table" role="presentation">';
        foreach($list as $c){
            $s=$c->setting_obj();$f=rrw_wp_customize_field($s->id);$val=$s->value();$name=esc_attr($f);$at='';
            foreach((array)$c->input_attrs as $k=>$v)$at.=' '.esc_attr($k).'="'.esc_attr((string)$v).'"';
            echo '<tr><th scope="row"><label for="'.$name.'">'.$F($c->label?:$s->id).'</label></th><td>';
            switch($c->type){
                case 'checkbox': echo '<input type="checkbox" id="'.$name.'" name="'.$name.'" value="1"'.checked((bool)$val,true,false).'>';break;
                case 'textarea': echo '<textarea id="'.$name.'" name="'.$name.'" rows="5"'.$at.'>'.esc_textarea((string)$val).'</textarea>';break;
                case 'select': case 'dropdown-pages':
                    echo '<select id="'.$name.'" name="'.$name.'">';
                    $ch=$c->type==='dropdown-pages'?array_column(array_map(fn($p)=>['id'=>(string)$p->ID,'t'=>$p->post_title],get_pages()?:[]),'t','id'):(array)$c->choices;
                    if($c->type==='dropdown-pages')echo '<option value="0">— Seite wählen —</option>';
                    foreach($ch as $k=>$t)echo '<option value="'.esc_attr((string)$k).'"'.selected((string)$val,(string)$k,false).'>'.$F(is_array($t)?($t['label']??$k):$t).'</option>';
                    echo '</select>';break;
                case 'radio':
                    foreach((array)$c->choices as $k=>$t)echo '<label style="display:block"><input type="radio" name="'.$name.'" value="'.esc_attr((string)$k).'"'.checked((string)$val,(string)$k,false).'> '.$F($t).'</label>';break;
                case 'color': echo '<input type="color" id="'.$name.'" name="'.$name.'" value="'.esc_attr(preg_match('/^#[0-9a-f]{6}$/i',(string)$val)?(string)$val:'#000000').'">';break;
                case 'image': echo '<input type="text" class="regular-text" id="'.$name.'" name="'.$name.'" value="'.esc_attr((string)$val).'" placeholder="Bild-Adresse (https://…)">'.($val&&filter_var($val,FILTER_VALIDATE_URL)?'<br><img src="'.esc_url((string)$val).'" alt="" style="max-width:160px;margin-top:6px">':'');break;
                case 'number': case 'range': case 'url': case 'email': case 'date': echo '<input type="'.esc_attr($c->type).'" id="'.$name.'" name="'.$name.'" value="'.esc_attr((string)$val).'"'.$at.'>';break;
                default: echo '<input type="text" class="regular-text" id="'.$name.'" name="'.$name.'" value="'.esc_attr(is_scalar($val)?(string)$val:'').'"'.$at.'>';
            }
            if($c->description)echo '<p class="description">'.wp_kses_post((string)$c->description).'</p>';
            echo '</td></tr>';
        }
        echo '</table></div>';
    }
    submit_button();echo '</form></div>';
}
