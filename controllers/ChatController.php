<?php
declare(strict_types=1);

/**
 * AI-chatin HTML-sivu. Varsinainen keskustelu tapahtuu selaimessa
 * (public/js/chat.js) REST API:n kautta (Api\ChatApiController).
 */
final class ChatController
{
    public function index(?int $id = null): void
    {
        $user = requireLogin(); // Mallin käyttö vaatii kirjautumisen.

        if ($id !== null) {
            $conversation = ChatService::findConversation($id);
            if (!$conversation || !ChatService::canView($conversation, $user)) {
                abort(404, 'Keskustelua ei löytynyt.');
            }
        }

        render('chat/index', [
            'pageTitle' => 'AI-chat',
            'activeConversationId' => $id,
            'model' => (string)config('ai.model', 'gemma4:e2b'),
            'scripts' => ['js/chat.js'],
        ]);
    }
}
