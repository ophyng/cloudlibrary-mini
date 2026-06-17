<?php
// ============================================
//  CloudLibrary Mini — Profil Mahasiswa
//  File   : mahasiswa/profil.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();

$user_id = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]); $user = $user->fetch();

$pesan = $pesan_type = '';

// Upload foto profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
    $file = $_FILES['foto_profil'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed)) {
        $pesan = "Format file harus JPG, PNG, atau WEBP."; $pesan_type = 'danger';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $pesan = "Ukuran foto maksimal 2MB."; $pesan_type = 'danger';
    } else {
        $folder = $_SERVER['DOCUMENT_ROOT'] . '/uploads/foto_profil/';
        if (!is_dir($folder)) mkdir($folder, 0755, true);
        // Hapus foto lama
        if (!empty($user['foto_profil']) && file_exists($folder . $user['foto_profil'])) {
            unlink($folder . $user['foto_profil']);
        }
        $nama_file = 'user_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $folder . $nama_file)) {
            $pdo->prepare("UPDATE users SET foto_profil = ? WHERE id = ?")->execute([$nama_file, $user_id]);
            $user['foto_profil'] = $nama_file;
            $pesan = "Foto profil berhasil diperbarui."; $pesan_type = 'success';
        } else {
            $pesan = "Gagal upload foto."; $pesan_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'update_profil') {
        $nama = trim($_POST['nama'] ?? '');
        $bio  = trim($_POST['bio']  ?? '');
        if (strlen($nama) < 2) { $pesan = "Nama minimal 2 karakter."; $pesan_type = 'danger'; }
        else {
            $pdo->prepare("UPDATE users SET nama=?, bio=? WHERE id=?")->execute([$nama, $bio, $user_id]);
            $_SESSION['nama'] = $nama;
            $pesan = "Profil berhasil diperbarui."; $pesan_type = 'success';
            $user['nama'] = $nama; $user['bio'] = $bio;
        }
    }
    if ($aksi === 'ganti_password') {
        $lama    = $_POST['password_lama']  ?? '';
        $baru    = $_POST['password_baru']  ?? '';
        $konfirm = $_POST['konfirmasi']     ?? '';
        if (!password_verify($lama, $user['password'])) { $pesan = "Password lama tidak sesuai."; $pesan_type = 'danger'; }
        elseif (strlen($baru) < 6) { $pesan = "Password baru minimal 6 karakter."; $pesan_type = 'danger'; }
        elseif ($baru !== $konfirm) { $pesan = "Konfirmasi password tidak cocok."; $pesan_type = 'danger'; }
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($baru, PASSWORD_DEFAULT), $user_id]);
            $pesan = "Password berhasil diganti."; $pesan_type = 'success';
        }
    }
}

// Statistik
$stat = $pdo->prepare("SELECT COUNT(*) AS total_pinjam, SUM(CASE WHEN status IN('aktif','hampir_habis') THEN 1 ELSE 0 END) AS aktif, SUM(CASE WHEN status IN('expired','dikembalikan') THEN 1 ELSE 0 END) AS selesai FROM peminjaman WHERE user_id=?");
$stat->execute([$user_id]); $stat = $stat->fetch();
$total_review   = $pdo->prepare("SELECT COUNT(*) FROM review WHERE user_id=?"); $total_review->execute([$user_id]); $total_review = $total_review->fetchColumn();
$total_wishlist = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?"); $total_wishlist->execute([$user_id]); $total_wishlist = $total_wishlist->fetchColumn();

$badges     = $pdo->prepare("SELECT b.*, ub.diperoleh_at FROM user_badge ub JOIN badge b ON ub.badge_id=b.id WHERE ub.user_id=? ORDER BY ub.diperoleh_at DESC");
$badges->execute([$user_id]); $badges = $badges->fetchAll();
$all_badges = $pdo->query("SELECT * FROM badge ORDER BY syarat_poin ASC")->fetchAll();
$earned_ids = array_column($badges, 'id');

$fav = $pdo->prepare("SELECT b.genre, COUNT(*) AS total FROM peminjaman p JOIN buku b ON p.buku_id=b.id WHERE p.user_id=? GROUP BY b.genre ORDER BY total DESC LIMIT 5");
$fav->execute([$user_id]); $fav_genres = $fav->fetchAll();

