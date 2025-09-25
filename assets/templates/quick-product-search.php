<?php
/**
 * /assets/templates/cisv2/quick-product-search.php
 *
 * Quick product search & stock adjustment UI.
 * Provides input table for coins/notes, float warnings,
 * and live updates for cash-up/banking totals.
 */
?>
<section id="quickProductSearch" class="cis-quick-search">
  <div class="row">

    <!-- Step 1: Cash Entry -->
    <div class="col-6">
      <h5>Step 1 - Count Cash</h5>
      <table class="table table-bordered table-striped table-sm">
        <thead>
          <tr>
            <td>Denomination</td>
            <td>Qty</td>
            <td>Total</td>
          </tr>
        </thead>
        <tbody>
          <!-- Example row: $100 notes -->
          <tr>
            <td>$100.00 Notes:</td>
            <td>
              <input
                data-den="100.00"
                class="cashUpTotalInput"
                type="number"
                min="0"
                onkeyup="storeCashupCalc(this,100.00,'#cashUpTotalhundredDollarOutput','#cashUpTotalCash',true,event);"
              >
            </td>
            <td><span class="cashUpTotalOutput" id="cashUpTotalhundredDollarOutput"></span></td>
          </tr>
          <!-- Repeat rows for $50, $20, $10, etc. -->
        </tbody>
      </table>
      <p id="float-error" class="error text-danger small d-none">You must fill out every box</p>
      <div>
        <strong>Total Cash: <span id="cashUpTotalCash">$0.00</span></strong>
      </div>
    </div>

    <!-- Step 2: Total Banking -->
    <div class="col-6">
      <h5>Step 2 - Total Banking</h5>
      <p class="small">Total cash to be banked and put in safe</p>
      <div id="bankingTotalScreen" class="overlay d-none">
        <p class="text-white text-center">Complete Step 1 To Continue</p>
      </div>
      <table class="table table-bordered table-striped table-sm">
        <thead>
          <tr>
            <td>Denomination</td>
            <td>Qty</td>
            <td>Total</td>
          </tr>
        </thead>
        <tbody>
          <!-- Example row: $100 notes banking -->
          <tr>
            <td>$100.00 Notes:</td>
            <td>
              <input
                data-den="100.00"
                class="cashUpBankingInput"
                type="number"
                min="0"
                onkeyup="storeCashupCalc(this,100.00,'#cashUpBankinghundredDollarOutput','#cashUpTotalBanking',false,event);"
              >
            </td>
            <td><span class="cashUpBankingOutput" id="cashUpBankinghundredDollarOutput"></span></td>
          </tr>
          <!-- Repeat rows for $50, $20, $10, etc. -->
        </tbody>
      </table>
      <div>
        <strong>Total Banking: <span id="cashUpTotalBanking">$0.00</span></strong>
      </div>
    </div>

  </div>
</section>
