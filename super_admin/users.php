<?php
// ============================================
//  CloudLibrary Mini — Super Admin: Kelola User
//  File   : super_admin/users.php
// ============================================
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: '.BASE_URL.'/auth/login.php'); exit;
}

$success = '';
$error   = '';

// --- Aksi POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $uid  = (int)($_POST['user_id'] ?? 0);

    if ($aksi === 'toggle_status' && $uid) {
        $cur = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'mahasiswa'");
        $cur->execute([$uid]);
        $row = $cur->fetch();
        if ($row) {
            $new = $row['status'] === 'aktif' ? 'nonaktif' : 'aktif';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new, $uid]);
            $success = $new === 'aktif' ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.';
        }
    }

    if ($aksi === 'reset_password' && $uid) {
        $hash = password_hash('mahasiswa123', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
        $success = 'Password direset ke mahasiswa123.';
    }

    if ($aksi === 'reset_poin' && $uid) {
        $pdo->prepare("UPDATE users SET poin = 0 WHERE id = ?")->execute([$uid]);
        $success = 'Poin user berhasil direset ke 0.';
    }

    if ($aksi === 'hapus' && $uid) {
        $pdo->prepare("UPDATE users SET status = 'dihapus' WHERE id = ? AND role = 'mahasiswa'")->execute([$uid]);
        $success = 'Akun mahasiswa berhasil dihapus.';
    }

    if (!$error) {
        header('Location: users.php?success=' . urlencode($success));
        exit;
    }
}

if (isset($_GET['success'])) $success = $_GET['success'];

// --- Filter ---
$search = trim($_GET['q']     ?? '');
$status = $_GET['status']     ?? '';
$sort   = $_GET['sort']       ?? 'terbaru';
$level  = $_GET['level']      ?? '';

$where  = ["u.role = 'mahasiswa'"];
$params = [];

if ($search) {
    $where[]  = "(u.nama LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status) {
    $where[]  = "u.status = ?";
    $params[] = $status;
}

$order = match($sort) {
    'nama'   => 'u.nama ASC',
    'poin'   => 'u.poin DESC',
    'pinjam' => 'total_pinjam DESC',
    default  => 'u.created_at DESC',
};

$stmt = $pdo->prepare("
    SELECT u.*,
           COUNT(DISTINCT p.id) AS total_pinjam,
           COUNT(DISTINCT r.id) AS total_review,
           SUM(CASE WHEN p.status IN ('aktif','hampir_habis') THEN 1 ELSE 0 END) AS pinjam_aktif
    FROM users u
    LEFT JOIN peminjaman p ON p.user_id = u.id
    LEFT JOIN reviews    r ON r.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY u.id
    ORDER BY $order
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Filter level di PHP
function getLevel(int $poin): array {
    return match(true) {
        $poin >= 500 => ['Legenda', 'fa-crown',        '#d4a017'],
        $poin >= 200 => ['Master',  'fa-gem',           '#8b5cf6'],
        $poin >= 100 => ['Ahli',    'fa-star',          '#c2185b'],
        $poin >= 50  => ['Aktif',   'fa-book-open',     '#2e7d32'],
        $poin >= 10  => ['Pemula',  'fa-seedling',      '#9c6b7a'],
        default      => ['Baru',    'fa-user',          '#bca5af'],
    };
}

if ($level) {
    $users = array_filter($users, fn($u) => getLevel((int)$u['poin'])[0] === $level);
    $users = array_values($users);
}

// Statistik
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'aktif'    THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) AS nonaktif,
        SUM(CASE WHEN status = 'dihapus'  THEN 1 ELSE 0 END) AS dihapus,
        SUM(poin) AS total_poin,
        ROUND(AVG(poin), 0) AS avg_poin
    FROM users WHERE role = 'mahasiswa'
")->fetch();

$title = "Kelola User — Super Admin CloudLibrary Mini";
include '../includes/navbar.php';
?>

<style>
/* -- BACKGROUND FOTO PERPUSTAKAAN -- */
body {
  font-family: 'Nunito', sans-serif;
  min-height: 100vh;
  overflow-x: hidden;
  position: relative;
  margin: 0;
  background: #dce8f5;
  background-image: url('gambar_library.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  background-repeat: no-repeat;
  color: #1a2744 !important;
}
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background: rgba(235, 243, 252, 0.18);
  z-index: 0;
  pointer-events: none;
}

