# ConectaDesk

O ConectaDesk é uma plataforma de Service Desk que centraliza solicitações, facilita o acompanhamento dos chamados e melhora a comunicação entre usuários e equipe. O sistema também identifica dificuldades recorrentes e oferece materiais para aumentar a autonomia dos usuários.

## Tecnologias

PHP 8.3, MySQL 8.4, HTML, CSS, JavaScript, PDO e Docker.

## Execução

Pré-requisito: Docker Desktop.

Na pasta do projeto:

```powershell
docker compose down -v
docker compose up -d --build
```

Acesse `http://localhost:8000`.

## Acessos de teste

Senha: `Conecta@123`

- `admin` — administrador
- `maria` — administrador
- `joao` — administrador
- `julia` — usuário comum
- `carlos` — usuário comum

## Banco

`database/schema.sql` cria o banco e as tabelas. `database/seed.php` insere os dados de demonstração.

## Pastas

- `api/` — backend e endpoints
- `public/` — frontend
- `config/` — conexão e configurações
- `database/` — SQL e dados iniciais
- `storage/` — arquivos enviados

Documentação complementar: `MEMORIAL_TECNICO.md` e `DICIONARIO_DE_DADOS.md`.
