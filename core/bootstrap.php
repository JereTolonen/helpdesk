<?php
declare(strict_types=1);

/**
 * Sovelluksen käynnistys: vakioit, automaattinen luokkien lataus,
 * apufunktiot, tietokanta ja sessio.
 */

define('BASE_PATH', dirname(__DIR__));

// Yksinkertainen autoloader: luokan nimi = tiedoston nimi.
// Esim. TicketController -> controllers/TicketController.php
//       Api\ChatApiController -> controllers/Api/ChatApiController.php
spl_autoload_register(static function (string $class): void {
    $relative = str_replace('\\', '/', $class) . '.php';
    foreach (['controllers', 'services', 'router'] as $dir) {
        $file = BASE_PATH . '/' . $dir . '/' . $relative;
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Istunto käynnistetään ennen asetustiedostojen latausta, jotta mahdollinen
// vahingossa tulostettu teksti asetustiedostossa ei estä istuntoevästettä.
if (session_status() === PHP_SESSION_NONE) {
    session_name('ohdesk_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require_once BASE_PATH . '/core/db.php';
require_once BASE_PATH . '/core/functions.php';
require_once BASE_PATH . '/core/auth.php';

$appConfig = loadAppConfig();

// Siivotaan asetustiedostojen mahdollinen ylimääräinen tuloste (tyhjät rivit, BOM).
if (ob_get_level() > 0 && trim((string)ob_get_contents(), " \t\n\r\0\x0B\xEF\xBB\xBF") === '') {
    ob_clean();
}
ini_set('display_errors', !empty($appConfig['app']['debug']) ? '1' : '0');
date_default_timezone_set('Europe/Helsinki');