:root {
  --s1:#3a6186;--s2:#2c4f78;--s3:#5b8fb9;--gold:#d4a017;--gold2:#f9c74f;
  --card:rgba(255,255,255,0.78);--card-b:rgba(255,255,255,0.85);
  --text:#1a2744;--muted:#6b7a99;
  --success:#15803d;--warning:#c2410c;--danger:#b91c1c;
  --sh:0 4px 20px rgba(58,97,134,0.10);--sh-md:0 10px 36px rgba(58,97,134,0.16);
}

.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 22px; position: relative; z-index: 1; flex-wrap:wrap; gap:10px;
}
.page-header h2 {
  font-family: 'Syne', sans-serif;
  font-size: 22px; font-weight: 900; color: var(--s1);
  display: flex; align-items: center; gap: 10px;
}
.page-header h2 i { color: var(--gold); }
.ph-sub {
  font-size: 12px; font-weight: 700; color: var(--muted);
  background: rgba(255,255,255,0.60);
  border: 2px solid rgba(255,255,255,0.85);
  padding: 6px 14px; border-radius: 100px;
  backdrop-filter: blur(20px);
  display:flex;align-items:center;gap:6px;
}

.alert-box {
  border-radius: 14px; padding: 13px 18px; margin-bottom: 18px;
  font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 10px;
  backdrop-filter: blur(20px); position: relative; z-index: 1;
}
.alert-success { background: rgba(46,125,50,0.08); border: 1.5px solid rgba(46,125,50,0.24); color: #15803d; }
.alert-error   { background: rgba(198,40,40,0.08); border: 1.5px solid rgba(198,40,40,0.24); color: #b91c1c; }

.stat-row {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
  margin-bottom: 24px; position: relative; z-index: 1;
}
@media(max-width:700px){ .stat-row{ grid-template-columns: repeat(2,1fr); } }

.stat-mini {
  background: var(--card); border: 2px solid var(--card-b);
  border-radius: 16px; padding: 16px;
  text-align: center; text-decoration: none; color: inherit;
  backdrop-filter: blur(20px);
  box-shadow: var(--sh); transition: transform .2s, box-shadow .2s;
  position: relative; overflow: hidden;
  border-top: 3px solid transparent;
}
.stat-mini:hover { transform: translateY(-2px); box-shadow: var(--sh-md); }
.stat-mini .stat-ico {
  width:40px;height:40px;border-radius:12px;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:16px;margin-bottom:8px;
}
.stat-mini .num { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 900; }
.stat-mini .lbl { font-size: 11px; font-weight: 700; color: var(--muted); margin-top: 3px; }

/* INSIGHT CARD */
.insight-card {
  background: linear-gradient(135deg, #2c4f78, #3a6186, #5b8fb9);
  border-radius: 20px; padding: 22px 28px; margin-bottom: 22px;
  position: relative; overflow: hidden;
  box-shadow: 0 10px 36px rgba(58,97,134,0.28);
  border: 1px solid rgba(255,255,255,0.18);
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;
  z-index: 1;
}
.insight-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(244,114,182,0.20), transparent 65%); }
.insight-card::after  { content: ''; position: absolute; inset: 8px; border: 2px dashed rgba(255,255,255,0.10); border-radius: 14px; pointer-events: none; }
.ic-left { position: relative; z-index: 1; }
.ic-eyebrow { font-size: 10px; font-weight: 900; letter-spacing: 2.5px; text-transform: uppercase; color: rgba(253,230,138,0.80); margin-bottom: 6px; display:flex;align-items:center;gap:6px; }
.ic-title { font-family: 'Syne', sans-serif; font-size: clamp(16px,2vw,22px); font-weight: 900; color: #fff; line-height: 1.3; margin-bottom: 4px; }
.ic-title .ig { color: #fde68a; }
.ic-sub { font-size: 11px; color: rgba(255,255,255,0.45); font-weight: 700; }
.ic-pills { display: flex; gap: 8px; flex-wrap: wrap; position: relative; z-index: 1; }
.ic-pill { background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22); border-radius: 12px; padding: 10px 16px; text-align: center; backdrop-filter: blur(6px); min-width: 68px; }
.ic-pill .pn { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 900; color: #fff; line-height: 1; }
.ic-pill .pl { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 700; margin-top: 2px; }

/* FILTER */
.filter-area {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-bottom: 12px; position: relative; z-index: 1;
}
.search-wrap{position:relative;flex:1;min-width:200px;}
.search-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.search-wrap input{
  width:100%;background:rgba(255,255,255,0.82);border:1.5px solid rgba(255,255,255,0.90);
  border-radius:100px;padding:9px 18px 9px 38px;font-size:13px;font-family:'Nunito',sans-serif;
  color:var(--text);outline:none;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:border-color .2s;
}
.search-wrap input:focus{border-color:rgba(58,97,134,0.35);}

.tab-row { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; position: relative; z-index: 1; }
.tab-pill {
  padding: 6px 14px; border-radius: 100px; font-size: 12px; font-weight: 700;
  border: 1.5px solid rgba(255,255,255,0.80); background: rgba(255,255,255,0.55);
  color: var(--muted); text-decoration: none; backdrop-filter: blur(10px); transition: all .18s;
  display:inline-flex;align-items:center;gap:5px;
}
.tab-pill:hover  { border-color: rgba(58,97,134,0.30); color: var(--s1); background: rgba(255,255,255,0.78); }
.tab-pill.active { background: rgba(58,97,134,0.10); color: var(--s1); border-color: rgba(58,97,134,0.30); }
.tab-pill i{font-size:10px;}
.tab-pill.gold-tab.active { background: rgba(212,160,23,0.12); color: var(--gold); border-color: rgba(212,160,23,0.32); }
.tab-pill.gold-tab:hover  { border-color: rgba(212,160,23,0.30); color: var(--gold); }

.result-count { font-size: 13px; color: var(--muted); font-weight: 700; margin-bottom: 14px; position: relative; z-index: 1; }

/* USER GRID */
.user-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(288px, 1fr));
  gap: 16px; position: relative; z-index: 1;
}

.user-card {
  background: var(--card); border: 2px solid var(--card-b);
  border-radius: 20px; padding: 22px;
  backdrop-filter: blur(20px);
  box-shadow: var(--sh);
  transition: transform .2s, box-shadow .2s, border-color .2s;
  position: relative; overflow: hidden;
}
.user-card:hover { transform: translateY(-3px); box-shadow: var(--sh-md); }
.user-card.nonaktif { opacity: .65; }
.user-card.dihapus  { opacity: .42; border-color: rgba(185,28,28,0.22); }

.user-avatar {
  width: 50px; height: 50px; border-radius: 50%;
  background: linear-gradient(135deg, var(--s1), var(--s3));
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-size: 19px; font-weight: 900; color: #fff;
  flex-shrink: 0; box-shadow: 0 3px 12px rgba(58,97,134,0.30);
}

.status-pill {
  font-size: 10px; font-weight: 800; padding: 3px 10px;
  border-radius: 100px; text-transform: uppercase;
  display:inline-flex;align-items:center;gap:4px;
}
.pill-aktif    { background: rgba(21,128,61,0.10);  color: #15803d; border: 1px solid rgba(21,128,61,0.22); }
.pill-nonaktif { background: rgba(194,65,12,0.10);  color: #c2410c; border: 1px solid rgba(194,65,12,0.22); }
.pill-dihapus  { background: rgba(185,28,28,0.10);  color: #b91c1c; border: 1px solid rgba(185,28,28,0.22); }

.card-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 7px; margin: 13px 0; }
.card-stat {
  background: rgba(255,255,255,0.50); border: 1px solid rgba(255,255,255,0.80);
  border-radius: 10px; padding: 8px; text-align: center; backdrop-filter: blur(8px);
}
.card-stat .csn { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 900; color: var(--s1); }
.card-stat .csl { font-size: 9px; color: var(--muted); font-weight: 700; margin-top: 2px; }

.info-row { display: flex; align-items: center; gap: 7px; font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 5px; }
.info-row i { color: var(--s1); font-size: 10px; width: 14px; text-align:center; }

.action-row { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 13px; }
.btn-xs {
  font-size: 11px; font-weight: 800; padding: 5px 12px; border-radius: 100px;
  background: rgba(255,255,255,0.60); backdrop-filter: blur(8px);
  color: var(--text); cursor: pointer; transition: all .18s;
  display:inline-flex;align-items:center;gap:5px;border:1.5px solid transparent;
}
.btn-xs.toggle-off { border-color: rgba(194,65,12,0.28); color: var(--warning); }
.btn-xs.toggle-off:hover { background: rgba(194,65,12,0.10); }
.btn-xs.toggle-on  { border-color: rgba(21,128,61,0.28); color: var(--success); }
.btn-xs.toggle-on:hover  { background: rgba(21,128,61,0.10); }
.btn-xs.reset { border-color: rgba(58,97,134,0.28); color: var(--s1); }
.btn-xs.reset:hover { background: rgba(58,97,134,0.08); }
.btn-xs.poin  { border-color: rgba(212,160,23,0.28); color: var(--gold); }
.btn-xs.poin:hover  { background: rgba(212,160,23,0.08); }
.btn-xs.hapus { border-color: rgba(185,28,28,0.28); color: var(--danger); }
.btn-xs.hapus:hover { background: rgba(185,28,28,0.08); }

.deleted-label{font-size:11px;color:var(--danger);font-weight:800;margin-top:12px;display:flex;align-items:center;gap:5px;}

.empty-state {
  text-align: center; padding: 52px 20px;
  background: var(--card); border: 2px solid var(--card-b);
  border-radius: 20px; backdrop-filter: blur(20px);
  box-shadow: var(--sh); position: relative; z-index: 1;
}
.empty-state i { font-size: 36px; color: var(--muted); display: block; margin-bottom: 10px; }
.empty-state p { font-size: 14px; color: var(--muted); font-weight: 700; }

/* MODAL */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(26,39,68,0.45); backdrop-filter: blur(6px); z-index: 999;
  align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: rgba(255,255,255,0.92); border: 2px solid rgba(255,255,255,0.95);
  border-radius: 22px; padding: 32px 28px; max-width: 380px; width: 90%; text-align: center;
  backdrop-filter: blur(24px);
  box-shadow: 0 24px 60px rgba(58,97,134,0.20);
  position: relative; overflow: hidden;
}
.modal-box::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--s1),var(--s3),var(--gold));}
.modal-box h3 { font-family:'Syne',sans-serif; font-size:20px; font-weight:900; color:var(--s1); margin-bottom:10px; }
.modal-box p  { color:var(--muted); font-size:13px; margin-bottom:22px; line-height:1.7; }
.modal-ico{width:56px;height:56px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px;}
.modal-actions { display:flex; gap:10px; justify-content:center; }
.modal-btn { padding:9px 22px; border-radius:100px; font-size:13px; font-weight:800; cursor:pointer; font-family:'Nunito',sans-serif; transition:all .18s; display:inline-flex;align-items:center;gap:6px; }
.modal-btn.cancel   { background:rgba(255,255,255,0.65); border:1.5px solid rgba(58,97,134,0.20); color:var(--muted); }
.modal-btn.cancel:hover { background:rgba(255,255,255,0.88); }
.modal-btn.confirm  { background:rgba(58,97,134,0.10); border:1.5px solid rgba(58,97,134,0.30); color:var(--s1); }
.modal-btn.confirm:hover { background:rgba(58,97,134,0.18); }
.modal-btn.confirm-warn   { background:rgba(212,160,23,0.10); border:1.5px solid rgba(212,160,23,0.30); color:var(--gold); }
.modal-btn.confirm-warn:hover { background:rgba(212,160,23,0.18); }
.modal-btn.confirm-danger { background:rgba(185,28,28,0.10); border:1.5px solid rgba(185,28,28,0.30); color:var(--danger); }
.modal-btn.confirm-danger:hover { background:rgba(185,28,28,0.18); }

