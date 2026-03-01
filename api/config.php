<?php
declare(strict_types=1);

/**
 * Lightweight .env loader for local hosting (php -S / Apache without injected env vars).
 * Existing environment variables always take precedence.
 */
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '') {
                continue;
            }

            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'smart_influencing');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('APP_KEY', getenv('APP_KEY') ?: 'smart-influencing-local-dev-key-change-me');
define('APP_AUTO_MIGRATE', getenv('APP_AUTO_MIGRATE') ?: '0');
define('APP_DEBUG', getenv('APP_DEBUG') ?: '0');
define('TOKEN_TTL_SECONDS', 60 * 60 * 24 * 30);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function create_token(array $payload): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $issuedAt = time();
    $payload['iat'] = $issuedAt;
    $payload['exp'] = $issuedAt + TOKEN_TTL_SECONDS;

    if (APP_KEY === null || APP_KEY === '') {
        throw new RuntimeException('APP_KEY is not configured. Set the APP_KEY environment variable.');
    }

    $headerSegment = base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadSegment = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $headerSegment . '.' . $payloadSegment, APP_KEY, true);
    $signatureSegment = base64url_encode($signature);

    return $headerSegment . '.' . $payloadSegment . '.' . $signatureSegment;
}

function verify_token(string $token): ?array
{
    if (APP_KEY === '') {
        return null;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerSegment, $payloadSegment, $signatureSegment] = $parts;
    $expectedSignature = base64url_encode(hash_hmac('sha256', $headerSegment . '.' . $payloadSegment, APP_KEY, true));
    if (!hash_equals($expectedSignature, $signatureSegment)) {
        return null;
    }

    $payloadRaw = base64url_decode($payloadSegment);
    if ($payloadRaw === null) {
        return null;
    }

    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) {
        return null;
    }

    $id = (int) ($payload['id'] ?? 0);
    $type = (string) ($payload['type'] ?? '');
    $exp = (int) ($payload['exp'] ?? 0);
    if ($id <= 0 || !in_array($type, ['brand', 'influencer'], true) || $exp <= time()) {
        return null;
    }

    return $payload;
}

function base64url_encode(string $input): string
{
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function base64url_decode(string $input): ?string
{
    $remainder = strlen($input) % 4;
    if ($remainder > 0) {
        $input .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}
