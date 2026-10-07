// OpiskelijaHelpdesk – kevyt asiakaspään JS.
// Ei kriittistä logiikkaa tässä tiedostossa: kaikki tietoturva ja
// käyttöoikeuksien tarkistus tapahtuu palvelimella (PHP).

document.addEventListener('DOMContentLoaded', function () {
  // Vahvista tuhoavat toiminnot (esim. tiketin sulkeminen tai käyttäjän poisto).
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      const message = el.getAttribute('data-confirm') || 'Oletko varma?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  // Lomakkeiden lähetyksen esto tuplaklikkauksilta.
  // (AI-chatin lomake hoitaa tilansa itse, ks. chat.js)
  document.querySelectorAll('form:not([data-composer])').forEach(function (form) {
    form.addEventListener('submit', function () {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        window.setTimeout(function () {
          submitBtn.disabled = true;
        }, 0);
      }
    });
  });
});
