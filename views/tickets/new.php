<?php /** @var array $errors @var array $old @var array $categories */ ?>
<section class="card">
  <h1 class="section-title">Luo uusi tukipyyntö</h1>
  <p class="section-description">
    Kuvaile ongelmaa mahdollisimman tarkasti, jotta tukihenkilö voi auttaa nopeasti.
    Vinkki: <a class="text-accent" href="<?= e(url('/chat')) ?>">AI-avustaja</a> voi ratkaista ongelman heti tai luoda tiketin puolestasi.
  </p>

  <form method="post" action="<?= e(url('/tickets/new')) ?>" class="form-grid" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <div class="form-group">
      <label for="title">Otsikko</label>
      <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" required maxlength="200">
      <?php if (isset($errors['title'])): ?><span class="field-error"><?= e($errors['title']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="category_id">Kategoria</label>
      <select id="category_id" name="category_id" required>
        <option value="">-- Valitse kategoria --</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int)$category['id'] ?>" <?= $old['category_id'] === (string)$category['id'] ? 'selected' : '' ?>>
            <?= e($category['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['category_id'])): ?><span class="field-error"><?= e($errors['category_id']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="priority">Prioriteetti</label>
      <select id="priority" name="priority">
        <?php foreach (allowedPriorities() as $p): ?>
          <option value="<?= e($p) ?>" <?= $old['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="description">Kuvaus</label>
      <textarea id="description" name="description" required><?= e($old['description']) ?></textarea>
      <?php if (isset($errors['description'])): ?><span class="field-error"><?= e($errors['description']) ?></span><?php endif; ?>
    </div>

    <div class="form-actions">
      <button type="submit" class="button">Lähetä tukipyyntö</button>
      <a class="button button--secondary" href="<?= e(url('/tickets')) ?>">Peruuta</a>
    </div>
  </form>
</section>
