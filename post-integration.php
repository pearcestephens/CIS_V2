<!-- =========================== -->
<!--  CIS Shipping Label Plugin  -->
<!--  (NZ Post eSHIP + GSS)      -->
<!-- =========================== -->

<div id="cis-ship-plugin"></div>

<style>
  .cis-ship{border:1px solid #e4e7ec;border-radius:12px;padding:16px;background:#fafbfc}
  .cis-ship h4{margin:0 0 12px 0;font-size:18px}
  .cis-ship .row{display:flex;gap:12px;flex-wrap:wrap}
  .cis-ship .col{flex:1 1 320px;min-width:320px}
  .cis-ship .card{border:1px solid #e4e7ec;border-radius:10px;background:#fff;padding:12px}
  .cis-ship label{font-size:12px;co    onSuccessRedirect: function(s){
      // mirror your old behavior: prefer referer with &status=6
      if (document.referrer) return document.referrer + (document.referrer.includes('?')?'&':'?') + 'status=6';
      return '/orders-overview-outlet.php?outletID=' + encodeURIComponent(s.outletID) + '&status=6';
    },ttom:4px;display:block}
  .cis-ship input[type="text"],.cis-ship input[type="number"],.cis-ship select,.cis-ship textarea{width:100%;border:1px solid #d0d5dd;border-radius:8px;padding:8px;font-size:14px}
  .cis-ship .stack{display:grid;gap:8px}
  .cis-ship .row-compact{display:flex;gap:8px}
  .cis-ship .row-compact>*{flex:1}
  .cis-ship .switcher{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
  .cis-ship .switcher button{border:1px solid #d0d5dd;background:#fff;border-radius:20px;padding:6px 12px;cursor:pointer}
  .cis-ship .switcher button.active{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .cis-ship .status{margin-top:10px;font-size:13px}
  .cis-ship .status.ok{color:#0a7a20}
  .cis-ship .status.warn{color:#9a6b00}
  .cis-ship .status.err{color:#b42318}
  .cis-ship .btn{border:0;border-radius:8px;padding:10px 14px;cursor:pointer;font-weight:600}
  .cis-ship .btn-primary{background:#0d6efd;color:#fff}
  .cis-ship .btn-secondary{background:#e9edf5;color:#111}
  .cis-ship .btn-danger{background:#d92d20;color:#fff}
  .cis-ship .btn:disabled{opacity:.6;cursor:not-allowed}
  .cis-ship small.muted{color:#6b7280;display:block;margin-top:4px}
  .cis-ship .badge{display:inline-block;background:#eef2ff;border:1px solid #d0d5dd;padding:2px 8px;border-radius:999px;font-size:12px}
  .cis-ship td[data-cell="status"].ok{color:#0a7a20}
  .cis-ship td[data-cell="status"].warn{color:#9a6b00}
  .cis-ship td[data-cell="status"].err{color:#b42318}
  .cis-ship .hr{height:1px;background:#eef2f7;margin:12px 0}
  .cis-ship .dim-table{width:100%;border-collapse:collapse}
  .cis-ship .dim-table th,.cis-ship .dim-table td{padding:6px;border-bottom:1px solid #eef2f7;font-size:13px}
  .cis-ship .dim-table input{text-align:center}
  .cis-hidden{display:none!important}
</style>

<!-- Address Adjust Modal -->
<div id="cis-addr-modal" class="cis-hidden">
  <div style="position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:99999;">
    <div style="width:520px; max-width:95vw; background:#fff; border-radius:12px; padding:16px; border:1px solid #e4e7ec;">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <h5 style="margin:0;">Adjust Address</h5>
        <button type="button" id="cis-addr-close" class="btn btn-secondary" style="padding:6px 10px;">Close</button>
      </div>
      <div class="hr"></div>
      <div class="stack">
        <div class="row-compact">
          <div><label>Company</label><input type="text" id="cis-addr-company" /></div>
        </div>
        <div><label>Street 1</label><input type="text" id="cis-addr-st1" /></div>
        <div><label>Street 2</label><input type="text" id="cis-addr-st2" /></div>
        <div class="row-compact">
          <div><label>Suburb</label><input type="text" id="cis-addr-suburb" /></div>
          <div><label>Postcode</label><input type="text" id="cis-addr-postcode" /></div>
        </div>
        <div class="row-compact">
          <div><label>City</label><input type="text" id="cis-addr-city" /></div>
          <div>
            <label>Ticket Type (GSS)</label>
            <select id="cis-addr-ticket">
              <option value="e20">E20</option>
              <option value="e40">E40</option>
              <option value="e60">E60</option>
            </select>
          </div>
        </div>
        <small class="muted">We’ll reuse the current Signature/Saturday/Create-Shipment settings for the manual run.</small>
      </div>
      <div class="hr"></div>
      <div style="display:flex; gap:8px; justify-content:flex-end;">
        <button type="button" id="cis-addr-try" class="btn btn-primary">Attempt Label with Adjusted Address</button>
      </div>
      <div id="cis-addr-status" class="status"></div>
    </div>
  </div>
</div>

<script>
const CISShipPlugin = (()=>{

  // ---------------- utils ----------------
  const qs  =(s, r=document)=>r.querySelector(s);
  const qsa =(s, r=document)=>Array.from(r.querySelectorAll(s));
  const el  =(tag, attrs={}, kids=[])=>{
    const n=document.createElement(tag);
    Object.entries(attrs).forEach(([k,v])=>{
      if(k==='class') n.className=v;
      else if(k==='html') n.innerHTML=v;
      else n.setAttribute(k,v);
    });
    kids.forEach(c=>n.appendChild(c));
    return n;
  };
  const setStatus =(node,msg,type='')=>{
    node.textContent = msg||'';
    node.classList.remove('ok','warn','err');
    if(type) node.classList.add(type);
  };
  const disableGroup=(nodes,disabled=true)=>nodes.forEach(n=>n.disabled=disabled);
  const toParams=(obj,prefix,params=new URLSearchParams())=>{
    if (obj===null || obj===undefined) return params;
    if (Array.isArray(obj)) obj.forEach((v,i)=>toParams(v, `${prefix}[${i}]`, params));
    else if (typeof obj==='object') Object.keys(obj).forEach(k=>toParams(obj[k], prefix?`${prefix}[${k}]`:k, params));
    else params.append(prefix, obj);
    return params;
  };

  // ---------------- state ----------------
  let S = {
    mount:null,
    orderID:0, outletID:0, userID:0,
    supportGSS:false, supportNZPost:false,
    gssToken:'', nzPostApiKey:'', nzPostSubKey:'',
    defaultSignature:true, defaultSaturday:false, defaultCreateShipment:true,
    defaultCarrier:'NZ Post Domestic',
    onSuccessRedirect:null,
    autoRedirect:false,               // stay on page by default
    onSuccess:null,
    gss:{ signature:true, saturday:false, createShipment:true, instructions:'' },
    nzp:{ saturday:false, printNow:true, serviceCode:'CPOLTPDL', packages:[] }
  };

  // ---------------- render root ----------------
  const render=()=>{
    S.mount.innerHTML='';
    const root=el('div',{class:'cis-ship'});
    root.appendChild(el('h4',{html:'Shipping Label'}));

    const switcher=el('div',{class:'switcher'});
    if (S.supportNZPost) switcher.appendChild(el('button',{type:'button',class:'active',id:'cis-tab-nzpost-btn',html:'NZ Post eSHIP'}));
    if (S.supportGSS)    switcher.appendChild(el('button',{type:'button',id:'cis-tab-gss-btn',html:'NZ Couriers (GSS)'}));
    root.appendChild(switcher);

    const body=el('div',{class:'row'});
    if (S.supportNZPost) body.appendChild(buildNZPostCard());
    if (S.supportGSS)    body.appendChild(buildGSSCard());
    S.mount.appendChild(root);

    if (S.supportNZPost && S.supportGSS){
      const btnN=qs('#cis-tab-nzpost-btn',root), btnG=qs('#cis-tab-gss-btn',root);
      const cardN=qs('#cis-card-nzpost',root),   cardG=qs('#cis-card-gss',root);
      const activate=(which)=>{
        if (which==='nzp'){ btnN.classList.add('active'); btnG.classList.remove('active'); cardN.classList.remove('cis-hidden'); cardG.classList.add('cis-hidden'); }
        else { btnG.classList.add('active'); btnN.classList.remove('active'); cardG.classList.remove('cis-hidden'); cardN.classList.add('cis-hidden'); }
      };
      btnN.onclick=()=>activate('nzp'); btnG.onclick=()=>activate('gss'); activate('nzp');
    } else {
      if (S.supportNZPost && !S.supportGSS) qs('#cis-tab-nzpost-btn')?.classList.add('active');
      if (!S.supportNZPost && S.supportGSS) qs('#cis-tab-gss-btn')?.classList.add('active');
    }
  };

  // ---------------- NZ Post ----------------
  const NZPOST_SERVICES = [
    {code:'CPOLTPDL', label:'Courier Pack DLE (Overnight)',         carrier:'NZ Post Domestic', needsDims:false},
    {code:'CPOLTPA5', label:'Courier Pack A5 (Overnight)',          carrier:'NZ Post Domestic', needsDims:false},
    {code:'CPOLTPA4', label:'Courier Pack A4 (Overnight)',          carrier:'NZ Post Domestic', needsDims:false},
    {code:'CPOLP',    label:'Courier Pack Parcel (Overnight)',      carrier:'NZ Post Domestic', needsDims:true},
    {code:'CPOLE',    label:'Courier Pack Economy Parcel (2–3 Days)',carrier:'NZ Post Domestic', needsDims:true},
  ];

  const buildNZPostCard=()=>{
    const card=el('div',{class:'col',id:'cis-card-nzpost'});
    const box =el('div',{class:'card'});
    box.appendChild(el('div',{html:'<span class="badge">NZ Post eSHIP</span>'}));
    const stack=el('div',{class:'stack'});

    const svcRow=el('div',{class:'row-compact'});
    const svcSel=el('select',{id:'cis-nzp-service'});
    NZPOST_SERVICES.forEach(s=>{
      const o=el('option',{value:s.code,html:s.label}); if (s.code===S.nzp.serviceCode) o.selected=true; svcSel.appendChild(o);
    });
    svcRow.appendChild(el('div',{},[el('label',{html:'Select Service'}), svcSel]));

    const optsRow=el('div',{class:'row-compact'});
    const sat=el('input',{type:'checkbox',id:'cis-nzp-saturday'}); if (S.defaultSaturday) sat.checked=true;
    const printNow=el('input',{type:'checkbox',id:'cis-nzp-printnow'}); printNow.checked=true;
    optsRow.appendChild(el('div',{},[
      el('label',{html:'Options'}),
      el('div',{class:'row-compact'},[
        el('label',{},[sat,document.createTextNode(' Saturday Delivery')]),
        el('label',{},[printNow,document.createTextNode(' Create Shipment & Print Label')]),
      ])
    ]));

    const dimsWrap=el('div',{id:'cis-nzp-dims',class:'cis-hidden'});
    dimsWrap.appendChild(el('label',{html:'Package Dimensions (cm/kg)'}));
    const dimTable=el('table',{class:'dim-table',id:'cis-nzp-dimtable'});
    dimTable.innerHTML=`
      <thead><tr><th>Length</th><th>Width</th><th>Height</th><th>Weight</th><th></th></tr></thead>
      <tbody></tbody>`;
    dimsWrap.appendChild(dimTable);
    dimsWrap.appendChild(el('div',{style:'display:flex;gap:8px;'},[
      el('button',{type:'button',class:'btn btn-secondary',id:'cis-nzp-add-dim',html:'Add Package Row'})
    ]));

    const instr=el('textarea',{id:'cis-nzp-notes',rows:'2',placeholder:'Delivery instructions (optional)'});
    const submit=el('button',{type:'button',class:'btn btn-primary',id:'cis-nzp-create',html:'Create NZ Post Label'});
    const status=el('div',{class:'status',id:'cis-nzp-status'});

    stack.appendChild(svcRow);
    stack.appendChild(optsRow);
    stack.appendChild(dimsWrap);
    stack.appendChild(el('div',{},[el('label',{html:'Delivery Instructions'}), instr]));
    stack.appendChild(submit);
    stack.appendChild(status);
    box.appendChild(stack); card.appendChild(box);

    const addDimRow=()=>{
      const tr=el('tr');
      tr.innerHTML=`<td><input type="number" min="1" step="1" placeholder="L" /></td>
                    <td><input type="number" min="1" step="1" placeholder="W" /></td>
                    <td><input type="number" min="1" step="1" placeholder="H" /></td>
                    <td><input type="number" min="0.1" step="0.1" placeholder="kg" /></td>
                    <td><button type="button" class="btn btn-danger btn-sm">×</button></td>`;
      tr.querySelector('button').onclick=()=>tr.remove();
      dimTable.tBodies[0].appendChild(tr);
    };
    const showDimsIfNeeded=()=>{
      const svc=svcSel.value; const needs=NZPOST_SERVICES.find(s=>s.code===svc)?.needsDims;
      dimsWrap.classList.toggle('cis-hidden', !needs);
      if (needs && dimTable.tBodies[0].rows.length===0) addDimRow();
    };
    svcSel.onchange=showDimsIfNeeded; qs('#cis-nzp-add-dim', card).onclick=addDimRow; showDimsIfNeeded();

    submit.onclick=async()=>{
      const btns=[submit]; disableGroup(btns,true);
      setStatus(status,'Communicating with NZ Post… please wait','warn');

      const svc=svcSel.value;
      const needs=NZPOST_SERVICES.find(s=>s.code===svc)?.needsDims;
      let packages=[];
      if (needs){
        qsa('tbody tr', dimTable).forEach(tr=>{
          const [l,w,h,kg]=qsa('input',tr).map(i=>i.value.trim());
          packages.push({length:l,width:w,height:h,weight:kg});
        });
        if (packages.length===0){ setStatus(status,'Please add at least one package row.','err'); disableGroup(btns,false); return; }
        if (packages.some(p=>!(p.length&&p.width&&p.height&&p.weight))){ setStatus(status,'All package dimensions are required.','err'); disableGroup(btns,false); return; }
      }

      try{
        const base={
          orderID:S.orderID, userID:S.userID, outletID:S.outletID,
          carrier: NZPOST_SERVICES.find(s=>s.code===svc)?.carrier || S.defaultCarrier,
          serviceCode: svc,
          createShipmentAndPrintLabel: qs('#cis-nzp-printnow').checked ? 1 : 0,
          deliveryInstructions: instr.value || '',
          saturdayDelivery: qs('#cis-nzp-saturday').checked ? 1 : 0
        };
        let params = new URLSearchParams(); Object.entries(base).forEach(([k,v])=>params.append(k,v));
        if (needs) params = toParams(packages,'packageArray',params);

        const res = await fetch('/assets/functions/ajax.php?method=createNZPostOrder',{method:'POST',headers:{'Accept':'application/json, text/plain, */*'},body:params,credentials:'include'});
        const text=await res.text(); let payload=null; try{ payload=JSON.parse(text); }catch{}

        if (payload && payload.error){
          const msgs=(payload.errorMessages||[]).join('\n')||'Unknown error'; setStatus(status,msgs,'err'); disableGroup(btns,false);
        } else if (payload && payload.success){
          setStatus(status,'Shipment created successfully. Printing…','ok');
          await afterSuccess({carrier:'nzpost',serviceCode:svc,saturday:qs('#cis-nzp-saturday').checked,printNow:qs('#cis-nzp-printnow').checked,packages,raw:text,payload});
        } else {
          if (text && text.toLowerCase().includes('error')){ setStatus(status,text,'err'); disableGroup(btns,false); }
          else {
            setStatus(status,'Shipment created. Printing…','ok');
            await afterSuccess({carrier:'nzpost',serviceCode:svc,saturday:qs('#cis-nzp-saturday').checked,printNow:qs('#cis-nzp-printnow').checked,packages,raw:text,payload:null});
          }
        }
      }catch(e){
        setStatus(status,'Network or server error: '+e.message,'err'); disableGroup(btns,false);
      }
    };

    return card;
  };

  // ---------------- GSS (NZ Couriers) ----------------
  const GSS_VOL_DIVISOR = 5000;
  const GSS_BOX_RULES = [
    { code:'e20', maxKg:3,  maxLongest:40,  maxGirth:120 },
    { code:'e40', maxKg:10, maxLongest:80,  maxGirth:200 },
    { code:'e60', maxKg:25, maxLongest:120, maxGirth:300 },
  ];
  const gssVolumetricKg=(L,W,H)=>{ const v=(+L||0)*(+W||0)*(+H||0); return v>0? v/GSS_VOL_DIVISOR : 0; };
  const gssSizeMetrics=(L,W,H)=>{ const d=[+L||0,+W||0,+H||0].sort((a,b)=>b-a); return {longest:d[0],girth:2*(d[1]+d[2])}; };
  const selectGSSPackageTypeForDims=(L,W,H,kg)=>{
    const chgKg=Math.max(+kg||0, gssVolumetricKg(L,W,H)); const {longest,girth}=gssSizeMetrics(L,W,H);
    for (const r of GSS_BOX_RULES){ if (chgKg<=r.maxKg && longest<=r.maxLongest && girth<=r.maxGirth) return r.code; }
    return 'e60';
  };
  const gssGetRows = (card)=>qsa('#cis-gss-dimtable tbody tr',card);
  const gssReadRowDims = (tr)=>{ const ins=qsa('input',tr); return {L:ins[0]?.value.trim(), W:ins[1]?.value.trim(), H:ins[2]?.value.trim(), K:ins[3]?.value.trim()}; };
  const gssSetRowComputedType=(tr,code)=>{ const c=tr.querySelector('[data-cell="computed"]'); if(c) c.innerHTML=`<span class="badge">${String(code).toUpperCase()}</span>`; };
  const gssGetRowOverride=(tr)=> tr.querySelector('select[data-role="override"]')?.value || 'auto';
  const gssSetRowStatus=(tr,text,type='')=>{ const c=tr.querySelector('[data-cell="status"]'); if(!c) return; c.textContent=text||''; c.classList.remove('ok','warn','err'); if(type) c.classList.add(type); };

  const buildGSSCard=()=>{
    const card=el('div',{class:'col cis-hidden',id:'cis-card-gss'});
    const box =el('div',{class:'card'});
    box.appendChild(el('div',{html:'<span class="badge">NZ Couriers via GoSweetSpot</span>'}));
    const stack=el('div',{class:'stack'});

    const optRow=el('div',{class:'row-compact'});
    const sig=el('input',{type:'checkbox',id:'cis-gss-sig'}); if (S.defaultSignature) sig.checked=true;
    const sat=el('input',{type:'checkbox',id:'cis-gss-sat'}); if (S.defaultSaturday) sat.checked=true;
    const shp=el('input',{type:'checkbox',id:'cis-gss-ship'}); if (S.defaultCreateShipment) shp.checked=true;
    optRow.appendChild(el('div',{},[
      el('label',{html:'Options'}),
      el('div',{class:'row-compact'},[
        el('label',{},[sig,document.createTextNode(' Signature Required')]),
        el('label',{},[sat,document.createTextNode(' Saturday Delivery')]),
        el('label',{},[shp,document.createTextNode(' Create Shipment & Send Tracking')]),
      ])
    ]));

    const dimsWrap=el('div',{}); dimsWrap.appendChild(el('label',{html:'Packages (cm/kg)'}));
    const dimTable=el('table',{class:'dim-table',id:'cis-gss-dimtable'});
    dimTable.innerHTML=`
      <thead><tr>
        <th>Length</th><th>Width</th><th>Height</th><th>Weight</th>
        <th>Computed</th><th>Override</th><th>Status</th><th></th>
      </tr></thead><tbody></tbody>`;
    dimsWrap.appendChild(dimTable);

    const btnRow=el('div',{style:'display:flex;gap:8px;flex-wrap:wrap;'},[
      el('button',{type:'button',class:'btn btn-secondary',id:'cis-gss-add',html:'Add Package'}),
      el('button',{type:'button',class:'btn btn-secondary',id:'cis-gss-autotype',html:'Auto-assign Types'}),
      el('button',{type:'button',class:'btn btn-primary',id:'cis-gss-create',html:'Create GSS Labels'})
    ]);

    const instr=el('input',{type:'text',id:'cis-gss-notes',placeholder:'Delivery instructions (optional)'});
    const status=el('div',{class:'status',id:'cis-gss-status'});

    stack.appendChild(optRow);
    stack.appendChild(dimsWrap);
    stack.appendChild(btnRow);
    stack.appendChild(el('div',{},[el('label',{html:'Delivery Instructions'}), instr]));
    stack.appendChild(status);
    box.appendChild(stack); card.appendChild(box);

    const addRow=(preset={L:'',W:'',H:'',K:''})=>{
      const tr=el('tr');
      tr.innerHTML=`<td><input type="number" min="1" step="1"  placeholder="L" value="${preset.L}"></td>
                    <td><input type="number" min="1" step="1"  placeholder="W" value="${preset.W}"></td>
                    <td><input type="number" min="1" step="1"  placeholder="H" value="${preset.H}"></td>
                    <td><input type="number" min="0.1" step="0.1" placeholder="kg" value="${preset.K}"></td>
                    <td data-cell="computed"><span class="badge">—</span></td>
                    <td><select data-role="override">
                          <option value="auto" selected>Auto</option>
                          <option value="e20">E20</option>
                          <option value="e40">E40</option>
                          <option value="e60">E60</option>
                        </select></td>
                    <td data-cell="status"></td>
                    <td><button type="button" class="btn btn-danger btn-sm">×</button></td>`;
      tr.querySelector('button').onclick=()=>tr.remove();
      qsa('input',tr).forEach(i=>i.addEventListener('input',()=>recomputeRow(tr)));
      dimTable.tBodies[0].appendChild(tr); recomputeRow(tr);
    };
    const recomputeRow=(tr)=>{
      const {L,W,H,K}=gssReadRowDims(tr);
      if (L&&W&&H&&K) gssSetRowComputedType(tr, selectGSSPackageTypeForDims(L,W,H,K));
      else gssSetRowComputedType(tr,'—');
      gssSetRowStatus(tr,'','');
    };
    const recomputeAll = ()=> gssGetRows(card).forEach(recomputeRow);

    addRow(); // seed one
    qs('#cis-gss-add',card).onclick = ()=>addRow();
    qs('#cis-gss-autotype',card).onclick = recomputeAll;

    qs('#cis-gss-create',card).onclick = async()=>{
      const rows=gssGetRows(card);
      if (!rows.length){ setStatus(status,'Add at least one package row.','err'); return; }
      for (const tr of rows){ const {L,W,H,K}=gssReadRowDims(tr); if (!(L&&W&&H&&K)){ setStatus(status,'All package rows require L/W/H/Weight.','err'); return; } }

      setStatus(status,'Creating labels…','warn');
      const ctrl=[qs('#cis-gss-create',card), qs('#cis-gss-add',card), qs('#cis-gss-autotype',card)];
      disableGroup(ctrl,true);

      const badIdx=[];
      try{
        for (let i=0;i<rows.length;i++){
          const tr=rows[i];
          const {L,W,H,K}=gssReadRowDims(tr);
          const override=gssGetRowOverride(tr);
          const computed=selectGSSPackageTypeForDims(L,W,H,K);
          const pkgType=(override==='auto'?computed:override);

          gssSetRowStatus(tr,`Submitting (${pkgType.toUpperCase()})…`,'warn');
          const res = await createGSSTicketOne(pkgType, null);
          if (res==='Done' || res==='Address Automatically Adjusted'){
            gssSetRowStatus(tr,'Label printed','ok');
          } else if (res==='Bad Address'){
            gssSetRowStatus(tr,'Bad address — adjust required','err'); badIdx.push(i);
          } else {
            gssSetRowStatus(tr, res || 'Unknown error','err');
          }
        }
        if (badIdx.length){
          setStatus(status,`Some packages need address adjustment (${badIdx.length}).`,'err');
          S._gssBadIdx = badIdx; toggleAddrModal(true);
        } else {
          setStatus(status,'All labels created.','ok');
          if (S.autoRedirect) await afterSuccess({carrier:'gss',batch:true});
        }
      }catch(e){
        setStatus(status,'Batch failed: '+e.message,'err');
      }finally{
        disableGroup(ctrl,false);
      }
    };

    async function createGSSTicketOne(packageType, manualAddr /* or null */){
      if (!S.gssToken) return 'GSS token missing for this outlet.';
      const sig = qs('#cis-gss-sig').checked ? 1 : 0;
      const sat = qs('#cis-gss-sat').checked ? 1 : 0;
      const shp = qs('#cis-gss-ship').checked ? 1 : 0;
      const notes = qs('#cis-gss-notes').value || '';

      const url=new URL('/assets/functions/ajax.php', window.location.origin);
      url.searchParams.set('method','createShipmentVapeShed');
      url.searchParams.set('orderID', S.orderID);
      url.searchParams.set('signature', sig);
      url.searchParams.set('saturday', sat);
      url.searchParams.set('createShipment', shp);
      url.searchParams.set('packageType', packageType);
      url.searchParams.set('outletID', S.outletID);
      url.searchParams.set('gssToken', S.gssToken);
      url.searchParams.set('userID', S.userID);
      if (notes) url.searchParams.set('instructions', notes); // no double-encoding

      if (manualAddr){
        url.searchParams.set('company',  manualAddr.company||'');
        url.searchParams.set('street1',  manualAddr.street1||'');
        url.searchParams.set('street2',  manualAddr.street2||'');
        url.searchParams.set('suburb',   manualAddr.suburb||'');
        url.searchParams.set('postcode', manualAddr.postcode||'');
        url.searchParams.set('city',     manualAddr.city||'');
        url.searchParams.set('manual',   'true');
      }
      const res=await fetch(url.toString(),{method:'GET',credentials:'include'});
      return await res.text();
    }

    return card;
  };

  // ---------------- shared behaviours ----------------
  const afterSuccess = async (ctx)=>{
    if (typeof S.onSuccess==='function'){ try{ S.onSuccess(ctx); }catch{} }
    if (S.autoRedirect===false){
      const buttons=qsa('.cis-ship .btn', S.mount); disableGroup(buttons,true);
      if (ctx?.carrier==='nzpost') setStatus(qs('#cis-nzp-status', S.mount), 'Shipment created. Label sent to printer.','ok');
      else setStatus(qs('#cis-gss-status', S.mount), 'Shipment created. Label sent to printer.','ok');
      setTimeout(()=>disableGroup(buttons,false), 3000);
      return;
    }
    const go=S.onSuccessRedirect; await new Promise(r=>setTimeout(r,1200));
    if (typeof go==='function'){ const url=go(S); if (url) return window.location.replace(url); }
    if (typeof go==='string' && go) return window.location.replace(go);
    const back=document.referrer;
    if (back) return window.location.replace(back + (back.includes('?')?'&':'?') + 'status=6');
    window.location.replace('/orders-overview-outlet.php?outletID='+encodeURIComponent(S.outletID)+'&status=6');
  };

  // address modal
  const toggleAddrModal=(open)=>{ const m=qs('#cis-addr-modal'); if(!m) return; m.classList.toggle('cis-hidden', !open); qs('#cis-addr-status').textContent=''; };
  const wireAddrModal=()=>{ qs('#cis-addr-close')?.addEventListener('click',()=>toggleAddrModal(false)); qs('#cis-addr-try')?.addEventListener('click',tryManualAddress); };

  const tryManualAddress = async ()=>{
    const st=qs('#cis-addr-status'); setStatus(st,'Submitting manual address to GSS…','warn');
    const company = qs('#cis-addr-company').value || '';
    const street1 = qs('#cis-addr-st1').value || '';
    const street2 = qs('#cis-addr-st2').value || '';
    const suburb  = qs('#cis-addr-suburb').value || '';
    const postcode= qs('#cis-addr-postcode').value || '';
    const city    = qs('#cis-addr-city').value || '';
    const ticket  = qs('#cis-addr-ticket').value || 'e20';

    if (!street1 || !city || !postcode){ setStatus(st,'Street, City, and Postcode are required.','err'); return; }
    if (!S.gssToken){ setStatus(st,'GSS token missing for this outlet.','err'); return; }

    const card=qs('#cis-card-gss', S.mount); const rows=gssGetRows(card);
    const targets = Array.isArray(S._gssBadIdx)&&S._gssBadIdx.length ? S._gssBadIdx : rows.map((_,i)=>i);

    try{
      for (const i of targets){
        const tr=rows[i]; const {L,W,H,K}=gssReadRowDims(tr);
        const override=gssGetRowOverride(tr);
        // If dims are incomplete, fall back to modal ticket choice
        const computed = (L&&W&&H&&K) ? selectGSSPackageTypeForDims(L,W,H,K) : ticket;
        const pkgType  = (override==='auto' ? computed : override);

        gssSetRowStatus(tr,`Retry (manual) ${pkgType.toUpperCase()}…`,'warn');
        const text = await (async()=>createGSSTicketOne(pkgType, {company,street1,street2,suburb,postcode,city}))();

        if (text==='Done' || text==='Address Automatically Adjusted') gssSetRowStatus(tr,'Label printed','ok');
        else if (text==='Bad Address') gssSetRowStatus(tr,'Still rejected — tweak fields and try again','err');
        else gssSetRowStatus(tr, text || 'Unknown error','err');
      }
      setStatus(st,'Manual run complete.','ok'); S._gssBadIdx=[];
      setTimeout(()=>toggleAddrModal(false), 800);
    }catch(e){
      setStatus(st,'Manual batch failed: '+e.message,'err');
    }
  };

  const reprintGSS=(consignmentID)=>{
    if (!consignmentID) return;
    if (!confirm('Re-print this label?')) return;
    const uri=window.location.toString(); if (uri.indexOf('#')>0) window.history.replaceState({}, document.title, uri.substring(0, uri.indexOf('#')));
    window.location.href = window.location.href + (window.location.href.includes('?')?'&':'?') + 'rePrintLabel=' + encodeURIComponent(consignmentID);
  };

  // --------------- public API ---------------
  return {
    init(opts){
      S={...S,...opts, gss:{...S.gss,...(opts?.gss||{})}, nzp:{...S.nzp,...(opts?.nzp||{})}};
      S.mount = qs('#cis-ship-plugin'); if (!S.mount) throw new Error('Mount #cis-ship-plugin not found.');
      wireAddrModal(); render();
    },
    reprintGSS
  };
})();
</script>

<script>
CISShipPlugin.init({
    // required
    orderID:  <?php echo (int)$_GET['orderID']; ?>,
    outletID: <?php echo (int)$_GET['outletID']; ?>,
    userID:   <?php echo (int)$_SESSION['userID']; ?>,

    // feature flags (show/hide tabs based on store capabilities)
    supportGSS:    <?php echo $outletObject->gss_token ? 'true':'false'; ?>,
    supportNZPost: <?php echo ($outletObject->nz_post_api_key && $outletObject->nz_post_subscription_key) ? 'true':'false'; ?>,

    // tokens/keys (only used to decide if features should render and to pass to the endpoints)
    gssToken: '<?php echo $outletObject->gss_token ?? '' ?>',
    nzPostApiKey: '<?php echo $outletObject->nz_post_api_key ?? '' ?>',
    nzPostSubKey: '<?php echo $outletObject->nz_post_subscription_key ?? '' ?>',

    // sensible defaults (can be computed from order context if you like)
    defaultSignature: true,
    defaultSaturday: (function(){
      // Like your old heuristic: auto-tick Saturday near week's end
      const d = new Date(); const day = d.getDay(); // 0 Sun..6 Sat
      const label = <?php echo json_encode($o->shipping_method_label ?? ''); ?>;
      return /Saturday/i.test(label) || day===4 || day===5; // Thu/Fri
    })(),
    defaultCreateShipment: true,

    // after success redirect; return a string URL, or set a fixed string.
    onSuccessRedirect: function(s){
    // example: simple “last action” chip
    const host=document.getElementById('cis-ship-plugin');
    let chip=document.getElementById('cis-ship-last');
    if(!chip){ chip=document.createElement('div'); chip.id='cis-ship-last'; chip.style.marginTop='8px'; chip.style.fontSize='13px'; chip.style.color='#0a7a20'; host.appendChild(chip); }
    const ts=new Date().toLocaleTimeString();
    if (ctx?.carrier==='nzpost'){
      chip.textContent=`✅ NZ Post label created at ${ts} (${ctx.serviceCode}${ctx.saturday?', Sat':''}${ctx.printNow?', printed':''}).`;
    } else {
      chip.textContent=`✅ GSS label${ctx?.batch?'s':''} created at ${ts}.`;
    }
  },

  // legacy redirect (unused when autoRedirect:false)
  onSuccessRedirect: function(s){
    if (document.referrer) return document.referrer + (document.referrer.includes('?')?'&':'?') + 'status=6';
    return '/orders-overview-outlet.php?outletID='+encodeURIComponent(s.outletID)+'&status=6';
  },

  // seed
  nzp:{ serviceCode:'CPOLTPDL' },
  gss:{ instructions:'' }
});
</script>
