<?php
/**
 * Unit tests that need no database:
 *   php tests/php/cron_test.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$failures = 0;

function check(string $label, bool $cond): void
{
    global $failures;
    if ($cond) {
        echo "ok   {$label}\n";
    } else {
        echo "FAIL {$label}\n";
        $failures++;
    }
}

// ---------------------------------------------------------------- Cron

check('valid: 0 3 * * *', Cron::isValid('0 3 * * *'));
check('valid: */15 9-17 * * 1-5', Cron::isValid('*/15 9-17 * * 1-5'));
check('valid: 0,30 2 * * 0', Cron::isValid('0,30 2 * * 0'));
check('invalid: 4 fields', !Cron::isValid('* * * *'));
check('invalid: garbage', !Cron::isValid('67 * * * *'));

check('match daily 03:00',
    Cron::matches('0 3 * * *', new DateTimeImmutable('2026-11-16 03:00:00')));
check('no match daily 03:01',
    !Cron::matches('0 3 * * *', new DateTimeImmutable('2026-11-16 03:01:00')));
check('match every 15 min at :45',
    Cron::matches('*/15 * * * *', new DateTimeImmutable('2026-11-16 10:45:00')));
check('no match every 15 min at :44',
    !Cron::matches('*/15 * * * *', new DateTimeImmutable('2026-11-16 10:44:00')));
check('weekday range mon-fri (Mon)',
    Cron::matches('0 9 * * 1-5', new DateTimeImmutable('2026-11-16 09:00:00')));

// ---------------------------------------------------------------- Cron::next

check('next: daily 03:00 after 10:00 → tomorrow 03:00',
    Cron::next('0 3 * * *', new DateTimeImmutable('2026-11-18 10:00:00'))
        === '2026-11-19 03:00:00');
check('next: at exact match → following day',
    Cron::next('0 3 * * *', new DateTimeImmutable('2026-11-19 03:00:00'))
        === '2026-11-20 03:00:00');
check('next: business hours skips the weekend',
    Cron::next('*/15 9-17 * * 1-5', new DateTimeImmutable('2026-11-20 18:00:00'))
        === '2026-11-23 09:00:00');
check('next: leap day',
    Cron::next('0 0 29 2 *', new DateTimeImmutable('2026-03-01 00:00:00'))
        === '2028-02-29 00:00:00');
check('next: every minute',
    Cron::next('* * * * *', new DateTimeImmutable('2026-11-18 10:00:30'))
        === '2026-11-18 10:01:00');
check('next: never (impossible date) is null',
    Cron::next('0 0 31 2 *', new DateTimeImmutable('2026-03-01 00:00:00'), 400) === null);
check('weekday range mon-fri (Sun)',
    !Cron::matches('0 9 * * 1-5', new DateTimeImmutable('2026-11-15 09:00:00')));
check('dom OR dow semantics (1st or any Monday)',
    Cron::matches('0 0 1 * 1', new DateTimeImmutable('2026-11-02 00:00:00'))); // Monday

$next = Cron::nextRun('0 3 * * *', new DateTimeImmutable('2026-11-16 04:10:00'));
check('nextRun → 2026-11-17 03:00',
    $next !== null && $next->format('Y-m-d H:i') === '2026-11-17 03:00');

// ---------------------------------------------------------------- Logger

$path = Logger::pathFor('daily_report', '2026-11-16T03:00', 'load', 2);
check('log path shape', str_ends_with($path, '/logs/runs/daily_report__2026-11-16T03-00__load__a2.log'));
check('log dir exists', is_dir(dirname($path)));

$probe = $path . '.unit';
@unlink($probe);
Logger::line($probe, 'hello');
check('Logger::line writes', str_contains((string) @file_get_contents($probe), 'hello'));
$tail = Logger::tail($probe, 100);
check('Logger::tail reads', str_contains($tail, 'hello'));
@unlink($probe);

// ---------------------------------------------------------------- result

echo $failures === 0 ? "\nall checks passed\n" : "\n{$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
