<?php
// Ergänzende WordPress-Funktionen (Bereich Admin, Teil 5): Dashboard (dashboard.php) – Widgets, Aktivität, Schnellentwurf, Feeds, Hinweise.
// Die Boxen werden direkt in $wp_meta_boxes eingetragen (add_meta_box ist im Kern ein Platzhalter) und mit rrw_adm_do_boxes() ausgegeben.
// Es gibt keinen Abruf bei wordpress.org außer den Feed-Widgets (nur beim Aufruf, 12 Stunden zwischengespeichert).

if(!function_exists('rrw_adm_add_box')){ function rrw_adm_add_box($id, $title, $callback, $page, $context='advanced', $priority='default', $args=null) {   // Box in $wp_meta_boxes eintragen
    global $wp_meta_boxes;
    if(!is_array($wp_meta_boxes))$wp_meta_boxes=[];
    $wp_meta_boxes[$page][$context][$priority][$id]=['id'=>$id,'title'=>$title,'callback'=>$callback,'args'=>$args];
} }
if(!function_exists('rrw_adm_do_boxes')){ function rrw_adm_do_boxes($page, $context, $object=null) {   // Boxen eines Bereichs ausgeben; liefert die Anzahl
    global $wp_meta_boxes;$n=0;$hidden=get_hidden_meta_boxes($page);$closed=(array)get_user_option("closedpostboxes_$page");
    foreach(['high','sorted','core','default','low'] as $prio)foreach($wp_meta_boxes[$page][$context][$prio]??[] as $b){
        if(!$b||!is_callable($b['callback']))continue;$n++;
        echo '<div id="'.esc_attr($b['id']).'" class="postbox'.(in_array($b['id'],$closed,true)?' closed':'').(in_array($b['id'],$hidden,true)?' hide-if-js':'').'"><div class="postbox-header"><h2 class="hndle">'.$b['title'].'</h2></div><div class="inside">';
        call_user_func($b['callback'],$object,$b);
        echo '</div></div>';
    }
    return $n;
} }
if(!function_exists('rrw_adm_post_link')){ function rrw_adm_post_link($post) { $p=get_post($post);return $p?admin_url('post.php?post='.(int)$p->ID.'&action=edit'):''; } }

