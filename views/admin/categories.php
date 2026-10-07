<?php /** @var array $categories @var array $errors */ $action = url('/admin/categories'); ?>
<h1 class="section-title">Kategoriat</h1>
<p class="section-description"><a class="text-accent" href="<?= e(url('/admin')) ?>">&larr; Takaisin ylläpitoon</a></p>

<section class="card">
  <h2 class="card__title">Lisää kategoria</h2>
  <p class="card__text">AI-avustaja luokittelee keskustelut näihin kategorioihin automaattisesti.</p>
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="create">
    <div class="form-group">
      <label for="name">Nimi</label>
      <input type="text" id="name" name="name" required>
      <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
    </div>
    <div class="form-group">
      <label for="description">Kuvaus</label>
      <textarea id="description" name="description"></textarea>
    </div>
    <div class="form-actions">
      <button type="submit" class="button">Lisää</button>
    </div>
  </form>
</section>

<section class="card">
  <h2 class="card__title">Kaikki kategoriat</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th class="wrap">Nimi</th><th class="wrap">Kuvaus</th><th>Tikettejä</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td class="wrap"><?= e($c['name']) ?></td>
            <td class="wrap"><?= e($c['description'] ?? '—') ?></td>
            <td><?= (int)$c['ticket_count'] ?></td>
            <td>
              <form method="post" action="<?= e($action) ?>" style="margin:0;" data-confirm="Poistetaanko kategoria '<?= e($c['name']) ?>'? Kategorian tiketit säilyvät ilman kategoriaa.">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="button button--small button--danger">Poista</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