$aktivitas = $pdo->prepare("SELECT p.*, b.judul, b.genre FROM peminjaman p JOIN buku b ON p.buku_id=b.id WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 5");
$aktivitas->execute([$user_id]); $aktivitas = $aktivitas->fetchAll();

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

function getLevel($poin) {
    if ($poin>=500) return ['name'=>'Legenda','icon'=>'fa-crown',    'color'=>'#ffd700','next'=>null,'min'=>500];
    if ($poin>=200) return ['name'=>'Master', 'icon'=>'fa-gem',      'color'=>'#00e5ff','next'=>500, 'min'=>200];
    if ($poin>=100) return ['name'=>'Ahli',   'icon'=>'fa-trophy',   'color'=>'#ff9800','next'=>200, 'min'=>100];
    if ($poin>=50)  return ['name'=>'Aktif',  'icon'=>'fa-fire',     'color'=>'#4fc3f7','next'=>100, 'min'=>50];
    if ($poin>=20)  return ['name'=>'Pemula', 'icon'=>'fa-star',     'color'=>'#81c784','next'=>50,  'min'=>20];
    return              ['name'=>'Baru',  'icon'=>'fa-seedling', 'color'=>'#9e9e9e','next'=>20,  'min'=>0];
}
$level = getLevel($user['poin']);
$progress_pct = $level['next'] ? min(100,round(($user['poin']-$level['min'])/($level['next']-$level['min'])*100)) : 100;
$foto_url = !empty($user['foto_profil']) ? BASE_URL.'/uploads/foto_profil/'.$user['foto_profil'] : null;

$title = "Profil — CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
body{
  background-color:#e8f0f8 !important;
  background-image:url('gambar perpustakaan.jpg') !important;
  background-size:cover !important;
  background-position:center center !important;
  background-attachment:fixed !important;
  background-repeat:no-repeat !important;
  min-height:100vh !important;
}
body::before{content:'';position:fixed;inset:0;z-index:0;background:rgba(200,220,245,0.30);pointer-events:none;}
.main-wrap,.container,main{background:transparent !important;}
:root{
  --d1:#0f2744;--d2:#1e4a82;--d3:#3a6186;
  --pk:#db2777;--gold:#b45309;--gold-l:#d97706;
  --text:#0a1628;--muted:#3d5270;
  --card:rgba(255,255,255,0.72);
  --card-b:rgba(255,255,255,0.90);
  --sh:0 4px 24px rgba(10,22,40,0.14);
  --sh-md:0 8px 36px rgba(10,22,40,0.22);
}

.profil-wrap{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:28px 20px 60px;}

/* PAGE HEADER */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:10px;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:var(--d1);display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:var(--d2);}
.page-header span{font-size:12px;color:var(--muted);background:rgba(255,255,255,0.50);padding:5px 14px;border-radius:100px;border:1px solid rgba(30,58,95,0.12);backdrop-filter:blur(8px);}