footer.sa-foot {
  position: relative; z-index: 1; text-align: center; padding: 20px;
  font-size: 12px; color: var(--muted); font-weight: 700;
  background: transparent;
  border-top: 1.5px dashed rgba(58,97,134,0.15);
  margin-top: 24px;
}

@keyframes fadeUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
.fu1{animation:fadeUp .4s ease .04s both;}
.fu2{animation:fadeUp .4s ease .12s both;}
.fu3{animation:fadeUp .4s ease .20s both;}
.fu4{animation:fadeUp .4s ease .28s both;}
.fu5{animation:fadeUp .4s ease .36s both;}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <h2><i class="fas fa-users"></i> Kelola User</h2>
  <div class="ph-sub"><i class="fas fa-shield-alt" style="color:var(--s1);"></i> Super Admin &middot; <?= $stats['total'] ?> mahasiswa terdaftar</div>
</div>

<!-- ALERT -->
<?php if ($success): ?>
<div class="alert-box alert-success fu1"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-box alert-error fu1"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stat-row fu2">
  <?php $sc = [
    [''        ,'fa-users',          '#3a6186','rgba(58,97,134,0.12)',  $stats['total'],    'Total'],
    ['aktif'   ,'fa-check-circle',   '#15803d','rgba(21,128,61,0.12)',  $stats['aktif'],    'Aktif'],
    ['nonaktif','fa-ban',            '#c2410c','rgba(194,65,12,0.12)',  $stats['nonaktif'], 'Nonaktif'],
    ['dihapus' ,'fa-trash-alt',      '#b91c1c','rgba(185,28,28,0.12)',  $stats['dihapus'],  'Dihapus'],
    [''        ,'fa-star',           '#d4a017','rgba(212,160,23,0.12)', number_format($stats['total_poin']), 'Total Poin'],
    [''        ,'fa-chart-line',     '#5b8fb9','rgba(91,143,185,0.12)', $stats['avg_poin'], 'Rata Poin'],
  ];
  foreach ($sc as [$sv,$si,$col,$bgc,$num,$lbl]): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => $sv])) ?>"
     class="stat-mini"
     style="border-top-color:<?= $col ?>;<?= ($status===$sv && $sv!=='') ? 'border-color:'.$col.';' : '' ?>">
    <div class="stat-ico" style="background:<?= $bgc ?>;color:<?= $col ?>;"><i class="fas <?= $si ?>"></i></div>
    <div class="num" style="color:<?= $col ?>;"><?= $num ?></div>
    <div class="lbl"><?= $lbl ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- INSIGHT CARD -->
