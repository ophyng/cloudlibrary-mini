<?php
// ============================================
//  CloudLibrary Mini — Admin: Kelola Pengguna
//  File   : admin/pengguna/index.php
// ============================================
session_start();
require_once '../../includes/functions.php';
cekLoginAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $uid  = (int)($_POST['user_id'] ?? 0);
    if ($aksi === 'toggle_status' && $uid) {
        $cur = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'mahasiswa'");
        $cur->execute([$uid]); $row = $cur->fetch();
        if ($row) {
            $new = $row['status'] === 'aktif' ? 'nonaktif' : 'aktif';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new, $uid]);
        }
    }
    if ($aksi === 'reset_password' && $uid) {
        $hash = password_hash('mahasiswa123', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
    }
    if ($aksi === 'hapus' && $uid) {
        $pdo->prepare("UPDATE users SET status = 'dihapus' WHERE id = ?")->execute([$uid]);
    }
    header('Location: index.php?' . http_build_query($_GET)); exit;
}

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$sort   = $_GET['sort']   ?? 'terbaru';
$where  = ["u.role = 'mahasiswa'"]; $params = [];
if ($search) { $where[] = "(u.nama LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = "u.status = ?"; $params[] = $status; }
$order = match($sort) { 'nama'=>'u.nama ASC','poin'=>'u.poin DESC','pinjaman'=>'total_pinjam DESC',default=>'u.created_at DESC' };
$stmt = $pdo->prepare("
    SELECT u.*, COUNT(DISTINCT p.id) AS total_pinjam, COUNT(DISTINCT r.id) AS total_review,
           SUM(CASE WHEN p.status IN ('aktif','hampir_habis') THEN 1 ELSE 0 END) AS pinjam_aktif
    FROM users u
    LEFT JOIN peminjaman p ON p.user_id = u.id
    LEFT JOIN review r ON r.user_id = u.id
    WHERE ".implode(' AND ',$where)." GROUP BY u.id ORDER BY $order
");
$stmt->execute($params); $users = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
        SUM(CASE WHEN status='aktif'    THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status='nonaktif' THEN 1 ELSE 0 END) AS nonaktif,
        SUM(CASE WHEN status='dihapus'  THEN 1 ELSE 0 END) AS dihapus,
        SUM(poin) AS total_poin
    FROM users WHERE role='mahasiswa'
")->fetch();

function getLevel(int $poin): array {
    return match(true) {
        $poin >= 500 => ['Legenda', 'fa-crown',      '#f9c74f'],
        $poin >= 200 => ['Master',  'fa-trophy',     '#a78bfa'],
        $poin >= 100 => ['Ahli',    'fa-star',       '#60a5fa'],
        $poin >= 50  => ['Aktif',   'fa-book-open',  '#4ade80'],
        $poin >= 10  => ['Pemula',  'fa-seedling',   '#94a3b8'],
        default      => ['Baru',    'fa-user',       '#64748b'],
    };
}

$title = "Kelola Pengguna — Admin CloudLibrary Mini";
include '../../includes/navbar.php';
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
  --card:rgba(255,255,255,0.10);--card-b:rgba(255,255,255,0.18);
  --text:#fff;--muted:rgba(255,255,255,0.55);
  --accent:#60a5fa;--accent2:#fbbf24;
  --success:#4ade80;--warning:#fbbf24;--danger:#f87171;
  --sh:0 4px 22px rgba(0,0,0,0.22);--sh-md:0 8px 32px rgba(0,0,0,0.32);
}

/* PAGE HEADER */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:12px;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:#f9c74f;}
.ph-sub{font-size:12px;font-weight:700;color:rgba(255,255,255,0.65);background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.18);padding:6px 14px;border-radius:100px;backdrop-filter:blur(10px);}

