<div class="btn-group" role="group" aria-label="Button group">
              <a style=" background: #9c27b0; border-radius: 10px; color: #fff; " class="btn" href="#" data-toggle="modal" data-target="#quickQtyChange">Quick Product Qty Change</a>
                <a style=" background: #8bc34a;border-radius: 10px;color: #fff;margin-left: 20px;" class="btn" href="#" data-toggle="modal" data-target="#quickFloatCount">Store Cashup Calculator</a>
</div>

<div class="modal fade" id="quickFloatCount" tabindex="-1" role="dialog" aria-labelledby="quickFloatCountLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickFloatCountLabel">Store Cashup Calculator</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
 
        <div id="cashupContainer">
        <ul class="nav nav-tabs" id="bankingNav" role="tablist">
                        <li class="nav-item">
                          <a class="nav-link active show" id="cashupCalcTab-tab" data-toggle="tab" href="#cashupCalcTab" role="tab" aria-controls="cashupCalcTab" aria-selected="true">Store Cashup Calculator</a>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link" id="cashUpnotesTab-tab" data-toggle="tab" href="#cashUpnotesTab" role="tab" aria-controls="cashUpnotesTab" aria-selected="false">Autosaving NotePad</a>
                        </li>
           
                      </ul>
        <div class="tab-content">
                        <div class="tab-pane active show" id="cashupCalcTab" role="tabpanel" aria-labelledby="cashupCalcTab">
                        <div class="row">
          <div class="col-6">
            <h5 style=" margin: 0; ">Step 1 - Total Cash</h5>
            <p style=" margin: 0; font-size: 11px; margin-bottom: 10px; ">Full amount in till at the end of the day</p>
            <div id="cashTotal">
            <div id="cashTotalScreen" style=" background-color: rgb(0 0 0);opacity: 0.8;position: absolute;height: 362px;width: 368px; display:none;">
          <p style=" font-size: 20px; margin-top: 45%; text-align: center; z-index: 9999; color: #fff; ">Complete Step 1 To Continue</p>
          
          </div>
          <table class="table table-responsive-sm table-bordered table-striped table-sm">
            <thead>
              <tr>
                <td></td>
                <td>Coin/Note Qty</td>
                <td>Total Cash</td>
              </tr>
           
            </thead>
                        <tbody>
                        <tr>
                            <td>$100.00 Notes:</td>
                            <td><input data-den="100.00" required="" step="any" onkeyup="storeCashupCalc(this,100.00,'#cashUpTotalhundredDollarOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="hundredDollarInput" id="hundredDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotalhundredDollarOutput"></span></td>
                          
                          </tr>
                          <tr>
                            <td>$50.00 Notes:</td>
                            <td><input data-den="50.00" required="" step="any" onkeyup="storeCashupCalc(this,50.00,'#cashUpTotalfifthyDollarOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="fifthyDollarInput" id="fifthyDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotalfifthyDollarOutput"></span></td>
                           
                          </tr>
                          <tr>
                            <td>$20.00 Notes:</td>
                            <td><input data-den="20.00" required="" step="any" onkeyup="storeCashupCalc(this,20.00,'#cashUpTotaltwentyDollarOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="twentyDollarInput" id="twentyDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotaltwentyDollarOutput"></span></td>
                         
                          </tr>
                          <tr>
                            <td>$10.00 Notes:</td> 
                            <td><input data-den="10.00" onkeyup="storeCashupCalc(this,10.00,'#cashUpTotaltenDollarOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="tenDollarInput" id="tenDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotaltenDollarOutput"></span></td>
                         
                          </tr>
                          <tr>
                            <td>$5.00 Notes:</td>
                            <td><input data-den="5.00" onkeyup="storeCashupCalc(this,5.000,'#cashUpTotalfiveDollarOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="fiveDollarInput" id="fiveDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotalfiveDollarOutput"></span></td>
                          
                          </tr>
                          <tr>
                            <td>$2.00 Coins:</td>
                            <td><input data-den="2.00" onkeyup="storeCashupCalc(this,2.00,'#cashUpTotaltwoDollarOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="twoDollarInput" id="twoDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotaltwoDollarOutput"></span></td>
                         
                          </tr>
                          <tr>
                            <td>$1.00 Coins:</td>
                            <td><input data-den="1.00" onkeyup="storeCashupCalc(this,1.00,'#cashUpTotaloneDollarOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="oneDollarInput" id="oneDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotaloneDollarOutput"></span></td>
                       
                          </tr>
                          <tr> 
                            <td>50c Coins:</td>
                            <td><input data-den="0.50" onkeyup="storeCashupCalc(this,0.50,'#cashUpTotalfiftyCentOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="fiftyCentInput" id="fiftyCentInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotalfiftyCentOutput"></span></td>
                           
                          </tr>
                          <tr> 
                            <td>20c Coins:</td>
                            <td><input data-den="0.20" onkeyup="storeCashupCalc(this,0.20,'#cashUpTotaltwentyCentOutput','#cashUpTotalCash',true,event);" class="cashUpTotalInput" value="" type="number" name="twentyCentInput" id="twentyCentInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotaltwentyCentOutput"></span></td>
                         
                          </tr>
                          <tr> 
                            <td>10c Coins:</td> 
                            <td><input data-den="0.10" class="cashUpTotalInput" data-den="0.10" onkeyup="storeCashupCalc(this,0.10,'#cashUpTotaltenCentOutput','#cashUpTotalCash',true,event);" value="" type="number" name="tenCentInput" id="tenCentInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpTotalOutput" id="cashUpTotaltenCentOutput"></span></td>                            
                          </tr>
                         
                         
                          
                          
                         
                          
                          
                          
                          
                          <tr>
                            <td>Total Cash:
                            <p id="float-error" class="error" style=" font-size: 12px; color: red; margin: 0; padding: 0; display:none;">You must fill out every box</p>
                            </td>
                            <td><span id="cashUpTotalCash">$0.00</span></td>
                            <td></td>
                      
                          </tr>                                      
                        </tbody>
                      </table>
            
          </div></div>
          <div class="col-6">
      <!--     <button class="btn btn-info" id="autoFloatButton" onclick="calcAutoFloat()" style="color: #fff;float: right;background-color: #63c2de;display:none;border-color: #63c2de;">Auto Float</button> -->
          <h5 style=" margin: 0; ">Step 2 - Total Banking</h5>
          <p style=" margin: 0; font-size: 11px; margin-bottom: 10px; ">Total cash to be banked and put in safe</p>
          <div id="bankingTotal">
            <div id="bankingTotalScreen" style=" background-color: rgb(0 0 0);opacity: 0.8;position: absolute;height: 362px;width: 368px; ">
          <p style=" font-size: 20px; margin-top: 45%; text-align: center; z-index: 9999; color: #fff; ">Complete Step 1 To Continue</p>
          </div>
          <table class="table table-responsive-sm table-bordered table-striped table-sm">
            <thead>
              <tr>
                <td></td>
                <td>Coin/Note Qty</td>
                <td>Total Cash</td>
            </tr>
           
            </thead>
                        <tbody>
                        <tr>
                            <td>$100.00 Notes:</td>
                            <td><input data-den="100.00" class="cashUpBankingInput" required="" step="any" onkeyup="storeCashupCalc(this,100.00,'#cashUpBankinghundredDollarOutput','#cashUpTotalBanking',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankinghundredDollarInput" id="cashUpBankinghundredDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankinghundredDollarOutput"></span></td>
                          
                          </tr>
                          <tr>
                            <td>$50.00 Notes:</td>
                            <td><input data-den="50.00" class="cashUpBankingInput" required="" step="any" onkeyup="storeCashupCalc(this,50.00,'#cashUpBankingfifthyDollarOutput','#cashUpTotalBanking',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingfifthyDollarInput" id="cashUpBankingfifthyDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingfifthyDollarOutput"></span></td>
                           
                          </tr>
                          <tr>
                            <td>$20.00 Notes:</td>
                            <td><input data-den="20.00" class="cashUpBankingInput"  required="" step="any" onkeyup="storeCashupCalc(this,20.00,'#cashUpBankingtwentyDollarOutput','#cashUpTotalCash',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingtwentyDollarInput" id="cashUpBankingtwentyDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingtwentyDollarOutput"></span></td>
                         
                          </tr>
                          <tr>
                            <td>$10.00 Notes:</td> 
                            <td><input data-den="10.00" class="cashUpBankingInput" onkeyup="storeCashupCalc(this,10.00,'#cashUpBankingtenDollarOutput','#cashUpTotalCash',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingtenDollarInput" id="cashUpBankingtenDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingtenDollarOutput"></span></td>
                         
                          </tr>
                          <tr>
                            <td>$5.00 Notes:</td>
                            <td><input data-den="5.00" class="cashUpBankingInput" onkeyup="storeCashupCalc(this,5.000,'#cashUpBankingfiveDollarOutput','#cashUpTotalCash',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingfiveDollarInput" id="cashUpBankingfiveDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingfiveDollarOutput"></span></td>
                          
                          </tr>
                          <tr>
                            <td>$2.00 Coins:</td>
                            <td><input data-den="2.00" class="cashUpBankingInput" onkeyup="storeCashupCalc(this,2.00,'#cashUpBankingtwoDollarOutput','#cashUpTotalCash',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingtwoDollarInput" id="cashUpBankingtwoDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingtwoDollarOutput"></span></td>
                         
                          </tr>
                          <tr>
                            <td>$1.00 Coins:</td>
                            <td><input data-den="1.00" class="cashUpBankingInput" onkeyup="storeCashupCalc(this,1.00,'#cashUpBankingoneDollarOutput','#cashUpTotalCash',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingoneDollarInput" id="cashUpBankingoneDollarInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingoneDollarOutput"></span></td>
                       
                          </tr>
                          <tr> 
                            <td>50c Coins:</td>
                            <td><input data-den="0.50" class="cashUpBankingInput" onkeyup="storeCashupCalc(this,0.50,'#cashUpBankingfiftyCentOutput','#cashUpTotalCash',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingfiftyCentInput" id="cashUpBankingfiftyCentInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingfiftyCentOutput"></span></td>
                           
                          </tr>
                          <tr> 
                            <td>20c Coins:</td>
                            <td><input data-den="0.20" class="cashUpBankingInput" onkeyup="storeCashupCalc(this,0.20,'#cashUpBankingtwentyCentOutput','#cashUpTotalCash',false,event);" class="cashUpTotalInput" value="" type="number" name="cashUpBankingtwentyCentInput" id="cashUpBankingtwentyCentInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingtwentyCentOutput"></span></td>
                         
                          </tr>
                          <tr> 
                            <td>10c Coins:</td>
                            <td><input data-den="0.10" class="cashUpBankingInput" onkeyup="storeCashupCalc(this,0.10,'#cashUpBankingtenCentOutput','#cashUpTotalCash',false,event);" value="" type="number" name="cashUpBankingtenCentInput" id="cashUpBankingtenCentInput" style="width: 100px; margin: 0; height: 20px; padding: 0;"></td>
                            <td><span class="cashUpBankingOutput" id="cashUpBankingtenCentOutput"></span></td>                            
                          </tr>

                         
                          
                         
                         
                          
                          
                          
                          
                          
                          <tr>
                            <td>Total Banking:
                            <p id="float-error" class="error" style=" font-size: 12px; color: red; margin: 0; padding: 0; display:none;">You must fill out every box</p>
                            </td>
                            <td><span id="cashUpTotalBanking">$0.00</span></td>
                            <td></td>
                      
                          </tr>                                      
                        </tbody>
                      </table>
                      
                      </div>
          </div></div>
          <div class="row">
          <div class="col-12">
          <table class="table table-responsive-sm table-bordered table-striped table-sm" style=" text-align: right; ">
                        <tbody>
                          <tr> 
                            <td>Total Cash:</td>        
                            <td style=" width: 100px; text-align:left;">$<span id="cashUpTotalCashOutput">0.00</span></td>             
                          </tr>
                          <tr> 
                            <td>Total Banking:</td> 
                            <td style=" width: 100px; text-align:left;">$<span id="cashUpTotalBankingOutput">0.00</span></td>                            
                          </tr> 
                          <tr> 
                            <td>Total Calculated Float:</td> 
                            <td style=" width: 100px; text-align:left;">$<span id="cashUpTotalFloatOutput">0.00</span><img id="floataccurate" src="assets/img/green-tick.png" style=" height: 14px; margin-left: 8px; margin-top: -4px; display:none; "></td>                          
                          </tr>
                                             
                        </tbody>
          </table>
          <p id="qtyNotAvailableWarning" class="cashupError" style=" color: red; font-size: 12px; display:none;">Warning: Quantity is more than you specified in Total Cash</p>
          <p id="floatUnder" class="cashupError" style=" color: red; font-size: 12px; display:none;"></p>
          <p id="floatOver"  class="cashupError" style=" color: red; font-size: 12px; display:none;"></p>
          <p id="totalToLow"  class="cashupError" style=" color: red; font-size: 12px; display:none;"></p>
          <p style=" font-size: 10px; ">Once you have submitted your VEND Register Closure, click 'Save' and the information you have filled in above will be automatically added to your CIS Register Closure once it becomes available. If there is a delay with the VEND Register Closure appearing. It will continue to be saved until the morning.</p>
          </div>
        </div>
        </div>
        <div class="tab-pane" id="cashUpnotesTab" role="tabpanel" aria-labelledby="cashUpnotesTab">
            <p>Note: Any information written here will only be accessible from the computer originally written on.</p>
            <textarea onkeyup="localStorage.autoSavingPad = this.value;" id="autosavingPad" style=" width: 100%;height: 500px;margin: 0;padding: 2px;font-size: 20px;white-space: pre-line;white-space: pre-wrap; " data-gramm="false"></textarea>
          </div>
      
          </div></div>
          <div id="cashupLoader" style="text-align: center;font-size: 18px;display:none;">
            <img src="assets/img/loader.gif">
            <p style="margin-top: 15px;">Saved...Loading CIS Main Dashboard</p>
          </div>   
        </div>
         
      <div class="modal-footer">
      <button type="button" class="btn btn-lg btn-danger" onclick="resetCashCalc();" style=" color: #fff; background: #ff9800;" >Reset</button>
      <button type="button" class="btn btn-lg btn-danger" data-dismiss="modal" style=" color: #fff; background: red; ">Close</button>
      <button type="button" class="btn btn-lg btn-info" id="cashUpSaveButton" style="display:none; color: #fff; background: #8bc34a; " onclick="saveLatestCashup();">Save & Close</button>
      </div>

    </div>
  </div>
