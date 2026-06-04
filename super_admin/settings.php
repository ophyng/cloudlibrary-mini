<?php
// ============================================
//  CloudLibrary Mini — Super Admin: Settings
//  File   : super_admin/settings.php
// ============================================
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: '.BASE_URL.'/auth/login.php'); exit;
}

$current_user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg   = '';

// ── HANDLE EXPORT SQL (must be before any output) ──
if (isset($_GET['export']) && $_GET['export'] === 'sql') {
    $db_name_q = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $tables_q = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$db_name_q'")->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_' . $db_name_q . '_' . date('Ymd_His') . '.sql"');

    echo "-- CloudLibrary Mini SQL Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: $db_name_q\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables_q as $tname) {
        try {
            $create = $pdo->query("SHOW CREATE TABLE `$tname`")->fetch(PDO::FETCH_NUM);
            echo "-- Table: $tname\nDROP TABLE IF EXISTS `$tname`;\n" . $create[1] . ";\n\n";
            $rows = $pdo->query("SELECT * FROM `$tname`")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
                foreach ($rows as $r) {
                    $vals = array_map(fn($v) => is_null($v) ? 'NULL' : $pdo->quote($v), $r);
                    echo "INSERT INTO `$tname` ($cols) VALUES (" . implode(', ', $vals) . ");\n";
                }
                echo "\n";
            }
        } catch (Exception $e) {
            echo "-- Error: " . $e->getMessage() . "\n\n";
        }
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n-- End of backup\n";

    // Log backup
    try {
        $pdo->prepare("INSERT INTO activity_log (user_id, role, aksi, detail, ip_address) VALUES (?,?,?,?,?)")
            ->execute([$current_user_id, 'super_admin', 'Backup DB', 'Export database SQL', $_SERVER['REMOTE_ADDR']]);
    } catch(Exception $e) {}
    exit;
}

// ── HANDLE POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new !== $confirm) { $error_msg = 'Password baru dan konfirmasi tidak cocok.'; }
        elseif (strlen($new) < 6) { $error_msg = 'Password minimal 6 karakter.'; }
        else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $row = $stmt->fetch();
            if ($row && md5($old) === $row['password']) {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([md5($new), $current_user_id]);
                $success_msg = 'Password berhasil diubah!';
                $pdo->prepare("INSERT INTO activity_log (user_id, role, aksi, detail, ip_address) VALUES (?,?,?,?,?)")
                    ->execute([$current_user_id, 'super_admin', 'Ganti Password', 'Super Admin mengganti password', $_SERVER['REMOTE_ADDR']]);
            } else { $error_msg = 'Password lama tidak sesuai.'; }
        }
    }

    if ($action === 'force_logout') {
        $success_msg = 'Perintah force logout terkirim. Semua user harus login ulang.';
        $pdo->prepare("INSERT INTO activity_log (user_id, role, aksi, detail, ip_address) VALUES (?,?,?,?,?)")
            ->execute([$current_user_id, 'super_admin', 'Force Logout', 'Force logout semua user', $_SERVER['REMOTE_ADDR']]);
    }

    if ($action === 'reset_points') {
        $pdo->query("UPDATE users SET poin = 0 WHERE role = 'mahasiswa'");
        $success_msg = 'Semua poin mahasiswa berhasil direset ke 0!';
        $pdo->prepare("INSERT INTO activity_log (user_id, role, aksi, detail, ip_address) VALUES (?,?,?,?,?)")
            ->execute([$current_user_id, 'super_admin', 'Reset Poin', 'Reset semua poin mahasiswa', $_SERVER['REMOTE_ADDR']]);
    }

    if ($action === 'clear_logs') {
        $pdo->query("DELETE FROM activity_log");
        $success_msg = 'Semua log aktivitas berhasil dihapus!';
    }

    if ($action === 'save_notif') {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (`key` VARCHAR(100) PRIMARY KEY, `value` TEXT)");
            $pairs = [
                'notif_due_reminder' => htmlspecialchars($_POST['notif_due_reminder'] ?? ''),
                'notif_overdue' => htmlspecialchars($_POST['notif_overdue'] ?? ''),
                'notif_welcome' => htmlspecialchars($_POST['notif_welcome'] ?? ''),
                'notif_auto_reminder' => isset($_POST['notif_auto_reminder']) ? '1' : '0',
            ];
            $stmt = $pdo->prepare("INSERT INTO system_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            foreach ($pairs as $k => $v) $stmt->execute([$k, $v]);
            $success_msg = 'Template notifikasi berhasil disimpan!';
        } catch (Exception $e) { $error_msg = 'Gagal: ' . $e->getMessage(); }
    }
}

