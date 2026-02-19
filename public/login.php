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
<!doctype html><html><body style="font-family: Arial; max-width: 460px; margin: 40px auto;">
<h1>Login</h1>
<?php if ($error): ?><p style="color: red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input name="username" placeholder="Username" required>
    <input name="password" type="password" placeholder="Password" required>
    <button type="submit">Login</button>
</form>
<p>No account yet? <a href="/register.php">Register</a></p>
</body></html>
