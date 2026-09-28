<?php
/**
 * Read-only integration verifier for an actual Joomla test installation.
 *
 * Usage:
 *   php scripts/test_joomla_integration.php <joomla-root> installed [version]
 *   php scripts/test_joomla_integration.php <joomla-root> upgrade [version]
 *   php scripts/test_joomla_integration.php <joomla-root> failed
 *   php scripts/test_joomla_integration.php <joomla-root> success
 *   php scripts/test_joomla_integration.php <joomla-root> skipped
 *   php scripts/test_joomla_integration.php <joomla-root> uninstalled
 *
 * The script never writes to Joomla or its database. Install, upgrade, trigger
 * the requested collection scenario, or uninstall through Joomla first; then
 * run the matching phase to verify the resulting real state.
 */

function failIntegration(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function passIntegration(string $message): void
{
    echo "PASS: $message\n";
}

function checkIntegration(bool $condition, string $message): void
{
    if (!$condition) {
        failIntegration($message);
    }

    passIntegration($message);
}

function scalar(PDO $pdo, string $sql, array $params = [])
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

function row(PDO $pdo, string $sql, array $params = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $value = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($value) ? $value : null;
}

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare('SHOW TABLES LIKE ?');
    $statement->execute([$table]);

    return $statement->fetchColumn() !== false;
}

if ($argc < 3) {
    failIntegration('Usage: php scripts/test_joomla_integration.php <joomla-root> <installed|upgrade|failed|success|skipped|uninstalled> [version]');
}

$root = realpath($argv[1]);
$phase = strtolower(trim($argv[2]));
$expectedVersion = trim($argv[3] ?? '1.9.4');
$allowedPhases = ['installed', 'upgrade', 'failed', 'success', 'skipped', 'uninstalled'];

checkIntegration($root !== false && is_dir($root), 'Joomla root exists');
checkIntegration(in_array($phase, $allowedPhases, true), 'integration phase is supported');

$configurationFile = $root . '/configuration.php';
checkIntegration(is_file($configurationFile), 'Joomla configuration.php exists');
require_once $configurationFile;
checkIntegration(class_exists('JConfig'), 'Joomla configuration class is available');

$config = new JConfig();
$prefix = (string) ($config->dbprefix ?? '');
checkIntegration((bool) preg_match('/^[A-Za-z0-9_]+$/', $prefix), 'database prefix is safe');

$host = (string) ($config->host ?? 'localhost');
$port = (string) ($config->port ?? '3306');
$database = (string) ($config->db ?? '');
$user = (string) ($config->user ?? '');
$password = (string) ($config->password ?? '');
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $exception) {
    failIntegration('Unable to connect to the Joomla database: ' . $exception->getMessage());
}

$extensions = $prefix . 'extensions';
$modules = $prefix . 'modules';
$tasks = $prefix . 'scheduler_tasks';
$history = $prefix . 'splaskscore_history';
$health = $prefix . 'splaskscore_health';

if ($phase === 'uninstalled') {
    $remaining = (int) scalar(
        $pdo,
        "SELECT COUNT(*) FROM `$extensions` WHERE element IN ('com_splaskscore', 'mod_splaskscore', 'splaskscoreanalytics', 'splaskscoreautomation')"
    );
    checkIntegration($remaining === 0, 'all SPLaSK Score extension records were removed');
    checkIntegration(!tableExists($pdo, $history), 'history table was removed');
    checkIntegration(!tableExists($pdo, $health), 'health table was removed');
    checkIntegration(!is_dir($root . '/administrator/components/com_splaskscore'), 'component files were removed');
    checkIntegration(!is_dir($root . '/administrator/modules/mod_splaskscore'), 'module files were removed');
    echo "Joomla uninstall integration verification passed\n";
    exit(0);
}

$component = row($pdo, "SELECT manifest_cache FROM `$extensions` WHERE type = 'component' AND element = 'com_splaskscore' LIMIT 1");
$moduleExtension = row($pdo, "SELECT manifest_cache FROM `$extensions` WHERE type = 'module' AND element = 'mod_splaskscore' AND client_id = 1 LIMIT 1");
$taskPlugin = row($pdo, "SELECT enabled, manifest_cache FROM `$extensions` WHERE type = 'plugin' AND folder = 'task' AND element = 'splaskscoreanalytics' LIMIT 1");
$systemPlugin = row($pdo, "SELECT enabled, manifest_cache FROM `$extensions` WHERE type = 'plugin' AND folder = 'system' AND element = 'splaskscoreautomation' LIMIT 1");

checkIntegration($component !== null, 'administrator component is installed');
checkIntegration($moduleExtension !== null, 'administrator dashboard module is installed');
checkIntegration($taskPlugin !== null && (int) $taskPlugin['enabled'] === 1, 'scheduler task plugin is installed and enabled');
checkIntegration($systemPlugin !== null && (int) $systemPlugin['enabled'] === 1, 'automation system plugin is installed and enabled');

foreach ([$component, $moduleExtension, $taskPlugin, $systemPlugin] as $extension) {
    $manifest = json_decode((string) ($extension['manifest_cache'] ?? '{}'), true) ?: [];
    checkIntegration((string) ($manifest['version'] ?? '') === $expectedVersion, 'extension manifest version is ' . $expectedVersion);
}

checkIntegration(is_file($root . '/administrator/components/com_splaskscore/tmpl/about/default.php'), 'component About page is installed');
checkIntegration(is_file($root . '/administrator/components/com_splaskscore/src/Controller/CollectionlogsController.php'), 'collection test/export controller is installed');
checkIntegration(is_file($root . '/media/com_splaskscore/js/admin.js'), 'administrator security and countdown script is installed');
checkIntegration(tableExists($pdo, $history), 'history table exists');
checkIntegration(tableExists($pdo, $health), 'health table exists');

$moduleCount = (int) scalar($pdo, "SELECT COUNT(*) FROM `$modules` WHERE module = 'mod_splaskscore' AND client_id = 1");
$taskCount = (int) scalar($pdo, "SELECT COUNT(*) FROM `$tasks` WHERE type = 'splaskscore.analytics.collect'");
checkIntegration($moduleCount === 1, 'exactly one managed dashboard module exists');
checkIntegration($taskCount === 1, 'exactly one managed scheduler task exists');

if ($phase === 'upgrade') {
    $historyCount = (int) scalar($pdo, "SELECT COUNT(*) FROM `$history`");
    checkIntegration($historyCount > 0, 'upgrade preserved existing history records');
}

if (in_array($phase, ['failed', 'success', 'skipped'], true)) {
    $latest = row($pdo, "SELECT status, message, recorded_at FROM `$health` ORDER BY recorded_at DESC, id DESC LIMIT 1");
    checkIntegration($latest !== null, 'a collection audit record exists');
    checkIntegration((string) $latest['status'] === $phase, 'latest collection audit status is ' . $phase);

    $task = row($pdo, "SELECT next_execution, params FROM `$tasks` WHERE type = 'splaskscore.analytics.collect' ORDER BY id ASC LIMIT 1");
    checkIntegration($task !== null && (string) $task['next_execution'] !== '', 'next scheduler execution is populated');
    $nextTimestamp = strtotime((string) $task['next_execution'] . ' UTC');
    checkIntegration($nextTimestamp !== false && $nextTimestamp > time(), 'next scheduler execution is in the future');
}

echo "Joomla $phase integration verification passed\n";
