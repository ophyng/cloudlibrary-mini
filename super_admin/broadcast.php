<?php
// ============================================
//  CloudLibrary Mini — Super Admin Dashboard
//  File   : super_admin/dashboard.php
// ============================================
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: '.BASE_URL.'/auth/login.php'); exit;
}

$admin_nama = $_SESSION['nama'] ?? 'Super Admin';

$total_user      = $pdo->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn();
$total_admin     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$total_buku      = $pdo->query("SELECT COUNT(*) FROM buku WHERE status != 'arsip'")->fetchColumn();
$total_pinjam    = $pdo->query("SELECT COUNT(*) FROM peminjaman")->fetchColumn();
$aktif_pinjam    = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status IN ('aktif','hampir_habis')")->fetchColumn();
$expired_count   = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='expired'")->fetchColumn();
$total_review    = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status='approved'")->fetchColumn();
$pending_review  = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();
$total_wishlist  = $pdo->query("SELECT COUNT(*) FROM wishlist")->fetchColumn();
$total_poin      = $pdo->query("SELECT SUM(poin) FROM users WHERE role='mahasiswa'")->fetchColumn();
$user_baru       = $pdo->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$admin_baru      = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$pinjam_hari_ini = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE DATE(created_at)=CURDATE()")->fetchColumn();

$grafik_raw = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b') AS bln, COUNT(*) AS total
    FROM peminjaman WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY created_at ASC LIMIT 6
")->fetchAll();
$max_graf = max(array_column($grafik_raw,'total') ?: [1]);

