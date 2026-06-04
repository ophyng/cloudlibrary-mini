<?php
// ============================================
//  CloudLibrary Mini — Dashboard Mahasiswa
//  File   : mahasiswa/dashboard.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();
updateStatusPeminjaman($pdo);

$user_id = $_SESSION['user_id'];

$genre_warna = [
    'Novel'    => ['bg'=>'#1a237e','icon'=>'fa-book',       'color'=>'#7986cb'],
    'Cerpen'   => ['bg'=>'#4a148c','icon'=>'fa-scroll',     'color'=>'#ce93d8'],
    'Fantasi'  => ['bg'=>'#1b5e20','icon'=>'fa-hat-wizard', 'color'=>'#81c784'],
    'Romance'  => ['bg'=>'#880e4f','icon'=>'fa-heart',      'color'=>'#f48fb1'],
    'Horror'   => ['bg'=>'#b71c1c','icon'=>'fa-ghost',      'color'=>'#ef9a9a'],
    'Misteri'  => ['bg'=>'#e65100','icon'=>'fa-user-secret','color'=>'#ffcc80'],
    'Sci-Fi'   => ['bg'=>'#006064','icon'=>'fa-rocket',     'color'=>'#80deea'],
    'Filsafat' => ['bg'=>'#37474f','icon'=>'fa-landmark',   'color'=>'#b0bec5'],
    'Sains'    => ['bg'=>'#1565c0','icon'=>'fa-flask',      'color'=>'#90caf9'],
    'Biografi' => ['bg'=>'#4e342e','icon'=>'fa-feather-alt','color'=>'#bcaaa4'],
];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]); $user = $stmt->fetch();

$pinjam = $pdo->prepare("SELECT p.*, b.judul, b.penulis, b.genre, b.cover FROM peminjaman p JOIN buku b ON p.buku_id = b.id WHERE p.user_id = ? AND p.status IN ('aktif','hampir_habis') ORDER BY p.tanggal_expired ASC");
$pinjam->execute([$user_id]); $pinjaman_aktif = $pinjam->fetchAll();

$total_dibaca = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id = ? AND status IN ('dikembalikan','expired')");
$total_dibaca->execute([$user_id]); $total_dibaca = $total_dibaca->fetchColumn();

$total_review = $pdo->prepare("SELECT COUNT(*) FROM review WHERE user_id = ?");
$total_review->execute([$user_id]); $total_review = $total_review->fetchColumn();

$total_wishlist = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$total_wishlist->execute([$user_id]); $total_wishlist = $total_wishlist->fetchColumn();

$badge_stmt = $pdo->prepare("SELECT b.* FROM user_badge ub JOIN badge b ON ub.badge_id = b.id WHERE ub.user_id = ?");
$badge_stmt->execute([$user_id]); $badges = $badge_stmt->fetchAll();

$trending = $pdo->query("SELECT * FROM buku WHERE is_featured = 1 AND status = 'tersedia' ORDER BY total_dipinjam DESC LIMIT 5")->fetchAll();

$fav_genre = $pdo->prepare("SELECT b.genre, COUNT(*) as total FROM peminjaman p JOIN buku b ON p.buku_id = b.id WHERE p.user_id = ? GROUP BY b.genre ORDER BY total DESC LIMIT 1");
$fav_genre->execute([$user_id]); $fav = $fav_genre->fetch();

$rekomendasi = [];
if ($fav) {
    $rek = $pdo->prepare("SELECT * FROM buku WHERE genre = ? AND status = 'tersedia' AND id NOT IN (SELECT buku_id FROM peminjaman WHERE user_id = ?) LIMIT 5");
    $rek->execute([$fav['genre'], $user_id]); $rekomendasi = $rek->fetchAll();
}

