<?php
// ============================================
//  CloudLibrary Mini — Admin: Kelola Peminjaman
//  File   : admin/peminjaman/index.php
// ============================================
session_start();
require_once '../../includes/functions.php';
cekLoginAdmin();
updateStatusPeminjaman($pdo);

$status = $_GET['status'] ?? '';
$genre  = $_GET['genre']  ?? '';
$search = trim($_GET['q'] ?? '');
$sort   = $_GET['sort']   ?? 'terbaru';
$where  = ["1=1"]; $params = [];
if ($status) { $where[] = "p.status = ?";  $params[] = $status; }
if ($genre)  { $where[] = "b.genre = ?";   $params[] = $genre; }
if ($search) { $where[] = "(u.nama LIKE ? OR b.judul LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$order = match($sort) { 'expired'=>'p.tanggal_expired ASC','user'=>'u.nama ASC','buku'=>'b.judul ASC',default=>'p.created_at DESC' };
$stmt = $pdo->prepare("
    SELECT p.*, u.nama AS nama_user, u.email, b.judul, b.genre, b.tipe
    FROM peminjaman p JOIN users u ON p.user_id=u.id JOIN buku b ON p.buku_id=b.id
    WHERE ".implode(' AND ',$where)." ORDER BY $order
");
$stmt->execute($params); $peminjaman = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
        SUM(CASE WHEN status='aktif'        THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status='hampir_habis' THEN 1 ELSE 0 END) AS hampir,
        SUM(CASE WHEN status='expired'      THEN 1 ELSE 0 END) AS expired,
        SUM(CASE WHEN status='dikembalikan' THEN 1 ELSE 0 END) AS selesai
    FROM peminjaman
")->fetch();

$total_antrian = $pdo->query("SELECT COUNT(*) FROM antrian WHERE status='menunggu'")->fetchColumn();
$semua_genre   = $pdo->query("SELECT DISTINCT b.genre FROM peminjaman p JOIN buku b ON p.buku_id=b.id ORDER BY b.genre")->fetchAll(PDO::FETCH_COLUMN);

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

$title = "Kelola Peminjaman — Admin CloudLibrary Mini";
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
.stat-mini .lbl{font-size:11px;font-weight:700;color:rgba(255,255,255,0.50);margin-top:3px;}

/* FILTER */
.filter-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;position:relative;z-index:1;}
.filter-form input[type=text]{flex:1;min-width:220px;background:rgba(255,255,255,0.12);border:1.5px solid rgba(255,255,255,0.22);border-radius:8px;padding:9px 18px;font-size:13px;font-family:'Nunito',sans-serif;color:#fff;outline:none;backdrop-filter:blur(10px);transition:border-color .2s;}
.filter-form input[type=text]::placeholder{color:rgba(255,255,255,0.35);}
.filter-form input[type=text]:focus{border-color:rgba(249,199,79,0.50);}
.filter-form select{background:rgba(255,255,255,0.12);border:1.5px solid rgba(255,255,255,0.22);border-radius:8px;padding:9px 14px;font-size:13px;font-family:'Nunito',sans-serif;color:#fff;outline:none;cursor:pointer;backdrop-filter:blur(10px);}
.filter-form select option{background:#1e3a5f;color:#fff;}

/* TABS */
.sort-tabs,.status-tabs{display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;}
.status-tabs{margin-bottom:16px;}
.sort-tab,.status-tab{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid rgba(255,255,255,0.20);background:rgba(255,255,255,0.10);color:rgba(255,255,255,0.70);text-decoration:none;transition:all .18s;backdrop-filter:blur(8px);display:inline-flex;align-items:center;gap:5px;}
.sort-tab:hover,.status-tab:hover{background:rgba(255,255,255,0.18);color:#fff;border-color:rgba(255,255,255,0.35);}
.sort-tab.active,.status-tab.active{background:#f9c74f;color:#0f172a;border-color:#f9c74f;font-weight:900;}

/* RESULT COUNT */
.result-count{font-size:13px;color:rgba(255,255,255,0.65);font-weight:700;margin-bottom:12px;position:relative;z-index:1;}

/* TABLE */
.table-wrap{background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:14px;overflow:hidden;overflow-x:auto;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:var(--sh);position:relative;z-index:1;}
.pinjam-table{width:100%;border-collapse:collapse;font-size:13px;min-width:860px;}
.pinjam-table thead{background:rgba(255,255,255,0.12);}
.pinjam-table th{font-size:10px;font-weight:900;color:rgba(255,255,255,0.55);text-transform:uppercase;letter-spacing:.6px;padding:12px 14px;text-align:left;border-bottom:1.5px solid rgba(255,255,255,0.15);white-space:nowrap;}
.pinjam-table td{padding:11px 14px;border-bottom:1px solid rgba(255,255,255,0.08);vertical-align:middle;color:#fff;}
.pinjam-table tr:last-child td{border-bottom:none;}
.pinjam-table tr:hover td{background:rgba(255,255,255,0.05);}

/* STATUS CHIP */
.status-chip{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;padding:3px 9px;border-radius:6px;text-transform:uppercase;white-space:nowrap;}
.chip-aktif{background:rgba(74,222,128,0.20);color:#4ade80;border:1px solid rgba(74,222,128,0.35);}
.chip-hampir_habis{background:rgba(251,191,36,0.20);color:#fbbf24;border:1px solid rgba(251,191,36,0.35);}
.chip-expired{background:rgba(248,113,113,0.20);color:#f87171;border:1px solid rgba(248,113,113,0.35);}
.chip-dikembalikan{background:rgba(148,163,184,0.20);color:#94a3b8;border:1px solid rgba(148,163,184,0.30);}

/* MINI COVER */
.mini-cover{width:32px;height:46px;border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.30);}

/* AVATAR */
.avatar-mini{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.28);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:12px;font-weight:900;color:#fff;flex-shrink:0;}

/* SISA BOX */
.sisa-box{font-family:'Syne',sans-serif;font-size:18px;font-weight:900;text-align:center;}

/* EMPTY */
.empty-state{text-align:center;padding:48px 20px;background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);border-radius:14px;backdrop-filter:blur(14px);box-shadow:var(--sh);position:relative;z-index:1;}
.empty-state i{font-size:36px;color:rgba(255,255,255,0.35);display:block;margin-bottom:10px;}
.empty-state p{font-size:14px;color:rgba(255,255,255,0.55);font-weight:700;}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}.fu4{animation:fadeUp .4s ease .28s both}.fu5{animation:fadeUp .4s ease .36s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <h2><i class="fas fa-exchange-alt"></i> Monitoring Peminjaman</h2>
  <div class="ph-sub">Total <?= $stats['total'] ?> transaksi</div>
</div>

<!-- STAT CARDS — SVG premium palet emas -->
<div class="stat-row fu2">

  <!-- Semua -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>''])) ?>" class="stat-mini" style="border-top:3px solid #f9c74f;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <defs><linearGradient id="pa" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient></defs>
        <rect x="4" y="6" width="14" height="20" rx="2" fill="rgba(249,199,79,0.15)" stroke="url(#pa)" stroke-width="1.2"/>
        <rect x="4" y="6" width="3.5" height="20" rx="1" fill="url(#pa)" opacity="0.7"/>
        <rect x="14" y="10" width="12" height="18" rx="2" fill="rgba(249,199,79,0.10)" stroke="url(#pa)" stroke-width="1"/>
        <rect x="14" y="10" width="3" height="18" rx="1" fill="url(#pa)" opacity="0.5"/>
        <rect x="22" y="8" width="14" height="22" rx="2" fill="rgba(249,199,79,0.18)" stroke="url(#pa)" stroke-width="1.3"/>
        <rect x="22" y="8" width="3.5" height="22" rx="1" fill="url(#pa)" opacity="0.9"/>
        <rect x="2" y="26" width="36" height="3.5" rx="1.5" fill="url(#pa)"/>
      </svg>
    </div>
    <div class="num"><?= $stats['total'] ?></div>
    <div class="lbl">Semua</div>
  </a>

  <!-- Aktif -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'aktif'])) ?>" class="stat-mini" style="border-top:3px solid #4ade80;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <circle cx="20" cy="20" r="15" fill="rgba(74,222,128,0.12)" stroke="#4ade80" stroke-width="1.3"/>
        <circle cx="20" cy="20" r="9"  fill="rgba(74,222,128,0.18)" stroke="#4ade80" stroke-width="1"/>
        <polyline points="13,20 18,25 27,14" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    </div>
    <div class="num"><?= $stats['aktif'] ?></div>
    <div class="lbl">Aktif</div>
  </a>

  <!-- Hampir Habis -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'hampir_habis'])) ?>" class="stat-mini" style="border-top:3px solid #fbbf24;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <rect x="8" y="4" width="24" height="5" rx="2.5" fill="#fbbf24"/>
        <rect x="8" y="31" width="24" height="5" rx="2.5" fill="#fbbf24"/>
        <path d="M11,9 L29,9 L21,20 L29,31 L11,31 L19,20 Z" fill="rgba(10,20,40,0.60)" stroke="#fbbf24" stroke-width="1.2" stroke-linejoin="round"/>
        <path d="M12,9 L28,9 L21,18 L20,18 Z" fill="#fbbf24" opacity="0.55"/>
        <path d="M20,23 L21,23 L28,31 L12,31 Z" fill="#fbbf24" opacity="0.85"/>
        <circle cx="20" cy="20" r="2" fill="#fbbf24"/>
      </svg>
    </div>
    <div class="num"><?= $stats['hampir'] ?></div>
    <div class="lbl">Hampir Habis</div>
  </a>

  <!-- Expired -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'expired'])) ?>" class="stat-mini" style="border-top:3px solid #f87171;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <circle cx="20" cy="20" r="15" fill="rgba(248,113,113,0.12)" stroke="#f87171" stroke-width="1.3"/>
        <line x1="14" y1="14" x2="26" y2="26" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="26" y1="14" x2="14" y2="26" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="num"><?= $stats['expired'] ?></div>
    <div class="lbl">Expired</div>
  </a>

  <!-- Selesai -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'dikembalikan'])) ?>" class="stat-mini" style="border-top:3px solid #94a3b8;">
    <div style="margin-bottom:6px;">
      <svg viewBox="0 0 40 40" fill="none" width="32" height="32" style="margin:0 auto;display:block;">
        <rect x="5" y="12" width="30" height="20" rx="3" fill="rgba(148,163,184,0.12)" stroke="#94a3b8" stroke-width="1.3"/>
        <rect x="5" y="7"  width="30" height="8"  rx="2" fill="rgba(148,163,184,0.20)" stroke="#94a3b8" stroke-width="1.3"/>
        <rect x="14" y="19" width="12" height="8" rx="1.5" fill="rgba(148,163,184,0.30)" stroke="#94a3b8" stroke-width="1"/>
        <line x1="5" y1="11" x2="35" y2="11" stroke="#94a3b8" stroke-width="0.8" opacity="0.5"/>
      </svg>
    </div>
    <div class="num"><?= $stats['selesai'] ?></div>
    <div class="lbl">Selesai</div>
  </a>

