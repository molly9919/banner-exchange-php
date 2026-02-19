<?php

declare(strict_types=1);

$error = null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? '127.0.0.1');
    $port = (int) ($_POST['port'] ?? 3306);
    $dbName = trim($_POST['name'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = (string) ($_POST['pass'] ?? '');
    $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['prefix'] ?? 'bx_');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@example.com');
    $adminPass = (string) ($_POST['admin_pass'] ?? 'admin123');

    try {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName), $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $schema = file_get_contents(__DIR__ . '/../sql/schema.sql');
        $schema = str_replace('{prefix}', $prefix, $schema);

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $query) {
            $pdo->exec($query);
        }

        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO ' . $prefix . 'users (email, username, password_hash, timezone, country_code, is_admin, is_approved, agree_rules, created_at) VALUES (?, ?, ?, "UTC", "ALL", 1, 1, 1, NOW())');
        $stmt->execute([$adminEmail, $adminUser, $passwordHash]);

        $settings = [
            'global_exchange_ratio' => '1.00',
            'exchange_mode' => 'both',
            'max_banners_per_user' => '10',
            'bonus_per_impression' => '1',
        ];

        $sStmt = $pdo->prepare('INSERT INTO ' . $prefix . 'settings (key_name, value_text) VALUES (?, ?)');
        foreach ($settings as $key => $value) {
            $sStmt->execute([$key, $value]);
        }

        $pdo->exec('INSERT INTO ' . $prefix . 'banner_sizes (size_key, width, height, exchange_ratio, max_banners_per_user) VALUES ("468x60",468,60,1.00,10),("728x90",728,90,1.00,10),("300x250",300,250,1.00,10)');
        $pdo->exec('INSERT INTO ' . $prefix . 'categories (size_key,name) VALUES ("468x60","General"),("728x90","General"),("300x250","General")');

        $config = "<?php\n\nreturn " . var_export([
            'db' => [
                'host' => $host,
                'port' => $port,
                'name' => $dbName,
                'user' => $user,
                'pass' => $pass,
                'prefix' => $prefix,
            ],
            'app' => ['name' => 'Banner Exchange'],
        ], true) . ";\n";

        if (!is_dir(__DIR__ . '/../config')) {
            mkdir(__DIR__ . '/../config', 0775, true);
        }

        file_put_contents(__DIR__ . '/../config/config.php', $config);
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><title>Installer</title></head>
<body style="font-family: Arial, sans-serif; max-width: 760px; margin: 20px auto;">
<h1>Banner Exchange Installer</h1>
<?php if ($done): ?>
    <p style="color: green;">Instalacija uspješna. Login: <a href="/login.php">/login.php</a></p>
<?php else: ?>
    <?php if ($error): ?><p style="color: red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
        <h3>Database</h3>
        <input name="host" placeholder="Host" value="127.0.0.1" required>
        <input name="port" placeholder="Port" value="3306" required>
        <input name="name" placeholder="Database name" required>
        <input name="user" placeholder="Database user" required>
        <input name="pass" placeholder="Database password" type="password">
        <input name="prefix" placeholder="Table prefix" value="bx_" required>
        <h3>Admin</h3>
        <input name="admin_user" placeholder="Admin username" value="admin" required>
        <input name="admin_email" placeholder="Admin email" value="admin@example.com" required>
        <input name="admin_pass" placeholder="Admin password" type="password" required>
        <div><button type="submit">Install</button></div>
    </form>
<?php endif; ?>
</body>
</html>
