<?php
declare(strict_types=1);

/**
 * AI-chatin logiikka.
 *
 * DIAGNOOSI ENSIN
 * ---------------
 * Malli vastaa aina rakenteisena JSONina (Ollaman "structured outputs"):
 *
 *   { phase: "question" | "solution" | "escalate", message, title, summary, category, priority }
 *
 * Palvelin päättää jokaisella vuorolla, mitkä phase-arvot ovat SALLITTUJA,
 * ja välittää ne mallille JSON Scheman enum-listana. Näin diagnoosijärjestys
 * on taattu riippumatta siitä, kuinka hyvin (pieni) malli noudattaa ohjeita:
 *
 *   kysymyskierroksia < min  -> vain "question"         (pakko kysyä ensin)
 *   kysymyskierroksia >= max -> "solution" | "escalate" (pakko tehdä päätös)
 *   muuten                   -> kaikki kolme            (malli päättää)
 *
 * "escalate" luo tukipyynnön automaattisesti. "solution"-vastauksen jälkeen
 * käyttäjä voi merkitä ongelman ratkenneeksi tai pyytää tukipyynnön.
 */
final class ChatService
{
    public const MAX_MESSAGE_LENGTH = 4000;

    private OllamaClient $ollama;
    private KnowledgeBase $knowledge;

    public function __construct(?OllamaClient $ollama = null, ?KnowledgeBase $knowledge = null)
    {
        $this->ollama = $ollama ?? new OllamaClient();
        $this->knowledge = $knowledge ?? new KnowledgeBase();
    }

    /* =============================================================
     * Keskustelut
     * =========================================================== */

    public static function findConversation(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT cc.*, u.name AS user_name, c.name AS category_name, t.status AS ticket_status
             FROM chat_conversations cc
             JOIN users u ON u.id = cc.user_id
             LEFT JOIN categories c ON c.id = cc.category_id
             LEFT JOIN tickets t ON t.id = cc.ticket_id
             WHERE cc.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Omistaja näkee oman keskustelunsa, tukihenkilö/admin kaikki (lukutilassa). */
    public static function canView(array $conversation, array $user): bool
    {
        return (int)$conversation['user_id'] === (int)$user['id'] || isStaff($user);
    }

    public static function canWrite(array $conversation, array $user): bool
    {
        return (int)$conversation['user_id'] === (int)$user['id'];
    }

    public static function listForUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT id, title, status, ticket_id, created_at, updated_at
             FROM chat_conversations
             WHERE user_id = :user_id
             ORDER BY updated_at DESC, id DESC
             LIMIT 50'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function createConversation(int $userId): int
    {
        db()->prepare('INSERT INTO chat_conversations (user_id, title) VALUES (:user_id, :title)')
            ->execute(['user_id' => $userId, 'title' => 'Uusi keskustelu']);
        return (int)db()->lastInsertId();
    }

    public static function messages(int $conversationId): array
    {
        $stmt = db()->prepare(
            'SELECT id, role, content, meta, created_at
             FROM messages
             WHERE conversation_id = :id
             ORDER BY id ASC'
        );
        $stmt->execute(['id' => $conversationId]);

        return array_map(static function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['meta'] = $row['meta'] ? (json_decode($row['meta'], true) ?: null) : null;
            return $row;
        }, $stmt->fetchAll());
    }

    /** Julkinen esitysmuoto API-vastauksiin. */
    public static function present(array $conversation, ?array $user = null): array
    {
        return [
            'id' => (int)$conversation['id'],
            'title' => $conversation['title'],
            'status' => $conversation['status'],
            'summary' => $conversation['summary'],
            'category' => $conversation['category_name'] ?? null,
            'priority' => $conversation['priority'],
            'ticket_id' => $conversation['ticket_id'] !== null ? (int)$conversation['ticket_id'] : null,
            'ticket_status' => $conversation['ticket_status'] ?? null,
            'owner' => $conversation['user_name'] ?? null,
            'can_write' => $user ? self::canWrite($conversation, $user) : false,
            'created_at' => $conversation['created_at'],
            'updated_at' => $conversation['updated_at'],
        ];
    }

    /* =============================================================
     * Vastauksen tuottaminen
     * =========================================================== */

