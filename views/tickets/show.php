<?php
/** @var array $ticket @var array $comments @var bool $isStaff @var bool $isOwner
 *  @var array $staffList @var ?int $conversationId @var array $errors */
$ticketUrl = url('/tickets/' . (int)$ticket['id']);
?>
<section class="card">
  <div class="form-actions" style="justify-content: space-between; align-items: flex-start;">
    <div>
      <span class="eyebrow text-accent">Tiketti #<?= (int)$ticket['id'] ?></span>
      <h1 class="section-title"><?= e($ticket['title']) ?></h1>
    </div>
    <div class="form-actions">
      <span class="<?= e(statusBadgeClass($ticket['status'])) ?>"><?= e($ticket['status']) ?></span>
      <span class="<?= e(priorityBadgeClass($ticket['priority'])) ?>"><?= e($ticket['priority']) ?></span>
    </div>
  </div>

  <div class="ticket-meta">
    <div class="ticket-meta__item"><strong>Kategoria</strong><?= e($ticket['category_name'] ?? '—') ?></div>
    <div class="ticket-meta__item"><strong>Tekijä</strong><?= e($ticket['creator_name'] ?? '—') ?></div>
    <div class="ticket-meta__item"><strong>Vastuuhenkilö</strong><?= e($ticket['assignee_name'] ?? 'Ei asetettu') ?></div>
    <div class="ticket-meta__item"><strong>Luotu</strong><?= e(formatDate($ticket['created_at'])) ?></div>
    <div class="ticket-meta__item"><strong>Päivitetty</strong><?= e(formatDate($ticket['updated_at'])) ?></div>
  </div>

  <?php if ($conversationId): ?>
    <div class="alert alert--info">
      Tämä tukipyyntö luotiin AI-chatista.
      <a class="text-accent" href="<?= e(url('/chat/' . (int)$conversationId)) ?>"><strong>Näytä keskustelu &rarr;</strong></a>
    </div>
  <?php endif; ?>

  <p class="ticket-description"><?= nl2br(e($ticket['description'])) ?></p>

  <div class="form-actions">
    <?php if ($isStaff && $ticket['status'] === 'Uusi'): ?>
      <form method="post" action="<?= e($ticketUrl) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="take_ticket">
        <button type="submit" class="button">Ota käsittelyyn</button>
      </form>
    <?php endif; ?>

    <?php if (($isOwner || $isStaff) && $ticket['status'] !== 'Suljettu'): ?>
      <form method="post" action="<?= e($ticketUrl) ?>" data-confirm="Suljetaanko tukipyyntö?">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="close_ticket">
        <button type="submit" class="button button--secondary">Sulje tukipyyntö</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if ($isStaff): ?>
  <section class="card">
    <h2 class="card__title">Muokkaa tikettiä</h2>
    <form method="post" action="<?= e($ticketUrl) ?>" class="form-grid">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="update_ticket">

      <div class="form-group">
        <label for="status">Tila</label>
        <select id="status" name="status">
          <?php foreach (allowedStatuses() as $s): ?>
            <option value="<?= e($s) ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['status'])): ?><span class="field-error"><?= e($errors['status']) ?></span><?php endif; ?>
      </div>

      <div class="form-group">
        <label for="priority">Prioriteetti</label>
        <select id="priority" name="priority">
          <?php foreach (allowedPriorities() as $p): ?>
            <option value="<?= e($p) ?>" <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['priority'])): ?><span class="field-error"><?= e($errors['priority']) ?></span><?php endif; ?>
      </div>

      <div class="form-group">
        <label for="assigned_to">Vastuuhenkilö</label>
        <select id="assigned_to" name="assigned_to">
          <option value="">Ei asetettu</option>
          <?php foreach ($staffList as $staff): ?>
            <option value="<?= (int)$staff['id'] ?>" <?= (int)$ticket['assigned_to'] === (int)$staff['id'] ? 'selected' : '' ?>>
              <?= e($staff['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="button">Tallenna muutokset</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<section class="card">
  <h2 class="card__title">Kommentit</h2>

  <div class="comment-list">
    <?php if (!$comments): ?>
      <p class="text-muted">Ei kommentteja vielä.</p>
    <?php endif; ?>
    <?php foreach ($comments as $comment): ?>
      <div class="comment">
        <div class="comment__meta">
          <span><strong><?= e($comment['author_name']) ?></strong> (<?= e($comment['author_role']) ?>)</span>
          <span><?= e(formatDate($comment['created_at'])) ?></span>
        </div>
        <div><?= nl2br(e($comment['message'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" action="<?= e($ticketUrl) ?>" class="form-grid">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="add_comment">
    <div class="form-group">
      <label for="message">Lisää kommentti</label>
      <textarea id="message" name="message" required></textarea>
      <?php if (isset($errors['message'])): ?><span class="field-error"><?= e($errors['message']) ?></span><?php endif; ?>
    </div>
    <div class="form-actions">
      <button type="submit" class="button">Lähetä kommentti</button>
    </div>
  </form>
</section>
