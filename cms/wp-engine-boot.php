<?php
// Startet den echten WordPress-Core der WordPress-Engine – nur über cms/api.php (oder Tests) auf oberster Ebene einbinden:
// wp-settings.php setzt globale Variablen und muss deshalb im globalen Gültigkeitsbereich laufen.
// Vorher gesetzt sein müssen: $GLOBALS['rrw_wpe_engine'] (Elvado\Wp\Engine), $GLOBALS['rrw_wpe_db'] (Elvado\Wp\DbConfig), optional $GLOBALS['rrw_wpe_opts'].
if (!isset($GLOBALS['rrw_wpe_engine'], $GLOBALS['rrw_wpe_db'])) { http_response_code(404); exit; }
if (!\Elvado\Wp\Bridge::booted()) {
    $__wpe = \Elvado\Wp\Bridge::prepare($GLOBALS['rrw_wpe_engine'], $GLOBALS['rrw_wpe_db'], (array)($GLOBALS['rrw_wpe_opts'] ?? []));
    $table_prefix = $__wpe['prefix'];
    require $__wpe['settings'];
    \Elvado\Wp\Bridge::restore($__wpe['snapshot']);
    \Elvado\Wp\Bridge::done($GLOBALS['rrw_wpe_engine']);
    unset($__wpe);
}
