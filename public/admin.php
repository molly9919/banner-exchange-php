<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

App\Auth::requireAdmin();

$prefix = $config['db']['prefix'];
$pdo = $db->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('UPDATE ' . $prefix . 'settings SET value_text = ? WHERE key_name = ?');
    foreach (['global_exchange_ratio', 'exchange_mode', 'max_banners_per_user', 'bonus_per_impression'] as $key) {
        if (isset($_POST[$key])) {
            $stmt->execute([trim((string) $_POST[$key]), $key]);
        }
    }
}

$stats = $exchange->publicStats();
$settings = $exchange->settings();
?>
<!doctype html><html><body style="font-family: Arial; max-width: 960px; margin: 20px auto;">
<h1>Admin panel</h1>
<p><a href="/logout.php">Logout</a></p>
<ul>
<li>Users: <?= $stats['users'] ?></li>
<li>Banners: <?= $stats['banners'] ?></li>
<li>Impressions: <?= $stats['impressions'] ?></li>
<li>Clicks: <?= $stats['clicks'] ?></li>
</ul>
<h2>Global settings</h2>
<form method="post">
    <input name="global_exchange_ratio" value="<?= htmlspecialchars($settings['global_exchange_ratio'] ?? '1.00') ?>" placeholder="Ratio">
    <select name="exchange_mode">
        <?php $mode = $settings['exchange_mode'] ?? 'both'; ?>
        <option value="impressions" <?= $mode === 'impressions' ? 'selected' : '' ?>>Impressions only</option>
        <option value="clicks" <?= $mode === 'clicks' ? 'selected' : '' ?>>Clicks only</option>
        <option value="both" <?= $mode === 'both' ? 'selected' : '' ?>>Both</option>
    </select>
    <input name="max_banners_per_user" value="<?= htmlspecialchars($settings['max_banners_per_user'] ?? '10') ?>">
    <input name="bonus_per_impression" value="<?= htmlspecialchars($settings['bonus_per_impression'] ?? '1') ?>">
    <button type="submit">Save</button>
</form>
<h2>Toplist</h2>
<ol><?php foreach ($stats['top'] as $row): ?><li><?= htmlspecialchars($row['username']) ?> (<?= (int) $row['credits'] ?>)</li><?php endforeach; ?></ol>
</body></html>
