(function(){
  'use strict';
  var w = window;
  if (!w.CIS_TELEMETRY) w.CIS_TELEMETRY = {};
  var cfg = Object.assign({
    enabled: true,
    consentKey: 'cis_telemetry_consent',
    endpoint: '/modules/_shared/telemetry_endpoint.php',
    heatmap: { enabled: true, gridX: 24, gridY: 14, throttleMs: 250 },
    clicks: { enabled: true },
    redactSelectors: ['input[type=password]', 'input[name*="password" i]', 'textarea'],
  }, w.CIS_TELEMETRY);

  function hasConsent(){
    try { return localStorage.getItem(cfg.consentKey) === 'true'; } catch(e){ return false; }
  }
  if (!cfg.enabled || !hasConsent()) return; // opt-in only

  var payloads = [];
  function sendBeacon(type, data){
    try {
      var body = JSON.stringify({ type: type, data: data, ts: Date.now(), href: location.href });
      if (navigator.sendBeacon) {
        var blob = new Blob([body], {type: 'application/json'});
        navigator.sendBeacon(cfg.endpoint, blob);
      } else {
        // async fallback
        fetch(cfg.endpoint, {method:'POST', headers:{'Content-Type':'application/json'}, body: body, keepalive:true});
      }
    } catch(e) { /* ignore */ }
  }

  // Client performance timings (Navigation Timing Level 2)
  try {
    var perf = performance.getEntriesByType('navigation')[0] || performance.timing;
    var nav = perf ? {
      type: perf.type || 'navigate',
      domContentLoaded: (perf.domContentLoadedEventEnd||0) - (perf.startTime||perf.navigationStart||0),
      load: (perf.loadEventEnd||0) - (perf.startTime||perf.navigationStart||0),
      ttfb: (perf.responseStart||0) - (perf.requestStart||0),
    } : {};
    sendBeacon('page_perf_client', nav);
  } catch(e){}

  // Heatmap (coarse grid, throttled). No keystrokes captured.
  var grid = null, lastSent = 0;
  function initGrid(){
    grid = new Array(cfg.heatmap.gridX * cfg.heatmap.gridY).fill(0);
  }
  function idxFromXY(x, y){
    var gx = Math.max(0, Math.min(cfg.heatmap.gridX-1, Math.floor(x / (window.innerWidth / cfg.heatmap.gridX))));
    var gy = Math.max(0, Math.min(cfg.heatmap.gridY-1, Math.floor(y / (window.innerHeight / cfg.heatmap.gridY))));
    return gy * cfg.heatmap.gridX + gx;
  }
  function onMove(e){
    if (!grid) return;
    var now = Date.now();
    grid[idxFromXY(e.clientX, e.clientY)]++;
    if (now - lastSent > 10000) { // send every ~10s
      lastSent = now;
      sendBeacon('heatmap', { grid: grid, gx: cfg.heatmap.gridX, gy: cfg.heatmap.gridY });
      initGrid();
    }
  }
  if (cfg.heatmap.enabled) {
    initGrid();
    window.addEventListener('mousemove', function(e){
      // JS throttle (cheap): rely on coarse grid accumulation + 10s batch send
      onMove(e);
    }, {passive:true});
    window.addEventListener('beforeunload', function(){
      if (grid) sendBeacon('heatmap', { grid: grid, gx: cfg.heatmap.gridX, gy: cfg.heatmap.gridY });
    });
  }

  // Clicks (aggregate by element tag + coarse selector hash). No innerText captured.
  function hashSelector(el){
    try {
      var parts = [];
      while (el && el.nodeType === 1 && parts.length < 4) {
        var p = el.nodeName.toLowerCase();
        if (el.id) p += '#' + el.id;
        else if (el.className && typeof el.className === 'string') p += '.' + el.className.split(/\s+/).slice(0,2).join('.');
        parts.unshift(p);
        el = el.parentElement;
      }
      return parts.join('>');
    } catch(e) { return 'unknown'; }
  }
  var clickCounts = {};
  if (cfg.clicks.enabled) {
    window.addEventListener('click', function(e){
      var el = e.target;
      // Ignore inputs/textareas to avoid content inference
      if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable)) return;
      var key = (el ? hashSelector(el) : 'unknown');
      clickCounts[key] = (clickCounts[key]||0) + 1;
    }, {passive:true});
    window.addEventListener('beforeunload', function(){
      var arr = Object.keys(clickCounts).map(function(k){ return { sel:k, n:clickCounts[k] }; });
      if (arr.length) sendBeacon('clicks', { items: arr.slice(0,100) });
    });
  }
})();