/* STAT ROW */
.stat-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px;position:relative;z-index:1;}
@media(max-width:900px){.stat-row{grid-template-columns:repeat(3,1fr);}}
@media(max-width:580px){.stat-row{grid-template-columns:repeat(2,1fr);}}
.stat-mini{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:14px;padding:16px;text-align:center;text-decoration:none;color:inherit;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);transition:transform .2s,box-shadow .2s,background .2s;position:relative;overflow:hidden;}
.stat-mini:hover{transform:translateY(-2px);box-shadow:var(--sh-md);background:rgba(255,255,255,0.16);}
.stat-mini .num{font-family:'Syne',sans-serif;font-size:26px;font-weight:900;color:#fff;}
.stat-mini .lbl{font-size:11px;font-weight:700;color:rgba(255,255,255,0.55);margin-top:3px;}

/* FILTER */
.filter-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;position:relative;z-index:1;}
.filter-form input[type=text]{flex:1;min-width:220px;background:rgba(255,255,255,0.12);border:1.5px solid rgba(255,255,255,0.22);border-radius:8px;padding:9px 18px;font-size:13px;font-family:'Nunito',sans-serif;color:#fff;outline:none;backdrop-filter:blur(10px);transition:border-color .2s;}
.filter-form input[type=text]::placeholder{color:rgba(255,255,255,0.35);}
.filter-form input[type=text]:focus{border-color:rgba(249,199,79,0.50);}

/* TABS */
.sort-tabs,.status-tabs{display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;}
.status-tabs{margin-bottom:16px;}
.sort-tab,.status-tab{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid rgba(255,255,255,0.20);background:rgba(255,255,255,0.10);color:rgba(255,255,255,0.70);text-decoration:none;transition:all .18s;backdrop-filter:blur(8px);}
.sort-tab:hover,.status-tab:hover{background:rgba(255,255,255,0.18);color:#fff;border-color:rgba(255,255,255,0.35);}
.sort-tab.active,.status-tab.active{background:#f9c74f;color:#0f172a;border-color:#f9c74f;font-weight:900;}

/* RESULT COUNT */
.result-count{font-size:13px;color:rgba(255,255,255,0.65);font-weight:700;margin-bottom:16px;position:relative;z-index:1;}

/* USER GRID */
.user-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;position:relative;z-index:1;}
.user-card{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:18px;padding:20px;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);transition:transform .2s,box-shadow .2s,background .2s;position:relative;overflow:hidden;}
.user-card:hover{border-color:rgba(255,255,255,0.30);transform:translateY(-3px);box-shadow:var(--sh-md);background:rgba(255,255,255,0.14);}
.user-card.nonaktif{opacity:.65;}
.user-card.dihapus{opacity:.45;border-color:rgba(248,113,113,0.30);}

/* AVATAR */
.avatar-lg{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,0.20);border:2px solid rgba(255,255,255,0.30);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:20px;font-weight:900;color:#fff;flex-shrink:0;}

/* STATUS PILL */
.status-pill{font-size:10px;font-weight:800;padding:2px 9px;border-radius:100px;text-transform:uppercase;}
.pill-aktif{background:rgba(74,222,128,0.20);color:#4ade80;border:1px solid rgba(74,222,128,0.35);}
.pill-nonaktif{background:rgba(251,191,36,0.20);color:#fbbf24;border:1px solid rgba(251,191,36,0.35);}
.pill-dihapus{background:rgba(248,113,113,0.20);color:#f87171;border:1px solid rgba(248,113,113,0.35);}

/* CARD STATS */
.card-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin:14px 0;}
.card-stat{background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.14);border-radius:10px;padding:8px;text-align:center;}
.card-stat .cs-num{font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:#60a5fa;}
.card-stat .cs-lbl{font-size:9px;color:rgba(255,255,255,0.45);font-weight:700;margin-top:2px;}

/* ACTION BUTTONS */
.action-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;}
.btn-xs{font-size:11px;font-weight:700;padding:5px 12px;border-radius:8px;background:rgba(255,255,255,0.10);backdrop-filter:blur(8px);color:rgba(255,255,255,0.75);cursor:pointer;transition:all .18s;border:1px solid rgba(255,255,255,0.20);font-family:'Nunito',sans-serif;display:inline-flex;align-items:center;gap:5px;}
.btn-xs:hover{background:rgba(255,255,255,0.20);color:#fff;border-color:rgba(255,255,255,0.35);}
.btn-xs.btn-danger{border-color:rgba(248,113,113,0.35);color:#f87171;}
.btn-xs.btn-danger:hover{background:rgba(248,113,113,0.15);}
.btn-xs.btn-green{border-color:rgba(74,222,128,0.35);color:#4ade80;}
.btn-xs.btn-green:hover{background:rgba(74,222,128,0.15);}
.btn-xs.btn-yellow{border-color:rgba(249,199,79,0.35);color:#f9c74f;}
.btn-xs.btn-yellow:hover{background:rgba(249,199,79,0.15);}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(5,15,35,0.70);backdrop-filter:blur(8px);z-index:999;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:rgba(15,30,60,0.90);border:1.5px solid rgba(255,255,255,0.18);border-radius:20px;padding:32px 28px;max-width:380px;width:90%;text-align:center;backdrop-filter:blur(24px);box-shadow:0 24px 60px rgba(0,0,0,0.40);position:relative;}
.modal-box h3{font-family:'Syne',sans-serif;font-size:20px;font-weight:900;color:#fff;margin-bottom:10px;}
.modal-box p{color:rgba(255,255,255,0.60);font-size:13px;margin-bottom:22px;line-height:1.7;}
.modal-box p strong{color:#fff;}
.modal-box p code{background:rgba(249,199,79,0.20);color:#f9c74f;padding:1px 6px;border-radius:4px;}
.modal-actions{display:flex;gap:10px;justify-content:center;}
.modal-btn{padding:9px 20px;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .18s;}
.modal-btn.cancel{background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.20);color:rgba(255,255,255,0.70);}
.modal-btn.cancel:hover{background:rgba(255,255,255,0.18);color:#fff;}
.modal-btn.confirm{background:rgba(96,165,250,0.20);border:1px solid rgba(96,165,250,0.40);color:#60a5fa;}
.modal-btn.confirm:hover{background:rgba(96,165,250,0.35);}
.modal-btn.confirm-danger{background:rgba(248,113,113,0.20);border:1px solid rgba(248,113,113,0.40);color:#f87171;}
.modal-btn.confirm-danger:hover{background:rgba(248,113,113,0.35);}

/* EMPTY STATE */
.empty-state{text-align:center;padding:48px 20px;background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:18px;backdrop-filter:blur(14px);box-shadow:var(--sh);position:relative;z-index:1;}
.empty-state i{font-size:36px;color:rgba(255,255,255,0.35);display:block;margin-bottom:10px;}
.empty-state p{font-size:14px;color:rgba(255,255,255,0.55);font-weight:700;}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}.fu4{animation:fadeUp .4s ease .28s both}.fu5{animation:fadeUp .4s ease .36s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <h2><i class="fas fa-users"></i> Kelola Pengguna</h2>
  <div class="ph-sub"><?= $stats['total'] ?> mahasiswa terdaftar</div>
</div>

<!-- STAT CARDS — SVG custom palet emas -->
<div class="stat-row fu2">

  <!-- Total -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>''])) ?>" class="stat-mini" style="border-top:3px solid #f9c74f;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <defs><linearGradient id="ug1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient></defs>
        <circle cx="14" cy="13" r="6" fill="rgba(249,199,79,0.20)" stroke="url(#ug1)" stroke-width="1.3"/>
        <circle cx="26" cy="13" r="5" fill="rgba(249,199,79,0.12)" stroke="url(#ug1)" stroke-width="1"/>
        <path d="M2,34 C2,26 26,26 26,34" fill="rgba(249,199,79,0.15)" stroke="url(#ug1)" stroke-width="1.3"/>
        <path d="M26,30 C26,26 38,26 38,32" fill="rgba(249,199,79,0.08)" stroke="url(#ug1)" stroke-width="1"/>
      </svg>
    </div>
    <div class="num"><?= $stats['total'] ?></div>
    <div class="lbl">Total</div>
  </a>

  <!-- Aktif -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'aktif'])) ?>" class="stat-mini" style="border-top:3px solid #4ade80;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <circle cx="20" cy="20" r="16" fill="rgba(74,222,128,0.12)" stroke="#4ade80" stroke-width="1.3"/>
        <circle cx="20" cy="20" r="10" fill="rgba(74,222,128,0.18)" stroke="#4ade80" stroke-width="1"/>
        <polyline points="13,20 18,25 27,14" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    </div>
    <div class="num"><?= $stats['aktif'] ?></div>
    <div class="lbl">Aktif</div>
  </a>

  <!-- Nonaktif -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'nonaktif'])) ?>" class="stat-mini" style="border-top:3px solid #fbbf24;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <circle cx="20" cy="20" r="16" fill="rgba(251,191,36,0.12)" stroke="#fbbf24" stroke-width="1.3"/>
        <line x1="12" y1="20" x2="28" y2="20" stroke="#fbbf24" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="num"><?= $stats['nonaktif'] ?></div>
    <div class="lbl">Nonaktif</div>
  </a>

  <!-- Dihapus -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'dihapus'])) ?>" class="stat-mini" style="border-top:3px solid #f87171;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <circle cx="20" cy="20" r="16" fill="rgba(248,113,113,0.12)" stroke="#f87171" stroke-width="1.3"/>
        <line x1="13" y1="13" x2="27" y2="27" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="27" y1="13" x2="13" y2="27" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="num"><?= $stats['dihapus'] ?></div>
    <div class="lbl">Dihapus</div>
  </a>

  <!-- Total Poin -->
  <a href="?<?= http_build_query(array_merge($_GET,['sort'=>'poin'])) ?>" class="stat-mini" style="border-top:3px solid #f9c74f;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <defs><linearGradient id="ug2" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient></defs>
        <polygon points="20,4 23,14 34,14 25,21 28,31 20,25 12,31 15,21 6,14 17,14" fill="url(#ug2)"/>
      </svg>
    </div>
    <div class="num"><?= number_format($stats['total_poin']) ?></div>
    <div class="lbl">Total Poin</div>
  </a>

