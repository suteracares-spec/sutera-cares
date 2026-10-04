/* ============================================================
   Sutera Care Provider — small page behaviours.
   Shared by index.html, privacy.html and the guides.
   ============================================================ */

(function () {
  'use strict';

  /* ---------------------------------------------------------
     The care line, written once. Fill in CARE_LINE with the
     full international number, digits only (e.g. '60123456789')
     and every phone link plus the WhatsApp button switch on.
     While it is empty, the placeholder text stays and the
     WhatsApp button stays hidden.
     --------------------------------------------------------- */
  var CARE_LINE = '';

  function pretty(digits) {
    // 60123456789 -> +60 12-345 6789 ; 601123456789 -> +60 11-2345 6789
    var m = /^60(1\d)(\d{3,4})(\d{4})$/.exec(digits);
    return m ? '+60 ' + m[1] + '-' + m[2] + ' ' + m[3] : '+' + digits;
  }

  function wireCareLine() {
    if (!/^\d{8,15}$/.test(CARE_LINE)) return;
    var shown = pretty(CARE_LINE);
    each('.js-careline', function (el) {
      var text = el.querySelector('.js-careline-text') || el;
      text.textContent = shown;
      if (el.tagName === 'A') el.href = 'tel:+' + CARE_LINE;
    });
    each('.js-whatsapp', function (el) {
      el.href = 'https://wa.me/' + CARE_LINE;
      el.hidden = false;
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
    wireCareLine();
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
