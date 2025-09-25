/* Purchase Orders — Receive: table helpers (selection, totals) */
(() => {
  const tb = document.querySelector('#receiving_table tbody');
  if (!tb) return;

  function recalcFooter() {
    const rows = [...tb.querySelectorAll('tr')];
    let expected = 0, received = 0, completed = 0;
    rows.forEach(tr => {
      const e = parseInt(tr.querySelector('td:nth-child(3) .badge')?.textContent || '0', 10);
      const r = parseInt(tr.querySelector('input.rx-recv')?.value || '0', 10);
      expected += e; received += r; if (r > 0) completed++;
    });
    const te = document.querySelector('#total_expected');
    const trc = document.querySelector('#total_received_display');
    const items = document.querySelector('#total_items');
    const rec = document.querySelector('#items_received');
    te && (te.textContent = String(expected));
    trc && (trc.textContent = String(received));
    items && (items.textContent = String(rows.length));
    rec && (rec.textContent = String(completed));
  }

  tb.addEventListener('input', (e) => {
    if (e.target.classList.contains('rx-recv')) {
      recalcFooter();
    }
  });

  recalcFooter();
})();
