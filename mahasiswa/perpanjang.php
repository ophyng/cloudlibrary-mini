<?php
// ============================================
//  CloudLibrary Mini — Perpanjang Peminjaman
//  File   : mahasiswa/perpanjang.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();

$pinjam_id = (int)($_GET['id'] ?? 0);
$user_id   = $_SESSION['user_id'];

if (!$pinjam_id) { header('Location: ' . BASE_URL . '/mahasiswa/dashboard.php'); exit; }

$stmt = $pdo->prepare("
    SELECT p.*, b.judul, b.penulis, b.genre, b.kategori_id, b.cover
    FROM peminjaman p
    JOIN buku b ON p.buku_id = b.id
    WHERE p.id = ? AND p.user_id = ? AND p.status IN ('aktif','hampir_habis')
    LIMIT 1
");
$stmt->execute([$pinjam_id, $user_id]);
$pinjam = $stmt->fetch();

if (!$pinjam) { header('Location: ' . BASE_URL . '/mahasiswa/dashboard.php'); exit; }

$setting = $pdo->prepare("SELECT * FROM pengaturan_pinjam WHERE kategori_id = ?");
$setting->execute([$pinjam['kategori_id']]);
$setting = $setting->fetch();

$boleh_perpanjang = $setting['boleh_perpanjang'] ?? 1;
$durasi_tambah    = $setting['durasi_hari']      ?? 7;

$pesan = $pesan_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $boleh_perpanjang && !$pinjam['diperpanjang']) {
    $expired_baru = date('Y-m-d', strtotime($pinjam['tanggal_expired'] . " +$durasi_tambah days"));
    $pdo->prepare("UPDATE peminjaman SET tanggal_expired = ?, diperpanjang = 1, status = 'aktif' WHERE id = ?")
        ->execute([$expired_baru, $pinjam_id]);
    kirimNotifikasi($pdo, $user_id, "Peminjaman \"$pinjam[judul]\" diperpanjang. Jatuh tempo baru: " . formatTanggal($expired_baru), 'info');
    tambahPoin($pdo, $user_id, 2);
    $pesan = "Berhasil diperpanjang! Jatuh tempo baru: " . formatTanggal($expired_baru);
    $pesan_type = 'success';
    $stmt->execute([$pinjam_id, $user_id]);
    $pinjam = $stmt->fetch();
}

$sisa = sisaHari($pinjam['tanggal_expired']);

$genre_warna = [
    'Novel'    => ['bg'=>'#1a237e','icon'=>'fa-book'],
    'Cerpen'   => ['bg'=>'#4a148c','icon'=>'fa-scroll'],
    'Fantasi'  => ['bg'=>'#1b5e20','icon'=>'fa-hat-wizard'],
    'Romance'  => ['bg'=>'#880e4f','icon'=>'fa-heart'],
    'Horror'   => ['bg'=>'#b71c1c','icon'=>'fa-ghost'],
    'Misteri'  => ['bg'=>'#e65100','icon'=>'fa-user-secret'],
    'Sci-Fi'   => ['bg'=>'#006064','icon'=>'fa-rocket'],
    'Filsafat' => ['bg'=>'#37474f','icon'=>'fa-landmark'],
    'Sains'    => ['bg'=>'#1565c0','icon'=>'fa-flask'],
    'Biografi' => ['bg'=>'#4e342e','icon'=>'fa-feather-alt'],
];
$gw = $genre_warna[$pinjam['genre']] ?? ['bg'=>'#1e2330','icon'=>'fa-book'];

$title = "Perpanjang Peminjaman — CloudLibrary Mini";
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
  background: rgba(220, 235, 255, 0.10);
  pointer-events: none;
}

:root {
  --text:   #0a1628;
  --sub:    #2d4a6e;
  --muted:  #5a7090;
  --gold:   #d97706;
  --green:  #16a34a;
  --warn:   #d97706;
  --danger: #dc2626;
  --blue:   #2563eb;
  --purple: #9333ea;
  --glass:        rgba(255,255,255,0.62);
  --glass-strong: rgba(255,255,255,0.82);
  --border:       rgba(30,58,95,0.10);
  --border-s:     rgba(30,58,95,0.18);
  --sh:    0 4px 24px rgba(30,58,95,0.08);
  --sh-lg: 0 8px 36px rgba(30,58,95,0.16);
}