    /**
     * Käsittelee käyttäjän viestin ja tallentaa sekä viestin että AI:n vastauksen.
     * @return array{messages: array, conversation: array}
     */
    public function reply(array $conversation, array $user, string $text): array
    {
        $conversationId = (int)$conversation['id'];
        $history = self::messages($conversationId);

        // Montako tarkentavaa kysymyskierrosta AI on jo käynyt?
        $questionRounds = count(array_filter(
            $history,
            static fn(array $m): bool => $m['role'] === 'assistant' && ($m['meta']['phase'] ?? '') === 'question'
        ));
        $allowedPhases = $this->allowedPhases($questionRounds);

        // RAG: haetaan ohjeita käyttäjän viimeisimpien viestien perusteella.
        $userTexts = array_column(array_filter($history, static fn(array $m): bool => $m['role'] === 'user'), 'content');
        $userTexts[] = $text;
        $sources = $this->knowledge->search(implode("\n", array_slice($userTexts, -3)), 3);

        $categories = array_column(TicketRepository::categories(), 'name');

        // Rakennetaan mallille lähetettävä keskustelu.
        $modelMessages = [[
            'role' => 'system',
            'content' => $this->systemPrompt($allowedPhases, $questionRounds, $sources, $categories),
        ]];
        foreach (array_slice($history, -20) as $m) {
            if ($m['role'] === 'user' || $m['role'] === 'assistant') {
                $modelMessages[] = ['role' => $m['role'], 'content' => $m['content']];
            }
        }
        $modelMessages[] = ['role' => 'user', 'content' => $text];

        $raw = $this->ollama->chat($modelMessages, $this->schema($allowedPhases, $categories));
        $answer = $this->parseAnswer($raw, $allowedPhases, $categories);

        // Tallennetaan vasta onnistuneen vastauksen jälkeen, jotta virhetilanteessa
        // käyttäjä voi yksinkertaisesti lähettää viestin uudelleen.
        $saved = [];
        $saved[] = $this->storeMessage($conversationId, 'user', $text, null);
        $saved[] = $this->storeMessage($conversationId, 'assistant', $answer['message'], [
            'phase' => $answer['phase'],
            'sources' => array_values(array_unique(array_column($sources, 'title'))),
        ]);

        // Päivitetään keskustelun diagnoositiedot.
        $isFirstReply = $questionRounds === 0 && !array_filter($history, static fn($m) => $m['role'] === 'assistant');
        $title = $conversation['title'];
        if ($isFirstReply || $title === 'Uusi keskustelu' || $title === '') {
            $title = $answer['title'] !== '' ? $answer['title'] : mb_strimwidth($text, 0, 80, '…');
        }

        db()->prepare(
            'UPDATE chat_conversations
             SET title = :title, summary = :summary, category_id = :category_id, priority = :priority,
                 status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute([
            'title' => mb_substr($title, 0, 200),
            'summary' => $answer['summary'],
            'category_id' => TicketRepository::categoryIdByName($answer['category']),
            'priority' => $answer['priority'],
            'status' => $answer['phase'] === 'solution' ? 'solution_offered' : 'diagnosing',
            'id' => $conversationId,
        ]);

        // Eskalointi: luodaan tukipyyntö automaattisesti.
        if ($answer['phase'] === 'escalate') {
            $this->createTicket(self::findConversation($conversationId), $user, true);
            $all = self::messages($conversationId);
            $saved[] = end($all); // "Tukipyyntö #N luotu" -järjestelmäviesti
        }

        return [
            'messages' => $saved,
            'conversation' => self::present(self::findConversation($conversationId), $user),
        ];
    }

    /** Merkitsee ongelman ratkenneeksi (AI:n ratkaisu auttoi). */
    public function resolve(array $conversation): void
    {
        db()->prepare("UPDATE chat_conversations SET status = 'resolved', updated_at = CURRENT_TIMESTAMP WHERE id = :id")
            ->execute(['id' => $conversation['id']]);
        $this->storeMessage((int)$conversation['id'], 'system', 'Ongelma merkitty ratkaistuksi. Hienoa! 🎉', ['event' => 'resolved']);
    }

    /**
     * Luo keskustelusta tukipyynnön (tiivistelmä + koko keskustelu kuvaukseen).
     * @param bool $automatic true = AI eskaloi, false = käyttäjä pyysi
     */
    public function createTicket(array $conversation, array $user, bool $automatic = false): int
    {
        if (!empty($conversation['ticket_id'])) {
            return (int)$conversation['ticket_id'];
        }

        $messages = self::messages((int)$conversation['id']);
        $transcript = '';
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                continue;
            }
            $who = $m['role'] === 'user' ? 'Käyttäjä' : 'AI';
            $transcript .= $who . ': ' . trim($m['content']) . "\n\n";
        }
        if (mb_strlen($transcript) > 8000) {
            $transcript = '…' . mb_substr($transcript, -8000);
        }

