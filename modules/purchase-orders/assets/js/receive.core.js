/* Purchase Orders — Receive: core bootstrap + data load */
(() => {
  const API = '/modules/purchase-orders/ajax/handler.php';
  const csrf = (document.querySelector('meta[name="csrf-token"]')?.content ||
                document.querySelector('[name="csrf"]')?.value || '').trim();
  const PO_ID = Number(document.querySelector('.po-receive')?.dataset?.poId || 0);

  if (!PO_ID || !csrf) {
    console.warn('[PO] missing po_id or csrf');
    return;
  }

  const el = {
    tableBody: document.querySelector('#receiving_table tbody'),
    totalExpected: document.querySelector('#total_expected'),
    totalReceived: document.querySelector('#total_received_display'),
    itemsReceived: document.querySelector('#items_received'),
    totalItems: document.querySelector('#total_items'),
    progressBar: document.querySelector('#progress_bar'),
    progressText: document.querySelector('#progress_text'),
    btnQuickSave: document.querySelector('#btn-quick-save'),
    btnPartial: document.querySelector('#btn-submit-partial'),
    btnFinal: document.querySelector('#btn-submit-final'),
    barcodeInput: document.querySelector('#barcode_input'),
  };

  function jreq(action, body = {}) {
    const fd = new FormData();
    fd.append('ajax_action', action);
    fd.append('csrf', csrf);
    Object.entries(body).forEach(([k, v]) => fd.append(k, v));
    return fetch(API, { method: 'POST', body: fd })
      .then(r => r.json());
  }

  function render(items) {
    el.tableBody.innerHTML = '';
    let totE = 0, totR = 0, receivedRows = 0;
    items.forEach(row => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="width:60px;text-align:center;">
          ${row.image ? `<img src="${row.image}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">` : ''}
        </td>
        <td>
          <strong>${escapeHtml(row.name)}</strong><br>
          <small class="text-muted">SKU: ${escapeHtml(row.sku || '')}</small>
        </td>
        <td><span class="badge badge-info">${row.expected}</span></td>
        <td>
          <input type="number" class="form-control form-control-sm rx-recv" min="0"
                 value="${row.received || 0}" data-pid="${row.product_id}" style="width:80px">
        </td>
        <td>
          <span class="badge ${row.status === 'Complete' ? 'badge-success' : (row.status === 'Partial' ? 'badge-warning' : 'badge-secondary')}">
            ${row.status}
          </span>
        </td>
        <td>
          <button class="btn btn-sm btn-outline-warning rx-undo" data-pid="${row.product_id}">Undo</button>
        </td>
      `;
      el.tableBody.appendChild(tr);
      totE += Number(row.expected || 0);
      totR += Number(row.received || 0);
      if (Number(row.received || 0) > 0) receivedRows++;
    });

    el.totalExpected.textContent = String(totE);
    el.totalReceived.textContent = String(totR);
    el.itemsReceived.textContent = String(receivedRows);
    el.totalItems.textContent = String(items.length);

    const pct = items.length ? Math.round((receivedRows / items.length) * 100) : 0;
    el.progressBar.style.width = pct + '%';
    el.progressText.textContent = pct + '% Complete';
  }

  function loadPO() {
    jreq('get_po', { po_id: PO_ID }).then(res => {
      if (!res.success) throw new Error(res.error || 'load failed');
      const { items } = res.data || {};
      render(items || []);
    }).catch((e) => console.error('[PO] load error', e));
  }

  function saveLine(pid, qty) {
    return jreq('save_progress', {
      po_id: PO_ID,
      product_id: pid,
      qty_received: String(qty),
      live: '1'
    });
  }

  // events
  document.addEventListener('input', (ev) => {
    const t = ev.target;
    if (t.classList.contains('rx-recv')) {
      const pid = t.getAttribute('data-pid');
      const qty = Math.max(0, parseInt(t.value || '0', 10) || 0);
      // Debounce: small delay
      clearTimeout(t._deb);
      t._deb = setTimeout(() => {
        saveLine(pid, qty).then(res => {
          if (!res.success) alert(res.error?.message || 'Save failed');
          // update progress quietly
          loadPO();
        });
      }, 250);
    }
  });

  document.addEventListener('click', (ev) => {
    const t = ev.target;
    if (t.classList.contains('rx-undo')) {
      const pid = t.getAttribute('data-pid');
      jreq('undo_item', { po_id: PO_ID, product_id: pid, live: '1' })
        .then(res => {
          if (!res.success) return alert(res.error?.message || 'Undo failed');
          loadPO();
        });
    }
  });

  el.btnPartial?.addEventListener('click', () => {
    if (!confirm('Submit partial receipt?')) return;
    jreq('submit_partial', { po_id: PO_ID, live: '1' })
      .then(res => {
        if (!res.success) return alert(res.error?.message || 'Partial failed');
        alert('Partial submitted');
        loadPO();
      });
  });

  el.btnFinal?.addEventListener('click', () => {
    if (!confirm('Submit final receipt and complete PO?')) return;
    jreq('submit_final', { po_id: PO_ID, live: '1' })
      .then(res => {
        if (!res.success) return alert(res.error?.message || 'Final failed');
        alert('PO completed');
        location.reload();
      });
  });

  // Simple scanner support: Enter on barcode field adds +1 to first matching row by SKU
  el.barcodeInput?.addEventListener('keypress', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = (el.barcodeInput.value || '').trim().toLowerCase();
    if (!code) return;
    const rows = el.tableBody.querySelectorAll('tr');
    for (const tr of rows) {
      const sku = (tr.querySelector('small.text-muted')?.textContent || '').toLowerCase();
      if (sku.includes(code)) {
        const ip = tr.querySelector('input.rx-recv');
        ip.value = String((parseInt(ip.value || '0', 10) || 0) + 1);
        ip.dispatchEvent(new Event('input', { bubbles: true }));
        break;
      }
    }
    el.barcodeInput.value = '';
  });

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // init
  loadPO();
})();