</div>

<!-- FILTER -->
<div class="filter-form fu3">
  <input type="text" id="searchInput" placeholder="Cari nama atau email mahasiswa..." value="<?= e($search) ?>" oninput="filterLive()">
  <div class="sort-tabs">
    <?php foreach(['terbaru'=>'<i class="fas fa-clock"></i> Terbaru','nama'=>'<i class="fas fa-sort-alpha-down"></i> Nama','poin'=>'<i class="fas fa-star"></i> Poin','pinjaman'=>'<i class="fas fa-book"></i> Pinjaman'] as $k=>$lbl): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['sort'=>$k])) ?>" class="sort-tab <?= $sort===$k?'active':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- STATUS TABS -->
<div class="status-tabs fu3">
  <?php foreach([
    ''         => '<i class="fas fa-users"></i> Semua',
    'aktif'    => '<i class="fas fa-check-circle"></i> Aktif',
    'nonaktif' => '<i class="fas fa-pause-circle"></i> Nonaktif',
    'dihapus'  => '<i class="fas fa-times-circle"></i> Dihapus',
  ] as $sv=>$sl): ?>
    <a href="?<?= http_build_query(array_merge($_GET,['status'=>$sv])) ?>" class="status-tab <?= $status===$sv?'active':'' ?>"><?= $sl ?></a>
  <?php endforeach; ?>
