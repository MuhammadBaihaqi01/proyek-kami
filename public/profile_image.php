<?php
// profile_image.php
// Generate a circular profile image with initials from username

// Get username from query string
$name = isset($_GET['name']) ? $_GET['name'] : 'User';

// Ambil dua huruf pertama dari setiap kata (misal: "admin" => "AD", "john doe" => "JD")
$words = preg_split('/\s+/', trim($name));
$initials = '';
foreach ($words as $w) {
    if ($w !== '') {
        $initials .= strtoupper(substr($w, 0, 1));
    }
    if (strlen($initials) == 2) break;
}
if (strlen($initials) < 2) {
    $initials = strtoupper(substr($name, 0, 2));
}

// Image size
$size = 60;

// Create image with transparent background
$im = imagecreatetruecolor($size, $size);
imagesavealpha($im, true);
$trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $trans);

// Colors
$bg = imagecolorallocate($im, 33, 150, 243); // Blue
$white = imagecolorallocate($im, 255, 255, 255);

// Draw circle
imagefilledellipse($im, $size / 2, $size / 2, $size, $size, $bg);

// Set path to font (gunakan font sistem jika tidak ada arial.ttf)
$font = __DIR__ . '/assets/arial.ttf';
$fontSize = 20;
if (file_exists($font)) {
    $bbox = imagettfbbox($fontSize, 0, $font, $initials);
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7];
    $x = ($size - $textWidth) / 2;
    $y = ($size + $textHeight) / 2;
    imagettftext($im, $fontSize, 0, $x, $y, $white, $font, $initials);
} else {
    // fallback ke font built-in
    $fontWidth = imagefontwidth(5);
    $fontHeight = imagefontheight(5);
    $textWidth = $fontWidth * strlen($initials);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $fontHeight) / 2;
    imagestring($im, 5, $x, $y, $initials, $white);
}

// Output image
header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
imagepng($im);
imagedestroy($im);