/* ALERT */
.alert{padding:13px 18px;border-radius:14px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:700;backdrop-filter:blur(16px);}
.alert-success{background:rgba(134,239,172,0.25);border:1px solid rgba(34,197,94,0.30);color:#14532d;}
.alert-danger{background:rgba(248,113,113,0.18);border:1px solid rgba(220,38,38,0.25);color:#7f1d1d;}

/* LAYOUT */
.profil-layout{display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;}
@media(max-width:900px){.profil-layout{grid-template-columns:1fr;}}

/* KARTU KIRI */
.profil-card{background:rgba(255,255,255,0.78);border:1.5px solid rgba(255,255,255,0.92);border-radius:24px;overflow:hidden;backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);box-shadow:var(--sh-md);position:sticky;top:80px;}
.profil-banner{height:100px;background:linear-gradient(135deg,#1e3a5f,#2d5986,#5b8fb9);position:relative;}
.profil-banner::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 70% 50%,rgba(244,114,182,0.25),transparent 60%);}

/* FOTO PROFIL */
.profil-avatar-wrap{display:flex;justify-content:center;margin-top:-44px;margin-bottom:14px;position:relative;z-index:1;}
.profil-avatar-container{position:relative;width:88px;height:88px;}
.profil-avatar{width:88px;height:88px;border-radius:50%;border:4px solid rgba(255,255,255,0.80);overflow:hidden;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:32px;font-weight:900;color:#fff;background:linear-gradient(135deg,var(--d1),var(--d2));box-shadow:0 4px 16px rgba(30,58,95,0.35);cursor:pointer;transition:all .2s;}
.profil-avatar img{width:100%;height:100%;object-fit:cover;}
.profil-avatar:hover .avatar-overlay{opacity:1;}
.avatar-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,0.50);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;cursor:pointer;}
.avatar-overlay i{color:#fff;font-size:20px;}
.foto-upload-input{position:absolute;inset:0;opacity:0;cursor:pointer;border-radius:50%;}
.foto-change-btn{position:absolute;bottom:2px;right:2px;width:26px;height:26px;border-radius:50%;background:var(--d2);border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:11px;box-shadow:0 2px 8px rgba(0,0,0,0.25);}

.profil-nama{text-align:center;padding:0 20px 4px;font-family:'Syne',sans-serif;font-size:18px;font-weight:900;color:var(--d1);}
.profil-role{text-align:center;font-size:11px;color:var(--muted);margin-bottom:14px;display:flex;align-items:center;justify-content:center;gap:5px;font-weight:700;}

/* LEVEL */
.level-wrap{padding:14px 20px;border-top:1px solid rgba(30,58,95,0.10);border-bottom:1px solid rgba(30,58,95,0.10);background:rgba(255,255,255,0.12);}
.level-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.level-name{font-size:13px;font-weight:800;display:flex;align-items:center;gap:6px;}
.level-poin{font-size:12px;color:var(--gold);font-weight:700;display:flex;align-items:center;gap:4px;}
.progress-bar{height:7px;border-radius:4px;background:rgba(30,58,95,0.12);overflow:hidden;}
.progress-fill{height:100%;border-radius:4px;transition:width .8s ease;}
.progress-info{display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-top:4px;}

/* STAT */
.profil-stats{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:rgba(30,58,95,0.08);}
.profil-stat-item{background:rgba(255,255,255,0.65);padding:14px;text-align:center;backdrop-filter:blur(8px);}
.profil-stat-item .num{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:var(--d1);}
.profil-stat-item .lbl{font-size:10px;color:var(--muted);margin-top:2px;font-weight:700;}

.profil-bio{padding:14px 20px;font-size:13px;color:var(--d1);line-height:1.6;border-top:1px solid rgba(15,39,68,0.10);font-weight:600;}

/* KANAN */
.profil-right{display:flex;flex-direction:column;gap:0;}
.tab-nav{display:flex;gap:4px;border-bottom:2px solid rgba(30,58,95,0.10);margin-bottom:20px;overflow-x:auto;flex-wrap:nowrap;}
.tab-btn{padding:9px 14px;font-size:12px;font-weight:800;color:var(--muted);background:none;border:none;border-bottom:3px solid transparent;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .2s;margin-bottom:-2px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;}
.tab-btn:hover{color:var(--d2);}
.tab-btn.active{color:var(--d2);border-bottom-color:var(--d2);}
.tab-content{display:none;}
.tab-content.active{display:block;}

/* PANEL */
.panel{background:rgba(255,255,255,0.78);border:1.5px solid rgba(255,255,255,0.92);border-radius:18px;padding:22px;margin-bottom:16px;backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);box-shadow:var(--sh);}
.panel h4{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;margin-bottom:18px;display:flex;align-items:center;gap:8px;color:var(--d1);}
.panel h4 i{color:var(--d2);}

/* BADGE */
.badge-item{background:rgba(255,255,255,0.65);border:1.5px solid rgba(30,58,95,0.12);border-radius:14px;padding:16px 10px;text-align:center;transition:all .2s;backdrop-filter:blur(8px);}
.badge-item.earned{border-color:rgba(180,83,9,0.35);background:rgba(245,158,11,0.12);}
.badge-item.locked{opacity:.45;filter:grayscale(1);}
.badge-icon-wrap{width:44px;height:44px;border-radius:14px;background:rgba(30,58,95,0.08);display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:20px;}
.badge-item.earned .badge-icon-wrap{background:rgba(180,83,9,0.14);color:var(--gold);}
.badge-name{font-size:11px;font-weight:900;color:var(--d1);margin-bottom:3px;}
.badge-desc{font-size:10px;color:var(--muted);font-weight:600;}
.badge-date{font-size:10px;color:var(--gold);margin-top:4px;display:flex;align-items:center;justify-content:center;gap:3px;font-weight:700;}

