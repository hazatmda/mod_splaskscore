<?php
/**
 * Verify the daily scheduler fallback for hosts that load an old cron library.
 */

namespace Cron {
    final class CronExpression
    {
        public function __construct(string $expression, object $fieldFactory)
        {
        }
    }
}

namespace Joomla\CMS {
    final class Factory
    {
        public static function getDate(): object
        {
            return new class {
                public function format(string $format, bool $local = false): string
                {
                    return gmdate($format);
                }
            };
        }

        public static function getApplication(): object
        {
            return new class {
                public function get(string $key, $default = null)
                {
                    return $key === 'offset' ? 'Asia/Kuala_Lumpur' : $default;
                }
            };
        }
    }
}

namespace {
    define('_JEXEC', 1);
    require dirname(__DIR__) . '/helper.php';

    $build = new \ReflectionMethod(\ModSplaskscoreHelper::class, 'buildSchedulerRules');
    $rules = $build->invoke(null, 'daily', '06:00')['execution_rules'];

    if (($rules['rule-type'] ?? '') !== 'interval-days') {
        throw new \RuntimeException('An incompatible cron constructor must select the daily interval fallback.');
    }

    if (($rules['interval-days'] ?? 0) !== 1) {
        throw new \RuntimeException('The scheduler compatibility fallback must run once per day.');
    }

    if (($rules['exec-time'] ?? '') !== '22:00') {
        throw new \RuntimeException('06:00 Asia/Kuala_Lumpur must be stored as 22:00 UTC for the daily fallback.');
    }

    echo "Scheduler cron compatibility regression checks passed\n";
}