</div>


<div class="modal fade" id="quickQtyChange" tabindex="-1" role="dialog" aria-labelledby="quickQtyChangeLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickQtyChangeLabel">Quick Product Qty Change</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
 

  <div class="form-group row">
    <label for="quick-product-qty-change" class="col-4 col-form-label">Search Product</label> 
    <div class="col-8">
      <input id="quick-product-qty-change" name="quick-product-qty-change" type="text" class="form-control" required="required"  onkeyup="QuicksearchProducts()" >
    </div>
  </div>
  <div class="form-group row">
    <label for="quick-qty-store-select" class="col-4 col-form-label">Store Outlet</label> 
    <div class="col-8">
      <?php 

      $quickOutlets = getAllOutletsFromDB();
    ?>
      <select id="quick-qty-store-select" name="quick-qty-store-select" class="custom-select" required="required" onchange="QuicksearchProducts()">
        <option value="">Select Your Outlet</option>
        <?php
        if (isset($quickOutlets)){
          foreach($quickOutlets as $quickOutlet){
            echo '<option value="'.$quickOutlet->id.'">'.$quickOutlet->name.'</option>';
          }
        }
        ?>
      
      </select>
    </div>
  </div> 
  <div class="form-group row">
    <label for="quick-qty-store-select" class="col-4 col-form-label">Confirm Outlet</label> 
    <div class="col-8">
      <?php 

      $quickOutlets = getAllOutletsFromDB();
    ?>
      <select id="quick-qty-store-select-confirm" name="quick-qty-store-select-confirm" class="custom-select" required="required" onchange="QuicksearchProducts()">
        <option value="" selected>Confirm Outlet</option>
        <?php
        if (isset($quickOutlets)){
          foreach($quickOutlets as $quickOutlet){
            echo '<option value="'.$quickOutlet->id.'">'.$quickOutlet->name.'</option>';
          }
        }
        ?>
      
      </select>
      <div id="quick-qty-outlet-hint" class="alert alert-info py-1 px-2 mt-2" role="alert" style="display:none;font-size:12px;">
        Select and confirm the same outlet to enable Save.
      </div>
    </div>
  </div> 
  <div class="form-group row">
  <div class="col-12">
    <table class="table table-bordered" id="quickProductChangeTable">
      <thead class="thead-light">
        <tr>
          <th>Product Name</th>
          <th>Existing Qty</th>
          <th>New Qty</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      </tbody>
    </table> 
  <p style=" padding: 0; margin: 0; font-size: 11px; ">Please Note: All Changes are logged -  <a style="margin:0;padding:0;" href="https://staff.vapeshed.co.nz/quick-product-qty-log.php">View Logs Here</a></p>
 
  </div>
  </div>


      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  function QuicksearchProducts(){

var searchTerm = $('#quick-product-qty-change').val().trim();
var outletToSearch = $('#quick-qty-store-select').val();

if (searchTerm.length > 1 && outletToSearch.length > 0){
   $.post("assets/functions/ajax.php?method=searchForProductByOutlet", {keyword: searchTerm,outletID:outletToSearch}, function(result){
       
    var searchResults = JSON.parse(result);

    $('#quickProductChangeTable tbody').empty();

    for (var i = 0; i < searchResults.length;i++){  
      if (searchResults[i].isFlagged){
        $('#quickProductChangeTable tbody').append("<tr product-id='"+searchResults[i].id+"'><td class='unselectable'>"+searchResults[i].name+"</td><td>Flagged</td><td></td><td></td></tr>");
      }else{
        $('#quickProductChangeTable tbody').append("<tr product-id='"+searchResults[i].id+"'><td><a target='_blank' href='https://vapeshed.vendhq.com/product/"+searchResults[i].id+"'>"+searchResults[i].name+"</a></td><td>"+searchResults[i].qtyInStock+"</td><td><input class='newQuickQtyInput' inputmode='numeric' pattern='[0-9]+' min='0' step='1' placeholder='0' onkeypress='return /[0-9]/i.test(event.key)' style='height: 21px;width: 60px;' type='number'></td><td><button class='btn btn-success btn-sm' data-product-id='"+searchResults[i].id+"' onclick='quickUpdateQty(this)'>Save</button></td></tr>");
      }
     } 

    
  // After rows are rendered, (re)apply outlet-based enable/disable
  updateQuickQtyButtons();

  }); 

    
}else{
  $('#quickProductChangeTable tbody').empty();
}
// Initialize on modal open
$('#quickQtyChange').on('shown.bs.modal', function(){
  updateQuickQtyButtons();
});
}

