<?php $userId = (int)($_SESSION['userID'] ?? 0); ?>
<?php
// Initialize notification object safely
$notificationObject = $notificationObject ?? (object)[
    'totalNotifications' => 0,
    'notificationArray' => []
];
?>

<!-- Notifications dropdown -->
<div
  id="notificationDropDown"
  class="dropdown-menu dropdown-menu-right dropdown-menu-end dropdown-menu-lg pt-0"
  aria-label="Notifications"
  role="menu"
>
  <h6 class="dropdown-header bg-light">
    <strong>
      You have
      <span class="userNotifCounter"><?= (int)$notificationObject->totalNotifications; ?></span>
      messages
    </strong>
  </h6>

  <?php foreach ($notificationObject->notificationArray as $m): ?>
    <?php
      $id        = (int)$m->id;
      $url       = (string)($m->url ?? '');
      $unread    = empty($m->read_at);
      $createdAt = (string)($m->created_at ?? '');
      $createdISO= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8');
      $subject   = htmlspecialchars($m->notification_subject ?? '', ENT_QUOTES, 'UTF-8');
      $text      = htmlspecialchars($m->notification_text ?? '', ENT_QUOTES, 'UTF-8');
      $timeAgo   = time_ago_in_php($createdAt);
    ?>

    <div class="dropdown-item px-3 py-2" role="menuitem" data-notif-id="<?= $id; ?>">
      <div class="d-flex">
        <div class="mr-2 flex-shrink-0">
          <span class="avatar">
            <img class="avatar-img" src="assets/img/avatars/6.jpg" alt="" width="32" height="32" loading="lazy">
          </span>
        </div>

        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-start">
            <time class="text-medium-emphasis small" datetime="<?= $createdISO; ?>" title="<?= $createdISO; ?>">
              <?= $timeAgo; ?>
            </time>

            <button
              type="button"
              class="btn btn-link btn-sm p-0 user-convo-read js-toggle-read <?= $unread ? 'font-weight-bold' : ''; ?>"
              data-notif-id="<?= $id; ?>"
              aria-pressed="<?= $unread ? 'false' : 'true'; ?>"
            >
              <?= $unread ? 'Mark as Read' : 'Mark as Unread'; ?>
            </button>
          </div>

          <?php if ($url !== ''): ?>
            <a
              class="d-block text-truncate notif-title js-open-notif <?= $unread ? 'font-weight-bold' : ''; ?>"
              href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
              data-notif-id="<?= $id; ?>"
            ><?= $subject; ?></a>
          <?php else: ?>
            <a
              class="d-block text-truncate notif-title js-open-notif <?= $unread ? 'font-weight-bold' : ''; ?>"
              href="#"
              data-notif-id="<?= $id; ?>"
              data-open-modal="true"
            ><?= $subject; ?></a>
          <?php endif; ?>

          <div class="small text-medium-emphasis text-truncate"><?= $text; ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="dropdown-divider"></div>
  <a class="dropdown-item text-center" href="/notification-history.php" role="menuitem">
    <strong>View all messages</strong>
  </a>
</div>

<script>
// ---- config ----
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

    // Set read
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
  // Prefer class toggle over inline display to avoid CSS override issues
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

    // Click anywhere on toast (not close button) opens dropdown via class (no inline display)
    $("#notif-toast").on("click", function(e){
      if ($(e.target).closest(".js-toast-close").length){ // close only
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

// ---- Delegated UI events (no inline onclick) ----
$(document).on('click', '#notificationDropDown .js-open-notif', function(e){
  var $a = $(this);
  var id = $a.data('notif-id');
  if ($a.data('open-modal')){
    e.preventDefault();
    openUserNotificationModal(id);
  } // else allow normal link navigation
});

$(document).on('click', '#notificationDropDown .js-toggle-read', function(e){
  e.preventDefault();
  e.stopPropagation();
  var id = $(this).data('notif-id');
  markNotificationRead(id, this, false);
});

// ---- Auto-toast (only outside history page) ----
<?php if ((int)$notificationObject->totalNotifications > 0): ?>
  if (window.location.href.indexOf("notification-history") === -1){
    document.addEventListener("DOMContentLoaded", function(){
      setTimeout(displayNotificationToast, 1000);
    });
  }
<?php endif; ?>
</script>
