<?php
// ============================================
//  CloudLibrary Mini — Admin: Moderasi Review
//  File   : admin/review/index.php
// ============================================
session_start();
require_once '../../includes/functions.php';
cekLoginAdmin();

// --- Aksi POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $rid  = (int)($_POST['review_id'] ?? 0);

    if ($aksi === 'approve' && $rid) {
        $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$rid]);
    }
    if ($aksi === 'reject' && $rid) {
        $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$rid]);
    }
    if ($aksi === 'hapus' && $rid) {
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$rid]);
    }
    if ($aksi === 'approve_all') {
        $pdo->query("UPDATE reviews SET status = 'approved' WHERE status = 'pending'");
    }

    header('Location: index.php?' . http_build_query($_GET));
    exit;
}

// --- Filter ---
$status = $_GET['status'] ?? 'pending';
$rating = $_GET['rating'] ?? '';
$search = trim($_GET['q']  ?? '');
$sort   = $_GET['sort']   ?? 'terbaru';

$where  = ["1=1"];
$params = [];

if ($status) { $where[] = "r.status = ?";  $params[] = $status; }
if ($rating) { $where[] = "r.rating = ?";  $params[] = $rating; }
if ($search) {
    $where[]  = "(u.nama LIKE ? OR b.judul LIKE ? OR r.komentar LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$order = match($sort) {
    'rating_asc'  => 'r.rating ASC',
    'rating_desc' => 'r.rating DESC',
    'nama'        => 'u.nama ASC',
    default       => 'r.created_at DESC',
};

$stmt = $pdo->prepare("
    SELECT r.*, u.nama AS nama_user, b.judul, b.genre
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN buku  b ON r.buku_id = b.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $order
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Statistik
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        ROUND(AVG(rating), 1) AS avg_rating
    FROM reviews
")->fetch();

// Distribusi rating
$dist_ratings = $pdo->query("
    SELECT rating, COUNT(*) AS total FROM reviews GROUP BY rating ORDER BY rating DESC
")->fetchAll();

$genre_icon = [
    'Novel'    => 'fa-book',
    'Cerpen'   => 'fa-scroll',
    'Fantasi'  => 'fa-hat-wizard',
    'Romance'  => 'fa-heart',
    'Horror'   => 'fa-ghost',
    'Misteri'  => 'fa-user-secret',
    'Sci-Fi'   => 'fa-rocket',
    'Filsafat' => 'fa-landmark',
    'Sains'    => 'fa-microscope',
    'Biografi' => 'fa-feather-alt',
];

$title = "Moderasi Review — Admin CloudLibrary Mini";
include '../../includes/navbar.php';
?>

<style>
/* ── FULL PAGE LIBRARY BACKGROUND ── */
body {
  background-image: url('library_bg.png') !important;
  background-size: cover !important;
  background-position: center !important;
  background-attachment: fixed !important;
  background-repeat: no-repeat !important;
  position: relative;
}
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background: rgba(8, 16, 40, 0.72);
  z-index: 0;
  pointer-events: none;
}
.content-wrapper, .container, main, [class*="content"] {
  position: relative;
  z-index: 1;
  background: transparent !important;
}

/* ── GLASS VARIABLES ── */
:root {
  --glass: rgba(255,255,255,0.07);
  --glass-border: rgba(255,255,255,0.14);
  --glass-hover: rgba(255,255,255,0.12);
  --gold: #f9c74f;
  --gold2: #e6a817;
  --text-w: rgba(255,255,255,0.92);
  --text-m: rgba(255,255,255,0.55);
  --success-c: #4ade80;
  --warning-c: #facc15;
  --danger-c:  #f87171;
}

/* ── PAGE HEADER ── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 22px;
  position: relative;
  z-index: 1;
  background: rgba(255,255,255,0.07) !important;
  border: 1px solid rgba(255,255,255,0.14) !important;
  backdrop-filter: blur(18px);
  border-radius: 16px;
  padding: 16px 22px;
}
.page-header h2 {
  font-family: 'Syne', sans-serif;
  font-size: 20px;
  font-weight: 900;
  color: var(--gold) !important;
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}
.page-header h2 i { color: var(--gold); }
.ph-meta { font-size: 13px; color: var(--text-m); }

/* ── STAT CARDS ── */
.stat-row {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-bottom: 22px;
  position: relative;
  z-index: 1;
}
@media(max-width:900px){ .stat-row{ grid-template-columns: repeat(3,1fr); } }
@media(max-width:580px){ .stat-row{ grid-template-columns: repeat(2,1fr); } }

.stat-mini {
  background: var(--glass);
  border: 1px solid var(--glass-border);
  border-radius: 14px;
  padding: 16px;
  text-align: center;
  text-decoration: none;
  color: var(--text-w);
  backdrop-filter: blur(18px);
  transition: all 0.2s;
  position: relative;
  overflow: hidden;
}
.stat-mini::before {
  content: '';
  position: absolute;
  top: 0; left: 10px; right: 10px; height: 1px;
  background: linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent);
}
.stat-mini:hover {
  background: var(--glass-hover);
  transform: translateY(-2px);
}
.stat-mini .svg-icon {
  width: 38px; height: 38px;
  margin: 0 auto 8px;
}
.stat-mini .num {
  font-family: 'Syne', sans-serif;
  font-size: 24px;
  font-weight: 900;
  color: var(--gold);
}
.stat-mini .lbl {
  font-size: 11px;
  color: var(--text-m);
  margin-top: 3px;
  font-weight: 600;
}

