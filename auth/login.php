<?php
session_start();
require_once '../config/db.php';
if (isset($_SESSION['user_id'])) {
    header("Location: ".BASE_URL."/".$_SESSION['role']."/dashboard.php"); exit();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = md5(trim($_POST['password'] ?? ''));
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND password=? AND status='aktif'");
    $stmt->execute([$email,$password]); $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id']=$user['id']; $_SESSION['nama']=$user['nama']; $_SESSION['role']=$user['role'];
        header("Location: ".BASE_URL."/".($user['role']==='admin'?'admin':'mahasiswa')."/dashboard.php"); exit();
    } else { $error='Email atau password salah, atau akun dinonaktifkan.'; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — CloudLibrary Mini</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    :root{--d1:#0f2744;--d2:#1e4a82;--d3:#3a6186;--d4:#5b8fb9;--pk:#f472b6;--pk2:#fbbfd8;--gold:#f9c74f;--text:#0a1628;--muted:#3d5270;}
    *{box-sizing:border-box;margin:0;padding:0;}html,body{height:100%;}
    body{font-family:'Nunito',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;background:url('gambar_library.jpg') center center/cover no-repeat fixed;}
    body::before{content:'';position:fixed;inset:0;z-index:0;background:rgba(10,22,48,0.55);}

    .login-wrap{position:relative;z-index:2;display:flex;width:900px;max-width:96vw;border-radius:28px;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,0.45),0 8px 24px rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.14);animation:popIn .5s cubic-bezier(.34,1.56,.64,1) forwards;}
    @keyframes popIn{from{opacity:0;transform:scale(.93)}to{opacity:1;transform:scale(1)}}

    /* LEFT — foto bg */
    .login-left{flex:1;background:url('gambar_library.jpg') center center/cover no-repeat;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;padding:44px 36px;min-height:520px;}
    .login-left::before{content:'';position:absolute;inset:0;z-index:0;background:linear-gradient(160deg,rgba(14,30,60,0.88) 0%,rgba(20,50,90,0.78) 50%,rgba(30,74,130,0.70) 100%);}
    .login-left::after{content:'';position:absolute;inset:12px;z-index:1;border:1.5px dashed rgba(255,255,255,0.15);border-radius:18px;pointer-events:none;}
    .ll-glow{position:absolute;top:-60px;left:-60px;z-index:1;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(244,114,182,0.20) 0%,transparent 70%);pointer-events:none;}
    .ll-inner{position:relative;z-index:2;display:flex;flex-direction:column;height:100%;}
    .ll-brand{display:flex;align-items:center;gap:10px;}
    .ll-brand-ico{width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.28);display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;}
    .ll-brand-txt{font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:#fff;}
    .ll-heading{margin-top:auto;padding-top:28px;}
    .ll-heading h2{font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:#fff;line-height:1.2;margin-bottom:10px;}
    .ll-heading h2 em{font-style:normal;color:var(--pk2);}
    .ll-heading p{font-size:13px;color:rgba(255,255,255,0.60);line-height:1.7;margin-bottom:24px;font-weight:600;}
    .features{display:flex;flex-direction:column;gap:12px;}
    .feat{display:flex;align-items:center;gap:12px;font-size:13px;color:rgba(255,255,255,0.88);font-weight:700;}
    .feat-ico{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.22);display:flex;align-items:center;justify-content:center;font-size:13px;color:#fff;flex-shrink:0;}
    .ll-foot{font-size:11px;color:rgba(255,255,255,0.28);font-weight:700;margin-top:24px;}

    /* RIGHT — form */
    .login-right{flex:1.1;padding:48px 44px;display:flex;flex-direction:column;justify-content:center;background:rgba(255,255,255,0.78);backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);border-left:1px solid rgba(255,255,255,0.40);}
    .form-logo{display:flex;align-items:center;gap:9px;margin-bottom:28px;}
    .form-logo svg{width:36px;height:36px;flex-shrink:0;}
    .form-logo-txt{font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:var(--d1);line-height:1.1;}
    .form-logo-txt span{display:block;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;}
    .login-right h3{font-family:'Syne',sans-serif;font-size:24px;font-weight:900;color:var(--d1);margin-bottom:5px;}
    .login-right .sub{font-size:13px;color:var(--muted);margin-bottom:28px;font-weight:600;}
    .alert{padding:12px 16px;border-radius:12px;font-size:13px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:9px;}
    .alert-danger{background:rgba(220,38,38,0.08);border:1.5px solid rgba(220,38,38,0.22);color:#991b1b;}
    .alert-success{background:rgba(34,197,94,0.08);border:1.5px solid rgba(34,197,94,0.22);color:#14532d;}
    .form-group{margin-bottom:16px;}
    .form-group label{display:block;font-size:10px;font-weight:900;color:var(--muted);margin-bottom:6px;letter-spacing:.7px;text-transform:uppercase;}
    .input-wrap{position:relative;}
    .iico{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--d3);font-size:13px;pointer-events:none;}
    .input-wrap input{width:100%;background:rgba(255,255,255,0.90);border:1.5px solid rgba(30,74,130,0.18);border-radius:12px;padding:12px 14px 12px 40px;color:var(--d1);font-size:14px;font-weight:600;font-family:'Nunito',sans-serif;transition:border-color .2s,box-shadow .2s;outline:none;}
    .input-wrap input:focus{border-color:var(--d2);box-shadow:0 0 0 3px rgba(30,74,130,0.10);}
    .input-wrap input::placeholder{color:#aab4c4;font-weight:500;}
    .toggle-pass{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--muted);cursor:pointer;font-size:14px;background:none;border:none;transition:color .2s;padding:4px;}
    .toggle-pass:hover{color:var(--d2);}
    .btn-login{width:100%;background:linear-gradient(135deg,var(--d1),var(--d2),var(--d4));color:#fff;border:none;border-radius:100px;padding:13px;font-size:14px;font-weight:900;font-family:'Nunito',sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:transform .2s,box-shadow .2s;box-shadow:0 6px 22px rgba(30,74,130,0.40);margin-top:8px;}
    .btn-login:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(30,74,130,0.50);}
    .divider{text-align:center;margin:18px 0;position:relative;color:var(--muted);font-size:12px;font-weight:700;}
    .divider::before,.divider::after{content:'';position:absolute;top:50%;width:42%;height:1.5px;background:rgba(30,74,130,0.12);border-radius:2px;}
    .divider::before{left:0}.divider::after{right:0}
    .reg-link{text-align:center;font-size:13px;color:var(--muted);font-weight:700;}
    .reg-link a{color:var(--d2);text-decoration:none;font-weight:900;}
    .reg-link a:hover{color:var(--d1);text-decoration:underline;}
    .back-link{text-align:center;margin-top:12px;}
    .back-link a{font-size:12px;color:var(--muted);font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:color .2s;}
    .back-link a:hover{color:var(--d2);}
    @media(max-width:720px){.login-left{display:none;}.login-right{padding:36px 24px;}}
  </style>
</head>
<body>
<div class="login-wrap">

  <!-- KIRI -->
  <div class="login-left">
    <div class="ll-glow"></div>
    <div class="ll-inner">
      <div class="ll-brand">
        <div class="ll-brand-ico"><i class="fas fa-book-open"></i></div>
        <span class="ll-brand-txt">CloudLibrary Mini</span>
      </div>
      <div class="ll-heading">
        <h2>Selamat Datang <em>Kembali!</em></h2>
        <p>Masuk ke akun kamu dan lanjutkan perjalanan membaca buku digitalmu.</p>
        <div class="features">
          <div class="feat"><div class="feat-ico"><i class="fas fa-book-open"></i></div>Baca buku langsung di browser</div>
          <div class="feat"><div class="feat-ico"><i class="fas fa-clock"></i></div>Peminjaman otomatis berbatas waktu</div>
          <div class="feat"><div class="feat-ico"><i class="fas fa-star"></i></div>Review &amp; rating buku</div>
          <div class="feat"><div class="feat-ico"><i class="fas fa-trophy"></i></div>Kumpulkan poin &amp; badge</div>
        </div>
      </div>
      <div class="ll-foot"><i class="fas fa-cloud" style="margin-right:5px;color:rgba(255,255,255,0.25);font-size:10px;"></i>CloudLibrary Mini &copy; <?= date('Y') ?></div>
    </div>
  </div>

  <!-- KANAN -->
  <div class="login-right">
    <div class="form-logo">
      <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="fl1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#0f2744"/><stop offset="100%" stop-color="#1e4a82"/></linearGradient>
          <linearGradient id="fl2" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f9c74f"/><stop offset="100%" stop-color="#f472b6"/></linearGradient>
        </defs>
        <rect x="1" y="1" width="34" height="34" rx="10" fill="url(#fl1)"/>
        <path d="M5,27 L5,12 Q5,10.5 7,10 L17,9 L17,27 Z" fill="rgba(255,255,255,0.20)" stroke="rgba(255,255,255,0.40)" stroke-width=".8"/>
        <path d="M19,9 L29,10 Q31,10.5 31,12 L31,27 L19,27 Z" fill="rgba(255,255,255,0.12)" stroke="rgba(255,255,255,0.28)" stroke-width=".8"/>
        <line x1="18" y1="9" x2="18" y2="27" stroke="rgba(255,255,255,0.72)" stroke-width="1.2" stroke-linecap="round"/>
        <line x1="7" y1="14" x2="16" y2="13.5" stroke="rgba(255,255,255,0.42)" stroke-width=".9" stroke-linecap="round"/>
        <line x1="7" y1="17.5" x2="15.5" y2="17" stroke="rgba(255,255,255,0.28)" stroke-width=".9" stroke-linecap="round"/>
        <line x1="20" y1="13.5" x2="29" y2="14" stroke="rgba(255,255,255,0.42)" stroke-width=".9" stroke-linecap="round"/>
        <line x1="20.5" y1="17" x2="29" y2="17.5" stroke="rgba(255,255,255,0.28)" stroke-width=".9" stroke-linecap="round"/>
        <circle cx="28" cy="9" r="5.5" fill="#0f2744"/>
        <polygon points="28,5.5 28.9,8 31.5,8 29.4,9.5 30.2,12 28,10.6 25.8,12 26.6,9.5 24.5,8 27.1,8" fill="url(#fl2)"/>
      </svg>
      <div class="form-logo-txt">CloudLibrary Mini<span>Perpustakaan Digital</span></div>
    </div>

    <h3>Masuk ke Akun</h3>
    <p class="sub">Gunakan email dan password yang sudah terdaftar.</p>

    <?php if($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['registered'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Registrasi berhasil! Silakan login.</div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label>Email</label>
        <div class="input-wrap">
          <i class="fas fa-envelope iico"></i>
          <input type="email" name="email" placeholder="email@example.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock iico"></i>
          <input type="password" name="password" id="pw" placeholder="Password kamu" required>
          <button type="button" class="toggle-pass" onclick="togglePass('pw','eye1')"><i class="fas fa-eye" id="eye1"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Masuk Sekarang</button>
    </form>

    <div class="divider">atau</div>
    <div class="reg-link">Belum punya akun? <a href="register.php">Daftar sekarang</a></div>
    <div class="back-link"><a href="<?= BASE_URL ?>/index.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a></div>
  </div>

</div>
<script>
function togglePass(inputId,iconId){
  const i=document.getElementById(inputId),ic=document.getElementById(iconId);
  if(i.type==='password'){i.type='text';ic.classList.replace('fa-eye','fa-eye-slash');}
  else{i.type='password';ic.classList.replace('fa-eye-slash','fa-eye');}
}
</script>
</body>
</html>