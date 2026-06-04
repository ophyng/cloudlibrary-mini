<?php
// ============================================
//  CloudLibrary Mini — Admin: Edit Buku
//  File   : admin/buku/edit.php
// ============================================
session_start();
require_once '../../includes/functions.php';
cekLoginAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$buku = $pdo->prepare("SELECT * FROM buku WHERE id = ?");
$buku->execute([$id]);
$buku = $buku->fetch();
if (!$buku) { header('Location: index.php'); exit; }

$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY tipe, nama")->fetchAll();
$pesan = $pesan_type = '';

$ebooks_dir = $_SERVER['DOCUMENT_ROOT'] . '/Web_Cloud_Computing/ebooks/';
if (!is_dir($ebooks_dir)) mkdir($ebooks_dir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul       = trim($_POST['judul']       ?? '');
    $penulis     = trim($_POST['penulis']     ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $genre       = trim($_POST['genre']       ?? '');
    $tipe        = $_POST['tipe'] === 'non-fiksi' ? 'non-fiksi' : 'fiksi';
    $deskripsi   = trim($_POST['deskripsi']   ?? '');
    $file_pdf    = $buku['file_pdf'];
    $tahun       = (int)($_POST['tahun'] ?? 0) ?: null;
    $bahasa      = trim($_POST['bahasa'] ?? 'Inggris');
    $stok        = max(0, (int)($_POST['stok'] ?? 1));
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    if (!empty($_FILES['file_pdf_upload']['name'])) {
        $upload = $_FILES['file_pdf_upload'];
        $ext    = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $pesan = "File harus berformat PDF."; $pesan_type = 'danger';
        } elseif ($upload['size'] > 50 * 1024 * 1024) {
            $pesan = "Ukuran file maksimal 50MB."; $pesan_type = 'danger';
        } else {
            $nama_file = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $upload['name']);
            if (move_uploaded_file($upload['tmp_name'], $ebooks_dir . $nama_file)) {
                $file_lama = $buku['file_pdf'];
                if ($file_lama && !str_starts_with($file_lama, 'http') && file_exists($ebooks_dir . $file_lama)) unlink($ebooks_dir . $file_lama);
                $file_pdf = $nama_file;
            } else {
                $pesan = "Gagal upload file."; $pesan_type = 'danger';
            }
        }
    } elseif (!empty(trim($_POST['file_pdf_manual'] ?? ''))) {
        $file_pdf = trim($_POST['file_pdf_manual']);
    }

    if (!$pesan) {
        if (!$judul || !$penulis || !$file_pdf || !$genre) {
            $pesan = "Judul, penulis, genre, dan file PDF wajib diisi."; $pesan_type = 'danger';
        } else {
            $pdo->prepare("UPDATE buku SET judul=?,penulis=?,kategori_id=?,genre=?,tipe=?,deskripsi=?,file_pdf=?,tahun=?,bahasa=?,stok=?,is_featured=? WHERE id=?")
                ->execute([$judul,$penulis,$kategori_id?:null,$genre,$tipe,$deskripsi,$file_pdf,$tahun,$bahasa,$stok,$is_featured,$id]);
            header('Location: index.php?msg=edit'); exit;
        }
    }

    $buku = array_merge($buku, ['judul'=>$judul,'penulis'=>$penulis,'kategori_id'=>$kategori_id,'genre'=>$genre,'tipe'=>$tipe,'deskripsi'=>$deskripsi,'file_pdf'=>$file_pdf,'tahun'=>$tahun,'bahasa'=>$bahasa,'stok'=>$stok]);
}

$title = "Edit Buku — Admin CloudLibrary Mini";
include '../../includes/navbar.php';
?>
<style>
body{
  background-color:#1e3a5f !important;
  background-image:url('library_bg.png') !important;
  background-size:cover !important;
  background-position:center center !important;
  background-attachment:fixed !important;
  background-repeat:no-repeat !important;
  min-height:100vh !important;
}
body::before{content:'';position:fixed;inset:0;z-index:0;background:rgba(5,15,35,0.62);pointer-events:none;}
.main-wrap,.container,main{background:transparent !important;}

:root{
  --card:rgba(255,255,255,0.10);
  --card-b:rgba(255,255,255,0.18);
  --text:#fff;
  --muted:rgba(255,255,255,0.55);
  --accent:#60a5fa;
  --accent2:#fbbf24;
  --success:#4ade80;
  --danger:#f87171;
  --sh:0 4px 22px rgba(0,0,0,0.25);
}

