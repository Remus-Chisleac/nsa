<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = $_SESSION['user'] ?? null;
$hostname = getenv('HOSTNAME') ?: gethostname() ?: 'unknown';
$ip = @gethostbyname((string) gethostname()) ?: ($_SERVER['SERVER_ADDR'] ?? 'unknown');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Networking Project</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
    code { background: #f4f4f4; padding: 0.2em 0.4em; border-radius: 4px; }
    nav a { margin-right: 1rem; }
  </style>
</head>
<body>
  <h1>Networking Project</h1>
  <p><strong>Served by:</strong> <code><?= htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') ?></code></p>
  <p><strong>Container IP:</strong> <code><?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?></code></p>
  <p><strong>Time:</strong> <?= htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8') ?></p>

  <nav>
    <?php if ($user): ?>
      <span>Logged in as <strong><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></strong></span>
      | <a href="/items.php">Items (CRUD)</a>
      | <a href="/logout.php">Logout</a>
    <?php else: ?>
      <a href="/login.php">Login</a>
      | <a href="/register.php">Register</a>
    <?php endif; ?>
  </nav>

  <?php if (!$user): ?>
    <p>Register and verify your email, then log in to manage items.</p>
  <?php else: ?>
    <p>Use <a href="/items.php">Items</a> for CRUD.</p>
  <?php endif; ?>
</body>
</html>
