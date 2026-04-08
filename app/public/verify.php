<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;

$token = (string) ($_GET['token'] ?? '');
$msg = 'Invalid or expired link.';
if ($token !== '') {
    $stmt = Database::pdoRead()->prepare('SELECT id FROM users WHERE verification_token = ? AND is_verified = 0');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
        $u = Database::pdoWrite()->prepare('UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?');
        $u->execute([(int) $row['id']]);
        $msg = 'Email verified. You can log in.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Verify</title></head>
<body>
  <h1>Email verification</h1>
  <p><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
  <p><a href="/login.php">Login</a></p>
</body>
</html>
