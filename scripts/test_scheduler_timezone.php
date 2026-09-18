<?php
/**
 * Read-only scheduler integration checks against a Joomla 5.2+ source tree.
 * Usage: php scripts/test_scheduler_timezone.php /path/to/joomla
 * The source tree must include libraries/vendor/dragonmantank/cron-expression.
 * Application/database services are isolated; rule compilation, scheduling,
 * date handling and cron evaluation use the actual Joomla/vendor classes.
 */

namespace Joomla\CMS\MVC\Model {
    class AdminModel
    {
    }
}

namespace Joomla\CMS {
    class Factory
    {
        public static string $timezone = 'UTC';

        public static function getApplication(): object
        {
            return new class {
                public function get(string $key, $default = null)
                {
                    return $key === 'offset' ? Factory::$timezone : $default;
                }
            };
        }

        public static function getDate($time = 'now', $timezone = 'UTC'): Date\Date
        {
            return new Date\Date($time, $timezone);
        }

        public static function getDbo(): object
        {
            return new class {
                public function getDateFormat(): string
                {
                    return 'Y-m-d H:i:s';
                }
            };
        }

        public static function getContainer(): object
        {
            return new class {
                public function get(string $name): object
                {
                    return Factory::getDbo();
                }
            };
        }
    }
}

namespace Joomla\Utilities {
    class ArrayHelper
    {
        public static function getValue(array $values, string $key)
        {
            return $values[$key] ?? null;
        }
    }
}

namespace {
    use Cron\CronExpression;
    use Joomla\CMS\Factory;
    use Joomla\Component\Scheduler\Administrator\Helper\ExecRuleHelper;
    use Joomla\Component\Scheduler\Administrator\Model\TaskModel;

    if (empty($argv[1])) {
        fwrite(STDERR, "Usage: php scripts/test_scheduler_timezone.php /path/to/joomla\n");
        exit(1);
    }

    define('_JEXEC', 1);
    $joomlaRoot = rtrim($argv[1], '/\\');
    spl_autoload_register(static function (string $class) use ($joomlaRoot): void {
        if (str_starts_with($class, 'Cron\\')) {
            require $joomlaRoot . '/libraries/vendor/dragonmantank/cron-expression/src/'
                . str_replace('\\', '/', $class) . '.php';
        }
    });

    require $joomlaRoot . '/libraries/src/Date/Date.php';
    require $joomlaRoot . '/administrator/components/com_scheduler/src/Model/TaskModel.php';
    require $joomlaRoot . '/administrator/components/com_scheduler/src/Helper/ExecRuleHelper.php';
    require dirname(__DIR__) . '/helper.php';

    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function taskFor(string $frequency, string $time): array
    {
        $build = new \ReflectionMethod(\ModSplaskscoreHelper::class, 'buildSchedulerRules');
        $normalise = new \ReflectionMethod(\ModSplaskscoreHelper::class, 'normaliseCollectionTime');
        $task = $build->invoke(null, $frequency, $normalise->invoke(null, $time));
        $model = (new \ReflectionClass(TaskModel::class))->newInstanceWithoutConstructor();
        $process = new \ReflectionMethod(TaskModel::class, 'processExecutionRules');
        $compile = new \ReflectionMethod(TaskModel::class, 'buildExecutionRules');
        $task['execution_rules'] = $process->invoke($model, $task['execution_rules']);
        $task['cron_rules'] = $compile->invoke($model, $task['execution_rules']);

        return $task;
    }

    // Use a host timezone different from the site to catch accidental reliance on PHP defaults.
    date_default_timezone_set('Pacific/Honolulu');
    $cases = [
        ['Malaysia default', 'Asia/Kuala_Lumpur', '06:00', '2026-01-01 12:00:00', '2026-01-01 22:00:00'],
        ['Changed collection time', 'Asia/Kuala_Lumpur', '09:30', '2026-01-01 12:00:00', '2026-01-02 01:30:00'],
        ['Local midnight', 'Asia/Kuala_Lumpur', '00:00', '2026-01-01 12:00:00', '2026-01-01 16:00:00'],
        ['Fractional offset', 'Asia/Kathmandu', '06:00', '2026-01-01 12:00:00', '2026-01-02 00:15:00'],
        ['Negative offset', 'America/Los_Angeles', '06:00', '2026-01-01 12:00:00', '2026-01-01 14:00:00'],
        ['UTC site', 'UTC', '06:00', '2026-01-01 12:00:00', '2026-01-02 06:00:00'],
        ['Winter offset', 'Europe/Berlin', '06:00', '2026-01-01 12:00:00', '2026-01-02 05:00:00'],
        ['Summer offset', 'Europe/Berlin', '06:00', '2026-07-01 12:00:00', '2026-07-02 04:00:00'],
        ['Invalid time fallback', 'Asia/Kuala_Lumpur', 'invalid', '2026-01-01 12:00:00', '2026-01-01 22:00:00'],
    ];

    foreach ($cases as [$name, $timezone, $time, $now, $expected]) {
        $task = taskFor('daily', $time);
        check($task['cron_rules']['type'] === 'cron-expression', "$name: daily collection must use timezone-aware cron");
        $cron = new CronExpression($task['cron_rules']['exp']);
        $next = $cron->getNextRunDate(new \DateTimeImmutable($now, new \DateTimeZone('UTC')), 0, false, $timezone);
        $actual = $next->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        check($actual === $expected, "$name: expected $expected UTC, got $actual UTC");
        echo "PASS: $name\n";
    }

    // Reuse one saved rule while changing the site setting: Joomla must read the new timezone.
    $task = taskFor('daily', '06:00');
    foreach (['Asia/Kuala_Lumpur', 'Asia/Kathmandu', 'America/Los_Angeles', 'UTC'] as $timezone) {
        Factory::$timezone = $timezone;
        $next = (new ExecRuleHelper($task))->nextExec(false);
        check($next->getOffset() === 0, 'Joomla must return the execution timestamp in UTC');
        $next->setTimezone(new \DateTimeZone($timezone));
        check($next->format('H:i') === '06:00', "$timezone: Joomla must schedule 06:00 in the site timezone");
        echo "PASS: Joomla scheduler uses $timezone\n";
    }

    $hourly = taskFor('hourly', '09:30');
    $before = time();
    $next = (new ExecRuleHelper($hourly))->nextExec(false);
    $after = time();
    check($next->getTimestamp() >= $before + 3600 && $next->getTimestamp() <= $after + 3600, 'Hourly collection must remain one hour apart');
    echo "PASS: Hourly collection remains one hour apart\n";

    // Report upstream transition behavior separately from this module's timezone regression.
    // Joomla 5.4.0 bundles cron-expression 3.4.0, which can skip the spring transition day.
    $daily = taskFor('daily', '06:00');
    $cron = new CronExpression($daily['cron_rules']['exp']);
    $next = $cron->getNextRunDate(new \DateTimeImmutable('2026-03-28 12:00:00 UTC'), 0, false, 'Europe/Berlin');
    $actual = $next->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    if ($actual !== '2026-03-29 04:00:00') {
        echo "UPSTREAM LIMITATION: Joomla's cron library returned $actual UTC across the Berlin spring DST transition; expected 2026-03-29 04:00:00 UTC.\n";
    } else {
        echo "PASS: Upstream cron library handles the spring DST transition\n";
    }
    echo "Scheduler timezone integration checks passed\n";
}
