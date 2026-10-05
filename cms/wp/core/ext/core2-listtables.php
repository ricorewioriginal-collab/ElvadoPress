<?php
// Ergänzende WordPress-Funktionen (Kern 2, Teil 6): Listen-Tabellen der Verwaltung (WP_*_List_Table) und Walker/Hilfsklassen der Verwaltungsseiten.
// Eigenständig umgesetzt; die Klassen erweitern WP_List_Table (echte Klasse, falls geladen, sonst die schlanke Basis der Schicht).
// Spaltenmethoden geben ihre Ausgabe per echo aus (mit beiden Basisklassen lauffähig). Abfragen laufen nur in prepare_items().

if(!trait_exists('RRW_C2_Table_Common')){
trait RRW_C2_Table_Common {
    protected function rrw_req($k, $d='') { return isset($_REQUEST[$k])&&is_scalar($_REQUEST[$k])?sanitize_text_field(wp_unslash((string)$_REQUEST[$k])):$d; }
    protected function rrw_per_page($option, $default=20) { $n=(int)get_user_option($option);return $n>0?$n:(int)apply_filters($option,$default); }
    protected function rrw_page() { return max(1,(int)($_REQUEST['paged']??1)); }
    protected function rrw_pagination($total, $per) { $this->set_pagination_args(['total_items'=>(int)$total,'total_pages'=>$per>0?(int)ceil($total/$per):1,'per_page'=>(int)$per]); }
    protected function rrw_view_links(array $counts, string $key, string $current, array $labels): array {
        $o=[];foreach($labels as $k=>$l){ if(!isset($counts[$k])||($k!=='all'&&!(int)$counts[$k]))continue;
            $o[$k]='<a href="'.esc_url(add_query_arg($key,$k==='all'?false:$k)).'"'.($current===$k||($k==='all'&&$current==='')?' class="current" aria-current="page"':'').'>'.esc_html($l).' <span class="count">('.number_format_i18n((int)$counts[$k]).')</span></a>'; }
        return $o;
    }
    public function column_default($item, $column_name) { echo '';do_action('manage_'.($this->_args['plural']??'items').'_custom_column',$column_name,is_object($item)?($item->ID??0):0); }
    protected function rrw_cb($id, $label='') { echo '<input type="checkbox" name="'.esc_attr($this->_args['singular']?:'item').'[]" value="'.esc_attr((string)$id).'" aria-label="'.esc_attr($label).'" />'; }
    public function get_table_classes() { return ['widefat','fixed','striped',$this->_args['plural']?:'items']; }
}
}