/* ───────── Aufbau ───────── */
if(!function_exists('wp_dashboard_setup')){ function wp_dashboard_setup() {
    global $wp_dashboard_control_callbacks;
    $wp_dashboard_control_callbacks=[];$s=get_current_screen();$sid=$s&&!empty($s->id)?$s->id:'dashboard';
    if(current_user_can('read'))rrw_adm_add_box('dashboard_right_now','Auf einen Blick','wp_dashboard_right_now',$sid,'normal','core');
    if(current_user_can('view_site_health_checks'))rrw_adm_add_box('dashboard_site_health','Zustand der Website','wp_dashboard_site_health',$sid,'normal','core');
    if(current_user_can('edit_posts'))rrw_adm_add_box('dashboard_activity','Aktivität','wp_dashboard_site_activity',$sid,'normal','core');
    if(current_user_can('edit_posts'))rrw_adm_add_box('dashboard_quick_press','Schnellentwurf','wp_dashboard_quick_press',$sid,'side','core');
    rrw_adm_add_box('dashboard_primary','WordPress-Ereignisse und -Neuigkeiten','wp_dashboard_events_news',$sid,'side','core');
    do_action('wp_dashboard_setup');
    foreach((array)apply_filters('wp_dashboard_widgets',[]) as $w)if(is_string($w)&&function_exists($w))rrw_adm_add_box($w,$w,$w,$sid,'normal','core');
} }
if(!function_exists('_wp_dashboard_control_callback')){ function _wp_dashboard_control_callback($dashboard, $meta_box) {   // Einstellungsformular eines Widgets
    echo '<form method="post" class="dashboard-widget-control-form wp-clearfix">';
    wp_dashboard_trigger_widget_control($meta_box['id']);
    wp_nonce_field('edit-dashboard-widget_'.$meta_box['id'],'dashboard-widget-nonce');
    echo '<input type="hidden" name="widget_id" value="'.esc_attr($meta_box['id']).'" /><input type="submit" name="submit" class="button button-primary" value="Absenden" /></form>';
} }
if(!function_exists('wp_dashboard_trigger_widget_control')){ function wp_dashboard_trigger_widget_control($widget_control_id=false) {
    global $wp_dashboard_control_callbacks;
    if(is_scalar($widget_control_id)&&$widget_control_id&&isset($wp_dashboard_control_callbacks[$widget_control_id])&&is_callable($wp_dashboard_control_callbacks[$widget_control_id]))
        call_user_func($wp_dashboard_control_callbacks[$widget_control_id],'',['id'=>$widget_control_id,'args'=>[]]);
} }
if(!function_exists('wp_dashboard')){ function wp_dashboard() {   // Spalten des Dashboards ausgeben
    $s=get_current_screen();$sid=$s&&!empty($s->id)?$s->id:'dashboard';$cols=max(1,min(4,(int)get_user_option('screen_layout_'.$sid)?:2));
    echo '<div id="dashboard-widgets-wrap"><div id="dashboard-widgets" class="metabox-holder columns-'.$cols.'">';
    foreach(['normal','side','column3','column4'] as $i=>$ctx){
        echo '<div id="postbox-container-'.($i+1).'" class="postbox-container"><div id="'.$ctx.'-sortables" class="meta-box-sortables">';
        if(rrw_adm_do_boxes($sid,$ctx,'')===0&&$i<$cols)wp_dashboard_empty();
        echo '</div></div>';
    }
    echo '</div><div class="clear"></div></div>';
} }
if(!function_exists('wp_dashboard_empty')){ function wp_dashboard_empty() { echo '<div class="empty-container" role="region" aria-label="Leerer Bereich"><span>Ziehe Boxen hierher</span></div>'; } }   // Platzhalter in leeren Spalten
if(!function_exists('wp_welcome_panel')){ function wp_welcome_panel() {
    echo '<div class="welcome-panel-content"><div class="welcome-panel-header"><h2>Willkommen bei WordPress!</h2><p><a href="'.esc_url(self_admin_url('about.php')).'">Neuigkeiten in dieser Version</a></p></div><div class="welcome-panel-column-container">';
    echo '<div class="welcome-panel-column"><h3>Erste Schritte</h3><a class="button button-primary" href="'.esc_url(admin_url('themes.php')).'">Website gestalten</a></div>';
    echo '<div class="welcome-panel-column"><h3>Weitere Schritte</h3><ul>';
    if(current_user_can('edit_pages'))echo '<li><a href="'.esc_url(admin_url('post-new.php?post_type=page')).'">Seite anlegen</a></li>';
    if(current_user_can('edit_posts'))echo '<li><a href="'.esc_url(admin_url('post-new.php')).'">Beitrag schreiben</a></li>';
    echo '<li><a href="'.esc_url(home_url('/')).'">Website ansehen</a></li></ul></div></div></div>';
} }

