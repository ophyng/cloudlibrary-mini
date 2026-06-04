<?php
// ============================================
//  CloudLibrary Mini — Super Admin: Statistik Penuh
//  File   : super_admin/statistik.php
// ============================================
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: '.BASE_URL.'/auth/login.php'); exit;
}

// -- Ringkasan utama --
$ringkasan = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM buku WHERE status != 'arsip')                        AS total_buku,
        (SELECT COUNT(*) FROM users WHERE role = 'mahasiswa' AND status = 'aktif') AS total_user,
        (SELECT COUNT(*) FROM peminjaman)                                           AS total_pinjam,
        (SELECT COUNT(*) FROM peminjaman WHERE status IN ('aktif','hampir_habis'))  AS pinjam_aktif,
        (SELECT COUNT(*) FROM peminjaman WHERE status = 'expired')                 AS pinjam_expired,
        (SELECT COUNT(*) FROM peminjaman WHERE status = 'dikembalikan')            AS pinjam_kembali,
        (SELECT COUNT(*) FROM reviews WHERE status = 'approved')                   AS total_review,
        (SELECT ROUND(AVG(rating),1) FROM reviews WHERE status = 'approved')       AS avg_rating,
        (SELECT COUNT(*) FROM antrian WHERE status = 'menunggu')                   AS total_antrian,
        (SELECT SUM(poin) FROM users WHERE role = 'mahasiswa')                     AS total_poin,
        (SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'
         AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()))      AS user_baru_bulan
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

$reg_bulanan = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS bln, COUNT(*) AS total
    FROM users WHERE role = 'mahasiswa'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY bln ORDER BY bln ASC
")->fetchAll();

