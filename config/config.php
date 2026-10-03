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

session_name('conectadesk_session');
session_start();

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


    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $exception) {
        throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $exception);
    }

    return $pdo;
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

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
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
    unset($user['password_hash']);
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

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        json_response(['error' => 'Não foi possível salvar a imagem.'], 500);
    }

    return [
        'path' => 'storage/uploads/' . $name,
        'original_name' => $file['name'],
        'mime_type' => $mime,
    ];
}

function pdf_escape(string $text): string
{
    $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function build_pdf(string $title, array $lines): string
{
    $allLines = array_merge([$title, ''], $lines);
    $allLines = array_slice($allLines, 0, 48);

    $stream = "BT\n/F1 16 Tf\n50 790 Td\n";
    foreach ($allLines as $index => $line) {
        $fontSize = $index === 0 ? 16 : 10;
        if ($index > 0) {
            $stream .= "0 -17 Td\n";
        }
        $stream .= "/F1 {$fontSize} Tf\n(" . pdf_escape((string)$line) . ") Tj\n";
    }
    $stream .= "ET\n";

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $index => $object) {
        $objectNumber = $index + 1;
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= $objectNumber . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

    return $pdf;
}

function send_simple_pdf(string $title, array $lines, string $filename): never
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo build_pdf($title, $lines);
    exit;
}

function send_excel(string $title, array $headers, array $rows, string $filename): never
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="' . count($headers) . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</th></tr>';
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

function export_dashboard(string $format): never
{
    $pdo = db();
    $total = (int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();

    $status = [];
    foreach (['Aberto', 'Em Atendimento', 'Concluído'] as $item) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE status = ?');
        $stmt->execute([$item]);
        $status[$item] = (int)$stmt->fetchColumn();
    }

    $categories = $pdo->query(
        'SELECT c.name, COUNT(t.id) AS total
         FROM categories c LEFT JOIN tickets t ON t.category_id = c.id
         GROUP BY c.id, c.name ORDER BY total DESC, c.name'
    )->fetchAll();

    $users = $pdo->query(
        'SELECT u.name, COUNT(t.id) AS total,
                COALESCE(SUM(CASE WHEN t.status = "Concluído" THEN 1 ELSE 0 END), 0) AS solved
         FROM users u LEFT JOIN tickets t ON t.requester_id = u.id
         GROUP BY u.id, u.name ORDER BY total DESC, u.name'
    )->fetchAll();

    if ($format === 'excel') {
        $rows = [];
        foreach ($categories as $category) {
            $rows[] = ['Categoria', $category['name'], 'Chamados', $category['total']];
        }
        foreach ($users as $user) {
            $rows[] = ['Usuário', $user['name'], 'Chamados', $user['total'] . ' | Concluídos: ' . $user['solved']];
        }
        send_excel('Dashboard ConectaDesk', ['Tipo', 'Nome', 'Indicador', 'Valor'], $rows, 'conectadesk-dashboard.xls');
    }

    $lines = [
        'Total de chamados: ' . $total,
        'Abertos: ' . $status['Aberto'],
        'Em atendimento: ' . $status['Em Atendimento'],
        'Concluídos: ' . $status['Concluído'],
        '',
        'Chamados por categoria:',
    ];
    foreach ($categories as $category) {
        $lines[] = $category['name'] . ': ' . $category['total'];
    }
    $lines[] = '';
    $lines[] = 'Chamados por usuário:';
    foreach ($users as $user) {
        $lines[] = $user['name'] . ': ' . $user['total'] . ' chamados | ' . $user['solved'] . ' concluídos';
    }

    send_simple_pdf('Dashboard ConectaDesk', $lines, 'conectadesk-dashboard.pdf');
}

function export_reports(string $format): never
{
    $pdo = db();

    $ratings = $pdo->query(
        'SELECT u.name, COUNT(r.id) AS evaluations, ROUND(COALESCE(AVG(r.rating), 0), 2) AS average_rating
         FROM users u LEFT JOIN ticket_ratings r ON r.user_id = u.id
         GROUP BY u.id, u.name ORDER BY u.name'
    )->fetchAll();

    $knowledge = $pdo->query(
        'SELECT c.title,
                COALESCE(SUM(cr.opened), 0) AS opens,
                COALESCE(SUM(cr.liked), 0) AS likes,
                (SELECT COUNT(*) FROM content_comments cc WHERE cc.content_id = c.id) AS comments
         FROM contents c LEFT JOIN content_recipients cr ON cr.content_id = c.id
         WHERE c.type = "material"
         GROUP BY c.id, c.title ORDER BY c.created_at DESC'
    )->fetchAll();

    $tickets = $pdo->query(
        'SELECT status, COUNT(*) AS total FROM tickets
         GROUP BY status ORDER BY FIELD(status, "Aberto", "Em Atendimento", "Concluído")'
    )->fetchAll();

    if ($format === 'excel') {
        $rows = [];
        foreach ($ratings as $rating) {
            $rows[] = ['Satisfação', $rating['name'], $rating['evaluations'], $rating['average_rating']];
        }
        foreach ($knowledge as $item) {
            $rows[] = ['Conhecimento', $item['title'], $item['opens'], 'Curtidas: ' . $item['likes'] . ' | Comentários: ' . $item['comments']];
        }
        foreach ($tickets as $ticket) {
            $rows[] = ['Chamados', $ticket['status'], $ticket['total'], ''];
        }
        send_excel('Relatórios ConectaDesk', ['Grupo', 'Item', 'Quantidade', 'Detalhes'], $rows, 'conectadesk-relatorios.xls');
    }

    $lines = ['Resultados dos chamados'];
    foreach ($tickets as $ticket) {
        $lines[] = $ticket['status'] . ': ' . $ticket['total'];
    }
    $lines[] = '';
    $lines[] = 'Satisfação dos usuários';
    foreach ($ratings as $rating) {
        $lines[] = $rating['name'] . ' | avaliações: ' . $rating['evaluations'] . ' | média: ' . $rating['average_rating'];
    }
    $lines[] = '';
    $lines[] = 'Base de Conhecimento';
    foreach ($knowledge as $item) {
        $lines[] = $item['title'] . ' | aberturas: ' . $item['opens'] . ' | curtidas: ' . $item['likes'] . ' | comentários: ' . $item['comments'];
    }

    send_simple_pdf('Relatórios ConectaDesk', $lines, 'conectadesk-relatorios.pdf');
}
