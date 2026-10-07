<?php
// Kopioi tämä tiedosto nimelle config.php ja täytä omat asetuksesi.
// config.php ei kuulu versionhallintaan (ks. .gitignore).
//
// Kaikki arvot voi ohittaa ympäristömuuttujilla (Docker käyttää näitä,
// ks. docker-compose.yml ja .env.example).

$env = static fn(string $key, string $default = ''): string =>
    (getenv($key) !== false && getenv($key) !== '') ? (string)getenv($key) : $default;

return [
    'db' => [
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', 'opiskelijahelpdesk'),
        'user' => $env('DB_USER', 'root'),
        'pass' => $env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'name' => 'OpiskelijaHelpdesk',
        // Jätä tyhjäksi -> polku päätellään automaattisesti (toimii myös alikansiossa).
        'base_path' => $env('APP_BASE_PATH', ''),
        // false = linkit muotoa /index.php/tickets (jos mod_rewrite ei ole käytössä).
        'pretty_urls' => $env('APP_PRETTY_URLS', 'true') !== 'false',
        'debug' => $env('APP_DEBUG', 'false') === 'true',
    ],

    'ai' => [
        // Ollama-palvelimen osoite. Dockerissa: http://ollama:11434
        'ollama_url' => rtrim($env('OLLAMA_URL', 'http://127.0.0.1:11434'), '/'),
        'model' => $env('OLLAMA_MODEL', 'gemma4:e2b'),
        // Sekunteja. Ensimmäinen kutsu lataa mallin muistiin ja voi kestää.
        'timeout' => (int)$env('OLLAMA_TIMEOUT', '300'),
        'temperature' => (float)$env('OLLAMA_TEMPERATURE', '0.3'),
        'num_ctx' => (int)$env('OLLAMA_NUM_CTX', '8192'),
        // gemma4 tukee "thinking"-tilaa. Pois päältä = nopeammat vastaukset.
        'think' => $env('OLLAMA_THINK', 'false') === 'true',
        // Diagnoosi: montako tarkentavaa kysymyskierrosta vähintään / enintään
        // ennen kuin AI saa (tai sen on pakko) antaa lopullisen päätöksen.
        'min_question_rounds' => (int)$env('CHAT_MIN_QUESTION_ROUNDS', '1'),
        'max_question_rounds' => (int)$env('CHAT_MAX_QUESTION_ROUNDS', '4'),
    ],
];