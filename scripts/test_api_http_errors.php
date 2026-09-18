<?php
/**
 * Regression checks for SPLaSK API HTTP status handling.
 *
 * Usage: php scripts/test_api_http_errors.php
 */

namespace Joomla\CMS\Http {
    final class HttpFactory
    {
        public static int $code = 200;
        public static string $body = '{}';

        public static function getHttp(): object
        {
            return new class {
                public function post(string $url, string $body, array $headers): object
                {
                    return (object) [
                        'code' => HttpFactory::$code,
                        'body' => HttpFactory::$body,
                    ];
                }
            };
        }
    }
}

namespace {
    use Joomla\CMS\Http\HttpFactory;

    define('_JEXEC', 1);
    require dirname(__DIR__) . '/helper.php';

    function checkApi(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function fetchApi(): array
    {
        $method = new \ReflectionMethod('ModSplaskscoreHelper', 'fetchScoreFromApi');

        return $method->invoke(null, 'test-token');
    }

    HttpFactory::$code = 200;
    HttpFactory::$body = '{"status":true,"final_score":88.5}';
    $success = fetchApi();
    checkApi(($success['final_score'] ?? null) === 88.5, 'HTTP 200 JSON response must be returned');
    echo "PASS: HTTP 200 response is accepted\n";

    HttpFactory::$code = 0;
    HttpFactory::$body = '{"status":true,"final_score":77}';
    $unknownStatus = fetchApi();
    checkApi(($unknownStatus['final_score'] ?? null) === 77, 'HTTP status 0 must remain compatible');
    echo "PASS: HTTP status 0 remains compatible\n";

    foreach ([403, 404, 503] as $code) {
        HttpFactory::$code = $code;
        HttpFactory::$body = '{"status":false}';

        try {
            fetchApi();
            throw new \RuntimeException('HTTP ' . $code . ' should have thrown an exception');
        } catch (\RuntimeException $exception) {
            $expected = 'Gagal menyambung ke API. (Kod Ralat HTTP: ' . $code . ')';
            checkApi($exception->getMessage() === $expected, 'Unexpected HTTP error message: ' . $exception->getMessage());
        }

        echo 'PASS: HTTP ' . $code . " is reported with its status code\n";
    }

    HttpFactory::$code = 200;
    HttpFactory::$body = '<html>invalid</html>';

    try {
        fetchApi();
        throw new \RuntimeException('Invalid JSON should have thrown an exception');
    } catch (\RuntimeException $exception) {
        checkApi($exception->getMessage() === 'Respons API SPLaSK tidak sah.', 'Invalid JSON message changed unexpectedly');
    }

    echo "PASS: invalid JSON is still rejected\n";
    echo "SPLaSK API HTTP status regression checks passed\n";
}
