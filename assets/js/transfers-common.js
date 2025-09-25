/* ==========================================================================
   CIS Transfers — Common JS Utilities
   Requires: jQuery (>=3.x)
   ========================================================================== */
(function (window, $) {
  'use strict';

  if (!$) {
    console.error('[CIS/Common] jQuery is required.');
    return;
  }

  // --- Ajax defaults --------------------------------------------------------
  $.ajaxSetup({
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    cache: false,
    timeout: 30000
  });

  // --- Namespaces -----------------------------------------------------------
  var CIS = window.CIS || (window.CIS = {});
  CIS.util = CIS.util || {};
  CIS.http = CIS.http || {};
  CIS.ui   = CIS.ui   || {};

  // --- Utils ----------------------------------------------------------------
  CIS.util.debounce = function (fn, wait) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, wait);
    };
  };

  CIS.util.safeParse = function (str, fallback) {
    try { return JSON.parse(str); } catch (e) { return fallback; }
  };

  CIS.util.safeStringify = function (obj) {
    try { return JSON.stringify(obj); } catch (e) { return ''; }
  };

  CIS.util.exists = function (selector) {
    return $(selector).length > 0;
  };

  // --- HTTP helpers ---------------------------------------------------------
  CIS.http.postJSON = function (url, payload) {
    return $.ajax({
      url: url || window.location.pathname + window.location.search,
      type: 'POST',
      dataType: 'json',
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify(payload || {})
    });
  };

  // --- UI helpers -----------------------------------------------------------
  CIS.ui.toast = function (message, type) {
    try {
      var cls = 'bg-info';
      if (type === 'success') cls = 'bg-success';
      else if (type === 'warning') cls = 'bg-warning';
      else if (type === 'error') cls = 'bg-danger';

      if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;" aria-live="polite" aria-atomic="true"></div>');
      }
      var id = 'toast-' + Date.now();
      var html = [
        '<div id="', id, '" class="toast align-items-center text-white ', cls, ' border-0 p-2 px-3"',
        ' role="alert" style="margin-bottom:10px;display:none;min-width:260px;">',
        '<div class="d-flex"><div class="toast-body">', $('<div>').text(String(message || '')).html(), '</div></div>',
        '</div>'
      ].join('');
      $('#toast-container').append(html);
      var $t = $('#' + id);
      $t.fadeIn(120);
      setTimeout(function () { $t.fadeOut(180, function () { $t.remove(); }); }, 3600);
    } catch (e) {
      console.warn('[CIS/Common] toast fallback:', e);
      alert(message);
    }
  };

  // Expose version for quick debugging
  CIS.__commonVersion = '1.0.0';

})(window, window.jQuery);
