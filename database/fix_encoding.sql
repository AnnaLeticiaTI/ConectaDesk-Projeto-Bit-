SET NAMES utf8mb4;

UPDATE categories SET name = CASE id
    WHEN 1 THEN 'Suporte'
    WHEN 2 THEN 'Manutenção'
    WHEN 3 THEN 'Requisição'
    WHEN 4 THEN 'Acesso e Permissões'
    WHEN 5 THEN 'Sistemas e Aplicações'
    WHEN 6 THEN 'Infraestrutura'
    WHEN 7 THEN 'Equipamentos'
    WHEN 8 THEN 'Rede e Conectividade'
    ELSE name
END
WHERE id BETWEEN 1 AND 8;