/* ── DISTRIBUSI RATING ── */
.dist-card {
  background: var(--glass);
  border: 1px solid var(--glass-border);
  border-radius: 16px;
  padding: 18px 22px;
  margin-bottom: 20px;
  backdrop-filter: blur(18px);
  position: relative;
  z-index: 1;
}
.dist-card .sec-title {
  font-size: 11px;
  font-weight: 800;
  color: var(--text-m);
  text-transform: uppercase;
  letter-spacing: 0.7px;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.dist-card .sec-title i { color: var(--gold); }
.dist-bar-wrap {
  background: rgba(255,255,255,0.08);
  border-radius: 6px;
  height: 7px;
  flex: 1;
  overflow: hidden;
}
.dist-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--gold2), var(--gold));
  border-radius: 6px;
  transition: width 0.5s ease;
}
.star-text { color: var(--gold); font-size: 12px; width: 64px; white-space: nowrap; }

/* ── FILTER BAR ── */
.filter-form {
  background: var(--glass);
  border: 1px solid var(--glass-border);
  border-radius: 14px;
  padding: 14px 18px;
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 12px;
  backdrop-filter: blur(18px);
  position: relative;
  z-index: 1;
}
.filter-form input[type="text"] {
  flex: 1;
  min-width: 200px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.14);
  border-radius: 10px;
  padding: 9px 14px;
  font-size: 13px;
  color: var(--text-w);
  font-family: 'Nunito', sans-serif;
  outline: none;
}
.filter-form input[type="text"]::placeholder { color: var(--text-m); }
.filter-form input[type="text"]:focus {
  border-color: rgba(249,199,79,0.4);
  box-shadow: 0 0 0 3px rgba(249,199,79,0.08);
}

/* ── TABS ── */
.status-tabs, .sort-tabs, .rating-pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 10px;
  position: relative;
  z-index: 1;
}
.status-tab, .sort-tab, .rating-pill {
  padding: 6px 14px;
  border-radius: 100px;
  font-size: 12px;
  font-weight: 700;
  border: 1px solid rgba(255,255,255,0.14);
  color: var(--text-m);
  text-decoration: none;
  background: var(--glass);
  backdrop-filter: blur(10px);
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.status-tab:hover, .sort-tab:hover, .rating-pill:hover {
  border-color: rgba(249,199,79,0.4);
  color: var(--gold);
}
.status-tab.active, .sort-tab.active, .rating-pill.active {
  background: rgba(249,199,79,0.12);
  color: var(--gold);
  border-color: rgba(249,199,79,0.35);
}

/* ── BULK BAR ── */
.bulk-bar {
  background: rgba(250,204,21,0.08);
  border: 1px solid rgba(250,204,21,0.25);
  border-radius: 12px;
  padding: 12px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  font-size: 13px;
  color: var(--text-w);
  backdrop-filter: blur(10px);
  position: relative;
  z-index: 1;
}
.btn-approve-all {
  background: linear-gradient(135deg, #16a34a, #4ade80);
  color: #fff;
  border: none;
  border-radius: 100px;
  padding: 7px 18px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  font-family: 'Nunito', sans-serif;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s;
}
.btn-approve-all:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(74,222,128,0.3); }

/* ── REVIEW CARDS ── */
.review-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  z-index: 1;
}
.review-card {
  background: var(--glass);
  border: 1px solid var(--glass-border);
  border-radius: 16px;
  padding: 18px 20px;
  backdrop-filter: blur(18px);
  transition: all 0.2s;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 16px;
  align-items: start;
}
.review-card:hover { background: var(--glass-hover); }
.review-card.pending  { border-left: 3px solid var(--warning-c); }
.review-card.approved { border-left: 3px solid var(--success-c); }
.review-card.rejected { border-left: 3px solid var(--danger-c); opacity: 0.75; }