/* Back link */
.btn-back{
  display:inline-flex;align-items:center;gap:7px;
  font-size:13px;color:rgba(255,255,255,0.65);text-decoration:none;
  margin-bottom:22px;padding:8px 16px;border-radius:8px;
  background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.18);
  transition:all .2s;position:relative;z-index:1;
}
.btn-back:hover{background:rgba(255,255,255,0.18);color:#fff;}

/* Page header */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:12px;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:#f9c74f;}

/* Alert */
.alert{padding:13px 16px;border-radius:12px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-family:'Nunito',sans-serif;font-weight:700;position:relative;z-index:1;backdrop-filter:blur(8px);}
.alert-danger{background:rgba(248,113,113,0.15);border:1.5px solid rgba(248,113,113,0.30);color:#f87171;}
.alert-success{background:rgba(74,222,128,0.15);border:1.5px solid rgba(74,222,128,0.30);color:#4ade80;}

/* Form wrap */
.form-wrap{max-width:760px;margin:0 auto;position:relative;z-index:1;}

/* Form cards — glass */
.form-card{
  background:rgba(255,255,255,0.08);
  border:1px solid rgba(255,255,255,0.15);
  border-radius:16px;padding:24px 26px;margin-bottom:18px;
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  box-shadow:var(--sh);
}
.form-card h3{
  font-family:'Syne',sans-serif;font-size:14px;font-weight:900;
  color:#fff;margin-bottom:18px;
  display:flex;align-items:center;gap:8px;
  padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.10);
}
.form-card h3 i{color:#f9c74f;}

/* Form groups */
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:10px;font-weight:900;margin-bottom:7px;letter-spacing:.7px;text-transform:uppercase;color:rgba(255,255,255,0.55);}
.form-group input,.form-group select,.form-group textarea{
  width:100%;border-radius:8px;padding:10px 14px;font-size:13px;
  font-family:'Nunito',sans-serif;outline:none;
  background:rgba(255,255,255,0.10);
  border:1.5px solid rgba(255,255,255,0.18);
  color:#fff;transition:border-color .2s;
}
.form-group input::placeholder,.form-group textarea::placeholder{color:rgba(255,255,255,0.30);}
.form-group select option{background:#1e3a5f;color:#fff;}
.form-group textarea{border-radius:10px;resize:vertical;min-height:90px;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
  border-color:rgba(249,199,79,0.55);box-shadow:0 0 0 3px rgba(249,199,79,0.10);
}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:600px){.form-row{grid-template-columns:1fr;}}

/* Genre grid */
.genre-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-top:8px;}
@media(max-width:600px){.genre-grid{grid-template-columns:repeat(3,1fr);}}
.genre-option{
  display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:10px 6px;border-radius:10px;
  border:1.5px solid rgba(255,255,255,0.15);cursor:pointer;
  transition:all .2s;text-align:center;font-size:11px;
  font-weight:700;color:rgba(255,255,255,0.55);
  background:rgba(255,255,255,0.06);
}
.genre-option input{display:none;}
.genre-option .g-ico{font-size:18px;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.08);margin-bottom:2px;}
.genre-option:hover{border-color:rgba(249,199,79,0.50);color:#fff;background:rgba(249,199,79,0.08);}
.genre-option.selected{border-color:#f9c74f;background:rgba(249,199,79,0.15);color:#f9c74f;}

/* Tipe toggle */
.tipe-toggle{display:flex;gap:8px;}
.tipe-option{
  flex:1;padding:10px;border-radius:10px;
  border:1.5px solid rgba(255,255,255,0.15);
  cursor:pointer;text-align:center;font-size:13px;font-weight:700;
  color:rgba(255,255,255,0.55);transition:all .2s;
  background:rgba(255,255,255,0.06);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.tipe-option input{display:none;}
.tipe-option:hover{border-color:rgba(249,199,79,0.40);color:#fff;}
.tipe-option.selected-fiksi{border-color:#60a5fa;background:rgba(96,165,250,0.15);color:#93c5fd;}
.tipe-option.selected-nonfiksi{border-color:#fbbf24;background:rgba(251,191,36,0.15);color:#fde68a;}

/* Toggle switch */
.toggle-switch{display:flex;align-items:center;gap:12px;cursor:pointer;}
.toggle-switch input{display:none;}
.toggle-track{width:44px;height:24px;border-radius:12px;background:rgba(255,255,255,0.20);position:relative;transition:background .3s;}
.toggle-track::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .3s;}
.toggle-switch input:checked + .toggle-track{background:#f9c74f;}
.toggle-switch input:checked + .toggle-track::after{transform:translateX(20px);}

/* Upload area */
.upload-area{
  border:2px dashed rgba(255,255,255,0.20);border-radius:12px;
  padding:28px 20px;text-align:center;cursor:pointer;
  transition:all .2s;position:relative;
  background:rgba(255,255,255,0.04);
}
.upload-area:hover,.upload-area.dragover{border-color:#f9c74f;background:rgba(249,199,79,0.06);}
.upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.upload-area .upload-icon{font-size:28px;color:#f9c74f;margin-bottom:8px;}
.upload-area p{font-size:13px;color:rgba(255,255,255,0.50);margin:0;}
.upload-area strong{color:#f9c74f;}

.file-current{
  display:flex;align-items:center;gap:10px;
  background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.14);
  border-radius:10px;padding:12px 16px;margin-bottom:12px;font-size:13px;color:#f87171;
}
.file-current span{flex:1;word-break:break-all;color:rgba(255,255,255,0.75);}

.upload-preview{
  display:none;align-items:center;gap:10px;
  background:rgba(74,222,128,0.10);border:1px solid rgba(74,222,128,0.25);
  border-radius:10px;padding:12px 16px;margin-top:12px;font-size:13px;color:#4ade80;
}
.upload-preview.show{display:flex;}

.tab-upload{display:flex;gap:8px;margin-bottom:16px;}
.tab-btn{
  padding:8px 16px;border-radius:8px;
  border:1px solid rgba(255,255,255,0.15);
  background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.55);
  font-size:12px;cursor:pointer;transition:all .2s;font-weight:700;font-family:'Nunito',sans-serif;
}
.tab-btn:hover{background:rgba(255,255,255,0.12);color:#fff;}
.tab-btn.active{background:rgba(249,199,79,0.15);border-color:#f9c74f;color:#f9c74f;}

/* Submit buttons */
.btn-submit{
  display:inline-flex;align-items:center;gap:8px;
  padding:12px 28px;border-radius:8px;
  background:#f9c74f;color:#0f172a;
  font-size:14px;font-weight:900;border:none;cursor:pointer;
  font-family:'Nunito',sans-serif;
  box-shadow:0 4px 16px rgba(249,199,79,0.35);
  transition:all .2s;
}
.btn-submit:hover{background:#d4a017;transform:translateY(-2px);}
.btn-cancel{
  display:inline-flex;align-items:center;gap:8px;
  padding:12px 22px;border-radius:8px;
  background:rgba(255,255,255,0.10);color:rgba(255,255,255,0.70);
  font-size:13px;font-weight:800;border:1px solid rgba(255,255,255,0.18);
  text-decoration:none;font-family:'Nunito',sans-serif;transition:all .2s;
}
.btn-cancel:hover{background:rgba(255,255,255,0.18);color:#fff;}

/* Genre FA icons map */
</style>

<!-- BACK BUTTON -->
<a href="index.php" class="btn-back">
  <i class="fas fa-arrow-left"></i> Kembali ke Daftar Buku
</a>

<div class="form-wrap">
  <div class="page-header">
    <h2><i class="fas fa-edit"></i> Edit Buku</h2>
    <span style="font-size:12px;color:rgba(255,255,255,0.45);background:rgba(255,255,255,0.08);padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);">ID #<?= $id ?></span>
  </div>

  <?php if ($pesan): ?>
    <div class="alert alert-<?= $pesan_type ?>"><i class="fas fa-exclamation-triangle"></i> <?= $pesan ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">

    <!-- INFO UTAMA -->
    <div class="form-card">
      <h3><i class="fas fa-book-open"></i> Informasi Utama</h3>
      <div class="form-group">
        <label>Judul Buku</label>
        <input type="text" name="judul" value="<?= e($buku['judul']) ?>" required placeholder="Masukkan judul buku...">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Penulis</label>
          <input type="text" name="penulis" value="<?= e($buku['penulis']) ?>" required placeholder="Nama penulis...">
        </div>
        <div class="form-group">
          <label>Kategori</label>
          <select name="kategori_id">
            <option value="">-- Pilih Kategori --</option>
            <?php foreach ($kategori_list as $k): ?>
              <option value="<?= $k['id'] ?>" <?= $buku['kategori_id']==$k['id']?'selected':'' ?>>
                <?= e($k['nama']) ?> (<?= $k['tipe'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Deskripsi</label>
        <textarea name="deskripsi" placeholder="Deskripsi singkat buku..."><?= e($buku['deskripsi']) ?></textarea>
      </div>
    </div>

    <!-- TIPE & GENRE -->
    <div class="form-card">
      <h3><i class="fas fa-tags"></i> Tipe & Genre</h3>
      <div class="form-group">
        <label>Tipe</label>
        <div class="tipe-toggle">
          <label class="tipe-option <?= $buku['tipe']==='fiksi'?'selected-fiksi':'' ?>" id="labelFiksi">
            <input type="radio" name="tipe" value="fiksi" <?= $buku['tipe']==='fiksi'?'checked':'' ?> onchange="updateTipe('fiksi')">
            <i class="fas fa-book" style="color:#93c5fd;"></i> Fiksi
          </label>
          <label class="tipe-option <?= $buku['tipe']==='non-fiksi'?'selected-nonfiksi':'' ?>" id="labelNonFiksi">
            <input type="radio" name="tipe" value="non-fiksi" <?= $buku['tipe']==='non-fiksi'?'checked':'' ?> onchange="updateTipe('non-fiksi')">
            <i class="fas fa-microscope" style="color:#fde68a;"></i> Non-Fiksi
          </label>
        </div>
      </div>
      <div class="form-group">
        <label>Genre</label>
        <input type="hidden" name="genre" id="genreInput" value="<?= e($buku['genre']) ?>">
        <div class="genre-grid">
          <?php
          $genres = [
            'Novel'   =>['fa-book',       '#60a5fa'],
            'Cerpen'  =>['fa-file-alt',   '#a78bfa'],
            'Fantasi' =>['fa-hat-wizard', '#34d399'],
            'Romance' =>['fa-heart',      '#f472b6'],
            'Horror'  =>['fa-ghost',      '#f87171'],
            'Misteri' =>['fa-search',     '#fbbf24'],
            'Sci-Fi'  =>['fa-rocket',     '#38bdf8'],
            'Filsafat'=>['fa-landmark',   '#94a3b8'],
            'Sains'   =>['fa-flask',      '#4ade80'],
            'Biografi'=>['fa-user',       '#fb923c'],
          ];
          foreach ($genres as $g => [$icon, $color]):
          ?>
          <div class="genre-option <?= $buku['genre']===$g?'selected':'' ?>"
               onclick="selectGenre('<?= $g ?>', this)">
            <div class="g-ico"><i class="fas <?= $icon ?>" style="color:<?= $buku['genre']===$g?'#f9c74f':$color ?>;font-size:15px;"></i></div>
            <span><?= $g ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- FILE PDF -->
    <div class="form-card">
      <h3><i class="fas fa-file-pdf"></i> File PDF Buku</h3>
      <div class="tab-upload">
        <button type="button" class="tab-btn active" onclick="switchTab('upload')" id="tabUpload">
          <i class="fas fa-upload"></i> Upload PDF Baru
        </button>
        <button type="button" class="tab-btn" onclick="switchTab('manual')" id="tabManual">
          <i class="fas fa-link"></i> URL / Nama File Manual
        </button>
      </div>

      <?php if ($buku['file_pdf']): ?>
      <div class="file-current">
        <i class="fas fa-file-pdf"></i>
        <span><?= e($buku['file_pdf']) ?></span>
        <span style="font-size:11px;color:rgba(255,255,255,0.40);">File saat ini</span>
      </div>
      <?php endif; ?>

      <div id="panelUpload">
        <div class="upload-area" id="uploadArea">
          <input type="file" name="file_pdf_upload" id="filePdfUpload" accept=".pdf" onchange="previewFile(this)">
          <div class="upload-icon"><i class="fas fa-cloud-upload-alt" style="font-size:32px;"></i></div>
          <p style="margin-top:8px;"><strong>Klik atau drag & drop</strong> file PDF di sini</p>
          <p style="margin-top:4px;font-size:11px;">Maksimal 50MB &middot; Format PDF</p>
        </div>
        <div class="upload-preview" id="uploadPreview">
          <i class="fas fa-check-circle"></i>
          <span id="uploadFileName">-</span>
          <span id="uploadFileSize" style="color:rgba(255,255,255,0.45);margin-left:auto;font-size:11px;"></span>
        </div>
        <p style="font-size:11px;color:rgba(255,255,255,0.35);margin-top:8px;">
          <i class="fas fa-info-circle"></i>
          File disimpan di folder <code style="color:#f9c74f;">/ebooks/</code>. Kosongkan jika tidak ingin mengganti.
        </p>
      </div>

      <div id="panelManual" style="display:none;">
        <div class="form-group" style="margin-bottom:0;">
          <label>URL atau Nama File PDF</label>
          <input type="text" name="file_pdf_manual" value="<?= e($buku['file_pdf']) ?>"
                 placeholder="contoh: buku.pdf atau https://...">
        </div>
      </div>
    </div>

    <!-- DETAIL -->
    <div class="form-card">
      <h3><i class="fas fa-sliders-h"></i> Detail Lainnya</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Tahun Terbit</label>
          <input type="number" name="tahun" value="<?= $buku['tahun'] ?>" min="1000" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
        </div>
        <div class="form-group">
          <label>Bahasa</label>
          <select name="bahasa">
            <?php foreach (['Inggris','Indonesia','Spanyol','Prancis','Jerman','Lainnya'] as $b): ?>
              <option value="<?= $b ?>" <?= $buku['bahasa']===$b?'selected':'' ?>><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Stok</label>
          <input type="number" name="stok" value="<?= $buku['stok'] ?>" min="0" max="999">
        </div>
        <div class="form-group">
          <label>Tampilkan sebagai Unggulan</label>
          <label class="toggle-switch" style="margin-top:10px;">
            <input type="checkbox" name="is_featured" <?= $buku['is_featured']?'checked':'' ?>>
            <div class="toggle-track"></div>
            <span style="font-size:13px;color:rgba(255,255,255,0.60);">
              <?= $buku['is_featured'] ? 'Ya — tampil di beranda' : 'Tidak' ?>
            </span>
          </label>
        </div>
      </div>
    </div>

    <!-- SUBMIT -->
    <div style="display:flex;gap:10px;position:relative;z-index:1;margin-bottom:30px;">
      <button type="submit" class="btn-submit">
        <i class="fas fa-save"></i> Simpan Perubahan
      </button>
      <a href="index.php" class="btn-cancel">
        <i class="fas fa-times"></i> Batal
      </a>
    </div>

  </form>
</div>

</div>
<footer class="footer" style="position:relative;z-index:1;background:rgba(0,0,0,0.35);border-top:1px solid rgba(255,255,255,0.10);color:rgba(255,255,255,0.50);">
  <p><i class="fas fa-cloud" style="color:#60a5fa;"></i> <span style="color:#fff;">CloudLibrary Mini</span> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?></p>
</footer>

<script>
function selectGenre(genre, el) {
  document.querySelectorAll('.genre-option').forEach(e => {
    e.classList.remove('selected');
    const ico = e.querySelector('i');
    if(ico) ico.style.color = ico.getAttribute('data-color') || '';
  });
  el.classList.add('selected');
  document.getElementById('genreInput').value = genre;
}

function updateTipe(tipe) {
  document.getElementById('labelFiksi').className    = 'tipe-option' + (tipe==='fiksi'     ? ' selected-fiksi'    : '');
  document.getElementById('labelNonFiksi').className = 'tipe-option' + (tipe==='non-fiksi' ? ' selected-nonfiksi' : '');
}

function switchTab(tab) {
  document.getElementById('panelUpload').style.display = tab === 'upload' ? 'block' : 'none';
  document.getElementById('panelManual').style.display = tab === 'manual' ? 'block' : 'none';
  document.getElementById('tabUpload').classList.toggle('active', tab === 'upload');
  document.getElementById('tabManual').classList.toggle('active', tab === 'manual');
}

function previewFile(input) {
  const file = input.files[0];
  if (!file) return;
  document.getElementById('uploadFileName').textContent = file.name;
  document.getElementById('uploadFileSize').textContent = (file.size/1024/1024).toFixed(2) + ' MB';
  document.getElementById('uploadPreview').classList.add('show');
}

const area = document.getElementById('uploadArea');
area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('dragover'); });
area.addEventListener('dragleave', () => area.classList.remove('dragover'));
area.addEventListener('drop', e => {
  e.preventDefault(); area.classList.remove('dragover');
  const input = document.getElementById('filePdfUpload');
  input.files = e.dataTransfer.files;
  previewFile(input);
});
</script>
</body>
</html>