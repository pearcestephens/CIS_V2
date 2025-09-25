// /modules/transfers/stock/js/pack.js
(function () {
  "use strict";

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const transferId = parseInt($("#transfer_id")?.value || "0", 10) || 0;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";

  const apiHandler = "/modules/transfers/stock/ajax/handler.php";
  const apiQueueLabel = "/modules/transfers/stock/ajax/queue.label.php";

  function jfetch(url, bodyObj, opts = {}) {
    const headers = {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrf
    };
    return fetch(url, {
      method: "POST",
      headers,
      body: JSON.stringify(bodyObj || {}),
      ...opts
    }).then(async r => {
      const j = await r.json().catch(() => ({}));
      if (!r.ok || j.ok === false || j.success === false) {
        const err = (j.error && (j.error.message || j.error.code)) || "request_failed";
        throw new Error(err);
      }
      return j.data || j;
    });
  }

  function fmt(n) {
    return new Intl.NumberFormat("en-NZ").format(n);
  }

  // State
  let items = []; // {id, product_id, sku, name, requested_qty, unit_g, suggested_ship_units}

  // Render items table
  function renderItems() {
    const tbody = $("#tblItems tbody");
    tbody.innerHTML = "";
    let totalWeight = 0;

    items.forEach(it => {
      const shipUnits = it.suggested_ship_units ?? Math.max(1, it.requested_qty || 0);
      const weight = (it.unit_g || 100) * shipUnits;
      totalWeight += weight;

      const tr = document.createElement("tr");
      tr.dataset.itemId = it.id;
      tr.dataset.productId = it.product_id;

      tr.innerHTML = `
        <td>
          <div class="kv">${it.sku || it.product_id}</div>
          <div class="small text-muted">${it.name || ""}</div>
        </td>
        <td>${fmt(it.requested_qty || 0)}</td>
        <td><input class="form-control form-control-sm qty-input" type="number" min="0" value="${shipUnits}"></td>
        <td class="ship-units">${fmt(shipUnits)}</td>
        <td class="weight-g">${fmt(weight)}</td>
      `;
      tbody.appendChild(tr);
    });

    $("#sum-weight").textContent = fmt(totalWeight);
    $("#sum-parcels").textContent = $("#parcelList .parcel-row").length.toString();
  }

  function collectPlan() {
    // very simple MVP: 1 parcel row UI; if none, backend will auto-attach anyway
    const rows = $$("#tblItems tbody tr");
    const itemsPlan = rows.map(tr => {
      const qty = parseInt($(".qty-input", tr)?.value || "0", 10) || 0;
      return {
        item_id: parseInt(tr.dataset.itemId || "0", 10) || undefined,
        product_id: parseInt(tr.dataset.productId || "0", 10) || undefined,
        qty
      };
    }).filter(x => (x.qty || 0) > 0);

    // Parcel weight from UI (g)
    const weightInput = $(".parcel-weight-input");
    const weight_g = parseInt(weightInput?.value || "0", 10) || 0;

    return {
      parcels: [{
        weight_g: weight_g > 0 ? weight_g : undefined,
        items: itemsPlan
      }]
    };
  }

  function refreshParcels() {
    return jfetch(apiHandler, { action: "get_parcels", transfer_id: transferId })
      .then(d => {
        const { parcels = [] } = d;
        $("#sum-parcels").textContent = fmt(parcels.length || 0);
        const totalKg = parcels.reduce((a, p) => a + (p.weight_kg || 0), 0);
        $("#sum-weight").textContent = fmt(Math.round(totalKg * 1000));
        // render quick list
        const box = $("#parcelList");
        box.innerHTML = "";
        parcels.forEach(p => {
          const div = document.createElement("div");
          div.className = "parcel-line";
          div.innerHTML = `<span class="text-muted">#${p.box_number}</span> · ${fmt(p.weight_kg || 0)} kg · ${fmt(p.items_count || 0)} items`;
          box.appendChild(div);
        });
      })
      .catch(() => { /* ignore */ });
  }

  // actions
  async function loadItems() {
    const data = await jfetch(apiHandler, { action: "list_items", transfer_id: transferId });
    items = (data.items || []).map(r => ({
      id: r.id,
      product_id: r.product_id,
      sku: r.sku,
      name: r.name,
      requested_qty: r.requested_qty,
      unit_g: r.unit_g ?? 100,
      suggested_ship_units: r.suggested_ship_units ?? (r.requested_qty || 1)
    }));
    renderItems();
  }

  async function savePackNote() {
    const notes = ($("#pack-notes")?.value || "").trim();
    if (!notes) return;
    await jfetch(apiHandler, { action: "save_pack", transfer_id: transferId, notes });
  }

  async function generateLabel(carrier) {
    const btn = carrier === "MVP" ? $("#btn-label-gss") : $("#btn-label-nzpost");
    btn.disabled = true;
    try {
      // Build plan (backend will auto attach items if empty)
      const plan = collectPlan();

      const res = await fetch(apiQueueLabel, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          transfer_pk: transferId,
          carrier: carrier || "MVP",
          parcel_plan: plan
        })
      }).then(r => r.json());

      if (!res || res.ok !== true) {
        const msg = (res && res.error && (res.error.message || res.error.code)) || "label_failed";
        throw new Error(msg);
      }

      // Save note so we keep context
      await savePackNote();

      // Reflect parcels
      await refreshParcels();

      toast("Label created.");
    } catch (e) {
      console.error(e);
      alert("Generate label failed: " + (e.message || e));
    } finally {
      btn.disabled = false;
    }
  }

  function toast(msg) {
    const el = document.createElement("div");
    el.className = "pack-toast";
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add("show"));
    setTimeout(() => { el.classList.remove("show"); el.remove(); }, 2500);
  }

  // UI wiring
  function wire() {
    // Update derived cells when qty changes
    $("#tblItems").addEventListener("input", (ev) => {
      if (!ev.target.classList.contains("qty-input")) return;
      const tr = ev.target.closest("tr");
      const it = items.find(x => x.id === parseInt(tr.dataset.itemId || "0", 10));
      const su = Math.max(0, parseInt(ev.target.value || "0", 10) || 0);
      const unitG = it?.unit_g || 100;
      $(".ship-units", tr).textContent = fmt(su);
      $(".weight-g", tr).textContent = fmt(su * unitG);
      // recompute total weight
      let total = 0;
      $$("#tblItems tbody tr").forEach(row => {
        total += parseInt($(".weight-g", row)?.textContent.replace(/,/g, "") || "0", 10) || 0;
      });
      $("#sum-weight").textContent = fmt(total);
    });

    // Add another parcel row (MVP)
    $("#parcelList").addEventListener("click", (e) => {
      if (!e.target.classList.contains("add-row")) return;
      const list = $("#parcelList");
      const idx = $$(".parcel-row", list).length + 1;
      const row = document.createElement("div");
      row.className = "parcel-row mb-2";
      row.innerHTML = `
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted">#${idx}</span>
          <input type="number" min="0" class="form-control form-control-sm parcel-weight-input" placeholder="Weight(g)" style="width:140px">
          <button class="btn btn-sm btn-outline-danger remove-row" type="button">Remove</button>
        </div>`;
      list.appendChild(row);
      $("#sum-parcels").textContent = fmt(idx);
    });
    $("#parcelList").addEventListener("click", e => {
      if (!e.target.classList.contains("remove-row")) return;
      const row = e.target.closest(".parcel-row");
      row?.remove();
      $("#sum-parcels").textContent = fmt($$(".parcel-row").length);
    });

    $("#btn-label-gss").addEventListener("click", () => generateLabel("MVP"));
    // (NZPost placeholder – disabled in UI)
    $("#btn-label-nzpost")?.addEventListener("click", () => generateLabel("NZPost"));

    $("#btn-save-pack").addEventListener("click", async () => {
      try {
        await savePackNote();
        toast("Pack saved.");
      } catch (e) {
        alert("Save failed: " + (e.message || e));
      }
    });
  }

  async function boot() {
    if (!transferId) {
      alert("Missing transfer_id");
      return;
    }
    $("#request-id")?.textContent = "";
    await loadItems();
    await refreshParcels();
    wire();
  }

  document.addEventListener("DOMContentLoaded", boot);
})();
