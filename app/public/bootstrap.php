<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

\App\Database::resetRequestStats();

$config = require dirname(__DIR__) . '/config/config.php';

\App\PrimaryState::scheduleBackgroundProbeIfDue();

$redisHost = $config['redis']['host'];
$redisPort = $config['redis']['port'];
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}");

session_name('NETCLASSSESSID');
session_start();