<div class="insight-card fu3">
  <div class="ic-left">
    <div class="ic-eyebrow"><i class="fas fa-chart-pie" style="font-size:9px;"></i> Ringkasan Mahasiswa</div>
    <div class="ic-title">
      <span class="ig"><?= $stats['aktif'] ?> mahasiswa aktif</span> dari total
      <?= $stats['total'] ?> terdaftar
    </div>
    <div class="ic-sub">Update: <?= date('d M Y, H:i') ?> WIB</div>
  </div>
  <div class="ic-pills">
    <div class="ic-pill"><div class="pn"><?= $stats['total'] ?></div><div class="pl">Total</div></div>
    <div class="ic-pill"><div class="pn"><?= $stats['aktif'] ?></div><div class="pl">Aktif</div></div>
    <div class="ic-pill"><div class="pn"><?= number_format($stats['total_poin']) ?></div><div class="pl">Poin</div></div>
    <div class="ic-pill"><div class="pn"><?= $stats['avg_poin'] ?></div><div class="pl">Avg Poin</div></div>
  </div>
</div>

<!-- FILTER -->
<div class="filter-area fu4">
  <div class="search-wrap">
    <i class="fas fa-search"></i>
    <input type="text" id="searchInput"
           placeholder="Cari nama atau email mahasiswa..."
           value="<?= e($search) ?>" oninput="filterLive()">
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <?php foreach (['terbaru'=>['fa-clock','Terbaru'],'nama'=>['fa-sort-alpha-down','Nama A-Z'],'poin'=>['fa-star','Poin'],'pinjam'=>['fa-book','Pinjaman']] as $k=>$v): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort' => $k])) ?>"
         class="tab-pill <?= $sort===$k?'active':'' ?>"><i class="fas <?= $v[0] ?>"></i> <?= $v[1] ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- STATUS TABS -->
