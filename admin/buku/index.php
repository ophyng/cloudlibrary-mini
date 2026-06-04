<?php
// ============================================
//  CloudLibrary Mini — Admin: Kelola Buku
//  File   : admin/buku/index.php
// ============================================
session_start();
require_once '../../includes/functions.php';
cekLoginAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($aksi === 'arsip'    && $id) { $pdo->prepare("UPDATE buku SET status = 'arsip'    WHERE id = ?")->execute([$id]); header('Location: index.php?msg=arsip');    exit; }
    if ($aksi === 'aktifkan' && $id) { $pdo->prepare("UPDATE buku SET status = 'tersedia' WHERE id = ?")->execute([$id]); header('Location: index.php?msg=aktif');    exit; }
    if ($aksi === 'featured' && $id) {
        $cur = $pdo->prepare("SELECT is_featured FROM buku WHERE id = ?"); $cur->execute([$id]); $cur = $cur->fetchColumn();
        $pdo->prepare("UPDATE buku SET is_featured = ? WHERE id = ?")->execute([$cur ? 0 : 1, $id]);
        header('Location: index.php?msg=featured'); exit;
    }
    if ($aksi === 'hapus' && $id) { $pdo->prepare("DELETE FROM buku WHERE id = ?")->execute([$id]); header('Location: index.php?msg=hapus'); exit; }
}

