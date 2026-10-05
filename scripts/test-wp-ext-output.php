<?php
// Prüft die ergänzenden Ausgabe-Funktionen der WordPress-Schicht (cms/wp/core/ext/output-*.php): Formatierung, kses, Links, Feed, Einbettung,
// Seitenausgabe, Admin-Leiste, Robots, Shortcodes, Menüs. Aufruf: php scripts/test-wp-ext-output.php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rrw-extout-'.bin2hex(random_bytes(4));mkdir($tmp);mkdir($tmp.'/wp-content');mkdir($tmp.'/wp-content/plugins');mkdir($tmp.'/cms');
define('WP_CONTENT_DIR',$tmp.'/wp-content');define('RRW_WP_DATA',$tmp.'/cms/.wp');define('RRW_WP_CMS_DATA',$tmp.'/cms');
$_SERVER['HTTP_HOST']='example.test';file_put_contents($tmp.'/cms/site.json','{}');$GLOBALS['RRW_SITE']=[];
require __DIR__.'/_testdb.php';
require __DIR__.'/../cms/wp/load.php';
$fail=0;$n=0;
function t(string $name,bool $ok,string $extra=''): void { global $fail,$n; $n++; if(!$ok){$fail++;echo "FEHLER: $name $extra\n";} }
rrw_wp_boot(['theme'=>false,'user'=>['id'=>1,'login'=>'admin','name'=>'Administration','email'=>'a@example.test','role'=>'administrator']]);

// Alle Funktionen der Liste müssen existieren (Ausnahmeliste: bewusst ausgelassen)
$skip=[];
$list=[];foreach(['formatting','kses','links','feed','embed','general','adminbar'] as $f)$list[]=file_get_contents(__DIR__.'/../cms/wp/core/ext/output-'.$f.'.php');
preg_match_all("/if\(!function_exists\('([A-Za-z0-9_]+)'\)\)\{\s*(?:\/\*\*.*?\*\/\s*)?function \1\b/s",implode("\n",$list),$m);
$missing=[];foreach(array_unique($m[1]) as $fn)if(!in_array($fn,$skip,true)&&!function_exists($fn))$missing[]=$fn;
t('Alle in ext/output-*.php deklarierten Funktionen existieren',!$missing,implode(',',$missing));
$listFile='/tmp/claude-0/lists/D-output.txt';
if(is_file($listFile)){ $miss=[];foreach(file($listFile,FILE_IGNORE_NEW_LINES) as $l){ $fn=explode("\t",$l)[0];if($fn!==''&&!in_array($fn,$skip,true)&&!function_exists($fn))$miss[]=$fn; } t('Alle Funktionen der Vorgabeliste existieren',!$miss,implode(',',$miss)); }

