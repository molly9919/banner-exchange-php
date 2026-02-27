<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $exchange->register($_POST);
        header('Location: /login.php');
        exit;
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
    <title>Register</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container" style="max-width:700px;">
    <div class="card"><h1>Create account</h1></div>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="card" method="post">
        <input name="email" type="email" placeholder="Email" required>
        <input name="username" placeholder="Username" required>
        <input name="password" type="password" placeholder="Password" required>
        <div class="inline">
            <input name="country_code" placeholder="Country code (BA, HR, RS, ... or ALL)" value="ALL">
            <input name="timezone" placeholder="Timezone (e.g. Europe/Sarajevo)" value="UTC">
        </div>
        <label><input type="checkbox" name="agree_rules" value="1" checked style="width:auto"> I agree with the exchange rules</label>
        <button type="submit">Create account</button>
    </form>
</div>
</body>
</html>
