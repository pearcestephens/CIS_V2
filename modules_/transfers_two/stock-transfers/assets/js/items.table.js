/* items.table.js (unified) */
(function(){
  const root = document.querySelector('.stx-outgoing'); if(!root) return;
  const table = root.querySelector('.stx-table') || document;
  function recalc(){
    let planned=0, counted=0;
    table.querySelectorAll('tbody tr').forEach(tr=>{
      const p = parseFloat(tr.getAttribute('data-planned')||'0')||0; planned+=p;
      const inp = tr.querySelector('[data-behavior="counted-input"]');
      const c = parseFloat(inp?.value||'0')||0; counted+=c;
    });
    const pEl = root.querySelector('#plannedTotal'); const cEl = root.querySelector('#countedTotal');
    if (pEl) pEl.textContent = planned.toFixed(0);
    if (cEl) cEl.textContent = counted.toFixed(0);
  }
  table.addEventListener('input', (e)=>{ if(e.target.closest('[data-behavior="counted-input"]')) recalc(); });
  table.addEventListener('click', (e)=>{
    const rm = e.target.closest('[data-action="remove-product"]'); if(!rm) return;
    e.preventDefault(); const tr = rm.closest('tr'); if(tr){ tr.remove(); recalc(); }
  });
  table.addEventListener('click', (e)=>{
    const fill = e.target.closest('[data-action="fill-planned"]'); if(!fill) return;
    e.preventDefault(); const tr = fill.closest('tr');
    const inp = tr?.querySelector('[data-behavior="counted-input"]');
    const planned = tr ? parseFloat(tr.getAttribute('data-planned')||'0')||0 : 0;
    if (inp) { inp.value = planned; recalc(); }
  });
  document.addEventListener('DOMContentLoaded', recalc);
})();
