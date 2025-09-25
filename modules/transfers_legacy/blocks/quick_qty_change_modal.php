<?php
declare(strict_types=1);
// Minimal port of Quick Product Qty Change modal shell
?>
<div class="modal fade" id="quickQtyChange" tabindex="-1" role="dialog" aria-labelledby="quickQtyChangeLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickQtyChangeLabel">Quick Product Qty Change</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="quick-product-qty-change" class="col-4 col-form-label">Search Product</label>
          <div class="col-8"><input id="quick-product-qty-change" name="quick-product-qty-change" type="text" class="form-control" required onkeyup="QuicksearchProducts()"></div>
        </div>
        <div class="form-group row">
          <label for="quick-qty-store-select" class="col-4 col-form-label">Store Outlet</label>
          <div class="col-8">
            <select id="quick-qty-store-select" name="quick-qty-store-select" class="custom-select" required onchange="QuicksearchProducts()">
              <option value="">Select Your Outlet</option>
            </select>
          </div>
        </div>
        <div class="form-group row">
          <label class="col-4 col-form-label">Confirm Outlet</label>
          <div class="col-8">
            <select id="quick-qty-store-select-confirm" name="quick-qty-store-select-confirm" class="custom-select" required>
              <option value="" selected>Confirm Outlet</option>
            </select>
          </div>
        </div>
        <table class="table table-bordered" id="quickProductChangeTable">
          <thead class="thead-light"><tr><th>Product Name</th><th>Existing Qty</th><th>New Qty</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
        <p class="small mb-0">All changes are logged — <a href="https://staff.vapeshed.co.nz/quick-product-qty-log.php" target="_blank" rel="noopener">View Logs</a></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
$('#quickQtyChange').on('shown.bs.modal', function(){
  if (window.CIS && typeof CIS.populateOutletSelects === 'function') {
    CIS.populateOutletSelects('#quick-qty-store-select', '#quick-qty-store-select-confirm');
  }
});
function QuicksearchProducts(){
  var searchTerm = $('#quick-product-qty-change').val().trim();
  var outletToSearch = $('#quick-qty-store-select').val();
  if (searchTerm.length > 1 && outletToSearch.length > 0){
    $.post('https://staff.vapeshed.co.nz/modules/transfers/ajax/handler.php', { ajax_action:'searchForProductByOutlet', keyword: searchTerm, outletID: outletToSearch }, function(result){
      try { var data = (typeof result === 'string') ? JSON.parse(result) : result; } catch(e){ data = []; }
      var $tbody = $('#quickProductChangeTable tbody');
      $tbody.empty();
      for (var i=0;i<data.length;i++){
        var r = data[i];
        if (r.isFlagged){
          $tbody.append("<tr product-id='"+r.id+"'><td class='unselectable'>"+r.name+"</td><td>Flagged</td><td></td><td></td></tr>");
        } else {
          $tbody.append("<tr product-id='"+r.id+"'><td><a target='_blank' href='https://vapeshed.vendhq.com/product/"+r.id+"'>"+r.name+"</a></td><td>"+r.qtyInStock+"</td><td><input class='newQuickQtyInput' onkeypress='return /[0-9]/i.test(event.key)' style='height:21px;width:60px;' type='number'></td><td><button class='btn btn-success btn-sm' onclick='quickUpdateQty(this)'>Save</button></td></tr>");
        }
      }
    });
  } else {
    $('#quickProductChangeTable tbody').empty();
  }
}
function quickUpdateQty(btn){
  var $tr = $(btn).closest('tr');
  var newQty = $tr.find('.newQuickQtyInput').val();
  var outletToUpdate = $('#quick-qty-store-select').val();
  var outletToUpdateConfirm = $('#quick-qty-store-select-confirm').val();
  var productID = $tr.attr('product-id');
  var staffID = 1;
  if (outletToUpdate == outletToUpdateConfirm && productID && newQty !== '' && !isNaN(newQty)){
    $(btn).prop('disabled', true).text('Updating...');
    $.post('https://staff.vapeshed.co.nz/modules/transfers/ajax/handler.php', { ajax_action:'updateQuickVendProductQty', _vendID:productID, _outletID:outletToUpdate, _newQty:newQty, _staffID:staffID }, function(){
      $(btn).text('Saved');
      $tr.find('td:nth-child(2)').text(newQty);
    });
  } else {
    alert('Outlets do not match or qty invalid.');
  }
}
</script>
