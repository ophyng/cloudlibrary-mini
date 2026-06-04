<?php
// ============================================
//  CloudLibrary Mini — Katalog Buku
//  File   : mahasiswa/katalog.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();
updateStatusPeminjaman($pdo);

$genre_warna = [
    'Novel'   =>['bg'=>'#1a237e','icon'=>'fa-book',       'accent'=>'#7986cb'],
    'Cerpen'  =>['bg'=>'#4a148c','icon'=>'fa-file-alt',   'accent'=>'#ce93d8'],
    'Fantasi' =>['bg'=>'#1b5e20','icon'=>'fa-hat-wizard', 'accent'=>'#81c784'],
    'Romance' =>['bg'=>'#880e4f','icon'=>'fa-heart',      'accent'=>'#f48fb1'],
    'Horror'  =>['bg'=>'#b71c1c','icon'=>'fa-ghost',      'accent'=>'#ef9a9a'],
    'Misteri' =>['bg'=>'#e65100','icon'=>'fa-search',     'accent'=>'#ffcc80'],
    'Sci-Fi'  =>['bg'=>'#006064','icon'=>'fa-rocket',     'accent'=>'#80deea'],
    'Filsafat'=>['bg'=>'#37474f','icon'=>'fa-landmark',   'accent'=>'#b0bec5'],
    'Sains'   =>['bg'=>'#1565c0','icon'=>'fa-flask',      'accent'=>'#90caf9'],
    'Biografi'=>['bg'=>'#4e342e','icon'=>'fa-user',       'accent'=>'#bcaaa4'],
];