        $summary = trim((string)$conversation['summary']);
        $description = ($automatic
                ? "[Luotu automaattisesti AI-chatista – AI arvioi, että ongelma vaatii tukihenkilön.]\n\n"
                : "[Luotu AI-chatista käyttäjän pyynnöstä – AI:n ehdotus ei ratkaissut ongelmaa.]\n\n")
            . ($summary !== '' ? "Tiivistelmä:\n" . $summary . "\n\n" : '')
            . "--- Keskustelu AI-avustajan kanssa ---\n\n" . trim($transcript);

        $title = trim((string)$conversation['title']);
        if ($title === '' || $title === 'Uusi keskustelu') {
            $title = 'AI-chat: tukipyyntö';
        }

        $ticketId = TicketRepository::create([
            'title' => $title,
            'description' => $description,
            'category_id' => $conversation['category_id'] !== null
                ? (int)$conversation['category_id']
                : TicketRepository::categoryIdByName('Muu'),
            'priority' => $conversation['priority'] ?: 'Normaali',
            'created_by' => (int)$conversation['user_id'],
        ]);

        db()->prepare(
            "UPDATE chat_conversations SET ticket_id = :ticket_id, status = 'escalated', updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        )->execute(['ticket_id' => $ticketId, 'id' => $conversation['id']]);

        $this->storeMessage(
            (int)$conversation['id'],
            'system',
            'Tukipyyntö #' . $ticketId . ' luotu' . ($automatic ? ' automaattisesti' : '') . '. Tukihenkilö jatkaa asian käsittelyä.',
            ['event' => 'ticket_created', 'ticket_id' => $ticketId]
        );

        return $ticketId;
    }

    /* =============================================================
     * Sisäiset apumetodit
     * =========================================================== */

    private function allowedPhases(int $questionRounds): array
    {
        $min = max(0, (int)config('ai.min_question_rounds', 1));
        $max = max($min + 1, (int)config('ai.max_question_rounds', 4));

        if ($questionRounds < $min) {
            return ['question'];
        }
        if ($questionRounds >= $max) {
            return ['solution', 'escalate'];
        }
        return ['question', 'solution', 'escalate'];
    }

    private function systemPrompt(array $allowedPhases, int $questionRounds, array $sources, array $categories): string
    {
        $state = match (true) {
            $allowedPhases === ['question'] =>
                "Olet kysynyt {$questionRounds} tarkentavaa kysymyskierrosta. Tiedot eivät vielä riitä diagnoosiin. "
                . 'Tällä vuorolla sinun TÄYTYY esittää tarkentavia kysymyksiä (phase="question"). Älä anna vielä ratkaisua.',
            !in_array('question', $allowedPhases, true) =>
                "Olet kysynyt jo {$questionRounds} kysymyskierrosta. Tee nyt lopullinen päätös: "
                . 'anna ratkaisu vaiheittain (phase="solution") TAI ohjaa tukihenkilölle (phase="escalate").',
            default =>
                "Olet kysynyt {$questionRounds} kysymyskierrosta. Jos tiedot riittävät varmaan diagnoosiin, anna ratkaisu "
                . '(phase="solution"). Jos jokin olennainen tieto puuttuu, kysy lisää (phase="question"). '
                . 'Jos ongelma vaatii ihmistä, valitse phase="escalate".',
        };

        $knowledge = '';
        if ($sources) {
            $knowledge = "\n\nYRITYKSEN OHJEET (käytä näitä ensisijaisesti, jos ne liittyvät ongelmaan):\n";
            foreach ($sources as $i => $s) {
                $knowledge .= "\n[" . ($i + 1) . '] ' . $s['title'] . "\n" . $s['text'] . "\n";
            }
        }

        return <<<PROMPT
Olet OpiskelijaHelpdeskin AI-tukihenkilö. Autat opiskelijoita IT- ja ohjelmistokehitysongelmissa,
kuten salasanat ja tunnukset, Outlook, tulostimet, verkko ja VPN, Linux-palvelimet, GitHub, MySQL ja ohjelmointi.

TOIMINTATAPA – DIAGNOOSI ENSIN:
1. Älä anna lopullista ratkaisua ennen kuin tiedät riittävästi. Kerää ensin tiedot tarkentavilla kysymyksillä.
2. Kysy kerrallaan 1–3 lyhyttä ja konkreettista kysymystä, esimerkiksi: käyttöjärjestelmä ja laite,
   tarkka virheilmoitus, milloin ongelma alkoi, mitä on jo kokeiltu, koskeeko ongelma muita.
   Älä kysy asioita, jotka käyttäjä on jo kertonut.
3. Kun tiedot riittävät, anna ratkaisu selkeinä numeroituina vaiheina (phase="solution").
4. Valitse phase="escalate", jos ongelma vaatii ihmistä: laiterikko, käyttöoikeuksien tai tunnusten avaus,
   tietoturvaepäily, maksut, tai ohjeet eivät ole auttaneet. Kerro silloin, että tukipyyntö luodaan.
5. Älä keksi faktoja. Jos et tiedä, sano se ja ohjaa tukihenkilölle.
6. Älä koskaan pyydä käyttäjää kertomaan salasanaansa.
7. Vastaa samalla kielellä kuin käyttäjä (suomi tai englanti). Ole ystävällinen ja tiivis.

VASTAUSMUOTO (JSON):
- phase: "question", "solution" tai "escalate"
- message: käyttäjälle näytettävä vastaus (voit käyttää rivinvaihtoja ja numeroituja listoja)
- title: lyhyt otsikko ongelmalle, enintään 80 merkkiä
- summary: tiivistelmä ongelmasta ja tähän mennessä kerätyistä tiedoista tukihenkilöä varten
- category: yksi näistä: {$this->listText($categories)}
- priority: Matala, Normaali, Korkea tai Kiireellinen (Kiireellinen vain, jos työnteko on kokonaan estynyt monelta)

TILANNE NYT: {$state}
Sallitut phase-arvot tällä vuorolla: {$this->listText($allowedPhases)}.{$knowledge}
PROMPT;
    }

