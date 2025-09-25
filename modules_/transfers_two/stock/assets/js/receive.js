/* receive.js (stock) */
(function(){
  function parseItems(input){
    const out = {};
    (input||'').split(',').map(s=>s.trim()).filter(Boolean).forEach(pair=>{
      const [sku,qty] = pair.split(':');
      const q = parseInt(qty,10);
      if (sku && !isNaN(q) && q>0) out[sku] = q;
    });
    return out;
  }

  async function submit(final){
    const tid = new URLSearchParams(window.location.search).get('transfer')||'';
    if(!tid){ STX.emit('toast',{type:'error',text:'Missing transfer id'}); return false; }
    const raw = document.getElementById('receive-items')?.value||'';
    const items = parseItems(raw);
    if (!Object.keys(items).length){ STX.emit('toast',{type:'error',text:'Enter items first'}); return false; }
    const action = final ? 'receive_final' : 'receive_partial';
    try{
      await STX.fetchJSON(action, { transfer_id: tid, items: JSON.stringify(items) });
      STX.emit('toast',{type:'success',text: final?'Final received saved':'Partial received saved'});
    }catch(err){
      const msg = err.response?.error || err.message || 'Failed';
      STX.emit('toast',{type:'error',text: msg});
    }
    return false;
  }

  window.STXReceive = { submit };
})();
