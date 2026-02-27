<?php

declare(strict_types=1);

$error = null;
$success = null;
$configPath = __DIR__ . '/../config/config.php';
$alreadyInstalled = file_exists($configPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $host = trim((string) ($_POST['host'] ?? '127.0.0.1'));
    $port = max(1, (int) ($_POST['port'] ?? 3306));
    $dbName = trim((string) ($_POST['name'] ?? ''));
    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($_POST['prefix'] ?? 'bx_'));
    $adminUser = trim((string) ($_POST['admin_user'] ?? 'admin'));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? 'admin@example.com'));
    $adminPass = (string) ($_POST['admin_pass'] ?? '');

    try {
        if ($dbName === '' || $user === '' || $prefix === '' || $adminUser === '' || $adminEmail === '' || $adminPass === '') {
            throw new RuntimeException('Please fill in all required fields.');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Admin email is not valid.');
        }

        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName), $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $schema = file_get_contents(__DIR__ . '/../sql/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Could not read schema file.');
        }

        $schema = str_replace('{prefix}', $prefix, $schema);
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $query) {
            if ($query !== '') {
                $pdo->exec($query);
            }
        }

        // MySQL DDL may auto-commit, so wrap only data seeding in a transaction.
        $pdo->beginTransaction();

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

        if (!is_dir(__DIR__ . '/../config') && !mkdir(__DIR__ . '/../config', 0775, true) && !is_dir(__DIR__ . '/../config')) {
            throw new RuntimeException('Could not create config directory.');
        }

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

        if (file_put_contents($configPath, $config) === false) {
            throw new RuntimeException('Could not write config/config.php.');
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        $success = '✅ Installation is complete. You can now log in at /login.php with your admin account.';
        $alreadyInstalled = true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner Exchange Installer</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Banner Exchange Installer</h1>
        <p class="small">Yellow/black admin-ready setup wizard.</p>
    </div>

    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if ($alreadyInstalled): ?>
        <div class="card">
            <h2>Installation Status</h2>
            <p>System is already installed. For security, remove or block public access to <code>install.php</code>.</p>
            <p><a href="/login.php">Go to Login</a></p>
        </div>
    <?php else: ?>
        <form class="card" method="post">
            <h2>Database</h2>
            <div class="inline">
                <input name="host" placeholder="Host" value="127.0.0.1" required>
                <input name="port" placeholder="Port" value="3306" required>
            </div>
            <input name="name" placeholder="Database name" required>
            <div class="inline">
                <input name="user" placeholder="Database user" required>
                <input name="pass" placeholder="Database password" type="password">
            </div>
            <input name="prefix" placeholder="Table prefix" value="bx_" required>

            <h2>Administrator account</h2>
            <input name="admin_user" placeholder="Admin username" value="admin" required>
            <input name="admin_email" placeholder="Admin email" value="admin@example.com" required>
            <input name="admin_pass" placeholder="Admin password" type="password" required>
            <button type="submit">Install now</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
