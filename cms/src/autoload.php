<?php
declare(strict_types=1);
// cms/src/autoload.php – PSR-4-Autoloader für den Namensraum Elvado\ (cms/src/<Pfad>.php). Neutral, ohne Composer.
// Hier liegen die objektorientierten Dienste (Datenbank, Repositories, KI-Gateway, GitHub-Sync, Lovable); die älteren Module in cms/lib/ bleiben prozedural.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Elvado\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
