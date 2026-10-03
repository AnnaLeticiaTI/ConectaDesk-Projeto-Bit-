<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Cache-Control: no-store');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    // login e cadastro
    if ($path === '/api/auth/login' && $method === 'POST') {
        $data = request_json();
        $login = clean((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($login === '' || $password === '') {
            json_response(['error' => 'Informe usuário e senha.'], 422);
        }


        $stmt = db()->prepare(
            'SELECT * FROM users WHERE username = :username_login OR email = :email_login OR secondary_email = :secondary_login LIMIT 1'
        );

        $stmt->execute([
            'username_login' => $login,
            'email_login' => $login,
            'secondary_login' => $login,
        ]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            json_response(['error' => 'Usuário ou senha inválidos.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user'] = public_user($user);
        json_response(['user' => $_SESSION['user']]);
    }

    if ($path === '/api/auth/register' && $method === 'POST') {
        $data = request_json();
        $name = clean((string)($data['name'] ?? ''));
        $username = clean((string)($data['username'] ?? ''));
        $email = clean((string)($data['email'] ?? ''));
        $secondaryEmail = clean((string)($data['secondary_email'] ?? ''));
        $phone = clean((string)($data['phone'] ?? ''));
        $department = clean((string)($data['department'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($name === '' || $username === '' || $email === '' || $password === '') {
            json_response(['error' => 'Preencha os campos obrigatórios.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Informe um e-mail empresarial válido.'], 422);
        }

        if ($secondaryEmail !== '' && !filter_var($secondaryEmail, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Informe um e-mail secundário válido.'], 422);
        }

        if (strlen($password) < 8) {
            json_response(['error' => 'A senha deve ter pelo menos 8 caracteres.'], 422);
        }

        $stmt = db()->prepare(
            'SELECT id FROM users
             WHERE username = :username
                OR email = :email
                OR (:secondary_email_check <> "" AND secondary_email = :secondary_email_value)
             LIMIT 1'
        );
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'secondary_email_check' => $secondaryEmail,
            'secondary_email_value' => $secondaryEmail,
        ]);

        if ($stmt->fetch()) {
            json_response(['error' => 'Usuário ou e-mail já cadastrado.'], 409);
        }


        $stmt = db()->prepare(
            'INSERT INTO users
                (name, username, password_hash, email, secondary_email, phone, department, role, created_at)
             VALUES
                (:name, :username, :password_hash, :email, :secondary_email, :phone, :department, "user", :created_at)'
        );
        $stmt->execute([
            'name' => $name,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'secondary_email' => $secondaryEmail !== '' ? $secondaryEmail : null,
            'phone' => $phone !== '' ? $phone : null,
            'department' => $department !== '' ? $department : null,
            'created_at' => now(),
        ]);

        json_response(['ok' => true], 201);
    }

    if ($path === '/api/auth/logout' && $method === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        json_response(['ok' => true]);
    }

    if ($path === '/api/auth/me' && $method === 'GET') {
        json_response(['user' => current_user()]);
    }

    // categorias e usuários
    if ($path === '/api/categories' && $method === 'GET') {
        require_auth();
        $rows = db()->query('SELECT id, name, severity FROM categories ORDER BY name')->fetchAll();
        json_response(['items' => $rows]);
    }

    if ($path === '/api/users' && $method === 'GET') {
        require_admin();
        $rows = db()->query(
            'SELECT id, name, username, email, secondary_email, phone, department, role, is_super_admin, avatar_path
             FROM users ORDER BY name'
        )->fetchAll();
        json_response(['items' => $rows]);
    }

    if ($path === '/api/users/role' && $method === 'POST') {
        $admin = require_admin();
        if ((int)$admin['is_super_admin'] !== 1) {
            json_response(['error' => 'Somente administradores autorizados podem alterar permissões.'], 403);
        }

        $data = request_json();
        $userId = (int)($data['user_id'] ?? 0);
        $role = (string)($data['role'] ?? 'user');

        if ($userId < 1 || !in_array($role, ['admin', 'user'], true)) {
            json_response(['error' => 'Dados de permissão inválidos.'], 422);
        }

        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);
        json_response(['ok' => true]);
    }

    // perfil
    if ($path === '/api/profile' && $method === 'PUT') {
        $user = require_auth();
        $data = request_json();
        $name = clean((string)($data['name'] ?? $user['name']));
        $email = clean((string)($data['email'] ?? $user['email']));
        $secondaryEmail = clean((string)($data['secondary_email'] ?? ''));
        $phone = clean((string)($data['phone'] ?? ''));
        $department = clean((string)($data['department'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Informe nome e e-mail válidos.'], 422);
        }

        if ($secondaryEmail !== '' && !filter_var($secondaryEmail, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Informe um e-mail secundário válido.'], 422);
        }

        $stmt = db()->prepare(
            'SELECT id FROM users
             WHERE id <> :id
               AND (email = :email_check OR (:secondary_email_check <> "" AND secondary_email = :secondary_email_value))
             LIMIT 1'
        );
        $stmt->execute([
            'id' => (int)$user['id'],
            'email_check' => $email,
            'secondary_email_check' => $secondaryEmail,
            'secondary_email_value' => $secondaryEmail,
        ]);

        if ($stmt->fetch()) {
            json_response(['error' => 'Este e-mail já está sendo utilizado.'], 409);
        }

        db()->prepare(
            'UPDATE users
             SET name = ?, email = ?, secondary_email = ?, phone = ?, department = ?
             WHERE id = ?'
        )->execute([
            $name,
            $email,
            $secondaryEmail !== '' ? $secondaryEmail : null,
            $phone !== '' ? $phone : null,
            $department !== '' ? $department : null,
            (int)$user['id'],
        ]);

        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int)$user['id']]);
        $_SESSION['user'] = public_user($stmt->fetch());

        json_response(['user' => $_SESSION['user']]);
    }

    if ($path === '/api/profile/avatar' && $method === 'POST') {
        $user = require_auth();
        $file = upload_image($_FILES['avatar'] ?? []);
        if (!$file) {
            json_response(['error' => 'Selecione uma imagem.'], 422);
        }

        db()->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')->execute([$file['path'], (int)$user['id']]);
        $_SESSION['user']['avatar_path'] = $file['path'];
        json_response(['user' => $_SESSION['user']]);
    }

    // chamados
    // filtros
    if ($path === '/api/tickets' && $method === 'GET') {
        require_auth();
        $where = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[] = 't.status = :status';
            $params['status'] = $_GET['status'];
        }
        if (!empty($_GET['category_id'])) {
            $where[] = 't.category_id = :category_id';
            $params['category_id'] = (int)$_GET['category_id'];
        }
        if (!empty($_GET['q'])) {
            $where[] = '(t.title LIKE :query_title OR t.code LIKE :query_code OR u.name LIKE :query_user)';
            $query = '%' . trim((string)$_GET['q']) . '%';
            $params['query_title'] = $query;
            $params['query_code'] = $query;
            $params['query_user'] = $query;
        }
        if (!empty($_GET['from'])) {
            $where[] = 'DATE(t.created_at) >= :date_from';
            $params['date_from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $where[] = 'DATE(t.created_at) <= :date_to';
            $params['date_to'] = $_GET['to'];
        }

        $sql = 'SELECT t.*, c.name AS category, c.severity, u.name AS requester
                FROM tickets t
                INNER JOIN categories c ON c.id = t.category_id
                INNER JOIN users u ON u.id = t.requester_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.created_at DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(['items' => $stmt->fetchAll()]);
    }

    if ($path === '/api/tickets' && $method === 'POST') {
        $user = require_auth();
        $title = clean((string)($_POST['title'] ?? ''));
        $description = clean((string)($_POST['description'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($title === '' || $description === '' || $categoryId < 1) {
            json_response(['error' => 'Título, descrição e categoria são obrigatórios.'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO tickets (code, title, description, category_id, requester_id, status, created_at, updated_at)
                 VALUES (NULL, ?, ?, ?, ?, "Aberto", ?, ?)'
            );
            $stmt->execute([$title, $description, $categoryId, (int)$user['id'], now(), now()]);
            $ticketId = (int)$pdo->lastInsertId();
            $code = ticket_code($ticketId);
            $pdo->prepare('UPDATE tickets SET code = ? WHERE id = ?')->execute([$code, $ticketId]);

            if (isset($_FILES['attachment'])) {
                $file = upload_image($_FILES['attachment']);
                if ($file) {
                    $pdo->prepare(
                        'INSERT INTO ticket_attachments (ticket_id, user_id, file_path, original_name, mime_type, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$ticketId, (int)$user['id'], $file['path'], $file['original_name'], $file['mime_type'], now()]);
                }
            }

            $pdo->commit();
            json_response(['id' => $ticketId, 'code' => $code], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if (preg_match('#^/api/tickets/(\d+)$#', $path, $matches) && $method === 'GET') {
        require_auth();
        $ticketId = (int)$matches[1];
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT t.*, c.name AS category, c.severity, u.name AS requester, u.email AS requester_email
             FROM tickets t
             INNER JOIN categories c ON c.id = t.category_id
             INNER JOIN users u ON u.id = t.requester_id
             WHERE t.id = ?'
        );
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            json_response(['error' => 'Chamado não encontrado.'], 404);
        }

        $stmt = $pdo->prepare(
            'SELECT tc.*, u.name AS user_name, u.role
             FROM ticket_comments tc
             INNER JOIN users u ON u.id = tc.user_id
             WHERE tc.ticket_id = ? ORDER BY tc.created_at'
        );
        $stmt->execute([$ticketId]);
        $ticket['comments'] = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT ta.*, u.name AS user_name
             FROM ticket_attachments ta
             INNER JOIN users u ON u.id = ta.user_id
             WHERE ta.ticket_id = ? ORDER BY ta.created_at'
        );
        $stmt->execute([$ticketId]);
        $ticket['attachments'] = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT * FROM ticket_ratings WHERE ticket_id = ?');
        $stmt->execute([$ticketId]);
        $ticket['rating'] = $stmt->fetch() ?: null;

        json_response($ticket);
    }

    if (preg_match('#^/api/tickets/(\d+)$#', $path, $matches) && $method === 'PUT') {
        $admin = require_admin();
        $ticketId = (int)$matches[1];
        $data = request_json();
        $pdo = db();

        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            json_response(['error' => 'Chamado não encontrado.'], 404);
        }

        $status = (string)($data['status'] ?? $ticket['status']);
        $categoryId = (int)($data['category_id'] ?? $ticket['category_id']);

        if (!in_array($status, ['Aberto', 'Em Atendimento', 'Concluído'], true)) {
            json_response(['error' => 'Status inválido.'], 422);
        }

        if ($categoryId < 1) {
            json_response(['error' => 'Categoria inválida.'], 422);
        }

        $pdo->prepare(
            'UPDATE tickets SET status = ?, category_id = ?, updated_at = ? WHERE id = ?'
        )->execute([$status, $categoryId, now(), $ticketId]);

        if ($status !== $ticket['status']) {
            $pdo->prepare(
                'INSERT INTO notifications (user_id, type, title, body, created_at)
                 VALUES (?, "ticket", ?, ?, ?)'
            )->execute([
                (int)$ticket['requester_id'],
                'Seu chamado tem uma nova movimentação',
                'O chamado ' . $ticket['code'] . ' agora está como ' . $status . '.',
                now(),
            ]);
        }

        json_response(['ok' => true, 'admin' => $admin['name']]);
    }

    if (preg_match('#^/api/tickets/(\d+)/comments$#', $path, $matches) && $method === 'POST') {
        $user = require_auth();
        $ticketId = (int)$matches[1];
        $body = clean((string)($_POST['body'] ?? ''));

        if ($body === '') {
            json_response(['error' => 'O comentário não pode ficar vazio.'], 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            json_response(['error' => 'Chamado não encontrado.'], 404);
        }

        if ($user['role'] !== 'admin' && (int)$ticket['requester_id'] !== (int)$user['id']) {
            json_response(['error' => 'Somente o solicitante pode adicionar informações a este chamado.'], 403);
        }

        $pdo->prepare(
            'INSERT INTO ticket_comments (ticket_id, user_id, body, created_at) VALUES (?, ?, ?, ?)'
        )->execute([$ticketId, (int)$user['id'], $body, now()]);

        if (isset($_FILES['attachment'])) {
            $file = upload_image($_FILES['attachment']);
            if ($file) {
                $pdo->prepare(
                    'INSERT INTO ticket_attachments (ticket_id, user_id, file_path, original_name, mime_type, created_at)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$ticketId, (int)$user['id'], $file['path'], $file['original_name'], $file['mime_type'], now()]);
            }
        }

        if ($user['role'] === 'admin' && (int)$ticket['requester_id'] !== (int)$user['id']) {
            $pdo->prepare(
                'INSERT INTO notifications (user_id, type, title, body, created_at)
                 VALUES (?, "ticket", ?, ?, ?)'
            )->execute([
                (int)$ticket['requester_id'],
                'Seu chamado tem uma nova movimentação',
                'A equipe adicionou uma nova interação ao chamado ' . $ticket['code'] . '.',
                now(),
            ]);
        }

        json_response(['ok' => true], 201);
    }

    if (preg_match('#^/api/tickets/(\d+)/rate$#', $path, $matches) && $method === 'POST') {
        $user = require_auth();
        $ticketId = (int)$matches[1];
        $data = request_json();
        $rating = (int)($data['rating'] ?? 0);
        $comment = clean((string)($data['comment'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            json_response(['error' => 'A nota deve estar entre 1 e 5.'], 422);
        }

        $stmt = db()->prepare('SELECT requester_id, status FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket || (int)$ticket['requester_id'] !== (int)$user['id']) {
            json_response(['error' => 'Acesso negado.'], 403);
        }
        if ($ticket['status'] !== 'Concluído') {
            json_response(['error' => 'A avaliação está disponível após a conclusão do chamado.'], 422);
        }

        db()->prepare(
            'INSERT INTO ticket_ratings (ticket_id, user_id, rating, comment, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)'
        )->execute([$ticketId, (int)$user['id'], $rating, $comment, now()]);

        json_response(['ok' => true]);
    }

    // dashboard
    if ($path === '/api/dashboard' && $method === 'GET') {
        require_admin();
        $pdo = db();

        $total = (int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $status = [];
        foreach (['Aberto', 'Em Atendimento', 'Concluído'] as $item) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE status = ?');
            $stmt->execute([$item]);
            $status[$item] = (int)$stmt->fetchColumn();
        }

        $categories = $pdo->query(
            'SELECT c.id, c.name, c.severity, COUNT(t.id) AS total
             FROM categories c
             LEFT JOIN tickets t ON t.category_id = c.id
             GROUP BY c.id, c.name, c.severity
             ORDER BY total DESC, c.name'
        )->fetchAll();

        $users = $pdo->query(
            'SELECT u.id, u.name,
                    COUNT(t.id) AS total,
                    COALESCE(SUM(CASE WHEN t.status = "Concluído" THEN 1 ELSE 0 END), 0) AS solved
             FROM users u
             LEFT JOIN tickets t ON t.requester_id = u.id
             GROUP BY u.id, u.name
             ORDER BY total DESC, u.name'
        )->fetchAll();

        $daily = $pdo->query(
            'SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM tickets
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day'
        )->fetchAll();

        $averageRating = (float)$pdo->query('SELECT COALESCE(AVG(rating), 0) FROM ticket_ratings')->fetchColumn();
        $content = $pdo->query(
            'SELECT COUNT(*) AS deliveries,
                    COALESCE(SUM(opened), 0) AS opened,
                    COALESCE(SUM(liked), 0) AS liked
             FROM content_recipients'
        )->fetch();

        json_response([
            'total' => $total,
            'status' => $status,
            'categories' => $categories,
            'users' => $users,
            'daily' => $daily,
            'average_rating' => round($averageRating, 2),
            'content' => $content,
        ]);
    }

    // conhecimento e avisos
    if ($path === '/api/contents' && $method === 'GET') {
        $user = require_auth();
        $pdo = db();

        if ($user['role'] === 'admin') {
            $stmt = $pdo->query(
                'SELECT c.*, u.name AS author,
                        COALESCE((SELECT SUM(cr2.opened) FROM content_recipients cr2 WHERE cr2.content_id = c.id), 0) AS opened,
                        COALESCE((SELECT SUM(cr3.liked) FROM content_recipients cr3 WHERE cr3.content_id = c.id), 0) AS liked
                 FROM contents c
                 INNER JOIN users u ON u.id = c.author_id
                 ORDER BY c.created_at DESC'
            );
        } else {
            $stmt = $pdo->prepare(
                'SELECT c.*, u.name AS author, cr.opened, cr.liked, cr.read_at
                 FROM contents c
                 INNER JOIN users u ON u.id = c.author_id
                 INNER JOIN content_recipients cr ON cr.content_id = c.id
                 WHERE cr.user_id = ?
                   AND (cr.end_date IS NULL OR cr.end_date >= CURDATE())
                 ORDER BY c.created_at DESC'
            );
            $stmt->execute([(int)$user['id']]);
        }

        json_response(['items' => $stmt->fetchAll()]);
    }

    if ($path === '/api/contents' && $method === 'POST') {
        $admin = require_admin();
        $data = request_json();
        $title = clean((string)($data['title'] ?? ''));
        $body = clean((string)($data['body'] ?? ''));
        $type = (string)($data['type'] ?? '');
        $category = clean((string)($data['category'] ?? ''));
        $frequency = (string)($data['frequency'] ?? 'once');
        $endDate = clean((string)($data['end_date'] ?? ''));
        $userIds = array_values(array_filter(array_map('intval', $data['user_ids'] ?? [])));

        if ($title === '' || $body === '' || !in_array($type, ['material', 'notice'], true) || !$userIds) {
            json_response(['error' => 'Título, conteúdo, tipo e destinatários são obrigatórios.'], 422);
        }

        if (!in_array($frequency, ['once', 'daily'], true)) {
            json_response(['error' => 'Frequência inválida.'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO contents (title, body, type, category, author_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$title, $body, $type, $category !== '' ? $category : null, (int)$admin['id'], now(), now()]);
            $contentId = (int)$pdo->lastInsertId();

            $recipient = $pdo->prepare(
                'INSERT INTO content_recipients (content_id, user_id, start_date, end_date, frequency, created_at)
                 VALUES (?, ?, CURDATE(), ?, ?, ?)'
            );
            $notification = $pdo->prepare(
                'INSERT INTO notifications (user_id, type, title, body, created_at)
                 VALUES (?, ?, ?, ?, ?)'
            );

            foreach ($userIds as $userId) {
                $recipient->execute([
                    $contentId,
                    $userId,
                    $endDate !== '' ? $endDate : null,
                    $frequency,
                    now(),
                ]);

                $notificationTitle = $type === 'material'
                    ? 'Você tem um novo material de conhecimento!'
                    : 'Você recebeu um novo Aviso!';

                $notification->execute([
                    $userId,
                    $type,
                    $notificationTitle,
                    $title,
                    now(),
                ]);
            }

            $pdo->commit();
            json_response(['id' => $contentId], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if (preg_match('#^/api/contents/(\d+)/interact$#', $path, $matches) && $method === 'POST') {
        $user = require_auth();
        $contentId = (int)$matches[1];
        $data = request_json();
        $action = (string)($data['action'] ?? '');
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT id FROM content_recipients
             WHERE content_id = ? AND user_id = ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$contentId, (int)$user['id']]);
        $recipient = $stmt->fetch();

        if (!$recipient) {
            json_response(['error' => 'Conteúdo não disponível para este usuário.'], 403);
        }

        if ($action === 'read') {
            $pdo->prepare('UPDATE content_recipients SET opened = 1, read_at = ? WHERE id = ?')->execute([now(), (int)$recipient['id']]);
        } elseif ($action === 'like') {
            $pdo->prepare('UPDATE content_recipients SET liked = 1 WHERE id = ?')->execute([(int)$recipient['id']]);
        } else {
            json_response(['error' => 'Ação inválida.'], 422);
        }

        json_response(['ok' => true]);
    }

    if (preg_match('#^/api/contents/(\d+)/comments$#', $path, $matches) && $method === 'POST') {
        $user = require_auth();
        $contentId = (int)$matches[1];
        $data = request_json();
        $body = clean((string)($data['body'] ?? ''));

        if ($body === '') {
            json_response(['error' => 'O comentário não pode ficar vazio.'], 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM content_recipients WHERE content_id = ? AND user_id = ?');
        $stmt->execute([$contentId, (int)$user['id']]);
        if (!$stmt->fetch()) {
            json_response(['error' => 'Conteúdo não disponível para este usuário.'], 403);
        }

        $pdo->prepare(
            'INSERT INTO content_comments (content_id, user_id, body, created_at) VALUES (?, ?, ?, ?)'
        )->execute([$contentId, (int)$user['id'], $body, now()]);

        json_response(['ok' => true], 201);
    }

    if (preg_match('#^/api/contents/(\d+)/detail$#', $path, $matches) && $method === 'GET') {
        $user = require_auth();
        $contentId = (int)$matches[1];
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT c.*, u.name AS author
             FROM contents c
             INNER JOIN users u ON u.id = c.author_id
             WHERE c.id = ?'
        );
        $stmt->execute([$contentId]);
        $content = $stmt->fetch();
        if (!$content) {
            json_response(['error' => 'Conteúdo não encontrado.'], 404);
        }

        if ($user['role'] !== 'admin') {
            $stmt = $pdo->prepare('SELECT id FROM content_recipients WHERE content_id = ? AND user_id = ?');
            $stmt->execute([$contentId, (int)$user['id']]);
            if (!$stmt->fetch()) {
                json_response(['error' => 'Conteúdo não disponível para este usuário.'], 403);
            }
        }

        $stmt = $pdo->prepare(
            'SELECT cc.*, u.name AS user_name
             FROM content_comments cc
             INNER JOIN users u ON u.id = cc.user_id
             WHERE cc.content_id = ? ORDER BY cc.created_at'
        );
        $stmt->execute([$contentId]);
        $content['comments'] = $stmt->fetchAll();

        json_response($content);
    }

    // notificações

    if ($path === '/api/notifications' && $method === 'GET') {
        $user = require_auth();
        $stmt = db()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30'
        );
        $stmt->execute([(int)$user['id']]);
        json_response(['items' => $stmt->fetchAll()]);
    }

    if (preg_match('#^/api/notifications/(\d+)/read$#', $path, $matches) && $method === 'POST') {
        $user = require_auth();
        db()->prepare(
            'UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ?'
        )->execute([now(), (int)$matches[1], (int)$user['id']]);
        json_response(['ok' => true]);
    }

    // relatórios
    if ($path === '/api/reports' && $method === 'GET') {
        require_admin();
        $pdo = db();

        $ratings = $pdo->query(
            'SELECT u.name,
                    COUNT(r.id) AS evaluations,
                    ROUND(COALESCE(AVG(r.rating), 0), 2) AS average_rating
             FROM users u
             LEFT JOIN ticket_ratings r ON r.user_id = u.id
             GROUP BY u.id, u.name
             ORDER BY u.name'
        )->fetchAll();

        $knowledge = $pdo->query(
            'SELECT c.id, c.title,
                    COALESCE(SUM(cr.opened), 0) AS opens,
                    COALESCE(SUM(cr.liked), 0) AS likes,
                    (SELECT COUNT(*) FROM content_comments cc WHERE cc.content_id = c.id) AS comments
             FROM contents c
             LEFT JOIN content_recipients cr ON cr.content_id = c.id
             WHERE c.type = "material"
             GROUP BY c.id, c.title
             ORDER BY c.created_at DESC'
        )->fetchAll();

        $tickets = $pdo->query(
            'SELECT status, COUNT(*) AS total
             FROM tickets GROUP BY status ORDER BY FIELD(status, "Aberto", "Em Atendimento", "Concluído")'
        )->fetchAll();

        $categories = $pdo->query(
            'SELECT c.name, COUNT(t.id) AS total
             FROM categories c
             LEFT JOIN tickets t ON t.category_id = c.id
             GROUP BY c.id, c.name ORDER BY total DESC, c.name'
        )->fetchAll();

        json_response([
            'ratings' => $ratings,
            'knowledge' => $knowledge,
            'tickets' => $tickets,
            'categories' => $categories,
        ]);
    }

    if ($path === '/api/export/dashboard' && $method === 'GET') {
        require_admin();
        export_dashboard((string)($_GET['format'] ?? 'pdf'));
    }

    if ($path === '/api/export/reports' && $method === 'GET') {
        require_admin();
        export_reports((string)($_GET['format'] ?? 'pdf'));
    }

    if (preg_match('#^/api/contents/(\d+)/pdf$#', $path, $matches) && $method === 'GET') {
        $user = require_auth();
        $contentId = (int)$matches[1];
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT c.*, u.name AS author FROM contents c INNER JOIN users u ON u.id = c.author_id WHERE c.id = ?'
        );
        $stmt->execute([$contentId]);
        $content = $stmt->fetch();
        if (!$content) {
            json_response(['error' => 'Conteúdo não encontrado.'], 404);
        }

        if ($user['role'] !== 'admin') {
            $stmt = $pdo->prepare('SELECT id FROM content_recipients WHERE content_id = ? AND user_id = ?');
            $stmt->execute([$contentId, (int)$user['id']]);
            if (!$stmt->fetch()) {
                json_response(['error' => 'Conteúdo não disponível para este usuário.'], 403);
            }
        }

        send_simple_pdf(
            $content['title'],
            [
                'Categoria: ' . ($content['category'] ?: 'Geral'),
                'Autor: ' . $content['author'],
                '',
                $content['body'],
            ],
            'material-' . $contentId . '.pdf'
        );
    }

    if ($path === '/api/health' && $method === 'GET') {
        db()->query('SELECT 1');
        json_response(['ok' => true]);
    }

    json_response(['error' => 'Rota não encontrada.'], 404);
} catch (Throwable $exception) {
    error_log('ConectaDesk: ' . $exception->getMessage());
    json_response(['error' => 'Não foi possível concluir a operação. Verifique o serviço e tente novamente.'], 500);
}
