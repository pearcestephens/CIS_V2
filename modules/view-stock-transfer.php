<?php
/**
 * view-stock-transfer.php
 * Purpose: Minimal legacy shim page providing simple tools for stock transfers.
 * Author: CIS Dev Bot
 * Last Modified: 2025-09-24
 * Dependencies: modules/module.php (env bootstrap), cis_pdo(), requireLoggedInUser()
 */

// Bootstrap legacy modules environment
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/module.php';

// Normalize transfer id from various keys
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$tid = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $tid = (int)$_GET[$__k]; break; } }

// PACKONLY flag maintained for backward compatibility (blocks submit-like actions if true)
$PACKONLY = ((int)($_GET['packonly'] ?? 0) === 1);

// Redirect all non-POST requests to the new template Pack view
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  $target = 'https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack';
  if ($tid > 0) {
    $target .= '&transfer=' . urlencode((string)$tid);
  }
  if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: ' . $target, true, 302);
  }
  exit;
}

// Local helper to enforce 200-only responses with embedded code/status
if (!function_exists('st_json_response')) {
    /**
     * Send a normalized JSON response with an always-200 HTTP status
     * @param array $data
     * @param int $statusCode logical status embedded in payload
     */
    function st_json_response(array $data, int $statusCode = 200): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        if ($statusCode !== 200) {
            $data['code'] = $statusCode;
            if (!isset($data['status'])) $data['status'] = 'error';
        } else {
            if (!isset($data['status'])) $data['status'] = ($data['success'] ?? true) ? 'ok' : 'error';
            if (!isset($data['code'])) $data['code'] = 200;
        }
        http_response_code(200);
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// --------- Unified, minimal POST handler (simple tools only) ---------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Require logged-in user for any actions
    try {
        $userRow = requireLoggedInUser();
    } catch (Throwable $e) {
        st_json_response(['success' => false, 'error' => 'Not logged in. Please sign in and try again.'], 401);
    }

    $allowedKeys  = ['searchForProduct', 'deleteTransfer', 'markReadyForDelivery'];
    $presentKeys  = array_values(array_filter($allowedKeys, static fn($k) => isset($_POST[$k])));
    if (count($presentKeys) !== 1) {
        st_json_response(['success' => false, 'error' => 'Provide exactly one action.'], 400);
    }

    $action = $presentKeys[0];

    // This page is not about packing; block submit-like actions
    if ($action === 'markReadyForDelivery') {
        $msg = $PACKONLY
            ? 'Pack-Only Mode: submission is disabled.'
            : 'Submission from this legacy tools page is disabled. Use the Stock Transfers module.';
        st_json_response(['success' => false, 'error' => $msg], 403);
    }

    if ($action === 'deleteTransfer') {
        st_json_response([
            'success' => false,
            'error'   => 'Delete is not available from this legacy tools page. Please use the Stock Transfers module.'
        ], 400);
    }

    if ($action === 'searchForProduct') {
        try {
            $payloadRaw = $_POST['searchForProduct'] ?? [];
            if (is_string($payloadRaw)) {
                $decoded = json_decode($payloadRaw, true);
                if (json_last_error() === JSON_ERROR_NONE) { $payloadRaw = $decoded; }
            }
            $payload = is_array($payloadRaw) ? $payloadRaw : (array)$payloadRaw;

            $keyword  = trim((string)($payload['keyword'] ?? ''));
            $outletId = (string)($payload['outletID'] ?? '');
            $limit    = 50;

            if (strlen($keyword) < 2) {
                st_json_response(['success' => true, 'data' => []]);
            }

            if (!function_exists('cis_pdo')) {
                st_json_response(['success' => false, 'error' => 'DB unavailable'], 500);
            }
            $pdo = cis_pdo();

            $isUuid = (bool)preg_match('/^[a-f0-9-]{8,}$/i', $keyword);
            $like = '%' . $keyword . '%';
            $params = [ ':like' => $like, ':outlet' => $outletId ];
            $where = '(vp.name LIKE :like OR vp.sku LIKE :like)';
            if ($isUuid) { $where .= ' OR vp.id = :id'; $params[':id'] = $keyword; }

            $sql = "SELECT vp.id AS id,
                           vp.name AS name,
                           vp.sku  AS sku,
                           COALESCE(vi.inventory_level, 0) AS stock,
                           COALESCE(vp.retail_price, vp.price, 0) AS price
                      FROM vend_products vp
                      LEFT JOIN vend_inventory vi ON vi.product_id = vp.id AND vi.outlet_id = :outlet
                     WHERE $where
                     ORDER BY vp.name ASC
                     LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            st_json_response(['success' => true, 'data' => $rows]);
        } catch (Throwable $e) {
            error_log('[view-stock-transfer.searchForProduct] ' . $e->getMessage());
            st_json_response(['success' => false, 'error' => 'Search failed'], 500);
        }
    }

    // Fallback (should not reach)
    st_json_response(['success' => false, 'error' => 'Unknown action'], 400);
}

