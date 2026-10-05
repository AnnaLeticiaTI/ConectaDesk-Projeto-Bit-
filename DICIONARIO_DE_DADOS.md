# Dicionário de Dados — ConectaDesk

Banco: MySQL 8.4

A estrutura abaixo corresponde ao `database/schema.sql`.

## users

- `id` — INT UNSIGNED, PK — identificador do usuário
- `name` — VARCHAR(120) — nome
- `username` — VARCHAR(80), UNIQUE — usuário de login
- `password_hash` — VARCHAR(255) — senha armazenada em hash
- `email` — VARCHAR(180), UNIQUE — e-mail principal
- `secondary_email` — VARCHAR(180) — e-mail secundário
- `phone` — VARCHAR(30) — telefone
- `department` — VARCHAR(100) — departamento
- `role` — ENUM('admin','user') — perfil de acesso
- `is_super_admin` — TINYINT(1) — permissão para alterar perfis
- `avatar_path` — VARCHAR(255) — caminho da foto de perfil
- `created_at` — DATETIME — data de cadastro

## categories

- `id` — INT UNSIGNED, PK — identificador da categoria
- `name` — VARCHAR(80), UNIQUE — nome da categoria
- `severity` — TINYINT UNSIGNED — nível de severidade

## tickets

- `id` — INT UNSIGNED, PK — identificador do chamado
- `code` — VARCHAR(20), UNIQUE — código do chamado
- `title` — VARCHAR(180) — título
- `description` — TEXT — descrição da solicitação
- `category_id` — INT UNSIGNED, FK — categoria do chamado
- `requester_id` — INT UNSIGNED, FK — usuário que abriu o chamado
- `status` — ENUM('Aberto','Em Atendimento','Concluído') — situação do chamado
- `created_at` — DATETIME — data de abertura
- `updated_at` — DATETIME — data da última alteração

`category_id` referencia `categories.id`.
`requester_id` referencia `users.id`.

## ticket_attachments

- `id` — INT UNSIGNED, PK — identificador do anexo
- `ticket_id` — INT UNSIGNED, FK — chamado relacionado
- `user_id` — INT UNSIGNED, FK — usuário que enviou o arquivo
- `file_path` — VARCHAR(255) — caminho do arquivo
- `original_name` — VARCHAR(255) — nome original do arquivo
- `mime_type` — VARCHAR(80) — tipo do arquivo
- `created_at` — DATETIME — data do envio

## ticket_comments

- `id` — INT UNSIGNED, PK — identificador do comentário
- `ticket_id` — INT UNSIGNED, FK — chamado relacionado
- `user_id` — INT UNSIGNED, FK — usuário que comentou
- `body` — TEXT — conteúdo do comentário
- `created_at` — DATETIME — data do comentário

## ticket_ratings

- `id` — INT UNSIGNED, PK — identificador da avaliação
- `ticket_id` — INT UNSIGNED, UNIQUE/FK — chamado avaliado
- `user_id` — INT UNSIGNED, FK — usuário que avaliou
- `rating` — TINYINT UNSIGNED — nota de 1 a 5
- `comment` — TEXT — comentário opcional da avaliação
- `created_at` — DATETIME — data da avaliação

## contents

- `id` — INT UNSIGNED, PK — identificador do conteúdo
- `title` — VARCHAR(180) — título
- `body` — TEXT — conteúdo
- `type` — ENUM('material','notice') — tipo do conteúdo
- `category` — VARCHAR(100) — categoria
- `author_id` — INT UNSIGNED, FK — administrador que criou
- `created_at` — DATETIME — data de criação
- `updated_at` — DATETIME — data da última alteração

## content_recipients

- `id` — INT UNSIGNED, PK — identificador do envio
- `content_id` — INT UNSIGNED, FK — conteúdo enviado
- `user_id` — INT UNSIGNED, FK — usuário destinatário
- `start_date` — DATE — início do período
- `frequency` — ENUM('once','daily') — frequência de envio
- `opened` — TINYINT(1) — indica se o conteúdo foi aberto
- `read_at` — DATETIME — data da leitura
- `liked` — TINYINT(1) — indica se o conteúdo foi curtido
- `created_at` — DATETIME — data do envio

Existe uma chave única para `content_id`, `user_id` e `start_date`.

## content_comments

- `id` — INT UNSIGNED, PK — identificador do comentário
- `content_id` — INT UNSIGNED, FK — conteúdo comentado
- `user_id` — INT UNSIGNED, FK — usuário que comentou
- `body` — TEXT — conteúdo do comentário
- `created_at` — DATETIME — data do comentário

## notifications

- `id` — INT UNSIGNED, PK — identificador da notificação
- `user_id` — INT UNSIGNED, FK — usuário que recebe
- `type` — ENUM('material','notice','ticket') — tipo da notificação
- `title` — VARCHAR(180) — título
- `body` — TEXT — mensagem
- `read_at` — DATETIME — data da leitura
- `created_at` — DATETIME — data da notificação

As relações e chaves estrangeiras estão definidas no `database/schema.sql`.
