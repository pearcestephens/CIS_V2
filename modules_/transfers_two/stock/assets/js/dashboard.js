/* dashboard.js (stock) */
(function(){
  const EL = {
    stats: ()=>document.getElementById('stx-stats'),
    table: ()=>document.getElementById('stx-table-body'),
    openTable: ()=>document.getElementById('stx-open-body'),
    pgStatus: ()=>document.getElementById('stx-pg-status'),
    pgPrev: ()=>document.getElementById('stx-pg-prev'),
    pgNext: ()=>document.getElementById('stx-pg-next'),
    q: ()=>document.getElementById('stx-filter-q'),
  state: ()=>document.getElementById('stx-filter-state'),
    from: ()=>document.getElementById('stx-filter-from'),
    to: ()=>document.getElementById('stx-filter-to'),
  taFrom: ()=>document.getElementById('stx-ta-from'),
  taTo: ()=>document.getElementById('stx-ta-to'),
    activity: ()=>document.getElementById('stx-activity'),
    activityRefresh: ()=>document.getElementById('stx-activity-refresh'),
    selAll: ()=>document.getElementById('stx-select-all'),
    bulkCancel: ()=>document.getElementById('stx-bulk-cancel'),
    bulkDelete: ()=>document.getElementById('stx-bulk-delete'),
    bulkSelectAllBtn: ()=>document.getElementById('stx-bulk-select-all'),
    bulkSelectNoneBtn: ()=>document.getElementById('stx-bulk-select-none'),
    // Freight widgets
    fwTotals: ()=>document.getElementById('fw-totals'),
    fwByCarrier: ()=>document.getElementById('fw-by-carrier'),
    fwHeaviest: ()=>document.getElementById('fw-heaviest'),
    fwUpdated: ()=>document.getElementById('fw-updated'),
    fwRefresh: ()=>document.getElementById('fw-refresh'),
  };

  let CURRENT = { page: 1, pageSize: 50, group: 'open', totalPages: 1 };
  const ZERO_GROUPS = { open: 0, in_motion: 0, arriving: 0, closed: 0 };
  let TOASTED = { stats:false, list:false };

  function renderStats(totals, groups){
    const c = EL.stats(); if(!c) return;
    // Build four primary KPI cards; compute groups from totals if not provided
    const g = groups || (function(){
      const t = totals||{};
      return {
        open: (t.draft||0)+(t.packing||0)+(t.ready_to_send||0),
        in_motion: (t.sent||0)+(t.in_transit||0),
        arriving: (t.receiving||0)+(t.partial||0),
        closed: (t.received||0)+(t.cancelled||0)
      };
    })();
      const meta = [
        {key:'open',     label:'Open',        hint:'Draft + Packing + Ready', cls:'stx-kpi--open',   icon:'<i class="fa fa-wrench" aria-hidden="true"></i>'},
    {key:'in_motion',label:'In Motion',   hint:'Sent + In Transit',       cls:'stx-kpi--motion', icon:'<i class="fa fa-truck" aria-hidden="true"></i>'},
    {key:'arriving', label:'Arriving',    hint:'Receiving + Partial',     cls:'stx-kpi--arrive', icon:'<i class="fa fa-cubes" aria-hidden="true"></i>'},
        {key:'closed',   label:'Closed',      hint:'Received + Cancelled',    cls:'stx-kpi--closed', icon:'<i class="fa fa-check-circle" aria-hidden="true"></i>'},
      ];
    const cards = meta.map(m=>{
      const v = g[m.key]||0;
    return `<div class="col-6 col-md-3 mb-2"><div class="card stx-kpi ${m.cls} shadow-sm"><div class="card-body py-2"><div class="d-flex justify-content-between align-items-center"><div><div class="text-muted text-uppercase" style="font-size:12px">${m.label}</div><div class="d-flex align-items-baseline"><div class="stx-kpi-value">${v}</div><div class="ml-2 text-muted" style="font-size:12px">${m.hint}</div></div></div><div class="stx-kpi-icon" aria-hidden="true">${m.icon}</div></div></div></div></div>`;
    }).join('');
    c.innerHTML = `<div class="row">${cards}</div>`;
  }

  async function loadStats(){
    try{
      const res = await STX.fetchJSON('get_dashboard_stats', {});
      renderStats(res.data.totals||{}, res.data.groups||null);
    }catch(e){
      // Keep the KPI cards visible with zero values if stats fail to load
      renderStats({}, ZERO_GROUPS);
      if (!TOASTED.stats){ STX.emit('toast',{type:'error',text:'Failed to load stats'}); TOASTED.stats = true; }
    }
  }

  function prettyState(s){
    const map = {
      draft:'Draft', packing:'Packing', ready_to_send:'Ready to Send', sent:'Sent', in_transit:'In Transit', receiving:'Receiving', partial:'Partial', received:'Received', cancelled:'Cancelled'
    };
    return map[s]||s;
  }
  function relTime(dateStr){
    try{
      const d = new Date(dateStr);
      const now = new Date();
      const diff = Math.max(0, (now - d) / 1000);
      if (diff < 60) return `${Math.floor(diff)}s ago`;
      if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
      const days = Math.floor(diff/86400);
      return `${days}d ago`;
    }catch(_){ return dateStr; }
  }
  function fmtUpdated(s){
    if(!s) return '';
    try{
      const d = new Date(s);
      const pad = n=> (n<10?('0'+n):n);
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return `${pad(d.getDate())} ${months[d.getMonth()]} ${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }catch(_){ return s; }
  }
  function outletCellName(name, id){
    const nm = (name||'').toString();
    const ident = (id||'').toString();
    return nm || ident || '';
  }
  function rowHtml(r, withCheckbox=false){
    const stateName = prettyState(r.state);
    const badge = `<span class="badge badge-${r.state==='received'?'success':(r.state==='sent'||r.state==='in_transit'?'info':(r.state==='cancelled'?'danger':(r.state==='ready_to_send'?'warning':'secondary')))}" data-state="${r.state}">${stateName}</span>`;
    const selectCell = withCheckbox ? `<td><input type="checkbox" class="stx-row-cb" data-id="${r.transfer_id}"></td>` : '';
    const idCell = `<td><a href="/modules/transfers/stock/pack.php?transfer=${r.transfer_id}">#${r.transfer_id}</a></td>`;
  const fromTxt = outletCellName(r.from_name, r.from);
  const toTxt = outletCellName(r.to_name, r.to);
    const updatedAbs = fmtUpdated(r.updated_at||'');
    const updatedRel = relTime(r.updated_at||'');
    const actions = `<div class="btn-group btn-group-sm" role="group">
      <a class="btn btn-xs btn-outline-primary" href="/modules/transfers/stock/pack.php?transfer=${r.transfer_id}">View</a>
      <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Status</button>
          <div class="dropdown-menu dropdown-menu-right stx-row-menu" data-transfer="${r.transfer_id}">
            <a class="dropdown-item stx-set-status" data-status="packing" href="#">Set Packing</a>
            <a class="dropdown-item stx-set-status" data-status="ready_to_send" href="#">Set Ready</a>
            <a class="dropdown-item stx-set-status" data-status="sent" href="#">Set Sent</a>
            <a class="dropdown-item stx-set-status" data-status="in_transit" href="#">Set In Transit</a>
            <a class="dropdown-item stx-set-status" data-status="received" href="#">Set Received</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-warning stx-row-cancel" href="#">Cancel</a>
            <a class="dropdown-item text-danger stx-row-delete" href="#">Delete</a>
        </div>
      </div>
    </div>`;
    const createdAbs = fmtUpdated(r.created_at||'');
    const createdRel = relTime(r.created_at||'');
    return `<tr>${selectCell}${idCell}<td>${badge}</td><td title="From outlet ID: ${r.from||''}">${fromTxt}</td><td title="To outlet ID: ${r.to||''}">${toTxt}</td><td title="${createdAbs}">${createdRel}</td><td title="${updatedAbs}">${updatedRel}</td><td class="text-right">${actions}</td></tr>`;
  }

  async function loadList(){
    const body = {
      q: (EL.q()?.value||'').trim(),
      state: (EL.state()?.value||'').trim(),
      outlet_from: (EL.from()?.value||'').trim(),
      outlet_to: (EL.to()?.value||'').trim(),
      state_group: '',
      page: CURRENT.page,
      page_size: CURRENT.pageSize,
    };
    try{
      const res = await STX.fetchJSON('list_transfers', body);
      const rows = res.data.rows||[];
      if (rows.length === 0) {
        (EL.table()||{}).innerHTML = `<tr><td colspan="8"><div class=\"stx-empty\">No transfers found. Try adjusting your filters or creating a new transfer.</div></td></tr>`;
      } else {
        (EL.table()||{}).innerHTML = rows.map(r=>rowHtml(r,true)).join('');
      }
  const pg = res.data.pagination||{};
  CURRENT.totalPages = pg.total_pages||1;
  const total = pg.total ?? rows.length ?? 0;
  const pgText = total>0 ? `Page ${pg.page||1} of ${pg.total_pages||1} (${total} results)` : 'Page 0 of 0 (0 results)';
  if(EL.pgStatus()) EL.pgStatus().textContent = pgText;
      if(EL.pgPrev()) EL.pgPrev().disabled = (CURRENT.page<=1);
      if(EL.pgNext()) EL.pgNext().disabled = (CURRENT.page>=CURRENT.totalPages);
    }catch(e){
      if (EL.table()) EL.table().innerHTML = `<tr><td colspan="8"><div class=\"stx-empty\">Couldn\'t load transfers right now. Please refresh in a moment.</div></td></tr>`;
      if (!TOASTED.list){ STX.emit('toast',{type:'error',text:'Failed to load list'}); TOASTED.list = true; }
    }
  }

  async function loadOpen(){
    try{
      const res = await STX.fetchJSON('list_transfers', { state_group: 'open', page: 1, page_size: 20 });
      const rows = res.data.rows||[];
      if (rows.length === 0) {
        (EL.openTable()||{}).innerHTML = `<tr><td colspan=\"7\"><div class=\"stx-empty\">All clear! There are no open transfers right now. <a href=\"/modules/transfers/stock/outgoing.php\" class=\"ml-1\">Create a transfer</a>.</div></td></tr>`;
      } else {
        (EL.openTable()||{}).innerHTML = rows.map(r=>rowHtml(r,false)).join('');
      }
    }catch(e){ /* silent */ }
  }

  let OUTLETS_CACHE = [];
  async function populateFilters(){
    // Statuses
    const statuses = ['', 'draft','packing','ready_to_send','sent','in_transit','receiving','partial','received','cancelled'];
    const sEl = EL.state(); if (sEl){
      sEl.innerHTML = statuses.map(v=>{
        const label = v? prettyState(v) : 'All';
        return `<option value="${v}">${label}</option>`;
      }).join('');
    }
    // Outlets
    try{
      const res = await STX.fetchJSON('list_outlets', {});
      OUTLETS_CACHE = (res.data.outlets||[]);
    }catch(e){
      OUTLETS_CACHE = [];
    }
  }

  function bindTypeahead(inputEl, menuEl){
    if (!inputEl || !menuEl) return;
    let activeIndex = -1;
    const closeMenu = ()=>{ menuEl.style.display='none'; menuEl.innerHTML=''; activeIndex=-1; };
    const render = (items)=>{
      if (!items.length){ closeMenu(); return; }
      menuEl.innerHTML = items.map((o,i)=>{
        const name = (o.name||'');
        const id = (o.id||'');
        return `<div class=\"stx-typeahead-item${i===0?' active':''}\" data-id=\"${id}\" data-name=\"${name}\" title=\"${id}\">${name}</div>`;
      }).join('');
      menuEl.style.display='block';
      activeIndex = 0;
    };
    const showAll = ()=>{
      // Show initial chunk sorted by name
      const items = OUTLETS_CACHE.slice().sort((a,b)=> (a.name||'').localeCompare(b.name||'' )).slice(0,50);
      render(items);
    };
    inputEl.addEventListener('input', ()=>{
      const v = (inputEl.value||'').toLowerCase().trim();
      if (!v){ closeMenu(); return; }
      const items = OUTLETS_CACHE.filter(o=> (o.name||'').toLowerCase().includes(v) || (o.id||'').toLowerCase().includes(v)).slice(0,50);
      render(items);
    });
    inputEl.addEventListener('focus', ()=>{ showAll(); });
    inputEl.addEventListener('dblclick', (e)=>{ e.preventDefault(); showAll(); });
    inputEl.addEventListener('keydown', (e)=>{
      const items = Array.from(menuEl.querySelectorAll('.stx-typeahead-item'));
      if (e.key==='ArrowDown' && menuEl.style.display==='none'){ showAll(); return; }
      if (!items.length) return;
      if (e.key==='ArrowDown'){ e.preventDefault(); activeIndex = Math.min(items.length-1, activeIndex+1); items.forEach((el,i)=> el.classList.toggle('active', i===activeIndex)); }
      if (e.key==='ArrowUp'){ e.preventDefault(); activeIndex = Math.max(0, activeIndex-1); items.forEach((el,i)=> el.classList.toggle('active', i===activeIndex)); }
  if (e.key==='Enter'){ e.preventDefault(); const el = items[activeIndex]; if (el){ inputEl.value = el.getAttribute('data-name')||el.getAttribute('data-id')||''; closeMenu(); inputEl.dispatchEvent(new Event('change')); } }
      if (e.key==='Escape'){ closeMenu(); }
    });
    menuEl.addEventListener('mousedown', (e)=>{
      const item = e.target.closest('.stx-typeahead-item');
      if (item){ inputEl.value = item.getAttribute('data-name')||item.getAttribute('data-id')||''; closeMenu(); inputEl.dispatchEvent(new Event('change')); }
    });
    document.addEventListener('click', (e)=>{ if (!menuEl.contains(e.target) && e.target!==inputEl){ closeMenu(); } });
  }

  function wireFilters(){
    ['keyup','change'].forEach(evt=>{
      [EL.q(), EL.state(), EL.from(), EL.to()].forEach(el=>{ if(el){ el.addEventListener(evt, ()=> loadList()); }});
    });
    // Pagination buttons
    EL.pgPrev()?.addEventListener('click', ()=>{ if(CURRENT.page>1){ CURRENT.page--; loadList(); } });
    EL.pgNext()?.addEventListener('click', ()=>{ if(CURRENT.page<CURRENT.totalPages){ CURRENT.page++; loadList(); } });
    // Bulk select
    EL.selAll()?.addEventListener('change', (e)=>{
      const on = !!EL.selAll().checked;
      document.querySelectorAll('.stx-row-cb').forEach(cb=>{ cb.checked = on; });
    });
    EL.bulkSelectAllBtn()?.addEventListener('click', ()=>{ document.querySelectorAll('.stx-row-cb').forEach(cb=>{ cb.checked = true; }); });
    EL.bulkSelectNoneBtn()?.addEventListener('click', ()=>{ document.querySelectorAll('.stx-row-cb').forEach(cb=>{ cb.checked = false; }); });
    // Open selected in new tabs (limit to 10)
    function openSelected(packonly){
      const ids = Array.from(document.querySelectorAll('.stx-row-cb:checked')).map(cb=>cb.getAttribute('data-id'));
      if (!ids.length){ STX.emit('toast',{type:'info',text:'No transfers selected'}); return; }
      const max = 10; if (ids.length > max){ STX.emit('toast',{type:'warning',text:`Opening first ${max} of ${ids.length}.`}); }
      ids.slice(0, max).forEach(id=>{
        const url = `/modules/transfers/stock/pack.php?transfer=${encodeURIComponent(id)}${packonly?'&packonly=1':''}`;
        window.open(url, '_blank');
      });
    }
    document.getElementById('stx-open-selected')?.addEventListener('click', ()=> openSelected(false));
    document.getElementById('stx-open-selected-packonly')?.addEventListener('click', ()=> openSelected(true));
    // Keyboard shortcuts: Ctrl+A select all, Ctrl+D select none
    document.addEventListener('keydown', (e)=>{
      const t = e.target;
      const isTextInput = t && (t.tagName==='INPUT' || t.tagName==='TEXTAREA' || t.tagName==='SELECT' || t.isContentEditable);
      if (!isTextInput && e.ctrlKey && (e.key==='a' || e.key==='A')){ e.preventDefault(); EL.bulkSelectAllBtn()?.click(); }
      if (!isTextInput && e.ctrlKey && (e.key==='d' || e.key==='D')){ e.preventDefault(); EL.bulkSelectNoneBtn()?.click(); }
    });
    // Bulk actions: status set, cancel, delete
    document.querySelectorAll('[data-status]').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const status = btn.getAttribute('data-status');
        const ids = Array.from(document.querySelectorAll('.stx-row-cb:checked')).map(cb=>cb.getAttribute('data-id'));
        for (const id of ids){
          try{ await STX.fetchJSON('set_status', { transfer_id: id, status }); }catch(e){ /* continue */ }
        }
        loadList();
        loadOpen();
      });
    });
    EL.bulkCancel()?.addEventListener('click', async ()=>{
      const ids = Array.from(document.querySelectorAll('.stx-row-cb:checked')).map(cb=>cb.getAttribute('data-id'));
      if (!ids.length) return;
      if (!confirm(`Cancel ${ids.length} transfer(s)? You can change status back later.`)) return;
      for (const id of ids){ try{ await STX.fetchJSON('cancel_transfer', { transfer_id: id }); }catch(e){} }
      loadList(); loadOpen();
    });
    EL.bulkDelete()?.addEventListener('click', async ()=>{
      const ids = Array.from(document.querySelectorAll('.stx-row-cb:checked')).map(cb=>cb.getAttribute('data-id'));
      if (!ids.length) return;
      if (!confirm(`Delete ${ids.length} transfer(s)? This cannot be undone. Only cancelled transfers can be deleted.`)) return;
      for (const id of ids){ try{ await STX.fetchJSON('delete_transfer', { transfer_id: id }); }catch(e){} }
      loadList(); loadOpen();
    });
    // Row-level status actions
    document.addEventListener('click', async (e)=>{
      const set = e.target.closest('.stx-set-status');
      const del = e.target.closest('.stx-row-delete');
      const can = e.target.closest('.stx-row-cancel');
      if (set){
        e.preventDefault();
        const menu = set.closest('.dropdown-menu');
        const id = menu?.getAttribute('data-transfer');
        const status = set.getAttribute('data-status');
        if (id && status){ try{ await STX.fetchJSON('set_status', { transfer_id: id, status }); }catch(_){} loadList(); loadOpen(); }
      } else if (del){
        e.preventDefault();
        const menu = del.closest('.dropdown-menu');
        const id = menu?.getAttribute('data-transfer');
        if (id && confirm('Delete this transfer? Only cancelled transfers can be deleted.')){ try{ await STX.fetchJSON('delete_transfer', { transfer_id: id }); }catch(_){} loadList(); loadOpen(); }
      } else if (can){
        e.preventDefault();
        const menu = can.closest('.dropdown-menu');
        const id = menu?.getAttribute('data-transfer');
        if (id && confirm('Cancel this transfer?')){ try{ await STX.fetchJSON('cancel_transfer', { transfer_id: id }); }catch(_){} loadList(); loadOpen(); }
      }
    });
    loadOpen();
    loadList();
  }

  window.STXDash = { loadStats, loadList, loadOpen };
  async function loadFreightWidgets(){
    try{
      const res = await STX.fetchJSON('get_freight_widgets', { state_group: 'open', limit: 50 });
      const d = res.data||{};
      if (EL.fwTotals()) {
        const t = d.totals||{};
        EL.fwTotals().innerHTML = `Transfers: <strong>${t.transfers||0}</strong> · Units: <strong>${t.units||0}</strong> · Total: <strong>${(t.kg||0).toFixed? (t.kg).toFixed(2) : t.kg} kg</strong> · Est. Boxes: <strong>${t.est_boxes||0}</strong>`;
      }
      if (EL.fwByCarrier()){
        const rows = (d.by_carrier||[]).map(c=>{
          const cost = c.est_cost==null? '—' : `$${Number(c.est_cost||0).toFixed(2)}`;
          return `<tr><td>${c.name||c.code||''}</td><td class="text-right">${c.count||0}</td><td class="text-right">${Number(c.kg||0).toFixed(2)}</td><td class="text-right">${c.est_boxes||0}</td><td class="text-right">${cost}</td></tr>`;
        });
        EL.fwByCarrier().innerHTML = rows.length? rows.join('') : '<tr><td colspan="5">No data</td></tr>';
      }
      if (EL.fwHeaviest()){
        const rows = (d.top_heaviest||[]).map(h=>`<tr><td><a href="/modules/transfers/stock/pack.php?transfer=${h.transfer_id}">#${h.transfer_id}</a></td><td class="text-right">${Number(h.kg||0).toFixed(2)}</td></tr>`);
        EL.fwHeaviest().innerHTML = rows.length? rows.join('') : '<tr><td colspan="2">No data</td></tr>';
      }
      if (EL.fwUpdated()) EL.fwUpdated().textContent = d.updated_at||'';
    }catch(e){
      if (EL.fwTotals()) EL.fwTotals().textContent = 'Failed to load freight widgets';
      if (EL.fwByCarrier()) EL.fwByCarrier().innerHTML = '<tr><td colspan="5">Error</td></tr>';
      if (EL.fwHeaviest()) EL.fwHeaviest().innerHTML = '<tr><td colspan="2">Error</td></tr>';
    }
  }
  // DOMContentLoaded wiring handled below together with filters/typeahead/activity
    let ACTIVITY_CACHE = [];
    let ACTIVITY_SHOW = 20;
    function fmtTime(s){
      if(!s) return '';
      try{
        const d = new Date(s);
        const pad = n=> (n<10?('0'+n):n);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return `${pad(d.getDate())} ${months[d.getMonth()]} ${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
      }catch(_){ return s; }
    }
    function renderActivity(){
      const items = ACTIVITY_CACHE.slice(0, ACTIVITY_SHOW);
      const html = items.length? `<ul class="list-unstyled mb-0">${items.map(it=>{
        const whenAbs = fmtTime(it.latest_at||'');
        const whenRel = relTime(it.latest_at||'');
        const badgeClass = it.state==='received'?'success':(it.state==='sent'||it.state==='in_transit'?'info':(it.state==='cancelled'?'danger':(it.state==='ready_to_send'?'warning':'secondary')));
  const stateBadge = `<span class="badge badge-${badgeClass}">${prettyState(it.state)}</span>`;
        const flags = it.flag_count>0?`<span class=\"text-danger ml-2\">⚠️ ${it.flag_count}</span>`:'';
        const fromTxt = (it.from_name||it.from||'');
        const toTxt = (it.to_name||it.to||'');
        const pair = fromTxt||toTxt ? ` <span class=\"text-muted\" title=\"${(it.from||'')} → ${(it.to||'')}\">${fromTxt} → ${toTxt}</span>` : '';
        return `<li class=\"mb-2\"><a href=\"/modules/transfers/stock/pack.php?transfer=${it.transfer_id}\">#${it.transfer_id}</a> ${stateBadge} <span class=\"text-muted\" title=\"${whenAbs}\">${whenRel}</span>${flags}${pair}</li>`;
      }).join('')}</ul>` : '<div class="text-muted">No recent activity.</div>';
      const box = EL.activity(); if(box) box.innerHTML = html;
      const moreBtn = document.getElementById('stx-activity-more');
      if (moreBtn) moreBtn.style.display = ACTIVITY_CACHE.length > ACTIVITY_SHOW ? '' : 'none';
    }
    async function loadActivity(refresh=false){
      try{
        const res = await STX.fetchJSON('get_activity', {});
        ACTIVITY_CACHE = res.data.items||[];
        if (refresh) ACTIVITY_SHOW = 20;
        renderActivity();
      }catch(e){ /* silent */ }
    }

    document.addEventListener('DOMContentLoaded', ()=>{
      // Render zero-state KPI cards immediately to avoid empty gap before stats load
      try { renderStats({}, ZERO_GROUPS); } catch(_) {}
      loadStats();
      populateFilters().then(()=>{
        bindTypeahead(EL.from(), EL.taFrom());
        bindTypeahead(EL.to(), EL.taTo());
        wireFilters();
        loadList();
      });
      loadOpen();
      loadActivity(true);
  // Freight widgets
  loadFreightWidgets();
  EL.fwRefresh()?.addEventListener('click', ()=> loadFreightWidgets());
      EL.activityRefresh()?.addEventListener('click', ()=>loadActivity(true));
      document.getElementById('stx-activity-more')?.addEventListener('click', ()=>{ ACTIVITY_SHOW += 20; renderActivity(); });

      // Clear buttons (in-label and inline). Any element with [data-clear] clears the input with that ID
      document.addEventListener('click', (e)=>{
        const btn = e.target.closest('[data-clear]');
        if (!btn) return;
        const id = btn.getAttribute('data-clear');
        const el = id ? document.getElementById(id) : null;
        if (el){ el.value=''; el.dispatchEvent(new Event('change')); el.focus(); }
      });

      // Subtle KPI shine loop: randomly trigger a shine on a random KPI card every 45–90 seconds
      (function kpiShineLoop(){
        const cards = Array.from(document.querySelectorAll('.stx-kpi'));
        if (cards.length){
          const card = cards[Math.floor(Math.random()*cards.length)];
          card.classList.add('stx-shine');
          setTimeout(()=> card.classList.remove('stx-shine'), 1400);
        }
        const next = 45000 + Math.floor(Math.random()*45000); // 45s–90s
        setTimeout(kpiShineLoop, next);
      })();
    });
})();
