/* shipping.np.js (stock path, migrated) */
(function(){
	const root = document.querySelector('.stx-outgoing');
	if(!root) return;
	root.addEventListener('click', async (e)=>{
		const btn = e.target.closest('[data-action="nzpost-create"]');
		if(!btn) return;
		e.preventDefault();
		const id = root.querySelector('input[name="transferID"], #transferID')?.value;
		const pkgEls = root.querySelectorAll('.nzpost-package');
		const pkgs=[]; pkgEls.forEach(el=>{ const w=parseFloat(el.dataset.weight||'0'); pkgs.push({weight:w}); });
		try{ const r = await STX.fetchJSON('create_label_nzpost',{transfer_id:id, packages: JSON.stringify(pkgs), service_code: (root.querySelector('#nzpostService, #stx-nzpost-service')?.value||'') });
			const d = r && r.data ? r.data : {};
			STX.emit('label:created', d);
			STX.emit('toast', {type:'success', text:'NZ Post label created'});
		}catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
	});
})();
