/*
 * pack.shipping.js
 * Ships labels via queue + manual fallback.
 */
(function(){
  'use strict';

  const root = document.querySelector('.pack-screen');
  const pluginHost = document.getElementById('cis-ship-plugin');
  if (!root || !pluginHost) {
    return;
  }

  const safeParse = (raw) => {
    try { return raw ? JSON.parse(raw) : {}; } catch (e) { return {}; }
  };

  const cfg = Object.assign({
    transferId: 0,
    support: { gss: false, nzpost: false },
    endpoints: {},
    request_id: '',
    csrf: ''
  }, safeParse(pluginHost.dataset.config || '{}'));

  const endpointQueue  = cfg.endpoints.queue_label || '/modules/transfers/stock/ajax/queue.label.php';
  const endpointManual = cfg.endpoints.manual_label || '/modules/transfers/stock/ajax/label.manual.php';

  const render = () => {
    const supportGSS = !!cfg.support?.gss;
    const supportNZP = !!cfg.support?.nzpost;

    pluginHost.innerHTML = [
      '<div class="cis-ship">',
      '  <h4 class="mb-3">Shipping Label</h4>',
      '  <div class="switcher" role="tablist">',
      supportNZP ? '    <button type="button" class="btn-nzp active" data-tab="nzpost">NZ Post eSHIP</button>' : '',
      supportGSS ? '    <button type="button" class="btn-gss '+(supportNZP ? '' : 'active')+'" data-tab="gss">NZ Couriers (GSS)</button>' : '',
      (!supportGSS && !supportNZP) ? '<span class="text-muted small">No carrier credentials on file for this outlet.</span>' : '',
      '  </div>',
      '  <div class="ship-pane" data-pane="nzpost" '+(supportNZP ? '' : 'style="display:none"')+'>',
      nzPostPane(),
      '  </div>',
      '  <div class="ship-pane" data-pane="gss" '+(supportNZP && supportGSS ? 'style="display:none"' : '')+'>',
      gssPane(),
      '  </div>',
      '</div>',
      addressModal()
    ].join('\n');

    wireTabs();
    wireNZPost();
    wireGSS();
    wireManualFallback();
  };

  const nzPostPane = () => (
    [
      '    <div class="card">',
      '      <span class="badge bg-light text-dark mb-2">NZ Post eSHIP</span>',
      '      <div class="stack">',
      '        <div class="mb-2">',
      '          <label>Select Service</label>',
      '          <select id="nzp-service" class="form-select form-select-sm">',
      '            <option value="CPOLTPDL">Courier Pack DLE (Overnight)</option>',
      '            <option value="CPOLTPA5">Courier Pack A5 (Overnight)</option>',
      '            <option value="CPOLTPA4">Courier Pack A4 (Overnight)</option>',
      '            <option value="CPOLP">Courier Pack Parcel (Overnight)</option>',
      '            <option value="CPOLE">Courier Pack Economy Parcel (2–3 Days)</option>',
      '          </select>',
      '        </div>',
      '        <div class="form-check form-switch">',
      '          <input class="form-check-input" type="checkbox" id="nzp-saturday">',
      '          <label class="form-check-label" for="nzp-saturday">Saturday Delivery</label>',
      '        </div>',
      '        <div class="form-check form-switch">',
      '          <input class="form-check-input" type="checkbox" id="nzp-print">',
      '          <label class="form-check-label" for="nzp-print">Print Label Immediately</label>',
      '        </div>',
      '        <div class="mt-2">',
      '          <label>Parcels</label>',
      '          <table class="dim-table" id="nzp-table">',
      '            <thead><tr><th>L (cm)</th><th>W</th><th>H</th><th>Weight (g)</th><th></th></tr></thead>',
      '            <tbody></tbody>',
      '          </table>',
      '          <button type="button" class="btn btn-secondary btn-sm mt-2" data-action="nzp-add">Add Parcel</button>',
      '        </div>',
      '        <div class="mt-2">',
      '          <label>Delivery Instructions</label>',
      '          <textarea class="form-control form-control-sm" rows="2" id="nzp-notes" placeholder="Optional"></textarea>',
      '        </div>',
      '        <button type="button" class="btn btn-primary w-100" id="nzp-create" '+(!cfg.support?.nzpost ? 'disabled' : '')+'>Create NZ Post Label</button>',
      '        <div class="status" id="nzp-status"></div>',
      '      </div>',
      '    </div>'
    ].join('\n')
  );

  const gssPane = () => (
    [
      '    <div class="card">',
      '      <span class="badge bg-light text-dark mb-2">NZ Couriers (GSS)</span>',
      '      <div class="stack">',
      '        <div class="row g-2">',
      '          <div class="col">',
      '            <div class="form-check form-switch">',
      '              <input class="form-check-input" type="checkbox" id="gss-signature" checked>',
      '              <label class="form-check-label" for="gss-signature">Signature Required</label>',
      '            </div>',
      '          </div>',
      '          <div class="col">',
      '            <div class="form-check form-switch">',
      '              <input class="form-check-input" type="checkbox" id="gss-saturday">',
      '              <label class="form-check-label" for="gss-saturday">Saturday</label>',
      '            </div>',
      '          </div>',
      '        </div>',
      '        <div class="mt-2">',
      '          <label>Parcels</label>',
      '          <table class="dim-table" id="gss-table">',
      '            <thead><tr><th>L (cm)</th><th>W</th><th>H</th><th>Weight (g)</th><th>Type</th><th></th></tr></thead>',
      '            <tbody></tbody>',
      '          </table>',
      '          <button type="button" class="btn btn-secondary btn-sm mt-2" data-action="gss-add">Add Parcel</button>',
      '        </div>',
      '        <div class="mt-2">',
      '          <label>Instructions</label>',
      '          <input type="text" class="form-control form-control-sm" id="gss-notes" placeholder="Optional notes">',
      '        </div>',
      '        <button type="button" class="btn btn-primary w-100" id="gss-create" '+(!cfg.support?.gss ? 'disabled' : '')+'>Create GSS Label</button>',
      '        <div class="status" id="gss-status"></div>',
      '      </div>',
      '    </div>'
    ].join('\n')
  );

  const addressModal = () => (
    [
      '<div id="cis-addr-modal" class="cis-hidden">',
      '  <div style="position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:99999;">',
      '    <div style="width:520px; max-width:95vw; background:#fff; border-radius:12px; padding:16px; border:1px solid #e4e7ec;">',
      '      <div style="display:flex; justify-content:space-between; align-items:center;">',
      '        <h5 style="margin:0;">Adjust Address</h5>',
      '        <button type="button" id="cis-addr-close" class="btn btn-secondary" style="padding:6px 10px;">Close</button>',
      '      </div>',
      '      <div class="hr"></div>',
      '      <div class="stack">',
      '        <div><label>Company</label><input type="text" id="cis-addr-company"></div>',
      '        <div><label>Street 1</label><input type="text" id="cis-addr-st1"></div>',
      '        <div><label>Street 2</label><input type="text" id="cis-addr-st2"></div>',
      '        <div class="row-compact">',
      '          <div><label>Suburb</label><input type="text" id="cis-addr-suburb"></div>',
      '          <div><label>Postcode</label><input type="text" id="cis-addr-postcode"></div>',
      '        </div>',
      '        <div class="row-compact">',
      '          <div><label>City</label><input type="text" id="cis-addr-city"></div>',
      '          <div>',
      '            <label>Ticket Type (GSS)</label>',
      '            <select id="cis-addr-ticket">',
      '              <option value="e20">E20</option>',
      '              <option value="e40">E40</option>',
      '              <option value="e60">E60</option>',
      '            </select>',
      '          </div>',
      '        </div>',
      '      </div>',
      '      <div class="hr"></div>',
      '      <div style="display:flex; gap:8px; justify-content:flex-end;">',
      '        <button type="button" id="cis-addr-try" class="btn btn-primary">Attempt Label with Adjusted Address</button>',
      '      </div>',
      '      <div id="cis-addr-status" class="status"></div>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join('\n')
  );

  const wireTabs = () => {
    const buttons = pluginHost.querySelectorAll('.switcher button[data-tab]');
    const panes = pluginHost.querySelectorAll('.ship-pane');
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.getAttribute('data-tab');
        buttons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        panes.forEach(p => {
          p.style.display = (p.getAttribute('data-pane') === tab) ? '' : 'none';
        });
      });
    });
  };

  const nzPostTable = () => pluginHost.querySelector('#nzp-table tbody');
  const gssTable = () => pluginHost.querySelector('#gss-table tbody');

  const addNZPostRow = (preset = {}) => {
    const body = nzPostTable();
    if (!body) return;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="number" min="1" step="1" value="${preset.l || ''}"></td>
      <td><input type="number" min="1" step="1" value="${preset.w || ''}"></td>
      <td><input type="number" min="1" step="1" value="${preset.h || ''}"></td>
      <td><input type="number" min="50" step="10" value="${preset.g || ''}"></td>
      <td><button type="button" class="btn btn-danger btn-sm" data-action="remove">×</button></td>`;
    body.appendChild(tr);
  };

  const addGSSRow = (preset = {}) => {
    const body = gssTable();
    if (!body) return;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="number" min="1" step="1" value="${preset.l || ''}"></td>
      <td><input type="number" min="1" step="1" value="${preset.w || ''}"></td>
      <td><input type="number" min="1" step="1" value="${preset.h || ''}"></td>
      <td><input type="number" min="50" step="10" value="${preset.g || ''}"></td>
      <td>
        <select data-role="ticket" class="form-select form-select-sm">
          <option value="auto">Auto</option>
          <option value="e20">E20</option>
          <option value="e40">E40</option>
          <option value="e60">E60</option>
        </select>
      </td>
      <td><button type="button" class="btn btn-danger btn-sm" data-action="remove">×</button></td>`;
    body.appendChild(tr);
  };

  const collectParcels = (table, opts = {}) => {
    if (!table) {
      return { error: 'Parcel table not available.' };
    }
  const rows = Array.from(table.querySelectorAll('tbody tr'));
    const parcels = [];
    for (const tr of rows) {
      const inputs = tr.querySelectorAll('input');
      if (inputs.length < 4) continue;
      const [l, w, h, g] = Array.from(inputs).map(inp => parseFloat(inp.value || '0'));
      if (!l || !w || !h || !g) return { error: 'All parcel dimensions and weights are required.' };
      const typeSel = tr.querySelector('select[data-role="ticket"]');
      const overrideTicket = typeSel ? (typeSel.value || 'auto') : 'auto';
      parcels.push({
        weight_g: Math.round(g),
        dims: [Math.round(l * 10), Math.round(w * 10), Math.round(h * 10)],
        options: Object.assign({}, opts, { ticket: overrideTicket })
      });
    }
    if (!parcels.length) {
      return { error: 'Add at least one parcel row.' };
    }
    return { parcels };
  };

  const queueLabel = async (carrier, plan, statusNode) => {
    if (!cfg.transferId) throw new Error('Transfer ID missing.');
    const payload = {
      transfer_pk: cfg.transferId,
      carrier,
      parcel_plan: plan,
      idempotency_key: `${cfg.request_id || 'pack'}-${carrier}-${Date.now()}`
    };

    const res = await fetch(endpointQueue, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': cfg.csrf || ''
      },
      body: JSON.stringify(payload)
    });

    const json = await res.json().catch(() => ({}));
    const success = res.ok && json && json.ok !== false;
    if (!success) {
      const err = json?.error?.message || json?.error?.code || json?.error || 'Label queue failed';
      throw new Error(err);
    }

    let message = 'Label request queued. Watch parcels for updates.';
    const printInfo = json?.print;
    if (printInfo && typeof printInfo === 'object') {
      if (printInfo.ok) {
        message += ' Print job dispatched to warehouse printer.';
      } else if (printInfo.error) {
        message += ` Print warning: ${printInfo.error}.`;
      }
    }

    statusNode.textContent = message;
    statusNode.classList.remove('err');
    statusNode.classList.add('ok');
    window.dispatchEvent(new CustomEvent('pack:labels-updated', { detail: { carrier, payload: json } }));
  };

  const wireNZPost = () => {
    if (!cfg.support?.nzpost) {
      addNZPostRow();
      return;
    }
    addNZPostRow();
    const table = nzPostTable();
    table?.addEventListener('click', ev => {
      if (ev.target instanceof HTMLElement && ev.target.dataset.action === 'remove') {
        ev.target.closest('tr')?.remove();
      }
    });
    pluginHost.querySelector('[data-action="nzp-add"]').addEventListener('click', () => addNZPostRow());

    const btn = pluginHost.querySelector('#nzp-create');
    const status = pluginHost.querySelector('#nzp-status');
    btn?.addEventListener('click', async () => {
      if (!btn) return;
      status.textContent = 'Submitting to NZ Post…';
      status.classList.remove('ok', 'err');
      status.classList.add('warn');
      btn.disabled = true;
      try {
        const parcelsResult = collectParcels(nzPostTable().closest('table'));
        if (parcelsResult.error) throw new Error(parcelsResult.error);
        const plan = {
          reference: `Transfer #${cfg.transferId}`,
          options: {
            nzpost: {
              service: pluginHost.querySelector('#nzp-service')?.value || 'CPOLTPDL',
              saturday: pluginHost.querySelector('#nzp-saturday')?.checked || false,
              print_now: pluginHost.querySelector('#nzp-print')?.checked || false,
              notes: pluginHost.querySelector('#nzp-notes')?.value || ''
            }
          },
          parcels: parcelsResult.parcels
        };
        await queueLabel('NZPOST', plan, status);
      } catch (err) {
        status.textContent = err.message || String(err);
        status.classList.remove('ok');
        status.classList.add('err');
      } finally {
        btn.disabled = false;
      }
    });
  };

  const wireGSS = () => {
    if (!cfg.support?.gss) {
      addGSSRow();
      return;
    }
    addGSSRow();
    const tableEl = gssTable()?.closest('table');
    tableEl?.addEventListener('click', ev => {
      if (ev.target instanceof HTMLElement && ev.target.dataset.action === 'remove') {
        ev.target.closest('tr')?.remove();
      }
    });
    pluginHost.querySelector('[data-action="gss-add"]').addEventListener('click', () => addGSSRow());

    const btn = pluginHost.querySelector('#gss-create');
    const status = pluginHost.querySelector('#gss-status');

    btn?.addEventListener('click', async () => {
      status.textContent = 'Submitting to GSS…';
      status.classList.remove('ok');
      status.classList.add('warn');
      btn.disabled = true;
      try {
        const parcelsResult = collectParcels(tableEl, {
          signature: pluginHost.querySelector('#gss-signature')?.checked || false,
          saturday: pluginHost.querySelector('#gss-saturday')?.checked || false
        });
        if (parcelsResult.error) throw new Error(parcelsResult.error);
        const plan = {
          reference: `Transfer #${cfg.transferId}`,
          options: {
            gss: {
              signature: pluginHost.querySelector('#gss-signature')?.checked || false,
              saturday: pluginHost.querySelector('#gss-saturday')?.checked || false,
              instructions: pluginHost.querySelector('#gss-notes')?.value || ''
            }
          },
          parcels: parcelsResult.parcels
        };
        await queueLabel('GSS', plan, status);
      } catch (err) {
        status.textContent = err.message || String(err);
        status.classList.remove('ok');
        status.classList.add('err');
      } finally {
        btn.disabled = false;
      }
    });
  };

  const wireManualFallback = () => {
  const btn = document.getElementById('ship-manual-save');
  if (!btn) return;
    btn.addEventListener('click', async () => {
      const carrier = (document.getElementById('ship-manual-carrier')?.value || 'INTERNAL').toUpperCase();
      const tracking = (document.getElementById('ship-manual-tracking')?.value || '').trim();
      const weightG = parseInt(document.getElementById('ship-manual-weight')?.value || '0', 10) || 0;
      const notes = (document.getElementById('ship-manual-notes')?.value || '').trim();

      if (!cfg.transferId) {
        alert('Transfer ID missing.');
        return;
      }
      if (carrier !== 'INTERNAL' && !tracking) {
        alert('Tracking number is required for external carriers.');
        return;
      }

      btn.disabled = true;
      try {
        const res = await fetch(endpointManual, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': cfg.csrf || ''
          },
          body: JSON.stringify({
            transfer_pk: cfg.transferId,
            carrier,
            service: notes || 'manual_entry',
            tracking,
            weight_g: weightG,
            notes
          })
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.ok === false) {
          throw new Error(json?.error || 'Failed to save manual label');
        }
        alert('Manual tracking saved.');
        document.getElementById('ship-manual-tracking').value = '';
        document.getElementById('ship-manual-weight').value = '';
        document.getElementById('ship-manual-notes').value = '';
        window.dispatchEvent(new CustomEvent('pack:labels-updated', { detail: { carrier, manual: true } }));
      } catch (err) {
        alert(err.message || String(err));
      } finally {
        btn.disabled = false;
      }
    });
  };

  render();
})();
