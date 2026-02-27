<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$size = $_GET['size'] ?? '468x60';
$userId = (int) ($_GET['user'] ?? 0);
$country = $_GET['country'] ?? 'ALL';

$banner = $exchange->pickBanner($size, $userId, $country);
if (!$banner) {
    echo '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#111;color:#ffd400;font:14px Arial">No banner available</div>';
    exit;
}

$clickUrl = '/click.php?token=' . urlencode($banner['event_token']);

if ($banner['type'] === 'html' && $banner['html_code']) {
    echo $banner['html_code'];
    exit;
}

$alt = htmlspecialchars((string) $banner['alt_text']);
$src = htmlspecialchars((string) $banner['image_url']);

echo '<a href="' . $clickUrl . '" target="_blank" rel="noopener">';
echo '<img src="' . $src . '" alt="' . $alt . '" style="max-width:100%;height:auto;border:0">';
echo '</a>';