/* ───────── Beiträge, Medien, Kommentare, Benutzer, Links ───────── */
if(!class_exists('WP_Posts_List_Table')){
class WP_Posts_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public $post_type='post';public $is_trash=false;public $user_posts_count=0;public $sticky_posts_count=0;public $comment_pending_count=[];public $hierarchical_display=false;public $current_level=0;public $per_page=20;
    public function __construct($args=[]) {
        $this->post_type=$this->rrw_req('post_type',(string)($GLOBALS['post_type']??'post'))?:'post';if(!post_type_exists($this->post_type))$this->post_type='post';
        $this->is_trash=$this->rrw_req('post_status')==='trash';
        parent::__construct(array_merge(['plural'=>'posts','singular'=>'post','ajax'=>true],(array)$args));
    }
    public function prepare_items() {
        $per=$this->per_page=$this->rrw_per_page('edit_'.$this->post_type.'_per_page',20);$st=$this->rrw_req('post_status');
        $a=['post_type'=>$this->post_type,'post_status'=>$st!==''?$st:'any','posts_per_page'=>$per,'paged'=>$this->rrw_page(),'orderby'=>$this->rrw_req('orderby','date'),'order'=>strtoupper($this->rrw_req('order','DESC'))==='ASC'?'ASC':'DESC'];
        if(($s=$this->rrw_req('s'))!=='')$a['s']=$s;
        if(($au=(int)$this->rrw_req('author'))>0)$a['author']=$au;
        if(($c=(int)$this->rrw_req('cat'))>0)$a['cat']=$c;
        $q=new WP_Query(apply_filters('edit_posts_per_page_args',$a,$this->post_type));
        $this->items=$q->posts;$this->rrw_pagination($q->found_posts,$per);
    }
    public function get_columns() {
        $c=['cb'=>'<input type="checkbox" />','title'=>'Titel','author'=>'Autor'];
        foreach(['category'=>'categories','post_tag'=>'tags'] as $tx=>$col)if(is_object_in_taxonomy($this->post_type,$tx))$c[$col]=$tx==='category'?'Kategorien':'Schlagwörter';
        if(post_type_supports($this->post_type,'comments'))$c['comments']='Kommentare';
        $c['date']='Datum';
        return apply_filters("manage_{$this->post_type}_posts_columns",$c);
    }
    public function get_sortable_columns() { return ['title'=>'title','author'=>'author','comments'=>'comment_count','date'=>['date',true]]; }
    protected function get_views() {
        $c=(array)wp_count_posts($this->post_type);$c['all']=array_sum(array_diff_key($c,['trash'=>0,'auto-draft'=>0]));
        return $this->rrw_view_links($c,'post_status',$this->rrw_req('post_status'),['all'=>'Alle','publish'=>'Veröffentlicht','future'=>'Geplant','draft'=>'Entwurf','pending'=>'Ausstehend','private'=>'Privat','trash'=>'Papierkorb']);
    }
    protected function get_bulk_actions() { return $this->is_trash?['untrash'=>'Wiederherstellen','delete'=>'Endgültig löschen']:['edit'=>'Bearbeiten','trash'=>'In den Papierkorb legen']; }
    public function column_cb($item) { $this->rrw_cb($item->ID,$item->post_title); }
    public function column_title($post) {
        $l=get_edit_post_link($post->ID);$t=$post->post_title!==''?$post->post_title:'(ohne Titel)';
        echo '<strong>'.($l?'<a class="row-title" href="'.esc_url($l).'">'.esc_html($t).'</a>':esc_html($t)).'</strong>';
        $s=get_post_status_object($post->post_status);if($post->post_status!=='publish'&&$s)echo ' &mdash; <span class="post-state">'.esc_html($s->label).'</span>';
    }
    public function column_author($post) { echo esc_html(get_the_author_meta('display_name',$post->post_author)); }
    public function column_categories($post) { $this->rrw_terms($post,'category'); }
    public function column_tags($post) { $this->rrw_terms($post,'post_tag'); }
    private function rrw_terms($post, $tx) { $t=get_the_terms($post->ID,$tx);echo $t&&!is_wp_error($t)?esc_html(implode(', ',wp_list_pluck($t,'name'))):'&#8212;'; }
    public function column_comments($post) { echo (int)$post->comment_count; }
    public function column_date($post) {
        $s=$post->post_status==='publish'?'Veröffentlicht':($post->post_status==='future'?'Geplant':'Zuletzt geändert');
        echo esc_html($s).'<br />'.esc_html(mysql2date('d.m.Y \u\m H:i',$post->post_status==='draft'?$post->post_modified:$post->post_date));
    }
    public function column_default($post, $column_name) { do_action('manage_posts_custom_column',$column_name,$post->ID);do_action("manage_{$this->post_type}_posts_custom_column",$column_name,$post->ID); }
    public function is_base_request() { return !array_intersect_key($_GET,array_flip(['post_status','s','author','cat','orderby'])); }
}
}
if(!class_exists('WP_Media_List_Table')){
class WP_Media_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public $detached=false;
    public function __construct($args=[]) { $this->detached=$this->rrw_req('attachment-filter')==='detached';parent::__construct(array_merge(['plural'=>'media','singular'=>'media','ajax'=>true],(array)$args)); }
    public function prepare_items() {
        $per=$this->rrw_per_page('upload_per_page',20);
        $a=['post_type'=>'attachment','post_status'=>$this->rrw_req('status')==='trash'?'trash':'inherit','posts_per_page'=>$per,'paged'=>$this->rrw_page(),'orderby'=>$this->rrw_req('orderby','date'),'order'=>strtoupper($this->rrw_req('order','DESC'))==='ASC'?'ASC':'DESC'];
        if(($s=$this->rrw_req('s'))!=='')$a['s']=$s;
        if(($m=$this->rrw_req('post_mime_type'))!=='')$a['post_mime_type']=$m;
        if($this->detached)$a['post_parent']=0;
        $q=new WP_Query($a);$this->items=$q->posts;$this->rrw_pagination($q->found_posts,$per);
    }
    public function get_columns() { return apply_filters('manage_media_columns',['cb'=>'<input type="checkbox" />','title'=>'Datei','author'=>'Autor','parent'=>'Hochgeladen zu','comments'=>'Kommentare','date'=>'Datum'],$this->detached); }
    public function get_sortable_columns() { return ['title'=>'title','author'=>'author','date'=>['date',true]]; }
    protected function get_bulk_actions() { return ['delete'=>'Endgültig löschen']; }
    public function column_cb($post) { $this->rrw_cb($post->ID,$post->post_title); }
    public function column_title($post) {
        $thumb=wp_get_attachment_image($post->ID,[60,60],true);$f=get_attached_file($post->ID);
        echo $thumb.'<strong><a href="'.esc_url((string)get_edit_post_link($post->ID)).'">'.esc_html($post->post_title).'</a></strong><p>'.esc_html($f?basename($f):'').'</p>';
    }
    public function column_author($post) { echo esc_html(get_the_author_meta('display_name',$post->post_author)); }
    public function column_parent($post) { $p=$post->post_parent?get_post($post->post_parent):null;echo $p?'<strong>'.esc_html($p->post_title).'</strong>':'(nicht zugeordnet)'; }
    public function column_comments($post) { echo (int)$post->comment_count; }
    public function column_date($post) { echo esc_html(mysql2date('d.m.Y',$post->post_date)); }
    public function column_default($post, $column_name) { do_action('manage_media_custom_column',$column_name,$post->ID); }
}
}
if(!class_exists('WP_Comments_List_Table')){
class WP_Comments_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public $checkbox=true;public $pending_count=[];public $user_can=true;
    public function __construct($args=[]) { parent::__construct(array_merge(['plural'=>'comments','singular'=>'comment','ajax'=>true],(array)$args)); }
    protected function rrw_post_id() { return (int)$this->rrw_req('p',(string)($GLOBALS['post_id']??0)); }
    public function prepare_items() {
        global $wpdb;$per=$this->rrw_per_page('edit_comments_per_page',20);$st=$this->rrw_req('comment_status','all');
        $where=['1=1'];
        $where[]=match($st){'moderated'=>"comment_approved = '0'",'approved'=>"comment_approved = '1'",'spam'=>"comment_approved = 'spam'",'trash'=>"comment_approved = 'trash'",default=>"comment_approved IN ('0','1')"};
        if($pid=$this->rrw_post_id())$where[]=$wpdb->prepare('comment_post_ID = %d',$pid);
        if(($t=$this->rrw_req('comment_type'))!=='')$where[]=$wpdb->prepare('comment_type = %s',$t==='comment'?'comment':$t);
        if(($s=$this->rrw_req('s'))!==''){ $l='%'.$wpdb->esc_like($s).'%';$where[]=$wpdb->prepare('(comment_author LIKE %s OR comment_author_email LIKE %s OR comment_content LIKE %s)',$l,$l,$l); }
        $w=implode(' AND ',$where);$off=($this->rrw_page()-1)*$per;
        $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE $w");
        $rows=$wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE $w ORDER BY comment_date_gmt DESC, comment_ID DESC LIMIT ".(int)$per.' OFFSET '.(int)$off);
        $this->items=array_map(fn($r)=>new WP_Comment($r),is_array($rows)?$rows:[]);
        $this->rrw_pagination($total,$per);
    }
    public function get_columns() {
        $c=$this->checkbox?['cb'=>'<input type="checkbox" />']:[];
        $c+=['author'=>'Autor','comment'=>'Kommentar'];if(!$this->rrw_post_id())$c['response']='Antwort auf';$c['date']='Eingereicht am';
        return apply_filters('manage_edit-comments_columns',$c);
    }
    public function get_sortable_columns() { return ['author'=>'comment_author','response'=>'comment_post_ID','date'=>'comment_date']; }
    protected function get_views() {
        $c=(array)wp_count_comments($this->rrw_post_id());
        return $this->rrw_view_links(['all'=>$c['all']??0,'moderated'=>$c['moderated']??0,'approved'=>$c['approved']??0,'spam'=>$c['spam']??0,'trash'=>$c['trash']??0],'comment_status',$this->rrw_req('comment_status'),['all'=>'Alle','moderated'=>'Ausstehend','approved'=>'Genehmigt','spam'=>'Spam','trash'=>'Papierkorb']);
    }
    protected function get_bulk_actions() { return ['unapprove'=>'Ablehnen','approve'=>'Genehmigen','spam'=>'Als Spam markieren','trash'=>'In den Papierkorb legen']; }
    public function column_cb($comment) { $this->rrw_cb($comment->comment_ID,$comment->comment_author); }
    public function column_author($comment) { echo '<strong>'.esc_html(get_comment_author($comment)).'</strong><br />'.esc_html($comment->comment_author_email).($comment->comment_author_IP!==''?'<br />'.esc_html($comment->comment_author_IP):''); }
    public function column_comment($comment) { echo '<p>'.wp_kses_post(get_comment_text($comment)).'</p>'; }
    public function column_response($comment) { $p=get_post($comment->comment_post_ID);echo $p?'<a href="'.esc_url((string)get_edit_post_link($p->ID)).'">'.esc_html($p->post_title).'</a>':''; }
    public function column_date($comment) { echo esc_html(mysql2date('d.m.Y \u\m H:i',$comment->comment_date)); }
    public function column_default($comment, $column_name) { do_action('manage_comments_custom_column',$column_name,$comment->comment_ID); }
}
}
if(!class_exists('WP_Post_Comments_List_Table')){
class WP_Post_Comments_List_Table extends WP_Comments_List_Table {
    protected function rrw_post_id() { return (int)($GLOBALS['post_id']??$this->rrw_req('post_ID',$this->rrw_req('p','0'))); }
    public function get_columns() { return ['author'=>'Autor','comment'=>'Kommentar']; }
    public function get_table_classes() { return ['widefat','fixed','comments']; }
    protected function get_views() { return []; }
    protected function get_bulk_actions() { return []; }
    public function get_sortable_columns() { return []; }
}
}
if(!class_exists('WP_Users_List_Table')){
class WP_Users_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public $role='';
    public function __construct($args=[]) { $this->role=$this->rrw_req('role');parent::__construct(array_merge(['plural'=>'users','singular'=>'user','ajax'=>true],(array)$args)); }
    public function prepare_items() {
        $per=$this->rrw_per_page('users_per_page',20);
        $a=['number'=>$per,'offset'=>($this->rrw_page()-1)*$per,'count_total'=>true,'orderby'=>$this->rrw_req('orderby','login'),'order'=>strtoupper($this->rrw_req('order','ASC'))==='DESC'?'DESC':'ASC'];
        if($this->role!=='')$a['role']=$this->role;
        if(($s=$this->rrw_req('s'))!=='')$a['search']='*'.$s.'*';
        $q=new WP_User_Query($a);$this->items=$q->get_results();$this->rrw_pagination($q->get_total(),$per);
    }
    public function get_columns() { return apply_filters('manage_users_columns',['cb'=>'<input type="checkbox" />','username'=>'Benutzername','name'=>'Name','email'=>'E-Mail-Adresse','role'=>'Rolle','posts'=>'Beiträge']); }
    public function get_sortable_columns() { return ['username'=>'login','email'=>'email']; }
    protected function get_views() {
        $c=count_users();$k=['all'=>$c['total_users']??0];foreach(($c['avail_roles']??[]) as $r=>$n)if($r!=='none')$k[$r]=$n;
        $labels=['all'=>'Alle'];foreach(wp_roles()->get_names() as $r=>$n)$labels[$r]=translate_user_role($n);
        return $this->rrw_view_links($k,'role',$this->role,$labels);
    }
    protected function get_bulk_actions() { return ['delete'=>'Löschen']; }
    public function column_cb($u) { $this->rrw_cb($u->ID,$u->user_login); }
    public function column_username($u) { $l=get_edit_user_link($u->ID);echo '<strong>'.($l?'<a href="'.esc_url($l).'">'.esc_html($u->user_login).'</a>':esc_html($u->user_login)).'</strong>'; }
    public function column_name($u) { echo esc_html(trim($u->first_name.' '.$u->last_name)?:'&#8212;'); }
    public function column_email($u) { echo '<a href="mailto:'.esc_attr($u->user_email).'">'.esc_html($u->user_email).'</a>'; }
    public function column_role($u) { $n=wp_roles()->get_names();$o=[];foreach((array)$u->roles as $r)$o[]=translate_user_role($n[$r]??$r);echo esc_html($o?implode(', ',$o):'Keine Rolle'); }
    public function column_posts($u) { echo (int)count_user_posts($u->ID); }
    public function column_default($u, $column_name) { echo apply_filters('manage_users_custom_column','',$column_name,$u->ID); }
}
}
if(!class_exists('WP_Links_List_Table')){
class WP_Links_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public function __construct($args=[]) { parent::__construct(array_merge(['plural'=>'bookmarks','singular'=>'bookmark','ajax'=>true],(array)$args)); }
    public function prepare_items() {
        $per=$this->rrw_per_page('edit_link_per_page',25);$a=['hide_invisible'=>0,'orderby'=>$this->rrw_req('orderby','name'),'order'=>$this->rrw_req('order','ASC')];
        if(($s=$this->rrw_req('s'))!=='')$a['search']=$s;if(($c=$this->rrw_req('cat_id'))!=='')$a['category']=(int)$c;
        $all=get_bookmarks($a);$this->items=array_slice($all,($this->rrw_page()-1)*$per,$per);$this->rrw_pagination(count($all),$per);
    }
    public function get_columns() { return apply_filters('manage_link-manager_columns',['cb'=>'<input type="checkbox" />','name'=>'Name','url'=>'Adresse','categories'=>'Kategorien','rel'=>'Beziehung','visible'=>'Sichtbar','rating'=>'Bewertung']); }
    public function get_sortable_columns() { return ['name'=>'name','url'=>'url','visible'=>'visible','rating'=>'rating']; }
    protected function get_bulk_actions() { return ['delete'=>'Löschen']; }
    public function column_cb($l) { $this->rrw_cb($l->link_id,$l->link_name); }
    public function column_name($l) { echo '<strong><a href="'.esc_url((string)get_edit_bookmark_link($l)).'">'.esc_html($l->link_name).'</a></strong>'; }
    public function column_url($l) { echo '<a href="'.esc_url($l->link_url).'">'.esc_html(preg_replace('~^https?://(www\.)?~','',$l->link_url)).'</a>'; }
    public function column_categories($l) { $t=taxonomy_exists('link_category')?wp_get_object_terms((int)$l->link_id,'link_category'):[];echo !is_wp_error($t)&&$t?esc_html(implode(', ',wp_list_pluck($t,'name'))):'&#8212;'; }
    public function column_rel($l) { echo esc_html($l->link_rel!==''?$l->link_rel:'&#8212;'); }
    public function column_visible($l) { echo $l->link_visible==='Y'?'Ja':'Nein'; }
    public function column_rating($l) { echo (int)$l->link_rating; }
    public function column_default($l, $column_name) { do_action('manage_link_custom_column',$column_name,$l->link_id); }
}
}

