#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Standalone probe: connects to the primary DB and updates Redis cache.
 * Spawned in the background from PrimaryState::scheduleBackgroundProbeIfDue().
 */

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    \App\Database::refreshPrimaryReachabilityFromProbe();
} finally {
    \App\PrimaryState::releaseProbeLock();
}
