(function(){
  var errorsChart, perfChart;

  function $(id){ return document.getElementById(id); }
  function n(v, d='—'){ return (v==null || isNaN(v)) ? d : v; }

  async function load(){
    const since = $('sinceSelect').value;
    const res = await fetch('/assets/services/pipeline/monitor.php?since=' + encodeURIComponent(since));
    const j = await res.json();
    if(!j || !j.ok){ renderError(j && j.error ? j.error : 'monitor fetch failed'); return; }
    render(j, since);
  }

  function renderError(msg){
    $('kpiErrors').textContent = '—';
    $('kpiSlowEndpoint').textContent = '—';
    $('kpiSlowMs').textContent = msg || 'error';
    $('kpiSqlAvg').textContent = '—';
    $('kpiProfiles').textContent = '—';
    $('tblErrors').innerHTML = '<tr><td colspan="5" class="text-danger p-3">'+(msg||'Error')+'</td></tr>';
    $('tblPerf').innerHTML   = '<tr><td colspan="6" class="text-danger p-3">'+(msg||'Error')+'</td></tr>';
  }

  function render(data, since){
    const errs = data.errors || [];
    const perf = data.perf || [];

    $('lastUpdated').textContent = new Date().toLocaleTimeString();
    $('errSinceHint').textContent = 'window: ' + since.toLowerCase();
    $('perfSinceHint').textContent = 'window: ' + since.toLowerCase();

    // KPIs
    const totalErr = errs.reduce((a,e)=>a+(e.c||0),0);
    $('kpiErrors').textContent = String(totalErr);
    $('kpiErrorsHint').textContent = totalErr ? (errs[0]?.action||'—') : 'no errors';

    if (perf.length){
      const slow = perf[0];
      $('kpiSlowEndpoint').textContent = slow.endpoint || '—';
      $('kpiSlowMs').textContent = (slow.php_ms_avg ? Math.round(slow.php_ms_avg) : '—') + (slow.php_ms_avg ? ' ms' : '');
      const sqlAvg = (perf.reduce((a,p)=>a+(p.sql_n_avg||0),0) / perf.length) || 0;
      $('kpiSqlAvg').textContent = (sqlAvg ? sqlAvg.toFixed(1) : '—');
      $('kpiProfiles').textContent = n(perf.reduce((a,p)=>a+(p.n||0),0), '0');
    } else {
      ['kpiSlowEndpoint','kpiSlowMs','kpiSqlAvg','kpiProfiles'].forEach(id=>$(id).textContent='—');
    }

    // Errors table
    const te = $('tblErrors'); te.innerHTML = '';
    if (!errs.length){
      te.innerHTML = '<tr><td colspan="5" class="text-muted p-3">No recent errors.</td></tr>';
    } else {
      errs.forEach(e=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${e.action||'—'}</td><td>${e.status||'—'}</td><td>${e.c||0}</td>
                        <td><small>${e.first_at||'—'}</small></td><td><small>${e.last_at||'—'}</small></td>`;
        te.appendChild(tr);
      });
    }

    // Perf table
    const tp = $('tblPerf'); tp.innerHTML = '';
    if (!perf.length){
      tp.innerHTML = '<tr><td colspan="6" class="text-muted p-3">No recent profiling samples.</td></tr>';
    } else {
      perf.forEach(p=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="text-truncate" style="max-width:280px">${p.endpoint||'—'}</td>
                        <td>${p.n||0}</td>
                        <td>${p.php_ms_avg ? Math.round(p.php_ms_avg) : 0}</td>
                        <td>${p.sql_ms_avg ? Math.round(p.sql_ms_avg) : 0}</td>
                        <td>${p.sql_n_avg ? Number(p.sql_n_avg).toFixed(1) : '0.0'}</td>
                        <td><small>${p.last_at||'—'}</small></td>`;
        tp.appendChild(tr);
      });
    }

    // Charts
    renderCharts(errs, perf);
  }

  function renderCharts(errs, perf){
    // Errors bar
    var labelsE = errs.map(e=>(e.action||'') + ' ('+(e.c||0)+')');
    var dataE   = errs.map(e=>e.c||0);
    var ctxE = document.getElementById('chartErrors').getContext('2d');
    if (errorsChart) errorsChart.destroy();
    errorsChart = new Chart(ctxE, {
      type: 'bar',
      data: { labels: labelsE, datasets: [{ label:'Errors', data:dataE }] },
      options: { legend:{display:false}, scales:{ yAxes:[{ticks:{beginAtZero:true}}] } }
    });

    // Perf horizontal bar (top 10)
    var labels