// ── Formatierung ──
t('wp_html_split trennt Tags',wp_html_split('a<b class="x">c</b>')===['a','<b class="x">','c','</b>','']);
t('wp_html_split: Kommentar bleibt ein Stück',in_array('<!-- x > y -->',wp_html_split('a<!-- x > y -->b'),true));
t('_get_wptexturize_shortcode_regex',(bool)preg_match('/'._get_wptexturize_shortcode_regex(['gallery','caption']).'/','[caption id=1]'));
$st=[];_wptexturize_pushpop_element('<pre class="a">',$st,['pre','code']);t('pushpop: öffnet',$st===['pre']);
_wptexturize_pushpop_element('</pre>',$st,['pre','code']);t('pushpop: schließt',$st===[]);
t('wptexturize_primes: 5\'10" → Primes',wptexturize_primes('Maß 5\'10"','"','&#8243;','&#8220;','&#8221;')==='Maß 5\'10&#8243;');
t('_autop_newline_preservation_helper',_autop_newline_preservation_helper(["a\nb"])==="a<WPPreserveNewline />b");
t('seems_utf8',seems_utf8('Grüße')&&!seems_utf8("\xff\xfe"));
t('utf8_uri_encode: Umlaut',utf8_uri_encode('aä')==='a%C3%A4');
t('utf8_uri_encode: Länge begrenzt',utf8_uri_encode('ääää',7)==='%C3%A4');
t('utf8_uri_encode: ASCII kodiert',utf8_uri_encode('a b',0,true)==='a%20b');
t('sanitize_locale_name',sanitize_locale_name('de_DE<script>')==='de_DEscript'&&sanitize_locale_name('pt-BR')==='pt-BR');
t('convert_invalid_entities',convert_invalid_entities('&#128; &#x93; &#65;')==='&#8364; &#8220; &#65;');
t('format_to_edit: maskiert',format_to_edit('<b>"x"</b>')==='&lt;b&gt;&quot;x&quot;&lt;/b&gt;'&&format_to_edit('<b>',true)==='<b>');
t('format_for_editor',format_for_editor('<p>"x"</p>')==='&lt;p&gt;"x"&lt;/p&gt;');
t('backslashit',backslashit('abc')==='\\a\\b\\c'&&backslashit('1a')==='\\\\1\\a');
t('urldecode_deep',urldecode_deep(['a%20b','c'=>['d%2Fe']])===['a b','c'=>['d/e']]);
$as=antispambot('a@b.de');t('antispambot: dekodiert zurück',rawurldecode(html_entity_decode($as,ENT_QUOTES,'UTF-8'))==='a@b.de'&&!str_contains($as,'@'));
t('_make_url_clickable_cb',_make_url_clickable_cb(['',' ','http://example.com/a','.'])===' <a href="http://example.com/a" rel="nofollow">http://example.com/a</a>.');
t('_make_url_clickable_cb: Klammer im Suffix',str_ends_with(_make_url_clickable_cb(['','(','http://example.com/a',')']),'</a>)'));
t('_make_web_ftp_clickable_cb',_make_web_ftp_clickable_cb(['',' ','www.example.com,'])===' <a href="http://www.example.com" rel="nofollow">http://www.example.com</a>,');
t('_make_email_clickable_cb',_make_email_clickable_cb(['',' ','a','b.de'])===' <a href="mailto:a@b.de">a@b.de</a>');
t('_make_clickable_rel_attr',_make_clickable_rel_attr('https://x.de')===' rel="nofollow"'&&_make_clickable_rel_attr('mailto:a@b.de')==='');
t('_split_str_by_whitespace',_split_str_by_whitespace('aa bb cc dd',5)===['aa bb ','cc dd']);
t('wp_rel_callback: ergänzt rel',wp_rel_callback([1=>'href="/x" rel="external"'],'nofollow')==='<a href="/x" rel="external nofollow">');
t('wp_rel_nofollow_callback: extern',str_contains(wp_rel_nofollow_callback([1=>'href="https://fremd.example/x"']),'rel="nofollow"'));
t('wp_rel_nofollow_callback: eigene Adresse',wp_rel_nofollow_callback([1=>'href="http://example.test/x"'])==='<a href="http://example.test/x">');
t('wp_rel_ugc',str_contains(stripslashes(wp_rel_ugc('<a href="/x">y</a>')),'rel="nofollow ugc"'));
t('wp_targeted_link_rel_callback: _blank',wp_targeted_link_rel_callback([0=>'<a href="/x" target="_blank">',1=>'href="/x" target="_blank"'])==='<a href="/x" target="_blank" rel="noopener">');
t('wp_targeted_link_rel_callback: ohne target',wp_targeted_link_rel_callback([0=>'<a href="/x">',1=>'href="/x"'])==='<a href="/x">');
wp_init_targeted_link_rel_filters();t('Targeted-Link-Filter gesetzt',has_filter('content_save_pre','wp_targeted_link_rel')!==false);
wp_remove_targeted_link_rel_filters();t('Targeted-Link-Filter entfernt',has_filter('content_save_pre','wp_targeted_link_rel')===false);
$GLOBALS['wpsmiliestrans']=[':)'=>'icon_smile.gif'];
t('translate_smiley',str_contains(translate_smiley([':) ']),'class="wp-smiley"')&&translate_smiley([])==='');
t('wp_iso_descrambler',wp_iso_descrambler('=?iso-8859-1?Q?Gr=FC=DFe_dir?=')==="Gr\xFC\xDFe dir");
t('iso8601_to_datetime user',iso8601_to_datetime('20240131T12:30:00')==='2024-01-31 12:30:00');
t('iso8601_to_datetime gmt',iso8601_to_datetime('20240131T12:30:00+0200','gmt')==='2024-01-31 10:30:00');
t('ent2ncr',ent2ncr('a&nbsp;b&amp;c&quot;')==='a&#160;b&#38;c&#34;');
t('_deep_replace',_deep_replace('%0d','a%%0d0dbc')==='abc');
t('htmlentities2: Entität bleibt',htmlentities2('ä &amp; é')==='&auml; &amp; &eacute;');
t('wp_pre_kses_less_than_callback',wp_pre_kses_less_than_callback(['<b'])==='&lt;b'&&wp_pre_kses_less_than_callback(['<b>'])==='<b>');
t('wp_pre_kses_block_attributes: bereinigt Attribut',str_contains(wp_pre_kses_block_attributes('<!-- wp:x {"t":"<script>a</script>b"} /-->',wp_kses_allowed_html('post'),wp_allowed_protocols()),'ab'));
t('wp_pre_kses_block_attributes: unverändert',($c='<!-- wp:x {"t":"b"} /-->')===wp_pre_kses_block_attributes($c,wp_kses_allowed_html('post'),wp_allowed_protocols()));
t('wp_sprintf',wp_sprintf('%s und %d',"A",3)==='A und 3'&&wp_sprintf('100%% %s','x')==='100% x');
t('wp_sprintf: %l und nummeriert',wp_sprintf('Tags: %l',['a','b'])==='Tags: a, b'&&wp_sprintf('%2$s %1$s','a','b')==='b a');
t('links_add_base_url',links_add_base_url('<a href="x/y.html"><img src="/i.png"><a href="https://a.de/">','http://base.de/dir/page')==='<a href="http://base.de/dir/x/y.html"><img src="http://base.de/i.png"><a href="https://a.de/">');
$GLOBALS['_links_add_target']='_top';t('_links_add_target',_links_add_target([1=>' href="/x" target="_blank"'])==='<a href="/x" target="_top">');
t('normalize_whitespace',normalize_whitespace(" a \t b\r\n\n\nc ")==="a b\nc");
t('sanitize_trackback_urls',sanitize_trackback_urls("http://a.de/t  javascript:x \nhttps://b.de/")==="http://a.de/t\nhttps://b.de/");
t('get_url_in_content',get_url_in_content('x <a class="a" href="https://a.de/p">y</a>')==='https://a.de/p'&&get_url_in_content('nix')===false);
t('wp_spaces_regexp',str_contains(wp_spaces_regexp(),'&nbsp;'));
t('url_shorten',url_shorten('https://www.example.com/')==='example.com'&&strlen(url_shorten('http://example.com/'.str_repeat('a',60)))===32+strlen('&hellip;'));
t('maybe_hash_hex_color',maybe_hash_hex_color('fff')==='#fff'&&maybe_hash_hex_color('rot')==='rot');
wp_enqueue_emoji_styles();t('wp_enqueue_emoji_styles',wp_style_is('wp-emoji-styles','enqueued'));
t('Emoji-Platzhalter',wp_staticize_emoji_for_email(['x'=>1])===['x'=>1]&&in_array('&#x1f600;',_wp_emoji_list('entities'),true)&&in_array('😀',_wp_emoji_list('chars'),true));

