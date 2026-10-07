<?php /** @var array $errors @var string $name @var string $email */ ?>
<section class="card card--narrow card--wide">
  <h1 class="section-title">Omat tiedot</h1>
  <p class="section-description">Päivitä nimesi, sähköpostiosoitteesi tai salasanasi.</p>

  <form method="post" action="<?= e(url('/profile')) ?>" class="form-grid" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <div class="form-group">
      <label for="name">Nimi</label>
      <input type="text" id="name" name="name" value="<?= e($name) ?>" required autocomplete="name">
      <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="email">Sähköposti</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
      <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
    </div>

    <hr class="divider">
    <p class="text-muted" style="margin:0;font-size:0.85rem;">Vaihda salasana (jätä tyhjäksi, jos et halua vaihtaa).</p>

    <div class="form-group">
      <label for="current_password">Nykyinen salasana</label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password">
      <?php if (isset($errors['current_password'])): ?><span class="field-error"><?= e($errors['current_password']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="new_password">Uusi salasana</label>
      <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password">
      <span class="hint">Vähintään 8 merkkiä.</span>
      <?php if (isset($errors['new_password'])): ?><span class="field-error"><?= e($errors['new_password']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="new_password_confirm">Vahvista uusi salasana</label>
      <input type="password" id="new_password_confirm" name="new_password_confirm" minlength="8" autocomplete="new-password">
      <?php if (isset($errors['new_password_confirm'])): ?><span class="field-error"><?= e($errors['new_password_confirm']) ?></span><?php endif; ?>
    </div>

    <div class="form-actions">
      <button type="submit" class="button">Tallenna</button>
      <a class="button button--secondary" href="<?= e(url('/')) ?>">Peruuta</a>
    </div>
  </form>
</section>
