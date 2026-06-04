<?php
// ============================================
//  CloudLibrary Mini — Detail Buku
//  File   : mahasiswa/detail_buku.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();
updateStatusPeminjaman($pdo);

$buku_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
if (!$buku_id) { header('Location: '.BASE_URL.'/mahasiswa/katalog.php'); exit; }

$stmt = $pdo->prepare("SELECT b.*, k.nama AS nama_kategori FROM buku b LEFT JOIN kategori k ON b.kategori_id=k.id WHERE b.id=?");
$stmt->execute([$buku_id]); $buku = $stmt->fetch();
if (!$buku) { header('Location: '.BASE_URL.'/mahasiswa/katalog.php'); exit; }

$pinjam_aktif = $pdo->prepare("SELECT * FROM peminjaman WHERE user_id=? AND buku_id=? AND status IN('aktif','hampir_habis') LIMIT 1");
$pinjam_aktif->execute([$user_id,$buku_id]); $pinjam_aktif = $pinjam_aktif->fetch();

$antrian = $pdo->prepare("SELECT * FROM antrian WHERE user_id=? AND buku_id=? AND status='menunggu' LIMIT 1");
$antrian->execute([$user_id,$buku_id]); $antrian = $antrian->fetch();

$in_wishlist = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND buku_id=?");
$in_wishlist->execute([$user_id,$buku_id]); $in_wishlist = (bool)$in_wishlist->fetch();

$review_user = $pdo->prepare("SELECT * FROM review WHERE user_id=? AND buku_id=?");
$review_user->execute([$user_id,$buku_id]); $review_user = $review_user->fetch();

$reviews = $pdo->prepare("SELECT r.*, u.nama AS nama_user FROM review r JOIN users u ON r.user_id=u.id WHERE r.buku_id=? AND r.status='tampil' ORDER BY r.created_at DESC");
$reviews->execute([$buku_id]); $reviews = $reviews->fetchAll();

$avg_rating    = ratingRataRata($pdo, $buku_id);
$jumlah_review = count($reviews);

$serupa = $pdo->prepare("SELECT * FROM buku WHERE genre=? AND id!=? AND status='tersedia' ORDER BY total_dipinjam DESC LIMIT 4");
$serupa->execute([$buku['genre'],$buku_id]); $serupa = $serupa->fetchAll();

