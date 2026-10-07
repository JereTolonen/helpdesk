<?php /** @var array $filters @var array $stats @var array $chatStats @var array $categories @var array $tickets */ ?>
<h1 class="section-title">Tukihenkilön työpöytä</h1>
<p class="section-description">Kaikki järjestelmän tiketit yhdessä näkymässä.</p>

<div class="stat-grid">
  <div class="stat"><span class="stat__value"><?= (int)$stats['new_count'] ?></span><span class="stat__label">Uusi</span></div>
  <div class="stat"><span class="stat__value"><?= (int)$stats['in_progress_count'] ?></span><span class="stat__label">Käsittelyssä</span></div>
  <div class="stat"><span class="stat__value"><?= (int)$stats['waiting_count'] ?></span><span class="stat__label">Odottaa opiskelijaa</span></div>
  <div class="stat"><span class="stat__value"><?= (int)$stats['closed_count'] ?></span><span class="stat__label">Ratkaistu / Suljettu</span></div>
</div>

<div class="stat-grid stat-grid--3">
  <div class="stat"><span class="stat__value"><?= (int)$chatStats['total'] ?></span><span class="stat__label">AI-keskustelut</span></div>
  <div class="stat"><span class="stat__value"><?= (int)$chatStats['resolved'] ?></span><span class="stat__label">Ratkesi AI:n avulla</span></div>
  <div class="stat"><span class="stat__value"><?= (int)$chatStats['escalated'] ?></span><span class="stat__label">AI loi tiketin</span></div>
</div>

<form method="get" action="<?= e(url('/staff')) ?>" class="filter-bar card">
  <div class="form-group">
    <label for="q">Haku otsikosta</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>">
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
  <div class="form-group">
    <label for="assigned">Vastuu</label>
    <select id="assigned" name="assigned">
      <option value="">Kaikki</option>
      <option value="me" <?= $filters['assigned'] === 'me' ? 'selected' : '' ?>>Omat</option>
      <option value="unassigned" <?= $filters['assigned'] === 'unassigned' ? 'selected' : '' ?>>Ei vastuuhenkilöä</option>
    </select>
  </div>
  <div class="form-actions">
    <button type="submit" class="button">Suodata</button>
    <a class="button button--secondary" href="<?= e(url('/staff')) ?>">Tyhjennä</a>
  </div>
</form>

<?php if (!$tickets): ?>
  <div class="card"><p class="section-description">Ei tikettejä hakuehdoilla.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th class="wrap">Otsikko</th>
          <th>Opiskelija</th>
          <th>Kategoria</th>
          <th>Tila</th>
          <th>Prioriteetti</th>
          <th>Vastuu</th>
          <th>Luotu</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td>#<?= (int)$t['id'] ?></td>
            <td class="wrap"><a class="row-link" href="<?= e(url('/tickets/' . (int)$t['id'])) ?>"><?= e($t['title']) ?></a></td>
            <td><?= e($t['creator_name'] ?? '—') ?></td>
            <td><?= e($t['category_name'] ?? '—') ?></td>
            <td><span class="<?= e(statusBadgeClass($t['status'])) ?>"><?= e($t['status']) ?></span></td>
            <td><span class="<?= e(priorityBadgeClass($t['priority'])) ?>"><?= e($t['priority']) ?></span></td>
            <td><?= e($t['assignee_name'] ?? '—') ?></td>
            <td><?= e(formatDate($t['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
