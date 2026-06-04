<?php
// ============================================
//  CloudLibrary Mini — Dashboard Admin
//  File   : admin/dashboard.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginAdmin();
updateStatusPeminjaman($pdo);

$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM buku WHERE status != 'arsip') AS total_buku,
        (SELECT COUNT(*) FROM users WHERE role = 'mahasiswa') AS total_user,
        (SELECT COUNT(*) FROM peminjaman WHERE status IN ('aktif','hampir_habis')) AS pinjam_aktif,
        (SELECT COUNT(*) FROM peminjaman WHERE status = 'expired') AS total_expired,
        (SELECT COUNT(*) FROM review WHERE status = 'tampil') AS total_review,
        (SELECT COUNT(*) FROM antrian WHERE status = 'menunggu') AS total_antrian
")->fetch();

$chart_data = $pdo->query("
    SELECT DATE(created_at) AS tgl, COUNT(*) AS total
    FROM peminjaman
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at) ORDER BY tgl ASC
")->fetchAll();

$chart_labels = []; $chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($tgl));
    $found = 0;
    foreach ($chart_data as $c) { if ($c['tgl'] === $tgl) { $found = $c['total']; break; } }
    $chart_values[] = $found;
}

$top_buku = $pdo->query("
    SELECT b.judul, b.genre, b.total_dipinjam, IFNULL(AVG(r.rating),0) AS avg_rating
    FROM buku b LEFT JOIN review r ON r.buku_id = b.id AND r.status = 'tampil'
    WHERE b.status != 'arsip' GROUP BY b.id ORDER BY b.total_dipinjam DESC LIMIT 5
")->fetchAll();

$top_genre = $pdo->query("
    SELECT b.genre, COUNT(p.id) AS total
    FROM peminjaman p JOIN buku b ON p.buku_id = b.id
    GROUP BY b.genre ORDER BY total DESC LIMIT 6
")->fetchAll();

$pinjam_aktif = $pdo->query("
    SELECT p.*, u.nama AS nama_user, b.judul, b.genre
    FROM peminjaman p JOIN users u ON p.user_id = u.id JOIN buku b ON p.buku_id = b.id
    WHERE p.status IN ('aktif','hampir_habis') ORDER BY p.tanggal_expired ASC LIMIT 8
")->fetchAll();

$top_user = $pdo->query("
    SELECT u.nama, u.poin, u.email, COUNT(p.id) AS total_pinjam,
           (SELECT COUNT(*) FROM review r WHERE r.user_id = u.id) AS total_review
    FROM users u LEFT JOIN peminjaman p ON p.user_id = u.id
    WHERE u.role = 'mahasiswa' GROUP BY u.id ORDER BY total_pinjam DESC LIMIT 5
")->fetchAll();

$review_baru = $pdo->query("
    SELECT r.*, u.nama AS nama_user, b.judul
    FROM review r JOIN users u ON r.user_id = u.id JOIN buku b ON r.buku_id = b.id
    WHERE r.status = 'tampil' ORDER BY r.created_at DESC LIMIT 5
")->fetchAll();

$genre_warna = [
    'Novel'   =>['bg'=>'#1a237e','icon'=>'fa-book'],
    'Cerpen'  =>['bg'=>'#4a148c','icon'=>'fa-file-alt'],
    'Fantasi' =>['bg'=>'#1b5e20','icon'=>'fa-hat-wizard'],
    'Romance' =>['bg'=>'#880e4f','icon'=>'fa-heart'],
    'Horror'  =>['bg'=>'#b71c1c','icon'=>'fa-ghost'],
    'Misteri' =>['bg'=>'#e65100','icon'=>'fa-search'],
    'Sci-Fi'  =>['bg'=>'#006064','icon'=>'fa-rocket'],
    'Filsafat'=>['bg'=>'#37474f','icon'=>'fa-landmark'],
    'Sains'   =>['bg'=>'#1565c0','icon'=>'fa-flask'],
    'Biografi'=>['bg'=>'#4e342e','icon'=>'fa-user'],
];

$title = "Dashboard Admin — CloudLibrary Mini";
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
  --navy:#fff;
  --sh:0 4px 22px rgba(0,0,0,0.22);
  --sh-md:0 8px 32px rgba(0,0,0,0.32);
}

/* PAGE HEADER */
.page-header{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:#f9c74f;}
.ph-badge{font-size:12px;font-weight:700;color:rgba(255,255,255,0.65);background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.18);padding:6px 14px;border-radius:100px;display:flex;align-items:center;gap:6px;backdrop-filter:blur(10px);}

/* STAT GRID */
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;position:relative;z-index:1;}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:580px){.stat-grid{grid-template-columns:1fr;}}
.stat-card{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:16px;padding:18px 16px;display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);transition:transform .2s,box-shadow .2s,background .2s;position:relative;overflow:hidden;}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md);background:rgba(255,255,255,0.16);}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-info .num{font-family:'Syne',sans-serif;font-size:28px;font-weight:900;line-height:1;color:#fff;}
.stat-info .lbl{font-size:12px;font-weight:700;color:rgba(255,255,255,0.65);margin-top:4px;}
.stat-info .sub{font-size:10px;font-weight:600;color:rgba(255,255,255,0.40);margin-top:2px;}

/* QUICK ACTIONS */
.quick-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;position:relative;z-index:1;}
@media(max-width:700px){.quick-actions{grid-template-columns:repeat(2,1fr);}}
.quick-btn{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:14px;padding:18px 12px;text-align:center;text-decoration:none;color:#fff;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);box-shadow:var(--sh);transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:8px;}
.quick-btn:hover{transform:translateY(-3px);box-shadow:var(--sh-md);background:rgba(255,255,255,0.18);}
.quick-btn i{font-size:20px;}
.quick-btn span{font-size:12px;font-weight:700;color:rgba(255,255,255,0.80);}

/* GRID 2 */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;position:relative;z-index:1;}
@media(max-width:900px){.grid-2{grid-template-columns:1fr;}}

