<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$stats = $exchange->publicStats();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Statistics</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container" style="max-width:780px;">
    <div class="card">
        <h1>Public statistics</h1>
        <p class="small"><a href="/login.php">Login</a></p>
    </div>
    <div class="card">
        <ul>
            <li>Users: <?= $stats['users'] ?></li>
            <li>Banners: <?= $stats['banners'] ?></li>
            <li>Impressions: <?= $stats['impressions'] ?></li>
            <li>Clicks: <?= $stats['clicks'] ?></li>
        </ul>
    </div>
    <div class="card">
        <h2>Toplist</h2>
        <ol><?php foreach ($stats['top'] as $row): ?><li><?= htmlspecialchars($row['username']) ?> - <?= (int) $row['credits'] ?></li><?php endforeach; ?></ol>
    </div>
</div>
</body>
</html>
