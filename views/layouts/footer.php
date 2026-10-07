
</main>
<footer class="site-footer">
  <div class="container">
    <p class="text-muted">OpiskelijaHelpdesk &middot; Opetuskäyttöön tehty tiketöintijärjestelmä</p>
  </div>
</footer>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $script): ?>
<script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
