(function(){
  var csrf = (document.querySelector('meta[name="csrf-token"]')||{}).content || '';

  var trendChart = null, classChart = null;
  var tblKelasRekap = null, tblBolos = null, tblKelasDetail = null;

  function toPct(v){ return (Math.round(v*10)/10).toFixed(1); }

  function post(action, payload){
    var fd = new FormData();
    fd.append('api','1');
    fd.append('csrf', csrf);
    fd.append('action', action);
    if (payload) {
      for (var k in payload) if (Object.prototype.hasOwnProperty.call(payload,k)) fd.append(k, payload[k]);
    }
    return fetch('', { method:'POST', body: fd })
      .then(function(res){ return res.json(); })
      .then(function(j){ if(!j.ok) throw new Error(j.msg||'Request gagal'); return j; });
  }

  function getFilters(){
    var period = document.getElementById('period').value;
    var prayer = document.getElementById('prayer').value;
    var start  = document.getElementById('start').value;
    var end    = document.getElementById('end').value;
    return { period: period, prayer: prayer, start: start, end: end };
  }

  function setPeriodLabel(meta){
    var el = document.getElementById('lblPeriod');
    el.textContent = 'Periode: ' + meta.from + ' s.d. ' + meta.to + ' • Sholat: ' + meta.prayer;
  }

  function renderTrend(labels, dzuhur, ashar){
    var ctx = document.getElementById('chartTrend');
    if (trendChart) trendChart.destroy();
    trendChart = new Chart(ctx, {
      type:'line',
      data:{ labels: labels, datasets:[
        {label:'Dzuhur', data: dzuhur, tension:0.3},
        {label:'Ashar',  data: ashar,  tension:0.3}
      ]},
      options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}}
    });
  }

  function renderClassTrend(labels, present){
    var ctx = document.getElementById('chartClass');
    if (classChart) classChart.destroy();
    classChart = new Chart(ctx, {
      type:'bar',
      data:{ labels: labels, datasets:[{label:'Approved', data: present}] },
      options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}}
    });
  }

  function loadOverview(){
    var f = getFilters();
    return post('overview', f).then(function(j){
      document.getElementById('kpi_students').textContent = j.kpi.total_students;
      document.getElementById('kpi_present').textContent  = j.kpi.total_present;
      document.getElementById('kpi_absent').textContent   = j.kpi.total_absent;
      document.getElementById('kpi_rate').textContent     = j.kpi.rate + '%';
      setPeriodLabel(j.meta);

      renderTrend(j.trend.labels, j.trend.dzuhur, j.trend.ashar);

      var tbody = document.querySelector('#tblKelasRekap tbody');
      tbody.innerHTML = '';
      for (var i=0;i<j.per_class.length;i++){
        var pc = j.per_class[i];
        var tr = document.createElement('tr');
        tr.setAttribute('data-class-id', pc.class_id);
        tr.setAttribute('data-class-name', pc.class_name);
        tr.innerHTML =
          '<td>'+escapeHtml(pc.class_name)+'</td>'+
          '<td>'+pc.students+'</td>'+
          '<td>'+pc.present+'</td>'+
          '<td>'+pc.absent+'</td>'+
          '<td>'+toPct(pc.rate)+'</td>'+
          '<td><button class="btn btn-sm btn-outline-primary btn-pill btn-detail">Detail</button></td>';
        tbody.appendChild(tr);
      }
      if (tblKelasRekap) tblKelasRekap.destroy();
      tblKelasRekap = new DataTable('#tblKelasRekap', {responsive:true, pageLength:10});
    });
  }

  function loadBolos(){
    var f = getFilters();
    var thr = Math.max(0, Math.min(100, parseInt(document.getElementById('threshold').value||'75',10)));
    var exclude = document.getElementById('exclude_female').checked ? 1 : 0;
    var payload = {
      period: f.period, prayer: f.prayer, start: f.start, end: f.end,
      threshold: (thr/100).toString(), exclude_female: exclude.toString()
    };
    return post('frequent_absentees', payload).then(function(j){
      var tbody = document.querySelector('#tblBolos tbody');
      tbody.innerHTML = '';
      for (var i=0;i<j.list.length;i++){
        var r = j.list[i];
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td>'+escapeHtml(r.kelas||'-')+'</td>'+
          '<td>'+escapeHtml(r.nis||'')+'</td>'+
          '<td>'+escapeHtml(r.name||'')+'</td>'+
          '<td>'+escapeHtml(r.gender||'-')+'</td>'+
          '<td>'+r.hadir+'</td>'+
          '<td>'+r.bolos+'</td>'+
          '<td>'+toPct(r.rate)+'</td>'+
          '<td>'+escapeHtml(r.catatan||'')+'</td>';
        tbody.appendChild(tr);
      }
      if (tblBolos) tblBolos.destroy();
      tblBolos = new DataTable('#tblBolos', {responsive:true, searching:false, paging:true, pageLength:8, order:[[6,'asc']]});
    });
  }

  function openClassDetail(classId, className){
    var f = getFilters();
    var payload = { period:f.period, prayer:f.prayer, start:f.start, end:f.end, class_id: classId };
    return post('class_detail', payload).then(function(j){
      document.getElementById('panelKelas').classList.remove('d-none');
      document.getElementById('lblKelas').textContent = className;

      document.getElementById('c_students').textContent = j.summary.students;
      document.getElementById('c_present').textContent  = j.summary.present;
      document.getElementById('c_absent').textContent   = Math.max(0, j.summary.expected - j.summary.present);
      document.getElementById('c_rate').textContent     = toPct(j.summary.rate)+'%';

      renderClassTrend(j.daily.labels, j.daily.present);

      var tbody = document.querySelector('#tblKelasDetail tbody');
      tbody.innerHTML = '';
      for (var i=0;i<j.table.length;i++){
        var r = j.table[i];
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td>'+escapeHtml(r.nis||'')+'</td>'+
          '<td>'+escapeHtml(r.name||'')+'</td>'+
          '<td>'+escapeHtml(r.gender||'-')+'</td>'+
          '<td>'+r.hadir+'</td>'+
          '<td>'+r.bolos+'</td>'+
          '<td>'+toPct(r.rate)+'</td>'+
          '<td>'+escapeHtml(r.catatan||'')+'</td>';
        tbody.appendChild(tr);
      }
      if (tblKelasDetail) tblKelasDetail.destroy();
      tblKelasDetail = new DataTable('#tblKelasDetail', {responsive:true, pageLength:20, order:[[5,'asc']]});
      document.getElementById('panelKelas').scrollIntoView({behavior:'smooth'});
    });
  }

  function attachEvents(){
    document.getElementById('formFilter').addEventListener('submit', function(e){
      e.preventDefault();
      toggleCustomDates();
      loadOverview().then(loadBolos);
    });

    document.getElementById('btnRefreshBolos').addEventListener('click', function(){
      loadBolos();
    });

    document.querySelector('#tblKelasRekap tbody').addEventListener('click', function(e){
      var btn = e.target.closest('.btn-detail'); if (!btn) return;
      var tr = btn.closest('tr');
      var classId = tr.getAttribute('data-class-id');
      var className = tr.getAttribute('data-class-name');
      openClassDetail(classId, className);
    });

    document.getElementById('btnCloseDetail').addEventListener('click', function(){
      document.getElementById('panelKelas').classList.add('d-none');
    });

    document.getElementById('period').addEventListener('change', toggleCustomDates);
    toggleCustomDates();
  }

  function toggleCustomDates(){
    var p = document.getElementById('period').value;
    var disabled = (p!=='custom');
    document.getElementById('start').disabled = disabled;
    document.getElementById('end').disabled   = disabled;
  }

  function escapeHtml(s){
    s = (s==null?'':String(s));
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  document.addEventListener('DOMContentLoaded', function(){
    attachEvents();
    loadOverview().then(loadBolos);
  });
})();