// ── kses ──
t('wp_kses_version',wp_kses_version()==='0.2.2');
t('wp_kses_uri_attributes',in_array('href',wp_kses_uri_attributes(),true));
t('valid_unicode',valid_unicode(65)&&valid_unicode(10)&&!valid_unicode(0)&&!valid_unicode(55296)&&valid_unicode(128512));
t('wp_kses_normalize_entities',wp_kses_normalize_entities('&amp; &foo; &#0065; &#x41; &nbsp; &#1;')==='&amp; &amp;foo; &#065; &#x41; &nbsp; &amp;#1;');
t('wp_kses_decode_entities',wp_kses_decode_entities('&#106;&#x61;va')==='java');
t('wp_kses_bad_protocol_once',wp_kses_bad_protocol_once('javascript:alert(1)',['http','https'])==='alert(1)'&&wp_kses_bad_protocol_once('https://a.de',['http','https'])==='https://a.de');
t('wp_kses_bad_protocol_once2',wp_kses_bad_protocol_once2('HTTP',['http'])==='http:'&&wp_kses_bad_protocol_once2('ftp',['http'])==='');
t('wp_kses_named_entities / xml',wp_kses_named_entities([1=>'1',1=>'amp'])==='&amp;'&&wp_kses_xml_named_entities([1=>'nbsp'])==='&amp;nbsp;');
t('wp_kses_stripslashes / array_lc',wp_kses_stripslashes('a\\"b')==='a"b'&&wp_kses_array_lc(['A'=>['Href'=>true]])===['a'=>['href'=>true]]);
t('wp_kses_html_error',wp_kses_html_error('a="b c" d')==='d');
t('wp_kses_hair_parse',wp_kses_hair_parse(' href="x y" title=\'z\' disabled')===[' href="x y"',' title=\'z\'',' disabled']);
t('wp_kses_attr_parse',wp_kses_attr_parse('<a href="x" />')===['<a ','href="x"',' />']);
$h=wp_kses_hair(' href="javascript:x" class=foo disabled class="b"',['http','https']);
t('wp_kses_hair',$h['href']['value']==='x'&&$h['class']['whole']==='class="foo"'&&$h['disabled']['vless']==='y'&&count($h)===3);
t('wp_kses_attr',wp_kses_attr('a',' href="https://a.de" onclick="x" rel=y',wp_kses_allowed_html('post'),wp_allowed_protocols())==='<a href="https://a.de" rel="y">');
t('wp_kses_attr: Element ohne Attribute',wp_kses_attr('br',' class="x" /',['br'=>[]],['http'])==='<br />');
$n1='href';$v1='x';$w1='href="x"';$l1='n';$n2='onclick';$v2='x';$w2='onclick="x"';$l2='n';
t('wp_kses_attr_check',wp_kses_attr_check($n1,$v1,$w1,$l1,'a',['a'=>['href'=>true]])&&!wp_kses_attr_check($n2,$v2,$w2,$l2,'a',['a'=>['href'=>true]])&&$w2==='');
$nm='data-x';$vv='1';$wh='data-x="1"';$vl='n';t('wp_kses_attr_check: Platzhalter data-*',wp_kses_attr_check($nm,$vv,$wh,$vl,'a',['a'=>['data-*'=>true]]));
t('wp_kses_check_attr_val',wp_kses_check_attr_val('abc','n','maxlen',2)===false&&wp_kses_check_attr_val('b','n','values',['a','b'])&&wp_kses_check_attr_val('5','n','maxval',9)&&!wp_kses_check_attr_val('x','n','minval',1));
t('wp_kses_one_attr',wp_kses_one_attr('href="javascript:alert(1)"','a')==='href="alert(1)"'&&wp_kses_one_attr(' class="x" ','p')===' class="x" '&&wp_kses_one_attr('onclick="x"','p')==='');
t('wp_kses_one_attr: unvollständige Anführungszeichen',wp_kses_one_attr('title="abc','p')==='');
t('wp_kses_split2: Tag/Schluss-Tag/Komma',wp_kses_split2('<b class="x" onclick="y">',wp_kses_allowed_html('post'),wp_allowed_protocols())==='<b class="x">'&&wp_kses_split2('</script>',wp_kses_allowed_html('post'),wp_allowed_protocols())===''&&wp_kses_split2('>','post',[])==='&gt;');
t('wp_kses_split',wp_kses_split('a<b>c</b><script>x</script>','post',wp_allowed_protocols())==='a<b>c</b>x');
t('wp_kses_hook',wp_kses_hook('x','post',[])==='x');
t('_wp_add_global_attributes',isset(_wp_add_global_attributes(true)['class'])&&isset(_wp_add_global_attributes(['href'=>true])['href']));
t('safecss_filter_attr: erlaubt/entfernt',safecss_filter_attr('color: red; behavior: url(x); margin:0')==='color: red;margin:0'||safecss_filter_attr('color: red; behavior: url(x); margin:0')==='color: red; margin:0');
t('safecss_filter_attr: url mit js abgelehnt',safecss_filter_attr('background-image: url(javascript:alert(1))')===''&&safecss_filter_attr('background-image: url(https://a.de/i.png)')!=='');
t('safecss_filter_attr: calc/var, Kommentare, Klammern',safecss_filter_attr('width: calc(100% - 10px)')!==''&&safecss_filter_attr('color: var(--x)')!==''&&safecss_filter_attr('width: expression(alert(1))')===''&&safecss_filter_attr('color: red/*x*/')==='');
t('safecss_filter_attr: Verlauf',safecss_filter_attr('background: linear-gradient(90deg, rgb(0,0,0) 0%, #fff 100%)')!=='');
t('wp_filter_global_styles_post',str_contains(wp_filter_global_styles_post(wp_json_encode(['isGlobalStylesUserThemeJSON'=>true,'styles'=>['color'=>['text'=>'red','background'=>'expression(x)']]])),'red')&&!str_contains(wp_filter_global_styles_post(wp_json_encode(['isGlobalStylesUserThemeJSON'=>true,'styles'=>['color'=>['background'=>'expression(x)']]])),'expression'));
t('wp_filter_global_styles_post: fremdes JSON unverändert',wp_filter_global_styles_post('{"a":1}')==='{"a":1}');

