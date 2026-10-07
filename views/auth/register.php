<main class="register-page">

  <section class="register-hero">

    <div class="register-hero__content">

      <div class="register-heading">

        <span class="home-section-heading__eyebrow">
          OpiskelijaHelpdesk
        </span>

        <h1>
          Luo uusi<br>
          <span>tunnus.</span>
        </h1>

        <p>
          Luo opiskelijatunnus ja pääset ilmoittamaan
          teknisistä ongelmista sekä seuraamaan tukipyyntöjäsi.
        </p>

      </div>


      <div class="register-card">

        <div class="register-card__header">
          <h2>Rekisteröidy</h2>

          <p>
            Rekisteröityminen luo opiskelijatunnuksen.
          </p>
        </div>


        <form method="post" action="<?= e(url('/register')) ?>" class="form-grid" novalidate>

          <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrfToken()) ?>"
          >


          <div class="form-group">
            <label for="name">Nimi</label>

            <input
              type="text"
              id="name"
              name="name"
              value="<?= e($name) ?>"
              required
              autocomplete="name"
              placeholder="Etunimi Sukunimi"
            >

            <?php if (isset($errors['name'])): ?>
              <span class="field-error">
                <?= e($errors['name']) ?>
              </span>
            <?php endif; ?>
          </div>


          <div class="form-group">
            <label for="email">Sähköposti</label>

            <input
              type="email"
              id="email"
              name="email"
              value="<?= e($email) ?>"
              required
              autocomplete="email"
              placeholder="nimi@example.com"
            >

            <?php if (isset($errors['email'])): ?>
              <span class="field-error">
                <?= e($errors['email']) ?>
              </span>
            <?php endif; ?>
          </div>


          <div class="form-group">
            <label for="password">Salasana</label>

            <input
              type="password"
              id="password"
              name="password"
              required
              minlength="8"
              autocomplete="new-password"
              placeholder="Vähintään 8 merkkiä"
            >

            <span class="hint">
              Vähintään 8 merkkiä.
            </span>

            <?php if (isset($errors['password'])): ?>
              <span class="field-error">
                <?= e($errors['password']) ?>
              </span>
            <?php endif; ?>
          </div>


          <div class="form-group">
            <label for="password_confirm">
              Vahvista salasana
            </label>

            <input
              type="password"
              id="password_confirm"
              name="password_confirm"
              required
              minlength="8"
              autocomplete="new-password"
              placeholder="Kirjoita salasana uudelleen"
            >

            <?php if (isset($errors['password_confirm'])): ?>
              <span class="field-error">
                <?= e($errors['password_confirm']) ?>
              </span>
            <?php endif; ?>
          </div>


          <div class="form-actions register-card__actions">

            <button type="submit" class="button button--hero">
              Luo tunnus
            </button>

            <a
              class="button button--hero-secondary"
              href="<?= e(url('/login')) ?>"
            >
              Minulla on jo tunnus
            </a>

          </div>

        </form>

      </div>


      <div class="register-page__hint">
        <span></span>
        OpiskelijaHelpdesk · Opiskelijat
      </div>

    </div>

  </section>

</main>
