/* pack.js (unified) */
(function(){
  function collectItems(){
    const root = document.querySelector('.stx-outgoing'); if(!root) return [];
    const rows = root.querySelectorAll('#transfer-table tbody tr');
    const out = [];
    rows.forEach(tr=>{
      const pid = tr.querySelector('.productID')?.value || '';
      const inp = tr.querySelector('[data-behavior="counted-input"]');
      const qty = parseFloat(inp?.value||'0')||0; if (!pid) return; if (qty<=0) return;
      out.push({ product_id: pid, qty_picked: qty });
    });
    return out;
  }

  async function packGoods(){
    const root = document.querySelector('.stx-outgoing'); if(!root) return;
    const tid = document.getElementById('transferID')?.value || '';
    const items = collectItems();
    if (!items.length){ STX.emit('toast',{type:'error', text:'No counted quantities entered'}); return; }
    try{
      await STX.fetchJSON('pack_goods', { transfer_id: tid, items: JSON.stringify(items) });
      STX.emit('toast', {type:'success', text:'Packed goods saved'});
    }catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
  }

  async function sendTransfer(force){
    const tid = document.getElementById('transferID')?.value || '';
    try{
      await STX.fetchJSON('send_transfer', { transfer_id: tid, force: force? '1':'0' });
      STX.emit('toast', {type:'success', text:'Transfer sent'});
      window.location.reload();
    }catch(err){
      const msg = err.response?.error || err.message || 'Failed to send';
      STX.emit('toast', {type:'error', text: msg});
      const btn = document.querySelector('[data-action="force-send"]'); if(btn) btn.classList.remove('d-none');
    }
  }

  window.STXPack = { collectItems, packGoods, sendTransfer };
})();
