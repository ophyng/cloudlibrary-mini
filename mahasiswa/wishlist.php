<?php
// ============================================
//  CloudLibrary Mini — Wishlist
//  File   : mahasiswa/wishlist.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_id'])) {
    $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND buku_id = ?")
        ->execute([$user_id, (int)$_POST['hapus_id']]);
    header('Location: wishlist.php?msg=hapus');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pinjam_id'])) {
    $buku_id = (int)$_POST['pinjam_id'];
    $buku = $pdo->prepare("SELECT * FROM buku WHERE id = ? AND stok > 0 AND status = 'tersedia'");
    $buku->execute([$buku_id]);
    $buku = $buku->fetch();
    if ($buku) {
        $cek = $pdo->prepare("SELECT id FROM peminjaman WHERE user_id = ? AND buku_id = ? AND status IN ('aktif','hampir_habis')");
        $cek->execute([$user_id, $buku_id]);
        if (!$cek->fetch()) {
            $dur = $pdo->prepare("SELECT durasi_hari FROM pengaturan_pinjam WHERE kategori_id = ?");
            $dur->execute([$buku['kategori_id']]);
            $durasi = $dur->fetchColumn() ?: 7;
            $tgl_pinjam  = date('Y-m-d');
            $tgl_expired = date('Y-m-d', strtotime("+$durasi days"));
            $pdo->prepare("INSERT INTO peminjaman (user_id, buku_id, tanggal_pinjam, tanggal_expired, status) VALUES (?,?,?,?,'aktif')")
                ->execute([$user_id, $buku_id, $tgl_pinjam, $tgl_expired]);
            $pdo->prepare("UPDATE buku SET stok = stok - 1, total_dipinjam = total_dipinjam + 1 WHERE id = ?")
                ->execute([$buku_id]);
            tambahPoin($pdo, $user_id, 5);
            kirimNotifikasi($pdo, $user_id, "Berhasil meminjam \"$buku[judul]\" dari wishlist!", 'info');
            cekBadge($pdo, $user_id);
        }
    }
    header('Location: wishlist.php?msg=pinjam');
    exit;
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');

$where  = ["w.user_id = ?"];
$params = [$user_id];