// ── LOAD SETTINGS ──
$settings = [];
try { $rows = $pdo->query("SELECT `key`,`value` FROM system_settings")->fetchAll(PDO::FETCH_ASSOC); foreach ($rows as $r) $settings[$r['key']] = $r['value']; } catch (Exception $e) {}
$notif_due     = $settings['notif_due_reminder'] ?? 'Hai {nama}, buku "{judul}" akan jatuh tempo besok ({tanggal}). Segera kembalikan!';
$notif_overdue = $settings['notif_overdue'] ?? 'Hai {nama}, buku "{judul}" sudah melewati batas waktu ({tanggal}). Denda Rp{denda}.';
$notif_welcome = $settings['notif_welcome'] ?? 'Selamat datang di CloudLibrary, {nama}! Akun Anda telah aktif.';
$notif_auto    = $settings['notif_auto_reminder'] ?? '1';

// ── SYSTEM HEALTH ──
$php_version = PHP_VERSION;
$php_memory = ini_get('memory_limit');
$php_mem_used = round(memory_get_usage(true)/1024/1024, 2);
$php_mem_peak = round(memory_get_peak_usage(true)/1024/1024, 2);
$disk_total = round(disk_total_space('/')/1073741824, 2);
$disk_free = round(disk_free_space('/')/1073741824, 2);
$disk_pct = $disk_total > 0 ? round((($disk_total-$disk_free)/$disk_total)*100, 1) : 0;
$uptime_raw = PHP_OS_FAMILY === 'Linux' ? trim(@shell_exec('uptime -p') ?: 'N/A') : 'N/A';
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$mysql_version = $pdo->query("SELECT VERSION()")->fetchColumn();
$req_today = 0;
try { $req_today = $pdo->query("SELECT COUNT(*) FROM activity_log WHERE DATE(created_at) = CURDATE()")->fetchColumn(); } catch (Exception $e) {}

