<?php
declare(strict_types=1);

/**
 * Kevyt asiakas paikalliselle Ollama-palvelimelle (https://ollama.com).
 * Käyttää rajapintaa POST /api/chat ja GET /api/tags.
 */
final class OllamaClient
{
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $model = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string)config('ai.ollama_url', 'http://127.0.0.1:11434'), '/');
        $this->model = $model ?? (string)config('ai.model', 'gemma4:e2b');
        $this->timeout = $timeout ?? (int)config('ai.timeout', 180);
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Lähettää keskustelun mallille ja palauttaa vastauksen tekstin.
     *
     * @param array<int, array{role:string, content:string}> $messages
     * @param array|string|null $format  JSON Schema (structured outputs) tai 'json'
     */
    public function chat(array $messages, array|string|null $format = null, array $options = []): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'keep_alive' => '30m',
            'options' => $options + [
                'temperature' => (float)config('ai.temperature', 0.3),
                'num_ctx' => (int)config('ai.num_ctx', 8192),
            ],
        ];
        if ($format !== null) {
            $payload['format'] = $format;
        }
        // gemma4 tukee thinking-tilaa; oletuksena pois päältä (nopeampi).
        $payload['think'] = (bool)config('ai.think', false);

        // AI-vastaus voi kestää (mallin lataus muistiin) – ei katkaista PHP-skriptiä kesken.
        @set_time_limit($this->timeout + 30);

        try {
            $response = $this->request('POST', '/api/chat', $payload, $this->timeout);
        } catch (RuntimeException $e) {
            $msg = mb_strtolower($e->getMessage());
            // Vanhemmat Ollama-versiot eivät tunne think-parametria tai JSON Schemaa:
            // yritetään uudelleen yhteensopivammalla pyynnöllä.
            if (str_contains($msg, 'think')) {
                unset($payload['think']);
                $response = $this->request('POST', '/api/chat', $payload, $this->timeout);
            } elseif ($format !== null && is_array($format) && (str_contains($msg, 'format') || str_contains($msg, 'schema'))) {
                $payload['format'] = 'json';
                $response = $this->request('POST', '/api/chat', $payload, $this->timeout);
            } else {
                throw $e;
            }
        }

        if (!isset($response['message']['content'])) {
            throw new RuntimeException('Ollama palautti odottamattoman vastauksen.');
        }
        return (string)$response['message']['content'];
    }

    /**
     * Muuttaa teknisen virheen käyttäjälle ymmärrettäväksi syyksi ja korjausohjeeksi.
     */
    public static function explainError(Throwable $e): string
    {
        $msg = mb_strtolower($e->getMessage());
        $model = (string)config('ai.model', 'gemma4:e2b');
        $url = (string)config('ai.ollama_url', '');
        $timeout = (int)config('ai.timeout', 300);

        return match (true) {
            str_contains($msg, 'timed out') || str_contains($msg, 'timeout') =>
                "Malli ei vastannut {$timeout} sekunnissa. Ensimmäinen vastaus lataa mallin muistiin ja voi kestää "
                . 'pitkään ilman näytönohjainta – yritä uudelleen. Tarvittaessa kasvata OLLAMA_TIMEOUT-arvoa.',
            str_contains($msg, 'not found') && (str_contains($msg, 'model') || str_contains($msg, 'pull')) =>
                "Mallia {$model} ei ole vielä ladattu. Odota, että lataus valmistuu "
                . "(docker compose logs -f ollama-pull) tai aja: docker compose exec ollama ollama pull {$model}",
            str_contains($msg, 'memory') =>
                "Mallille {$model} ei riitä muistia. Kasvata Dockerin muistirajaa (Docker Desktop → Settings → "
                . 'Resources, vähintään 8 Gt) tai käytä kevyempää mallia.',
            str_contains($msg, 'yhteyttä') || str_contains($msg, 'connect') || str_contains($msg, 'resolve') =>
                "Ollama-palvelimeen ({$url}) ei saada yhteyttä. Tarkista, että ollama-kontti on käynnissä "
                . '(docker compose ps) ja että OLLAMA_URL on oikein.',
            str_contains($msg, 'does not support') || str_contains($msg, 'unknown model architecture') =>
                'Ollama-versio on liian vanha tälle mallille. Päivitä: docker compose pull ollama && docker compose up -d',
            default => 'Tekninen syy: ' . mb_substr($e->getMessage(), 0, 300),
        };
    }

    /** Tarkistaa, vastaako palvelin ja onko malli ladattu. */
    public function status(): array
    {
        try {
            $tags = $this->request('GET', '/api/tags', null, 5);
            $models = array_map(static fn(array $m): string => (string)($m['name'] ?? ''), $tags['models'] ?? []);
            $wanted = $this->model;
            $hasModel = in_array($wanted, $models, true)
                || in_array($wanted . ':latest', $models, true);

            return [
                'online' => true,
                'model' => $wanted,
                'model_available' => $hasModel,
                'models' => $models,
            ];
        } catch (Throwable $e) {
            return [
                'online' => false,
                'model' => $this->model,
                'model_available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function request(string $method, string $path, ?array $body, int $timeout): array
    {
        $url = $this->baseUrl . $path;
        $json = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            if ($json !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            }
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                throw new RuntimeException('Ollama-palvelimeen ei saatu yhteyttä (' . $url . '): ' . $error);
            }
        } else {
            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\n",
                'content' => $json ?? '',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ]]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                throw new RuntimeException('Ollama-palvelimeen ei saatu yhteyttä (' . $url . ').');
            }
            preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0] ?? '', $m);
            $status = (int)($m[1] ?? 0);
        }

        $decoded = json_decode((string)$raw, true);
        if ($status >= 400) {
            $message = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : ('HTTP ' . $status);
            throw new RuntimeException('Ollama-virhe: ' . $message);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Ollama palautti virheellistä JSONia.');
        }
        return $decoded;
    }
}