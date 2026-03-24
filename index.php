<?php
require_once 'includes/config.php';

// Redirecionar se já logado
if (isClientLoggedIn()) {
    header('Location: painel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cnpj  = preg_replace('/\D/', '', trim($_POST['cnpj'] ?? ''));
    $senha = trim($_POST['senha'] ?? '');

    if (empty($cnpj) || empty($senha)) {
        $error = 'Por favor, preencha o CNPJ e a senha.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare('SELECT * FROM clientes WHERE cnpj = ? AND ativo = 1 LIMIT 1');
            // Aceita CNPJ com ou sem formatação
            $cnpjFormatado = formatCNPJ($cnpj);
            $stmt->execute([$cnpjFormatado]);
            $client = $stmt->fetch();

            if ($client && password_verify($senha, $client['senha'])) {
                $_SESSION['client_id']    = $client['id'];
                $_SESSION['client_name']  = $client['razao_social'];
                $_SESSION['client_cnpj']  = $client['cnpj'];
                $_SESSION['client_email'] = $client['email'];
                header('Location: painel.php');
                exit;
            } else {
                $error = 'CNPJ ou senha incorretos. Verifique seus dados e tente novamente.';
            }
        } catch (Exception $e) {
            $error = 'Erro de conexão com o banco de dados. Tente novamente em instantes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal de Suporte – ERP Condomínio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .login-features {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .5rem;
      margin-top: 1.5rem;
    }
    .login-feature {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-size: .75rem;
      color: var(--text-muted);
    }
    .login-feature svg { color: var(--secondary); flex-shrink: 0; }
    .cnpj-input-wrap { position: relative; }
    .cnpj-input-wrap svg {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
    }
    .cnpj-input-wrap .form-control { padding-left: 2.25rem; }
    .pass-toggle {
      position: absolute;
      right: .75rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-muted);
    }
    .pass-wrap { position: relative; }
    .pass-wrap .form-control { padding-right: 2.5rem; }
    .demo-box {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: var(--radius);
      padding: .75rem 1rem;
      margin-top: 1rem;
      font-size: .78rem;
      color: #1e40af;
    }
    .demo-box strong { display: block; margin-bottom: .25rem; }
  </style>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <img src="assets/logo.png" alt="ERP Condomínio">
    </div>
    <div class="login-title">Portal de Suporte</div>
    <div class="login-subtitle">Acesse com seu CNPJ e senha para gerenciar seus chamados</div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="cnpj">CNPJ da Empresa</label>
        <div class="cnpj-input-wrap">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          </svg>
          <input type="text" id="cnpj" name="cnpj" class="form-control" placeholder="00.000.000/0000-00"
                 maxlength="18" autocomplete="username" required
                 value="<?= sanitize($_POST['cnpj'] ?? '') ?>" oninput="maskCNPJ(this)">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="senha">Senha</label>
        <div class="pass-wrap">
          <input type="password" id="senha" name="senha" class="form-control"
                 placeholder="Digite sua senha" autocomplete="current-password" required>
          <button type="button" class="pass-toggle" onclick="togglePass()">
            <svg id="eye-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:.5rem">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
        Entrar no Portal
      </button>
    </form>

    <div class="demo-box">
      <strong>🔑 Dados de demonstração:</strong>
      CNPJ: <code>12.345.678/0001-90</code> &nbsp;|&nbsp; Senha: <code>123456</code>
    </div>

    <div class="login-features">
      <div class="login-feature">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Abertura de chamados
      </div>
      <div class="login-feature">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Acompanhamento SLA
      </div>
      <div class="login-feature">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Chat em tempo real
      </div>
      <div class="login-feature">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Histórico completo
      </div>
    </div>

    <div style="text-align:center;margin-top:1.25rem;font-size:.75rem;color:var(--text-muted)">
      Problemas para acessar? <a href="mailto:suporte@erpcondominios.com.br">Fale conosco</a>
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

function togglePass() {
  const inp = document.getElementById('senha');
  const ico = document.getElementById('eye-icon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    inp.type = 'password';
    ico.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}
</script>
</body>
</html>
