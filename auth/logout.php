<?php
// ============================================
//  CloudLibrary Mini — Logout
//  File   : auth/logout.php
// ============================================
session_start();

// ── Catat activity log sebelum logout ──
if (isset($_SESSION['user_id'])) {
    require_once '../config/db.php';
    try {
        $pdo->prepare("INSERT INTO activity_log (user_id, role, aksi, detail, ip_address) VALUES (?,?,?,?,?)")
            ->execute([
                $_SESSION['user_id'],
                $_SESSION['role'] ?? 'unknown',
                'Logout',
                'Logout dari sistem',
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
    } catch (Exception $e) { /* skip kalau tabel belum ada */ }
}

session_unset();
session_destroy();

// Hapus cookie kalau ada
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header("Location: ../auth/login.php?logout=1");
exit();
?>