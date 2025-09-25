<?php
/**
 * /assets/templates/cisv2/personalisation-menu.php
 *
 * User personalisation and notification toast logic.
 */

$userId = (int)($_SESSION['userID'] ?? 0);

// Safe notification object fallback
$notificationObject = $notificationObject ?? (object)[
  'totalNotifications' => 0,
  'notificationArray'  => []
];
?>
<script>
  // ---- Config ----
  var VS_USER_ID = <?= $userId; ?>;

  // ---- API helpers ----
  function openUserNotificationModal(_notificationID){
    $.post('assets/functions/ajax.php?method=getUserNotificationObject',
      { notificationID:_notificationID, _staffID:VS_USER_ID }
    ).done(function(response){
      var jsonObject = JSON.parse(response || '{}');
      $("#notificationModelLabel").html(
        (jsonObject.notification_subject||'') +
        "<br><p style='font-size:12px;margin:0;padding:0;'>" + (jsonObject.notification_text||'') + "</p>"
      );
      $("#notificationModel .modal-body").empty()
        .append(jsonObject.full_text || '')
        .append("<br><small class='text-medium-emphasis'>Generated: " + (jsonObject.created_at||'') + "</small><br>");
      $("#notificationModel").modal("show");

      // mark read
      var $rowBtn = $(".user-notif-"+_notificationID).find('.user-convo-read');
      markNotificationRead(_notificationID, $rowBtn.get(0), true);
    });
  }

  function markNotificationRead(_notificationID, object, readOnly){
    var $btn = $(object);
    var currentLabel = ($btn.text() || '').trim();
    var markAsRead = (currentLabel === "Mark as Read") ? 1 : 0;
    if (readOnly === true){ markAsRead = 1; }

    $.post('assets/functions/ajax.php?method=markNotificationRead',
      { _markAsRead:markAsRead, notificationID:_notificationID, _staffID:VS_USER_ID }
    ).done(function(response){
      var $row = $('.dropdown-item[data-notif-id="'+_notificationID+'"]');
      var $title = $row.find('.notif-title');

      if (markAsRead === 1){
        $title.removeClass("font-weight-bold");
        $btn.text("Mark as Unread").removeClass("font-weight-bold").attr('aria-pressed','true');
      } else {
        $title.addClass("font-weight-bold");
        $btn.text("Mark as Read").addClass("font-weight-bold").attr('aria-pressed','false');
      }

      $(".userNotifCounter").text(response);
      if (String(response) === "0"){ $(".notific-count").hide(); } else { $(".notific-count").show(); }
    });
  }

  // ---- Toast helpers ----
  function toastNotificationStoreData(){
    var userData = { userID:VS_USER_ID, timestamp:Date.now() };
    sessionStorage.setItem('notificationData', JSON.stringify(userData));
    $(".toast").remove();
  }

  function openNotificationDropdownFromToast(){
    $("#notificationDropDown").addClass("show");
    toastNotificationStoreData();
    $(".toast").remove();
  }

  function displayNotificationToast(){
    var toastHtml =
      '<div id="notif-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">' +
        '<div class="toast-header">' +
          '<i class="fa fa-bell-o" aria-hidden="true"></i>' +
          '<strong class="mr-auto">New Notification</strong>' +
          '<button type="button" class="ml-2 mb-1 close js-toast-close" data-dismiss="toast" aria-label="Close">' +
            '<span aria-hidden="true">&times;</span>' +
          '</button>' +
        '</div>' +
        '<div class="toast-body">You have a new notification to read.</div>' +
      '</div>';

    var stored = sessionStorage.getItem('notificationData');
    var shouldShow = true;

    if (stored){
      try {
        var data = JSON.parse(stored);
        var ageSec = (Date.now() - (data.timestamp||0)) / 1000;
        shouldShow = ageSec > 60;
      } catch(e){ shouldShow = true; }
    }

    if (shouldShow){
      $(".toast").remove();
      $("body").append(toastHtml);

      $("#notif-toast").on("click", function(e){
        if ($(e.target).closest(".js-toast-close").length){
          e.stopPropagation();
          toastNotificationStoreData();
          $("#notif-toast").remove();
        } else {
          openNotificationDropdownFromToast();
        }
      });

      setTimeout(function(){ $("#notif-toast").fadeOut(function(){ $(this).remove(); }); }, 20000);
    }
  }

  // ---- Auto-toast (only outside history page) ----
  <?php if ((int)$notificationObject->totalNotifications > 0): ?>
    if (window.location.href.indexOf("notification-history") === -1){
      document.addEventListener("DOMContentLoaded", function(){
        setTimeout(displayNotificationToast, 1000);
      });
    }
  <?php endif; ?>
</script>