/* ───────── Widgets ───────── */
if(!function_exists('wp_dashboard_right_now')){ function wp_dashboard_right_now() {
    echo '<div class="main"><ul>';
    foreach(['post','page'] as $pt){
        $o=get_post_type_object($pt);if(!$o||!current_user_can($o->cap->edit_posts))continue;
        $n=(int)(wp_count_posts($pt)->publish??0);
        echo '<li class="'.$pt.'-count"><a href="'.esc_url(admin_url('edit.php?post_type='.$pt)).'">'.sprintf('%s %s',number_format_i18n($n),esc_html($pt==='post'?($n===1?'Beitrag':'Beiträge'):($n===1?'Seite':'Seiten'))).'</a></li>';
    }
    if(current_user_can('moderate_comments')){
        $c=wp_count_comments();$a=(int)$c->approved;
        echo '<li class="comment-count"><a href="'.esc_url(admin_url('edit-comments.php')).'">'.sprintf('%s %s',number_format_i18n($a),$a===1?'Kommentar':'Kommentare').'</a></li>';
        if((int)$c->moderated>0)echo '<li class="comment-mod-count"><a href="'.esc_url(admin_url('edit-comments.php?comment_status=moderated')).'">'.sprintf('%s in der Moderation',number_format_i18n($c->moderated)).'</a></li>';
    }
    echo '</ul><p id="wp-version-message"><span id="wp-version">WordPress '.esc_html(get_bloginfo('version','display')).'</span>';
    $t=wp_get_theme();if($t&&$t->get('Name'))echo ' mit Theme <a href="'.esc_url(admin_url('themes.php')).'">'.esc_html($t->get('Name')).'</a>';
    echo '</p>';
    do_action('rightnow_end');do_action('activity_box_end');
    echo '</div>';
} }
if(!function_exists('wp_network_dashboard_right_now')){ function wp_network_dashboard_right_now() {   // Netzwerk-Dashboard: es gibt nur eine Website
    echo '<div class="main"><p>Dies ist keine Multisite-Installation.</p></div>';
} }
if(!function_exists('wp_dashboard_quick_press')){ function wp_dashboard_quick_press($error_msg=false) {   // Schnellentwurf-Formular (legt einen Auto-Entwurf an)
    if(!current_user_can('edit_posts'))return;
    $post=get_default_post_to_edit('post',true);update_user_option(get_current_user_id(),'dashboard_quick_press_last_post_id',(int)$post->ID);
    if($error_msg)echo '<div class="error"><p>'.$error_msg.'</p></div>';
    echo '<form name="post" action="'.esc_url(admin_url('post.php')).'" method="post" id="quick-press" class="initial-form hide-if-no-js">';
    echo '<div class="input-text-wrap" id="title-wrap"><label for="title">Titel</label><input type="text" name="post_title" id="title" autocomplete="off" /></div>';
    echo '<div class="textarea-wrap" id="description-wrap"><label for="content">Inhalt</label><textarea name="content" id="content" rows="3" cols="15" placeholder="Was beschäftigt dich?"></textarea></div>';
    wp_nonce_field('add-post');
    echo '<input type="hidden" name="action" id="quickpost-action" value="post-quickdraft-save" /><input type="hidden" name="post_ID" value="'.(int)$post->ID.'" /><input type="hidden" name="post_type" value="post" />';
    echo '<p class="submit"><input type="submit" name="save" id="save-post" class="button button-primary" value="Speichern" /><br class="clear" /></p></form>';
    wp_dashboard_recent_drafts();
} }
if(!function_exists('wp_dashboard_recent_drafts')){ function wp_dashboard_recent_drafts($drafts=false) {
    if(!$drafts)$drafts=get_posts(['post_type'=>'post','post_status'=>'draft','author'=>get_current_user_id(),'posts_per_page'=>4,'orderby'=>'modified','order'=>'DESC']);
    if(!$drafts)return;
    echo '<div class="drafts"><h2 class="hide-if-no-js">Deine letzten Entwürfe</h2><ul>';
    foreach($drafts as $d){ $t=_draft_or_post_title($d);
        echo '<li><div class="draft-title"><a href="'.esc_url(rrw_adm_post_link($d)).'" aria-label="'.esc_attr(sprintf('„%s“ bearbeiten',$t)).'">'.esc_html($t).'</a><time datetime="'.esc_attr(get_the_time('c',$d)).'">'.esc_html(get_the_time('j. F Y',$d)).'</time></div>';
        $c=wp_trim_words($d->post_content,10);if($c)echo '<p>'.esc_html($c).'</p>';echo '</li>'; }
    echo '</ul></div>';
} }
if(!function_exists('_wp_dashboard_recent_comments_row')){ function _wp_dashboard_recent_comments_row(&$comment, $show_date=true) {
    $c=get_comment($comment);if(!$c)return;$post=get_post($c->comment_post_ID);
    echo '<div id="comment-'.(int)$c->comment_ID.'" class="comment-item '.esc_attr(wp_get_comment_status($c)).'"><div class="dashboard-comment-wrap has-row-actions">';
    echo '<p class="comment-meta"><cite class="comment-author">'.esc_html($c->comment_author).'</cite>';
    if($post)echo ' zu <a href="'.esc_url(get_comment_link($c)).'">'.esc_html(get_the_title($post)).'</a>';
    if($show_date)echo ' <span class="comment-date">'.esc_html(mysql2date('j. F Y',$c->comment_date)).'</span>';
    echo '</p><blockquote><p>'.esc_html(wp_trim_words(wp_strip_all_tags($c->comment_content),20)).'</p></blockquote></div></div>';
} }
if(!function_exists('wp_dashboard_site_activity')){ function wp_dashboard_site_activity() {
    echo '<div id="activity-widget">';
    $f=wp_dashboard_recent_posts(['max'=>5,'status'=>'future','order'=>'ASC','title'=>'Bald veröffentlicht','id'=>'future-posts']);
    $p=wp_dashboard_recent_posts(['max'=>5,'status'=>'publish','order'=>'DESC','title'=>'Kürzlich veröffentlicht','id'=>'published-posts']);
    $c=current_user_can('edit_posts')?wp_dashboard_recent_comments():false;
    if(!$f&&!$p&&!$c)echo '<div class="no-activity"><p>Keine Aktivität</p></div>';
    echo '</div>';
} }
if(!function_exists('wp_dashboard_recent_posts')){ function wp_dashboard_recent_posts($args) {   // @return bool ob Beiträge ausgegeben wurden
    $q=get_posts(['post_type'=>'post','post_status'=>$args['status'],'orderby'=>'date','order'=>$args['order'],'posts_per_page'=>(int)$args['max'],'no_found_rows'=>true]);
    if(!$q)return false;
    echo '<div id="'.esc_attr($args['id']).'" class="activity-block"><h3>'.esc_html($args['title']).'</h3><ul>';
    foreach($q as $p){ $t=_draft_or_post_title($p);
        echo '<li><span>'.esc_html(mysql2date('j. M, H:i',$p->post_date)).'</span> <a href="'.esc_url(rrw_adm_post_link($p)).'" aria-label="'.esc_attr(sprintf('„%s“ bearbeiten',$t)).'">'.esc_html($t).'</a></li>'; }
    echo '</ul></div>';return true;
} }
if(!function_exists('wp_dashboard_recent_comments')){ function wp_dashboard_recent_comments($total_items=5) {
    $list=get_comments(['number'=>(int)$total_items,'status'=>'approved','type'=>'comment','orderby'=>'comment_date_gmt','order'=>'DESC']);
    if(!$list)return false;
    echo '<div id="latest-comments" class="activity-block table-view-list"><h3>Neueste Kommentare</h3><div id="the-comment-list" data-wp-lists="list:comment">';
    foreach($list as $c)_wp_dashboard_recent_comments_row($c);
    echo '</div></div>';return true;
} }

