<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);
require APP_ROOT . '/autoload.php';

use Stream\Repository\{HitRepository, DateRange};
use Stream\Storage\Database;

$config = require APP_ROOT . '/config.php';

if (!$config['stats']['enabled']) { http_response_code(404); exit('Not found.'); }

$authUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$authPass = $_SERVER['PHP_AUTH_PW']   ?? '';
if (
    !hash_equals($config['stats']['username'], $authUser) ||
    !hash_equals(hash('sha256', $config['stats']['password']), hash('sha256', $authPass))
) {
    header('WWW-Authenticate: Basic realm="Stream Stats"');
    http_response_code(401);
    exit('Acceso denegado.');
}

$db   = Database::getInstance($config['db']['path']);
$repo = new HitRepository($db);

// ── API JSON ──────────────────────────────────────────────────
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    $range = new DateRange($_GET['from'] ?? '', $_GET['to'] ?? '');

    echo json_encode([
        'range'         => ['from' => $range->from, 'to' => $range->to],
        'summary'       => $repo->summary()->toArray(),
        'by_format'     => $repo->totalByFormat($range),
        'audio_vs_video'=> $repo->audioVsVideo($range),
        'top_domains'   => $repo->topDomains($range),
        'top_apps'      => $repo->topApps($range),
        'top_countries' => $repo->topCountries($range),
        'by_day'        => $repo->hitsByDay($range),
        'by_hour'       => $repo->hitsByHour($range),
        'geo_points'    => $repo->geoPoints($range),
        'recent'        => $repo->recent(100),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$summary = $repo->summary();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Radio UAS — Stream Stats</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root{
  --bg:#f6f8fa;--bg2:#ffffff;--bg3:#f0f2f5;--border:#d0d7de;
  --text:#1f2328;--muted:#656d76;--accent:#0969da;
  --green:#1a7f37;--orange:#9a6700;--red:#d1242f;--purple:#8250df;
  --mono:"SF Mono","Fira Code","Cascadia Code",monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px}

/* Header */
header{background:var(--bg2);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200}
header h1{font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px}
.dot{width:8px;height:8px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);animation:pulse 2s infinite;display:inline-block}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
#clock{color:var(--muted);font-size:12px;font-family:var(--mono)}

/* Filtros */
.filters{display:flex;align-items:center;gap:10px;padding:14px 24px;background:var(--bg2);border-bottom:1px solid var(--border);flex-wrap:wrap}
.filters label{font-size:12px;color:var(--muted);font-weight:500}
.filters input[type=date]{background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:5px 10px;border-radius:6px;font-size:13px;cursor:pointer}
.filters input[type=date]:focus{outline:none;border-color:var(--accent)}
.btn-group{display:flex;gap:6px;margin-left:4px}
.btn{padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:var(--bg3);color:var(--muted);font-size:12px;cursor:pointer;transition:all .15s}
.btn:hover,.btn.active{background:var(--accent);border-color:var(--accent);color:#fff}
.btn-apply{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:600}
.btn-apply:hover{background:#4090e0}

/* Layout */
main{padding:20px 24px;max-width:1440px;margin:0 auto}
section{margin-bottom:28px}
section>h2{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.9px;color:var(--muted);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border)}

/* KPIs */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.kpi{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px 18px;position:relative;overflow:hidden}
.kpi::before{content:'';position:absolute;inset:0;opacity:.06;pointer-events:none}
.kpi.c1::before{background:var(--accent)}
.kpi.c2::before{background:var(--green)}
.kpi.c3::before{background:var(--orange)}
.kpi.c4::before{background:var(--purple)}
.kpi .lbl{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:8px}
.kpi .val{font-size:30px;font-weight:800;font-family:var(--mono);line-height:1}
.kpi.c1 .val{color:var(--accent)}
.kpi.c2 .val{color:var(--green)}
.kpi.c3 .val{color:var(--orange)}
.kpi.c4 .val{color:var(--purple)}

/* Cards */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
@media(max-width:1100px){.grid3{grid-template-columns:1fr 1fr}}
@media(max-width:760px){.grid2,.grid3{grid-template-columns:1fr}}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:18px}
.card h3{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px}
.full{grid-column:1/-1}
canvas{max-height:210px!important}

/* Mapa */
#map{height:340px;border-radius:8px;overflow:hidden;border:1px solid var(--border)}

/* Tablas */
.tbl-wrap{overflow-x:auto;border-radius:8px;border:1px solid var(--border)}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:var(--bg3);padding:8px 12px;text-align:left;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);white-space:nowrap}
tbody td{padding:8px 12px;border-top:1px solid var(--border);vertical-align:middle}
tbody tr:hover td{background:rgba(88,166,255,.04)}
.badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;font-family:var(--mono)}
.badge.type-domain{background:rgba(210,153,34,.15);color:var(--orange)}
.badge.type-app{background:rgba(188,140,255,.15);color:var(--purple)}
.badge.mp3 {background:rgba(63,185,80,.15);color:var(--green)}
.badge.hls{background:rgba(88,166,255,.15);color:var(--accent)}
.mono{font-family:var(--mono);font-size:12px;color:var(--muted)}
.em{color:var(--muted);font-style:italic}
.bar-wrap{display:flex;align-items:center;gap:8px}
.bar{height:5px;border-radius:3px;background:var(--accent);opacity:.6}
.flag{font-size:16px;margin-right:4px}
.loading{text-align:center;padding:32px;color:var(--muted);font-size:13px}
</style>
</head>
<body>