/* Avatar */
.avatar-sm {
  width: 36px; height: 36px;
  border-radius: 50%;
  flex-shrink: 0;
  background: linear-gradient(135deg, var(--gold2), var(--gold));
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Syne', sans-serif;
  font-size: 14px;
  font-weight: 900;
  color: #1a1a1a;
}

/* Stars */
.stars { color: var(--gold); font-size: 13px; letter-spacing: 1px; }
.stars-empty { color: rgba(255,255,255,0.2); }

/* Status badge */
.status-badge {
  font-size: 10px;
  font-weight: 800;
  padding: 4px 11px;
  border-radius: 100px;
  text-transform: uppercase;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.badge-pending  { background: rgba(250,204,21,0.12); color: var(--warning-c); border: 1px solid rgba(250,204,21,0.3); }
.badge-approved { background: rgba(74,222,128,0.12); color: var(--success-c); border: 1px solid rgba(74,222,128,0.3); }
.badge-rejected { background: rgba(248,113,113,0.12); color: var(--danger-c); border: 1px solid rgba(248,113,113,0.3); }

/* Komentar box */
.komentar-box {
  background: rgba(255,255,255,0.05);
  border-radius: 10px;
  padding: 12px 14px;
  font-size: 13px;
  line-height: 1.7;
  color: var(--text-w);
  border-left: 3px solid rgba(249,199,79,0.35);
  margin-bottom: 10px;
  font-style: italic;
}

/* Action buttons */
.action-row { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
.btn-xs {
  font-size: 11px;
  font-weight: 700;
  padding: 5px 13px;
  border-radius: 100px;
  border: 1px solid rgba(255,255,255,0.14);
  background: rgba(255,255,255,0.07);
  color: var(--text-m);
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: 'Nunito', sans-serif;
}
.btn-xs.approve { border-color: rgba(74,222,128,0.3); color: var(--success-c); }
.btn-xs.approve:hover { background: rgba(74,222,128,0.12); }
.btn-xs.reject  { border-color: rgba(250,204,21,0.3);  color: var(--warning-c); }
.btn-xs.reject:hover  { background: rgba(250,204,21,0.08); }
.btn-xs.hapus   { border-color: rgba(248,113,113,0.3); color: var(--danger-c); }
.btn-xs.hapus:hover   { background: rgba(248,113,113,0.08); }

/* Result count */
.result-count {
  font-size: 13px;
  color: var(--text-m);
  margin-bottom: 12px;
  position: relative;
  z-index: 1;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--glass);
  border: 1px solid var(--glass-border);
  border-radius: 16px;
  backdrop-filter: blur(18px);
  position: relative;
  z-index: 1;
  color: var(--text-m);
}
.empty-state i { font-size: 42px; color: var(--gold); opacity: 0.5; margin-bottom: 14px; display: block; }
.empty-state p { font-size: 14px; font-weight: 600; }

/* Footer */
.footer {
  position: relative;
  z-index: 1;
  background: var(--glass) !important;
  border-top: 1px solid var(--glass-border) !important;
  color: var(--text-m) !important;
  backdrop-filter: blur(18px);
}
.footer span { color: var(--gold) !important; }
</style>

<!-- PAGE HEADER -->
<div class="page-header">
  <h2>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
      <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="#f9c74f" stroke-width="1.8" stroke-linejoin="round" fill="rgba(249,199,79,0.18)"/>
    </svg>
    Moderasi Review
  </h2>
  <span class="ph-meta">
    <?= $stats['total'] ?> total review &middot; Rata-rata
    <span style="color:var(--gold);font-weight:700;"><?= $stats['avg_rating'] ?></span>
    <svg width="13" height="13" viewBox="0 0 24 24" style="vertical-align:middle;margin-left:2px;">
      <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="#f9c74f"/>
    </svg>
  </span>
</div>

<!-- STAT CARDS -->
<div class="stat-row">

  <!-- Total -->
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>" class="stat-mini"
     style="<?= $status === '' ? 'border-color:rgba(249,199,79,0.45);' : '' ?>">
    <svg class="svg-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="8" y="10" width="32" height="28" rx="4" stroke="#f9c74f" stroke-width="2" fill="rgba(249,199,79,0.1)"/>
      <line x1="14" y1="18" x2="34" y2="18" stroke="#f9c74f" stroke-width="2" stroke-linecap="round"/>
      <line x1="14" y1="24" x2="34" y2="24" stroke="#f9c74f" stroke-width="2" stroke-linecap="round"/>
      <line x1="14" y1="30" x2="26" y2="30" stroke="#f9c74f" stroke-width="2" stroke-linecap="round"/>
      <polygon points="30,27 36,30 30,33" fill="#f9c74f" opacity="0.7"/>
    </svg>
    <div class="num"><?= $stats['total'] ?></div>
    <div class="lbl">Semua Review</div>
  </a>

  <!-- Pending -->
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'pending'])) ?>" class="stat-mini"
     style="<?= $status === 'pending' ? 'border-color:rgba(250,204,21,0.45);' : '' ?>">
    <svg class="svg-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="24" cy="24" r="14" stroke="#facc15" stroke-width="2" fill="rgba(250,204,21,0.1)"/>
      <line x1="24" y1="14" x2="24" y2="24" stroke="#facc15" stroke-width="2.5" stroke-linecap="round"/>
      <line x1="24" y1="24" x2="30" y2="28" stroke="#facc15" stroke-width="2.5" stroke-linecap="round"/>
      <circle cx="24" cy="24" r="2" fill="#facc15"/>
    </svg>
    <div class="num" style="color:#facc15;"><?= $stats['pending'] ?></div>
    <div class="lbl">Menunggu</div>
  </a>

  <!-- Approved -->
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'approved'])) ?>" class="stat-mini"
     style="<?= $status === 'approved' ? 'border-color:rgba(74,222,128,0.45);' : '' ?>">
    <svg class="svg-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="24" cy="24" r="14" stroke="#4ade80" stroke-width="2" fill="rgba(74,222,128,0.1)"/>
      <circle cx="24" cy="24" r="9" stroke="#4ade80" stroke-width="1.2" stroke-dasharray="3 2" fill="none" opacity="0.5"/>
      <polyline points="17,24 22,29 31,19" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div class="num" style="color:#4ade80;"><?= $stats['approved'] ?></div>
    <div class="lbl">Disetujui</div>
  </a>

  <!-- Rejected -->
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'rejected'])) ?>" class="stat-mini"
     style="<?= $status === 'rejected' ? 'border-color:rgba(248,113,113,0.45);' : '' ?>">
    <svg class="svg-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="24" cy="24" r="14" stroke="#f87171" stroke-width="2" fill="rgba(248,113,113,0.1)"/>
      <line x1="18" y1="18" x2="30" y2="30" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
      <line x1="30" y1="18" x2="18" y2="30" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
    </svg>
    <div class="num" style="color:#f87171;"><?= $stats['rejected'] ?></div>
    <div class="lbl">Ditolak</div>
  </a>

  <!-- Avg Rating -->
  <a href="?<?= http_build_query($_GET) ?>" class="stat-mini">
    <svg class="svg-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <polygon points="24,6 28.6,17.6 41,18.8 32,27 34.9,39.4 24,33 13.1,39.4 16,27 7,18.8 19.4,17.6" stroke="#f9c74f" stroke-width="1.8" stroke-linejoin="round" fill="rgba(249,199,79,0.25)"/>
      <circle cx="24" cy="24" r="5" fill="#f9c74f" opacity="0.5"/>
    </svg>
    <div class="num"><?= $stats['avg_rating'] ?></div>
    <div class="lbl">Avg Rating</div>
  </a>

