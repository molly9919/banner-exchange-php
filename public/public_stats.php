<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$stats = $exchange->publicStats();
?>
<!doctype html><html><body style="font-family: Arial; max-width: 720px; margin: 20px auto;">
<h1>Public statistika</h1>
<ul>
<li>Korisnika: <?= $stats['users'] ?></li>
<li>Bannera: <?= $stats['banners'] ?></li>
<li>Impresija: <?= $stats['impressions'] ?></li>
<li>Klikova: <?= $stats['clicks'] ?></li>
</ul>
<h2>Toplist</h2>
<ol><?php foreach ($stats['top'] as $row): ?><li><?= htmlspecialchars($row['username']) ?> - <?= (int) $row['credits'] ?></li><?php endforeach; ?></ol>
</body></html>