<header>
  <h1><span class="dot"></span> Radio UAS — Stream Stats</h1>
  <span id="clock">—</span>
</header>

<!-- Filtros de fecha -->
<div class="filters">
  <label>Desde</label>
  <input type="date" id="f-from">
  <label>Hasta</label>
  <input type="date" id="f-to">
  <div class="btn-group">
    <button class="btn" data-days="1">Hoy</button>
    <button class="btn active" data-days="7">7 días</button>
    <button class="btn" data-days="30">30 días</button>
    <button class="btn" data-days="90">90 días</button>
    <button class="btn" data-days="0">Todo</button>
  </div>
  <button class="btn btn-apply" id="btn-apply">Aplicar →</button>
</div>

<main>

  <!-- KPIs -->
  <section>
    <h2>Resumen General</h2>
    <div class="kpi-grid">
      <div class="kpi c1"><div class="lbl">Total Hits</div><div class="val" id="k-total"><?= number_format($summary->totalHits) ?></div></div>
      <div class="kpi c2"><div class="lbl">Hits Hoy</div><div class="val" id="k-today"><?= number_format($summary->hitsToday) ?></div></div>
      <div class="kpi c3"><div class="lbl">IPs Únicas Hoy</div><div class="val" id="k-ips"><?= number_format($summary->uniqueIpsToday) ?></div></div>
      <div class="kpi c4"><div class="lbl">Orígenes Únicos</div><div class="val" id="k-domains"><?= number_format($summary->uniqueReferers) ?></div></div>
    </div>
  </section>

  <!-- Gráficas principales -->
  <section>
    <h2>Tendencias</h2>
    <div class="grid2">
      <div class="card">
        <h3>Hits por día</h3>
        <canvas id="chartDay"></canvas>
      </div>
      <div class="card">
        <h3>🎵 Audio vs 🎬 Video</h3>
        <canvas id="chartAVV"></canvas>
      </div>
      <div class="card">
        <h3>Distribución por hora</h3>
        <canvas id="chartHour"></canvas>
      </div>
      <div class="card">
        <h3>Proporción Audio / Video</h3>
        <canvas id="chartDonut"></canvas>
      </div>
    </div>
  </section>

  <!-- Mapa -->
  <section>
    <h2>Mapa de Oyentes</h2>
    <div class="card full">
      <h3>Ubicación por País y Ciudad</h3>
      <div id="map"></div>
    </div>
  </section>

  <!-- Tablas -->
  <section>
    <h2>Top Consumidores</h2>
    <div class="grid3">
      <div class="card"><h3>Top Dominios</h3><div id="tblDomains" class="loading">Cargando…</div></div>
      <div class="card"><h3>Top Apps</h3><div id="tblApps" class="loading">Cargando…</div></div>
      <div class="card"><h3>Top Países</h3><div id="tblCountries" class="loading">Cargando…</div></div>
    </div>
  </section>

  <!-- Recientes -->
  <section>
    <h2>Hits Recientes</h2>
    <div class="card full"><div id="tblRecent" class="loading">Cargando…</div></div>
  </section>

