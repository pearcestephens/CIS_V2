/* ==========================================================================
   CIS Transfers — PACK Page
   Depends: jQuery, /assets/js/transfers-common.js
   ========================================================================== */
(function (window, $) {
  'use strict';

  if (!window.CIS || !window.CIS.http) {
    console.error('[Transfers/Pack] CIS common not loaded.');
    return;
  }

  var CIS = window.CIS;
  var Pack = {};
  var $table, draftKey, autosaveTimer = null;

  // --- DOM helpers ----------------------------------------------------------
  function q(sel) { return document.querySelector(sel); }
  function qa(sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); }

  // --- Number input bounds/enforcement --------------------------------------
  function enforceBounds(input) {
    var max = parseInt(input.getAttribute('max'), 10); if (!isFinite(max)) max = 999999;
    var min = parseInt(input.getAttribute('min'), 10); if (!isFinite(min)) min = 0;
    var v = parseInt(input.value, 10);
    if (isFinite(v)) {
      if (v > max) input.value = String(max);
      if (v < min) input.value = String(min);
    }
  }

  function syncPrintValue(input) {
    var sib = input && input.parentElement ? input.parentElement.querySelector('.counted-print-value') : null;
    if (sib) sib.textContent = input.value || '0';
  }

  function checkInvalidQty(input) {
    var $row = $(input).closest('tr');
    var inventory = parseInt($row.attr('data-inventory'), 10) || 0;
    var planned = parseInt($row.attr('data-planned'), 10) || 0;
    var raw = String(input.value || '').trim();

    function addZeroBadge() {
      if ($row.find('.badge-to-remove').length === 0) {
        $row.find('.counted-td').append('<span class="badge badge-to-remove">Will remove at submit</span>');
      }
      $row.addClass('table-secondary');
    }
    function removeZeroBadge() {
      $row.find('.badge-to-remove').remove();
      $row.removeClass('table-secondary');
    }

    if (raw === '') {
      $(input).addClass('is-invalid').removeClass('is-warning');
      $row.addClass('table-warning');
      removeZeroBadge();
      return;
    }

    var counted = Number(raw);
    if (!isFinite(counted) || counted < 0) {
      $(input).addClass('is-invalid').removeClass('is-warning');
      $row.addClass('table-warning');
      removeZeroBadge();
      return;
    }
    if (counted === 0) {
      $(input).removeClass('is-invalid is-warning'); $row.removeClass('table-warning');
      addZeroBadge();
      return;
    }
    if (inventory > 0 && counted > inventory) {
      $(input).addClass('is-invalid').removeClass('is-warning');
      $row.addClass('table-warning');
      removeZeroBadge();
      return;
    }

    var suspicious = (counted >= 99) || (planned > 0 && counted >= planned * 3) || (inventory > 0 && counted >= inventory * 2);
    if (suspicious) {
      $(input).removeClass('is-invalid').addClass('is-warning');
      $row.removeClass('table-warning');
      removeZeroBadge();
    } else {
      $(input).removeClass('is-invalid is-warning');
      $row.removeClass('table-warning');
      removeZeroBadge();
    }
  }

  // --- Totals recompute ------------------------------------------------------
  function recomputeTotals() {
    var plannedTotal = 0, countedTotal = 0, rows = 0;
    $table.find('tbody tr').each(function () {
      var $r = $(this);
      plannedTotal += parseInt($r.attr('data-planned'), 10) || 0;
      var v = parseInt($r.find('input[type="number"]').val(), 10) || 0;
      countedTotal += v;
      rows++;
    });
    var diff = countedTotal - plannedTotal;

    $('#plannedTotal').text(plannedTotal.toLocaleString());
    $('#countedTotal').text(countedTotal.toLocaleString());
    $('#diffTotal').text(diff.toLocaleString())
      .css('color', diff > 0 ? '#dc3545' : diff < 0 ? '#fd7e14' : '#28a745')
      ;
    $('#itemsToTransfer').text(rows);
  }

  // --- Row ops ---------------------------------------------------------------
  function removeProduct(el) {
    if (!confirm('Remove this product from the transfer?')) return;
    $(el).closest('tr').remove();
    recomputeTotals();
    addToLocalStorage();
  }

  function autofillCountedFromPlanned() {
    $table.find('tbody tr').each(function () {
      var $r = $(this);
      var input = $r.find('input[type="number"]')[0];
      var planned = parseInt($r.attr('data-planned'), 10) || 0;
      var inventory = parseInt($r.attr('data-inventory'), 10) || 0;
      var val = Math.min(planned, inventory);
      input.value = String(val);
      syncPrintValue(input);
      checkInvalidQty(input);
    });
    recomputeTotals();
    addToLocalStorage();
  }

  // --- Draft / localStorage --------------------------------------------------
  function buildDraft() {
    var quantities = {};
    $table.find('tbody tr').each(function () {
      var $r = $(this);
      var productID = $r.find('.productID').val() || $r.find('input[data-item]').data('item');
      var counted = $r.find('input[type="number"]').val();
      if (productID && counted !== '') quantities[productID] = counted;
    });

    var trackingNumbers = [];
    $('#tracking-items .tracking-input').each(function () {
      var v = String($(this).val() || '').trim();
      if (v) trackingNumbers.push(v);
    });

    return {
      quantities: quantities,
      notes: $('#notesForTransfer').val() || '',
      deliveryMode: $('input[name="delivery-mode"]:checked').val() || 'courier',
      trackingNumbers: trackingNumbers,
      timestamp: Date.now()
    };
  }

  function addToLocalStorage() {
    try {
      var data = buildDraft();
      localStorage.setItem(draftKey, CIS.util.safeStringify(data));
      $('#draft-status').text('Draft: Saved').removeClass('badge-secondary').addClass('badge-success');
      $('#btn-restore-draft, #btn-discard-draft').prop('disabled', false);
      $('#draft-last-saved').text('Last saved: ' + new Date(data.timestamp).toLocaleTimeString());
    } catch (e) {
      console.warn('[Transfers/Pack] draft save failed', e);
    }
  }

  function loadStoredValues() {
    try {
      var raw = localStorage.getItem(draftKey);
      if (!raw) return;
      var data = CIS.util.safeParse(raw, null);
      if (!data) return;

      if (data.quantities) {
        $table.find('tbody tr').each(function () {
          var $r = $(this);
          var productID = $r.find('.productID').val() || $r.find('input[data-item]').data('item');
          var val = data.quantities[productID];
          if (typeof val !== 'undefined') {
            var input = $r.find('input[type="number"]')[0];
            input.value = String(val);
            syncPrintValue(input);
            checkInvalidQty(input);
          }
        });
      }
      if (data.notes) $('#notesForTransfer').val(String(data.notes));
      if (data.deliveryMode) $('input[name="delivery-mode"][value="' + data.deliveryMode + '"]').prop('checked', true);

      // Tracking numbers
      if (Array.isArray(data.trackingNumbers)) {
        $('#tracking-items').empty();
        for (var i = 0; i < data.trackingNumbers.length; i++) {
          addTrackingInput(data.trackingNumbers[i]);
        }
        updateTrackingCount();
      }

      $('#draft-status').text('Draft: Saved').removeClass('badge-secondary').addClass('badge-success');
      $('#btn-restore-draft, #btn-discard-draft').prop('disabled', false);
      if (data.timestamp) $('#draft-last-saved').text('Last saved: ' + new Date(data.timestamp).toLocaleTimeString());
    } catch (e) {
      console.warn('[Transfers/Pack] draft load failed', e);
    }
  }

  function saveDraftClick() {
    addToLocalStorage();
    CIS.ui.toast('Draft saved', 'success');
  }
  function restoreDraftClick() {
    if (!confirm('Restore saved draft? Current changes will be overwritten.')) return;
    loadStoredValues();
    recomputeTotals();
    CIS.ui.toast('Draft restored', 'info');
  }
  function discardDraftClick() {
    if (!confirm('Discard saved draft?')) return;
    try { localStorage.removeItem(draftKey); } catch (e) {}
    $('#draft-status').text('Draft: Off').removeClass('badge-success').addClass('badge-secondary');
    $('#draft-last-saved').text('Not saved');
    $('#btn-restore-draft, #btn-discard-draft').prop('disabled', true);
    $table.find('tbody tr input[type="number"]').val('').each(function () { syncPrintValue(this); });
    $('#notesForTransfer').val('');
    $('#tracking-items').empty();
    updateTrackingCount();
    CIS.ui.toast('Draft discarded', 'warning');
  }
  function toggleAutosave() {
    var enabled = $('#toggle-autosave').is(':checked');
    if (enabled) {
      if (autosaveTimer) clearInterval(autosaveTimer);
      autosaveTimer = setInterval(addToLocalStorage, 30000);
      CIS.ui.toast('Autosave enabled', 'info');
    } else {
      if (autosaveTimer) clearInterval(autosaveTimer);
      autosaveTimer = null;
      CIS.ui.toast('Autosave disabled', 'info');
    }
  }

  // --- Tracking numbers ------------------------------------------------------
  function updateTrackingCount() {
    var count = $('#tracking-items .tracking-input').length;
    $('#tracking-count').text(count + ' number' + (count !== 1 ? 's' : ''));
  }

  function addTrackingInput(prefill) {
    var html = [
      '<div class="input-group input-group-sm mb-2">',
      '<input type="text" class="form-control tracking-input" placeholder="Enter tracking number or URL..." value="', prefill ? $('<div>').text(String(prefill)).html() : '', '">',
      '<div class="input-group-append">',
      '<button class="btn btn-outline-danger btn-sm" type="button" data-action="tracking-remove"><i class="fa fa-times"></i></button>',
      '</div></div>'
    ].join('');
    $('#tracking-items').append(html);
  }

  // --- Courier panels (minimal operational toggling + cost calc) ------------
  function courierServiceChanged() {
    var v = $('#courier-service').val();
    $('.courier-panel').hide();
    if (v === 'gss')      { $('#gss-panel').show(); }
    else if (v === 'nzpost') { $('#nzpost-panel').show(); }
    else if (v === 'manual') { $('#manual-panel').show(); }
    // Persist choice
    try { localStorage.setItem('vs_courier_service', v || ''); } catch (e) {}
  }

  function loadLastCourierService() {
    try {
      var saved = localStorage.getItem('vs_courier_service');
      if (saved) {
        $('#courier-service').val(saved);
      }
    } catch (e) {}
    courierServiceChanged();
  }

  function nzPostRecalc() {
    var l = parseFloat($('#nzpost-length').val()) || 0;
    var w = parseFloat($('#nzpost-width').val()) || 0;
    var h = parseFloat($('#nzpost-height').val()) || 0;
    var weight = parseFloat($('#nzpost-weight').val()) || 0;
    if (!(l && w && h && weight)) {
      $('#nzpost-cost-display').text('$0.00'); return;
    }
    var volKg = (l * w * h) / 5000;
    var chargeKg = Math.max(weight, volKg);
    var service = $('#nzpost-service-type').val();
    var base = 0;
    if (service === 'CPOLE') base = Math.max(8.50, chargeKg * 4.20);
    else if (service === 'CPOLP') base = Math.max(12.50, chargeKg * 6.80);
    else base = Math.max(15.00, chargeKg * 8.50);
    var fuel = base * 0.15;
    var gst = (base + fuel) * 0.15;
    var total = base + fuel + gst;
    $('#nzpost-cost-display').text('$' + total.toFixed(2));
  }

  function gssRecalc() {
    var l = parseFloat($('#gss-length').val()) || 0;
    var w = parseFloat($('#gss-width').val()) || 0;
    var h = parseFloat($('#gss-height').val()) || 0;
    var weight = parseFloat($('#gss-weight').val()) || 0;
    if (!(l && w && h && weight)) {
      $('#gss-cost-display').text('$0.00'); return;
    }
    var vol = (l * w * h) / 4000;
    var charge = Math.max(weight, vol);
    var total = Math.max(8.50, charge * 2.80);
    $('#gss-cost-display').text('$' + total.toFixed(2));
  }

  // --- Validation & submit ---------------------------------------------------
  function validateQuantities() {
    var offenders = [];
    $table.find('tbody tr').each(function () {
      var $row = $(this);
      var $input = $row.find('input[type="number"]');
      if ($input.hasClass('is-invalid')) {
        offenders.push($row.find('td:nth-child(2)').text().trim());
      }
    });
    if (offenders.length) {
      throw new Error('Please fix quantity errors for: ' + offenders.slice(0, 3).join(', ') + (offenders.length > 3 ? '...' : ''));
    }
  }

  function collectItemsForSubmit() {
    var items = [];
    $table.find('tbody tr').each(function () {
      var $r = $(this);
      var id = $r.find('.productID').val() || $r.find('input[data-item]').data('item');
      if (!id) return;
      var qty = parseInt($r.find('input[type="number"]').val(), 10) || 0;
      items.push({ id: id, qty_sent_total: qty });
    });
    return items;
  }

  function markReadyForDelivery() {
    try {
      validateQuantities();
    } catch (e) {
      CIS.ui.toast(e.message || 'Please fix quantity errors', 'error');
      return;
    }

    var items = collectItemsForSubmit();
    var payload = { items: items };

    var $btn = $('#createTransferButton, #savePack');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

    CIS.http.postJSON('', payload)
      .done(function (res) {
        if (res && res.success) {
          try { localStorage.removeItem(draftKey); } catch (e) {}
          CIS.ui.toast('Transfer saved (PACKAGED).', 'success');
          setTimeout(function () { window.location.reload(); }, 800);
        } else {
          CIS.ui.toast((res && (res.error || res.message)) || 'Save failed', 'error');
        }
      })
      .fail(function (xhr) {
        var msg = 'Network or server error.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
        CIS.ui.toast(msg, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('Save Pack');
      });
  }

  // --- Public: init ----------------------------------------------------------
  Pack.init = function () {
    // Cache elements
    $table = $('#transfer-table');

    var transferId = $('#transferID').val() || (q('#transferID') ? q('#transferID').value : '0');
    draftKey = 'stock_transfer_' + String(transferId || '0');

    // Wire inputs
    $(document).on('input', '#transfer-table input[type="number"]', function () {
      enforceBounds(this);
      syncPrintValue(this);
      checkInvalidQty(this);
      recomputeTotals();
      addToLocalStorage();
    });

    // Toolbar buttons (if present)
    $('#btn-save-draft').on('click', saveDraftClick);
    $('#btn-restore-draft').on('click', restoreDraftClick);
    $('#btn-discard-draft').on('click', discardDraftClick);
    $('#toggle-autosave').on('change', toggleAutosave);

    // Simple keyboard shortcuts
    $(document).on('keydown', function (e) {
      if (e.ctrlKey && e.key.toLowerCase() === 's') { e.preventDefault(); saveDraftClick(); }
      if (e.shiftKey && (e.key === 'F' || e.key === 'f')) { e.preventDefault(); autofillCountedFromPlanned(); }
    });

    // Tracking add/remove
    $(document).on('click', '#btn-add-tracking', function () { addTrackingInput(''); });
    $(document).on('click', '[data-action="tracking-remove"]', function () {
      $(this).closest('.input-group').remove();
      updateTrackingCount();
      addToLocalStorage();
    });
    $(document).on('input', '.tracking-input', CIS.util.debounce(function () {
      updateTrackingCount();
      addToLocalStorage();
    }, 300));

    // Courier panel toggles + cost calcs (if present)
    if (CIS.util.exists('#courier-service')) {
      $('#courier-service').on('change', courierServiceChanged);
      loadLastCourierService();
    }
    $(document).on('input', '#nzpost-length,#nzpost-width,#nzpost-height,#nzpost-weight,#nzpost-service-type', CIS.util.debounce(nzPostRecalc, 150));
    $(document).on('input', '#gss-length,#gss-width,#gss-height,#gss-weight', CIS.util.debounce(gssRecalc, 150));

    // Primary actions
    $('#savePack, #createTransferButton').on('click', markReadyForDelivery);
    $('#autofillFromPlanned').on('click', autofillCountedFromPlanned);
    $(document).on('click', '[data-action="remove-product"]', function () { removeProduct(this); });

    // Initialize counters & draft
    $table.find('input[type="number"]').each(function () { syncPrintValue(this); });
    loadStoredValues();
    recomputeTotals();

    // Export a few helpers for legacy inline HTML hooks (if any remain)
    window.enforceBounds = enforceBounds;
    window.syncPrintValue = syncPrintValue;
    window.checkInvalidQty = checkInvalidQty;
    window.removeProduct = removeProduct;
    window.autofillCountedFromPlanned = autofillCountedFromPlanned;
    window.recomputeTotals = recomputeTotals;
    window.markReadyForDelivery = markReadyForDelivery;

    console.log('[Transfers/Pack] init complete.');
  };

  // Auto-init on DOM ready
  $(function () { Pack.init(); });

  // Expose
  window.TransfersPack = Pack;

})(window, window.jQuery);