// ── Inhalte für Links/Feeds ──
$cat=wp_insert_term('Musik','category');$cid=is_array($cat)?(int)$cat['term_id']:0;
$ids=[];foreach(['Eins','Zwei','Drei'] as $i=>$tt){ $ids[]=wp_insert_post(['post_title'=>$tt,'post_content'=>"Inhalt $tt",'post_status'=>'publish','post_date'=>'2024-01-0'.($i+1).' 10:00:00','post_category'=>[$cid]]); }
t('Testbeiträge angelegt',count(array_filter($ids))===3);
$GLOBALS['wp_query']=new WP_Query(['p'=>$ids[1],'post_type'=>'post']);   // Einzelansicht
$GLOBALS['post']=get_post($ids[1]);
// ── Links ──
t('get_term_feed_link',str_ends_with((string)get_term_feed_link($cid),'/feed/')&&get_category_feed_link($cid)===get_term_feed_link($cid,'category'));
t('get_term_feed_link: Typ atom',str_ends_with((string)get_term_feed_link($cid,'category','atom'),'/feed/atom/'));
t('get_term_feed_link: unbekannter Begriff',get_term_feed_link(99999991)===false);
t('get_author_feed_link',str_contains(get_author_feed_link(1),'/author/')&&str_ends_with(get_author_feed_link(1),'/feed/')&&get_author_feed_link(999999)==='');
t('get_search_feed_link',str_contains(get_search_feed_link('a b'),'feed=rss2')&&str_contains(get_search_comments_feed_link('x'),'feed=comments-rss2'));
t('get_post_type_archive_feed_link: post → Feed',get_post_type_archive_feed_link('post')===get_feed_link());
t('get_edit_term_link',is_string(get_edit_term_link($cid,'category'))&&str_contains((string)get_edit_term_link($cid,'category'),'tag_ID='.$cid));
t('get_edit_tag_link',get_edit_tag_link($cid,'category')===get_edit_term_link($cid,'category'));
t('edit_term_link',str_contains((string)edit_term_link('',' [',']',get_term($cid,'category'),false),'Bearbeiten'));
t('get_edit_bookmark_link: ohne Blogroll null',get_edit_bookmark_link(1)===null);
t('permalink_anchor',(function(){ ob_start();permalink_anchor();$o=ob_get_clean();return str_contains($o,'<a id="post-');})());
t('wp_force_plain_post_permalink: veröffentlicht',wp_force_plain_post_permalink($ids[1])===false);
$dr=wp_insert_post(['post_title'=>'Entwurf','post_status'=>'draft']);t('wp_force_plain_post_permalink: Entwurf',wp_force_plain_post_permalink($dr)===true);
$pg=wp_insert_post(['post_title'=>'Impressum','post_type'=>'page','post_status'=>'publish']);t('_get_page_link',str_contains((string)_get_page_link($pg),'impressum'));
t('the_feed_link',(function(){ ob_start();the_feed_link('Feed');return str_contains(ob_get_clean(),'>Feed</a>'); })());
t('post_comments_feed_link',(function() use($ids){ ob_start();post_comments_feed_link('Kommentare',$ids[1]);return str_contains(ob_get_clean(),'>Kommentare</a>'); })());
t('_navigation_markup',_navigation_markup('<a>x</a>','pager','Seiten','Seitenliste')==='<nav class="navigation pager" aria-label="Seitenliste"><h2 class="screen-reader-text">Seiten</h2><div class="nav-links"><a>x</a></div></nav>');
t('get_previous_post_link / get_next_post_link',str_contains((string)get_previous_post_link(),'Eins')&&str_contains((string)get_next_post_link(),'Drei'));
t('get_adjacent_post_rel_link',str_contains((string)get_adjacent_post_rel_link('%title',false,'',true),"rel='prev' title='Eins'")&&str_contains((string)get_adjacent_post_rel_link('%title',false,'',false),"rel='next'"));
t('adjacent_posts_rel_link_wp_head / prev / next',(function(){ ob_start();adjacent_posts_rel_link_wp_head();prev_post_rel_link();next_post_rel_link();$o=ob_get_clean();return substr_count($o,'<link rel=')===4; })());
$b=get_boundary_post(false,'',true);$e=get_boundary_post(false,'',false);
t('get_boundary_post',is_array($b)&&$b&&(int)$b[0]->ID===(int)$ids[0]&&(int)$e[0]->ID===(int)$ids[2]);
t('adjacent_post_link',(function(){ ob_start();adjacent_post_link('%link','%title',false,'',false);return str_contains(ob_get_clean(),'Drei'); })());
t('get_posts_nav_link: Einzelansicht leer',get_posts_nav_link()==='');
t('get_next/previous_comments_link: ohne Seiten',get_next_comments_link()===null&&get_previous_comments_link()===null);
t('get_the_comments_navigation / pagination leer',get_the_comments_navigation()===''&&get_the_comments_pagination()==='');
t('user_admin_url / get_edit_profile_url',str_contains(user_admin_url('x.php'),'wp-admin/user/x.php')&&str_contains(get_edit_profile_url(1),'profile.php'));
t('wp_get_canonical_url',wp_get_canonical_url($ids[1])===get_permalink($ids[1])&&wp_get_canonical_url($dr)===false);
t('wp_get_shortlink',wp_get_shortlink($ids[1])===home_url('?p='.$ids[1])&&wp_get_shortlink(0,'query')!==''&&wp_get_shortlink(999999991)==='');
t('the_shortlink',(function(){ ob_start();the_shortlink('Kurz','T','<i>','</i>');$o=ob_get_clean();return str_contains($o,'rel="shortlink"')&&str_starts_with($o,'<i>'); })());
t('is_avatar_comment_type',is_avatar_comment_type('comment')&&is_avatar_comment_type('')&&!is_avatar_comment_type('pingback'));
$av=get_avatar_data('Test@Example.com',['size'=>48]);
t('get_avatar_data: E-Mail',$av['found_avatar']===true&&str_contains($av['url'],md5('test@example.com'))&&str_contains($av['url'],'s=48'));
t('get_avatar_data: Benutzer + Standardbild',str_contains(get_avatar_data(1,['default'=>'identicon'])['url'],'d=identicon'));
t('get_avatar_data: Pingback ohne Avatar',get_avatar_data((object)['comment_ID'=>1,'comment_type'=>'pingback','user_id'=>0,'comment_author_email'=>'','comment_post_ID'=>1])['url']===false);
t('wp_internal_hosts / wp_is_internal_link',in_array('example.test',wp_internal_hosts(),true)&&wp_is_internal_link('http://example.test/x')&&!wp_is_internal_link('https://fremd.de/')&&!wp_is_internal_link('/relativ'));

