<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;

$error = '';
$ok = '';
$token = trim((string) ($_GET['token'] ?? ''));
$showForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');
    if ($token === '') {
        $error = 'Missing token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
        $showForm = $token !== '';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
        $showForm = $token !== '';
    } else {
        $stmt = Database::pdoRead()->prepare(
            'SELECT id, reset_expires_at FROM users WHERE reset_token = ?'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            $error = 'Invalid or expired reset link.';
        } else {
            $expires = $row['reset_expires_at'] ?? null;
            if ($expires === null) {
                $error = 'Invalid reset link.';
            } else {
                $exp = new \DateTimeImmutable((string) $expires);
                if ($exp < new \DateTimeImmutable('now')) {
                    $error = 'This reset link has expired. Request a new one.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = Database::pdoWrite()->prepare(
                        'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?'
                    );
                    $upd->execute([$hash, (int) $row['id']]);
                    $ok = 'Password updated. You can log in.';
                }
            }
        }
    }
} else {
    if ($token === '') {
        $error = 'Missing token. Open the link from your email.';
    } else {
        $stmt = Database::pdoRead()->prepare(
            'SELECT reset_expires_at FROM users WHERE reset_token = ?'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            $error = 'Invalid or expired reset link.';
        } else {
            $expires = $row['reset_expires_at'] ?? null;
            if ($expires === null) {
                $error = 'Invalid reset link.';
            } else {
                $exp = new \DateTimeImmutable((string) $expires);
                if ($exp < new \DateTimeImmutable('now')) {
                    $error = 'This reset link has expired. Request a new one.';
                } else {
                    $showForm = true;
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Reset password</title></head>
<body>
  <h1>Reset password</h1>
  <?php if ($ok): ?>
    <p style="color:green"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="/login.php">Login</a></p>
  <?php elseif ($error): ?>
    <p style="color:red"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="/forgot-password.php">Request a new link</a></p>
  <?php endif; ?>

  <?php if ($showForm && !$ok): ?>
    <form method="post">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <p><label>New password <input type="password" name="password" required minlength="8"></label></p>
      <p><label>Confirm <input type="password" name="password2" required minlength="8"></label></p>
      <p><button type="submit">Set password</button></p>
    </form>
  <?php endif; ?>

  <p><a href="/index.php">Home</a></p>
</body>
</html>
