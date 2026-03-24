<?php
require_once '../includes/config.php';
requireAdminLogin();

// Ativar exibição de erros para debug (pode ser removido após testes)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = getDB();

$successMsg = '';
$errorMsg   = '';
$action     = $_GET['action'] ?? $_POST['action'] ?? '';

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Novo cliente
    if ($action === 'criar') {
        $razao  = trim($_POST['razao_social'] ?? '');
        $cnpj_raw = preg_replace('/\D/','',$_POST['cnpj'] ?? '');
        $cnpj   = formatCNPJ($cnpj_raw);
        $email  = trim($_POST['email'] ?? '');
        $tel    = trim($_POST['telefone'] ?? '');
        $senha  = trim($_POST['senha'] ?? '');

        // Log de tentativa de cadastro
        error_log("[CADASTRO] Tentativa de cadastro: Razão: $razao, CNPJ: $cnpj, Email: $email");

        if (empty($razao) || empty($cnpj_raw) || empty($email) || empty($senha)) {
            $errorMsg = 'Preencha todos os campos obrigatórios.';
            error_log("[CADASTRO] Erro: Campos obrigatórios vazios.");
        } elseif (strlen($senha) < 6) {
            $errorMsg = 'A senha deve ter pelo menos 6 caracteres.';
            error_log("[CADASTRO] Erro: Senha muito curta.");
        } else {
            try {
                $check = $db->prepare('SELECT id FROM clientes WHERE cnpj = ?');
                $check->execute([$cnpj]);
                if ($check->fetch()) {
                    $errorMsg = 'Já existe um cliente com este CNPJ (' . $cnpj . ').';
                    error_log("[CADASTRO] Erro: CNPJ duplicado ($cnpj).");
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    // Usando colunas explícitas e garantindo que o banco aceite
                    $stmtInsert = $db->prepare('INSERT INTO clientes (razao_social, cnpj, email, telefone, senha, ativo, criado_em) VALUES (?, ?, ?, ?, ?, 1, NOW())');
                    $result = $stmtInsert->execute([$razao, $cnpj, $email, $tel, $hash]);
                    
                    if ($result) {
                        $successMsg = "Cliente \"{$razao}\" cadastrado com sucesso!";
                        error_log("[CADASTRO] Sucesso: Cliente $razao cadastrado.");
                        $action = ''; // Volta para a listagem
                    } else {
                        $errInfo = $stmtInsert->errorInfo();
                        $errorMsg = 'Erro ao inserir no banco de dados: ' . ($errInfo[2] ?? 'Erro desconhecido');
                        error_log("[CADASTRO] Erro SQL: " . print_r($errInfo, true));
                    }
                }
            } catch (Exception $e) {
                $errorMsg = 'Erro de exceção: ' . $e->getMessage();
                error_log("[CADASTRO] Exceção: " . $e->getMessage());
            }
        }
    }

    // Editar cliente
    if ($action === 'salvar_edicao') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $razao     = trim($_POST['razao_social'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $tel       = trim($_POST['telefone'] ?? '');
        $ativo     = (int)($_POST['ativo'] ?? 1);
        $novaSenha = trim($_POST['nova_senha'] ?? '');

        if (empty($razao) || empty($email)) {
            $errorMsg = 'Razão social e e-mail são obrigatórios.';
        } else {
            if (!empty($novaSenha)) {
                if (strlen($novaSenha) < 6) {
                    $errorMsg = 'A nova senha deve ter pelo menos 6 caracteres.';
                } else {
                    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $db->prepare('UPDATE clientes SET razao_social=?, email=?, telefone=?, ativo=?, senha=? WHERE id=?')
                       ->execute([$razao, $email, $tel, $ativo, $hash, $clienteId]);
                }
            } else {
                $db->prepare('UPDATE clientes SET razao_social=?, email=?, telefone=?, ativo=? WHERE id=?')
                   ->execute([$razao, $email, $tel, $ativo, $clienteId]);
            }
            if (!$errorMsg) $successMsg = 'Cliente atualizado com sucesso!';
            $action = '';
        }
    }

    // Excluir cliente
    if ($action === 'excluir') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $db->prepare('DELETE FROM clientes WHERE id = ?')->execute([$clienteId]);
        $successMsg = 'Cliente removido com sucesso.';
        $action = '';
    }
}

