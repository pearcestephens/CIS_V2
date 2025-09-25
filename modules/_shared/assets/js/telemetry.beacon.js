/*
  Minimal consent-gated client telemetry beacon
  Captures: clicks, scroll depth, viewport, navigation timing
  Does NOT capture keystrokes or screenshots.
*/
(function(){
  var w = window, d = document;
  if (!w.__CIS_TELEM__) w.__CIS_TELEM__ = {};
  var S = w.__CIS_TELEM__;
  if (S.ready) return; S.ready = true;

  // Config
  var endpoint = 'https://staff.vapeshed.co.nz/modules/_shared/telemetry_beacon.php';
  var batchSize = 20, flushMs = 5000;
  var consent = !!(w.CIS_TELEMETRY_CONSENT || d.documentElement.getAttribute('data-telem-consent') === 'true');
  if (!consent) return; // hard gate

  // CSRF
  var csrf = (d.querySelector('meta[name="csrf-token"]')||{}).content || (w.CSRF_TOKEN||'');
  if (!csrf) { console.warn('telemetry: missing CSRF'); return; }

  var q = [];
  function now(){ return Date.now(); }
  function ev(type, payload){ q.push({t:now(), type:type, p:payload||{}}); if(q.length>=batchSize) flush(); }
  function flush(){ if(!q.length) return; var out=q.slice(); q.length=0; send(out); }

  function send(events){
    var body = JSON.stringify({ consent:true, events:events, href:location.href, ref:document.referrer, vp:[w.innerWidth,w.innerHeight], tz:Intl.DateTimeFormat().resolvedOptions().timeZone });
    try {
      navigator.sendBeacon && navigator.sendBeacon(endpoint, body) || fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body:body, keepalive:true});
    } catch(e){ /* noop */ }
  }

  // Clicks
  d.addEventListener('click', function(e){
    var t = e.target.closest('a,button,[role="button"],.btn');
    var tag = t ? (t.tagName + '#' + (t.id||'') + '.' + (t.className||'')) : (e.target.tagName||'');
    var href = t && t.href ? t.href : null;
    ev('click',{tag:tag, href:href});
  }, {capture:true});

  // Scroll depth
  var maxY = 0; 
  w.addEventListener('scroll', function(){
    var y = (w.scrollY||d.documentElement.scrollTop||0) + w.innerHeight;
    if (y>maxY){ maxY=y; ev('scroll',{maxY:maxY, docH:d.documentElement.scrollHeight}); }
  }, {passive:true});

  // Navigation timing (one-shot)
  w.addEventListener('load', function(){
    try { var t = performance.getEntriesByType('navigation')[0] || performance.timing; ev('nav', { dom: t.domContentLoadedEventEnd||0, load: t.loadEventEnd||0, start: t.startTime||t.navigationStart||0 }); } catch(_){ }
    setTimeout(flush, 1200);
  });

  // Periodic flush
  setInterval(flush, flushMs);
})();