$genre_pop = $pdo->query("
    SELECT b.genre, COUNT(p.id) AS total
    FROM peminjaman p JOIN buku b ON p.buku_id = b.id
    GROUP BY b.genre ORDER BY total DESC LIMIT 8
")->fetchAll();

$status_dist = $pdo->query("SELECT status, COUNT(*) AS total FROM peminjaman GROUP BY status")->fetchAll();
$status_map  = array_column($status_dist, 'total', 'status');

$top_buku = $pdo->query("
    SELECT b.judul, b.genre, COUNT(p.id) AS total,
           ROUND(AVG(r.rating),1) AS avg_rating
    FROM buku b
    LEFT JOIN peminjaman p ON p.buku_id = b.id
    LEFT JOIN reviews r    ON r.buku_id = b.id AND r.status = 'approved'
    GROUP BY b.id ORDER BY total DESC LIMIT 10
")->fetchAll();

$top_user = $pdo->query("
    SELECT u.nama, u.poin, u.email,
           COUNT(DISTINCT p.id) AS total_pinjam,
           COUNT(DISTINCT r.id) AS total_review
    FROM users u
    LEFT JOIN peminjaman p ON p.user_id = u.id
    LEFT JOIN reviews r    ON r.user_id = u.id
    WHERE u.role = 'mahasiswa'
    GROUP BY u.id ORDER BY total_pinjam DESC LIMIT 10
")->fetchAll();

$level_dist = $pdo->query("
    SELECT
        SUM(CASE WHEN poin >= 500 THEN 1 ELSE 0 END) AS legenda,
        SUM(CASE WHEN poin >= 200 AND poin < 500 THEN 1 ELSE 0 END) AS master,
        SUM(CASE WHEN poin >= 100 AND poin < 200 THEN 1 ELSE 0 END) AS ahli,
        SUM(CASE WHEN poin >= 50  AND poin < 100 THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN poin >= 10  AND poin < 50  THEN 1 ELSE 0 END) AS pemula,
        SUM(CASE WHEN poin < 10 THEN 1 ELSE 0 END) AS baru
    FROM users WHERE role = 'mahasiswa'
")->fetch();

$genre_warna = [
    'Novel'    => ['icon'=>'fa-book',       'color'=>'#3a6186'],
    'Cerpen'   => ['icon'=>'fa-scroll',     'color'=>'#7c3aed'],
    'Fantasi'  => ['icon'=>'fa-hat-wizard', 'color'=>'#15803d'],
    'Romance'  => ['icon'=>'fa-heart',      'color'=>'#db2777'],
    'Horror'   => ['icon'=>'fa-ghost',      'color'=>'#b91c1c'],
    'Misteri'  => ['icon'=>'fa-user-secret','color'=>'#c2410c'],
    'Sci-Fi'   => ['icon'=>'fa-rocket',     'color'=>'#0369a1'],
    'Filsafat' => ['icon'=>'fa-landmark',   'color'=>'#57534e'],
    'Sains'    => ['icon'=>'fa-flask',      'color'=>'#1d4ed8'],
    'Biografi' => ['icon'=>'fa-feather-alt','color'=>'#d4a017'],
];

$harian_labels  = json_encode(array_column($pinjam_harian,  'tgl'));
$harian_data    = json_encode(array_column($pinjam_harian,  'total'));
$bulanan_labels = json_encode(array_column($pinjam_bulanan, 'bln'));
$bulanan_data   = json_encode(array_column($pinjam_bulanan, 'total'));
$reg_labels     = json_encode(array_column($reg_bulanan,    'bln'));
$reg_data       = json_encode(array_column($reg_bulanan,    'total'));
$genre_labels   = json_encode(array_column($genre_pop, 'genre'));
$genre_data     = json_encode(array_column($genre_pop, 'total'));
$genre_colors   = json_encode(array_map(fn($g) => $genre_warna[$g['genre']]['color'] ?? '#3a6186', $genre_pop));
$status_labels  = json_encode(['Aktif','Hampir Habis','Expired','Selesai']);
$status_data    = json_encode([$status_map['aktif']??0,$status_map['hampir_habis']??0,$status_map['expired']??0,$status_map['dikembalikan']??0]);
$level_labels   = json_encode(['Legenda','Master','Ahli','Aktif','Pemula','Baru']);
$level_data     = json_encode([$level_dist['legenda'],$level_dist['master'],$level_dist['ahli'],$level_dist['aktif'],$level_dist['pemula'],$level_dist['baru']]);

$title = "Statistik Penuh — Super Admin CloudLibrary Mini";
include '../includes/navbar.php';
?>

<style>
body {
  font-family: 'Nunito', sans-serif;
  min-height: 100vh; overflow-x: hidden; position: relative; margin: 0;
  background: #dce8f5;
  background-image: url('gambar_library.jpg');
  background-size: cover; background-position: center;
  background-attachment: fixed; background-repeat: no-repeat;
  color: #1a2744 !important;
}
body::before {
  content: ''; position: fixed; inset: 0;
  background: rgba(235, 243, 252, 0.18);
  z-index: 0; pointer-events: none;
}
:root {
  --s1:#3a6186;--s2:#2c4f78;--s3:#5b8fb9;--gold:#d4a017;--gold2:#f9c74f;
  --card:rgba(255,255,255,0.78);--card-b:rgba(255,255,255,0.85);
  --text:#1a2744;--muted:#6b7a99;
  --success:#15803d;--warning:#c2410c;--danger:#b91c1c;
  --sh:0 4px 20px rgba(58,97,134,0.10);--sh-md:0 10px 36px rgba(58,97,134,0.16);
}

.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:12px;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:var(--gold);}
.ph-sub{font-size:12px;font-weight:700;color:var(--muted);background:rgba(255,255,255,0.78);border:2px solid rgba(255,255,255,0.85);padding:6px 14px;border-radius:100px;backdrop-filter:blur(20px);display:flex;align-items:center;gap:6px;}

.export-row{display:flex;gap:8px;margin-bottom:22px;flex-wrap:wrap;position:relative;z-index:1;}
.btn-export{font-family:'Nunito',sans-serif;font-size:12px;font-weight:800;padding:8px 18px;border-radius:100px;border:1.5px solid rgba(58,97,134,0.25);background:rgba(255,255,255,0.78);color:var(--s1);cursor:pointer;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:all .2s;display:inline-flex;align-items:center;gap:6px;}
.btn-export:hover{background:linear-gradient(135deg,var(--s2),var(--s1));color:#fff;border-color:transparent;transform:translateY(-1px);box-shadow:var(--sh-md);}

.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px;position:relative;z-index:1;}
@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr)}}

.sc{background:var(--card);border:2px solid var(--card-b);border-radius:16px;padding:16px 18px;display:flex;align-items:center;gap:13px;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden;}
.sc:hover{transform:translateY(-2px);box-shadow:var(--sh-md);}
.sc-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.sc-num{font-family:'Syne',sans-serif;font-size:24px;font-weight:900;line-height:1;}
.sc-lbl{font-size:11px;font-weight:700;color:var(--muted);margin-top:3px;}

