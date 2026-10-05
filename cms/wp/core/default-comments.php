<?php
// Standard-Kommentarvorlage, wenn das Theme keine comments.php mitbringt.
if(!defined('ABSPATH'))exit;
?>
<div id="comments" class="comments-area">
<?php if(have_comments()): ?>
  <h2 class="comments-title"><?php comments_number('Keine Kommentare','1 Kommentar','% Kommentare'); ?></h2>
  <ol class="comment-list"><?php wp_list_comments(['style'=>'ol','avatar_size'=>40]); ?></ol>
<?php endif; ?>
<?php comment_form(); ?>
</div>
