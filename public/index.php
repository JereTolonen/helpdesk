<?php
declare(strict_types=1);

/**
 * Front controller: kaikki pyynnöt ohjataan tänne (ks. .htaccess).
 * public/ on ainoa kansio, joka näkyy selaimelle.
 */

// PHP:n sisäänrakennettu kehityspalvelin: tarjoile olemassa olevat
// staattiset tiedostot (css, js, kuvat) sellaisenaan.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

// Puskuroidaan tuloste: jos jokin tiedosto (esim. config.php) tulostaa vahingossa
// tyhjän rivin tai BOM-merkin ennen <?php-tagia, otsakkeet ja istuntoeväste
// voidaan silti lähettää (muuten CSRF-tarkistus epäonnistuisi aina).
ob_start();

require dirname(__DIR__) . '/core/bootstrap.php';

$router = new Router();
require BASE_PATH . '/router/web.php';
require BASE_PATH . '/router/api.php';

$currentPath = Router::currentPath();
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $currentPath);