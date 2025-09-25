/* shipping.np.js (unified) */
(function(){
  const root = document.querySelector('.stx-outgoing');
  if(!root) return;
  // Helper to get transfer id within nzpost pane context
  function trId(){ return root.getAttribute('data-transfer-id') || root.querySelector('input[name="transferID"]').value; }
  function collectPackages(){
    const rows = root.querySelectorAll('#nzpost-packages-table tbody tr');
    const list=[];
    rows.forEach(tr=>{
      const l = parseFloat(tr.querySelector('[data-field="length"]')?.value||'0');
      const w = parseFloat(tr.querySelector('[data-field="width"]')?.value||'0');
      const h = parseFloat(tr.querySelector('[data-field="height"]')?.value||'0');
      const kg = parseFloat(tr.querySelector('[data-field="weight"]')?.value||'0');
      const desc = (tr.querySelector('[data-field="desc"]')?.value||'').trim();
      list.push({ length:l, width:w, height:h, weight:kg, description:desc });
    });
    return list;
  }
  root.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-action="nzpost-create"]');
    if(!btn) return;
    e.preventDefault();
    const id = trId();
    const pkgs = collectPackages();
    const service = (document.getElementById('nzpost-service-type')?.value||'');
    try{ const r = await STX.fetchJSON('create_label_nzpost',{transfer_id:id, packages: JSON.stringify(pkgs), service_code: service});
      const d = r && r.data ? r.data : {};
      STX.emit('label:created', d);
      STX.emit('toast', {type:'success', text:'NZ Post label created'});
    }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
  });

  root.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-action="nzpost-order"]');
    if(!btn) return; e.preventDefault();
    const id = trId();
    const pkgs = collectPackages();
    const signature = !!document.getElementById('nzpost-signature')?.checked;
    const saturday = !!document.getElementById('nzpost-saturday')?.checked;
    const instructions = (document.getElementById('nzpost-instructions')?.value||'');
    const attention = (document.getElementById('nzpost-attention')?.value||'');
    const service = (document.getElementById('nzpost-service-type')?.value||'');
    try{
      const r = await STX.fetchJSON('create_order_nzpost', { transfer_id: id, packages: JSON.stringify(pkgs), service_code: service, signature: signature?1:0, saturday: saturday?1:0, instructions: instructions, attention: attention });
      const d = r?.data || {};
      // If address candidates provided, present modal for selection
      const candidates = d.raw && Array.isArray(d.raw.address_candidates) ? d.raw.address_candidates : [];
      if (candidates.length) {
        const list = document.getElementById('nzpost-address-candidates');
        if (list) {
          list.innerHTML = '';
          candidates.forEach((c, idx)=>{
            const item = document.createElement('label');
            item.className = 'list-group-item list-group-item-action';
            const pretty = `${c.name||''} — ${c.street||''}, ${c.suburb||''}, ${c.city||''} ${c.post_code||''}`;
            item.innerHTML = `<input type="radio" name="nzpostCandidate" value="${idx}" class="mr-2"> ${pretty}`;
            item.dataset.payload = JSON.stringify(c);
            list.appendChild(item);
          });
          $('#nzpostAddressModal').modal('show');
          const applyBtn = document.getElementById('nzpost-address-apply');
          if (applyBtn) {
            applyBtn.onclick = async () => {
              const chosen = list.querySelector('input[name="nzpostCandidate"]:checked');
              if (!chosen) { STX.emit('toast',{type:'warning', text:'Please select an address'}); return; }
              const payload = JSON.parse(chosen.closest('label').dataset.payload||'{}');
              try{
                const r2 = await STX.fetchJSON('create_order_nzpost', { transfer_id: id, packages: JSON.stringify(pkgs), service_code: service, signature: signature?1:0, saturday: saturday?1:0, instructions: instructions, attention: attention, destination_override: JSON.stringify(payload) });
                const d2 = r2?.data || {};
                STX.emit('toast', {type:'success', text:'Order confirmed: '+(d2.order_number||'')});
                $('#nzpostAddressModal').modal('hide');
                const printBtn = document.getElementById('nzpost-create-btn');
                if (printBtn) { printBtn.classList.add('btn-glow'); setTimeout(()=>printBtn.classList.remove('btn-glow'), 1200); }
              }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
            };
          }
        }
      } else {
        STX.emit('toast', {type:'success', text:'Order created: '+(d.order_number||'')});
        const printBtn = document.getElementById('nzpost-create-btn');
        if (printBtn) { printBtn.classList.add('btn-glow'); setTimeout(()=>printBtn.classList.remove('btn-glow'), 1200); }
      }
      // Optionally enable/flash the print button
    }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
  });
})();
