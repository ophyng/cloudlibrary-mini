<?php
$repo = 'https://raw.githubusercontent.com/ophyng/cloudlibrary-mini/main/';
$owner = 'ophyng';
$repoName = 'cloudlibrary-mini';
$branch = 'main';

$jobs = [
    'ebooks'         => '/var/www/html/ebooks/',
    'uploads/covers' => '/var/www/html/uploads/covers/',
];

// Buat folder dengan cara berbeda
foreach ($jobs as $dest) {
    if (!is_dir($dest)) {
        system("mkdir -p " . escapeshellarg($dest));
        system("chmod 777 " . escapeshellarg($dest));
        system("chown www-data:www-data " . escapeshellarg($dest));
    }
    echo is_dir($dest) ? "✅ Folder ada: $dest<br>" : "❌ Folder gagal: $dest<br>";
    echo is_writable($dest) ? "✅ Writable: $dest<br>" : "❌ Not writable: $dest<br>";
}

$total = 0;
$errors = [];
$ctx = stream_context_create(['http' => ['header' => 'User-Agent: PHP']]);

foreach ($jobs as $path => $dest) {
    $api = "https://api.github.com/repos/$owner/$repoName/contents/$path?ref=$branch";
    $json = file_get_contents($api, false, $ctx);
    $files = json_decode($json, true);
    if (!is_array($files)) { $errors[] = "Gagal list: $path"; continue; }
    foreach ($files as $f) {
        if ($f['type'] !== 'file') continue;
        $content = file_get_contents($f['download_url'], false, $ctx);
        if ($content === false) { $errors[] = "Gagal download: " . $f['name']; continue; }
        if (file_put_contents($dest . $f['name'], $content) !== false) {
            echo "✅ " . $f['name'] . "<br>"; $total++;
        } else {
            echo "❌ Gagal tulis: " . $f['name'] . "<br>"; $errors[] = $f['name'];
        }
        flush();
    }
}

echo "<br><strong>Selesai! $total file berhasil.</strong><br>";
foreach ($errors as $e) echo "❌ $e<br>";
?>
