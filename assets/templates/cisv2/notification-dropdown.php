<?php
// Let callers suppress rendering (e.g., before sending headers)
if (defined('SUPPRESS_NOTIFICATION_DROPDOWN') && SUPPRESS_NOTIFICATION_DROPDOWN) { return; }

$userId = (int)($_SESSION['userID'] ?? 0);

// Ensure we have an object with safe defaults so the template never notices/warns
if (!isset($notificationObject) || !is_object($notificationObject)) {
    $notificationObject = (object)[
        'totalNotifications' => 0,
        'notificationArray'  => []
    ];
}
?>

<!-- Notifications dropdown (semantic + accessible) -->
<section
  id="notificationDropDown"
  class="dropdown-menu dropdown-menu-right dropdown-menu-end dropdown-menu-lg pt-0"
  aria-label="Notifications"
>
  <header class="dropdown-header bg-light">
    <h2 class="h6 m-0">
      <strong>
        You have
        <span class="userNotifCounter"><?= (int)$notificationObject->totalNotifications; ?></span>
        messages
      </strong>
    </h2>
  </header>

  <?php
    $notificationArray = isset($notificationObject->notificationArray) ? $notificationObject->notificationArray : [];
    if (!empty($notificationArray) && is_array($notificationArray)):
  ?>
    <ul class="list-unstyled mb-0" role="menu" aria-label="Notification list">
      <?php foreach ($notificationArray as $m): ?>
        <?php
          $id         = (int)$m->id;
          $url        = (string)($m->url ?? '');
          $unread     = empty($m->read_at);
          $createdAt  = (string)($m->created_at ?? '');
          $createdISO = htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8');
          $subject    = htmlspecialchars($m->notification_subject ?? '', ENT_QUOTES, 'UTF-8');
          $text       = htmlspecialchars($m->notification_text ?? '', ENT_QUOTES, 'UTF-8');
          $timeAgo    = time_ago_in_php($createdAt);
        ?>
        <li
          class="px-3 py-2 border-0"
          role="none"
          data-notif-id="<?= $id; ?>"
          <?= $unread ? 'aria-current="true"' : ''; ?>
        >
          <article class="dropdown-item p-0 d-flex align-items-start gap-2" role="group" aria-label="Notification">
            <figure class="m-0 flex-shrink-0">
              <span class="avatar">
                <img
                  class="avatar-img"
                  src="assets/img/avatars/6.jpg"
                  alt=""
                  width="32"
                  height="32"
                  loading="lazy"
                >
              </span>
            </figure>

            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start">
                <time class="text-medium-emphasis small"
                      datetime="<?= $createdISO; ?>"
                      title="<?= $createdISO; ?>">
                  <?= $timeAgo; ?>
                </time>

                <button
                  type="button"
                  class="btn btn-link btn-sm p-0 user-convo-read js-toggle-read <?= $unread ? 'font-weight-bold' : ''; ?>"
                  data-notif-id="<?= $id; ?>"
                  aria-pressed="<?= $unread ? 'false' : 'true'; ?>"
                  role="menuitem"
                >
                  <?= $unread ? 'Mark as Read' : 'Mark as Unread'; ?>
                </button>
              </div>

              <?php if ($url !== ''): ?>
                <a
                  class="d-block text-truncate notif-title js-open-notif <?= $unread ? 'font-weight-bold' : ''; ?>"
                  href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                  data-notif-id="<?= $id; ?>"
                  role="menuitem"
                ><?= $subject; ?></a>
              <?php else: ?>
                <a
                  class="d-block text-truncate notif-title js-open-notif <?= $unread ? 'font-weight-bold' : ''; ?>"
                  href="#"
                  data-notif-id="<?= $id; ?>"
                  data-open-modal="true"
                  role="menuitem"
                ><?= $subject; ?></a>
              <?php endif; ?>

              <p class="small text-medium-emphasis text-truncate mb-0"><?= $text; ?></p>
            </div>
          </article>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <hr class="dropdown-divider" aria-hidden="true">
  <footer class="text-center">
    <a class="dropdown-item text-center" href="/notification-history.php" role="menuitem">
      <strong>View all messages</strong>
    </a>
  </footer>
