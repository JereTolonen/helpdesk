<?php
declare(strict_types=1);

/**
 * Keskitetty asetusten lataus ja PDO-tietokantayhteys.
 * Kutsu db() saadaksesi jaetun PDO-instanssin (singleton).
 */

function loadAppConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $configFile = BASE_PATH . '/config/config.php';
    $exampleFile = BASE_PATH . '/config/config.example.php';

    if (file_exists($configFile)) {
        $config = require $configFile;
    } elseif (file_exists($exampleFile)) {
        // Kehitystilassa käytetään esimerkkiasetuksia, jos varsinaista ei ole tehty.
        $config = require $exampleFile;
    } else {
        throw new RuntimeException('Asetustiedostoa config/config.php ei löytynyt.');
    }

    return $config;
}

/** Hakee asetuksen pistenotaatiolla, esim. config('ai.model'). */
function config(string $key, mixed $default = null): mixed
{
    $value = loadAppConfig();
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = loadAppConfig()['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Sama aikavyöhyke tietokantaan kuin PHP:lle (TIMESTAMP-sarakkeet näkyvät oikein).
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    } catch (PDOException $e) {
        // Ei koskaan näytetä tietokannan virhettä sellaisenaan käyttäjälle.
        error_log('Tietokantayhteysvirhe: ' . $e->getMessage());
        http_response_code(500);
        die('Tietokantayhteys epäonnistui. Tarkista config/config.php-asetukset.');
    }

    return $pdo;
}
