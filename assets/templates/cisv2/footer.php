     <footer class="app-footer">
      <div>
        <a href="https://www.vapeshed.co.nz">The Vape Shed</a>
        <span>&copy; <?php echo date("Y"); ?> Ecigdis Ltd</span>
      </div>
      <div class="ml-auto">
        <div>
  <small class="">
    Developed by <a href="https://www.pearcestephens.co.nz" target="_blank">Pearce Stephens</a>
  </small>
  <a href="/submit_ticket.php" class="btn btn-sm btn-outline-danger" style="font-size: 13px;">
    🐞 Report a Bug
  </a>
</div>

<script>
(function(){
  const rid = (document.querySelector('meta[name="request-id"]')?.content) || '';
  function post(evt, target, data){
    try{ fetch('/assets/services/queue/public/client.event.php', {
      method:'POST', headers: {'Content-Type':'application/json','X-Request-ID':rid},
      body: JSON.stringify({event:evt, page:location.pathname, target, data})
    }); }catch(e){}
  }
  document.addEventListener('click', e=>{
    const t = e.target.closest('button,[type=submit],a');
    if(t) post('click', t.name||t.id||t.outerText?.slice(0,60)||t.tagName, null);
  }, true);
  document.addEventListener('submit', e=>{
    const f = e.target;
    post('submit', f.name||f.id||f.action||'form', {action:f.action});
  }, true);
})();
</script>


<style>
  .btn-outline-danger:hover {
    background-color: #dc3545 !important;
    color: white !important;
    transition: 0.2s ease-in-out;
  }
</style>
      </div>
      <?php if (isset($_SESSION["userID"])){ ?>
<script>

    var url = document.location.toString();
    if (url.match('#')) {
        $('.nav-tabs a[href="#' + url.split('#')[1] + '"]').tab('show');
    }

    //Change hash for page-reload
    $('.nav-tabs li a').on('click', function (e) {
        window.location.hash = e.target.hash;
    }); 
    
 </script>
 
 <!-- CIS Universal File Manager - Site-Wide -->
 <script src="<?php echo HTTPS_URL; ?>assets/js/cis-file-manager.js"></script>
 <script>
 // Global CIS settings for file manager
 window.CIS_SETTINGS = {
     file_upload_dry_run: <?php echo json_encode(getSetting('file_upload_dry_run', false)); ?>,
     file_upload_debug: <?php echo json_encode(getSetting('file_upload_debug', false)); ?>,
     file_upload_max_size: <?php echo json_encode(getSetting('file_upload_max_size', 104857600)); ?>, // 100MB
     file_upload_chunk_size: <?php echo json_encode(getSetting('file_upload_chunk_size', 1048576)); ?> // 1MB
 };
 </script>
 
 <?php } ?>
</footer>