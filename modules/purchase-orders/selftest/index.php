<?php declare(strict_types=1); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PO Module — Self Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --ok:#1aa34a; --err:#d12b2b; --muted:#6c757d; }
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin:0; padding:24px; background:#0b0f14; color:#e9eef5;}
  h1 { margin:0 0 12px; font-weight:600; }
  .card { background:#121823; border:1px solid #1c2431; border-radius:12px; padding:16px; margin-bottom:16px; }
  .row { display:flex; gap:12px; flex-wrap:wrap; }
  .col { flex:1 1 340px; min-width:300px;}
  button { background:#1c2535; color:#e9eef5; border:1px solid #273249; border-radius:8px; padding:10px 14px; cursor:pointer; margin:4px 6px 0 0; }
  button:hover { background:#232e42; }
  .pill { display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; }
  .ok{ background:rgba(26,163,74,.15); color:#1aa34a; border:1px solid rgba(26,163,74,.35); }
  .err{ background:rgba(209,43,43,.15); color:#d12b2b; border:1px solid rgba(209,43,43,.35); }
  .muted{ background:rgba(108,117,125,.15); color:#9fb0c8; border:1px solid rgba(108,117,125,.35); }
  .log { background:#0f141d; border:1px solid #1b2433; padding:12px; border-radius:8px; max-height:360px; overflow:auto; font:12px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  code { color:#9cc3ff; }
</style>
</head>
<body>
<h1>PO Module — Self Test</h1>

<div class="card">
  <div class="row">
    <div class="col">
      <div><b>Module Base</b>: <code id="base"></code></div>
      <div class="foot">Tip: run buttons top-right; log will show request/response.</div>
    </div>
    <div class="col">
      <button id="runAll">Run All</button>
      <button id="getCsrf">Get CSRF</button>
      <button id="testA">Happy Path</button>
      <button id="testB">Replay</button>
      <button id="testC">Conflict</button>
      <button id="testD">Live Upsert</button>
      <button id="health">Health</button>
      <button id="drain">Drain Queue (safe)</button>
    </div>
  </div>
</div>

<div class="card">
  <div><b>Status</b> <span id="status" class="pill muted">idle</span></div>
  <div class="log" id="log"></div>
</div>

<script>
(async function(){
  const base = location.origin + '/modules/purchase-orders';
  document.getElementById('base').textContent = base;

  const elLog = document.getElementById('log');
  const elStatus = document.getElementById('status');
  const ctx = { base, csrf: null, idemA: null };

  function log(obj, label='') {
    const t = new Date().toISOString();
    const line = typeof obj === 'string' ? `[${t}] ${label} ${obj}` : `[${t}] ${label} ` + JSON.stringify(obj, null, 2);
    elLog.textContent += line + "\n"; elLog.scrollTop = elLog.scrollHeight;
  }
  function status(txt, ok=true){ elStatus.textContent = txt; elStatus.className = 'pill ' + (ok?'ok':'err'); }
  async function xhr(url, opts={}) {
    const res = await fetch(url, opts);
    const ct = res.headers.get('content-type') || '';
    const body = ct.includes('application/json') ? await res.json() : await res.text();
    return { ok: res.ok, status: res.status, headers: res.headers, body };
  }
  function newIdem(){ return Array.from(crypto.getRandomValues(new Uint8Array(16))).map(b=>b.toString(16).padStart(2,'0')).join(''); }

  async function getCSRF(){
    status('getting CSRF…', true);
    const r = await xhr(`${ctx.base}/tools.php?action=csrf`, { credentials:'include', headers:{'Accept':'application/json'}});
    const hdr = r.headers.get('x-csrf-token');
    ctx.csrf = hdr || (r.body?.data?.csrf);
    log({ header: hdr, json: r.body }, 'CSRF');
    status(ctx.csrf ? 'csrf ok' : 'csrf fail', !!ctx.csrf);
  }

  async function testA(){ // happy path live=0
    if (!ctx.csrf) await getCSRF();
    ctx.idemA = newIdem();
    status('Test A…', true);
    const form = new URLSearchParams({ product_id:'1001', outlet_id:'1', stock:'7', live:'0' });
    const r = await xhr(`${ctx.base}/ajax/actions/update_live_stock.php`, {
      method:'POST', credentials:'include',
      headers:{ 'X-CSRF-Token': ctx.csrf, 'Idempotency-Key': ctx.idemA, 'Accept':'application/json' },
      body: form
    });
    log(r, 'Test A'); status(r.ok ? 'A ok' : 'A fail', r.ok);
  }

  async function testB(){ // replay
    if (!ctx.csrf || !ctx.idemA) { log('Run Test A first'); return; }
    status('Test B…', true);
    const form = new URLSearchParams({ product_id:'1001', outlet_id:'1', stock:'7', live:'0' });
    const r = await xhr(`${ctx.base}/ajax/actions/update_live_stock.php`, {
      method:'POST', credentials:'include',
      headers:{ 'X-CSRF-Token': ctx.csrf, 'Idempotency-Key': ctx.idemA, 'Accept':'application/json' },
      body: form
    });
    log(r, 'Test B'); status(r.ok ? 'B ok' : 'B fail', r.ok);
  }

  async function testC(){ // conflict (different body, same Idem => 409)
    if (!ctx.csrf || !ctx.idemA) { log('Run Test A first'); return; }
    status('Test C…', true);
    const form = new URLSearchParams({ product_id:'1001', outlet_id:'1', stock:'9', live:'0' });
    const r = await xhr(`${ctx.base}/ajax/actions/update_live_stock.php`, {
      method:'POST', credentials:'include',
      headers:{ 'X-CSRF-Token': ctx.csrf, 'Idempotency-Key': ctx.idemA, 'Accept':'application/json' },
      body: form
    });
    log(r, 'Test C'); status(r.status === 409 ? 'C ok (409)' : 'C unexpected', r.status === 409);
  }

  async function testD(){ // live=1 upsert (local DB path)
    if (!ctx.csrf) await getCSRF();
    status('Test D…', true);
    const form = new URLSearchParams({ product_id:'1001', outlet_id:'1', stock:'11', live:'1' });
    const r = await xhr(`${ctx.base}/ajax/actions/update_live_stock.php`, {
      method:'POST', credentials:'include',
      headers:{ 'X-CSRF-Token': ctx.csrf, 'Idempotency-Key': newIdem(), 'Accept':'application/json' },
      body: form
    });
    log(r, 'Test D'); status(r.ok ? 'D ok' : 'D fail', r.ok);
  }

  async function health(){
    status('health…', true);
    const r = await xhr(`${location.origin}/core/health.php`, { headers:{'Accept':'application/json'} });
    log(r, 'Health'); status(r.ok ? 'health ok' : 'health fail', r.ok);
  }

  async function drain(){ // if you add a drain endpoint later
    status('drain…', true);
    const r = await xhr(`${ctx.base}/selftest/consume.php`, { headers:{'Accept':'application/json'} });
    log(r, 'Drain'); status(r.ok ? 'drain ok' : 'drain fail', r.ok);
  }

  // wire buttons
  document.getElementById('getCsrf').onclick = getCSRF;
  document.getElementById('testA').onclick  = testA;
  document.getElementById('testB').onclick  = testB;
  document.getElementById('testC').onclick  = testC;
  document.getElementById('testD').onclick  = testD;
  document.getElementById('health').onclick = health;
  document.getElementById('drain').onclick  = drain;
  document.getElementById('runAll').onclick = async () => {
    elLog.textContent = '';
    await getCSRF(); await testA(); await testB(); await testC(); await testD(); await health();
    status('All done', true);
  };
})();
</script>
</body>
</html>
