/* ==========================================================================
   Transfers — Shipping & Labels (Pack View)
   Depends: jQuery, /assets/js/transfers-common.js
   ========================================================================== */
(function(window,$){
  'use strict';
  if (!window.CIS || !$) return;

  // Remember last-used carrier
  function getCarrierPref() { try { return localStorage.getItem('vs_carrier_pref') || ''; } catch(e){ return ''; } }
  function setCarrierPref(v) { try { localStorage.setItem('vs_carrier_pref', v); } catch(e){} }

  function slTbody(){ return $('#sl-packages tbody'); }
  function slAddRow(t){ t = t||{name:'Box', L:30,W:20,H:15,KG:2.5};
    slTbody().append(
      '<tr>'
      +'<td><input class="form-control form-control-sm sl-name" value="'+t.name+'"></td>'
      +'<td><input class="form-control form-control-sm sl-l" type="number" step="0.1" min="0" value="'+t.L+'"></td>'
      +'<td><input class="form-control form-control-sm sl-w" type="number" step="0.1" min="0" value="'+t.W+'"></td>'
      +'<td><input class="form-control form-control-sm sl-h" type="number" step="0.1" min="0" value="'+t.H+'"></td>'
      +'<td><input class="form-control form-control-sm sl-kg" type="number" step="0.01" min="0" value="'+t.KG+'"></td>'
      +'<td class="text-end"><button type="button" class="btn btn-outline-secondary btn-sm sl-remove">Remove</button></td>'
      +'</tr>');
  }
  function slCollectPkgs(){
    var pk = [];
    slTbody().find('tr').each(function(){
      var $r = $(this);
      pk.push({
        name: $r.find('.sl-name').val()||'Box',
        length_cm: parseFloat($r.find('.sl-l').val()||'0'),
        width_cm:  parseFloat($r.find('.sl-w').val()||'0'),
        height_cm: parseFloat($r.find('.sl-h').val()||'0'),
        weight_kg: parseFloat($r.find('.sl-kg').val()||'0')
      });
    });
    return pk;
  }
  function slCollectRecipient(){
    return {
      name:     $('#sl-name').val(),
      company:  $('#sl-company').val(),
      email:    $('#sl-email').val(),
      phone:    $('#sl-phone').val(),
      street1:  $('#sl-street1').val(),
      street2:  $('#sl-street2').val(),
      suburb:   $('#sl-suburb').val(),
      city:     $('#sl-city').val(),
      state:    $('#sl-state').val(),
      postcode: $('#sl-postcode').val(),
      country:  $('#sl-country').val() || 'NZ'
    };
  }

  function slBootstrap() {
    var tid = parseInt($('#transferID').val()||'0',10);
    $.getJSON('/modules/transfers/stock/api/carrier_capabilities.php', {transfer: tid})
      .done(function(res){
        if (!res || !res.success) return;
        var pref = getCarrierPref();
        var hasGSS = !!res.carriers.has_gss;
        var hasNZP = !!res.carriers.has_nz_post;
        var def   = res.carriers.default;

        var carrier = pref || def;
        if (!hasGSS && carrier==='gss') carrier = hasNZP ? 'nz_post' : 'manual';
        if (!hasNZP && carrier==='nz_post') carrier = hasGSS ? 'gss' : 'manual';

        $('#sl-carrier').val(carrier);

        // seed one box if empty
        if (slTbody().find('tr').length === 0) slAddRow();

        // default recipient (destination)
        window.DEFAULT_RECIPIENT = res.destination.address || {};

        // wire built-in slip buttons to latest shipment
        var url = '/modules/transfers/stock/print/box_slip.php?transfer='+tid+'&shipment=latest';
        $('#sl-print-slips, #btn-preview-labels, #btn-open-label-window, #btn-print-labels').each(function(){
          $(this).off('click').on('click', function(){ window.open(url, '_blank'); });
        });
      });
  }

  // Handlers
  $(document).on('click','#sl-add',function(){ slAddRow(); });
  $(document).on('click','#sl-copy',function(){
    var $last = slTbody().find('tr').last();
    if (!$last.length) { slAddRow(); return; }
    slAddRow({
      name:$last.find('.sl-name').val(),
      L:parseFloat($last.find('.sl-l').val()||'0'),
      W:parseFloat($last.find('.sl-w').val()||'0'),
      H:parseFloat($last.find('.sl-h').val()||'0'),
      KG:parseFloat($last.find('.sl-kg').val()||'0')
    });
  });
  $(document).on('click','#sl-clear',function(){ slTbody().empty(); });
  $(document).on('click','.sl-remove',function(){ $(this).closest('tr').remove(); });
  $(document).on('change','#sl-carrier',function(){ setCarrierPref($(this).val()); });
  $(document).on('click','#sl-override',function(){ $('#sl-address').toggleClass('d-none'); });

  $(document).on('click','#sl-create',function(){
    var tid = parseInt($('#transferID').val()||'0',10);
    var payload = {
      transfer_id: tid,
      delivery_mode: $('#sl-delivery-mode').val(),
      carrier: $('#sl-carrier').val(),
      service_code: $('#sl-service').val().trim(),
      options: {
        signature: $('#sl-signature').is(':checked'),
        saturday:  $('#sl-saturday').is(':checked'),
        atl:       $('#sl-atl').is(':checked'),
        instructions: $('#sl-instructions').val(),
        printer: $('#sl-printer').val()
      },
      packages: slCollectPkgs()
    };
    if (!$('#sl-address').hasClass('d-none')) payload.recipient = slCollectRecipient();

    var $btn = $('#sl-create');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');
    $('#sl-feedback').text('Creating labels...');

    $.ajax({
      url: '/modules/transfers/stock/api/create_label.php',
      type: 'POST',
      dataType: 'json',
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify(payload)
    }).done(function(res){
      if (res && res.success) {
        var list = (res.tracking||[]).map(function(t){
          var code = t.code || '';
          var url  = t.url  || '#';
          return '<a target="_blank" href="'+url+'">'+code+'</a>';
        }).join(', ');
        $('#sl-feedback').html('Done. Tracking: ' + (list || '—'));
        // refresh slips link to pick the latest shipment
        var url = '/modules/transfers/stock/print/box_slip.php?transfer='+tid+'&shipment=latest';
        $('#sl-print-slips').attr('href', url);
      } else {
        var msg = (res && (res.error || res.message)) || 'Create failed';
        $('#sl-feedback').text('Error: ' + msg);
      }
    }).fail(function(xhr){
      var msg = 'Server error';
      if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
      $('#sl-feedback').text(msg);
    }).always(function(){
      $btn.prop('disabled', false).html('Create Labels');
    });
  });

  // Ensure the Pack "Save" JSON includes selected carrier (honoured server-side)
  var _origMark = window.markReadyForDelivery;
  if (typeof _origMark === 'function') {
    window.markReadyForDelivery = function () {
      window.__PACK_SELECTED_CARRIER__ = $('#sl-carrier').val() || 'NZ_POST';
      return _origMark.apply(this, arguments);
    };
  }

  // init
  $(function(){ slBootstrap(); });

})(window, window.jQuery);


