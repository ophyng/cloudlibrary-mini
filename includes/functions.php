<?php
// ============================================
//  CloudLibrary Mini — Functions
//  File   : includes/functions.php
// ============================================

require_once __DIR__ . '/../config/db.php';

// ──────────────────────────────────────────
//  CEK LOGIN & REDIRECT OTOMATIS
// ──────────────────────────────────────────

function cekLoginMahasiswa() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit();
    }
}

function cekLoginAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit();
    }
}

// Redirect setelah login sesuai role
function redirectSetelahLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit();
    }
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "/mahasiswa/dashboard.php");
    }
    exit();
}

// Kalau sudah login, jangan bisa akses login/register lagi
function cekSudahLogin() {
    if (isset($_SESSION['user_id'])) {
        redirectSetelahLogin();
    }
}

// ──────────────────────────────────────────
//  FORMAT TANGGAL
// ──────────────────────────────────────────

function formatTanggal($tanggal) {
    if (!$tanggal) return '-';
    $bulan = [
        '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = date('d', strtotime($tanggal));
    $m = date('n', strtotime($tanggal));
    $y = date('Y', strtotime($tanggal));
    return "$d {$bulan[$m]} $y";
}

// ──────────────────────────────────────────
//  HITUNG SISA HARI
// ──────────────────────────────────────────

function sisaHari($tanggal_expired) {
    $today   = new DateTime(date('Y-m-d'));
    $expired = new DateTime($tanggal_expired);
    $diff    = $today->diff($expired);
    return $expired >= $today ? (int)$diff->days : -(int)$diff->days;
}

// ──────────────────────────────────────────
//  UPDATE STATUS PEMINJAMAN OTOMATIS
// ──────────────────────────────────────────

function updateStatusPeminjaman($pdo) {
    $pdo->exec("
        UPDATE peminjaman 
        SET status = 'expired' 
        WHERE tanggal_expired < CURDATE() 
          AND status NOT IN ('dikembalikan', 'expired')
    ");
    $pdo->exec("
        UPDATE peminjaman 
        SET status = 'hampir_habis' 
        WHERE tanggal_expired = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
          AND status = 'aktif'
    ");
    $pdo->exec("
        UPDATE buku b
        JOIN peminjaman p ON p.buku_id = b.id
        SET b.stok = b.stok + 1,
            b.status = 'tersedia'
        WHERE p.status = 'expired'
          AND p.tanggal_kembali IS NULL
    ");
    $pdo->exec("
        UPDATE peminjaman 
        SET tanggal_kembali = CURDATE()
        WHERE status = 'expired'
          AND tanggal_kembali IS NULL
    ");
}

// ──────────────────────────────────────────
//  POIN & BADGE
// ──────────────────────────────────────────

function tambahPoin($pdo, $user_id, $poin) {
    $stmt = $pdo->prepare("UPDATE users SET poin = poin + ? WHERE id = ?");
    $stmt->execute([$poin, $user_id]);
    cekBadge($pdo, $user_id);
}

function cekBadge($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT poin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) return;

    $badges = $pdo->query("SELECT * FROM badge ORDER BY syarat_poin ASC")->fetchAll();
    foreach ($badges as $badge) {
        if ($user['poin'] >= $badge['syarat_poin']) {
            $cek = $pdo->prepare("SELECT id FROM user_badge WHERE user_id = ? AND badge_id = ?");
            $cek->execute([$user_id, $badge['id']]);
            if (!$cek->fetch()) {
                $ins = $pdo->prepare("INSERT INTO user_badge (user_id, badge_id) VALUES (?, ?)");
                $ins->execute([$user_id, $badge['id']]);
                kirimNotifikasi($pdo, $user_id, "🏆 Kamu mendapat badge baru: {$badge['nama']}!", 'info');
            }
        }
    }
}

// ──────────────────────────────────────────
//  NOTIFIKASI
// ──────────────────────────────────────────

function kirimNotifikasi($pdo, $user_id, $pesan, $tipe = 'info') {
    $stmt = $pdo->prepare("INSERT INTO notifikasi (user_id, pesan, tipe) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $pesan, $tipe]);
}

function jumlahNotifBelumDibaca($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

// ──────────────────────────────────────────
//  RATING & BINTANG
// ──────────────────────────────────────────

function ratingRataRata($pdo, $buku_id) {
    $stmt = $pdo->prepare("SELECT AVG(rating) as rata FROM review WHERE buku_id = ? AND status = 'tampil'");
    $stmt->execute([$buku_id]);
    $result = $stmt->fetch();
    return $result['rata'] ? round($result['rata'], 1) : 0;
}

function tampilBintang($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($rating)
            ? '<i class="fas fa-star" style="color:#e8a838"></i>'
            : '<i class="far fa-star" style="color:#374151"></i>';
    }
    return $html;
}

// ──────────────────────────────────────────
//  CEK STATUS PINJAM USER
// ──────────────────────────────────────────

function cekPinjamanAktif($pdo, $user_id, $buku_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM peminjaman 
        WHERE user_id = ? AND buku_id = ? 
          AND status IN ('aktif', 'hampir_habis')
    ");
    $stmt->execute([$user_id, $buku_id]);
    return $stmt->fetch();
}

function cekDiWishlist($pdo, $user_id, $buku_id) {
    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND buku_id = ?");
    $stmt->execute([$user_id, $buku_id]);
    return $stmt->fetch();
}

function cekSudahReview($pdo, $user_id, $buku_id) {
    $stmt = $pdo->prepare("SELECT id FROM review WHERE user_id = ? AND buku_id = ?");
    $stmt->execute([$user_id, $buku_id]);
    return $stmt->fetch();
}

// ──────────────────────────────────────────
//  SANITASI OUTPUT
// ──────────────────────────────────────────

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}