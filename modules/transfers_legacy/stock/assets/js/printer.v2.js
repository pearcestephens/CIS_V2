/* printer.v2.js — fully remodelled shipping UI controller for printer_v2.php */
(function(window, document){
  'use strict';
  var el = document.getElementById('stx-printer-v2'); if (!el) return;
  var state = { method: 'manual', hasNZ:false, hasGSS:false, contents:{}, transferId: (document.getElementById('transferID')||{}).value||'' };
  var ajaxUrl = 'https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php';
  var csrf = (document.querySelector('meta[name="csrf-token"]')||{}).content || '';
  function setStatus(msg){ var s=document.getElementById('stxv2-status'); if(s) s.textContent=msg; }
  function step(n){ document.querySelectorAll('#stx-printer-v2 .stxv2-step').forEach(function(p){ p.classList.add('d-none'); });
    var panel=document.querySelector('#stx-printer-v2 .stxv2-step[data-step-panel="'+n+'"]'); if(panel) panel.classList.remove('d-none');
    document.querySelectorAll('#stx-printer-v2 [data-step]').forEach(function(b){ b.classList.toggle('badge-primary', b.getAttribute('data-step')==String(n)); b.classList.toggle('badge-secondary', b.getAttribute('data-step')!=String(n)); });
  }
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]||c;}); }
  function fetchJSON(action, payload){ var body=new URLSearchParams(); body.set('csrf_token', csrf); Object.keys(payload||{}).forEach(function(k){ body.set(k, payload[k]); }); return fetch(ajaxUrl+'?ajax_action='+encodeURIComponent(action),{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body: body.toString() }).then(function(r){ return r.json(); }).then(function(j){ if(!j||!j.success) throw new Error((j&&j.error)||'Request failed'); return j; }); }

  // Availability
  (function initAvailability(){ fetchJSON('get_printers_config',{}).then(function(res){ var d=res.data||{}; state.hasNZ=!!d.has_nzpost; state.hasGSS=!!d.has_gss; var nz=document.getElementById('stxv2-chip-nzpost'); if(nz){ nz.textContent=state.hasNZ? 'Available':'Unavailable'; nz.classList.toggle('badge-success', state.hasNZ); nz.classList.toggle('badge-warning', !state.hasNZ); }
    var g=document.getElementById('stxv2-chip-gss'); if(g){ g.textContent=state.hasGSS? 'Available':'Unavailable'; g.classList.toggle('badge-success', state.hasGSS); g.classList.toggle('badge-warning', !state.hasGSS); }
  }).catch(function(){ setStatus('Printer config unavailable'); }); })();

  // Load presets
  (function initPresets(){ if(!window.STXPackages) return; STXPackages.load().then(function(){
    // Populate default selector and first row
    var def = document.getElementById('stxv2-default-preset'); if(def){
      // fill
      var pres = STXPackages.getPresets();
      pres.forEach(function(p){ var o=document.createElement('option'); o.value=p.code||p.id; o.textContent=p.label||p.name||o.value; def.appendChild(o); });
    }
    // per-row preset dropdowns
    var rows = el.querySelectorAll('.stxv2-preset');
    rows.forEach(function(sel){
      var pres = STXPackages.getPresets();
      pres.forEach(function(p){ var o=document.createElement('option'); o.value=p.code||p.id; o.textContent=p.label||p.name||o.value; sel.appendChild(o); });
    });
  }); })();

  function parcels(){ var rows=el.querySelectorAll('.stxv2-parcels tr'); var out=[]; rows.forEach(function(r){ var qty=parseInt((r.querySelector('.stxv2-qty')||{}).value||'1',10)||1; var w=parseFloat((r.querySelector('.stxv2-weight')||{}).value||'0')||0; var W=parseFloat((r.querySelector('.stxv2-w')||{}).value||'0')||0; var H=parseFloat((r.querySelector('.stxv2-h')||{}).value||'0')||0; var D=parseFloat((r.querySelector('.stxv2-d')||{}).value||'0')||0; var p=(r.querySelector('.stxv2-preset')||{}).value||''; var idx=Array.prototype.indexOf.call(r.parentNode.children,r)+1; var cont=state.contents[idx]||[]; for(var i=0;i<Math.max(1,qty);i++){ out.push({ weight:w, width:W, height:H, depth:D, preset:p, contents:cont }); } }); return out; }

  // Read items from the table with approx weights
  function readItemsWithWeights(weightsMap){
    var rows=document.querySelectorAll('#productSearchBody tr'); var list=[]; var DEFAULT_W=0.15; // kg fallback
    rows.forEach(function(tr){ var pid=(tr.querySelector('.productID')||{}).value||''; if(!pid) return; var name=((tr.children[1]||{}).textContent||'').trim();
      var planned=parseInt((tr.querySelector('.planned')||{}).textContent||'0',10)||0; var counted=parseInt((tr.querySelector('[data-behavior="counted-input"]')||{}).value||'0',10)||0; var qty=counted>0?counted:planned; if(qty<=0) return;
      var w=parseFloat((weightsMap||{})[pid]||DEFAULT_W)||DEFAULT_W; list.push({ pid:pid, name:name, qty:qty, w:w }); });
    return list;
  }

  // Choose the best preset for a box weight (min capacity >= w), fallback to largest
  function choosePresetForWeight(presets, w){
    var best=null; var largest=null; presets.forEach(function(p){ var cap=parseFloat(p.capacity_kg||0)||0; if(!largest||cap>largest.capacity_kg) largest={ capacity_kg:cap, p:p }; if(cap>0 && cap>=w){ if(!best || cap<best.capacity_kg) best={ capacity_kg:cap, p:p }; } });
    return best? best.p : (largest? largest.p : null);
  }

  // First-Fit-Decreasing bin-packing on weights, returns array of boxes with items and totalWeight
  function packBoxes(items, presets){
    // explode quantities into units for simplicity (cap small numbers)
    var units=[]; items.forEach(function(it){ for(var i=0;i<it.qty;i++){ units.push({ pid:it.pid, name:it.name, w:it.w }); } });
    units.sort(function(a,b){ return b.w - a.w; });
    var boxes=[]; var MAX_UNITS=2000; if(units.length>MAX_UNITS){ units=units.slice(0,MAX_UNITS); }
    // Prefer the most efficient common preset for average weight
    var avg=0; units.forEach(function(u){ avg+=u.w; }); avg = units.length? avg/units.length : 0.5;
    var boxPresets=(window.STXPackages? STXPackages.getPresets():[]).filter(function(p){ return (p.type||'box').toLowerCase()==='box' && (p.capacity_kg||0)>0; });
    // Fallback to any preset if no boxes
    if (!boxPresets.length && window.STXPackages){ boxPresets = STXPackages.getPresets(); }
    var defaultPreset = choosePresetForWeight(boxPresets, Math.max(1, avg*6));

    units.forEach(function(u){
      var placed=false; for(var i=0;i<boxes.length;i++){ var b=boxes[i]; if(b.weight + u.w <= b.capacity){ b.weight += u.w; b.items.push(u); placed=true; break; } }
      if(!placed){ var preset = defaultPreset || choosePresetForWeight(boxPresets, u.w*3) || boxPresets[0] || {};
        var cap=parseFloat(preset.capacity_kg||0)||Math.max(1, u.w*3);
        boxes.push({ preset:preset, capacity:cap, weight:u.w, items:[u] });
      }
    });
    return boxes;
  }
  function computeCost(){ if(!window.STXPackages) return 0; var rows=el.querySelectorAll('.stxv2-parcels tr'); var total=0; rows.forEach(function(r){ var qty=parseInt((r.querySelector('.stxv2-qty')||{}).value||'1',10)||1; var sel=r.querySelector('.stxv2-preset'); if(!sel||!sel.value) return; var preset=STXPackages.findPresetByValue(sel.value); if(!preset) return; total += (parseFloat(preset.cost_nzd||0)||0) * qty; }); var out=document.getElementById('stxv2-cost'); if(out) out.textContent='Estimated Cost: $'+total.toFixed(2)+' NZD'; return total; }

  // Suggest boxes by weights via backend
  function suggest(){ setStatus('Suggesting…'); fetchJSON('get_product_weights', { transfer_id: state.transferId }).then(function(json){ var weights=(json.data&&json.data.weights)||{};
      var items = readItemsWithWeights(weights); if(!items.length){ setStatus('Nothing to suggest'); return; }
      var presets = (window.STXPackages? STXPackages.getPresets():[]);
      var boxPresets = presets.filter(function(p){ return (p.type||'box').toLowerCase()==='box' && (p.capacity_kg||0)>0; });
      if(!boxPresets.length) { boxPresets = presets; }
      var boxes = packBoxes(items, boxPresets);

      // Render boxes to UI (1 box per row, qty=1)
      var tbody=el.querySelector('.stxv2-parcels'); tbody.innerHTML='';
      boxes.forEach(function(b){ var tr=document.createElement('tr'); tr.innerHTML='<td><input type="number" class="form-control form-control-sm stxv2-qty" value="1" min="1"></td>\
<td><select class="form-control form-control-sm stxv2-preset"><option value="">Select preset…</option></select></td>\
<td><input type="number" step="0.01" class="form-control form-control-sm stxv2-weight" value="'+(b.weight.toFixed(2))+'" min="0"></td>\
<td><input type="number" class="form-control form-control-sm stxv2-w" value="'+(b.preset.width_cm||30)+'" min="0"></td>\
<td><input type="number" class="form-control form-control-sm stxv2-h" value="'+(b.preset.height_cm||20)+'" min="0"></td>\
<td><input type="number" class="form-control form-control-sm stxv2-d" value="'+(b.preset.depth_cm||10)+'" min="0"></td>\
<td><div class="d-flex align-items-center" style="gap:8px;"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="stxv2-contents"><i class="fa fa-box-open mr-1"></i>Assign</button><span class="badge badge-light stxv2-contents-badge">Unassigned</span><button type="button" class="btn btn-link text-danger p-0 ml-auto" data-action="stxv2-remove" aria-label="Remove"><i class="fa fa-trash"></i></button></div></td>';
        tbody.appendChild(tr);
        // fill preset select
        var sel=tr.querySelector('.stxv2-preset'); presets.forEach(function(p){ var o=document.createElement('option'); o.value=p.code||p.id; o.textContent=p.label||p.name||o.value; sel.appendChild(o); });
        if (b.preset && (b.preset.code||b.preset.id)) sel.value = (b.preset.code||b.preset.id);
      });

      // Build contents plan per box
      state.contents = {}; boxes.forEach(function(b, idx){ var m={}; b.items.forEach(function(u){ m[u.pid] = (m[u.pid]||0) + 1; }); var arr=[]; Object.keys(m).forEach(function(pid){ var it=items.find(function(i){ return i.pid===pid; }); arr.push({ pid: pid, name: (it? it.name : pid), qty: m[pid] }); }); state.contents[idx+1]=arr; });

      computeCost(); setStatus('Suggested '+boxes.length+' box'+(boxes.length===1?'':'es'));
  }).catch(function(){ setStatus('Suggest failed'); }); }

  // Modal for contents assignment
  function openContents(row){ var modal=document.getElementById('stxv2-contents-modal'); if(!modal) return; var body=modal.querySelector('#stxv2-contents-body'); body.innerHTML=''; var rowIdx=Array.prototype.indexOf.call(row.parentNode.children,row)+1; var plan=state.contents[rowIdx]||[]; var assigned={}; plan.forEach(function(i){ assigned[i.pid]=i.qty; }); var rows=document.querySelectorAll('#productSearchBody tr'); rows.forEach(function(r){ var pid=(r.querySelector('.productID')||{}).value||''; if(!pid) return; var name=((r.children[1]||{}).textContent||'').trim(); var planned=parseInt((r.querySelector('.planned')||{}).textContent||'0',10)||0; var counted=parseInt((r.querySelector('[data-behavior="counted-input"]')||{}).value||'0',10)||0; var remaining=counted>0?counted:planned; var cur=assigned[pid]||0; var tr=document.createElement('tr'); tr.innerHTML='<td>'+esc(name)+'</td><td class="text-right pr-3">'+remaining+'</td><td><input type="number" class="form-control form-control-sm" min="0" max="'+remaining+'" value="'+cur+'" data-pid="'+esc(pid)+'" data-name="'+esc(name)+'"></td>'; body.appendChild(tr); });
    var save=modal.querySelector('#stxv2-contents-save'); if(save){ save.onclick=function(){ var inputs=body.querySelectorAll('input[type="number"]'); var newPlan=[]; var total=0; inputs.forEach(function(inp){ var q=parseInt(inp.value||'0',10)||0; if(q>0){ newPlan.push({ pid:String(inp.getAttribute('data-pid')), name:String(inp.getAttribute('data-name')), qty:q }); total+=q; } }); state.contents[rowIdx]=newPlan; var badge=row.querySelector('.stxv2-contents-badge'); if(badge){ badge.textContent = total>0 ? (total+' item'+(total===1?'':'s')) : 'Unassigned'; } if(window.jQuery){ window.jQuery(modal).modal('hide'); } else { modal.style.display='none'; modal.classList.remove('show'); document.body.classList.remove('modal-open'); var back=document.querySelector('.modal-backdrop'); if(back) back.parentNode.removeChild(back); } }; }
    if(window.jQuery){ window.jQuery(modal).modal('show'); } else { modal.style.display='block'; modal.classList.add('show'); document.body.classList.add('modal-open'); var backdrop=document.createElement('div'); backdrop.className='modal-backdrop fade show'; document.body.appendChild(backdrop); }
  }

  // Actions
  el.addEventListener('click', function(e){
    var m=e.target.closest('.stxv2-method'); if(m){ var chosen=m.getAttribute('data-method'); state.method=chosen; setStatus('Method: '+chosen.toUpperCase()); return; }
    if(e.target.closest('#stxv2-next-1')){ step(2); return; }
    if(e.target.closest('#stxv2-next-2')){ step(3); return; }
    if(e.target.closest('#stxv2-suggest')){ suggest(); return; }
    if(e.target.closest('#stxv2-add-row')){ var tb=el.querySelector('.stxv2-parcels'); var tr=document.createElement('tr'); tr.innerHTML='<td><input type="number" class="form-control form-control-sm stxv2-qty" value="1" min="1"></td><td><select class="form-control form-control-sm stxv2-preset"><option value="">Select preset…</option></select></td><td><input type="number" step="0.01" class="form-control form-control-sm stxv2-weight" value="1.00" min="0"></td><td><input type="number" class="form-control form-control-sm stxv2-w" value="30" min="0"></td><td><input type="number" class="form-control form-control-sm stxv2-h" value="20" min="0"></td><td><input type="number" class="form-control form-control-sm stxv2-d" value="10" min="0"></td><td><div class="d-flex align-items-center" style="gap:8px;"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="stxv2-contents"><i class="fa fa-box-open mr-1"></i>Assign</button><span class="badge badge-light stxv2-contents-badge">Unassigned</span><button type="button" class="btn btn-link text-danger p-0 ml-auto" data-action="stxv2-remove" aria-label="Remove"><i class="fa fa-trash"></i></button></div></td>';
      tb.appendChild(tr); // populate presets
      if(window.STXPackages){ var sel=tr.querySelector('.stxv2-preset'); var pres=STXPackages.getPresets(); pres.forEach(function(p){ var o=document.createElement('option'); o.value=p.code||p.id; o.textContent=p.label||p.name||o.value; sel.appendChild(o); }); }
      computeCost(); return; }
    var rem=e.target.closest('[data-action="stxv2-remove"]'); if(rem){ rem.closest('tr')?.remove(); computeCost(); return; }
    var cont=e.target.closest('[data-action="stxv2-contents"]'); if(cont){ var row=cont.closest('tr'); openContents(row); return; }
    // Create actions
    if(e.target.closest('[data-action="stxv2-nzpost"]')){ if(!state.hasNZ){ setStatus('NZ Post unavailable'); return; } var ref=(document.getElementById('stxv2-ref')||{}).value||''; var sig=document.getElementById('stxv2-signature')?.checked?1:0; var sat=document.getElementById('stxv2-saturday')?.checked?1:0; var ps=JSON.stringify(parcels()); setStatus('Creating NZ Post…'); fetchJSON('create_label_nzpost',{ transfer_id: state.transferId, reference: ref, signature: sig, saturday: sat, parcels: ps}).then(function(j){ setStatus('Label ready'); // event for legacy listeners
        var d=j.data||{}; var ev=new CustomEvent('stx:label:created',{detail:d}); document.dispatchEvent(ev); }).catch(function(err){ setStatus(err.message||'NZ Post failed'); }); return; }
    if(e.target.closest('[data-action="stxv2-gss"]')){ if(!state.hasGSS){ setStatus('GSS unavailable'); return; } var ref=(document.getElementById('stxv2-ref')||{}).value||''; var sig=document.getElementById('stxv2-signature')?.checked?1:0; var sat=document.getElementById('stxv2-saturday')?.checked?1:0; var ps=JSON.stringify(parcels()); setStatus('Creating GSS…'); fetchJSON('create_label_gss',{ transfer_id: state.transferId, reference: ref, signature: sig, saturday: sat, parcels: ps}).then(function(j){ setStatus('Label ready'); var d=j.data||{}; var ev=new CustomEvent('stx:label:created',{detail:d}); document.dispatchEvent(ev); }).catch(function(err){ setStatus(err.message||'GSS failed'); }); return; }
    if(e.target.closest('[data-action="stxv2-manual"]')){ var num=(document.getElementById('stxv2-manual-num')||{}).value||''; var car=(document.getElementById('stxv2-manual-car')||{}).value||''; setStatus('Saving…'); fetchJSON('save_manual_tracking',{ transfer_id: state.transferId, tracking_number: num, carrier: car}).then(function(){ setStatus('Saved'); }).catch(function(err){ setStatus(err.message||'Save failed'); }); return; }
    if(e.target.closest('[data-action="stxv2-slips"]')){ // open internal slips per row
      var boxes = el.querySelectorAll('.stxv2-parcels tr').length || 1; var from='', to=''; var meta=document.querySelector('meta[name="page-subtitle"]'); if(meta && meta.content && meta.content.indexOf('→')>-1){ var parts=meta.content.split('→'); from=(parts[0]||'').trim(); to=(parts[1]||'').trim(); }
      for (var i=1;i<=boxes;i++){ var url='https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_slip.php?transfer='+encodeURIComponent(state.transferId)+'&box='+i+'&from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to); window.open(url,'_blank'); }
      setStatus('Opened box slips'); return; }
    if(e.target.closest('[data-action="stxv2-stickers"]')){ // compact colorful sticker per box
      var rows = el.querySelectorAll('.stxv2-parcels tr'); var boxes = rows.length || 1; var from='', to=''; var meta=document.querySelector('meta[name="page-subtitle"]'); if(meta && meta.content && meta.content.indexOf('→')>-1){ var parts=meta.content.split('→'); from=(parts[0]||'').trim(); to=(parts[1]||'').trim(); }
      for (var i=0;i<rows.length;i++){ var r=rows[i]; var idx=i+1; var w=(r.querySelector('.stxv2-weight')||{}).value||''; var p=(r.querySelector('.stxv2-preset')||{}).value||''; var url='https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_sticker.php?transfer='+encodeURIComponent(state.transferId)+'&box='+idx+'&boxes='+boxes+'&w='+encodeURIComponent(w)+'&p='+encodeURIComponent(p)+'&from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to)+'&car='+(state.method||'manual'); window.open(url,'_blank'); }
      if (rows.length===0){ var url='https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_sticker.php?transfer='+encodeURIComponent(state.transferId)+'&box=1&boxes=1&car='+(state.method||'manual')+'&from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to); window.open(url,'_blank'); }
      setStatus('Opened sticker labels'); return; }
  });

  // Computations
  el.addEventListener('input', function(e){ if(e.target.closest('.stxv2-qty, .stxv2-preset')) computeCost(); });

  // Start on step 1
  step(1);
  setStatus('Ready');

  // Integrate with Planner V3 contents if present
  document.addEventListener('stx:planner:contents', function(e){ try{ var map=e.detail||{}; if(map && typeof map==='object'){ state.contents = map; var rows=el.querySelectorAll('.stxv2-parcels tr'); rows.forEach(function(r,idx){ var badge=r.querySelector('.stxv2-contents-badge'); if(!badge) return; var arr=state.contents[idx+1]||[]; var total=0; arr.forEach(function(x){ total+=parseInt(x.qty||0,10)||0; }); badge.textContent = total>0 ? (total+' item'+(total===1?'':'s')) : 'Unassigned'; }); setStatus('Adopted planner contents'); } }catch(err){}
  });
})(window, document);
