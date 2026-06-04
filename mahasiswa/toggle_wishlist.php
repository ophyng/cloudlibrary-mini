<?php
// ============================================
//  CloudLibrary Mini — Toggle Wishlist
//  File   : mahasiswa/toggle_wishlist.php
// ============================================
session_start();
require_once '../includes/functions.php';
cekLoginMahasiswa();

$user_id = $_SESSION['user_id'];
$buku_id = (int)($_POST['buku_id'] ?? 0);

// Ambil URL referer untuk redirect balik ke halaman asal
$redirect = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/mahasiswa/katalog.php';

if (!$buku_id) {
    header('Location: ' . $redirect);
    exit;
}

// Cek apakah buku ada
$cek_buku = $pdo->prepare("SELECT id FROM buku WHERE id = ?");
$cek_buku->execute([$buku_id]);
if (!$cek_buku->fetch()) {
    header('Location: ' . $redirect);
    exit;
}

// Cek apakah sudah di wishlist
$cek = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND buku_id = ?");
$cek->execute([$user_id, $buku_id]);
$existing = $cek->fetch();

if ($existing) {
    // Hapus dari wishlist
    $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND buku_id = ?")
        ->execute([$user_id, $buku_id]);
} else {
    // Tambah ke wishlist
    $pdo->prepare("INSERT INTO wishlist (user_id, buku_id) VALUES (?, ?)")
        ->execute([$user_id, $buku_id]);
}

// Redirect balik ke halaman sebelumnya
header('Location: ' . $redirect);
exit;