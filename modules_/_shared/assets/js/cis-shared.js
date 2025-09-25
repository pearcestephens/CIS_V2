/*
 * https://staff.vapeshed.co.nz/modules/_shared/assets/js/cis-shared.js
 * Purpose: Small shared helpers for CIS modules (data fetch, outlet list, select population).
 * Size: < 25KB
 */
(function(window, document){
  'use strict';
  var CIS = window.CIS = window.CIS || {};

  function fetchJSON(url, opts){
    opts = opts || {};
    if (!opts.headers) opts.headers = {};
    // Default to POST with form-encoded body if opts.body is object (for handler.php conventions)
    return fetch(url, opts).then(function(r){ return r.json().catch(function(){ return {}; }); });
  }

  CIS.fetchJSON = fetchJSON;

  // --- Outlets cache & helpers ------------------------------------------------
  var _outlets = null; // [{id,name}] cached
  CIS.fetchOutlets = function(){
    if (Array.isArray(_outlets)) return Promise.resolve(_outlets);
    // Try preferred AJAX handler (transfers)
    var form = new URLSearchParams();
    form.append('ajax_action','listOutlets');
    return fetch('https://staff.vapeshed.co.nz/modules/transfers/ajax/handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: form.toString()
    })
    .then(function(r){ return r.json().catch(function(){ return {}; }); })
    .then(function(data){
      var list = [];
      if (Array.isArray(data)) list = data;
      else if (data && Array.isArray(data.outlets)) list = data.outlets;
      // Normalize
      list = list.map(function(o){ return { id: (o.id||o.outlet_id||o.value||''), name: (o.name||o.label||'') }; })
                 .filter(function(o){ return o.id && o.name; });
      _outlets = list;
      return _outlets;
    })
    .catch(function(){ _outlets = []; return _outlets; });
  };

  CIS.populateOutletSelects = function(){
    var selectors = Array.prototype.slice.call(arguments);
    return CIS.fetchOutlets().then(function(list){
      selectors.forEach(function(sel){
        var el = (typeof sel === 'string') ? document.querySelector(sel) : sel;
        if (!el) return;
        // If already populated, skip
        if (el.options && el.options.length > 1) return;
        // Clear and add default
        el.innerHTML = '';
        var d = document.createElement('option');
        d.value = '';
        d.textContent = 'Select Your Outlet';
        el.appendChild(d);
        list.forEach(function(o){
          var op = document.createElement('option');
          op.value = o.id;
          op.textContent = o.name;
          el.appendChild(op);
        });
      });
      return list;
    });
  };

})(window, document);
