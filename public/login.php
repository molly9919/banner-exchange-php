<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $exchange->login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if (!$user) {
        $error = 'Invalid username/password';
    } else {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['is_admin'] = (bool) $user['is_admin'];
        $_SESSION['moderator_rights'] = $exchange->moderatorRightsForUser((int) $user['id']);
        header('Location: ' . ($user['is_admin'] || !empty($_SESSION['moderator_rights']) ? '/admin.php' : '/dashboard.php'));
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container" style="max-width:560px;">
    <div class="card">
        <h1>Login</h1>
        <p class="small">Access your yellow/black dashboard.</p>
    </div>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="card" method="post">
        <input name="username" placeholder="Username" required>
        <input name="password" type="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <div class="card small">No account yet? <a href="/register.php">Register</a></div>
</div>
</body>
</html>
