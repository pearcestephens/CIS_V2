<?php
declare(strict_types=1);
/** admin/dashboard.php — __MODULE_NAME__ admin view */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <title>__MODULE_NAME__ Admin — CIS</title>
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/assets/css/module.css">
</head>
<body>
  <div class="container mt-3" data-module="__MODULE_SLUG__">
    <div class="__MODULE_SLUG__-card">
      <div class="__MODULE_SLUG__-header">
        <h3 class="mb-0">__MODULE_NAME__ — Admin</h3>
        <div class="__MODULE_SLUG__-actions">
          <button class="btn btn-sm btn-outline-secondary" data-action="ping">Ping</button>
        </div>
      </div>
      <p class="text-muted">Starter admin dashboard. Add panels and hook AJAX actions.</p>
    </div>

    <div class="card mt-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <strong>AI Helper</strong>
        <a class="btn btn-sm btn-outline-secondary" href="https://staff.vapeshed.co.nz/modules/_shared/diagnostics.php">Diagnostics</a>
      </div>
      <div class="card-body">
        <p class="mb-2 text-muted">Generate module content (Index/Admin views, README notes, JS/CSS helpers). Requires OpenAI key configured server-side.</p>
        <form id="ai-form" class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="ai_goal">Goal/Purpose</label>
            <input class="form-control" id="ai_goal" name="goal" placeholder="e.g., Speed up daily stock adjustments">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="ai_tone">Tone</label>
            <input class="form-control" id="ai_tone" name="tone" placeholder="professional, concise">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="ai_entities">Entities</label>
            <input class="form-control" id="ai_entities" name="entities" placeholder="e.g., adjustments, items">
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="button" class="btn btn-primary" id="ai-generate">Generate with AI</button>
            <button type="button" class="btn btn-outline-success" id="ai-save" disabled>Save drafts</button>
          </div>
        </form>
        <div id="ai-output" class="mt-3" hidden>
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ai-index" type="button">Index View</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ai-admin" type="button">Admin View</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ai-readme" type="button">README</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ai-js" type="button">JS</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ai-css" type="button">CSS</button></li>
          </ul>
          <div class="tab-content border p-2 border-top-0">
            <div id="ai-index" class="tab-pane fade show active" role="tabpanel"><pre class="small" id="ai-index-pre"></pre></div>
            <div id="ai-admin" class="tab-pane fade" role="tabpanel"><pre class="small" id="ai-admin-pre"></pre></div>
            <div id="ai-readme" class="tab-pane fade" role="tabpanel"><pre class="small" id="ai-readme-pre"></pre></div>
            <div id="ai-js" class="tab-pane fade" role="tabpanel"><pre class="small" id="ai-js-pre"></pre></div>
            <div id="ai-css" class="tab-pane fade" role="tabpanel"><pre class="small" id="ai-css-pre"></pre></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/assets/js/module.js" defer></script>
  <script>
  (function(){
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const handler = 'https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/ajax/handler.php';
    const out = document.getElementById('ai-output');
    const btnGen = document.getElementById('ai-generate');
    const btnSave = document.getElementById('ai-save');
    function post(action, data){
      const fd = new FormData(); fd.append('action', action); Object.entries(data||{}).forEach(([k,v])=>fd.append(k,v));
      return fetch(handler, { method:'POST', headers:{'X-CSRF-Token': csrf}, body: fd }).then(r=>r.json());
    }
    btnGen?.addEventListener('click', async ()=>{
      btnGen.disabled = true; btnSave.disabled = true;
      const goal = document.getElementById('ai_goal').value.trim();
      const tone = document.getElementById('ai_tone').value.trim();
      const entities = document.getElementById('ai_entities').value.trim();
      const res = await post('__MODULE_SLUG__.ai_generate', { goal, tone, entities });
      btnGen.disabled = false;
      if(!res.success){ alert(res.error?.message||'AI failed'); return; }
      out.hidden = false; btnSave.disabled = false;
      document.getElementById('ai-index-pre').textContent = res.data.index_view||'';
      document.getElementById('ai-admin-pre').textContent = res.data.admin_view||'';
      document.getElementById('ai-readme-pre').textContent = res.data.readme||'';
      document.getElementById('ai-js-pre').textContent = res.data.js||'';
      document.getElementById('ai-css-pre').textContent = res.data.css||'';
    });
    btnSave?.addEventListener('click', async ()=>{
      btnSave.disabled = true;
      const payload = {
        index_view: document.getElementById('ai-index-pre').textContent,
        admin_view: document.getElementById('ai-admin-pre').textContent,
        readme: document.getElementById('ai-readme-pre').textContent,
        js: document.getElementById('ai-js-pre').textContent,
        css: document.getElementById('ai-css-pre').textContent,
      };
      const res = await post('__MODULE_SLUG__.ai_save_drafts', payload);
      btnSave.disabled = false;
      if(!res.success){ alert(res.error?.message||'Save failed'); return; }
      alert('Drafts saved: ' + (res.data?.dir || '')); 
    });
  })();
  </script>
</body>
</html>