/* ───────── Feeds und Ereignisse ───────── */
if(!function_exists('wp_dashboard_rss_output')){ function wp_dashboard_rss_output($widget_id) {
    $w=get_option('dashboard_widget_options');
    echo '<div class="rss-widget">';wp_widget_rss_output(is_array($w)&&isset($w[$widget_id])?$w[$widget_id]:[]);echo '</div>';
} }
if(!function_exists('wp_dashboard_cached_rss_widget')){ function wp_dashboard_cached_rss_widget($widget_id, $callback, $check_urls=[], ...$args) {   // Ausgabe 12 Stunden zwischenspeichern
    $loc=get_user_locale();$key='dash_v2_'.md5($widget_id.'_'.$loc);
    if(false!==($out=get_transient($key))){ echo $out;return true; }
    if(!$check_urls){ $w=get_option('dashboard_widget_options');if(empty($w[$widget_id]['url'])){ echo '<p>Der Feed ist nicht eingerichtet.</p>';return false; }$check_urls=[$w[$widget_id]['url']]; }
    if($callback&&is_callable($callback)){
        array_unshift($args,$widget_id,$check_urls);ob_start();call_user_func_array($callback,$args);$out=ob_get_clean();
        set_transient($key,$out,12*HOUR_IN_SECONDS);echo $out;return true;
    }
    return false;
} }
if(!function_exists('wp_dashboard_rss_control')){ function wp_dashboard_rss_control($widget_id, $form_inputs=[]) {
    $w=get_option('dashboard_widget_options');if(!is_array($w))$w=[];if(!isset($w[$widget_id]))$w[$widget_id]=[];
    if('POST'===strtoupper((string)($_SERVER['REQUEST_METHOD']??''))&&isset($_POST['widget-rss'][1])){
        $w[$widget_id]=wp_widget_rss_process(wp_unslash($_POST['widget-rss'][1]));update_option('dashboard_widget_options',$w,false);
        delete_transient('dash_v2_'.md5($widget_id.'_'.get_user_locale()));
    }
    wp_widget_rss_form($w[$widget_id],$form_inputs);
} }
if(!function_exists('wp_dashboard_primary_output')){ function wp_dashboard_primary_output($widget_id, $feeds) {
    foreach((array)$feeds as $f){
        $url=is_array($f)?($f['url']??''):(string)$f;if($url==='')continue;
        echo '<div class="rss-widget">';wp_widget_rss_output($url,is_array($f)?$f:[]);echo '</div>';
    }
} }
if(!function_exists('wp_dashboard_primary')){ function wp_dashboard_primary() {
    $feeds=['news'=>['link'=>apply_filters('dashboard_primary_link','https://wordpress.org/news/'),'url'=>apply_filters('dashboard_primary_feed','https://wordpress.org/news/feed/'),'title'=>apply_filters('dashboard_primary_title','WordPress-Blog'),'items'=>2,'show_summary'=>0,'show_author'=>0,'show_date'=>0]];
    wp_dashboard_cached_rss_widget('dashboard_primary','wp_dashboard_primary_output',$feeds);
} }
if(!function_exists('wp_dashboard_events_news')){ function wp_dashboard_events_news() {
    wp_print_community_events_markup();
    echo '<div class="wordpress-news hide-if-no-js">';wp_dashboard_primary();echo '</div>';
} }
if(!function_exists('wp_print_community_events_markup')){ function wp_print_community_events_markup() {   // Ereignisliste bleibt leer (kein Abruf bei wordpress.org)
    echo '<div class="community-events-errors"></div><div class="community-events"><div class="activity-block"><p>Es sind keine Veranstaltungen bekannt.</p></div></div>';
} }
if(!function_exists('wp_print_community_events_templates')){ function wp_print_community_events_templates() {
    echo '<script id="tmpl-community-events-no-upcoming-events" type="text/template"><li class="event-none"><p>Es sind keine Veranstaltungen bekannt.</p></li></script>';
} }