</div>

<!-- FILTER -->
<div class="filter-form fu3">
  <input type="text" id="searchInput" placeholder="Cari nama user atau judul buku..." value="<?= e($search) ?>" oninput="filterLive()">
  <select onchange="applyFilter('genre',this.value)">
    <option value="">Semua Genre</option>
    <?php foreach($semua_genre as $g): ?><option value="<?= $g ?>" <?= $genre===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?>
  </select>
  <div class="sort-tabs">
    <?php foreach([
      'terbaru' =>'<i class="fas fa-clock"></i> Terbaru',
      'expired' =>'<i class="fas fa-hourglass-end"></i> Jatuh Tempo',
      'user'    =>'<i class="fas fa-user"></i> User',
      'buku'    =>'<i class="fas fa-book"></i> Buku',
    ] as $k=>$lbl): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['sort'=>$k])) ?>" class="sort-tab <?= $sort===$k?'active':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- STATUS TABS -->
<div class="status-tabs fu3">
  <?php foreach([
    ''            =>'<i class="fas fa-list"></i> Semua',
    'aktif'       =>'<i class="fas fa-check-circle"></i> Aktif',
    'hampir_habis'=>'<i class="fas fa-hourglass-half"></i> Hampir Habis',
    'expired'     =>'<i class="fas fa-times-circle"></i> Expired',
    'dikembalikan'=>'<i class="fas fa-box-archive"></i> Selesai',
  ] as $sv=>$sl): ?>
    <a href="?<?= http_build_query(array_merge($_GET,['status'=>$sv])) ?>" class="status-tab <?= $status===$sv?'active':'' ?>"><?= $sl ?></a>
  <?php endforeach; ?>