$pesan = $pesan_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'pinjam' && !$pinjam_aktif && $buku['stok'] > 0) {
        $max = $pdo->prepare("SELECT max_pinjam_bersamaan FROM pengaturan_pinjam WHERE kategori_id=?"); $max->execute([$buku['kategori_id']]); $max = $max->fetchColumn() ?: 3;
        $jml = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id=? AND status IN('aktif','hampir_habis')"); $jml->execute([$user_id]); $jml = $jml->fetchColumn();
        if ($jml >= $max) { $pesan = "Kamu sudah meminjam $max buku sekaligus. Kembalikan dulu sebelum meminjam lagi."; $pesan_type = 'warning'; }
        else {
            $dur = $pdo->prepare("SELECT durasi_hari FROM pengaturan_pinjam WHERE kategori_id=?"); $dur->execute([$buku['kategori_id']]); $durasi = $dur->fetchColumn() ?: 7;
            $tgl_pinjam = date('Y-m-d'); $tgl_expired = date('Y-m-d', strtotime("+$durasi days"));
            $pdo->prepare("INSERT INTO peminjaman (user_id,buku_id,tanggal_pinjam,tanggal_expired,status) VALUES(?,?,?,?,'aktif')")->execute([$user_id,$buku_id,$tgl_pinjam,$tgl_expired]);
            $pdo->prepare("UPDATE buku SET stok=stok-1, total_dipinjam=total_dipinjam+1 WHERE id=?")->execute([$buku_id]);
            tambahPoin($pdo,$user_id,5); kirimNotifikasi($pdo,$user_id,"Kamu berhasil meminjam \"$buku[judul]\". Batas waktu: ".formatTanggal($tgl_expired),'info'); cekBadge($pdo,$user_id);
            $pesan = "Berhasil meminjam buku! Batas waktu: ".formatTanggal($tgl_expired); $pesan_type = 'success';
            header("Refresh:1;url=".BASE_URL."/mahasiswa/detail_buku.php?id=$buku_id");
        }
    }
    elseif ($aksi === 'antre' && !$pinjam_aktif && !$antrian) {
        $pdo->prepare("INSERT INTO antrian (user_id,buku_id) VALUES(?,?)")->execute([$user_id,$buku_id]);
        kirimNotifikasi($pdo,$user_id,"Kamu masuk antrian buku \"$buku[judul]\".",'info');
        $pesan = "Berhasil masuk antrian!"; $pesan_type = 'success';
        header("Refresh:1;url=".BASE_URL."/mahasiswa/detail_buku.php?id=$buku_id");
    }
    elseif ($aksi === 'wishlist') {
        if ($in_wishlist) { $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND buku_id=?")->execute([$user_id,$buku_id]); $pesan="Dihapus dari Wishlist."; $pesan_type='info'; }
        else { $pdo->prepare("INSERT INTO wishlist (user_id,buku_id) VALUES(?,?)")->execute([$user_id,$buku_id]); $pesan="Ditambahkan ke Wishlist!"; $pesan_type='success'; }
        header("Refresh:1;url=".BASE_URL."/mahasiswa/detail_buku.php?id=$buku_id");
    }
    elseif ($aksi === 'review') {
        $rating = (int)($_POST['rating'] ?? 0); $komentar = trim($_POST['komentar'] ?? '');
        if ($rating >= 1 && $rating <= 5 && $komentar) {
            if ($review_user) { $pdo->prepare("UPDATE review SET rating=?,komentar=? WHERE id=?")->execute([$rating,$komentar,$review_user['id']]); }
            else { $pdo->prepare("INSERT INTO review (user_id,buku_id,rating,komentar) VALUES(?,?,?,?)")->execute([$user_id,$buku_id,$rating,$komentar]); tambahPoin($pdo,$user_id,10); }
            cekBadge($pdo,$user_id); $pesan="Review berhasil disimpan! +10 poin"; $pesan_type='success';
            header("Refresh:1;url=".BASE_URL."/mahasiswa/detail_buku.php?id=$buku_id");
        } else { $pesan="Rating dan komentar wajib diisi."; $pesan_type='danger'; }
    }
}

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
$gw = $genre_warna[$buku['genre']] ?? ['bg'=>'#1e2330','icon'=>'fa-book'];
$tersedia = $buku['stok'] > 0 && $buku['status'] === 'tersedia';

$title = e($buku['judul'])." — CloudLibrary Mini";
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
  --card:rgba(255,255,255,0.75);
  --card-b:rgba(255,255,255,0.92);
  --sh:0 4px 20px rgba(10,22,40,0.12);
  --sh-md:0 8px 32px rgba(10,22,40,0.18);
}

