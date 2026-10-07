<?php
declare(strict_types=1);

final class AuthController
{
    public function showLogin(): void
    {
        if (isLoggedIn()) {
            redirect('/');
        }
        render('auth/login', ['pageTitle' => 'Kirjaudu sisään', 'errors' => [], 'email' => '']);
    }

    public function login(): void
    {
        if (isLoggedIn()) {
            redirect('/');
        }
        requireCsrf();

        $errors = [];
        $email = post('email');
        $password = post('password');

        if ($email === '' || $password === '') {
            $errors['general'] = 'Täytä sähköposti ja salasana.';
        } elseif (!attemptLogin($email, $password)) {
            $errors['general'] = 'Väärä sähköposti tai salasana.';
        } else {
            // Palataan sivulle, jolle yritettiin ennen kirjautumista (esim. AI-chat).
            $target = $_SESSION['intended'] ?? '/';
            unset($_SESSION['intended']);
            redirect($target);
        }

        render('auth/login', ['pageTitle' => 'Kirjaudu sisään', 'errors' => $errors, 'email' => $email]);
    }

    public function showRegister(): void
    {
        if (isLoggedIn()) {
            redirect('/');
        }
        render('auth/register', ['pageTitle' => 'Rekisteröidy', 'errors' => [], 'name' => '', 'email' => '']);
    }

    public function register(): void
    {
        if (isLoggedIn()) {
            redirect('/');
        }
        requireCsrf();

        $errors = [];
        $name = post('name');
        $email = post('email');
        $password = post('password');
        $passwordConfirm = post('password_confirm');

        if ($name === '' || mb_strlen($name) < 2) {
            $errors['name'] = 'Anna nimi (vähintään 2 merkkiä).';
        }
        if ($email === '' || !isValidEmail($email)) {
            $errors['email'] = 'Anna kelvollinen sähköpostiosoite.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Salasanan tulee olla vähintään 8 merkkiä.';
        }
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Salasanat eivät täsmää.';
        }

        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Sähköpostiosoite on jo käytössä.';
            }
        }

        if (!$errors) {
            // Julkinen rekisteröinti luo aina opiskelija-tunnuksen.
            // Tukihenkilö- ja ylläpitäjätunnukset luodaan ylläpidon kautta.
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'student',
            ]);

            flash('success', 'Tunnus luotu. Voit nyt kirjautua sisään.');
            redirect('/login');
        }

        render('auth/register', [
            'pageTitle' => 'Rekisteröidy',
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
        ]);
    }

    public function logout(): void
    {
        logoutUser();
        session_start();
        flash('info', 'Olet kirjautunut ulos.');
        redirect('/login');
    }
}
