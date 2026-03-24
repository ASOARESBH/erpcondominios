<?php
/**
 * ERP Condomínio – Setup/Instalação
 * Execute este arquivo UMA VEZ para criar as tabelas no banco de dados.
 * APAGUE este arquivo após a instalação!
 */

// Chave de segurança para instalação (altere antes de usar)
define('SETUP_KEY', 'erpcondo2024setup');

$key = $_GET['key'] ?? '';
if ($key !== SETUP_KEY) {
    die('<h2>Acesso negado.</h2><p>Passe a chave correta via ?key=CHAVE</p>');
}

require_once 'includes/config.php';

$results = [];
$hasError = false;

try {
    $db = getDB();

    // Criar tabela clientes
    $db->exec("
        CREATE TABLE IF NOT EXISTS `clientes` (
          `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `razao_social`  VARCHAR(200) NOT NULL,
          `cnpj`          VARCHAR(18)  NOT NULL UNIQUE,
          `email`         VARCHAR(150) NOT NULL,
          `telefone`      VARCHAR(20)  DEFAULT NULL,
          `senha`         VARCHAR(255) NOT NULL,
          `ativo`         TINYINT(1)   NOT NULL DEFAULT 1,
          `criado_em`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `atualizado_em` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = ['ok', 'Tabela `clientes` criada/verificada.'];

    // Criar tabela chamados
    $db->exec("
        CREATE TABLE IF NOT EXISTS `chamados` (
          `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `numero`        VARCHAR(12)  NOT NULL UNIQUE,
          `cliente_id`    INT UNSIGNED NOT NULL,
          `assunto`       VARCHAR(255) NOT NULL,
          `descricao`     TEXT         NOT NULL,
          `prioridade`    ENUM('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
          `status`        ENUM('aberto','em_andamento','aguardando','fechado','cancelado') NOT NULL DEFAULT 'aberto',
          `anexo`         VARCHAR(500) DEFAULT NULL,
          `criado_em`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `atualizado_em` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `fechado_em`    DATETIME     DEFAULT NULL,
          FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = ['ok', 'Tabela `chamados` criada/verificada.'];

    // Criar tabela mensagens
    $db->exec("
        CREATE TABLE IF NOT EXISTS `mensagens` (
          `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `chamado_id` INT UNSIGNED NOT NULL,
          `autor`      ENUM('cliente','admin') NOT NULL,
          `nome`       VARCHAR(100) NOT NULL,
          `mensagem`   TEXT         NOT NULL,
          `criado_em`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`chamado_id`) REFERENCES `chamados`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = ['ok', 'Tabela `mensagens` criada/verificada.'];

    // Criar tabela historico_status
    $db->exec("
        CREATE TABLE IF NOT EXISTS `historico_status` (
          `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `chamado_id`  INT UNSIGNED NOT NULL,
          `status_de`   VARCHAR(30)  NOT NULL,
          `status_para` VARCHAR(30)  NOT NULL,
          `observacao`  TEXT         DEFAULT NULL,
          `autor`       VARCHAR(100) NOT NULL,
          `criado_em`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`chamado_id`) REFERENCES `chamados`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = ['ok', 'Tabela `historico_status` criada/verificada.'];

    // Lógica de clientes de demonstração removida para produção.
    $results[] = ['info', 'Lógica de clientes de demonstração desativada para este ambiente de produção.'];

    // Verificar diretório de uploads
    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0755, true);
        $results[] = ['ok', 'Diretório uploads/ criado.'];
    } else {
        $results[] = ['ok', 'Diretório uploads/ já existe.'];
    }

} catch (Exception $e) {
    $results[] = ['error', 'ERRO: ' . $e->getMessage()];
    $hasError = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup – ERP Condomínio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-page" style="background:linear-gradient(135deg,#1e3a8a,#16a34a)">
  <div class="login-card" style="max-width:520px">
    <div class="login-logo"><img src="assets/logo.png" alt="ERP Condomínio" style="height:70px"></div>
    <div class="login-title">Instalação do Sistema</div>
    <div class="login-subtitle">Configuração do banco de dados</div>

    <div style="margin-top:1.25rem">
      <?php foreach ($results as [$type, $msg]): ?>
      <div class="alert alert-<?= $type === 'ok' ? 'success' : ($type === 'error' ? 'danger' : 'info') ?>" style="margin-bottom:.5rem">
        <?= $type === 'ok' ? '✅' : ($type === 'error' ? '❌' : 'ℹ️') ?> <?= sanitize($msg) ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$hasError): ?>
    <div class="alert alert-success" style="margin-top:1rem;font-weight:600">
      🎉 Instalação concluída com sucesso!
    </div>
    <div class="alert alert-warning" style="margin-top:.5rem">
      ⚠️ <strong>IMPORTANTE:</strong> Apague o arquivo <code>setup.php</code> do servidor imediatamente após a instalação!
    </div>
    <div style="display:flex;gap:.75rem;margin-top:1.25rem">
      <a href="index.php" class="btn btn-primary btn-block">Acessar Portal do Cliente</a>
      <a href="admin/login.php" class="btn btn-outline btn-block">Painel Admin</a>
    </div>
    <?php else: ?>
    <div class="alert alert-danger" style="margin-top:1rem">
      Verifique as configurações do banco de dados em <code>includes/config.php</code> e tente novamente.
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