/* ───────── Plugins, Themes, Installation ───────── */
if(!class_exists('WP_Plugins_List_Table')){
class WP_Plugins_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public $status='all';
    public function __construct($args=[]) { $this->status=$this->rrw_req('plugin_status','all');parent::__construct(array_merge(['plural'=>'plugins','singular'=>'plugin','ajax'=>true],(array)$args)); }
    public function prepare_items() {
        $all=get_plugins();$mu=function_exists('get_mu_plugins')?get_mu_plugins():[];$dro=function_exists('get_dropins')?get_dropins():[];
        $src=match($this->status){'mustuse'=>$mu,'dropins'=>$dro,default=>$all};
        $s=strtolower($this->rrw_req('s'));$out=[];
        foreach($src as $f=>$d){
            $act=is_plugin_active($f);
            if($this->status==='active'&&!$act)continue;if($this->status==='inactive'&&$act)continue;
            if($s!==''&&!str_contains(strtolower(($d['Name']??'').' '.($d['Description']??'').' '.($d['Author']??'')),$s))continue;
            $out[$f]=$d;
        }
        uasort($out,fn($a,$b)=>strcasecmp((string)($a['Name']??''),(string)($b['Name']??'')));
        $this->items=$out;$this->rrw_pagination(count($out),max(1,count($out)));
    }
    public function get_columns() { return ['cb'=>'<input type="checkbox" />','name'=>'Plugin','description'=>'Beschreibung']; }
    protected function get_views() {
        $all=get_plugins();$act=count(array_filter(array_keys($all),'is_plugin_active'));
        return $this->rrw_view_links(['all'=>count($all),'active'=>$act,'inactive'=>count($all)-$act],'plugin_status',$this->status==='all'?'':$this->status,['all'=>'Alle','active'=>'Aktiv','inactive'=>'Inaktiv']);
    }
    protected function get_bulk_actions() { return ['activate-selected'=>'Aktivieren','deactivate-selected'=>'Deaktivieren','delete-selected'=>'Löschen']; }
    public function display_rows() { foreach($this->items as $f=>$d)$this->single_row([$f,$d]); }
    public function single_row($item) {
        [$f,$d]=$item;$act=is_plugin_active($f);
        echo '<tr class="'.($act?'active':'inactive').'" data-plugin="'.esc_attr($f).'">';
        foreach($this->get_columns() as $k=>$_){ echo '<td>';
            if($k==='cb')echo '<input type="checkbox" name="checked[]" value="'.esc_attr($f).'" />';
            elseif($k==='name')echo '<strong>'.esc_html($d['Name']??$f).'</strong> <span class="version">'.esc_html($d['Version']??'').'</span><div class="row-actions">'.implode(' | ',$this->plugin_actions($f,$act)).'</div>';
            else echo '<p>'.wp_kses_post($d['Description']??'').'</p>';
            echo '</td>'; }
        echo '</tr>';
    }
    protected function plugin_actions($f, $act) {
        $a=$act?'deactivate':'activate';$u=wp_nonce_url(self_admin_url('plugins.php?action='.$a.'&plugin='.urlencode($f)),$a.'-plugin_'.$f);
        return [$a=>'<a href="'.esc_url($u).'">'.($act?'Deaktivieren':'Aktivieren').'</a>'];
    }
    public function no_items() { echo 'Es wurden keine Plugins gefunden.'; }
}
}
if(!class_exists('WP_Themes_List_Table')){
class WP_Themes_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public $features=[];
    public function __construct($args=[]) { parent::__construct(array_merge(['plural'=>'themes','singular'=>'theme','ajax'=>true],(array)$args)); }
    public function prepare_items() {
        $t=wp_get_themes();$s=strtolower($this->rrw_req('s'));
        if($s!=='')$t=array_filter($t,fn($x)=>str_contains(strtolower($x->get('Name').' '.$x->get('Description').' '.$x->get('Author')),$s));
        uasort($t,fn($a,$b)=>strcasecmp($a->get('Name'),$b->get('Name')));
        $this->items=$t;$this->rrw_pagination(count($t),max(1,count($t)));
    }
    public function get_columns() { return ['name'=>'Theme','version'=>'Version','author'=>'Autor','status'=>'Status']; }
    public function display_rows() { foreach($this->items as $slug=>$t){ $act=get_stylesheet()===$slug;
        echo '<tr class="theme'.($act?' active':'').'" data-slug="'.esc_attr($slug).'"><td>'.esc_html($t->get('Name')).'</td><td>'.esc_html($t->get('Version')).'</td><td>'.esc_html(wp_strip_all_tags($t->get('Author'))).'</td><td>'.($act?'Aktiv':'Inaktiv').'</td></tr>'; } }
    public function no_items() { echo 'Es wurden keine Themes gefunden.'; }
    public function tablenav($which='top') {}
    public function get_registered_features_html() { return ''; }
}
}
if(!class_exists('WP_Plugin_Install_List_Table')){
class WP_Plugin_Install_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public $order='DESC';public $orderby='';public $groups=[];public $error=null;
    public function __construct($args=[]) { parent::__construct(array_merge(['plural'=>'plugins','singular'=>'plugin'],(array)$args)); }
    public function ajax_user_can() { return current_user_can('install_plugins'); }
    // Das Plugin-Verzeichnis wird über den CMS-Installer genutzt: hier gibt es keine Treffer
    public function prepare_items() { $r=plugins_api('query_plugins',[]);$this->error=is_wp_error($r)?$r:null;$this->items=[];$this->rrw_pagination(0,30); }
    public function get_columns() { return ['name'=>'Name','version'=>'Version','rating'=>'Bewertung','description'=>'Beschreibung']; }
    protected function get_views() { return ['featured'=>'Empfohlen','popular'=>'Beliebt','search'=>'Suchergebnisse']; }
    public function no_items() { echo $this->error?esc_html($this->error->get_error_message()):'Keine Plugins gefunden.'; }
    public function display_rows() {}
}
}
if(!class_exists('WP_Theme_Install_List_Table')){
class WP_Theme_Install_List_Table extends WP_Plugin_Install_List_Table {
    public $features=[];
    public function ajax_user_can() { return current_user_can('install_themes'); }
    public function prepare_items() { $r=themes_api('query_themes',[]);$this->error=is_wp_error($r)?$r:null;$this->items=[];$this->rrw_pagination(0,36); }
    public function get_columns() { return ['name'=>'Name','version'=>'Version']; }
    protected function get_views() { return ['featured'=>'Empfohlen','popular'=>'Beliebt','new'=>'Neueste']; }
    public function no_items() { echo $this->error?esc_html($this->error->get_error_message()):'Keine Themes gefunden.'; }
}
}

