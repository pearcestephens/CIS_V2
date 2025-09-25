/* planner.v3.js — next-gen packaging planner with balanced weight and constraints */
(function(window, document){
  'use strict';
  var root = document.getElementById('stx-planner-v3'); if (!root) return;
  var ajaxUrl = 'https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php';
  var csrf = (document.querySelector('meta[name="csrf-token"]')||{}).content||'';
  var transferId = (document.getElementById('transferID')||{}).value||'';

  function fetchJSON(action, payload){ var body=new URLSearchParams(); body.set('csrf_token', csrf); Object.keys(payload||{}).forEach(function(k){ body.set(k, payload[k]); }); return fetch(ajaxUrl+'?ajax_action='+encodeURIComponent(action),{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body: body.toString() }).then(function(r){ return r.json(); }).then(function(j){ if(!j||!j.success) throw new Error((j&&j.error)||'Request failed'); return j; }); }
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]||c;}); }
  function setStatus(msg){ var el=document.getElementById('stxv3-status'); if(el) el.textContent=msg; }

  function readItems(){ var rows=document.querySelectorAll('#productSearchBody tr'); var out=[]; rows.forEach(function(tr){ var pid=(tr.querySelector('.productID')||{}).value||''; if(!pid) return; var name=((tr.children[1]||{}).textContent||'').trim(); var planned=parseInt((tr.querySelector('.planned')||{}).textContent||'0',10)||0; var counted=parseInt((tr.querySelector('input[type="number"]')||{}).value||'0',10)||0; var qty=counted>0?counted:planned; if(qty>0) out.push({pid:pid,name:name,qty:qty}); }); return out; }

  // Balanced partition with constraints
  function plan(items, weights, attrs, presets, opts){
    opts = opts || {}; var mode=opts.mode||'balanced'; var maxW=parseFloat(opts.maxW||0)||0; var maxBatt=parseInt(opts.maxBatt||0,10)||0; var noMixBE=!!opts.noMixBE;
    // explode units
    var units=[]; items.forEach(function(it){ var w=parseFloat(weights[it.pid]||0.15)||0.15; var a=attrs[it.pid]||{}; var isEliq = (String((a.type||'')).indexOf('liqu')>-1) || /juice|eliq|liquid/i.test(String((it.name||''))); for(var i=0;i<it.qty;i++){ units.push({pid:it.pid,name:it.name,w:w,is_battery:!!a.is_battery,eliq:isEliq,fragile:!!a.fragile}); } });
    // Separate batteries to ensure distributed
    var batt = units.filter(function(u){return u.is_battery;});
    var rest = units.filter(function(u){return !u.is_battery;});
    // Sort both by weight desc
    batt.sort(function(a,b){ return b.w - a.w; }); rest.sort(function(a,b){ return b.w - a.w; });
    var all = batt.concat(rest);

    // Determine target box count from presets and total weight
    var totalW = all.reduce(function(s,u){return s+u.w;},0);
    var boxPresets = (presets||[]).filter(function(p){ return (p.type||'box').toLowerCase()==='box' && (p.capacity_kg||0)>0; });
    if (!boxPresets.length && window.STXPackages){ boxPresets = STXPackages.getPresets(); }
    // Pick a baseline capacity
  var cap = 5; if(boxPresets.length){ cap = Math.min.apply(null, boxPresets.map(function(p){ return parseFloat(p.capacity_kg||5)||5; })); }
  if (maxW>0) cap = Math.min(cap, maxW);
    var targetBoxes = Math.max(1, Math.ceil(totalW / cap));

    // Initialize boxes with chosen preset cycling from most efficient
    var boxes=[]; for (var i=0;i<targetBoxes;i++){ var pres = boxPresets[i % Math.max(1, boxPresets.length)] || {}; var capacity = parseFloat(pres.capacity_kg||cap)||cap; boxes.push({ preset: pres, capacity: capacity, weight: 0, items: [], batt: 0 }); }

    // Greedy least-weight bin to balance; batteries distributed first
    function fitsConstraints(b,u){ if (b.weight + u.w > b.capacity) return false; if (maxBatt>0 && u.is_battery && b.batt >= maxBatt) return false; if (noMixBE && u.is_battery){ // don’t mix batteries and e-liquid in same box
        var hasE = b.items.some(function(x){ return x.eliq; }); if (hasE) return false; }
      if (noMixBE && u.eliq){ var hasB = b.items.some(function(x){ return x.is_battery; }); if (hasB) return false; }
      return true; }
    function place(u){
      var idx=-1; var minW=Infinity; for(var i=0;i<boxes.length;i++){ var b=boxes[i]; if (fitsConstraints(b,u)){ if (mode==='min_boxes'){ // first-fit
              idx=i; break; } else { if (b.weight < minW){ minW=b.weight; idx=i; } } } }
      if (idx<0){ // need a new box
        var pres = boxPresets[boxes.length % Math.max(1, boxPresets.length)] || {}; var capacity=parseFloat(pres.capacity_kg||cap)||cap; boxes.push({ preset: pres, capacity: capacity, weight: 0, items: [], batt: 0 }); idx=boxes.length-1;
      }
      var b = boxes[idx]; b.items.push(u); b.weight += u.w; if (u.is_battery) b.batt += 1; return idx;
    }

    batt.forEach(place);
    rest.forEach(place);

    // Build contents map
    var contents = {}; boxes.forEach(function(b, i){ var m={}; b.items.forEach(function(u){ m[u.pid]=(m[u.pid]||0)+1; }); var arr=[]; Object.keys(m).forEach(function(pid){ var name=(items.find(function(x){return x.pid===pid;})||{}).name||pid; arr.push({ pid:pid, name:name, qty:m[pid] }); }); contents[i+1]=arr; });

    return { boxes: boxes, contents: contents };
  }

  function render(planRes){ var tbody=root.querySelector('.stxv3-parcels'); tbody.innerHTML=''; var presets=(window.STXPackages? STXPackages.getPresets():[]);
    planRes.boxes.forEach(function(b){ var tr=document.createElement('tr'); tr.innerHTML='<td>1</td><td><select class="form-control form-control-sm stxv3-preset"><option value="">Select preset…</option></select></td><td>'+b.weight.toFixed(2)+' kg</td><td>'+(b.items.length)+'</td><td>'+(b.batt>0?('<span class="badge badge-warning">'+b.batt+' batt</span>'):'-')+'</td>';
      tbody.appendChild(tr); var sel=tr.querySelector('.stxv3-preset'); presets.forEach(function(p){ var o=document.createElement('option'); o.value=p.code||p.id; o.textContent=p.label||p.name||o.value; sel.appendChild(o); });
      if (b.preset && (b.preset.code||b.preset.id)) sel.value=(b.preset.code||b.preset.id);
    });
    setStatus('Planned '+planRes.boxes.length+' boxes');
  }

  function go(){ setStatus('Planning…'); var opts={ mode:(document.getElementById('stxv3-mode')||{}).value||'balanced', maxW:(document.getElementById('stxv3-maxw')||{}).value||0, maxBatt:(document.getElementById('stxv3-maxbatt')||{}).value||0, noMixBE:(document.getElementById('stxv3-nomix-batt-eliq')||{}).checked||false }; Promise.all([
    fetchJSON('get_product_weights', { transfer_id: transferId }),
    fetchJSON('get_product_attributes', { transfer_id: transferId })
  ]).then(function(arr){ var weights=(arr[0].data&&arr[0].data.weights)||{}; var attrs=(arr[1].data&&arr[1].data.attributes)||{}; var items=readItems(); if(!items.length){ setStatus('No items'); return; }
    var presets = (window.STXPackages? STXPackages.getPresets():[]);
    var res = plan(items, weights, attrs, presets, opts);
    root._contents = res.contents; // expose mapping
    try { var evt = new CustomEvent('stx:planner:contents', { detail: res.contents }); document.dispatchEvent(evt); } catch(e) {}
    render(res);
  }).catch(function(err){ setStatus(err.message||'Plan failed'); }); }

  // Save plan: lightweight persist into localStorage keyed by transfer
  function savePlan(){ if(!root._contents) return; try{ var key='stx:plan:'+transferId; localStorage.setItem(key, JSON.stringify(root._contents)); setStatus('Plan saved'); }catch(e){ setStatus('Save failed'); } }
  function autoPlanHook(){ var auto=(document.getElementById('stxv3-autoplan')||{}).checked||false; if(auto) go(); }
  root.addEventListener('click', function(e){ if (e.target.closest('#stxv3-run')) { go(); } if (e.target.closest('#stxv3-save')) { savePlan(); } });
  document.addEventListener('input', function(e){ if(e.target && (e.target.id==='stxv3-mode' || e.target.id==='stxv3-maxw' || e.target.id==='stxv3-maxbatt' || e.target.id==='stxv3-nomix-batt-eliq' || e.target.classList.contains('stxv3-counted'))){ autoPlanHook(); } });
  setStatus('Ready');
})(window, document);
