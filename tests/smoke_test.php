<?php
declare(strict_types=1);

$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$cookie = '';

function request(string $method, string $path, ?array $body = null, ?string $contentType = null): array
{
    global $baseUrl, $cookie;

    $headers = [
        'Accept: application/json',
    ];

    if ($contentType !== null) {
        $headers[] = 'Content-Type: ' . $contentType;
    }

    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $options = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ],
    ];

    if ($body !== null) {
        $options['http']['content'] = $contentType === 'application/json'
            ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : http_build_query($body);
    }

    $context = stream_context_create($options);
    $response = @file_get_contents($baseUrl . $path, false, $context);
    $response = $response === false ? '' : $response;

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int)$matches[1];
        }
        if (stripos($header, 'Set-Cookie:') === 0) {
            $value = trim(substr($header, strlen('Set-Cookie:')));
            $cookie = explode(';', $value, 2)[0];
        }
    }

    return [$status, $response];
}

function assert_status(string $name, int $expected, int $actual): void
{
    if ($actual !== $expected) {
        throw new RuntimeException("$name: esperado HTTP $expected, recebido HTTP $actual");
    }
    echo "PASS: $name\n";
}

function json_body(string $body): array
{
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Resposta JSON inválida.');
    }
    return $data;
}

try {
    [$status] = request('GET', '/api/health');
    assert_status('healthcheck', 200, $status);

    [$status] = request('POST', '/api/auth/login', [], 'application/json');
    assert_status('login sem dados', 422, $status);

    [$status] = request('POST', '/api/auth/login', [
        'username' => 'admin',
        'password' => 'senha-incorreta',
    ], 'application/json');
    assert_status('login inválido', 401, $status);

    [$status, $body] = request('POST', '/api/auth/login', [
        'username' => 'admin',
        'password' => 'Conecta@123',
    ], 'application/json');
    assert_status('login válido', 200, $status);

    $login = json_body($body);
    if (($login['user']['role'] ?? '') !== 'admin') {
        throw new RuntimeException('Login válido não retornou perfil administrador.');
    }
    echo "PASS: perfil administrador\n";

    [$status, $body] = request('GET', '/api/auth/me');
    assert_status('sessão autenticada', 200, $status);
    $currentUser = json_body($body)['user'] ?? [];
    [$status] = request('PUT', '/api/profile', [
        'name' => $currentUser['name'] ?? 'Administrador Principal',
        'email' => $currentUser['email'] ?? 'admin@conectadesk.local',
        'secondary_email' => $currentUser['secondary_email'] ?? '',
        'phone' => $currentUser['phone'] ?? '',
        'department' => $currentUser['department'] ?? 'Tecnologia da Informação',
    ], 'application/json');
    assert_status('atualização de perfil', 200, $status);

    [$status, $body] = request('GET', '/api/categories');
    assert_status('categorias', 200, $status);
    $categories = json_body($body);
    $categoryId = (int)($categories['items'][0]['id'] ?? 0);
    if ($categoryId < 1) {
        throw new RuntimeException('Nenhuma categoria disponível para os testes.');
    }
    echo "PASS: categoria disponível\n";

    foreach ([
        ['/api/dashboard', 'dashboard'],
        ['/api/reports', 'relatórios'],
        ['/api/tickets', 'chamados'],
        ['/api/notifications', 'notificações'],
    ] as [$path, $name]) {
        [$status] = request('GET', $path);
        assert_status($name, 200, $status);
    }

    [$status, $body] = request('POST', '/api/tickets', [
        'title' => 'Teste automatizado ConectaDesk',
        'description' => 'Chamado criado pelo teste automatizado.',
        'category_id' => (string)$categoryId,
    ], 'application/x-www-form-urlencoded');
    assert_status('criação de chamado', 201, $status);
    $ticket = json_body($body);
    $ticketId = (int)($ticket['id'] ?? 0);
    if ($ticketId < 1) {
        throw new RuntimeException('Criação de chamado não retornou ID.');
    }
    echo "PASS: ID do chamado criado\n";

    [$status] = request('GET', '/api/tickets/' . $ticketId);
    assert_status('detalhe do chamado', 200, $status);

    [$status] = request('PUT', '/api/tickets/' . $ticketId, [
        'title' => 'Teste automatizado ConectaDesk atualizado',
        'description' => 'Chamado atualizado pelo teste automatizado.',
        'category_id' => (string)$categoryId,
    ], 'application/json');
    assert_status('edição de chamado aberto', 200, $status);

    [$status, $body] = request('GET', '/api/contents');
    assert_status('conteúdos', 200, $status);
    $contents = json_body($body);
    $contentId = (int)($contents['items'][0]['id'] ?? 0);
    if ($contentId > 0) {
        [$status, $body] = request('GET', '/api/contents/' . $contentId . '/pdf');
        assert_status('PDF de material', 200, $status);
        if (strncmp($body, '%PDF-', 5) !== 0) {
            throw new RuntimeException('PDF de material não retornou um documento PDF válido.');
        }
        echo "PASS: conteúdo do PDF de material\n";
    }

    [$status] = request('GET', '/api/export/dashboard?format=pdf');
    assert_status('exportação dashboard PDF', 200, $status);

    [$status, $body] = request('GET', '/api/export/dashboard?format=excel');
    assert_status('exportação dashboard Excel', 200, $status);
    if (stripos($body, '<html') === false) {
        throw new RuntimeException('Exportação Excel do dashboard não retornou HTML compatível.');
    }
    echo "PASS: conteúdo do Excel do dashboard\n";

    [$status] = request('GET', '/api/export/reports?format=pdf');
    assert_status('exportação relatórios PDF', 200, $status);

    [$status, $body] = request('GET', '/api/export/reports?format=excel');
    assert_status('exportação relatórios Excel', 200, $status);
    if (stripos($body, '<html') === false) {
        throw new RuntimeException('Exportação Excel dos relatórios não retornou HTML compatível.');
    }
    echo "PASS: conteúdo do Excel dos relatórios\n";

    [$status] = request('DELETE', '/api/tickets/' . $ticketId);
    assert_status('exclusão de chamado aberto', 200, $status);

    [$status] = request('GET', '/api/tickets/' . $ticketId);
    assert_status('chamado excluído não encontrado', 404, $status);

    [$status] = request('POST', '/api/auth/logout');
    assert_status('logout', 200, $status);

    echo "\nTodos os testes automatizados passaram.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "\nFALHA: {$exception->getMessage()}\n");
    exit(1);
}