/* ───────── Anwendungspasswörter, Datenschutzanfragen ───────── */
if(!class_exists('WP_Application_Passwords_List_Table')){
class WP_Application_Passwords_List_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    public function __construct($args=[]) { parent::__construct(array_merge(['plural'=>'application_passwords','singular'=>'application_password'],(array)$args)); }
    public function get_columns() { return ['name'=>'Name','created'=>'Erstellt','last_used'=>'Zuletzt verwendet','last_ip'=>'Letzte IP','revoke'=>'Widerrufen']; }
    public function prepare_items() {
        $uid=(int)($GLOBALS['user_id']??get_current_user_id());
        $this->items=array_reverse(class_exists('WP_Application_Passwords')?WP_Application_Passwords::get_user_application_passwords($uid):[]);
        $this->rrw_pagination(count($this->items),max(1,count($this->items)));
    }
    protected function get_default_primary_column_name() { return 'name'; }
    public function column_name($item) { echo esc_html($item['name']??''); }
    public function column_created($item) { echo empty($item['created'])?'&mdash;':esc_html(date_i18n('d.m.Y H:i',(int)$item['created'])); }
    public function column_last_used($item) { echo empty($item['last_used'])?'&mdash;':esc_html(date_i18n('d.m.Y H:i',(int)$item['last_used'])); }
    public function column_last_ip($item) { echo empty($item['last_ip'])?'&mdash;':esc_html($item['last_ip']); }
    public function column_revoke($item) { echo '<button type="button" class="button delete" data-uuid="'.esc_attr($item['uuid']??'').'">Widerrufen</button>'; }
    public function column_default($item, $column_name) { do_action('manage_application_passwords_custom_column',$column_name,$item); }
    public function print_js_template_row() {}
}
}
if(!class_exists('WP_Privacy_Requests_Table')){
abstract class WP_Privacy_Requests_Table extends WP_List_Table {
    use RRW_C2_Table_Common;
    protected $request_type='export_personal_data';protected $post_type='user_request';
    public function __construct($args=[]) { parent::__construct(array_merge(['plural'=>'privacy_requests','singular'=>'privacy_request'],(array)$args)); }
    public function get_columns() { return ['cb'=>'<input type="checkbox" />','email'=>'Anfragender','status'=>'Status','created_timestamp'=>'Angefragt','next_steps'=>'Nächste Schritte']; }
    protected function get_bulk_actions() { return ['delete'=>'Anfragen entfernen','resend'=>'E-Mail erneut senden']; }
    public function get_sortable_columns() { return ['email'=>'requester','created_timestamp'=>'requested']; }
    protected function get_views() {
        $c=wp_count_posts($this->post_type);$k=['all'=>0];$st=['request-pending'=>'Ausstehend','request-confirmed'=>'Bestätigt','request-failed'=>'Fehlgeschlagen','request-completed'=>'Abgeschlossen'];
        foreach($st as $s=>$_){ $k[$s]=0; }
        $q=new WP_Query(['post_type'=>$this->post_type,'name'=>$this->request_type,'post_status'=>array_keys($st),'posts_per_page'=>-1,'fields'=>'ids']);
        foreach($q->posts as $id){ $k['all']++;$s=get_post_status($id);if(isset($k[$s]))$k[$s]++; }
        return $this->rrw_view_links($k,'filter-status',$this->rrw_req('filter-status'),['all'=>'Alle']+$st);
    }
    public function prepare_items() {
        $per=$this->rrw_per_page('privacy_requests_per_page',20);$st=$this->rrw_req('filter-status');
        $a=['post_type'=>$this->post_type,'post_name__in'=>[$this->request_type],'posts_per_page'=>$per,'paged'=>$this->rrw_page(),'post_status'=>$st!==''?$st:'any','orderby'=>'date','order'=>$this->rrw_req('order','DESC')];
        if(($s=$this->rrw_req('s'))!=='')$a['s']=$s;
        $q=new WP_Query($a);$this->items=[];foreach($q->posts as $p){ $r=wp_get_user_request($p->ID);if($r)$this->items[]=$r; }
        $this->rrw_pagination($q->found_posts,$per);
    }
    public function column_cb($item) { $this->rrw_cb($item->ID,$item->email); }
    public function column_email($item) { echo '<a href="mailto:'.esc_attr($item->email).'">'.esc_html($item->email).'</a>'; }
    public function column_status($item) { $s=get_post_status_object($item->status);echo esc_html($s?$s->label:$item->status); }
    public function column_created_timestamp($item) { echo esc_html(date_i18n('d.m.Y H:i',(int)$item->created_timestamp)); }
    public function column_default($item, $column_name) { do_action('manage_privacy_requests_custom_column',$column_name,$item); }
    abstract public function column_next_steps($item);
    public function embed_scripts() {}
}
}
if(!class_exists('WP_Privacy_Data_Export_Requests_List_Table')){
class WP_Privacy_Data_Export_Requests_List_Table extends WP_Privacy_Requests_Table {
    protected $request_type='export_personal_data';
    public function column_next_steps($item) {
        switch($item->status){ case 'request-pending': echo 'Warten auf Bestätigung durch den Benutzer';break; case 'request-confirmed': echo 'Exportdatei erstellen';break;
            case 'request-failed': echo 'Erneut versuchen';break; case 'request-completed': echo 'Exportdatei herunterladen';break; }
    }
}
}
if(!class_exists('WP_Privacy_Data_Removal_Requests_List_Table')){
class WP_Privacy_Data_Removal_Requests_List_Table extends WP_Privacy_Requests_Table {
    protected $request_type='remove_personal_data';
    public function column_next_steps($item) {
        switch($item->status){ case 'request-pending': echo 'Warten auf Bestätigung durch den Benutzer';break; case 'request-confirmed': echo 'Persönliche Daten löschen';break;
            case 'request-failed': echo 'Erneut versuchen';break; case 'request-completed': echo 'Abgeschlossen';break; }
    }
}
}
if(!class_exists('_WP_List_Table_Compat')){
class _WP_List_Table_Compat extends WP_List_Table {
    public $_screen;public $_columns;
    public function __construct($screen, $columns=[]) {   // ohne Elternkonstruktor, wie in WordPress
        $this->_screen=is_string($screen)&&method_exists('WP_Screen','get')?(WP_Screen::get($screen)?:$screen):$screen;
        if(!empty($columns)){ $this->_columns=$columns;add_filter('manage_'.(is_object($this->_screen)?$this->_screen->id:(string)$this->_screen).'_columns',[$this,'get_columns'],0); }
    }
    public function get_column_info() { $c=$this->get_columns();$h=function_exists('get_hidden_columns')&&is_object($this->_screen)?get_hidden_columns($this->_screen):[];return [$c,$h,[],array_key_first($c)?:'']; }
    public function get_columns() { return (array)$this->_columns; }
}
}
