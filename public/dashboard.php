<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

App\Auth::requireUser();
$userId = App\Auth::userId();

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'add_banner');

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $res = $exchange->changePassword((int) $userId, $current, $new);
            if (!empty($res['ok'])) {
                $message = 'Password changed successfully.';
            } else {
                $error = (string) ($res['error'] ?? 'Could not change password.');
            }
        }
    }

    if ($action === 'add_banner') {
        if (isset($_FILES['banner_file']) && (int) ($_FILES['banner_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $name = (string) ($_FILES['banner_file']['name'] ?? '');
            $tmp = (string) ($_FILES['banner_file']['tmp_name'] ?? '');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['gif', 'jpg', 'jpeg', 'png', 'swf'];

            if (in_array($ext, $allowed, true) && is_uploaded_file($tmp)) {
                $uploadDir = __DIR__ . '/uploads/banners';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $target = $uploadDir . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
                if (move_uploaded_file($tmp, $target)) {
                    $_POST['image_url'] = '/uploads/banners/' . basename($target);
                    if (empty($_POST['type']) || $_POST['type'] === 'html') {
                        $_POST['type'] = $ext === 'swf' ? 'swf' : 'png';
                    }
                }
            }
        }

        $exchange->addBanner($userId, $_POST);
        $message = $message ?? 'Banner saved.';
    }
}

$pdo = $db->pdo();
$prefix = $config['db']['prefix'];
$user = $pdo->query('SELECT * FROM ' . $prefix . 'users WHERE id = ' . (int) $userId)->fetch();
$banners = $pdo->query('SELECT * FROM ' . $prefix . 'banners WHERE user_id = ' . (int) $userId . ' ORDER BY id DESC')->fetchAll();
$categories = $pdo->query('SELECT * FROM ' . $prefix . 'categories ORDER BY name ASC')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container">
    <div class="card">
        <h1>User dashboard</h1>
        <div class="top-nav">
            <span class="badge">Credits: <?= (int) $user['credits'] ?></span>
            <a href="/public_stats.php">Public stats</a>
            <a href="/logout.php">Logout</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form class="card" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_banner">
        <h2>Add banner</h2>
        <div class="inline">
            <input name="size_key" placeholder="size_key (e.g. 468x60)" required>
            <select name="category_id">
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['size_key']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="type"><option>gif</option><option>jpg</option><option>png</option><option>swf</option><option value="html">html</option></select>
        </div>
        <input name="image_url" placeholder="Image URL (for remote image banners)">
        <input type="file" name="banner_file" accept=".gif,.jpg,.jpeg,.png,.swf">
        <p class="small">You can upload a local banner file or use a remote Image URL.</p>
        <textarea name="html_code" placeholder="HTML/Text ad code"></textarea>
        <input name="target_url" placeholder="Target URL">
        <input name="alt_text" placeholder="Alt text">
        <div class="inline">
            <input name="countries" value="ALL" placeholder="Countries (BA,HR,RS or ALL)">
            <input name="allowed_days" value="1,2,3,4,5,6,7" placeholder="Weekdays 1-7">
            <input name="start_hour" value="0" placeholder="Start hour 0-23">
            <input name="end_hour" value="23" placeholder="End hour 0-23">
        </div>
        <div class="inline">
            <label><input type="checkbox" name="is_sponsored" value="1" style="width:auto"> Sponsored</label>
            <input name="priority" value="0" placeholder="Priority">
        </div>
        <button type="submit">Save banner</button>
    </form>


    <form class="card" method="post">
        <h2>Change my password</h2>
        <input type="hidden" name="action" value="change_password">
        <input type="password" name="current_password" placeholder="Current password" required>
        <input type="password" name="new_password" placeholder="New password" required>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        <button type="submit">Update password</button>
    </form>

    <div class="card table-wrap">
        <h2>My banners</h2>
        <table>
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
    </div>
</div>
</body>
</html>
