(() => {
  'use strict';
  const ORIGIN = location.origin;
  const ENDPOINT = ORIGIN + '/modules/_shared/telemetry_beacon.php';
  const CONSENT_KEY = 'cisTelemetryConsent';
  const sampling = { mousemoveMs: 200, flushMs: 5000 };
  const state = { consent: false, queue: [], lastMouseSend: 0, lastMove: 0 };

  function hasConsent() {
    try { return localStorage.getItem(CONSENT_KEY) === 'yes'; } catch { return false; }
  }
  function setConsent(yes) {
    try { localStorage.setItem(CONSENT_KEY, yes ? 'yes' : 'no'); } catch {}
    state.consent = !!yes;
  }
  state.consent = hasConsent();

  function enqueue(ev) {
    if (!state.consent) return;
    state.queue.push({ t: Date.now(), ...ev });
  }

  async function flush() {
    if (!state.consent) return;
    if (state.queue.length === 0) return;
    const batch = state.queue.splice(0, state.queue.length);
    try {
      await fetch(ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          module: (document.body?.dataset?.module) || '',
          view: (document.body?.dataset?.view) || '',
          events: batch,
          uri: location.pathname + location.search,
          ref: document.referrer || '',
        })
      });
    } catch (e) {
      // swallow errors; do not disrupt UX
    }
  }

  // Public API to grant/withdraw consent
  window.CISTelemetry = {
    grant: () => setConsent(true),
    withdraw: () => setConsent(false),
    flush,
  };

  // Do nothing until consent is present
  if (!state.consent) return;

  // Page load metrics
  window.addEventListener('load', () => {
    const perf = performance.getEntriesByType('navigation')[0];
    const ttfb = perf && perf.responseStart ? Math.round(perf.responseStart) : null;
    const dom = perf && perf.domContentLoadedEventEnd ? Math.round(perf.domContentLoadedEventEnd) : null;
    const load = perf && perf.loadEventEnd ? Math.round(perf.loadEventEnd) : null;
    enqueue({ type: 'page-load', ttfb, dom, load });
    flush();
  });

  // Clicks (no innerText, no value capture)
  document.addEventListener('click', (e) => {
    const el = e.target && e.target.closest ? e.target.closest('*') : e.target;
    if (!el) return;
    const rect = (el.getBoundingClientRect && el.getBoundingClientRect()) || { left:0, top:0 };
    enqueue({ type: 'click', x: e.clientX, y: e.clientY, tag: el.tagName, id: el.id || '', class: (el.className||'').toString().slice(0,80), dx: Math.round(e.clientX-rect.left), dy: Math.round(e.clientY-rect.top) });
  }, { passive: true });

  // Mouse move sampling
  document.addEventListener('mousemove', (e) => {
    const now = performance.now();
    if (now - state.lastMove < sampling.mousemoveMs) return;
    state.lastMove = now;
    enqueue({ type: 'mm', x: e.clientX, y: e.clientY });
  }, { passive: true });

  // Hover (enter only)
  document.addEventListener('mouseover', (e) => {
    const el = e.target;
    if (!el) return;
    enqueue({ type: 'hover', tag: el.tagName, id: el.id || '', class: (el.className||'').toString().slice(0,80) });
  }, { passive: true });

  // Keypress metadata (no key values, no input values)
  document.addEventListener('keydown', (e) => {
    const el = e.target;
    if (!el || !(el instanceof HTMLElement)) return;
    const tag = el.tagName;
    const type = (el.getAttribute('type')||'').toLowerCase();
    if (tag === 'INPUT' && (type === 'password' || type === 'email' || type === 'tel')) return; // skip sensitive fields
    enqueue({ type: 'key', tag, inputType: type || null });
  }, { passive: true });

  // Visibility
  document.addEventListener('visibilitychange', () => {
    enqueue({ type: 'vis', hidden: document.hidden });
  });

  // Periodic flush
  setInterval(flush, sampling.flushMs);
})();
