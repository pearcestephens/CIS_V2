/* shipping.gss.js (stock path, migrated) */
(function(){
	const root = document.querySelector('.stx-outgoing');
	if(!root) return;
	root.addEventListener('click', async (e)=>{
		const btn = e.target.closest('[data-action="gss-create-label"]');
		if(!btn) return;
		e.preventDefault();
		const id = root.querySelector('input[name="transferID"], #transferID')?.value;
		try{ const r = await STX.fetchJSON('create_label_gss',{transfer_id:id, packages: '[]', service_code: (root.querySelector('#gssService, #stx-gss-service')?.value||'') });
			const d = r && r.data ? r.data : {};
			STX.emit('label:created', d);
			STX.emit('toast', {type:'success', text:'GSS label created'});
		}catch(err){ STX.emit('toast', {type:'error', text: err.response?.error || err.message}); }
	});
})();
