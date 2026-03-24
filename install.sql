-- ============================================================
--  ERP Condomínio - Sistema de Chamados
--  Script de instalação do banco de dados
--  Execute este script no phpMyAdmin ou MySQL CLI
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- Tabela de clientes
CREATE TABLE IF NOT EXISTS `clientes` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `razao_social` VARCHAR(200) NOT NULL,
  `cnpj`         VARCHAR(18)  NOT NULL UNIQUE,
  `email`        VARCHAR(150) NOT NULL,
  `telefone`     VARCHAR(20)  DEFAULT NULL,
  `senha`        VARCHAR(255) NOT NULL,
  `ativo`        TINYINT(1)   NOT NULL DEFAULT 1,
  `criado_em`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de chamados
CREATE TABLE IF NOT EXISTS `chamados` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `numero`       VARCHAR(12)  NOT NULL UNIQUE,
  `cliente_id`   INT UNSIGNED NOT NULL,
  `assunto`      VARCHAR(255) NOT NULL,
  `descricao`    TEXT         NOT NULL,
  `prioridade`   ENUM('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
  `status`       ENUM('aberto','em_andamento','aguardando','fechado','cancelado') NOT NULL DEFAULT 'aberto',
  `anexo`        VARCHAR(500) DEFAULT NULL,
  `criado_em`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `fechado_em`   DATETIME     DEFAULT NULL,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de mensagens do chat por chamado
CREATE TABLE IF NOT EXISTS `mensagens` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `chamado_id` INT UNSIGNED NOT NULL,
  `autor`      ENUM('cliente','admin') NOT NULL,
  `nome`       VARCHAR(100) NOT NULL,
  `mensagem`   TEXT         NOT NULL,
  `criado_em`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`chamado_id`) REFERENCES `chamados`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de histórico de status
CREATE TABLE IF NOT EXISTS `historico_status` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `chamado_id` INT UNSIGNED NOT NULL,
  `status_de`  VARCHAR(30)  NOT NULL,
  `status_para`VARCHAR(30)  NOT NULL,
  `observacao` TEXT         DEFAULT NULL,
  `autor`      VARCHAR(100) NOT NULL,
  `criado_em`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`chamado_id`) REFERENCES `chamados`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clientes de demonstração (senha: 123456)
INSERT INTO `clientes` (`razao_social`, `cnpj`, `email`, `telefone`, `senha`) VALUES
('Condomínio Residencial Primavera', '12.345.678/0001-90', 'primavera@email.com', '(11) 99999-0001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Condomínio Edifício Central',      '98.765.432/0001-10', 'central@email.com',   '(11) 99999-0002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Associação Moradores Vila Nova',   '11.222.333/0001-44', 'vianova@email.com',   '(11) 99999-0003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
