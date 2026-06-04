<?php
// ============================================
//  CloudLibrary Mini — Super Admin: Kelola Admin
//  File   : super_admin/admin.php
// ============================================
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: '.BASE_URL.'/auth/login.php'); exit;
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $uid  = (int)($_POST['user_id'] ?? 0);

    if ($aksi === 'tambah') {
        $nama  = trim($_POST['nama']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = trim($_POST['password'] ?? '');
        if (!$nama || !$email || !$pass) {
            $error = 'Semua field wajib diisi!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid!';
        } else {
            $cek = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $cek->execute([$email]);
            if ($cek->fetch()) {
                $error = 'Email sudah terdaftar!';
            } else {
                $hash = md5($pass);
                $pdo->prepare("INSERT INTO users (nama, email, password, role, status, created_at) VALUES (?,?,?,'admin','aktif',NOW())")
                    ->execute([$nama, $email, $hash]);
                $success = "Admin \"$nama\" berhasil ditambahkan!";
            }
        }
    }

    if ($aksi === 'toggle_status' && $uid) {
        $cur = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'admin'");
        $cur->execute([$uid]);
        $row = $cur->fetch();
        if ($row) {
            $new = $row['status'] === 'aktif' ? 'nonaktif' : 'aktif';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new, $uid]);
            $success = $new === 'aktif' ? 'Admin berhasil diaktifkan.' : 'Admin berhasil diblokir.';
        }
    }

    if ($aksi === 'reset_password' && $uid) {
        $hash = md5('admin123');
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
        $success = 'Password admin direset ke admin123.';
    }

    if ($aksi === 'hapus' && $uid) {
        $pdo->prepare("UPDATE users SET status = 'dihapus' WHERE id = ? AND role = 'admin'")->execute([$uid]);
        $success = 'Admin berhasil dihapus dari sistem.';
    }

    if (!$error) {
        header('Location: admin.php?success=' . urlencode($success));
        exit;
    }
}

if (isset($_GET['success'])) $success = $_GET['success'];

$search = trim($_GET['q']      ?? '');
$status = $_GET['status']      ?? '';
$sort   = $_GET['sort']        ?? 'terbaru';

$where  = ["u.role = 'admin'"];
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
    'nama'  => 'u.nama ASC',
    'lama'  => 'u.created_at ASC',
    default => 'u.created_at DESC',
};

$stmt = $pdo->prepare("
    SELECT u.* FROM users u
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $order
");
$stmt->execute($params);
$admins = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='aktif'    THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status='nonaktif' THEN 1 ELSE 0 END) AS nonaktif,
        SUM(CASE WHEN status='dihapus'  THEN 1 ELSE 0 END) AS dihapus
    FROM users WHERE role='admin'
")->fetch();

$title = "Kelola Admin — Super Admin CloudLibrary Mini";
include '../includes/navbar.php';
?>

<style>
/* ── BACKGROUND — FOTO PERPUSTAKAAN ── */
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

/* ── TOKENS ── */
:root {
  --s1:#3a6186;--s2:#2c4f78;--s3:#5b8fb9;--gold:#d4a017;--gold2:#f9c74f;
  --card:rgba(255,255,255,0.78);--card-b:rgba(255,255,255,0.85);
  --text:#1a2744;--muted:#6b7a99;
  --success:#15803d;--warning:#c2410c;--danger:#b91c1c;
  --sh:0 4px 20px rgba(58,97,134,0.10);--sh-md:0 10px 36px rgba(58,97,134,0.16);
}

/* BACK BUTTON */
.btn-back {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 18px; border-radius: 100px;
  background: rgba(255,255,255,0.60);
  border: 2px solid rgba(255,255,255,0.85);
  color: var(--s1); font-size: 12px; font-weight: 800;
  text-decoration: none; backdrop-filter: blur(20px);
  box-shadow: var(--sh); transition: all .2s;
}
.btn-back:hover { background: rgba(255,255,255,0.82); transform: translateX(-2px); }

.page-header {
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:10px;
}
.page-header-left { display:flex;align-items:center;gap:12px;flex-wrap:wrap; }
.page-header h2 {
  font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:var(--s1);
  display:flex;align-items:center;gap:10px;
}
.page-header h2 i { color:var(--gold); }
.ph-sub {
  font-size:12px;font-weight:700;color:var(--muted);
  background:rgba(255,255,255,0.78);border:2px solid rgba(255,255,255,0.85);
  padding:6px 14px;border-radius:100px;backdrop-filter:blur(20px);
  display:flex;align-items:center;gap:6px;
}

