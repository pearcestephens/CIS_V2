/* pack.init.js (unified) */
(function(){
  const root = document.querySelector('.stx-outgoing'); if(!root) return;
  function u(url){ return 'https://staff.vapeshed.co.nz/modules/transfers/stock-transfers/assets/js/' + url; }

  function ensureTable(){ if (ensureTable._p) return ensureTable._p; ensureTable._p = STX.lazy(u('items.table.js')); return ensureTable._p; }

  function bind(){
    // Pack and send buttons
    root.addEventListener('click', async (e)=>{
      const b = e.target.closest('[data-action="pack-goods"]'); if(!b) return; e.preventDefault();
      await ensureTable(); STXPack.packGoods();
    });
    root.addEventListener('click', async (e)=>{
      const b = e.target.closest('[data-action="send-transfer"]'); if(!b) return; e.preventDefault();
      STXPack.sendTransfer(false);
    });
    root.addEventListener('click', async (e)=>{
      const b = e.target.closest('[data-action="force-send"]'); if(!b) return; e.preventDefault();
      STXPack.sendTransfer(true);
    });

    // Toggle tracking section
    function toggleTracking(){
      const val = root.querySelector('input[name="delivery-mode"]:checked')?.value;
      const sec = document.getElementById('tracking-section'); if(!sec) return;
      sec.style.display = (val === 'courier') ? '' : 'none';
    }
    root.addEventListener('change', (e)=>{ if(e.target.matches('[data-action="toggle-tracking"], input[name="delivery-mode"]')) toggleTracking(); });
    document.addEventListener('DOMContentLoaded', toggleTracking);

    // Load table JS on first input/click in items table
    root.addEventListener('input', (e)=>{ if(e.target.closest('[data-behavior="counted-input"]')) ensureTable(); });
    root.addEventListener('click', (e)=>{ if(e.target.closest('[data-action="remove-product"],[data-action="fill-planned"]')) ensureTable(); });

    // Shipping tab lazy-loader reuse from outgoing
    root.addEventListener('click', async (e)=>{
      const a = e.target.closest('[data-tab]'); if(!a) return; const name = a.getAttribute('data-tab');
      if(name==='nzpost'){ await STX.lazy(u('shipping.np.js')); }
      else if(name==='gss'){ await STX.lazy(u('shipping.gss.js')); }
      else if(name==='manual'){ await STX.lazy(u('shipping.manual.js')); }
      else if(name==='history'){ await STX.lazy(u('history.js')); }
    });

    // Initialize printer if present
    try {
      if (window.STXPrinter){
        const csrf = (document.querySelector('meta[name="csrf-token"]')?.content) || (root.querySelector('input[name="csrf"]')?.value) || '';
        const ajax = (document.querySelector('meta[name="stx-ajax"]')?.content) || (root.querySelector('input[name="stx-ajax"]')?.value) || undefined;
        const tid = document.getElementById('transferID')?.value || '';
        window.STXPrinter.init({ transferId: tid, csrf: csrf, ajaxUrl: ajax });
      }
    } catch (e) { /* no-op */ }
  }

  document.addEventListener('DOMContentLoaded', bind);
})();