</div>

<!-- RESULT COUNT -->
<div class="result-count fu4">Menampilkan <strong id="resultCount"><?= count($users) ?></strong> pengguna</div>

<!-- USER GRID -->
<?php if($users): ?>
<div class="user-grid fu5" id="userGrid">
  <?php foreach($users as $u):
    [$lvl_name,$lvl_icon,$lvl_color] = getLevel((int)$u['poin']);
    $init = strtoupper(substr($u['nama'],0,1));
  ?>
  <div class="user-card <?= $u['status']==='nonaktif'?'nonaktif':($u['status']==='dihapus'?'dihapus':'') ?>"
       data-nama="<?= strtolower($u['nama']) ?>" data-email="<?= strtolower($u['email']) ?>">

    <!-- Header kartu -->
    <div style="display:flex;align-items:flex-start;gap:12px;">
      <div class="avatar-lg"><?= $init ?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:800;font-size:14px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($u['nama']) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,0.50);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($u['email']) ?></div>
        <div style="margin-top:6px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <span class="status-pill pill-<?= $u['status'] ?>"><?= $u['status'] ?></span>
          <span style="font-size:11px;font-weight:700;color:<?= $lvl_color ?>;display:inline-flex;align-items:center;gap:4px;">
            <i class="fas <?= $lvl_icon ?>" style="font-size:10px;"></i> <?= $lvl_name ?>
          </span>
        </div>
      </div>
    </div>

    <!-- 3 stat mini -->
    <div class="card-stats">
      <div class="card-stat">
        <div class="cs-num"><?= $u['total_pinjam'] ?></div>
        <div class="cs-lbl">Pinjaman</div>
      </div>
      <div class="card-stat">
        <div class="cs-num" style="color:#a78bfa;"><?= $u['total_review'] ?></div>
        <div class="cs-lbl">Review</div>
      </div>
      <div class="card-stat">
        <div class="cs-num" style="color:#f9c74f;"><?= $u['poin'] ?></div>
        <div class="cs-lbl">Poin</div>
      </div>
    </div>

    <!-- Info tambahan -->
    <div style="font-size:11px;color:rgba(255,255,255,0.45);font-weight:600;margin-bottom:4px;display:flex;align-items:center;gap:5px;">
      <i class="fas fa-calendar-alt" style="font-size:10px;"></i> Daftar: <?= formatTanggal($u['created_at']) ?>
    </div>
    <?php if($u['pinjam_aktif']>0): ?>
    <div style="font-size:11px;color:#4ade80;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:5px;">
      <i class="fas fa-book-open" style="font-size:10px;"></i> <?= $u['pinjam_aktif'] ?> pinjaman aktif sekarang
    </div>
    <?php endif; ?>

    <!-- Tombol aksi -->
    <?php if($u['status']!=='dihapus'): ?>
    <div class="action-row">
      <form method="POST" style="display:inline;">
        <input type="hidden" name="aksi" value="toggle_status">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <?php if($u['status']==='aktif'): ?>
          <button type="submit" class="btn-xs"><i class="fas fa-ban"></i> Nonaktifkan</button>
        <?php else: ?>
          <button type="submit" class="btn-xs btn-green"><i class="fas fa-check"></i> Aktifkan</button>
        <?php endif; ?>
      </form>
      <button class="btn-xs btn-yellow" onclick="openModal('reset_password',<?= $u['id'] ?>,'<?= addslashes($u['nama']) ?>')">
        <i class="fas fa-key"></i> Reset PW
      </button>
      <button class="btn-xs btn-danger" onclick="openModal('hapus',<?= $u['id'] ?>,'<?= addslashes($u['nama']) ?>')">
        <i class="fas fa-trash"></i> Hapus
      </button>
    </div>
    <?php else: ?>
    <div style="font-size:11px;color:#f87171;font-weight:700;margin-top:10px;display:flex;align-items:center;gap:5px;">
      <i class="fas fa-ban"></i> Akun ini sudah dihapus
    </div>
    <?php endif; ?>

  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state fu5">
  <i class="fas fa-users"></i>
  <p>Tidak ada pengguna yang ditemukan<?= $search?" untuk \"".e($search)."\"":'' ?>.</p>
