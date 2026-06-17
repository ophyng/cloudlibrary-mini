<?php
$repo = 'https://raw.githubusercontent.com/ophyng/cloudlibrary-mini/main/';

$jobs = [
    'ebooks'          => '/var/www/html/ebooks/',
    'uploads/covers'  => '/var/www/html/uploads/covers/',
    'uploads/foto_profil' => '/var/www/html/uploads/foto_profil/',
];

// Buat folder dulu
foreach ($jobs as $dest) {
    if (!is_dir($dest)) mkdir($dest, 0755, true);
}

// Ambil daftar file dari GitHub API
$owner = 'ophyng';
$repoName = 'cloudlibrary-mini';
$branch = 'main';

$total = 0;
$errors = [];

foreach ($jobs as $path => $dest) {
    $api = "https://api.github.com/repos/$owner/$repoName/contents/$path?ref=$branch";
    $ctx = stream_context_create(['http' => ['header' => 'User-Agent: PHP']]);
    $json = file_get_contents($api, false, $ctx);
    $files = json_decode($json, true);

    if (!is_array($files)) {
        $errors[] = "Gagal ambil list dari $path";
        continue;
    }

    foreach ($files as $f) {
        if ($f['type'] !== 'file') continue;
        $content = file_get_contents($f['download_url'], false, $ctx);
        if ($content === false) {
            $errors[] = "Gagal download: " . $f['name'];
            continue;
        }
        file_put_contents($dest . $f['name'], $content);
        $total++;
        echo "✅ " . $f['name'] . "<br>";
        flush();
    }
}

echo "<br><strong>Selesai! $total file berhasil di-restore.</strong><br>";
if ($errors) {
    echo "<br>Errors:<br>";
    foreach ($errors as $e) echo "❌ $e<br>";
}
?>
