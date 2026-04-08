<?php

declare(strict_types=1);

namespace App;

final class Database
{
    private static bool $routed = false;

    private static ?\PDO $pdoPrimary = null;

    private static ?\PDO $pdoReplica = null;

    /** @var 'balanced'|'primary_only'|'readonly_replica'|'' */
    private static string $mode = '';

    private static ?string $writeHost = null;

    private static ?string $primaryLabel = null;

    private static ?string $replicaLabel = null;

    private static int $readSeq = 0;

    private static string $lastReadTarget = '';

    /** @var array<string, string> */
    private static array $lastConnectErrors = [];

    /** Call once per HTTP request (e.g. from bootstrap) so read stats reflect this request only. */
    public static function resetRequestStats(): void
    {
        self::$readSeq = 0;
        self::$lastReadTarget = '';
    }

    /** True when the primary is connected and writes are allowed. */
    public static function canWrite(): bool
    {
        self::route();

        return self::$pdoPrimary !== null;
    }

    /**
     * CLI / background probe — does not use route() static state; updates Redis only.
     */
    public static function refreshPrimaryReachabilityFromProbe(): void
    {
        $cfg = self::cfg();
        $host = $cfg['db']['host'];
        $p = self::tryConnect($host);
        PrimaryState::markProbeResult($p !== null);
    }

    private static function cfg(): array
    {
        return require dirname(__DIR__) . '/config/config.php';
    }

    private static function dsn(string $host): string
    {
        $db = self::cfg()['db'];

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $db['port'],
            $db['name']
        );
    }

    /** @return array<int, int|bool> */
    private static function pdoOptions(): array
    {
        return [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 8,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ];
    }

    private static function tryConnect(string $host): ?\PDO
    {
        try {
            $db = self::cfg()['db'];
            $pdo = new \PDO(self::dsn($host), $db['user'], $db['pass'], self::pdoOptions());
            $pdo->query('SELECT 1');

            return $pdo;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (\strlen($msg) > 400) {
                $msg = \substr($msg, 0, 400) . '…';
            }
            self::$lastConnectErrors[$host] = $msg;

            return null;
        }
    }

    private static function route(): void
    {
        if (self::$routed) {
            return;
        }

        $db = self::cfg()['db'];
        $primary = $db['host'];
        $replica = $db['replica_host'];

        self::$lastConnectErrors = [];
        self::$primaryLabel = $primary;
        self::$replicaLabel = $replica;

        $r = self::tryConnect($replica);

        $skipPrimaryTcp = PrimaryState::shouldSkipPrimaryTcp();
        $p = null;

        if (!$skipPrimaryTcp) {
            $p = self::tryConnect($primary);
            if ($p !== null) {
                PrimaryState::markPrimaryUp();
            } else {
                PrimaryState::markPrimaryDownFromRoute();
            }
        }

        if ($r === null && $p === null && $skipPrimaryTcp) {
            $p = self::tryConnect($primary);
            if ($p !== null) {
                PrimaryState::markPrimaryUp();
            } else {
                PrimaryState::markPrimaryDownFromRoute();
            }
        }

        if ($p !== null && $r !== null) {
            self::$pdoPrimary = $p;
            self::$pdoReplica = $r;
            self::$writeHost = $primary;
            self::$mode = 'balanced';
        } elseif ($p !== null) {
            self::$pdoPrimary = $p;
            self::$pdoReplica = null;
            self::$writeHost = $primary;
            self::$mode = 'primary_only';
        } elseif ($r !== null) {
            self::$pdoPrimary = null;
            self::$pdoReplica = $r;
            self::$writeHost = null;
            self::$mode = 'readonly_replica';
        } else {
            $ep = self::$lastConnectErrors[$primary] ?? 'connection failed';
            $er = self::$lastConnectErrors[$replica] ?? 'connection failed';
            throw new \RuntimeException(
                'No database is reachable (primary and replica both failed). '
                . 'Primary [' . $primary . ']: ' . $ep
                . ' | Replica [' . $replica . ']: ' . $er
                . ' — Check: `docker compose ps` (db-primary, db-replica running?), '
                . 'web + DB on same Compose network, DB_USER/DB_PASSWORD/DB_NAME in .env.'
            );
        }

        self::$routed = true;
    }

    public static function pdoWrite(): \PDO
    {
        self::route();
        if (self::$pdoPrimary === null) {
            throw new \RuntimeException(
                'Primary database is unavailable — writes are disabled. '
                . 'The replica is read-only; you can still use pages that only list or read data.'
            );
        }

        return self::$pdoPrimary;
    }

    public static function pdoRead(): \PDO
    {
        self::route();

        if (self::$mode === 'balanced') {
            self::$readSeq++;
            $toPrimary = (self::$readSeq % 2) === 1;
            if ($toPrimary) {
                self::$lastReadTarget = (string) self::$primaryLabel;

                return self::$pdoPrimary;
            }
            self::$lastReadTarget = (string) self::$replicaLabel;

            return self::$pdoReplica;
        }

        if (self::$mode === 'primary_only') {
            self::$lastReadTarget = (string) self::$primaryLabel;

            return self::$pdoPrimary;
        }

        if (self::$mode === 'readonly_replica') {
            self::$lastReadTarget = (string) self::$replicaLabel;

            return self::$pdoReplica;
        }

        throw new \RuntimeException('Database routing not initialized.');
    }

    /** @deprecated Use pdoWrite() or pdoRead() */
    public static function pdo(): \PDO
    {
        return self::pdoWrite();
    }

    public static function routingLine(): string
    {
        self::route();

        return match (self::$mode) {
            'balanced' => self::routingLineLastReadOnly(),
            'primary_only' => self::routingLineLastReadOnly(),
            'readonly_replica' => self::routingLineReadonlyReplica(),
            default => 'Database routing unknown',
        };
    }

    /** Short line: which host served the last read in this request. */
    private static function routingLineLastReadOnly(): string
    {
        $last = self::$lastReadTarget !== '' ? self::$lastReadTarget : '—';

        return 'Last read from: ' . $last;
    }

    private static function routingLineReadonlyReplica(): string
    {
        return 'Last read from: ' . self::$replicaLabel . ' (read-only). Primary last checked (Redis): '
            . PrimaryState::lastPrimaryCheckIso();
    }

    /**
     * Extra markup when the primary is unavailable: manual check button (return URL for redirect).
     */
    public static function primaryStatusExtraHtml(string $returnUri = '/'): string
    {
        self::route();
        if (self::$mode !== 'readonly_replica') {
            return '';
        }
        $ret = self::sanitizeReturnPath($returnUri);

        return '<p style="margin:0.25rem 0 0"><form method="post" action="/check-primary.php" style="display:inline">'
            . '<input type="hidden" name="return" value="' . htmlspecialchars($ret, ENT_QUOTES, 'UTF-8') . '">'
            . '<button type="submit">Check primary now</button></form></p>';
    }

    private static function sanitizeReturnPath(string $uri): string
    {
        if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) {
            return '/index.php';
        }

        return $uri;
    }
}
