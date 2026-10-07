<?php
declare(strict_types=1);

/* ---------------------------------------------------------------
 * HTML / turvallisuus
 * ------------------------------------------------------------- */

/** Turvallinen HTML-tulostus (XSS-suojaus). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------------
 * Polut ja URL-osoitteet
 * ------------------------------------------------------------- */

/**
 * Sovelluksen juuripolku URL:ssa ('' tai esim. '/helpdesk/public').
 * Päätellään front controllerin (public/index.php) sijainnista, joten
 * sovellus toimii myös alikansiossa ilman lisäasetuksia.
 */
function basePath(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configured = (string)config('app.base_path', '');
    if ($configured !== '') {
        return $base = rtrim($configured, '/');
    }

    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');

    // Projektin juuren .htaccess ohjaa pyynnöt public/-kansioon (palvelimet, joissa
    // document rootia ei voi vaihtaa). Silloin osoitteissa ei näy "/public".
    $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (str_ends_with($base, '/public') && !str_starts_with($requestPath . '/', $base . '/')) {
        $base = substr($base, 0, -strlen('/public'));
    }

    return $base;
}

/** Rakentaa sisäisen linkin reitille, esim. url('/tickets/5'). */
function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    if (!config('app.pretty_urls', true)) {
        return basePath() . '/index.php' . ($path === '/' ? '' : $path);
    }
    return basePath() . $path;
}

/** Linkki public-kansion staattiseen tiedostoon, esim. asset('css/style.css'). */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/' . ltrim($path, '/');
    $version = is_file($file) ? '?v=' . filemtime($file) : '';
    return basePath() . '/' . ltrim($path, '/') . $version;
}

/** Ohjaa toiseen osoitteeseen ja lopettaa suorituksen. Polku on reitti, esim. '/login'. */
function redirect(string $path): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** Onko nykyinen reitti annettu polku (navigaation korostusta varten). */
function isActive(string $prefix): bool
{
    $current = $GLOBALS['currentPath'] ?? '/';
    return $prefix === '/' ? $current === '/' : str_starts_with($current, $prefix);
}

/* ---------------------------------------------------------------
 * Näkymät
 * ------------------------------------------------------------- */

/**
 * Renderöi näkymän views-kansiosta yhteisen layoutin sisään.
 * render('tickets/index', ['tickets' => $tickets, 'pageTitle' => 'Omat tiketit'])
 */
function render(string $view, array $data = [], bool $withLayout = true): void
{
    $viewFile = BASE_PATH . '/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('Näkymää ei löytynyt: ' . $view);
    }

    extract($data, EXTR_SKIP);
    $user = currentUser();
    $pageTitle = $data['pageTitle'] ?? config('app.name', 'OpiskelijaHelpdesk');

    if ($withLayout) {
        require BASE_PATH . '/views/layouts/header.php';
    }
    require $viewFile;
    if ($withLayout) {
        require BASE_PATH . '/views/layouts/footer.php';
    }
}

/** Näyttää virhesivun ja lopettaa. */
function abort(int $status, string $message): never
{
    http_response_code($status);
    render('errors/error', ['status' => $status, 'message' => $message, 'pageTitle' => 'Virhe ' . $status]);
    exit;
}

/* ---------------------------------------------------------------
 * Flash-viestit
 * ------------------------------------------------------------- */

/** Tallentaa kertaluontoisen ilmoitusviestin sessioon. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Hakee ja tyhjentää kertaluontoiset ilmoitusviestit. */
function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------------------------------------------------------------
 * Pyyntödata
 * ------------------------------------------------------------- */

/** Yksinkertainen apu POST-datan hakuun oletusarvolla. */
function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function get(string $key, string $default = ''): string
{
    return trim((string)($_GET[$key] ?? $default));
}

/** Lukee JSON-pyynnön rungon (REST API). */
function jsonInput(): array
{
    static $data = null;
    if ($data === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $data = is_array($decoded) ? $decoded : $_POST;
    }
    return $data;
}

/** Lähettää JSON-vastauksen ja lopettaa suorituksen. */
function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400, array $extra = []): never
{
    jsonResponse(['error' => $message] + $extra, $status);
}

/* ---------------------------------------------------------------
 * Tikettien tilat ja prioriteetit
 * ------------------------------------------------------------- */

/** Sallitut tikettien tilat ja prioriteetit (validointia varten). */
function allowedStatuses(): array
{
    return ['Uusi', 'Käsittelyssä', 'Odottaa opiskelijaa', 'Ratkaistu', 'Suljettu'];
}

function allowedPriorities(): array
{
    return ['Matala', 'Normaali', 'Korkea', 'Kiireellinen'];
}

/** Validoi sähköpostiosoitteen muodon. */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Palauttaa CSS-luokan statuksen värikoodausta varten. */
function statusBadgeClass(string $status): string
{
    return match ($status) {
        'Uusi' => 'badge badge--new',
        'Käsittelyssä' => 'badge badge--progress',
        'Odottaa opiskelijaa' => 'badge badge--waiting',
        'Ratkaistu' => 'badge badge--resolved',
        'Suljettu' => 'badge badge--closed',
        default => 'badge',
    };
}

function priorityBadgeClass(string $priority): string
{
    return match ($priority) {
        'Matala' => 'badge badge--low',
        'Normaali' => 'badge badge--normal',
        'Korkea' => 'badge badge--high',
        'Kiireellinen' => 'badge badge--urgent',
        default => 'badge',
    };
}

function formatDate(?string $value, string $format = 'd.m.Y H:i'): string
{
    return $value ? date($format, strtotime($value)) : '—';
}

/* ---------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------- */

/** Yksinkertainen CSRF-tokenin luonti ja tarkistus. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Lomakkeiden CSRF-tarkistus. */
function requireCsrf(): void
{
    if (empty($_SESSION['csrf_token'])) {
        // Istuntoa ei ole: eväste puuttuu tai istuntoa ei voitu tallentaa palvelimelle.
        error_log('CSRF: istunto puuttuu (session_id=' . session_id() . ', cookie='
            . (isset($_COOKIE[session_name()]) ? 'kyllä' : 'ei') . ', save_path=' . session_save_path() . ')');
        abort(400, 'Istunto on vanhentunut tai selain esti evästeet. Lataa sivu uudelleen ja yritä uudelleen.');
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        abort(400, 'Virheellinen tai vanhentunut lomake (CSRF). Lataa sivu uudelleen ja yritä uudelleen.');
    }
}

/** REST API:n CSRF-tarkistus: token X-CSRF-Token -otsakkeessa. */
function requireApiCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (jsonInput()['csrf_token'] ?? null);
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        jsonError('Virheellinen tai puuttuva CSRF-token.', 419);
    }
}