// ── Feed ──
t('get_default_feed',get_default_feed()==='rss2');
t('feed_content_type',feed_content_type('atom')==='application/atom+xml'&&feed_content_type()==='application/rss+xml'&&feed_content_type('x')==='application/octet-stream');
t('prep_atom_text_construct',prep_atom_text_construct('Text')===['text','Text']&&prep_atom_text_construct('<b>a</b>')[0]==='xhtml'&&prep_atom_text_construct('<b>a')[0]==='html');
t('get_feed_build_date',(bool)preg_match('/^\d{4}-\d\d-\d\d/',get_feed_build_date('Y-m-d')));
t('get_self_link / self_link',str_starts_with(get_self_link(),'http'));
t('html_type_rss',(function(){ ob_start();html_type_rss();return ob_get_clean()==='html'; })());
t('get_the_title_rss / the_title_rss',(function(){ ob_start();the_title_rss();return ob_get_clean()===get_the_title_rss(); })());
$q=new WP_Query(['p'=>$ids[1]]);$GLOBALS['wp_query']=$q;while($q->have_posts()){ $q->the_post(); }
t('get_the_content_feed',str_contains(get_the_content_feed(),'Inhalt Zwei'));
t('the_permalink_rss / comments_link_feed',(function(){ ob_start();the_permalink_rss();comments_link_feed();return str_contains(ob_get_clean(),'#comments'); })());
t('get_the_category_rss',str_contains(get_the_category_rss('rss2'),'<category><![CDATA[Musik]]></category>')&&str_contains(get_the_category_rss('atom'),'term="Musik"')&&str_contains(get_the_category_rss('rdf'),'dc:subject'));
add_post_meta($ids[1],'enclosure',"https://example.test/a.mp3\n1234\naudio/mpeg mp3");
t('rss_enclosure / atom_enclosure',(function(){ ob_start();rss_enclosure();atom_enclosure();$o=ob_get_clean();return str_contains($o,'<enclosure url="https://example.test/a.mp3" length="1234" type="audio/mpeg" />')&&str_contains($o,'rel="enclosure"'); })());
t('get_comment_guid: ohne Kommentar false',get_comment_guid(999999)===false);
t('rss2_site_icon / atom_site_icon ohne Icon',(function(){ ob_start();rss2_site_icon();atom_site_icon();return ob_get_clean()===''; })());
t('fetch_feed: ungültige Adresse',is_wp_error(fetch_feed('ftp://x')));
$f=RRW_WP_Feed::parse('<rss version="2.0"><channel><title>T</title><link>http://a.de/</link><description>D</description><item><title>I1</title><link>http://a.de/1</link><description>d1</description><pubDate>Mon, 01 Jan 2024 10:00:00 +0000</pubDate></item></channel></rss>');
t('RRW_WP_Feed: RSS',$f&&$f->get_title()==='T'&&$f->get_item_quantity()===1&&$f->get_items()[0]->get_permalink()==='http://a.de/1'&&$f->get_items()[0]->get_date('Y-m-d')==='2024-01-01');
$f=RRW_WP_Feed::parse('<feed xmlns="http://www.w3.org/2005/Atom"><title>AT</title><link href="http://a.de/"/><entry><title>E</title><link href="http://a.de/e"/><id>x</id><updated>2024-02-03T10:00:00Z</updated><summary>S</summary></entry></feed>');
t('RRW_WP_Feed: Atom',$f&&$f->get_title()==='AT'&&$f->get_link()==='http://a.de/'&&$f->get_items(0,1)[0]->get_title()==='E'&&$f->get_items()[0]->get_description()==='S');
t('RRW_WP_Feed: kein Feed',RRW_WP_Feed::parse('<html/>')===null);