</main>

<script>
/* ════════════════════════════════════════════════════════════
   Constantes y helpers
════════════════════════════════════════════════════════════ */
const FORMAT_LABEL = { mp3: 'AUDIO', hls: 'VIDEO', m3u8: 'VIDEO' };

// Etiquetas para referers conocidos — dominios se muestran tal cual en Title Case
const REFERER_LABEL = { android: 'Android', ios: 'iOS' };
function refererLabel(val) {
  if (!val) return '—';
  if (REFERER_LABEL[val]) return REFERER_LABEL[val];
  // Dominio: capitalizar primera letra de cada segmento
  return val.split('.').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('.');
}
const COLORS       = { mp3: '#3fb950', hls: '#58a6ff' };
const GRID = '#e8ecf0', TICK = '#656d76';
const $ = id => document.getElementById(id);
const fmt = n => Number(n).toLocaleString('es-MX');
const today = () => new Date().toISOString().slice(0, 10);
const daysAgo = n => new Date(Date.now() - n * 864e5).toISOString().slice(0, 10);

/* ════════════════════════════════════════════════════════════
   Estado del filtro
════════════════════════════════════════════════════════════ */
let currentFrom = daysAgo(7);
let currentTo   = today();

// Inicializar inputs
$('f-from').value = currentFrom;
$('f-to').value   = currentTo;

/* ════════════════════════════════════════════════════════════
   Botones de rango rápido
════════════════════════════════════════════════════════════ */
document.querySelectorAll('.btn[data-days]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.btn[data-days]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const d = parseInt(btn.dataset.days);
    if (d === 0) {
      $('f-from').value = '2020-01-01';
      $('f-to').value   = today();
    } else {
      $('f-from').value = daysAgo(d === 1 ? 0 : d);
      $('f-to').value   = today();
    }
  });
});

$('btn-apply').addEventListener('click', () => {
  currentFrom = $('f-from').value || daysAgo(7);
  currentTo   = $('f-to').value   || today();
  refresh();
});

/* ════════════════════════════════════════════════════════════
   Fetch datos
════════════════════════════════════════════════════════════ */
async function refresh() {
  const url  = `?api=1&from=${currentFrom}&to=${currentTo}`;
  const data = await fetch(url).then(r => r.json());

  // KPIs (siempre globales)
  $('k-total').textContent   = fmt(data.summary.total_hits);
  $('k-today').textContent   = fmt(data.summary.hits_today);
  $('k-ips').textContent     = fmt(data.summary.unique_ips_today);
  $('k-domains').textContent = fmt(data.summary.unique_referers);

  renderDayChart(data.by_day);
  renderAVVChart(data.audio_vs_video);
  renderHourChart(data.by_hour);
  renderDonut(data.by_format);
  renderMap(data.geo_points);

  $('tblDomains').innerHTML   = buildBarTable(data.top_domains,   r => refererLabel(r.referer));
  $('tblApps').innerHTML      = buildBarTable(data.top_apps,      r => refererLabel(r.referer));
  $('tblCountries').innerHTML = buildBarTable(data.top_countries, r => {
    const flag = r.country_code ? countryFlag(r.country_code) : '';
    return `${flag}${r.country || '<span class="em">—</span>'}`;
  });

  renderRecent(data.recent);
  $('clock').textContent = 'Actualizado: ' + new Date().toLocaleTimeString('es-MX');
}

