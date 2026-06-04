<?php
// ============================================
//  CloudLibrary Mini — Super Admin: Log Aktivitas
//  File   : super_admin/log.php
// ============================================
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: '.BASE_URL.'/auth/login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'hapus_semua') {
        $pdo->query("DELETE FROM activity_log");
        header('Location: log.php?success=1'); exit;
    }
    if ($aksi === 'hapus_satu') {
        $id = (int)($_POST['log_id'] ?? 0);
        $pdo->prepare("DELETE FROM activity_log WHERE id=?")->execute([$id]);
        header('Location: log.php?success=1'); exit;
    }
}

$search  = trim($_GET['q']      ?? '');
$role    = $_GET['role']        ?? '';
$aksi_f  = $_GET['aksi']        ?? '';
$sort    = $_GET['sort']        ?? 'terbaru';
$per_page = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(u.nama LIKE ? OR al.aksi LIKE ? OR al.detail LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($role)   { $where[] = "al.role = ?";  $params[] = $role; }
if ($aksi_f) { $where[] = "al.aksi LIKE ?"; $params[] = "%$aksi_f%"; }

$order = match($sort) {
    'lama'  => 'al.created_at ASC',
    default => 'al.created_at DESC',
};

$where_sql = implode(' AND ', $where);

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log al LEFT JOIN users u ON al.user_id=u.id WHERE $where_sql");
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $pdo->prepare("
    SELECT al.*, u.nama AS user_nama, u.email AS user_email
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE $where_sql
    ORDER BY $order
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN role='super_admin' THEN 1 ELSE 0 END) AS sa,
        SUM(CASE WHEN role='admin'       THEN 1 ELSE 0 END) AS admin,
        SUM(CASE WHEN role='mahasiswa'   THEN 1 ELSE 0 END) AS mhs,
        SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) AS hari_ini
    FROM activity_log
")->fetch();

$top_aksi = $pdo->query("
    SELECT aksi, COUNT(*) AS total FROM activity_log
    GROUP BY aksi ORDER BY total DESC LIMIT 5
")->fetchAll();

$title = "Log Aktivitas — Super Admin CloudLibrary Mini";
include '../includes/navbar.php';
?>
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
.btn-back:hover{background:rgba(255,255,255,0.82);transform:translateX(-2px);}

.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:10px;}
.page-header-left{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:var(--gold);}
.ph-sub{font-size:12px;font-weight:700;color:var(--muted);background:rgba(255,255,255,0.78);border:2px solid rgba(255,255,255,0.85);padding:6px 14px;border-radius:100px;backdrop-filter:blur(20px);display:flex;align-items:center;gap:6px;}

.alert-box{border-radius:14px;padding:13px 18px;margin-bottom:18px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;backdrop-filter:blur(20px);position:relative;z-index:1;}
.alert-success{background:rgba(21,128,61,0.08);border:1.5px solid rgba(21,128,61,0.24);color:#15803d;}

.stat-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:22px;position:relative;z-index:1;}
@media(max-width:900px){.stat-row{grid-template-columns:repeat(3,1fr);}}
@media(max-width:600px){.stat-row{grid-template-columns:repeat(2,1fr);}}
.stat-mini{background:var(--card);border:2px solid var(--card-b);border-radius:16px;padding:14px;text-align:center;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:transform .2s;position:relative;overflow:hidden;border-top:3px solid transparent;}
.stat-mini:hover{transform:translateY(-2px);}
.stat-mini .stat-ico{width:36px;height:36px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:15px;margin-bottom:6px;}
.stat-mini .num{font-family:'Syne',sans-serif;font-size:24px;font-weight:900;}
.stat-mini .lbl{font-size:10px;font-weight:700;color:var(--muted);margin-top:3px;}

.main-grid{display:grid;grid-template-columns:1fr 260px;gap:18px;position:relative;z-index:1;}
@media(max-width:1000px){.main-grid{grid-template-columns:1fr;}}