// ── DATABASE ──
$db_tables = []; $db_size_mb = 0;
$db_name_q = $pdo->query("SELECT DATABASE()")->fetchColumn();
try {
    $db_tables = $pdo->query("
        SELECT TABLE_NAME, TABLE_ROWS,
               ROUND((DATA_LENGTH+INDEX_LENGTH)/1024/1024, 3) AS size_mb,
               CREATE_TIME, UPDATE_TIME, ENGINE
        FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$db_name_q' ORDER BY TABLE_ROWS DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($db_tables as $t) $db_size_mb += (float)$t['size_mb'];
    $db_size_mb = round($db_size_mb, 3);
} catch (Exception $e) {}

// ── SECURITY ──
$login_history = [];
try {
    $login_history = $pdo->query("
        SELECT al.*, u.nama, u.email, u.role FROM activity_log al
        JOIN users u ON al.user_id = u.id WHERE al.aksi = 'Login'
        ORDER BY al.created_at DESC LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {}

$login_today = 0; $login_week = 0; $unique_ips = 0;
try {
    $login_today = $pdo->query("SELECT COUNT(*) FROM activity_log WHERE aksi='Login' AND DATE(created_at)=CURDATE()")->fetchColumn();
    $login_week = $pdo->query("SELECT COUNT(*) FROM activity_log WHERE aksi='Login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $unique_ips = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM activity_log WHERE aksi='Login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
} catch (Exception $e) {}

$sa_data = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $sa_data->execute([$current_user_id]); $sa_data = $sa_data->fetch();
$last_login = $pdo->prepare("SELECT created_at, ip_address FROM activity_log WHERE user_id = ? AND aksi = 'Login' ORDER BY created_at DESC LIMIT 1");
$last_login->execute([$current_user_id]); $last_login_data = $last_login->fetch();

// ── BACKUP HISTORY (from activity_log) ──
$backup_history = [];
try {
    $backup_history = $pdo->query("
        SELECT al.*, u.nama FROM activity_log al JOIN users u ON al.user_id = u.id
        WHERE al.aksi = 'Backup DB' ORDER BY al.created_at DESC LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {}

$total_records = 0;
foreach ($db_tables as $t) $total_records += (int)$t['TABLE_ROWS'];

$title = "Pengaturan Sistem — Super Admin CloudLibrary Mini";
include '../includes/navbar.php';
?>
<style>
body{font-family:'Nunito',sans-serif;min-height:100vh;overflow-x:hidden;position:relative;margin:0;background:#dce8f5;background-image:url('gambar_library.jpg');background-size:cover;background-position:center;background-attachment:fixed;background-repeat:no-repeat;color:#1a2744 !important;}
body::before{content:'';position:fixed;inset:0;background:rgba(235,243,252,0.28);z-index:0;pointer-events:none;}

:root{--s1:#3a6186;--s2:#2c4f78;--s3:#5b8fb9;--gold:#d4a017;--card:rgba(255,255,255,0.78);--card-b:rgba(255,255,255,0.85);--text:#1a2744;--muted:#6b7a99;--success:#15803d;--warning:#c2410c;--danger:#b91c1c;--sh:0 4px 20px rgba(58,97,134,0.10);--sh-md:0 10px 36px rgba(58,97,134,0.16);}

.btn-back{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:100px;background:var(--card);border:2px solid var(--card-b);color:var(--s1);font-size:12px;font-weight:800;text-decoration:none;backdrop-filter:blur(20px);box-shadow:var(--sh);transition:all .2s;}
.btn-back:hover{background:rgba(255,255,255,0.90);transform:translateX(-2px);}

.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;z-index:1;flex-wrap:wrap;gap:10px;}
.page-header-left{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.page-header h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:900;color:var(--s1);display:flex;align-items:center;gap:10px;}
.page-header h2 i{color:var(--gold);}
.ph-sub{font-size:12px;font-weight:700;color:var(--muted);background:var(--card);border:2px solid var(--card-b);padding:6px 14px;border-radius:100px;backdrop-filter:blur(20px);display:flex;align-items:center;gap:6px;}

.alert-box{border-radius:14px;padding:13px 18px;margin-bottom:18px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;backdrop-filter:blur(20px);position:relative;z-index:1;}
.alert-success{background:rgba(21,128,61,0.08);border:1.5px solid rgba(21,128,61,0.24);color:var(--success);}
.alert-error{background:rgba(185,28,28,0.08);border:1.5px solid rgba(185,28,28,0.24);color:var(--danger);}

/* TAB NAV */
.tab-nav{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px;position:relative;z-index:1;}
.tab-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:12px;border:2px solid var(--card-b);background:var(--card);color:var(--muted);font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;backdrop-filter:blur(20px);font-family:'Nunito',sans-serif;}
.tab-btn:hover,.tab-btn.active{background:var(--s1);color:#fff;border-color:var(--s1);box-shadow:0 4px 14px rgba(58,97,134,0.25);}
.tab-btn i{font-size:13px;}

/* PANELS */
.panel{display:none;position:relative;z-index:1;}
.panel.active{display:block;animation:fadeIn .3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}

/* CARDS */
.card{background:var(--card);backdrop-filter:blur(20px);border:2px solid var(--card-b);border-radius:18px;box-shadow:var(--sh);padding:24px;margin-bottom:20px;}
.card-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:900;color:var(--text);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.card-title i{color:var(--s1);}
.card-subtitle{font-size:12px;color:var(--muted);margin-left:auto;font-weight:400;}

/* GRIDS */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:16px;position:relative;z-index:1;}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;position:relative;z-index:1;}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;position:relative;z-index:1;}
@media(max-width:900px){.g2,.g3{grid-template-columns:1fr;}.g4{grid-template-columns:repeat(2,1fr);}}

/* HEALTH CARDS */
.hcard{background:var(--card);border:2px solid var(--card-b);border-radius:14px;padding:18px 20px;backdrop-filter:blur(20px);}
.hcard-head{display:flex;align-items:center;gap:8px;margin-bottom:12px;}
.hcard-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;}
.hcard-lbl{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.hcard-val{font-size:24px;font-weight:800;color:var(--text);line-height:1;}
.hcard-sub{font-size:11px;color:var(--muted);margin-top:4px;}

/* PROGRESS */
.prog-bar{height:7px;background:rgba(58,97,134,.12);border-radius:99px;overflow:hidden;margin-top:10px;}
.prog-fill{height:100%;border-radius:99px;transition:width .6s ease;}
.fill-blue{background:linear-gradient(90deg,var(--s1),var(--s3));}
.fill-green{background:linear-gradient(90deg,#15803d,#22c55e);}
.fill-warn{background:linear-gradient(90deg,#c2410c,#f59e0b);}
.fill-danger{background:linear-gradient(90deg,#b91c1c,#ef4444);}

/* TABLE */
.tbl-wrap{overflow-x:auto;border-radius:12px;border:1.5px solid rgba(58,97,134,0.08);}
table.stbl{width:100%;border-collapse:collapse;font-size:13px;}
table.stbl th{font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:1.5px solid rgba(58,97,134,0.08);background:rgba(255,255,255,0.40);white-space:nowrap;}
table.stbl td{padding:9px 14px;border-bottom:1px solid rgba(58,97,134,0.05);color:var(--text);vertical-align:middle;}
table.stbl tr:last-child td{border-bottom:none;}
table.stbl tr:hover td{background:rgba(58,97,134,0.02);}

/* SIZE BAR */
.size-bar{display:flex;align-items:center;gap:8px;}
.size-track{flex:1;height:6px;background:rgba(58,97,134,0.08);border-radius:3px;overflow:hidden;}
.size-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--s1),var(--s3));}
.size-val{font-size:10px;font-weight:800;color:var(--s1);min-width:55px;text-align:right;}

/* BADGES */
.badge-sm{font-size:9px;font-weight:900;padding:2px 8px;border-radius:100px;text-transform:uppercase;}
.badge-green{background:rgba(21,128,61,0.10);color:#15803d;border:1px solid rgba(21,128,61,0.22);}
.badge-blue{background:rgba(58,97,134,0.10);color:#3a6186;border:1px solid rgba(58,97,134,0.22);}
.badge-purple{background:rgba(124,58,237,0.10);color:#7c3aed;border:1px solid rgba(124,58,237,0.22);}
.badge-gold{background:rgba(212,160,23,0.10);color:#d4a017;border:1px solid rgba(212,160,23,0.22);}

/* AVATAR */
.av-xs{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--s1),var(--s3));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;color:#fff;font-family:'Syne',sans-serif;flex-shrink:0;}

/* INFO ROWS */
.info-item{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(58,97,134,0.06);}
.info-item:last-child{border-bottom:none;}
.info-label{font-size:12px;font-weight:700;color:var(--muted);display:flex;align-items:center;gap:6px;}
.info-label i{font-size:11px;color:var(--s1);width:16px;text-align:center;}
.info-value{font-size:12px;font-weight:800;color:var(--text);}

/* FORMS */
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:10px;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px;}
.form-group input,.form-group textarea{width:100%;background:rgba(255,255,255,0.82);border:1.5px solid rgba(255,255,255,0.90);border-radius:12px;padding:10px 16px;font-size:13px;font-family:'Nunito',sans-serif;color:var(--text);outline:none;transition:border-color .2s;}
.form-group input:focus,.form-group textarea:focus{border-color:rgba(58,97,134,0.35);box-shadow:0 0 0 3px rgba(58,97,134,0.08);}
.form-group textarea{resize:vertical;min-height:80px;}

.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:100px;background:linear-gradient(135deg,var(--s1),var(--s3));color:#fff;font-size:13px;font-weight:900;border:none;cursor:pointer;font-family:'Nunito',sans-serif;box-shadow:0 4px 16px rgba(58,97,134,0.30);transition:all .2s;}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(58,97,134,0.40);}
.btn-outline{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:100px;background:var(--card);border:1.5px solid rgba(58,97,134,0.25);color:var(--s1);font-size:13px;font-weight:900;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .2s;text-decoration:none;}
.btn-outline:hover{background:rgba(58,97,134,0.08);transform:translateY(-1px);}
.btn-danger-sm{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:100px;background:rgba(185,28,28,0.10);border:1.5px solid rgba(185,28,28,0.25);color:var(--danger);font-size:12px;font-weight:800;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .2s;}
.btn-danger-sm:hover{background:rgba(185,28,28,0.18);}

/* SWITCH */
.switch-wrap{display:flex;align-items:center;gap:12px;}
.switch-toggle{position:relative;display:inline-block;width:44px;height:24px;}
.switch-toggle input{opacity:0;width:0;height:0;}
.slider{position:absolute;cursor:pointer;inset:0;background:rgba(58,97,134,0.20);border-radius:24px;transition:.3s;}
.slider:before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:3px;bottom:3px;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,0.15);}
input:checked+.slider{background:var(--s1);}
input:checked+.slider:before{transform:translateX(20px);}
.switch-label{font-size:13px;color:var(--text);font-weight:600;}

/* NOTIF TEMPLATE */
.notif-card{border:1.5px solid rgba(58,97,134,0.12);border-radius:14px;padding:16px;margin-bottom:12px;background:rgba(255,255,255,0.50);transition:box-shadow .2s;}
.notif-card:hover{box-shadow:var(--sh);}
.notif-head{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
.notif-ico{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;}
.notif-title{font-size:13px;font-weight:700;color:var(--text);}
.var-chip{display:inline-block;padding:2px 8px;border-radius:5px;background:rgba(58,97,134,0.10);color:var(--s1);font-size:11px;font-family:monospace;margin:2px;}

/* DANGER ZONE */
.danger-zone{border:2px solid rgba(185,28,28,0.20);border-radius:18px;padding:20px 24px;background:rgba(185,28,28,0.03);margin-bottom:20px;}
.dz-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:900;color:var(--danger);margin-bottom:14px;display:flex;align-items:center;gap:7px;}
.dz-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(185,28,28,0.08);gap:20px;flex-wrap:wrap;}
.dz-item:last-child{border-bottom:none;}
.dz-info-title{font-size:13px;font-weight:700;color:var(--text);}
.dz-info-desc{font-size:12px;color:var(--muted);margin-top:2px;}

/* PW STRENGTH */
#pw-strength{margin-bottom:12px;}

footer.sa-foot{position:relative;z-index:1;text-align:center;padding:20px;font-size:12px;color:var(--muted);font-weight:700;background:transparent;border-top:1.5px dashed rgba(58,97,134,0.15);margin-top:24px;}

@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.fu1{animation:fadeUp .4s ease .04s both}.fu2{animation:fadeUp .4s ease .12s both}
</style>

<!-- PAGE HEADER -->
<div class="page-header fu1">
  <div class="page-header-left">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <h2><i class="fas fa-cog"></i> Pengaturan Sistem</h2>
  </div>
  <div class="ph-sub"><i class="fas fa-server" style="font-size:10px;"></i> System Control Panel &middot; PHP <?= $php_version ?></div>
</div>

<?php if ($success_msg): ?>
<div class="alert-box alert-success fu1"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div class="alert-box alert-error fu1"><i class="fas fa-exclamation-circle"></i> <?= e($error_msg) ?></div>
<?php endif; ?>

<!-- TAB NAV -->
<div class="tab-nav fu2">
  <button class="tab-btn active" onclick="showTab('health')" id="tab-health"><i class="fas fa-heartbeat"></i> System Health</button>
  <button class="tab-btn" onclick="showTab('database')" id="tab-database"><i class="fas fa-database"></i> Database Inspector</button>
  <button class="tab-btn" onclick="showTab('security')" id="tab-security"><i class="fas fa-shield-alt"></i> Security Center</button>
  <button class="tab-btn" onclick="showTab('backup')" id="tab-backup"><i class="fas fa-download"></i> Backup & Restore</button>
  <button class="tab-btn" onclick="showTab('notif')" id="tab-notif"><i class="fas fa-bell"></i> Notification Center</button>
</div>

<!-- ══════ PANEL 1: SYSTEM HEALTH ══════ -->
<div id="panel-health" class="panel active">
  <div class="g4" style="margin-bottom:20px;">
    <?php $hcards = [
      ['fa-microchip','PHP Memory','rgba(58,97,134,0.12)','#3a6186',$php_mem_used.' MB','Peak: '.$php_mem_peak.' MB / Limit: '.$php_memory, min(100,round($php_mem_used/(int)$php_memory*100,1)), 'fill-blue'],
      ['fa-hdd','Disk Space','rgba(21,128,61,0.12)','#15803d',$disk_free.' GB Free','Total: '.$disk_total.' GB ('.$disk_pct.'% used)', $disk_pct, $disk_pct>80?'fill-danger':($disk_pct>60?'fill-warn':'fill-green')],
      ['fa-exchange-alt','Request Hari Ini','rgba(212,160,23,0.12)','#d4a017',number_format($req_today),'Activity log entries', min(100,round($req_today/5)), 'fill-warn'],
      ['fa-server','Server Uptime','rgba(124,58,237,0.12)','#7c3aed',$uptime_raw ?: 'N/A',$server_software, 0, ''],
    ]; foreach($hcards as [$ico,$lbl,$bg,$col,$val,$sub,$pct,$pcls]): ?>
    <div class="hcard">
      <div class="hcard-head"><div class="hcard-ico" style="background:<?=$bg?>;color:<?=$col?>;"><i class="fas <?=$ico?>"></i></div><div class="hcard-lbl"><?=$lbl?></div></div>
      <div class="hcard-val" style="<?=$lbl==='Server Uptime'?'font-size:14px;line-height:1.4;':''?>"><?=$val?></div>
      <div class="hcard-sub"><?=$sub?></div>
      <?php if($pct > 0): ?><div class="prog-bar"><div class="prog-fill <?=$pcls?>" style="width:<?=$pct?>%"></div></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="g2">
    <div class="card">
      <div class="card-title"><i class="fas fa-info-circle"></i> Server Environment</div>
      <?php $env=[
        ['fa-code','PHP Version','PHP '.$php_version],['fa-database','MySQL Version',$mysql_version],
        ['fa-clock','Max Execution',ini_get('max_execution_time').'s'],['fa-upload','Upload Max',ini_get('upload_max_filesize')],
        ['fa-globe','Timezone',date_default_timezone_get()],['fa-calendar-alt','Server Time',date('d M Y H:i:s')],
      ]; foreach($env as [$ico,$lbl,$val]): ?>
      <div class="info-item"><div class="info-label"><i class="fas <?=$ico?>"></i> <?=$lbl?></div><div class="info-value"><?=e($val)?></div></div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-title"><i class="fas fa-puzzle-piece"></i> PHP Extensions</div>
      <?php $exts=['pdo','pdo_mysql','mysqli','mbstring','openssl','json','curl','zip','gd','session'];
      foreach($exts as $ext): $ok=extension_loaded($ext); ?>
      <div class="info-item">
        <div class="info-label"><i class="fas fa-cube"></i> <?=$ext?></div>
        <div class="info-value"><?php if($ok): ?><span class="badge-sm badge-green"><i class="fas fa-check"></i> Aktif</span><?php else: ?><span class="badge-sm" style="background:rgba(185,28,28,0.10);color:#b91c1c;border:1px solid rgba(185,28,28,0.22);"><i class="fas fa-times"></i> N/A</span><?php endif; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══════ PANEL 2: DATABASE INSPECTOR ══════ -->
<div id="panel-database" class="panel">
  <div class="g3" style="margin-bottom:20px;">
    <?php $dbcards=[
      ['fa-table','#3a6186','rgba(58,97,134,0.12)',count($db_tables),'Total Tabel'],
      ['fa-list-ol','#15803d','rgba(21,128,61,0.12)',number_format($total_records),'Total Records'],
      ['fa-weight-hanging','#d4a017','rgba(212,160,23,0.12)',$db_size_mb.' MB','Database Size'],
    ]; foreach($dbcards as [$ico,$col,$bg,$val,$lbl]): ?>
    <div class="hcard" style="text-align:center;">
      <div style="display:inline-flex;"><div class="hcard-ico" style="background:<?=$bg?>;color:<?=$col?>;margin:0 auto 10px;"><i class="fas <?=$ico?>"></i></div></div>
      <div class="hcard-val" style="color:<?=$col?>;"><?=$val?></div>
      <div class="hcard-sub"><?=$lbl?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-title"><i class="fas fa-layer-group"></i> Detail Tabel <span class="card-subtitle"><span class="badge-sm badge-blue"><?=$db_name_q?></span></span></div>
    <div class="tbl-wrap">
      <table class="stbl">
        <thead><tr><th>Tabel</th><th>Records</th><th>Size</th><th>Engine</th><th>Last Update</th></tr></thead>
        <tbody>
          <?php $max_sz=max(array_column($db_tables,'size_mb')?:[0.001]);
          foreach($db_tables as $t): $pct=round((float)$t['size_mb']/max($max_sz,0.001)*100); ?>
          <tr>
            <td style="font-weight:800;color:var(--s1);"><?=e($t['TABLE_NAME'])?></td>
            <td style="font-family:'Syne',sans-serif;font-weight:900;"><?=number_format($t['TABLE_ROWS'])?></td>
            <td><div class="size-bar"><div class="size-track"><div class="size-fill" style="width:<?=$pct?>%"></div></div><div class="size-val"><?=$t['size_mb']?> MB</div></div></td>
            <td><span class="badge-sm badge-green"><?=$t['ENGINE']?></span></td>
            <td style="font-size:11px;color:var(--muted);"><?=$t['UPDATE_TIME']?date('d M Y H:i',strtotime($t['UPDATE_TIME'])):'N/A'?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══════ PANEL 3: SECURITY CENTER ══════ -->
<div id="panel-security" class="panel">
  <div class="g3" style="margin-bottom:20px;">
    <?php $sccards=[
      ['fa-sign-in-alt','#3a6186','rgba(58,97,134,0.12)',$login_today,'Login Hari Ini'],
      ['fa-calendar-week','#15803d','rgba(21,128,61,0.12)',$login_week,'Login 7 Hari'],
      ['fa-network-wired','#7c3aed','rgba(124,58,237,0.12)',$unique_ips,'Unique IPs (7d)'],
    ]; foreach($sccards as [$ico,$col,$bg,$val,$lbl]): ?>
    <div class="hcard" style="text-align:center;">
      <div style="display:inline-flex;"><div class="hcard-ico" style="background:<?=$bg?>;color:<?=$col?>;margin:0 auto 10px;"><i class="fas <?=$ico?>"></i></div></div>
      <div class="hcard-val" style="color:<?=$col?>;"><?=$val?></div>
      <div class="hcard-sub"><?=$lbl?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="g2">
    <div class="card">
      <div class="card-title"><i class="fas fa-key"></i> Ganti Password</div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group"><label>Password Lama</label><input type="password" name="old_password" required placeholder="Masukkan password lama"></div>
        <div class="form-group"><label>Password Baru</label><input type="password" name="new_password" id="np" required placeholder="Min. 6 karakter" minlength="6"></div>
        <div id="pw-strength"></div>
        <div class="form-group"><label>Konfirmasi Password Baru</label><input type="password" name="confirm_password" required placeholder="Ulangi password baru"></div>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Simpan Password</button>
      </form>
    </div>
    <div class="card">
      <div class="card-title"><i class="fas fa-user-shield"></i> Akun Super Admin</div>
      <?php $acc=[
        ['fa-user','Nama',e($sa_data['nama']??'')],['fa-envelope','Email',e($sa_data['email']??'')],
        ['fa-crown','Role','Super Admin'],['fa-calendar','Terdaftar',isset($sa_data['created_at'])?formatTanggal($sa_data['created_at']):'N/A'],
        ['fa-clock','Last Login',$last_login_data?date('d M Y, H:i',strtotime($last_login_data['created_at'])):'N/A'],
        ['fa-map-marker-alt','Last IP',e($last_login_data['ip_address']??'N/A')],
      ]; foreach($acc as [$ico,$lbl,$val]): ?>
      <div class="info-item"><div class="info-label"><i class="fas <?=$ico?>"></i> <?=$lbl?></div><div class="info-value"><?=$val?></div></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><i class="fas fa-history"></i> Riwayat Login Terbaru <span class="card-subtitle">10 terakhir</span></div>
    <div class="tbl-wrap">
      <table class="stbl">
        <thead><tr><th>User</th><th>Role</th><th>IP</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php foreach($login_history as $rl): $rb=match($rl['role']){'super_admin'=>'badge-gold','admin'=>'badge-blue',default=>'badge-purple'}; ?>
          <tr>
            <td><div style="display:flex;align-items:center;gap:8px;"><div class="av-xs"><?=strtoupper(substr($rl['nama'],0,1))?></div><div><div style="font-weight:800;font-size:12px;"><?=e($rl['nama'])?></div><div style="font-size:10px;color:var(--muted);"><?=e($rl['email'])?></div></div></div></td>
            <td><span class="badge-sm <?=$rb?>"><?=$rl['role']?></span></td>
            <td style="font-size:11px;color:var(--muted);"><?=e($rl['ip_address']??'N/A')?></td>
            <td style="font-size:11px;"><?=date('d M Y',strtotime($rl['created_at']))?> <span style="color:var(--s1);font-weight:800;"><?=date('H:i:s',strtotime($rl['created_at']))?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($login_history)): ?><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--muted);">Belum ada riwayat login.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="danger-zone">
    <div class="dz-title"><i class="fas fa-exclamation-triangle"></i> Danger Zone</div>
    <div class="dz-item">
      <div><div class="dz-info-title">Force Logout Semua User</div><div class="dz-info-desc">Paksa semua user logout. Mereka harus login ulang.</div></div>
      <form method="POST" onsubmit="return confirm('Yakin force logout semua user?')"><input type="hidden" name="action" value="force_logout"><button type="submit" class="btn-danger-sm"><i class="fas fa-power-off"></i> Force Logout</button></form>
    </div>
    <div class="dz-item">
      <div><div class="dz-info-title">Reset Semua Poin User</div><div class="dz-info-desc">Set poin semua mahasiswa ke 0. Tidak bisa dibatalkan.</div></div>
      <form method="POST" onsubmit="return confirm('YAKIN reset semua poin? Tidak bisa dibatalkan!')"><input type="hidden" name="action" value="reset_points"><button type="submit" class="btn-danger-sm"><i class="fas fa-redo"></i> Reset Poin</button></form>
    </div>
    <div class="dz-item">
      <div><div class="dz-info-title">Hapus Semua Log Aktivitas</div><div class="dz-info-desc">Bersihkan seluruh activity_log. Data tidak bisa dipulihkan.</div></div>
      <form method="POST" onsubmit="return confirm('YAKIN hapus semua log?')"><input type="hidden" name="action" value="clear_logs"><button type="submit" class="btn-danger-sm"><i class="fas fa-trash-alt"></i> Hapus Log</button></form>
    </div>
  </div>
</div>

<!-- ══════ PANEL 4: BACKUP & RESTORE ══════ -->
<div id="panel-backup" class="panel">
  <div class="g2" style="margin-bottom:20px;">
    <div class="card">
      <div class="card-title"><i class="fas fa-download"></i> Export Database</div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:14px;line-height:1.7;">Download backup lengkap database <strong><?=$db_name_q?></strong> dalam format SQL. File berisi semua tabel, struktur, dan data. Disarankan backup sebelum perubahan besar.</p>
      <div class="g2" style="margin-bottom:16px;">
        <div class="info-item"><div class="info-label"><i class="fas fa-database"></i> Database</div><div class="info-value"><?=$db_name_q?></div></div>
        <div class="info-item"><div class="info-label"><i class="fas fa-table"></i> Tabel</div><div class="info-value"><?=count($db_tables)?></div></div>
        <div class="info-item"><div class="info-label"><i class="fas fa-list-ol"></i> Records</div><div class="info-value"><?=number_format($total_records)?></div></div>
        <div class="info-item"><div class="info-label"><i class="fas fa-weight-hanging"></i> Size</div><div class="info-value"><?=$db_size_mb?> MB</div></div>
      </div>
      <a href="?export=sql" class="btn-primary" onclick="return confirm('Download backup database sekarang?')"><i class="fas fa-download"></i> Download Backup SQL</a>
    </div>
    <div class="card">
      <div class="card-title"><i class="fas fa-info-circle"></i> Panduan Backup</div>
      <div style="font-size:12px;color:var(--muted);line-height:1.8;">
        <p style="margin-bottom:12px;"><strong style="color:var(--text);">Kapan harus backup?</strong></p>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;"><i class="fas fa-check-circle" style="color:var(--success);margin-top:2px;font-size:11px;"></i> Sebelum menghapus data massal (reset poin, hapus log)</div>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;"><i class="fas fa-check-circle" style="color:var(--success);margin-top:2px;font-size:11px;"></i> Sebelum update atau migrasi sistem</div>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;"><i class="fas fa-check-circle" style="color:var(--success);margin-top:2px;font-size:11px;"></i> Minimal 1x seminggu untuk data penting</div>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:16px;"><i class="fas fa-check-circle" style="color:var(--success);margin-top:2px;font-size:11px;"></i> Setelah input data besar (import buku baru)</div>
        <p style="margin-bottom:12px;"><strong style="color:var(--text);">Cara restore:</strong></p>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;"><i class="fas fa-arrow-right" style="color:var(--s1);margin-top:2px;font-size:11px;"></i> Buka phpMyAdmin</div>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;"><i class="fas fa-arrow-right" style="color:var(--s1);margin-top:2px;font-size:11px;"></i> Pilih database <?=$db_name_q?></div>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;"><i class="fas fa-arrow-right" style="color:var(--s1);margin-top:2px;font-size:11px;"></i> Klik tab Import, pilih file .sql</div>
        <div style="display:flex;align-items:flex-start;gap:8px;"><i class="fas fa-arrow-right" style="color:var(--s1);margin-top:2px;font-size:11px;"></i> Klik Go, tunggu selesai</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><i class="fas fa-history"></i> Riwayat Backup <span class="card-subtitle">10 terakhir</span></div>
    <div class="tbl-wrap">
      <table class="stbl">
        <thead><tr><th>User</th><th>Detail</th><th>IP</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php foreach($backup_history as $bh): ?>
          <tr>
            <td><div style="display:flex;align-items:center;gap:8px;"><div class="av-xs"><?=strtoupper(substr($bh['nama'],0,1))?></div><div style="font-weight:800;font-size:12px;"><?=e($bh['nama'])?></div></div></td>
            <td style="font-size:12px;color:var(--muted);"><?=e($bh['detail'])?></td>
            <td style="font-size:11px;color:var(--muted);"><?=e($bh['ip_address']??'N/A')?></td>
            <td style="font-size:11px;"><?=date('d M Y',strtotime($bh['created_at']))?> <span style="color:var(--s1);font-weight:800;"><?=date('H:i',strtotime($bh['created_at']))?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($backup_history)): ?><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--muted);">Belum pernah backup. Klik tombol di atas untuk backup pertama!</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══════ PANEL 5: NOTIFICATION CENTER ══════ -->
<div id="panel-notif" class="panel">
  <form method="POST">
    <input type="hidden" name="action" value="save_notif">

    <div class="card" style="margin-bottom:16px;">
      <div class="card-title"><i class="fas fa-robot"></i> Auto Notification Rules</div>
      <div class="switch-wrap">
        <label class="switch-toggle"><input type="checkbox" name="notif_auto_reminder" <?=$notif_auto==='1'?'checked':''?>><span class="slider"></span></label>
        <span class="switch-label">Aktifkan Auto-Reminder H-1 Jatuh Tempo</span>
      </div>
      <p style="font-size:12px;color:var(--muted);margin-top:8px;margin-left:56px;">Sistem otomatis kirim notifikasi ke peminjam 1 hari sebelum batas waktu pengembalian.</p>
    </div>

    <div class="card">
      <div class="card-title"><i class="fas fa-file-alt"></i> Template Notifikasi</div>
      <p style="font-size:12px;color:var(--muted);margin-bottom:14px;">Gunakan variabel untuk template dinamis:</p>
      <div style="margin-bottom:16px;"><span class="var-chip">{nama}</span><span class="var-chip">{judul}</span><span class="var-chip">{tanggal}</span><span class="var-chip">{denda}</span><span class="var-chip">{email}</span></div>

      <div class="notif-card">
        <div class="notif-head"><div class="notif-ico" style="background:rgba(212,160,23,0.12);color:var(--gold);"><i class="fas fa-clock"></i></div><div class="notif-title">Reminder Jatuh Tempo (H-1)</div></div>
        <textarea name="notif_due_reminder" class="form-control" style="border-radius:10px;"><?=htmlspecialchars($notif_due)?></textarea>
      </div>
      <div class="notif-card">
        <div class="notif-head"><div class="notif-ico" style="background:rgba(185,28,28,0.12);color:var(--danger);"><i class="fas fa-exclamation-circle"></i></div><div class="notif-title">Notif Buku Terlambat / Overdue</div></div>
        <textarea name="notif_overdue" class="form-control" style="border-radius:10px;"><?=htmlspecialchars($notif_overdue)?></textarea>
      </div>
      <div class="notif-card">
        <div class="notif-head"><div class="notif-ico" style="background:rgba(21,128,61,0.12);color:var(--success);"><i class="fas fa-user-plus"></i></div><div class="notif-title">Selamat Datang User Baru</div></div>
        <textarea name="notif_welcome" class="form-control" style="border-radius:10px;"><?=htmlspecialchars($notif_welcome)?></textarea>
      </div>

      <div style="margin-top:16px;"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> Simpan Template</button></div>
    </div>
  </form>
</div>

<footer class="sa-foot">
  <i class="fas fa-cloud" style="color:var(--s1);margin-right:5px;"></i>
  <strong style="color:var(--s1);">CloudLibrary Mini</strong>
  <span style="margin:0 8px;color:rgba(58,97,134,0.15);">|</span>
  Sistem Perpustakaan Digital Berbasis Cloud Computing &copy; <?= date('Y') ?>
</footer>

<script>
function showTab(name) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
  document.getElementById('tab-' + name).classList.add('active');
  sessionStorage.setItem('sa_settings_tab', name);
}
(function(){ const t = sessionStorage.getItem('sa_settings_tab'); if(t) showTab(t); })();

const npInput = document.getElementById('np');
const pwStr = document.getElementById('pw-strength');
if(npInput){npInput.addEventListener('input',function(){
  const v=this.value;let s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const l=['','Lemah','Cukup','Baik','Kuat','Sangat Kuat'];
  const c=['','#b91c1c','#c2410c','#d4a017','#15803d','#0d9488'];
  const p=[0,20,40,60,80,100];
  if(!v.length){pwStr.innerHTML='';return;}
  pwStr.innerHTML=`<div style="display:flex;align-items:center;gap:8px;font-size:12px;color:${c[s]};"><div style="flex:1;height:5px;background:rgba(58,97,134,.12);border-radius:99px;overflow:hidden;"><div style="width:${p[s]}%;height:100%;background:${c[s]};border-radius:99px;transition:all .3s;"></div></div><span style="font-weight:700;width:90px;">${l[s]}</span></div>`;
});}
</script>
</body>
</html>