</div>
<?php endif; ?>

</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box">
    <div id="modalIcon" style="font-size:32px;margin-bottom:10px;color:#f9c74f;"></div>
    <h3 id="modalTitle"></h3>
    <p id="modalDesc"></p>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeModal()">Batal</button>
      <form method="POST" id="modalForm" style="display:inline;">
        <input type="hidden" name="aksi" id="modalAksi">
        <input type="hidden" name="user_id" id="modalUserId">
        <button type="submit" class="modal-btn" id="modalConfirmBtn"></button>
      </form>
    </div>
  </div>
</div>

<footer class="footer" style="position:relative;z-index:1;background:rgba(0,0,0,0.35);border-top:1px solid rgba(255,255,255,0.10);color:rgba(255,255,255,0.50);">
  <p><i class="fas fa-cloud" style="color:#60a5fa;"></i> <span style="color:#fff;">CloudLibrary Mini</span> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?></p>
</footer>

<script>
function filterLive(){
  const q=document.getElementById('searchInput').value.toLowerCase();
  let v=0;
  document.querySelectorAll('#userGrid .user-card').forEach(c=>{
    const m=c.dataset.nama.includes(q)||c.dataset.email.includes(q);
    c.style.display=m?'':'none'; if(m)v++;
  });
  document.getElementById('resultCount').textContent=v;
}

function openModal(aksi,userId,nama){
  const cfg={
    reset_password:{icon:'fa-key',title:'Reset Password?',desc:`Password <strong>${nama}</strong> akan direset ke <code>mahasiswa123</code>.`,btnText:'Ya, Reset',btnClass:'confirm'},
    hapus:{icon:'fa-trash',title:'Hapus Pengguna?',desc:`Akun <strong>${nama}</strong> akan dinonaktifkan permanen. Riwayat tetap tersimpan.`,btnText:'Ya, Hapus',btnClass:'confirm-danger'},
  };
  const c=cfg[aksi];
  const mi=document.getElementById('modalIcon');
  mi.innerHTML=`<i class="fas ${c.icon}"></i>`;
  mi.style.color=aksi==='hapus'?'#f87171':'#f9c74f';
  document.getElementById('modalTitle').textContent=c.title;
  document.getElementById('modalDesc').innerHTML=c.desc;
  document.getElementById('modalConfirmBtn').textContent=c.btnText;
  document.getElementById('modalConfirmBtn').className='modal-btn '+c.btnClass;
  document.getElementById('modalAksi').value=aksi;
  document.getElementById('modalUserId').value=userId;
  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal(){document.getElementById('modalOverlay').classList.remove('open');}
document.getElementById('modalOverlay').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal();});
</script>
</body>
</html>