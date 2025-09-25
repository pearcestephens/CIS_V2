/* printer.js (stock path) */
(function(window, document){
  'use strict';
  var STXPrinter = {
    _opts: { ajaxUrl: 'https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php', csrf: '', transferId: null, onStatus: function(msg){ var el=document.querySelector('.stx-printer__status'); if(el){ el.textContent = msg; } }, simulate: 0 },
  _state: { contentsPlan: {} }, // key: rowIndex (1-based) => array of {pid, name, qty}
  _recalcTimer: null,
  init: function(options){ this._opts = Object.assign({}, this._opts, options || {}); this._bind(); this._wireEvents(); this._initCarrier(); this._opts.onStatus('Ready'); },
  _bind: function(){ var roots=document.querySelectorAll('.stx-printer'); if(!roots || !roots.length){ return; } for(var i=0;i<roots.length;i++){ var r=roots[i]; r.addEventListener('click', this._onClick.bind(this)); r.addEventListener('click', this._onParcelClick.bind(this)); r.addEventListener('change', this._onParcelChange.bind(this)); r.addEventListener('input', this._onParcelChange.bind(this)); } },
  _initCarrier: function(){ var self=this; try{ var body = 'csrf_token='+(encodeURIComponent(this._opts.csrf||''))+'&transfer_id='+(encodeURIComponent(this._opts.transferId||'')); fetch(this._opts.ajaxUrl+'?ajax_action=get_printers_config', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(json){ if(!json || !json.success) return; var d=json.data||{}; var sel=document.getElementById('stx-carrier'); if(!sel) return; var hasNZ=!!d.has_nzpost, hasGSS=!!d.has_gss; // hide unavailable
        Array.prototype.forEach.call(sel.options, function(o){ if(o.value==='nzpost' && !hasNZ) o.style.display='none'; if(o.value==='gss' && !hasGSS) o.style.display='none'; if(o.value==='manual') o.style.display=''; });
    // availability badges + clearer wording
    var nzBadge=document.getElementById('stx-nzpost-status'); if(nzBadge){ nzBadge.textContent = hasNZ ? 'Available' : 'Unavailable (use Box Slips or Manual)'; nzBadge.classList.remove('badge-light'); nzBadge.classList.add(hasNZ ? 'badge-success' : 'badge-warning'); }
    var gssBadge=document.getElementById('stx-gss-status'); if(gssBadge){ gssBadge.textContent = hasGSS ? 'Available' : 'Unavailable (use Box Slips or Manual)'; gssBadge.classList.remove('badge-light'); gssBadge.classList.add(hasGSS ? 'badge-success' : 'badge-warning'); }
        // default selection
        var def = d.default || 'none'; if(def==='none') def='manual'; sel.value = def;
        self._toggleCarrierBlocks(def);
        sel.addEventListener('change', function(){ self._toggleCarrierBlocks(sel.value); });
        // Suggest packaging once presets are loaded
        if (window.STXPackages){ STXPackages.load().then(function(){ STXPackages.populateDropdowns(document); self._suggestPackaging(); }); }
      }).catch(function(){}); }catch(_){ }
    },
    _toggleCarrierBlocks: function(kind){ var blocks=document.querySelectorAll('.stx-block'); for(var i=0;i<blocks.length;i++){ var el=blocks[i]; var isNZ=el.classList.contains('stx-block-nzpost'); var isGSS=el.classList.contains('stx-block-gss'); var isMan=el.classList.contains('stx-block-manual'); var show=(kind==='nzpost'&&isNZ)||(kind==='gss'&&isGSS)||(kind==='manual'&&isMan); el.style.display = show?'' : 'none'; }
    },
    _wireEvents: function(){ document.addEventListener('stx:label:created', function(e){ var data=e.detail||{}; if(data && data.label_url){ try{ window.open(data.label_url,'_blank'); }catch(err){} } if(data && data.tracking_number){ STXPrinter._copyToClipboard(data.tracking_number); }
      function openSlips(){ try{
        var boxes = Array.isArray(data.packages)? data.packages.length : (data.box_count || 1);
        var from = (document.querySelector('.stx-header .text-nowrap:nth-of-type(1)')||{}).textContent || '';
        var to   = (document.querySelector('.stx-header .text-nowrap:nth-of-type(2)')||{}).textContent || '';
        if (!from && !to){
          // Fallback to CIS meta subtitle (e.g., "Outlet A → Outlet B")
          var metaSubtitle = document.querySelector('meta[name="page-subtitle"]');
          if (metaSubtitle && metaSubtitle.content && metaSubtitle.content.indexOf('→')>-1){
            var parts = metaSubtitle.content.split('→');
            from = (parts[0]||'').trim();
            to = (parts[1]||'').trim();
          }
        }
        for (var i=1; i<=Math.max(1, boxes); i++){
          var url = 'https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_slip.php?transfer='+encodeURIComponent(STXPrinter._opts.transferId)+'&box='+i+'&from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to)+'&car='+(data.carrier||'');
          window.open(url, '_blank');
        }
      }catch(__){}
      }
      // Record shipment server-side for history/receipts (with CSRF) then open slips
      try{
        var payload={ csrf_token: STXPrinter._opts.csrf||'', transfer_id: STXPrinter._opts.transferId, carrier: data.carrier||'', reference: data.reference||'', tracking_number: data.tracking_number||'', label_url: data.label_url||'', parcels: JSON.stringify(data.packages||[]), contents_plan: JSON.stringify(STXPrinter._buildAbsoluteContentsMap()) };
        fetch(STXPrinter._opts.ajaxUrl+'?ajax_action=record_shipment', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body: Object.keys(payload).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(payload[k]||'');}).join('&') }).then(function(r){ return r.json(); }).then(function(){ openSlips(); }).catch(function(){ openSlips(); });
      }catch(_){ openSlips(); }
      STXPrinter._opts.onStatus('Label ready' + (data.tracking_number?(' · '+data.tracking_number):''));
    }); },
    _suggestPackaging: function(){
      try{
        if (!window.STXPackages || !this._opts.transferId) return;
        var self=this;
        // fetch product weights
        fetch(this._opts.ajaxUrl+'?ajax_action=get_product_weights', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body: 'csrf_token='+(encodeURIComponent(this._opts.csrf||''))+'&transfer_id='+encodeURIComponent(this._opts.transferId) })
          .then(function(r){ return r.json(); })
          .then(function(json){ if(!json || !json.success) return; var weights=json.data && json.data.weights || {}; self._applyPackaging(weights); })
          .catch(function(){});
      }catch(_){ }
    },
    recalcDueToItemsChange: function(){
      var self=this;
      if (!window.STXPackages) return;
      clearTimeout(this._recalcTimer);
      this._recalcTimer = setTimeout(function(){
        // If no contents have been assigned yet, re-suggest packaging; otherwise just update cost
        var hasPlan=false; var plan=self._state.contentsPlan||{}; for(var k in plan){ if(plan.hasOwnProperty(k) && Array.isArray(plan[k]) && plan[k].length){ hasPlan=true; break; } }
        if (!hasPlan){ self._suggestPackaging(); }
        else { try{ STXPackages.computeCostEstimate(document); }catch(e){} }
    self._updateContentsBadges();
      }, 350);
    },
  _updateContentsBadges: function(){ 
    try{ 
      var rows=document.querySelectorAll('.stx-parcels tr'); 
      for(var i=0;i<rows.length;i++){ 
        var row=rows[i]; 
        var badge=row.querySelector('.stx-contents-badge'); 
        if(!badge) continue; 
        var idx=i+1; 
        var items=this._state.contentsPlan[idx]||[]; 
        var total=0; 
        for(var j=0;j<items.length;j++){ 
          total += parseInt(items[j].qty||0,10)||0; 
        }
        if (total>0){ 
          badge.textContent = total + (total===1?' item':' items'); 
          badge.classList.remove('is-empty'); 
        } 
        else { 
          badge.textContent = 'Unassigned'; 
          badge.classList.add('is-empty'); 
        } 
      } 
    }catch(_){ }
  },
    _applyPackaging: function(weights){
      try{
        // Build a map of product_id -> counted qty from the items table
        var rows = document.querySelectorAll('#productSearchBody tr');
        var items = [];
        for (var i=0;i<rows.length;i++){
          var r=rows[i]; var pid=(r.querySelector('.productID')||{}).value||''; if(!pid) continue;
          var qty=parseInt((r.querySelector('[data-behavior="counted-input"]')||{}).value||'0',10) || 0; if(qty<=0) qty = parseInt((r.querySelector('.planned')||{}).textContent||'0',10) || 0;
          var w = parseFloat(weights[pid]||0) || 0; if (qty>0) items.push({ pid: pid, qty: qty, weight: w });
        }
        if (!items.length) return;
        // Total weight and naive bin-packing by max box weight capacity from presets
        var presets = (window.STXPackages && STXPackages.getPresets()) || [];
        if (!presets.length) return;
        // Determine a default box preset: prefer a box with capacity_kg if provided
        var boxPresets = presets.filter(function(p){ return (p.type||'').toLowerCase()==='box'; });
        var satchelPresets = presets.filter(function(p){ return (p.type||'').toLowerCase()==='satchel'; });
        // Sort boxes by capacity ascending to try filling smallest first
        boxPresets.sort(function(a,b){ return (a.capacity_kg||0)-(b.capacity_kg||0); });
        // Compute total weight
        var totalKg = 0; items.forEach(function(it){ totalKg += (it.weight||0) * it.qty; });
        if (totalKg<=0){ return; }
        // Greedy fill into boxes by capacity
        var boxes=[]; var remaining = totalKg;
        for (var b=0;b<boxPresets.length && remaining>0;b++){
          var cap = boxPresets[b].capacity_kg || 0;
          if (cap<=0) continue;
          var need = Math.floor(remaining / cap);
          if (remaining % cap > 0) need += 1;
          if (need>0){ boxes.push({ preset: boxPresets[b], count: need }); remaining = 0; }
        }
        if (!boxes.length && satchelPresets.length){ boxes.push({ preset: satchelPresets[0], count: 1 }); }
        // Apply suggestion to UI: set rows count and preset select
        var tbody = document.querySelector('.stx-parcels'); if(!tbody) return;
        // Clear existing rows except first
        var keepFirst = tbody.querySelector('tr');
        // reset first row
        if (keepFirst){
          (keepFirst.querySelector('.stx-qty')||{}).value='1';
          (keepFirst.querySelector('.stx-weight')||{}).value='1.00';
          (keepFirst.querySelector('.stx-width')||{}).value='30';
          (keepFirst.querySelector('.stx-height')||{}).value='20';
          (keepFirst.querySelector('.stx-depth')||{}).value='10';
        }
        // remove others
        var sib = keepFirst? keepFirst.nextElementSibling : null; while (sib){ var next=sib.nextElementSibling; sib.remove(); sib=next; }
        // Ensure presets are populated
        if (window.STXPackages){ STXPackages.populateDropdowns(document); }
        // Fill rows
        if (boxes.length){
          // Configure first row
          var first = keepFirst || document.createElement('tr');
          if (!keepFirst){ first.innerHTML = tbody.parentNode.querySelector('tr')?.innerHTML || ''; tbody.appendChild(first); }
          var sel = first.querySelector('.stx-preset-select'); if(sel){ sel.value = boxes[0].preset.code || boxes[0].preset.id; }
          var qtyEl = first.querySelector('.stx-qty'); if(qtyEl){ qtyEl.value = String(Math.max(1, boxes[0].count)); }
          if (window.STXPackages){ STXPackages.applyPresetToRow(first, boxes[0].preset); }
          // Additional rows if more presets suggested
          for (var i2=1;i2<boxes.length;i2++){
            var tr=document.createElement('tr');
            tr.innerHTML='<td><input type="number" class="form-control form-control-sm stx-qty" value="1" min="1"></td>'+
              '<td><div class="input-group input-group-sm"><select class="form-control stx-preset-select"><option value="">Select preset…</option></select><div class="input-group-append"><button type="button" class="btn btn-outline-secondary" data-action="preset-apply" title="Apply preset">Apply</button></div></div></td>'+
              '<td><input type="number" step="0.01" class="form-control form-control-sm stx-weight" value="1.00" min="0"></td>'+
              '<td><input type="number" class="form-control form-control-sm stx-width" value="30" min="0"></td>'+
              '<td><input type="number" class="form-control form-control-sm stx-height" value="20" min="0"></td>'+
              '<td><input type="number" class="form-control form-control-sm stx-depth" value="10" min="0"></td>'+
              '<td><button type="button" class="btn btn-link text-danger p-0" data-action="parcel-remove" aria-label="Remove"><i class="fa fa-trash"></i></button></td>';
            tbody.appendChild(tr);
            if (window.STXPackages){ STXPackages.populateDropdowns(tr); STXPackages.applyPresetToRow(tr, boxes[i2].preset); }
            var sel2 = tr.querySelector('.stx-preset-select'); if(sel2){ sel2.value = boxes[i2].preset.code || boxes[i2].preset.id; }
            var q2 = tr.querySelector('.stx-qty'); if(q2){ q2.value = String(Math.max(1, boxes[i2].count)); }
          }
          if (window.STXPackages){ STXPackages.computeCostEstimate(document); }
        }
      }catch(_){ }
    },
    _onClick: function(evt){ var btn=evt.target.closest('.stx-action'); var copyBtn=evt.target.closest('.stx-copy'); if(btn){ var action=btn.getAttribute('data-action'); if(action==='nzpost.create') return this._nzpostCreate(); if(action==='gss.create') return this._gssCreate(); if(action==='manual.save') return this._manualSave(); if(action==='inhouse.assign') return this._inhouseAssign(); } if(copyBtn){ var selector=copyBtn.getAttribute('data-target'); var input=selector?document.querySelector(selector):null; if(input){ this._copyToClipboard(input.value||''); this._opts.onStatus('Copied'); } }
      // Box slips (internal) button
      var slips = evt.target.closest('[data-action="boxslips.print"]'); if (slips){ evt.preventDefault(); try{
        var boxes = document.querySelectorAll('.stx-parcels tr').length||1; var from='', to='';
        var metaSubtitle = document.querySelector('meta[name="page-subtitle"]');
        if (metaSubtitle && metaSubtitle.content && metaSubtitle.content.indexOf('→')>-1){ var parts = metaSubtitle.content.split('→'); from=(parts[0]||'').trim(); to=(parts[1]||'').trim(); }
        for (var i=1;i<=boxes;i++){ var url = 'https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_slip.php?transfer='+encodeURIComponent(this._opts.transferId)+'&box='+i+'&from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to); window.open(url,'_blank'); }
        this._opts.onStatus('Opened box slips');
      }catch(e){ this._opts.onStatus('Could not open box slips'); }
      return false; }
    },
  _collectParcels: function(){ var rows=document.querySelectorAll('.stx-parcels tr'); var parcels=[]; for(var i=0;i<rows.length;i++){ var r=rows[i]; var qty=parseInt((r.querySelector('.stx-qty')||{}).value||'1',10); var weight=parseFloat((r.querySelector('.stx-weight')||{}).value||'0'); var w=parseFloat((r.querySelector('.stx-width')||{}).value||'0'); var h=parseFloat((r.querySelector('.stx-height')||{}).value||'0'); var d=parseFloat((r.querySelector('.stx-depth')||{}).value||'0'); var rowIdx=i+1; var contentsForRow=(STXPrinter._state.contentsPlan && STXPrinter._state.contentsPlan[rowIdx]) ? STXPrinter._state.contentsPlan[rowIdx] : []; for(var q=0;q<Math.max(1,qty);q++){ parcels.push({ weight: weight, width: w, height: h, depth: d, contents: contentsForRow }); } } return parcels; },
    _nzpostCreate: function(){ var svc=document.getElementById('stx-nzpost-service').value; var ref=document.getElementById('stx-nzpost-ref').value||''; var sig=document.getElementById('stx-signature')?.checked?1:0; var sat=document.getElementById('stx-saturday')?.checked?1:0; var parcels=this._collectParcels(); return this._post('create_label_nzpost', { transfer_id: this._opts.transferId, service: svc, parcels: JSON.stringify(parcels), reference: ref, signature: sig, saturday: sat }); },
    _gssCreate: function(){ var svc=document.getElementById('stx-gss-service').value; var ref=document.getElementById('stx-gss-ref').value||''; var sig=document.getElementById('stx-signature')?.checked?1:0; var sat=document.getElementById('stx-saturday')?.checked?1:0; var parcels=this._collectParcels(); return this._post('create_label_gss', { transfer_id: this._opts.transferId, service: svc, parcels: JSON.stringify(parcels), reference: ref, signature: sig, saturday: sat }); },
    _manualSave: function(){ var no=document.getElementById('stx-manual-number').value||''; var car=document.getElementById('stx-manual-carrier').value||'other'; return this._post('save_manual_tracking', { transfer_id: this._opts.transferId, tracking_number: no, carrier: car }); },
    _inhouseAssign: function(){ var driver=document.getElementById('stx-inhouse-driver').value||''; var eta=document.getElementById('stx-inhouse-eta').value||''; var ev=new CustomEvent('stx:inhouse:assigned', { detail: { driver: driver, eta: eta } }); document.dispatchEvent(ev); this._opts.onStatus('In-house assigned: '+driver+(eta?(' · '+eta):'')); return false; },
    _post: function(ajaxAction, payload){ var self=this; var url=this._opts.ajaxUrl + '?ajax_action=' + encodeURIComponent(ajaxAction); var body=new URLSearchParams(); body.set('csrf_token', this._opts.csrf || ''); body.set('simulate', String(this._opts.simulate||0)); Object.keys(payload||{}).forEach(function(k){ body.set(k, payload[k]); });
      self._opts.onStatus('Working…');
      var stEl = document.querySelector('.stx-printer__status'); if (stEl){ stEl.classList.add('is-pulse'); setTimeout(function(){ stEl.classList.remove('is-pulse'); }, 250); }
      return fetch(url,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString(), credentials:'same-origin' }).then(function(r){ return r.json(); }).then(function(json){ if(json && json.success){ var d=(json.data||{}); if(d.label_url || d.tracking_number){ var ev=new CustomEvent('stx:label:created', { detail: d }); document.dispatchEvent(ev);} self._opts.onStatus('Done'); return json; } var msg=(json && json.error && json.error.message) ? json.error.message : 'Action failed'; self._opts.onStatus(msg); return json; }).catch(function(err){ self._opts.onStatus('Network error'); console.error(err); return { success:false, error:{ message:'Network error' } } }); },
    _copyToClipboard: function(text){ try{ var ta=document.createElement('textarea'); ta.value=text||''; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);}catch(e){} },
  _onParcelChange: function(evt){ if(window.STXPackages){ STXPackages.computeCostEstimate(document); } },
  _onParcelClick: function(evt){ var add=evt.target.closest('[data-action="parcel-add"]'); var remove=evt.target.closest('[data-action="parcel-remove"]'); var apply=evt.target.closest('[data-action="preset-apply"]'); var contentsBtn=evt.target.closest('[data-action="parcel-contents"]'); var tbody=document.querySelector('.stx-parcels'); if(add && tbody){ evt.preventDefault(); var tr=document.createElement('tr'); tr.innerHTML='<td><input type="number" class="form-control form-control-sm stx-qty" value="1" min="1"></td>'+
        '<td><div class="input-group input-group-sm"><select class="form-control stx-preset-select"><option value="">Select preset…</option></select><div class="input-group-append"><button type="button" class="btn btn-outline-secondary" data-action="preset-apply" title="Apply preset">Apply</button></div></div></td>'+
        '<td><input type="number" step="0.01" class="form-control form-control-sm stx-weight" value="1.00" min="0"></td>'+
        '<td><input type="number" class="form-control form-control-sm stx-width" value="30" min="0"></td>'+
        '<td><input type="number" class="form-control form-control-sm stx-height" value="20" min="0"></td>'+
        '<td><input type="number" class="form-control form-control-sm stx-depth" value="10" min="0"></td>'+
        '<td><div class="d-flex align-items-center" style="gap:8px;"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="parcel-contents"><i class="fa fa-box-open mr-1"></i>Assign</button><span class="badge badge-light stx-contents-badge">0 items</span><button type="button" class="btn btn-link text-danger p-0 ml-auto" data-action="parcel-remove" aria-label="Remove"><i class="fa fa-trash"></i></button></div></td>';
    tbody.appendChild(tr); if(window.STXPackages){ STXPackages.populateDropdowns(tr); STXPackages.computeCostEstimate(document); } STXPrinter._updateContentsBadges(); }
    if(remove){ evt.preventDefault(); var row=remove.closest('tr'); if(row){ var idx=Array.prototype.indexOf.call(tbody.children, row)+1; delete STXPrinter._state.contentsPlan[idx]; row.parentNode.removeChild(row); STXPrinter._reindexContentsPlan(); if(window.STXPackages){ STXPackages.computeCostEstimate(document); } STXPrinter._updateContentsBadges(); } }
      if(apply){ evt.preventDefault(); var row=apply.closest('tr'); if(row && window.STXPackages){ var sel=row.querySelector('.stx-preset-select'); var p=STXPackages.findPresetByValue(sel && sel.value); if(p){ STXPackages.applyPresetToRow(row,p); STXPackages.computeCostEstimate(document); } } }
      if(contentsBtn){ evt.preventDefault(); var row=contentsBtn.closest('tr'); STXPrinter._openContentsModal(row); }
    },
  _openContentsModal: function(row){ try{ var modal=document.getElementById('stx-contents-modal'); if(!modal) return; var rowIdx=this._rowToBoxIndex(row); var body=modal.querySelector('#stx-contents-body'); if(!body) return; // build rows from items table
    body.innerHTML=''; var assignedMap={}; var existing=(this._state.contentsPlan[rowIdx]||[]); for(var i=0;i<existing.length;i++){ assignedMap[String(existing[i].pid)]=existing[i].qty; }
        var rows=document.querySelectorAll('#productSearchBody tr'); for(var r=0;r<rows.length;r++){ var tr=rows[r]; var pid=(tr.querySelector('.productID')||{}).value||''; if(!pid) continue; var name=((tr.children[1]||{}).textContent||'').trim(); var planned=parseInt((tr.querySelector('.planned')||{}).textContent||'0',10)||0; var counted=parseInt((tr.querySelector('[data-behavior="counted-input"]')||{}).value||'0',10)||0; var remaining=counted>0?counted:planned; var cur=assignedMap[String(pid)]||0; var rowEl=document.createElement('tr'); rowEl.innerHTML='<td>'+STXPrinter._escape(name)+'</td><td class="text-right pr-3">'+remaining+'</td><td><input type="number" class="form-control form-control-sm" min="0" max="'+remaining+'" value="'+cur+'" data-pid="'+pid+'" data-name="'+STXPrinter._escapeAttr(name)+'"></td>'; body.appendChild(rowEl); }
        // wire save
    var saveBtn=modal.querySelector('#stx-contents-save'); if(saveBtn){ saveBtn.onclick=function(){ var inputs=body.querySelectorAll('input[type="number"]'); var plan=[]; var total=0; for(var i=0;i<inputs.length;i++){ var inp=inputs[i]; var q=parseInt(inp.value||'0',10)||0; if(q>0){ plan.push({ pid: String(inp.getAttribute('data-pid')||''), name: String(inp.getAttribute('data-name')||''), qty: q }); total+=q; } } STXPrinter._state.contentsPlan[rowIdx]=plan; // update badge
            var badge=row.querySelector('.stx-contents-badge'); if(badge){ badge.textContent = total + (total===1?' item':' items'); }
            if(window.jQuery){ window.jQuery(modal).modal('hide'); } else { modal.style.display='none'; modal.classList.remove('show'); document.body.classList.remove('modal-open'); var back=document.querySelector('.modal-backdrop'); if(back) back.parentNode.removeChild(back); }
      STXPrinter._updateContentsBadges();
        }; }
        // show modal
        if(window.jQuery){ window.jQuery(modal).modal('show'); } else { modal.style.display='block'; modal.classList.add('show'); document.body.classList.add('modal-open'); var backdrop=document.createElement('div'); backdrop.className='modal-backdrop fade show'; document.body.appendChild(backdrop); }
      }catch(_){ }
    },
    _rowToBoxIndex: function(row){ var tbody=row && row.parentNode; if(!tbody) return 1; return Array.prototype.indexOf.call(tbody.children, row)+1; },
  _reindexContentsPlan: function(){ var newPlan={}; var rows=document.querySelectorAll('.stx-parcels tr'); for(var i=0;i<rows.length;i++){ var idx=i+1; var old=STXPrinter._state.contentsPlan[idx]; if(old) newPlan[idx]=old; } STXPrinter._state.contentsPlan=newPlan; },
  _buildAbsoluteContentsMap: function(){ var map={}; var rows=document.querySelectorAll('.stx-parcels tr'); var abs=1; for(var i=0;i<rows.length;i++){ var r=rows[i]; var qty=parseInt((r.querySelector('.stx-qty')||{}).value||'1',10); var rowIdx=i+1; var plan=STXPrinter._state.contentsPlan[rowIdx]||[]; for(var q=0;q<Math.max(1,qty);q++){ map[abs]=plan; abs++; } } return map; },
    _escape: function(s){ return (s||'').replace(/[&<>]/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]||c; }); },
    _escapeAttr: function(s){ return (s||'').replace(/["'&<>]/g,function(c){ return {'"':'&quot;','\'':'&#39;','&':'&amp;','<':'&lt;','>':'&gt;'}[c]||c; }); }
  };
  window.STXPrinter = STXPrinter;
})(window, document);
