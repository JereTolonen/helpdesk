<?php
declare(strict_types=1);

/**
 * REST API -reitit (JSON). Kaikki vaativat kirjautumisen (session-eväste),
 * muuttavat pyynnöt lisäksi X-CSRF-Token -otsakkeen.
 * @var Router $router
 */

use Api\ChatApiController;
use Api\TicketApiController;

// Tila / terveystarkistus (ei vaadi kirjautumista)
$router->get('/api/health', [ChatApiController::class, 'health']);

// Tiketit
$router->get('/api/tickets', [TicketApiController::class, 'index']);
$router->post('/api/ticket', [TicketApiController::class, 'store']);
$router->post('/api/tickets', [TicketApiController::class, 'store']);
$router->get('/api/ticket/{id}', [TicketApiController::class, 'show']);
$router->put('/api/ticket/{id}', [TicketApiController::class, 'update']);
$router->delete('/api/ticket/{id}', [TicketApiController::class, 'destroy']);
$router->get('/api/tickets/{id}', [TicketApiController::class, 'show']);
$router->put('/api/tickets/{id}', [TicketApiController::class, 'update']);
$router->delete('/api/tickets/{id}', [TicketApiController::class, 'destroy']);

// AI-chat
$router->get('/api/chat/conversations', [ChatApiController::class, 'index']);
$router->post('/api/chat/conversations', [ChatApiController::class, 'store']);
$router->get('/api/chat/conversations/{id}', [ChatApiController::class, 'show']);
$router->delete('/api/chat/conversations/{id}', [ChatApiController::class, 'destroy']);
$router->post('/api/chat/conversations/{id}/messages', [ChatApiController::class, 'message']);
$router->post('/api/chat/conversations/{id}/resolve', [ChatApiController::class, 'resolve']);
$router->post('/api/chat/conversations/{id}/ticket', [ChatApiController::class, 'createTicket']);
