/* Purchase Orders — Admin dashboard */
(() => {
  const API = '/modules/purchase-orders/ajax/handler.php';
  const csrf = document.querySelector('.po-admin')?.dataset?.csrf ||
               document.querySelector('meta[name="csrf-token"]')?.content ||
               document.querySelector('[name="csrf"]')?.value || '';

  function jreq(action, body = {}) {
    const fd = new FormData();
    fd.append('ajax_action', action);
    fd.append('csrf', csrf);
    Object.entries(body).forEach(([k,v]) => fd.append(k, v));
    return fetch(API, { method: 'POST', body: fd }).then(r => r.json());
  }

  const poFilter = document.querySelector('#po-filter-id');

  // Receipts tab
  function loadReceipts(page = 1) {
    const po = parseInt(poFilter?.value || '0', 10);
    jreq('admin.list_receipts', { po_id: String(po || ''), page: String(page), size: '25' })
      .then(res => {
        const tb = document.querySelector('#tbl-receipts tbody'); if (!tb) return;
        if (!res.success) { tb.innerHTML = `<tr><td colspan="7" class="text-danger">Error</td></tr>`; return; }
        const rows = res.data.rows || [];
        tb.innerHTML = rows.map(r => `
          <tr>
            <td>${r.receipt_id}</td>
            <td>${r.purchase_order_id}</td>
            <td>${r.outlet_id ?? ''}</td>
            <td>${r.is_final ? 'Yes' : 'No'}</td>
            <td>${r.items}</td>
            <td>${r.created_by ?? ''}</td>
            <td>${r.created_at}</td>
          </tr>`).join('') || `<tr><td colspan="7" class="text-muted">No receipts</td></tr>`;
        document.querySelector('#rcp-page').textContent = `page ${res.data.page}`;
        document.querySelector('#receipts-meta').textContent = `${res.data.total} total`;
      });
  }

  // Events tab
  function loadEvents(page = 1) {
    const po = parseInt(poFilter?.value || '0', 10);
    jreq('admin.list_events', { po_id: String(po || ''), page: String(page), size: '25' })
      .then(res => {
        const tb = document.querySelector('#tbl-events tbody'); if (!tb) return;
        if (!res.success) { tb.innerHTML = `<tr><td colspan="6" class="text-danger">Error</td></tr>`; return; }
        const rows = res.data.rows || [];
        tb.innerHTML = rows.map(r => `
          <tr>
            <td>${r.event_id}</td>
            <td>${r.purchase_order_id}</td>
            <td>${r.event_type}</td>
            <td><pre class="small mb-0">${escapeHtml(r.event_data || '')}</pre></td>
            <td>${r.created_by ?? ''}</td>
            <td>${r.created_at}</td>
          </tr>`).join('') || `<tr><td colspan="6" class="text-muted">No events</td></tr>`;
        document.querySelector('#evt-page').textContent = `page ${res.data.page}`;
        document.querySelector('#events-meta').textContent = `${res.data.total} total`;
      });
  }

  // Queue tab
  function loadQueue(page = 1) {
    const status = document.querySelector('#queue-status')?.value || '';
    const outlet = document.querySelector('#queue-outlet')?.value || '';
    jreq('admin.list_inventory_requests', { status, outlet_id: outlet, page: String(page), size: '25' })
      .then(res => {
        const tb = document.querySelector('#tbl-queue tbody'); if (!tb) return;
        if (!res.success) { tb.innerHTML = `<tr><td colspan="8" class="text-danger">Error</td></tr>`; return; }
        const rows = res.data.rows || [];
        tb.innerHTML = rows.map(r => `
          <tr>
            <td>${r.request_id}</td>
            <td>${r.outlet_id}</td>
            <td>${r.product_id}</td>
            <td>${r.delta}</td>
            <td><span class="badge badge-${r.status === 'failed' ? 'danger' : (r.status === 'pending' ? 'warning' : 'success')}">${r.status}</span></td>
            <td>${r.reason}</td>
            <td>${r.requested_at}</td>
            <td class="text-right">
               <button class="btn btn-sm btn-outline-secondary q-retry" data-id="${r.request_id}">Retry</button>
               <button class="btn btn-sm btn-outline-primary q-resend" data-id="${r.request_id}">Force Resend</button>
            </td>
          </tr>`).join('') || `<tr><td colspan="8" class="text-muted">No queue rows</td></tr>`;
        document.querySelector('#q-page').textContent = `page ${res.data.page}`;
        document.querySelector('#queue-meta').textContent = `${res.data.total} total`;
      });
  }

  // bindings
  document.querySelector('#btn-apply-filter')?.addEventListener('click', () => {
    loadReceipts(1); loadEvents(1);
  });
  document.querySelector('#btn-refresh-receipts')?.addEventListener('click', () => loadReceipts());
  document.querySelector('#btn-refresh-events')?.addEventListener('click', () => loadEvents());
  document.querySelector('#btn-refresh-queue')?.addEventListener('click', () => loadQueue());

  document.addEventListener('click', (e) => {
    const t = e.target;
    if (t.classList.contains('q-retry')) {
      jreq('admin.retry_request', { request_id: t.dataset.id })
        .then(res => { if (!res.success) alert('Retry failed'); loadQueue(); });
    }
    if (t.classList.contains('q-resend')) {
      jreq('admin.force_resend', { request_id: t.dataset.id })
        .then(res => { if (!res.success) alert('Force resend failed'); loadQueue(); });
    }
  });

  function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

  // init
  loadReceipts();
  loadEvents();
  loadQueue();
})();
