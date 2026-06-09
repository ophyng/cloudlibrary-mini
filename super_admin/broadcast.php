<?php
// ============================================
//  CloudLibrary Mini — Super Admin: Broadcast
//  File   : super_admin/broadcast.php
// ============================================

cat << 'HTMLEOF' >> /home/claude/broadcast.php
<style>
body{
  font-family:'Nunito',sans-serif;min-height:100vh;overflow-x:hidden;position:relative;margin:0;
  background:#dce8f5;
  background-image:url('gambar_library.jpg');
  background-size:cover;background-position:center;background-attachment:fixed;background-repeat:no-repeat;
  color:#1a2744 !important;
}
body::before{content:'';position:fixed;inset:0;background:rgba(235,243,252,0.28);z-index:0;pointer-events:none;}

:root{
  --s1:#3a6186;--s2:#2c4f78;--s3:#5b8fb9;--gold:#d4a017;--gold2:#f9c74f;
  --card:rgba(255,255,255,0.78);--card-b:rgba(255,255,255,0.85);
  --text:#1a2744;--muted:#6b7a99;
  --success:#15803d;--warning:#c2410c;--danger:#b91c1c;
  --sh:0 4px 20px rgba(58,97,134,0.10);--sh-md:0 10px 36px rgba(58,97,134,0.16);
}

.btn-back{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:100px;background:rgba(255,255,255,0.78);border:2px solid rgba(255,255,255,0.85);color:var(--s1);font-size:12px;font-weight:800;text-decoration:none;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:all .2s;}
.btn-back:hover{background:rgba(255,255,255,0.90);transform:translateX(-2px);}

.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:10px;}
.page-header-left{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:var(--gold);}
.ph-sub{font-size:12px;font-weight:700;color:var(--muted);background:rgba(255,255,255,0.78);border:2px solid rgba(255,255,255,0.85);padding:6px 14px;border-radius:100px;backdrop-filter:blur(20px);display:flex;align-items:center;gap:6px;}

.alert-box{border-radius:14px;padding:13px 18px;margin-bottom:18px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;backdrop-filter:blur(20px);position:relative;z-index:1;}
.alert-success{background:rgba(21,128,61,0.08);border:1.5px solid rgba(21,128,61,0.24);color:var(--success);}
.alert-error{background:rgba(185,28,28,0.08);border:1.5px solid rgba(185,28,28,0.24);color:var(--danger);}

.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px;position:relative;z-index:1;}
@media(max-width:600px){.stat-row{grid-template-columns:1fr 1fr;}}
.stat-mini{background:var(--card);border:2px solid var(--card-b);border-radius:16px;padding:16px;text-align:center;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:transform .2s;position:relative;overflow:hidden;border-top:3px solid transparent;}
.stat-mini:hover{transform:translateY(-2px);}
.stat-mini .stat-ico{width:40px;height:40px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:8px;}
.stat-mini .num{font-family:'Syne',sans-serif;font-size:28px;font-weight:900;}
.stat-mini .lbl{font-size:11px;font-weight:700;color:var(--muted);margin-top:3px;}

.main-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;position:relative;z-index:1;}
@media(max-width:900px){.main-grid{grid-template-columns:1fr;}}

.broadcast-card{background:var(--card);border:2px solid var(--card-b);border-radius:22px;overflow:hidden;backdrop-filter:blur(20px);box-shadow:var(--sh);position:relative;}
.broadcast-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--s1),var(--s3),var(--gold));}
.bc-header{padding:20px 24px 16px;border-bottom:1.5px solid rgba(58,97,134,0.08);}
.bc-header h3{font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:8px;}
.bc-body{padding:20px 24px;}

.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:7px;}
.form-group input,.form-group textarea,.form-group select{
  width:100%;background:rgba(255,255,255,0.82);border:1.5px solid rgba(255,255,255,0.90);
  border-radius:12px;padding:11px 16px;font-size:13px;font-family:'Nunito',sans-serif;
  color:var(--text);outline:none;backdrop-filter:blur(10px);transition:border-color .2s,box-shadow .2s;
}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:rgba(58,97,134,0.35);box-shadow:0 0 0 3px rgba(58,97,134,0.08);}
.form-group textarea{resize:vertical;min-height:120px;border-radius:14px;}

