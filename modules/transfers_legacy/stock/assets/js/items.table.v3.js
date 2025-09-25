/* items.table.v3.js — UX-first table interactions */
(function(window, document){
  'use strict';
  var tbl = document.getElementById('stxv3-table'); if (!tbl) return;
  var filter = document.getElementById('stxv3-filter');
  function recompute(){ var rows=tbl.tBodies[0].rows; var items=0, plan=0, count=0; for(var i=0;i<rows.length;i++){ var r=rows[i]; if(r.classList.contains('d-none')) continue; items++; plan += parseInt(r.getAttribute('data-planned')||'0',10)||0; var c=r.querySelector('.stxv3-counted').value; count += parseInt(c||'0',10)||0; } var diff=count-plan; document.getElementById('stxv3-items').textContent=items; document.getElementById('stxv3-plan').textContent=plan; document.getElementById('stxv3-count').textContent=count; document.getElementById('stxv3-diff').textContent=diff; }
  function applyFilter(){ var q=(filter.value||'').toLowerCase(); var rows=tbl.tBodies[0].rows; for(var i=0;i<rows.length;i++){ var r=rows[i]; var name=(r.getAttribute('data-name')||'').toLowerCase(); r.classList.toggle('d-none', q && name.indexOf(q)===-1); } recompute(); }

  // keyboard navigation
  tbl.addEventListener('keydown', function(e){ var inp=e.target.closest('.stxv3-counted'); if(!inp) return; if(e.key==='Enter' || e.key==='ArrowDown'){ e.preventDefault(); var all=[].slice.call(tbl.querySelectorAll('.stxv3-counted')); var idx=all.indexOf(inp); if(idx>-1 && idx<all.length-1){ all[idx+1].focus(); all[idx+1].select(); } }
    if(e.key==='ArrowUp'){ e.preventDefault(); var all=[].slice.call(tbl.querySelectorAll('.stxv3-counted')); var idx=all.indexOf(inp); if(idx>0){ all[idx-1].focus(); all[idx-1].select(); } }
  });

  tbl.addEventListener('input', function(e){ if(e.target.classList.contains('stxv3-counted')){ var inv=parseInt(e.target.closest('tr').getAttribute('data-inv')||'0',10)||0; var v=parseInt(e.target.value||'0',10)||0; if(v>inv) e.target.value=inv; recompute(); }
  });

  // remove row
  tbl.addEventListener('click', function(e){ var btn=e.target.closest('.stxv3-remove'); if(btn){ var tr=btn.closest('tr'); if(confirm('Remove this product from transfer?')){ tr.parentNode.removeChild(tr); recompute(); } }});

  // bulk fill
  var fillBtn=document.getElementById('stxv3-fill'); if(fillBtn){ fillBtn.addEventListener('click', function(){ var rows=tbl.tBodies[0].rows; for(var i=0;i<rows.length;i++){ var r=rows[i]; var planned=parseInt(r.getAttribute('data-planned')||'0',10)||0; var inp=r.querySelector('.stxv3-counted'); if(inp) inp.value=planned; } recompute(); }); }

  // attributes decorate
  function decorate(){ var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||''; var ajax='https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php'; var ids=[]; var rows=tbl.tBodies[0].rows; for(var i=0;i<rows.length;i++){ var pid=rows[i].getAttribute('data-pid'); if(pid) ids.push(pid); }
    if(!ids.length) return; var body=new URLSearchParams(); body.set('csrf_token', csrf); body.set('product_ids', JSON.stringify(ids)); fetch(ajax+'?ajax_action=get_product_attributes', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body: body.toString() }).then(function(r){ return r.json(); }).then(function(j){ if(!j||!j.success) return; var a=j.data&&j.data.attributes||{}; for(var i=0;i<rows.length;i++){ var r=rows[i]; var pid=r.getAttribute('data-pid'); var at=a[pid]||{}; var tags=[]; if(at.is_battery) tags.push('Battery'); if(at.fragile) tags.push('Fragile'); if(at.hazmat) tags.push('Hazmat'); r.querySelector('.stxv3-tags').textContent = tags.length? tags.join(', ') : '-'; }
    });
  }

  if(filter){ filter.addEventListener('input', applyFilter); }
  recompute(); decorate();
})(window, document);
