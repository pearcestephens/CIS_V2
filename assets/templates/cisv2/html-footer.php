<?php if (isset($_SESSION["userID"])) { ?>

  <div class="modal fade" id="nicotineCheckModal" tabindex="-1" role="dialog" aria-labelledby="nicotineCheckModal" aria-hidden="true">
    <div class="modal-dialog" role="document" style="padding:0;">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="nicotineCheckModal">Quick Nicotine Check</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
          </button>
        </div>
        <div class="modal-body">
          <h6>Nicotine Check For: <span id="outletName"></span></h6>
          <div class="form-group row">
            <label for="nicotineRemain" class="col-5 col-form-label">Nicotine Remaining (ML)</label>
            <div class="col-7">
              <select id="nicotineRemain" name="nicotineRemain" class="custom-select" required="required">
                <option value="" selected>Please Select</option>
                <option value="0">0ml</option>
                <option value="100">100ml</option>
                <option value="200">200ml</option>
                <option value="300">300ml</option>
                <option value="400">400ml</option>
                <option value="500">500ml</option>
                <option value="600">600ml</option>
                <option value="700">700ml</option>
                <option value="800">800ml</option>
                <option value="900">900ml</option>
                <option value="1000">1000ml (1L)</option>
                <option value="1500">1500ml (1.5L)</option>
                <option value="2000">2000ml (2L)</option>
                <option value="3000">3000ml (3L)</option>
                <option value="4000">4000ml (4L)</option>
              </select>
              <span style="color:red;display:none" id="updatingLabel">Updating...Please wait</span>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <input type="hidden" id="nicotineOutlet">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" onclick="updateNicotineLevel()">Confirm Check</button>
        </div>
      </div>
    </div>
  </div>

<!-- ✅ jQuery is already loaded in your header -->
<!-- Popper v1 (required by Bootstrap 4) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>

<!-- Bootstrap 4.2 JS (jQuery plugins like $(...).tab() work) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.0/dist/js/bootstrap.min.js"></script>

<!-- Pace + Perfect Scrollbar (safe with BS4) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/pace.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/perfect-scrollbar/1.5.5/perfect-scrollbar.min.js"></script>

<!-- CoreUI v3 (Bootstrap 4-compatible). DO NOT use CoreUI v5 here. -->
<script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@3.4.0/dist/js/coreui.bundle.min.js"></script>

<!-- Chart.js v2 (matches CoreUI custom-tooltips plugin) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script src="<?php echo HTTPS_URL; ?>assets/node_modules/@coreui/coreui-plugin-chartjs-custom-tooltips/dist/js/custom-tooltips.min.js"></script>

<!-- Your app JS -->
<script src="<?php echo HTTPS_URL; ?>assets/js/main.js"></script>

<!-- jQuery UI (loads after jQuery; fine with BS4) -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- CIS Sidebar Mobile Enhancement - Load AFTER jQuery UI -->
<script src="<?php echo HTTPS_URL; ?>assets/js/sidebar-mobile-enhance.js?v=20250904c"></script>


  <script>
    $('#quickFloatCount').on('shown.bs.modal', function() {

      var width = $('#bankingTotal').width();
      var height = $('#bankingTotal').height();
      $('#bankingTotalScreen').width(width);
      $('#bankingTotalScreen').height(height);

    })

    $('.cashUpTotalInput,.cashUpTotalInput').keypress(function(event) {
      if (event.charCode >= 48 && event.charCode <= 57) {
        return true;
      }
      return false;
    });

    function saveLatestCashup() {
      var savedCashUp = {
        timestamp: new Date(),
        cashTenCents: $('#tenCentInput').val(),
        cashTwentyCents: $('#twentyCentInput').val(),
        cashFiftyCents: $('#fiftyCentInput').val(),
        cashOneDollar: $('#oneDollarInput').val(),
        cashTwoDollar: $('#twoDollarInput').val(),
        cashFiveDollar: $('#fiveDollarInput').val(),
        cashTenDollar: $('#tenDollarInput').val(),
        cashTwentyDollar: $('#twentyDollarInput').val(),
        cashFiftyDollar: $('#fifthyDollarInput').val(),
        cashHundredDollar: $('#hundredDollarInput').val(),
        bankingTenCents: $('#cashUpBankingtenCentInput').val(),
        bankingTwentyCents: $('#cashUpBankingtwentyCentInput').val(),
        bankingFiftyCents: $('#cashUpBankingfiftyCentInput').val(),
        bankingOneDollar: $('#cashUpBankingoneDollarInput').val(),
        bankingTwoDollar: $('#cashUpBankingtwoDollarInput').val(),
        bankingFiveDollar: $('#cashUpBankingfiveDollarInput').val(),
        bankingTenDollar: $('#cashUpBankingtenDollarInput').val(),
        bankingTwentyDollar: $('#cashUpBankingtwentyDollarInput').val(),
        bankingFiftyDollar: $('#cashUpBankingfifthyDollarInput').val(),
        bankingHundredDollar: $('#cashUpBankinghundredDollarInput').val(),
        totalCash: $('#cashUpTotalCashOutput').html(),
        totalBanking: $('#cashUpTotalBankingOutput').html(),
        totalFloat: $('#cashUpTotalFloatOutput').html()

      };

      localStorage.savedCashUp = JSON.stringify(savedCashUp);

      if (window.location.href.indexOf("closure-reconciliation.php") > -1) {
        $('#totalCashBanked').val($('#cashUpTotalBankingOutput').html());
        $('#remainderInTill').val($('#cashUpTotalFloatOutput').html());
        $('#quickFloatCount').modal('hide');
        $('#storeCashUpJSON').val(localStorage.savedCashUp);
      } else {
        $('.modal-footer').hide();
        $('#cashupContainer').hide();
        $('#cashupLoader').show();
        window.location.href = '/';
      }
    }


    function suggestCashUpErrors(totalCash, totalBanking) {

      var currentFloat = totalCash - totalBanking;

      if (currentFloat < 300) {
        $('#floataccurate').hide();
        $('#floatUnder').html('Warning: Float is under $300.00, it is currently: $' + currentFloat.toFixed(2));
        $('#floatUnder').show();
      } else if (currentFloat > 300) {
        $('#floatOver').html('Warning: Float is over $300.00, it is currently: $' + currentFloat.toFixed(2));
        $('#floatOver').show();
        $('#floataccurate').hide();
      }

      if (currentFloat == 300) {
        $('#floataccurate').fadeIn();
      }
    }

    function storeCashUpToggleHideDenomination(inputIndex, disabled, value) {

      var tbody = $('#bankingTotal table tbody');
      var inputObject = tbody[0].children[inputIndex - 1].children[1].children[0];
      var inputObjectTwo = tbody[0].children[inputIndex - 1].children[2].children[0];
      $(inputObject).attr("disabled", disabled);
      $(inputObject).val(value);
      if (isNaN(value) || value.length == 0) {
        $(inputObjectTwo).html(value);
      } else {
        $(inputObjectTwo).html(parseFloat(value).toFixed(2));
      }
    }

    function checkDollarsAvailableStepOne(inputIndex, qty) {

      var tbody = $('#cashTotal table tbody');
      var inputObject = tbody[0].children[inputIndex - 1].children[1].children[0];

      if (parseInt(inputObject.value) < parseInt(qty)) {
        return inputObject.value;
      }
      return false;

    }

    function storeCashupCalc(input, number, outputID, totalID, stepOne, event) {

      event.stopImmediatePropagation();

      var qty = input.value;
      var inputIndex = input.parentElement.parentElement.rowIndex;
      $('.cashupError').hide();

      if (qty == "") {
        $(outputID).html("");
        if (stepOne) {
          storeCashUpToggleHideDenomination(inputIndex, false, "");
        }
      } else {

        if (qty == 0 && stepOne) {
          storeCashUpToggleHideDenomination(inputIndex, true, "0");
        } else if (stepOne) {
          storeCashUpToggleHideDenomination(inputIndex, false, "");
        }

        if (stepOne == false) {

          var isValidNumber = checkDollarsAvailableStepOne(inputIndex, qty);
          if (isValidNumber !== false) {
            $('#qtyNotAvailableWarning').show();
            input.value = "";
            $(outputID).html("");
            return;
          }
        }

        $(outputID).html((qty * number).toFixed(2));
      }

      if (stepOne) {
        countAndDisplayCashUpTotalCash();
      } else {
        countAndDisplayCashUpTotalBanking();
      }
    }

    function countAndDisplayCashUpTotalBanking() {

      var total = 0.00;
      var allValid = true;
      $('.cashUpBankingInput').each(function() {

        if (!isNaN(this.value) && this.value.length > 0) {

          total += parseFloat($(this).data("den") * this.value);
        } else {
          allValid = false;
        }
      });

      $("#cashUpTotalBanking").html("$" + total.toFixed(2));
      $("#cashUpTotalBankingOutput").html(total.toFixed(2));

      var totalCash = parseFloat($('#cashUpTotalCashOutput').html());
      var totalBanking = parseFloat($('#cashUpTotalBankingOutput').html());
      var floatTotal = totalCash - totalBanking;
      $('#cashUpTotalFloatOutput').html(floatTotal.toFixed(2));
      suggestCashUpErrors(totalCash, totalBanking);

      if (allValid) {

        $('#cashUpSaveButton').show();
      }

    }

    function countAndDisplayCashUpTotalCash() {

      var total = 0.00;
      var allValid = true;
      $('.cashUpTotalInput').each(function() {

        if (!isNaN(this.value) && this.value.length > 0) {
          total += parseFloat($(this).data("den") * this.value);
        } else {
          allValid = false;
        }
      });

      $("#cashUpTotalCash").html("$" + total.toFixed(2));
      $("#cashUpTotalCashOutput").html(total.toFixed(2));

      if (allValid) {

        if (total < 295) {
          $('#totalToLow').html("Your total cash is very low. Are you sure you are entering it correctly?<Br><br>Remember Step 1 includes the entire contents of your till (Float + Banking)");
          $('#totalToLow').show();
        }

        $('#bankingTotalScreen').hide();
        $('#autoFloatButton').show();
      } else {
        $('#bankingTotalScreen').show();
        $('#autoFloatButton').hide();
      }

    }

    function autoWriteTotalsForCashUp() {

      $('.cashUpTotalInput').each(function() {
        if (!isNaN(this.value) && this.value.length > 0) {
          $(this.parentElement.parentElement).find(".cashUpTotalOutput").html((parseFloat($(this).data("den") * this.value)).toFixed(2));
        }
      });

      $('.cashUpBankingInput').each(function() {
        if (!isNaN(this.value) && this.value.length > 0) {
          $(this.parentElement.parentElement).find(".cashUpBankingOutput").html((parseFloat($(this).data("den") * this.value)).toFixed(2));
        }
      });
    }

    function resetCashCalc() {

      $('.cashUpTotalInput').each(function() {
        if (!isNaN(this.value) && this.value.length > 0) {
          this.value = "";
          $(this.parentElement.parentElement).find(".cashUpTotalOutput").html("0.00");
        }
      });

      $('.cashUpBankingInput').each(function() {
        if (!isNaN(this.value) && this.value.length > 0) {
          this.value = "";
          $(this.parentElement.parentElement).find(".cashUpBankingOutput").html("0.00");
        }
      });

      $('#cashUpTotalCash,#cashUpTotalBanking,#cashUpTotalCashOutput,#cashUpTotalBankingOutput,#cashUpTotalFloatOutput').html("0.00");
      $('#bankingTotalScreen').show();
      $('.cashupError,#autoFloatButton').hide();
      localStorage.removeItem("savedCashUp");
      $('#storeCashUpJSON').val("");

    }

    $('.cashUpTotalInput,.cashUpTotalInput,#remainderInTill,#totalCashBanked').on('mousewheel', function(e) {
      $(this).blur();
    });

    if (localStorage.autoSavingPad) {
      $('#autosavingPad').html(localStorage.autoSavingPad);
    }

    if (localStorage.savedCashUp) {

      var saleObject = JSON.parse(localStorage.savedCashUp);

      var tenCents = saleObject.cashTenCents - saleObject.bankingTenCents;
      var twentyCents = saleObject.cashTwentyCents - saleObject.bankingTwentyCents;
      var fifthyCents = saleObject.cashFiftyCents - saleObject.bankingFiftyCents;
      var oneDollar = saleObject.cashOneDollar - saleObject.bankingOneDollar;
      var twoDollar = saleObject.cashTwoDollar - saleObject.bankingTwoDollar;
      var fiveDollar = saleObject.cashFiveDollar - saleObject.bankingFiveDollar;
      var tenDollar = saleObject.cashTenDollar - saleObject.bankingTenDollar;
      var twentyDollar = saleObject.cashTwentyDollar - saleObject.bankingTwentyDollar;
      var fiftyDollar = saleObject.cashFiftyDollar - saleObject.bankingFiftyDollar;
      var hundredDollar = saleObject.cashHundredDollar - saleObject.bankingHundredDollar;

      $('#tenCentInput').val(saleObject.cashTenCents);
      $('#twentyCentInput').val(saleObject.cashTwentyCents);
      $('#fiftyCentInput').val(saleObject.cashFiftyCents);
      $('#oneDollarInput').val(saleObject.cashOneDollar);
      $('#twoDollarInput').val(saleObject.cashTwoDollar);
      $('#fiveDollarInput').val(saleObject.cashFiveDollar);
      $('#tenDollarInput').val(saleObject.cashTenDollar);
      $('#twentyDollarInput').val(saleObject.cashTwentyDollar);
      $('#fifthyDollarInput').val(saleObject.cashFiftyDollar);
      $('#hundredDollarInput').val(saleObject.cashHundredDollar);

      $('#cashUpBankingtenCentInput').val(saleObject.bankingTenCents);
      $('#cashUpBankingtwentyCentInput').val(saleObject.bankingTwentyCents);
      $('#cashUpBankingfiftyCentInput').val(saleObject.bankingFiftyCents);
      $('#cashUpBankingoneDollarInput').val(saleObject.bankingOneDollar);
      $('#cashUpBankingtwoDollarInput').val(saleObject.bankingTwoDollar);
      $('#cashUpBankingfiveDollarInput').val(saleObject.bankingFiveDollar);
      $('#cashUpBankingtenDollarInput').val(saleObject.bankingTenDollar);
      $('#cashUpBankingtwentyDollarInput').val(saleObject.bankingTwentyDollar);
      $('#cashUpBankingfifthyDollarInput').val(saleObject.bankingFiftyDollar);
      $('#cashUpBankinghundredDollarInput').val(saleObject.bankingHundredDollar);

      $('#totalCashBanked').val(saleObject.totalBanking);
      $('#remainderInTill').val(saleObject.totalFloat);
      countAndDisplayCashUpTotalCash();
      countAndDisplayCashUpTotalBanking();
      autoWriteTotalsForCashUp();
      $('#storeCashUpJSON').val(localStorage.savedCashUp);

    }

    function updateNicotineLevel() {

      var _ml = $('#nicotineRemain').val();

      if (_ml.length > 0) {

        $('#updatingLabel').show();

        var _outletID = $('#nicotineOutlet').val();
        var _staffID = <?php if (isset($_SESSION["userID"])) {
                          echo $_SESSION["userID"];
                        } else {
                          echo '';
                        } ?>;

        $.post("assets/functions/ajax.php?method=isLoggedIn", function(data, status) {

          if (data == "true" && _staffID != '') {

            $.post("assets/functions/ajax.php?method=updateNicotineLevel", {
              outletID: _outletID,
              ml: _ml,
              userID: _staffID
            }, function(data, status) {
              $('#updatingLabel').css("color", "green");
              $('#updatingLabel').html("Updating Complete...refreshing now");

              if (typeof nicotineNeedingUpdate !== 'undefined') {
                if (nicotineNeedingUpdate == true) {
                  nicotineNeedingUpdate = false;
                  markTransferComplete();
                }
              } else {
                setTimeout(function() {
                  window.location.assign("/")
                }, 0);
              }


            });

          } else {
            alert("Session has timed out, please refresh and login again.");
          }

        });
      } else {
        alert("Please Select an option");
      } 
    }

    function openNicotineModal(id, name) {

      $('#nicotineOutlet').val(id);
      $('#outletName').html(name);
      $('#nicotineCheckModal').modal('show');

    }
  </script>
<?php } ?>

<!-- CIS Staff Support Chat Bot - DISABLED FOR TESTING -->
<?php /* if (isset($_SESSION["userID"])) { ?>
<link rel="stylesheet" href="<?php echo HTTPS_URL; ?>assets/css/staff-chat-bot.css?v=<?php echo time(); ?>">
<script src="<?php echo HTTPS_URL; ?>assets/js/staff-chat-bot.js?v=<?php echo time(); ?>"></script>
<?php } */ ?>

</body>

</html>