# Memorial Técnico de Desenvolvimento — ConectaDesk

## Projeto

O ConectaDesk é um portal interno de Service Desk. A proposta foi centralizar chamados, acompanhar o atendimento e usar a Base de Conhecimento para ajudar nos problemas recorrentes.

## Tecnologias

Usei PHP 8.3 no backend, HTML, CSS e JavaScript no frontend e MySQL 8.4 no banco. O acesso ao banco é feito com PDO. O Docker foi usado para facilitar a execução em outra máquina sem precisar instalar PHP e MySQL separadamente.

Não usei framework porque o projeto é pequeno e eu queria manter a estrutura direta e fácil de entender.

## Estrutura

`api/` concentra a API e as regras do sistema.

`public/` concentra a interface, CSS e JavaScript.

`config/` concentra conexão e configurações.

`database/` contém o SQL de criação e os dados iniciais.

`storage/` guarda os arquivos enviados.

O frontend faz as requisições para a API e não acessa o banco diretamente.

## Banco de dados

O banco foi separado em usuários, categorias, chamados, anexos, comentários, avaliações, conteúdos, destinatários e notificações.

O `schema.sql` cria as tabelas e os relacionamentos. O `seed.php` coloca os dados de demonstração.

## Autenticação

O login usa usuário, e-mail ou e-mail secundário. As senhas são armazenadas com `password_hash` e verificadas com `password_verify`. A sessão PHP controla o acesso às áreas do sistema.

As operações administrativas são validadas no backend. As consultas usam parâmetros preparados.

## Funcionalidades

Implementei login, cadastro, chamados, filtros, status, comentários, anexos, avaliação, Base de Conhecimento, Avisos, notificações internas, dashboard, relatórios, perfil, configurações e permissões.

No chamado, o fluxo usado é `Aberto`, `Em Atendimento` e `Concluído`. O usuário comum pode acompanhar o próprio atendimento e adicionar informações. A alteração de status e categoria fica com o administrador.

Também segui as regras específicas do material do ConectaDesk. Por isso, mesmo o PDF mencionando edição e exclusão de solicitações abertas, essas ações não ficam disponíveis para o usuário comum no projeto.

## Decisões

Escolhi uma estrutura simples porque o objetivo era entregar uma aplicação funcional, organizada e fácil de executar. O Docker também deixa a configuração do ambiente mais previsível.

## O que eu faria depois

Em uma próxima versão eu acrescentaria testes automatizados, envio de e-mail, paginação para bases maiores, proteção CSRF e armazenamento externo para arquivos. Se o sistema crescesse, também separaria a API em arquivos menores.
