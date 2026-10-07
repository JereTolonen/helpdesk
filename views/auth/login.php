<main class="login-page">

  <section class="login-hero">

    <div class="login-hero__content">

      <div class="login-heading">
        <span class="home-section-heading__eyebrow">
          OpiskelijaHelpdesk
        </span>

        <h1>
          Kirjaudu<br>
          <span>sisään.</span>
        </h1>

        <p>
          Kirjaudu sisään nähdäksesi omat tukipyyntösi
          ja käyttääksesi OpiskelijaHelpdeskiä.
        </p>
      </div>


      <div class="login-card">

        <?php if (isset($errors['general'])): ?>
          <div class="alert alert--error">
            <?= e($errors['general']) ?>
          </div>
        <?php endif; ?>


        <form method="post" action="<?= e(url('/login')) ?>" class="form-grid" novalidate>

          <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrfToken()) ?>"
          >


          <div class="form-group">
            <label for="email">Sähköposti</label>

            <input
              type="email"
              id="email"
              name="email"
              value="<?= e($email) ?>"
              required
              autofocus
              autocomplete="email"
              placeholder="nimi@example.com"
            >
          </div>


          <div class="form-group">
            <label for="password">Salasana</label>

            <input
              type="password"
              id="password"
              name="password"
              required
              autocomplete="current-password"
              placeholder="Syötä salasanasi"
            >
          </div>


          <div class="form-actions login-card__actions">

            <button type="submit" class="button button--hero">
              Kirjaudu sisään
            </button>

            <a
              class="button button--hero-secondary"
              href="<?= e(url('/register')) ?>"
            >
              Luo uusi tunnus
            </a>

          </div>

        </form>

      </div>


      <div class="login-page__hint">
        <span></span>
        OpiskelijaHelpdesk · Nopea · Selkeä · Helppo
      </div>

    </div>

  </section>

</main>
