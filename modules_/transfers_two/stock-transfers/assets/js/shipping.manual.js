/* shipping.manual.js (unified) */
(function(){
  const root = document.querySelector('.stx-outgoing');
  if(!root) return;

  const panel = document.getElementById('manual-panel') || document.getElementById('manual-pane');
  if(!panel) return;

  const list = panel.querySelector('#tracking-items, #manual-tracking-items');
  const addBtn = panel.querySelector('#btn-add-tracking, [data-action="manual-add-tracking"]');
  const saveBtn = panel.querySelector('[data-action="manual-save"]');
  const statusEl = panel.querySelector('#manual-status');
  const countEl = panel.querySelector('#tracking-count');

  function htmlToEl(html){ const div=document.createElement('div'); div.innerHTML=html.trim(); return div.firstChild; }
  function updateCount(){ if(!countEl||!list) return; const n=list.querySelectorAll('.trk-row').length; countEl.textContent = n+ (n===1?' number':' numbers'); }

  function addRow(val=''){
    if(!list) return; 
    const row = htmlToEl('<div class="trk-row input-group input-group-sm mb-1" role="group" aria-label="Tracking row">\
      <div class="input-group-prepend"><span class="input-group-text">URL/ID</span></div>\
      <input type="text" class="form-control trk-input" placeholder="Paste tracking URL or ID" value="">\
      <div class="input-group-append">\
        <button class="btn btn-outline-secondary trk-parse" type="button" title="Parse"><i class="fa fa-magic" aria-hidden="true"></i></button>\
        <button class="btn btn-outline-danger trk-remove" type="button" title="Remove"><i class="fa fa-times" aria-hidden="true"></i></button>\
      </div>\
    </div>');
    row.querySelector('.trk-input').value = val;
    list.appendChild(row);
    updateCount();
  }

  function parseCandidate(raw){
    if(!raw) return '';
    try {
      const u = new URL(raw);
      const segs = u.pathname.split('/').filter(Boolean);
      let cand = segs[segs.length-1] || '';
      if(!cand && u.search){ const p=new URLSearchParams(u.search); for(const [k,v] of p){ if(/[A-Za-z0-9]/.test(v)){ cand = v; break; } } }
      return (cand||raw).replace(/[^A-Za-z0-9\-]/g,'');
    } catch(e){
      return String(raw).replace(/[^A-Za-z0-9\-]/g,'');
    }
  }

  panel.addEventListener('click', (e)=>{
    if(e.target.closest('.trk-remove')){ e.preventDefault(); const row=e.target.closest('.trk-row'); if(row){ row.remove(); updateCount(); } }
    else if(e.target.closest('.trk-parse')){ e.preventDefault(); const row=e.target.closest('.trk-row'); const inp=row?.querySelector('.trk-input'); if(inp){ inp.value = parseCandidate(inp.value); }
    } else if(addBtn && e.target.closest('#'+(addBtn.id||'btn-add-tracking')) || e.target.closest('[data-action="manual-add-tracking"]')){
      e.preventDefault(); addRow('');
    }
  });

  if(addBtn){ addBtn.addEventListener('click', (e)=>{ e.preventDefault(); addRow(''); }); }

  if(saveBtn){
    saveBtn.addEventListener('click', async (e)=>{
      e.preventDefault(); if(!list) return;
      const rows = Array.from(list.querySelectorAll('.trk-row'));
      if(rows.length===0){ if(statusEl) statusEl.textContent='Add at least one tracking number.'; return; }
      const id = root.getAttribute('data-transfer-id') || '';
      for(const r of rows){
        const raw = r.querySelector('.trk-input')?.value || '';
        const tracking = parseCandidate(raw);
        if(!tracking) continue;
        try {
          if(window.STX && STX.fetchJSON){
            await STX.fetchJSON('save_manual_tracking',{ transfer_id:id, tracking_number:tracking, notes:'' });
          } else if(window.STXPrinter){
            await window.STXPrinter._post('save_manual_tracking',{ transfer_id:id, tracking_number:tracking });
          }
        } catch(err){ /* keep going; report last one */ if(statusEl){ statusEl.textContent = err?.response?.error || err.message || 'Failed'; } }
      }
      if(statusEl) statusEl.textContent = 'Saved '+rows.length+' tracking '+(rows.length===1?'number':'numbers')+'.';
    });
  }

  // Auto add one row for UX
  if(list && list.children.length===0){ addRow(''); }
})();
