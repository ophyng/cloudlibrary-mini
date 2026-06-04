<?php
// ============================================
//  CloudLibrary Mini — Baca Buku (PDF Viewer)
//  File   : mahasiswa/baca.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();
updateStatusPeminjaman($pdo);

$buku_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$buku_id) { header('Location: ' . BASE_URL . '/mahasiswa/katalog.php'); exit; }

// FIX: tambah b.cover
$pinjam = $pdo->prepare("
    SELECT p.*, b.judul, b.penulis, b.file_pdf, b.genre, b.cover
    FROM peminjaman p
    JOIN buku b ON p.buku_id = b.id
    WHERE p.user_id = ? AND p.buku_id = ? AND p.status IN ('aktif','hampir_habis')
    LIMIT 1
");
$pinjam->execute([$user_id, $buku_id]);
$pinjam = $pinjam->fetch();

if (!$pinjam) {
    header('Location: ' . BASE_URL . '/mahasiswa/detail_buku.php?id=' . $buku_id);
    exit;
}

$file_pdf = $pinjam['file_pdf'];
if (str_starts_with($file_pdf, 'http')) {
    $pdf_url     = $file_pdf;
    $is_external = true;
} else {
    $pdf_url     = BASE_URL . '/ebooks/' . $file_pdf;
    $is_external = false;
}

$bookmark = $pdo->prepare("SELECT halaman_terakhir FROM bookmark WHERE user_id = ? AND buku_id = ?");
$bookmark->execute([$user_id, $buku_id]);
$bookmark = $bookmark->fetch();
$halaman_terakhir = $bookmark['halaman_terakhir'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['halaman'])) {
    $hal = max(1, (int)$_POST['halaman']);
    $cek = $pdo->prepare("SELECT id FROM bookmark WHERE user_id = ? AND buku_id = ?");
    $cek->execute([$user_id, $buku_id]);
    if ($cek->fetch()) {
        $pdo->prepare("UPDATE bookmark SET halaman_terakhir = ?, updated_at = NOW() WHERE user_id = ? AND buku_id = ?")->execute([$hal, $user_id, $buku_id]);
    } else {
        $pdo->prepare("INSERT INTO bookmark (user_id, buku_id, halaman_terakhir) VALUES (?,?,?)")->execute([$user_id, $buku_id, $hal]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

$today = date('Y-m-d');
$cek_poin = $pdo->prepare("SELECT id FROM notifikasi WHERE user_id = ? AND pesan LIKE ? AND DATE(created_at) = ?");
$cek_poin->execute([$user_id, "%poin membaca%buku_id:$buku_id%", $today]);
if (!$cek_poin->fetch()) {
    tambahPoin($pdo, $user_id, 2);
    kirimNotifikasi($pdo, $user_id, "+2 poin membaca \"$pinjam[judul]\" · buku_id:$buku_id", 'info');
}

$sisa = sisaHari($pinjam['tanggal_expired']);

// FA icons (ganti semua emoji)
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
$title = "Baca: " . e($pinjam['judul']) . " — CloudLibrary Mini";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= $title ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
:root{--bg:#0a0c10;--surface:#111318;--card:#16191f;--border:#1e2330;--accent:#4fc3f7;--accent2:#e8a838;--text:#e8eaf0;--muted:#6b7280;--danger:#ef4444;--warning:#f59e0b;--success:#22c55e;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);height:100vh;display:flex;flex-direction:column;overflow:hidden;}
body.light{--bg:#eef0f3;--surface:#fff;--card:#fff;--border:#dde1e7;--text:#1a1a2e;--muted:#6b7280;}

/* TOPBAR */
.topbar{height:52px;background:rgba(10,12,16,.97);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 14px;gap:8px;flex-shrink:0;z-index:100;}
body.light .topbar{background:rgba(255,255,255,.97);}
.topbar-back{display:flex;align-items:center;gap:5px;color:var(--muted);text-decoration:none;font-size:12px;font-weight:600;padding:5px 10px;border-radius:7px;border:1px solid var(--border);transition:all .2s;flex-shrink:0;}
.topbar-back:hover{color:var(--accent);border-color:var(--accent);}
.topbar-title{flex:1;min-width:0;}
.topbar-title h3{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.topbar-title p{font-size:11px;color:var(--muted);}
.topbar-right{display:flex;align-items:center;gap:5px;flex-shrink:0;}
.sisa-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:5px;display:flex;align-items:center;gap:4px;}
.sisa-ok{background:rgba(34,197,94,.12);color:var(--success);}
.sisa-warn{background:rgba(245,158,11,.12);color:var(--warning);}
.sisa-crit{background:rgba(239,68,68,.12);color:var(--danger);}
.tbtn{width:32px;height:32px;border-radius:7px;background:var(--card);border:1px solid var(--border);color:var(--muted);font-size:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;text-decoration:none;}
.tbtn:hover,.tbtn.on{border-color:var(--accent);color:var(--accent);background:rgba(79,195,247,.08);}

/* LAYOUT */
.reader-layout{display:flex;flex:1;overflow:hidden;}

/* SIDEBAR */
.sidebar{width:230px;flex-shrink:0;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;transition:width .3s;}
.sidebar.hide{width:0;}
.sb-head{padding:10px 12px;border-bottom:1px solid var(--border);font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;display:flex;align-items:center;gap:6px;}
.sb-body{flex:1;overflow-y:auto;padding:10px;}

/* Cover sidebar — foto atau icon */
.sb-cover{width:100%;height:130px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;position:relative;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.50);}
.sb-cover img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1;}
.sb-cover i{font-size:40px;color:#fff;position:relative;z-index:1;}
.sb-cover::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.07) 0%,transparent 55%,rgba(0,0,0,.25) 100%);z-index:2;pointer-events:none;}
/* Spine buku */
.sb-cover::before{content:'';position:absolute;left:0;top:0;bottom:0;width:6px;background:rgba(0,0,0,.28);z-index:3;}

.sb-info{font-size:11px;color:var(--muted);line-height:1.85;}
.sb-info strong{color:var(--text);font-size:12px;font-weight:700;}
.sb-info .sb-row{display:flex;align-items:center;gap:5px;margin-bottom:1px;}
.sb-info .sb-row i{width:13px;text-align:center;font-size:10px;}
.sb-div{border:none;border-top:1px solid var(--border);margin:8px 0;}
.sb-big{font-family:'Playfair Display',serif;font-size:28px;font-weight:900;line-height:1;}
.sb-link{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--muted);text-decoration:none;padding:6px 7px;border-radius:6px;transition:all .2s;}
.sb-link:hover{background:rgba(79,195,247,.08);color:var(--accent);}
.sb-nav{padding:10px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:6px;}
.sb-nav label{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;display:flex;align-items:center;gap:4px;}
.sb-nav input[type=number]{width:100%;background:var(--card);border:1px solid var(--border);border-radius:6px;padding:6px 8px;color:var(--text);font-size:13px;text-align:center;outline:none;font-family:'DM Sans',sans-serif;}
.sb-nav input:focus{border-color:var(--accent);}
.nav-btns{display:flex;gap:5px;}
.nav-btns button{flex:1;padding:6px;background:var(--card);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:11px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:4px;}
.nav-btns button:hover{border-color:var(--accent);color:var(--accent);}
.btn-save-bm{width:100%;padding:7px;background:var(--accent);color:#000;border:none;border-radius:6px;font-weight:800;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .2s;}
.btn-save-bm:hover{opacity:.85;}

/* VIEWER */
.viewer{flex:1;display:flex;flex-direction:column;overflow:hidden;}
.vtoolbar{padding:6px 12px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap;}
.vt-info{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;}
.vt-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;display:flex;align-items:center;gap:4px;}
.vt-local{background:rgba(34,197,94,.1);color:var(--success);}
.vt-ext{background:rgba(79,195,247,.1);color:var(--accent);}
.pdf-controls{display:flex;align-items:center;gap:6px;margin-left:auto;flex-wrap:wrap;}
.pdf-controls button{padding:4px 9px;background:var(--card);border:1px solid var(--border);border-radius:5px;color:var(--text);font-size:11px;cursor:pointer;transition:all .2s;}
.pdf-controls button:hover{border-color:var(--accent);color:var(--accent);}
.pdf-controls span{font-size:11px;color:var(--muted);min-width:70px;text-align:center;}
.zoom-val{font-size:11px;color:var(--accent);font-weight:700;min-width:35px;text-align:center;}
.canvas-wrap{flex:1;overflow:auto;display:flex;flex-direction:column;align-items:center;padding:16px;gap:12px;background:var(--bg);}
body.light .canvas-wrap{background:#dde1e7;}
#pdfCanvas{box-shadow:0 6px 24px rgba(0,0,0,.5);border-radius:3px;max-width:100%;}
.iframe-wrap{flex:1;overflow:hidden;}
.iframe-wrap iframe{width:100%;height:100%;border:none;}
.pdf-loading{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;color:var(--muted);font-size:13px;}
.spinner{width:36px;height:36px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.toast{position:fixed;bottom:18px;right:18px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:9px 14px;font-size:12px;display:flex;align-items:center;gap:6px;box-shadow:0 6px 20px rgba(0,0,0,.4);z-index:999;opacity:0;transform:translateY(6px);transition:all .3s;pointer-events:none;}
.toast.show{opacity:1;transform:translateY(0);}
.toast i{color:var(--success);}
@media(max-width:768px){.sidebar{display:none;}.topbar-title p{display:none;}}
</style>
</head>
<body>

<div class="topbar">
  <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $buku_id ?>" class="topbar-back">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
  <div class="topbar-title">
    <h3><?= e($pinjam['judul']) ?></h3>
    <p><?= e($pinjam['penulis']) ?></p>
  </div>
  <div class="topbar-right">
    <span class="sisa-badge <?= $sisa<=1?'sisa-crit':($sisa<=3?'sisa-warn':'sisa-ok') ?>">
      <i class="fas fa-clock"></i> <?= max($sisa,0) ?> hari
    </span>
    <button class="tbtn on" id="btnSidebar" title="Panel Info" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <button class="tbtn" id="btnDark" title="Dark/Light" onclick="toggleDark()"><i class="fas fa-moon"></i></button>
    <button class="tbtn" title="Simpan Bookmark" onclick="saveCurrentPage()"><i class="fas fa-bookmark"></i></button>
    <button class="tbtn" title="Layar Penuh" onclick="toggleFs()"><i class="fas fa-expand" id="iconFs"></i></button>
  </div>
</div>

<div class="reader-layout">
  <div class="sidebar" id="sidebar">
    <div class="sb-head"><i class="fas fa-book-open"></i> Info Buku</div>
    <div class="sb-body">

      <!-- FIX: cover foto kalau ada, FA icon kalau tidak -->
      <div class="sb-cover" style="background:linear-gradient(135deg,<?= $gw['bg'] ?>,<?= $gw['bg'] ?>bb);">
        <?php if(!empty($pinjam['cover'])): ?>
          <img src="<?= BASE_URL ?>/uploads/covers/<?= e($pinjam['cover']) ?>" alt="">
        <?php else: ?>
          <i class="fas <?= $gw['icon'] ?>"></i>
        <?php endif; ?>
      </div>

      <div class="sb-info">
        <strong><?= e($pinjam['judul']) ?></strong><br>
        <span style="color:var(--muted);font-size:11px;"><?= e($pinjam['penulis']) ?></span>
        <div style="height:8px;"></div>
        <div class="sb-row"><i class="fas fa-tag" style="color:var(--accent);"></i><?= e($pinjam['genre']) ?></div>
        <div class="sb-row"><i class="fas fa-calendar-plus" style="color:var(--accent);"></i>Pinjam: <?= formatTanggal($pinjam['tanggal_pinjam']) ?></div>
        <div class="sb-row"><i class="fas fa-hourglass-half" style="color:var(--warning);"></i>Expired: <?= formatTanggal($pinjam['tanggal_expired']) ?></div>
      </div>

      <hr class="sb-div">
      <div style="font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px;">Sisa Waktu</div>
      <div class="sb-big" style="color:<?= $sisa<=1?'var(--danger)':($sisa<=3?'var(--warning)':'var(--accent)') ?>;">
        <?= max($sisa,0) ?> <span style="font-size:12px;font-weight:400;color:var(--muted);">hari</span>
      </div>
      <hr class="sb-div">
      <div style="font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px;">Terakhir Dibaca</div>
      <div style="font-size:20px;font-weight:700;color:var(--accent);font-family:'Playfair Display',serif;">Hal. <span id="pageDisplay"><?= $halaman_terakhir ?></span></div>
      <hr class="sb-div">
      <a href="<?= BASE_URL ?>/mahasiswa/detail_buku.php?id=<?= $buku_id ?>" class="sb-link"><i class="fas fa-info-circle"></i> Detail Buku</a>
      <a href="<?= BASE_URL ?>/mahasiswa/perpanjang.php?id=<?= $pinjam['id'] ?>" class="sb-link"><i class="fas fa-redo"></i> Perpanjang Pinjaman</a>
    </div>
    <div class="sb-nav">
      <label><i class="fas fa-bookmark"></i> Catat Halaman</label>
      <input type="number" id="pageInput" value="<?= $halaman_terakhir ?>" min="1">
      <div class="nav-btns">
        <button onclick="goPrev()"><i class="fas fa-chevron-left"></i> Prev</button>
        <button onclick="goNext()">Next <i class="fas fa-chevron-right"></i></button>
      </div>
      <button class="btn-save-bm" onclick="saveCurrentPage()"><i class="fas fa-bookmark"></i> Simpan Bookmark</button>
    </div>
  </div>

  <div class="viewer">
    <div class="vtoolbar">
      <div class="vt-info">
        <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
        <?= e($pinjam['judul']) ?>
        <?php if ($is_external): ?>
          <span class="vt-badge vt-ext"><i class="fas fa-globe"></i> Eksternal</span>
        <?php else: ?>
          <span class="vt-badge vt-local"><i class="fas fa-folder"></i> File Lokal</span>
        <?php endif; ?>
      </div>
      <?php if (!$is_external): ?>
      <div class="pdf-controls" id="pdfControls" style="display:none;">
        <button onclick="prevPage()"><i class="fas fa-chevron-left"></i></button>
        <span id="pageInfo">Hal 1/1</span>
        <button onclick="nextPage()"><i class="fas fa-chevron-right"></i></button>
        <div style="width:1px;height:18px;background:var(--border);"></div>
        <button onclick="zoomOut()"><i class="fas fa-search-minus"></i></button>
        <span class="zoom-val" id="zoomVal">100%</span>
        <button onclick="zoomIn()"><i class="fas fa-search-plus"></i></button>
        <button onclick="rotatePdf()" title="Putar"><i class="fas fa-sync"></i></button>
      </div>
      <?php endif; ?>
      <?php if ($pinjam['status']==='hampir_habis'): ?>
        <span style="margin-left:auto;font-size:11px;color:var(--warning);background:rgba(245,158,11,.1);padding:3px 8px;border-radius:5px;display:flex;align-items:center;gap:5px;">
          <i class="fas fa-exclamation-triangle"></i> Akses berakhir <?= formatTanggal($pinjam['tanggal_expired']) ?>
        </span>
      <?php endif; ?>
    </div>

    <?php if ($is_external): ?>
    <div class="iframe-wrap">
      <iframe src="<?= e($pdf_url) ?>" allowfullscreen></iframe>
    </div>
    <?php else: ?>
    <div class="pdf-loading" id="pdfLoading">
      <div class="spinner"></div>
      <span>Memuat PDF...</span>
    </div>
    <div class="canvas-wrap" id="canvasWrap" style="display:none;">
      <canvas id="pdfCanvas"></canvas>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="toast" id="toast"><i class="fas fa-bookmark"></i><span id="toastMsg">Tersimpan!</span></div>

<?php if (!$is_external): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const PDF_URL='<?= e($pdf_url) ?>';
let pdfDoc=null,currentPage=<?= $halaman_terakhir ?>,totalPages=0,zoom=1.5,rot=0,task=null;
const canvas=document.getElementById('pdfCanvas'),ctx=canvas.getContext('2d');
pdfjsLib.getDocument(PDF_URL).promise.then(pdf=>{
  pdfDoc=pdf; totalPages=pdf.numPages;
  document.getElementById('pdfLoading').style.display='none';
  document.getElementById('canvasWrap').style.display='flex';
  document.getElementById('pdfControls').style.display='flex';
  renderPage(currentPage);
}).catch(()=>{
  document.getElementById('pdfLoading').innerHTML=
    '<i class="fas fa-exclamation-triangle" style="font-size:28px;color:var(--danger);"></i>'+
    '<span>Gagal memuat PDF</span>'+
    '<a href="'+PDF_URL+'" target="_blank" style="color:var(--accent);font-size:12px;">Buka di tab baru</a>';
});
function renderPage(n){
  if(!pdfDoc)return;
  if(task)task.cancel();
  pdfDoc.getPage(n).then(page=>{
    const vp=page.getViewport({scale:zoom,rotation:rot});
    canvas.width=vp.width; canvas.height=vp.height;
    task=page.render({canvasContext:ctx,viewport:vp});
    task.promise.then(()=>{
      currentPage=n;
      document.getElementById('pageInfo').textContent='Hal '+n+' / '+totalPages;
      document.getElementById('pageInput').value=n;
      document.getElementById('pageDisplay').textContent=n;
      task=null;
    }).catch(()=>{});
  });
}
function prevPage(){if(currentPage>1)renderPage(currentPage-1);}
function nextPage(){if(currentPage<totalPages)renderPage(currentPage+1);}
function zoomIn(){zoom=Math.min(zoom+0.25,4);document.getElementById('zoomVal').textContent=Math.round(zoom/1.5*100)+'%';renderPage(currentPage);}
function zoomOut(){zoom=Math.max(zoom-0.25,0.5);document.getElementById('zoomVal').textContent=Math.round(zoom/1.5*100)+'%';renderPage(currentPage);}
function rotatePdf(){rot=(rot+90)%360;renderPage(currentPage);}
function goPrev(){prevPage();}
function goNext(){nextPage();}
document.addEventListener('keydown',e=>{
  if(e.key==='ArrowRight'||e.key==='ArrowDown')nextPage();
  if(e.key==='ArrowLeft'||e.key==='ArrowUp')prevPage();
  if(e.key==='+')zoomIn(); if(e.key==='-')zoomOut();
});
</script>
<?php else: ?>
<script>
let currentPage=<?= $halaman_terakhir ?>;
function goPrev(){currentPage=Math.max(1,currentPage-1);document.getElementById('pageInput').value=currentPage;document.getElementById('pageDisplay').textContent=currentPage;}
function goNext(){currentPage++;document.getElementById('pageInput').value=currentPage;document.getElementById('pageDisplay').textContent=currentPage;}
</script>
<?php endif; ?>

<script>
let sbOn=true,darkOn=true;
function toggleSidebar(){sbOn=!sbOn;document.getElementById('sidebar').classList.toggle('hide',!sbOn);document.getElementById('btnSidebar').classList.toggle('on',sbOn);}
function toggleDark(){darkOn=!darkOn;document.body.classList.toggle('light',!darkOn);document.querySelector('#btnDark i').className=darkOn?'fas fa-moon':'fas fa-sun';}
function toggleFs(){if(!document.fullscreenElement){document.documentElement.requestFullscreen();document.getElementById('iconFs').className='fas fa-compress';}else{document.exitFullscreen();document.getElementById('iconFs').className='fas fa-expand';}}
function saveCurrentPage(){
  const p=parseInt(document.getElementById('pageInput').value)||currentPage;
  currentPage=p; document.getElementById('pageDisplay').textContent=p;
  fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'halaman='+p});
  showToast('Halaman '+p+' disimpan!');
}
function showToast(msg){const t=document.getElementById('toast');document.getElementById('toastMsg').textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500);}
setInterval(()=>{const p=parseInt(document.getElementById('pageInput').value)||currentPage;fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'halaman='+p});},120000);
</script>
</body>
</html>