</div>

<!-- DISTRIBUSI RATING -->
<div class="dist-card">
  <div class="sec-title">
    <i class="fas fa-chart-bar"></i> Distribusi Rating
  </div>
  <?php
  $total_rev = max($stats['total'], 1);
  $dist_map  = array_column($dist_ratings, 'total', 'rating');
  for ($r = 5; $r >= 1; $r--):
    $cnt = $dist_map[$r] ?? 0;
    $pct = round($cnt / $total_rev * 100);
  ?>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:9px;">
    <span class="star-text"><?= str_repeat('★', $r) ?><?= str_repeat('☆', 5-$r) ?></span>
    <div class="dist-bar-wrap">
      <div class="dist-bar-fill" style="width:<?= $pct ?>%;"></div>
    </div>
    <span style="font-size:11px;color:var(--text-m);width:28px;text-align:right;font-weight:700;"><?= $cnt ?></span>
  </div>
  <?php endfor; ?>
</div>

<!-- FILTER BAR -->
<div class="filter-form" style="margin-bottom:12px;">
  <input type="text" id="searchInput" placeholder="Cari nama user, judul buku, atau komentar..."
         value="<?= e($search) ?>" oninput="filterLive()">
  <div class="sort-tabs">
    <?php foreach ([
      'terbaru'     => ['fa-clock', 'Terbaru'],
      'rating_desc' => ['fa-sort-amount-down', 'Rating &darr;'],
      'rating_asc'  => ['fa-sort-amount-up', 'Rating &uarr;'],
      'nama'        => ['fa-user', 'Nama'],
    ] as $k => [$ico, $lbl]): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort' => $k])) ?>"
         class="sort-tab <?= $sort === $k ? 'active' : '' ?>">
        <i class="fas <?= $ico ?>"></i> <?= $lbl ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- STATUS TABS -->
