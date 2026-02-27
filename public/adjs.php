<?php

declare(strict_types=1);

$size = preg_replace('/[^0-9x]/', '', (string) ($_GET['size'] ?? '468x60'));
$user = (int) ($_GET['user'] ?? 0);
$country = preg_replace('/[^A-Za-z,]/', '', (string) ($_GET['country'] ?? 'ALL'));

if ($user <= 0) {
    header('Content-Type: application/javascript; charset=UTF-8');
    echo 'console.warn("Banner Exchange: missing user id");';
    exit;
}

[$w, $h] = array_pad(array_map('intval', explode('x', $size)), 2, 0);
if ($w <= 0 || $h <= 0) {
    $w = 468; $h = 60; $size = '468x60';
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$src = $scheme . '://' . $host . '/serve.php?size=' . urlencode($size) . '&user=' . $user . '&country=' . urlencode($country);

header('Content-Type: application/javascript; charset=UTF-8');
?>
(function(){
  var iframe = document.createElement('iframe');
  iframe.src = <?= json_encode($src, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  iframe.width = <?= (int) $w ?>;
  iframe.height = <?= (int) $h ?>;
  iframe.scrolling = 'no';
  iframe.frameBorder = '0';
  iframe.setAttribute('loading', 'lazy');
  var s = document.currentScript;
  if (s && s.parentNode) {
    s.parentNode.insertBefore(iframe, s.nextSibling);
  } else {
    document.write(iframe.outerHTML);
  }
})();