<div class="tab-row fu4">
  <?php foreach ([
    ''=>['fa-layer-group','Semua'],
    'aktif'=>['fa-check-circle','Aktif'],
    'nonaktif'=>['fa-ban','Nonaktif'],
    'dihapus'=>['fa-trash-alt','Dihapus']
  ] as $sv=>$sl): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['status' => $sv])) ?>"
       class="tab-pill <?= $status===$sv?'active':'' ?>"><i class="fas <?= $sl[0] ?>"></i> <?= $sl[1] ?></a>
  <?php endforeach; ?>
</div>

<!-- LEVEL FILTER PILLS -->
<div class="tab-row fu4" style="margin-bottom:16px;">
  <a href="?<?= http_build_query(array_merge($_GET, ['level' => ''])) ?>"
     class="tab-pill gold-tab <?= $level===''?'active':'' ?>"><i class="fas fa-layer-group"></i> Semua Level</a>
  <?php foreach (['Legenda'=>'fa-crown','Master'=>'fa-gem','Ahli'=>'fa-star','Aktif'=>'fa-book-open','Pemula'=>'fa-seedling','Baru'=>'fa-user'] as $lv=>$ico): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['level' => $lv])) ?>"
     class="tab-pill gold-tab <?= $level===$lv?'active':'' ?>"><i class="fas <?= $ico ?>"></i> <?= $lv ?></a>
  <?php endforeach; ?>