<div class="status-tabs">
  <?php foreach ([
    'pending'  => ['fa-clock', 'Menunggu (' . $stats['pending'] . ')'],
    ''         => ['fa-list', 'Semua'],
    'approved' => ['fa-check-circle', 'Disetujui'],
    'rejected' => ['fa-times-circle', 'Ditolak'],
  ] as $sv => [$ico, $sl]): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['status' => $sv])) ?>"
       class="status-tab <?= $status === $sv ? 'active' : '' ?>">
      <i class="fas <?= $ico ?>"></i> <?= $sl ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- FILTER RATING PILLS -->
<div class="rating-pills">
  <a href="?<?= http_build_query(array_merge($_GET, ['rating' => ''])) ?>"
     class="rating-pill <?= $rating === '' ? 'active' : '' ?>">
    <i class="fas fa-star"></i> Semua
  </a>
  <?php for ($r = 5; $r >= 1; $r--): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['rating' => $r])) ?>"
     class="rating-pill <?= (string)$rating === (string)$r ? 'active' : '' ?>">
    <?= $r ?> <i class="fas fa-star" style="font-size:10px;"></i>
  </a>
  <?php endfor; ?>
</div>

<!-- BULK ACTION -->
<?php if ($stats['pending'] > 0 && ($status === 'pending' || $status === '')): ?>
<div class="bulk-bar">
  <span>
    <i class="fas fa-exclamation-triangle" style="color:var(--warning-c);margin-right:6px;"></i>
    <strong><?= $stats['pending'] ?></strong> review menunggu moderasi
  </span>
  <form method="POST" style="display:inline;">
    <input type="hidden" name="aksi" value="approve_all">
    <button type="submit" class="btn-approve-all"
            onclick="return confirm('Setujui semua review pending sekaligus?')">
      <i class="fas fa-check-double"></i> Setujui Semua Pending
    </button>
  </form>
</div>
<?php endif; ?>

<!-- RESULT COUNT -->
<div class="result-count">
  Menampilkan <strong id="resultCount" style="color:var(--gold);"><?= count($reviews) ?></strong> review
</div>

