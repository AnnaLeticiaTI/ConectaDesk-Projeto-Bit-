<?php
declare(strict_types=1);

// configurações
const APP_NAME = 'ConectaDesk';
const UPLOAD_DIR = __DIR__ . '/../storage/uploads/';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const ALLOWED_UPLOADS = ['image/jpeg', 'image/png', 'image/webp'];

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}


function load_env_file(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $file = __DIR__ . '/../.env';
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

function envv(string $key, string $default = ''): string
{
    load_env_file();
    $value = getenv($key);
    return $value === false ? $default : $value;
}

// banco de dados
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = envv('DB_HOST', '127.0.0.1');
    $port = envv('DB_PORT', '3306');
    $name = envv('DB_NAME', 'conectadesk');
    $user = envv('DB_USER', 'conectadesk');
    $password = envv('DB_PASSWORD', 'conectadesk');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $sslMode = envv('DB_SSL_MODE');
    $sslCa = envv('DB_SSL_CA');
    $sslCaContent = envv('DB_SSL_CA_CONTENT');

    if ($sslMode !== '') {
        $dsn .= ";sslmode=" . strtolower($sslMode);
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($sslCaContent !== '') {
        $sslCa = sys_get_temp_dir() . '/conectadesk-aiven-ca.pem';
        if (!is_file($sslCa)) {
            file_put_contents($sslCa, $sslCaContent, LOCK_EX);
            chmod($sslCa, 0600);
        }
    }

    if ($sslCa !== '') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    try {
        $pdo = new PDO($dsn, $user, $password, $options);
    } catch (Throwable $exception) {
        throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $exception);
    }

    return $pdo;
}

function ensure_file_storage_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $columns = [
        ['users', 'avatar_data', 'LONGBLOB NULL'],
        ['users', 'avatar_mime_type', 'VARCHAR(80) NULL'],
        ['ticket_attachments', 'file_data', 'LONGBLOB NULL'],
    ];

    foreach ($columns as [$table, $column, $definition]) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    $checked = true;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];
    return is_array($data) ? $data : [];
}

function auth_cookie_name(): string
{
    return 'conectadesk_auth';
}

function set_auth_cookie(string $token, int $expiresAt): void
{
    setcookie(auth_cookie_name(), $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || envv('VERCEL') === '1',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function create_auth_session(array $user): array
{
    $token = bin2hex(random_bytes(32));
    $expiresAt = time() + (8 * 60 * 60);
    $expiresAtDb = date('Y-m-d H:i:s', $expiresAt);

    db()->prepare(
        'INSERT INTO auth_sessions (token_hash, user_id, expires_at, created_at) VALUES (?, ?, ?, ?)'
    )->execute([
        hash('sha256', $token),
        (int)$user['id'],
        $expiresAtDb,
        now(),
    ]);

    set_auth_cookie($token, $expiresAt);
    return public_user($user);
}

function clear_auth_session(): void
{
    $token = $_COOKIE[auth_cookie_name()] ?? '';
    if ($token !== '') {
        db()->prepare('DELETE FROM auth_sessions WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }

    setcookie(auth_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || envv('VERCEL') === '1',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_user(): ?array
{
    $token = $_COOKIE[auth_cookie_name()] ?? '';
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.*
         FROM auth_sessions s
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.token_hash = :token_hash AND s.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    return public_user($user);
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['error' => 'Não autenticado.'], 401);
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if (($user['role'] ?? '') !== 'admin') {
        json_response(['error' => 'Acesso restrito a administradores.'], 403);
    }
    return $user;
}

function clean(string $value): string
{
    return trim(strip_tags($value));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function ticket_code(int $id): string
{
    return 'REQ-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function public_user(array $user): array
{
    unset($user['password_hash'], $user['avatar_data'], $user['avatar_mime_type']);
    return $user;
}

// arquivos
function upload_image(array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['error' => 'Não foi possível enviar a imagem.'], 422);
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        json_response(['error' => 'A imagem deve ter no máximo 5 MB.'], 422);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_UPLOADS, true)) {
        json_response(['error' => 'Formato de imagem não permitido.'], 422);
    }

    $extension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ][$mime];

    $name = bin2hex(random_bytes(12)) . '.' . $extension;
    $path = UPLOAD_DIR . $name;

    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        json_response(['error' => 'Não foi possível ler a imagem.'], 422);
    }

    @file_put_contents($path, $data, LOCK_EX);

    return [
        'path' => 'storage/uploads/' . $name,
        'original_name' => $file['name'],
        'mime_type' => $mime,
        'data' => $data,
    ];
}