    /** JSON Schema rakenteiselle vastaukselle (Ollama "format"). */
    private function schema(array $allowedPhases, array $categories): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'phase' => ['type' => 'string', 'enum' => array_values($allowedPhases)],
                'message' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'category' => ['type' => 'string', 'enum' => array_values($categories ?: ['Muu'])],
                'priority' => ['type' => 'string', 'enum' => allowedPriorities()],
            ],
            'required' => ['phase', 'message', 'title', 'summary', 'category', 'priority'],
        ];
    }

    /** Jäsentää ja validoi mallin vastauksen. Pieni malli voi joskus poiketa muodosta. */
    private function parseAnswer(string $raw, array $allowedPhases, array $categories): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data) && preg_match('/\{.*\}/s', $raw, $m)) {
            $data = json_decode($m[0], true);
        }
        if (!is_array($data)) {
            // Varasuunnitelma: näytetään raakateksti kysymyksenä/ratkaisuna.
            $data = ['message' => trim($raw)];
        }

        $phase = in_array($data['phase'] ?? null, $allowedPhases, true) ? $data['phase'] : $allowedPhases[0];
        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') {
            $message = $phase === 'question'
                ? 'Kertoisitko tarkemmin, mitä tapahtuu? Mikä laite ja käyttöjärjestelmä on käytössä, ja näkyykö jokin virheilmoitus?'
                : 'En pysty antamaan varmaa ratkaisua. Ohjaan asian tukihenkilölle.';
        }

        return [
            'phase' => $phase,
            'message' => $message,
            'title' => trim(mb_substr((string)($data['title'] ?? ''), 0, 120)),
            'summary' => trim(mb_substr((string)($data['summary'] ?? ''), 0, 4000)),
            'category' => in_array($data['category'] ?? null, $categories, true) ? $data['category'] : 'Muu',
            'priority' => in_array($data['priority'] ?? null, allowedPriorities(), true) ? $data['priority'] : 'Normaali',
        ];
    }

    private function storeMessage(int $conversationId, string $role, string $content, ?array $meta): array
    {
        db()->prepare(
            'INSERT INTO messages (conversation_id, role, content, meta) VALUES (:conversation_id, :role, :content, :meta)'
        )->execute([
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'meta' => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return [
            'id' => (int)db()->lastInsertId(),
            'role' => $role,
            'content' => $content,
            'meta' => $meta,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function listText(array $items): string
    {
        return implode(', ', array_map(static fn($i) => '"' . $i . '"', $items));
    }
}