.insight-card{background:linear-gradient(135deg,#2c4f78,#3a6186,#5b8fb9);border-radius:20px;padding:22px 28px;margin-bottom:22px;position:relative;overflow:hidden;box-shadow:0 10px 36px rgba(58,97,134,0.28);border:1px solid rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;z-index:1;}
.insight-card::before{content:'';position:absolute;top:-50px;right:-50px;width:200px;height:200px;background:radial-gradient(circle,rgba(244,114,182,0.20),transparent 65%);}
.insight-card::after{content:'';position:absolute;inset:8px;border:2px dashed rgba(255,255,255,0.10);border-radius:14px;pointer-events:none;}
.ic-left{position:relative;z-index:1;}
.ic-eyebrow{font-size:10px;font-weight:900;letter-spacing:2.5px;text-transform:uppercase;color:rgba(253,230,138,0.80);margin-bottom:6px;display:flex;align-items:center;gap:6px;}
.ic-title{font-family:'Syne',sans-serif;font-size:clamp(15px,2vw,21px);font-weight:900;color:#fff;line-height:1.3;margin-bottom:4px;}
.ic-title .ig{color:#fde68a;}
.ic-sub{font-size:11px;color:rgba(255,255,255,0.45);font-weight:700;}
.ic-pills{display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;}
.ic-pill{background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.22);border-radius:12px;padding:10px 14px;text-align:center;backdrop-filter:blur(6px);min-width:62px;}
.ic-pill .pn{font-family:'Syne',sans-serif;font-size:17px;font-weight:900;color:#fff;line-height:1;}
.ic-pill .pl{font-size:10px;color:rgba(255,255,255,0.5);font-weight:700;margin-top:2px;}

.stitle{font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:var(--s1);margin-bottom:14px;margin-top:28px;display:flex;align-items:center;gap:10px;position:relative;z-index:1;}
.stitle-ico{width:32px;height:32px;border-radius:10px;background:rgba(58,97,134,0.12);border:1px solid rgba(58,97,134,0.22);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;color:var(--s1);}
.stitle::after{content:'';flex:1;height:1.5px;background:linear-gradient(90deg,rgba(58,97,134,0.22),transparent);margin-left:8px;}

.cg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;position:relative;z-index:1;}
@media(max-width:760px){.cg2{grid-template-columns:1fr}}

.panel{background:var(--card);border:2px solid var(--card-b);border-radius:18px;overflow:hidden;backdrop-filter:blur(20px);box-shadow:var(--sh);position:relative;z-index:1;}
.ph{padding:13px 18px;border-bottom:1.5px solid rgba(255,255,255,0.55);background:rgba(255,255,255,0.22);display:flex;align-items:center;gap:8px;}
.ph h3{font-family:'Syne',sans-serif;font-size:13px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:7px;}
.ph h3 i{color:var(--gold);font-size:12px;}
.pb{padding:18px;}

.tw{background:var(--card);border:2px solid var(--card-b);border-radius:18px;overflow:hidden;overflow-x:auto;backdrop-filter:blur(20px);box-shadow:var(--sh);position:relative;z-index:1;}
table.st{width:100%;border-collapse:collapse;font-size:13px;}
table.st th{font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;padding:12px 14px;text-align:left;border-bottom:1.5px solid rgba(255,255,255,0.55);background:rgba(255,255,255,0.22);white-space:nowrap;}
table.st td{padding:11px 14px;border-bottom:1px solid rgba(58,97,134,0.06);vertical-align:middle;color:var(--text);}
table.st tr:last-child td{border-bottom:none;}
table.st tr:hover td{background:rgba(58,97,134,0.025);}

.gbw{background:rgba(58,97,134,0.08);border-radius:6px;height:8px;flex:1;overflow:hidden;}
.gbf{height:100%;border-radius:6px;transition:width .7s ease;}

.av{width:28px;height:28px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--s1),var(--s3));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:11px;font-weight:900;color:#fff;box-shadow:0 2px 8px rgba(58,97,134,0.30);}

.level-bar-wrap{display:flex;flex-direction:column;gap:10px;}
.level-bar-row{display:flex;align-items:center;gap:10px;}
.level-bar-lbl{width:60px;font-size:11px;font-weight:800;color:var(--text);flex-shrink:0;}
.level-bar-bg{flex:1;height:10px;background:rgba(58,97,134,0.08);border-radius:5px;overflow:hidden;}
.level-bar-fill{height:100%;border-radius:5px;transition:width .7s ease;}
.level-bar-val{width:28px;text-align:right;font-size:11px;font-weight:900;color:var(--s1);}

.medal-ico{width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;}
.medal-1{background:rgba(212,160,23,0.15);color:#d4a017;}
.medal-2{background:rgba(156,163,175,0.15);color:#6b7280;}
.medal-3{background:rgba(180,83,9,0.15);color:#b45309;}

footer.sa-foot {
  position: relative; z-index: 1; text-align: center; padding: 20px;
  font-size: 12px; color: var(--muted); font-weight: 700;
  background: transparent;
  border-top: 1.5px dashed rgba(58,97,134,0.15);
  margin-top: 24px;
}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}
.fu3{animation:fadeUp .4s ease .20s both}.fu4{animation:fadeUp .4s ease .28s both}
.fu5{animation:fadeUp .4s ease .36s both}.fu6{animation:fadeUp .4s ease .44s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <h2><i class="fas fa-chart-bar"></i> Statistik Penuh</h2>
  <div class="ph-sub"><i class="fas fa-shield-alt" style="color:var(--s1);"></i> Super Admin &middot; <?= date('d M Y H:i') ?></div>
</div>

<!-- EXPORT -->
<div class="export-row fu1">
  <button class="btn-export" onclick="window.print()"><i class="fas fa-print"></i> Cetak Laporan</button>
  <button class="btn-export" onclick="exportCSV('tableBuku','laporan-buku.csv')"><i class="fas fa-file-download"></i> Export Buku CSV</button>
  <button class="btn-export" onclick="exportCSV('tableUser','laporan-user.csv')"><i class="fas fa-file-download"></i> Export User CSV</button>
</div>

<!-- SUMMARY CARDS -->
<div class="summary-grid fu2">
  <?php $cards=[
    ['fa-book',          'rgba(58,97,134,0.12)', 'rgba(58,97,134,0.28)',  '#3a6186',$ringkasan['total_buku'],       'Total Buku Aktif'],
    ['fa-users',         'rgba(21,128,61,0.12)', 'rgba(21,128,61,0.28)',  '#15803d',$ringkasan['total_user'],       'Mahasiswa Aktif'],
    ['fa-book-open',     'rgba(212,160,23,0.12)','rgba(212,160,23,0.28)', '#d4a017',$ringkasan['pinjam_aktif'],     'Pinjaman Aktif'],
    ['fa-check-circle',  'rgba(21,128,61,0.10)', 'rgba(21,128,61,0.22)',  '#15803d',$ringkasan['pinjam_kembali'],   'Sudah Kembali'],
    ['fa-exclamation-triangle','rgba(185,28,28,0.10)','rgba(185,28,28,0.22)','#b91c1c',$ringkasan['pinjam_expired'],'Expired'],
    ['fa-star',          'rgba(212,160,23,0.10)','rgba(212,160,23,0.22)', '#d4a017',$ringkasan['avg_rating'],       'Avg Rating'],
    ['fa-comment-dots',  'rgba(124,58,237,0.10)','rgba(124,58,237,0.22)', '#7c3aed',$ringkasan['total_review'],     'Total Review'],
    ['fa-trophy',        'rgba(58,97,134,0.10)', 'rgba(58,97,134,0.22)',  '#3a6186',number_format($ringkasan['total_poin']),'Total Poin'],
  ]; foreach($cards as [$ico,$bg,$bdr,$col,$num,$lbl]): ?>
  <div class="sc" style="border-color:<?= $bdr ?>;">
    <div class="sc-ico" style="background:<?= $bg ?>;color:<?= $col ?>;"><i class="fas <?= $ico ?>"></i></div>
    <div><div class="sc-num" style="color:<?= $col ?>;"><?= $num ?></div><div class="sc-lbl"><?= $lbl ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- INSIGHT BANNER -->
<div class="insight-card fu3">
  <div class="ic-left">
    <div class="ic-eyebrow"><i class="fas fa-chart-pie" style="font-size:9px;"></i> Ringkasan Sistem</div>
    <div class="ic-title">
      <span class="ig"><?= $ringkasan['total_pinjam'] ?> total transaksi</span> &amp;
      <?= $ringkasan['user_baru_bulan'] ?> user baru bulan ini
    </div>
    <div class="ic-sub">Data real-time &middot; Update: <?= date('d M Y, H:i') ?> WIB</div>
  </div>
  <div class="ic-pills">
    <div class="ic-pill"><div class="pn"><?= $ringkasan['total_pinjam'] ?></div><div class="pl">Transaksi</div></div>
    <div class="ic-pill"><div class="pn"><?= $ringkasan['pinjam_aktif'] ?></div><div class="pl">Aktif</div></div>
    <div class="ic-pill"><div class="pn"><?= $ringkasan['total_antrian'] ?></div><div class="pl">Antrian</div></div>
    <div class="ic-pill"><div class="pn"><?= $ringkasan['user_baru_bulan'] ?></div><div class="pl">User Baru</div></div>
  </div>
</div>

<!-- CHARTS -->
<div class="stitle fu4"><div class="stitle-ico"><i class="fas fa-chart-line"></i></div> Tren Peminjaman</div>
<div class="panel fu4" style="margin-bottom:16px;">
  <div class="ph"><h3><i class="fas fa-chart-area"></i> Peminjaman Harian — 30 Hari Terakhir</h3></div>
  <div class="pb"><canvas id="chartHarian" height="80"></canvas></div>
</div>

<div class="cg2 fu4" style="margin-bottom:16px;">
  <div class="panel">
    <div class="ph"><h3><i class="fas fa-chart-bar"></i> Peminjaman Bulanan — 12 Bulan</h3></div>
    <div class="pb"><canvas id="chartBulanan" height="160"></canvas></div>
  </div>
  <div class="panel">
    <div class="ph"><h3><i class="fas fa-user-plus"></i> Registrasi User Baru — 12 Bulan</h3></div>
    <div class="pb"><canvas id="chartReg" height="160"></canvas></div>
  </div>
</div>

<div class="cg2 fu5" style="margin-bottom:28px;">
  <div class="panel">
    <div class="ph"><h3><i class="fas fa-fire"></i> Genre Terpopuler</h3></div>
    <div class="pb"><canvas id="chartGenre" height="160"></canvas></div>
  </div>
  <div class="panel">
    <div class="ph"><h3><i class="fas fa-circle-notch"></i> Status Peminjaman</h3></div>
    <div class="pb"><canvas id="chartStatus" height="160"></canvas></div>
  </div>
</div>

<!-- GENRE HORIZONTAL BARS -->
<div class="stitle fu5"><div class="stitle-ico"><i class="fas fa-theater-masks"></i></div> Popularitas Genre</div>
<div class="panel fu5" style="margin-bottom:22px;">
  <div class="pb">
    <?php $max_g=max(array_column($genre_pop,'total')??[1]);
    foreach($genre_pop as $g):
      $gw=$genre_warna[$g['genre']]??['icon'=>'fa-book','color'=>'#3a6186'];
      $pct=round($g['total']/$max_g*100); ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:13px;">
      <div style="width:22px;text-align:center;font-size:14px;color:<?=$gw['color']?>;"><i class="fas <?=$gw['icon']?>"></i></div>
      <div style="width:68px;font-size:12px;font-weight:800;color:var(--text);"><?=e($g['genre'])?></div>
      <div class="gbw"><div class="gbf" style="width:<?=$pct?>%;background:<?=$gw['color']?>;"></div></div>
      <div style="width:32px;text-align:right;font-size:12px;font-weight:900;color:<?=$gw['color']?>;"><?=$g['total']?></div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($genre_pop)): ?><div style="color:var(--muted);font-size:12px;">Belum ada data.</div><?php endif; ?>
  </div>
</div>

<!-- DISTRIBUSI LEVEL USER -->
<div class="stitle fu5"><div class="stitle-ico"><i class="fas fa-crown"></i></div> Distribusi Level Mahasiswa</div>
<div class="panel fu5" style="margin-bottom:22px;">
  <div class="pb">
    <?php
    $total_user_all = max(array_sum(array_values($level_dist)), 1);
    $levels = [
      ['fa-crown',    'Legenda',$level_dist['legenda'],'#d4a017'],
      ['fa-gem',      'Master', $level_dist['master'], '#8b5cf6'],
      ['fa-star',     'Ahli',   $level_dist['ahli'],   '#3a6186'],
      ['fa-book-open','Aktif',  $level_dist['aktif'],  '#15803d'],
      ['fa-seedling', 'Pemula', $level_dist['pemula'], '#6b7a99'],
      ['fa-user',     'Baru',   $level_dist['baru'],   '#9ca3af'],
    ];
    foreach($levels as [$ico,$nm,$val,$col]):
      $pct=round($val/$total_user_all*100); ?>
    <div class="level-bar-row" style="margin-bottom:10px;">
      <div style="width:22px;text-align:center;font-size:13px;color:<?=$col?>;"><i class="fas <?=$ico?>"></i></div>
      <div class="level-bar-lbl"><?=$nm?></div>
      <div class="level-bar-bg"><div class="level-bar-fill" style="width:<?=$pct?>%;background:<?=$col?>;"></div></div>
      <div class="level-bar-val" style="color:<?=$col?>;"><?=$val?></div>
      <div style="width:30px;font-size:10px;color:var(--muted);font-weight:700;text-align:right;"><?=$pct?>%</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- TOP 10 BUKU -->
<div class="stitle fu5"><div class="stitle-ico"><i class="fas fa-book"></i></div> Top 10 Buku Terpopuler</div>
<div class="tw fu5" style="margin-bottom:22px;">
  <table class="st" id="tableBuku">
    <thead><tr><th>#</th><th>Judul</th><th>Genre</th><th style="text-align:center;">Dipinjam</th><th style="text-align:center;">Rating</th></tr></thead>
    <tbody>
      <?php foreach($top_buku as $i=>$b):
        $gw=$genre_warna[$b['genre']]??['icon'=>'fa-book','color'=>'#3a6186']; ?>
      <tr>
        <td style="text-align:center;">
          <?php if($i<3): ?><span class="medal-ico medal-<?=$i+1?>"><i class="fas fa-medal"></i></span>
          <?php else: ?><span style="color:var(--muted);font-family:'Syne',sans-serif;font-size:13px;font-weight:900;"><?=$i+1?></span><?php endif; ?>
        </td>
        <td><div style="display:flex;align-items:center;gap:8px;"><i class="fas <?=$gw['icon']?>" style="font-size:14px;color:<?=$gw['color']?>;"></i><span style="font-weight:700;font-size:13px;"><?=e($b['judul'])?></span></div></td>
        <td><span style="font-size:11px;font-weight:800;padding:2px 9px;border-radius:100px;background:rgba(58,97,134,0.10);color:var(--s1);"><?=e($b['genre'])?></span></td>
        <td style="text-align:center;"><span style="font-family:'Syne',sans-serif;font-size:17px;font-weight:900;color:var(--s1);"><?=$b['total']?></span><span style="font-size:10px;color:var(--muted);">&times;</span></td>
        <td style="text-align:center;"><?php if($b['avg_rating']): ?><span style="color:var(--gold);font-weight:800;font-size:12px;"><i class="fas fa-star" style="font-size:10px;"></i> <?=$b['avg_rating']?></span><?php else: ?><span style="color:rgba(58,97,134,0.2);">&mdash;</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($top_buku)): ?><tr><td colspan="5" style="text-align:center;padding:32px;color:var(--muted);">Belum ada data.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- TOP 10 USER -->
<div class="stitle fu6"><div class="stitle-ico"><i class="fas fa-medal"></i></div> Top 10 Mahasiswa Paling Aktif</div>
<div class="tw fu6" style="margin-bottom:50px;">
  <table class="st" id="tableUser">
    <thead><tr><th>#</th><th>Mahasiswa</th><th style="text-align:center;">Pinjaman</th><th style="text-align:center;">Review</th><th style="text-align:center;">Poin</th></tr></thead>
    <tbody>
      <?php foreach($top_user as $i=>$u): ?>
      <tr>
        <td style="text-align:center;">
          <?php if($i<3): ?><span class="medal-ico medal-<?=$i+1?>"><i class="fas fa-medal"></i></span>
          <?php else: ?><span style="color:var(--muted);font-family:'Syne',sans-serif;font-size:13px;font-weight:900;"><?=$i+1?></span><?php endif; ?>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="av"><?=strtoupper(substr($u['nama'],0,1))?></div>
            <div>
              <div style="font-weight:800;font-size:13px;"><?=e($u['nama'])?></div>
              <div style="font-size:10px;color:var(--muted);font-weight:600;"><?=e($u['email'])?></div>
            </div>
          </div>
        </td>
        <td style="text-align:center;"><span style="font-family:'Syne',sans-serif;font-size:17px;font-weight:900;color:var(--s1);"><?=$u['total_pinjam']?></span></td>
        <td style="text-align:center;"><span style="font-size:13px;font-weight:800;color:#7c3aed;"><?=$u['total_review']?></span></td>
        <td style="text-align:center;"><span style="font-size:13px;font-weight:800;color:var(--gold);"><i class="fas fa-star" style="font-size:10px;"></i> <?=$u['poin']?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($top_user)): ?><tr><td colspan="5" style="text-align:center;padding:32px;color:var(--muted);">Belum ada data.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<footer class="sa-foot">
  <i class="fas fa-cloud" style="color:var(--s1);margin-right:5px;"></i>
  <strong style="color:var(--s1);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(58,97,134,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.color='#6b7a99';Chart.defaults.borderColor='rgba(58,97,134,0.07)';
Chart.defaults.font.family='Nunito, sans-serif';Chart.defaults.font.weight='700';
const tip={backgroundColor:'rgba(26,39,68,0.94)',borderColor:'rgba(58,97,134,0.30)',borderWidth:1,titleColor:'#dce8f5',bodyColor:'#93c5fd',padding:12,cornerRadius:12};

new Chart(document.getElementById('chartHarian'),{type:'line',data:{
  labels:<?=$harian_labels?>,datasets:[{data:<?=$harian_data?>,borderColor:'#3a6186',backgroundColor:'rgba(58,97,134,0.08)',borderWidth:2.5,pointRadius:4,pointBackgroundColor:'#3a6186',pointBorderColor:'rgba(255,255,255,0.9)',pointBorderWidth:2,tension:0.4,fill:true}]},
  options:{responsive:true,plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(58,97,134,0.05)'}},x:{grid:{display:false},ticks:{maxTicksLimit:10}}}}});

new Chart(document.getElementById('chartBulanan'),{type:'bar',data:{
  labels:<?=$bulanan_labels?>,datasets:[{data:<?=$bulanan_data?>,backgroundColor:'rgba(58,97,134,0.20)',borderColor:'#3a6186',borderWidth:1.5,borderRadius:8,borderSkipped:false}]},
  options:{responsive:true,plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(58,97,134,0.05)'}},x:{grid:{display:false}}}}});

new Chart(document.getElementById('chartReg'),{type:'bar',data:{
  labels:<?=$reg_labels?>,datasets:[{data:<?=$reg_data?>,backgroundColor:'rgba(212,160,23,0.22)',borderColor:'#d4a017',borderWidth:1.5,borderRadius:8,borderSkipped:false}]},
  options:{responsive:true,plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(58,97,134,0.05)'}},x:{grid:{display:false}}}}});

new Chart(document.getElementById('chartGenre'),{type:'doughnut',data:{
  labels:<?=$genre_labels?>,datasets:[{data:<?=$genre_data?>,backgroundColor:<?=$genre_colors?>,borderColor:'rgba(255,255,255,0.8)',borderWidth:2.5}]},
  options:{responsive:true,cutout:'65%',plugins:{legend:{position:'right',labels:{boxWidth:12,font:{size:11,weight:'700'},padding:10,color:'#6b7a99'}},tooltip:tip}}});

new Chart(document.getElementById('chartStatus'),{type:'doughnut',data:{
  labels:<?=$status_labels?>,datasets:[{data:<?=$status_data?>,backgroundColor:['rgba(21,128,61,0.85)','rgba(194,65,12,0.85)','rgba(185,28,28,0.85)','rgba(156,163,175,0.85)'],borderColor:'rgba(255,255,255,0.8)',borderWidth:2.5}]},
  options:{responsive:true,cutout:'65%',plugins:{legend:{position:'right',labels:{boxWidth:12,font:{size:11,weight:'700'},padding:10,color:'#6b7a99'}},tooltip:tip}}});

function exportCSV(tableId, filename) {
  const rows=[]; const table=document.getElementById(tableId);
  table.querySelectorAll('tr').forEach(tr=>{
    rows.push(Array.from(tr.querySelectorAll('th,td')).map(c=>`"${c.innerText.trim().replace(/"/g,'""')}"`).join(','));
  });
  const blob=new Blob([rows.join('\n')],{type:'text/csv;charset=utf-8;'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=filename;a.click();
}
</script>
</body>
</html>