<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;

if (empty($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

$uid = (int) $_SESSION['user']['id'];
$error = '';
$hostname = getenv('HOSTNAME') ?: gethostname() ?: 'unknown';
$ip = @gethostbyname((string) gethostname()) ?: ($_SERVER['SERVER_ADDR'] ?? 'unknown');

$writesOk = Database::canWrite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isMutating = \in_array($action, ['create', 'update', 'delete'], true);
    if ($isMutating && !$writesOk) {
        $error = 'Primary database is unavailable; create, edit, and delete are disabled.';
    } elseif ($isMutating) {
        if ($action === 'create') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title === '') {
                $error = 'Title required.';
            } else {
                $stmt = Database::pdoWrite()->prepare('INSERT INTO items (title, description, created_by) VALUES (?,?,?)');
                $stmt->execute([$title, $description, $uid]);
                header('Location: /items.php');
                exit;
            }
        }
        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $stmt = Database::pdoWrite()->prepare('UPDATE items SET title=?, description=? WHERE id=? AND created_by=?');
            $stmt->execute([$title, $description, $id, $uid]);
            header('Location: /items.php');
            exit;
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = Database::pdoWrite()->prepare('DELETE FROM items WHERE id=? AND created_by=?');
            $stmt->execute([$id, $uid]);
            header('Location: /items.php');
            exit;
        }
    }
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$list = Database::pdoRead()->prepare('SELECT id, title, description, created_at FROM items WHERE created_by = ? ORDER BY id DESC');
$list->execute([$uid]);
$rows = $list->fetchAll();
$editRow = null;
if ($editId > 0) {
    $e = Database::pdoRead()->prepare('SELECT id, title, description FROM items WHERE id=? AND created_by=?');
    $e->execute([$editId, $uid]);
    $editRow = $e->fetch() ?: null;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Items</title>
</head>
<body>
  <h1>Items (CRUD)</h1>
  <p><strong>Web container:</strong> <code><?= htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?>)</code></p>
  <?php
    try {
        $dbLine = Database::routingLine();
    } catch (Throwable $e) {
        $dbLine = 'Database error: ' . $e->getMessage();
    }
  ?>
  <p><strong>Database:</strong> <?= htmlspecialchars($dbLine, ENT_QUOTES, 'UTF-8') ?></p>
  <?= Database::primaryStatusExtraHtml('/items.php') ?>
  <?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

  <h2><?= $editRow ? 'Edit' : 'Create' ?></h2>
  <?php if ($writesOk): ?>
  <form method="post">
    <?php if ($editRow): ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
    <?php else: ?>
      <input type="hidden" name="action" value="create">
    <?php endif; ?>
    <p><label>Title <input name="title" required value="<?= htmlspecialchars((string) ($editRow['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>Description<br><textarea name="description" rows="3" cols="40"><?= htmlspecialchars((string) ($editRow['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label></p>
    <p><button type="submit"><?= $editRow ? 'Save' : 'Add' ?></button></p>
  </form>
  <?php if ($editRow): ?><p><a href="/items.php">Cancel edit</a></p><?php endif; ?>
  <?php else: ?>
  <p><em>Create and edit are unavailable while the primary database is unreachable (read-only mode).</em></p>
  <?php if ($editRow): ?><p><a href="/items.php">Back to list</a></p><?php endif; ?>
  <?php endif; ?>

  <h2>Your items</h2>
  <ul>
    <?php foreach ($rows as $r): ?>
      <li>
        <strong><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></strong>
        — <?= htmlspecialchars($r['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        <small>(<?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?>)</small>
        <?php if ($writesOk): ?>
        <a href="/items.php?edit=<?= (int) $r['id'] ?>">Edit</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button type="submit">Delete</button>
        </form>
        <?php else: ?>
        <span title="Primary DB unavailable">Edit</span>
        <button type="button" disabled title="Primary DB unavailable">Delete</button>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <p><a href="/index.php">Home</a> | <a href="/logout.php">Logout</a></p>
</body>
</html>
