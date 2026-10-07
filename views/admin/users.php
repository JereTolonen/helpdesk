<?php /** @var array $users @var array $errors */ $action = url('/admin/users'); ?>
<h1 class="section-title">Käyttäjät ja roolit</h1>
<p class="section-description"><a class="text-accent" href="<?= e(url('/admin')) ?>">&larr; Takaisin ylläpitoon</a></p>

<?php if (isset($errors['general'])): ?>
  <div class="alert alert--error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<section class="card">
  <h2 class="card__title">Lisää käyttäjä (esim. tukihenkilö)</h2>
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="create_user">

    <div class="form-group">
      <label for="name">Nimi</label>
      <input type="text" id="name" name="name" required>
      <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
    </div>
    <div class="form-group">
      <label for="email">Sähköposti</label>
      <input type="email" id="email" name="email" required>
      <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
    </div>
    <div class="form-group">
      <label for="role">Rooli</label>
      <select id="role" name="role">
        <option value="support">Tukihenkilö</option>
        <option value="admin">Ylläpitäjä</option>
        <option value="student">Opiskelija</option>
      </select>
    </div>
    <div class="form-group">
      <label for="password">Salasana</label>
      <input type="password" id="password" name="password" required minlength="8">
      <?php if (isset($errors['password'])): ?><span class="field-error"><?= e($errors['password']) ?></span><?php endif; ?>
    </div>
    <div class="form-actions">
      <button type="submit" class="button">Luo käyttäjä</button>
    </div>
  </form>
</section>

<section class="card">
  <h2 class="card__title">Kaikki käyttäjät</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nimi</th><th>Sähköposti</th><th>Rooli</th><th>Luotu</th><th>Muuta rooli</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['role']) ?></td>
            <td><?= e(formatDate($u['created_at'], 'd.m.Y')) ?></td>
            <td>
              <form method="post" action="<?= e($action) ?>" class="form-actions" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <select name="role" class="button--small">
                  <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Opiskelija</option>
                  <option value="support" <?= $u['role'] === 'support' ? 'selected' : '' ?>>Tukihenkilö</option>
                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Ylläpitäjä</option>
                </select>
                <button type="submit" class="button button--small">Tallenna</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
