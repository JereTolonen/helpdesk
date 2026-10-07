<?php /** @var array $filters @var array $categories @var array $tickets */ ?>
<div class="page-heading">
  <div>
    <h1 class="section-title">Omat tiketit</h1>
    <p class="section-description">Tässä näkyvät vain sinun luomasi tukipyynnöt.</p>
  </div>
  <a class="button button--secondary" href="<?= e(url('/chat')) ?>">Kysy ensin AI-avustajalta</a>
</div>

<form method="get" action="<?= e(url('/tickets')) ?>" class="filter-bar card">
  <div class="form-group">
    <label for="q">Haku otsikosta</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="esim. VPN ei toimi">
  </div>
  <div class="form-group">
    <label for="status">Tila</label>
    <select id="status" name="status">
      <option value="">Kaikki</option>
      <?php foreach (allowedStatuses() as $s): ?>
        <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label for="priority">Prioriteetti</label>
    <select id="priority" name="priority">
      <option value="">Kaikki</option>
      <?php foreach (allowedPriorities() as $p): ?>
        <option value="<?= e($p) ?>" <?= $filters['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label for="category_id">Kategoria</label>
    <select id="category_id" name="category_id">
      <option value="">Kaikki</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>" <?= $filters['category_id'] === (string)$category['id'] ? 'selected' : '' ?>>
          <?= e($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-actions">
    <button type="submit" class="button">Suodata</button>
    <a class="button button--secondary" href="<?= e(url('/tickets')) ?>">Tyhjennä</a>
  </div>
</form>

<?php if (!$tickets): ?>
  <div class="card">
    <p class="section-description">Ei tikettejä hakuehdoilla. <a class="text-accent" href="<?= e(url('/tickets/new')) ?>">Luo uusi tiketti</a>.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th class="wrap">Otsikko</th>
          <th>Kategoria</th>
          <th>Tila</th>
          <th>Prioriteetti</th>
          <th>Luotu</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td>#<?= (int)$t['id'] ?></td>
            <td class="wrap"><a class="row-link" href="<?= e(url('/tickets/' . (int)$t['id'])) ?>"><?= e($t['title']) ?></a></td>
            <td><?= e($t['category_name'] ?? '—') ?></td>
            <td><span class="<?= e(statusBadgeClass($t['status'])) ?>"><?= e($t['status']) ?></span></td>
            <td><span class="<?= e(priorityBadgeClass($t['priority'])) ?>"><?= e($t['priority']) ?></span></td>
            <td><?= e(formatDate($t['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
