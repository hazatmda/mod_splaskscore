<?php
/**
 * Read-only regression checks for SPLaSK history scope handling.
 *
 * Usage: php scripts/test_history_scope.php
 *
 * Guards the token-change failure mode: a pasted token with a trailing newline or a
 * non-breaking space used to derive a different SHA-256 hash, which created a second
 * history scope and made existing analytics look empty. It also pins the stable analytics
 * scope key format that replaces token hashing as the history key. No database or Joomla
 * tree is required; only pure helpers are exercised through reflection.
 */

define('_JEXEC', 1);

require dirname(__DIR__) . '/helper.php';

/**
 * @param   bool    $condition  Assertion result.
 * @param   string  $message    Failure description.
 *
 * @return  void
 */
function checkScope(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

$normalise = new \ReflectionMethod('ModSplaskscoreHelper', 'normaliseToken');
$notice = new \ReflectionMethod('ModSplaskscoreHelper', 'historyScopeNotice');
$scopeKey = new \ReflectionMethod('ModSplaskscoreHelper', 'isAnalyticsScopeKey');

$token = 'a1b2c3d4e5f6';
$canonicalHash = hash('sha256', $token);

$variants = [
    'plain' => $token,
    'leading space' => ' ' . $token,
    'trailing space' => $token . ' ',
    'trailing newline' => $token . "\n",
    'windows newline' => $token . "\r\n",
    'trailing tab' => $token . "\t",
    'non-breaking space' => $token . "\xC2\xA0",
    'byte order mark' => "\xEF\xBB\xBF" . $token,
    'surrounding whitespace' => "  \t" . $token . "\r\n ",
];

foreach ($variants as $label => $value) {
    $normalised = $normalise->invoke(null, $value);
    checkScope(
        $normalised === $token,
        sprintf('Token normalisation failed for %s: got "%s"', $label, addcslashes($normalised, "\0..\37"))
    );
    checkScope(
        hash('sha256', $normalised) === $canonicalHash,
        sprintf('Scope hash changed for %s; a pasted token must never create a second scope', $label)
    );
    echo 'PASS: ' . $label . " keeps one scope hash\n";
}

checkScope($normalise->invoke(null, '') === '', 'An empty token must stay empty');
checkScope($normalise->invoke(null, '   ') === '', 'A whitespace-only token must normalise to empty');
echo "PASS: empty and whitespace-only tokens normalise to empty\n";

checkScope($normalise->invoke(null, 'aaaa bbbb') === 'aaaa bbbb', 'Internal spaces must be preserved');
echo "PASS: internal token characters are never altered\n";

checkScope(
    hash('sha256', $normalise->invoke(null, 'ABCDEF')) !== hash('sha256', $normalise->invoke(null, 'abcdef')),
    'Token normalisation must stay case-sensitive'
);
echo "PASS: token normalisation remains case-sensitive\n";

checkScope($notice->invoke(null, ['legacy' => false, 'healed' => 0]) === '', 'No notice is expected when nothing was adopted');
echo "PASS: no notice when no legacy rows were adopted\n";

$adoptedNotice = (string) $notice->invoke(null, ['legacy' => true, 'healed' => 7]);
checkScope(str_contains($adoptedNotice, '7 rekod'), 'An adoption notice must report how many rows were adopted');
echo "PASS: adoption notice reports the adopted row count\n";

foreach ([str_repeat('a', 32), '0123456789abcdef0123456789abcdef'] as $validScope) {
    checkScope($scopeKey->invoke(null, $validScope) === true, 'A 32-character hex scope key must be accepted: ' . $validScope);
}
echo "PASS: 32-character hex scope keys are accepted\n";

$invalidScopes = [
    'legacy token hash' => str_repeat('a', 64),
    'too short' => str_repeat('a', 31),
    'too long' => str_repeat('a', 33),
    'uppercase' => strtoupper(str_repeat('a', 32)),
    'non hex' => 'z' . str_repeat('a', 31),
    'empty' => '',
];

foreach ($invalidScopes as $label => $invalidScope) {
    checkScope($scopeKey->invoke(null, $invalidScope) === false, 'Rejected scope key expected for ' . $label);
}
echo "PASS: legacy token hashes and malformed keys are rejected as scopes\n";

echo "History scope regression checks passed\n";
