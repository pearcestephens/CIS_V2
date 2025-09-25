/* outgoing.init.js (unified) */
(function(){
  const root = document.querySelector('.stx-outgoing');
  if(!root) return;

  function u(url){ return 'https://staff.vapeshed.co.nz/modules/transfers/stock-transfers/assets/js/' + url; }

  function trId(){
    return root.getAttribute('data-transfer-id') ||
      document.getElementById('transferID')?.value ||
      root.querySelector('input[name="transferID"]')?.value || '';
  }

  function bind(){
    root.addEventListener('click', async (e)=>{
      const el = e.target.closest('[data-action="mark-ready"]');
      if(!el) return; e.preventDefault();
      const id = trId();
      try{ await STX.fetchJSON('mark_ready', {transfer_id:id});
        STX.emit('toast', {type:'success', text:'Marked ready'});
        location.reload();
      }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
    });

    root.addEventListener('click', async (e)=>{
      const el = e.target.closest('[data-action="manual-save"]'); if(!el) return; e.preventDefault();
      const id = trId();
      const tracking = document.getElementById('manualTrackingNumber')?.value || '';
      const notes = document.getElementById('manualTrackingNotes')?.value || '';
      try{ await STX.fetchJSON('save_manual_tracking',{transfer_id:id, tracking_number:tracking, notes:notes});
        STX.emit('toast', {type:'success', text:'Saved tracking'});
      }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
    });

    root.addEventListener('click', async (e)=>{
      const btn = e.target.closest('[data-action="sync-shipment"]'); if(!btn) return; e.preventDefault();
      const id = trId();
      // Infer carrier from active tab if possible
      let carrier = '';
      const active = document.querySelector('#shippingTabs .nav-link.active');
      if(active){
        if(active.id && active.id.indexOf('nzpost')!==-1) carrier = 'NZ_POST';
        else if(active.id && active.id.indexOf('gss')!==-1) carrier = 'GSS';
      }
      try{ await STX.fetchJSON('sync_shipment',{transfer_id:id, carrier:carrier}); STX.emit('toast', {type:'success', text:'Shipment synced'}); }
      catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
    });

    // Lazy tabs
    const tabs = root.querySelector('.stx-tabs');
    if(tabs){
      tabs.addEventListener('click', async (e)=>{
        const btn = e.target.closest('[data-tab]'); if(!btn) return;
        const name = btn.getAttribute('data-tab');
        if(name==='nzpost'){ await STX.lazy(u('shipping.np.js')); }
        else if(name==='gss'){ await STX.lazy(u('shipping.gss.js')); }
        else if(name==='manual'){ await STX.lazy(u('shipping.manual.js')); }
        else if(name==='history'){ await STX.lazy(u('history.js')); }
      });
    }

    // Lazy load items table interactions on first input/click
    let tableLoaded = false;
    function ensureTable(){ if(tableLoaded) return Promise.resolve(); tableLoaded = true; return STX.lazy(u('items.table.js')); }
    root.addEventListener('input', (e)=>{ if(e.target.closest('[data-behavior="counted-input"]')) ensureTable(); });
    root.addEventListener('click', (e)=>{ if(e.target.closest('[data-action="remove-product"],[data-action="fill-planned"]')) ensureTable(); });
  }

  document.addEventListener('DOMContentLoaded', function(){
    bind();
    // Load catalog for dynamic service lists
    STX.lazy(u('catalog.loader.js')).catch(()=>{});
    try {
      if (window.STXPrinter){
        const csrf = (document.querySelector('meta[name="csrf-token"]')?.content) || (root.querySelector('input[name="csrf"]')?.value) || '';
        const ajax = (document.querySelector('meta[name="stx-ajax"]')?.content) || (root.querySelector('input[name="stx-ajax"]')?.value) || undefined;
        window.STXPrinter.init({ transferId: trId(), csrf: csrf, ajaxUrl: ajax });
      }
    } catch (e) { /* no-op */ }

    // After any label is created, trigger a backend sync to refresh shipment table accuracy
    if (window.STX && STX.on){
      STX.on('label:created', async ()=>{
        try { await STX.fetchJSON('sync_shipment', { transfer_id: trId() }); STX.emit('toast',{type:'info', text:'Shipment synced'}); } catch(e){ /* silent */ }
      });
    }
  });
})();