.alert-box {
  border-radius:14px;padding:13px 18px;margin-bottom:18px;
  font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;
  backdrop-filter:blur(20px);position:relative;z-index:1;
}
.alert-success{background:rgba(46,125,50,0.08);border:1.5px solid rgba(46,125,50,0.24);color:#15803d;}
.alert-error  {background:rgba(198,40,40,0.08);border:1.5px solid rgba(198,40,40,0.24);color:#b91c1c;}

/* ── STAT CARDS ── */
.stat-row {
  display:grid;grid-template-columns:repeat(4,1fr);gap:12px;
  margin-bottom:24px;position:relative;z-index:1;
}
@media(max-width:700px){.stat-row{grid-template-columns:repeat(2,1fr)}}

.stat-mini {
  background:var(--card);border:2px solid var(--card-b);border-radius:16px;padding:16px;
  text-align:center;text-decoration:none;color:inherit;
  backdrop-filter:blur(20px);
  box-shadow:var(--sh);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden;
  border-top:3px solid transparent;
}
.stat-mini:hover{transform:translateY(-2px);box-shadow:var(--sh-md);}
.stat-mini .stat-ico{
  width:40px;height:40px;border-radius:12px;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:16px;margin-bottom:8px;
}
.stat-mini .num{font-family:'Syne',sans-serif;font-size:28px;font-weight:900;}
.stat-mini .lbl{font-size:11px;font-weight:700;color:var(--muted);margin-top:3px;}

/* ── FORM ── */
.form-card {
  background:var(--card);border:2px solid var(--card-b);border-radius:20px;padding:24px 26px;
  backdrop-filter:blur(20px);
  box-shadow:var(--sh);margin-bottom:22px;position:relative;z-index:1;overflow:hidden;
}
.form-card::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--s1),var(--s3),var(--gold));}
.form-card h3{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;color:var(--s1);margin-bottom:18px;display:flex;align-items:center;gap:8px;}

.form-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;}
@media(max-width:800px){.form-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:520px){.form-grid{grid-template-columns:1fr;}}

.form-group label{display:block;font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px;}
.form-group input{
  width:100%;background:rgba(255,255,255,0.82);border:1.5px solid rgba(255,255,255,0.90);
  border-radius:100px;padding:10px 16px;font-size:13px;font-family:'Nunito',sans-serif;
  color:var(--text);outline:none;backdrop-filter:blur(12px);box-shadow:var(--sh);transition:border-color .2s,box-shadow .2s;
}
.form-group input:focus{border-color:rgba(58,97,134,0.35);box-shadow:0 0 0 3px rgba(58,97,134,0.08);}

.btn-tambah{
  display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:100px;
  background:linear-gradient(135deg,var(--s1),var(--s3));color:#fff;font-size:13px;font-weight:900;
  border:none;cursor:pointer;font-family:'Nunito',sans-serif;
  box-shadow:0 4px 16px rgba(58,97,134,0.30);transition:all .2s;white-space:nowrap;
}
.btn-tambah:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(58,97,134,0.40);}

/* ── FILTER ── */
.filter-area{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;position:relative;z-index:1;}
.filter-area input[type=text]{
  flex:1;min-width:200px;background:rgba(255,255,255,0.82);border:1.5px solid rgba(255,255,255,0.90);
  border-radius:100px;padding:9px 18px 9px 38px;font-size:13px;font-family:'Nunito',sans-serif;
  color:var(--text);outline:none;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:border-color .2s;
}
.filter-area input[type=text]:focus{border-color:rgba(58,97,134,0.35);}
.search-wrap{position:relative;flex:1;min-width:200px;}
.search-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.search-wrap input{padding-left:38px;}

.tab-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;position:relative;z-index:1;}
.tab-pill{
  padding:6px 14px;border-radius:100px;font-size:12px;font-weight:700;
  border:1.5px solid rgba(255,255,255,0.80);background:rgba(255,255,255,0.55);
  color:var(--muted);text-decoration:none;backdrop-filter:blur(10px);transition:all .18s;
  display:inline-flex;align-items:center;gap:5px;
}
.tab-pill:hover{border-color:rgba(58,97,134,0.30);color:var(--s1);background:rgba(255,255,255,0.78);}
.tab-pill.active{background:rgba(58,97,134,0.10);color:var(--s1);border-color:rgba(58,97,134,0.30);}
.tab-pill i{font-size:10px;}

