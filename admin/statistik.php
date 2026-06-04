<?php
// ============================================
//  CloudLibrary Mini — Admin: Statistik & Laporan
//  File   : admin/statistik.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginAdmin();

$ringkasan = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM buku WHERE status = 'tersedia')          AS total_buku,
        (SELECT COUNT(*) FROM users WHERE role = 'mahasiswa') AS total_user,
        (SELECT COUNT(*) FROM peminjaman)                           AS total_pinjam,
        (SELECT COUNT(*) FROM peminjaman WHERE status IN ('aktif','hampir_habis')) AS pinjam_aktif,
        (SELECT COUNT(*) FROM review WHERE status = 'tampil')    AS total_review,
        (SELECT ROUND(AVG(rating),1) FROM review WHERE status = 'tampil') AS avg_rating,
        (SELECT COUNT(*) FROM antrian WHERE status = 'menunggu')    AS total_antrian,
        (SELECT SUM(poin) FROM users WHERE role = 'mahasiswa')      AS total_poin
")->fetch();

$pinjam_harian = $pdo->query("
    SELECT DATE(created_at) AS tgl, COUNT(*) AS total
    FROM peminjaman WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY tgl ASC
")->fetchAll();

$pinjam_bulanan = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS bln, COUNT(*) AS total
    FROM peminjaman WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY bln ORDER BY bln ASC
")->fetchAll();

$genre_pop = $pdo->query("
    SELECT b.genre, COUNT(p.id) AS total
    FROM peminjaman p JOIN buku b ON p.buku_id = b.id
    GROUP BY b.genre ORDER BY total DESC LIMIT 8
")->fetchAll();

$top_buku = $pdo->query("
    SELECT b.judul, b.genre, COUNT(p.id) AS total,
           ROUND(AVG(r.rating),1) AS avg_rating
    FROM buku b
    LEFT JOIN peminjaman p ON p.buku_id = b.id
    LEFT JOIN review r ON r.buku_id = b.id AND r.status = 'tampil'
    GROUP BY b.id ORDER BY total DESC LIMIT 10
")->fetchAll();

$top_user = $pdo->query("
    SELECT u.nama, u.poin,
           COUNT(DISTINCT p.id) AS total_pinjam,
           COUNT(DISTINCT r.id) AS total_review
    FROM users u
    LEFT JOIN peminjaman p ON p.user_id = u.id
    LEFT JOIN review r ON r.user_id = u.id
    WHERE u.role = 'mahasiswa'
    GROUP BY u.id ORDER BY total_pinjam DESC LIMIT 10
")->fetchAll();

$status_dist = $pdo->query("SELECT status, COUNT(*) AS total FROM peminjaman GROUP BY status")->fetchAll();

$reg_bulanan = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS bln, COUNT(*) AS total
    FROM users WHERE role = 'mahasiswa'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY bln ORDER BY bln ASC
")->fetchAll();

$genre_warna = [
    'Novel'   =>['icon'=>'fa-book',       'color'=>'#60a5fa'],
    'Cerpen'  =>['icon'=>'fa-file-alt',   'color'=>'#a78bfa'],
    'Fantasi' =>['icon'=>'fa-hat-wizard', 'color'=>'#34d399'],
    'Romance' =>['icon'=>'fa-heart',      'color'=>'#f472b6'],
    'Horror'  =>['icon'=>'fa-ghost',      'color'=>'#f87171'],
    'Misteri' =>['icon'=>'fa-search',     'color'=>'#fbbf24'],
    'Sci-Fi'  =>['icon'=>'fa-rocket',     'color'=>'#38bdf8'],
    'Filsafat'=>['icon'=>'fa-landmark',   'color'=>'#94a3b8'],
    'Sains'   =>['icon'=>'fa-flask',      'color'=>'#4ade80'],
    'Biografi'=>['icon'=>'fa-user',       'color'=>'#fb923c'],
];

$harian_labels  = json_encode(array_column($pinjam_harian,  'tgl'));
$harian_data    = json_encode(array_column($pinjam_harian,  'total'));
$bulanan_labels = json_encode(array_column($pinjam_bulanan, 'bln'));
$bulanan_data   = json_encode(array_column($pinjam_bulanan, 'total'));
$genre_labels   = json_encode(array_column($genre_pop, 'genre'));
$genre_data     = json_encode(array_column($genre_pop, 'total'));
$genre_colors   = json_encode(array_map(fn($g) => $genre_warna[$g['genre']]['color'] ?? '#60a5fa', $genre_pop));
$reg_labels     = json_encode(array_column($reg_bulanan, 'bln'));
$reg_data       = json_encode(array_column($reg_bulanan, 'total'));
$status_map     = array_column($status_dist, 'total', 'status');
$status_labels  = json_encode(['Aktif','Hampir Habis','Expired','Selesai']);
$status_data    = json_encode([$status_map['aktif']??0,$status_map['hampir_habis']??0,$status_map['expired']??0,$status_map['dikembalikan']??0]);

$title = "Statistik & Laporan — Admin CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
body{
  background-color:#1e3a5f !important;
  background-image:url('library_bg.png') !important;
  background-size:cover !important;
  background-position:center center !important;
  background-attachment:fixed !important;
  background-repeat:no-repeat !important;
  min-height:100vh !important;
}
body::before{content:'';position:fixed;inset:0;z-index:0;background:rgba(5,15,35,0.62);pointer-events:none;}
.main-wrap,.container,main{background:transparent !important;}

:root{
  --card:rgba(255,255,255,0.10);
  --card-b:rgba(255,255,255,0.18);
  --text:#fff;
  --muted:rgba(255,255,255,0.55);
  --accent:#60a5fa;
  --accent2:#fbbf24;
  --success:#4ade80;
  --warning:#fbbf24;
  --danger:#f87171;
  --sh:0 4px 22px rgba(0,0,0,0.22);
  --sh-md:0 8px 32px rgba(0,0,0,0.32);
}

.page-header{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:#f9c74f;}
.page-header span{font-size:12px;color:rgba(255,255,255,0.45);background:rgba(255,255,255,0.08);padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);}

/* Summary grid */
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px;position:relative;z-index:1;}
@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.summary-grid{grid-template-columns:1fr 1fr;}}
.summary-card{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:14px;padding:18px 16px;display:flex;align-items:center;gap:14px;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);}
.summary-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.summary-num{font-family:'Syne',sans-serif;font-size:24px;font-weight:900;line-height:1;color:#fff;}
.summary-lbl{font-size:11px;color:rgba(255,255,255,0.50);margin-top:3px;font-weight:600;}

/* Section title */
.section-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;margin-bottom:14px;margin-top:32px;display:flex;align-items:center;gap:10px;color:#fff;position:relative;z-index:1;}
.section-title i{color:#f9c74f;}
.section-title::after{content:'';flex:1;height:1px;background:rgba(255,255,255,0.12);margin-left:8px;}

/* Chart cards */
.chart-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;position:relative;z-index:1;}
@media(max-width:760px){.chart-grid-2{grid-template-columns:1fr;}}
.chart-card{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:14px;padding:20px;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);margin-bottom:16px;position:relative;z-index:1;}
.chart-card h4{font-size:11px;font-weight:900;color:rgba(255,255,255,0.50);text-transform:uppercase;letter-spacing:.8px;margin-bottom:16px;}

/* Table */
.stat-table{width:100%;border-collapse:collapse;font-size:13px;}
.stat-table th{font-size:10px;font-weight:900;color:rgba(255,255,255,0.50);text-transform:uppercase;letter-spacing:.6px;padding:10px 14px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.08);}
.stat-table td{padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);vertical-align:middle;color:#fff;}
.stat-table tr:last-child td{border-bottom:none;}
.stat-table tr:hover td{background:rgba(255,255,255,0.05);}
.table-wrap{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:14px;overflow:hidden;overflow-x:auto;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);margin-bottom:28px;position:relative;z-index:1;}

