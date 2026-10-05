<?php
// Einstieg des eigenständigen CMS: Vor der Einrichtung geht es zum Einrichtungsassistenten, danach liefert die WordPress-Theme-Laufzeit die Website aus.
declare(strict_types=1);
if(!is_file(__DIR__.'/cms/data/install.lock')&&!is_file(__DIR__.'/cms/data/system.local.json')){ header('Location: /cms/');http_response_code(302);exit; }
require __DIR__.'/cms/wp-front.php';
