<?php
/**
 * Verify the one-success-per-day and same-day cooldown schedule policy.
 */

define('_JEXEC', 1);
require dirname(__DIR__) . '/helper.php';

function checkSchedule(string $label, string $expected, string $actual): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . $expected . ', received ' . $actual);
    }

    echo 'PASS: ' . $label . PHP_EOL;
}

$timezone = new DateTimeZone('Asia/Kuala_Lumpur');
$calculate = new ReflectionMethod(ModSplaskscoreHelper::class, 'calculateNextCollectionExecution');
$synchronize = new ReflectionMethod(ModSplaskscoreHelper::class, 'calculateSynchronizedNextExecution');
$bounds = new ReflectionMethod(ModSplaskscoreHelper::class, 'getSiteDayUtcBounds');

checkSchedule(
    'success waits for the next local day collection time',
    '2026-09-28 22:00:00',
    $calculate->invoke(
        null,
        true,
        500,
        '06:00',
        new DateTimeImmutable('2026-09-28 01:00:00', new DateTimeZone('UTC')),
        $timezone
    )
);

checkSchedule(
    '500-minute failure retry remains on the same local day',
    '2026-09-28 06:20:00',
    $calculate->invoke(
        null,
        false,
        500,
        '06:00',
        new DateTimeImmutable('2026-09-27 22:00:00', new DateTimeZone('UTC')),
        $timezone
    )
);

checkSchedule(
    'repeated 500-minute failure retry remains on the same local day',
    '2026-09-28 14:40:00',
    $calculate->invoke(
        null,
        false,
        500,
        '06:00',
        new DateTimeImmutable('2026-09-28 06:20:00', new DateTimeZone('UTC')),
        $timezone
    )
);

checkSchedule(
    'retry crossing midnight resets to the next daily collection time',
    '2026-09-28 22:00:00',
    $calculate->invoke(
        null,
        false,
        500,
        '06:00',
        new DateTimeImmutable('2026-09-28 14:40:00', new DateTimeZone('UTC')),
        $timezone
    )
);

[$dayStart, $dayEnd] = $bounds->invoke(
    null,
    new DateTimeImmutable('2026-09-28 15:59:00', new DateTimeZone('UTC')),
    $timezone
);
checkSchedule('site-local day UTC start boundary', '2026-09-27 16:00:00', $dayStart);
checkSchedule('site-local day UTC end boundary', '2026-09-28 16:00:00', $dayEnd);

checkSchedule(
    'saving settings preserves a pending same-day retry',
    '2026-09-28 01:01:05',
    $synchronize->invoke(
        null,
        false,
        'failed',
        '2026-09-27 22:01:05',
        180,
        '06:00',
        new DateTimeImmutable('2026-09-27 22:30:00', new DateTimeZone('UTC')),
        $timezone
    )
);

checkSchedule(
    'a missed same-day retry becomes due on the next cron pass',
    '2026-09-28 02:52:00',
    $synchronize->invoke(
        null,
        false,
        'failed',
        '2026-09-27 22:01:05',
        180,
        '06:00',
        new DateTimeImmutable('2026-09-28 02:53:00', new DateTimeZone('UTC')),
        $timezone
    )
);

checkSchedule(
    'saving settings after success still waits for the next daily collection',
    '2026-09-28 22:00:00',
    $synchronize->invoke(
        null,
        true,
        'success',
        '2026-09-27 22:01:05',
        180,
        '06:00',
        new DateTimeImmutable('2026-09-28 02:53:00', new DateTimeZone('UTC')),
        $timezone
    )
);

checkSchedule(
    'a synchronized retry crossing midnight returns to the daily collection time',
    '2026-09-28 22:00:00',
    $synchronize->invoke(
        null,
        false,
        'failed',
        '2026-09-28 14:40:00',
        180,
        '06:00',
        new DateTimeImmutable('2026-09-28 14:45:00', new DateTimeZone('UTC')),
        $timezone
    )
);

if ($synchronize->invoke(
    null,
    false,
    '',
    '',
    180,
    '06:00',
    new DateTimeImmutable('2026-09-28 02:53:00', new DateTimeZone('UTC')),
    $timezone
) !== null) {
    throw new RuntimeException('saving settings without a current-day outcome must keep Joomla scheduler output');
}
echo "PASS: saving settings without a current-day outcome keeps Joomla scheduler output\n";

echo "Scheduler retry policy regression checks passed\n";