/* ── ADMIN GRID ── */
.admin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;position:relative;z-index:1;}

.admin-card{
  background:var(--card);border:2px solid var(--card-b);border-radius:20px;padding:22px;
  backdrop-filter:blur(20px);
  box-shadow:var(--sh);transition:transform .2s,box-shadow .2s,border-color .2s;position:relative;overflow:hidden;
}
.admin-card.status-aktif   {border-left:3px solid var(--success);}
.admin-card.status-nonaktif{border-left:3px solid var(--warning);opacity:.75;}
.admin-card.status-dihapus {border-left:3px solid var(--danger);opacity:.45;}
.admin-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md);}

.admin-avatar{
  width:52px;height:52px;border-radius:50%;
  background:linear-gradient(135deg,var(--s1),var(--s3));
  display:flex;align-items:center;justify-content:center;
  font-family:'Syne',sans-serif;font-size:20px;font-weight:900;color:#fff;flex-shrink:0;
  box-shadow:0 3px 12px rgba(58,97,134,0.28);
}

.status-pill{font-size:10px;font-weight:800;padding:3px 10px;border-radius:100px;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px;}
.pill-aktif   {background:rgba(21,128,61,0.10); color:var(--success);border:1px solid rgba(21,128,61,0.22);}
.pill-nonaktif{background:rgba(194,65,12,0.10); color:var(--warning);border:1px solid rgba(194,65,12,0.22);}
.pill-dihapus {background:rgba(185,28,28,0.10); color:var(--danger);border:1px solid rgba(185,28,28,0.22);}

.info-row{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--muted);font-weight:600;margin-top:6px;}
.info-row i{color:var(--s1);font-size:10px;width:14px;text-align:center;}

.action-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:14px;}
.btn-xs{
  font-size:11px;font-weight:800;padding:6px 13px;border-radius:100px;
  background:rgba(255,255,255,0.78);backdrop-filter:blur(8px);
  color:var(--text);cursor:pointer;transition:all .18s;border:1.5px solid transparent;
  display:inline-flex;align-items:center;gap:5px;
}
.btn-xs.toggle-off{border-color:rgba(194,65,12,0.28);color:var(--warning);}
.btn-xs.toggle-off:hover{background:rgba(194,65,12,0.10);}
.btn-xs.toggle-on {border-color:rgba(21,128,61,0.28);color:var(--success);}
.btn-xs.toggle-on:hover {background:rgba(21,128,61,0.10);}
.btn-xs.reset{border-color:rgba(58,97,134,0.28);color:var(--s1);}
.btn-xs.reset:hover{background:rgba(58,97,134,0.08);}
.btn-xs.hapus{border-color:rgba(185,28,28,0.28);color:var(--danger);}
.btn-xs.hapus:hover{background:rgba(185,28,28,0.08);}

.deleted-label{font-size:11px;color:var(--danger);font-weight:800;margin-top:12px;display:flex;align-items:center;gap:5px;}

.empty-state{
  text-align:center;padding:52px 20px;
  background:var(--card);border:2px solid var(--card-b);border-radius:20px;
  backdrop-filter:blur(20px);box-shadow:var(--sh);position:relative;z-index:1;
}
.empty-state i{font-size:36px;color:var(--muted);display:block;margin-bottom:10px;}
.empty-state p{font-size:14px;color:var(--muted);font-weight:700;}

/* ── MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(26,39,68,0.45);backdrop-filter:blur(6px);z-index:999;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{
  background:rgba(255,255,255,0.92);border:2px solid rgba(255,255,255,0.95);
  border-radius:22px;padding:32px 28px;max-width:380px;width:90%;text-align:center;
  backdrop-filter:blur(24px);box-shadow:0 24px 60px rgba(58,97,134,0.20);
  position:relative;overflow:hidden;
}
.modal-box::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--s1),var(--s3),var(--gold));}
.modal-box h3{font-family:'Syne',sans-serif;font-size:20px;font-weight:900;color:var(--s1);margin-bottom:10px;}
.modal-box p{color:var(--muted);font-size:13px;margin-bottom:22px;line-height:1.7;}
.modal-ico{width:56px;height:56px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px;}
.modal-actions{display:flex;gap:10px;justify-content:center;}
.modal-btn{padding:9px 22px;border-radius:100px;font-size:13px;font-weight:800;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .18s;display:inline-flex;align-items:center;gap:6px;}
.modal-btn.cancel{background:rgba(255,255,255,0.65);border:1.5px solid rgba(58,97,134,0.20);color:var(--muted);}
.modal-btn.cancel:hover{background:rgba(255,255,255,0.88);}
.modal-btn.confirm{background:rgba(58,97,134,0.10);border:1.5px solid rgba(58,97,134,0.30);color:var(--s1);}
.modal-btn.confirm:hover{background:rgba(58,97,134,0.18);}
.modal-btn.confirm-danger{background:rgba(185,28,28,0.10);border:1.5px solid rgba(185,28,28,0.30);color:var(--danger);}
.modal-btn.confirm-danger:hover{background:rgba(185,28,28,0.18);}

.result-count{font-size:13px;color:var(--muted);font-weight:700;margin-bottom:14px;position:relative;z-index:1;}

/* ── FOOTER ── */
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
.fu5{animation:fadeUp .4s ease .36s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <div class="page-header-left">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h2><i class="fas fa-crown"></i> Kelola Admin</h2>
  </div>
  <div class="ph-sub"><i class="fas fa-shield-alt" style="color:var(--s1);"></i> Super Admin Panel &middot; <?= $stats['total'] ?> admin terdaftar</div>
</div>

<!-- ALERTS -->
<?php if ($success): ?>
<div class="alert-box alert-success fu1"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-box alert-error fu1"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stat-row fu2">
  <?php $sc = [
    [''        ,'fa-users-cog',   '#3a6186','rgba(58,97,134,0.12)',  $stats['total'],    'Total Admin'],
    ['aktif'   ,'fa-check-circle','#15803d','rgba(21,128,61,0.12)',  $stats['aktif'],    'Aktif'],
    ['nonaktif','fa-ban',         '#c2410c','rgba(194,65,12,0.12)',  $stats['nonaktif'], 'Diblokir'],
    ['dihapus' ,'fa-trash-alt',   '#b91c1c','rgba(185,28,28,0.12)',  $stats['dihapus'],  'Dihapus'],
  ];
  foreach ($sc as [$sv,$si,$col,$bgc,$num,$lbl]): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => $sv])) ?>"
     class="stat-mini"
     style="border-top-color:<?= $col ?>;<?= $status===$sv ? 'border-color:'.$col.';' : '' ?>">
    <div class="stat-ico" style="background:<?= $bgc ?>;color:<?= $col ?>;"><i class="fas <?= $si ?>"></i></div>
    <div class="num" style="color:<?= $col ?>;"><?= $num ?></div>
    <div class="lbl"><?= $lbl ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- FORM TAMBAH -->
<div class="form-card fu3">
  <h3><i class="fas fa-user-plus"></i> Tambah Admin Baru</h3>
  <form method="POST" autocomplete="off">
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-grid">
      <div class="form-group">
        <label>Nama Lengkap</label>
        <input type="text" name="nama" placeholder="Nama admin..." required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="email@domain.com" required>
      </div>
      <div class="form-group">
        <label>Password Awal</label>
        <input type="password" name="password" placeholder="Min. 6 karakter" required minlength="6">
      </div>
      <div>
        <button type="submit" class="btn-tambah">
          <i class="fas fa-plus"></i> Tambahkan
        </button>
      </div>
    </div>
  </form>
</div>

<!-- FILTER -->
<div class="filter-area fu4">
  <div class="search-wrap">
    <i class="fas fa-search"></i>
    <input type="text" id="searchInput"
           placeholder="Cari nama atau email admin..."
           value="<?= e($search) ?>" oninput="filterLive()">
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <?php foreach (['terbaru'=>['fa-clock','Terbaru'],'lama'=>['fa-calendar-alt','Lama'],'nama'=>['fa-sort-alpha-down','Nama']] as $k=>$v): ?>
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
    'nonaktif'=>['fa-ban','Diblokir'],
    'dihapus'=>['fa-trash-alt','Dihapus']
  ] as $sv => $sl): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['status' => $sv])) ?>"
       class="tab-pill <?= $status===$sv ? 'active' : '' ?>"><i class="fas <?= $sl[0] ?>"></i> <?= $sl[1] ?></a>
  <?php endforeach; ?>