/* PANELS */
.panel{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:16px;overflow:hidden;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);position:relative;}
.panel-header{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between;}
.panel-header h3{font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:#fff;display:flex;align-items:center;gap:8px;}
.panel-header h3 i{color:#f9c74f;font-size:13px;}
.panel-header a{font-size:11px;font-weight:800;color:rgba(255,255,255,0.65);text-decoration:none;padding:4px 12px;border-radius:100px;background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.18);transition:all .18s;}
.panel-header a:hover{background:rgba(255,255,255,0.20);color:#fff;}
.panel-body{padding:16px 20px;}

/* CHART */
.chart-wrap{position:relative;height:200px;}

/* GENRE BAR */
.genre-mini-bar{height:7px;border-radius:4px;background:rgba(255,255,255,0.08);overflow:hidden;margin-top:5px;}
.genre-mini-fill{height:100%;border-radius:4px;}

/* TABLE */
.mini-table{width:100%;border-collapse:collapse;font-size:13px;}
.mini-table th{font-size:10px;font-weight:900;color:rgba(255,255,255,0.50);text-transform:uppercase;letter-spacing:.6px;padding:10px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.08);}
.mini-table td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,0.07);vertical-align:middle;color:#fff;}
.mini-table tr:last-child td{border-bottom:none;}
.mini-table tr:hover td{background:rgba(255,255,255,0.05);}

