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
<!doctype html><html><body style="font-family: Arial; max-width: 600px; margin: 30px auto;">
<h1>Registracija</h1>
<?php if ($error): ?><p style="color: red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input name="email" type="email" placeholder="Email" required>
    <input name="username" placeholder="Username" required>
    <input name="password" type="password" placeholder="Password" required>
    <input name="country_code" placeholder="Country code (npr. BA)" value="ALL">
    <input name="timezone" placeholder="Timezone (Europe/Sarajevo)" value="UTC">
    <label><input type="checkbox" name="agree_rules" value="1" checked> Slažem se sa pravilima</label>
    <button type="submit">Kreiraj račun</button>
</form>
</body></html>
