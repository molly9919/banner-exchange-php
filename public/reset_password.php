<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($newPassword !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = $exchange->resetPasswordByToken($token, $newPassword);
        if (!empty($result['ok'])) {
            $message = 'Password changed successfully. You can now login.';
        } else {
            $error = (string) ($result['error'] ?? 'Reset failed.');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container" style="max-width:600px;">
    <div class="card"><h1>Reset password</h1></div>
    <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="card" method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="password" name="new_password" placeholder="New password" required>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        <button type="submit">Set new password</button>
    </form>
    <div class="card small"><a href="/login.php">Back to login</a></div>
</div>
</body>
</html>
