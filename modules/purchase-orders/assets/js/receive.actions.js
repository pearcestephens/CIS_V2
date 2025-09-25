/* Purchase Orders — Receive: evidence upload + QR + minor UX */
(() => {
  const API = '/modules/purchase-orders/ajax/handler.php';
  const csrf = (document.querySelector('meta[name="csrf-token"]')?.content ||
                document.querySelector('[name="csrf"]')?.value || '').trim();
  const root = document.querySelector('.po-receive');
  if (!root) return;
  const PO_ID = Number(root.dataset.poId || 0);

  const listEl = document.querySelector('#evidence-list tbody') ||
                 document.querySelector('#evidence-list');

  function api(action, body) {
    const fd = new FormData();
    fd.append('ajax_action', action);
    fd.append('csrf', csrf);
    Object.entries(body || {}).forEach(([k, v]) => fd.append(k, v));
    return fetch(API, { method: 'POST', body: fd }).then(r => r.json());
  }

  function listEvidence() {
    api('list_evidence', { po_id: PO_ID }).then(res => {
      if (!res.success) return;
      const rows = res.data?.rows || res.data?.pagination ? res.data.rows : [];
      const out = rows.map(r =>
        `<tr><td>${r.id}</td><td><a href="${r.file_path}" target="_blank" rel="noopener">${r.file_path}</a></td><td>${r.evidence_type}</td><td>${r.uploaded_by ?? ''}</td><td>${r.uploaded_at}</td></tr>`
      ).join('');
      if (listEl) listEl.innerHTML = out || `<tr><td colspan="5" class="text-muted">No evidence yet</td></tr>`;
    });
  }

  // Upload form (if present on page)
  const form = document.querySelector('#evidence-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const file = document.querySelector('#ev-file')?.files?.[0];
      const type = document.querySelector('#ev-type')?.value || 'delivery';
      const desc = document.querySelector('#ev-desc')?.value || '';
      if (!file) return alert('Choose a file');
      const fd = new FormData();
      fd.append('ajax_action', 'upload_evidence');
      fd.append('csrf', csrf);
      fd.append('po_id', String(PO_ID));
      fd.append('evidence_type', type);
      fd.append('description', desc);
      fd.append('file', file);
      fetch(API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (!res.success) return alert(res.error?.message || 'Upload failed');
          listEvidence();
        });
    });

    document.querySelector('#btn-refresh-evidence')?.addEventListener('click', listEvidence);
  }

  listEvidence();
})();