$search = trim($_GET['q'] ?? ''); $genre = $_GET['genre'] ?? ''; $tipe = $_GET['tipe'] ?? ''; $status = $_GET['status'] ?? ''; $sort = $_GET['sort'] ?? 'terbaru';
$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(b.judul LIKE ? OR b.penulis LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($genre)  { $where[] = "b.genre = ?"; $params[] = $genre; }
if ($tipe)   { $where[] = "b.tipe = ?";  $params[] = $tipe; }
if ($status) { $where[] = "b.status = ?"; $params[] = $status; }
else         { $where[] = "b.status != 'arsip'"; }
$order = match($sort) { 'az'=>'b.judul ASC','populer'=>'b.total_dipinjam DESC','rating'=>'avg_rating DESC',default=>'b.created_at DESC' };
$stmt = $pdo->prepare("SELECT b.*, IFNULL(AVG(r.rating),0) AS avg_rating, COUNT(r.id) AS jml_review FROM buku b LEFT JOIN review r ON r.buku_id = b.id AND r.status = 'tampil' WHERE ".implode(' AND ',$where)." GROUP BY b.id ORDER BY $order");
$stmt->execute($params); $buku_list = $stmt->fetchAll();
$semua_genre = $pdo->query("SELECT DISTINCT genre FROM buku ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);
$total_aktif = $pdo->query("SELECT COUNT(*) FROM buku WHERE status = 'tersedia'")->fetchColumn();
$total_arsip = $pdo->query("SELECT COUNT(*) FROM buku WHERE status = 'arsip'")->fetchColumn();
$total_semua = $pdo->query("SELECT COUNT(*) FROM buku")->fetchColumn();
$total_habis = $pdo->query("SELECT COUNT(*) FROM buku WHERE stok = 0 AND status != 'arsip'")->fetchColumn();
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
$msg = $_GET['msg'] ?? ''; $title = "Kelola Buku — Admin CloudLibrary Mini";
include '../../includes/navbar.php';
?>
<style>
body{
  background-color:#1e3a5f !important;
  background-image: url('library_bg.png') !important;
  background-size: cover !important;
  background-position: center center !important;
  background-attachment: fixed !important;
  background-repeat: no-repeat !important;
  min-height: 100vh !important;
}
body::before{
  content:'';
  position:fixed; inset:0; z-index:0;
  background: rgba(5, 15, 35, 0.60);
  pointer-events:none;
}
/* Pastikan main content area tidak punya background sendiri */
.main-wrap, .container, .content-wrap, main, #main {
  background: transparent !important;
}
:root{--card:rgba(255,255,255,0.15);--card-b:rgba(255,255,255,0.25);--text:#ffffff;--muted:rgba(255,255,255,0.70);--accent:#60a5fa;--accent2:#fbbf24;--success:#4ade80;--warning:#fbbf24;--danger:#f87171;--navy:#fff;--d2:#60a5fa;--d3:#93c5fd;--sh:0 4px 22px rgba(0,0,0,0.20);--sh-md:0 8px 32px rgba(0,0,0,0.30);}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:12px;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:#60a5fa;}
.ph-sub{font-size:12px;font-weight:700;color:rgba(255,255,255,0.7);background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.25);padding:6px 14px;border-radius:100px;}
.btn-tambah{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:8px;background:#2563eb;color:#fff;font-size:13px;font-weight:800;text-decoration:none;font-family:'Nunito',sans-serif;box-shadow:0 3px 12px rgba(37,99,211,0.40);transition:all .2s;}
.btn-tambah:hover{background:#1d4ed8;transform:translateY(-2px);}
.alert-bar{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:700;position:relative;z-index:1;backdrop-filter:blur(10px);}
.alert-success{background:rgba(74,222,128,0.15);border:1.5px solid rgba(74,222,128,0.30);color:#4ade80;}
.alert-info{background:rgba(96,165,250,0.15);border:1.5px solid rgba(96,165,250,0.30);color:#93c5fd;}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;position:relative;z-index:1;}
@media(max-width:800px){.stat-row{grid-template-columns:repeat(2,1fr)}}
.stat-mini{background:rgba(255,255,255,0.12);border:1.5px solid rgba(255,255,255,0.25);border-radius:12px;padding:16px 18px;text-decoration:none;color:inherit;box-shadow:var(--sh);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);}
.stat-mini:hover{transform:translateY(-2px);box-shadow:var(--sh-md);background:rgba(255,255,255,0.20);}
.stat-mini .num{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:#fff;}
.stat-mini .lbl{font-size:11px;font-weight:700;color:rgba(255,255,255,0.65);margin-top:2px;}
.filter-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;position:relative;z-index:1;}
.filter-form input[type=text]{flex:1;min-width:200px;background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.30);border-radius:8px;padding:9px 18px;font-size:13px;font-family:'Nunito',sans-serif;color:#fff;outline:none;box-shadow:var(--sh);transition:border-color .2s;backdrop-filter:blur(10px);}
.filter-form input[type=text]::placeholder{color:rgba(255,255,255,0.50);}
.filter-form input[type=text]:focus{border-color:rgba(255,255,255,0.60);}
.filter-form select{background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.30);border-radius:8px;padding:9px 16px;font-size:13px;font-family:'Nunito',sans-serif;color:#fff;outline:none;cursor:pointer;backdrop-filter:blur(10px);}
.filter-form select option{background:#1e3a5f;color:#fff;}
.status-tabs,.sort-tabs{display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;}
.status-tabs{margin-bottom:16px;}
.status-tab,.sort-tab{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.12);color:rgba(255,255,255,0.80);text-decoration:none;transition:all .18s;backdrop-filter:blur(8px);}
.status-tab:hover,.sort-tab:hover{background:rgba(255,255,255,0.22);color:#fff;border-color:rgba(255,255,255,0.45);}
.status-tab.active,.sort-tab.active{background:#2563eb;color:#fff;border-color:#2563eb;}
.result-count{font-size:13px;color:rgba(255,255,255,0.80);font-weight:700;margin-bottom:12px;position:relative;z-index:1;}
.table-wrap{background:rgba(255,255,255,0.12);border:1.5px solid rgba(255,255,255,0.22);border-radius:14px;overflow:hidden;overflow-x:auto;box-shadow:var(--sh);position:relative;z-index:1;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);}
.table-wrap::before{display:none;}
.buku-table{width:100%;border-collapse:collapse;font-size:13px;min-width:860px;}
.buku-table thead{background:rgba(255,255,255,0.18);}
.buku-table th{font-size:10px;font-weight:900;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:.6px;padding:12px 14px;text-align:left;border-bottom:1.5px solid rgba(255,255,255,0.20);white-space:nowrap;}
.buku-table td{padding:11px 14px;border-bottom:1px solid rgba(255,255,255,0.10);vertical-align:middle;color:#fff;}
.buku-table tr:last-child td{border-bottom:none;}
.buku-table tr:hover td{background:rgba(255,255,255,0.08);}
.buku-status{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;padding:3px 9px;border-radius:100px;text-transform:uppercase;white-space:nowrap;}
.status-tersedia{background:rgba(74,222,128,0.20);color:#4ade80;border:1px solid rgba(74,222,128,0.40);}
.status-habis{background:rgba(248,113,113,0.20);color:#f87171;border:1px solid rgba(248,113,113,0.40);}
.status-arsip{background:rgba(255,255,255,0.12);color:rgba(255,255,255,0.60);border:1px solid rgba(255,255,255,0.20);}
.mini-cover{width:32px;height:46px;border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:15px;box-shadow:0 2px 8px rgba(0,0,0,0.30);}
.act-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:8px;font-size:10px;font-weight:800;border:none;cursor:pointer;font-family:'Nunito',sans-serif;text-decoration:none;transition:all .18s;}
.act-edit{background:rgba(96,165,250,0.25);color:#93c5fd;border:1px solid rgba(96,165,250,0.40);}.act-edit:hover{background:rgba(96,165,250,0.40);}
.act-arsip{background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.75);border:1px solid rgba(255,255,255,0.25);}.act-arsip:hover{background:rgba(255,255,255,0.25);}
.act-aktif{background:rgba(74,222,128,0.20);color:#4ade80;border:1px solid rgba(74,222,128,0.35);}.act-aktif:hover{background:rgba(74,222,128,0.35);}
.act-hapus{background:rgba(248,113,113,0.20);color:#f87171;border:1px solid rgba(248,113,113,0.35);}.act-hapus:hover{background:rgba(248,113,113,0.35);}
.tipe-badge{font-size:10px;font-weight:800;padding:2px 8px;border-radius:6px;}
.tipe-fiksi{background:rgba(96,165,250,0.25);color:#93c5fd;}
.tipe-nonfiksi{background:rgba(251,191,36,0.25);color:#fde68a;}
.empty-state{text-align:center;padding:48px 20px;background:rgba(255,255,255,0.12);border:1.5px solid rgba(255,255,255,0.20);border-radius:14px;backdrop-filter:blur(12px);box-shadow:var(--sh);position:relative;z-index:1;}
.empty-state i{font-size:36px;color:rgba(255,255,255,0.50);display:block;margin-bottom:10px;}
.empty-state p{font-size:14px;color:rgba(255,255,255,0.70);font-weight:700;}
.empty-state a{color:#60a5fa;text-decoration:underline;}

/* ── HERO BANNER ── */
.hero-banner{
  position:relative;
  width:100%; height:260px;
  border-radius:18px; overflow:hidden;
  margin-bottom:28px;
  background-image: url('library_bg.png');
  background-size:cover; background-position:center 40%;
  box-shadow:0 8px 32px rgba(30,58,95,0.22);
}
.hero-overlay{
  position:absolute; inset:0;
  background:linear-gradient(
    to right,
    rgba(10,25,60,0.82) 0%,
    rgba(10,25,60,0.65) 50%,
    rgba(10,25,60,0.25) 100%
  );
}
.hero-content{
  position:relative; z-index:1;
  height:100%; padding:32px 40px;
  display:flex; align-items:center;
  justify-content:space-between; gap:20px;
}
.hero-left{ flex:1; }
.hero-kicker{
  font-size:11px; font-weight:800; letter-spacing:2.5px;
  text-transform:uppercase; color:rgba(255,255,255,0.55);
  margin-bottom:10px;
}
.hero-title{
  font-family:'Syne',sans-serif;
  font-size:clamp(26px,3vw,42px);
  font-weight:900; color:#fff;
  line-height:1.1; margin-bottom:10px;
}
.hero-title-accent{
  color:#60a5fa;
}
.hero-desc{
  font-size:13px; color:rgba(255,255,255,0.60);
  font-weight:600; line-height:1.7;
  max-width:420px; margin-bottom:20px;
}
.btn-hero{
  display:inline-flex; align-items:center; gap:8px;
  padding:11px 24px; border-radius:8px;
  background:#2563eb; color:#fff;
  font-size:13px; font-weight:800;
  text-decoration:none; font-family:'Nunito',sans-serif;
  box-shadow:0 4px 16px rgba(37,99,211,0.40);
  transition:all .2s;
}
.btn-hero:hover{ background:#1d4ed8; transform:translateY(-2px); }

/* Hero stats (kanan) */
.hero-stats{
  display:flex; flex-direction:column; gap:12px;
  background:rgba(255,255,255,0.10);
  border:1px solid rgba(255,255,255,0.18);
  border-radius:14px; padding:20px 24px;
  backdrop-filter:blur(12px);
  min-width:140px;
}
.hstat{ text-align:center; }
.hstat-num{
  font-family:'Syne',sans-serif;
  font-size:28px; font-weight:900; color:#fff; line-height:1;
}
.hstat-lbl{
  font-size:10px; font-weight:700;
  color:rgba(255,255,255,0.50);
  text-transform:uppercase; letter-spacing:1px;
  margin-top:3px;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}.fu4{animation:fadeUp .4s ease .28s both}.fu5{animation:fadeUp .4s ease .36s both}
</style>

<!-- PAGE CONTENT -->

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <div>
    <h2><i class="fas fa-book"></i> Kelola Buku</h2>
    <div style="font-size:12px;color:var(--muted);margin-top:4px;font-weight:600;">Total <?= $total_semua ?> buku dalam sistem</div>
  </div>
  <a href="tambah.php" class="btn-tambah"><i class="fas fa-plus"></i> Tambah Buku</a>
</div>

<!-- STAT CARDS -->
<div class="stat-row fu2">

  <!-- Total Buku — Rak buku + bintang emas -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>''])) ?>" class="stat-mini" style="border-top:3px solid #f9c74f;">
    <div style="display:flex;align-items:center;gap:14px;">
      <svg viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" width="48" height="48" style="flex-shrink:0;">
        <defs>
          <linearGradient id="g1a" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient>
        </defs>
        <!-- Rak bawah -->
        <rect x="4" y="42" width="48" height="5" rx="2" fill="url(#g1a)"/>
        <!-- Buku 1 tebal navy -->
        <rect x="6" y="18" width="13" height="24" rx="2" fill="#0f2744" stroke="url(#g1a)" stroke-width="1.2"/>
        <rect x="6" y="18" width="3.5" height="24" rx="1" fill="url(#g1a)" opacity="0.8"/>
        <line x1="13" y1="24" x2="17" y2="24" stroke="#fff" stroke-width="0.9" opacity="0.5"/>
        <line x1="13" y1="28" x2="17" y2="28" stroke="#fff" stroke-width="0.9" opacity="0.5"/>
        <!-- Buku 2 miring -->
        <rect x="21" y="22" width="9" height="20" rx="1.5" fill="#1e3a5f" stroke="url(#g1a)" stroke-width="1.1" transform="rotate(-6,25,32)"/>
        <!-- Buku 3 -->
        <rect x="32" y="14" width="15" height="28" rx="2" fill="#0f2744" stroke="url(#g1a)" stroke-width="1.3"/>
        <rect x="32" y="14" width="4" height="28" rx="1" fill="url(#g1a)" opacity="0.9"/>
        <line x1="39" y1="20" x2="45" y2="20" stroke="#fff" stroke-width="0.9" opacity="0.5"/>
        <line x1="39" y1="25" x2="45" y2="25" stroke="#fff" stroke-width="0.9" opacity="0.5"/>
        <line x1="39" y1="30" x2="43" y2="30" stroke="#fff" stroke-width="0.9" opacity="0.4"/>
        <!-- Bintang ornamen -->
        <polygon points="28,4 30,10 36,10 31,14 33,20 28,16 23,20 25,14 20,10 26,10" fill="url(#g1a)"/>
      </svg>
      <div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:#fff;line-height:1;"><?= $total_semua ?></div>
        <div style="font-size:11px;font-weight:700;color:#f9c74f;margin-top:3px;letter-spacing:1px;text-transform:uppercase;">Total Buku</div>
      </div>
    </div>
  </a>

  <!-- Tersedia — Mahkota emas -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'tersedia'])) ?>" class="stat-mini" style="border-top:3px solid #f9c74f;">
    <div style="display:flex;align-items:center;gap:14px;">
      <svg viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" width="48" height="48" style="flex-shrink:0;">
        <defs>
          <linearGradient id="g2a" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient>
        </defs>
        <!-- Mahkota -->
        <path d="M8,38 L10,18 L22,30 L28,10 L34,30 L46,18 L48,38 Z" fill="url(#g2a)" stroke="#d4a017" stroke-width="1" stroke-linejoin="round"/>
        <!-- Alas mahkota -->
        <rect x="6" y="36" width="44" height="10" rx="3" fill="url(#g2a)" stroke="#d4a017" stroke-width="0.8"/>
        <!-- Permata tengah -->
        <circle cx="28" cy="22" r="5" fill="#fff" opacity="0.95"/>
        <circle cx="28" cy="22" r="3" fill="#f9c74f"/>
        <!-- Permata kiri kanan -->
        <circle cx="13" cy="30" r="3" fill="#fff" opacity="0.8"/>
        <circle cx="43" cy="30" r="3" fill="#fff" opacity="0.8"/>
        <!-- Centang di alas -->
        <polyline points="20,40 26,44 36,37" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
      <div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:#fff;line-height:1;"><?= $total_aktif ?></div>
        <div style="font-size:11px;font-weight:700;color:#f9c74f;margin-top:3px;letter-spacing:1px;text-transform:uppercase;">Tersedia</div>
      </div>
    </div>
  </a>

  <!-- Stok Habis — Jam pasir emas -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>''])) ?>" class="stat-mini" style="border-top:3px solid #f9c74f;">
    <div style="display:flex;align-items:center;gap:14px;">
      <svg viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" width="48" height="48" style="flex-shrink:0;">
        <defs>
          <linearGradient id="g3a" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient>
        </defs>
        <!-- Batang atas bawah -->
        <rect x="10" y="4" width="36" height="7" rx="3" fill="url(#g3a)"/>
        <rect x="10" y="45" width="36" height="7" rx="3" fill="url(#g3a)"/>
        <!-- Badan jam pasir -->
        <path d="M14,11 L42,11 L30,28 L42,45 L14,45 L26,28 Z" fill="#0f2744" stroke="url(#g3a)" stroke-width="1.3" stroke-linejoin="round"/>
        <!-- Pasir atas sedikit -->
        <path d="M15,11 L41,11 L29,22 L27,22 Z" fill="url(#g3a)" opacity="0.75"/>
        <!-- Pasir bawah menumpuk -->
        <path d="M27,34 L29,34 L40,45 L16,45 Z" fill="url(#g3a)" opacity="0.90"/>
        <!-- Titik tengah -->
        <circle cx="28" cy="28" r="3" fill="url(#g3a)"/>
        <!-- Garis dashed samping -->
        <line x1="8" y1="11" x2="8" y2="45" stroke="#f9c74f" stroke-width="0.7" stroke-dasharray="3,3" opacity="0.4"/>
        <line x1="48" y1="11" x2="48" y2="45" stroke="#f9c74f" stroke-width="0.7" stroke-dasharray="3,3" opacity="0.4"/>
      </svg>
      <div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:#fff;line-height:1;"><?= $total_habis ?></div>
        <div style="font-size:11px;font-weight:700;color:#f9c74f;margin-top:3px;letter-spacing:1px;text-transform:uppercase;">Stok Habis</div>
      </div>
    </div>
  </a>

  <!-- Diarsipkan — Dokumen dengan pita emas -->
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'arsip'])) ?>" class="stat-mini" style="border-top:3px solid #f9c74f;">
    <div style="display:flex;align-items:center;gap:14px;">
      <svg viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" width="48" height="48" style="flex-shrink:0;">
        <defs>
          <linearGradient id="g4a" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#d4a017"/></linearGradient>
        </defs>
        <!-- Dokumen -->
        <rect x="10" y="4" width="32" height="42" rx="3" fill="#0f2744" stroke="url(#g4a)" stroke-width="1.3"/>
        <!-- Lipatan pojok -->
        <path d="M30,4 L42,16 L30,16 Z" fill="#1e3a5f" stroke="url(#g4a)" stroke-width="1"/>
        <path d="M30,4 L42,16" stroke="url(#g4a)" stroke-width="1.3"/>
        <!-- Garis isi dokumen -->
        <line x1="16" y1="22" x2="28" y2="22" stroke="#fff" stroke-width="1" opacity="0.6"/>
        <line x1="16" y1="27" x2="30" y2="27" stroke="#fff" stroke-width="1" opacity="0.6"/>
        <line x1="16" y1="32" x2="24" y2="32" stroke="#fff" stroke-width="1" opacity="0.4"/>
        <!-- Pita emas -->
        <rect x="8" y="34" width="40" height="8" rx="2" fill="url(#g4a)"/>
        <!-- Simpul pita -->
        <path d="M24,38 C18,32 18,44 24,38" fill="url(#g4a)" stroke="#0f172a" stroke-width="0.8"/>
        <path d="M32,38 C38,32 38,44 32,38" fill="url(#g4a)" stroke="#0f172a" stroke-width="0.8"/>
        <circle cx="28" cy="38" r="3.5" fill="#d4a017" stroke="#0f172a" stroke-width="0.8"/>
        <!-- Ekor pita -->
        <path d="M22,42 L16,50" stroke="url(#g4a)" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M34,42 L40,50" stroke="url(#g4a)" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
      <div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:#fff;line-height:1;"><?= $total_arsip ?></div>
        <div style="font-size:11px;font-weight:700;color:#f9c74f;margin-top:3px;letter-spacing:1px;text-transform:uppercase;">Diarsipkan</div>
      </div>
    </div>
  </a>

</div>

<!-- ALERT -->
<?php
$msgs=['arsip'=>[false,'Buku diarsipkan.'],'aktif'=>[true,'Buku diaktifkan.'],'featured'=>[true,'Status Unggulan diperbarui.'],'hapus'=>[false,'Buku dihapus permanen.'],'tambah'=>[true,'Buku berhasil ditambahkan.'],'edit'=>[true,'Buku berhasil diperbarui.']];
if ($msg && isset($msgs[$msg])): [$ok,$txt]=$msgs[$msg]; ?>
<div class="alert-bar <?= $ok?'alert-success':'alert-info' ?> fu1">
  <i class="fas fa-<?= $ok?'check-circle':'info-circle' ?>"></i> <?= $txt ?>
</div>
<?php endif; ?>

<!-- FILTER -->
<div class="filter-form fu3">
  <input type="text" id="searchInput" placeholder="Cari judul atau penulis..." value="<?= e($search) ?>" oninput="filterLive()">
  <select onchange="applyFilter('genre',this.value)">
    <option value="">Semua Genre</option>
    <?php foreach($semua_genre as $g): ?><option value="<?= $g ?>" <?= $genre===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?>
  </select>
  <select onchange="applyFilter('tipe',this.value)">
    <option value="">Semua Tipe</option>
    <option value="fiksi"     <?= $tipe==='fiksi'    ?'selected':'' ?>>Fiksi</option>
    <option value="non-fiksi" <?= $tipe==='non-fiksi'?'selected':'' ?>>Non-Fiksi</option>
  </select>
  <div class="sort-tabs">
    <?php foreach(['terbaru'=>'<i class="fas fa-clock"></i> Terbaru','az'=>'<i class="fas fa-sort-alpha-down"></i> A–Z','populer'=>'<i class="fas fa-fire"></i> Populer','rating'=>'<i class="fas fa-star"></i> Rating'] as $k=>$lbl): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['sort'=>$k])) ?>" class="sort-tab <?= $sort===$k?'active':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- STATUS TABS -->