</section>

<script>
// ------- config -------
var VS_USER_ID = <?= $userId; ?>;

// ------- API helpers -------
function openUserNotificationModal(_notificationID){
  $.post('assets/functions/ajax.php?method=getUserNotificationObject',
    { notificationID:_notificationID, _staffID:VS_USER_ID }
  ).done(function(response){
    var jsonObject = {};
    try { jsonObject = JSON.parse(response || '{}'); } catch(e){}

    $("#notificationModelLabel").html(
      (jsonObject.notification_subject||'') +
      "<br><p style='font-size:12px;margin:0;padding:0;'>" + (jsonObject.notification_text||'') + "</p>"
    );

    $("#notificationModel .modal-body").empty()
      .append(jsonObject.full_text || '')
      .append("<br><small class='text-medium-emphasis'>Generated: " + (jsonObject.created_at||'') + "</small><br>");

    $("#notificationModel").modal("show");

    // mark as read silently in UI
    var $btn = $('[data-notif-id=\"'+_notificationID+'\"] .user-convo-read').get(0);
    markNotificationRead(_notificationID, $btn, true);
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
    var $row   = $('[data-notif-id=\"'+_notificationID+'\"]').first();
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

// ------- Toast helpers (no inline onclick; do not set inline display) -------
function toastNotificationStoreData(){
  var userData = { userID:VS_USER_ID, timestamp:Date.now() };
  sessionStorage.setItem('notificationData', JSON.stringify(userData));
  $(".toast").remove();
}

function openNotificationDropdownFromToast(){
  // Use class toggle so CSS controls visibility; avoid inline styles
  $("#notificationDropDown").addClass("show").attr('data-popper-placement','bottom-end');
  toastNotificationStoreData();
  $("#notif-toast").remove();
}

function displayNotificationToast(){
  var $toast = $(
    '<div id="notif-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">' +
      '<div class="toast-header">' +
        '<i class="fa fa-bell-o" aria-hidden="true"></i>' +
        '<strong class="mr-auto">New Notification</strong>' +
        '<button type="button" class="ml-2 mb-1 close js-toast-close" data-dismiss="toast" aria-label="Close">' +
          '<span aria-hidden="true">&times;</span>' +
        '</button>' +
      '</div>' +
      '<div class="toast-body">You have a new notification to read.</div>' +
    '</div>'
  );

  var shouldShow = true;
  var stored = sessionStorage.getItem('notificationData');
  if (stored){
    try {
      var data = JSON.parse(stored);
      var ageSec = (Date.now() - (data.timestamp||0)) / 1000;
      shouldShow = ageSec > 60;
    } catch(e){ shouldShow = true; }
  }

  if (shouldShow){
    $(".toast").remove();
    $("body").append($toast);

    $toast.on("click", function(e){
      if ($(e.target).closest(".js-toast-close").length){
        e.stopPropagation();
        toastNotificationStoreData();
        $toast.remove();
      } else {
        openNotificationDropdownFromToast();
      }
    });

    setTimeout(function(){ $toast.fadeOut(function(){ $(this).remove(); }); }, 20000);
  }
}

// ------- Delegated UI events (no inline handlers) -------
$(document).on('click', '#notificationDropDown .js-open-notif', function(e){
  var $a  = $(this);
  var id  = $a.data('notif-id');
  var useModal = !!$a.data('open-modal');
  if (useModal){
    e.preventDefault();
    openUserNotificationModal(id);
  } // else allow navigation for real URLs
});

$(document).on('click', '#notificationDropDown .js-toggle-read', function(e){
  e.preventDefault();
  e.stopPropagation();
  var id = $(this).data('notif-id');
  markNotificationRead(id, this, false);
});

// ------- Auto-toast (outside history page) - Always show notifications -------
<?php if ((int)$notificationObject->totalNotifications > 0): ?>
if (window.location.href.indexOf("notification-history") === -1){
  document.addEventListener("DOMContentLoaded", function(){
    setTimeout(displayNotificationToast, 1000);
  });
}
<?php endif; ?>
</script>