</div>

<!-- RESULT COUNT -->
<div class="result-count fu5">
  Menampilkan <strong id="resultCount"><?= count($admins) ?></strong> admin
</div>

<!-- ADMIN GRID -->
<?php if ($admins): ?>
<div class="admin-grid fu5" id="adminGrid">
  <?php foreach ($admins as $a):
    $init = strtoupper(substr($a['nama'], 0, 1)); ?>
  <div class="admin-card status-<?= $a['status'] ?>"
       data-nama="<?= strtolower($a['nama']) ?>"
       data-email="<?= strtolower($a['email']) ?>">
    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;">
      <div class="admin-avatar"><?= $init ?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:900;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
          <?= e($a['nama']) ?>
        </div>
        <div style="margin-top:5px;">
          <span class="status-pill pill-<?= $a['status'] ?>">
            <?php if($a['status']==='aktif'): ?><i class="fas fa-check-circle" style="font-size:9px;"></i>
            <?php elseif($a['status']==='nonaktif'): ?><i class="fas fa-ban" style="font-size:9px;"></i>
            <?php else: ?><i class="fas fa-trash-alt" style="font-size:9px;"></i>
            <?php endif; ?>
            <?= $a['status'] ?>
          </span>
        </div>
      </div>
    </div>
    <div class="info-row"><i class="fas fa-envelope"></i> <?= e($a['email']) ?></div>
    <div class="info-row"><i class="fas fa-calendar-alt"></i> Bergabung: <?= formatTanggal($a['created_at']) ?></div>
    <div class="info-row"><i class="fas fa-shield-alt"></i> Role: Admin</div>
    <?php if ($a['status'] !== 'dihapus'): ?>
    <div class="action-row">
      <form method="POST" style="display:inline;">
        <input type="hidden" name="aksi" value="toggle_status">
        <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
        <?php if ($a['status'] === 'aktif'): ?>
          <button type="submit" class="btn-xs toggle-off"><i class="fas fa-ban"></i> Blokir</button>
        <?php else: ?>
          <button type="submit" class="btn-xs toggle-on"><i class="fas fa-check-circle"></i> Aktifkan</button>
        <?php endif; ?>
      </form>
      <button class="btn-xs reset" onclick="openModal('reset_password', <?= $a['id'] ?>, '<?= addslashes($a['nama']) ?>')"><i class="fas fa-key"></i> Reset PW</button>
      <button class="btn-xs hapus" onclick="openModal('hapus', <?= $a['id'] ?>, '<?= addslashes($a['nama']) ?>')"><i class="fas fa-trash-alt"></i> Hapus</button>
    </div>
    <?php else: ?>
    <div class="deleted-label"><i class="fas fa-times-circle"></i> Akun ini sudah dihapus</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state fu5">
  <i class="fas fa-user-shield"></i>
  <p>Belum ada admin yang terdaftar<?= $search ? " untuk \"".e($search)."\"" : '' ?>.</p>
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
  document.querySelectorAll('#adminGrid .admin-card').forEach(card => {
    const match = card.dataset.nama.includes(q) || card.dataset.email.includes(q);
    card.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  document.getElementById('resultCount').textContent = visible;
}

function openModal(aksi, userId, nama) {
  const configs = {
    reset_password: {
      iconBg:'rgba(58,97,134,0.10)', iconColor:'#3a6186', iconClass:'fas fa-key',
      title:'Reset Password?',
      desc:`Password <strong>${nama}</strong> akan direset ke <code>admin123</code>.`,
      btnText:'Ya, Reset', btnIcon:'fas fa-undo', btnClass:'confirm',
    },
    hapus: {
      iconBg:'rgba(185,28,28,0.10)', iconColor:'#b91c1c', iconClass:'fas fa-trash-alt',
      title:'Hapus Admin?',
      desc:`Akun <strong>${nama}</strong> akan dihapus dari sistem.`,
      btnText:'Ya, Hapus', btnIcon:'fas fa-trash-alt', btnClass:'confirm-danger',
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