/* Genre bar */
.genre-bar-wrap{background:rgba(255,255,255,0.08);border-radius:5px;height:8px;flex:1;overflow:hidden;}
.genre-bar-fill{height:100%;border-radius:5px;transition:width .5s;}

/* Avatar */
.avatar-mini{width:28px;height:28px;border-radius:50%;flex-shrink:0;background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:11px;font-weight:900;color:#fff;}

/* Export buttons */
.export-row{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;position:relative;z-index:1;}
.btn-export{font-size:12px;font-weight:700;padding:8px 16px;border-radius:8px;border:1px solid rgba(255,255,255,0.18);background:rgba(255,255,255,0.10);color:rgba(255,255,255,0.70);cursor:pointer;transition:all .2s;font-family:'Nunito',sans-serif;display:inline-flex;align-items:center;gap:7px;backdrop-filter:blur(8px);}
.btn-export:hover{border-color:#f9c74f;color:#f9c74f;background:rgba(249,199,79,0.10);}

/* Medal colors */
.medal-1{color:#f9c74f;} .medal-2{color:#94a3b8;} .medal-3{color:#cd7f32;}
</style>

<!-- PAGE HEADER -->
<div class="page-header">
  <h2><i class="fas fa-chart-bar"></i> Statistik & Laporan</h2>
  <span>Data real-time sistem</span>
</div>

<!-- EXPORT BUTTONS -->
<div class="export-row">
  <button class="btn-export" onclick="window.print()"><i class="fas fa-print"></i> Cetak Laporan</button>
  <button class="btn-export" onclick="exportCSV()"><i class="fas fa-file-download"></i> Export CSV</button>
</div>

<!-- SUMMARY CARDS -->
<div class="summary-grid">
  <?php
  $sc = [
    ['fa-book-open',    'rgba(249,199,79,0.15)', '#f9c74f', $ringkasan['total_buku'],    'Total Buku Aktif'],
    ['fa-users',        'rgba(74,222,128,0.15)', '#4ade80', $ringkasan['total_user'],    'Mahasiswa'],
    ['fa-clock',        'rgba(251,191,36,0.15)', '#fbbf24', $ringkasan['pinjam_aktif'],  'Sedang Dipinjam'],
    ['fa-star',         'rgba(249,199,79,0.12)', '#f9c74f', $ringkasan['avg_rating'],    'Rata-rata Rating'],
    ['fa-exchange-alt', 'rgba(96,165,250,0.15)', '#60a5fa', $ringkasan['total_pinjam'],  'Total Transaksi'],
    ['fa-comment-alt',  'rgba(167,139,250,0.15)','#a78bfa', $ringkasan['total_review'],  'Total Review'],
    ['fa-list-ol',      'rgba(248,113,113,0.15)','#f87171', $ringkasan['total_antrian'], 'Antrian Aktif'],
    ['fa-trophy',       'rgba(249,199,79,0.12)', '#f9c74f', number_format($ringkasan['total_poin']),'Total Poin'],
  ];
  foreach ($sc as [$icon, $bg, $color, $num, $lbl]):
  ?>
  <div class="summary-card">
    <div class="summary-icon" style="background:<?= $bg ?>;">
      <i class="fas <?= $icon ?>" style="color:<?= $color ?>;font-size:18px;"></i>
    </div>
    <div>
      <div class="summary-num" style="color:<?= $color ?>;"><?= $num ?></div>
      <div class="summary-lbl"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- TREN PEMINJAMAN -->
<div class="section-title"><i class="fas fa-chart-line"></i> Tren Peminjaman</div>
<div class="chart-card">
  <h4>Peminjaman Harian — 30 Hari Terakhir</h4>
  <canvas id="chartHarian" height="80"></canvas>
</div>

<div class="chart-grid-2">
  <div class="chart-card">
    <h4>Peminjaman Bulanan — 12 Bulan</h4>
    <canvas id="chartBulanan" height="160"></canvas>
  </div>
  <div class="chart-card">
    <h4>Registrasi Pengguna Baru — 12 Bulan</h4>
    <canvas id="chartReg" height="160"></canvas>
  </div>
</div>

<div class="chart-grid-2">
  <div class="chart-card">
    <h4>Genre Terpopuler</h4>
    <canvas id="chartGenre" height="160"></canvas>
  </div>
  <div class="chart-card">
    <h4>Distribusi Status Peminjaman</h4>
    <canvas id="chartStatus" height="160"></canvas>
  </div>
</div>

<!-- GENRE BREAKDOWN -->
<div class="section-title"><i class="fas fa-layer-group"></i> Popularitas Genre</div>
<div class="chart-card">
  <?php
  $max_genre = max(array_column($genre_pop, 'total') ?: [1]);
  foreach ($genre_pop as $g):
    $gw  = $genre_warna[$g['genre']] ?? ['icon'=>'fa-book','color'=>'#60a5fa'];
    $pct = round($g['total'] / $max_genre * 100);
  ?>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
    <div style="width:26px;text-align:center;">
      <i class="fas <?= $gw['icon'] ?>" style="color:<?= $gw['color'] ?>;font-size:13px;"></i>
    </div>
    <div style="width:70px;font-size:12px;font-weight:700;color:#fff;"><?= e($g['genre']) ?></div>
    <div class="genre-bar-wrap">
      <div class="genre-bar-fill" style="width:<?= $pct ?>%;background:<?= $gw['color'] ?>;"></div>
    </div>
    <div style="width:36px;text-align:right;font-size:12px;font-weight:800;color:<?= $gw['color'] ?>;"><?= $g['total'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- TOP 10 BUKU -->
<div class="section-title"><i class="fas fa-trophy"></i> Top 10 Buku Terpopuler</div>
<div class="table-wrap">
  <table class="stat-table" id="tableBuku">
    <thead>
      <tr>
        <th width="40">#</th>
        <th>Judul Buku</th>
        <th>Genre</th>
        <th style="text-align:center;">Dipinjam</th>
        <th style="text-align:center;">Rating</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($top_buku as $i => $b):
        $gw = $genre_warna[$b['genre']] ?? ['icon'=>'fa-book','color'=>'#60a5fa'];
      ?>
      <tr>
        <td style="text-align:center;">
          <?php if($i===0): ?><i class="fas fa-medal medal-1" style="font-size:16px;"></i>
          <?php elseif($i===1): ?><i class="fas fa-medal medal-2" style="font-size:16px;"></i>
          <?php elseif($i===2): ?><i class="fas fa-medal medal-3" style="font-size:16px;"></i>
          <?php else: ?><span style="color:rgba(255,255,255,0.35);font-size:12px;font-weight:700;"><?= $i+1 ?></span>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <i class="fas <?= $gw['icon'] ?>" style="font-size:14px;color:<?= $gw['color'] ?>;"></i>
            <span style="font-weight:700;font-size:13px;"><?= e($b['judul']) ?></span>
          </div>
        </td>
        <td><span style="font-size:11px;font-weight:700;color:<?= $gw['color'] ?>;"><?= e($b['genre']) ?></span></td>
        <td style="text-align:center;">
          <span style="font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:#60a5fa;"><?= $b['total'] ?></span>
        </td>
        <td style="text-align:center;">
          <?php if($b['avg_rating']): ?>
            <span style="color:#fbbf24;font-weight:700;font-size:12px;"><i class="fas fa-star" style="font-size:10px;"></i> <?= $b['avg_rating'] ?></span>
          <?php else: ?>
            <span style="color:rgba(255,255,255,0.20);font-size:11px;"><i class="fas fa-minus"></i></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- TOP 10 USER -->
<div class="section-title"><i class="fas fa-crown"></i> Top 10 Pengguna Aktif</div>
<div class="table-wrap">
  <table class="stat-table" id="tableUser">
    <thead>
      <tr>
        <th width="40">#</th>
        <th>Mahasiswa</th>
        <th style="text-align:center;">Pinjaman</th>
        <th style="text-align:center;">Review</th>
        <th style="text-align:center;">Poin</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($top_user as $i => $u): ?>
      <tr>
        <td style="text-align:center;">
          <?php if($i===0): ?><i class="fas fa-medal medal-1" style="font-size:16px;"></i>
          <?php elseif($i===1): ?><i class="fas fa-medal medal-2" style="font-size:16px;"></i>
          <?php elseif($i===2): ?><i class="fas fa-medal medal-3" style="font-size:16px;"></i>
          <?php else: ?><span style="color:rgba(255,255,255,0.35);font-size:12px;font-weight:700;"><?= $i+1 ?></span>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="avatar-mini"><?= strtoupper(substr($u['nama'],0,1)) ?></div>
            <span style="font-weight:700;font-size:13px;"><?= e($u['nama']) ?></span>
          </div>
        </td>
        <td style="text-align:center;">
          <span style="font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:#60a5fa;"><?= $u['total_pinjam'] ?></span>
        </td>
        <td style="text-align:center;">
          <span style="font-size:13px;font-weight:700;color:#a78bfa;"><?= $u['total_review'] ?></span>
        </td>
        <td style="text-align:center;">
          <span style="font-size:13px;font-weight:700;color:#f9c74f;">
            <i class="fas fa-star" style="font-size:10px;"></i> <?= $u['poin'] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</div>
<footer class="footer" style="position:relative;z-index:1;background:rgba(0,0,0,0.35);border-top:1px solid rgba(255,255,255,0.10);color:rgba(255,255,255,0.50);">
  <p><i class="fas fa-cloud" style="color:#60a5fa;"></i> <span style="color:#fff;">CloudLibrary Mini</span> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?></p>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.color       = 'rgba(255,255,255,0.50)';
Chart.defaults.borderColor = 'rgba(255,255,255,0.08)';
Chart.defaults.font.family = 'Nunito, sans-serif';

const gridColor = 'rgba(255,255,255,0.06)';
const tickOpts  = { color: 'rgba(255,255,255,0.45)', font: { size: 11, family: 'Nunito', weight: '700' } };

new Chart(document.getElementById('chartHarian'), {
  type: 'line',
  data: {
    labels: <?= $harian_labels ?>,
    datasets: [{
      label: 'Peminjaman',
      data: <?= $harian_data ?>,
      borderColor: '#f9c74f',
      backgroundColor: 'rgba(249,199,79,0.10)',
      borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#f9c74f',
      tension: 0.4, fill: true,
    }]
  },
  options: {
    responsive: true, plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize:1, ...tickOpts }, grid: { color: gridColor } },
      x: { grid: { display: false }, ticks: { maxTicksLimit:10, ...tickOpts } }
    }
  }
});

new Chart(document.getElementById('chartBulanan'), {
  type: 'bar',
  data: {
    labels: <?= $bulanan_labels ?>,
    datasets: [{
      label: 'Pinjaman',
      data: <?= $bulanan_data ?>,
      backgroundColor: 'rgba(96,165,250,0.25)',
      borderColor: '#60a5fa', borderWidth: 1.5, borderRadius: 6,
    }]
  },
  options: {
    responsive: true, plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero:true, ticks:{ stepSize:1, ...tickOpts }, grid:{ color:gridColor } },
      x: { grid:{ display:false }, ticks: tickOpts }
    }
  }
});