/* ════════════════════════════════════════════════════════════
   Gráficas
════════════════════════════════════════════════════════════ */
const charts = {};

function mkChart(id, config) {
  if (charts[id]) charts[id].destroy();
  charts[id] = new Chart($(id), config);
}

function chartDefaults(extra = {}) {
  return {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: { labels: { color: '#1f2328', font: { size: 11 } } } },
    scales: {
      x: { ticks: { color: TICK, maxTicksLimit: 10 }, grid: { color: GRID } },
      y: { ticks: { color: TICK }, grid: { color: GRID }, beginAtZero: true },
    },
    ...extra,
  };
}

/* Hits por día */
function renderDayChart(rows) {
  const days    = [...new Set(rows.map(r => r.day))].sort();
  const formats = [...new Set(rows.map(r => r.format))];
  const datasets = formats.map(f => ({
    label: FORMAT_LABEL[f] ?? f,
    data: days.map(d => (rows.find(r => r.day === d && r.format === f) ?? {total:0}).total),
    borderColor: COLORS[f] ?? '#7d8590',
    backgroundColor: (COLORS[f] ?? '#7d8590') + '20',
    tension: .35, fill: true, pointRadius: 2,
  }));
  mkChart('chartDay', { type:'line', data:{ labels:days, datasets }, options: chartDefaults() });
}

/* Audio vs Video lado a lado */
function renderAVVChart(rows) {
  const days  = rows.map(r => r.day);
  const audio = rows.map(r => r.audio ?? 0);
  const video = rows.map(r => r.video ?? 0);
  mkChart('chartAVV', {
    type: 'bar',
    data: {
      labels: days,
      datasets: [
        { label:'Audio', data:audio, backgroundColor:'#3fb95088', borderColor:'#3fb950', borderWidth:1, borderRadius:3 },
        { label:'Video', data:video, backgroundColor:'#58a6ff88', borderColor:'#58a6ff', borderWidth:1, borderRadius:3 },
      ],
    },
    options: chartDefaults({ plugins: { legend: { labels: { color:'#e6edf3', font:{size:11} } } } }),
  });
}

/* Distribución por hora */
function renderHourChart(rows) {
  const labels = Array.from({length:24}, (_,i) => String(i).padStart(2,'0')+'h');
  const data   = Array(24).fill(0);
  rows.forEach(r => { data[parseInt(r.hour,10)] = r.total; });
  mkChart('chartHour', {
    type: 'bar',
    data: { labels, datasets:[{ label:'Hits', data, backgroundColor:'#58a6ff30', borderColor:'#58a6ff', borderWidth:1, borderRadius:3 }] },
    options: chartDefaults({ plugins:{ legend:{ display:false } } }),
  });
}

/* Donut Audio vs Video */
function renderDonut(rows) {
  const labels = rows.map(r => FORMAT_LABEL[r.format] ?? r.format);
  const data   = rows.map(r => r.total);
  const colors = rows.map(r => COLORS[r.format] ?? '#7d8590');
  mkChart('chartDonut', {
    type: 'doughnut',
    data: { labels, datasets:[{ data, backgroundColor: colors.map(c => c+'cc'), borderColor: colors, borderWidth:2 }] },
    options: {
      responsive:true, maintainAspectRatio:true, cutout:'65%',
      plugins:{ legend:{ position:'bottom', labels:{ color:'#1f2328', font:{size:12}, padding:16 } } },
    },
  });
}

/* ════════════════════════════════════════════════════════════
   Mapa Leaflet
════════════════════════════════════════════════════════════ */
let leafletMap   = null;
let markersLayer = null;