</div>

<!-- RESULT COUNT -->
<div class="result-count fu4">Menampilkan <strong><?= count($peminjaman) ?></strong> peminjaman</div>

<!-- TABLE -->
<?php if($peminjaman): ?>
<div class="table-wrap fu5">
  <table class="pinjam-table" id="pinjamTable">
    <thead>
      <tr>
        <th style="width:40px;">#</th>
        <th>Mahasiswa</th>
        <th>Buku</th>
        <th>Tgl Pinjam</th>
        <th>Tgl Expired</th>
        <th style="text-align:center;width:70px;">Sisa</th>
        <th style="width:120px;">Status</th>
        <th style="text-align:center;width:80px;">Perpanjang</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($peminjaman as $i=>$p):
        $sisa = sisaHari($p['tanggal_expired']);
        $gw   = $genre_warna[$p['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
      ?>
      <tr data-nama="<?= strtolower($p['nama_user']) ?>" data-judul="<?= strtolower($p['judul']) ?>">
        <td style="color:rgba(255,255,255,0.40);font-size:12px;font-weight:700;"><?= $i+1 ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="avatar-mini"><?= strtoupper(substr($p['nama_user'],0,1)) ?></div>
            <div>
              <div style="font-weight:700;font-size:12px;"><?= e($p['nama_user']) ?></div>
              <div style="font-size:10px;color:rgba(255,255,255,0.40);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($p['email']) ?></div>
            </div>
          </div>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="mini-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
              <i class="fas <?= $gw['icon'] ?>" style="color:#fff;font-size:13px;"></i>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($p['judul']) ?></div>
              <div style="font-size:10px;color:rgba(255,255,255,0.45);"><?= e($p['genre']) ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:12px;color:rgba(255,255,255,0.55);white-space:nowrap;"><?= formatTanggal($p['tanggal_pinjam']) ?></td>
        <td style="font-size:12px;white-space:nowrap;color:<?= $sisa<=1?'#f87171':($sisa<=3?'#fbbf24':'rgba(255,255,255,0.55)') ?>;">
          <?= formatTanggal($p['tanggal_expired']) ?>
        </td>
        <td style="text-align:center;">
          <?php if(in_array($p['status'],['aktif','hampir_habis'])): ?>
            <div class="sisa-box" style="color:<?= $sisa<=1?'#f87171':($sisa<=3?'#fbbf24':'#4ade80') ?>;"><?= max($sisa,0) ?></div>
            <div style="font-size:10px;color:rgba(255,255,255,0.35);font-weight:700;">hari</div>
          <?php else: ?>
            <span style="color:rgba(255,255,255,0.20);"><i class="fas fa-minus"></i></span>
          <?php endif; ?>
        </td>
        <td>
          <?php
          $chipMap=[
            'aktif'       =>'<i class="fas fa-check"></i> Aktif',
            'hampir_habis'=>'<i class="fas fa-hourglass-half"></i> Hampir',
            'expired'     =>'<i class="fas fa-times"></i> Expired',
            'dikembalikan'=>'<i class="fas fa-archive"></i> Selesai',
          ];
          ?><span class="status-chip chip-<?= $p['status'] ?>"><?= $chipMap[$p['status']] ?? $p['status'] ?></span>
        </td>
        <td style="text-align:center;">
          <?php if($p['diperpanjang']): ?>
            <i class="fas fa-sync-alt" style="color:#60a5fa;font-size:14px;" title="Sudah diperpanjang"></i>
          <?php else: ?>
            <span style="color:rgba(255,255,255,0.20);font-size:12px;"><i class="fas fa-minus"></i></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty-state fu5">
  <i class="fas fa-exchange-alt"></i>
  <p>Tidak ada data peminjaman<?= $status?" dengan status \"$status\"":'' ?>.</p>
</div>
<?php endif; ?>

<!-- ANTRIAN -->
<?php if($total_antrian>0): ?>
<div style="margin-top:32px;position:relative;z-index:1;">
  <div class="page-header" style="margin-bottom:16px;">
    <h2 style="font-size:18px;"><i class="fas fa-list-ol"></i> Antrian Buku</h2>
    <div class="ph-sub"><?= $total_antrian ?> menunggu</div>
  </div>
  <?php
  $antrian = $pdo->query("
      SELECT a.*, u.nama AS nama_user, b.judul, b.genre, b.stok
      FROM antrian a JOIN users u ON a.user_id=u.id JOIN buku b ON a.buku_id=b.id
      WHERE a.status='menunggu' ORDER BY a.tanggal_daftar ASC LIMIT 10
  ")->fetchAll();
  ?>
  <div class="table-wrap">
    <table class="pinjam-table">
      <thead>
        <tr><th>#</th><th>User</th><th>Buku</th><th>Sejak</th><th>Stok</th></tr>
      </thead>
      <tbody>
        <?php foreach($antrian as $i=>$a):
          $gw = $genre_warna[$a['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
        ?>
        <tr>
          <td style="color:rgba(255,255,255,0.40);font-size:12px;font-weight:700;"><?= $i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="avatar-mini"><?= strtoupper(substr($a['nama_user'],0,1)) ?></div>
              <span style="font-size:12px;font-weight:700;"><?= e($a['nama_user']) ?></span>
            </div>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="mini-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
                <i class="fas <?= $gw['icon'] ?>" style="color:#fff;font-size:12px;"></i>
              </div>
              <div>
                <div style="font-size:12px;font-weight:700;"><?= e($a['judul']) ?></div>
                <div style="font-size:10px;color:rgba(255,255,255,0.45);"><?= e($a['genre']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:12px;color:rgba(255,255,255,0.50);"><?= formatTanggal($a['tanggal_daftar']) ?></td>
          <td>
            <span style="font-weight:800;font-size:13px;color:<?= $a['stok']>0?'#4ade80':'#f87171' ?>;">
              <i class="fas fa-<?= $a['stok']>0?'check-circle':'times-circle' ?>" style="font-size:11px;"></i>
              <?= $a['stok'] ?> stok
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div>
<footer class="footer" style="position:relative;z-index:1;background:rgba(0,0,0,0.35);border-top:1px solid rgba(255,255,255,0.10);color:rgba(255,255,255,0.50);">
  <p><i class="fas fa-cloud" style="color:#60a5fa;"></i> <span style="color:#fff;">CloudLibrary Mini</span> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?></p>
</footer>

<script>
function filterLive(){const q=document.getElementById('searchInput').value.toLowerCase();document.querySelectorAll('#pinjamTable tbody tr').forEach(r=>{r.style.display=(r.dataset.nama.includes(q)||r.dataset.judul.includes(q))?'':'none';});}
function applyFilter(key,val){const p=new URLSearchParams(window.location.search);if(val)p.set(key,val);else p.delete(key);window.location.search=p.toString();}
</script>
</body>
</html>