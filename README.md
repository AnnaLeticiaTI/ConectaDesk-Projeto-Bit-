# ConectaDesk

Sistema de Service Desk para abertura e acompanhamento de chamados, materiais de apoio e controle do atendimento.

## Tecnologias

PHP 8.3, MySQL 8.4, HTML, CSS, JavaScript, PDO e Docker.

## Rodar o projeto

É necessário ter o Docker Desktop instalado.

Na pasta do projeto:

```powershell
docker compose down -v
docker compose up -d --build
```

Depois acesse:

`http://localhost:8000`

## Acessos de teste

Senha: `Conecta@123`

- `admin` — administrador
- `maria` — administrador
- `joao` — administrador
- `julia` — usuário comum
- `carlos` — usuário comum

## Banco

O banco é criado pelo `database/schema.sql` e os dados de teste pelo `database/seed.php`.

## Pastas principais

- `api/` — backend e endpoints
- `public/` — frontend
- `config/` — conexão e configurações
- `database/` — SQL e dados iniciais
- `storage/` — arquivos enviados

Mais detalhes técnicos estão no `MEMORIAL_TECNICO.md` e no `DICIONARIO_DE_DADOS.md`.
