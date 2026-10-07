<?php /** @var int $status  @var string $message */ ?>
<section class="card card--narrow">
  <span class="eyebrow text-accent">Virhe <?= (int)$status ?></span>
  <h1 class="section-title"><?= e($message) ?></h1>
  <div class="form-actions">
    <a class="button" href="<?= e(url('/')) ?>">Etusivulle</a>
    <a class="button button--secondary" href="javascript:history.back()">Takaisin</a>
  </div>
</section>