/* ───────── Hinweise ───────── */
if(!function_exists('wp_dashboard_quota')){ function wp_dashboard_quota() { return true; } }   // Speicherkontingent gibt es nur in Multisite
if(!function_exists('wp_check_browser_version')){ function wp_check_browser_version() { return false; } }   // keine Browserprüfung über wordpress.org
if(!function_exists('wp_dashboard_browser_nag')){ function wp_dashboard_browser_nag() {
    $r=wp_check_browser_version();if(!$r)return;
    echo '<div class="notice notice-error"><p>Dein Browser ('.esc_html($r['name']).') ist veraltet. Bitte aktualisiere ihn.</p></div>';
} }
if(!function_exists('dashboard_browser_nag_class')){ function dashboard_browser_nag_class($classes) {
    $r=wp_check_browser_version();if($r&&!empty($r['insecure']))$classes=is_array($classes)?array_merge($classes,['browser-insecure']):trim($classes.' browser-insecure');
    return $classes;
} }
if(!function_exists('wp_dashboard_php_nag')){ function wp_dashboard_php_nag() {
    $r=wp_check_php_version();if(!$r||!empty($r['is_acceptable'])&&!empty($r['is_secure']))return;
    $msg=empty($r['is_acceptable'])?sprintf('Deine PHP-Version (%s) ist für WordPress zu alt; mindestens %s ist nötig.',PHP_VERSION,$r['minimum_version']):sprintf('Deine PHP-Version (%s) erhält keine Sicherheitsupdates mehr. Empfohlen ist %s.',PHP_VERSION,$r['recommended_version']);
    echo '<div class="notice notice-'.(empty($r['is_acceptable'])?'error':'warning').' php-nag"><p>'.esc_html($msg).'</p></div>';
} }
if(!function_exists('dashboard_php_nag_class')){ function dashboard_php_nag_class($classes) {
    $r=wp_check_php_version();if(!$r)return $classes;
    $add=empty($r['is_acceptable'])?'php-insecure':(empty($r['is_secure'])?'php-no-security-updates':'');
    if($add==='')return $classes;
    return is_array($classes)?array_merge($classes,[$add]):trim($classes.' '.$add);
} }
if(!function_exists('wp_dashboard_site_health')){ function wp_dashboard_site_health() {
    $r=json_decode((string)get_transient('health-check-site-status-result'),true);
    echo '<div class="health-check-widget">';
    if(!is_array($r)||!$r)echo '<p>Es liegt noch keine Auswertung vor. <a href="'.esc_url(admin_url('site-health.php')).'">Zustand der Website prüfen</a></p>';
    else { $crit=(int)($r['critical']??0);$rec=(int)($r['recommended']??0);$good=(int)($r['good']??0);
        echo '<p>'.($crit?sprintf('Die Website hat %d kritische Probleme.',$crit):($rec?'Die Website ist in gutem Zustand; es gibt Verbesserungsvorschläge.':'Die Website ist in gutem Zustand.')).'</p>';
        echo '<p><a href="'.esc_url(admin_url('site-health.php')).'">'.sprintf('%d gut, %d empfohlen, %d kritisch',$good,$rec,$crit).'</a></p>'; }
    echo '</div>';
} }
