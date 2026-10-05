SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS conectadesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE conectadesk;

-- usuários
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 email VARCHAR(180) NOT NULL UNIQUE,
 secondary_email VARCHAR(180) NULL,
 phone VARCHAR(30) NULL,
 department VARCHAR(100) NULL,
 role ENUM('admin','user') NOT NULL DEFAULT 'user',
 is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
 avatar_path VARCHAR(255) NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB;

-- categorias
CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 severity TINYINT UNSIGNED NOT NULL DEFAULT 2
) ENGINE=InnoDB;

-- chamados
CREATE TABLE IF NOT EXISTS tickets (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) UNIQUE NULL,
 title VARCHAR(180) NOT NULL,
 description TEXT NOT NULL,
 category_id INT UNSIGNED NOT NULL,
 requester_id INT UNSIGNED NOT NULL,
 status ENUM('Aberto','Em Atendimento','Concluído') NOT NULL DEFAULT 'Aberto',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 FOREIGN KEY(category_id) REFERENCES categories(id),
 FOREIGN KEY(requester_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- anexos
CREATE TABLE IF NOT EXISTS ticket_attachments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 file_path VARCHAR(255) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(80) NOT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- comentários
CREATE TABLE IF NOT EXISTS ticket_comments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- avaliações
CREATE TABLE IF NOT EXISTS ticket_ratings (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id INT UNSIGNED NOT NULL UNIQUE,
 user_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 comment TEXT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- conteúdos
CREATE TABLE IF NOT EXISTS contents (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 type ENUM('material','notice') NOT NULL,
 category VARCHAR(100) NULL,
 author_id INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- destinatários
CREATE TABLE IF NOT EXISTS content_recipients (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 content_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 start_date DATE NOT NULL,
 frequency ENUM('once','daily') NOT NULL DEFAULT 'once',
 opened TINYINT(1) NOT NULL DEFAULT 0,
 read_at DATETIME NULL,
 liked TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_delivery(content_id,user_id,start_date),
 KEY idx_content_recipient_user(content_id,user_id),
 FOREIGN KEY(content_id) REFERENCES contents(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- comentários dos conteúdos
CREATE TABLE IF NOT EXISTS content_comments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 content_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(content_id) REFERENCES contents(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- notificações
CREATE TABLE IF NOT EXISTS notifications (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 type ENUM('material','notice','ticket') NOT NULL,
 title VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO categories(name,severity) VALUES
 ('Suporte',3),
 ('Manutenção',4),
 ('Requisição',2),
 ('Acesso e Permissões',4),
 ('Sistemas e Aplicações',4),
 ('Infraestrutura',5),
 ('Equipamentos',5),
 ('Rede e Conectividade',5);