// ── Einbettung ──
wp_oembed_add_provider('https://*.beispiel.de/v/*','https://beispiel.de/oembed');
t('wp_oembed_add_provider: Platzhalter',rrw_ext_oembed_provider_for('https://x.beispiel.de/v/12')==='https://beispiel.de/oembed');
t('wp_oembed_add_provider: eingebauter Anbieter',rrw_ext_oembed_provider_for('https://www.youtube.com/watch?v=abc')==='https://www.youtube.com/oembed');
t('wp_oembed_remove_provider',wp_oembed_remove_provider('https://*.beispiel.de/v/*')&&rrw_ext_oembed_provider_for('https://x.beispiel.de/v/12')===false&&!wp_oembed_remove_provider('gibt-es-nicht'));
t('wp_embed_handler_youtube',str_contains(wp_embed_handler_youtube([0,'','abc'],['width'=>300,'height'=>200],'https://youtube.com/embed/abc',[]),'src="https://www.youtube.com/embed/abc"'));
t('wp_embed_handler_audio / video',wp_embed_handler_audio([],[],'https://a.de/x.mp3',[])==='[audio src="https://a.de/x.mp3" /]'&&wp_embed_handler_video([],[],'https://a.de/x.mp4',['width'=>3,'height'=>2])==='[video width="3" height="2" src="https://a.de/x.mp4" /]');
t('get_post_embed_url',str_contains((string)get_post_embed_url($ids[1]),'embed=true'));
t('get_oembed_endpoint_url',str_contains(get_oembed_endpoint_url('http://example.test/x/'),'oembed/1.0/embed')&&str_contains(get_oembed_endpoint_url('http://a/','xml'),'format=xml'));
t('get_post_embed_html',(function() use($ids){ $h=get_post_embed_html(500,300,$ids[1]);return str_contains($h,'<blockquote class="wp-embedded-content"')&&str_contains($h,'width="500"')&&str_contains($h,'#?secret='); })());
$d=get_oembed_response_data($ids[1],1000);
t('get_oembed_response_data',$d['type']==='link'&&$d['title']==='Zwei'&&$d['version']==='1.0'&&get_oembed_response_data($dr,500)===false);
$r=get_oembed_response_data_rich($d,get_post($ids[1]),600,338);
t('get_oembed_response_data_rich',$r['type']==='rich'&&$r['width']===600&&str_contains($r['html'],'<iframe'));
t('get_oembed_response_data_for_url',get_oembed_response_data_for_url(get_permalink($ids[1]),['width'=>400])===false||is_array(get_oembed_response_data_for_url(get_permalink($ids[1]),['width'=>400])));
t('wp_oembed_ensure_format',wp_oembed_ensure_format('xml')==='xml'&&wp_oembed_ensure_format('html')==='json');
t('_oembed_create_xml',_oembed_create_xml(['type'=>'rich','a'=>['b'=>'<x>']])==="<?xml version=\"1.0\"?>\n<oembed><type>rich</type><a><b>&lt;x&gt;</b></a></oembed>\n"&&_oembed_create_xml([])===false);
t('wp_filter_oembed_iframe_title_attribute',wp_filter_oembed_iframe_title_attribute('<iframe src="x"></iframe>',(object)['title'=>'Titel'],'u')==='<iframe title="Titel" src="x"></iframe>'
    &&wp_filter_oembed_iframe_title_attribute('<iframe title="alt" src="x"></iframe>',(object)['title'=>'Neu'],'u')==='<iframe title="Neu" src="x"></iframe>'&&wp_filter_oembed_iframe_title_attribute(false,(object)[],'u')===false);
t('_oembed_filter_feed_content',!str_contains(_oembed_filter_feed_content('<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);" src="x">'),'style='));
t('wp_filter_pre_oembed_result: fremde Adresse',wp_filter_pre_oembed_result(null,'https://fremd.de/x',[])===null);
t('wp_embed_excerpt_attachment / excerpt_more',wp_embed_excerpt_attachment('x')==='x'&&wp_embed_excerpt_more(' [...]')===' [...]');
t('the_embed_site_title / print_embed_sharing_button',(function(){ ob_start();the_embed_site_title();print_embed_sharing_button();$o=ob_get_clean();return str_contains($o,'wp-embed-site-title')&&str_contains($o,'wp-embed-share'); })());
t('print_embed_sharing_dialog',(function(){ ob_start();print_embed_sharing_dialog();return str_contains(ob_get_clean(),'wp-embed-share-dialog'); })());
t('wp_enqueue_embed_styles',(function(){ wp_enqueue_embed_styles();return wp_style_is('wp-embed-template','enqueued'); })());
t('wp_oembed_register_route',(function(){ wp_oembed_register_route();return true; })());

