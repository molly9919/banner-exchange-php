<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $exchange->requestPasswordReset((string) ($_POST['email'] ?? ''));
        $message = 'If the email exists, a reset link has been sent.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container" style="max-width:600px;">
    <div class="card"><h1>Forgot password</h1></div>
    <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="card" method="post">
        <input type="email" name="email" placeholder="Your account email" required>
        <button type="submit">Send reset link</button>
    </form>
    <div class="card small"><a href="/login.php">Back to login</a></div>
</div>
</body>
</html>