function quickUpdateQty(object){
  var $btn = $(object);
  var $row = $btn.closest('tr');
  var newQtyRaw = $row.find('.newQuickQtyInput').val();
  var outletToUpdate = $('#quick-qty-store-select').val();
  var outletToUpdateConfirm = $('#quick-qty-store-select-confirm').val();
  var productID = ($row.attr('product-id') || $btn.data('product-id') || '').toString();
  if (!productID) {
    var href = ($row.find('a[target="_blank"]').attr('href')||'');
    var m = href.match(/product\/(\w[\w\-]+)/);
    if (m && m[1]) { productID = m[1]; }
  }
  var staffID = <?php echo isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0; ?>;
  var productName = $.trim($row.find('td:first-child').text());

  // Sanitize and coerce
  // Vend product IDs are UUIDs; send as string and let server validate
  var pid = productID.trim();
  var oid = parseInt(outletToUpdate, 10) || 0;
  var oid2 = parseInt(outletToUpdateConfirm, 10) || 0;
  var qty = (function(v){ v = (v||"").toString().trim(); return v === "" || isNaN(v) ? null : parseInt(v,10); })(newQtyRaw);

  if (pid !== '' && qty !== null && qty >= 0 && oid > 0 && oid2 > 0){
  // $btn and $row already defined above
    $btn.prop('disabled', true).data('origText', $btn.html()).html('Updating...');

    // Post to CIS bridge endpoint for queue-backed, secure, logged updates
    var payload = {
  _vendID: pid,
      _outletID: oid,
      _outletID_confirm: oid2,
      _newQty: qty,
      _staffID: staffID
    };
    if (typeof window !== 'undefined' && window.CIS_CSRF) { payload._csrf = window.CIS_CSRF; }

    $.ajax({
      url: '<?php echo HTTPS_URL; ?>assets/services/queue/public/cis.quick_qty.bridge.php',
      method: 'POST',
      dataType: 'json',
      data: payload,
      success: function(resp){
        try {
          if (resp && resp.ok) {
            // Update visible current qty immediately for good UX
            $row.find('td:nth-child(2)').html(qty);
            $btn.html('Saved');
            quickQtyToast('Queued qty update: ' + (productName||'Product') + ' → ' + qty, 'success');
          } else {
            var msg = (resp && resp.error && (resp.error.message || resp.error.code)) || 'Update failed';
            quickQtyToast('Quick Qty update failed: ' + msg, 'danger');
            $btn.html($btn.data('origText'));
            $btn.prop('disabled', false);
          }
        } catch (e) {
          quickQtyToast('Unexpected response processing error.', 'danger');
          $btn.html($btn.data('origText'));
          $btn.prop('disabled', false);
        }
      },
      error: function(xhr){
        var msg = 'Network/server error (' + xhr.status + ')';
        try {
          var j = xhr.responseJSON;
          if (j && j.error && (j.error.message || j.error.code)) {
            msg = j.error.message || j.error.code;
          }
        } catch(_) {}
        quickQtyToast('Quick Qty update failed: ' + msg, 'danger');
        $btn.html($btn.data('origText'));
        $btn.prop('disabled', false);
      }
    });

  }else{
    quickQtyToast("Please check your inputs and try again. (pid="+pid+", outlet="+oid+", confirm="+oid2+", qty="+newQtyRaw+")", 'warning');
    return false;
  }
}