</div>

<!-- RESULT COUNT -->
<div class="result-count fu5">
  Menampilkan <strong id="resultCount"><?= count($users) ?></strong> mahasiswa
</div>

<!-- USER GRID -->
<?php if ($users): ?>
<div class="user-grid fu5" id="userGrid">
  <?php foreach ($users as $u):
    [$lvl_name, $lvl_icon, $lvl_color] = getLevel((int)$u['poin']);
    $init = strtoupper(substr($u['nama'], 0, 1));
  ?>
  <div class="user-card <?= $u['status'] === 'nonaktif' ? 'nonaktif' : ($u['status'] === 'dihapus' ? 'dihapus' : '') ?>"
       data-nama="<?= strtolower($u['nama']) ?>"
       data-email="<?= strtolower($u['email']) ?>">

    <div style="display:flex;align-items:flex-start;gap:12px;">
      <div class="user-avatar"><?= $init ?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:900;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
          <?= e($u['nama']) ?>
        </div>
        <div style="font-size:11px;color:var(--muted);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">
          <?= e($u['email']) ?>
        </div>
        <div style="display:flex;align-items:center;gap:6px;margin-top:6px;flex-wrap:wrap;">
          <span class="status-pill pill-<?= $u['status'] ?>">
            <?php if($u['status']==='aktif'): ?><i class="fas fa-check-circle" style="font-size:9px;"></i>
            <?php elseif($u['status']==='nonaktif'): ?><i class="fas fa-ban" style="font-size:9px;"></i>
            <?php else: ?><i class="fas fa-trash-alt" style="font-size:9px;"></i>
            <?php endif; ?>
            <?= $u['status'] ?>
          </span>
          <span style="font-size:11px;font-weight:800;color:<?= $lvl_color ?>;display:inline-flex;align-items:center;gap:4px;">
            <i class="fas <?= $lvl_icon ?>" style="font-size:10px;"></i> <?= $lvl_name ?>
          </span>
        </div>
      </div>
    </div>

    <div class="card-stats">
      <div class="card-stat">
        <div class="csn"><?= $u['total_pinjam'] ?></div>
        <div class="csl">Pinjaman</div>
      </div>
      <div class="card-stat">
        <div class="csn"><?= $u['total_review'] ?></div>
        <div class="csl">Review</div>
      </div>
      <div class="card-stat">
        <div class="csn" style="color:var(--gold);"><?= $u['poin'] ?></div>
        <div class="csl">Poin</div>
      </div>
    </div>

    <div class="info-row"><i class="fas fa-calendar-alt"></i> Daftar: <?= formatTanggal($u['created_at']) ?></div>
    <?php if ($u['pinjam_aktif'] > 0): ?>
    <div class="info-row"><i class="fas fa-book-open"></i> <span style="color:var(--success);font-weight:800;"><?= $u['pinjam_aktif'] ?> pinjaman aktif</span></div>
    <?php endif; ?>

    <?php if ($u['status'] !== 'dihapus'): ?>
    <div class="action-row">
      <form method="POST" style="display:inline;">
        <input type="hidden" name="aksi" value="toggle_status">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <?php if ($u['status'] === 'aktif'): ?>
          <button type="submit" class="btn-xs toggle-off"><i class="fas fa-ban"></i> Nonaktifkan</button>
        <?php else: ?>
          <button type="submit" class="btn-xs toggle-on"><i class="fas fa-check-circle"></i> Aktifkan</button>
        <?php endif; ?>
      </form>
      <button class="btn-xs reset" onclick="openModal('reset', <?= $u['id'] ?>, '<?= addslashes($u['nama']) ?>')"><i class="fas fa-key"></i> Reset PW</button>
      <button class="btn-xs poin"  onclick="openModal('poin',  <?= $u['id'] ?>, '<?= addslashes($u['nama']) ?>')"><i class="fas fa-star"></i> Reset Poin</button>
      <button class="btn-xs hapus" onclick="openModal('hapus', <?= $u['id'] ?>, '<?= addslashes($u['nama']) ?>')"><i class="fas fa-trash-alt"></i> Hapus</button>
    </div>
    <?php else: ?>
    <div class="deleted-label"><i class="fas fa-times-circle"></i> Akun ini sudah dihapus</div>
    <?php endif; ?>

  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<div class="empty-state fu5">
  <i class="fas fa-users"></i>
  <p>Tidak ada mahasiswa<?= $search ? " untuk \"".e($search)."\"" : '' ?>.</p>
