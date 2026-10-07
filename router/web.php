<?php
declare(strict_types=1);

/**
 * Selainsivujen reitit (HTML).
 * @var Router $router
 */

// Etusivu
$router->get('/', [HomeController::class, 'index']);

// Kirjautuminen ja rekisteröinti
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/logout', [AuthController::class, 'logout']);

// Oma profiili
$router->get('/profile', [ProfileController::class, 'edit']);
$router->post('/profile', [ProfileController::class, 'update']);

// Tiketit (opiskelija)
$router->get('/tickets', [TicketController::class, 'index']);
$router->get('/tickets/new', [TicketController::class, 'create']);
$router->post('/tickets/new', [TicketController::class, 'store']);
$router->get('/tickets/{id}', [TicketController::class, 'show']);
$router->post('/tickets/{id}', [TicketController::class, 'action']);

// Tukihenkilön työpöytä
$router->get('/staff', [StaffController::class, 'dashboard']);

// Ylläpito
$router->get('/admin', [AdminController::class, 'index']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->post('/admin/users', [AdminController::class, 'usersAction']);
$router->get('/admin/categories', [AdminController::class, 'categories']);
$router->post('/admin/categories', [AdminController::class, 'categoriesAction']);

// AI-chat (vaatii kirjautumisen)
$router->get('/chat', [ChatController::class, 'index']);
$router->get('/chat/{id}', [ChatController::class, 'index']);
