<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$ok = $exchange->verifyEmailToken($token);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container" style="max-width:720px;">
    <div class="card">
        <h1>Email verification</h1>
        <?php if ($ok): ?>
            <div class="alert ok">Your email is verified. You can now <a href="/login.php">login</a>.</div>
        <?php else: ?>
            <div class="alert err">Verification link is invalid or already used.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
