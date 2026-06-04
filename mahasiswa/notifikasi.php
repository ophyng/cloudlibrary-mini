<?php
// ============================================
//  CloudLibrary Mini — Notifikasi
//  File   : mahasiswa/notifikasi.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();

$user_id = $_SESSION['user_id'];

// Tandai semua sudah dibaca
if (isset($_GET['baca_semua'])) {
    $pdo->prepare("UPDATE notifikasi SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    header('Location: notifikasi.php');
    exit;
}

// Tandai satu sudah dibaca
if (isset($_GET['baca'])) {
    $pdo->prepare("UPDATE notifikasi SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([(int)$_GET['baca'], $user_id]);
    header('Location: notifikasi.php');
    exit;
}

// Hapus semua
if (isset($_GET['hapus_semua'])) {
    $pdo->prepare("DELETE FROM notifikasi WHERE user_id = ?")->execute([$user_id]);
    header('Location: notifikasi.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? '';
$where  = ["user_id = ?"];
$params = [$user_id];
if ($filter === 'belum') { $where[] = "is_read = 0"; }
if ($filter === 'sudah') { $where[] = "is_read = 1"; }
if (in_array($filter, ['info','peringatan','expired'])) {
    $where[] = "tipe = ?"; $params[] = $filter;
}

$stmt = $pdo->prepare("SELECT * FROM notifikasi WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC");
$stmt->execute($params);
$notifikasi = $stmt->fetchAll();

// Hitung belum dibaca
$belum_baca_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND is_read = 0");
$belum_baca_stmt->execute([$user_id]);
$belum_baca = (int)$belum_baca_stmt->fetchColumn();

$title = "Notifikasi — CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
/* ── FULL PAGE BACKGROUND ── */
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

/* ── CSS VARIABLES ── */
:root {
  --glass:       rgba(255,255,255,0.25);
  --border:      rgba(30,58,95,0.07);
  --border-s:    rgba(30,58,95,0.09);
  --text:        #1a2332;
  --text-sub:    #3d5270;
  --muted:       #6b80a0;
  --d1:          #1e3a5f;
  --d2:          #2d5986;
  --d3:          #4a7ab5;
  --gold:        #d97706;
  --pk:          #db2777;
  --sh:          0 4px 24px rgba(30,58,95,0.08);
  --sh-md:       0 8px 36px rgba(30,58,95,0.18);
}

/* ── LAYOUT ── */
.page-outer {
  position: relative;
  z-index: 1;
  max-width: 780px;
  margin: 0 auto;
  padding: 28px 20px 60px;
}

/* ── PAGE HEADER ── */
.ntf-page-header {
  background: rgba(255,255,255,0.25);
  border: 1px solid rgba(30,58,95,0.09);
  border-radius: 24px;
  padding: 26px 30px;
  margin-bottom: 20px;
  backdrop-filter: blur(32px);
  -webkit-backdrop-filter: blur(32px);
  box-shadow: var(--sh-md);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px;
  position: relative;
  overflow: hidden;
}
.ntf-page-header::before {
  content: '';
  position: absolute;
  top: -50px; right: -50px;
  width: 180px; height: 180px;
  background: radial-gradient(circle, rgba(45,89,134,0.10), transparent 65%);
  pointer-events: none;
}
.ntf-header-left { display: flex; align-items: center; gap: 16px; }
.ntf-header-icon {
  width: 52px; height: 52px;
  border-radius: 16px;
  background: linear-gradient(135deg, #1e3a5f, #2d5986);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: #fff;
  box-shadow: 0 4px 16px rgba(30,58,95,0.35);
  flex-shrink: 0;
  position: relative;
}
.ntf-badge {
  position: absolute;
  top: -6px; right: -6px;
  min-width: 20px; height: 20px;
  border-radius: 10px;
  background: #dc2626;
  color: #fff;
  font-size: 10px; font-weight: 900;
  display: flex; align-items: center; justify-content: center;
  padding: 0 5px;
  border: 2px solid rgba(255,255,255,0.80);
}
.ntf-header-title {
  font-family: 'Syne', sans-serif;
  font-size: 24px; font-weight: 900;
  color: var(--text);
}
.ntf-header-sub {
  font-size: 12px; color: var(--muted);
  font-weight: 600; margin-top: 2px;
}

/* ── STAT GRID ── */
.ntf-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 18px;
}
@media(max-width:640px) { .ntf-stat-grid { grid-template-columns: repeat(2,1fr); } }

.ntf-stat-card {
  background: rgba(255,255,255,0.25);
  border: 1px solid rgba(30,58,95,0.08);
  border-radius: 18px;
  padding: 18px 14px;
  text-align: center;
  backdrop-filter: blur(28px);
  box-shadow: var(--sh);
  transition: transform 0.2s, box-shadow 0.2s;
}
.ntf-stat-card:hover { transform: translateY(-3px); box-shadow: var(--sh-md); }
.ntf-stat-icon {
  width: 42px; height: 42px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 10px;
  font-size: 18px;
}
.ntf-stat-num {
  font-family: 'Syne', sans-serif;
  font-size: 28px; font-weight: 900; line-height: 1;
}
.ntf-stat-lbl {
  font-size: 10px; font-weight: 800;
  color: var(--muted); margin-top: 4px;
  text-transform: uppercase; letter-spacing: 0.5px;
}

/* ── TOPBAR FILTER + AKSI ── */
.ntf-topbar {
  background: rgba(255,255,255,0.25);
  border: 1px solid rgba(30,58,95,0.08);
  border-radius: 20px;
  padding: 16px 20px;
  margin-bottom: 16px;
  backdrop-filter: blur(28px);
  box-shadow: var(--sh);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

/* ── FILTER PILLS ── */
.ntf-pills { display: flex; gap: 7px; flex-wrap: wrap; }
.ntf-pill {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 100px;
  font-size: 11px; font-weight: 800;
  border: 1.5px solid rgba(30,58,95,0.12);
  color: var(--text-sub);
  text-decoration: none;
  background: rgba(255,255,255,0.28);
  backdrop-filter: blur(14px);
  transition: all 0.2s; font-family: 'Nunito', sans-serif;
  white-space: nowrap;
}
.ntf-pill:hover { background: rgba(255,255,255,0.70); color: var(--d1); }
.ntf-pill.active {
  background: linear-gradient(135deg, #1e3a5f, #2d5986);
  color: #fff; border-color: transparent;
  box-shadow: 0 3px 12px rgba(30,58,95,0.30);
}
.ntf-pill.active-blue   { background: linear-gradient(135deg,#1e40af,#3b82f6);  color:#fff; border-color:transparent; }
.ntf-pill.active-slate  { background: linear-gradient(135deg,#1e293b,#475569);  color:#fff; border-color:transparent; }
.ntf-pill.active-cyan   { background: linear-gradient(135deg,#164e63,#0891b2);  color:#fff; border-color:transparent; }
.ntf-pill.active-orange { background: linear-gradient(135deg,#78350f,#d97706);  color:#fff; border-color:transparent; }
.ntf-pill.active-red    { background: linear-gradient(135deg,#7f1d1d,#dc2626);  color:#fff; border-color:transparent; }

/* ── ACTION BUTTONS ── */
.ntf-actions { display: flex; gap: 8px; }
.btn-baca-semua {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 16px; border-radius: 100px;
  background: rgba(255,255,255,0.30);
  border: 1.5px solid rgba(30,58,95,0.14);
  color: var(--d2); font-size: 12px; font-weight: 800;
  text-decoration: none; transition: all 0.2s;
  font-family: 'Nunito', sans-serif;
}
.btn-baca-semua:hover { background: rgba(255,255,255,0.85); }

.btn-hapus-semua {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 16px; border-radius: 100px;
  background: rgba(220,38,38,0.10);
  border: 1.5px solid rgba(220,38,38,0.20);
  color: #dc2626; font-size: 12px; font-weight: 800;
  text-decoration: none; transition: all 0.2s;
  font-family: 'Nunito', sans-serif;
}
.btn-hapus-semua:hover { background: rgba(220,38,38,0.20); }

/* ── GROUP LABEL ── */
.group-label {
  display: flex; align-items: center; gap: 10px;
  font-size: 11px; font-weight: 800;
  color: var(--muted);
  text-transform: uppercase; letter-spacing: 0.8px;
  margin: 22px 0 10px;
}
.group-label-icon {
  width: 26px; height: 26px;
  border-radius: 8px;
  background: rgba(255,255,255,0.28);
  border: 1px solid rgba(30,58,95,0.09);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; color: var(--d3);
  backdrop-filter: blur(14px);
  flex-shrink: 0;
}
.group-label::after {
  content: ''; flex: 1; height: 1px;
  background: rgba(30,58,95,0.08);
}

/* ── NOTIF ITEM ── */
.notif-item {
  display: flex; gap: 14px; align-items: flex-start;
  padding: 18px 20px;
  background: rgba(255,255,255,0.25);
  border: 1px solid rgba(30,58,95,0.08);
  border-radius: 18px;
  margin-bottom: 10px;
  backdrop-filter: blur(28px);
  -webkit-backdrop-filter: blur(28px);
  box-shadow: var(--sh);
  transition: transform 0.2s, box-shadow 0.2s;
}
.notif-item:hover { transform: translateY(-2px); box-shadow: var(--sh-md); }
.notif-item.unread {
  background: rgba(255,255,255,0.32);
  border-color: rgba(45,89,134,0.18);
}

/* Icon lingkaran */
.notif-icon {
  width: 44px; height: 44px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.icon-info       { background: rgba(8,145,178,0.12);  color: #0891b2; }
.icon-peringatan { background: rgba(217,119,6,0.12);  color: #d97706; }
.icon-expired    { background: rgba(220,38,38,0.12);  color: #dc2626; }

/* Body */
.notif-body { flex: 1; min-width: 0; }
.notif-pesan {
  font-size: 13px; line-height: 1.7; margin-bottom: 8px;
  color: var(--text); font-weight: 600;
}
.notif-item.unread .notif-pesan { font-weight: 800; }

.notif-meta {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  font-size: 11px; color: var(--muted);
}

/* Tipe chip */
.tipe-chip {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 800; letter-spacing: 0.5px;
  padding: 3px 9px; border-radius: 6px; text-transform: uppercase;
}
.chip-info       { background: rgba(8,145,178,0.10);  color: #0891b2; }
.chip-peringatan { background: rgba(217,119,6,0.10);  color: #d97706; }
.chip-expired    { background: rgba(220,38,38,0.10);  color: #dc2626; }

/* Tandai dibaca link */
.link-baca {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 800;
  color: var(--d2); text-decoration: none;
  padding: 3px 9px; border-radius: 6px;
  background: rgba(45,89,134,0.08);
  border: 1px solid rgba(45,89,134,0.14);
  transition: all 0.2s;
}
.link-baca:hover { background: rgba(45,89,134,0.18); }

.sudah-baca-tag {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 700;
  color: #15803d;
}

/* Dot unread */
.notif-dot-wrap { display: flex; align-items: flex-start; padding-top: 4px; }
.notif-dot-unread {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--d2);
  flex-shrink: 0;
  box-shadow: 0 0 0 3px rgba(45,89,134,0.20);
}
.notif-dot-read {
  width: 8px; height: 8px;
  flex-shrink: 0;
}

/* ── EMPTY STATE ── */
.empty-ntf {
  background: rgba(255,255,255,0.25);
  border: 1px solid rgba(30,58,95,0.08);
  border-radius: 24px; padding: 60px;
  text-align: center; backdrop-filter: blur(28px);
  box-shadow: var(--sh);
}
.empty-ntf .empty-icon {
  width: 72px; height: 72px;
  border-radius: 22px;
  background: rgba(45,89,134,0.10);
  border: 1.5px solid rgba(45,89,134,0.18);
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; color: var(--d2);
  margin: 0 auto 18px;
}
.empty-ntf h3 {
  font-family: 'Syne', sans-serif;
  font-size: 20px; font-weight: 900;
  color: var(--text); margin-bottom: 8px;
}
.empty-ntf p {
  font-size: 13px; color: var(--muted);
  font-weight: 600; line-height: 1.8; margin-bottom: 16px;
}
.btn-lihat-semua {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 22px; border-radius: 100px;
  background: rgba(255,255,255,0.30);
  border: 1.5px solid rgba(30,58,95,0.14);
  color: var(--d2); font-size: 12px; font-weight: 800;
  text-decoration: none; transition: all 0.2s;
  font-family: 'Nunito', sans-serif;
}
.btn-lihat-semua:hover { background: rgba(255,255,255,0.85); }

/* ── FOOTER ── */
footer.mhs-footer {
  position: relative; z-index: 1;
  text-align: center; padding: 24px;
  font-size: 12px; color: var(--muted); font-weight: 700;
  background: rgba(255,255,255,0.45);
  backdrop-filter: blur(22px);
  border-top: 1px solid rgba(30,58,95,0.07);
}

/* ── ANIMATIONS ── */
@keyframes fadeUp { from{opacity:0;transform:translateY(16px);} to{opacity:1;transform:translateY(0);} }
.fu { animation: fadeUp 0.5s ease both; }
.fu:nth-child(1){animation-delay:.05s;} .fu:nth-child(2){animation-delay:.10s;}
.fu:nth-child(3){animation-delay:.15s;} .fu:nth-child(4){animation-delay:.20s;}
.fu:nth-child(5){animation-delay:.25s;}

@media(max-width:600px){
  .ntf-topbar { flex-direction: column; align-items: flex-start; }
  .ntf-page-header { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="page-outer">

  <!-- ═ HEADER ═ -->
  <div class="ntf-page-header fu">
    <div class="ntf-header-left">
      <div class="ntf-header-icon">
        <i class="fas fa-bell"></i>
        <?php if ($belum_baca > 0): ?>
          <span class="ntf-badge"><?= $belum_baca ?></span>
        <?php endif; ?>
      </div>
      <div>
        <div class="ntf-header-title">Notifikasi</div>
        <div class="ntf-header-sub">
          <?= $belum_baca > 0
            ? $belum_baca . ' belum dibaca'
            : 'Semua sudah dibaca' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ═ STAT CARDS ═ -->
  <?php
  $total_notif    = count($notifikasi);
  $jml_belum      = array_sum(array_map(fn($n) => !$n['is_read'] ? 1 : 0, $notifikasi));
  $jml_peringatan = array_sum(array_map(fn($n) => $n['tipe']==='peringatan' ? 1 : 0, $notifikasi));
  $jml_expired    = array_sum(array_map(fn($n) => $n['tipe']==='expired' ? 1 : 0, $notifikasi));
  ?>
  <div class="ntf-stat-grid fu">

    <div class="ntf-stat-card" style="border-color:rgba(45,89,134,0.20);">
      <div class="ntf-stat-icon" style="background:rgba(45,89,134,0.10);color:var(--d2);">
        <i class="fas fa-bell"></i>
      </div>
      <div class="ntf-stat-num" style="color:var(--d2);"><?= $total_notif ?></div>
      <div class="ntf-stat-lbl">Total</div>
    </div>

    <div class="ntf-stat-card" style="border-color:rgba(8,145,178,0.20);">
      <div class="ntf-stat-icon" style="background:rgba(8,145,178,0.10);color:#0891b2;">
        <i class="fas fa-envelope"></i>
      </div>
      <div class="ntf-stat-num" style="color:#0891b2;"><?= $belum_baca ?></div>
      <div class="ntf-stat-lbl">Belum Dibaca</div>
    </div>

    <div class="ntf-stat-card" style="border-color:rgba(217,119,6,0.20);">
      <div class="ntf-stat-icon" style="background:rgba(217,119,6,0.10);color:#d97706;">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <div class="ntf-stat-num" style="color:#d97706;"><?= $jml_peringatan ?></div>
      <div class="ntf-stat-lbl">Peringatan</div>
    </div>

    <div class="ntf-stat-card" style="border-color:rgba(220,38,38,0.20);">
      <div class="ntf-stat-icon" style="background:rgba(220,38,38,0.10);color:#dc2626;">
        <i class="fas fa-times-circle"></i>
      </div>
      <div class="ntf-stat-num" style="color:#dc2626;"><?= $jml_expired ?></div>
      <div class="ntf-stat-lbl">Expired</div>
    </div>

  </div>

  <!-- ═ TOPBAR FILTER + AKSI ═ -->
  <div class="ntf-topbar fu">
    <div class="ntf-pills">
      <?php
      $pills = [
        ''           => ['icon'=>'fa-th-list',             'label'=>'Semua',        'active_class'=>'active'],
        'belum'      => ['icon'=>'fa-envelope',            'label'=>'Belum Dibaca', 'active_class'=>'active-blue'],
        'sudah'      => ['icon'=>'fa-envelope-open',       'label'=>'Sudah Dibaca', 'active_class'=>'active-slate'],
        'info'       => ['icon'=>'fa-info-circle',         'label'=>'Info',         'active_class'=>'active-cyan'],
        'peringatan' => ['icon'=>'fa-exclamation-triangle','label'=>'Peringatan',   'active_class'=>'active-orange'],
        'expired'    => ['icon'=>'fa-times-circle',        'label'=>'Expired',      'active_class'=>'active-red'],
      ];
      foreach ($pills as $k => $p):
        $is_active = ($filter === $k);
      ?>
        <a href="?filter=<?= $k ?>"
           class="ntf-pill <?= $is_active ? $p['active_class'] : '' ?>">
          <i class="fas <?= $p['icon'] ?>" style="font-size:10px;"></i>
          <?= $p['label'] ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="ntf-actions">
      <?php if ($belum_baca > 0): ?>
        <a href="?baca_semua=1" class="btn-baca-semua">
          <i class="fas fa-check-double"></i> Baca Semua
        </a>
      <?php endif; ?>
      <?php if ($notifikasi): ?>
        <a href="?hapus_semua=1" class="btn-hapus-semua"
           onclick="return confirm('Hapus semua notifikasi?')">
          <i class="fas fa-trash-alt"></i> Hapus Semua
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═ DAFTAR NOTIFIKASI ═ -->
  <?php if ($notifikasi):
    // Kelompokkan per hari
    $grouped   = [];
    foreach ($notifikasi as $n) {
      $tgl = date('Y-m-d', strtotime($n['created_at']));
      $grouped[$tgl][] = $n;
    }
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $icon_map = [
      'info'       => ['class' => 'icon-info',       'fa' => 'fa-info-circle'],
      'peringatan' => ['class' => 'icon-peringatan',  'fa' => 'fa-exclamation-triangle'],
      'expired'    => ['class' => 'icon-expired',     'fa' => 'fa-times-circle'],
    ];

    foreach ($grouped as $tgl => $items):
      if ($tgl === $today)         $label_hari = 'Hari Ini';
      elseif ($tgl === $yesterday) $label_hari = 'Kemarin';
      else                          $label_hari = formatTanggal($tgl);
  ?>

    <!-- Group Label -->
    <div class="group-label fu">
      <div class="group-label-icon">
        <i class="fas fa-calendar-day"></i>
      </div>
      <?= $label_hari ?>
    </div>

    <?php foreach ($items as $idx => $n):
      $ic = $icon_map[$n['tipe']] ?? ['class'=>'icon-info','fa'=>'fa-bell'];
    ?>
    <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?> fu"
         style="animation-delay:<?= ($idx * 0.04 + 0.05) ?>s;">

      <!-- Ikon tipe -->
      <div class="notif-icon <?= $ic['class'] ?>">
        <i class="fas <?= $ic['fa'] ?>"></i>
      </div>

      <!-- Isi -->
      <div class="notif-body">
        <p class="notif-pesan"><?= e($n['pesan']) ?></p>
        <div class="notif-meta">

          <span class="tipe-chip chip-<?= $n['tipe'] ?>">
            <i class="fas <?= $ic['fa'] ?>" style="font-size:8px;"></i>
            <?= $n['tipe'] ?>
          </span>

          <span>
            <i class="fas fa-clock" style="font-size:9px;margin-right:3px;color:var(--d3);"></i>
            <?= date('H:i', strtotime($n['created_at'])) ?>
          </span>

          <?php if (!$n['is_read']): ?>
            <a href="?baca=<?= $n['id'] ?>&filter=<?= $filter ?>" class="link-baca">
              <i class="fas fa-check" style="font-size:9px;"></i> Tandai Dibaca
            </a>
          <?php else: ?>
            <span class="sudah-baca-tag">
              <i class="fas fa-check-double" style="font-size:9px;"></i> Sudah dibaca
            </span>
          <?php endif; ?>

        </div>
      </div>

      <!-- Dot indicator -->
      <div class="notif-dot-wrap">
        <?php if (!$n['is_read']): ?>
          <div class="notif-dot-unread"></div>
        <?php else: ?>
          <div class="notif-dot-read"></div>
        <?php endif; ?>
      </div>

    </div>
    <?php endforeach; ?>

  <?php endforeach; ?>

  <?php else: ?>
  <div class="empty-ntf fu">
    <div class="empty-icon">
      <i class="fas fa-bell-slash"></i>
    </div>
    <h3>
      <?= $filter ? 'Tidak Ada Notifikasi' : 'Belum Ada Notifikasi' ?>
    </h3>
    <p>
      <?= $filter
        ? 'Tidak ada notifikasi dengan filter ini.'
        : 'Semua aktivitas perpustakaanmu akan muncul di sini.' ?>
    </p>
    <?php if ($filter): ?>
      <a href="<?= BASE_URL ?>/mahasiswa/notifikasi.php" class="btn-lihat-semua">
        <i class="fas fa-arrow-left" style="font-size:10px;"></i> Lihat Semua
      </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /page-outer -->

<footer class="mhs-footer">
  <i class="fas fa-cloud" style="color:var(--d2);margin-right:6px;"></i>
  <strong style="color:var(--d2);">CloudLibrary Mini</strong> — Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>
</body>
</html>