new Chart(document.getElementById('chartReg'), {
  type: 'bar',
  data: {
    labels: <?= $reg_labels ?>,
    datasets: [{
      label: 'User Baru',
      data: <?= $reg_data ?>,
      backgroundColor: 'rgba(74,222,128,0.25)',
      borderColor: '#4ade80', borderWidth: 1.5, borderRadius: 6,
    }]
  },
  options: {
    responsive: true, plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero:true, ticks:{ stepSize:1, ...tickOpts }, grid:{ color:gridColor } },
      x: { grid:{ display:false }, ticks: tickOpts }
    }
  }
});

new Chart(document.getElementById('chartGenre'), {
  type: 'doughnut',
  data: {
    labels: <?= $genre_labels ?>,
    datasets: [{
      data: <?= $genre_data ?>,
      backgroundColor: <?= $genre_colors ?>,
      borderColor: 'rgba(5,15,35,0.80)', borderWidth: 2,
    }]
  },
  options: {
    responsive: true, cutout: '60%',
    plugins: {
      legend: { position:'right', labels:{ boxWidth:12, font:{ size:11 }, color:'rgba(255,255,255,0.65)' } }
    }
  }
});

new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: {
    labels: <?= $status_labels ?>,
    datasets: [{
      data: <?= $status_data ?>,
      backgroundColor: ['#4ade80','#fbbf24','#f87171','#94a3b8'],
      borderColor: 'rgba(5,15,35,0.80)', borderWidth: 2,
    }]
  },
  options: {
    responsive: true, cutout: '60%',
    plugins: {
      legend: { position:'right', labels:{ boxWidth:12, font:{ size:11 }, color:'rgba(255,255,255,0.65)' } }
    }
  }
});

function exportCSV() {
  const rows = [['Rank','Judul Buku','Genre','Dipinjam','Avg Rating']];
  document.querySelectorAll('#tableBuku tbody tr').forEach((tr, i) => {
    const cells = tr.querySelectorAll('td');
    rows.push([i+1, cells[1].innerText.trim(), cells[2].innerText.trim(), cells[3].innerText.trim(), cells[4].innerText.trim()]);
  });
  const csv  = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type:'text/csv;charset=utf-8;' });
  const a    = Object.assign(document.createElement('a'), { href:URL.createObjectURL(blob), download:'laporan-cloudlibrary.csv' });
  a.click(); URL.revokeObjectURL(a.href);
}
</script>
</body>
</html>