function getLevel($poin) {
    if ($poin >= 500) return ['name'=>'Legenda','fa'=>'fa-crown',    'color'=>'#f59e0b','min'=>500,'next'=>null];
    if ($poin >= 200) return ['name'=>'Master', 'fa'=>'fa-gem',      'color'=>'#06b6d4','min'=>200,'next'=>500];
    if ($poin >= 100) return ['name'=>'Ahli',   'fa'=>'fa-trophy',   'color'=>'#f97316','min'=>100,'next'=>200];
    if ($poin >= 50)  return ['name'=>'Aktif',  'fa'=>'fa-fire',     'color'=>'#3b82f6','min'=>50, 'next'=>100];
    if ($poin >= 20)  return ['name'=>'Pemula', 'fa'=>'fa-star',     'color'=>'#22c55e','min'=>20, 'next'=>50];
    return                   ['name'=>'Baru',   'fa'=>'fa-seedling', 'color'=>'#94a3b8','min'=>0,  'next'=>20];
}
$level = getLevel($user['poin']);
$progress_pct = $level['next'] ? min(100, round(($user['poin']-$level['min'])/($level['next']-$level['min'])*100)) : 100;

$title = "Dashboard — CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
/* ── BACKGROUND ── */
body {
  background-image: url('gambar perpustakaan.jpg') !important;
  background-size: cover !important;
  background-position: center top !important;
  background-attachment: fixed !important;
  overflow-x: hidden;
}
body::before {
  content: ''; position: fixed; inset: 0; z-index: 0;
  background: rgba(215, 232, 250, 0.12);
  pointer-events: none;
}

/* ── LAYOUT — 1 kolom seperti katalog ── */
.dash-wrap {
  position: relative; z-index: 1;
  max-width: 1180px;
  margin: 0 auto;
  padding: 28px 24px 60px;
  /* CSS vars scoped di sini, tidak override navbar */
  --text:  #0a1628;
  --sub:   #2d4a6e;
  --muted: #64748b;
  --d1:    #0f2744;
  --d2:    #1e4a82;
  --d3:    #3a6186;
  --gold:  #d97706;
  --g1:    rgba(255,255,255,0.80);
  --g2:    rgba(255,255,255,0.92);
  --gb:    rgba(30,58,95,0.09);
  --sh:    0 1px 12px rgba(10,22,40,0.08);
  --shm:   0 4px 24px rgba(10,22,40,0.13);
}

/* ══════════════════════════
   SIDEBAR — disembunyikan
   (navbar sudah punya sidebar)
══════════════════════════ */
.dash-sb { display: none !important; }
.dash-main { padding: 0; }