// ── Seitenausgabe ──
t('wp_registration_url',str_contains(wp_registration_url(),'action=register'));
$lf=wp_login_form(['echo'=>false,'redirect'=>'/ziel','remember'=>false]);
t('wp_login_form',str_contains($lf,'name="log"')&&str_contains($lf,'name="pwd"')&&str_contains($lf,'value="/ziel"')&&!str_contains($lf,'rememberme'));
t('wp_login_form: Erinnern',str_contains(wp_login_form(['echo'=>false,'value_remember'=>true]),'checked="checked"'));
t('site_icon_url',(function(){ ob_start();site_icon_url(32,'http://a.de/i.png');return ob_get_clean()==='http://a.de/i.png'; })());
t('get_the_post_type_description',get_the_post_type_description()==='');
t('calendar_week_mod',calendar_week_mod(8)==1&&calendar_week_mod(-1)==6);
t('delete_get_calendar_cache',(function(){ wp_cache_set('get_calendar','x','calendar');delete_get_calendar_cache();return wp_cache_get('get_calendar','calendar')===false; })());
t('allowed_tags',str_contains(allowed_tags(),'&lt;a href=&quot;&quot;'));
t('the_date_xml / the_weekday',(function(){ ob_start();the_date_xml();$a=ob_get_clean();ob_start();the_weekday();$w=ob_get_clean();return $a==='2024-01-02'&&$w!==''; })());
t('the_weekday_date: nur einmal je Tag',(function(){ $GLOBALS['previousweekday']=null;ob_start();the_weekday_date('[',']');the_weekday_date('[',']');$o=ob_get_clean();return substr_count($o,'[')===1; })());
t('wp_strict_cross_origin_referrer',(function(){ ob_start();wp_strict_cross_origin_referrer();return str_contains(ob_get_clean(),'strict-origin-when-cross-origin'); })());
add_filter('wp_preload_resources',fn($r)=>[['href'=>'/f.woff2','as'=>'font','type'=>'font/woff2','crossorigin'=>'anonymous'],['href'=>'/x','as'=>'kaputt'],['as'=>'image']]);
t('wp_preload_resources',(function(){ ob_start();wp_preload_resources();$o=ob_get_clean();return substr_count($o,'rel=\'preload\'')===1&&str_contains($o,"as='font'"); })());
wp_enqueue_script('fremd-js','https://cdn.example.org/x.js');wp_enqueue_style('eigen-css','/a.css');
t('wp_dependencies_unique_hosts',wp_dependencies_unique_hosts()===['cdn.example.org']);
t('wp_required_field_indicator / message',str_contains(wp_required_field_indicator(),'class="required"')&&str_contains(wp_required_field_message(),'required-field-message'));
t('wp_heartbeat_settings',isset(wp_heartbeat_settings([])['ajaxurl'])||is_admin()===false);
t('Editor-Standardwerte (kein TinyMCE)',user_can_richedit()===false&&wp_default_editor()==='html');
$cm=wp_get_code_editor_settings(['type'=>'text/css']);
t('wp_get_code_editor_settings',$cm['codemirror']['mode']==='text/css'&&wp_get_code_editor_settings(['file'=>'a.php'])['codemirror']['mode']==='application/x-httpd-php'&&wp_get_code_editor_settings(['type'=>'unbekannt'])===false);
t('the_search_query',(function(){ ob_start();the_search_query();return ob_get_clean()===''; })());
t('the_generator',(function(){ ob_start();the_generator('html');return str_contains(ob_get_clean(),'generator'); })());
t('wp_admin_css_uri',str_contains(wp_admin_css_uri('colors'),'colors.css?version='));
register_admin_color_schemes();
t('register_admin_color_schemes / wp_admin_css_color',count($GLOBALS['_wp_admin_css_colors'])===9&&$GLOBALS['_wp_admin_css_colors']['fresh']->colors[2]==='#2271b1'&&$GLOBALS['_wp_admin_css_colors']['fresh']->name==='Standard');

// ── Robots ──
update_option('blog_public','1');
t('wp_robots_no_robots (öffentlich)',wp_robots_no_robots([])===['noindex'=>true,'follow'=>true]);
update_option('blog_public','0');
t('wp_robots_no_robots (nicht öffentlich)',wp_robots_no_robots([])===['noindex'=>true,'nofollow'=>true]&&wp_robots_noindex([])===['noindex'=>true,'nofollow'=>true]);
update_option('blog_public','1');
t('wp_robots_noindex (öffentlich)',wp_robots_noindex(['a'=>true])===['a'=>true]);
t('wp_robots_sensitive_page',wp_robots_sensitive_page([])===['noindex'=>true,'noarchive'=>true]);
t('wp_robots_max_image_preview_large',wp_robots_max_image_preview_large([])===['max-image-preview'=>'large']);
t('wp_robots_noindex_embeds / search (keine Suche)',wp_robots_noindex_embeds(['x'=>1])===['x'=>1]&&wp_robots_noindex_search(['x'=>1])===['x'=>1]);

