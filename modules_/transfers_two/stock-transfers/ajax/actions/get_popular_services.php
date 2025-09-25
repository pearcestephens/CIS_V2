<?php
// get_popular_services.php (unified)
// Placeholder: Returns a static suggestion list. Replace with DB-backed aggregate of past shipments.
$outlet = (string)($_POST['outlet_id'] ?? '');
try {
  $popular = [
    ['carrier'=>'NZ_POST','service'=>'BOX','container'=>'BOX_3','score'=>0.9],
    ['carrier'=>'GSS','service'=>'LOCAL','container'=>null,'score'=>0.7],
    ['carrier'=>'NZ_POST','service'=>'LETTER','container'=>'OVERSIZE_LETTER','score'=>0.6],
  ];
  jresp(true, ['outlet_id'=>$outlet, 'popular'=>$popular, 'request_id'=>$__ajax_context['request_id']]);
} catch (Throwable $e) {
  error_log('[transfers.stock-transfers.get_popular]['.$__ajax_context['request_id'].'] '.$e->getMessage());
  jresp(false,'Server error',500);
}