.detail-outer{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:24px 20px 60px;}
.back-link{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--d2);text-decoration:none;background:rgba(255,255,255,0.65);padding:7px 16px;border-radius:100px;border:1px solid rgba(30,58,95,0.15);backdrop-filter:blur(10px);transition:all .2s;margin-bottom:20px;}
.back-link:hover{background:rgba(255,255,255,0.90);}
.alert{padding:13px 18px;border-radius:14px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:700;backdrop-filter:blur(16px);}
.alert-success{background:rgba(134,239,172,0.30);border:1px solid rgba(34,197,94,0.35);color:#14532d;}
.alert-warning{background:rgba(253,224,71,0.25);border:1px solid rgba(234,179,8,0.35);color:#713f12;}
.alert-danger{background:rgba(248,113,113,0.22);border:1px solid rgba(220,38,38,0.30);color:#7f1d1d;}
.alert-info{background:rgba(147,197,253,0.25);border:1px solid rgba(59,130,246,0.30);color:#1e3a5f;}
.detail-wrap{display:grid;grid-template-columns:260px 1fr;gap:28px;margin-bottom:32px;}
@media(max-width:768px){.detail-wrap{grid-template-columns:1fr;}}
.cover-panel{display:flex;flex-direction:column;gap:10px;}
/* FIX: tambah overflow:hidden dan position:relative */
.book-cover-big{border-radius:18px;overflow:hidden;height:340px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;box-shadow:var(--sh-md);position:relative;}
.book-cover-big::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.10) 0%,transparent 60%,rgba(0,0,0,0.18) 100%);pointer-events:none;}
.cover-icon-big{font-size:64px;color:#fff;position:relative;z-index:1;}
.cover-genre-label{font-size:11px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.60);position:relative;z-index:1;}
.cover-stok{position:absolute;top:12px;right:12px;z-index:3;font-size:10px;font-weight:900;padding:4px 10px;border-radius:8px;}
.cover-stok.ada{background:rgba(22,163,74,0.85);color:#fff;}
.cover-stok.habis{background:rgba(220,38,38,0.85);color:#fff;}
.btn-wish{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:10px;border-radius:100px;font-size:13px;font-weight:800;cursor:pointer;transition:all .2s;font-family:'Nunito',sans-serif;border:none;}
.btn-wish.on{background:rgba(219,39,119,0.12);border:1.5px solid rgba(219,39,119,0.35);color:#be185d;}
.btn-wish.on:hover{background:rgba(219,39,119,0.22);}
.btn-wish.off{background:rgba(255,255,255,0.65);border:1.5px solid rgba(30,58,95,0.18);color:var(--d2);}
.btn-wish.off:hover{background:rgba(255,255,255,0.90);}
.btn-share{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:10px;border-radius:100px;font-size:12px;font-weight:800;cursor:pointer;background:rgba(255,255,255,0.55);border:1.5px solid rgba(30,58,95,0.15);color:var(--muted);font-family:'Nunito',sans-serif;transition:all .2s;}
.btn-share:hover{background:rgba(255,255,255,0.85);color:var(--d2);}
.info-tipe{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:900;letter-spacing:1px;padding:4px 10px;border-radius:6px;margin-bottom:10px;text-transform:uppercase;}
.tipe-fiksi{background:rgba(30,74,130,0.12);color:var(--d2);}
.tipe-nonfiksi{background:rgba(180,83,9,0.12);color:var(--gold);}
.info-title{font-family:'Syne',sans-serif;font-size:26px;font-weight:900;line-height:1.3;margin-bottom:6px;color:var(--d1);}
.info-penulis{font-size:14px;color:var(--muted);margin-bottom:16px;display:flex;align-items:center;gap:6px;font-weight:600;}
.info-rating{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.rating-big{font-family:'Syne',sans-serif;font-size:36px;font-weight:900;color:var(--gold);}
.stars-row{display:flex;align-items:center;gap:3px;}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:22px;}
.meta-item{background:var(--card);border:1.5px solid var(--card-b);border-radius:12px;padding:12px 14px;backdrop-filter:blur(14px);}
.meta-item .lbl{font-size:10px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:4px;}
.meta-item .val{font-size:14px;font-weight:700;color:var(--d1);}
.buku-desc{font-size:14px;color:var(--text);line-height:1.75;margin-bottom:22px;background:var(--card);border:1.5px solid var(--card-b);border-radius:14px;padding:16px 18px;backdrop-filter:blur(14px);}
.pinjam-bar{background:var(--card);border:1.5px solid var(--card-b);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap;backdrop-filter:blur(14px);box-shadow:var(--sh);}
.sisa-hari{font-family:'Syne',sans-serif;font-size:32px;font-weight:900;}
.btn-pinjam-main{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:100px;font-size:14px;font-weight:900;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .2s;border:none;}
.btn-pinjam-main.biru{background:linear-gradient(135deg,var(--d1),var(--d2));color:#fff;box-shadow:0 4px 16px rgba(30,74,130,0.35);}
.btn-pinjam-main.biru:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(30,74,130,0.45);}
.btn-pinjam-main.hijau{background:linear-gradient(135deg,#14532d,#16a34a);color:#fff;box-shadow:0 4px 16px rgba(22,163,74,0.30);}
.btn-pinjam-main.hijau:hover{transform:translateY(-2px);}
.btn-pinjam-main.oren{background:linear-gradient(135deg,#78350f,#d97706);color:#fff;box-shadow:0 4px 16px rgba(217,119,6,0.30);}
.btn-pinjam-main.oren:hover{transform:translateY(-2px);}
.btn-pinjam-main.perpanjang{background:rgba(255,255,255,0.65);border:1.5px solid rgba(30,58,95,0.22)!important;color:var(--d2);box-shadow:none;}
.btn-pinjam-main.perpanjang:hover{background:rgba(255,255,255,0.90);}
.section-block{background:var(--card);border:1.5px solid var(--card-b);border-radius:18px;padding:22px;margin-bottom:20px;backdrop-filter:blur(20px);box-shadow:var(--sh);position:relative;z-index:1;}
.section-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:var(--d1);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.section-title i{color:var(--d2);}
.section-title span{font-size:12px;font-weight:600;color:var(--muted);}
.star-input{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:4px;margin-bottom:12px;}
.star-input input{display:none;}
.star-input label{font-size:28px;cursor:pointer;color:rgba(30,58,95,0.18);transition:color .15s;}
.star-input label:hover,.star-input label:hover~label,.star-input input:checked~label{color:#d97706!important;}
.review-card{background:rgba(255,255,255,0.55);border:1px solid rgba(30,58,95,0.10);border-radius:14px;padding:16px;margin-bottom:10px;backdrop-filter:blur(10px);}
.review-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--d2),var(--d3));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:#fff;flex-shrink:0;}
.review-nama{font-size:13px;font-weight:800;color:var(--d1);}
.review-komentar{font-size:13px;color:var(--text);line-height:1.65;font-weight:500;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:10px;font-weight:900;color:var(--muted);margin-bottom:6px;letter-spacing:.5px;text-transform:uppercase;}
.form-group textarea{width:100%;border-radius:10px;padding:10px 14px;font-size:13px;font-family:'Nunito',sans-serif;outline:none;background:rgba(255,255,255,0.75);border:1.5px solid rgba(30,58,95,0.16);color:var(--d1);resize:vertical;min-height:80px;font-weight:600;}
.form-group textarea:focus{border-color:var(--d2);}
.btn-submit-sm{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:100px;background:linear-gradient(135deg,var(--d1),var(--d2));color:#fff;font-size:12px;font-weight:900;border:none;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .2s;}
.btn-submit-sm:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(30,74,130,0.30);}
.serupa-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;}
.serupa-card{background:var(--card);border:1.5px solid var(--card-b);border-radius:14px;overflow:hidden;backdrop-filter:blur(12px);transition:transform .2s,box-shadow .2s;text-decoration:none;}
.serupa-card:hover{transform:translateY(-4px);box-shadow:var(--sh-md);}
/* FIX: serupa-cover tambah position:relative overflow:hidden */
.serupa-cover{height:110px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;position:relative;overflow:hidden;}
.serupa-body{padding:10px 12px;}
.serupa-title{font-family:'Syne',sans-serif;font-size:12px;font-weight:900;color:var(--d1);margin-bottom:3px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.serupa-author{font-size:10px;color:var(--muted);font-weight:600;}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}.fu4{animation:fadeUp .4s ease .28s both}
</style>

<div class="detail-outer">

  <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="back-link fu1">
    <i class="fas fa-arrow-left"></i> Kembali ke Katalog
  </a>

  <?php if($pesan): ?>
  <div class="alert alert-<?= $pesan_type ?> fu1">
    <i class="fas fa-<?= $pesan_type==='success'?'check-circle':($pesan_type==='warning'?'exclamation-triangle':'info-circle') ?>"></i>
    <?= $pesan ?>
  </div>
  <?php endif; ?>

  <div class="detail-wrap fu2">

    <!-- COVER PANEL -->
    <div class="cover-panel">
      <div class="book-cover-big" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>bb);">
        <span class="cover-stok <?= $tersedia?'ada':'habis' ?>">
          <i class="fas fa-<?= $tersedia?'check':'times' ?>"></i> <?= $tersedia?$buku['stok'].' stok':'Habis' ?>
        </span>
        <!-- FIX 1: tampil foto cover kalau ada, fallback ikon -->
        <?php if(!empty($buku['cover'])): ?>
          <img src="<?= BASE_URL ?>/uploads/covers/<?= e($buku['cover']) ?>"
               style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;" alt="">
        <?php else: ?>
          <i class="fas <?= $gw['icon'] ?> cover-icon-big"></i>
          <span class="cover-genre-label"><?= e($buku['genre']) ?></span>
        <?php endif; ?>
      </div>

      <form method="POST">
        <input type="hidden" name="aksi" value="wishlist">
        <button type="submit" class="btn-wish <?= $in_wishlist?'on':'off' ?>">
          <i class="fas fa-heart"></i>
          <?= $in_wishlist?'Hapus dari Wishlist':'Tambah ke Wishlist' ?>
        </button>
      </form>

      <button onclick="navigator.clipboard.writeText(window.location.href).then(()=>alert('Link disalin!'))" class="btn-share">
        <i class="fas fa-share-alt"></i> Salin Link Buku
      </button>
    </div>

    <!-- INFO PANEL -->
    <div>
      <span class="info-tipe <?= $buku['tipe']==='fiksi'?'tipe-fiksi':'tipe-nonfiksi' ?>">
        <i class="fas fa-<?= $buku['tipe']==='fiksi'?'book':'book-open' ?>"></i> <?= strtoupper($buku['tipe']) ?>
      </span>

      <h1 class="info-title"><?= e($buku['judul']) ?></h1>
      <p class="info-penulis">
        <i class="fas fa-pen-nib" style="font-size:11px;color:var(--d3);"></i> <?= e($buku['penulis']) ?>
      </p>

      <div class="info-rating">
        <span class="rating-big"><?= number_format($avg_rating,1) ?></span>
        <div>
          <div class="stars-row">
            <?php for($s=1;$s<=5;$s++): ?>
              <i class="fas fa-star" style="font-size:16px;color:<?= $s<=$avg_rating?'#d97706':'rgba(30,58,95,0.15)' ?>;"></i>
            <?php endfor; ?>
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:3px;font-weight:600;"><?= $jumlah_review ?> ulasan</div>
        </div>
      </div>

      <div class="meta-grid">
        <div class="meta-item">
          <div class="lbl"><i class="fas fa-tag"></i> Genre</div>
          <div class="val"><?= e($buku['genre']) ?></div>
        </div>
        <div class="meta-item">
          <div class="lbl"><i class="fas fa-calendar-alt"></i> Tahun</div>
          <div class="val"><?= $buku['tahun'] ?: '-' ?></div>
        </div>
        <div class="meta-item">
          <div class="lbl"><i class="fas fa-globe"></i> Bahasa</div>
          <div class="val"><?= e($buku['bahasa'] ?: 'Indonesia') ?></div>
        </div>
        <div class="meta-item">
          <div class="lbl"><i class="fas fa-hand-holding"></i> Dipinjam</div>
          <div class="val"><?= $buku['total_dipinjam'] ?>x</div>
        </div>
      </div>

      <?php if($buku['deskripsi']): ?>
      <div class="buku-desc"><?= nl2br(e($buku['deskripsi'])) ?></div>
      <?php endif; ?>

      <?php if($pinjam_aktif):
        $sisa = sisaHari($pinjam_aktif['tanggal_expired']);
        $warn = $pinjam_aktif['status'] === 'hampir_habis';
      ?>
      <div class="pinjam-bar" style="border-color:<?= $warn?'rgba(217,119,6,0.35)':'rgba(22,163,74,0.30)' ?>;">
        <div style="text-align:center;min-width:60px;">
          <div style="font-size:10px;color:var(--muted);font-weight:700;margin-bottom:2px;">SISA</div>
          <div class="sisa-hari" style="color:<?= $sisa<=1?'#dc2626':($warn?'#d97706':'#16a34a') ?>;"><?= max($sisa,0) ?></div>
          <div style="font-size:10px;color:var(--muted);font-weight:700;">hari</div>
        </div>
        <div style="flex:1;">
          <div style="font-size:12px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:5px;margin-bottom:3px;">
            <i class="fas fa-calendar-plus" style="font-size:10px;"></i> Dipinjam <?= formatTanggal($pinjam_aktif['tanggal_pinjam']) ?>
          </div>
          <div style="font-size:12px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:5px;">
            <i class="fas fa-calendar-times" style="font-size:10px;"></i> Jatuh tempo <?= formatTanggal($pinjam_aktif['tanggal_expired']) ?>
          </div>
          <?php if($warn): ?>
          <div style="font-size:12px;color:#d97706;font-weight:800;margin-top:4px;display:flex;align-items:center;gap:5px;">
            <i class="fas fa-exclamation-triangle" style="font-size:10px;"></i> Hampir habis!
          </div>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="<?= BASE_URL ?>/mahasiswa/baca.php?id=<?= $buku_id ?>" class="btn-pinjam-main hijau">
            <i class="fas fa-book-open"></i> Baca Sekarang
          </a>
          <a href="<?= BASE_URL ?>/mahasiswa/perpanjang.php?id=<?= $pinjam_aktif['id'] ?>" class="btn-pinjam-main perpanjang">
            <i class="fas fa-redo"></i> Perpanjang
          </a>
          <a href="<?= BASE_URL ?>/mahasiswa/kembalikan.php?id=<?= $pinjam_aktif['id'] ?>"
             class="btn-pinjam-main"
             style="background:rgba(220,38,38,0.10);border:1.5px solid rgba(220,38,38,0.25)!important;color:#dc2626;"
             onclick="return confirm('Kembalikan buku ini sekarang? Akses akan langsung ditutup.')">
            <i class="fas fa-undo"></i> Kembalikan
          </a>
        </div>
      </div>

      <?php elseif($antrian): ?>
      <div class="pinjam-bar" style="border-color:rgba(217,119,6,0.28);">
        <i class="fas fa-list-ol" style="font-size:22px;color:#d97706;"></i>
        <div>
          <div style="font-weight:800;color:var(--d1);">Kamu sedang dalam antrian</div>
          <div style="font-size:12px;color:var(--muted);margin-top:3px;font-weight:600;">
            <i class="fas fa-calendar-alt" style="font-size:10px;"></i> Daftar sejak <?= formatTanggal($antrian['tanggal_daftar']) ?>
          </div>
        </div>
      </div>

      <?php else: ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if($tersedia): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="aksi" value="pinjam">
            <button type="submit" class="btn-pinjam-main biru">
              <i class="fas fa-hand-holding"></i> Pinjam Sekarang
            </button>
          </form>
        <?php else: ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="aksi" value="antre">
            <button type="submit" class="btn-pinjam-main oren">
              <i class="fas fa-list-ol"></i> Masuk Antrian
            </button>
          </form>
          <span style="display:inline-flex;align-items:center;gap:6px;padding:12px 20px;border-radius:100px;background:rgba(220,38,38,0.10);border:1.5px solid rgba(220,38,38,0.25);color:#dc2626;font-size:13px;font-weight:800;">
            <i class="fas fa-times-circle"></i> Stok Habis
          </span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- REVIEW SECTION -->
  <div class="section-block fu3">
    <div class="section-title">
      <i class="fas fa-star"></i> Ulasan Pembaca
      <span>(<?= $jumlah_review ?> ulasan)</span>
    </div>

    <?php
    $sudah_pinjam = $pdo->prepare("SELECT id FROM peminjaman WHERE user_id=? AND buku_id=? LIMIT 1");
    $sudah_pinjam->execute([$user_id,$buku_id]); $boleh_review = (bool)$sudah_pinjam->fetch();
    ?>

    <?php if($boleh_review): ?>
    <div style="background:rgba(255,255,255,0.55);border:1px solid rgba(30,58,95,0.12);border-radius:14px;padding:18px;margin-bottom:20px;backdrop-filter:blur(10px);">
      <div style="font-size:13px;font-weight:900;color:var(--d1);margin-bottom:14px;display:flex;align-items:center;gap:7px;">
        <i class="fas fa-<?= $review_user?'edit':'pen' ?>" style="color:var(--d2);"></i>
        <?= $review_user?'Edit Ulasan Kamu':'Tulis Ulasan' ?>
      </div>
      <form method="POST">
        <input type="hidden" name="aksi" value="review">
        <div style="margin-bottom:12px;">
          <div style="font-size:10px;color:var(--muted);font-weight:900;letter-spacing:.5px;text-transform:uppercase;margin-bottom:8px;">Rating</div>
          <div class="star-input">
            <?php for($i=5;$i>=1;$i--): ?>
              <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= ($review_user&&$review_user['rating']==$i)?'checked':'' ?>>
              <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
            <?php endfor; ?>
          </div>
        </div>
        <div class="form-group">
          <label>Ulasan</label>
          <textarea name="komentar" placeholder="Tulis pendapatmu tentang buku ini..."><?= e($review_user['komentar']??'') ?></textarea>
        </div>
        <button type="submit" class="btn-submit-sm">
          <i class="fas fa-paper-plane"></i> Kirim Ulasan
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="alert alert-info" style="margin-bottom:16px;">
      <i class="fas fa-info-circle"></i> Pinjam buku ini terlebih dahulu untuk memberikan ulasan.
    </div>
    <?php endif; ?>

    <?php if($reviews): ?>
      <?php foreach($reviews as $r): ?>
      <div class="review-card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
          <div class="review-avatar"><?= strtoupper(substr($r['nama_user'],0,1)) ?></div>
          <div style="flex:1;">
            <div class="review-nama"><?= e($r['nama_user']) ?></div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:3px;">
              <?php for($s=1;$s<=5;$s++): ?>
                <i class="fas fa-star" style="font-size:11px;color:<?= $s<=$r['rating']?'#d97706':'rgba(30,58,95,0.18)' ?>;"></i>
              <?php endfor; ?>
              <span style="font-size:11px;color:var(--muted);font-weight:600;margin-left:2px;"><?= formatTanggal($r['created_at']) ?></span>
            </div>
          </div>
        </div>
        <p class="review-komentar"><?= nl2br(e($r['komentar'])) ?></p>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:28px;color:var(--muted);">
        <i class="fas fa-comment-slash" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>
        <span style="font-size:14px;font-weight:700;">Belum ada ulasan. Jadilah yang pertama!</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- BUKU SERUPA -->
  <?php if($serupa): ?>
  <div class="section-block fu4">
    <div class="section-title">
      <i class="fas fa-layer-group"></i> Buku Serupa
      <span>genre <?= e($buku['genre']) ?></span>
    </div>
    <div class="serupa-grid">
      <?php foreach($serupa as $s):
        $gws = $genre_warna[$s['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
      ?>
      <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $s['id'] ?>" class="serupa-card">
        <!-- FIX 2: cover serupa -->
        <div class="serupa-cover" style="background:linear-gradient(135deg,<?= $gws['bg'] ?>,<?= $gws['bg'] ?>bb);">
          <?php if(!empty($s['cover'])): ?>
            <img src="<?= BASE_URL ?>/uploads/covers/<?= e($s['cover']) ?>"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;" alt="">
          <?php else: ?>
            <i class="fas <?= $gws['icon'] ?>"></i>
          <?php endif; ?>
        </div>
        <div class="serupa-body">
          <div class="serupa-title"><?= e($s['judul']) ?></div>
          <div class="serupa-author"><i class="fas fa-pen-nib" style="font-size:9px;"></i> <?= e($s['penulis']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<footer style="position:relative;z-index:1;text-align:center;padding:24px;font-size:12px;color:var(--muted);font-weight:700;border-top:1.5px dashed rgba(30,58,95,0.12);background:rgba(255,255,255,0.35);backdrop-filter:blur(16px);">
  <i class="fas fa-cloud" style="color:var(--d2);"></i> <strong style="color:var(--d2);">CloudLibrary Mini</strong> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>
</body>
</html>