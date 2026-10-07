<?php
declare(strict_types=1);

/**
 * Kevyt reititin.
 *
 *   $router->get('/tickets/{id}', [TicketController::class, 'show']);
 *
 * {nimi}-paikkamerkit välitetään kontrollerin metodille parametreina
 * (numeroiset muutetaan int-tyyppisiksi).
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:callable|array}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void    { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, callable|array $handler): void   { $this->add('POST', $pattern, $handler); }
    public function put(string $pattern, callable|array $handler): void    { $this->add('PUT', $pattern, $handler); }
    public function delete(string $pattern, callable|array $handler): void { $this->add('DELETE', $pattern, $handler); }

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = preg_replace_callback(
            '#\{(\w+)\}#',
            static fn(array $m): string => $m[1] === 'id' || str_ends_with($m[1], 'Id')
                ? '(?P<' . $m[1] . '>\d+)'
                : '(?P<' . $m[1] . '>[^/]+)',
            rtrim($pattern, '/') ?: '/'
        );

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
        ];
    }

    /** Selvittää pyydetyn reitin polun (ilman sovelluksen juuripolkua). */
    public static function currentPath(): string
    {
        $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = rawurldecode($path);

        $base = basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        // Tuki osoitteille ilman mod_rewritea: /index.php/tickets
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }

        $path = '/' . trim($path, '/');
        return $path;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        // HTML-lomakkeet voivat lähettää PUT/DELETE kentällä _method.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string)$_POST['_method']);
        }

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[] = ctype_digit($value) ? (int)$value : $value;
                }
            }

            $this->invoke($route['handler'], $params);
            return;
        }

        $isApi = str_starts_with($path, '/api/') || $path === '/api';

        if ($allowedMethods) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            $isApi ? jsonError('Metodi ei ole sallittu.', 405) : abort(405, 'Metodi ei ole sallittu.');
        }

        $isApi ? jsonError('Rajapintaa ei löytynyt.', 404) : abort(404, 'Sivua ei löytynyt.');
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler) && is_string($handler[0])) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action(...$params);
            return;
        }
        $handler(...$params);
    }
}
