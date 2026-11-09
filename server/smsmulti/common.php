<?php
declare(strict_types=1);

function gateway_headers(): void
{
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}

gateway_headers();

function gateway_json(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function gateway_db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = trim((string) getenv('SMS_GATEWAY_DB_PATH'));
    if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR) {
        throw new RuntimeException('Set SMS_GATEWAY_DB_PATH to an absolute path outside the public web directory.');
    }
    if (is_link($path)) {
        throw new RuntimeException('The SMS database path cannot be a symbolic link.');
    }

    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        throw new RuntimeException('The database directory could not be created.');
    }
    @chmod($directory, 0700);

    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $realDirectory = realpath($directory);
    if ($documentRoot !== false && $realDirectory !== false
        && ($realDirectory === $documentRoot || str_starts_with($realDirectory, $documentRoot.DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('The SMS database must be stored outside the public web directory.');
    }

    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO SQLite is required.');
    }

    $pdo = new PDO('sqlite:'.$path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS devices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        device_id TEXT UNIQUE,
        active INTEGER NOT NULL DEFAULT 1,
        last_seen INTEGER,
        created_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        target_device_id INTEGER NOT NULL REFERENCES devices(id),
        phone TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'unsent' CHECK (status IN ('unsent','processing','sent','failed')),
        locked_by TEXT,
        locked_at INTEGER,
        created_at INTEGER NOT NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS sms_queue_idx ON sms(target_device_id, status, id)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_hash TEXT NOT NULL,
        attempted_at INTEGER NOT NULL
    )");
    @chmod($path, 0600);

    return $pdo;
}

function gateway_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('absms_admin');
    $scriptPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
    $cookiePath = str_replace('\\', '/', dirname($scriptPath));
    $cookiePath = $cookiePath === '.' || $cookiePath === '/' ? '/' : rtrim($cookiePath, '/').'/';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function gateway_require_admin(): void
{
    gateway_admin_session();
    if (empty($_SESSION['admin_authenticated'])) {
        header('Location: login.php', true, 303);
        exit;
    }
}

function gateway_csrf_token(): string
{
    gateway_admin_session();
    if (! isset($_SESSION['csrf_token']) || ! is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function gateway_verify_csrf(): void
{
    gateway_admin_session();
    $submitted = (string) ($_POST['_csrf'] ?? '');
    if ($submitted === '' || ! hash_equals(gateway_csrf_token(), $submitted)) {
        http_response_code(419);
        exit('The form session expired. Reload and try again.');
    }
}

function gateway_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gateway_utf8_length(string $value): int
{
    $length = preg_match_all('/./us', $value);

    return $length === false ? PHP_INT_MAX : $length;
}

function gateway_ip_hash(): string
{
    $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return hash('sha256', $address);
}

function gateway_login_throttled(): bool
{
    $pdo = gateway_db();
    $cutoff = time() - 600;
    $pdo->prepare('DELETE FROM admin_login_attempts WHERE attempted_at < ?')->execute([$cutoff]);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE ip_hash = ? AND attempted_at >= ?');
    $statement->execute([gateway_ip_hash(), $cutoff]);

    return (int) $statement->fetchColumn() >= 10;
}

function gateway_record_login_failure(): void
{
    $pdo = gateway_db();
    $pdo->prepare('INSERT INTO admin_login_attempts (ip_hash, attempted_at) VALUES (?, ?)')
        ->execute([gateway_ip_hash(), time()]);
}

function gateway_clear_login_failures(): void
{
    gateway_db()->prepare('DELETE FROM admin_login_attempts WHERE ip_hash = ?')->execute([gateway_ip_hash()]);
}

function gateway_require_device(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ($headers['Authorization'] ?? $headers['authorization'] ?? ''));
    if (! preg_match('/^Bearer ([a-f0-9]{64})$/i', trim($authorization), $match)) {
        gateway_json(['error' => 'Device authorization required.'], 401);
    }

    $deviceId = trim((string) ($_SERVER['HTTP_X_DEVICE_ID'] ?? $headers['X-Device-ID'] ?? $headers['x-device-id'] ?? ''));
    if (! preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $deviceId)) {
        gateway_json(['error' => 'Invalid device identifier.'], 400);
    }

    $pdo = gateway_db();
    $statement = $pdo->prepare('SELECT id, label, token_hash, device_id, active FROM devices WHERE token_hash = ? AND active = 1');
    $statement->execute([hash('sha256', strtolower($match[1]))]);
    $device = $statement->fetch();
    if (! $device || ! hash_equals((string) $device['token_hash'], hash('sha256', strtolower($match[1])))) {
        gateway_json(['error' => 'Device authorization failed.'], 401);
    }

    if ($device['device_id'] === null) {
        $bind = $pdo->prepare('UPDATE devices SET device_id = ? WHERE id = ? AND device_id IS NULL AND active = 1');
        $bind->execute([$deviceId, $device['id']]);
        $statement->execute([hash('sha256', strtolower($match[1]))]);
        $device = $statement->fetch();
    }
    if (! $device || ! is_string($device['device_id']) || ! hash_equals($device['device_id'], $deviceId)) {
        gateway_json(['error' => 'This token is registered to a different device.'], 403);
    }

    return $device;
}
