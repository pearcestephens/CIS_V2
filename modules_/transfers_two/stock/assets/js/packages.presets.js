/* packages.presets.js (stock path)
   Provides packaging presets (e.g., satchels/bags E20, boxes) from a JSON file
   at /modules/transfers/stock/assets/data/package_presets.json and utilities to
   apply them to parcel rows and compute estimated cost. */
(function(window, document){
  'use strict';
  var STXPackages = {
    _presets: [],
    _loaded: false,
    _loading: null,
    _dataUrl: 'https://staff.vapeshed.co.nz/modules/transfers/stock/assets/data/package_presets.json',
    load: function(){
      if (this._loaded) return Promise.resolve(this._presets);
      if (this._loading) return this._loading;
      var self=this;
      this._loading = fetch(this._dataUrl, { credentials:'same-origin' })
        .then(function(r){ if(!r.ok) throw new Error('presets not found'); return r.json(); })
        .then(function(json){ if(Array.isArray(json)){ self._presets = json; } self._loaded=true; return self._presets; })
        .catch(function(){ self._presets = []; self._loaded=true; return self._presets; });
      return this._loading;
    },
    getPresets: function(){ return this._presets.slice(); },
    populateDropdowns: function(root){
      var selects = (root||document).querySelectorAll('.stx-preset-select');
      if (!selects.length) return;
      var opts = this._presets;
      for (var i=0;i<selects.length;i++){
        var sel = selects[i];
        // Clear existing options except first
        for (var j=sel.options.length-1;j>0;j--){ sel.remove(j); }
        for (var k=0;k<opts.length;k++){
          var p = opts[k];
          var o = document.createElement('option');
          o.value = p.code || p.id || ('p'+k);
          o.textContent = p.label || (p.name || o.value);
          sel.appendChild(o);
        }
      }
    },
    findPresetByValue: function(val){
      var arr = this._presets || [];
      for (var i=0;i<arr.length;i++){
        var p = arr[i];
        if ((p.code && p.code===val) || (p.id && String(p.id)===String(val))) return p;
      }
      return null;
    },
    applyPresetToRow: function(row, preset){
      if (!row || !preset) return;
      var setNum = function(sel, v){ var el=row.querySelector(sel); if(el){ el.value = (v!=null? v : el.value); var ev=new Event('input',{bubbles:true}); el.dispatchEvent(ev);} };
      // Expected preset fields: weight_kg, width_cm, height_cm, depth_cm
      setNum('.stx-weight', preset.weight_kg!=null ? preset.weight_kg : undefined);
      setNum('.stx-width', preset.width_cm!=null ? preset.width_cm : undefined);
      setNum('.stx-height', preset.height_cm!=null ? preset.height_cm : undefined);
      setNum('.stx-depth', preset.depth_cm!=null ? preset.depth_cm : undefined);
      // optional: default qty 1
      var q=row.querySelector('.stx-qty'); if(q && !q.value){ q.value='1'; }
    },
    computeCostEstimate: function(root){
      root = root || document;
      var rows = root.querySelectorAll('.stx-parcels tr');
      var total = 0;
      for (var i=0;i<rows.length;i++){
        var r = rows[i];
        var qty = parseInt((r.querySelector('.stx-qty')||{}).value||'1',10) || 1;
        var sel = r.querySelector('.stx-preset-select');
        if (!sel || !sel.value) continue;
        var p = STXPackages.findPresetByValue(sel.value);
        if (!p) continue;
        var cost = parseFloat(p.cost_nzd || 0) || 0;
        total += cost * qty;
      }
      var out = root.querySelector('.stx-cost-estimate');
      if (out){ out.textContent = 'Estimated Cost: $' + total.toFixed(2) + ' NZD'; }
      return total;
    }
  };
  window.STXPackages = STXPackages;
  // Auto-load and populate when DOM ready if a printer exists
  document.addEventListener('DOMContentLoaded', function(){
    if (!document.querySelector('.stx-printer')) return;
    STXPackages.load().then(function(){ STXPackages.populateDropdowns(document); STXPackages.computeCostEstimate(document); });
  });
})(window, document);
