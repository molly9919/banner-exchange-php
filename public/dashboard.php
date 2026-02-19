<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

App\Auth::requireUser();
$userId = App\Auth::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exchange->addBanner($userId, $_POST);
}

$pdo = $db->pdo();
$prefix = $config['db']['prefix'];
$user = $pdo->query('SELECT * FROM ' . $prefix . 'users WHERE id = ' . (int) $userId)->fetch();
$banners = $pdo->query('SELECT * FROM ' . $prefix . 'banners WHERE user_id = ' . (int) $userId . ' ORDER BY id DESC')->fetchAll();
$categories = $pdo->query('SELECT * FROM ' . $prefix . 'categories ORDER BY name ASC')->fetchAll();
?>
<!doctype html><html><body style="font-family: Arial; max-width: 960px; margin: 20px auto;">
<h1>User dashboard</h1>
<p>Krediti: <strong><?= (int) $user['credits'] ?></strong> | <a href="/logout.php">Logout</a></p>
<h2>Dodaj banner</h2>
<form method="post">
    <input name="size_key" placeholder="size_key (npr 468x60)" required>
    <select name="category_id"><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['size_key']) ?>)</option><?php endforeach; ?></select>
    <select name="type"><option>gif</option><option>jpg</option><option>png</option><option>swf</option><option value="html">html</option></select>
    <input name="image_url" placeholder="Image URL (ako nije html)">
    <textarea name="html_code" placeholder="HTML/Text oglas"></textarea>
    <input name="target_url" placeholder="Target URL">
    <input name="alt_text" placeholder="Alt text">
    <input name="countries" value="ALL" placeholder="BA,HR,RS ili ALL">
    <input name="allowed_days" value="1,2,3,4,5,6,7" placeholder="Dani 1-7">
    <input name="start_hour" value="0" placeholder="Start hour 0-23">
    <input name="end_hour" value="23" placeholder="End hour 0-23">
    <label><input type="checkbox" name="is_sponsored" value="1"> Sponsored</label>
    <input name="priority" value="0" placeholder="Priority">
    <button type="submit">Spasi banner</button>
</form>

<h2>Moji banneri</h2>
<table border="1" cellpadding="6" cellspacing="0">
<tr><th>ID</th><th>Size</th><th>Type</th><th>Target</th><th>Active</th></tr>
<?php foreach ($banners as $b): ?>
<tr>
<td><?= (int) $b['id'] ?></td>
<td><?= htmlspecialchars($b['size_key']) ?></td>
<td><?= htmlspecialchars($b['type']) ?></td>
<td><?= htmlspecialchars((string) $b['target_url']) ?></td>
<td><?= (int) $b['is_active'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
