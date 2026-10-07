<?php
declare(strict_types=1);

namespace Api;

use ChatService;
use OllamaClient;
use Throwable;

/**
 * AI-chatin REST API. Kaikki reitit vaativat kirjautumisen (paitsi /api/health).
 */
final class ChatApiController
{
    /** GET /api/health – Ollaman ja mallin tila. */
    public function health(): void
    {
        $status = (new OllamaClient())->status();
        // Ei paljasteta sisäisiä virheilmoituksia kirjautumattomalle.
        if (!isLoggedIn()) {
            unset($status['error'], $status['models']);
        }
        jsonResponse(['app' => 'ok', 'ollama' => $status]);
    }

    /** GET /api/chat/conversations – omat keskustelut. */
    public function index(): void
    {
        $user = requireApiUser();
        $rows = ChatService::listForUser($user['id']);
        jsonResponse(['conversations' => array_map(static fn(array $c): array => [
            'id' => (int)$c['id'],
            'title' => $c['title'],
            'status' => $c['status'],
            'ticket_id' => $c['ticket_id'] !== null ? (int)$c['ticket_id'] : null,
            'updated_at' => $c['updated_at'],
        ], $rows)]);
    }

    /** POST /api/chat/conversations – uusi keskustelu. Valinnainen { message } lähettää heti ensimmäisen viestin. */
    public function store(): void
    {
        $user = requireApiUser();
        requireApiCsrf();

        $id = ChatService::createConversation($user['id']);
        $conversation = ChatService::findConversation($id);

        $text = trim((string)(jsonInput()['message'] ?? ''));
        if ($text !== '') {
            $this->replyOrFail($conversation, $user, $text, 201);
        }

        jsonResponse(['conversation' => ChatService::present($conversation, $user), 'messages' => []], 201);
    }

    /** GET /api/chat/conversations/{id} – keskustelu viesteineen. */
    public function show(int $id): void
    {
        $user = requireApiUser();
        $conversation = $this->load($id, $user);

        jsonResponse([
            'conversation' => ChatService::present($conversation, $user),
            'messages' => ChatService::messages($id),
        ]);
    }

    /** POST /api/chat/conversations/{id}/messages – { message } -> AI:n vastaus. */
    public function message(int $id): void
    {
        $user = requireApiUser();
        requireApiCsrf();
        $conversation = $this->load($id, $user, true);

        if (in_array($conversation['status'], ['resolved', 'escalated'], true)) {
            jsonError('Keskustelu on päättynyt. Aloita uusi keskustelu.', 409);
        }

        $text = trim((string)(jsonInput()['message'] ?? ''));
        if ($text === '') {
            jsonError('Viesti ei voi olla tyhjä.', 422);
        }
        if (mb_strlen($text) > ChatService::MAX_MESSAGE_LENGTH) {
            jsonError('Viesti on liian pitkä (enintään ' . ChatService::MAX_MESSAGE_LENGTH . ' merkkiä).', 422);
        }

        $this->replyOrFail($conversation, $user, $text);
    }

    /** POST /api/chat/conversations/{id}/resolve – AI:n ratkaisu auttoi. */
    public function resolve(int $id): void
    {
        $user = requireApiUser();
        requireApiCsrf();
        $conversation = $this->load($id, $user, true);

        if ($conversation['status'] === 'escalated') {
            jsonError('Keskustelusta on jo tehty tukipyyntö.', 409);
        }

        (new ChatService())->resolve($conversation);
        $this->show($id);
    }

    /** POST /api/chat/conversations/{id}/ticket – luo tukipyyntö keskustelusta. */
    public function createTicket(int $id): void
    {
        $user = requireApiUser();
        requireApiCsrf();
        $conversation = $this->load($id, $user, true);

        if (!ChatService::messages($id)) {
            jsonError('Kerro ensin ongelmastasi, jotta tukipyyntöön saadaan kuvaus.', 422);
        }

        $ticketId = (new ChatService())->createTicket($conversation, $user, false);

        jsonResponse([
            'ticket_id' => $ticketId,
            'ticket_url' => url('/tickets/' . $ticketId),
            'conversation' => ChatService::present(ChatService::findConversation($id), $user),
            'messages' => ChatService::messages($id),
        ], 201);
    }

    /** DELETE /api/chat/conversations/{id} */
    public function destroy(int $id): void
    {
        $user = requireApiUser();
        requireApiCsrf();
        $this->load($id, $user, true);

        db()->prepare('DELETE FROM chat_conversations WHERE id = :id')->execute(['id' => $id]);
        jsonResponse(['deleted' => true]);
    }

    /* ------------------------------------------------------------ */

    private function load(int $id, array $user, bool $write = false): array
    {
        $conversation = ChatService::findConversation($id);
        if (!$conversation || !ChatService::canView($conversation, $user)) {
            jsonError('Keskustelua ei löytynyt.', 404);
        }
        if ($write && !ChatService::canWrite($conversation, $user)) {
            jsonError('Voit muokata vain omia keskustelujasi.', 403);
        }
        return $conversation;
    }

    private function replyOrFail(array $conversation, array $user, string $text, int $status = 200): never
    {
        try {
            $result = (new ChatService())->reply($conversation, $user, $text);
        } catch (Throwable $e) {
            error_log('AI-chat virhe: ' . $e->getMessage());
            jsonError(
                'AI-avustaja ei juuri nyt vastaa. ' . OllamaClient::explainError($e),
                503,
                ['detail' => config('app.debug') ? $e->getMessage() : null,
                 'conversation' => ChatService::present($conversation, $user)]
            );
        }
        jsonResponse($result, $status);
    }
}