/* FORM */
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:10px;font-weight:900;margin-bottom:7px;letter-spacing:.6px;text-transform:uppercase;color:var(--d1);}
.form-group input,.form-group textarea{width:100%;border-radius:10px;padding:10px 14px;font-size:13px;font-family:'Nunito',sans-serif;outline:none;background:rgba(255,255,255,0.80);border:1.5px solid rgba(15,39,68,0.20);color:var(--d1);transition:border-color .2s;font-weight:600;}
.form-group input::placeholder,.form-group textarea::placeholder{color:var(--muted);}
.form-group textarea{resize:vertical;min-height:90px;border-radius:12px;}
.form-group input:focus,.form-group textarea:focus{border-color:var(--d2);box-shadow:0 0 0 3px rgba(45,89,134,0.10);}
.btn-submit{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:100px;background:linear-gradient(135deg,var(--d1),var(--d2));color:#fff;font-size:13px;font-weight:900;border:none;cursor:pointer;font-family:'Nunito',sans-serif;box-shadow:0 3px 12px rgba(30,58,95,0.30);transition:all .2s;}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(30,58,95,0.40);}

/* GENRE BAR */
.genre-bar-item{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.genre-bar-label{font-size:12px;font-weight:700;min-width:80px;color:var(--text);display:flex;align-items:center;gap:5px;}
.genre-bar-track{flex:1;height:8px;border-radius:4px;background:rgba(30,58,95,0.10);overflow:hidden;}
.genre-bar-fill{height:100%;border-radius:4px;}
.genre-bar-count{font-size:12px;color:var(--muted);min-width:24px;text-align:right;font-weight:700;}

/* AKTIVITAS */
.akt-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(30,58,95,0.08);}
.akt-item:last-child{border-bottom:none;}
.akt-cover{width:36px;height:50px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;color:#fff;}
.akt-info{flex:1;min-width:0;}
.akt-info h5{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text);}
.akt-info p{font-size:11px;color:var(--muted);margin-top:2px;}
.akt-status{font-size:10px;font-weight:800;flex-shrink:0;padding:3px 8px;border-radius:6px;}

/* FOTO UPLOAD AREA */
.foto-upload-area{border:2px dashed rgba(30,58,95,0.25);border-radius:14px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:rgba(255,255,255,0.20);}
.foto-upload-area:hover{border-color:var(--d2);background:rgba(255,255,255,0.35);}
.foto-upload-area input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}
</style>

<div class="profil-wrap">

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <h2><i class="fas fa-user-circle"></i> Profil Saya</h2>
  <span><i class="fas fa-calendar-alt" style="font-size:10px;"></i> Bergabung <?= formatTanggal($user['created_at']) ?></span>
</div>

<!-- ALERT -->
<?php if($pesan): ?>
<div class="alert alert-<?= $pesan_type ?> fu1">
  <i class="fas fa-<?= $pesan_type==='success'?'check-circle':'exclamation-triangle' ?>"></i> <?= $pesan ?>
</div>
<?php endif; ?>

