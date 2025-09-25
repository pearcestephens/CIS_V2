<?php
/**
 * Queue monitor widget for CIS header/footer.
 * Returns an array with 'item' (nav markup) and 'after' (styles/scripts).
 */

declare(strict_types=1);

if (!function_exists('cisv2_queue_health')) {
    return ['item' => '', 'after' => ''];
}

$initialData = cisv2_queue_health();
$statusKind = 'warn';
$statusText = 'Unknown';

if (is_array($initialData)) {
    if (!empty($initialData['ok']) || (isset($initialData['overall']) && stripos((string)$initialData['overall'], 'healthy') !== false)) {
        $statusKind = 'ok';
    } elseif (isset($initialData['error'])) {
        $statusKind = 'error';
    }
    $statusText = (string) ($initialData['overall'] ?? $initialData['status'] ?? $initialData['error'] ?? 'Unknown');
}

$initialJson = htmlspecialchars(json_encode($initialData, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

$metricsHtml = '<em>No metrics available</em>';
if (!empty($initialData['checks']['queue_health']['body']) && is_array($initialData['checks']['queue_health']['body'])) {
    $metricsHtml = '<ul class="list-unstyled mb-0">';
    foreach ($initialData['checks']['queue_health']['body'] as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
        }
        $metricsHtml .= '<li><strong>' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $metricsHtml .= '</ul>';
}

$item = <<<HTML
<li class="nav-item dropdown queue-monitor">
  <a class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" id="queueMonitorToggle">
    <span class="queue-status-dot queue-status-{$statusKind}" aria-hidden="true"></span>
    <span class="d-none d-lg-inline">Queue</span>
  </a>
  <div class="dropdown-menu dropdown-menu-right queue-monitor-dropdown p-3" aria-labelledby="queueMonitorToggle" data-endpoint="/assets/services/queue/public/health.php" data-initial="{$initialJson}">
    <div class="queue-summary mb-2">
      <strong>Status:</strong> <span data-field="status">{$statusText}</span>
    </div>
    <div class="queue-metrics small mb-2" data-field="metrics">{$metricsHtml}</div>
    <div class="queue-updated text-muted small" data-field="updated">Updated: <span data-value="time">just now</span></div>
    <button type="button" class="btn btn-sm btn-outline-primary" data-action="refresh">Refresh</button>
  </div>
</li>
HTML;

$after = <<<HTML
<style>
.queue-monitor .queue-status-dot {
  display:inline-block;
  width:10px;
  height:10px;
  border-radius:50%;
  margin-right:6px;
}
.queue-status-ok { background-color:#28a745; }
.queue-status-warn { background-color:#ffc107; }
.queue-status-error { background-color:#dc3545; }
.queue-monitor-dropdown.is-loading::after {
  content:'Loading…';
  display:block;
  font-size:0.75rem;
  color:#6c757d;
  margin-top:0.5rem;
}
.queue-monitor-dropdown .queue-metrics li { margin-bottom:0.25rem; }
.queue-monitor-dropdown button[data-action="refresh"] { width:100%; }
</style>
<script>
(function(){
  var dropdown = document.querySelector('.queue-monitor-dropdown');
  if (!dropdown) { return; }
  var endpoint = dropdown.getAttribute('data-endpoint');
  var initialJson = dropdown.getAttribute('data-initial');
  var statusDot = document.querySelector('#queueMonitorToggle .queue-status-dot');
  var statusField = dropdown.querySelector('[data-field="status"]');
  var metricsField = dropdown.querySelector('[data-field="metrics"]');
  var updatedField = dropdown.querySelector('[data-field="updated"] span[data-value="time"]');
  var refreshBtn = dropdown.querySelector('[data-action="refresh"]');

  function decode(json){
    try { return JSON.parse(json); } catch (e) { return null; }
  }

  function escapeHtml(value){
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function determineStatus(data){
    if (!data || typeof data !== 'object') { return 'error'; }
    if (data.ok === true || data.success === true) { return 'ok'; }
    if (data.overall && String(data.overall).toLowerCase().indexOf('healthy') !== -1) { return 'ok'; }
    if (data.error || data.ok === false) { return 'error'; }
    return 'warn';
  }

  function formatMetrics(data){
    var checks = data && data.checks && data.checks.queue_health;
    var body = checks && checks.body ? checks.body : (data && data.body ? data.body : null);
    if (!body || typeof body !== 'object') {
      return '<em>No metrics available</em>';
    }
    var rows = [];
    Object.keys(body).slice(0, 12).forEach(function(key){
      var value = body[key];
      if (value && typeof value === 'object') {
        try { value = JSON.stringify(value); } catch (e) { value = '[object]'; }
      }
      rows.push('<li><strong>' + escapeHtml(key) + ':</strong> ' + escapeHtml(value) + '</li>');
    });
    if (!rows.length) {
      return '<em>No metrics available</em>';
    }
    return '<ul class="list-unstyled mb-0">' + rows.join('') + '</ul>';
  }

  function applyStatus(kind){
    if (!statusDot) { return; }
    statusDot.classList.remove('queue-status-ok','queue-status-warn','queue-status-error');
    statusDot.classList.add('queue-status-' + kind);
  }

  function render(data){
    if (!data) { data = {error:'No data'}; }
    var statusKind = determineStatus(data);
    var statusText = data.overall || data.status || data.error || 'Unknown';
    if (statusField) { statusField.textContent = statusText; }
    if (metricsField) { metricsField.innerHTML = formatMetrics(data); }
    if (updatedField) { updatedField.textContent = new Date().toLocaleTimeString(); }
    applyStatus(statusKind);
  }

  var initialData = initialJson ? decode(initialJson) : null;
  if (initialData) { render(initialData); }

  function fetchData(){
    if (!endpoint) { return; }
    dropdown.classList.add('is-loading');
    fetch(endpoint + '?t=' + Date.now(), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    }).then(function(response){
      return response.json();
    }).then(function(data){
      render(data);
    }).catch(function(){
      render({error:'Request failed'});
    }).finally(function(){
      dropdown.classList.remove('is-loading');
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', function(ev){
      ev.preventDefault();
      fetchData();
    });
  }

  setInterval(fetchData, 60000);
})();
</script>
HTML;

return ['item' => $item, 'after' => $after];