.log-card{background:var(--card);border:2px solid var(--card-b);border-radius:20px;overflow:hidden;backdrop-filter:blur(20px);box-shadow:var(--sh);}
.lc-header{padding:16px 20px;border-bottom:1.5px solid rgba(58,97,134,0.08);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.lc-header h3{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:8px;}

.filter-bar{padding:12px 20px;border-bottom:1.5px solid rgba(58,97,134,0.06);display:flex;gap:8px;flex-wrap:wrap;align-items:center;background:rgba(255,255,255,0.50);}
.filter-input{background:rgba(255,255,255,0.82);border:1.5px solid rgba(255,255,255,0.90);border-radius:100px;padding:7px 14px;font-size:12px;font-family:'Nunito',sans-serif;color:var(--text);outline:none;flex:1;min-width:160px;transition:border-color .2s;}
.filter-input:focus{border-color:rgba(58,97,134,0.35);}
.filter-select{background:rgba(255,255,255,0.82);border:1.5px solid rgba(255,255,255,0.90);border-radius:100px;padding:7px 14px;font-size:12px;font-family:'Nunito',sans-serif;color:var(--text);outline:none;cursor:pointer;}
.btn-filter{padding:7px 16px;border-radius:100px;background:linear-gradient(135deg,var(--s1),var(--s3));color:#fff;font-size:12px;font-weight:800;border:none;cursor:pointer;font-family:'Nunito',sans-serif;box-shadow:0 3px 12px rgba(58,97,134,0.25);display:inline-flex;align-items:center;gap:5px;}
.btn-filter:hover{transform:translateY(-1px);}
.btn-clear{padding:7px 16px;border-radius:100px;background:rgba(255,255,255,0.80);color:var(--muted);font-size:12px;font-weight:800;border:1.5px solid rgba(255,255,255,0.88);cursor:pointer;font-family:'Nunito',sans-serif;text-decoration:none;}

.log-table-wrap{overflow-x:auto;}
table.log-table{width:100%;border-collapse:collapse;}
table.log-table th{font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;padding:11px 16px;text-align:left;border-bottom:1.5px solid rgba(58,97,134,0.08);background:rgba(255,255,255,0.25);white-space:nowrap;}
table.log-table td{padding:11px 16px;border-bottom:1px solid rgba(58,97,134,0.05);font-size:12px;color:var(--text);vertical-align:middle;}
table.log-table tr:last-child td{border-bottom:none;}
table.log-table tr:hover td{background:rgba(58,97,134,0.025);}

.role-badge{font-size:9px;font-weight:900;padding:3px 9px;border-radius:100px;text-transform:uppercase;white-space:nowrap;}
.rb-sa{background:rgba(58,97,134,0.10);color:#3a6186;border:1px solid rgba(58,97,134,0.22);}
.rb-admin{background:rgba(91,143,185,0.10);color:#5b8fb9;border:1px solid rgba(91,143,185,0.22);}
.rb-mhs{background:rgba(124,58,237,0.10);color:#7c3aed;border:1px solid rgba(124,58,237,0.22);}

.aksi-badge{font-size:10px;font-weight:800;padding:4px 10px;border-radius:8px;white-space:nowrap;}
.ab-login{background:rgba(21,128,61,0.10);color:#15803d;}
.ab-logout{background:rgba(107,122,153,0.10);color:#6b7a99;}
.ab-tambah{background:rgba(58,97,134,0.10);color:#3a6186;}
.ab-edit{background:rgba(212,160,23,0.10);color:#d4a017;}
.ab-hapus{background:rgba(185,28,28,0.10);color:#b91c1c;}
.ab-kirim{background:rgba(124,58,237,0.10);color:#7c3aed;}
.ab-default{background:rgba(107,122,153,0.08);color:var(--muted);}

.av-xs{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--s1),var(--s3));display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;color:#fff;font-family:'Syne',sans-serif;flex-shrink:0;}

.btn-del-row{font-size:10px;font-weight:800;color:rgba(185,28,28,0.5);background:none;border:none;cursor:pointer;padding:3px 8px;border-radius:6px;transition:all .15s;display:inline-flex;align-items:center;gap:3px;}
.btn-del-row:hover{background:rgba(185,28,28,0.08);color:#b91c1c;}

.pagination{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1.5px solid rgba(58,97,134,0.06);flex-wrap:wrap;gap:8px;}
.pag-info{font-size:11px;color:var(--muted);font-weight:700;}
.pag-btns{display:flex;gap:4px;}
.pag-btn{padding:5px 12px;border-radius:8px;font-size:11px;font-weight:800;text-decoration:none;color:var(--muted);background:rgba(255,255,255,0.80);border:1.5px solid rgba(255,255,255,0.88);transition:all .2s;}
.pag-btn:hover{color:var(--s1);border-color:rgba(58,97,134,0.25);}
.pag-btn.active{background:rgba(58,97,134,0.10);color:var(--s1);border-color:rgba(58,97,134,0.25);}
.pag-btn.disabled{opacity:.4;pointer-events:none;}

.side-col{display:flex;flex-direction:column;gap:14px;}
.side-card{background:var(--card);border:2px solid var(--card-b);border-radius:18px;overflow:hidden;backdrop-filter:blur(20px);box-shadow:var(--sh);}
.side-card-head{padding:14px 18px 10px;border-bottom:1.5px solid rgba(58,97,134,0.08);}
.side-card-head h4{font-family:'Syne',sans-serif;font-size:13px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:7px;}
.side-card-body{padding:14px 18px;}

.top-aksi-item{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(58,97,134,0.06);}
.top-aksi-item:last-child{border-bottom:none;}
.top-aksi-name{font-size:11px;font-weight:800;color:var(--text);}
.top-aksi-bar-wrap{flex:1;height:6px;background:rgba(58,97,134,0.08);border-radius:3px;overflow:hidden;margin:0 10px;}
.top-aksi-bar{height:100%;background:linear-gradient(90deg,var(--s1),var(--s3));border-radius:3px;}
.top-aksi-num{font-family:'Syne',sans-serif;font-size:12px;font-weight:900;color:var(--s1);}

.danger-zone{background:rgba(185,28,28,0.04);border:1.5px solid rgba(185,28,28,0.18);border-radius:14px;padding:14px;}
.dz-title{font-family:'Syne',sans-serif;font-size:12px;font-weight:900;color:#b91c1c;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.dz-desc{font-size:11px;color:var(--muted);font-weight:600;line-height:1.6;margin-bottom:12px;}
.btn-danger-full{width:100%;padding:9px;border-radius:100px;background:rgba(185,28,28,0.10);border:1.5px solid rgba(185,28,28,0.28);color:#b91c1c;font-size:12px;font-weight:800;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:7px;}
.btn-danger-full:hover{background:rgba(185,28,28,0.18);}

.empty-state{text-align:center;padding:40px 20px;color:var(--muted);}
.empty-state i{font-size:32px;display:block;margin-bottom:10px;opacity:.4;}
.empty-state p{font-size:13px;font-weight:700;}

footer.sa-foot{position:relative;z-index:1;text-align:center;padding:20px;font-size:12px;color:var(--muted);font-weight:700;background:transparent;border-top:1.5px dashed rgba(58,97,134,0.15);margin-top:24px;}

@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}.fu3{animation:fadeUp .4s ease .20s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <div class="page-header-left">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h2><i class="fas fa-history"></i> Log Aktivitas</h2>
  </div>
  <div class="ph-sub"><i class="fas fa-search" style="font-size:10px;"></i> <?= number_format($total_rows) ?> total log</div>
</div>

<?php if(isset($_GET['success'])): ?>
<div class="alert-box alert-success fu1"><i class="fas fa-check-circle"></i> Berhasil!</div>
<?php endif; ?>

<!-- STAT ROW -->
<div class="stat-row fu2">
  <?php $sc=[
    ['fa-clipboard-list','#3a6186','rgba(58,97,134,0.12)', $stats['total'],    'Total Log'],
    ['fa-bolt',          '#5b8fb9','rgba(91,143,185,0.12)',$stats['hari_ini'], 'Hari Ini'],
    ['fa-crown',         '#d4a017','rgba(212,160,23,0.12)',$stats['sa'],       'Super Admin'],
    ['fa-shield-alt',    '#3a6186','rgba(58,97,134,0.12)', $stats['admin'],    'Admin'],
    ['fa-user-graduate', '#7c3aed','rgba(124,58,237,0.12)',$stats['mhs'],      'Mahasiswa'],
  ]; foreach($sc as [$ico,$col,$bgc,$num,$lbl]): ?>
  <div class="stat-mini" style="border-top-color:<?= $col ?>;">
    <div class="stat-ico" style="background:<?= $bgc ?>;color:<?= $col ?>;"><i class="fas <?= $ico ?>"></i></div>
    <div class="num" style="color:<?= $col ?>;"><?= number_format($num) ?></div>
    <div class="lbl"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- MAIN GRID -->
<div class="main-grid fu3">

  <div class="log-card">
    <div class="lc-header">
      <h3><i class="fas fa-list"></i> Riwayat Aktivitas</h3>
      <span style="font-size:11px;color:var(--muted);font-weight:700;">Halaman <?= $page ?> dari <?= max(1,$total_pages) ?></span>
    </div>

    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="filter-input" placeholder="Cari nama / aktivitas..." value="<?= e($search) ?>">
      <select name="role" class="filter-select">
        <option value="" <?= !$role?'selected':'' ?>>Semua Role</option>
        <option value="super_admin" <?= $role==='super_admin'?'selected':'' ?>>Super Admin</option>
        <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
        <option value="mahasiswa" <?= $role==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
      </select>
      <select name="aksi" class="filter-select">
        <option value="" <?= !$aksi_f?'selected':'' ?>>Semua Aksi</option>
        <option value="Login" <?= $aksi_f==='Login'?'selected':'' ?>>Login</option>
        <option value="Logout" <?= $aksi_f==='Logout'?'selected':'' ?>>Logout</option>
        <option value="Tambah" <?= $aksi_f==='Tambah'?'selected':'' ?>>Tambah</option>
        <option value="Edit" <?= $aksi_f==='Edit'?'selected':'' ?>>Edit</option>
        <option value="Hapus" <?= $aksi_f==='Hapus'?'selected':'' ?>>Hapus</option>
        <option value="Broadcast" <?= $aksi_f==='Broadcast'?'selected':'' ?>>Broadcast</option>
        <option value="Pinjam" <?= $aksi_f==='Pinjam'?'selected':'' ?>>Pinjam</option>
      </select>
      <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
      <a href="log.php" class="btn-clear">Reset</a>
    </form>

    <div class="log-table-wrap">
      <?php if($logs): ?>
      <table class="log-table">
        <thead><tr><th>#</th><th>Pengguna</th><th>Role</th><th>Aktivitas</th><th>Detail</th><th>IP</th><th>Waktu</th><th></th></tr></thead>
        <tbody>
          <?php foreach($logs as $i=>$log):
            $nama=$log['user_nama']??'User #'.$log['user_id'];
            $init=strtoupper(substr($nama,0,1));
            $aksi_lower=strtolower($log['aksi']);
            $ab_class='ab-default';
            if(str_contains($aksi_lower,'login'))$ab_class='ab-login';
            elseif(str_contains($aksi_lower,'logout'))$ab_class='ab-logout';
            elseif(str_contains($aksi_lower,'tambah')||str_contains($aksi_lower,'buat'))$ab_class='ab-tambah';
            elseif(str_contains($aksi_lower,'edit')||str_contains($aksi_lower,'update'))$ab_class='ab-edit';
            elseif(str_contains($aksi_lower,'hapus')||str_contains($aksi_lower,'delete'))$ab_class='ab-hapus';
            elseif(str_contains($aksi_lower,'broadcast')||str_contains($aksi_lower,'kirim'))$ab_class='ab-kirim';
            $rb_class=match($log['role']){'super_admin'=>'rb-sa','admin'=>'rb-admin',default=>'rb-mhs'};
          ?>
          <tr>
            <td style="color:var(--muted);font-size:11px;"><?= $offset+$i+1 ?></td>
            <td><div style="display:flex;align-items:center;gap:8px;"><div class="av-xs"><?= $init ?></div><div><div style="font-weight:800;font-size:12px;"><?= e($nama) ?></div><div style="font-size:10px;color:var(--muted);"><?= e($log['user_email']??'') ?></div></div></div></td>
            <td><span class="role-badge <?= $rb_class ?>"><?= $log['role'] ?></span></td>
            <td><span class="aksi-badge <?= $ab_class ?>"><?= e($log['aksi']) ?></span></td>
            <td style="max-width:200px;"><div style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;" title="<?= e($log['detail']) ?>"><?= e($log['detail']??'—') ?></div></td>
            <td style="font-size:10px;color:var(--muted);font-weight:600;white-space:nowrap;"><?= e($log['ip_address']??'—') ?></td>
            <td style="font-size:10px;color:var(--muted);font-weight:600;white-space:nowrap;"><?= date('d M Y',strtotime($log['created_at'])) ?><br><span style="color:var(--s1);font-weight:800;"><?= date('H:i:s',strtotime($log['created_at'])) ?></span></td>
            <td><form method="POST" style="display:inline;"><input type="hidden" name="aksi" value="hapus_satu"><input type="hidden" name="log_id" value="<?= $log['id'] ?>"><button type="submit" class="btn-del-row" title="Hapus log ini"><i class="fas fa-trash-alt"></i></button></form></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state"><i class="fas fa-history"></i><p>Belum ada log aktivitas<?= $search?" untuk \"".e($search)."\"":'' ?>.</p></div>
      <?php endif; ?>
    </div>

    <?php if($total_pages>1): ?>
    <div class="pagination">
      <div class="pag-info"><?= number_format($offset+1) ?>–<?= number_format(min($offset+$per_page,$total_rows)) ?> dari <?= number_format($total_rows) ?> log</div>
      <div class="pag-btns">
        <?php $base='?'.http_build_query(array_merge($_GET,['page'=>1]));$prev='?'.http_build_query(array_merge($_GET,['page'=>$page-1]));$next='?'.http_build_query(array_merge($_GET,['page'=>$page+1]));$last='?'.http_build_query(array_merge($_GET,['page'=>$total_pages])); ?>
        <a href="<?= $base ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>">&laquo;</a>
        <a href="<?= $prev ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>">&lsaquo;</a>
        <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
          <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="pag-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="<?= $next ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>">&rsaquo;</a>
        <a href="<?= $last ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>">&raquo;</a>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- SIDE -->
  <div class="side-col">
    <div class="side-card">
      <div class="side-card-head"><h4><i class="fas fa-fire" style="color:var(--gold);"></i> Aktivitas Terbanyak</h4></div>
      <div class="side-card-body">
        <?php $max_aksi=$top_aksi?$top_aksi[0]['total']:1;foreach($top_aksi as $ta): ?>
        <div class="top-aksi-item">
          <div class="top-aksi-name"><?= e($ta['aksi']) ?></div>
          <div class="top-aksi-bar-wrap"><div class="top-aksi-bar" style="width:<?= round($ta['total']/$max_aksi*100) ?>%;"></div></div>
          <div class="top-aksi-num"><?= $ta['total'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($top_aksi)): ?><div style="text-align:center;color:var(--muted);font-size:12px;padding:16px 0;">Belum ada data.</div><?php endif; ?>
      </div>
    </div>

    <div class="side-card">
      <div class="side-card-head"><h4><i class="fas fa-info-circle" style="color:var(--s1);"></i> Cara Kerja</h4></div>
      <div class="side-card-body">
        <div style="font-size:11px;color:var(--muted);font-weight:600;line-height:1.8;">
          Log aktivitas dicatat otomatis saat pengguna melakukan aksi seperti:<br><br>
          <span style="color:var(--text);font-weight:800;"><i class="fas fa-sign-in-alt" style="font-size:10px;color:var(--success);width:16px;"></i> Login / Logout</span><br>
          <span style="color:var(--text);font-weight:800;"><i class="fas fa-book" style="font-size:10px;color:var(--s1);width:16px;"></i> Tambah / Edit / Hapus buku</span><br>
          <span style="color:var(--text);font-weight:800;"><i class="fas fa-clipboard-list" style="font-size:10px;color:var(--gold);width:16px;"></i> Pinjam / Kembalikan buku</span><br>
          <span style="color:var(--text);font-weight:800;"><i class="fas fa-bullhorn" style="font-size:10px;color:#7c3aed;width:16px;"></i> Kirim broadcast</span><br>
          <span style="color:var(--text);font-weight:800;"><i class="fas fa-users" style="font-size:10px;color:var(--s3);width:16px;"></i> Kelola user & admin</span>
        </div>
      </div>
    </div>

    <div class="side-card">
      <div class="side-card-head"><h4><i class="fas fa-exclamation-triangle" style="color:#b91c1c;"></i> Danger Zone</h4></div>
      <div class="side-card-body">
        <div class="danger-zone">
          <div class="dz-title"><i class="fas fa-exclamation-triangle"></i> Hapus Semua Log</div>
          <div class="dz-desc">Semua riwayat aktivitas akan dihapus permanen. Tindakan ini tidak dapat dibatalkan!</div>
          <form method="POST" onsubmit="return confirm('Yakin hapus SEMUA log aktivitas? Tidak dapat dibatalkan!')">
            <input type="hidden" name="aksi" value="hapus_semua">
            <button type="submit" class="btn-danger-full"><i class="fas fa-trash-alt"></i> Hapus Semua Log</button>
          </form>
        </div>
      </div>
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
</body>
</html>