</div>
<?php endif; ?>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box">
    <div class="modal-ico" id="modalIcon"></div>
    <h3 id="modalTitle"></h3>
    <p id="modalDesc"></p>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeModal()"><i class="fas fa-times"></i> Batal</button>
      <form method="POST" id="modalForm" style="display:inline;">
        <input type="hidden" name="aksi"    id="modalAksi">
        <input type="hidden" name="user_id" id="modalUserId">
        <button type="submit" class="modal-btn" id="modalConfirmBtn"></button>
      </form>
    </div>
  </div>
</div>

<footer class="sa-foot">
  <i class="fas fa-cloud" style="color:var(--s1);margin-right:5px;"></i>
  <strong style="color:var(--s1);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(58,97,134,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
function filterLive() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  let visible = 0;
  document.querySelectorAll('#userGrid .user-card').forEach(card => {
    const match = card.dataset.nama.includes(q) || card.dataset.email.includes(q);
    card.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  document.getElementById('resultCount').textContent = visible;
}

function openModal(aksi, userId, nama) {
  const configs = {
    reset: {
      iconBg:'rgba(58,97,134,0.10)', iconColor:'#3a6186', iconClass:'fas fa-key',
      title: 'Reset Password?',
      desc: `Password <strong>${nama}</strong> akan direset ke <code>mahasiswa123</code>.`,
      btnText: 'Ya, Reset', btnIcon:'fas fa-undo', btnClass: 'confirm',
    },
    poin: {
      iconBg:'rgba(212,160,23,0.10)', iconColor:'#d4a017', iconClass:'fas fa-star',
      title: 'Reset Poin?',
      desc: `Poin <strong>${nama}</strong> akan direset ke <strong>0</strong>. Tindakan ini tidak dapat dibatalkan.`,
      btnText: 'Ya, Reset Poin', btnIcon:'fas fa-undo', btnClass: 'confirm-warn',
    },
    hapus: {
      iconBg:'rgba(185,28,28,0.10)', iconColor:'#b91c1c', iconClass:'fas fa-trash-alt',
      title: 'Hapus Akun?',
      desc: `Akun <strong>${nama}</strong> akan dihapus. Riwayat peminjaman tetap tersimpan.`,
      btnText: 'Ya, Hapus', btnIcon:'fas fa-trash-alt', btnClass: 'confirm-danger',
    },
  };
  const c = configs[aksi];
  const icoEl = document.getElementById('modalIcon');
  icoEl.style.background = c.iconBg;
  icoEl.style.color = c.iconColor;
  icoEl.innerHTML = '<i class="' + c.iconClass + '"></i>';
  document.getElementById('modalTitle').textContent      = c.title;
  document.getElementById('modalDesc').innerHTML         = c.desc;
  document.getElementById('modalConfirmBtn').innerHTML    = '<i class="' + c.btnIcon + '"></i> ' + c.btnText;
  document.getElementById('modalConfirmBtn').className    = 'modal-btn ' + c.btnClass;
  document.getElementById('modalAksi').value             = aksi;
  document.getElementById('modalUserId').value           = userId;
  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}
document.getElementById('modalOverlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});
</script>
</body>
</html>