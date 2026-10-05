<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$pdo = db();

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

echo "Migração concluída." . PHP_EOL;
