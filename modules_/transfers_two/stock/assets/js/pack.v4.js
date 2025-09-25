/* pack.v4.js — controller for Pack V4 */
(function(){
  const root = document.querySelector('.stx-pack-v4'); if (!root) return;
  const transferId = (root.querySelector('#transferID')||{}).value||'';

  function recalc(){
    let plan=0, pack=0, remain=0;
    root.querySelectorAll('#stxv4-items-table tbody tr').forEach(tr=>{
      const p = parseInt(tr.querySelector('.stxv4-planned')?.getAttribute('data-val')||'0',10)||0;
      const v = parseInt(tr.querySelector('.stxv4-packed')?.value||'0',10)||0;
      const r = Math.max(0, p - v);
      tr.querySelector('.stxv4-remain').textContent = r.toLocaleString();
      plan += p; pack += Math.min(v,p); remain += Math.max(0, p - Math.min(v,p));
    });
    const set = (id,val)=>{ const el=root.querySelector('#'+id); if(el) el.textContent = (val||0).toLocaleString(); };
    set('stxv4-total-plan', plan); set('stxv4-total-pack', pack); set('stxv4-total-remain', remain);
  }

  function pickRow(tr, qty){
    // For now we keep it client-side (UX) and rely on finalize/pack actions to persist.
    const inp = tr.querySelector('.stxv4-packed'); if(!inp) return;
    if (typeof qty==='number') inp.value = Math.max(0, qty);
    recalc();
  }

  function collect(){
    const items=[];
    root.querySelectorAll('#stxv4-items-table tbody tr').forEach(tr=>{
      const pid = tr.getAttribute('data-pid')||'';
      const p = parseInt(tr.querySelector('.stxv4-planned')?.getAttribute('data-val')||'0',10)||0;
      const v = parseInt(tr.querySelector('.stxv4-packed')?.value||'0',10)||0;
      if (!pid) return; items.push({ product_id: pid, qty: Math.max(0, Math.min(v,p)) });
    });
    return items;
  }

  async function packAll(){
    try{
      await STX.fetchJSON('pack_goods', { transfer_id: transferId, items: JSON.stringify(collect()) });
      STX.emit('toast',{type:'success',text:'Packed'});
    }catch(err){ STX.emit('toast',{type:'error',text: err.response?.error || err.message}); }
  }

  // Wire toolbar
  root.querySelector('#stxv4-print-pick')?.addEventListener('click', ()=>{
    const url = `/modules/transfers/stock/print/picking_sheet.php?transfer=${encodeURIComponent(transferId)}`;
    window.open(url, '_blank');
  });
  root.querySelector('#stxv4-open-printer')?.addEventListener('click', ()=>{
    const el = document.getElementById('stx-printer-v2'); if (el){ el.scrollIntoView({behavior:'smooth', block:'start'}); el.classList.add('u-focusable'); el.focus({preventScroll:true}); STX.emit('toast',{type:'info',text:'Jumped to Labels'}); setTimeout(()=> el.classList.remove('u-focusable'), 800); }
  });

  // Table interactions
  root.addEventListener('click', (e)=>{
    const dec = e.target.closest('.stxv4-dec'); if (dec){ e.preventDefault(); const tr = dec.closest('tr'); const inp = tr.querySelector('.stxv4-packed'); if(!inp) return; const v = parseInt(inp.value||'0',10)||0; inp.value = Math.max(0, v-1); recalc(); return; }
    const inc = e.target.closest('.stxv4-inc'); if (inc){ e.preventDefault(); const tr = inc.closest('tr'); const inp = tr.querySelector('.stxv4-packed'); const max = parseInt(tr.querySelector('.stxv4-planned')?.getAttribute('data-val')||'0',10)||0; const v = parseInt(inp.value||'0',10)||0; inp.value = Math.min(max, v+1); recalc(); return; }
    const rm = e.target.closest('.stxv4-remove'); if (rm){ e.preventDefault(); const tr = rm.closest('tr'); tr.remove(); recalc(); return; }
    const pk = e.target.closest('.stxv4-packrow'); if (pk){ e.preventDefault(); const tr = pk.closest('tr'); const inp = tr.querySelector('.stxv4-packed'); const max = parseInt(tr.querySelector('.stxv4-planned')?.getAttribute('data-val')||'0',10)||0; inp.value = max; recalc(); return; }
  });
  root.addEventListener('input', (e)=>{ if (e.target.matches('.stxv4-packed')) recalc(); });

  // Footer actions
  root.querySelector('#stxv4-pack-all')?.addEventListener('click', (e)=>{ e.preventDefault(); packAll(); });
  root.querySelector('#stxv4-save')?.addEventListener('click', (e)=>{ e.preventDefault(); STX.emit('toast',{type:'info',text:'Saved (client)'}); });

  // Scan/Enter-to-add placeholder (non-intrusive)
  const scan = root.querySelector('#stxv4-scan');
  scan?.addEventListener('keydown', (e)=>{
    if (e.key==='Enter'){ e.preventDefault(); STX.emit('toast',{type:'info',text:'Add via scan coming soon'}); }
  });

  // Initial totals
  recalc();
})();
