<?php

declare(strict_types=1);

namespace App;

/**
 * Caches primary DB reachability in Redis so requests do not repeat slow TCP timeouts
 * while the primary is known to be down. Background probes use escalating intervals:
 * 5s → 10s → 30s → 60s (then 60s).
 */
final class PrimaryState
{
    public const REDIS_KEY_OK = 'nsa:db:primary_ok';

    public const REDIS_KEY_CHECKED = 'nsa:db:primary_checked_at';

    public const REDIS_KEY_TIER = 'nsa:db:probe_tier';

    public const REDIS_KEY_PROBE_LOCK = 'nsa:db:probe_lock';

    public const PROBE_LOCK_TTL_SEC = 90;

    /** @return list<int> */
    public static function backoffSecondsByTier(): array
    {
        return [5, 10, 30, 60];
    }

    public static function backoffSecondsForTier(int $tier): int
    {
        $t = self::backoffSecondsByTier();

        return $t[min(max(0, $tier), \count($t) - 1)];
    }

    private static function redis(): ?\Redis
    {
        try {
            $c = require dirname(__DIR__) . '/config/config.php';
            $r = new \Redis();
            $r->connect($c['redis']['host'], (int) $c['redis']['port'], 1.5);

            return $r;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{ok: ?bool, checked_at: int, tier: int}
     */
    public static function readCache(): array
    {
        $r = self::redis();
        if ($r === null) {
            return ['ok' => null, 'checked_at' => 0, 'tier' => 0];
        }
        try {
            $okRaw = $r->get(self::REDIS_KEY_OK);
            $at = $r->get(self::REDIS_KEY_CHECKED);
            $tierRaw = $r->get(self::REDIS_KEY_TIER);
            $checkedAt = is_numeric($at) ? (int) $at : 0;
            $tier = is_numeric($tierRaw) ? (int) $tierRaw : 0;
            if ($okRaw === false) {
                return ['ok' => null, 'checked_at' => $checkedAt, 'tier' => $tier];
            }

            return ['ok' => $okRaw === '1', 'checked_at' => $checkedAt, 'tier' => $tier];
        } catch (\Throwable) {
            return ['ok' => null, 'checked_at' => 0, 'tier' => 0];
        } finally {
            try {
                $r->close();
            } catch (\Throwable) {
            }
        }
    }

    /** ISO 8601 time of last primary reachability check stored in Redis (for UI). */
    public static function lastPrimaryCheckIso(): string
    {
        $c = self::readCache();
        if ($c['checked_at'] <= 0) {
            return 'never';
        }

        return date('c', $c['checked_at']);
    }

    public static function markPrimaryUp(): void
    {
        $r = self::redis();
        if ($r === null) {
            return;
        }
        try {
            $r->mSet([
                self::REDIS_KEY_OK => '1',
                self::REDIS_KEY_CHECKED => (string) time(),
                self::REDIS_KEY_TIER => '0',
            ]);
        } catch (\Throwable) {
        } finally {
            try {
                $r->close();
            } catch (\Throwable) {
            }
        }
    }

    /** First detection in a web request (live tryConnect): start backoff at tier 0 (next probe in 5s). */
    public static function markPrimaryDownFromRoute(): void
    {
        $r = self::redis();
        if ($r === null) {
            return;
        }
        try {
            $r->mSet([
                self::REDIS_KEY_OK => '0',
                self::REDIS_KEY_CHECKED => (string) time(),
                self::REDIS_KEY_TIER => '0',
            ]);
        } catch (\Throwable) {
        } finally {
            try {
                $r->close();
            } catch (\Throwable) {
            }
        }
    }

    /** Result of background or manual probe: advance tier on repeated failure. */
    public static function markProbeResult(bool $reachable): void
    {
        $r = self::redis();
        if ($r === null) {
            return;
        }
        try {
            if ($reachable) {
                $r->mSet([
                    self::REDIS_KEY_OK => '1',
                    self::REDIS_KEY_CHECKED => (string) time(),
                    self::REDIS_KEY_TIER => '0',
                ]);
            } else {
                $tierRaw = $r->get(self::REDIS_KEY_TIER);
                $tier = is_numeric($tierRaw) ? (int) $tierRaw : 0;
                $nextTier = min(3, $tier + 1);
                $r->mSet([
                    self::REDIS_KEY_OK => '0',
                    self::REDIS_KEY_CHECKED => (string) time(),
                    self::REDIS_KEY_TIER => (string) $nextTier,
                ]);
            }
        } catch (\Throwable) {
        } finally {
            try {
                $r->close();
            } catch (\Throwable) {
            }
        }
    }

    public static function shouldSkipPrimaryTcp(): bool
    {
        $c = self::readCache();

        return $c['ok'] === false;
    }

    public static function scheduleBackgroundProbeIfDue(): void
    {
        $c = self::readCache();
        if ($c['ok'] !== false) {
            return;
        }
        $delay = self::backoffSecondsForTier($c['tier']);
        $age = time() - $c['checked_at'];
        if ($age < $delay) {
            return;
        }

        $r = self::redis();
        if ($r === null) {
            return;
        }
        try {
            if (!$r->setnx(self::REDIS_KEY_PROBE_LOCK, '1')) {
                return;
            }
            $r->expire(self::REDIS_KEY_PROBE_LOCK, self::PROBE_LOCK_TTL_SEC);
        } catch (\Throwable) {
            return;
        } finally {
            try {
                $r->close();
            } catch (\Throwable) {
            }
        }

        $script = dirname(__DIR__) . '/bin/probe-primary.php';
        if (!is_readable($script)) {
            self::releaseProbeLock();

            return;
        }

        $php = \PHP_BINARY !== '' ? \PHP_BINARY : 'php';
        $cmd = $php . ' ' . \escapeshellarg($script) . ' >/dev/null 2>&1 &';
        @\exec($cmd);
    }

    public static function releaseProbeLock(): void
    {
        $r = self::redis();
        if ($r === null) {
            return;
        }
        try {
            $r->del(self::REDIS_KEY_PROBE_LOCK);
        } catch (\Throwable) {
        } finally {
            try {
                $r->close();
            } catch (\Throwable) {
            }
        }
    }
}