function renderMap(points) {
  if (!leafletMap) {
    leafletMap = L.map('map', { zoomControl:true, attributionControl:true })
      .setView([20, 0], 2);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      attribution: '© OpenStreetMap © CARTO',
      subdomains: 'abcd', maxZoom: 19,
    }).addTo(leafletMap);

    markersLayer = L.layerGroup().addTo(leafletMap);
  }

  markersLayer.clearLayers();

  if (!points.length) return;

  const maxTotal = Math.max(...points.map(p => p.total));

  points.forEach(p => {
    if (!p.lat || !p.lon) return;

    const radius = Math.max(6, Math.min(30, (p.total / maxTotal) * 30));
    const circle = L.circleMarker([p.lat, p.lon], {
      radius,
      fillColor: '#0969da',
      color:     '#0969da',
      weight: 1,
      opacity: .8,
      fillOpacity: .45,
    });

    circle.bindPopup(`
      <div style="font-family:monospace;font-size:13px;min-width:140px;color:#1f2328">
        <strong>${p.city || '—'}, ${p.country || '—'}</strong><br>
        ${p.zip ? '<span style="color:var(--muted)">' + p.zip + '</span><br>' : ''}
        <span style="color:#58a6ff">${fmt(p.total)} hits</span>
      </div>
    `);

    markersLayer.addLayer(circle);
  });
}

/* ════════════════════════════════════════════════════════════
   Tablas
════════════════════════════════════════════════════════════ */
function buildBarTable(rows, renderCell) {
  if (!rows.length) return '<p style="color:var(--muted);padding:14px 0">Sin datos en este período.</p>';
  const max = rows[0].total;
  let h = `<div class="tbl-wrap"><table><thead><tr><th>Nombre</th><th>Hits</th><th>Ratio</th></tr></thead><tbody>`;
  rows.forEach(r => {
    const pct = Math.round((r.total / max) * 110);
    h += `<tr>
      <td>${renderCell(r)}</td>
      <td class="mono">${fmt(r.total)}</td>
      <td><div class="bar-wrap"><div class="bar" style="width:${pct}px"></div></div></td>
    </tr>`;
  });
  return h + '</tbody></table></div>';
}

function renderRecent(rows) {
  if (!rows.length) { $('tblRecent').innerHTML = '<p style="color:var(--muted);padding:14px 0">Sin datos.</p>'; return; }
  let h = `<div class="tbl-wrap"><table><thead><tr>
    <th>#</th><th>Tipo</th><th>Referer</th><th>Tipo</th><th>IP</th><th>País</th><th>Ciudad</th><th>Fecha UTC</th>
  </tr></thead><tbody>`;
  rows.forEach(r => {
    const flag = r.country_code ? countryFlag(r.country_code) : '';
    h += `<tr>
      <td class="mono">${r.id}</td>
      <td><span class="badge ${r.format}">${FORMAT_LABEL[r.format] ?? r.format}</span></td>
      <td>${refererLabel(r.referer)}</td>
      <td><span class="badge type-${r.referer_type}">${r.referer_type}</span></td>
      <td class="mono">${r.ip}</td>
      <td>${flag}${r.country || '<span class="em">—</span>'}</td>
      <td>${r.city   || '<span class="em">—</span>'}</td>
      <td class="mono" style="font-size:11px">${r.created_at}</td>
    </tr>`;
  });
  $('tblRecent').innerHTML = h + '</tbody></table></div>';
}

/* Convierte código de país a emoji de bandera */
function countryFlag(code) {
  if (!code || code.length !== 2) return '';
  const cp = [...code.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65);
  return String.fromCodePoint(...cp) + ' ';
}

/* ════════════════════════════════════════════════════════════
   Bootstrap
════════════════════════════════════════════════════════════ */
refresh();
setInterval(refresh, 30_000);
</script>
</body>
</html>