<div class="status-tabs fu3">
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>''])) ?>" class="status-tab <?= !$status?'active':'' ?>"><i class="fas fa-book"></i> Aktif (<?= $total_aktif ?>)</a>
  <a href="?<?= http_build_query(array_merge($_GET,['status'=>'arsip'])) ?>" class="status-tab <?= $status==='arsip'?'active':'' ?>"><i class="fas fa-archive"></i> Arsip (<?= $total_arsip ?>)</a>
</div>

<!-- RESULT COUNT -->
<div class="result-count fu4">Menampilkan <strong><?= count($buku_list) ?></strong> buku</div>

<!-- TABLE -->
<?php if($buku_list): ?>
<div class="table-wrap fu5">
  <table class="buku-table" id="bukuTable">
    <thead>
      <tr>
        <th style="width:40px;">#</th>
        <th>Buku</th>
        <th>Genre / Tipe</th>
        <th style="text-align:center;width:60px;">Stok</th>
        <th style="text-align:center;width:70px;">Pinjam</th>
        <th style="text-align:center;width:100px;">Rating</th>
        <th style="width:110px;">Status</th>
        <th style="text-align:center;width:70px;"><i class="fas fa-star"></i></th>
        <th style="width:160px;">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($buku_list as $i=>$b):
        $gw=$genre_warna[$b['genre']]??['bg'=>'#1e3a5f','icon'=>'📚']; ?>
      <tr data-judul="<?= strtolower($b['judul']) ?>" data-penulis="<?= strtolower($b['penulis']) ?>">
        <td style="color:rgba(255,255,255,0.55);font-size:12px;font-weight:700;text-align:center;"><?= $i+1 ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <?php if(!empty($b['cover'])): ?>
            <div class="mini-cover" style="background:#0f2744;padding:0;overflow:hidden;">
              <img src="/Web_Cloud_Computing/uploads/covers/<?= e($b['cover']) ?>"
                   style="width:100%;height:100%;object-fit:cover;border-radius:6px;" alt="cover">
            </div>
            <?php else: ?>
            <div class="mini-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
              <i class="fas <?= $gw['icon'] ?>" style="color:#fff;font-size:14px;"></i>
            </div>
            <?php endif; ?>
            <div>
              <div style="font-weight:800;font-size:12px;color:#fff;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($b['judul']) ?></div>
              <div style="font-size:10px;color:rgba(255,255,255,0.60);font-weight:600;"><?= e($b['penulis']) ?></div>
              <?php if(!empty($b['tahun'])): ?><div style="font-size:10px;color:rgba(255,255,255,0.35);"><?= $b['tahun'] ?></div><?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:4px;">
            <i class="fas <?= $gw['icon'] ?>" style="color:<?= $gw['bg'] ?>;width:14px;filter:brightness(1.5);"></i> <?= e($b['genre']) ?>
          </div>
          <span class="tipe-badge <?= $b['tipe']==='fiksi'?'tipe-fiksi':'tipe-nonfiksi' ?>"><?= strtoupper($b['tipe']) ?></span>
        </td>
        <td style="text-align:center;">
          <span style="font-family:'Syne',sans-serif;font-size:20px;font-weight:900;color:<?= $b['stok']>0?'#4ade80':'#f87171' ?>;"><?= $b['stok'] ?></span>
        </td>
        <td style="text-align:center;">
          <span style="font-family:'Syne',sans-serif;font-size:18px;font-weight:900;color:#93c5fd;"><?= $b['total_dipinjam'] ?></span>
          <div style="font-size:9px;color:rgba(255,255,255,0.45);font-weight:700;">×</div>
        </td>
        <td style="text-align:center;">
          <?php if($b['jml_review']>0): ?>
            <div style="color:#fbbf24;font-size:12px;font-weight:800;"><i class="fas fa-star"></i> <?= number_format($b['avg_rating'],1) ?></div>
            <div style="font-size:10px;color:rgba(255,255,255,0.50);font-weight:600;"><?= $b['jml_review'] ?> ulasan</div>
          <?php else: ?><span style="color:rgba(255,255,255,0.25);font-size:13px;"><i class="fas fa-minus"></i></span><?php endif; ?>
        </td>
        <td>
          <?php
          $stcls=match($b['status']){'tersedia'=>'status-tersedia','arsip'=>'status-arsip',default=>'status-habis'};
          $stlbl=match($b['status']){'tersedia'=>'<i class="fas fa-check-circle"></i> Tersedia','arsip'=>'<i class="fas fa-archive"></i> Arsip',default=>'<i class="fas fa-times-circle"></i> Habis'};
          ?><span class="buku-status <?= $stcls ?>"><?= $stlbl ?></span>
        </td>
        <td style="text-align:center;">
          <form method="POST" style="display:inline;margin:0;">
            <input type="hidden" name="aksi" value="featured"><input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button type="submit" style="background:none;border:none;cursor:pointer;font-size:16px;padding:2px;color:<?= $b['is_featured']?'#d97706':'#cbd5e1' ?>;" title="<?= $b['is_featured']?'Hapus Unggulan':'Jadikan Unggulan' ?>">
              <i class="fas fa-star"></i>
            </button>
          </form>
        </td>
        <td>
          <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">
            <a href="edit.php?id=<?= $b['id'] ?>" class="act-btn act-edit"><i class="fas fa-edit"></i> Edit</a>
            <?php if($b['status']!=='arsip'): ?>
            <form method="POST" style="margin:0;"><input type="hidden" name="aksi" value="arsip"><input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button type="submit" class="act-btn act-arsip"><i class="fas fa-archive"></i> Arsip</button></form>
            <?php else: ?>
            <form method="POST" style="margin:0;"><input type="hidden" name="aksi" value="aktifkan"><input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button type="submit" class="act-btn act-aktif"><i class="fas fa-undo"></i> Aktifkan</button></form>
            <?php endif; ?>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Hapus buku ini permanen?')">
              <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button type="submit" class="act-btn act-hapus"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty-state fu5">
  <i class="fas fa-book"></i>
  <p>Tidak ada buku ditemukan.<br><a href="tambah.php">Tambah buku pertama →</a></p>
</div>
<?php endif; ?>

</div>
<footer class="footer" style="position:relative;z-index:1;background:rgba(0,0,0,0.35);border-top:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.60);">
  <p><i class="fas fa-cloud" style="color:#60a5fa;"></i> <span style="color:#fff;">CloudLibrary Mini</span> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?></p>
</footer>
<script>
function filterLive(){const q=document.getElementById('searchInput').value.toLowerCase();document.querySelectorAll('#bukuTable tbody tr').forEach(row=>{row.style.display=(row.dataset.judul.includes(q)||row.dataset.penulis.includes(q))?'':'none';});}
function applyFilter(key,val){const p=new URLSearchParams(window.location.search);if(val)p.set(key,val);else p.delete(key);window.location.search=p.toString();}
</script>
</body>
</html>