/* CHIPS */
.chip{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;padding:3px 9px;border-radius:6px;text-transform:uppercase;}
.chip-aktif{background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.25);}
.chip-hampir{background:rgba(251,191,36,0.15);color:#fbbf24;border:1px solid rgba(251,191,36,0.25);}

.sisa-badge{font-size:11px;font-weight:800;padding:3px 9px;border-radius:6px;}

/* AVATAR */
.avatar-mini{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,0.20);border:1.5px solid rgba(255,255,255,0.30);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;color:#fff;font-family:'Syne',sans-serif;flex-shrink:0;}

/* FOOTER */
.footer{color:rgba(255,255,255,0.45);}
.footer span{color:rgba(255,255,255,0.80);}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}.fu4{animation:fadeUp .4s ease .28s both}.fu5{animation:fadeUp .4s ease .36s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <h2><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h2>
  <div class="ph-badge">
    <i class="fas fa-sync-alt" style="color:#60a5fa;"></i>
    Update: <?= date('d/m/Y H:i') ?>
  </div>
</div>

<!-- STAT CARDS — SVG custom icons palet emas -->
<div class="stat-grid fu2">

  <!-- Total Buku -->
  <a href="<?= BASE_URL ?>/admin/buku/index.php" class="stat-card" style="border-color:rgba(249,199,79,0.30);">
    <div class="stat-icon" style="background:rgba(249,199,79,0.15);">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32">
        <defs><linearGradient id="da" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient></defs>
        <rect x="3" y="28" width="34" height="4" rx="2" fill="url(#da)"/>
        <rect x="4" y="12" width="10" height="16" rx="1.5" fill="#0f2744" stroke="url(#da)" stroke-width="1"/>
        <rect x="4" y="12" width="2.5" height="16" rx="1" fill="url(#da)" opacity="0.8"/>
        <rect x="16" y="14" width="7" height="14" rx="1" fill="#1e3a5f" stroke="url(#da)" stroke-width="0.9" transform="rotate(-5,19,21)"/>
        <rect x="25" y="9" width="11" height="19" rx="1.5" fill="#0f2744" stroke="url(#da)" stroke-width="1"/>
        <rect x="25" y="9" width="3" height="19" rx="1" fill="url(#da)" opacity="0.9"/>
        <polygon points="20,2 21.5,7 27,7 22.5,10 24,15 20,12 16,15 17.5,10 13,7 18.5,7" fill="url(#da)"/>
      </svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $stats['total_buku'] ?></div>
      <div class="lbl">Total Buku</div>
      <div class="sub">aktif di sistem</div>
    </div>
  </a>

  <!-- Mahasiswa -->
  <a href="<?= BASE_URL ?>/admin/pengguna/index.php" class="stat-card" style="border-color:rgba(74,222,128,0.30);">
    <div class="stat-icon" style="background:rgba(74,222,128,0.15);">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32">
        <circle cx="14" cy="13" r="6" fill="rgba(74,222,128,0.20)" stroke="#4ade80" stroke-width="1.3"/>
        <circle cx="27" cy="13" r="5" fill="rgba(74,222,128,0.12)" stroke="#4ade80" stroke-width="1"/>
        <path d="M2,32 C2,24 26,24 26,32" fill="rgba(74,222,128,0.20)" stroke="#4ade80" stroke-width="1.3"/>
        <path d="M26,28 C26,24 38,24 38,30" fill="rgba(74,222,128,0.12)" stroke="#4ade80" stroke-width="1"/>
        <circle cx="14" cy="13" r="3.5" fill="#4ade80" opacity="0.6"/>
      </svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $stats['total_user'] ?></div>
      <div class="lbl">Mahasiswa</div>
      <div class="sub">terdaftar</div>
    </div>
  </a>

  <!-- Dipinjam Aktif -->
  <a href="<?= BASE_URL ?>/admin/peminjaman/index.php" class="stat-card" style="border-color:rgba(251,191,36,0.30);">
    <div class="stat-icon" style="background:rgba(251,191,36,0.15);">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32">
        <circle cx="20" cy="20" r="15" fill="rgba(251,191,36,0.12)" stroke="#fbbf24" stroke-width="1.3"/>
        <circle cx="20" cy="20" r="11" fill="rgba(251,191,36,0.08)" stroke="#fbbf24" stroke-width="0.8"/>
        <line x1="20" y1="9" x2="20" y2="20" stroke="#fbbf24" stroke-width="2" stroke-linecap="round"/>
        <line x1="20" y1="20" x2="28" y2="24" stroke="#fbbf24" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="20" cy="20" r="2" fill="#fbbf24"/>
        <line x1="20" y1="5" x2="20" y2="8" stroke="#fbbf24" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="35" y1="20" x2="32" y2="20" stroke="#fbbf24" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $stats['pinjam_aktif'] ?></div>
      <div class="lbl">Dipinjam Aktif</div>
      <div class="sub">sedang berlangsung</div>
    </div>
  </a>

  <!-- Expired -->
  <a href="<?= BASE_URL ?>/admin/peminjaman/index.php?status=expired" class="stat-card" style="border-color:rgba(248,113,113,0.30);">
    <div class="stat-icon" style="background:rgba(248,113,113,0.15);">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32">
        <circle cx="20" cy="20" r="15" fill="rgba(248,113,113,0.12)" stroke="#f87171" stroke-width="1.3"/>
        <line x1="13" y1="13" x2="27" y2="27" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="27" y1="13" x2="13" y2="27" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $stats['total_expired'] ?></div>
      <div class="lbl">Expired</div>
      <div class="sub">akses ditutup</div>
    </div>
  </a>

  <!-- Review -->
  <a href="<?= BASE_URL ?>/admin/review/index.php" class="stat-card" style="border-color:rgba(249,199,79,0.30);">
    <div class="stat-icon" style="background:rgba(249,199,79,0.15);">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32">
        <defs><linearGradient id="db" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient></defs>
        <polygon points="20,5 23,14 33,14 25,20 28,30 20,24 12,30 15,20 7,14 17,14" fill="url(#db)"/>
      </svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $stats['total_review'] ?></div>
      <div class="lbl">Review Aktif</div>
      <div class="sub">dari pembaca</div>
    </div>
  </a>

  <!-- Antrian -->
  <a href="<?= BASE_URL ?>/admin/peminjaman/index.php" class="stat-card" style="border-color:rgba(148,163,184,0.30);">
    <div class="stat-icon" style="background:rgba(148,163,184,0.15);">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32">
        <line x1="8" y1="12" x2="32" y2="12" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="8" y1="20" x2="28" y2="20" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="8" y1="28" x2="22" y2="28" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $stats['total_antrian'] ?></div>
      <div class="lbl">Antrian</div>
      <div class="sub">menunggu giliran</div>
    </div>
  </a>

</div>

<!-- QUICK ACTIONS -->
<div class="quick-actions fu3">
  <a href="<?= BASE_URL ?>/admin/buku/tambah.php" class="quick-btn">
    <i class="fas fa-plus-circle" style="color:#f9c74f;"></i>
    <span>Tambah Buku</span>
  </a>
  <a href="<?= BASE_URL ?>/admin/pengguna/index.php" class="quick-btn">
    <i class="fas fa-users" style="color:#4ade80;"></i>
    <span>Kelola User</span>
  </a>
  <a href="<?= BASE_URL ?>/admin/review/index.php" class="quick-btn">
    <i class="fas fa-star" style="color:#fbbf24;"></i>
    <span>Moderasi Review</span>
  </a>
  <a href="<?= BASE_URL ?>/admin/statistik.php" class="quick-btn">
    <i class="fas fa-chart-line" style="color:#a78bfa;"></i>
    <span>Statistik</span>
  </a>
</div>

<!-- ROW: GRAFIK + GENRE -->
<div class="grid-2 fu4">
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-chart-line"></i> Peminjaman 7 Hari Terakhir</h3>
    </div>
    <div class="panel-body">
      <div class="chart-wrap"><canvas id="chartPinjam"></canvas></div>
    </div>
  </div>
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-fire"></i> Genre Terpopuler</h3>
      <a href="<?= BASE_URL ?>/admin/statistik.php">Selengkapnya</a>
    </div>
    <div class="panel-body">
      <?php if ($top_genre):
        $max_g = $top_genre[0]['total'];
        $cols  = ['#f9c74f','#60a5fa','#4ade80','#f472b6','#a78bfa','#38bdf8'];
      ?>
        <?php foreach ($top_genre as $i => $g):
          $pct = $max_g > 0 ? round($g['total'] / $max_g * 100) : 0;
          $gw  = $genre_warna[$g['genre']] ?? ['icon'=>'fa-book','bg'=>'#1e3a5f'];
        ?>
        <div style="margin-bottom:14px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
            <span style="font-size:12px;font-weight:800;color:#fff;display:flex;align-items:center;gap:6px;">
              <i class="fas <?= $gw['icon'] ?>" style="color:<?= $cols[$i]??'#f9c74f' ?>;font-size:12px;width:14px;"></i>
              <?= e($g['genre']) ?>
            </span>
            <span style="font-size:11px;color:rgba(255,255,255,0.50);font-weight:700;"><?= $g['total'] ?>x</span>
          </div>
          <div class="genre-mini-bar">
            <div class="genre-mini-fill" style="width:<?= $pct ?>%;background:<?= $cols[$i] ?? '#f9c74f' ?>;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="text-align:center;padding:28px 0;color:rgba(255,255,255,0.45);font-weight:700;">
          <i class="fas fa-chart-bar" style="font-size:26px;margin-bottom:8px;display:block;"></i>Belum ada data.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ROW: PEMINJAMAN AKTIF + TOP BUKU -->
<div class="grid-2 fu5">
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-clock"></i> Peminjaman Aktif</h3>
      <a href="<?= BASE_URL ?>/admin/peminjaman/index.php">Lihat Semua</a>
    </div>
    <div class="panel-body" style="padding:0;">
      <?php if ($pinjam_aktif): ?>
        <table class="mini-table">
          <thead><tr><th>User</th><th>Buku</th><th>Sisa</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($pinjam_aktif as $p):
              $sisa = sisaHari($p['tanggal_expired']);
              $gw   = $genre_warna[$p['genre']] ?? ['icon'=>'fa-book','bg'=>'#1e3a5f'];
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div class="avatar-mini"><?= strtoupper(substr($p['nama_user'],0,1)) ?></div>
                  <span style="font-size:12px;font-weight:700;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(explode(' ',$p['nama_user'])[0]) ?></span>
                </div>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <i class="fas <?= $gw['icon'] ?>" style="font-size:12px;color:rgba(255,255,255,0.50);"></i>
                  <span style="font-size:12px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;"><?= e($p['judul']) ?></span>
                </div>
              </td>
              <td>
                <span class="sisa-badge"
                  style="background:<?= $sisa<=1?'rgba(248,113,113,0.15)':($sisa<=3?'rgba(251,191,36,0.15)':'rgba(74,222,128,0.15)') ?>;
                         color:<?= $sisa<=1?'#f87171':($sisa<=3?'#fbbf24':'#4ade80') ?>;
                         border:1px solid <?= $sisa<=1?'rgba(248,113,113,0.25)':($sisa<=3?'rgba(251,191,36,0.25)':'rgba(74,222,128,0.25)') ?>;">
                  <?= max($sisa,0) ?>h
                </span>
              </td>
              <td>
                <span class="chip <?= $p['status']==='hampir_habis'?'chip-hampir':'chip-aktif' ?>">
                  <i class="fas <?= $p['status']==='hampir_habis'?'fa-exclamation-triangle':'fa-check' ?>"></i>
                  <?= $p['status']==='hampir_habis'?'Hampir':'Aktif' ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:rgba(255,255,255,0.45);font-weight:700;">
          <i class="fas fa-check-circle" style="font-size:26px;color:#4ade80;display:block;margin-bottom:8px;"></i>
          Tidak ada peminjaman aktif.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-trophy"></i> Buku Paling Diminati</h3>
      <a href="<?= BASE_URL ?>/admin/buku/index.php">Kelola</a>
    </div>
    <div class="panel-body" style="padding:0;">
      <?php if ($top_buku): ?>
        <table class="mini-table">
          <thead><tr><th>#</th><th>Judul</th><th>Genre</th><th>Pinjam</th><th>Rating</th></tr></thead>
          <tbody>
            <?php
            $medal_colors = ['#f9c74f','#94a3b8','#cd7f32','rgba(255,255,255,0.35)','rgba(255,255,255,0.35)'];
            $medal_icons  = ['fa-medal','fa-medal','fa-medal','fa-hashtag','fa-hashtag'];
            foreach ($top_buku as $i => $b):
              $gw = $genre_warna[$b['genre']] ?? ['icon'=>'fa-book','bg'=>'#1e3a5f'];
            ?>
            <tr>
              <td>
                <i class="fas <?= $medal_icons[$i] ?>" style="font-size:16px;color:<?= $medal_colors[$i] ?>;"></i>
              </td>
              <td style="max-width:120px;"><div style="font-size:12px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($b['judul']) ?></div></td>
              <td>
                <div style="display:flex;align-items:center;gap:5px;">
                  <i class="fas <?= $gw['icon'] ?>" style="font-size:11px;color:rgba(255,255,255,0.50);"></i>
                  <span style="font-size:11px;color:rgba(255,255,255,0.55);font-weight:600;"><?= e($b['genre']) ?></span>
                </div>
              </td>
              <td><span style="font-weight:800;color:#60a5fa;"><?= $b['total_dipinjam'] ?>x</span></td>
              <td><span style="color:#fbbf24;font-size:12px;font-weight:800;"><i class="fas fa-star" style="font-size:10px;"></i> <?= number_format($b['avg_rating'],1) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:rgba(255,255,255,0.45);font-weight:700;">
          <i class="fas fa-book" style="font-size:26px;display:block;margin-bottom:8px;"></i>Belum ada data.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ROW: TOP USER + REVIEW -->
<div class="grid-2 fu5">
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-user-star"></i> Pengguna Paling Aktif</h3>
      <a href="<?= BASE_URL ?>/admin/pengguna/index.php">Semua</a>
    </div>
    <div class="panel-body" style="padding:0;">
      <table class="mini-table">
        <thead><tr><th>#</th><th>Nama</th><th>Pinjam</th><th>Review</th><th>Poin</th></tr></thead>
        <tbody>
          <?php foreach ($top_user as $i => $u): ?>
          <tr>
            <td style="font-weight:900;color:rgba(255,255,255,0.40);font-size:13px;font-family:'Syne',sans-serif;"><?= $i+1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="avatar-mini"><?= strtoupper(substr($u['nama'],0,1)) ?></div>
                <div>
                  <div style="font-size:12px;font-weight:700;"><?= e(explode(' ',$u['nama'])[0]) ?></div>
                  <div style="font-size:10px;color:rgba(255,255,255,0.40);font-weight:600;"><?= e($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td><span style="font-weight:800;color:#60a5fa;"><?= $u['total_pinjam'] ?>x</span></td>
            <td><span style="color:#fbbf24;font-weight:800;"><i class="fas fa-star" style="font-size:10px;"></i> <?= $u['total_review'] ?></span></td>
            <td><span style="font-size:12px;color:rgba(255,255,255,0.50);font-weight:700;"><?= $u['poin'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-comment-alt"></i> Review Terbaru</h3>
      <a href="<?= BASE_URL ?>/admin/review/index.php">Moderasi</a>
    </div>
    <div class="panel-body" style="padding:0 20px;">
      <?php if ($review_baru): ?>
        <?php foreach ($review_baru as $r): ?>
        <div style="padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;gap:10px;align-items:flex-start;">
          <div class="avatar-mini" style="margin-top:2px;"><?= strtoupper(substr($r['nama_user'],0,1)) ?></div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
              <span style="font-size:12px;font-weight:800;color:#fff;"><?= e(explode(' ',$r['nama_user'])[0]) ?></span>
              <span style="font-size:12px;color:#fbbf24;font-weight:800;">
                <?php for($s=1;$s<=5;$s++): ?>
                  <i class="fas fa-star" style="font-size:9px;<?= $s<=$r['rating']?'':'opacity:0.2' ?>"></i>
                <?php endfor; ?>
              </span>
            </div>
            <div style="font-size:11px;color:rgba(255,255,255,0.45);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;">
              <i class="fas fa-book" style="font-size:10px;"></i> <?= e($r['judul']) ?>
            </div>
            <div style="font-size:12px;color:rgba(255,255,255,0.75);line-height:1.6;font-weight:600;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= e($r['komentar']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:rgba(255,255,255,0.45);font-weight:700;">
          <i class="fas fa-comment-slash" style="font-size:26px;display:block;margin-bottom:8px;"></i>Belum ada review.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

</div>
<footer class="footer" style="position:relative;z-index:1;background:rgba(0,0,0,0.35);border-top:1px solid rgba(255,255,255,0.10);">
  <p><i class="fas fa-cloud" style="color:#60a5fa;"></i> <span>CloudLibrary Mini</span> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?></p>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartPinjam').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chart_labels) ?>,
    datasets: [{
      label: 'Peminjaman',
      data: <?= json_encode($chart_values) ?>,
      backgroundColor: 'rgba(249,199,79,0.20)',
      borderColor: 'rgba(249,199,79,0.80)',
      borderWidth: 2, borderRadius: 8, borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(5,15,35,0.90)',
        borderColor: 'rgba(249,199,79,0.30)', borderWidth: 1,
        titleColor: '#fff', bodyColor: '#f9c74f',
        padding: 10, cornerRadius: 10,
        callbacks: { label: c => ` ${c.parsed.y} peminjaman` }
      }
    },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: 'rgba(255,255,255,0.50)', font: { size: 11, family: 'Nunito', weight: '700' } } },
      y: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: 'rgba(255,255,255,0.50)', font: { size: 11, family: 'Nunito', weight: '700' }, stepSize: 1 }, beginAtZero: true }
    }
  }
});
</script>
</body>
</html>