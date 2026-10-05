<?php
// Minimale _WP_Editors: TinyMCE gehört nicht zur Kompatibilitätsschicht; Editoren erhalten ein einfaches Textfeld.
class _WP_Editors {
    public static $mce_locale = 'de';
    public static function print_default_editor_scripts() {}
    public static function print_tinymce_scripts() {}
    public static function enqueue_default_editor() {}
    public static function enqueue_scripts($default_scripts = false) {}
    public static function editor($content, $editor_id, $settings = []) {
        $cls = esc_attr((string)($settings['editor_class'] ?? ''));
        $name = esc_attr((string)($settings['textarea_name'] ?? $editor_id));
        $rows = (int)($settings['textarea_rows'] ?? 10);
        echo '<div id="wp-' . esc_attr($editor_id) . '-wrap" class="wp-core-ui wp-editor-wrap html-active"><textarea class="' . $cls . '" rows="' . $rows . '" cols="40" name="' . $name . '" id="' . esc_attr($editor_id) . '">' . esc_textarea($content) . '</textarea></div>';
    }
    public static function editor_js() {}
    public static function force_uncompressed_tinymce() {}
    public static function get_mce_locale() { return 'de'; }
}