// ── Shortcodes ──
add_shortcode('abc',fn($a,$c='')=>"[$c]");
t('apply_shortcodes',apply_shortcodes('[abc]x[/abc]')==='[x]');
t('get_shortcode_tags_in_content',get_shortcode_tags_in_content('a [abc]b [abc]c[/abc][/abc] [nix]')===['abc','abc']&&get_shortcode_tags_in_content('ohne')===[]);
preg_match_all(get_shortcode_atts_regex(),'a="1" b=\'2\' c=3 "vier" fünf',$am,PREG_SET_ORDER);
t('get_shortcode_atts_regex',count($am)===5&&$am[0][1]==='a'&&$am[3][7]==='vier');
t('unescape_invalid_shortcodes',unescape_invalid_shortcodes('&#91;a&#93;')==='[a]');
t('strip_shortcode_tag',strip_shortcode_tag([0=>'[[abc]]',1=>'[',6=>']'])==='[abc]'&&strip_shortcode_tag([0=>' [abc]',1=>' ',6=>''])===' ');
add_filter('abcfilter',fn($x)=>_filter_do_shortcode_context());
t('_filter_do_shortcode_context',apply_filters('abcfilter','')==='abcfilter');

// ── Menüs ──
$mk=function(int $id,int $parent,string $type,string $obj,int $oid,string $url='#'){ return (object)['ID'=>$id,'db_id'=>$id,'menu_item_parent'=>$parent,'type'=>$type,'object'=>$obj,'object_id'=>$oid,'url'=>$url,'classes'=>['menu-item'],'title'=>'x']; };
$items=[$mk(1,0,'custom','custom',0),$mk(2,1,'post_type','page',(int)$pg,get_permalink($pg)),$mk(3,0,'post_type','page',999)];
$GLOBALS['wp_query']=new WP_Query(['page_id'=>$pg,'post_type'=>'page']);
_wp_menu_item_classes_by_context($items);
t('_wp_menu_item_classes_by_context: aktueller Eintrag',in_array('current-menu-item',$items[1]->classes,true)&&in_array('current_page_item',$items[1]->classes,true));
t('_wp_menu_item_classes_by_context: Eltern',in_array('current-menu-parent',$items[0]->classes,true)&&!in_array('current-menu-item',$items[2]->classes,true));
t('_nav_menu_item_id_use_once',_nav_menu_item_id_use_once('a',(object)['ID'=>77])==='a'&&_nav_menu_item_id_use_once('a',(object)['ID'=>77])===false);
t('wp_nav_menu_remove_menu_item_has_children_class',wp_nav_menu_remove_menu_item_has_children_class(['a','menu-item-has-children'],null,(object)['depth'=>1],0)===['a']&&in_array('menu-item-has-children',wp_nav_menu_remove_menu_item_has_children_class(['menu-item-has-children'],null,(object)['depth'=>0],0),true));
t('walk_nav_menu_tree',str_contains(walk_nav_menu_tree([$mk(5,0,'custom','custom',0,'http://a.de/')],0,(object)['walker'=>null,'menu_class'=>'m']),'<li'));

// ── Admin-Leiste ──
$bar=new WP_Admin_Bar();$bar->initialize();
wp_admin_bar_add_secondary_groups($bar);wp_admin_bar_wp_menu($bar);
t('Admin-Leiste: Gruppen und Logo',$bar->get_node('top-secondary')->group&&$bar->get_node('wp-logo')&&$bar->get_node('wporg')->parent==='wp-logo-external');
wp_admin_bar_new_content_menu($bar);t('Admin-Leiste: Neu-Menü',(bool)$bar->get_node('new-content')&&(bool)$bar->get_node('new-post')&&(bool)$bar->get_node('new-page'));
wp_admin_bar_comments_menu($bar);t('Admin-Leiste: Kommentare',(bool)$bar->get_node('comments'));
wp_admin_bar_search_menu($bar);t('Admin-Leiste: Suche',(bool)$bar->get_node('search')&&$bar->get_node('search')->parent==='top-secondary');
wp_admin_bar_shortlink_menu($bar);wp_admin_bar_customize_menu($bar);wp_admin_bar_edit_site_menu($bar);wp_admin_bar_edit_menu($bar);wp_admin_bar_my_sites_menu($bar);wp_admin_bar_updates_menu($bar);wp_admin_bar_recovery_mode_menu($bar);wp_admin_bar_sidebar_toggle($bar);
t('Admin-Leiste: Multisite/Updates/Wiederherstellung ohne Knoten',!$bar->get_node('updates')&&!$bar->get_node('recovery-mode')&&!$bar->get_node('menu-toggle'));
wp_admin_bar_appearance_menu($bar);t('Admin-Leiste: Aussehen',(bool)$bar->get_node('themes')&&$bar->get_node('themes')->parent==='appearance');
wp_admin_bar_site_menu($bar);t('Admin-Leiste: Website-Menü',(bool)$bar->get_node('site-name')&&(bool)$bar->get_node('dashboard'));
wp_admin_bar_my_account_item($bar);wp_admin_bar_my_account_menu($bar);
t('Admin-Leiste: Konto',(bool)$bar->get_node('my-account')&&(bool)$bar->get_node('logout')&&(bool)$bar->get_node('edit-profile'));
ob_start();$bar->output('');$html=ob_get_clean();t('WP_Admin_Bar::output',str_contains($html,'id="wp-admin-bar-new-content"')&&substr_count($html,'<ul>')>=3);
t('_get_admin_bar_pref',_get_admin_bar_pref('front',1)===true);
t('_wp_admin_bar_init ohne Leiste',_wp_admin_bar_init()===false);
wp_enqueue_admin_bar_header_styles();wp_enqueue_admin_bar_bump_styles();
t('Admin-Leisten-Stile',!empty($GLOBALS['rrw_wp_styles']['reg']['admin-bar']['inline_after']));

echo $fail===0?"OK: $n Prüfungen bestanden\n":"$fail von $n Prüfungen fehlgeschlagen\n";
exit($fail===0?0:1);