/* user card */
.d-user {
  background: var(--g1);
  border: 1px solid var(--gb);
  border-radius: 16px; padding: 18px 16px;
  backdrop-filter: blur(20px); box-shadow: var(--sh);
}
.d-avatar {
  width: 48px; height: 48px; border-radius: 50%;
  background: linear-gradient(135deg, #1e3a7a, #3b82f6);
  border: 2.5px solid rgba(255,255,255,0.85);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-size: 19px; font-weight: 900; color: #fff;
  box-shadow: 0 3px 12px rgba(37,99,235,0.22);
  margin-bottom: 10px;
}
.d-name { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 900; color: var(--text); margin-bottom: 1px; }
.d-role { font-size: 11px; color: var(--muted); margin-bottom: 10px; }
.d-pts  {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 700; color: var(--gold);
  background: rgba(217,119,6,0.08); border: 1px solid rgba(217,119,6,0.16);
  padding: 3px 10px; border-radius: 100px; margin-bottom: 12px;
}
.d-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.d-stat  {
  background: rgba(255,255,255,0.60); border: 1px solid var(--gb);
  border-radius: 9px; padding: 8px 10px; text-align: center;
}
.d-stat .n { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 900; color: var(--text); line-height: 1; }
.d-stat .l { font-size: 9px; color: var(--muted); font-weight: 600; margin-top: 2px; text-transform: uppercase; letter-spacing: .4px; }

/* level card */
.d-level {
  background: var(--g1); border: 1px solid var(--gb);
  border-left: 3px solid; border-radius: 0 14px 14px 0;
  padding: 14px 14px; backdrop-filter: blur(20px); box-shadow: var(--sh);
}
.lv-top  { display: flex; align-items: center; gap: 9px; margin-bottom: 11px; }
.lv-ico  { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.lv-name { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 900; }
.lv-pts  { font-size: 10px; color: var(--muted); font-weight: 500; margin-top: 1px; }
.lv-bar  { height: 4px; background: rgba(10,22,40,0.08); border-radius: 2px; overflow: hidden; margin-bottom: 4px; }
.lv-fill { height: 100%; border-radius: 2px; transition: width 1s ease; }
.lv-range{ display: flex; justify-content: space-between; font-size: 9px; color: var(--muted); font-weight: 600; }

/* nav */
.d-nav {
  background: var(--g1); border: 1px solid var(--gb);
  border-radius: 14px; overflow: hidden;
  backdrop-filter: blur(20px); box-shadow: var(--sh);
}
.d-nav-hd { padding: 10px 14px 6px; font-size: 9px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .8px; }
/* scope ke .dash-sb agar tidak mempengaruhi navbar */
.d-nav .d-link {
  display: flex; align-items: center; gap: 9px;
  padding: 10px 14px; font-size: 12px; font-weight: 600;
  color: var(--sub); text-decoration: none;
  border-top: 1px solid rgba(30,58,95,0.05);
  transition: all 0.15s;
}
.d-nav .d-link:hover { background: rgba(37,99,235,0.05); color: var(--d2); padding-left: 18px; }
.d-nav .d-link-ico { width: 26px; height: 26px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }

/* quote */
.d-quote {
  background: linear-gradient(145deg, rgba(8,20,40,0.86), rgba(15,36,68,0.80));
  border: 1px solid rgba(255,255,255,0.09);
  border-radius: 14px; padding: 16px;
  backdrop-filter: blur(20px);
  position: relative; overflow: hidden;
}
.d-quote::before { content: '\201C'; position: absolute; top: -10px; left: 8px; font-size: 60px; color: rgba(255,255,255,0.04); font-family: Georgia, serif; line-height: 1; }
.d-qt   { font-size: 11px; color: rgba(255,255,255,0.70); line-height: 1.8; font-style: italic; font-weight: 400; position: relative; z-index: 1; }
.d-qa { font-size: 10px; color: rgba(255,255,255,0.28); font-weight: 600; margin-top: 7px; position: relative; z-index: 1; }

/* ══════════════════════════
   MAIN CONTENT
══════════════════════════ */
.dash-main { padding: 20px 24px 40px; }

/* HERO — ramping */
.hero {
  background: linear-gradient(145deg, rgba(7,21,40,0.88), rgba(15,36,68,0.82) 55%, rgba(26,74,138,0.68));
  border: 1px solid rgba(255,255,255,0.11);
  border-radius: 20px; padding: 28px 32px;
  margin-bottom: 14px;
  backdrop-filter: blur(28px); box-shadow: var(--shm);
  position: relative; overflow: hidden;
}
.hero::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.22), transparent); }
.hero::after  { content: ''; position: absolute; top: -50px; right: -50px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(219,39,119,0.09), transparent 60%); pointer-events: none; }
.hero-ey {
  font-size: 10px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;
  color: rgba(219,39,119,0.70); margin-bottom: 8px;
  display: flex; align-items: center; gap: 8px; position: relative; z-index: 1;
}
.hero-ey span { display: block; width: 18px; height: 1.5px; background: rgba(219,39,119,0.55); border-radius: 1px; }
.hero-title {
  font-family: 'Syne', sans-serif;
  font-size: clamp(20px, 2.4vw, 30px);
  font-weight: 900; color: #fff; line-height: 1.1;
  margin-bottom: 8px; position: relative; z-index: 1;
}
.hero-title .grad {
  background: linear-gradient(90deg, #f472b6, #a855f7, #60a5fa);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.hero-sub { font-size: 12px; color: rgba(255,255,255,0.45); line-height: 1.65; margin-bottom: 18px; position: relative; z-index: 1; }
.hero-row { display: flex; gap: 9px; flex-wrap: wrap; position: relative; z-index: 1; }
.h-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 18px; border-radius: 100px;
  font-size: 12px; font-weight: 700; text-decoration: none;
  font-family: 'Nunito', sans-serif; transition: all 0.2s;
}
.h-btn.p { background: linear-gradient(135deg, #db2777, #a855f7); color: #fff; box-shadow: 0 3px 14px rgba(168,85,247,0.30); }
.h-btn.p:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(168,85,247,0.42); }
.h-btn.g { background: rgba(255,255,255,0.09); border: 1px solid rgba(255,255,255,0.19); color: rgba(255,255,255,0.82); }
.h-btn.g:hover { background: rgba(255,255,255,0.15); }

/* STAT CARDS — 4 kolom */
.stats-row {
  display: grid; grid-template-columns: repeat(4,1fr); gap: 10px;
  margin-bottom: 16px;
}
.sc {
  background: var(--g1); border: 1px solid var(--gb);
  border-radius: 14px; padding: 14px;
  backdrop-filter: blur(18px); box-shadow: var(--sh);
  border-top: 2.5px solid;
  transition: transform 0.2s, box-shadow 0.2s;
}
.sc:hover { transform: translateY(-2px); box-shadow: var(--shm); }
.sc-ico { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; margin-bottom: 10px; }
.sc-num { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); line-height: 1; }
.sc-lbl { font-size: 10px; color: var(--muted); font-weight: 600; margin-top: 3px; text-transform: uppercase; letter-spacing: .4px; }

/* SECTION */
.sect {
  background: var(--g1); border: 1px solid var(--gb);
  border-radius: 16px; padding: 18px 20px;
  backdrop-filter: blur(18px); box-shadow: var(--sh);
  margin-bottom: 14px;
}
.sect-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.sect-ttl { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 900; color: var(--text); display: flex; align-items: center; gap: 8px; }
.sect-ico { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
.sect-more { font-size: 11px; font-weight: 700; color: var(--d2); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 100px; background: rgba(37,99,235,0.07); border: 1px solid rgba(37,99,235,0.13); transition: all 0.2s; }
.sect-more:hover { background: var(--d2); color: #fff; }

/* BORROW — compact */
.borrow-list { display: flex; flex-direction: column; gap: 8px; }
.borrow-item {
  background: rgba(255,255,255,0.58); border: 1px solid rgba(255,255,255,0.85);
  border-radius: 12px; padding: 12px 14px;
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  transition: all 0.2s;
}
.borrow-item:hover { background: rgba(255,255,255,0.80); transform: translateY(-1px); }
.borrow-item.warn { border-color: rgba(217,119,6,0.25); background: rgba(255,251,235,0.70); }
.bc-cover {
  width: 38px; height: 54px; border-radius: 7px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; color: #fff;
  box-shadow: 1px 2px 10px rgba(0,0,0,0.18); overflow: hidden;
}
.bc-info { flex: 1; min-width: 120px; }
.bc-tag  { font-size: 9px; font-weight: 700; padding: 1px 7px; border-radius: 4px; background: rgba(37,99,235,0.09); color: var(--d2); display: inline-block; margin-bottom: 3px; text-transform: uppercase; letter-spacing: .4px; }
.bc-title  { font-family: 'Syne', sans-serif; font-size: 12px; font-weight: 900; color: var(--text); margin-bottom: 1px; }
.bc-author { font-size: 10px; color: var(--muted); display: flex; align-items: center; gap: 3px; }
.bc-days { text-align: center; flex-shrink: 0; }
.bc-days .n { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 900; line-height: 1; }
.bc-days .l { font-size: 9px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
.bc-warn { background: rgba(217,119,6,0.09); border: 1px solid rgba(217,119,6,0.20); border-radius: 7px; padding: 4px 9px; font-size: 10px; color: #92400e; font-weight: 700; flex-shrink: 0; display: flex; align-items: center; gap: 4px; }
.bc-acts { display: flex; gap: 6px; flex-shrink: 0; }
.bc-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 7px 12px; border-radius: 100px;
  font-size: 11px; font-weight: 700; text-decoration: none;
  font-family: 'Nunito', sans-serif; transition: all 0.2s;
}
.bc-btn.r { background: linear-gradient(135deg, #1e3a7a, #2563eb); color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,0.25); }
.bc-btn.r:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,99,235,0.38); }
.bc-btn.e { background: rgba(255,255,255,0.60); border: 1px solid rgba(30,58,95,0.13); color: var(--sub); }
.bc-btn.e:hover { background: rgba(255,255,255,0.90); color: var(--text); }

/* BOOK GRID — 5 kolom */
.book-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
@media(max-width:1200px){ .book-grid{ grid-template-columns: repeat(4,1fr); } }
@media(max-width:900px){  .book-grid{ grid-template-columns: repeat(3,1fr); } }
.bk {
  background: rgba(255,255,255,0.68); border: 1px solid rgba(255,255,255,0.88);
  border-radius: 13px; overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}
.bk:hover { transform: translateY(-4px); box-shadow: var(--shm); }
.bk-cover {
  height: 110px; position: relative; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: #fff;
}
.bk-tipe { position: absolute; top: 6px; left: 6px; z-index: 2; font-size: 8px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: .3px; }
.tipe-fiksi    { background: rgba(219,39,119,0.88); color: #fff; }
.tipe-nonfiksi { background: rgba(37,99,235,0.88); color: #fff; }
.bk-body  { padding: 9px 11px; }
.bk-genre { font-size: 9px; font-weight: 700; color: var(--d3); margin-bottom: 3px; text-transform: uppercase; letter-spacing: .3px; }
.bk-title { font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 900; color: var(--text); line-height: 1.3; margin-bottom: 2px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bk-author{ font-size: 10px; color: var(--muted); margin-bottom: 7px; }
.bk-btn   { display: block; text-align: center; padding: 5px; border-radius: 7px; background: linear-gradient(135deg, var(--d1), var(--d2)); color: #fff; font-size: 10px; font-weight: 700; text-decoration: none; transition: opacity 0.2s; font-family: 'Nunito', sans-serif; }
.bk-btn:hover { opacity: 0.85; }

/* BADGE */
.badge-strip { display: flex; gap: 7px; flex-wrap: wrap; }
.badge-chip  { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.60); border: 1px solid rgba(217,119,6,0.18); border-radius: 9px; padding: 7px 12px; font-size: 11px; font-weight: 700; color: var(--text); transition: transform 0.2s; }
.badge-chip:hover { transform: translateY(-2px); }
.badge-chip i { color: var(--gold); font-size: 12px; }

/* EMPTY */
.empty { text-align: center; padding: 36px 20px; }
.empty i { font-size: 28px; color: var(--muted); opacity: .35; display: block; margin-bottom: 10px; }
.empty p { font-size: 13px; color: var(--muted); line-height: 1.7; }
.empty a { color: var(--d2); font-weight: 700; text-decoration: none; }

/* FOOTER */
footer.d-foot {
  position: relative; z-index: 1; text-align: center; padding: 20px;
  font-size: 11px; color: var(--muted); font-weight: 600;
  background: rgba(255,255,255,0.42); backdrop-filter: blur(16px);
  border-top: 1px solid rgba(30,58,95,0.07);
}

/* ANIM */
@keyframes fu { from{ opacity:0; transform:translateY(12px); } to{ opacity:1; transform:translateY(0); } }
.fu  { animation: fu 0.40s ease both; }
.fu1 { animation-delay:.04s; } .fu2 { animation-delay:.09s; }
.fu3 { animation-delay:.14s; } .fu4 { animation-delay:.19s; }
.fu5 { animation-delay:.24s; } .fu6 { animation-delay:.28s; }
</style>

<div class="dash-wrap">

<!-- ══════════════ SIDEBAR ══════════════ -->
<div class="dash-sb">

  <!-- USER CARD -->
  <div class="d-user fu fu1">
    <div class="d-avatar"><?= strtoupper(substr($user['nama'],0,1)) ?></div>
    <div class="d-name"><?= e(explode(' ',$user['nama'])[0]) ?></div>
    <div class="d-role">Mahasiswa &middot; CloudLibrary</div>
    <div class="d-pts"><i class="fas fa-star" style="font-size:10px;"></i><?= $user['poin'] ?> poin</div>
    <div class="d-stats">
      <div class="d-stat"><div class="n"><?= count($pinjaman_aktif) ?></div><div class="l">Dipinjam</div></div>
      <div class="d-stat"><div class="n"><?= $total_dibaca ?></div><div class="l">Selesai</div></div>
      <div class="d-stat"><div class="n"><?= $total_review ?></div><div class="l">Review</div></div>
      <div class="d-stat"><div class="n"><?= $total_wishlist ?></div><div class="l">Wishlist</div></div>
    </div>
  </div>

  <!-- LEVEL -->
  <div class="d-level fu fu2" style="border-left-color:<?= $level['color'] ?>;">
    <div class="lv-top">
      <div class="lv-ico" style="background:<?= $level['color'] ?>14;border:1.5px solid <?= $level['color'] ?>25;">
        <i class="fas <?= $level['fa'] ?>" style="color:<?= $level['color'] ?>;"></i>
      </div>
      <div>
        <div class="lv-name" style="color:<?= $level['color'] ?>;"><?= $level['name'] ?></div>
        <div class="lv-pts"><?= $user['poin'] ?> poin</div>
      </div>
    </div>
    <div class="lv-bar"><div class="lv-fill" style="width:<?= $progress_pct ?>%;background:<?= $level['color'] ?>;"></div></div>
    <div class="lv-range"><span><?= $user['poin'] ?></span><span><?= $level['next'] ? $level['next'].' poin' : 'Maks!' ?></span></div>
  </div>

  <!-- NAV -->
  <div class="d-nav fu fu3">
    <div class="d-nav-hd">Menu</div>
    <?php $navs=[
      ['katalog.php',    'fa-book',        'Katalog Buku',  'rgba(37,99,235,0.10)',  '#3b82f6'],
      ['riwayat.php',    'fa-history',     'Riwayat',       'rgba(22,163,74,0.10)',  '#16a34a'],
      ['wishlist.php',   'fa-heart',       'Wishlist',      'rgba(219,39,119,0.10)', '#db2777'],
      ['notifikasi.php', 'fa-bell',        'Notifikasi',    'rgba(217,119,6,0.10)',  '#d97706'],
      ['profil.php',     'fa-user-circle', 'Profil',        'rgba(168,85,247,0.10)', '#9333ea'],
    ];
    foreach($navs as [$href,$ico,$lbl,$bg,$clr]):?>
    <a href="<?= BASE_URL ?>/mahasiswa/<?= $href ?>" class="d-link">
      <div class="d-link-ico" style="background:<?= $bg ?>;color:<?= $clr ?>;"><i class="fas <?= $ico ?>"></i></div>
      <?= $lbl ?>
    </a>
    <?php endforeach;?>
  </div>

  <!-- QUOTE -->
  <div class="d-quote fu fu4">
    <div class="d-qt">"Sebuah buku yang dibaca adalah sebuah dunia yang dibuka."</div>
    <div class="d-qa">— CloudLibrary Mini</div>
  </div>

</div><!-- /sidebar -->

<!-- ══════════════ MAIN ══════════════ -->
<div class="dash-main">

  <!-- HERO — ramping -->
  <div class="hero fu fu1">
    <div class="hero-ey"><span></span>Selamat Datang Kembali</div>
    <div class="hero-title">Halo, <span class="grad"><?= e($user['nama']) ?>!</span></div>
    <div class="hero-sub">Dunia buku menunggu kamu. Baca, eksplorasi, dan kembangkan diri hari ini.</div>
    <div class="hero-row">
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="h-btn p"><i class="fas fa-search"></i> Cari Buku</a>
      <a href="<?= BASE_URL ?>/mahasiswa/riwayat.php" class="h-btn g"><i class="fas fa-history"></i> Riwayat</a>
    </div>
  </div>

  <!-- 4 STAT CARDS -->
  <div class="stats-row fu fu2">
    <div class="sc" style="border-top-color:#3b82f6;">
      <div class="sc-ico" style="background:rgba(37,99,235,0.10);color:#3b82f6;"><i class="fas fa-book-open"></i></div>
      <div class="sc-num"><?= count($pinjaman_aktif) ?></div>
      <div class="sc-lbl">Dipinjam</div>
    </div>
    <div class="sc" style="border-top-color:#16a34a;">
      <div class="sc-ico" style="background:rgba(22,163,74,0.10);color:#16a34a;"><i class="fas fa-check-circle"></i></div>
      <div class="sc-num"><?= $total_dibaca ?></div>
      <div class="sc-lbl">Selesai</div>
    </div>
    <div class="sc" style="border-top-color:#d97706;">
      <div class="sc-ico" style="background:rgba(217,119,6,0.10);color:#d97706;"><i class="fas fa-star"></i></div>
      <div class="sc-num"><?= $total_review ?></div>
      <div class="sc-lbl">Review</div>
    </div>
    <div class="sc" style="border-top-color:#db2777;">
      <div class="sc-ico" style="background:rgba(219,39,119,0.10);color:#db2777;"><i class="fas fa-heart"></i></div>
      <div class="sc-num"><?= $total_wishlist ?></div>
      <div class="sc-lbl">Wishlist</div>
    </div>
  </div>

  <!-- BADGE -->
  <?php if($badges): ?>
  <div class="sect fu fu3">
    <div class="sect-top">
      <div class="sect-ttl">
        <div class="sect-ico" style="background:rgba(217,119,6,0.12);color:var(--gold);"><i class="fas fa-trophy"></i></div>
        Badge Kamu
      </div>
    </div>
    <div class="badge-strip">
      <?php foreach($badges as $b): ?>
        <div class="badge-chip" title="<?= e($b['deskripsi']) ?>"><i class="fas fa-medal"></i><?= e($b['nama']) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- PEMINJAMAN AKTIF -->
  <div class="sect fu fu4">
    <div class="sect-top">
      <div class="sect-ttl">
        <div class="sect-ico" style="background:rgba(37,99,235,0.10);color:#3b82f6;"><i class="fas fa-clock"></i></div>
        Peminjaman Aktif
      </div>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="sect-more"><i class="fas fa-plus"></i> Pinjam Buku</a>
    </div>
    <?php if($pinjaman_aktif): ?>
    <div class="borrow-list">
      <?php foreach($pinjaman_aktif as $p):
        $sisa = sisaHari($p['tanggal_expired']);
        $warn = $p['status']==='hampir_habis';
        $gw   = $genre_warna[$p['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
        $dc   = $sisa<=1?'#dc2626':($warn?'#d97706':'#2563eb');
      ?>
      <div class="borrow-item <?= $warn?'warn':'' ?>">
        <div class="bc-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
          <?php if(!empty($p['cover'])): ?>
            <img src="<?= BASE_URL ?>/uploads/covers/<?= e($p['cover']) ?>" style="width:100%;height:100%;object-fit:contain;" alt="">
          <?php else: ?>
            <i class="fas <?= $gw['icon'] ?>"></i>
          <?php endif; ?>
        </div>
        <div class="bc-info">
          <div class="bc-tag"><?= e($p['genre']) ?></div>
          <div class="bc-title"><?= e($p['judul']) ?></div>
          <div class="bc-author"><i class="fas fa-pen-nib" style="font-size:9px;"></i><?= e($p['penulis']) ?></div>
        </div>
        <div class="bc-days">
          <div class="n" style="color:<?= $dc ?>;"><?= max($sisa,0) ?></div>
          <div class="l">hari lagi</div>
        </div>
        <?php if($warn): ?><div class="bc-warn"><i class="fas fa-exclamation-triangle"></i> Hampir habis!</div><?php endif; ?>
        <div class="bc-acts">
          <a href="<?= BASE_URL ?>/mahasiswa/baca.php?id=<?= $p['buku_id'] ?>" class="bc-btn r"><i class="fas fa-book-open"></i> Baca</a>
          <a href="<?= BASE_URL ?>/mahasiswa/perpanjang.php?id=<?= $p['id'] ?>" class="bc-btn e"><i class="fas fa-redo"></i></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty"><i class="fas fa-book"></i><p>Belum ada buku dipinjam.<br><a href="<?= BASE_URL ?>/mahasiswa/katalog.php">Mulai cari buku sekarang</a></p></div>
    <?php endif; ?>
  </div>

  <!-- REKOMENDASI -->
  <?php if($rekomendasi): ?>
  <div class="sect fu fu5">
    <div class="sect-top">
      <div class="sect-ttl">
        <div class="sect-ico" style="background:rgba(219,39,119,0.10);color:#db2777;"><i class="fas fa-magic"></i></div>
        Rekomendasi
        <span style="font-size:11px;font-weight:500;color:var(--muted);"><?= e($fav['genre']) ?></span>
      </div>
    </div>
    <div class="book-grid">
      <?php foreach($rekomendasi as $buku):
        $rating = ratingRataRata($pdo,$buku['id']);
        $gw = $genre_warna[$buku['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
      ?>
      <div class="bk">
        <div class="bk-cover" style="background:linear-gradient(145deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>bb);">
          <span class="bk-tipe <?= $buku['tipe']==='fiksi'?'tipe-fiksi':'tipe-nonfiksi' ?>"><?= $buku['tipe'] ?></span>
          <?php if(!empty($buku['cover'])): ?>
            <img src="<?= BASE_URL ?>/uploads/covers/<?= e($buku['cover']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;" alt="">
          <?php else: ?>
            <i class="fas <?= $gw['icon'] ?>"></i>
          <?php endif; ?>
        </div>
        <div class="bk-body">
          <div class="bk-genre"><?= e($buku['genre']) ?></div>
          <div class="bk-title"><?= e($buku['judul']) ?></div>
          <div class="bk-author"><?= e($buku['penulis']) ?></div>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $buku['id'] ?>" class="bk-btn">Lihat <i class="fas fa-arrow-right" style="font-size:9px;"></i></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- BUKU POPULER -->
  <?php if($trending): ?>
  <div class="sect fu fu6">
    <div class="sect-top">
      <div class="sect-ttl">
        <div class="sect-ico" style="background:rgba(239,68,68,0.10);color:#f87171;"><i class="fas fa-fire"></i></div>
        Buku Populer
      </div>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="sect-more"><i class="fas fa-arrow-right"></i> Lihat Semua</a>
    </div>
    <div class="book-grid">
      <?php foreach($trending as $buku):
        $rating = ratingRataRata($pdo,$buku['id']);
        $gw = $genre_warna[$buku['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
      ?>
      <div class="bk">
        <div class="bk-cover" style="background:linear-gradient(145deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>bb);">
          <span class="bk-tipe <?= $buku['tipe']==='fiksi'?'tipe-fiksi':'tipe-nonfiksi' ?>"><?= $buku['tipe'] ?></span>
          <?php if(!empty($buku['cover'])): ?>
            <img src="<?= BASE_URL ?>/uploads/covers/<?= e($buku['cover']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;" alt="">
          <?php else: ?>
            <i class="fas <?= $gw['icon'] ?>"></i>
          <?php endif; ?>
        </div>
        <div class="bk-body">
          <div class="bk-genre"><?= e($buku['genre']) ?></div>
          <div class="bk-title"><?= e($buku['judul']) ?></div>
          <div class="bk-author"><?= e($buku['penulis']) ?></div>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $buku['id'] ?>" class="bk-btn">Lihat <i class="fas fa-arrow-right" style="font-size:9px;"></i></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /dash-main -->
</div><!-- /dash-wrap -->

<footer class="d-foot">
  <i class="fas fa-cloud" style="color:var(--d2);margin-right:5px;"></i>
  <strong style="color:var(--d2);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(30,58,95,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>
</body>
</html>