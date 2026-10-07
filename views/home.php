<?php if (!$user): ?>

  <main class="home-page">

    <!-- HERO -->
    <section class="home-hero">
      <div class="home-hero__content">

        <span class="home-hero__eyebrow">
          OPISKELIJOIDEN IT-TUKI
        </span>

        <h1 class="home-hero__title">
          Tervetuloa<br>
          <span>OpiskelijaHelpdeskiin.</span>
        </h1>

        <p class="home-hero__description">
          Tekninen apu opiskelijoille yhdestä paikasta.
          Kysy AI-avustajalta ympäri vuorokauden, ilmoita ongelmasta,
          seuraa tukipyyntöäsi ja saa apua tukihenkilöiltä.
        </p>

        <div class="home-hero__actions">
          <a class="button button--hero" href="<?= e(url('/login')) ?>">
            Kirjaudu sisään
          </a>

          <a class="button button--hero-secondary" href="<?= e(url('/register')) ?>">
            Luo uusi tunnus
          </a>
        </div>

        <div class="home-hero__hint">
          <span></span>
          Nopea · Selkeä · Helppo
        </div>

      </div>
    </section>


    <!-- TIETOA PALVELUSTA -->
    <section class="home-info">
      <div class="home-info__container">

        <div class="home-section-heading">
          <span class="home-section-heading__eyebrow">
            OpiskelijaHelpdesk
          </span>

          <h2>
            Kaikki tukipyynnöt<br>
            yhdessä paikassa.
          </h2>

          <p>
            OpiskelijaHelpdesk auttaa opiskelijoita ilmoittamaan
            teknisistä ongelmista ja seuraamaan niiden käsittelyä.
            Tukipyyntöjen hallinta tapahtuu selkeästi yhdestä paikasta.
          </p>
        </div>


        <div class="home-features">

          <article class="home-feature">
            <div class="home-feature__number">01</div>

            <h3>Ilmoita ongelmasta</h3>

            <p>
              Luo uusi tukipyyntö ja kuvaile kohtaamasi tekninen
              ongelma. Mitä tarkemmin kuvailet tilanteen,
              sitä helpommin tukihenkilö voi auttaa.
            </p>
          </article>


          <article class="home-feature">
            <div class="home-feature__number">02</div>

            <h3>Seuraa tikettiäsi</h3>

            <p>
              Näet omat tukipyyntösi ja niiden tämänhetkisen
              tilan. Voit seurata, miten asian käsittely etenee.
            </p>
          </article>


          <article class="home-feature">
            <div class="home-feature__number">03</div>

            <h3>Saa apua</h3>

            <p>
              Tukihenkilöt käsittelevät ilmoituksia ja voivat
              vastata tukipyyntöön liittyviin kysymyksiin.
            </p>
          </article>

        </div>

      </div>
    </section>


    <!-- MITEN SE TOIMII -->
    <section class="home-process">
      <div class="home-process__container">

        <div class="home-process__intro">
          <span class="home-section-heading__eyebrow">
            Näin se toimii
          </span>

          <h2>
            Apua muutamassa<br>
            yksinkertaisessa vaiheessa.
          </h2>
        </div>


        <div class="home-process__steps">

          <div class="home-process__step">
            <strong>1</strong>

            <div>
              <h3>Kirjaudu</h3>
              <p>
                Kirjaudu omalle käyttäjätilillesi.
              </p>
            </div>
          </div>


          <div class="home-process__step">
            <strong>2</strong>

            <div>
              <h3>Luo tiketti</h3>
              <p>
                Kerro, mikä ongelma tarvitsee ratkaisemista.
              </p>
            </div>
          </div>


          <div class="home-process__step">
            <strong>3</strong>

            <div>
              <h3>Seuraa käsittelyä</h3>
              <p>
                Tarkista tukipyyntösi tila omista tiketeistäsi.
              </p>
            </div>
          </div>

        </div>

      </div>
    </section>


    <!-- CTA -->
    <section class="home-cta">
      <div class="home-cta__content">

        <span class="home-section-heading__eyebrow">
          Aloita nyt
        </span>

        <h2>
          Tarvitsetko apua?
        </h2>

        <p>
          Luo tunnus tai kirjaudu sisään ja tee ensimmäinen
          tukipyyntösi.
        </p>

        <div class="home-hero__actions">
          <a class="button button--hero" href="<?= e(url('/login')) ?>">
            Kirjaudu sisään
          </a>

          <a class="button button--hero-secondary" href="<?= e(url('/register')) ?>">
            Luo tunnus
          </a>
        </div>

      </div>
    </section>

  </main>

<?php elseif ($user['role'] === 'student'): ?>

  <section class="home-hero home-hero--logged">
    <div class="home-hero__content">

      <span class="home-hero__eyebrow">
        OPISKELIJA
      </span>

      <h1 class="home-hero__title">
        Hei,<br>
        <span><?= e($user['name']) ?>!</span>
      </h1>

      <p class="home-hero__description">
        Kysy ensin AI-avustajalta – se selvittää ongelmasi
        tarkentavilla kysymyksillä ja luo tarvittaessa tukipyynnön
        puolestasi. Voit myös luoda tiketin suoraan.
      </p>

      <div class="home-hero__actions">
        <a class="button button--hero" href="<?= e(url('/chat')) ?>">
          Kysy AI-avustajalta
        </a>

        <a class="button button--hero-secondary" href="<?= e(url('/tickets/new')) ?>">
          Luo uusi tiketti
        </a>

        <a class="button button--hero-secondary" href="<?= e(url('/tickets')) ?>">
          Omat tiketit
        </a>
      </div>

    </div>
  </section>

<?php else: ?>

  <section class="home-hero home-hero--logged">
    <div class="home-hero__content">

      <span class="home-hero__eyebrow">
        TUKIHENKILÖ
      </span>

      <h1 class="home-hero__title">
        Hei,<br>
        <span><?= e($user['name']) ?>!</span>
      </h1>

      <p class="home-hero__description">
        Siirry työpöydälle käsittelemään avoimia
        tukipyyntöjä.
      </p>

      <div class="home-hero__actions">
        <a class="button button--hero" href="<?= e(url('/staff')) ?>">
          Avaa työpöytä
        </a>

        <a class="button button--hero-secondary" href="<?= e(url('/chat')) ?>">
          AI-chat
        </a>

        <?php if ($user['role'] === 'admin'): ?>
          <a class="button button--hero-secondary" href="<?= e(url('/admin')) ?>">
            Ylläpito
          </a>
        <?php endif; ?>
      </div>

    </div>
  </section>

<?php endif; ?>
