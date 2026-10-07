<?php
declare(strict_types=1);

/** Käyttäjän omien tietojen muokkaus. */
final class ProfileController
{
    public function edit(): void
    {
        $user = requireLogin();
        render('profile/edit', [
            'pageTitle' => 'Omat tiedot',
            'errors' => [],
            'name' => $user['name'],
            'email' => $user['email'],
        ]);
    }

    public function update(): void
    {
        $user = requireLogin();
        requireCsrf();

        $errors = [];
        $name = post('name');
        $email = post('email');
        $currentPassword = post('current_password');
        $newPassword = post('new_password');
        $newPasswordConfirm = post('new_password_confirm');

        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Anna nimi (vähintään 2 merkkiä).';
        }
        if (!isValidEmail($email)) {
            $errors['email'] = 'Anna kelvollinen sähköpostiosoite.';
        } else {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id');
            $stmt->execute(['email' => $email, 'id' => $user['id']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Sähköpostiosoite on jo käytössä.';
            }
        }

        $changePassword = $newPassword !== '';
        if ($changePassword) {
            $stmt = db()->prepare('SELECT password FROM users WHERE id = :id');
            $stmt->execute(['id' => $user['id']]);
            $hash = (string)$stmt->fetchColumn();

            if (!password_verify($currentPassword, $hash)) {
                $errors['current_password'] = 'Nykyinen salasana on väärin.';
            }
            if (mb_strlen($newPassword) < 8) {
                $errors['new_password'] = 'Uuden salasanan tulee olla vähintään 8 merkkiä.';
            }
            if ($newPassword !== $newPasswordConfirm) {
                $errors['new_password_confirm'] = 'Salasanat eivät täsmää.';
            }
        }

        if (!$errors) {
            db()->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id')
                ->execute(['name' => $name, 'email' => $email, 'id' => $user['id']]);

            if ($changePassword) {
                db()->prepare('UPDATE users SET password = :password WHERE id = :id')
                    ->execute(['password' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $user['id']]);
            }

            refreshSessionUser($user['id']);
            flash('success', 'Tiedot päivitetty.');
            redirect('/profile');
        }

        render('profile/edit', [
            'pageTitle' => 'Omat tiedot',
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
        ]);
    }
}