$top_buku = $pdo->query("
    SELECT b.judul, b.genre, COUNT(p.id) AS total
    FROM buku b LEFT JOIN peminjaman p ON p.buku_id=b.id
    GROUP BY b.id ORDER BY total DESC LIMIT 5
")->fetchAll();

$top_users = $pdo->query("
    SELECT nama, poin FROM users WHERE role='mahasiswa' ORDER BY poin DESC LIMIT 5
")->fetchAll();

$genre_stats = $pdo->query("
    SELECT b.genre, COUNT(*) AS total FROM peminjaman p JOIN buku b ON p.buku_id=b.id
    GROUP BY b.genre ORDER BY total DESC LIMIT 6
")->fetchAll();

$admin_list = $pdo->query("
    SELECT id, nama, email, created_at FROM users WHERE role='admin' ORDER BY created_at DESC LIMIT 5
")->fetchAll();

$recent_pinjam = $pdo->query("
    SELECT p.*, u.nama AS user_nama, b.judul, b.genre
    FROM peminjaman p JOIN users u ON p.user_id=u.id JOIN buku b ON p.buku_id=b.id
    ORDER BY p.created_at DESC LIMIT 6
")->fetchAll();

$genre_warna = [
    'Novel'    => ['bg'=>'#1a237e','icon'=>'fa-book',       'accent'=>'#7986cb'],
    'Cerpen'   => ['bg'=>'#4a148c','icon'=>'fa-scroll',     'accent'=>'#ce93d8'],
    'Fantasi'  => ['bg'=>'#1b5e20','icon'=>'fa-hat-wizard', 'accent'=>'#81c784'],
    'Romance'  => ['bg'=>'#880e4f','icon'=>'fa-heart',      'accent'=>'#f48fb1'],
    'Horror'   => ['bg'=>'#b71c1c','icon'=>'fa-ghost',      'accent'=>'#ef9a9a'],
    'Misteri'  => ['bg'=>'#e65100','icon'=>'fa-user-secret','accent'=>'#ffcc80'],
    'Sci-Fi'   => ['bg'=>'#006064','icon'=>'fa-rocket',     'accent'=>'#80deea'],
    'Filsafat' => ['bg'=>'#37474f','icon'=>'fa-landmark',   'accent'=>'#b0bec5'],
    'Sains'    => ['bg'=>'#1565c0','icon'=>'fa-flask',      'accent'=>'#90caf9'],
    'Biografi' => ['bg'=>'#4e342e','icon'=>'fa-feather-alt','accent'=>'#bcaaa4'],
];

$title = "Dashboard — Super Admin CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
body{
  font-family:'Nunito',sans-serif;min-height:100vh;overflow-x:hidden;position:relative;margin:0;
  background:#dce8f5;
  background-image:url('gambar_library.jpg');
  background-size:cover;background-position:center;background-attachment:fixed;background-repeat:no-repeat;
  color:#1a2744 !important;
}
body::before{content:'';position:fixed;inset:0;background:rgba(235,243,252,0.28);z-index:0;pointer-events:none;}

:root{
  --s1:#3a6186;--s2:#2c4f78;--s3:#5b8fb9;--gold:#d4a017;--gold2:#f9c74f;
  --card-bg:rgba(255,255,255,0.78);--card-border:rgba(255,255,255,0.85);
  --text-main:#1a2744;--text-muted:#6b7a99;
  --success:#15803d;--warn:#c2410c;--danger:#b91c1c;
  --sh:0 4px 20px rgba(58,97,134,0.10);--sh-md:0 10px 36px rgba(58,97,134,0.16);
}

.dash-content{position:relative;z-index:1;padding:24px 0 60px;}

/* HERO */
.sa-hero{border-radius:24px;margin-bottom:24px;overflow:hidden;position:relative;background:linear-gradient(135deg,#2c4f78 0%,#3a6186 35%,#5b8fb9 65%,#93c5fd 100%);box-shadow:0 20px 60px rgba(58,97,134,0.35);border:1px solid rgba(255,255,255,0.2);min-height:220px;}
.sa-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(244,114,182,0.22),transparent 65%);}
.sa-hero-wm{position:absolute;bottom:-16px;right:0;font-family:'Syne',sans-serif;font-size:100px;font-weight:900;color:rgba(255,255,255,0.05);line-height:1;letter-spacing:-4px;z-index:1;white-space:nowrap;}
.sa-hero-inner{position:relative;z-index:2;padding:28px 36px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;min-height:220px;}

.shi-left{flex:1;min-width:200px;}
.shi-eyebrow{font-size:10px;font-weight:900;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.65);margin-bottom:10px;display:flex;align-items:center;gap:8px;}
.shi-eyebrow::before{content:'';display:block;width:20px;height:2px;background:rgba(255,255,255,0.7);}
.shi-title{font-family:'Syne',sans-serif;font-size:clamp(22px,2.5vw,36px);font-weight:900;color:#fff;line-height:1.1;margin-bottom:10px;}
.shi-title .t-gold{background:linear-gradient(90deg,#fde68a,#f9c74f,#d4a017);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:block;}
.shi-sub{font-size:12px;color:rgba(255,255,255,0.55);font-weight:600;line-height:1.7;margin-bottom:18px;max-width:380px;}
.shi-cta{display:flex;gap:10px;flex-wrap:wrap;}
.btn-sa-p{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:100px;background:rgba(255,255,255,0.2);border:2px solid rgba(255,255,255,0.5);color:#fff;font-size:12px;font-weight:900;text-decoration:none;transition:all .25s;font-family:'Nunito',sans-serif;backdrop-filter:blur(8px);}
.btn-sa-p:hover{background:rgba(255,255,255,0.35);transform:translateY(-2px);}
.btn-sa-g{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:100px;background:linear-gradient(135deg,#2563eb,#3a6186);color:#fff;font-size:12px;font-weight:900;text-decoration:none;transition:all .25s;font-family:'Nunito',sans-serif;box-shadow:0 4px 16px rgba(37,99,235,0.4);}
.btn-sa-g:hover{transform:translateY(-2px);}

.shi-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;}
.shi-admin-row{display:flex;align-items:center;gap:10px;}
.shi-avatar{width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,0.25);border:3px solid rgba(255,255,255,0.5);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:18px;font-weight:900;color:#fff;}
.shi-name{font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:#fff;}
.shi-role{font-size:10px;color:rgba(255,255,255,0.55);font-weight:700;margin-top:1px;display:flex;align-items:center;gap:4px;}
.shi-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);border-radius:100px;padding:5px 13px;font-size:11px;font-weight:800;color:#fff;backdrop-filter:blur(6px);}
.shi-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;min-width:320px;}
.shi-stat-block{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);border-radius:12px;padding:10px;text-align:center;backdrop-filter:blur(6px);}
.shi-stat-block .num{font-family:'Syne',sans-serif;font-size:20px;font-weight:900;color:#fff;line-height:1;}
.shi-stat-block .lbl{font-size:9px;color:rgba(255,255,255,0.55);font-weight:700;margin-top:2px;}

/* MARQUEE */
.marquee-wrap{background:linear-gradient(90deg,#2c4f78,#3a6186,#2c4f78);border-radius:12px;overflow:hidden;margin-bottom:24px;padding:9px 0;box-shadow:0 3px 14px rgba(58,97,134,0.2);}
.marquee-track{display:flex;white-space:nowrap;animation:marqueeScroll 24s linear infinite;}
.marquee-track:hover{animation-play-state:paused;}
.marquee-item{display:inline-flex;align-items:center;gap:8px;padding:0 22px;font-size:11px;font-weight:800;color:rgba(255,255,255,0.75);}
.marquee-dot{width:4px;height:4px;border-radius:50%;background:var(--gold2);flex-shrink:0;}
@keyframes marqueeScroll{0%{transform:translateX(0);}100%{transform:translateX(-50%);}}

/* STAT GRID */
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:22px;}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr);}}

.stat-card{background:var(--card-bg);border:2px solid var(--card-border);border-radius:18px;padding:16px 18px;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:transform .2s,box-shadow .2s;text-decoration:none;color:inherit;display:block;position:relative;overflow:hidden;border-top:3px solid transparent;}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md);}
.sc-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;}
.sc-ico{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.sc-badge{font-size:10px;font-weight:800;padding:3px 9px;border-radius:7px;}
.sc-num{font-family:'Syne',sans-serif;font-size:26px;font-weight:900;color:var(--text-main);margin-bottom:2px;}
.sc-lbl{font-size:11px;color:var(--text-muted);font-weight:700;}

/* QUICK ACTIONS */
.quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;}
@media(max-width:800px){.quick-grid{grid-template-columns:repeat(2,1fr);}}
.quick-btn{background:var(--card-bg);border:2px solid var(--card-border);border-radius:16px;padding:18px 12px;text-align:center;text-decoration:none;color:var(--text-main);transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:8px;backdrop-filter:blur(20px);box-shadow:var(--sh);}
.quick-btn:hover{transform:translateY(-3px);box-shadow:var(--sh-md);}
.qb-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:18px;}
.qb-lbl{font-size:12px;font-weight:900;color:var(--text-main);}
.qb-sub{font-size:10px;color:var(--text-muted);font-weight:600;}

/* POSTER INSIGHT */
.poster-insight{background:linear-gradient(135deg,#2c4f78,#3a6186,#5b8fb9);border-radius:20px;padding:24px 32px;margin-bottom:22px;position:relative;overflow:hidden;box-shadow:0 14px 44px rgba(58,97,134,0.28);border:1px solid rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
.poster-insight::before{content:'';position:absolute;top:-50px;right:-50px;width:200px;height:200px;background:radial-gradient(circle,rgba(244,114,182,0.18),transparent 65%);}
.pi-border{position:absolute;inset:8px;border:2px dashed rgba(255,255,255,0.1);border-radius:14px;pointer-events:none;}
.pi-left{position:relative;z-index:1;}
.pi-eyebrow{font-size:10px;font-weight:900;letter-spacing:2.5px;text-transform:uppercase;color:rgba(253,230,138,0.8);margin-bottom:6px;display:flex;align-items:center;gap:6px;}
.pi-title{font-family:'Syne',sans-serif;font-size:clamp(16px,2vw,24px);font-weight:900;color:#fff;line-height:1.2;margin-bottom:4px;}
.pi-title .pi-gold{color:#fde68a;}
.pi-sub{font-size:11px;color:rgba(255,255,255,0.45);font-weight:700;}
.pi-stats{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1;}
.pi-stat{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);border-radius:12px;padding:10px 14px;text-align:center;backdrop-filter:blur(6px);min-width:68px;}
.pi-stat .pn{font-family:'Syne',sans-serif;font-size:18px;font-weight:900;color:#fff;line-height:1;}
.pi-stat .pl{font-size:10px;color:rgba(255,255,255,0.5);font-weight:700;margin-top:2px;}

/* SECTION */
.sect-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.sect-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;color:var(--text-main);display:flex;align-items:center;gap:8px;}
.sect-dot{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.sect-link{font-size:12px;font-weight:800;color:var(--s1);text-decoration:none;padding:5px 12px;border-radius:100px;background:rgba(58,97,134,0.07);border:1px solid rgba(58,97,134,0.16);transition:all .2s;}
.sect-link:hover{background:var(--s1);color:#fff;}

.card-sa{background:var(--card-bg);border:2px solid var(--card-border);border-radius:18px;padding:18px 20px;backdrop-filter:blur(20px);box-shadow:var(--sh);margin-bottom:16px;position:relative;overflow:hidden;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
@media(max-width:800px){.grid-2{grid-template-columns:1fr;}}

/* CHART */
.chart-bars{display:flex;align-items:flex-end;gap:8px;height:110px;padding-bottom:4px;}
.chart-bar-wrap{display:flex;flex-direction:column;align-items:center;flex:1;gap:4px;}
.chart-bar{width:100%;border-radius:5px 5px 0 0;background:linear-gradient(to top,#2c4f78,#5b8fb9);min-height:4px;transition:height .6s ease;}
.chart-bar-lbl{font-size:9px;font-weight:800;color:var(--text-muted);}
.chart-bar-val{font-size:9px;font-weight:900;color:var(--s1);}

/* GENRE */
.genre-bar-row{display:flex;align-items:center;gap:8px;margin-bottom:9px;}
.genre-bar-row:last-child{margin-bottom:0;}
.genre-bar-lbl{font-size:11px;font-weight:800;min-width:80px;color:var(--text-main);display:flex;align-items:center;gap:5px;}
.genre-bar-track{flex:1;height:8px;background:rgba(58,97,134,0.08);border-radius:4px;overflow:hidden;}
.genre-bar-fill{height:100%;border-radius:4px;}
.genre-bar-cnt{font-size:10px;color:var(--text-muted);font-weight:800;min-width:22px;text-align:right;}

/* TOP LIST */
.top-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1.5px solid rgba(58,97,134,0.07);}
.top-item:last-child{border-bottom:none;}
.top-rank{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;color:rgba(58,97,134,0.2);width:20px;flex-shrink:0;text-align:center;}
.top-rank.gold{color:var(--gold);}
.top-info{flex:1;min-width:0;}
.top-name{font-size:12px;font-weight:900;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.top-sub{font-size:10px;color:var(--text-muted);font-weight:700;}
.top-val{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;color:var(--s1);flex-shrink:0;}

.av-mini{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--s1),var(--s3));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;color:#fff;font-family:'Syne',sans-serif;flex-shrink:0;}

.medal-ico{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;}
.medal-1{background:rgba(212,160,23,0.15);color:#d4a017;}
.medal-2{background:rgba(156,163,175,0.15);color:#6b7280;}
.medal-3{background:rgba(180,83,9,0.15);color:#b45309;}

.act-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1.5px solid rgba(58,97,134,0.07);}
.act-item:last-child{border-bottom:none;}
.act-ico{width:34px;height:46px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;color:#fff;}
.act-info{flex:1;min-width:0;}
.act-title{font-size:12px;font-weight:900;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.act-user{font-size:10px;color:var(--text-muted);font-weight:600;margin-top:1px;display:flex;align-items:center;gap:4px;}
.act-status{font-size:9px;font-weight:900;padding:3px 8px;border-radius:6px;flex-shrink:0;}
.as-aktif{background:rgba(21,128,61,0.1);color:#15803d;}
.as-hampir{background:rgba(194,65,12,0.1);color:#c2410c;}
.as-expired{background:rgba(185,28,28,0.1);color:#b91c1c;}
.as-selesai{background:rgba(107,114,128,0.1);color:var(--text-muted);}

.admin-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1.5px solid rgba(58,97,134,0.07);}
.admin-item:last-child{border-bottom:none;}

footer.sa-foot{position:relative;z-index:1;text-align:center;padding:24px;font-size:12px;color:var(--text-muted);font-weight:700;border-top:1.5px dashed rgba(58,97,134,0.15);}

@keyframes fadeUp{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
.fade-up{animation:fadeUp .4s ease both;}
</style>

<div class="dash-content">

  <!-- HERO -->
  <div class="sa-hero fade-up">
    <div class="sa-hero-wm">SUPER ADMIN</div>
    <div class="sa-hero-inner">
      <div class="shi-left">
        <div class="shi-eyebrow"><i class="fas fa-crown" style="font-size:10px;color:rgba(253,230,138,0.8);"></i> Super Admin Panel</div>
        <div class="shi-title">
          Selamat Datang,
          <span class="t-gold"><?= e($admin_nama) ?>!</span>
        </div>
        <div class="shi-sub">Pantau dan kendalikan seluruh ekosistem CloudLibrary Mini.</div>
        <div class="shi-cta">
          <a href="<?= BASE_URL ?>/super_admin/admin.php" class="btn-sa-g"><i class="fas fa-users-cog"></i> Kelola Admin</a>
          <a href="<?= BASE_URL ?>/super_admin/settings.php" class="btn-sa-p"><i class="fas fa-cog"></i> Pengaturan</a>
        </div>
      </div>
      <div class="shi-right">
        <div class="shi-admin-row">
          <div class="shi-avatar"><?= strtoupper(substr($admin_nama,0,1)) ?></div>
          <div>
            <div class="shi-name"><?= e(explode(' ',$admin_nama)[0]) ?></div>
            <div class="shi-role"><i class="fas fa-crown" style="font-size:9px;color:rgba(253,230,138,0.7);"></i> Super Administrator</div>
          </div>
        </div>
        <div class="shi-badge"><i class="fas fa-circle" style="font-size:7px;color:#4ade80;"></i> Aktif &middot; <?= date('H:i') ?> WIB</div>
        <div class="shi-stat-grid">
          <div class="shi-stat-block"><div class="num"><?= $total_admin ?></div><div class="lbl">Admin</div></div>
          <div class="shi-stat-block"><div class="num"><?= $total_user ?></div><div class="lbl">User</div></div>
          <div class="shi-stat-block"><div class="num"><?= $total_buku ?></div><div class="lbl">Buku</div></div>
          <div class="shi-stat-block"><div class="num"><?= $pinjam_hari_ini ?></div><div class="lbl">Hari Ini</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- MARQUEE -->
  <div class="marquee-wrap fade-up">
    <div class="marquee-track">
      <?php
      $mi=['Super Admin','Kelola Sistem','Monitor Buku','Manajemen User','Statistik','Pengaturan','Broadcast','Badge & Poin'];
      $all=array_merge($mi,$mi,$mi);
      foreach($all as $m): ?>
        <span class="marquee-item"><span class="marquee-dot"></span><?= $m ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid fade-up">
    <?php $scards=[
      ['fa-crown',     'Total Admin',   $total_admin,  'rgba(58,97,134,0.10)','#3a6186','+'.$admin_baru.' bln ini'],
      ['fa-users',     'Mahasiswa',     $total_user,   'rgba(91,143,185,0.10)','#5b8fb9','+'.$user_baru.' bln ini'],
      ['fa-book',      'Total Buku',    $total_buku,   'rgba(212,160,23,0.10)','#d4a017','Koleksi aktif'],
      ['fa-clipboard-list','Total Transaksi',$total_pinjam,'rgba(21,128,61,0.10)','#15803d',$aktif_pinjam.' aktif'],
      ['fa-exclamation-circle','Expired',$expired_count,'rgba(185,28,28,0.10)','#b91c1c','Perlu tindakan'],
      ['fa-star',      'Review',        $total_review, 'rgba(212,160,23,0.10)','#d4a017',$pending_review.' pending'],
    ]; foreach($scards as [$ic,$lb,$vl,$bg,$col,$sub]): ?>
    <div class="stat-card" style="border-top-color:<?= $col ?>;">
      <div class="sc-top">
        <div class="sc-ico" style="background:<?= $bg ?>;color:<?= $col ?>;"><i class="fas <?= $ic ?>"></i></div>
        <span class="sc-badge" style="background:<?= $bg ?>;color:<?= $col ?>;"><?= $sub ?></span>
      </div>
      <div class="sc-num" style="color:<?= $col ?>;"><?= $vl ?></div>
      <div class="sc-lbl"><?= $lb ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="quick-grid fade-up">
    <?php $qa=[
      [BASE_URL.'/super_admin/admin.php',   'fa-users-cog', 'rgba(58,97,134,0.10)', '#3a6186','Kelola Admin', 'Tambah/blokir admin'],
      [BASE_URL.'/super_admin/users.php',   'fa-users',     'rgba(91,143,185,0.10)','#5b8fb9','Kelola User',  'Manajemen mahasiswa'],
      [BASE_URL.'/super_admin/statistik.php','fa-chart-bar', 'rgba(212,160,23,0.10)','#d4a017','Statistik',   'Laporan penuh'],
      [BASE_URL.'/super_admin/settings.php','fa-cog',        'rgba(21,128,61,0.10)', '#15803d','Pengaturan',  'Konfigurasi sistem'],
    ]; foreach($qa as [$hr,$ic,$bg,$col,$lb,$sub]): ?>
    <a href="<?= $hr ?>" class="quick-btn">
      <div class="qb-ico" style="background:<?= $bg ?>;color:<?= $col ?>;"><i class="fas <?= $ic ?>"></i></div>
      <div class="qb-lbl"><?= $lb ?></div>
      <div class="qb-sub"><?= $sub ?></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- POSTER INSIGHT -->
  <div class="poster-insight fade-up">
    <div class="pi-border"></div>
    <div class="pi-left">
      <div class="pi-eyebrow"><i class="fas fa-chart-pie" style="font-size:9px;"></i> Insight Real-Time</div>
      <div class="pi-title">
        <span class="pi-gold"><?= $pinjam_hari_ini ?> peminjaman</span> hari ini &mdash;
        <?= $expired_count ?> buku <span class="pi-gold">expired</span> perlu tindakan.
      </div>
      <div class="pi-sub">Update: <?= date('d M Y, H:i') ?> WIB</div>
    </div>
    <div class="pi-stats">
      <div class="pi-stat"><div class="pn"><?= $total_pinjam ?></div><div class="pl">Transaksi</div></div>
      <div class="pi-stat"><div class="pn"><?= $aktif_pinjam ?></div><div class="pl">Aktif</div></div>
      <div class="pi-stat"><div class="pn"><?= number_format($total_poin) ?></div><div class="pl">Poin</div></div>
      <div class="pi-stat"><div class="pn"><?= $total_wishlist ?></div><div class="pl">Wishlist</div></div>
    </div>
  </div>

  <!-- GRAFIK + GENRE -->
  <div class="grid-2 fade-up">
    <div class="card-sa" style="margin-bottom:0;">
      <div class="sect-hdr">
        <div class="sect-title"><div class="sect-dot" style="background:rgba(58,97,134,0.1);color:var(--s1);"><i class="fas fa-chart-bar"></i></div>Peminjaman 6 Bulan</div>
      </div>
      <div class="chart-bars">
        <?php foreach($grafik_raw as $g):
          $h=round(($g['total']/max($max_graf,1))*100); ?>
          <div class="chart-bar-wrap">
            <div class="chart-bar-val"><?= $g['total'] ?></div>
            <div class="chart-bar" style="height:<?= $h ?>px;"></div>
            <div class="chart-bar-lbl"><?= $g['bln'] ?></div>
          </div>
        <?php endforeach; ?>
        <?php if(empty($grafik_raw)): ?>
          <div style="color:var(--text-muted);font-size:12px;width:100%;text-align:center;padding:24px 0;">Belum ada data.</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-sa" style="margin-bottom:0;">
      <div class="sect-hdr">
        <div class="sect-title"><div class="sect-dot" style="background:rgba(212,160,23,0.1);color:var(--gold);"><i class="fas fa-fire"></i></div>Genre Terpopuler</div>
      </div>
      <?php
      $gcols=['#3a6186','#5b8fb9','#d4a017','#f9c74f','#c2410c','#15803d'];
      $max_g=$genre_stats ? $genre_stats[0]['total'] : 1;
      foreach($genre_stats as $i=>$gs):
        $gw=$genre_warna[$gs['genre']]??['icon'=>'fa-book'];?>
        <div class="genre-bar-row">
          <div class="genre-bar-lbl"><i class="fas <?= $gw['icon'] ?>" style="font-size:11px;color:<?= $gcols[$i]??'#3a6186' ?>;"></i> <?= e($gs['genre']) ?></div>
          <div class="genre-bar-track"><div class="genre-bar-fill" style="width:<?= round($gs['total']/$max_g*100) ?>%;background:<?= $gcols[$i]??'#3a6186' ?>;"></div></div>
          <div class="genre-bar-cnt"><?= $gs['total'] ?>&times;</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TOP BUKU + TOP USER -->
  <div class="grid-2 fade-up">
    <div class="card-sa" style="margin-bottom:0;">
      <div class="sect-hdr">
        <div class="sect-title"><div class="sect-dot" style="background:rgba(212,160,23,0.1);color:var(--gold);"><i class="fas fa-trophy"></i></div>Buku Terlaris</div>
      </div>
      <?php foreach($top_buku as $i=>$b):
        $gw=$genre_warna[$b['genre']]??['icon'=>'fa-book']; ?>
        <div class="top-item">
          <div class="top-rank <?= $i===0?'gold':'' ?>">
            <?php if($i<3): ?><span class="medal-ico medal-<?=$i+1?>"><i class="fas fa-medal"></i></span>
            <?php else: ?><?=$i+1?><?php endif; ?>
          </div>
          <div style="width:28px;height:28px;border-radius:7px;background:<?= $gw['bg']??'#3a6186' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas <?= $gw['icon'] ?>" style="font-size:12px;color:#fff;"></i></div>
          <div class="top-info">
            <div class="top-name"><?= e($b['judul']) ?></div>
            <div class="top-sub"><?= e($b['genre']) ?></div>
          </div>
          <div class="top-val"><?= $b['total'] ?>&times;</div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card-sa" style="margin-bottom:0;">
      <div class="sect-hdr">
        <div class="sect-title"><div class="sect-dot" style="background:rgba(58,97,134,0.1);color:var(--s1);"><i class="fas fa-medal"></i></div>Pembaca Teraktif</div>
      </div>
      <?php foreach($top_users as $i=>$u): ?>
        <div class="top-item">
          <div class="top-rank <?= $i===0?'gold':'' ?>"><?= $i+1 ?></div>
          <div class="av-mini"><?= strtoupper(substr($u['nama'],0,1)) ?></div>
          <div class="top-info">
            <div class="top-name"><?= e($u['nama']) ?></div>
            <div class="top-sub">Mahasiswa</div>
          </div>
          <div class="top-val" style="color:var(--gold);"><i class="fas fa-star" style="font-size:10px;"></i> <?= $u['poin'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ADMIN + ACTIVITY -->
  <div class="grid-2 fade-up">
    <div class="card-sa" style="margin-bottom:0;">
      <div class="sect-hdr">
        <div class="sect-title"><div class="sect-dot" style="background:rgba(58,97,134,0.1);color:var(--s1);"><i class="fas fa-users-cog"></i></div>Daftar Admin</div>
        <a href="<?= BASE_URL ?>/super_admin/admin.php" class="sect-link"><i class="fas fa-arrow-right"></i> Kelola</a>
      </div>
      <?php if($admin_list): foreach($admin_list as $a): ?>
        <div class="admin-item">
          <div class="av-mini" style="background:linear-gradient(135deg,var(--s1),var(--gold));"><?= strtoupper(substr($a['nama'],0,1)) ?></div>
          <div class="top-info">
            <div class="top-name"><?= e($a['nama']) ?></div>
            <div class="top-sub"><i class="fas fa-envelope" style="font-size:9px;"></i> <?= e($a['email']) ?></div>
          </div>
          <div style="font-size:10px;color:var(--text-muted);font-weight:700;flex-shrink:0;"><?= formatTanggal($a['created_at']) ?></div>
        </div>
      <?php endforeach; else: ?>
        <div style="text-align:center;padding:24px;color:var(--text-muted);font-weight:700;">Belum ada admin.</div>
      <?php endif; ?>
    </div>
    <div class="card-sa" style="margin-bottom:0;">
      <div class="sect-hdr">
        <div class="sect-title"><div class="sect-dot" style="background:rgba(91,143,185,0.1);color:var(--s3);"><i class="fas fa-clock"></i></div>Aktivitas Terkini</div>
      </div>
      <?php if($recent_pinjam):
        $sm=['aktif'=>['as-aktif','Aktif'],'hampir_habis'=>['as-hampir','Hampir'],'expired'=>['as-expired','Expired'],'dikembalikan'=>['as-selesai','Selesai']];
        foreach($recent_pinjam as $p):
          $gw=$genre_warna[$p['genre']]??['bg'=>'#3a6186','icon'=>'fa-book'];
          $s=$sm[$p['status']]??['as-selesai',$p['status']]; ?>
          <div class="act-item">
            <div class="act-ico" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);"><i class="fas <?= $gw['icon'] ?>"></i></div>
            <div class="act-info">
              <div class="act-title"><?= e($p['judul']) ?></div>
              <div class="act-user"><i class="fas fa-user" style="font-size:9px;"></i> <?= e($p['user_nama']) ?></div>
            </div>
            <span class="act-status <?= $s[0] ?>"><?= $s[1] ?></span>
          </div>
        <?php endforeach;
      else: ?>
        <div style="text-align:center;padding:24px;color:var(--text-muted);font-weight:700;">Belum ada aktivitas.</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<footer class="sa-foot">
  <i class="fas fa-cloud" style="color:var(--s1);margin-right:5px;"></i>
  <strong style="color:var(--s1);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(58,97,134,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>
</body>
</html>
