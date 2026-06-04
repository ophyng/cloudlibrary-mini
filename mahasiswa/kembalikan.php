<?php
// ============================================
//  CloudLibrary Mini — Kembalikan Buku
//  File   : mahasiswa/kembalikan.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();

$pinjam_id = (int)($_GET['id'] ?? 0);
$user_id   = $_SESSION['user_id'];

if (!$pinjam_id) { header('Location: ' . BASE_URL . '/mahasiswa/dashboard.php'); exit; }

$stmt = $pdo->prepare("
    SELECT p.*, b.judul, b.penulis, b.genre, b.cover, b.id AS buku_id_ref
    FROM peminjaman p
    JOIN buku b ON p.buku_id = b.id
    WHERE p.id = ? AND p.user_id = ? AND p.status IN ('aktif','hampir_habis')
    LIMIT 1
");
$stmt->execute([$pinjam_id, $user_id]);
$pinjam = $stmt->fetch();

if (!$pinjam) { header('Location: ' . BASE_URL . '/mahasiswa/dashboard.php'); exit; }

$pesan = $pesan_type = '';
$sudah_dikembalikan = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'kembalikan') {
    // Update status peminjaman → dikembalikan
    $pdo->prepare("
        UPDATE peminjaman
        SET status = 'dikembalikan', tanggal_dikembalikan = NOW()
        WHERE id = ? AND user_id = ?
    ")->execute([$pinjam_id, $user_id]);

    // Tambah stok buku kembali
    $pdo->prepare("UPDATE buku SET stok = stok + 1 WHERE id = ?")
        ->execute([$pinjam['buku_id']]);

    // Notifikasi + poin
    kirimNotifikasi($pdo, $user_id,
        "Buku \"$pinjam[judul]\" berhasil dikembalikan. Terima kasih!",
        'info'
    );
    tambahPoin($pdo, $user_id, 3);
    cekBadge($pdo, $user_id);

    $pesan = "Buku berhasil dikembalikan! +3 poin ditambahkan.";
    $pesan_type = 'success';
    $sudah_dikembalikan = true;
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

$title = "Kembalikan Buku — CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
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
  --glass:        rgba(255,255,255,0.62);
  --glass-b:      rgba(255,255,255,0.82);
  --border:       rgba(30,58,95,0.10);
  --border-s:     rgba(30,58,95,0.18);
  --sh:    0 4px 24px rgba(30,58,95,0.08);
  --sh-lg: 0 8px 36px rgba(30,58,95,0.16);
}

.km-outer {
  position: relative; z-index: 1;
  max-width: 580px; margin: 0 auto;
  padding: 28px 20px 80px;
}

.back-link {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 12px; font-weight: 700; color: var(--sub);
  text-decoration: none; margin-bottom: 22px;
  background: var(--glass); border: 1px solid var(--border);
  padding: 7px 14px; border-radius: 100px;
  backdrop-filter: blur(16px); transition: all 0.2s;
}
.back-link:hover { background: var(--glass-b); color: var(--text); }

/* PAGE HEADER */
.km-header {
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 22px; padding: 24px 28px;
  margin-bottom: 16px;
  backdrop-filter: blur(28px); box-shadow: var(--sh-lg);
  position: relative; overflow: hidden;
  display: flex; align-items: center; gap: 18px;
}
.km-header::after { content: ''; position: absolute; top: -50px; right: -50px; width: 180px; height: 180px; background: radial-gradient(circle, rgba(37,99,235,0.07), transparent 60%); pointer-events: none; }
.km-header-icon { width: 52px; height: 52px; border-radius: 16px; background: linear-gradient(135deg, #dc2626, #ef4444); display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; box-shadow: 0 4px 16px rgba(220,38,38,0.25); flex-shrink: 0; position: relative; z-index: 1; }
.km-header-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); }
.km-header-sub   { font-size: 12px; color: var(--muted); margin-top: 3px; font-weight: 500; }

/* ALERT */
.km-alert {
  border-radius: 14px; padding: 14px 18px; margin-bottom: 16px;
  font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 10px;
  backdrop-filter: blur(18px); border: 1px solid;
}
.km-alert.success { background: rgba(22,163,74,0.10); border-color: rgba(22,163,74,0.25); color: #14532d; }

/* GLASS BOX */
.g-box {
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 20px; overflow: hidden;
  backdrop-filter: blur(22px); box-shadow: var(--sh);
  margin-bottom: 14px;
}
.g-box-head {
  padding: 18px 22px; border-bottom: 1px solid rgba(30,58,95,0.07);
  display: flex; align-items: center; gap: 14px;
}
.g-box-body { padding: 18px 22px; }

/* BUKU COVER MINI */
.buku-cover-mini {
  width: 50px; height: 70px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; color: #fff;
  box-shadow: 2px 4px 14px rgba(0,0,0,0.20); overflow: hidden;
}

/* INFO ROWS */
.info-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 11px 0; border-bottom: 1px solid rgba(30,58,95,0.07);
  font-size: 13px;
}
.info-row:last-child { border-bottom: none; }
.info-row .k { color: var(--muted); display: flex; align-items: center; gap: 7px; font-weight: 500; }
.info-row .k i { width: 14px; text-align: center; font-size: 11px; color: var(--blue); }
.info-row .v { font-weight: 700; color: var(--sub); }
.info-row .v.ok   { color: var(--green); }
.info-row .v.warn { color: var(--warn); }
.info-row .v.bad  { color: var(--danger); }

/* WARNING BOX */
.warn-box {
  background: rgba(220,38,38,0.06);
  border: 1px solid rgba(220,38,38,0.18);
  border-radius: 14px; padding: 16px 18px;
  margin-bottom: 16px;
}
.warn-box-title {
  font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 900;
  color: var(--danger); display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
}
.warn-list { list-style: none; padding: 0; margin: 0; }
.warn-list li {
  font-size: 12px; color: var(--sub); font-weight: 600; line-height: 1.7;
  display: flex; align-items: flex-start; gap: 8px; margin-bottom: 4px;
}
.warn-list li:last-child { margin-bottom: 0; }
.warn-list li i { color: var(--danger); font-size: 10px; margin-top: 4px; flex-shrink: 0; }

/* CONFIRM FORM */
.confirm-section {
  background: var(--glass);
  border: 1.5px solid rgba(220,38,38,0.20);
  border-radius: 20px; padding: 22px;
  backdrop-filter: blur(22px); box-shadow: var(--sh);
}
.confirm-title {
  font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 900;
  color: var(--text); margin-bottom: 14px;
  display: flex; align-items: center; gap: 8px;
}
.confirm-title i { color: var(--danger); }
.confirm-check {
  display: flex; align-items: flex-start; gap: 10px;
  background: rgba(220,38,38,0.05); border: 1px solid rgba(220,38,38,0.15);
  border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; cursor: pointer;
}
.confirm-check input[type=checkbox] { margin-top: 2px; width: 16px; height: 16px; accent-color: var(--danger); cursor: pointer; flex-shrink: 0; }
.confirm-check-text { font-size: 12px; color: var(--sub); font-weight: 600; line-height: 1.7; }

.btn-confirm-km {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 14px;
  background: linear-gradient(135deg, #dc2626, #ef4444);
  color: #fff; font-size: 14px; font-weight: 900;
  border: none; border-radius: 14px; cursor: pointer;
  font-family: 'Nunito', sans-serif; letter-spacing: .3px;
  box-shadow: 0 4px 18px rgba(220,38,38,0.25);
  transition: all 0.22s; opacity: 0.5; pointer-events: none;
}
.btn-confirm-km.aktif { opacity: 1; pointer-events: all; }
.btn-confirm-km.aktif:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(220,38,38,0.38); }

/* SUCCESS STATE */
.success-box {
  text-align: center; padding: 44px 28px;
}
.success-icon {
  width: 72px; height: 72px; border-radius: 20px;
  background: rgba(22,163,74,0.10); border: 1.5px solid rgba(22,163,74,0.25);
  display: flex; align-items: center; justify-content: center;
  font-size: 30px; color: var(--green); margin: 0 auto 20px;
}
.success-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); margin-bottom: 8px; }
.success-desc  { font-size: 13px; color: var(--muted); line-height: 1.7; max-width: 340px; margin: 0 auto 24px; }

