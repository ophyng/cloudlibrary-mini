<?php
// ============================================
//  CloudLibrary Mini — Generate Default Covers
//  File : generate_covers.php (taruh di root, jalankan sekali)
//  Akan membuat folder uploads/covers/ berisi cover default per genre
// ============================================

$genres = [
    'Novel'    => ['bg1'=>'#1a237e','bg2'=>'#3949ab','icon'=>'📖','label'=>'NOVEL',    'accent'=>'#7986cb'],
    'Cerpen'   => ['bg1'=>'#4a148c','bg2'=>'#7b1fa2','icon'=>'📝','label'=>'CERPEN',   'accent'=>'#ce93d8'],
    'Fantasi'  => ['bg1'=>'#1b5e20','bg2'=>'#2e7d32','icon'=>'🧙','label'=>'FANTASI',  'accent'=>'#81c784'],
    'Romance'  => ['bg1'=>'#880e4f','bg2'=>'#c2185b','icon'=>'❤️','label'=>'ROMANCE',  'accent'=>'#f48fb1'],
    'Horror'   => ['bg1'=>'#b71c1c','bg2'=>'#c62828','icon'=>'👻','label'=>'HORROR',   'accent'=>'#ef9a9a'],
    'Misteri'  => ['bg1'=>'#e65100','bg2'=>'#f57c00','icon'=>'🕵️','label'=>'MISTERI',  'accent'=>'#ffcc80'],
    'Sci-Fi'   => ['bg1'=>'#006064','bg2'=>'#0097a7','icon'=>'🚀','label'=>'SCI-FI',   'accent'=>'#80deea'],
    'Filsafat' => ['bg1'=>'#37474f','bg2'=>'#546e7a','icon'=>'🏛️','label'=>'FILSAFAT', 'accent'=>'#b0bec5'],
    'Sains'    => ['bg1'=>'#0d47a1','bg2'=>'#1565c0','icon'=>'🔬','label'=>'SAINS',    'accent'=>'#90caf9'],
    'Biografi' => ['bg1'=>'#4e342e','bg2'=>'#6d4c41','icon'=>'📜','label'=>'BIOGRAFI', 'accent'=>'#bcaaa4'],
    'default'  => ['bg1'=>'#263238','bg2'=>'#37474f','icon'=>'📚','label'=>'BUKU',     'accent'=>'#b0bec5'],
];

$dir = __DIR__ . '/uploads/covers/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

foreach ($genres as $genre => $g) {
    $filename = $dir . 'cover_' . strtolower(str_replace([' ','-','/'], '_', $genre)) . '.svg';

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="420" viewBox="0 0 300 420">
  <defs>
    <linearGradient id="bg_{$genre}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$g['bg1']};stop-opacity:1" />
      <stop offset="100%" style="stop-color:{$g['bg2']};stop-opacity:1" />
    </linearGradient>
    <linearGradient id="shine_{$genre}" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" style="stop-color:rgba(255,255,255,0);stop-opacity:0" />
      <stop offset="50%" style="stop-color:rgba(255,255,255,0.08);stop-opacity:1" />
      <stop offset="100%" style="stop-color:rgba(255,255,255,0);stop-opacity:0" />
    </linearGradient>
  </defs>

  <!-- Background -->
  <rect width="300" height="420" fill="url(#bg_{$genre})" rx="8"/>

  <!-- Spine line kiri -->
  <rect x="0" y="0" width="18" height="420" fill="rgba(0,0,0,0.25)" rx="8 0 0 8"/>
  <rect x="18" y="0" width="2" height="420" fill="rgba(255,255,255,0.12)"/>

  <!-- Pattern dots -->
  <pattern id="dots_{$genre}" x="0" y="0" width="30" height="30" patternUnits="userSpaceOnUse">
    <circle cx="15" cy="15" r="1.5" fill="rgba(255,255,255,0.06)"/>
  </pattern>
  <rect x="20" y="0" width="280" height="420" fill="url(#dots_{$genre})"/>

  <!-- Shine overlay -->
  <rect x="20" y="0" width="280" height="420" fill="url(#shine_{$genre})"/>

  <!-- Top accent line -->
  <rect x="20" y="0" width="280" height="3" fill="{$g['accent']}" opacity="0.8"/>

  <!-- Circle deco besar di background -->
  <circle cx="240" cy="80" r="90" fill="rgba(255,255,255,0.04)"/>
  <circle cx="240" cy="80" r="65" fill="rgba(255,255,255,0.04)"/>

  <!-- Icon area -->
  <rect x="75" y="110" width="150" height="150" rx="20" fill="rgba(255,255,255,0.08)"/>
  <rect x="80" y="115" width="140" height="140" rx="16" fill="rgba(0,0,0,0.15)"/>

  <!-- Icon text (emoji) -->
  <text x="150" y="215" font-size="72" text-anchor="middle" dominant-baseline="middle"
        font-family="Apple Color Emoji, Segoe UI Emoji, Noto Color Emoji, sans-serif">{$g['icon']}</text>

  <!-- Genre label -->
  <rect x="50" y="295" width="200" height="32" rx="6" fill="rgba(255,255,255,0.12)"/>
  <text x="150" y="317" font-size="15" font-weight="900" text-anchor="middle"
        font-family="Arial, sans-serif" fill="rgba(255,255,255,0.9)"
        letter-spacing="4">{$g['label']}</text>

  <!-- CloudLibrary branding -->
  <text x="150" y="380" font-size="10" text-anchor="middle"
        font-family="Arial, sans-serif" fill="rgba(255,255,255,0.3)"
        letter-spacing="2">CLOUDLIBRARY MINI</text>

  <!-- Bottom accent line -->
  <rect x="20" y="417" width="280" height="3" fill="{$g['accent']}" opacity="0.8" rx="0 0 8 8"/>
</svg>
SVG;

    file_put_contents($filename, $svg);
    echo "✅ Cover dibuat: cover_" . strtolower(str_replace([' ','-','/'], '_', $genre)) . ".svg<br>";
}

echo "<br>🎉 Semua cover default berhasil dibuat di <strong>uploads/covers/</strong>!";
echo "<br>Hapus file ini setelah dijalankan.";
?>