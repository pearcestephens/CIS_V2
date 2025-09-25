(function () {
  "use strict";

  const qs = (s, r = document) => r.querySelector(s);
  const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));

  const params = new URLSearchParams(location.search);
  const transferId = parseInt(params.get("transfer_id") || "0", 10);

  const API = "/modules/transfers/receive/ajax/handler.php";
  const RID = (crypto?.randomUUID?.() || Math.random().toString(16).slice(2));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";

  if (!transferId) {
    console.warn("[Receive] Missing transfer_id");
    return;
  }

  const el = {
    itemsTbody: qs("#receive-items tbody"),
    itemsTotal: qs("#total_items"),
    itemsReceived: qs("#items_received"),
    progressBar: qs("#progress_bar"),
    progressText: qs("#progress_text"),
    scanInput: qs("#scan-input"),
    reqId: qs("#request-id"),
    saveBtn: qs("#btn-save-receipt")
  };

  async function post(action, body) {
    const res = await fetch(API, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Request-ID": RID,
        "X-CSRF-Token": csrf
      },
      body: JSON.stringify(Object.assign({ action }, body || {})),
      cache: "no-store"
    }).catch(() => null);

    if (!res) return { ok: false, error: "network" };
    const j = await res.json().catch(() => ({ ok: false, error: "bad_json" }));
    return j;
  }

  function render(items) {
    el.itemsTbody.innerHTML = "";
    let total = 0, received = 0;

    items.forEach(r => {
      const tr = document.createElement("tr");
      tr.dataset.itemId = r.id;
      total += Number(r.expected || 0);
      received += Number(r.received || 0);

      tr.innerHTML = `
        <td class="mono">${r.sku || r.product_id}</td>
        <td>${r.name || ""}</td>
        <td class="text-end">${r.expected}</td>
        <td class="text-end">
          <input class="form-control form-control-sm rc-qty" type="number" min="0" value="${r.received || 0}" style="width:90px">
        </td>`;
      el.itemsTbody.appendChild(tr);
    });

    el.itemsTotal.textContent = String(total);
    el.itemsReceived.textContent = String(received);

    const pct = total ? Math.min(100, Math.floor((received / total) * 100)) : 0;
    el.progressBar.style.width = pct + "%";
    el.progressText.textContent = pct + "% Complete";
  }

  async function load() {
    const j = await post("get_shipment", { transfer_id: transferId });
    if (!j || j.ok !== true) {
      alert("Failed to load shipment");
      return;
    }
    const items = j.items || [];
    render(items);
    el.reqId && (el.reqId.textContent = "req " + (j.request_id || RID));
  }

  async function saveReceipt() {
    const items = qsa("#receive-items tbody tr").map(tr => ({
      transfer_item_id: parseInt(tr.dataset.itemId || "0", 10),
      qty_received: parseInt(qs(".rc-qty", tr)?.value || "0", 10),
      condition: "ok",
      notes: ""
    }));

    const j = await post("save_receipt", { transfer_id: transferId, items });
    if (!j || j.ok !== true) {
      alert("Save failed");
      return;
    }
    alert("Receipt saved: #" + j.receipt_id);
    await load();
  }

  function updateProgressUI() {
    const rows = qsa("#receive-items tbody tr");
    let expected = 0, received = 0;
    rows.forEach(tr => {
      expected += parseInt(tr.children[2].textContent || "0", 10) || 0;
      received += parseInt(qs(".rc-qty", tr)?.value || "0", 10) || 0;
    });
    el.itemsTotal.textContent = String(expected);
    el.itemsReceived.textContent = String(received);
    const pct = expected ? Math.floor((received / expected) * 100) : 0;
    el.progressBar.style.width = Math.min(100, pct) + "%";
    el.progressText.textContent = pct + "% Complete";
  }

  // Events
  qs("#receive-items")?.addEventListener("input", e => {
    if (e.target.classList.contains("rc-qty")) updateProgressUI();
  });

  el.scanInput?.addEventListener("keypress", async (e) => {
    if (e.key !== "Enter") return;
    e.preventDefault();
    const value = (el.scanInput.value || "").trim();
    if (!value) return;
    const res = await post("scan_or_select", { transfer_id: transferId, type: "item", value, qty: 1 });
    if (!res || res.ok !== true) alert("Scan failed");
    el.scanInput.value = "";
    await load();
  });

  el.saveBtn?.addEventListener("click", saveReceipt);

  // Boot
  load().catch(() => {});
})();
