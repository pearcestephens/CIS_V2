(function () {
  "use strict";

  const screen = document.querySelector(".receive-screen");
  if (!screen) {
    return;
  }

  const configRaw = screen.getAttribute("data-receive-config") || "{}";
  let config;
  try {
    config = JSON.parse(configRaw);
  } catch (err) {
    console.error("Failed to parse receive config", err);
    return;
  }

  const requestBaseId = config.request_id || (crypto?.randomUUID?.() || Date.now().toString(16));

  const els = {
    table: document.getElementById("receive-items"),
    qtyInputs: () => Array.from(document.querySelectorAll("#receive-items .qty-input")),
    outstandingCells: () => Array.from(document.querySelectorAll("#receive-items tbody tr")),
    metricReceived: document.getElementById("metric-items-received"),
    metricOutstanding: document.getElementById("metric-items-outstanding"),
    parcelTotals: {
      total: document.getElementById("metric-parcels-total"),
      received: document.getElementById("metric-parcels-received"),
      missing: document.getElementById("metric-parcels-missing"),
      damaged: document.getElementById("metric-parcels-damaged"),
    },
    finalizeBtn: document.getElementById("btn-finalize"),
    refreshBtn: document.getElementById("btn-refresh"),
    qrBtn: document.getElementById("btn-generate-qr"),
    feedback: document.getElementById("receive-feedback"),
    discrepancyForm: document.getElementById("form-discrepancy"),
    parcelDeclareForm: document.getElementById("form-parcel-declare"),
    discrepancyList: document.getElementById("discrepancy-list"),
    mediaList: document.getElementById("media-list"),
  };

  const debounceHandles = new WeakMap();
  let finalizeBusy = false;

  function showFeedback(message, type = "info", durationMs = 3200) {
    if (!els.feedback) return;
    els.feedback.className = `small text-${type}`;
    els.feedback.textContent = message;
    if (durationMs > 0) {
      setTimeout(() => {
        if (els.feedback?.textContent === message) {
          els.feedback.textContent = "";
        }
      }, durationMs);
    }
  }

  function buildHeaders(extra = {}) {
    return Object.assign({
      "Content-Type": "application/json",
      "X-CSRF-Token": config.csrf || "",
      "X-Request-ID": `${requestBaseId}-${Date.now()}`
    }, extra || {});
  }

  async function apiPost(url, payload) {
    const res = await fetch(url, {
      method: "POST",
      headers: buildHeaders(),
      body: JSON.stringify(payload || {}),
      cache: "no-store",
    }).catch((err) => {
      console.error("receive.api", err);
      return null;
    });
    if (!res) {
      return { ok: false, error: "network" };
    }
    const data = await res.json().catch(() => ({ ok: false, error: "invalid_json" }));
    if (!res.ok && data && typeof data === "object") {
      data.ok = false;
    }
    return data;
  }

  function updateItemMetrics() {
    let received = 0;
    let outstanding = 0;

    document.querySelectorAll("#receive-items tbody tr").forEach((row) => {
      const expected = Number(row.getAttribute("data-expected")) || 0;
      const input = row.querySelector(".qty-input");
      const qty = input ? Number(input.value) || 0 : 0;

      const outstandingCell = row.querySelector(".outstanding-cell");
      const diff = Math.max(0, expected - qty);
      if (outstandingCell) {
        outstandingCell.textContent = String(diff);
      }

      received += qty;
      outstanding += diff;
    });

    if (els.metricReceived) {
      els.metricReceived.textContent = String(received);
    }
    if (els.metricOutstanding) {
      els.metricOutstanding.textContent = String(outstanding);
    }
  }

  async function commitQuantity(row, qty) {
    const itemId = Number(row.getAttribute("data-item-id")) || 0;
    if (!itemId || qty < 0) {
      return;
    }
    const payload = {
      item_id: itemId,
      qty: qty,
    };
    const { endpoints } = config;
    showFeedback(`Saving quantity…`, "muted", 2000);
    const res = await apiPost(endpoints.set_qty, payload);
    if (!res || res.ok !== true) {
      showFeedback(res?.error || "Failed to save quantity", "danger", 6000);
      return;
    }
    updateItemMetrics();
    showFeedback("Quantity saved", "success", 2000);
  }

  function scheduleQuantityCommit(row, qty) {
    if (!row) return;
    if (debounceHandles.has(row)) {
      clearTimeout(debounceHandles.get(row));
    }
    const handle = setTimeout(() => {
      commitQuantity(row, qty).catch((err) => console.error(err));
    }, 420);
    debounceHandles.set(row, handle);
  }

  function onQuantityInput(event) {
    const input = event.target;
    if (!input.classList.contains("qty-input")) return;
    const row = input.closest("tr");
    if (!row) return;
    const expected = Number(row.getAttribute("data-expected")) || 0;
    let value = Number(input.value);
    if (Number.isNaN(value) || value < 0) {
      value = 0;
    }
    if (expected > 0 && value > expected) {
      value = expected;
      input.value = String(expected);
    }
    scheduleQuantityCommit(row, value);
    updateItemMetrics();
  }

  async function handleDiscrepancySubmit(event) {
    event.preventDefault();
    if (!config.endpoints?.add_discrepancy) return;

    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    const payload = {
      transfer_id: config.transferId,
      product_id: data.product_id || "",
      type: data.type || "missing",
      qty: Number(data.qty || 0),
      notes: data.notes || "",
    };
    if (data.item_id) {
      payload.item_id = Number(data.item_id);
    }

    showFeedback("Logging discrepancy…", "muted");
    const res = await apiPost(config.endpoints.add_discrepancy, payload);
    if (!res || res.ok !== true) {
      showFeedback(res?.error || "Failed to log discrepancy", "danger", 6000);
      return;
    }
    showFeedback("Discrepancy recorded", "warning", 4000);
    form.reset();
    setTimeout(() => window.location.reload(), 800);
  }

  async function handleParcelDeclare(event) {
    event.preventDefault();
    const { endpoints } = config;
    if (!endpoints?.parcel_action) return;

    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    const payload = {
      action: "declare",
      transfer_id: config.transferId,
      box_number: Number(data.box_number || 0),
      weight_kg: data.weight_kg ? Number(data.weight_kg) : null,
      status: data.status || "received",
      notes: data.notes || null,
    };

    showFeedback("Declaring parcel…", "muted");
    const res = await apiPost(endpoints.parcel_action, payload);
    if (!res || res.ok !== true) {
      showFeedback(res?.error || "Parcel declaration failed", "danger", 6000);
      return;
    }
    showFeedback("Parcel recorded", "success", 2500);
    form.reset();
    setTimeout(() => window.location.reload(), 750);
  }

  async function handleParcelAction(event) {
    const btn = event.target.closest(".parcel-action");
    if (!btn) return;
    event.preventDefault();

    const action = btn.getAttribute("data-action");
    const parcelId = Number(btn.getAttribute("data-parcel")) || 0;
    if (!action || !parcelId) {
      return;
    }

    showFeedback("Updating parcel…", "muted");
    const res = await apiPost(config.endpoints.parcel_action, {
      action,
      parcel_id: parcelId,
      transfer_id: config.transferId,
    });
    if (!res || res.ok !== true) {
      showFeedback(res?.error || "Parcel update failed", "danger", 6000);
      return;
    }
    showFeedback("Parcel updated", "success", 2400);
    setTimeout(() => window.location.reload(), 640);
  }

  async function finalizeReceive() {
    if (finalizeBusy) return;
    finalizeBusy = true;
    try {
      const outstanding = Number(els.metricOutstanding?.textContent || "0") || 0;
      if (outstanding > 0) {
        const proceed = confirm(`There are still ${outstanding} items outstanding. Finalize anyway?`);
        if (!proceed) {
          finalizeBusy = false;
          return;
        }
      }
      showFeedback("Finalizing receive…", "muted", 6000);
      const res = await apiPost(config.endpoints.finalize, {
        transfer_id: config.transferId,
      });
      if (!res || res.ok !== true) {
        showFeedback(res?.error || "Finalize failed", "danger", 8000);
        finalizeBusy = false;
        return;
      }
      showFeedback(res.complete ? "Transfer fully received" : "Partial receive noted", res.complete ? "success" : "warning", 6000);
      setTimeout(() => {
        if (res.complete && config.redirect_on_complete) {
          window.location.href = config.redirect_on_complete;
        } else {
          window.location.reload();
        }
      }, 1200);
    } finally {
      finalizeBusy = false;
    }
  }

  async function createUploadToken(context = {}) {
    const { endpoints } = config;
    if (!endpoints?.create_upload_token) {
      showFeedback("Upload endpoint unavailable", "danger");
      return null;
    }
    const payload = Object.assign({
      transfer_id: config.transferId,
    }, context || {});
    const res = await apiPost(endpoints.create_upload_token, payload);
    if (!res || res.ok !== true || !res.token) {
      showFeedback(res?.error || "Failed to create upload token", "danger", 6000);
      return null;
    }
    return res;
  }

  async function launchQrUpload(context) {
    const tokenData = await createUploadToken(context);
    if (!tokenData) return;
    const qrUrl = tokenData.qr_url || `${config.endpoints.media_qr}?token=${encodeURIComponent(tokenData.token)}`;
    try {
      window.open(qrUrl, "receive-upload", "width=480,height=720");
    } catch (err) {
      console.warn("QR window blocked", err);
      showFeedback("QR window blocked – copy link manually", "warning", 6000);
      navigator.clipboard?.writeText(qrUrl).catch(() => {});
    }
  }

  function attachEvents() {
    if (els.table) {
      els.table.addEventListener("input", onQuantityInput);
      els.table.addEventListener("change", onQuantityInput);
      els.table.addEventListener("blur", (event) => {
        if (event.target.classList?.contains("qty-input")) {
          const row = event.target.closest("tr");
          const qty = Number(event.target.value) || 0;
          commitQuantity(row, qty).catch(() => {});
        }
      }, true);
    }

    document.body.addEventListener("click", handleParcelAction);

    if (els.discrepancyForm) {
      els.discrepancyForm.addEventListener("submit", handleDiscrepancySubmit);
    }

    if (els.parcelDeclareForm) {
      els.parcelDeclareForm.addEventListener("submit", handleParcelDeclare);
    }

    if (els.finalizeBtn) {
      els.finalizeBtn.addEventListener("click", finalizeReceive);
    }

    if (els.refreshBtn) {
      els.refreshBtn.addEventListener("click", () => window.location.reload());
    }

    if (els.qrBtn) {
      els.qrBtn.addEventListener("click", () => launchQrUpload({ scope: "transfer" }));
    }

    document.body.addEventListener("click", (event) => {
      const target = event.target.closest(".parcel-qr");
      if (!target) return;
      event.preventDefault();
      const parcelId = Number(target.getAttribute("data-parcel")) || null;
      launchQrUpload({ scope: "parcel", parcel_id: parcelId }).catch(() => {});
    });
  }

  attachEvents();
  updateItemMetrics();
})();
