/* ============================================================
   Sutera Care Provider — small page behaviours.
   Shared by index.html, privacy.html and the guides.
   ============================================================ */

(function () {
  'use strict';

  /* ---------------------------------------------------------
     WhatsApp. The care line number is written into the HTML
     (tel: and wa.me links) so it works without JavaScript; to
     change it, search the provider/ folder for 60189422745.
     Here we only add a greeting, in the language the visitor is
     reading, so the office knows what the chat is about.
     data-wa="job" marks links from the careers section.
     --------------------------------------------------------- */
  var CARE_LINE = '60189422745';

  var GREETING = {
    care: {
      en: 'Hi Sutera Care, I would like to ask about home care.',
      ms: 'Hai Sutera Care, saya ingin bertanya tentang penjagaan di rumah.',
      zh: '您好 Sutera Care，我想咨询居家护理服务。'
    },
    job: {
      en: 'Hi Sutera Care, I would like to ask about working as a caregiver.',
      ms: 'Hai Sutera Care, saya ingin bertanya tentang kerja sebagai penjaga.',
      zh: '您好 Sutera Care，我想咨询看护员的工作。'
    }
  };

  function currentLang() {
    var l = (document.documentElement.getAttribute('lang') || 'en').slice(0, 2);
    return l === 'ms' || l === 'zh' ? l : 'en';
  }

  /* Delegated, because the language switcher rewrites some links. */
  function wireWhatsApp() {
    each('.js-whatsapp[hidden]', function (el) { el.hidden = false; });
    document.addEventListener('click', function (e) {
      var link = e.target.closest ? e.target.closest('.js-whatsapp') : null;
      if (!link) return;
      var kind = GREETING[link.getAttribute('data-wa')] ? link.getAttribute('data-wa') : 'care';
      link.href = 'https://wa.me/' + CARE_LINE + '?text=' + encodeURIComponent(GREETING[kind][currentLang()]);
    });
  }

  /* ---------------------------------------------------------
     Mobile menu
     --------------------------------------------------------- */
  function wireMenu() {
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('main-nav');
    if (!toggle || !nav) return;

    function set(open) {
      toggle.setAttribute('aria-expanded', String(open));
      nav.classList.toggle('is-open', open);
    }
    toggle.addEventListener('click', function () {
      set(toggle.getAttribute('aria-expanded') !== 'true');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) set(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) {
        set(false);
        toggle.focus();
      }
    });
  }

  /* ---------------------------------------------------------
     The forms post to the portal, which sends the visitor back
     with ?sent=enquiry or ?sent=application. Show the matching
     confirmation and tidy the URL so a refresh doesn't repeat it.
     --------------------------------------------------------- */
  function showSent() {
    var params = new URLSearchParams(location.search);
    var sent = params.get('sent');
    if (!sent) return;
    var el = document.getElementById('sent-' + sent);
    if (el) {
      el.hidden = false;
      el.scrollIntoView({ block: 'center' });
    }
    params.delete('sent');
    var q = params.toString();
    history.replaceState(null, '', location.pathname + (q ? '?' + q : '') + location.hash);
  }

  function each(sel, fn) {
    Array.prototype.forEach.call(document.querySelectorAll(sel), fn);
  }

  function init() {
    wireWhatsApp();
    wireMenu();
    showSent();
    each('.js-year', function (el) { el.textContent = new Date().getFullYear(); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
