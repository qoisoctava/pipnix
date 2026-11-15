<?php
declare(strict_types=1);

/**
 * 5-field cron matcher (min hour dom mon dow) plus "interval:<minutes>".
 * Supports: *, a, a-b, a-b/n, *​/n and comma lists; dow accepts 0-7 (7 = Sun).
 */
final class Cron
{
    /** @return array<int,int>|null null = unrestricted ("*") */
    private static function field(string $spec, int $min, int $max, bool $dow = false): ?array
    {
        $spec = trim($spec);
        if ($spec === '' || $spec === '*') {
            return $dow ? [0, 1, 2, 3, 4, 5, 6] : null;
        }
        $out = [];
        foreach (explode(',', $spec) as $part) {
            $step = 1;
            if (str_contains($part, '/')) {
                [$part, $stepStr] = explode('/', $part, 2);
                $step = max(1, (int) $stepStr);
            }
            if ($part === '*' || $part === '') {
                $from = $min;
                $to   = $max;
            } elseif (str_contains($part, '-')) {
                [$a, $b] = explode('-', $part, 2);
                $from = (int) $a;
                $to   = (int) $b;
            } else {
                $from = (int) $part;
                $to   = $from;
            }
            if ($from > $to) {
                return null; // invalid → treat as unrestricted (validated separately)
            }
            for ($v = $from; $v <= $to; $v += $step) {
                if ($v >= $min && $v <= $max) {
                    $out[] = $dow && $v === 7 ? 0 : $v;
                }
            }
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    public static function isValid(string $expr): bool
    {
        $parts = preg_split('/\s+/', trim($expr)) ?: [];
        if (count($parts) !== 5) {
            return false;
        }
        $bounds = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];
        foreach ($parts as $i => $p) {
            if (!preg_match('/^[0-9*\/,\-]+$/', $p)) {
                return false;
            }
            [$min, $max] = $bounds[$i];
            foreach (explode(',', $p) as $part) {
                $step = 1;
                if (str_contains($part, '/')) {
                    [$part, $stepStr] = explode('/', $part, 2);
                    if (!ctype_digit($stepStr) || (int) $stepStr < 1) {
                        return false;
                    }
                    $step = (int) $stepStr;
                }
                if ($part === '*') {
                    continue;
                }
                if (str_contains($part, '-')) {
                    [$a, $b] = explode('-', $part, 2);
                    if (!ctype_digit($a) || !ctype_digit($b)) {
                        return false;
                    }
                    $from = (int) $a;
                    $to   = (int) $b;
                } else {
                    if (!ctype_digit($part)) {
                        return false;
                    }
                    $from = $to = (int) $part;
                }
                if ($from < $min || $to > $max || $from > $to) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function matches(string $expr, DateTimeImmutable $t): bool
    {
        $parts = preg_split('/\s+/', trim($expr)) ?: [];
        if (count($parts) !== 5) {
            return false;
        }
        $min  = self::field($parts[0], 0, 59);
        $hour = self::field($parts[1], 0, 23);
        $dom  = self::field($parts[2], 1, 31);
        $mon  = self::field($parts[3], 1, 12);
        $dow  = self::field($parts[4], 0, 7, true);

        if ($min !== null && !in_array((int) $t->format('i'), $min, true)) return false;
        if ($hour !== null && !in_array((int) $t->format('G'), $hour, true)) return false;
        if ($mon !== null && !in_array((int) $t->format('n'), $mon, true)) return false;

        $domOk = $dom === null || in_array((int) $t->format('j'), $dom, true);
        $dowOk = $dow === null || in_array((int) $t->format('w'), $dow, true);

        // Standard cron: restricted dom + restricted dow => OR.
        if ($dom !== null && $dow !== null) {
            return $domOk || $dowOk;
        }
        return $domOk && $dowOk;
    }

    public static function nextRun(string $expr, DateTimeImmutable $from): ?DateTimeImmutable
    {
        $t = $from->setTime((int) $from->format('G'), (int) $from->format('i'), 0);
        for ($i = 0; $i < 527040; $i++) { // 366 days
            $t = $t->modify('+1 minute');
            if (self::matches($expr, $t)) {
                return $t;
            }
        }
        return null;
    }
}
