# Memorial Técnico de Desenvolvimento — ConectaDesk

## Projeto

O ConectaDesk é uma plataforma de Service Desk que centraliza solicitações, facilita o acompanhamento dos chamados e melhora a comunicação entre usuários e equipe. O sistema também identifica dificuldades recorrentes e oferece materiais para aumentar a autonomia dos usuários.

## Tecnologias

Usei PHP 8.3 no backend, HTML, CSS e JavaScript no frontend e MySQL 8.4 no banco. O acesso ao banco é feito com PDO e o Docker foi usado para facilitar a execução do projeto.

Não usei framework porque a proposta é pequena e eu queria manter o código direto, organizado e fácil de entender.

## Estrutura

`api/` concentra os endpoints e regras do sistema.

`public/` concentra a interface, CSS e JavaScript.

`config/` concentra a conexão e as configurações.

`database/` contém o SQL de criação e os dados iniciais.

`storage/` guarda os arquivos enviados.

O frontend conversa com a API e não acessa o banco diretamente.

## Banco e autenticação

O banco foi separado em usuários, categorias, chamados, anexos, comentários, avaliações, conteúdos, destinatários e notificações. O `schema.sql` cria a estrutura e o `seed.php` insere os dados de demonstração.

O login aceita usuário, e-mail ou e-mail secundário. As senhas usam `password_hash` e `password_verify`, e a sessão PHP controla o acesso. As operações administrativas são validadas no backend e as consultas usam parâmetros preparados.

## Funcionalidades

Implementei login, cadastro, chamados, filtros, status, comentários, anexos, avaliação, Base de Conhecimento, Avisos, notificações, dashboard, relatórios, perfil, configurações e permissões.

O fluxo dos chamados é `Aberto`, `Em Atendimento` e `Concluído`. A alteração de status e categoria fica com o administrador. As mudanças de status atualizam a listagem sem exigir recarregamento manual da página.

Os comentários dos materiais ficam visíveis para os usuários que têm acesso ao conteúdo, incluindo administradores.

O Dashboard possui exportação em PDF e Excel com os mesmos blocos de informações apresentados na tela.

## Decisões

Mantive uma estrutura simples para deixar o projeto funcional, organizado e fácil de executar. Também priorizei correções na origem dos problemas, sem criar regras paralelas para esconder erros.

## Melhorias futuras

Em uma próxima versão eu acrescentaria testes automatizados, envio de e-mail, paginação para bases maiores, proteção CSRF e armazenamento externo para arquivos. Se o sistema crescesse, também separaria a API em arquivos menores.