// Buscar clientes
$busca = trim($_GET['busca'] ?? '');
if ($busca) {
    $stmt = $db->prepare('SELECT * FROM clientes WHERE razao_social LIKE ? OR cnpj LIKE ? OR email LIKE ? ORDER BY razao_social');
    $stmt->execute(["%{$busca}%", "%{$busca}%", "%{$busca}%"]);
} else {
    $stmt = $db->query('SELECT * FROM clientes ORDER BY razao_social');
}
$clientes = $stmt->fetchAll();

// Carregar cliente para edição
$clienteEdit = null;
if ($action === 'editar' && isset($_GET['id'])) {
    $stmtE = $db->prepare('SELECT * FROM clientes WHERE id = ?');
    $stmtE->execute([(int)$_GET['id']]);
    $clienteEdit = $stmtE->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes – Admin ERP Condomínio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include 'partials/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'partials/topbar.php'; ?>

    <div class="page-content">

      <?php if ($successMsg): ?><div class="alert alert-success"><?= sanitize($successMsg) ?></div><?php endif; ?>
      <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= sanitize($errorMsg) ?></div><?php endif; ?>
      
      <!-- Link para ver o log de erros do PHP no servidor -->
      <div style="margin-bottom: 1rem; font-size: 0.8rem; color: #666;">
        Nota: Verifique o arquivo <code>error_log</code> na pasta <code>admin/</code> do seu servidor para detalhes técnicos das falhas.
      </div>

      <!-- Formulário: Novo ou Editar Cliente -->
      <?php if ($action === 'novo' || $action === 'criar' && $errorMsg): ?>
      <div class="card mb-2">
        <div class="card-header">
          <span class="card-title">Cadastrar Novo Cliente</span>
          <a href="clientes.php" class="btn btn-outline btn-sm">Cancelar</a>
        </div>
        <div class="card-body">
          <form method="POST" action="">
            <input type="hidden" name="action" value="criar">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
              <div class="form-group">
                <label class="form-label">Razão Social <span style="color:var(--danger)">*</span></label>
                <input type="text" name="razao_social" class="form-control" required
                       value="<?= sanitize($_POST['razao_social'] ?? '') ?>" placeholder="Nome da empresa">
              </div>
              <div class="form-group">
                <label class="form-label">CNPJ <span style="color:var(--danger)">*</span></label>
                <input type="text" name="cnpj" class="form-control" required maxlength="18"
                       value="<?= sanitize($_POST['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00"
                       oninput="maskCNPJ(this)">
              </div>
              <div class="form-group">
                <label class="form-label">E-mail <span style="color:var(--danger)">*</span></label>
                <input type="email" name="email" class="form-control" required
                       value="<?= sanitize($_POST['email'] ?? '') ?>" placeholder="email@empresa.com">
              </div>
              <div class="form-group">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control"
                       value="<?= sanitize($_POST['telefone'] ?? '') ?>" placeholder="(11) 99999-9999">
              </div>
              <div class="form-group">
                <label class="form-label">Senha de Acesso <span style="color:var(--danger)">*</span></label>
                <input type="text" name="senha" class="form-control" required minlength="6"
                       value="<?= sanitize($_POST['senha'] ?? '') ?>" placeholder="Mínimo 6 caracteres">
              </div>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:.5rem">
              <button type="submit" class="btn btn-success">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                Cadastrar Cliente
              </button>
              <a href="clientes.php" class="btn btn-outline">Cancelar</a>
            </div>
          </form>
        </div>
      </div>

      <?php elseif ($action === 'editar' && $clienteEdit): ?>
      <div class="card mb-2">
        <div class="card-header">
          <span class="card-title">Editar: <?= sanitize($clienteEdit['razao_social']) ?></span>
          <a href="clientes.php" class="btn btn-outline btn-sm">Cancelar</a>
        </div>
        <div class="card-body">
          <form method="POST" action="">
            <input type="hidden" name="action" value="salvar_edicao">
            <input type="hidden" name="cliente_id" value="<?= $clienteEdit['id'] ?>">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
              <div class="form-group">
                <label class="form-label">Razão Social <span style="color:var(--danger)">*</span></label>
                <input type="text" name="razao_social" class="form-control" required
                       value="<?= sanitize($clienteEdit['razao_social']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">CNPJ (não editável)</label>
                <input type="text" class="form-control" value="<?= sanitize($clienteEdit['cnpj']) ?>" disabled>
              </div>
              <div class="form-group">
                <label class="form-label">E-mail <span style="color:var(--danger)">*</span></label>
                <input type="email" name="email" class="form-control" required
                       value="<?= sanitize($clienteEdit['email']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control"
                       value="<?= sanitize($clienteEdit['telefone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Nova Senha (deixe em branco para manter)</label>
                <input type="text" name="nova_senha" class="form-control" minlength="6"
                       placeholder="Deixe em branco para não alterar">
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="ativo" class="form-control">
                  <option value="1" <?= $clienteEdit['ativo']?'selected':'' ?>>✅ Ativo</option>
                  <option value="0" <?= !$clienteEdit['ativo']?'selected':'' ?>>❌ Inativo</option>
                </select>
              </div>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:.5rem">
              <button type="submit" class="btn btn-success">Salvar Alterações</button>
              <a href="clientes.php" class="btn btn-outline">Cancelar</a>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Lista de Clientes -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Clientes Cadastrados (<?= count($clientes) ?>)</span>
          <div style="display:flex;gap:.5rem;align-items:center">
            <form method="GET" action="" style="display:flex;gap:.5rem">
              <input type="text" name="busca" class="form-control" style="width:200px"
                     placeholder="Buscar cliente..." value="<?= sanitize($busca) ?>">
              <button type="submit" class="btn btn-outline btn-sm">Buscar</button>
            </form>
            <a href="clientes.php?action=novo" class="btn btn-success btn-sm">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              Novo Cliente
            </a>
          </div>
        </div>

        <?php if (empty($clientes)): ?>
        <div class="empty-state">
          <h3>Nenhum cliente cadastrado</h3>
          <p>Clique em "Novo Cliente" para começar.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Razão Social</th>
                <th>CNPJ</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>Status</th>
                <th>Cadastro</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clientes as $cl): ?>
              <?php
              $totalCh = $db->prepare('SELECT COUNT(*) FROM chamados WHERE cliente_id = ?');
              $totalCh->execute([$cl['id']]);
              $nCh = $totalCh->fetchColumn();
              ?>
              <tr>
                <td class="text-muted text-small"><?= $cl['id'] ?></td>
                <td>
                  <div style="font-weight:600"><?= sanitize($cl['razao_social']) ?></div>
                  <div class="text-muted text-small"><?= $nCh ?> chamado<?= $nCh !== 1 ? 's' : '' ?></div>
                </td>
                <td class="text-small"><?= sanitize($cl['cnpj']) ?></td>
                <td class="text-small"><?= sanitize($cl['email']) ?></td>
                <td class="text-small"><?= sanitize($cl['telefone'] ?: '—') ?></td>
                <td>
                  <?php if ($cl['ativo']): ?>
                  <span class="badge badge-fechado">Ativo</span>
                  <?php else: ?>
                  <span class="badge badge-cancelado">Inativo</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted text-small"><?= date('d/m/Y', strtotime($cl['criado_em'])) ?></td>
                <td>
                  <div style="display:flex;gap:.35rem">
                    <a href="clientes.php?action=editar&id=<?= $cl['id'] ?>" class="btn btn-outline btn-sm">
                      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                      </svg>
                      Editar
                    </a>
                    <a href="chamados.php?cliente=<?= $cl['id'] ?>" class="btn btn-outline btn-sm">
                      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 11l3 3L22 4"/>
                      </svg>
                      Chamados
                    </a>
                    <form method="POST" action="" style="display:inline"
                          onsubmit="return confirm('Tem certeza que deseja excluir este cliente e todos os seus chamados?')">
                      <input type="hidden" name="action" value="excluir">
                      <input type="hidden" name="cliente_id" value="<?= $cl['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                          <path d="M10 11v6"/><path d="M14 11v6"/>
                        </svg>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('admin-sidebar').classList.toggle('open');
}
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
