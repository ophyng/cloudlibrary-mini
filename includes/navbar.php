<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= isset($title) ? e($title) : 'CloudLibrary Mini' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Nunito',sans-serif;min-height:100vh;}

    /* ══ SIDEBAR — Glass Transparent ══ */
    .app-sidebar{
      position:fixed;top:0;left:0;width:220px;height:100vh;
      display:flex;flex-direction:column;z-index:999;
      overflow-y:auto;overflow-x:hidden;
      transition:width .3s ease;
      background:rgba(8,16,40,0.60);
      backdrop-filter:blur(20px);
      -webkit-backdrop-filter:blur(20px);
      border-right:1px solid rgba(255,255,255,0.10);
      box-shadow:4px 0 28px rgba(0,0,0,0.30);
    }

    .sb-brand{padding:22px 18px 14px;border-bottom:1px solid rgba(255,255,255,0.08);text-decoration:none;display:block;}
    .sb-brand-top{display:flex;align-items:center;gap:9px;font-family:'Syne',sans-serif;font-size:15px;font-weight:900;color:#fff;}
    .sb-brand-top i{font-size:16px;color:#60a5fa;}
    .sb-badge{
      display:inline-flex;align-items:center;gap:5px;margin-top:7px;
      font-size:9px;font-weight:900;color:rgba(255,255,255,0.85);
      padding:3px 10px;border-radius:100px;letter-spacing:.5px;
      text-transform:uppercase;width:fit-content;
      background:rgba(255,255,255,0.12);
      border:1px solid rgba(255,255,255,0.18);
    }

    .sb-user{padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.04);}
    .sb-avatar{width:36px;height:36px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:#fff;background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.25);}
    .sb-user-name{font-family:'Syne',sans-serif;font-size:12px;font-weight:900;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .sb-user-role{font-size:10px;font-weight:700;color:rgba(255,255,255,0.50);margin-top:2px;display:flex;align-items:center;gap:5px;}
    .sb-poin{display:inline-flex;align-items:center;gap:4px;margin-top:4px;font-size:10px;font-weight:800;padding:2px 8px;border-radius:100px;background:rgba(251,191,36,0.15);color:#fbbf24;border:1px solid rgba(251,191,36,0.25);}

    .sb-nav{padding:14px 10px;flex:1;}
    .sb-lbl{font-size:9px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px 8px;color:rgba(255,255,255,0.35);}
    .sb-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:12px;font-weight:800;text-decoration:none;color:rgba(255,255,255,0.60);margin-bottom:3px;transition:all .2s;position:relative;}
    .sb-link:hover{background:rgba(255,255,255,0.08);color:#fff;transform:translateX(3px);}
    .sb-link.active{background:rgba(255,255,255,0.14);color:#fff;border:1px solid rgba(255,255,255,0.18);}
    .sb-link.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;border-radius:0 3px 3px 0;background:#60a5fa;}
    .sb-ico{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.65);position:relative;transition:background .2s;}
    .sb-link:hover .sb-ico,.sb-link.active .sb-ico{background:rgba(255,255,255,0.16);color:#fff;}
    .notif-dot{position:absolute;top:-3px;right:-3px;width:8px;height:8px;background:#ef4444;border-radius:50%;border:2px solid transparent;}

    .sb-bottom{padding:14px 10px;border-top:1px solid rgba(255,255,255,0.08);background:rgba(0,0,0,0.12);}
    .sb-logout{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:12px;font-weight:800;color:rgba(248,113,113,0.80);cursor:pointer;background:none;border:none;width:100%;transition:all .2s;font-family:'Nunito',sans-serif;}
    .sb-logout:hover{background:rgba(248,113,113,0.10);color:#f87171;}
    .sb-logout .sb-ico{background:rgba(248,113,113,0.10);color:rgba(248,113,113,0.70);}
    .sb-profil-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:12px;font-weight:800;color:rgba(255,255,255,0.60);text-decoration:none;margin-bottom:4px;transition:all .2s;}
    .sb-profil-link:hover{background:rgba(255,255,255,0.08);color:#fff;}
    .sb-profil-link .sb-ico{background:rgba(255,255,255,0.07);}

    .main-wrap{margin-left:220px;min-height:100vh;}
    .container{max-width:1100px;margin:0 auto;padding:28px 28px 40px;}
    .footer{text-align:center;padding:20px 28px;font-size:12px;font-weight:700;border-top:1px solid rgba(255,255,255,0.08);position:relative;z-index:1;color:rgba(255,255,255,0.45);}
    .footer span{color:rgba(255,255,255,0.75);}

    /* shared */
    .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;font-size:12px;font-weight:800;font-family:'Nunito',sans-serif;text-decoration:none;cursor:pointer;border:none;transition:all .2s;}
    .btn-sm{padding:6px 14px;font-size:11px;}
    .alert{padding:13px 16px;border-radius:12px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-family:'Nunito',sans-serif;font-weight:700;}
    .alert-danger {background:rgba(248,113,113,0.12);border:1.5px solid rgba(248,113,113,0.25);color:#f87171;}
    .alert-success{background:rgba(74,222,128,0.12);border:1.5px solid rgba(74,222,128,0.25);color:#4ade80;}
    .alert-warning{background:rgba(251,191,36,0.12);border:1.5px solid rgba(251,191,36,0.25);color:#fbbf24;}
    .alert-info   {background:rgba(96,165,250,0.12);border:1.5px solid rgba(96,165,250,0.25);color:#93c5fd;}
    .form-group{margin-bottom:18px;}
    .form-group label{display:block;font-size:10px;font-weight:900;margin-bottom:7px;letter-spacing:.7px;text-transform:uppercase;color:rgba(255,255,255,0.55);}
    .form-group input,.form-group select,.form-group textarea{width:100%;border-radius:8px;padding:10px 16px;font-size:13px;font-family:'Nunito',sans-serif;outline:none;background:rgba(255,255,255,0.10);border:1.5px solid rgba(255,255,255,0.18);color:#fff;transition:border-color .2s;}
    .form-group input::placeholder,.form-group textarea::placeholder{color:rgba(255,255,255,0.30);}
    .form-group select option{background:#1e3a5f;color:#fff;}
    .form-group textarea{border-radius:10px;resize:vertical;min-height:100px;}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:rgba(96,165,250,0.55);box-shadow:0 0 0 3px rgba(96,165,250,0.10);}
    .empty-state{text-align:center;padding:50px 20px;color:rgba(255,255,255,0.45);}
    .empty-state i{font-size:40px;margin-bottom:14px;display:block;opacity:.4;}
    .empty-state p{font-size:14px;font-weight:700;}

    @media(max-width:768px){
      .app-sidebar{width:64px;}
      .sb-brand-top span,.sb-badge,.sb-user-info,.sb-link span,.sb-lbl,.sb-profil-link span{display:none;}
      .sb-link,.sb-profil-link{justify-content:center;padding:10px;}
      .sb-ico{margin:0;}
      .main-wrap{margin-left:64px;}
    }
  </style>
</head>

<?php
$_role = $_SESSION['role'] ?? 'mahasiswa';
$_init = strtoupper(substr($_SESSION['nama'] ?? 'U', 0, 1));
$_nama_pendek = e(explode(' ', $_SESSION['nama'] ?? 'User')[0]);
?>

<body>

<div class="app-sidebar">

  <!-- BRAND -->
  <?php if($_role === 'super_admin'): ?>
    <a href="<?= BASE_URL ?>/super_admin/dashboard.php" class="sb-brand">
      <div class="sb-brand-top"><i class="fas fa-cloud"></i><span>CloudLibrary</span></div>
      <div class="sb-badge"><i class="fas fa-crown" style="font-size:8px;color:#fbbf24;"></i> Super Admin</div>
    </a>
  <?php elseif($_role === 'admin'): ?>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sb-brand">
      <div class="sb-brand-top"><i class="fas fa-cloud"></i><span>CloudLibrary</span></div>
      <div class="sb-badge"><i class="fas fa-shield-alt" style="font-size:8px;color:#60a5fa;"></i> Admin Panel</div>
    </a>
  <?php else: ?>
    <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="sb-brand">
      <div class="sb-brand-top"><i class="fas fa-cloud"></i><span>CloudLibrary</span></div>
      <div class="sb-badge"><i class="fas fa-user-graduate" style="font-size:8px;color:#a78bfa;"></i> Mahasiswa</div>
    </a>
  <?php endif; ?>

  <!-- USER INFO -->
  <?php if(isset($_SESSION['user_id'])): ?>
  <div class="sb-user">
    <div class="sb-avatar"><?= $_init ?></div>
    <div class="sb-user-info">
      <div class="sb-user-name"><?= $_nama_pendek ?></div>
      <div class="sb-user-role">
        <?php if($_role==='super_admin'): ?>
          <i class="fas fa-crown" style="font-size:9px;color:#fbbf24;"></i> Super Admin
        <?php elseif($_role==='admin'): ?>
          <i class="fas fa-shield-alt" style="font-size:9px;color:#60a5fa;"></i> Administrator
        <?php else: ?>
          <i class="fas fa-user-graduate" style="font-size:9px;color:#a78bfa;"></i> Mahasiswa
        <?php endif; ?>
      </div>
      <?php if($_role === 'mahasiswa'):
        $up = $pdo->prepare("SELECT poin FROM users WHERE id=?");
        $up->execute([$_SESSION['user_id']]);
        $poin_user = $up->fetchColumn() ?: 0; ?>
        <div class="sb-poin"><i class="fas fa-star" style="font-size:9px;"></i> <?= $poin_user ?> poin</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- NAV LINKS -->
  <nav class="sb-nav">

    <?php if($_role === 'super_admin'): ?>
      <div class="sb-lbl">Menu Utama</div>
      <a href="<?= BASE_URL ?>/super_admin/dashboard.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'dashboard')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-home"></i></div><span>Dashboard</span>
      </a>
      <a href="<?= BASE_URL ?>/super_admin/admin.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'/admin.')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-users-cog"></i></div><span>Kelola Admin</span>
      </a>
      <a href="<?= BASE_URL ?>/super_admin/users.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'users')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-users"></i></div><span>Kelola User</span>
      </a>
      <a href="<?= BASE_URL ?>/super_admin/statistik.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'statistik')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-chart-bar"></i></div><span>Statistik</span>
      </a>
      <a href="<?= BASE_URL ?>/super_admin/broadcast.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'broadcast')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-bullhorn"></i></div><span>Broadcast</span>
      </a>
      <a href="<?= BASE_URL ?>/super_admin/log.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'/log.')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-history"></i></div><span>Log Aktivitas</span>
      </a>
      <a href="<?= BASE_URL ?>/super_admin/settings.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'settings')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-cog"></i></div><span>Pengaturan</span>
      </a>

    <?php elseif($_role === 'admin'): ?>
      <div class="sb-lbl">Menu Admin</div>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'dashboard')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-tachometer-alt"></i></div><span>Dashboard</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/buku/index.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'/buku/')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-book"></i></div><span>Buku</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/peminjaman/index.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'/peminjaman/')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-clock"></i></div><span>Peminjaman</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/pengguna/index.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'/pengguna/')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-users"></i></div><span>Pengguna</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/review/index.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'/review/')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-star"></i></div><span>Review</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/statistik.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'statistik')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-chart-bar"></i></div><span>Statistik</span>
      </a>

    <?php else: ?>
      <div class="sb-lbl">Menu</div>
      <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'dashboard')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-home"></i></div><span>Dashboard</span>
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'katalog')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-book"></i></div><span>Katalog Buku</span>
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/riwayat.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'riwayat')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-history"></i></div><span>Riwayat</span>
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/wishlist.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'wishlist')!==false?'active':'' ?>">
        <div class="sb-ico"><i class="fas fa-heart"></i></div><span>Wishlist</span>
      </a>
      <a href="<?= BASE_URL ?>/mahasiswa/notifikasi.php" class="sb-link <?= strpos($_SERVER['PHP_SELF'],'notifikasi')!==false?'active':'' ?>">
        <div class="sb-ico">
          <i class="fas fa-bell"></i>
          <?php
          $notif_count = jumlahNotifBelumDibaca($pdo, $_SESSION['user_id']);
          if($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </div><span>Notifikasi</span>
      </a>
    <?php endif; ?>

  </nav>

  <!-- BOTTOM -->
  <div class="sb-bottom">
    <?php if($_role === 'mahasiswa'): ?>
    <a href="<?= BASE_URL ?>/mahasiswa/profil.php" class="sb-profil-link">
      <div class="sb-ico"><i class="fas fa-user-circle"></i></div><span>Profil Saya</span>
    </a>
    <?php endif; ?>
    <button class="sb-logout" onclick="window.location='<?= BASE_URL ?>/auth/logout.php'">
      <div class="sb-ico"><i class="fas fa-sign-out-alt"></i></div><span>Keluar</span>
    </button>
  </div>

</div><!-- /sidebar -->

<div class="main-wrap">
<div class="container">