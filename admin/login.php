<?php
require_once '../includes/config.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user  = trim($_POST['usuario'] ?? '');
    $pass  = trim($_POST['senha']   ?? '');

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['admin']      = true;
        $_SESSION['admin_user'] = $user;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuário ou senha incorretos.';
        // Pequeno delay para dificultar brute force
        sleep(1);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – ERP Condomínio Suporte</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .admin-badge {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      border-radius: 9999px;
      padding: .2rem .75rem;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
<div class="login-page" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#15803d 100%)">
  <div class="login-card">
    <div class="login-logo">
      <img src="../assets/logo.png" alt="ERP Condomínio">
    </div>
    <div style="text-align:center">
      <div class="admin-badge">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        Área Administrativa
      </div>
    </div>
    <div class="login-title" style="color:#0f172a">Painel do Administrador</div>
    <div class="login-subtitle">Acesso restrito à equipe de suporte</div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label">Usuário</label>
        <input type="text" name="usuario" class="form-control" placeholder="Usuário administrador"
               autocomplete="username" required value="<?= sanitize($_POST['usuario'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Senha</label>
        <input type="password" name="senha" class="form-control" placeholder="Senha" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-block btn-lg" style="background:#0f172a;color:#fff;margin-top:.5rem">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        Acessar Painel
      </button>
    </form>

    <div style="text-align:center;margin-top:1.25rem">
      <a href="../index.php" style="font-size:.78rem;color:var(--text-muted)">← Voltar ao Portal do Cliente</a>
    </div>
  </div>
</div>
</body>
</html>
