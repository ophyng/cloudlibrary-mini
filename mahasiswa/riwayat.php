<?php
// ============================================
//  CloudLibrary Mini — Riwayat Peminjaman
//  File   : mahasiswa/riwayat.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();
updateStatusPeminjaman($pdo);

$user_id = $_SESSION['user_id'];

$filter_status = $_GET['status'] ?? '';
$filter_genre  = $_GET['genre']  ?? '';
$search        = trim($_GET['q'] ?? '');

$where  = ["p.user_id = ?"];
$params = [$user_id];

if ($filter_status) { $where[] = "p.status = ?";  $params[] = $filter_status; }
if ($filter_genre)  { $where[] = "b.genre = ?";   $params[] = $filter_genre; }
if ($search)        { $where[] = "(b.judul LIKE ? OR b.penulis LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

// FIX 1: tambah b.cover
$sql = "
    SELECT p.*, b.judul, b.penulis, b.genre, b.tipe, b.cover,
           IFNULL(r.rating, 0) AS sudah_review
    FROM peminjaman p
    JOIN buku b ON p.buku_id = b.id
    LEFT JOIN review r ON r.user_id = p.user_id AND r.buku_id = p.buku_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

$stats = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('aktif','hampir_habis') THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) AS selesai,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired
    FROM peminjaman WHERE user_id = ?
");
$stats->execute([$user_id]);
$stats = $stats->fetch();

