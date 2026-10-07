<?php
/** @var ?int $activeConversationId @var string $model @var array $user */
?>
<div class="chat-page"
     data-chat-root
     data-active-id="<?= $activeConversationId !== null ? (int)$activeConversationId : '' ?>"
     data-user-id="<?= (int)$user['id'] ?>">

  <div class="page-heading">
    <div>
      <span class="eyebrow text-accent">AI-avustaja</span>
      <h1 class="section-title">AI-chat</h1>
      <p class="section-description">
        Kuvaile ongelmasi. Avustaja esittää ensin tarkentavia kysymyksiä ja antaa ratkaisun vasta,
        kun tiedot riittävät. Jos ongelma vaatii tukihenkilöä, tukipyyntö luodaan automaattisesti.
      </p>
    </div>
    <div class="ai-status" data-ai-status title="Tekoälymallin tila">
      <span class="ai-status__dot"></span>
      <span class="ai-status__text">Tarkistetaan mallia…</span>
      <span class="ai-status__model"><?= e($model) ?></span>
    </div>
  </div>

  <div class="chat-layout">

    <!-- Keskustelulista -->
    <aside class="chat-sidebar">
      <button type="button" class="button chat-sidebar__new" data-new-chat>+ Uusi keskustelu</button>
      <span class="chat-sidebar__label">Omat keskustelut</span>
      <ul class="chat-list" data-chat-list>
        <li class="chat-list__empty text-muted">Ladataan…</li>
      </ul>
    </aside>

    <!-- Keskustelu -->
    <section class="chat-panel" aria-live="polite">
      <header class="chat-panel__header">
        <div class="chat-panel__title">
          <h2 data-chat-title>Uusi keskustelu</h2>
          <span class="badge" data-chat-badge hidden></span>
        </div>
        <ol class="diagnosis-steps" data-steps>
          <li data-step="describe">Kuvaus</li>
          <li data-step="diagnose">Tarkentavat kysymykset</li>
          <li data-step="decide">Ratkaisu</li>
        </ol>
      </header>

      <div class="chat-messages" data-messages>
        <div class="chat-welcome" data-welcome>
          <span class="chat-welcome__mark">AI</span>
          <h3>Miten voin auttaa?</h3>
          <p>Kerro omin sanoin, mikä ei toimi. Kysyn muutaman tarkentavan kysymyksen ennen kuin ehdotan ratkaisua.</p>
          <div class="chat-suggestions">
            <button type="button" class="chat-suggestion" data-suggestion>En saa MySQL-palvelinta käynnistettyä.</button>
            <button type="button" class="chat-suggestion" data-suggestion>Outlook kysyy salasanaa koko ajan.</button>
            <button type="button" class="chat-suggestion" data-suggestion>Tulostin ei tulosta mitään.</button>
            <button type="button" class="chat-suggestion" data-suggestion>Git push hylätään: authentication failed.</button>
          </div>
        </div>
      </div>

      <!-- Päätöspalkki ratkaisuehdotuksen jälkeen -->
      <div class="chat-decision" data-decision hidden>
        <div>
          <strong>Ratkesiko ongelma?</strong>
          <span class="text-muted">Voit myös jatkaa keskustelua, jos jokin vaihe ei onnistunut.</span>
        </div>
        <div class="form-actions">
          <button type="button" class="button button--small" data-resolve>Kyllä, ratkesi</button>
          <button type="button" class="button button--small button--secondary" data-create-ticket>Ei – luo tukipyyntö</button>
        </div>
      </div>

      <!-- Päättynyt keskustelu -->
      <div class="chat-ended" data-ended hidden>
        <span data-ended-text></span>
        <div class="form-actions">
          <a class="button button--small button--secondary" data-ended-ticket hidden>Avaa tukipyyntö</a>
          <button type="button" class="button button--small" data-new-chat>Aloita uusi keskustelu</button>
        </div>
      </div>

      <!-- Kirjoituskenttä -->
      <form class="chat-composer" data-composer novalidate>
        <div class="chat-composer__row">
          <label class="sr-only" for="chat-input">Viesti</label>
          <textarea id="chat-input" rows="1" maxlength="4000" placeholder="Kirjoita viesti… (Enter lähettää, Shift+Enter rivinvaihto)" data-input></textarea>
          <button type="button" class="chat-icon-button" data-mic hidden title="Puhu viesti" aria-label="Puhu viesti">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><path d="M12 17v5"/></svg>
          </button>
          <button type="submit" class="button chat-composer__send" data-send>Lähetä</button>
        </div>
        <div class="chat-composer__meta">
          <span>AI voi erehtyä. Älä koskaan kerro salasanaasi chatissa.</span>
          <span class="chat-composer__tools">
            <label class="chat-toggle" data-tts-toggle hidden>
              <input type="checkbox" data-tts> Puhevastaus
            </label>
            <button type="button" class="chat-link" data-create-ticket-link hidden>Luo tukipyyntö tästä keskustelusta</button>
          </span>
        </div>
      </form>

      <div class="chat-readonly" data-readonly hidden>
        Tarkastelet toisen käyttäjän keskustelua (vain luku).
      </div>
    </section>
  </div>
</div>