/* ── LAYOUT ── */
.pp-outer {
  position: relative; z-index: 1;
  max-width: 600px; margin: 0 auto;
  padding: 28px 20px 80px;
}

/* ── BACK LINK ── */
.back-link {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 12px; font-weight: 700; color: var(--sub);
  text-decoration: none; margin-bottom: 22px;
  background: rgba(255,255,255,0.62); border: 1px solid rgba(30,58,95,0.12);
  padding: 7px 14px; border-radius: 100px;
  backdrop-filter: blur(16px); transition: all 0.2s;
}
.back-link:hover { background: rgba(255,255,255,0.90); color: var(--text); }

/* ── PAGE HEADER ── */
.pp-header {
  background: rgba(255,255,255,0.62);
  border: 1px solid rgba(30,58,95,0.10);
  border-radius: 22px; padding: 24px 28px;
  margin-bottom: 16px;
  backdrop-filter: blur(28px); box-shadow: var(--sh-lg);
  position: relative; overflow: hidden;
  display: flex; align-items: center; gap: 18px;
}
.pp-header::after  { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(37,99,235,0.08), transparent 60%); pointer-events: none; }
.pp-header-icon { width: 52px; height: 52px; border-radius: 16px; background: linear-gradient(135deg, #1e3a7a, #2563eb); display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; box-shadow: 0 4px 16px rgba(37,99,235,0.25); flex-shrink: 0; position: relative; z-index: 1; }
.pp-header-text { position: relative; z-index: 1; }
.pp-header-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); }
.pp-header-sub   { font-size: 12px; color: var(--muted); margin-top: 3px; font-weight: 500; }

