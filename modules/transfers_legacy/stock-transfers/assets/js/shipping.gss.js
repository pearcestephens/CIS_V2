/* shipping.gss.js (unified, multi-box) */
(function(){
  const root = document.querySelector('.stx-outgoing');
  if(!root) return;

  function trId(){
    return root.getAttribute('data-transfer-id') ||
      (root.querySelector('input[name="transferID"], #transferID')?.value) || '';
  }

  function collectPackages(){
    const rows = root.querySelectorAll('#gss-packages-table tbody tr');
    const list = [];
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

  function addRowFromPreset(l,w,h,kg,desc){
    const tbody = root.querySelector('#gss-packages-table tbody');
    if(!tbody) return;
    const tr = document.createElement('tr');
    tr.innerHTML = '<td>'+
      '<div class="input-group input-group-sm">'
      + '<input type="number" class="form-control" data-field="length" placeholder="L" min="1" max="120" value="'+(l||'')+'">'
      + '<div class="input-group-append input-group-prepend"><span class="input-group-text">×</span></div>'
      + '<input type="number" class="form-control" data-field="width" placeholder="W" min="1" max="120" value="'+(w||'')+'">'
      + '<div class="input-group-append input-group-prepend"><span class="input-group-text">×</span></div>'
      + '<input type="number" class="form-control" data-field="height" placeholder="H" min="1" max="120" value="'+(h||'')+'">'
      + '</div>'+
    '</td>'+
    '<td>'+
      '<div class="input-group input-group-sm">'
      + '<input type="number" class="form-control" data-field="weight" placeholder="2.5" step="0.1" min="0.1" max="30" value="'+(kg||'')+'">'
      + '<div class="input-group-append"><span class="input-group-text">kg</span></div>'
      + '</div>'+
    '</td>'+
    '<td><input type="text" class="form-control form-control-sm" data-field="desc" placeholder="Box contents" value="'+(desc||'')+'"></td>'+
    '<td class="text-muted text-center">—</td>'+
    '<td class="text-center"><button type="button" class="btn btn-link text-danger p-0" data-action="gss-remove-package" aria-label="Remove"><i class="fa fa-trash"></i></button></td>';
    tbody.appendChild(tr);
    updateTotals();
  }

  function updateTotals(){
    try{
      const pkgs = collectPackages();
      const count = pkgs.length;
      const totalW = pkgs.reduce((s,p)=> s + (parseFloat(p.weight||0)||0), 0).toFixed(1);
      const elCount = document.getElementById('gss-package-count');
      const elWeight = document.getElementById('gss-total-weight');
      if (elCount) elCount.textContent = (count||0) + ' packages';
      if (elWeight) elWeight.textContent = (totalW||'0.0') + 'kg total';
    }catch(_){ }
  }

  root.addEventListener('click', (e)=>{
    const add = e.target.closest('[data-action="gss-add-package"]');
    if(add){
      e.preventDefault();
      const l = parseFloat(document.getElementById('gss-length')?.value||'');
      const w = parseFloat(document.getElementById('gss-width')?.value||'');
      const h = parseFloat(document.getElementById('gss-height')?.value||'');
      const kg = parseFloat(document.getElementById('gss-weight')?.value||'');
      addRowFromPreset(isFinite(l)?l:'', isFinite(w)?w:'', isFinite(h)?h:'', isFinite(kg)?kg:'', '');
      return;
    }
    const preset = e.target.closest('[data-action="gss-preset"]');
    if(preset){
      e.preventDefault();
      const dim = (preset.getAttribute('data-dim')||'').split(',').map(x=>parseFloat(x));
      addRowFromPreset(dim[0]||'', dim[1]||'', dim[2]||'', dim[3]||'', preset.textContent.trim());
      return;
    }
    const remove = e.target.closest('[data-action="gss-remove-package"]');
    if(remove){ e.preventDefault(); const row = remove.closest('tr'); if(row){ row.parentNode.removeChild(row); updateTotals(); } return; }
  });

  root.addEventListener('input', (e)=>{
    if(e.target.closest('#gss-packages-table')) updateTotals();
  });

  // Create label/booking from full tab pane
  root.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-action="gss-create-booking"]');
    if(!btn) return; e.preventDefault();
    const id = trId();
    let pkgs = collectPackages();
    if(pkgs.length === 0){
      // If no rows, fall back to single inputs as a convenience
      const l = parseFloat(document.getElementById('gss-length')?.value||'0');
      const w = parseFloat(document.getElementById('gss-width')?.value||'0');
      const h = parseFloat(document.getElementById('gss-height')?.value||'0');
      const kg = parseFloat(document.getElementById('gss-weight')?.value||'0');
      if(l && w && h && kg) pkgs = [{ length:l, width:w, height:h, weight:kg, description:'Package' }];
    }
    const service = (document.getElementById('gss-service-type')?.value||'');
    try{
      const r = await STX.fetchJSON('create_label_gss', { transfer_id: id, packages: JSON.stringify(pkgs), service: service });
      const d = r && r.data ? r.data : {};
      STX.emit('label:created', d);
      STX.emit('toast', {type:'success', text:'GSS docket created'});
    }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
  });

  // Minimal support for compact panel button (maps package type -> one parcel)
  root.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-action="gss-create-label"]');
    if(!btn) return; e.preventDefault();
    const id = trId();
    const type = (document.getElementById('gss-package-type')?.value||'').toLowerCase();
    const map = {
      'satchel3kg': { length:35, width:25, height:5, weight:3.0 },
      'satchel5kg': { length:35, width:25, height:5, weight:5.0 },
      'box3kg':     { length:30, width:20, height:15, weight:2.5 },
      'box5kg':     { length:35, width:25, height:20, weight:5.0 },
      'box10kg':    { length:40, width:30, height:25, weight:8.0 }
    };
    const pkg = map[type] || { length:30, width:20, height:15, weight:2.5 };
    const service = (document.getElementById('gss-service-type')?.value||'ECONOMY');
    try{
      const r = await STX.fetchJSON('create_label_gss',{ transfer_id:id, packages: JSON.stringify([pkg]), service: service });
      const d = r && r.data ? r.data : {};
      STX.emit('label:created', d);
      STX.emit('toast', {type:'success', text:'GSS docket created'});
    }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
  });
})();