$fav_genre = $pdo->prepare("
    SELECT b.genre, COUNT(*) AS total
    FROM peminjaman p JOIN buku b ON p.buku_id = b.id
    WHERE p.user_id = ?
    GROUP BY b.genre ORDER BY total DESC LIMIT 3
");
$fav_genre->execute([$user_id]);
$fav_genres = $fav_genre->fetchAll();

$semua_genre = $pdo->query("SELECT DISTINCT genre FROM buku ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);

$genre_warna = [
    'Novel'    => ['bg' => '#1a237e', 'icon' => 'fa-book'],
    'Cerpen'   => ['bg' => '#4a148c', 'icon' => 'fa-scroll'],
    'Fantasi'  => ['bg' => '#1b5e20', 'icon' => 'fa-hat-wizard'],
    'Romance'  => ['bg' => '#880e4f', 'icon' => 'fa-heart'],
    'Horror'   => ['bg' => '#b71c1c', 'icon' => 'fa-ghost'],
    'Misteri'  => ['bg' => '#e65100', 'icon' => 'fa-user-secret'],
    'Sci-Fi'   => ['bg' => '#006064', 'icon' => 'fa-rocket'],
    'Filsafat' => ['bg' => '#37474f', 'icon' => 'fa-landmark'],
    'Sains'    => ['bg' => '#1565c0', 'icon' => 'fa-microscope'],
    'Biografi' => ['bg' => '#4e342e', 'icon' => 'fa-feather-alt'],
];

$title = "Riwayat Peminjaman — CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
body {
  background-image: url('gambar perpustakaan.jpg') !important;
  background-size: cover !important;
  background-position: center top !important;
  background-attachment: fixed !important;
  background-repeat: no-repeat !important;
  position: relative;
  overflow-x: hidden;
}
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background: rgba(220, 235, 255, 0.10);
  z-index: 0;
  pointer-events: none;
}
:root {
  --glass:       rgba(255,255,255,0.25);
  --glass-b:     rgba(255,255,255,0.80);
  --glass-hover: rgba(255,255,255,0.28);
  --border:      rgba(30,58,95,0.07);
  --border-s:    rgba(30,58,95,0.09);
  --text:        #1a2332;
  --text-sub:    #3d5270;
  --muted:       #6b80a0;
  --d1:          #1e3a5f;
  --d2:          #2d5986;
  --d3:          #4a7ab5;
  --gold:        #d97706;
  --gold-l:      #fbbf24;
  --pk:          #db2777;
  --pk-l:        #f472b6;
  --sh:          0 4px 24px rgba(30,58,95,0.08);
  --sh-md:       0 8px 36px rgba(30,58,95,0.18);
}
.page-outer { position: relative; z-index: 1; max-width: 1080px; margin: 0 auto; padding: 28px 20px 60px; }
.rw-page-header { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.09); border-radius: 24px; padding: 28px 32px; margin-bottom: 22px; backdrop-filter: blur(32px); -webkit-backdrop-filter: blur(32px); box-shadow: var(--sh-md); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; position: relative; overflow: hidden; }
.rw-page-header::before { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(45,89,134,0.12), transparent 65%); pointer-events: none; }
.rw-header-left  { display: flex; align-items: center; gap: 16px; }
.rw-header-icon  { width: 52px; height: 52px; border-radius: 16px; background: linear-gradient(135deg, #1e3a5f, #2d5986); display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; box-shadow: 0 4px 16px rgba(30,58,95,0.35); flex-shrink: 0; }
.rw-header-title { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); }
.rw-header-sub   { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
@media(max-width:768px) { .stat-grid { grid-template-columns: repeat(2,1fr); } }
.stat-card { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 20px; padding: 20px 16px; text-align: center; backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); box-shadow: var(--sh); transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden; }
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--sh-md); }
.stat-icon-wrap { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 20px; position: relative; z-index: 1; }
.stat-angka { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 900; line-height: 1; position: relative; z-index: 1; }
.stat-label { font-size: 10px; font-weight: 800; color: var(--muted); margin-top: 4px; letter-spacing: 0.5px; text-transform: uppercase; position: relative; z-index: 1; }
.sect-card { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 24px; padding: 22px 24px; backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); box-shadow: var(--sh); margin-bottom: 18px; }
.sect-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.sect-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 900; color: var(--text); display: flex; align-items: center; gap: 10px; }
.sect-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.fav-pills { display: flex; gap: 10px; flex-wrap: wrap; }
.fav-pill { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.28); border: 1px solid rgba(30,58,95,0.09); border-radius: 14px; padding: 10px 16px; backdrop-filter: blur(18px); transition: transform 0.2s; }
.fav-pill:hover { transform: translateY(-2px); }
.fav-pill-rank { width: 24px; height: 24px; border-radius: 8px; background: linear-gradient(135deg, #1e3a5f, #2d5986); color: #fff; font-size: 11px; font-weight: 900; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.fav-pill-icon { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.fav-pill-name  { font-size: 13px; font-weight: 800; color: var(--text); }
.fav-pill-count { font-size: 10px; color: var(--muted); font-weight: 700; }
.filter-row { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 20px; padding: 18px 20px; margin-bottom: 16px; backdrop-filter: blur(28px); box-shadow: var(--sh); display: flex; flex-direction: column; gap: 14px; }
.filter-inputs { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.rw-search { flex: 1; min-width: 200px; background: rgba(255,255,255,0.50); border: 1.5px solid rgba(30,58,95,0.12); border-radius: 100px; padding: 10px 18px; font-size: 13px; font-family: 'Nunito', sans-serif; font-weight: 700; color: var(--text); outline: none; transition: all 0.2s; }
.rw-search:focus { border-color: var(--d2); background: rgba(255,255,255,0.85); box-shadow: 0 0 0 3px rgba(45,89,134,0.12); }
.rw-search::placeholder { color: var(--muted); }
.rw-select { background: rgba(255,255,255,0.55); border: 1.5px solid rgba(30,58,95,0.12); border-radius: 100px; padding: 10px 18px; font-size: 12px; font-family: 'Nunito', sans-serif; font-weight: 700; color: var(--text); outline: none; cursor: pointer; transition: all 0.2s; min-width: 150px; }
.rw-select:focus { border-color: var(--d2); }
.status-tabs { display: flex; gap: 7px; flex-wrap: wrap; }
.status-tab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 100px; font-size: 12px; font-weight: 800; border: 1.5px solid rgba(30,58,95,0.12); color: var(--text-sub); text-decoration: none; background: rgba(255,255,255,0.28); backdrop-filter: blur(14px); transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.status-tab:hover { background: rgba(255,255,255,0.70); color: var(--d1); }
.status-tab.active { background: linear-gradient(135deg, #1e3a5f, #2d5986); color: #fff; border-color: transparent; box-shadow: 0 3px 12px rgba(30,58,95,0.35); }
.status-tab.tab-aktif.active       { background: linear-gradient(135deg,#14532d,#16a34a); }
.status-tab.tab-hampir.active      { background: linear-gradient(135deg,#78350f,#d97706); }
.status-tab.tab-expired.active     { background: linear-gradient(135deg,#7f1d1d,#dc2626); }
.status-tab.tab-selesai.active     { background: linear-gradient(135deg,#1e293b,#475569); }
.result-info { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 14px; }
.result-info strong { color: var(--text); font-family: 'Syne', sans-serif; font-size: 14px; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 7px; text-transform: uppercase; letter-spacing: 0.5px; }
.status-aktif        { background: rgba(22,163,74,0.12);  color: #15803d; }
.status-hampir_habis { background: rgba(217,119,6,0.12);  color: #92400e; }
.status-expired      { background: rgba(220,38,38,0.12);  color: #991b1b; }
.status-dikembalikan { background: rgba(71,85,105,0.12);  color: #334155; }
.riwayat-item { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 20px; padding: 18px 22px; margin-bottom: 12px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); box-shadow: var(--sh); transition: transform 0.2s, box-shadow 0.2s; }
.riwayat-item:hover { transform: translateY(-2px); box-shadow: var(--sh-md); }
/* FIX: tambah overflow:hidden */
.riwayat-cover { width: 50px; height: 68px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; box-shadow: 2px 4px 12px rgba(0,0,0,0.30); color: #fff; overflow: hidden; }
.riwayat-info { flex: 1; min-width: 180px; }
.riwayat-genre-tag { font-size: 10px; font-weight: 800; color: var(--d3); margin-bottom: 3px; }
.riwayat-title { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 900; color: var(--text); margin-bottom: 3px; line-height: 1.4; }
.riwayat-author { font-size: 11px; color: var(--muted); font-weight: 600; margin-bottom: 8px; }
.riwayat-badge-row { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.badge-perpanjang { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 7px; background: rgba(45,89,134,0.10); color: var(--d2); }
.badge-reviewed { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 7px; background: rgba(217,119,6,0.10); color: var(--gold); }
.riwayat-dates { flex-shrink: 0; display: flex; flex-direction: column; gap: 4px; }
.riwayat-date-row { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 600; color: var(--muted); }
.riwayat-date-row i { width: 16px; text-align: center; font-size: 10px; flex-shrink: 0; }
.riwayat-date-row strong { color: var(--text); font-weight: 800; }
.sisa-hari { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 8px; margin-top: 2px; }
.riwayat-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; align-items: center; }
.btn-baca-r { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 100px; background: linear-gradient(135deg, #1e3a5f, #2d5986); color: #fff; font-size: 12px; font-weight: 800; text-decoration: none; box-shadow: 0 3px 10px rgba(30,58,95,0.30); transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.btn-baca-r:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(30,58,95,0.40); }
.btn-perpanjang-r { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 100px; background: rgba(255,255,255,0.30); border: 1.5px solid rgba(30,58,95,0.14); color: var(--text); font-size: 12px; font-weight: 800; text-decoration: none; transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.btn-perpanjang-r:hover { background: rgba(255,255,255,0.85); }
.btn-lihat-r { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 100px; background: rgba(255,255,255,0.30); border: 1.5px solid rgba(30,58,95,0.14); color: var(--text); font-size: 12px; font-weight: 800; text-decoration: none; transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.btn-lihat-r:hover { background: rgba(255,255,255,0.85); }
.btn-review-r { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 100px; background: linear-gradient(135deg, #78350f, #d97706); color: #fff; font-size: 12px; font-weight: 800; text-decoration: none; box-shadow: 0 3px 10px rgba(217,119,6,0.30); transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.btn-review-r:hover { transform: translateY(-1px); opacity: 0.9; }
.empty-riwayat { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 24px; padding: 60px; text-align: center; backdrop-filter: blur(28px); box-shadow: var(--sh); }
.empty-riwayat .empty-icon { width: 72px; height: 72px; border-radius: 22px; background: rgba(45,89,134,0.10); border: 1.5px solid rgba(45,89,134,0.18); display: flex; align-items: center; justify-content: center; font-size: 28px; color: var(--d2); margin: 0 auto 18px; }
.empty-riwayat h3 { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); margin-bottom: 8px; }
.empty-riwayat p { font-size: 13px; color: var(--muted); font-weight: 600; line-height: 1.8; margin-bottom: 20px; }
.btn-primary-r { display: inline-flex; align-items: center; gap: 7px; padding: 12px 26px; border-radius: 100px; background: linear-gradient(135deg, #1e3a5f, #2d5986); color: #fff; font-size: 13px; font-weight: 900; text-decoration: none; box-shadow: 0 4px 18px rgba(30,58,95,0.35); transition: all 0.25s; font-family: 'Nunito', sans-serif; }
.btn-primary-r:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(30,58,95,0.45); }
footer.mhs-footer { position: relative; z-index: 1; text-align: center; padding: 24px; font-size: 12px; color: var(--muted); font-weight: 700; background: rgba(255,255,255,0.45); backdrop-filter: blur(22px); border-top: 1px solid rgba(30,58,95,0.07); }
@keyframes fadeUp { from{opacity:0;transform:translateY(16px);} to{opacity:1;transform:translateY(0);} }
.fu { animation: fadeUp 0.5s ease both; }
.fu:nth-child(1){animation-delay:.05s;} .fu:nth-child(2){animation-delay:.10s;}
.fu:nth-child(3){animation-delay:.15s;} .fu:nth-child(4){animation-delay:.20s;}
.fu:nth-child(5){animation-delay:.25s;} .fu:nth-child(6){animation-delay:.30s;}
@media(max-width:700px) { .riwayat-dates { display: none; } .riwayat-item { gap: 12px; } }
</style>

<div class="page-outer">

  <div class="rw-page-header fu">
    <div class="rw-header-left">
      <div class="rw-header-icon"><i class="fas fa-history"></i></div>
      <div>
        <div class="rw-header-title">Riwayat Peminjaman</div>
        <div class="rw-header-sub">Total <?= $stats['total'] ?> transaksi</div>
      </div>
    </div>
  </div>

  <div class="stat-grid fu">
    <div class="stat-card" style="border-color:rgba(45,89,134,0.20);">
      <div class="stat-icon-wrap" style="background:rgba(45,89,134,0.10);color:var(--d2);"><i class="fas fa-layer-group"></i></div>
      <div class="stat-angka" style="color:var(--d2);"><?= $stats['total'] ?></div>
      <div class="stat-label">Total Pinjam</div>
    </div>
    <div class="stat-card" style="border-color:rgba(22,163,74,0.22);">
      <div class="stat-icon-wrap" style="background:rgba(22,163,74,0.10);color:#16a34a;"><i class="fas fa-book-open"></i></div>
      <div class="stat-angka" style="color:#16a34a;"><?= $stats['aktif'] ?></div>
      <div class="stat-label">Sedang Dipinjam</div>
    </div>
    <div class="stat-card" style="border-color:rgba(71,85,105,0.22);">
      <div class="stat-icon-wrap" style="background:rgba(71,85,105,0.10);color:#475569;"><i class="fas fa-check-circle"></i></div>
      <div class="stat-angka" style="color:#475569;"><?= $stats['selesai'] ?></div>
      <div class="stat-label">Selesai</div>
    </div>
    <div class="stat-card" style="border-color:rgba(220,38,38,0.22);">
      <div class="stat-icon-wrap" style="background:rgba(220,38,38,0.10);color:#dc2626;"><i class="fas fa-hourglass-end"></i></div>
      <div class="stat-angka" style="color:#dc2626;"><?= $stats['expired'] ?></div>
      <div class="stat-label">Expired</div>
    </div>
  </div>

  <?php if ($fav_genres): ?>
  <div class="sect-card fu">
    <div class="sect-head">
      <div class="sect-title">
        <div class="sect-icon" style="background:rgba(239,68,68,0.12);color:#f87171;"><i class="fas fa-fire"></i></div>
        Genre Favoritmu
      </div>
    </div>
    <div class="fav-pills">
      <?php
      $genre_icon_map = [
        'Novel'    => ['fa' => 'fa-book',        'color' => '#6366f1'],
        'Cerpen'   => ['fa' => 'fa-scroll',      'color' => '#8b5cf6'],
        'Fantasi'  => ['fa' => 'fa-hat-wizard',  'color' => '#10b981'],
        'Romance'  => ['fa' => 'fa-heart',       'color' => '#ec4899'],
        'Horror'   => ['fa' => 'fa-ghost',       'color' => '#ef4444'],
        'Misteri'  => ['fa' => 'fa-user-secret', 'color' => '#f97316'],
        'Sci-Fi'   => ['fa' => 'fa-rocket',      'color' => '#06b6d4'],
        'Filsafat' => ['fa' => 'fa-landmark',    'color' => '#64748b'],
        'Sains'    => ['fa' => 'fa-microscope',  'color' => '#3b82f6'],
        'Biografi' => ['fa' => 'fa-feather-alt', 'color' => '#92400e'],
      ];
      foreach ($fav_genres as $i => $fg):
        $gi = $genre_icon_map[$fg['genre']] ?? ['fa' => 'fa-book', 'color' => '#93c5fd'];
      ?>
      <div class="fav-pill">
        <div class="fav-pill-rank"><?= $i + 1 ?></div>
        <div class="fav-pill-icon" style="background:<?= $gi['color'] ?>18;color:<?= $gi['color'] ?>;"><i class="fas <?= $gi['fa'] ?>"></i></div>
        <div>
          <div class="fav-pill-name"><?= e($fg['genre']) ?></div>
          <div class="fav-pill-count"><?= $fg['total'] ?> kali dipinjam</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="filter-row fu">
    <div class="filter-inputs">
      <i class="fas fa-search" style="color:var(--muted);font-size:14px;flex-shrink:0;"></i>
      <input type="text" id="searchInput" class="rw-search" placeholder="Cari judul atau penulis..." value="<?= e($search) ?>" oninput="filterLive()">
      <select class="rw-select" onchange="applyFilter('genre', this.value)">
        <option value="">Semua Genre</option>
        <?php foreach ($semua_genre as $g): ?>
          <option value="<?= $g ?>" <?= $filter_genre===$g ? 'selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="status-tabs">
      <a href="?<?= http_build_query(array_merge($_GET, ['status'=>''])) ?>" class="status-tab <?= !$filter_status ? 'active' : '' ?>"><i class="fas fa-th-list" style="font-size:10px;"></i> Semua</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'aktif'])) ?>" class="status-tab tab-aktif <?= $filter_status==='aktif' ? 'active' : '' ?>"><i class="fas fa-circle" style="font-size:8px;"></i> Aktif</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'hampir_habis'])) ?>" class="status-tab tab-hampir <?= $filter_status==='hampir_habis' ? 'active' : '' ?>"><i class="fas fa-exclamation-circle" style="font-size:10px;"></i> Hampir Habis</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'expired'])) ?>" class="status-tab tab-expired <?= $filter_status==='expired' ? 'active' : '' ?>"><i class="fas fa-times-circle" style="font-size:10px;"></i> Expired</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'dikembalikan'])) ?>" class="status-tab tab-selesai <?= $filter_status==='dikembalikan' ? 'active' : '' ?>"><i class="fas fa-check-circle" style="font-size:10px;"></i> Selesai</a>
    </div>
  </div>

  <div class="result-info fu">
    <i class="fas fa-list" style="font-size:12px;"></i>
    Menampilkan <strong><?= count($riwayat) ?></strong> hasil
  </div>

  <?php if ($riwayat): ?>
  <div id="riwayatList">
    <?php foreach ($riwayat as $idx => $r):
      $gw   = $genre_warna[$r['genre']] ?? ['bg' => '#1e3a5f', 'icon' => 'fa-book'];
      $sisa = sisaHari($r['tanggal_expired']);
      $is_aktif = in_array($r['status'], ['aktif', 'hampir_habis']);
      if ($sisa <= 1)     $sisa_color = '#dc2626';
      elseif ($sisa <= 3) $sisa_color = '#d97706';
      else                $sisa_color = '#16a34a';
    ?>
    <div class="riwayat-item fu" style="animation-delay:<?= ($idx * 0.04 + 0.1) ?>s;"
         data-judul="<?= strtolower(e($r['judul'])) ?>"
         data-penulis="<?= strtolower(e($r['penulis'])) ?>">

      <!-- FIX 2: cover buku -->
      <div class="riwayat-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
        <?php if(!empty($r['cover'])): ?>
          <img src="<?= BASE_URL ?>/uploads/covers/<?= e($r['cover']) ?>"
               style="width:100%;height:100%;object-fit:contain;" alt="">
        <?php else: ?>
          <i class="fas <?= $gw['icon'] ?>"></i>
        <?php endif; ?>
      </div>

      <div class="riwayat-info">
        <div class="riwayat-genre-tag"><?= e($r['genre']) ?></div>
        <div class="riwayat-title"><?= e($r['judul']) ?></div>
        <div class="riwayat-author"><i class="fas fa-pen-nib" style="font-size:9px;margin-right:3px;"></i><?= e($r['penulis']) ?></div>
        <div class="riwayat-badge-row">
          <?php
          $status_map = [
            'aktif'        => ['class' => 'status-aktif',        'icon' => 'fa-circle',           'label' => 'Aktif'],
            'hampir_habis' => ['class' => 'status-hampir_habis', 'icon' => 'fa-exclamation-circle','label' => 'Hampir Habis'],
            'expired'      => ['class' => 'status-expired',      'icon' => 'fa-times-circle',      'label' => 'Expired'],
            'dikembalikan' => ['class' => 'status-dikembalikan', 'icon' => 'fa-check-circle',      'label' => 'Selesai'],
          ];
          $sm = $status_map[$r['status']] ?? ['class'=>'status-dikembalikan','icon'=>'fa-circle','label'=>$r['status']];
          ?>
          <span class="status-badge <?= $sm['class'] ?>"><i class="fas <?= $sm['icon'] ?>" style="font-size:8px;"></i> <?= $sm['label'] ?></span>
          <?php if ($r['diperpanjang']): ?><span class="badge-perpanjang"><i class="fas fa-redo" style="font-size:9px;"></i> Diperpanjang</span><?php endif; ?>
          <?php if ($r['sudah_review']): ?><span class="badge-reviewed"><i class="fas fa-star" style="font-size:9px;"></i> Sudah Diulas</span><?php endif; ?>
        </div>
      </div>

      <div class="riwayat-dates">
        <div class="riwayat-date-row">
          <i class="fas fa-calendar-plus" style="color:var(--d3);"></i>
          Pinjam: <strong><?= formatTanggal($r['tanggal_pinjam']) ?></strong>
        </div>
        <div class="riwayat-date-row">
          <i class="fas fa-hourglass-half" style="color:<?= $is_aktif && $sisa<=3 ? '#d97706' : 'var(--muted)' ?>;"></i>
          Expired: <strong><?= formatTanggal($r['tanggal_expired']) ?></strong>
        </div>
        <?php if ($is_aktif): ?>
          <div class="sisa-hari" style="background:<?= $sisa_color ?>18;color:<?= $sisa_color ?>;"><i class="fas fa-stopwatch" style="font-size:10px;"></i><?= max($sisa, 0) ?> hari lagi</div>
        <?php endif; ?>
      </div>

      <div class="riwayat-actions">
        <?php if ($is_aktif): ?>
          <a href="<?= BASE_URL ?>/mahasiswa/baca.php?id=<?= $r['buku_id'] ?>" class="btn-baca-r"><i class="fas fa-book-open"></i> Baca</a>
          <?php if (!$r['diperpanjang']): ?><a href="<?= BASE_URL ?>/mahasiswa/perpanjang.php?id=<?= $r['id'] ?>" class="btn-perpanjang-r" title="Perpanjang"><i class="fas fa-redo"></i></a><?php endif; ?>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $r['buku_id'] ?>" class="btn-lihat-r"><i class="fas fa-eye"></i> Lihat</a>
        <?php endif; ?>
        <?php if (!$r['sudah_review'] && in_array($r['status'], ['expired','dikembalikan'])): ?>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $r['buku_id'] ?>#review" class="btn-review-r"><i class="fas fa-star"></i> Review</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <div class="empty-riwayat fu">
    <div class="empty-icon"><i class="fas fa-history"></i></div>
    <h3>Belum Ada Riwayat</h3>
    <p>Kamu belum pernah meminjam buku.<br>Mulai jelajahi koleksi dan pinjam buku favoritmu!</p>
    <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn-primary-r"><i class="fas fa-book"></i> Mulai Pinjam Buku</a>
  </div>
  <?php endif; ?>

</div>

<footer class="mhs-footer">
  <i class="fas fa-cloud" style="color:var(--d2);margin-right:6px;"></i>
  <strong style="color:var(--d2);">CloudLibrary Mini</strong> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
function filterLive() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#riwayatList .riwayat-item').forEach(el => {
    const match = el.dataset.judul.includes(q) || el.dataset.penulis.includes(q);
    el.style.display = match ? '' : 'none';
  });
}
function applyFilter(key, val) {
  const params = new URLSearchParams(window.location.search);
  if (val) params.set(key, val); else params.delete(key);
  window.location.search = params.toString();
}
</script>
</body>
</html>