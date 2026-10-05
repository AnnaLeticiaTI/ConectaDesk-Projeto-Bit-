<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$pdo = db();
$pdo->exec("SET NAMES utf8mb4");

$categoryNames = [
    1 => 'Suporte',
    2 => 'Manutenção',
    3 => 'Requisição',
    4 => 'Acesso e Permissões',
    5 => 'Sistemas e Aplicações',
    6 => 'Infraestrutura',
    7 => 'Equipamentos',
    8 => 'Rede e Conectividade',
];
$categoryUpdate = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
foreach ($categoryNames as $categoryId => $categoryName) {
    $categoryUpdate->execute([$categoryName, $categoryId]);
}

// dados iniciais

$users = [
    ['Administrador Principal', 'admin', 'admin@conectadesk.local', null, null, 'Tecnologia da Informação', 'admin', 1],
    ['Maria Silva', 'maria', 'maria@conectadesk.local', null, null, 'Tecnologia da Informação', 'admin', 1],
    ['João Santos', 'joao', 'joao@conectadesk.local', null, null, 'Tecnologia da Informação', 'admin', 1],
    ['Julia Mendes', 'julia', 'julia@conectadesk.local', null, null, 'Recursos Humanos', 'user', 0],
    ['Carlos Oliveira', 'carlos', 'carlos@conectadesk.local', null, null, 'Financeiro', 'user', 0],
];

$userStatement = $pdo->prepare(
    'INSERT INTO users
        (name, username, password_hash, email, secondary_email, phone, department, role, is_super_admin, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        password_hash = VALUES(password_hash),
        email = VALUES(email),
        secondary_email = VALUES(secondary_email),
        phone = VALUES(phone),
        department = VALUES(department),
        role = VALUES(role),
        is_super_admin = VALUES(is_super_admin)'
);

// usuários de teste
foreach ($users as $user) {
    $userStatement->execute([
        $user[0],
        $user[1],
        password_hash('Conecta@123', PASSWORD_DEFAULT),
        $user[2],
        $user[3],
        $user[4],
        $user[5],
        $user[6],
        $user[7],
        now(),
    ]);
}

// dados de exemplo
if ((int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn() === 0) {
    $julia = (int)$pdo->query("SELECT id FROM users WHERE username = 'julia'")->fetchColumn();
    $admin = (int)$pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
    $suporte = (int)$pdo->query("SELECT id FROM categories WHERE name = 'Suporte'")->fetchColumn();
    $acesso = (int)$pdo->query("SELECT id FROM categories WHERE name = 'Acesso e Permissões'")->fetchColumn();
    $equipamentos = (int)$pdo->query("SELECT id FROM categories WHERE name = 'Equipamentos'")->fetchColumn();
    $manutencao = (int)$pdo->query("SELECT id FROM categories WHERE name = 'Manutenção'")->fetchColumn();

    $ticketStatement = $pdo->prepare(
        'INSERT INTO tickets (code, title, description, category_id, requester_id, status, created_at, updated_at)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)'
    );

    $tickets = [
        ['Ajuda com cabo Ethernet', 'Preciso de orientação para conectar o equipamento à rede.', $suporte, $julia, 'Aberto'],
        ['Acesso ao sistema interno', 'Não consigo acessar o sistema interno da empresa.', $acesso, $julia, 'Em Atendimento'],
        ['Computador sem conexão', 'A estação está sem acesso à rede.', $equipamentos, $admin, 'Concluído'],
        ['Manutenção preventiva', 'O equipamento apresenta lentidão e precisa de avaliação.', $manutencao, $julia, 'Aberto'],
    ];

    foreach ($tickets as $index => $ticket) {
        $createdAt = date('Y-m-d H:i:s', strtotime('-' . ($index * 2) . ' days'));
        $ticketStatement->execute([
            $ticket[0],
            $ticket[1],
            $ticket[2],
            $ticket[3],
            $ticket[4],
            $createdAt,
            $createdAt,
        ]);

        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE tickets SET code = ? WHERE id = ?')->execute([ticket_code($id), $id]);
    }
}

// materiais e avisos
if ((int)$pdo->query('SELECT COUNT(*) FROM contents')->fetchColumn() === 0) {
    $admin = (int)$pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
    $julia = (int)$pdo->query("SELECT id FROM users WHERE username = 'julia'")->fetchColumn();
    $carlos = (int)$pdo->query("SELECT id FROM users WHERE username = 'carlos'")->fetchColumn();

    $contentStatement = $pdo->prepare(
        'INSERT INTO contents (title, body, type, category, author_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $recipientStatement = $pdo->prepare(
        'INSERT INTO content_recipients (content_id, user_id, start_date, frequency, created_at)
         VALUES (?, ?, CURDATE(), "once", ?)'
    );
    $notificationStatement = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, created_at)
         VALUES (?, ?, ?, ?, ?)'
    );

    $contentStatement->execute([
        'Como redefinir sua senha',
        'Acesse o portal de acesso, selecione a opção de redefinição e siga as instruções apresentadas.',
        'material',
        'Acesso',
        $admin,
        now(),
        now(),
    ]);
    $materialId = (int)$pdo->lastInsertId();

    foreach ([$julia, $carlos] as $userId) {
        $recipientStatement->execute([$materialId, $userId, now()]);
        $notificationStatement->execute([
            $userId,
            'material',
            'Você tem um novo material de conhecimento!',
            'Como redefinir sua senha está disponível na Base de Conhecimento.',
            now(),
        ]);
    }

    $contentStatement->execute([
        'Manutenção programada',
        'A equipe de TI realizará uma manutenção preventiva nos equipamentos nesta semana.',
        'notice',
        'Manutenção',
        $admin,
        now(),
        now(),
    ]);
    $noticeId = (int)$pdo->lastInsertId();

    foreach ([$julia, $carlos] as $userId) {
        $recipientStatement->execute([$noticeId, $userId, now()]);
        $notificationStatement->execute([
            $userId,
            'notice',
            'Você recebeu um novo Aviso!',
            'Manutenção programada está disponível na área de Avisos.',
            now(),
        ]);
    }
}

echo "Seed concluído." . PHP_EOL;
