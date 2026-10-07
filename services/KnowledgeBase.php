<?php
declare(strict_types=1);

/**
 * Yksinkertainen RAG (Retrieval-Augmented Generation).
 *
 * Lukee yrityksen ohjeet knowledge/-kansion Markdown-tiedostoista,
 * pilkkoo ne "## "-otsikoiden kohdalta paloiksi ja hakee käyttäjän
 * kysymykseen parhaiten sopivat palat avainsanahaulla (TF-IDF-tyyppinen
 * pisteytys + kevyt suomen kielen "stemmaus" sanan alun perusteella).
 * Löydetyt palat liitetään mallin järjestelmäkehotteeseen.
 *
 * Uusia ohjeita voi lisätä yksinkertaisesti lisäämällä .md-tiedoston kansioon.
 */
final class KnowledgeBase
{
    private const STOPWORDS = [
        'ja', 'tai', 'on', 'ei', 'en', 'se', 'ne', 'että', 'kun', 'jos', 'niin', 'mutta', 'myös', 'vain',
        'minä', 'mä', 'sinä', 'hän', 'me', 'te', 'he', 'mun', 'minun', 'olen', 'oli', 'ole', 'kuin',
        'miten', 'mitä', 'mikä', 'mutta', 'voi', 'saa', 'saan', 'enkä', 'tämä', 'tuo', 'joka', 'mukaan',
        'the', 'and', 'for', 'with', 'not', 'can', 'how', 'what', 'have', 'this', 'that', 'my', 'is',
    ];

    /** @var array<int, array{source:string, title:string, text:string, terms:array<string,int>}> */
    private array $chunks = [];
    /** @var array<string,int> */
    private array $docFreq = [];

    public function __construct(?string $dir = null)
    {
        $dir ??= BASE_PATH . '/knowledge';
        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $this->indexFile($file);
        }
        foreach ($this->chunks as $chunk) {
            foreach (array_keys($chunk['terms']) as $term) {
                $this->docFreq[$term] = ($this->docFreq[$term] ?? 0) + 1;
            }
        }
    }

    /**
     * Palauttaa enintään $limit osuvinta ohjepalaa.
     * @return array<int, array{source:string, title:string, text:string, score:float}>
     */
    public function search(string $query, int $limit = 3): array
    {
        $queryTerms = array_unique(array_keys(self::terms($query)));
        if (!$queryTerms || !$this->chunks) {
            return [];
        }

        $total = count($this->chunks);
        $results = [];

        foreach ($this->chunks as $chunk) {
            $score = 0.0;
            foreach ($queryTerms as $term) {
                if (isset($chunk['terms'][$term])) {
                    $idf = log(($total + 1) / ($this->docFreq[$term] + 0.5));
                    $score += (1 + log($chunk['terms'][$term])) * $idf;
                }
            }
            if ($score > 0.9) {
                $results[] = [
                    'source' => $chunk['source'],
                    'title' => $chunk['title'],
                    'text' => $chunk['text'],
                    'score' => round($score, 2),
                ];
            }
        }

        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        // Pidetään vain riittävän osuvat: vähintään puolet parhaan osuman pisteistä.
        $best = $results[0]['score'] ?? 0;
        $results = array_filter($results, static fn(array $r): bool => $r['score'] >= $best * 0.5);

        return array_slice(array_values($results), 0, $limit);
    }

    private function indexFile(string $file): void
    {
        $content = (string)file_get_contents($file);
        $source = basename($file);

        // Dokumentin pääotsikko (# Otsikko)
        $docTitle = preg_match('/^#\s+(.+)$/m', $content, $m) ? trim($m[1]) : $source;

        $parts = preg_split('/^(?=##\s)/m', $content) ?: [$content];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || str_starts_with($part, '# ') && !str_contains($part, "\n")) {
                continue;
            }
            $title = preg_match('/^##\s+(.+)$/m', $part, $m) ? $docTitle . ' – ' . trim($m[1]) : $docTitle;
            // Otsikot painavat enemmän: lisätään ne termeihin kahdesti.
            $terms = self::terms($part . ' ' . $title . ' ' . $title);
            if (!$terms) {
                continue;
            }
            $this->chunks[] = [
                'source' => $source,
                'title' => $title,
                'text' => mb_substr($part, 0, 1800),
                'terms' => $terms,
            ];
        }
    }

    /** Pilkkoo tekstin termeiksi: pienet kirjaimet, ei stop-sanoja, sanan alku (max 6 merkkiä). */
    private static function terms(string $text): array
    {
        $text = mb_strtolower($text);
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-\.]*[\p{L}\p{N}]|[\p{L}\p{N}]/u', $text, $m);

        $terms = [];
        foreach ($m[0] as $word) {
            if (mb_strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            // Kevyt "stemmaus": suomen taivutusmuodot (tulostin, tulostimen, tulostimeen)
            // osuvat samaan termiin, kun verrataan sanan kuutta ensimmäistä merkkiä.
            $stem = mb_substr($word, 0, 6);
            $terms[$stem] = ($terms[$stem] ?? 0) + 1;
        }
        return $terms;
    }
}
