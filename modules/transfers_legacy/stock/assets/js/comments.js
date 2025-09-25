/* comments.js - client for transfer comments */
(function(){
  const root = document.querySelector('#stx-comments'); if(!root) return;
  const tid = root.getAttribute('data-transfer-id') || document.getElementById('transferID')?.value || '';
  const list = root.querySelector('.stx-comments-list');
  const form = root.querySelector('.stx-comments-form');
  const input = form?.querySelector('input[name="note"]');
  const csrf = (document.querySelector('meta[name="csrf-token"]')?.content) || (document.querySelector('.stx-outgoing input[name="csrf"]')?.value) || '';

  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;','\'':'&#39;'}[c]||c)); }
  function timeago(ts){ try{ const d=new Date(ts); const diff=(Date.now()-d.getTime())/1000; if(diff<60) return 'just now'; if(diff<3600) return Math.floor(diff/60)+'m ago'; if(diff<86400) return Math.floor(diff/3600)+'h ago'; return d.toLocaleString(); }catch(_){ return ts; } }

  async function fetchComments(){
    try{
      const res = await STX.fetchJSON('list_comments', { transfer_id: tid, limit: 100 });
      const items = Array.isArray(res?.data?.items) ? res.data.items : [];
      render(items);
    }catch(e){ list.innerHTML = '<div class="text-danger small">Failed to load comments</div>'; }
  }
  function render(items){
    if (!list) return;
    if (!items.length){ list.innerHTML = '<div class="text-muted small">No comments yet.</div>'; return; }
    list.innerHTML = items.map(r=>{
      const user = esc(r.username || ('User #'+(r.user_id||'')));
      const note = esc(r.note||'');
      const when = esc(r.created_at||'');
      return `<div class="stx-comment py-1" role="listitem"><strong>${user}</strong> <span class="text-muted">${timeago(when)}</span><br>${note}</div>`;
    }).join('');
  }
  form?.addEventListener('submit', async (e)=>{
    e.preventDefault(); const note=(input?.value||'').trim(); if(!note) return;
    try{
      await STX.fetchJSON('add_comment', { transfer_id: tid, note, csrf });
      input.value=''; await fetchComments();
    }catch(err){ STX.toast?.({ type:'error', text: err.message||'Failed to post' }); }
  });

  document.addEventListener('DOMContentLoaded', fetchComments);
})();
