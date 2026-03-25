<?php
/**
 * ERP Condomínio – Gestão de Clientes (Painel Admin)
 * Auditoria Sênior V10: Correção Definitiva de Gravação
 */
require_once '../includes/config.php';
requireAdminLogin();

$db = getDB();
$successMsg = '';
$errorMsg   = '';
$action     = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // NOVO CLIENTE
    if ($action === 'criar') {
        $razao  = trim($_POST['razao_social'] ?? '');
        $cnpj_input = $_POST['cnpj'] ?? '';
        $cnpj_raw = preg_replace('/\D/', '', $cnpj_input);
        $cnpj   = formatCNPJ($cnpj_raw);
        $email  = trim($_POST['email'] ?? '');
        $tel    = trim($_POST['telefone'] ?? '');
        $senha  = $_POST['senha'] ?? '';

        if (empty($razao) || empty($cnpj_raw) || empty($email) || empty($senha)) {
            $errorMsg = 'Preencha todos os campos obrigatórios.';
        } else {
            try {
                $check = $db->prepare('SELECT id FROM clientes WHERE cnpj = ?');
                $check->execute([$cnpj]);
                if ($check->fetch()) {
                    $errorMsg = "CNPJ $cnpj já cadastrado.";
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $agora = date('Y-m-d H:i:s');
                    
                    // QUERY V10: Todas as colunas obrigatórias enviadas explicitamente
                    $sql = "INSERT INTO clientes (razao_social, cnpj, email, telefone, senha, ativo, criado_em, atualizado_em) 
                            VALUES (:razao, :cnpj, :email, :tel, :senha, 1, :criado, :atualizado)";
                    
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        ':razao' => $razao,
                        ':cnpj'  => $cnpj,
                        ':email' => $email,
                        ':tel'   => $tel,
                        ':senha' => $hash,
                        ':criado' => $agora,
                        ':atualizado' => $agora
                    ]);

                    if ($result) {
                        $successMsg = "Cliente cadastrado com sucesso!";
                        $action = '';
                    }
                }
            } catch (PDOException $e) {
                $errorMsg = "Erro no banco: " . $e->getMessage();
                sys_log("ERRO CRUD CLIENTE: " . $e->getMessage(), 'CRITICAL');
            }
        }
    }

    // SALVAR EDIÇÃO
    if ($action === 'salvar_edicao') {
        $id        = (int)($_POST['cliente_id'] ?? 0);
        $razao     = trim($_POST['razao_social'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $tel       = trim($_POST['telefone'] ?? '');
        $ativo     = (int)($_POST['ativo'] ?? 1);
        $novaSenha = $_POST['nova_senha'] ?? '';
        $agora     = date('Y-m-d H:i:s');

        try {
            if (!empty($novaSenha)) {
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $sql = "UPDATE clientes SET razao_social = ?, email = ?, telefone = ?, ativo = ?, senha = ?, atualizado_em = ? WHERE id = ?";
                $db->prepare($sql)->execute([$razao, $email, $tel, $ativo, $hash, $agora, $id]);
            } else {
                $sql = "UPDATE clientes SET razao_social = ?, email = ?, telefone = ?, ativo = ?, atualizado_em = ? WHERE id = ?";
                $db->prepare($sql)->execute([$razao, $email, $tel, $ativo, $agora, $id]);
            }
            $successMsg = 'Dados atualizados!';
            $action = '';
        } catch (PDOException $e) {
            $errorMsg = "Erro ao atualizar: " . $e->getMessage();
        }
    }

    // EXCLUIR
    if ($action === 'excluir') {
        $id = (int)($_POST['cliente_id'] ?? 0);
        try {
            $db->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
            $successMsg = 'Cliente removido.';
            $action = '';
        } catch (PDOException $e) {
            $errorMsg = "Erro ao excluir: " . $e->getMessage();
        }
    }
}

// Listagem
$busca = trim($_GET['busca'] ?? '');
$sql = "SELECT * FROM clientes " . ($busca ? "WHERE razao_social LIKE ? OR cnpj LIKE ?" : "") . " ORDER BY id DESC";
$stmt = $db->prepare($sql);
if ($busca) $stmt->execute(["%$busca%", "%$busca%"]); else $stmt->execute();
$clientes = $stmt->fetchAll();

// Carregar para edição
$clienteEdit = null;
if ($action === 'editar' && isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM clientes WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $clienteEdit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes – Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include 'partials/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'partials/topbar.php'; ?>
    <div class="page-content">
      
      <?php if ($successMsg): ?><div class="alert alert-success">✅ <?= sanitize($successMsg) ?></div><?php endif; ?>
      <?php if ($errorMsg): ?><div class="alert alert-danger">❌ <?= sanitize($errorMsg) ?></div><?php endif; ?>

      <?php if ($action === 'novo' || ($action === 'criar' && $errorMsg)): ?>
      <div class="card">
        <div class="card-header"><span class="card-title">Novo Cliente</span></div>
        <div class="card-body">
          <form method="POST" action="clientes.php">
            <input type="hidden" name="action" value="criar">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <input type="text" name="razao_social" class="form-control" placeholder="Razão Social" required>
                <input type="text" name="cnpj" class="form-control" placeholder="CNPJ" required oninput="maskCNPJ(this)">
                <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                <input type="text" name="telefone" class="form-control" placeholder="Telefone">
                <input type="text" name="senha" class="form-control" placeholder="Senha" required>
            </div>
            <button type="submit" class="btn btn-success" style="margin-top:1rem">Salvar Cliente</button>
            <a href="clientes.php" class="btn btn-outline">Cancelar</a>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($action === 'editar' && $clienteEdit): ?>
      <div class="card">
        <div class="card-header"><span class="card-title">Editar: <?= sanitize($clienteEdit['razao_social']) ?></span></div>
        <div class="card-body">
          <form method="POST" action="clientes.php">
            <input type="hidden" name="action" value="salvar_edicao">
            <input type="hidden" name="cliente_id" value="<?= $clienteEdit['id'] ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <input type="text" name="razao_social" class="form-control" value="<?= sanitize($clienteEdit['razao_social']) ?>" required>
                <input type="email" name="email" class="form-control" value="<?= sanitize($clienteEdit['email']) ?>" required>
                <input type="text" name="telefone" class="form-control" value="<?= sanitize($clienteEdit['telefone']) ?>">
                <select name="ativo" class="form-control">
                    <option value="1" <?= $clienteEdit['ativo']?'selected':'' ?>>Ativo</option>
                    <option value="0" <?= !$clienteEdit['ativo']?'selected':'' ?>>Inativo</option>
                </select>
                <input type="text" name="nova_senha" class="form-control" placeholder="Nova Senha (deixe em branco para manter)">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:1rem">Salvar Alterações</button>
            <a href="clientes.php" class="btn btn-outline">Cancelar</a>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <div class="card" style="margin-top:1.5rem">
        <div class="card-header">
            <span class="card-title">Lista de Clientes</span>
            <a href="clientes.php?action=novo" class="btn btn-primary btn-sm">+ Novo Cliente</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Razão Social</th><th>CNPJ</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= sanitize($c['razao_social']) ?></td>
                        <td><?= sanitize($c['cnpj']) ?></td>
                        <td><?= $c['ativo'] ? '✅' : '❌' ?></td>
                        <td>
                            <a href="clientes.php?action=editar&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
                            <form method="POST" action="clientes.php" style="display:inline" onsubmit="return confirm('Excluir?')">
                                <input type="hidden" name="action" value="excluir">
                                <input type="hidden" name="cliente_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">X</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
      </div>
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
