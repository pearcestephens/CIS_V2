<?php
/**
 * /assets/templates/cisv2/notification-dropdown.php
 *
 * Notifications dropdown in the top navbar.
 */

if (defined('SUPPRESS_NOTIFICATION_DROPDOWN') && SUPPRESS_NOTIFICATION_DROPDOWN) {
  return;
}

$userId = (int)($_SESSION['userID'] ?? 0);

// Provide safe defaults
if (!isset($notificationObject) || !is_object($notificationObject)) {
  $notificationObject = (object)[
    'totalNotifications' => 0,
    'notificationArray'  => []
  ];
}
?>
<section
  id="notificationDropDown"
  class="dropdown-menu dropdown-menu-right dropdown-menu-end dropdown-menu-lg pt-0"
  aria-label="Notifications"
>
  <header class="dropdown-header bg-light">
    <h2 class="h6 m-0">
      <strong>
        You have
        <span class="userNotifCounter">
          <?= (int)$notificationObject->totalNotifications; ?>
        </span>
        messages
      </strong>
    </h2>
  </header>

  <?php
    $notificationArray = $notificationObject->notificationArray ?? [];
    if (!empty($notificationArray) && is_array($notificationArray)):
  ?>
    <ul class="list-unstyled mb-0" role="menu" aria-label="Notification list">
      <?php foreach ($notificationArray as $m): ?>
        <?php
          $id         = (int)($m->id ?? 0);
          $url        = (string)($m->url ?? '');
          $unread     = empty($m->read_at);
          $createdAt  = (string)($m->created_at ?? '');
          $createdISO = htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8');
          $subject    = htmlspecialchars($m->notification_subject ?? '', ENT_QUOTES, 'UTF-8');
          $text       = htmlspecialchars($m->notification_text ?? '', ENT_QUOTES, 'UTF-8');
          $timeAgo    = function_exists('time_ago_in_php')
                        ? time_ago_in_php($createdAt)
                        : $createdAt;
        ?>
        <li
          class="px-3 py-2 border-0 user-notif-<?= $id; ?>"
          role="none"
          data-notif-id="<?= $id; ?>"
          <?= $unread ? 'aria-current="true"' : ''; ?>
        >
          <article class="dropdown-item p-0 d-flex align-items-start gap-2" role="group">
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
                >
                  <?= $unread ? 'Mark as Read' : 'Mark as Unread'; ?>
                </button>
              </div>

              <?php if ($url !== ''): ?>
                <a class="d-block text-truncate notif-title js-open-notif <?= $unread ? 'font-weight-bold' : ''; ?>"
                   href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                   data-notif-id="<?= $id; ?>">
                  <?= $subject; ?>
                </a>
              <?php else: ?>
                <a class="d-block text-truncate notif-title js-open-notif <?= $unread ? 'font-weight-bold' : ''; ?>"
                   href="#"
                   data-notif-id="<?= $id; ?>"
                   data-open-modal="true">
                  <?= $subject; ?>
                </a>
              <?php endif; ?>

              <div class="small text-medium-emphasis text-truncate"><?= $text; ?></div>
            </div>
          </article>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item text-center" href="/notification-history.php" role="menuitem">
      <strong>View all messages</strong>
    </a>
  <?php else: ?>
    <div class="p-3 text-muted small">No new notifications.</div>
  <?php endif; ?>
</section>