<!-- REVIEW LIST -->
<?php if ($reviews): ?>
<div class="review-list" id="reviewList">
  <?php foreach ($reviews as $rv):
    $gico = $genre_icon[$rv['genre']] ?? 'fa-book';
    $init = strtoupper(substr($rv['nama_user'], 0, 1));
    $stars_filled = str_repeat('★', $rv['rating']);
    $stars_empty  = str_repeat('☆', 5 - $rv['rating']);
  ?>
  <div class="review-card <?= $rv['status'] ?>"
       data-nama="<?= strtolower($rv['nama_user']) ?>"
       data-judul="<?= strtolower($rv['judul']) ?>"
       data-komentar="<?= strtolower($rv['komentar']) ?>">

    <!-- KIRI: konten review -->
    <div>
      <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;">
        <div class="avatar-sm"><?= $init ?></div>
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-weight:800;font-size:13px;color:var(--text-w);"><?= e($rv['nama_user']) ?></span>
            <span style="color:var(--text-m);font-size:11px;">menilai</span>
            <div style="display:flex;align-items:center;gap:5px;">
              <i class="fas <?= $gico ?>" style="color:var(--gold);font-size:12px;"></i>
              <span style="font-size:12px;font-weight:700;color:var(--text-w);"><?= e($rv['judul']) ?></span>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:5px;">
            <span class="stars"><?= $stars_filled ?><span class="stars-empty"><?= $stars_empty ?></span></span>
            <span style="font-family:'Syne',sans-serif;font-size:13px;font-weight:900;color:var(--gold);">
              <?= $rv['rating'] ?>/5
            </span>
          </div>
        </div>
      </div>

      <!-- Komentar -->
      <?php if ($rv['komentar']): ?>
      <div class="komentar-box">"<?= e($rv['komentar']) ?>"</div>
      <?php else: ?>
      <div style="font-size:12px;color:var(--text-m);font-style:italic;margin-bottom:10px;">
        (Tidak ada komentar)
      </div>
      <?php endif; ?>

      <!-- Meta -->
      <div style="font-size:11px;color:var(--text-m);display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span><i class="fas fa-calendar-alt" style="margin-right:4px;"></i><?= formatTanggal($rv['created_at']) ?></span>
        <span><i class="fas fa-tag" style="margin-right:4px;color:var(--gold);"></i><?= e($rv['genre']) ?></span>
      </div>

      <!-- Tombol aksi -->
      <div class="action-row">
        <?php if ($rv['status'] === 'pending'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="aksi" value="approve">
            <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
            <button type="submit" class="btn-xs approve">
              <i class="fas fa-check"></i> Setujui
            </button>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="aksi" value="reject">
            <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
            <button type="submit" class="btn-xs reject">
              <i class="fas fa-times"></i> Tolak
            </button>
          </form>
        <?php elseif ($rv['status'] === 'approved'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="aksi" value="reject">
            <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
            <button type="submit" class="btn-xs reject">
              <i class="fas fa-times"></i> Tolak
            </button>
          </form>
        <?php elseif ($rv['status'] === 'rejected'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="aksi" value="approve">
            <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
            <button type="submit" class="btn-xs approve">
              <i class="fas fa-check"></i> Setujui Ulang
            </button>
          </form>
        <?php endif; ?>
        <form method="POST" style="display:inline;"
              onsubmit="return confirm('Hapus review ini permanen?')">
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
          <button type="submit" class="btn-xs hapus">
            <i class="fas fa-trash-alt"></i> Hapus
          </button>
        </form>
      </div>
    </div>

    <!-- KANAN: status badge -->
    <div style="text-align:right;">
      <?php
      $badge = [
        'pending'  => ['fa-clock', 'Pending'],
        'approved' => ['fa-check', 'Approved'],
        'rejected' => ['fa-times', 'Rejected'],
      ];
      [$bico, $blbl] = $badge[$rv['status']] ?? ['fa-circle', $rv['status']];
      ?>
      <span class="status-badge badge-<?= $rv['status'] ?>">
        <i class="fas <?= $bico ?>"></i> <?= $blbl ?>
      </span>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<div class="empty-state">
  <i class="fas fa-star-half-alt"></i>
  <p>Tidak ada review<?= $status ? " dengan status \"$status\"" : '' ?>.</p>
</div>
<?php endif; ?>

<div style="height:40px;"></div>

<footer class="footer">
  <p><i class="fas fa-cloud" style="color:var(--gold);margin-right:6px;"></i>
    <span>CloudLibrary Mini</span> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?></p>
</footer>

<script>
function filterLive() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  let visible = 0;
  document.querySelectorAll('#reviewList .review-card').forEach(card => {
    const match = card.dataset.nama.includes(q)
                || card.dataset.judul.includes(q)
                || card.dataset.komentar.includes(q);
    card.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  document.getElementById('resultCount').textContent = visible;
}
</script>
</body>
</html>