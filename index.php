<?php
/**
 * ERP Condomínio – Portal do Cliente (Login)
 * Auditoria Sênior: Correção de Autenticação e Implementação de Logs
 */
require_once 'includes/config.php';

// Redirecionar se já estiver logado
if (isset($_SESSION['cliente_id'])) {
    // Redirecionamento seguro para a mesma pasta
    header('Location: ./painel.php');
    exit;
}

$error = '';

/**
 * Função de log sênior para rastrear falhas de login
 */
function auth_log($message) {
    $log_file = __DIR__ . '/auth_error.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $log_entry = "[$timestamp] [IP: $ip] $message" . PHP_EOL;
    
    // Tentativa sênior de garantir que o arquivo possa ser escrito
    if (!file_exists($log_file)) {
        @touch($log_file);
        @chmod($log_file, 0666);
    }
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Processar Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cnpj_input = $_POST['cnpj'] ?? '';
    $cnpj_raw   = preg_replace('/\D/', '', $cnpj_input);
    $cnpj       = formatCNPJ($cnpj_raw);
    $senha      = $_POST['senha'] ?? '';

    auth_log("Tentativa de login: CNPJ: $cnpj (Raw: $cnpj_raw)");

    if (empty($cnpj_raw) || empty($senha)) {
        $error = 'Por favor, informe o CNPJ e a senha.';
        auth_log("ERRO: Campos vazios.");
    } else {
        try {
            $db = getDB();
            
            // Buscar cliente (verificando se está ativo)
            $stmt = $db->prepare('SELECT * FROM clientes WHERE cnpj = ?');
            $stmt->execute([$cnpj]);
            $cliente = $stmt->fetch();

            if ($cliente) {
                auth_log("Cliente encontrado: ID " . $cliente['id'] . " | Ativo: " . $cliente['ativo']);

                if (!$cliente['ativo']) {
                    $error = 'Sua conta está inativa. Entre em contato com o suporte.';
                    auth_log("ERRO: Conta inativa para ID " . $cliente['id']);
                } 
                // Verificar senha usando password_verify
                elseif (password_verify($senha, $cliente['senha'])) {
                    // Login Sucesso
                    $_SESSION['cliente_id']    = $cliente['id'];
                    $_SESSION['cliente_nome']  = $cliente['razao_social'];
                    $_SESSION['cliente_cnpj']  = $cliente['cnpj'];
                    $_SESSION['cliente_email'] = $cliente['email'];
                    
                    auth_log("SUCESSO: Login realizado para ID " . $cliente['id']);
                    
                    // Redirecionamento relativo explícito
                    header('Location: ./painel.php');
                    exit;
                } else {
                    $error = 'CNPJ ou senha incorretos.';
                    auth_log("ERRO: Senha incorreta para ID " . $cliente['id']);
                    // Log técnico: O hash no banco é: substr($cliente['senha'], 0, 10)...
                }
            } else {
                $error = 'CNPJ ou senha incorretos.';
                auth_log("ERRO: CNPJ não encontrado no banco: $cnpj");
            }
        } catch (PDOException $e) {
            $error = 'Erro técnico no servidor. Tente novamente mais tarde.';
            auth_log("EXCEÇÃO PDO: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – ERP Condomínio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <img src="assets/logo.png" alt="ERP Condomínio">
    </div>
    <div class="login-title">Portal do Cliente</div>
    <div class="login-subtitle">Acesse para abrir e acompanhar seus chamados</div>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="margin-top:1rem; border-left:4px solid #dc2626">
        <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="index.php" style="margin-top:1.5rem">
      <div class="form-group">
        <label class="form-label">CNPJ da Empresa</label>
        <input type="text" name="cnpj" class="form-control" placeholder="00.000.000/0000-00" 
               required maxlength="18" oninput="maskCNPJ(this)" autofocus
               value="<?= sanitize($_POST['cnpj'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Senha de Acesso</label>
        <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem; height:48px; font-weight:600">
        Entrar no Portal
      </button>
    </form>

    <div class="login-footer" style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid #f1f5f9; text-align:center">
      <p style="font-size:0.85rem; color:#64748b">
        Problemas com o acesso? <br>
        <span style="color:#1e40af; font-weight:500">Entre em contato com o suporte técnico.</span>
      </p>
    </div>
  </div>
</div>

<script>
function maskCNPJ(input) {
  let v = input.value.replace(/\D/g, '').substring(0, 14);
  v = v.replace(/^(\d{2})(\d)/, '$1.$2');
  v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
  v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
  v = v.replace(/(\d{4})(\d)/, '$1-$2');
  input.value = v;
}
</script>
</body>
</html>
