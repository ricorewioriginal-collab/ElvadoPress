<?php
// PHPMailer-kompatible Ausnahme (Namespace PHPMailer\PHPMailer), eigenständig umgesetzt.
namespace PHPMailer\PHPMailer;

class Exception extends \Exception
{
    /** Fehlermeldung als HTML-sicherer Text. */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_COMPAT | ENT_HTML401) . "</strong><br />\n";
    }
}