// Hook Pack "Save" to carry carrier selection (used by TransfersService::savePack)
(function(window,$){
  'use strict';
  if (!window.CIS || !$) return;

  // ensure markReadyForDelivery payload carries carrier
  var _origMark = window.markReadyForDelivery;
  if (typeof _origMark === 'function') {
    window.markReadyForDelivery = function () {
      window.__PACK_SELECTED_CARRIER__ = $('#sl-carrier').val() || 'NZ_POST';
      return _origMark.apply(this, arguments);
    };
  }

  // Box-slip buttons
  $(document).on('click','#btn-preview-labels', function(){
    var tid = parseInt($('#transferID').val()||'0',10);
    window.open('/modules/transfers/stock/print/box_slip.php?transfer='+tid+'&shipment=latest','_blank');
  });
  $(document).on('click','#btn-open-label-window', function(){
    var tid = parseInt($('#transferID').val()||'0',10);
    window.open('/modules/transfers/stock/print/box_slip.php?transfer='+tid+'&shipment=latest','_blank');
  });
  $(document).on('click','#btn-print-labels', function(){
    // Open the slips window and try to auto-print (box_slip.php calls window.print())
    var tid = parseInt($('#transferID').val()||'0',10);
    window.open('/modules/transfers/stock/print/box_slip.php?transfer='+tid+'&shipment=latest','_blank');
  });
})(window, window.jQuery);