/* BUTTONS */
.btn-row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.btn-act {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 11px 22px; border-radius: 100px;
  font-size: 13px; font-weight: 800; text-decoration: none;
  transition: all 0.22s; font-family: 'Nunito', sans-serif;
}
.btn-act.blue  { background: linear-gradient(135deg, #1e3a7a, #2563eb); color: #fff; box-shadow: 0 3px 14px rgba(37,99,235,0.25); }
.btn-act.blue:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(37,99,235,0.38); }
.btn-act.ghost { background: var(--glass); border: 1px solid var(--border); color: var(--sub); }
.btn-act.ghost:hover { background: var(--glass-b); color: var(--text); }

footer.km-foot {
  position: relative; z-index: 1; text-align: center; padding: 22px;
  font-size: 11px; color: var(--muted); font-weight: 600;
  background: rgba(255,255,255,0.45); backdrop-filter: blur(20px);
  border-top: 1px solid rgba(30,58,95,0.07);
}

@keyframes fu { from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }
.fu { animation: fu 0.42s ease both; }
.fu:nth-child(1){ animation-delay:.04s; } .fu:nth-child(2){ animation-delay:.09s; }
.fu:nth-child(3){ animation-delay:.14s; } .fu:nth-child(4){ animation-delay:.19s; }
.fu:nth-child(5){ animation-delay:.24s; }
</style>

<div class="km-outer">

  <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $pinjam['buku_id'] ?>" class="back-link fu">
    <i class="fas fa-arrow-left"></i> Kembali ke Detail Buku
  </a>

  <!-- HEADER -->
  <div class="km-header fu">
    <div class="km-header-icon"><i class="fas fa-undo"></i></div>
    <div>
      <div class="km-header-title">Kembalikan Buku</div>
      <div class="km-header-sub">Pengembalian manual sebelum jatuh tempo</div>
    </div>
  </div>

  <?php if($sudah_dikembalikan): ?>
  <!-- SUCCESS STATE -->
  <div class="g-box fu">
    <div class="success-box">
      <div class="success-icon"><i class="fas fa-check"></i></div>
      <div class="success-title">Buku Berhasil Dikembalikan!</div>
      <div class="success-desc">
        Terima kasih sudah mengembalikan <strong><?= e($pinjam['judul']) ?></strong>.<br>
        Stok buku sudah ditambah kembali dan <strong>+3 poin</strong> ditambahkan ke akunmu.
      </div>
      <div class="btn-row">
        <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn-act blue">
          <i class="fas fa-book"></i> Cari Buku Lain
        </a>
        <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="btn-act ghost">
          <i class="fas fa-home"></i> Dashboard
        </a>
      </div>
    </div>
  </div>

  <?php else: ?>

  <!-- INFO BUKU -->
  <div class="g-box fu">
    <div class="g-box-head">
      <div class="buku-cover-mini" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
        <?php if(!empty($pinjam['cover'])): ?>
          <img src="<?= BASE_URL ?>/uploads/covers/<?= e($pinjam['cover']) ?>" style="width:100%;height:100%;object-fit:contain;" alt="">
        <?php else: ?>
          <i class="fas <?= $gw['icon'] ?>"></i>
        <?php endif; ?>
      </div>
      <div>
        <div style="margin-bottom:5px;">
          <span style="font-size:9px;font-weight:800;padding:2px 9px;border-radius:5px;background:rgba(37,99,235,0.10);color:var(--blue);text-transform:uppercase;letter-spacing:.5px;"><?= e($pinjam['genre']) ?></span>
        </div>
        <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:var(--text);line-height:1.3;"><?= e($pinjam['judul']) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:5px;">
          <i class="fas fa-pen-nib" style="font-size:10px;"></i><?= e($pinjam['penulis']) ?>
        </div>
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
        <span class="k"><i class="fas fa-redo"></i> Diperpanjang</span>
        <span class="v"><?= $pinjam['diperpanjang'] ? 'Ya' : 'Belum' ?></span>
      </div>
    </div>
  </div>

  <!-- WARNING -->
  <div class="warn-box fu">
    <div class="warn-box-title">
      <i class="fas fa-exclamation-triangle"></i> Perhatian Sebelum Mengembalikan
    </div>
    <ul class="warn-list">
      <li><i class="fas fa-circle"></i> Setelah dikembalikan, akses buku akan <strong>langsung ditutup</strong>.</li>
      <li><i class="fas fa-circle"></i> Kamu <strong>tidak bisa membaca</strong> buku ini lagi kecuali meminjam ulang.</li>
      <li><i class="fas fa-circle"></i> Stok buku akan otomatis bertambah sehingga bisa dipinjam pengguna lain.</li>
      <li><i class="fas fa-circle"></i> Kamu mendapat <strong>+3 poin</strong> sebagai apresiasi pengembalian tepat waktu.</li>
    </ul>
  </div>

  <!-- KONFIRMASI FORM -->
  <div class="confirm-section fu">
    <div class="confirm-title">
      <i class="fas fa-undo"></i> Konfirmasi Pengembalian
    </div>
    <form method="POST" id="formKembalikan">
      <input type="hidden" name="aksi" value="kembalikan">
      <label class="confirm-check" for="chkKonfirm">
        <input type="checkbox" id="chkKonfirm" onchange="toggleBtn(this)">
        <span class="confirm-check-text">
          Saya mengerti bahwa dengan mengembalikan buku ini, akses saya ke
          <strong><?= e($pinjam['judul']) ?></strong> akan segera ditutup dan saya tidak dapat
          melanjutkan membaca tanpa meminjam ulang.
        </span>
      </label>
      <button type="submit" class="btn-confirm-km" id="btnKembalikan">
        <i class="fas fa-undo"></i> Kembalikan Buku Sekarang
      </button>
    </form>
  </div>

  <?php endif; ?>

</div>

<footer class="km-foot">
  <i class="fas fa-cloud" style="color:var(--blue);margin-right:5px;"></i>
  <strong style="color:var(--blue);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(30,58,95,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
function toggleBtn(cb) {
  const btn = document.getElementById('btnKembalikan');
  if (cb.checked) {
    btn.classList.add('aktif');
  } else {
    btn.classList.remove('aktif');
  }
}
</script>
</body>
</html>