if ($filter === 'tersedia') { $where[] = "b.stok > 0 AND b.status = 'tersedia'"; }
if ($filter === 'habis')    { $where[] = "(b.stok = 0 OR b.status != 'tersedia')"; }
if ($search) {
    $where[]  = "(b.judul LIKE ? OR b.penulis LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

// FIX 1: tambah b.cover di query
$stmt = $pdo->prepare("
    SELECT w.*, b.judul, b.penulis, b.genre, b.tipe, b.stok, b.status AS buku_status,
           b.total_dipinjam, b.cover,
           IFNULL(AVG(r.rating),0) AS avg_rating
    FROM wishlist w
    JOIN buku b ON w.buku_id = b.id
    LEFT JOIN review r ON r.buku_id = b.id AND r.status = 'tampil'
    WHERE " . implode(' AND ', $where) . "
    GROUP BY w.id
    ORDER BY w.created_at DESC
");
$stmt->execute($params);
$wishlist = $stmt->fetchAll();

$dipinjam_ids_stmt = $pdo->prepare("SELECT buku_id FROM peminjaman WHERE user_id = ? AND status IN ('aktif','hampir_habis')");
$dipinjam_ids_stmt->execute([$user_id]);
$dipinjam_ids = $dipinjam_ids_stmt->fetchAll(PDO::FETCH_COLUMN);

$genre_warna = [
    'Novel'    => ['bg' => '#1a237e', 'icon' => 'fa-book'],
    'Cerpen'   => ['bg' => '#4a148c', 'icon' => 'fa-scroll'],
    'Fantasi'  => ['bg' => '#1b5e20', 'icon' => 'fa-hat-wizard'],
    'Romance'  => ['bg' => '#880e4f', 'icon' => 'fa-heart'],
    'Horror'   => ['bg' => '#b71c1c', 'icon' => 'fa-ghost'],
    'Misteri'  => ['bg' => '#e65100', 'icon' => 'fa-user-secret'],
    'Sci-Fi'   => ['bg' => '#006064', 'icon' => 'fa-rocket'],
    'Filsafat' => ['bg' => '#37474f', 'icon' => 'fa-landmark'],
    'Sains'    => ['bg' => '#1565c0', 'icon' => 'fa-microscope'],
    'Biografi' => ['bg' => '#4e342e', 'icon' => 'fa-feather-alt'],
];

$total_tersedia = array_sum(array_map(fn($w) => ($w['stok']>0 && $w['buku_status']==='tersedia')?1:0, $wishlist));
$total_habis    = count($wishlist) - $total_tersedia;

$msg   = $_GET['msg'] ?? '';
$title = "Wishlist — CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
body {
  background-image: url('gambar perpustakaan.jpg') !important;
  background-size: cover !important;
  background-position: center top !important;
  background-attachment: fixed !important;
  background-repeat: no-repeat !important;
  position: relative;
  overflow-x: hidden;
}
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background: rgba(220, 235, 255, 0.10);
  z-index: 0;
  pointer-events: none;
}
:root {
  --glass:       rgba(255,255,255,0.25);
  --glass-b:     rgba(255,255,255,0.80);
  --glass-hover: rgba(255,255,255,0.28);
  --border:      rgba(30,58,95,0.07);
  --border-s:    rgba(30,58,95,0.09);
  --text:        #1a2332;
  --text-sub:    #3d5270;
  --muted:       #6b80a0;
  --d1:          #1e3a5f;
  --d2:          #2d5986;
  --d3:          #4a7ab5;
  --gold:        #d97706;
  --gold-l:      #fbbf24;
  --pk:          #db2777;
  --pk-l:        #f472b6;
  --sh:          0 4px 24px rgba(30,58,95,0.08);
  --sh-md:       0 8px 36px rgba(30,58,95,0.18);
}
.page-outer { position: relative; z-index: 1; max-width: 1100px; margin: 0 auto; padding: 28px 20px 60px; }
.wish-page-header { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.09); border-radius: 24px; padding: 28px 32px; margin-bottom: 22px; backdrop-filter: blur(32px); -webkit-backdrop-filter: blur(32px); box-shadow: var(--sh-md); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; position: relative; overflow: hidden; }
.wish-page-header::before { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(219,39,119,0.12), transparent 65%); pointer-events: none; }
.wish-page-header-left { display: flex; align-items: center; gap: 16px; }
.wish-header-icon { width: 52px; height: 52px; border-radius: 16px; background: linear-gradient(135deg, #880e4f, #db2777); display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; box-shadow: 0 4px 16px rgba(219,39,119,0.35); flex-shrink: 0; }
.wish-header-title { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); }
.wish-header-sub { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.wish-header-stats { display: flex; gap: 12px; flex-wrap: wrap; }
.wish-stat-pill { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 100px; background: rgba(255,255,255,0.28); border: 1px solid rgba(30,58,95,0.09); font-size: 12px; font-weight: 800; color: var(--text); backdrop-filter: blur(18px); }
.wish-alert { border-radius: 16px; padding: 14px 20px; margin-bottom: 18px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 10px; backdrop-filter: blur(22px); border: 1px solid; }
.wish-alert.info    { background: rgba(147,197,253,0.20); border-color: rgba(59,130,246,0.25); color: var(--d1); }
.wish-alert.success { background: rgba(134,239,172,0.20); border-color: rgba(34,197,94,0.25);  color: #14532d; }
.wish-search-row { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 20px; padding: 18px 20px; margin-bottom: 18px; backdrop-filter: blur(28px); box-shadow: var(--sh); display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.wish-search-input { flex: 1; min-width: 200px; background: rgba(255,255,255,0.50); border: 1.5px solid rgba(30,58,95,0.12); border-radius: 100px; padding: 10px 18px; font-size: 13px; font-family: 'Nunito', sans-serif; font-weight: 700; color: var(--text); outline: none; transition: all 0.2s; }
.wish-search-input:focus { border-color: var(--d2); background: rgba(255,255,255,0.85); box-shadow: 0 0 0 3px rgba(45,89,134,0.12); }
.wish-search-input::placeholder { color: var(--muted); }
.filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 100px; font-size: 12px; font-weight: 800; border: 1.5px solid rgba(30,58,95,0.14); color: var(--text-sub); text-decoration: none; background: rgba(255,255,255,0.28); backdrop-filter: blur(14px); transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.filter-pill:hover { background: rgba(255,255,255,0.75); border-color: var(--d2); color: var(--d1); }
.filter-pill.active { background: linear-gradient(135deg, #1e3a5f, #2d5986); border-color: transparent; color: #fff; box-shadow: 0 3px 12px rgba(30,58,95,0.35); }
.filter-pill.active-green { background: linear-gradient(135deg, #14532d, #16a34a); border-color: transparent; color: #fff; box-shadow: 0 3px 12px rgba(22,163,74,0.35); }
.filter-pill.active-red { background: linear-gradient(135deg, #7f1d1d, #dc2626); border-color: transparent; color: #fff; box-shadow: 0 3px 12px rgba(220,38,38,0.35); }
.wishlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; }
.wish-card { background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 20px; overflow: hidden; backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); box-shadow: var(--sh); transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; position: relative; }
.wish-card:hover { transform: translateY(-5px); box-shadow: var(--sh-md); }
/* FIX: tambah overflow:hidden dan position:relative */
.wish-cover { height: 180px; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 8px; font-size: 36px; position: relative; color: #fff; overflow: hidden; }
.wish-cover-label { font-size: 10px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,0.55); }
.wish-tipe-badge { position: absolute; top: 10px; left: 10px; font-size: 9px; font-weight: 900; padding: 3px 9px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; z-index: 3; }
.tipe-fiksi    { background: rgba(219,39,119,0.85); color: #fff; }
.tipe-nonfiksi { background: rgba(30,58,95,0.85);   color: #fff; }
.wish-remove { position: absolute; top: 10px; right: 10px; width: 30px; height: 30px; border-radius: 50%; background: rgba(220,38,38,0.15); border: 1px solid rgba(220,38,38,0.30); color: #dc2626; font-size: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; z-index: 3; }
.wish-remove:hover { background: rgba(220,38,38,0.30); transform: scale(1.1); }
.wish-date { position: absolute; bottom: 8px; left: 10px; font-size: 10px; color: rgba(255,255,255,0.50); background: rgba(0,0,0,0.30); padding: 2px 8px; border-radius: 5px; display: flex; align-items: center; gap: 4px; z-index: 3; }
.wish-body { padding: 14px; flex: 1; display: flex; flex-direction: column; }
.wish-genre-tag { font-size: 10px; font-weight: 800; color: var(--d3); margin-bottom: 4px; }
.wish-title { font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 900; color: var(--text); line-height: 1.4; margin-bottom: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.wish-author { font-size: 11px; color: var(--muted); font-weight: 600; margin-bottom: 8px; }
.wish-rating { font-size: 11px; color: var(--gold); margin-bottom: 8px; }
.stok-dot { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; margin-bottom: 10px; }
.stok-dot.ada   { color: #15803d; }
.stok-dot.habis { color: #dc2626; }
.stok-dot i { font-size: 7px; }
.wish-actions { margin-top: auto; display: flex; flex-direction: column; gap: 6px; }
.btn-pinjam { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px; border-radius: 100px; background: linear-gradient(135deg, #1e3a5f, #2d5986); color: #fff; font-size: 12px; font-weight: 800; text-decoration: none; border: none; cursor: pointer; box-shadow: 0 3px 10px rgba(30,58,95,0.35); transition: all 0.2s; font-family: 'Nunito', sans-serif; width: 100%; }
.btn-pinjam:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(30,58,95,0.45); }
.btn-baca-w { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px; border-radius: 100px; background: linear-gradient(135deg, #14532d, #16a34a); color: #fff; font-size: 12px; font-weight: 800; text-decoration: none; box-shadow: 0 3px 10px rgba(22,163,74,0.35); transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.btn-baca-w:hover { transform: translateY(-1px); opacity: 0.9; }
.btn-antrian { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px; border-radius: 100px; background: linear-gradient(135deg, #78350f, #d97706); color: #fff; font-size: 12px; font-weight: 800; text-decoration: none; box-shadow: 0 3px 10px rgba(217,119,6,0.35); transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.btn-antrian:hover { transform: translateY(-1px); opacity: 0.9; }
.btn-detail { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px; border-radius: 100px; background: rgba(255,255,255,0.28); border: 1.5px solid rgba(30,58,95,0.14); color: var(--text); font-size: 12px; font-weight: 800; text-decoration: none; transition: all 0.2s; font-family: 'Nunito', sans-serif; }
.btn-detail:hover { background: rgba(255,255,255,0.85); }
.empty-wish-full { grid-column: 1 / -1; background: rgba(255,255,255,0.25); border: 1px solid rgba(30,58,95,0.08); border-radius: 24px; padding: 60px; text-align: center; backdrop-filter: blur(28px); box-shadow: var(--sh); }
.empty-wish-full .empty-icon { width: 72px; height: 72px; border-radius: 22px; background: rgba(219,39,119,0.10); border: 1.5px solid rgba(219,39,119,0.20); display: flex; align-items: center; justify-content: center; font-size: 30px; color: var(--pk); margin: 0 auto 20px; }
.empty-wish-full h3 { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); margin-bottom: 8px; }
.empty-wish-full p { font-size: 13px; color: var(--muted); font-weight: 600; line-height: 1.8; margin-bottom: 20px; }
.btn-primary-w { display: inline-flex; align-items: center; gap: 7px; padding: 12px 26px; border-radius: 100px; background: linear-gradient(135deg, #1e3a5f, #2d5986); color: #fff; font-size: 13px; font-weight: 900; text-decoration: none; box-shadow: 0 4px 18px rgba(30,58,95,0.40); transition: all 0.25s; font-family: 'Nunito', sans-serif; }
.btn-primary-w:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(30,58,95,0.50); }
footer.mhs-footer { position: relative; z-index: 1; text-align: center; padding: 24px; font-size: 12px; color: var(--muted); font-weight: 700; background: rgba(255,255,255,0.45); backdrop-filter: blur(22px); border-top: 1px solid rgba(30,58,95,0.07); }
@keyframes fadeUp { from{opacity:0;transform:translateY(16px);} to{opacity:1;transform:translateY(0);} }
.fu { animation: fadeUp 0.5s ease both; }
.fu:nth-child(1){animation-delay:.05s;} .fu:nth-child(2){animation-delay:.10s;}
.fu:nth-child(3){animation-delay:.15s;} .fu:nth-child(4){animation-delay:.20s;}
.fu:nth-child(5){animation-delay:.25s;} .fu:nth-child(6){animation-delay:.30s;}
.fu:nth-child(7){animation-delay:.35s;} .fu:nth-child(8){animation-delay:.40s;}
@media(max-width:600px) { .wishlist-grid { grid-template-columns: 1fr 1fr; } .wish-page-header { flex-direction: column; align-items: flex-start; } }
@media(max-width:400px) { .wishlist-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-outer">

  <div class="wish-page-header fu">
    <div class="wish-page-header-left">
      <div class="wish-header-icon"><i class="fas fa-heart"></i></div>
      <div>
        <div class="wish-header-title">Wishlist Saya</div>
        <div class="wish-header-sub"><?= count($wishlist) ?> buku tersimpan</div>
      </div>
    </div>
    <div class="wish-header-stats">
      <div class="wish-stat-pill"><i class="fas fa-circle" style="color:#15803d;font-size:8px;"></i><?= $total_tersedia ?> tersedia</div>
      <div class="wish-stat-pill"><i class="fas fa-circle" style="color:#dc2626;font-size:8px;"></i><?= $total_habis ?> stok habis</div>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="wish-stat-pill" style="text-decoration:none;color:var(--d2);border-color:rgba(45,89,134,0.20);">
        <i class="fas fa-plus" style="font-size:10px;"></i> Tambah Buku
      </a>
    </div>
  </div>

  <?php if ($msg === 'hapus'): ?>
    <div class="wish-alert info fu"><i class="fas fa-trash-alt"></i> Buku berhasil dihapus dari wishlist.</div>
  <?php elseif ($msg === 'pinjam'): ?>
    <div class="wish-alert success fu"><i class="fas fa-check-circle"></i> Berhasil meminjam buku dari wishlist! +5 poin ditambahkan.</div>
  <?php endif; ?>

  <div class="wish-search-row fu">
    <i class="fas fa-search" style="color:var(--muted);font-size:14px;flex-shrink:0;"></i>
    <input type="text" id="searchInput" class="wish-search-input" placeholder="Cari judul atau penulis..." value="<?= e($search) ?>" oninput="filterLive()">
    <div class="filter-pills">
      <a href="?q=<?= urlencode($search) ?>" class="filter-pill <?= !$filter ? 'active' : '' ?>">
        <i class="fas fa-heart" style="font-size:10px;"></i> Semua (<?= count($wishlist) ?>)
      </a>
      <a href="?filter=tersedia&q=<?= urlencode($search) ?>" class="filter-pill <?= $filter==='tersedia' ? 'active-green' : '' ?>">
        <i class="fas fa-check-circle" style="font-size:10px;"></i> Tersedia (<?= $total_tersedia ?>)
      </a>
      <a href="?filter=habis&q=<?= urlencode($search) ?>" class="filter-pill <?= $filter==='habis' ? 'active-red' : '' ?>">
        <i class="fas fa-times-circle" style="font-size:10px;"></i> Stok Habis (<?= $total_habis ?>)
      </a>
    </div>
  </div>

  <div class="wishlist-grid" id="wishGrid">

    <?php if ($wishlist): ?>
      <?php foreach ($wishlist as $i => $w):
        $gw       = $genre_warna[$w['genre']] ?? ['bg' => '#1e3a5f', 'icon' => 'fa-book'];
        $tersedia = $w['stok'] > 0 && $w['buku_status'] === 'tersedia';
        $dipinjam = in_array($w['buku_id'], $dipinjam_ids);
        $rating   = round($w['avg_rating'], 1);
      ?>
      <div class="wish-card fu" style="animation-delay:<?= ($i * 0.05 + 0.1) ?>s;"
           data-judul="<?= strtolower(e($w['judul'])) ?>"
           data-penulis="<?= strtolower(e($w['penulis'])) ?>">

        <!-- FIX 2: cover wishlist -->
        <div class="wish-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>99);">
          <?php if(!empty($w['cover'])): ?>
            <img src="<?= BASE_URL ?>/uploads/covers/<?= e($w['cover']) ?>"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;" alt="">
          <?php else: ?>
            <i class="fas <?= $gw['icon'] ?>" style="font-size:36px;"></i>
            <span class="wish-cover-label"><?= e($w['genre']) ?></span>
          <?php endif; ?>

          <span class="wish-tipe-badge <?= $w['tipe']==='fiksi' ? 'tipe-fiksi' : 'tipe-nonfiksi' ?>">
            <?= $w['tipe'] ?>
          </span>

          <form method="POST" style="display:contents;">
            <input type="hidden" name="hapus_id" value="<?= $w['buku_id'] ?>">
            <button class="wish-remove" type="submit"
                    onclick="return confirm('Hapus buku ini dari wishlist?')"
                    title="Hapus dari Wishlist">
              <i class="fas fa-times"></i>
            </button>
          </form>

          <span class="wish-date">
            <i class="fas fa-clock"></i>
            <?= formatTanggal($w['created_at']) ?>
          </span>
        </div>

        <div class="wish-body">
          <div class="wish-genre-tag"><?= e($w['genre']) ?></div>
          <div class="wish-title"><?= e($w['judul']) ?></div>
          <div class="wish-author"><i class="fas fa-pen-nib" style="font-size:9px;margin-right:3px;"></i><?= e($w['penulis']) ?></div>

          <?php if ($rating > 0): ?>
          <div class="wish-rating">
            <?= tampilBintang($rating) ?>
            <span style="color:var(--muted);font-size:10px;"><?= $rating ?></span>
          </div>
          <?php endif; ?>

          <div class="stok-dot <?= $tersedia ? 'ada' : 'habis' ?>">
            <i class="fas fa-circle"></i>
            <?= $tersedia ? 'Tersedia &middot; ' . $w['stok'] . ' stok' : 'Stok Habis' ?>
          </div>

          <div class="wish-actions">
            <?php if ($dipinjam): ?>
              <a href="<?= BASE_URL ?>/mahasiswa/baca.php?id=<?= $w['buku_id'] ?>" class="btn-baca-w">
                <i class="fas fa-book-open"></i> Baca Sekarang
              </a>
            <?php elseif ($tersedia): ?>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="pinjam_id" value="<?= $w['buku_id'] ?>">
                <button type="submit" class="btn-pinjam">
                  <i class="fas fa-hand-holding"></i> Pinjam Sekarang
                </button>
              </form>
            <?php else: ?>
              <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $w['buku_id'] ?>" class="btn-antrian">
                <i class="fas fa-list-ol"></i> Masuk Antrian
              </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $w['buku_id'] ?>" class="btn-detail">
              <i class="fas fa-eye"></i> Lihat Detail
            </a>
          </div>
        </div>

      </div>
      <?php endforeach; ?>

    <?php else: ?>
      <div class="empty-wish-full">
        <div class="empty-icon"><i class="fas fa-heart-broken"></i></div>
        <h3>Wishlist Masih Kosong</h3>
        <p>Kamu belum menyimpan buku apapun ke wishlist.<br>Jelajahi katalog dan tambahkan buku favoritmu!</p>
        <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn-primary-w">
          <i class="fas fa-book"></i> Jelajahi Katalog
        </a>
      </div>
    <?php endif; ?>

  </div>

</div>

<footer class="mhs-footer">
  <i class="fas fa-cloud" style="color:var(--d2);margin-right:6px;"></i>
  <strong style="color:var(--d2);">CloudLibrary Mini</strong> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
function filterLive() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#wishGrid .wish-card').forEach(el => {
    const match = el.dataset.judul.includes(q) || el.dataset.penulis.includes(q);
    el.style.display = match ? '' : 'none';
  });
}
</script>
</body>
</html>