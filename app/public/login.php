<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $pdo = Database::pdo();
    $stmt = $pdo->prepare('SELECT id, email, password_hash, is_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        $error = 'Invalid credentials.';
    } elseif (!(int) $row['is_verified']) {
        $error = 'Email not verified yet.';
    } else {
        $_SESSION['user'] = ['id' => (int) $row['id'], 'email' => $row['email']];
        header('Location: /items.php');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Login</title></head>
<body>
  <h1>Login</h1>
  <?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <form method="post">
    <p><label>Email <input type="email" name="email" required></label></p>
    <p><label>Password <input type="password" name="password" required></label></p>
    <p><button type="submit">Login</button></p>
  </form>
  <p><a href="/index.php">Home</a></p>
</body>
</html>
