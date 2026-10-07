<?php
declare(strict_types=1);

final class AdminController
{
    public function index(): void
    {
        requireRole(['admin']);
        render('admin/index', ['pageTitle' => 'Ylläpito']);
    }

    /* ---------------- Käyttäjät ---------------- */

    public function users(array $errors = []): void
    {
        requireRole(['admin']);
        $users = db()->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC')->fetchAll();
        render('admin/users', ['pageTitle' => 'Käyttäjät', 'users' => $users, 'errors' => $errors]);
    }

    public function usersAction(): void
    {
        $user = requireRole(['admin']);
        requireCsrf();
        $action = post('action');
        $errors = [];
        $validRoles = ['student', 'support', 'admin'];

        if ($action === 'update_role') {
            $targetId = post('user_id');
            $newRole = post('role');

            if (!ctype_digit($targetId) || !in_array($newRole, $validRoles, true)) {
                $errors['general'] = 'Virheellinen pyyntö.';
            } elseif ((int)$targetId === $user['id'] && $newRole !== 'admin') {
                $errors['general'] = 'Et voi poistaa itseltäsi ylläpitäjän roolia.';
            } else {
                db()->prepare('UPDATE users SET role = :role WHERE id = :id')
                    ->execute(['role' => $newRole, 'id' => (int)$targetId]);
                flash('success', 'Rooli päivitetty.');
                redirect('/admin/users');
            }
        }

        if ($action === 'create_user') {
            $name = post('name');
            $email = post('email');
            $role = post('role', 'support');
            $password = post('password');

            if (mb_strlen($name) < 2) {
                $errors['name'] = 'Anna nimi.';
            }
            if (!isValidEmail($email)) {
                $errors['email'] = 'Anna kelvollinen sähköposti.';
            }
            if (!in_array($role, $validRoles, true)) {
                $errors['role'] = 'Virheellinen rooli.';
            }
            if (mb_strlen($password) < 8) {
                $errors['password'] = 'Salasanan tulee olla vähintään 8 merkkiä.';
            }

            if (!$errors) {
                $check = db()->prepare('SELECT id FROM users WHERE email = :email');
                $check->execute(['email' => $email]);
                if ($check->fetch()) {
                    $errors['email'] = 'Sähköposti on jo käytössä.';
                }
            }

            if (!$errors) {
                db()->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)')
                    ->execute([
                        'name' => $name,
                        'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                    ]);
                flash('success', 'Käyttäjä luotu.');
                redirect('/admin/users');
            }
        }

        $this->users($errors);
    }

    /* ---------------- Kategoriat ---------------- */

    public function categories(array $errors = []): void
    {
        requireRole(['admin']);
        $categories = db()->query(
            'SELECT c.id, c.name, c.description, COUNT(t.id) AS ticket_count
             FROM categories c
             LEFT JOIN tickets t ON t.category_id = c.id
             GROUP BY c.id, c.name, c.description
             ORDER BY c.name'
        )->fetchAll();

        render('admin/categories', ['pageTitle' => 'Kategoriat', 'categories' => $categories, 'errors' => $errors]);
    }

    public function categoriesAction(): void
    {
        requireRole(['admin']);
        requireCsrf();
        $action = post('action');
        $errors = [];

        if ($action === 'create') {
            $name = post('name');
            $description = post('description');

            if (mb_strlen($name) < 2) {
                $errors['name'] = 'Anna kategorian nimi.';
            }

            if (!$errors) {
                try {
                    db()->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)')
                        ->execute(['name' => $name, 'description' => $description ?: null]);
                    flash('success', 'Kategoria lisätty.');
                    redirect('/admin/categories');
                } catch (PDOException) {
                    $errors['name'] = 'Samanniminen kategoria on jo olemassa.';
                }
            }
        }

        if ($action === 'delete') {
            $id = post('category_id');
            if (ctype_digit($id)) {
                // Tiketit säilyvät, category_id asetetaan NULL:ksi (ks. ON DELETE SET NULL).
                db()->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => (int)$id]);
                flash('success', 'Kategoria poistettu.');
            }
            redirect('/admin/categories');
        }

        $this->categories($errors);
    }
}
