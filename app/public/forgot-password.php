<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Mail;

$error = '';
$ok = '';
$prefillEmail = trim((string) ($_GET['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email required.';
    } else {
        try {
            $stmt = Database::pdoRead()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row) {
                $token = bin2hex(random_bytes(32));
                $expires = (new \DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');
                $upd = Database::pdoWrite()->prepare('UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?');
                $upd->execute([$token, $expires, (int) $row['id']]);
                Mail::sendPasswordReset($email, $token);
            }
            $ok = 'If an account exists for that address, we sent password reset instructions. Check your mail (e.g. Mailpit).';
        } catch (Throwable $e) {
            $error = 'Could not send mail: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Forgot password</title></head>
<body>
  <h1>Forgot password</h1>
  <?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($ok): ?><p style="color:green"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <form method="post">
    <p><label>Email <input type="email" name="email" required value="<?= htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><button type="submit">Send reset link</button></p>
  </form>
  <p><a href="/login.php">Login</a> | <a href="/index.php">Home</a></p>
</body>
</html>
