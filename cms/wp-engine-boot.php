<?php
// Startet den echten WordPress-Core der WordPress-Engine – nur über cms/api.php (oder Tests) auf oberster Ebene einbinden:
// wp-settings.php setzt globale Variablen und muss deshalb im globalen Gültigkeitsbereich laufen.
// Vorher gesetzt sein müssen: $GLOBALS['elvado_wpe_engine'] (Elvado\Wp\Engine), $GLOBALS['elvado_wpe_db'] (Elvado\Wp\DbConfig), optional $GLOBALS['elvado_wpe_opts'].
if (!isset($GLOBALS['elvado_wpe_engine'], $GLOBALS['elvado_wpe_db'])) { http_response_code(404); exit; }
if (!\Elvado\Wp\Bridge::booted()) {
    $__wpe = \Elvado\Wp\Bridge::prepare($GLOBALS['elvado_wpe_engine'], $GLOBALS['elvado_wpe_db'], (array)($GLOBALS['elvado_wpe_opts'] ?? []));
    $table_prefix = $__wpe['prefix'];
    require $__wpe['settings'];
    \Elvado\Wp\Bridge::restore($__wpe['snapshot']);
    \Elvado\Wp\Bridge::done($GLOBALS['elvado_wpe_engine']);
    unset($__wpe);
}