// Disable/enable Save buttons until outlets are set and match
function updateQuickQtyButtons(){
  var oid = parseInt($('#quick-qty-store-select').val()||'0',10) || 0;
  var oid2 = parseInt($('#quick-qty-store-select-confirm').val()||'0',10) || 0;
  var enable = (oid > 0 && oid2 > 0 && oid === oid2);
  $('#quickProductChangeTable tbody .btn-success').each(function(){
    $(this).prop('disabled', !enable);
  });
  var $hint = $('#quick-qty-outlet-hint');
  if (!oid || !oid2) {
    $hint.removeClass('alert-warning').addClass('alert-info').text('Select and confirm the same outlet to enable Save.').show();
  } else if (oid !== oid2) {
    $hint.removeClass('alert-info').addClass('alert-warning').text('Selected outlets do not match. Please confirm the same outlet.').show();
  } else {
    $hint.hide();
  }
}

$('#quick-qty-store-select, #quick-qty-store-select-confirm').on('change', function(){
  updateQuickQtyButtons();
});

// Minimal Bootstrap 4 toast helper for Quick Qty
function ensureQuickQtyToastContainer(){
  var $c = $('#quickQtyToastContainer');
  if ($c.length === 0) {
    $('body').append('<div id="quickQtyToastContainer" aria-live="polite" aria-atomic="true" style="position:fixed;top:75px;right:20px;z-index:1080;"></div>');
    $c = $('#quickQtyToastContainer');
  }
  return $c;
}

function quickQtyToast(message, variant){
  var $c = ensureQuickQtyToastContainer();
  var id = 'qqt_'+Date.now()+'_'+Math.floor(Math.random()*10000);
  var bg = (variant === 'success') ? 'bg-success' : (variant === 'warning' ? 'bg-warning' : 'bg-danger');
  var text = (variant === 'warning') ? 'text-dark' : 'text-white';
  var html = ''+
    '<div id="'+id+'" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="4000" data-autohide="true" style="min-width:260px;">'
    +  '<div class="toast-body '+bg+' '+text+'">'+
           $('<div/>').text(message).html() +
       '</div>'+
    '</div>';
  var $t = $(html).appendTo($c);
  try { $t.toast('show'); } catch(e) { /* in case Toast plugin not initialized */ }
  $t.on('hidden.bs.toast', function(){ $(this).remove(); });
}

</script>

<style>
  #quickProductChangeTable tbody tr td{
    padding: 5px; margin: 0; line-height: 1;
  }

  #quickProductChangeTable .btn:hover{
    color: #fff;
    background: #196d36;
    border: 1px solid #3c923f;
  }
  #quickProductChangeTable .btn-success{
      color: #fff;
      border: 1px solid #4dbd74;
  }

</style>