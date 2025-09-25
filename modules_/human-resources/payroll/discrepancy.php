<?php
declare(strict_types=1);

// ---------- App / Config ----------
require_once($_SERVER['DOCUMENT_ROOT'] . '/app.php'); // composer, sessions, db, auth, helpers

// ---------- Functions (single combined file for this feature) ----------
require_once('functions/wages.php'); // exposes class PayrollPortal

// Harden page responses (robots + cache)
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// AJAX routing
if (!empty($_POST['ajax'])) {
    PayrollPortal::handleAjax(); // emits JSON + exit
}

// Force OTP lock on fresh GET
PayrollPortal::lockOnEntry();

// Prepare UI vars
$user = PayrollPortal::currentUser();
$CSRF = PayrollPortal::csrfToken();

// Templates
template('html-header.php'); // your <head> etc
template('header.php');      // your site header
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css">
<style>
:root{ --brand:#6f42c1; }
body  { background:#f6f7fb; font-size:15px; }
.container-narrow { max-width:980px; }
.card { border:0; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.06); }
.card-header { background:#fff; border-bottom:1px solid #eee; }
.badge-soft { background:#eef2ff; color:#3949ab; }
.btn-brand { background:var(--brand); color:#fff; }
.btn-brand:hover { filter:brightness(.95); color:#fff; }
.table-sm td,.table-sm th{ padding:.5rem; }
.stack-mobile thead{ display:none; }
.stack-mobile tr{ display:block; margin:0 0 .75rem 0; background:#fff; border-radius:8px; }
.stack-mobile td{ display:flex; justify-content:space-between; padding:.5rem .75rem; border-top:0!important; }
.stack-mobile td::before{ content:attr(data-label); font-weight:600; color:#6c757d; margin-right:1rem; }
@media (min-width: 768px){ .stack-mobile thead{display:table-header-group} .stack-mobile tr{display:table-row} .stack-mobile td{display:table-cell} .stack-mobile td::before{display:none} }
.otp-overlay { position:fixed; inset:0; backdrop-filter:saturate(0.9) blur(2px); background:rgba(246,247,251,.98); z-index:1090; display:flex; align-items:center; justify-content:center; }
.otp-box { width:92%; max-width:440px; background:#fff; border-radius:16px; box-shadow:0 12px 34px rgba(0,0,0,.12); padding:22px; }
.otp-box h5{ font-weight:700 }
.code6 { letter-spacing:.45em; font-size:28px; text-align:center; }
.locked { filter:blur(2px); pointer-events:none; user-select:none; }
.snack{ position:fixed; right:14px; bottom:14px; background:#222; color:#fff; padding:8px 12px; border-radius:6px; opacity:0; transform:translateY(8px); transition:.2s; z-index:1080}
.snack.show{ opacity:1; transform:none }
.pill{ border:1px solid #e8eaf6; border-radius:999px; padding:.15rem .6rem; font-size:.8rem; }
.dropzone{ border:2px dashed #ced4da; border-radius:10px; background:#fff; padding:12px; text-align:center; color:#6c757d; }
.kv{ display:flex; justify-content:space-between; gap:.5rem; }
.kv b{ color:#495057 }
</style>

<div class="container container-narrow my-3" id="app">

  <!-- OTP Overlay -->
  <div id="otpOverlay" class="otp-overlay">
    <div class="otp-box">
      <h5>Verify it’s you</h5>
      <p class="text-muted mb-2">We’ve sent a 6‑digit code to <span id="otpDest" class="font-weight-bold"></span>. Enter it to unlock your payslips. This page will auto‑lock after <b>15 minutes</b> of inactivity.</p>
      <div class="form-group">
        <input id="otpCode" maxlength="6" class="form-control code6" placeholder="••••••" inputmode="numeric" autocomplete="one-time-code">
      </div>
      <button id="btnVerify" class="btn btn-brand btn-block mb-2"><i class="fa fa-unlock"></i> Verify & Continue</button>
      <button id="btnResend" class="btn btn-outline-secondary btn-block"><i class="fa fa-paper-plane"></i> Resend code</button>
      <div id="otpMsg" class="small text-danger mt-2"></div>
    </div>
  </div>

  <div id="main" class="locked">
    <!-- Header -->
    <div class="d-flex align-items-center mb-3">
      <div class="mr-auto">
        <h4 class="mb-0">Payslips</h4>
        <div class="text-muted small">Hi <?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?> — secure self‑service</div>
      </div>
      <span class="pill text-muted"><i class="fa fa-shield-alt mr-1"></i> OTP active</span>
    </div>

    <!-- Payslip picker -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="form-row">
          <div class="form-group col-12 col-md-7 mb-2">
            <label class="small mb-1">Select a pay period</label>
            <select id="selPayslip" class="custom-select"></select>
          </div>
          <div class="form-group col-12 col-md-5 mb-2 text-md-right">
            <button id="btnExportPdf" class="btn btn-outline-secondary"><i class="fa fa-file-pdf"></i> Export PDF</button>
          </div>
        </div>
        <div id="psMeta" class="small text-muted"></div>
      </div>
    </div>

    <!-- Payslip lines -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong class="mr-auto">Lines</strong>
        <span class="badge badge-soft" id="netBadge">Net: —</span>
      </div>
      <div class="card-body p-2">
        <div class="table-responsive">
          <table class="table table-sm table-hover stack-mobile" id="tblLines">
            <thead><tr><th>Type</th><th>Name</th><th>Units/Amount</th><th>Value</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Raise issue -->
    <div class="card mb-4">
      <div class="card-header"><strong>Report pay issue / request change</strong></div>
      <div class="card-body">
        <form id="frmIssue">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label class="small">Line</label>
              <select class="custom-select" id="issueLine"></select>
              <small class="form-text text-muted">Pick the specific line you’re referring to.</small>
            </div>
            <div class="form-group col-md-4">
              <label class="small">Change type</label>
              <select class="custom-select" id="changeType">
                <option value="underpaid_time">Underpaid time (hours)</option>
                <option value="overpaid_time">Overpaid time (hours)</option>
                <option value="reimbursement">Reimbursement (amount)</option>
              </select>
            </div>
            <div class="form-group col-md-4">
              <label class="small">Hours / Amount</label>
              <input id="valNum" type="number" step="0.01" min="0" class="form-control" placeholder="e.g. 1.50">
              <small class="form-text text-muted">Enter hours for time, or $ for reimbursement.</small>
            </div>
          </div>

          <div class="kv mb-2"><div class="text-muted">Estimated impact on net pay:</div><b id="netPreview">—</b></div>

          <div class="form-group">
            <label class="small">Explain (optional)</label>
            <textarea id="note" class="form-control" rows="2" placeholder="Short explanation helps us resolve faster"></textarea>
          </div>

          <div class="form-group">
            <label class="small">Evidence (image or PDF)</label>
            <div class="dropzone" id="dz">Drop file here or click to select</div>
            <input type="file" id="evidence" class="d-none" accept=".jpg,.jpeg,.png,.pdf">
            <div class="small mt-2" id="ocrOut"></div>
          </div>

          <div class="form-group form-check">
            <input class="form-check-input" type="checkbox" id="consent">
            <label class="form-check-label small" for="consent">I confirm this is accurate, and consent to the update if approved.</label>
          </div>

          <button class="btn btn-brand" id="btnSubmitIssue" type="submit"><i class="fa fa-paper-plane"></i> Submit</button>
        </form>
      </div>
    </div>

    <?php if (PayrollPortal::isAdmin()): ?>
    <!-- Admin: queue -->
    <div class="card mb-5">
      <div class="card-header d-flex align-items-center">
        <strong>Admin — Pending issues</strong>
        <button id="btnReloadIssues" class="btn btn-sm btn-outline-secondary ml-auto">Refresh</button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0" id="tblIssues">
            <thead><tr><th>#</th><th>Staff</th><th>Line</th><th>Type</th><th>Hours/Amount</th><th>Anomalies</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="small text-muted p-2">Note: Xero only allows payslip edits in <b>Draft</b> pay runs. If posted, revert to draft in Xero first.</div>
      </div>
    </div>
    <?php endif; ?>

  </div> <!-- /main -->

</div>

<div class="snack" id="snack"></div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script>
(function(){
  const CSRF = '<?= htmlspecialchars($CSRF) ?>';
  let current  = { payRunId:'', payslipId:'', periodEnd:'', net:0.0, lines:[] };
  let evidence = { hash:'', ocr:null };

  // ---------- Snack ----------
  function snack(s){ const el=document.getElementById('snack'); el.textContent=s; el.classList.add('show'); setTimeout(()=>el.classList.remove('show'),1800); }

  // ---------- OTP ----------
  function lockUI(){ $('#main').addClass('locked'); $('#otpOverlay').show(); }
  function unlockUI(){ $('#main').removeClass('locked'); $('#otpOverlay').hide(); }
  function otpRequest(){
    $.post('', {ajax:'otp_request'}, function(r){
      if(!r||!r.ok){ $('#otpMsg').text('Could not send code.'); return; }
      $('#otpDest').text(r.to);
    }, 'json');
  }
  $('#btnResend').on('click', otpRequest);
  $('#btnVerify').on('click', function(){
    const code=$('#otpCode').val().replace(/\D+/g,'');
    $.post('', {ajax:'otp_verify', code}, function(r){
      if(r && r.ok){ unlockUI(); initAfterUnlock(); snack('Unlocked'); } else { $('#otpMsg').text('Invalid code'); }
    }, 'json');
  });
  otpRequest();

  // Keepalive + auto-lock feedback
  setInterval(function(){
    $.post('', {ajax:'ping', csrf:CSRF}, function(){}, 'json').fail(function(xhr){
      if(xhr.status===401){ lockUI(); $('#otpMsg').text('Session locked. Please re‑verify.'); }
    });
  }, 60000);

  // ---------- Helpers ----------
  function money(n){ if(n===null||n===undefined||isNaN(n)) return '—'; return '$'+Number(n).toFixed(2); }

  // ---------- List payslips ----------
  function loadPayslips(){
    $.post('', {ajax:'list_payslips', csrf:CSRF}, function(r){
      if(!r||!r.ok){ snack('Failed to load payslips'); return; }
      const sel=$('#selPayslip').empty();
      if(!r.payslips.length){ sel.append('<option value="">No payslips found</option>'); return; }
      r.payslips.forEach(ps=>{
        const label = (ps.periodStart||'?')+' → '+(ps.periodEnd||'?')+' · '+(ps.status||'');
        sel.append(`<option value="${ps.payslipId}" data-run="${ps.payRunId}" data-end="${ps.periodEnd||''}">${label}</option>`);
      });
      sel.trigger('change');
    }, 'json');
  }
  $('#selPayslip').on('change', function(){
    const opt=this.options[this.selectedIndex];
    const payslipId=this.value; const payRunId=opt.getAttribute('data-run'); const periodEnd=opt.getAttribute('data-end');
    current={payRunId, payslipId, periodEnd, net:0, lines:[]};
    if(!payslipId){ $('#tblLines tbody').empty(); $('#netBadge').text('Net: —'); return; }
    $.post('', {ajax:'payslip_detail', csrf:CSRF, payslipId}, function(r){
      if(!r||!r.ok){ snack('Failed to fetch lines'); return; }
      current.net = Number(r.net||0);
      $('#netBadge').text('Net: '+money(r.net));
      const tb=$('#tblLines tbody').empty();
      const sel=$('#issueLine').empty();
      (r.earnings||[]).forEach(e=>{
        tb.append(`<tr><td data-label="Type">Earnings</td><td data-label="Name">${e.name||''}</td><td data-label="Units/Amount">${Number(e.numberOfUnits||0).toFixed(2)}</td><td data-label="Value">${money(e.amount||0)}</td></tr>`);
        sel.append(`<option value="earnings:${e.id}">Earnings · ${e.name||''}</option>`);
        current.lines.push({type:'earnings', id:String(e.id), ratePerUnit:e.ratePerUnit||0});
      });
      (r.reimbursements||[]).forEach(ri=>{
        tb.append(`<tr><td data-label="Type">Reimb.</td><td data-label="Name">${ri.name||''}</td><td data-label="Units/Amount">—</td><td data-label="Value">${money(ri.amount||0)}</td></tr>`);
        sel.append(`<option value="reimbursement:${ri.id}">Reimb · ${ri.name||''}</option>`);
        current.lines.push({type:'reimbursement', id:String(ri.id)});
      });
      $('#psMeta').text(`Gross ${money(r.gross)} · Tax ${money(r.tax)} · Net ${money(r.net)}`);
      netPreview();
    }, 'json');
  });

  // ---------- Net preview ----------
  function netPreview(){
    const ct=$('#changeType').val();
    const v = Number($('#valNum').val()||0);
    let delta=0;
    if(ct==='reimbursement'){ delta = v; }
    else {
      const parts = ($('#issueLine').val()||'').split(':');
      const found = current.lines.find(x=>x.type===parts[0] && String(x.id)===String(parts[1]));
      const rate  = found && found.ratePerUnit ? Number(found.ratePerUnit) : 0;
      delta = (ct==='underpaid_time') ? (v*rate) : (-v*rate);
    }
    $('#netPreview').text( delta===0 ? '—' : (delta>0?'+':'−')+money(Math.abs(delta)) );
  }
  $('#changeType, #valNum, #issueLine').on('input change', netPreview);

  // ---------- Upload + OCR ----------
  $('#dz').on('click', ()=>$('#evidence').click());
  $('#dz').on('dragover', function(e){ e.preventDefault(); $(this).addClass('border-primary'); });
  $('#dz').on('dragleave', function(){ $(this).removeClass('border-primary'); });
  $('#dz').on('drop', function(e){ e.preventDefault(); $('#evidence')[0].files = e.originalEvent.dataTransfer.files; $('#evidence').trigger('change'); });
  $('#evidence').on('change', function(){
    const f=this.files[0]; if(!f) return;
    const fd=new FormData(); fd.append('ajax','upload_evidence'); fd.append('csrf',CSRF); fd.append('file', f);
    $('#ocrOut').text('Uploading & reading…');
    $.ajax({url:'', method:'POST', data:fd, processData:false, contentType:false, dataType:'json'})
      .done(function(r){
        if(!r||!r.ok){ $('#ocrOut').text('Upload failed'); return; }
        evidence.hash=r.file.hash; evidence.ocr=r.ocr||null;
        $('#ocrOut').html('<span class="text-success">Evidence saved</span>' + (evidence.ocr? '<pre class="mt-2 small">'+$('<div/>').text(JSON.stringify(evidence.ocr,null,2)).html()+'</pre>':''));
      })
      .fail(()=>$('#ocrOut').text('Upload failed'));
  });

  // ---------- Submit issue ----------
  $('#frmIssue').on('submit', function(ev){
    ev.preventDefault();
    const pick=$('#issueLine').val()||''; if(!pick){ snack('Pick a line'); return; }
    if(!$('#consent').is(':checked')){ snack('Please tick consent'); return; }
    const [lineType,lineId]=pick.split(':');
    const ct=$('#changeType').val(); const val=Number($('#valNum').val()||0);
    const body={
      ajax:'submit_issue', csrf:CSRF,
      payslipId: current.payslipId, payRunId: current.payRunId, periodEnd: current.periodEnd,
      lineType, lineId, changeType:ct,
      hours: (ct!=='reimbursement'? val : 0),
      amount: (ct==='reimbursement'? val : 0),
      note: $('#note').val()||'',
      consent: true,
      evidenceHash: evidence.hash||'',
      ocrJson: evidence.ocr? JSON.stringify(evidence.ocr) : null
    };
    $.post('', body, function(r){
      if(!r||!r.ok){ snack('Submit failed'); return; }
      snack('Submitted'); $('#note').val(''); $('#valNum').val(''); $('#consent').prop('checked',false); $('#ocrOut').empty();
      if(r.anomalies && r.anomalies.length){ alert('Heads up:\n'+r.anomalies.map(a=>a.type).join('\n')); }
    }, 'json');
  });

  // ---------- Export PDF ----------
  $('#btnExportPdf').on('click', function(){
    const html = `
      <html><head><meta charset="utf-8"><style>
      body{font-family:Arial,Helvetica,sans-serif;font-size:12px}
      h3{margin:0 0 8px 0} table{border-collapse:collapse;width:100%}
      th,td{border:1px solid #ddd;padding:6px}
      .kv{display:flex;gap:1rem} .kv div{flex:1}
      </style></head><body>
      <h3>Payslip — ${$('#selPayslip option:selected').text()}</h3>
      <div class="kv"><div>Employee: <?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div><div>Date: <?= date('Y-m-d') ?></div></div>
      ${document.getElementById('tblLines').outerHTML}
      <p>Gross/Tax/Net: ${document.getElementById('psMeta').textContent}</p>
      </body></html>`;
    $.post('', {ajax:'export_pdf', csrf:CSRF, html}, function(r){
      if(r && r.ok && r.file){ window.location = r.file; } else { snack('PDF failed'); }
    }, 'json');
  });

  // ---------- Admin ----------
  function loadIssues(){
    $.post('', {ajax:'list_issues', csrf:CSRF}, function(r){
      if(!r||!r.ok) return;
      const tb=$('#tblIssues tbody').empty();
      r.rows.forEach(i=>{
        const an = (i.anomaly_json? JSON.parse(i.anomaly_json) : []).map(x=>x.type).join(', ');
        tb.append(`<tr>
          <td>${i.id}</td>
          <td>${i.staff_id}</td>
          <td>${i.line_type}:${i.line_id}</td>
          <td>${i.change_type}</td>
          <td>${i.hours||i.amount||''}</td>
          <td>${an||'—'}</td>
          <td class="text-right"><button class="btn btn-sm btn-outline-success btnApply" data-id="${i.id}">Apply now</button></td>
        </tr>`);
      });
    }, 'json');
  }
  $(document).on('click','.btnApply', function(){
    const id=this.getAttribute('data-id');
    if(!confirm('Apply this change to Xero now?')) return;
    $.post('', {ajax:'apply_now', csrf:CSRF, issueId:id}, function(r){
      if(r && r.ok){ snack('Applied'); loadIssues(); } else { alert('Failed: '+(r && r.error || '')); }
    }, 'json');
  });
  $('#btnReloadIssues').on('click', loadIssues);

  // ---------- Initialize ----------
  function initAfterUnlock(){
    loadPayslips();
    <?php if (PayrollPortal::isAdmin()): ?> loadIssues(); <?php endif; ?>
  }
})();
</script>

<?php
// You may also call template('footer.php') here if you use a footer template.