<div class="profil-layout fu2">

  <!-- KOLOM KIRI -->
  <div class="profil-card">
    <div class="profil-banner"></div>

    <!-- FOTO PROFIL + UPLOAD -->
    <div class="profil-avatar-wrap">
      <div class="profil-avatar-container">
        <form method="POST" enctype="multipart/form-data" id="fotoForm">
          <div class="profil-avatar" onclick="document.getElementById('fotoInput').click();">
            <?php if($foto_url): ?>
              <img src="<?= $foto_url ?>" alt="Foto Profil">
            <?php else: ?>
              <?= strtoupper(substr($user['nama'],0,1)) ?>
            <?php endif; ?>
            <div class="avatar-overlay">
              <i class="fas fa-camera"></i>
            </div>
          </div>
          <input type="file" name="foto_profil" id="fotoInput" accept="image/*" style="display:none;" onchange="document.getElementById('fotoForm').submit();">
        </form>
        <div class="foto-change-btn" onclick="document.getElementById('fotoInput').click();" title="Ganti foto">
          <i class="fas fa-camera"></i>
        </div>
      </div>
    </div>

    <div class="profil-nama"><?= e($user['nama']) ?></div>
    <div class="profil-role">
      <i class="fas fa-user-graduate" style="font-size:10px;color:var(--d3);"></i> Mahasiswa
    </div>

    <!-- LEVEL -->
    <div class="level-wrap">
      <div class="level-header">
        <span class="level-name">
          <i class="fas <?= $level['icon'] ?>" style="color:<?= $level['color'] ?>;"></i>
          <span style="color:<?= $level['color'] ?>;"><?= $level['name'] ?></span>
        </span>
        <span class="level-poin">
          <i class="fas fa-star" style="font-size:10px;"></i> <?= $user['poin'] ?> poin
        </span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $progress_pct ?>%;background:linear-gradient(90deg,<?= $level['color'] ?>,<?= $level['color'] ?>88);"></div>
      </div>
      <div class="progress-info">
        <span><?= $user['poin'] ?> poin</span>
        <?php if($level['next']): ?>
          <span>Selanjutnya: <?= $level['next'] ?> poin</span>
        <?php else: ?>
          <span><i class="fas fa-check-circle" style="color:#4ade80;font-size:9px;"></i> Level Maksimum!</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- STAT -->
    <div class="profil-stats">
      <div class="profil-stat-item">
        <div class="num"><?= $stat['total_pinjam'] ?></div>
        <div class="lbl">Total Pinjam</div>
      </div>
      <div class="profil-stat-item">
        <div class="num"><?= $stat['selesai'] ?></div>
        <div class="lbl">Selesai</div>
      </div>
      <div class="profil-stat-item">
        <div class="num"><?= $total_review ?></div>
        <div class="lbl">Review</div>
      </div>
      <div class="profil-stat-item">
        <div class="num"><?= count($badges) ?></div>
        <div class="lbl">Badge</div>
      </div>
    </div>

    <!-- BIO -->
    <div class="profil-bio">
      <?php if($user['bio']): ?>
        <?= nl2br(e($user['bio'])) ?>
      <?php else: ?>
        <span style="color:rgba(30,58,95,0.30);font-style:italic;font-size:12px;">
          <i class="fas fa-pen" style="font-size:10px;"></i> Belum ada bio. Klik Edit Profil untuk menambahkan.
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- KOLOM KANAN -->
  <div class="profil-right fu3">
    <div class="tab-nav">
      <button class="tab-btn active" onclick="showTab('badge',this)"><i class="fas fa-trophy"></i> Badge</button>
      <button class="tab-btn" onclick="showTab('genre',this)"><i class="fas fa-chart-bar"></i> Statistik</button>
      <button class="tab-btn" onclick="showTab('aktivitas',this)"><i class="fas fa-clock"></i> Aktivitas</button>
      <button class="tab-btn" onclick="showTab('edit',this)"><i class="fas fa-edit"></i> Edit Profil</button>
      <button class="tab-btn" onclick="showTab('password',this)"><i class="fas fa-lock"></i> Password</button>
    </div>

    <!-- TAB BADGE -->
    <div class="tab-content active" id="tab-badge">
      <div class="panel">
        <h4><i class="fas fa-trophy"></i> Koleksi Badge</h4>
        <div class="badge-grid">
          <?php foreach($all_badges as $b):
            $earned    = in_array($b['id'],$earned_ids);
            $earn_data = null;
            if($earned) foreach($badges as $ub) { if($ub['id']==$b['id']){$earn_data=$ub;break;} }
          ?>
          <div class="badge-item <?= $earned?'earned':'locked' ?>">
            <div class="badge-icon-wrap">
              <i class="fas fa-<?= $earned?'medal':'lock' ?>" style="font-size:18px;color:<?= $earned?'#d97706':'#9ca3af' ?>;"></i>
            </div>
            <div class="badge-name"><?= e($b['nama']) ?></div>
            <div class="badge-desc"><?= e($b['deskripsi']) ?></div>
            <?php if($earned && $earn_data): ?>
              <div class="badge-date"><i class="fas fa-check-circle"></i> <?= formatTanggal($earn_data['diperoleh_at']) ?></div>
            <?php else: ?>
              <div class="badge-desc" style="margin-top:4px;color:#9ca3af;"><i class="fas fa-lock" style="font-size:9px;"></i> <?= $b['syarat_poin'] ?> poin</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if(!$all_badges): ?>
          <div style="text-align:center;padding:28px;color:var(--muted);font-weight:700;">
            <i class="fas fa-trophy" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>Belum ada badge tersedia.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- TAB GENRE -->
    <div class="tab-content" id="tab-genre">
      <div class="panel">
        <h4><i class="fas fa-chart-bar"></i> Statistik Baca</h4>
        <?php if($fav_genres):
          $max = $fav_genres[0]['total'];
          $colors = ['#2563eb','#d97706','#16a34a','#dc2626','#7c3aed'];
        ?>
          <div style="margin-bottom:20px;">
            <div style="font-size:12px;color:var(--muted);font-weight:700;margin-bottom:14px;">Genre yang paling sering kamu baca:</div>
            <?php foreach($fav_genres as $i=>$fg):
              $pct = round($fg['total']/$max*100);
              $gw  = $genre_warna[$fg['genre']] ?? ['icon'=>'fa-book'];
              $col = $colors[$i] ?? '#2563eb';
            ?>
            <div class="genre-bar-item">
              <div class="genre-bar-label"><i class="fas <?= $gw['icon'] ?>" style="color:<?= $col ?>;font-size:11px;"></i> <?= e($fg['genre']) ?></div>
              <div class="genre-bar-track"><div class="genre-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
              <div class="genre-bar-count"><?= $fg['total'] ?>x</div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="background:rgba(255,255,255,0.30);border:1px solid rgba(30,58,95,0.12);border-radius:12px;padding:16px;backdrop-filter:blur(8px);">
              <div style="font-size:10px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Genre Favorit</div>
              <div style="font-size:16px;font-weight:800;color:var(--d1);display:flex;align-items:center;gap:6px;">
                <i class="fas <?= $genre_warna[$fav_genres[0]['genre']]['icon'] ?? 'fa-book' ?>" style="color:var(--d2);"></i>
                <?= e($fav_genres[0]['genre']) ?>
              </div>
            </div>
            <div style="background:rgba(255,255,255,0.30);border:1px solid rgba(30,58,95,0.12);border-radius:12px;padding:16px;backdrop-filter:blur(8px);">
              <div style="font-size:10px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Total Wishlist</div>
              <div style="font-family:'Syne',sans-serif;font-size:26px;font-weight:900;color:var(--d2);"><?= $total_wishlist ?></div>
            </div>
          </div>
        <?php else: ?>
          <div style="text-align:center;padding:28px;color:var(--muted);font-weight:700;">
            <i class="fas fa-chart-bar" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>Belum ada data. Mulai pinjam buku!
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- TAB AKTIVITAS -->
    <div class="tab-content" id="tab-aktivitas">
      <div class="panel">
        <h4><i class="fas fa-clock"></i> Aktivitas Terbaru</h4>
        <?php if($aktivitas): ?>
          <?php foreach($aktivitas as $a):
            $gw = $genre_warna[$a['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
            $scol = ['aktif'=>'#16a34a','hampir_habis'=>'#d97706','expired'=>'#dc2626','dikembalikan'=>'#6b7280'];
            $sbg  = ['aktif'=>'rgba(22,163,74,0.12)','hampir_habis'=>'rgba(217,119,6,0.12)','expired'=>'rgba(220,38,38,0.12)','dikembalikan'=>'rgba(107,114,128,0.12)'];
          ?>
          <div class="akt-item">
            <div class="akt-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
              <i class="fas <?= $gw['icon'] ?>"></i>
            </div>
            <div class="akt-info">
              <h5><?= e($a['judul']) ?></h5>
              <p><?= formatTanggal($a['tanggal_pinjam']) ?> — <?= formatTanggal($a['tanggal_expired']) ?></p>
            </div>
            <span class="akt-status" style="color:<?= $scol[$a['status']]??'#6b7280' ?>;background:<?= $sbg[$a['status']]??'rgba(107,114,128,0.12)' ?>;">
              <?= ucfirst(str_replace('_',' ',$a['status'])) ?>
            </span>
          </div>
          <?php endforeach; ?>
          <div style="margin-top:14px;">
            <a href="<?= BASE_URL ?>/mahasiswa/riwayat.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:100px;background:rgba(30,58,95,0.10);color:var(--d2);font-size:12px;font-weight:800;text-decoration:none;border:1px solid rgba(30,58,95,0.18);transition:all .2s;">
              <i class="fas fa-history"></i> Lihat Semua Riwayat
            </a>
          </div>
        <?php else: ?>
          <div style="text-align:center;padding:28px;color:var(--muted);font-weight:700;">
            <i class="fas fa-clock" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>Belum ada aktivitas.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- TAB EDIT PROFIL -->
    <div class="tab-content" id="tab-edit">
      <div class="panel">
        <h4><i class="fas fa-user-edit"></i> Edit Informasi Profil</h4>

        <!-- UPLOAD FOTO SECTION -->
        <div style="margin-bottom:20px;">
          <div class="form-group" style="margin-bottom:0;">
            <label>Foto Profil</label>
          </div>
          <form method="POST" enctype="multipart/form-data">
            <div class="foto-upload-area">
              <input type="file" name="foto_profil" accept="image/*" onchange="this.form.submit();">
              <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:var(--d3);margin-bottom:8px;display:block;"></i>
              <div style="font-size:13px;font-weight:700;color:var(--d2);">Klik untuk upload foto baru</div>
              <div style="font-size:11px;color:var(--muted);margin-top:4px;">JPG, PNG, WEBP · Maks. 2MB</div>
            </div>
          </form>
        </div>

        <form method="POST">
          <input type="hidden" name="aksi" value="update_profil">
          <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" value="<?= e($user['nama']) ?>" placeholder="Nama lengkap kamu" required>
          </div>
          <div class="form-group">
            <label>Bio / Deskripsi Singkat</label>
            <textarea name="bio" placeholder="Ceritakan sedikit tentang dirimu..."><?= e($user['bio'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" value="<?= e($user['email']) ?>" disabled style="opacity:.5;cursor:not-allowed;">
            <div style="font-size:11px;color:var(--muted);margin-top:4px;"><i class="fas fa-lock" style="font-size:9px;"></i> Email tidak dapat diubah.</div>
          </div>
          <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Simpan Perubahan</button>
        </form>
      </div>
    </div>

    <!-- TAB PASSWORD -->
    <div class="tab-content" id="tab-password">
      <div class="panel">
        <h4><i class="fas fa-lock"></i> Ganti Password</h4>
        <form method="POST">
          <input type="hidden" name="aksi" value="ganti_password">
          <div class="form-group">
            <label>Password Lama</label>
            <div style="position:relative;">
              <input type="password" name="password_lama" id="passLama" placeholder="Password saat ini" required>
              <button type="button" onclick="togglePass('passLama',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>Password Baru</label>
            <div style="position:relative;">
              <input type="password" name="password_baru" id="passBaru" placeholder="Minimal 6 karakter" required>
              <button type="button" onclick="togglePass('passBaru',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>Konfirmasi Password Baru</label>
            <input type="password" name="konfirmasi" placeholder="Ulangi password baru" required>
          </div>
          <button type="submit" class="btn-submit"><i class="fas fa-key"></i> Ganti Password</button>
        </form>
      </div>
      <div style="background:rgba(248,113,113,0.08);border:1px solid rgba(220,38,38,0.18);border-radius:12px;padding:14px 16px;font-size:12px;color:var(--muted);backdrop-filter:blur(8px);">
        <i class="fas fa-shield-alt" style="color:#dc2626;margin-right:6px;"></i>
        Gunakan password kuat: kombinasi huruf besar, kecil, angka, dan simbol.
      </div>
    </div>

  </div>
</div>

</div>

<footer style="position:relative;z-index:1;text-align:center;padding:24px;font-size:12px;color:var(--muted);font-weight:700;border-top:1.5px dashed rgba(30,58,95,0.12);background:rgba(255,255,255,0.30);backdrop-filter:blur(16px);">
  <i class="fas fa-cloud" style="color:var(--d2);"></i> <strong style="color:var(--d2);">CloudLibrary Mini</strong> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
function showTab(id,btn){
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  btn.classList.add('active');
}
function togglePass(id,btn){
  const inp=document.getElementById(id);
  const isPass=inp.type==='password';
  inp.type=isPass?'text':'password';
  btn.querySelector('i').className=isPass?'fas fa-eye-slash':'fas fa-eye';
}
<?php if($pesan&&in_array($_POST['aksi']??'',['update_profil'])): ?>
document.addEventListener('DOMContentLoaded',()=>{showTab('edit',document.querySelectorAll('.tab-btn')[3]);});
<?php elseif($pesan&&($_POST['aksi']??'')==='ganti_password'): ?>
document.addEventListener('DOMContentLoaded',()=>{showTab('password',document.querySelectorAll('.tab-btn')[4]);});
<?php endif; ?>
</script>
</body>
</html>
