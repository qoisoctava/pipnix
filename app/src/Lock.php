<?php
declare(strict_types=1);

/**
 * Advisory lock over MySQL GET_LOCK so overlapping cron ticks are harmless.
 */
final class Lock
{
    private static ?string $held = null;

    public static function acquire(?string $name = null, ?int $timeout = null): bool
    {
        $name    = $name ?? (string) (Env::cfg('lock.name') ?: 'pipnix');
        $timeout = $timeout ?? (int) (Env::cfg('lock.timeout') ?: 50);
        $row = Db::one('SELECT GET_LOCK(?, ?) AS ok', [$name, $timeout]);
        if ((int) ($row['ok'] ?? 0) === 1) {
            self::$held = $name;
            return true;
        }
        return false;
    }

    public static function release(): void
    {
        if (self::$held !== null) {
            Db::one('SELECT RELEASE_LOCK(?) AS ok', [self::$held]);
            self::$held = null;
        }
    }
}
