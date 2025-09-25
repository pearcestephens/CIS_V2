/* printer.js (unified) */
(function(window, document){
  'use strict';

  var STXPrinter = {
    _opts: {
      ajaxUrl: 'https://staff.vapeshed.co.nz/modules/transfers/stock-transfers/ajax/handler.php',
      csrf: '',
      transferId: null,
      onStatus: function(msg){ var el = document.querySelector('.stx-printer__status'); if(el){ el.textContent = msg; } },
      simulate: 0
    },

    init: function(options){
      this._opts = Object.assign({}, this._opts, options || {});
      this._bind();
      this._wireEvents();
      this._opts.onStatus('Ready');
    },

    _bind: function(){
      var root = document.querySelector('.stx-printer');
      if(!root){ return; }
      root.addEventListener('click', this._onClick.bind(this));
    },

    _wireEvents: function(){
      // When backend actions succeed and emit label info
      document.addEventListener('stx:label:created', function(e){
        var data = e.detail || {};
        if(data && data.label_url){
          try { window.open(data.label_url, '_blank'); } catch(err){}
        }
        if(data && data.tracking_number){ STXPrinter._copyToClipboard(data.tracking_number); }
        STXPrinter._opts.onStatus('Label ready' + (data.tracking_number ? (' · ' + data.tracking_number) : ''));
      });
    },

    _onClick: function(evt){
      var btn = evt.target.closest('.stx-action');
      var copyBtn = evt.target.closest('.stx-copy');
      if(btn){
        var action = btn.getAttribute('data-action');
        if(action === 'nzpost.create') return this._nzpostCreate();
        if(action === 'gss.create') return this._gssCreate();
        if(action === 'manual.save') return this._manualSave();
        if(action === 'inhouse.assign') return this._inhouseAssign();
        if(action === 'sticker.print') return this._stickerPrint();
        // no-op: settings are session-only
      }
      if(copyBtn){
        var selector = copyBtn.getAttribute('data-target');
        var input = selector ? document.querySelector(selector) : null;
        if(input){ this._copyToClipboard(input.value || ''); this._opts.onStatus('Copied'); }
      }
    },

    // removed persistence: session-only toggles

    _stickerPrint: function(){
      var boxesEl = document.getElementById('stx-sticker-boxes');
      var boxes = parseInt((boxesEl && boxesEl.value ? boxesEl.value : ''), 10);
      if(!(boxes>0)) boxes = 1;
  var auto = 0; // do not auto-print stickers
      var s = {
        showTracking: !!(document.getElementById('stx-sticker-show-tracking') && document.getElementById('stx-sticker-show-tracking').checked),
        showPacker:   !!(document.getElementById('stx-sticker-show-packer')   && document.getElementById('stx-sticker-show-packer').checked),
        showDate:     !!(document.getElementById('stx-sticker-show-date')     && document.getElementById('stx-sticker-show-date').checked)
      };
      var params = '&boxes=' + boxes + '&auto=' + auto +
                   '&show_tracking=' + (s.showTracking?1:0) +
                   '&show_packer=' + (s.showPacker?1:0) +
                   '&show_date=' + (s.showDate?1:0);
      var url = 'https://staff.vapeshed.co.nz/modules/transfers/stock-transfers/views/sticker.php?transfer=' + encodeURIComponent(this._opts.transferId) + params;
      try { window.open(url, '_blank'); } catch(err){}
      this._opts.onStatus('Sticker opened');
      return false;
    },

    _nzpostCreate: function(){
      var svc = document.getElementById('stx-nzpost-service').value;
      var parcels = parseInt(document.getElementById('stx-nzpost-parcels').value||'1', 10);
      var ref = document.getElementById('stx-nzpost-ref').value||'';
      return this._post('create_label_nzpost', { transfer_id: this._opts.transferId, service: svc, parcels: parcels, reference: ref });
    },

    _gssCreate: function(){
      var svc = document.getElementById('stx-gss-service').value;
      var parcels = parseInt(document.getElementById('stx-gss-parcels').value||'1', 10);
      var ref = document.getElementById('stx-gss-ref').value||'';
      return this._post('create_label_gss', { transfer_id: this._opts.transferId, service: svc, parcels: parcels, reference: ref });
    },

    _manualSave: function(){
      var no = document.getElementById('stx-manual-number').value||'';
      var car = document.getElementById('stx-manual-carrier').value||'other';
      return this._post('save_manual_tracking', { transfer_id: this._opts.transferId, tracking_number: no, carrier: car });
    },

    _inhouseAssign: function(){
      var driver = document.getElementById('stx-inhouse-driver').value||'';
      var eta = document.getElementById('stx-inhouse-eta').value||'';
      // No backend action specified; just emit a log event for now
      var ev = new CustomEvent('stx:inhouse:assigned', { detail: { driver: driver, eta: eta } });
      document.dispatchEvent(ev);
      this._opts.onStatus('In-house assigned: ' + driver + (eta?(' · '+eta):''));
      return false;
    },

    _post: function(ajaxAction, payload){
      var self = this;
      var url = this._opts.ajaxUrl + '?ajax_action=' + encodeURIComponent(ajaxAction);
      var body = new URLSearchParams();
      body.set('csrf_token', this._opts.csrf || '');
      body.set('simulate', String(this._opts.simulate||0));
      Object.keys(payload||{}).forEach(function(k){ body.set(k, payload[k]); });
      self._opts.onStatus('Working…');
      return fetch(url, { method: 'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if(json && json.success){
            // emit event for printer
            var d = (json.data||{});
            if(d.label_url || d.tracking_number){
              var ev = new CustomEvent('stx:label:created', { detail: d });
              document.dispatchEvent(ev);
            }
            self._opts.onStatus('Done');
            return json;
          }
          var msg = (json && json.error && json.error.message) ? json.error.message : 'Action failed';
          self._opts.onStatus(msg);
          return json;
        })
        .catch(function(err){ self._opts.onStatus('Network error'); console.error(err); return { success:false, error:{ message:'Network error' } } });
    },

    _copyToClipboard: function(text){
      try {
        var ta = document.createElement('textarea');
        ta.value = text || '';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
      } catch(e){}
    }
  };

  window.STXPrinter = STXPrinter;
})(window, document);