$search = trim($_GET['q']   ?? '');
$tipe   = $_GET['tipe']     ?? '';
$genre  = $_GET['genre']    ?? '';
$sort   = $_GET['sort']     ?? 'populer';
$where  = ["b.status != 'arsip'"]; $params = [];
if ($search) { $where[] = "(b.judul LIKE ? OR b.penulis LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($tipe)   { $where[] = "b.tipe = ?";  $params[] = $tipe; }
if ($genre)  { $where[] = "b.genre = ?"; $params[] = $genre; }
$order = match($sort) { 'terbaru'=>'b.created_at DESC','rating'=>'avg_rating DESC','az'=>'b.judul ASC',default=>'b.total_dipinjam DESC' };
$stmt = $pdo->prepare("
    SELECT b.*, IFNULL(AVG(r.rating),0) AS avg_rating, COUNT(r.id) AS jumlah_review
    FROM buku b LEFT JOIN review r ON r.buku_id=b.id AND r.status='tampil'
    WHERE ".implode(' AND ',$where)." GROUP BY b.id ORDER BY $order
");
$stmt->execute($params); $buku_list = $stmt->fetchAll();
$semua_genre  = $pdo->query("SELECT DISTINCT genre FROM buku WHERE status!='arsip' ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);
$dipinjam_ids = $pdo->prepare("SELECT buku_id FROM peminjaman WHERE user_id=? AND status IN('aktif','hampir_habis')");
$dipinjam_ids->execute([$_SESSION['user_id']]); $dipinjam_ids = $dipinjam_ids->fetchAll(PDO::FETCH_COLUMN);
$wishlist_ids = $pdo->prepare("SELECT buku_id FROM wishlist WHERE user_id=?");
$wishlist_ids->execute([$_SESSION['user_id']]); $wishlist_ids = $wishlist_ids->fetchAll(PDO::FETCH_COLUMN);

$title = "Katalog Buku — CloudLibrary Mini";
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
body::before{content:'';position:fixed;inset:0;z-index:0;background:rgba(220,235,255,0.18);pointer-events:none;}
.main-wrap,.container,main{background:transparent !important;}
:root{
  --d1:#1e3a5f;--d2:#2d5986;--d3:#5b8fb9;
  --pk:#f472b6;--gold:#f59e0b;
  --text:#0f1c2e;--muted:#5a6a85;
  --card-bg:rgba(255,255,255,0.22);
  --card-border:rgba(255,255,255,0.55);
  --sh:0 4px 20px rgba(30,58,95,0.10);
  --sh-md:0 10px 36px rgba(30,58,95,0.16);
}

.kat-wrap{position:relative;z-index:1;max-width:1180px;margin:0 auto;padding:32px 24px 60px;}

/* HERO */
.kat-header{background:linear-gradient(135deg,#1e3a5f 0%,#2d5986 45%,#5b8fb9 100%);border-radius:24px;padding:32px 40px;margin-bottom:28px;position:relative;overflow:hidden;box-shadow:0 20px 56px rgba(30,58,95,0.28);border:1px solid rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;}
.kat-header::before{content:'';position:absolute;top:-80px;right:-80px;width:320px;height:320px;background:radial-gradient(circle,rgba(244,114,182,0.18) 0%,transparent 65%);}
.kat-inner-border{position:absolute;inset:10px;border:2px dashed rgba(255,255,255,0.12);border-radius:16px;pointer-events:none;}
.kat-header-left{position:relative;z-index:1;}
.kat-title-icon{width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:14px;color:#fff;}
.kat-title{font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:#fff;margin-bottom:6px;}
.kat-subtitle{font-size:13px;color:rgba(255,255,255,0.60);font-weight:600;}
.kat-header-right{position:relative;z-index:1;display:flex;gap:12px;flex-wrap:wrap;}
.kat-stat-pill{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.22);border-radius:14px;padding:13px 20px;text-align:center;backdrop-filter:blur(8px);min-width:90px;}
.kat-stat-pill .num{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:#fff;}
.kat-stat-pill .lbl{font-size:10px;color:rgba(255,255,255,0.55);font-weight:700;margin-top:2px;}
.kat-nav-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:100px;font-size:12px;font-weight:800;text-decoration:none;font-family:'Nunito',sans-serif;transition:all .2s;}
.kat-btn-w{background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.25);color:#fff;}
.kat-btn-w:hover{background:rgba(255,255,255,0.25);}
.kat-btn-p{background:rgba(244,114,182,0.22);border:1.5px solid rgba(244,114,182,0.38);color:#fbbfd8;}
.kat-btn-p:hover{background:rgba(244,114,182,0.35);}

/* FILTER CARD */
.filter-card{background:rgba(255,255,255,0.22);border:1.5px solid rgba(255,255,255,0.50);border-radius:20px;padding:20px 24px;margin-bottom:20px;backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);box-shadow:var(--sh);}
.filter-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:16px;}
.search-box{flex:1;min-width:220px;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.35);border:1.5px solid rgba(58,97,134,0.18);border-radius:100px;padding:10px 18px;transition:border-color .2s;}
.search-box:focus-within{border-color:var(--d2);}
.search-box i{color:var(--d3);font-size:13px;flex-shrink:0;}
.search-box input{border:none;background:transparent;outline:none;font-size:13px;font-weight:600;color:var(--text);font-family:'Nunito',sans-serif;width:100%;}
.search-box input::placeholder{color:var(--muted);font-weight:500;}
.filter-select{padding:10px 16px;border-radius:100px;border:1.5px solid rgba(58,97,134,0.18);background:rgba(255,255,255,0.35);color:var(--text);font-size:12px;font-weight:700;cursor:pointer;font-family:'Nunito',sans-serif;outline:none;}
.flbl{font-size:11px;font-weight:900;color:var(--muted);margin-bottom:8px;letter-spacing:.5px;text-transform:uppercase;}
.sort-tabs,.genre-pills{display:flex;gap:7px;flex-wrap:wrap;}
.sort-tabs{margin-bottom:16px;}
.sort-tab,.genre-pill{padding:7px 15px;border-radius:100px;font-size:12px;font-weight:800;border:1.5px solid rgba(58,97,134,0.18);color:var(--muted);background:rgba(255,255,255,0.30);cursor:pointer;text-decoration:none;transition:all .2s;font-family:'Nunito',sans-serif;display:inline-flex;align-items:center;gap:5px;backdrop-filter:blur(8px);}
.sort-tab:hover,.genre-pill:hover{border-color:var(--d2);color:var(--d2);background:rgba(255,255,255,0.55);}
.sort-tab.active{background:linear-gradient(135deg,var(--d1),var(--d2));color:#fff;border-color:transparent;box-shadow:0 3px 10px rgba(30,58,95,0.25);}
.genre-pill.active{background:linear-gradient(135deg,var(--pk),#e879a8);color:#fff;border-color:transparent;box-shadow:0 3px 10px rgba(244,114,182,0.30);}

/* RESULT BAR */
.result-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:20px;font-size:13px;font-weight:700;color:var(--muted);position:relative;z-index:1;}
.result-bar strong{color:var(--d2);}
.result-tag{background:rgba(58,97,134,0.10);border:1px solid rgba(58,97,134,0.20);border-radius:100px;padding:3px 11px;font-size:11px;font-weight:800;color:var(--d2);text-decoration:none;display:inline-flex;align-items:center;gap:4px;}

/* BOOK GRID */
.book-grid-kat{display:grid;grid-template-columns:repeat(auto-fill,minmax(205px,1fr));gap:18px;position:relative;z-index:1;}
.book-card-kat{background:rgba(255,255,255,0.22);border:1.5px solid rgba(255,255,255,0.50);border-radius:20px;overflow:hidden;backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);box-shadow:var(--sh);transition:transform .25s,box-shadow .25s;position:relative;}
.book-card-kat:hover{transform:translateY(-6px);box-shadow:var(--sh-md);background:rgba(255,255,255,0.30);}
.book-cover-kat{height:155px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;position:relative;overflow:hidden;}
.book-cover-kat .cover-icon{font-size:32px;position:relative;z-index:1;color:#fff;}
.book-cover-kat .cover-genre-lbl{font-size:9px;font-weight:900;letter-spacing:1.2px;color:rgba(255,255,255,0.55);text-transform:uppercase;position:relative;z-index:1;}
.book-cover-kat::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.12) 0%,transparent 60%,rgba(0,0,0,0.20) 100%);}
.tipe-pill-kat{position:absolute;top:10px;left:10px;z-index:2;font-size:9px;font-weight:900;letter-spacing:.8px;padding:3px 9px;border-radius:20px;}
.pill-fiksi{background:rgba(244,114,182,0.88);color:#fff;}
.pill-nonfiksi{background:rgba(91,143,185,0.88);color:#fff;}
.wish-btn-kat{position:absolute;top:10px;right:10px;z-index:2;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,0.38);border:1.5px solid rgba(255,255,255,0.20);color:rgba(255,255,255,0.75);font-size:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;}
.wish-btn-kat:hover{background:rgba(0,0,0,0.60);color:#fff;transform:scale(1.12);}
.wish-btn-kat.active{color:#ef4444;background:rgba(239,68,68,0.20);border-color:rgba(239,68,68,0.40);}
.book-body-kat{padding:14px 16px;}
.book-genre-tag-kat{font-size:10px;font-weight:800;background:rgba(58,97,134,0.08);color:var(--d2);padding:2px 9px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;margin-bottom:7px;}
.book-title-kat{font-family:'Syne',sans-serif;font-size:13px;font-weight:900;color:var(--text);margin-bottom:3px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.book-author-kat{font-size:11px;color:var(--muted);font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:4px;}
.book-rating-kat{font-size:11px;color:var(--gold);margin-bottom:8px;display:flex;align-items:center;gap:3px;}
.stok-badge-kat{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;padding:3px 9px;border-radius:7px;margin-bottom:10px;}
.stok-ada{background:rgba(34,197,94,0.10);color:#16a34a;}
.stok-habis{background:rgba(239,68,68,0.10);color:#dc2626;}
.btn-action-kat{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:9px;border-radius:100px;font-size:12px;font-weight:800;text-align:center;text-decoration:none;transition:all .2s;font-family:'Nunito',sans-serif;}
.btn-pinjam{background:linear-gradient(135deg,#2563eb,#5b8fb9);color:#fff;box-shadow:0 3px 10px rgba(37,99,235,0.28);}
.btn-pinjam:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(37,99,235,0.38);}
.btn-baca{background:linear-gradient(135deg,#059669,#34d399);color:#fff;box-shadow:0 3px 10px rgba(5,150,105,0.28);}
.btn-baca:hover{transform:translateY(-1px);}
.btn-antre{background:rgba(58,97,134,0.10);color:var(--d2);border:1.5px solid rgba(58,97,134,0.25);}
.btn-antre:hover{background:var(--d2);color:#fff;}
.empty-kat{background:var(--card-bg);border:1.5px solid var(--card-border);border-radius:20px;padding:60px 40px;text-align:center;backdrop-filter:blur(14px);box-shadow:var(--sh);position:relative;z-index:1;}
.empty-kat i{font-size:48px;color:var(--muted);display:block;margin-bottom:16px;opacity:.5;}
.empty-kat p{font-size:14px;color:var(--muted);font-weight:600;line-height:1.8;}
.empty-kat a{color:var(--d2);font-weight:800;text-decoration:none;}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}
</style>

<div class="kat-wrap">

  <!-- HERO HEADER -->
  <div class="kat-header fu1">
    <div class="kat-inner-border"></div>
    <div class="kat-header-left">
      <div class="kat-title-icon"><i class="fas fa-book-open"></i></div>
      <div class="kat-title">Katalog Buku</div>
      <div class="kat-subtitle"><i class="fas fa-cloud" style="font-size:11px;margin-right:4px;"></i>Temukan buku favoritmu dari koleksi digital</div>
    </div>
    <div class="kat-header-right">
      <div class="kat-stat-pill">
        <div class="num"><?= count($buku_list) ?></div>
        <div class="lbl">Hasil</div>
      </div>
      <div class="kat-stat-pill">
        <div class="num"><?= count($semua_genre) ?></div>
        <div class="lbl">Genre</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;justify-content:center;">
        <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="kat-nav-btn kat-btn-w">
          <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/mahasiswa/wishlist.php" class="kat-nav-btn kat-btn-p">
          <i class="fas fa-heart"></i> Wishlist
        </a>
      </div>
    </div>
  </div>

  <!-- FILTER CARD -->
  <div class="filter-card fu2">
    <div class="filter-row">
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Cari judul atau penulis..." value="<?= e($search) ?>" oninput="filterLive()">
      </div>
      <select id="tipeSelect" class="filter-select" onchange="submitFilter()">
        <option value="" <?= !$tipe?'selected':'' ?>>Semua Tipe</option>
        <option value="fiksi"     <?= $tipe==='fiksi'    ?'selected':'' ?>>Fiksi</option>
        <option value="non-fiksi" <?= $tipe==='non-fiksi'?'selected':'' ?>>Non-Fiksi</option>
      </select>
    </div>

    <div class="flbl">Urutkan</div>
    <div class="sort-tabs">
      <?php foreach([
        'populer'=>'<i class="fas fa-fire"></i> Populer',
        'terbaru'=>'<i class="fas fa-clock"></i> Terbaru',
        'rating' =>'<i class="fas fa-star"></i> Rating',
        'az'     =>'<i class="fas fa-sort-alpha-down"></i> A–Z',
      ] as $k=>$lbl): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['sort'=>$k])) ?>" class="sort-tab <?= $sort===$k?'active':'' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <div class="flbl" style="margin-top:16px;">Genre</div>
    <div class="genre-pills">
      <a href="?<?= http_build_query(array_merge($_GET,['genre'=>'','q'=>$search])) ?>" class="genre-pill <?= !$genre?'active':'' ?>">
        <i class="fas fa-th-large"></i> Semua
      </a>
      <?php foreach($semua_genre as $g):
        $gw = $genre_warna[$g] ?? ['icon'=>'fa-book'];
      ?>
        <a href="?<?= http_build_query(array_merge($_GET,['genre'=>$g,'q'=>$search])) ?>" class="genre-pill <?= $genre===$g?'active':'' ?>">
          <i class="fas <?= $gw['icon'] ?>"></i> <?= e($g) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RESULT BAR -->
  <div class="result-bar fu3">
    <span>
      Menampilkan <strong><?= count($buku_list) ?></strong> buku
      <?= $search?"· cari <strong>\"".e($search)."\"</strong>"  :'' ?>
      <?= $genre ?"· genre <strong>".e($genre)."</strong>"       :'' ?>
      <?= $tipe  ?"· tipe <strong>".e($tipe)."</strong>"         :'' ?>
    </span>
    <?php if($search||$genre||$tipe): ?>
      <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="result-tag"><i class="fas fa-times"></i> Reset filter</a>
    <?php endif; ?>
  </div>

  <!-- BOOK GRID -->
  <?php if($buku_list): ?>
  <div class="book-grid-kat" id="bookGrid">
    <?php foreach($buku_list as $b):
      $gw           = $genre_warna[$b['genre']] ?? ['bg'=>'#1e3a5f','icon'=>'fa-book'];
      $sedang_pinjam= in_array($b['id'],$dipinjam_ids);
      $in_wishlist  = in_array($b['id'],$wishlist_ids);
      $tersedia     = $b['stok']>0 && $b['status']==='tersedia';
      $rating       = round($b['avg_rating'],1);
    ?>
    <div class="book-card-kat" data-judul="<?= strtolower(e($b['judul'])) ?>" data-penulis="<?= strtolower(e($b['penulis'])) ?>">
      <span class="tipe-pill-kat <?= $b['tipe']==='fiksi'?'pill-fiksi':'pill-nonfiksi' ?>"><?= strtoupper($b['tipe']) ?></span>
      <form method="POST" action="<?= BASE_URL ?>/mahasiswa/toggle_wishlist.php" style="display:contents">
        <input type="hidden" name="buku_id" value="<?= $b['id'] ?>">
        <button type="submit" class="wish-btn-kat <?= $in_wishlist?'active':'' ?>" title="<?= $in_wishlist?'Hapus dari Wishlist':'Tambah ke Wishlist' ?>">
          <i class="fas fa-heart"></i>
        </button>
      </form>
      <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $b['id'] ?>" style="display:block;text-decoration:none;">
        <div class="book-cover-kat" style="background:linear-gradient(135deg,<?= $gw['bg'] ?> 0%,<?= $gw['bg'] ?>bb 100%);">
          <?php if(!empty($b['cover'])): ?>
            <img src="<?= BASE_URL ?>/uploads/covers/<?= e($b['cover']) ?>"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;padding:6px;"
                 alt="<?= e($b['judul']) ?>">
          <?php else: ?>
            <i class="fas <?= $gw['icon'] ?> cover-icon"></i>
            <span class="cover-genre-lbl"><?= e($b['genre']) ?></span>
          <?php endif; ?>
        </div>
      </a>
      <div class="book-body-kat">
        <span class="book-genre-tag-kat"><i class="fas <?= $gw['icon'] ?>" style="font-size:9px;"></i> <?= e($b['genre']) ?></span>
        <div class="book-title-kat"><?= e($b['judul']) ?></div>
        <div class="book-author-kat"><i class="fas fa-pen-nib" style="font-size:9px;opacity:.6;"></i> <?= e($b['penulis']) ?></div>
        <?php if($b['jumlah_review']>0): ?>
        <div class="book-rating-kat">
          <?php for($s=1;$s<=5;$s++): ?><i class="fas fa-star" style="font-size:10px;<?= $s<=$rating?'':'opacity:0.20' ?>"></i><?php endfor; ?>
          <span style="color:var(--muted);font-size:10px;margin-left:2px;">(<?= $b['jumlah_review'] ?>)</span>
        </div>
        <?php endif; ?>
        <span class="stok-badge-kat <?= $tersedia?'stok-ada':'stok-habis' ?>">
          <i class="fas fa-<?= $tersedia?'check-circle':'times-circle' ?>"></i>
          <?= $tersedia?'Tersedia · '.$b['stok'].' stok':'Tidak Tersedia' ?>
        </span>
        <?php if($sedang_pinjam): ?>
          <a href="<?= BASE_URL ?>/mahasiswa/baca.php?id=<?= $b['id'] ?>" class="btn-action-kat btn-baca"><i class="fas fa-book-open"></i> Baca Sekarang</a>
        <?php elseif($tersedia): ?>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $b['id'] ?>" class="btn-action-kat btn-pinjam"><i class="fas fa-eye"></i> Lihat &amp; Pinjam</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $b['id'] ?>" class="btn-action-kat btn-antre"><i class="fas fa-clock"></i> Antre</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-kat">
    <i class="fas fa-search"></i>
    <p>Tidak ada buku ditemukan.<br><a href="<?= BASE_URL ?>/mahasiswa/katalog.php">Reset semua filter</a></p>
  </div>
  <?php endif; ?>

</div>

<footer style="position:relative;z-index:1;text-align:center;padding:28px 24px;font-size:12px;color:var(--muted);font-weight:700;border-top:1.5px dashed rgba(58,97,134,0.15);margin-top:12px;">
  <i class="fas fa-cloud" style="color:var(--d2);"></i> <strong style="color:var(--d2);">CloudLibrary Mini</strong> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
function filterLive(){const q=document.getElementById('searchInput').value.toLowerCase().trim();document.querySelectorAll('#bookGrid .book-card-kat').forEach(c=>{c.style.display=(!q||c.dataset.judul.includes(q)||c.dataset.penulis.includes(q))?'':'none';});}
function submitFilter(){const t=document.getElementById('tipeSelect').value;const p=new URLSearchParams(window.location.search);if(t)p.set('tipe',t);else p.delete('tipe');window.location.search=p.toString();}
</script>
</body>
</html>