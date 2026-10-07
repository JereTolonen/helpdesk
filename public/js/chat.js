// OpiskelijaHelpdesk – AI-chat (asiakaspää)
// Kaikki tieto haetaan ja tallennetaan REST API:n kautta (/api/chat/...).
// Käyttöoikeudet ja diagnoosilogiikka tarkistetaan palvelimella.

(function () {
  'use strict';

  const root = document.querySelector('[data-chat-root]');
  if (!root) return;

  const base = document.querySelector('meta[name="base-path"]').content.replace(/\/$/, '');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  const el = {
    status: root.querySelector('[data-ai-status]'),
    list: root.querySelector('[data-chat-list]'),
    title: root.querySelector('[data-chat-title]'),
    badge: root.querySelector('[data-chat-badge]'),
    steps: root.querySelector('[data-steps]'),
    messages: root.querySelector('[data-messages]'),
    welcome: root.querySelector('[data-welcome]'),
    decision: root.querySelector('[data-decision]'),
    ended: root.querySelector('[data-ended]'),
    endedText: root.querySelector('[data-ended-text]'),
    endedTicket: root.querySelector('[data-ended-ticket]'),
    composer: root.querySelector('[data-composer]'),
    input: root.querySelector('[data-input]'),
    send: root.querySelector('[data-send]'),
    mic: root.querySelector('[data-mic]'),
    ttsToggle: root.querySelector('[data-tts-toggle]'),
    tts: root.querySelector('[data-tts]'),
    ticketLink: root.querySelector('[data-create-ticket-link]'),
    readonly: root.querySelector('[data-readonly]'),
  };

  const state = {
    conversation: null, // nykyinen keskustelu (API:n present-muoto)
    messages: [],
    busy: false,
  };

  const STATUS_LABELS = {
    diagnosing: ['Diagnoosi käynnissä', 'badge badge--progress'],
    solution_offered: ['Ratkaisu ehdotettu', 'badge badge--new'],
    resolved: ['Ratkaistu', 'badge badge--resolved'],
    escalated: ['Tukipyyntö luotu', 'badge badge--waiting'],
  };

  const PHASE_LABELS = {
    question: 'Tarkentava kysymys',
    solution: 'Ratkaisuehdotus',
    escalate: 'Ohjataan tukihenkilölle',
  };

  /* ------------------------------------------------------------
   * API
   * ---------------------------------------------------------- */

  async function api(method, path, body) {
    const response = await fetch(base + '/api' + path, {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: body ? JSON.stringify(body) : undefined,
    });

    let data = {};
    try { data = await response.json(); } catch (e) { /* tyhjä vastaus */ }

    if (response.status === 401) {
      window.location.href = base + '/login';
      throw new Error('Kirjautuminen vaaditaan.');
    }
    if (!response.ok) {
      const error = new Error(data.error || 'Tuntematon virhe (' + response.status + ').');
      error.status = response.status;
      error.data = data;
      throw error;
    }
    return data;
  }

  /* ------------------------------------------------------------
   * Tekstin muotoilu (turvallinen: ensin escape, sitten kevyt markdown)
   * ---------------------------------------------------------- */

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function formatMessage(text) {
    const codeBlocks = [];
    let html = escapeHtml(text).replace(/```[\w-]*\n?([\s\S]*?)```/g, function (_, code) {
      codeBlocks.push('<pre><code>' + code.replace(/\n$/, '') + '</code></pre>');
      return '\u0000' + (codeBlocks.length - 1) + '\u0000';
    });

    html = html
      .replace(/`([^`\n]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');

    // Listat ja kappaleet
    const lines = html.split('\n');
    let out = '';
    let listType = null;
    for (const raw of lines) {
      const line = raw.trim();
      const ol = line.match(/^(\d+)[.)]\s+(.*)$/);
      const ul = line.match(/^[-*•]\s+(.*)$/);
      if (ol || ul) {
        const type = ol ? 'ol' : 'ul';
        if (listType !== type) {
          if (listType) out += '</' + listType + '>';
          out += '<' + type + '>';
          listType = type;
        }
        out += '<li>' + (ol ? ol[2] : ul[1]) + '</li>';
        continue;
      }
      if (listType) { out += '</' + listType + '>'; listType = null; }
      if (line === '') continue;
      out += '<p>' + line + '</p>';
    }
    if (listType) out += '</' + listType + '>';

    return out.replace(/\u0000(\d+)\u0000/g, function (_, i) { return codeBlocks[Number(i)]; });
  }

  function formatTime(value) {
    const d = value ? new Date(value.replace(' ', 'T')) : new Date();
    if (isNaN(d)) return '';
    return d.toLocaleTimeString('fi-FI', { hour: '2-digit', minute: '2-digit' });
  }

  function formatDate(value) {
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d)) return '';
    const today = new Date();
    return d.toDateString() === today.toDateString()
      ? d.toLocaleTimeString('fi-FI', { hour: '2-digit', minute: '2-digit' })
      : d.toLocaleDateString('fi-FI', { day: 'numeric', month: 'numeric' });
  }

  /* ------------------------------------------------------------
   * Renderöinti
   * ---------------------------------------------------------- */

  function renderMessage(message) {
    const wrap = document.createElement('div');

    if (message.role === 'system') {
      wrap.className = 'chat-message chat-message--system';
      wrap.innerHTML = '<span>' + escapeHtml(message.content) + '</span>';
      const ticketId = message.meta && message.meta.ticket_id;
      if (ticketId) {
        const link = document.createElement('a');
        link.href = base + '/tickets/' + ticketId;
        link.className = 'text-accent';
        link.textContent = ' Avaa tukipyyntö #' + ticketId + ' →';
        wrap.querySelector('span').appendChild(link);
      }
      return wrap;
    }

    const isUser = message.role === 'user';
    const phase = message.meta && message.meta.phase;
    wrap.className = 'chat-message ' + (isUser ? 'chat-message--user' : 'chat-message--ai')
      + (phase ? ' chat-message--' + phase : '');

    const meta = document.createElement('div');
    meta.className = 'chat-message__meta';
    const ownerLabel = state.conversation && !state.conversation.can_write && state.conversation.owner
      ? state.conversation.owner : 'Sinä';
    meta.innerHTML = '<span>' + escapeHtml(isUser ? ownerLabel : 'AI-avustaja')
      + (phase ? ' · <em>' + escapeHtml(PHASE_LABELS[phase] || phase) + '</em>' : '')
      + '</span><span>' + escapeHtml(formatTime(message.created_at)) + '</span>';

    const bubble = document.createElement('div');
    bubble.className = 'chat-message__bubble';
    if (isUser) {
      bubble.textContent = message.content;
    } else {
      bubble.innerHTML = formatMessage(message.content);
    }

    wrap.appendChild(meta);
    wrap.appendChild(bubble);

    const sources = message.meta && message.meta.sources;
    if (!isUser && sources && sources.length) {
      const src = document.createElement('div');
      src.className = 'chat-message__sources';
      src.textContent = 'Lähteet: ' + sources.join(' · ');
      wrap.appendChild(src);
    }

    return wrap;
  }

  function renderMessages() {
    el.messages.querySelectorAll('.chat-message, .chat-typing, .chat-error').forEach(function (n) { n.remove(); });
    el.welcome.hidden = state.messages.length > 0;
    state.messages.forEach(function (m) { el.messages.appendChild(renderMessage(m)); });
    scrollToBottom();
  }

  function appendMessages(messages) {
    el.welcome.hidden = true;
    messages.forEach(function (m) {
      state.messages.push(m);
      el.messages.appendChild(renderMessage(m));
    });
    scrollToBottom();
  }

  function scrollToBottom() {
    el.messages.scrollTop = el.messages.scrollHeight;
  }

  function renderHeader() {
    const c = state.conversation;
    el.title.textContent = c ? c.title : 'Uusi keskustelu';

    if (c && STATUS_LABELS[c.status] && state.messages.length) {
      el.badge.textContent = STATUS_LABELS[c.status][0];
      el.badge.className = STATUS_LABELS[c.status][1];
      el.badge.hidden = false;
    } else {
      el.badge.hidden = true;
    }

    // Diagnoosin vaiheet: Kuvaus -> Tarkentavat kysymykset -> Ratkaisu
    const decided = c && ['solution_offered', 'resolved', 'escalated'].includes(c.status);
    const steps = { describe: 'todo', diagnose: 'todo', decide: 'todo' };
    if (!state.messages.length) {
      steps.describe = 'active';
    } else if (decided) {
      steps.describe = steps.diagnose = 'done';
      steps.decide = 'active';
    } else {
      steps.describe = 'done';
      steps.diagnose = 'active';
    }
    el.steps.querySelectorAll('[data-step]').forEach(function (li) {
      li.className = 'is-' + steps[li.dataset.step];
    });
  }

  function renderControls() {
    const c = state.conversation;
    const canWrite = !c || c.can_write;
    const ended = c && (c.status === 'resolved' || c.status === 'escalated');

    el.readonly.hidden = canWrite;
    el.composer.hidden = !canWrite || ended;
    el.decision.hidden = !(canWrite && c && c.status === 'solution_offered' && !state.busy);
    el.ticketLink.hidden = !(canWrite && c && !ended && state.messages.length > 0 && !state.busy);

    el.ended.hidden = !ended || !canWrite;
    if (ended && canWrite) {
      if (c.status === 'resolved') {
        el.endedText.textContent = 'Keskustelu päättyi – ongelma ratkesi.';
        el.endedTicket.hidden = true;
      } else {
        el.endedText.textContent = 'Keskustelusta luotiin tukipyyntö #' + c.ticket_id + '. Tukihenkilö jatkaa käsittelyä.';
        el.endedTicket.hidden = !c.ticket_id;
        el.endedTicket.href = base + '/tickets/' + c.ticket_id;
      }
    }

    el.input.disabled = state.busy;
    el.send.disabled = state.busy;
    if (el.mic) el.mic.disabled = state.busy;
  }

  function renderAll() {
    renderMessages();
    renderHeader();
    renderControls();
    highlightActiveInList();
  }

  /* ------------------------------------------------------------
   * Keskustelulista
   * ---------------------------------------------------------- */

  async function loadList() {
    try {
      const data = await api('GET', '/chat/conversations');
      el.list.innerHTML = '';
      if (!data.conversations.length) {
        el.list.innerHTML = '<li class="chat-list__empty text-muted">Ei aiempia keskusteluja.</li>';
        return;
      }
      data.conversations.forEach(function (c) {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = base + '/chat/' + c.id;
        a.className = 'chat-list__item';
        a.dataset.id = c.id;

        const title = document.createElement('span');
        title.className = 'chat-list__title';
        title.textContent = c.title;

        const meta = document.createElement('span');
        meta.className = 'chat-list__meta';
        const label = STATUS_LABELS[c.status] ? STATUS_LABELS[c.status][0] : c.status;
        meta.textContent = label + ' · ' + formatDate(c.updated_at);

        const dot = document.createElement('span');
        dot.className = 'chat-list__dot chat-list__dot--' + c.status;

        a.appendChild(dot);
        a.appendChild(title);
        a.appendChild(meta);
        a.addEventListener('click', function (event) {
          event.preventDefault();
          openConversation(c.id, true);
        });
        li.appendChild(a);
        el.list.appendChild(li);
      });
      highlightActiveInList();
    } catch (error) {
      el.list.innerHTML = '<li class="chat-list__empty text-muted">Keskustelujen lataus epäonnistui.</li>';
    }
  }

  function highlightActiveInList() {
    const id = state.conversation ? String(state.conversation.id) : '';
    el.list.querySelectorAll('.chat-list__item').forEach(function (a) {
      a.classList.toggle('is-active', a.dataset.id === id);
    });
  }

  /* ------------------------------------------------------------
   * Toiminnot
   * ---------------------------------------------------------- */

  async function openConversation(id, push) {
    try {
      const data = await api('GET', '/chat/conversations/' + id);
      state.conversation = data.conversation;
      state.messages = data.messages;
      if (push) history.pushState({ id: id }, '', base + '/chat/' + id);
      renderAll();
      if (state.conversation.can_write) el.input.focus({ preventScroll: true });
    } catch (error) {
      showError(error.message);
    }
  }

  function newConversation(push) {
    state.conversation = null;
    state.messages = [];
    if (push) history.pushState({ id: null }, '', base + '/chat');
    renderAll();
    el.input.value = '';
    autoResize();
    el.input.focus({ preventScroll: true });
  }

  async function sendMessage(text) {
    text = text.trim();
    if (!text || state.busy) return;

    state.busy = true;
    el.input.value = '';
    autoResize();
    clearErrors();

    // Näytetään käyttäjän viesti heti (optimistinen päivitys).
    const pending = renderMessage({ role: 'user', content: text, created_at: null });
    pending.classList.add('is-pending');
    el.welcome.hidden = true;
    el.messages.appendChild(pending);
    const typing = showTyping();
    renderControls();
    scrollToBottom();

    try {
      let data;
      if (!state.conversation) {
        data = await api('POST', '/chat/conversations', { message: text });
        history.pushState({ id: data.conversation.id }, '', base + '/chat/' + data.conversation.id);
      } else {
        data = await api('POST', '/chat/conversations/' + state.conversation.id + '/messages', { message: text });
      }

      pending.remove();
      typing.remove();
      state.conversation = data.conversation;
      appendMessages(data.messages);
      speakLatest(data.messages);
      loadList();
    } catch (error) {
      pending.remove();
      typing.remove();
      el.input.value = text; // palautetaan viesti uudelleenlähetystä varten
      autoResize();
      if (error.status === 503 && !state.conversation && error.data && error.data.conversation) {
        // Keskustelu luotiin, mutta AI ei vastannut -> jatketaan samaa keskustelua.
        state.conversation = error.data.conversation;
        history.pushState({ id: state.conversation.id }, '', base + '/chat/' + state.conversation.id);
        loadList();
      }
      showError(error.message);
    } finally {
      state.busy = false;
      renderHeader();
      renderControls();
      el.input.focus({ preventScroll: true });
    }
  }

  async function resolveConversation() {
    if (!state.conversation || state.busy) return;
    state.busy = true;
    renderControls();
    try {
      const data = await api('POST', '/chat/conversations/' + state.conversation.id + '/resolve');
      state.conversation = data.conversation;
      state.messages = data.messages;
      loadList();
    } catch (error) {
      showError(error.message);
    } finally {
      state.busy = false;
      renderAll();
    }
  }

  async function createTicket() {
    if (!state.conversation || state.busy) return;
    if (!window.confirm('Luodaanko keskustelusta tukipyyntö? Tiivistelmä ja keskustelu liitetään tukipyyntöön.')) return;
    state.busy = true;
    renderControls();
    try {
      const data = await api('POST', '/chat/conversations/' + state.conversation.id + '/ticket');
      state.conversation = data.conversation;
      state.messages = data.messages;
      loadList();
    } catch (error) {
      showError(error.message);
    } finally {
      state.busy = false;
      renderAll();
    }
  }

  function showTyping() {
    const node = document.createElement('div');
    node.className = 'chat-typing';
    node.innerHTML = '<span class="chat-typing__dots"><i></i><i></i><i></i></span><span class="chat-typing__text">AI-avustaja miettii…</span>';
    el.messages.appendChild(node);
    // Ensimmäinen kutsu lataa mallin muistiin – kerrotaan käyttäjälle.
    setTimeout(function () {
      if (node.isConnected) {
        node.querySelector('.chat-typing__text').textContent = 'Malli latautuu muistiin, ensimmäinen vastaus voi kestää hetken…';
      }
    }, 8000);
    return node;
  }

  function showError(message) {
    clearErrors();
    const node = document.createElement('div');
    node.className = 'chat-error alert alert--error';
    node.textContent = message;
    el.messages.appendChild(node);
    scrollToBottom();
  }

  function clearErrors() {
    el.messages.querySelectorAll('.chat-error').forEach(function (n) { n.remove(); });
  }

  /* ------------------------------------------------------------
   * Tekoälyn tila
   * ---------------------------------------------------------- */

  async function checkStatus() {
    const text = el.status.querySelector('.ai-status__text');
    try {
      const data = await api('GET', '/health');
      const o = data.ollama;
      el.status.classList.remove('is-online', 'is-warning', 'is-offline');
      if (o.online && o.model_available) {
        el.status.classList.add('is-online');
        text.textContent = 'Valmis';
      } else if (o.online) {
        el.status.classList.add('is-warning');
        text.textContent = 'Mallia ladataan';
        el.status.title = 'Ollama vastaa, mutta mallia ' + o.model + ' ei ole vielä ladattu (ollama pull ' + o.model + ').';
      } else {
        el.status.classList.add('is-offline');
        text.textContent = 'Ei yhteyttä';
        el.status.title = 'Ollama-palvelimeen ei saada yhteyttä.';
      }
    } catch (error) {
      el.status.classList.add('is-offline');
      text.textContent = 'Ei yhteyttä';
    }
  }

  /* ------------------------------------------------------------
   * Puheohjaus ja puhevastaus (Web Speech API, jos selain tukee)
   * ---------------------------------------------------------- */

  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  let recognizer = null;

  if (Recognition && el.mic) {
    el.mic.hidden = false;
    recognizer = new Recognition();
    recognizer.lang = 'fi-FI';
    recognizer.interimResults = true;
    recognizer.continuous = false;

    let baseText = '';
    recognizer.addEventListener('start', function () {
      baseText = el.input.value ? el.input.value.trim() + ' ' : '';
      el.mic.classList.add('is-recording');
    });
    recognizer.addEventListener('result', function (event) {
      let transcript = '';
      for (let i = 0; i < event.results.length; i++) transcript += event.results[i][0].transcript;
      el.input.value = baseText + transcript;
      autoResize();
    });
    recognizer.addEventListener('end', function () {
      el.mic.classList.remove('is-recording');
      el.input.focus({ preventScroll: true });
    });
    recognizer.addEventListener('error', function () {
      el.mic.classList.remove('is-recording');
    });

    el.mic.addEventListener('click', function () {
      if (el.mic.classList.contains('is-recording')) recognizer.stop();
      else recognizer.start();
    });
  }

  if ('speechSynthesis' in window && el.ttsToggle) {
    el.ttsToggle.hidden = false;
    try { el.tts.checked = localStorage.getItem('ohdesk-tts') === '1'; } catch (e) { /* ei tallennusta */ }
    el.tts.addEventListener('change', function () {
      try { localStorage.setItem('ohdesk-tts', el.tts.checked ? '1' : '0'); } catch (e) { /* ei tallennusta */ }
      if (!el.tts.checked) window.speechSynthesis.cancel();
    });
  }

  function speakLatest(messages) {
    if (!el.tts || !el.tts.checked || !('speechSynthesis' in window)) return;
    const ai = messages.filter(function (m) { return m.role === 'assistant'; }).pop();
    if (!ai) return;
    const plain = ai.content.replace(/[`*#>]/g, '').replace(/\s+/g, ' ');
    const utterance = new SpeechSynthesisUtterance(plain);
    // Arvataan kieli: suomen kirjaimet tai yleiset sanat -> fi-FI
    utterance.lang = /[äöå]|\b(ja|on|ei|että|mikä|kun)\b/i.test(plain) ? 'fi-FI' : 'en-US';
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(utterance);
  }

  /* ------------------------------------------------------------
   * Tapahtumat
   * ---------------------------------------------------------- */

  function autoResize() {
    el.input.style.height = 'auto';
    el.input.style.height = Math.min(el.input.scrollHeight, 200) + 'px';
  }

  el.composer.addEventListener('submit', function (event) {
    event.preventDefault();
    sendMessage(el.input.value);
  });

  el.input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      sendMessage(el.input.value);
    }
  });
  el.input.addEventListener('input', autoResize);

  root.querySelectorAll('[data-suggestion]').forEach(function (button) {
    button.addEventListener('click', function () { sendMessage(button.textContent); });
  });

  root.querySelectorAll('[data-new-chat]').forEach(function (button) {
    button.addEventListener('click', function () { newConversation(true); });
  });

  root.querySelector('[data-resolve]').addEventListener('click', resolveConversation);
  root.querySelector('[data-create-ticket]').addEventListener('click', createTicket);
  el.ticketLink.addEventListener('click', createTicket);

  window.addEventListener('popstate', function (event) {
    const id = event.state && event.state.id;
    if (id) openConversation(id, false);
    else newConversation(false);
  });

  /* ------------------------------------------------------------
   * Käynnistys
   * ---------------------------------------------------------- */

  checkStatus();
  loadList();
  if (root.dataset.activeId) {
    history.replaceState({ id: Number(root.dataset.activeId) }, '', window.location.href);
    openConversation(root.dataset.activeId, false);
  } else {
    history.replaceState({ id: null }, '', window.location.href);
    renderAll();
  }
})();