// --------- GET: render extremely simple tools page ---------
$transferId = $tid;
$dashboardUrl = 'https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php';
$moduleUrl    = $transferId > 0
    ? 'https://staff.vapeshed.co.nz/modules/transfers/stock/outgoing.php?transfer=' . urlencode((string)$transferId)
    : 'https://staff.vapeshed.co.nz/modules/transfers/stock/outgoing.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stock Transfer Tools</title>
  <style>
    :root { --gap: 12px; --fg: #1b1f23; --muted:#6a737d; --brand:#0d6efd; --bg:#fff; --line:#e1e4e8; --danger:#dc3545; --warn:#fd7e14; --ok:#198754; }
    body { font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--fg); background:#f6f8fa; margin:0; }
    .container { max-width: 980px; margin: 24px auto; padding: 0 var(--gap); }
    .card { background:var(--bg); border:1px solid var(--line); border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    .card-header { padding:16px 20px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .card-body { padding: 16px 20px; }
    .row { display:flex; gap: var(--gap); flex-wrap:wrap; }
    .col { flex:1 1 300px; }
    h1 { font-size: 18px; margin:0; }
    h2 { font-size: 14px; margin: 0 0 6px; color:#111; }
    .muted { color: var(--muted); }
    .btn { display:inline-block; background:var(--brand); color:#fff; text-decoration:none; padding:8px 12px; border-radius:6px; border:1px solid #0b5ed7; font-weight:600; cursor:pointer; }
    .btn:disabled { opacity:.6; cursor:not-allowed; }
    .btn.secondary { background:#fff; color:var(--brand); border-color:var(--brand); }
    .btn.link { background:transparent; border:none; color:var(--brand); padding:0; }
    .toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
    label { font-size:12px; color:var(--muted); }
    input[type="text"], input[type="number"] { padding:8px 10px; border:1px solid var(--line); border-radius:6px; width:100%; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:8px 10px; border-bottom:1px solid var(--line); text-align:left; }
    th { font-size:12px; color:var(--muted); font-weight:600; background:#fafbfc; }
    .copy { color: var(--brand); cursor: pointer; text-decoration: underline; }
    .pill { display:inline-block; padding:2px 8px; border-radius:999px; background:#eef2ff; color:#334; font-size:12px; }
    .help { font-size:12px; color:var(--muted); }
    .spacer { height:8px; }
    .alert { padding:10px 12px; border:1px solid var(--line); border-radius:6px; background:#fffbe6; }
    .status { font-size:12px; margin-top:4px; }
    .status.ok { color: var(--ok); }
    .status.warn { color: var(--warn); }
    .status.error { color: var(--danger); }
    .grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: var(--gap); }
    @media (max-width: 760px){ .grid { grid-template-columns: 1fr; } }
    .kbd { font-family: ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; background:#f0f3f6; border:1px solid var(--line); padding:1px 6px; border-radius:4px; }
    .aside { border-left: 3px solid var(--line); padding-left: 10px; color: var(--muted); font-size: 12px; }
    ul.tips { margin: 8px 0 0 16px; padding: 0; }
    ul.tips li { margin: 4px 0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Stock Transfer Tools</h1>
        <div class="toolbar">
          <a class="btn secondary" href="<?= htmlspecialchars($dashboardUrl) ?>">Open Transfers Dashboard</a>
          <a class="btn secondary" href="<?= htmlspecialchars($moduleUrl) ?>">Open Full Module</a>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col">
            <div class="field">
              <label>Transfer ID</label>
              <input type="number" id="transfer-id" value="<?= (int)$transferId ?>" min="0" step="1" />
              <div class="help">This legacy page doesn’t submit transfers. Use links above for the full workflow.</div>
            </div>
            <div class="field">
              <label>Mode</label>
              <span class="pill">Simple tools only</span>
            </div>
          </div>
          <div class="col">
            <div class="field">
              <label>Quick Product Search</label>
              <div class="row" style="gap:8px; align-items:flex-end;">
                <div class="col" style="flex:1 1 120px;">
                  <label class="muted">Outlet ID</label>
                  <input type="text" id="outlet-id" placeholder="e.g. 1" />
                </div>
                <div class="col" style="flex:3 1 240px;">
                  <label class="muted">Keyword / SKU / UUID</label>
                  <input type="text" id="keyword" placeholder="Search products…" />
                </div>
                <div class="col" style="flex:0 0 auto;">
                  <button class="btn" id="btn-search">Search</button>
                </div>
              </div>
              <div class="spacer"></div>
              <table id="results">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th style="width:90px;">Stock</th>
                    <th style="width:90px;">Price</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="4" class="muted">Type at least 2 characters and click Search.</td></tr>
                </tbody>
              </table>
              <div class="aside">Tip: Click a SKU to copy it to your clipboard.</div>
            </div>
          </div>
        </div>

        <div class="spacer"></div>
        <div class="grid">
          <div>
            <h2>Quantity Validator</h2>
            <div class="field">
              <label>Quantity</label>
              <input type="text" id="qty" inputmode="numeric" placeholder="e.g. 9 or 12" />
              <div id="qty-status" class="status muted">Enter a quantity. We’ll normalize leading zeros and flag suspicious values.</div>
            </div>
          </div>
          <div>
            <h2>Barcode Tools</h2>
            <div class="field">
              <label>Scan or Enter Barcode</label>
              <input type="text" id="barcode" placeholder="EAN-13, UPC-A, EAN-8…" />
              <div id="barcode-status" class="status muted">Press <span class="kbd">Enter</span> to validate.</div>
            </div>
            <div class="field">
              <label>Compute Check Digit (EAN-13)</label>
              <input type="text" id="barcode-base" placeholder="First 12 digits" />
              <div class="status muted">Outputs full code with check digit when valid length.</div>
              <div id="barcode-full" class="status"></div>
            </div>
          </div>
        </div>

        <div class="spacer"></div>
        <div class="card" style="border:none;">
          <div class="card-body" style="padding:0;">
            <h2>Box & Weight Calculator</h2>
            <div class="row" style="gap:12px;">
              <div class="col">
                <div class="field">
                  <label>Quantity</label>
                  <input type="text" id="bx-qty" inputmode="numeric" placeholder="Units (e.g. 48)" />
                </div>
              </div>
              <div class="col">
                <div class="field">
                  <label>Unit Weight (kg)</label>
                  <input type="text" id="bx-unit" inputmode="decimal" placeholder="e.g. 0.12" />
                </div>
              </div>
              <div class="col">
                <div class="field">
                  <label>Max Per Box (kg)</label>
                  <input type="text" id="bx-max" inputmode="decimal" placeholder="e.g. 16" value="16" />
                </div>
              </div>
              <div class="col">
                <div class="field">
                  <label>Tare Per Box (kg)</label>
                  <input type="text" id="bx-tare" inputmode="decimal" placeholder="e.g. 0.2" value="0.2" />
                </div>
              </div>
            </div>
            <div id="bx-status" class="status muted">Enter quantities/weights to compute box plan.</div>
            <div id="bx-output" class="status"></div>
          </div>
        </div>

        <div class="spacer"></div>
        <div class="grid">
          <div>
            <h2>Measure & Dimensional Weight</h2>
            <div class="row" style="gap:12px;">
              <div class="col"><div class="field"><label>Length (cm)</label><input type="text" id="dim-l" inputmode="decimal" placeholder="e.g. 40"></div></div>
              <div class="col"><div class="field"><label>Width (cm)</label><input type="text" id="dim-w" inputmode="decimal" placeholder="e.g. 30"></div></div>
              <div class="col"><div class="field"><label>Height (cm)</label><input type="text" id="dim-h" inputmode="decimal" placeholder="e.g. 25"></div></div>
              <div class="col"><div class="field"><label>Actual Weight (kg)</label><input type="text" id="dim-kg" inputmode="decimal" placeholder="e.g. 8.5"></div></div>
            </div>
            <div class="row" style="gap:12px; align-items:flex-end;">
              <div class="col" style="flex:1 1 180px;">
                <div class="field">
                  <label>Service (Volumetric Divisor)</label>
                  <select id="dim-svc">
                    <option value="5000">NZ Post (÷ 5000)</option>
                    <option value="4000">GSS (÷ 4000)</option>
                  </select>
                </div>
              </div>
              <div class="col" style="flex:0 0 auto;">
                <button class="btn" id="btn-dim">Calculate</button>
              </div>
            </div>
            <div id="dim-status" class="status muted">Enter dimensions to calculate volumetric and chargeable weight.</div>
            <div id="dim-output" class="status"></div>
          </div>
          <div>
            <h2>Box Fit Helper</h2>
            <div class="row" style="gap:12px;">
              <div class="col"><div class="field"><label>Item L×W×H (cm)</label><input type="text" id="fit-item" placeholder="e.g. 10×5×3"></div></div>
              <div class="col"><div class="field"><label>Box L×W×H (cm)</label><input type="text" id="fit-box" placeholder="e.g. 40×30×25"></div></div>
            </div>
            <div id="fit-status" class="status muted">Approximates grid packing (no rotation). Best for regular items.</div>
            <div id="fit-output" class="status"></div>
            <div class="aside">Tip: If void space is high, consider a smaller box or filler. Keep boxes under ~16–20kg for safe handling.</div>
          </div>
        </div>

        <div class="spacer"></div>
        <div class="alert">
          This is a minimal legacy shim. It keeps the old URL working and offers quick search and basic tools. For packing, labels, and full workflows, use the Stock Transfers module.
          <ul class="tips">
            <li>Label on the largest flat face; avoid seams and corners.</li>
            <li>Heavy items at the bottom; distribute weight evenly.</li>
            <li>Use the right tape pattern (H-tape) and reinforce heavy boxes.</li>
            <li>Use filler to stop movement; shake test should feel solid.</li>
            <li>Top-heavy boxes risk tipping; choose a lower, wider box.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      const $ = (s, p=document) => p.querySelector(s);
      const $$ = (s, p=document) => Array.from(p.querySelectorAll(s));
      const resultsBody = $('#results tbody');
      const btn = $('#btn-search');

      function setRows(html){ resultsBody.innerHTML = html; }

      async function search(){
        const keyword = ($('#keyword').value || '').trim();
        const outletID = ($('#outlet-id').value || '').trim();
        if (keyword.length < 2){
          setRows('<tr><td colspan="4" class="muted">Type at least 2 characters.</td></tr>');
          return;
        }
        btn.disabled = true; btn.textContent = 'Searching…';
        try {
          const payload = { keyword, outletID };
          const form = new FormData();
          form.append('searchForProduct', JSON.stringify(payload));
          const res = await fetch(window.location.href, { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const json = await res.json();
          if (!json || json.success !== true){ throw new Error(json?.error || 'Search failed'); }
          const rows = Array.isArray(json.data) ? json.data : [];
          if (!rows.length){ setRows('<tr><td colspan="4" class="muted">No results</td></tr>'); return; }
          const html = rows.map(r => {
            const name = (r.name||'').replace(/</g,'&lt;');
            const sku  = (r.sku||'');
            const stock = Number(r.stock||0);
            const price = Number(r.price||0);
            return `<tr><td>${name}</td><td class=\"copy\" data-sku=\"${sku}\" title=\"Click to copy SKU\">${sku}</td><td>${stock}</td><td>$${price.toFixed(2)}</td></tr>`;
          }).join('');
          setRows(html);
        } catch (e){
          setRows(`<tr><td colspan="4" class="muted">${(e && e.message) ? e.message : 'Search error'}</td></tr>`);
        } finally { btn.disabled = false; btn.textContent = 'Search'; }
      }

      btn.addEventListener('click', search);
      $('#keyword').addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ e.preventDefault(); search(); } });

      // Copy SKU on click
      resultsBody.addEventListener('click', (e)=>{
        const cell = e.target.closest('.copy');
        if (!cell) return;
        const sku = cell.getAttribute('data-sku') || cell.textContent.trim();
        if (!sku) return;
        copyText(sku).then(()=>{ cell.textContent = sku + ' ✓'; setTimeout(()=>{ cell.textContent = sku; }, 1200); });
      });

      function copyText(text){
        if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
        return new Promise((resolve,reject)=>{
          try { const ta = document.createElement('textarea'); ta.value=text; ta.style.position='fixed'; ta.style.opacity='0'; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); resolve(); } catch(e){ reject(e); }
        });
      }

      // Quantity Validator
      const qtyInput = $('#qty');
      const qtyStatus = $('#qty-status');
      function qtyValidate(){
        const raw = (qtyInput.value||'').trim();
        if (raw === '') { qtyStatus.className='status muted'; qtyStatus.textContent='Enter a quantity.'; return; }
        let normalized = raw;
        let notes = [];
        if (/^0\d+$/.test(raw)) { normalized = String(parseInt(raw,10)); notes.push('Leading zero normalized'); }
        if (!/^\d+$/.test(normalized)) { qtyStatus.className='status error'; qtyStatus.textContent='Invalid: numbers only.'; return; }
        const val = parseInt(normalized,10);
        if (val >= 1000) { qtyStatus.className='status error'; qtyStatus.textContent=`${val} looks way too large.`; return; }
        if (normalized !== raw) { qtyInput.value = normalized; }
        // Suspicion heuristics
        if (val >= 99) { notes.push('Very high quantity — double check'); }
        if (/^(\d)\1$/.test(String(val)) && val < 100) { notes.push('Repeated digit — did you mean a single digit?'); }
        if (val === 0) { qtyStatus.className='status warn'; qtyStatus.textContent='Zero entered — is this intended?'; return; }
        qtyStatus.className = notes.length ? 'status warn' : 'status ok';
        qtyStatus.textContent = notes.length ? notes.join('. ') : 'Looks good';
      }
      qtyInput.addEventListener('input', qtyValidate);
      qtyInput.addEventListener('blur', qtyValidate);

      // Barcode tools
      const bcInput = $('#barcode');
      const bcStatus = $('#barcode-status');
      const bcBase = $('#barcode-base');
      const bcFull = $('#barcode-full');

      function sumDigits(str){
        let sOdd=0, sEven=0;
        for (let i=0;i<str.length;i++){
          const d = str.charCodeAt(i)-48; if (d<0||d>9) return null;
          if (((str.length - i) % 2) === 1) sOdd += d; else sEven += d; // EAN-like pattern
        }
        return sOdd + 3*sEven;
      }
      function checkDigitFromWeightedSum(sum){ const m = sum % 10; return (10 - m) % 10; }
      function ean13CheckDigit(base12){ if(!/^\d{12}$/.test(base12)) return null; const sum = sumDigits(base12); if(sum===null) return null; return checkDigitFromWeightedSum(sum); }
      function upcACheckDigit(base11){ if(!/^\d{11}$/.test(base11)) return null; let sOdd=0, sEven=0; for (let i=0;i<base11.length;i++){ const d=base11.charCodeAt(i)-48; if(d<0||d>9) return null; if ((i % 2)===0) sOdd+=d; else sEven+=d; } const sum=(sOdd*3)+sEven; return checkDigitFromWeightedSum(sum); }
      function ean8CheckDigit(base7){ if(!/^\d{7}$/.test(base7)) return null; let s=0; for (let i=0;i<7;i++){ const d=base7.charCodeAt(i)-48; s += d * (i%2===0 ? 3 : 1); } return checkDigitFromWeightedSum(s); }
      function validateBarcode(){
        const raw = (bcInput.value||'').trim().replace(/\s+/g,'');
        if (!raw){ bcStatus.className='status muted'; bcStatus.textContent='Scan or enter a code.'; return; }
        if (!/^\d+$/.test(raw)){ bcStatus.className='status error'; bcStatus.textContent='Digits only (no spaces or letters).'; return; }
        const len = raw.length;
        if (len===13){ const base = raw.slice(0,12), cd = raw.slice(12); const expect = ean13CheckDigit(base); if (expect===null){ bcStatus.className='status error'; bcStatus.textContent='Invalid EAN-13 base digits.'; return; } bcStatus.className = (String(expect)===cd) ? 'status ok' : 'status warn'; bcStatus.textContent = (String(expect)===cd) ? 'Valid EAN-13' : `Check digit mismatch — expected ${expect}`; }
        else if (len===12){ const base = raw.slice(0,11), cd = raw.slice(11); const expect = upcACheckDigit(base); if (expect===null){ bcStatus.className='status error'; bcStatus.textContent='Invalid UPC-A base digits.'; return; } bcStatus.className = (String(expect)===cd) ? 'status ok' : 'status warn'; bcStatus.textContent = (String(expect)===cd) ? 'Valid UPC-A' : `Check digit mismatch — expected ${expect}`; }
        else if (len===8){ const base = raw.slice(0,7), cd = raw.slice(7); const expect = ean8CheckDigit(base); if (expect===null){ bcStatus.className='status error'; bcStatus.textContent='Invalid EAN-8 base digits.'; return; } bcStatus.className = (String(expect)===cd) ? 'status ok' : 'status warn'; bcStatus.textContent = (String(expect)===cd) ? 'Valid EAN-8' : `Check digit mismatch — expected ${expect}`; }
        else { bcStatus.className='status warn'; bcStatus.textContent=`Unsupported length (${len}). Expected 8, 12, or 13 digits.`; }
      }
      bcInput.addEventListener('keydown',(e)=>{ if (e.key==='Enter'){ e.preventDefault(); validateBarcode(); }});
      bcInput.addEventListener('blur', validateBarcode);
      function computeEAN13(){ const base = (bcBase.value||'').trim(); if (!/^\d{12}$/.test(base)){ bcFull.className='status muted'; bcFull.textContent='Enter exactly 12 digits to compute EAN-13.'; return; } const cd = ean13CheckDigit(base); if (cd===null){ bcFull.className='status error'; bcFull.textContent='Invalid base digits.'; return; } bcFull.className='status ok'; bcFull.textContent = `Full EAN-13: ${base}${cd}`; }
      bcBase.addEventListener('input', computeEAN13);

      // Box & Weight Calculator
      const bxQty = $('#bx-qty'), bxUnit = $('#bx-unit'), bxMax = $('#bx-max'), bxTare = $('#bx-tare');
      const bxOut = $('#bx-output'), bxStatus = $('#bx-status');
      function toNum(x){ const v = Number(String(x||'').replace(/,/g,'.')); return Number.isFinite(v) ? v : NaN; }
      function calcBoxes(){
        const rawQ = (bxQty.value||'').trim();
        let q = parseInt(rawQ,10);
        if (/^0\d+$/.test(rawQ)) { q = parseInt(rawQ,10); bxQty.value = String(q); }
        const uw = toNum(bxUnit.value);
        const max = toNum(bxMax.value);
        const tare = toNum(bxTare.value);
        if (!Number.isFinite(q) || q<=0 || !Number.isFinite(uw) || uw<=0 || !Number.isFinite(max) || max<=0 || !Number.isFinite(tare) || tare<0){
          bxStatus.className='status warn'; bxStatus.textContent='Enter positive numbers for all fields.'; bxOut.textContent=''; return;
        }
        const available = max - tare;
        if (available <= 0){ bxStatus.className='status error'; bxStatus.textContent='Max per box must exceed tare weight.'; bxOut.textContent=''; return; }
        const total = q * uw;
        const boxes = Math.max(1, Math.ceil(total / available));
        const avgPerBox = total / boxes;
        const lastBox = total - (available * (boxes-1));
        const notes = [];
        if (uw > 5) notes.push('Unit weight unusually high');
        if (max > 35) notes.push('Max per box exceeds safe handling');
        if (boxes > 50) notes.push('Very high number of boxes');
        bxStatus.className = notes.length ? 'status warn' : 'status ok';
        bxStatus.textContent = notes.length ? notes.join('. ') : 'Computed box plan';
        bxOut.className='status';
        bxOut.innerHTML = `Total weight: <b>${total.toFixed(2)} kg</b>. Boxes needed: <b>${boxes}</b>. Avg per box: <b>${avgPerBox.toFixed(2)} kg</b>. Last box approx: <b>${Math.max(0,lastBox).toFixed(2)} kg</b>.`;
      }
      [bxQty, bxUnit, bxMax, bxTare].forEach(el => el.addEventListener('input', calcBoxes));
      [bxQty, bxUnit, bxMax, bxTare].forEach(el => el.addEventListener('blur', calcBoxes));

      // Dimensional Weight
      const dimL=$('#dim-l'), dimW=$('#dim-w'), dimH=$('#dim-h'), dimKG=$('#dim-kg'), dimSvc=$('#dim-svc');
      const dimOut=$('#dim-output'), dimStatus=$('#dim-status');
      function dimToNum(x){ const v = Number(String(x||'').replace(/,/g,'.')); return Number.isFinite(v) ? v : NaN; }
      function dimCompute(){
        const L=dimToNum(dimL.value), W=dimToNum(dimW.value), H=dimToNum(dimH.value), kg=dimToNum(dimKG.value);
        const divisor = parseInt(dimSvc.value,10) || 5000;
        if (!Number.isFinite(L)||!Number.isFinite(W)||!Number.isFinite(H)||!Number.isFinite(kg) || L<=0||W<=0||H<=0||kg<=0){
          dimStatus.className='status warn'; dimStatus.textContent='Enter positive numbers for all fields.'; dimOut.textContent=''; return;
        }
        const vol = L*W*H; // cm^3
        const volWeight = vol / divisor; // kg
        const chargeable = Math.max(kg, volWeight);
        const notes = [];
        if (Math.max(L,W,H) > 120) notes.push('Longest side over 120cm — oversize');
        if (kg > 25) notes.push('Over 25kg — team lift or break down');
        if (volWeight > kg) notes.push('Volumetric weight exceeds actual — consider smaller box');
        dimStatus.className = notes.length ? 'status warn' : 'status ok';
        dimStatus.textContent = notes.length ? notes.join('. ') : 'Computed dimensional weight';
        dimOut.className='status';
        dimOut.innerHTML = `Volumetric weight: <b>${volWeight.toFixed(2)} kg</b> (divisor ${divisor}). Chargeable weight: <b>${chargeable.toFixed(2)} kg</b>.`;
      }
      $('#btn-dim').addEventListener('click', dimCompute);
      [dimL,dimW,dimH,dimKG,dimSvc].forEach(el=>el.addEventListener('blur', dimCompute));

      // Box Fit Helper
      const fitItem=$('#fit-item'), fitBox=$('#fit-box'), fitOut=$('#fit-output'), fitStatus=$('#fit-status');
      function parseDims(str){
        const m = String(str||'').trim().toLowerCase().replace(/x/g,'×').split('×').map(s=>Number(s.replace(/,/g,'.')));
        if (m.length!==3 || m.some(v=>!Number.isFinite(v) || v<=0)) return null; return m; }
      function fitCompute(){
        const i = parseDims(fitItem.value), b = parseDims(fitBox.value);
        if (!i || !b){ fitStatus.className='status warn'; fitStatus.textContent='Use format L×W×H in cm (e.g., 10×5×3).'; fitOut.textContent=''; return; }
        const [il,iw,ih] = i, [bl,bw,bh] = b;
        const perLayer = Math.max(0, Math.floor(bl/il)) * Math.max(0, Math.floor(bw/iw));
        const layers = Math.max(0, Math.floor(bh/ih));
        const perBox = perLayer * layers;
        const itemVol = il*iw*ih, boxVol = bl*bw*bh;
        const utilization = perBox>0 ? (perBox*itemVol)/boxVol : 0;
        const notes = [];
        if (perBox === 0) { notes.push('Item does not fit — increase box size or rotate'); }
        else if (utilization < 0.5) { notes.push('High void space — consider smaller box or mixed packing'); }
        fitStatus.className = notes.length ? 'status warn' : 'status ok';
        fitStatus.textContent = notes.length ? notes.join('. ') : 'Computed fit';
        fitOut.className='status';
        fitOut.innerHTML = `Per layer: <b>${perLayer}</b>, Layers: <b>${layers}</b>, Items per box: <b>${perBox}</b>, Space used: <b>${(utilization*100).toFixed(0)}%</b>.`;
      }
      [fitItem, fitBox].forEach(el=>el.addEventListener('input', fitCompute));
      [fitItem, fitBox].forEach(el=>el.addEventListener('blur', fitCompute));

    })();
  </script>
</body>
</html>