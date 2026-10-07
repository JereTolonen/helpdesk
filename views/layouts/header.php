<?php
/** @var ?array $user  @var string $pageTitle */
?>
<!DOCTYPE html>
<html lang="fi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="base-path" content="<?= e(rtrim(url('/'), '/')) ?>">
<title><?= e($pageTitle) ?> · OpiskelijaHelpdesk</title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="page-shell">
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="<?= e(url('/')) ?>">
      <span class="brand-mark">OH</span>
      <span>OpiskelijaHelpdesk</span>
    </a>
    <nav class="nav-links">
      <?php if ($user): ?>
        <a class="nav-link<?= isActive('/chat') ? ' is-active' : '' ?>" href="<?= e(url('/chat')) ?>">AI-chat</a>
        <?php if ($user['role'] === 'student'): ?>
          <a class="nav-link<?= isActive('/tickets/new') ? ' is-active' : '' ?>" href="<?= e(url('/tickets/new')) ?>">Uusi tiketti</a>
          <a class="nav-link<?= isActive('/tickets') && !isActive('/tickets/new') ? ' is-active' : '' ?>" href="<?= e(url('/tickets')) ?>">Omat tiketit</a>
        <?php elseif ($user['role'] === 'support'): ?>
          <a class="nav-link<?= isActive('/staff') ? ' is-active' : '' ?>" href="<?= e(url('/staff')) ?>">Työpöytä</a>
        <?php elseif ($user['role'] === 'admin'): ?>
          <a class="nav-link<?= isActive('/staff') ? ' is-active' : '' ?>" href="<?= e(url('/staff')) ?>">Työpöytä</a>
          <a class="nav-link<?= isActive('/admin') ? ' is-active' : '' ?>" href="<?= e(url('/admin')) ?>">Ylläpito</a>
        <?php endif; ?>
        <a class="nav-user text-muted" href="<?= e(url('/profile')) ?>" title="Muokkaa omia tietoja"><?= e($user['name']) ?> · <?= e($user['role']) ?></a>
        <a class="nav-link" href="<?= e(url('/logout')) ?>">Kirjaudu ulos</a>
      <?php else: ?>
        <a class="nav-link" href="<?= e(url('/login')) ?>">Kirjaudu</a>
        <a class="button" href="<?= e(url('/register')) ?>">Rekisteröidy</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container main-content">
  <?php foreach (getFlashes() as $flash): ?>
    <div class="alert alert--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endforeach; ?>
