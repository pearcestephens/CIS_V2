/* catalog.loader.js */
(function(){
  const root = document.querySelector('.stx-outgoing');
  if(!root || !window.STX) return;
  async function load(){
    try{
      const resp = await STX.fetchJSON('get_shipping_catalog',{});
      const data = resp && resp.data ? resp.data : {}; 
      const carriers = data.carriers||[];
      // Populate NZ Post service select if exists
      const nzSvc = document.getElementById('nzpost-service-type') || document.getElementById('nzpost-service');
      if(nzSvc){
        const nz = carriers.find(c=>c.code==='NZ_POST');
        if(nz && Array.isArray(nz.services)){
          nzSvc.innerHTML = '<option value="">Choose service...</option>' + nz.services.map(s=>`<option value="${s.code}">${s.name||s.code}</option>`).join('');
        }
      }
      // Populate GSS service select if exists
      const gssSvc = document.getElementById('gss-service-type') || document.getElementById('gss-service');
      if(gssSvc){
        const gss = carriers.find(c=>c.code==='GSS');
        if(gss && Array.isArray(gss.services)){
          gssSvc.innerHTML = '<option value="">Choose service...</option>' + gss.services.map(s=>`<option value="${s.code}">${s.name||s.code}</option>`).join('');
        }
      }
    }catch(e){ /* silent */ }
  }
  if(document.readyState==='complete' || document.readyState==='interactive') load(); else document.addEventListener('DOMContentLoaded', load);
})();
