/* proxy to unified ajax (stock) */
(function(){
  const CORE = {};
  function discoverAjaxUrl(){
    const m = document.querySelector('meta[name="stx-ajax"]'); if (m && m.content) return m.content;
    const i = document.querySelector('input[name="stx-ajax"]'); if (i && i.value) return i.value;
    const guessFrom = (function(){
      const cs = document.currentScript; if (cs && cs.src) return cs.src;
      const arr = Array.prototype.slice.call(document.getElementsByTagName('script'));
      const hit = arr.find(s=> (s && s.src && s.src.indexOf('/modules/transfers/stock/assets/js/core.js')!==-1));
      return hit ? hit.src : '';
    })();
    if (guessFrom){ try { const u = new URL(guessFrom, window.location.origin); const origin = u.origin; const base = u.pathname.replace(/assets\/js\/core\.js.*$/, ''); return origin + base + 'ajax/handler.php'; } catch(_){} }
    return (window.location.origin || '') + '/modules/transfers/stock/ajax/handler.php';
  }
  const ajaxUrl = discoverAjaxUrl();
  function getCsrf(){ const m = document.querySelector('meta[name="csrf-token"]'); if (m) return m.content; const i = document.querySelector('input[name="csrf"]'); const j = document.querySelector('input[name="csrf_token"]'); return (i?i.value:'') || (j?j.value:''); }
  CORE.fetchJSON = async function(action, data){ const fd = new FormData(); fd.set('ajax_action', action); fd.set('csrf', getCsrf()); for (const k in (data||{})) fd.set(k, data[k]); const res = await fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' }); const json = await res.json().catch(()=>({success:false,error:'Invalid JSON'})); if(!res.ok || !json.success){ const err = new Error((json && json.error && json.error.message) ? json.error.message : (json.error||('HTTP '+res.status))); err.response=json; throw err; } return json; };
  CORE.on = function(evt, cb){ document.addEventListener('stx:'+evt, cb); };
  CORE.emit = function(evt, detail){ document.dispatchEvent(new CustomEvent('stx:'+evt, {detail})); };
  CORE.loadScript = function(url){ return new Promise((resolve, reject)=>{ if (document.querySelector('script[src="'+url+'"]')) return resolve(); const s = document.createElement('script'); s.src = url; s.async = true; s.onload = resolve; s.onerror = ()=>reject(new Error('Failed to load '+url)); document.head.appendChild(s); }); };
  CORE.lazy = CORE.loadScript;
  (function(){ const wrapId = 'stx-toast-wrap'; function ensureWrap(){ let w = document.getElementById(wrapId); if(!w){ w = document.createElement('div'); w.id = wrapId; w.style.cssText = 'position:fixed;right:12px;bottom:12px;z-index:9999;display:flex;flex-direction:column;gap:8px;'; document.body.appendChild(w);} return w; } CORE.toast = function(opts){ const o = Object.assign({type:'info', text:''}, opts||{}); const el = document.createElement('div'); const bg = o.type==='error'?'#f8d7da':(o.type==='success'?'#d4edda':'#d1ecf1'); const col = o.type==='error'?'#721c24':(o.type==='success'?'#155724':'#0c5460'); el.style.cssText = 'background:'+bg+';color:'+col+';padding:8px 12px;border-radius:4px;box-shadow:0 2px 6px rgba(0,0,0,.15);max-width:320px;font-size:13px;'; el.textContent = o.text || ''; const w = ensureWrap(); w.appendChild(el); setTimeout(()=>{ el.remove(); }, 3000); }; document.addEventListener('stx:toast', (e)=> CORE.toast(e.detail||{})); })();
  window.STX = CORE;
})();
