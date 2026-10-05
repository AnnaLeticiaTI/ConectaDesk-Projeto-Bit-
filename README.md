# ConectaDesk

O ConectaDesk é uma plataforma de Service Desk que centraliza solicitações, facilita o acompanhamento dos chamados e melhora a comunicação entre usuários e equipe. O sistema também identifica dificuldades recorrentes e oferece materiais para aumentar a autonomia dos usuários.

## Tecnologias

PHP 8.3, MySQL 8.4, HTML, CSS, JavaScript, PDO e Docker.

Como recursos extras, o projeto utiliza SQL em banco externo e Vercel para hospedagem.

## Execução

Pré-requisito: Docker Desktop.

Na pasta do projeto:

```powershell
docker compose down -v
docker compose up -d --build
```

Acesse `http://localhost:8000`.

## Acessos de teste

Usuário: admin

Senha: `Conecta@123`

- `admin` — administrador
- `maria` — administrador
- `joao` — administrador
- `julia` — usuário comum
- `carlos` — usuário comum

## Banco

`database/schema.sql` cria o banco e as tabelas. `database/migrate.php` aplica ajustes de estrutura em bancos já existentes. `database/seed.php` insere os dados de demonstração.

## Pastas

- `api/` — backend e endpoints
- `public/` — frontend
- `config/` — conexão e configurações
- `database/` — SQL e dados iniciais
- `storage/` — arquivos locais temporários; os anexos e fotos também ficam persistidos no banco

Documentação complementar: `MEMORIAL_TECNICO.md` e `DICIONARIO_DE_DADOS.md`.


### Banco já existente
Se a instalação já tiver sido executada e alguma categoria aparecer com caracteres incorretos, execute `database/fix_encoding.sql` uma vez no banco para corrigir os nomes sem recriar os dados.
## Testes automatizados

Com o projeto em execução, os testes podem ser executados dentro do container da aplicação:

```powershell
docker compose exec app php tests/smoke_test.php
```

Os testes verificam o healthcheck, autenticação, permissões de administrador, categorias, dashboard, relatórios, chamados, notificações, criação e exclusão de chamado e exportações em PDF/Excel.

## CI/CD

O projeto possui GitHub Actions em `.github/workflows/ci.yml`. A cada Pull Request ou `push` na branch `main`, o pipeline valida o Docker Compose, verifica a sintaxe PHP, sobe a aplicação e executa os testes automatizados.

Quando um `push` na `main` passa por todos os testes, o pipeline publica automaticamente a imagem da aplicação no GitHub Container Registry (GHCR), com as tags `latest` e o SHA do commit.

