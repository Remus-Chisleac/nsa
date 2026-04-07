<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Mail;

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password_hash, verification_token, is_verified) VALUES (?,?,?,0)'
            );
            $stmt->execute([$email, $hash, $token]);
            Mail::sendVerification($email, $token);
            $ok = 'Registered. Check Mailpit for the verification link.';
        } catch (\PDOException $e) {
            $sqlState = $e->errorInfo[0] ?? '';
            if ($sqlState === '23000') {
                $error = 'Email already registered.';
            } else {
                $error = 'Registration failed.';
            }
        } catch (Throwable $e) {
            $error = 'Could not send mail: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Register</title></head>
<body>
  <h1>Register</h1>
  <?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($ok): ?><p style="color:green"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <form method="post">
    <p><label>Email <input type="email" name="email" required></label></p>
    <p><label>Password <input type="password" name="password" required minlength="8"></label></p>
    <p><button type="submit">Register</button></p>
  </form>
  <p><a href="/index.php">Home</a></p>
</body>
</html>
