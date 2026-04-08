<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php', true, 303);
    exit;
}

$return = (string) ($_POST['return'] ?? '/index.php');
if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')) {
    $return = '/index.php';
}

try {
    Database::refreshPrimaryReachabilityFromProbe();
} catch (Throwable) {
    // Probe failed; Redis still updated if partial; ignore for redirect
}

header('Location: ' . $return, true, 303);
exit;
