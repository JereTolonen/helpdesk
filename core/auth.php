<?php
declare(strict_types=1);

/** Palauttaa kirjautuneen käyttäjän tiedot tai null. */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function isStaff(?array $user = null): bool
{
    $user ??= currentUser();
    return $user !== null && in_array($user['role'], ['support', 'admin'], true);
}

/** Pakottaa kirjautumisen - ohjaa login-sivulle jos ei kirjautunut. */
function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['intended'] = $GLOBALS['currentPath'] ?? '/';
        }
        flash('error', 'Kirjaudu sisään jatkaaksesi.');
        redirect('/login');
    }
    return $user;
}

/**
 * Pakottaa vaaditun roolin (yksi tai useampi sallittu).
 * Käyttöoikeuksien tarkistus tapahtuu AINA palvelinpuolella tätä funktiota käyttäen.
 */
function requireRole(array $roles): array
{
    $user = requireLogin();
    if (!in_array($user['role'], $roles, true)) {
        abort(403, 'Ei käyttöoikeutta tähän toimintoon.');
    }
    return $user;
}

/** REST API: vaatii kirjautumisen, palauttaa 401 JSON-virheen jos ei kirjautunut. */
function requireApiUser(array $roles = []): array
{
    $user = currentUser();
    if ($user === null) {
        jsonError('Kirjautuminen vaaditaan.', 401);
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        jsonError('Ei käyttöoikeutta.', 403);
    }
    return $user;
}

/** Kirjaa käyttäjän sisään sähköpostin ja salasanan perusteella. Palauttaa true/false. */
function attemptLogin(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, name, email, password, role FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Ei koskaan tallenneta salasanahashia sessioon.
    unset($user['password']);
    $user['id'] = (int)$user['id'];
    $_SESSION['user'] = $user;
    session_regenerate_id(true);

    return true;
}

/** Päivittää session käyttäjätiedot (esim. profiilin muokkauksen jälkeen). */
function refreshSessionUser(int $id): void
{
    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    if ($user) {
        $user['id'] = (int)$user['id'];
        $_SESSION['user'] = $user;
    }
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