.radio-group{display:flex;gap:8px;flex-wrap:wrap;}
.radio-pill{position:relative;}
.radio-pill input{position:absolute;opacity:0;pointer-events:none;}
.radio-pill label{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:100px;cursor:pointer;font-size:12px;font-weight:800;border:1.5px solid rgba(255,255,255,0.80);background:rgba(255,255,255,0.55);color:var(--muted);transition:all .2s;backdrop-filter:blur(8px);}
.radio-pill input:checked+label{background:rgba(58,97,134,0.10);color:var(--s1);border-color:rgba(58,97,134,0.30);}
.radio-pill.tipe-info input:checked+label{background:rgba(58,97,134,0.10);color:#3a6186;border-color:rgba(58,97,134,0.30);}
.radio-pill.tipe-success input:checked+label{background:rgba(21,128,61,0.10);color:#15803d;border-color:rgba(21,128,61,0.30);}
.radio-pill.tipe-warning input:checked+label{background:rgba(194,65,12,0.10);color:#c2410c;border-color:rgba(194,65,12,0.30);}
.radio-pill.tipe-danger input:checked+label{background:rgba(185,28,28,0.10);color:#b91c1c;border-color:rgba(185,28,28,0.30);}

.preview-card{border-radius:16px;padding:16px;margin-top:16px;border:1.5px dashed rgba(58,97,134,0.25);background:rgba(255,255,255,0.50);backdrop-filter:blur(8px);}
.preview-label{font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.preview-notif{border-radius:12px;padding:13px 16px;display:flex;align-items:flex-start;gap:10px;}
.pn-info{background:rgba(58,97,134,0.08);border:1px solid rgba(58,97,134,0.20);}
.pn-success{background:rgba(21,128,61,0.08);border:1px solid rgba(21,128,61,0.20);}
.pn-warning{background:rgba(194,65,12,0.08);border:1px solid rgba(194,65,12,0.20);}
.pn-danger{background:rgba(185,28,28,0.08);border:1px solid rgba(185,28,28,0.20);}
.pn-ico{font-size:18px;flex-shrink:0;margin-top:1px;width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;}
.pn-judul{font-family:'Syne',sans-serif;font-size:13px;font-weight:900;color:var(--text);margin-bottom:3px;}
.pn-pesan{font-size:11px;color:var(--muted);font-weight:600;line-height:1.6;}

.btn-send{width:100%;padding:13px;border-radius:100px;background:linear-gradient(135deg,var(--s1),var(--s3));color:#fff;font-size:14px;font-weight:900;border:none;cursor:pointer;font-family:'Nunito',sans-serif;box-shadow:0 6px 20px rgba(58,97,134,0.35);transition:all .2s;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:6px;}
.btn-send:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(58,97,134,0.45);}

.template-section{margin-bottom:18px;}
.template-title{font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.template-chips{display:flex;gap:8px;flex-wrap:wrap;}
.template-chip{padding:6px 13px;border-radius:100px;font-size:11px;font-weight:800;background:rgba(255,255,255,0.70);border:1.5px solid rgba(58,97,134,0.18);color:var(--s1);cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:5px;}
.template-chip:hover{background:rgba(58,97,134,0.08);transform:translateY(-1px);}

.log-card{background:var(--card);border:2px solid var(--card-b);border-radius:22px;overflow:hidden;backdrop-filter:blur(20px);box-shadow:var(--sh);}
.log-header{padding:20px 24px 16px;border-bottom:1.5px solid rgba(58,97,134,0.08);}
.log-header h3{font-family:'Syne',sans-serif;font-size:16px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:8px;}
.log-list{padding:8px 0;max-height:520px;overflow-y:auto;}

.log-item{padding:14px 24px;border-bottom:1px solid rgba(58,97,134,0.06);transition:background .15s;}
.log-item:last-child{border-bottom:none;}
.log-item:hover{background:rgba(58,97,134,0.025);}

.log-tipe-badge{display:inline-flex;align-items:center;gap:4px;font-size:9px;font-weight:900;padding:2px 9px;border-radius:100px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.lt-info{background:rgba(58,97,134,0.10);color:#3a6186;border:1px solid rgba(58,97,134,0.22);}
.lt-success{background:rgba(21,128,61,0.10);color:#15803d;border:1px solid rgba(21,128,61,0.22);}
.lt-warning{background:rgba(194,65,12,0.10);color:#c2410c;border:1px solid rgba(194,65,12,0.22);}
.lt-danger{background:rgba(185,28,28,0.10);color:#b91c1c;border:1px solid rgba(185,28,28,0.22);}

.log-judul{font-family:'Syne',sans-serif;font-size:13px;font-weight:900;color:var(--text);margin-bottom:4px;}
.log-pesan{font-size:11px;color:var(--muted);font-weight:600;line-height:1.5;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.log-meta{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;}
.log-meta-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.log-meta span{font-size:10px;color:var(--muted);font-weight:700;display:flex;align-items:center;gap:4px;}
.log-del{font-size:10px;font-weight:800;color:rgba(185,28,28,0.6);background:none;border:none;cursor:pointer;padding:3px 9px;border-radius:100px;transition:all .15s;display:inline-flex;align-items:center;gap:4px;}
.log-del:hover{background:rgba(185,28,28,0.08);color:#b91c1c;}

.log-empty{text-align:center;padding:52px 20px;color:var(--muted);}
.log-empty i{font-size:36px;display:block;margin-bottom:12px;opacity:.4;}
.log-empty p{font-size:13px;font-weight:700;}

footer.sa-foot{position:relative;z-index:1;text-align:center;padding:20px;font-size:12px;color:var(--muted);font-weight:700;background:transparent;border-top:1.5px dashed rgba(58,97,134,0.15);margin-top:24px;}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}
.fu3{animation:fadeUp .4s ease .20s both}.fu4{animation:fadeUp .4s ease .28s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <div class="page-header-left">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h2><i class="fas fa-bullhorn"></i> Broadcast Notifikasi</h2>
  </div>
  <div class="ph-sub"><i class="fas fa-paper-plane" style="font-size:10px;"></i> Kirim pesan ke semua pengguna</div>
</div>

<?php if ($success): ?>
<div class="alert-box alert-success fu1"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-box alert-error fu1"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stat-row fu2">
  <?php $sc=[
    ['fa-users',         '#3a6186','rgba(58,97,134,0.12)', $total_semua, 'Total Penerima'],
    ['fa-user-graduate', '#5b8fb9','rgba(91,143,185,0.12)',$total_mhs,   'Mahasiswa Aktif'],
    ['fa-shield-alt',    '#d4a017','rgba(212,160,23,0.12)',$total_admin, 'Admin Aktif'],
  ]; foreach($sc as [$ico,$col,$bgc,$num,$lbl]): ?>
  <div class="stat-mini" style="border-top-color:<?= $col ?>;">
    <div class="stat-ico" style="background:<?= $bgc ?>;color:<?= $col ?>;"><i class="fas <?= $ico ?>"></i></div>
    <div class="num" style="color:<?= $col ?>;"><?= $num ?></div>
    <div class="lbl"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- MAIN GRID -->
<div class="main-grid fu3">

  <div class="broadcast-card">
    <div class="bc-header">
      <h3><i class="fas fa-paper-plane"></i> Kirim Broadcast Baru</h3>
    </div>
    <div class="bc-body">

      <div class="template-section">
        <div class="template-title"><i class="fas fa-bolt" style="font-size:10px;"></i> Template Cepat</div>
        <div class="template-chips">
          <div class="template-chip" onclick="pakai('Pemeliharaan Sistem','Sistem akan dalam pemeliharaan pada malam ini pukul 23.00-01.00 WIB. Mohon selesaikan aktivitas membaca sebelum waktu tersebut.','warning')"><i class="fas fa-wrench"></i> Maintenance</div>
          <div class="template-chip" onclick="pakai('Buku Baru Tersedia!','Koleksi buku baru telah ditambahkan. Yuk cek katalog dan temukan bacaan favoritmu!','success')"><i class="fas fa-book"></i> Buku Baru</div>
          <div class="template-chip" onclick="pakai('Pengingat Peminjaman','Cek buku pinjaman kamu! Pastikan tidak ada yang hampir jatuh tempo ya.','info')"><i class="fas fa-clock"></i> Pengingat</div>
          <div class="template-chip" onclick="pakai('Selamat Membaca!','Semangat membaca hari ini! Kumpulkan poin dan raih badge eksklusif.','success')"><i class="fas fa-award"></i> Semangat</div>
        </div>
      </div>

      <form method="POST" id="broadcastForm">
        <input type="hidden" name="aksi" value="broadcast">

        <div class="form-group">
          <label>Judul Notifikasi</label>
          <input type="text" name="judul" id="inputJudul" placeholder="Contoh: Buku Baru Tersedia!" oninput="updatePreview()" required maxlength="100">
        </div>

        <div class="form-group">
          <label>Isi Pesan</label>
          <textarea name="pesan" id="inputPesan" placeholder="Tulis pesan broadcast di sini..." oninput="updatePreview()" required maxlength="500"></textarea>
          <div style="font-size:10px;color:var(--muted);font-weight:700;margin-top:4px;text-align:right;">
            <span id="charCount">0</span>/500 karakter
          </div>
        </div>

        <div class="form-group">
          <label>Kirim Ke</label>
          <div class="radio-group">
            <div class="radio-pill">
              <input type="radio" name="target" id="t-semua" value="semua" checked onchange="updatePreview()">
              <label for="t-semua"><i class="fas fa-users" style="font-size:10px;"></i> Semua (<?= $total_semua ?>)</label>
            </div>
            <div class="radio-pill">
              <input type="radio" name="target" id="t-mhs" value="mahasiswa" onchange="updatePreview()">
              <label for="t-mhs"><i class="fas fa-user-graduate" style="font-size:10px;"></i> Mahasiswa (<?= $total_mhs ?>)</label>
            </div>
            <div class="radio-pill">
              <input type="radio" name="target" id="t-admin" value="admin" onchange="updatePreview()">
              <label for="t-admin"><i class="fas fa-shield-alt" style="font-size:10px;"></i> Admin (<?= $total_admin ?>)</label>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Tipe Notifikasi</label>
          <div class="radio-group">
            <div class="radio-pill tipe-info">
              <input type="radio" name="tipe" id="tp-info" value="info" checked onchange="updatePreview()">
              <label for="tp-info"><i class="fas fa-info-circle" style="font-size:10px;"></i> Info</label>
            </div>
            <div class="radio-pill tipe-success">
              <input type="radio" name="tipe" id="tp-success" value="success" onchange="updatePreview()">
              <label for="tp-success"><i class="fas fa-check-circle" style="font-size:10px;"></i> Sukses</label>
            </div>
            <div class="radio-pill tipe-warning">
              <input type="radio" name="tipe" id="tp-warning" value="warning" onchange="updatePreview()">
              <label for="tp-warning"><i class="fas fa-exclamation-triangle" style="font-size:10px;"></i> Peringatan</label>
            </div>
            <div class="radio-pill tipe-danger">
              <input type="radio" name="tipe" id="tp-danger" value="danger" onchange="updatePreview()">
              <label for="tp-danger"><i class="fas fa-exclamation-circle" style="font-size:10px;"></i> Penting</label>
            </div>
          </div>
        </div>

        <div class="preview-card">
          <div class="preview-label"><i class="fas fa-eye" style="font-size:10px;"></i> Preview Notifikasi</div>
          <div class="preview-notif pn-info" id="previewBox">
            <div class="pn-ico" id="previewIco" style="background:rgba(58,97,134,0.12);color:#3a6186;"><i class="fas fa-info-circle"></i></div>
            <div>
              <div class="pn-judul" id="previewJudul">Judul notifikasi akan muncul di sini</div>
              <div class="pn-pesan" id="previewPesan">Isi pesan broadcast akan muncul di sini...</div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-send" onclick="return konfirmasi()">
          <i class="fas fa-paper-plane"></i> Kirim Broadcast
        </button>
      </form>
    </div>
  </div>

  <div class="log-card">
    <div class="log-header">
      <h3><i class="fas fa-history"></i> Riwayat Broadcast</h3>
    </div>
    <div class="log-list">
      <?php if($logs): foreach($logs as $log):
        $tipe_ico = match($log['tipe']) {
          'success' => 'fa-check-circle','warning' => 'fa-exclamation-triangle','danger' => 'fa-exclamation-circle', default => 'fa-info-circle'
        };
        $tipe_col = match($log['tipe']) {
          'success' => '#15803d','warning' => '#c2410c','danger' => '#b91c1c', default => '#3a6186'
        };
        $target_ico = match($log['target']) {
          'mahasiswa' => 'fa-user-graduate','admin' => 'fa-shield-alt', default => 'fa-users'
        };
        $target_lbl = match($log['target']) {
          'mahasiswa' => 'Mahasiswa','admin' => 'Admin', default => 'Semua'
        };
      ?>
      <div class="log-item">
        <div>
          <span class="log-tipe-badge lt-<?= $log['tipe'] ?>"><i class="fas <?= $tipe_ico ?>"></i> <?= $log['tipe'] ?></span>
        </div>
        <div class="log-judul"><?= e($log['judul']) ?></div>
        <div class="log-pesan"><?= e($log['pesan']) ?></div>
        <div class="log-meta">
          <div class="log-meta-left">
            <span><i class="fas fa-calendar-alt" style="font-size:9px;"></i> <?= date('d M Y, H:i', strtotime($log['created_at'])) ?></span>
            <span><i class="fas <?= $target_ico ?>" style="font-size:9px;"></i> <?= $target_lbl ?></span>
            <span><i class="fas fa-users" style="font-size:9px;"></i> <?= $log['jumlah_penerima'] ?> penerima</span>
          </div>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="aksi" value="hapus_log">
            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
            <button type="submit" class="log-del" onclick="return confirm('Hapus log ini?')"><i class="fas fa-trash-alt"></i> Hapus</button>
          </form>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="log-empty">
        <i class="fas fa-bullhorn"></i>
        <p>Belum ada riwayat broadcast.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div style="height:40px;"></div>

<footer class="sa-foot">
  <i class="fas fa-cloud" style="color:var(--s1);margin-right:5px;"></i>
  <strong style="color:var(--s1);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(58,97,134,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
const tipeConfig = {
  info:    { ico:'fa-info-circle',           col:'#3a6186', bg:'rgba(58,97,134,0.12)',  cls:'pn-info' },
  success: { ico:'fa-check-circle',          col:'#15803d', bg:'rgba(21,128,61,0.12)',  cls:'pn-success' },
  warning: { ico:'fa-exclamation-triangle',  col:'#c2410c', bg:'rgba(194,65,12,0.12)',  cls:'pn-warning' },
  danger:  { ico:'fa-exclamation-circle',    col:'#b91c1c', bg:'rgba(185,28,28,0.12)',  cls:'pn-danger' },
};

function updatePreview() {
  const judul = document.getElementById('inputJudul').value || 'Judul notifikasi akan muncul di sini';
  const pesan = document.getElementById('inputPesan').value || 'Isi pesan broadcast akan muncul di sini...';
  const tipe  = document.querySelector('input[name="tipe"]:checked')?.value || 'info';
  const cfg   = tipeConfig[tipe];

  const box = document.getElementById('previewBox');
  box.className = 'preview-notif ' + cfg.cls;
  const icoEl = document.getElementById('previewIco');
  icoEl.style.background = cfg.bg;
  icoEl.style.color = cfg.col;
  icoEl.innerHTML = '<i class="fas ' + cfg.ico + '"></i>';
  document.getElementById('previewJudul').textContent = judul;
  document.getElementById('previewPesan').textContent = pesan;

  const len = document.getElementById('inputPesan').value.length;
  document.getElementById('charCount').textContent = len;
  document.getElementById('charCount').style.color = len > 450 ? '#b91c1c' : 'var(--muted)';
}

function pakai(judul, pesan, tipe) {
  document.getElementById('inputJudul').value = judul;
  document.getElementById('inputPesan').value = pesan;
  document.querySelector(`input[name="tipe"][value="${tipe}"]`).checked = true;
  updatePreview();
  document.getElementById('inputJudul').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function konfirmasi() {
  const judul  = document.getElementById('inputJudul').value;
  const target = document.querySelector('input[name="target"]:checked')?.value;
  const lbl    = target === 'mahasiswa' ? 'semua mahasiswa' : target === 'admin' ? 'semua admin' : 'semua pengguna';
  return confirm(`Kirim broadcast "${judul}" ke ${lbl}?`);
}

updatePreview();
</script>
</body>
</html>