/* ── ALERT ── */
.pp-alert {
  border-radius: 14px; padding: 14px 18px; margin-bottom: 16px;
  font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 10px;
  backdrop-filter: blur(18px); border: 1px solid;
}
.pp-alert.success { background: rgba(22,163,74,0.10); border-color: rgba(22,163,74,0.25); color: #14532d; }
.pp-alert.warning { background: rgba(217,119,6,0.10); border-color: rgba(217,119,6,0.25); color: #78350f; }
.pp-alert.danger  { background: rgba(220,38,38,0.10); border-color: rgba(220,38,38,0.25); color: #7f1d1d; }

/* ── GLASS CARD ── */
.g-box {
  background: rgba(255,255,255,0.62);
  border: 1px solid rgba(30,58,95,0.10);
  border-radius: 20px; overflow: hidden;
  backdrop-filter: blur(22px); box-shadow: var(--sh);
  margin-bottom: 14px; transition: border-color 0.2s;
}
.g-box:hover { border-color: rgba(30,58,95,0.18); }
.g-box-head {
  padding: 18px 22px; border-bottom: 1px solid rgba(30,58,95,0.07);
  display: flex; align-items: center; gap: 14px;
}
.g-box-body { padding: 18px 22px; }

/* ── BUKU COVER MINI ── */
.buku-cover-mini {
  width: 50px; height: 70px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: #fff;
  box-shadow: 2px 4px 14px rgba(0,0,0,0.40); overflow: hidden;
}

/* ── INFO ROWS ── */
.info-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 0; border-bottom: 1px solid rgba(30,58,95,0.07);
  font-size: 13px;
}
.info-row:last-child { border-bottom: none; }
.info-row .k { color: var(--muted); display: flex; align-items: center; gap: 7px; font-weight: 500; }
.info-row .k i { width: 14px; text-align: center; font-size: 11px; color: var(--blue); }
.info-row .v { font-weight: 700; color: var(--sub); }
.info-row .v.ok   { color: var(--green); }
.info-row .v.warn { color: var(--warn); }
.info-row .v.bad  { color: var(--danger); }

/* ── SISA HARI BIG ── */
.sisa-wrap {
  background: rgba(255,255,255,0.62);
  border: 1px solid rgba(30,58,95,0.10); border-radius: 20px; padding: 28px 22px;
  text-align: center; margin-bottom: 14px;
  backdrop-filter: blur(24px); box-shadow: var(--sh);
}
.sisa-n   { font-family: 'Syne', sans-serif; font-size: 64px; font-weight: 900; line-height: 1; }
.sisa-lbl { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 6px; letter-spacing: .5px; text-transform: uppercase; }
.sisa-note{ font-size: 11px; font-weight: 700; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 6px; }
/* ── TIMELINE ── */
.timeline { display: flex; flex-direction: column; gap: 0; margin: 4px 0 22px; position: relative; }
.timeline::before { content: ''; position: absolute; left: 17px; top: 22px; bottom: 22px; width: 1.5px; background: rgba(30,58,95,0.10); }
.tl-item { display: flex; align-items: flex-start; gap: 14px; padding: 10px 0; position: relative; z-index: 1; }
.tl-dot {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 12px;
  border: 1.5px solid rgba(30,58,95,0.12); background: rgba(255,255,255,0.60);
  color: var(--muted);
}
.tl-dot.done  { background: rgba(22,163,74,0.10);  border-color: rgba(22,163,74,0.35);   color: var(--green); }
.tl-dot.now   { background: rgba(37,99,235,0.10);  border-color: rgba(37,99,235,0.35);   color: var(--blue); }
.tl-dot.next  { background: rgba(217,119,6,0.10);  border-color: rgba(217,119,6,0.35);   color: var(--warn); }
.tl-lbl  { font-size: 10px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.tl-val  { font-size: 14px; font-weight: 800; color: var(--text); margin-top: 2px; }
.tl-val.accent { color: var(--warn); }

/* ── INFO BOX ── */
.info-box {
  background: rgba(37,99,235,0.06); border: 1px solid rgba(37,99,235,0.16);
  border-radius: 12px; padding: 13px 16px;
  font-size: 12px; color: var(--sub); margin-bottom: 20px;
  display: flex; align-items: flex-start; gap: 9px; line-height: 1.7;
}
.info-box i { color: var(--blue); flex-shrink: 0; margin-top: 1px; }

/* ── LOCKED / BLOCKED BOX ── */
.state-box {
  text-align: center; padding: 44px 28px;
}
.state-icon {
  width: 68px; height: 68px; border-radius: 20px;
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; margin: 0 auto 20px;
}
.state-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 900; color: var(--text); margin-bottom: 10px; }
.state-desc  { font-size: 13px; color: var(--muted); line-height: 1.7; max-width: 320px; margin: 0 auto 24px; }

/* ── BUTTONS ── */
.btn-confirm {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 14px;
  background: linear-gradient(135deg, #1e3a7a, #2563eb);
  color: #fff; font-size: 14px; font-weight: 900;
  border: none; border-radius: 14px; cursor: pointer;
  font-family: 'Nunito', sans-serif; letter-spacing: .3px;
  box-shadow: 0 4px 18px rgba(37,99,235,0.35);
  transition: all 0.22s;
}
.btn-confirm:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,0.50); }

.btn-row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 22px; }
.btn-act {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 11px 22px; border-radius: 100px;
  font-size: 13px; font-weight: 800; text-decoration: none;
  transition: all 0.22s; font-family: 'Nunito', sans-serif;
}
.btn-act.blue   { background: linear-gradient(135deg, #1e3a7a, #2563eb); color: #fff; box-shadow: 0 3px 14px rgba(37,99,235,0.30); }
.btn-act.blue:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(37,99,235,0.45); }
.btn-act.ghost  { background: rgba(255,255,255,0.62); border: 1px solid rgba(30,58,95,0.12); color: var(--sub); }
.btn-act.ghost:hover { background: rgba(255,255,255,0.90); color: var(--text); }

/* ── FOOTER ── */
footer.pp-foot {
  position: relative; z-index: 1; text-align: center; padding: 22px;
  font-size: 11px; color: var(--muted); font-weight: 600;
  background: rgba(255,255,255,0.45); backdrop-filter: blur(20px);
  border-top: 1px solid rgba(30,58,95,0.07);
}

/* ── ANIM ── */
@keyframes fu { from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }
.fu { animation: fu 0.42s ease both; }
.fu:nth-child(1){ animation-delay:.04s; } .fu:nth-child(2){ animation-delay:.09s; }
.fu:nth-child(3){ animation-delay:.14s; } .fu:nth-child(4){ animation-delay:.19s; }
.fu:nth-child(5){ animation-delay:.24s; }
</style>

<div class="pp-outer">

  <!-- BACK -->
  <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="back-link fu">
    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
  </a>

  <!-- PAGE HEADER -->
  <div class="pp-header fu">
    <div class="pp-header-icon"><i class="fas fa-redo"></i></div>
    <div class="pp-header-text">
      <div class="pp-header-title">Perpanjang Peminjaman</div>
      <div class="pp-header-sub">Tambah durasi akses buku kamu</div>
    </div>
  </div>

  <!-- ALERT -->
  <?php if($pesan): ?>
  <div class="pp-alert <?= $pesan_type ?> fu">
    <i class="fas fa-<?= $pesan_type==='success'?'check-circle':'exclamation-triangle' ?>"></i>
    <?= $pesan ?>
  </div>
  <?php if($pesan_type==='success'): ?>
  <div class="btn-row fu">
    <a href="<?= BASE_URL ?>/mahasiswa/baca.php?id=<?= $pinjam['buku_id'] ?>" class="btn-act blue"><i class="fas fa-book-open"></i> Lanjut Baca</a>
    <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="btn-act ghost"><i class="fas fa-home"></i> Dashboard</a>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- INFO BUKU -->
  <div class="g-box fu">
    <div class="g-box-head">
      <!-- cover foto atau icon -->
      <div class="buku-cover-mini" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
        <?php if(!empty($pinjam['cover'])): ?>
          <img src="<?= BASE_URL ?>/uploads/covers/<?= e($pinjam['cover']) ?>" style="width:100%;height:100%;object-fit:contain;" alt="">
        <?php else: ?>
          <i class="fas <?= $gw['icon'] ?>"></i>
        <?php endif; ?>
      </div>
      <div>
        <div style="margin-bottom:5px;">
          <span style="font-size:9px;font-weight:800;padding:2px 9px;border-radius:5px;background:rgba(96,165,250,0.12);color:var(--blue);text-transform:uppercase;letter-spacing:.5px;"><?= e($pinjam['genre']) ?></span>
        </div>
        <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:var(--text);line-height:1.3;"><?= e($pinjam['judul']) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:5px;"><i class="fas fa-pen-nib" style="font-size:10px;"></i><?= e($pinjam['penulis']) ?></div>
      </div>
    </div>
    <div class="g-box-body">
      <div class="info-row">
        <span class="k"><i class="fas fa-calendar-plus"></i> Tanggal Pinjam</span>
        <span class="v"><?= formatTanggal($pinjam['tanggal_pinjam']) ?></span>
      </div>
      <div class="info-row">
        <span class="k"><i class="fas fa-hourglass-half"></i> Jatuh Tempo</span>
        <span class="v <?= $sisa<=1?'bad':($sisa<=3?'warn':'ok') ?>"><?= formatTanggal($pinjam['tanggal_expired']) ?></span>
      </div>
      <div class="info-row">
        <span class="k"><i class="fas fa-clock"></i> Sisa Hari</span>
        <span class="v <?= $sisa<=1?'bad':($sisa<=3?'warn':'ok') ?>"><?= max($sisa,0) ?> hari</span>
      </div>
      <div class="info-row">
        <span class="k"><i class="fas fa-redo"></i> Status Perpanjang</span>
        <span class="v <?= $pinjam['diperpanjang']?'bad':'ok' ?>">
          <?php if($pinjam['diperpanjang']): ?>
            <i class="fas fa-times-circle" style="font-size:11px;"></i> Sudah diperpanjang
          <?php else: ?>
            <i class="fas fa-check-circle" style="font-size:11px;"></i> Belum diperpanjang
          <?php endif; ?>
        </span>
      </div>
    </div>
  </div>

  <!-- SISA HARI BIG -->
  <div class="sisa-wrap fu">
    <div class="sisa-n" style="color:<?= $sisa<=1?'var(--danger)':($sisa<=3?'var(--warn)':'var(--blue)') ?>;"><?= max($sisa,0) ?></div>
    <div class="sisa-lbl">Hari tersisa sebelum akses ditutup</div>
    <?php if($sisa<=1): ?>
    <div class="sisa-note" style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Akses hampir berakhir! Segera perpanjang.</div>
    <?php elseif($sisa<=3): ?>
    <div class="sisa-note" style="color:var(--warn);"><i class="fas fa-exclamation-circle"></i> Sisa waktu sedikit, pertimbangkan perpanjang.</div>
    <?php endif; ?>
  </div>

  <!-- FORM PERPANJANG / STATUS -->
  <?php if($boleh_perpanjang && !$pinjam['diperpanjang']): ?>

  <div class="g-box fu">
    <div class="g-box-head" style="border-bottom:none;padding-bottom:6px;">
      <div style="font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:var(--text);display:flex;align-items:center;gap:8px;">
        <i class="fas fa-calendar-alt" style="color:var(--blue);"></i> Rencana Perpanjangan
      </div>
    </div>
    <div class="g-box-body">
      <div class="timeline">
        <div class="tl-item">
          <div class="tl-dot done"><i class="fas fa-check"></i></div>
          <div>
            <div class="tl-lbl">Dipinjam</div>
            <div class="tl-val"><?= formatTanggal($pinjam['tanggal_pinjam']) ?></div>
          </div>
        </div>
        <div class="tl-item">
          <div class="tl-dot now"><i class="fas fa-clock"></i></div>
          <div>
            <div class="tl-lbl">Jatuh Tempo Sekarang</div>
            <div class="tl-val"><?= formatTanggal($pinjam['tanggal_expired']) ?></div>
          </div>
        </div>
        <div class="tl-item">
          <div class="tl-dot next"><i class="fas fa-plus"></i></div>
          <div>
            <div class="tl-lbl">Jatuh Tempo Baru (+<?= $durasi_tambah ?> hari)</div>
            <div class="tl-val accent"><?= formatTanggal(date('Y-m-d', strtotime($pinjam['tanggal_expired'] . " +$durasi_tambah days"))) ?></div>
          </div>
        </div>
      </div>

      <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <span>Perpanjangan hanya bisa dilakukan <strong style="color:var(--text);">1 kali</strong> selama masa peminjaman. Akses buku akan diperpanjang otomatis selama <strong style="color:var(--blue);"><?= $durasi_tambah ?> hari</strong> tambahan.</span>
      </div>

      <form method="POST">
        <button type="submit" class="btn-confirm">
          <i class="fas fa-redo"></i> Konfirmasi Perpanjang Peminjaman
        </button>
      </form>
    </div>
  </div>

  <?php elseif($pinjam['diperpanjang']): ?>

  <div class="g-box fu">
    <div class="state-box">
      <div class="state-icon" style="background:rgba(248,113,113,0.12);border:1.5px solid rgba(248,113,113,0.25);">
        <i class="fas fa-lock" style="color:var(--danger);"></i>
      </div>
      <div class="state-title">Sudah Diperpanjang</div>
      <div class="state-desc">Peminjaman ini sudah pernah diperpanjang. Setiap buku hanya bisa diperpanjang <strong style="color:var(--text);">1 kali</strong>.</div>
      <div class="btn-row">
        <a href="<?= BASE_URL ?>/mahasiswa/baca.php?id=<?= $pinjam['buku_id'] ?>" class="btn-act blue"><i class="fas fa-book-open"></i> Lanjut Baca</a>
        <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn-act ghost"><i class="fas fa-book"></i> Katalog</a>
      </div>
    </div>
  </div>

  <?php else: ?>

  <div class="g-box fu">
    <div class="state-box">
      <div class="state-icon" style="background:rgba(248,113,113,0.10);border:1.5px solid rgba(248,113,113,0.22);">
        <i class="fas fa-ban" style="color:var(--danger);"></i>
      </div>
      <div class="state-title">Perpanjangan Tidak Tersedia</div>
      <div class="state-desc">Admin menonaktifkan fitur perpanjangan untuk kategori buku ini.</div>
      <div class="btn-row">
        <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="btn-act ghost"><i class="fas fa-home"></i> Kembali ke Dashboard</a>
      </div>
    </div>
  </div>

  <?php endif; ?>

</div><!-- /pp-outer -->

<footer class="pp-foot">
  <i class="fas fa-cloud" style="color:var(--blue);margin-right:5px;"></i>
  <strong style="color:var(--blue);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(30,58,95,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>
</body>
</html>