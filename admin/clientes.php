<?php
/**
 * ERP Condomínio – Gestão de Clientes (Painel Admin)
 * Auditoria Sênior: Correção de CRUD e Implementação de Logs
 */
require_once '../includes/config.php';
requireAdminLogin();

// Configurações de depuração sênior
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = getDB();
$successMsg = '';
$errorMsg   = '';
$action     = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Função de log sênior para rastrear operações de CRUD
 */
function crud_log($message) {
    $log_file = __DIR__ . '/crud_error.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Processamento de formulários com validação robusta
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

        crud_log("Iniciando criação de cliente: Razão: $razao | CNPJ: $cnpj | Email: $email");

        // Validações básicas
        if (empty($razao) || empty($cnpj_raw) || empty($email) || empty($senha)) {
            $errorMsg = 'Preencha todos os campos obrigatórios (Razão Social, CNPJ, E-mail e Senha).';
            crud_log("ERRO: Campos obrigatórios ausentes.");
        } elseif (strlen($cnpj_raw) !== 14) {
            $errorMsg = 'CNPJ inválido. Certifique-se de digitar os 14 números corretamente.';
            crud_log("ERRO: CNPJ inválido (tamanho: " . strlen($cnpj_raw) . ").");
        } elseif (strlen($senha) < 6) {
            $errorMsg = 'A senha deve ter pelo menos 6 caracteres.';
            crud_log("ERRO: Senha muito curta.");
        } else {
            try {
                // Verificar duplicidade
                $check = $db->prepare('SELECT id FROM clientes WHERE cnpj = ?');
                $check->execute([$cnpj]);
                if ($check->fetch()) {
                    $errorMsg = "Já existe um cliente cadastrado com o CNPJ $cnpj.";
                    crud_log("ERRO: Tentativa de duplicar CNPJ: $cnpj");
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    
                    // Inserção com tratamento de erro explícito
                    $sql = "INSERT INTO clientes (razao_social, cnpj, email, telefone, senha, ativo, criado_em) 
                            VALUES (:razao, :cnpj, :email, :tel, :senha, 1, NOW())";
                    
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        ':razao' => $razao,
                        ':cnpj'  => $cnpj,
                        ':email' => $email,
                        ':tel'   => $tel,
                        ':senha' => $hash
                    ]);

                    if ($result) {
                        $successMsg = "Cliente \"$razao\" cadastrado com sucesso!";
                        crud_log("SUCESSO: Cliente $razao (ID: " . $db->lastInsertId() . ") criado.");
                        $action = ''; // Retorna para a lista
                    } else {
                        $info = $stmt->errorInfo();
                        $errorMsg = "Erro no banco de dados: " . ($info[2] ?? 'Erro desconhecido');
                        crud_log("ERRO SQL (Insert): " . print_r($info, true));
                    }
                }
            } catch (PDOException $e) {
                $errorMsg = "Falha técnica: " . $e->getMessage();
                crud_log("EXCEÇÃO PDO (Criar): " . $e->getMessage());
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

        crud_log("Iniciando edição de cliente ID: $id | Razão: $razao");

        if (empty($razao) || empty($email)) {
            $errorMsg = 'Razão Social e E-mail são obrigatórios.';
            crud_log("ERRO (Edição): Campos obrigatórios vazios.");
        } else {
            try {
                if (!empty($novaSenha)) {
                    if (strlen($novaSenha) < 6) {
                        $errorMsg = 'A nova senha deve ter pelo menos 6 caracteres.';
                        crud_log("ERRO (Edição): Nova senha muito curta.");
                    } else {
                        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                        $sql = "UPDATE clientes SET razao_social = ?, email = ?, telefone = ?, ativo = ?, senha = ? WHERE id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$razao, $email, $tel, $ativo, $hash, $id]);
                        $successMsg = 'Cliente e senha atualizados com sucesso!';
                        crud_log("SUCESSO: Cliente ID $id atualizado com nova senha.");
                    }
                } else {
                    $sql = "UPDATE clientes SET razao_social = ?, email = ?, telefone = ?, ativo = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$razao, $email, $tel, $ativo, $id]);
                    $successMsg = 'Dados do cliente atualizados com sucesso!';
                    crud_log("SUCESSO: Cliente ID $id atualizado.");
                }
                if (!$errorMsg) $action = '';
            } catch (PDOException $e) {
                $errorMsg = "Erro ao atualizar: " . $e->getMessage();
                crud_log("EXCEÇÃO PDO (Editar): " . $e->getMessage());
            }
        }
    }

    // EXCLUIR CLIENTE
    if ($action === 'excluir') {
        $id = (int)($_POST['cliente_id'] ?? 0);
        crud_log("Iniciando exclusão de cliente ID: $id");
        try {
            $db->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
            $successMsg = 'Cliente removido permanentemente.';
            crud_log("SUCESSO: Cliente ID $id removido.");
            $action = '';
        } catch (PDOException $e) {
            $errorMsg = "Não foi possível excluir o cliente: " . $e->getMessage();
            crud_log("EXCEÇÃO PDO (Excluir): " . $e->getMessage());
        }
    }
}

// BUSCA E LISTAGEM
$busca = trim($_GET['busca'] ?? '');
try {
    if ($busca) {
        $stmt = $db->prepare('SELECT * FROM clientes WHERE razao_social LIKE ? OR cnpj LIKE ? OR email LIKE ? ORDER BY razao_social');
        $stmt->execute(["%$busca%", "%$busca%", "%$busca%"]);
    } else {
        $stmt = $db->query('SELECT * FROM clientes ORDER BY id DESC');
    }
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    crud_log("ERRO (Listagem): " . $e->getMessage());
    $clientes = [];
}

// CARREGAR PARA EDIÇÃO
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
      
      <!-- Feedback Visual -->
      <?php if ($successMsg): ?>
        <div class="alert alert-success" style="padding:1rem; border-left:5px solid #16a34a; margin-bottom:1.5rem; background:#f0fdf4">
          <strong>✅ Sucesso!</strong> <?= sanitize($successMsg) ?>
        </div>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
        <div class="alert alert-danger" style="padding:1rem; border-left:5px solid #dc2626; margin-bottom:1.5rem; background:#fef2f2">
          <strong>❌ Erro no Cadastro:</strong> <?= sanitize($errorMsg) ?>
          <p style="margin-top:0.5rem; font-size:0.85rem; color:#991b1b">Verifique o log <code>admin/crud_error.log</code> para detalhes técnicos.</p>
        </div>
      <?php endif; ?>

      <!-- Formulário: Novo ou Editar -->
      <?php if ($action === 'novo' || ($action === 'criar' && $errorMsg)): ?>
      <div class="card mb-2" style="border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)">
        <div class="card-header" style="background:#f8fafc">
          <span class="card-title">🆕 Cadastrar Novo Cliente</span>
          <a href="clientes.php" class="btn btn-outline btn-sm">Cancelar</a>
        </div>
        <div class="card-body">
          <form method="POST" action="clientes.php">
            <input type="hidden" name="action" value="criar">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.25rem">
              <div class="form-group">
                <label class="form-label">Razão Social <span style="color:var(--danger)">*</span></label>
                <input type="text" name="razao_social" class="form-control" required
                       value="<?= sanitize($_POST['razao_social'] ?? '') ?>" placeholder="Ex: Condomínio Solar">
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
                       value="<?= sanitize($_POST['email'] ?? '') ?>" placeholder="contato@empresa.com">
              </div>
              <div class="form-group">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control"
                       value="<?= sanitize($_POST['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
              </div>
              <div class="form-group">
                <label class="form-label">Senha de Acesso <span style="color:var(--danger)">*</span></label>
                <input type="text" name="senha" class="form-control" required minlength="6"
                       value="<?= sanitize($_POST['senha'] ?? '') ?>" placeholder="Mínimo 6 caracteres">
              </div>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem; padding-top:1rem; border-top:1px solid #f1f5f9">
              <button type="submit" class="btn btn-success" style="padding:0.75rem 1.5rem">
                💾 Salvar Cliente
              </button>
              <a href="clientes.php" class="btn btn-outline">Cancelar</a>
            </div>
          </form>
        </div>
      </div>

      <?php elseif ($action === 'editar' && $clienteEdit): ?>
      <div class="card mb-2">
        <div class="card-header">
          <span class="card-title">✏️ Editar: <?= sanitize($clienteEdit['razao_social']) ?></span>
          <a href="clientes.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>
        <div class="card-body">
          <form method="POST" action="clientes.php">
            <input type="hidden" name="action" value="salvar_edicao">
            <input type="hidden" name="cliente_id" value="<?= $clienteEdit['id'] ?>">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.25rem">
              <div class="form-group">
                <label class="form-label">Razão Social</label>
                <input type="text" name="razao_social" class="form-control" required
                       value="<?= sanitize($clienteEdit['razao_social']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">CNPJ (Somente leitura)</label>
                <input type="text" class="form-control" value="<?= sanitize($clienteEdit['cnpj']) ?>" disabled>
              </div>
              <div class="form-group">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" required
                       value="<?= sanitize($clienteEdit['email']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control"
                       value="<?= sanitize($clienteEdit['telefone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Nova Senha (Deixe vazio para manter)</label>
                <input type="text" name="nova_senha" class="form-control" minlength="6"
                       placeholder="Digite para alterar a senha">
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="ativo" class="form-control">
                  <option value="1" <?= $clienteEdit['ativo']?'selected':'' ?>>✅ Ativo</option>
                  <option value="0" <?= !$clienteEdit['ativo']?'selected':'' ?>>❌ Inativo</option>
                </select>
              </div>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem">
              <button type="submit" class="btn btn-success">Atualizar Cadastro</button>
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
                     placeholder="Buscar..." value="<?= sanitize($busca) ?>">
              <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
            </form>
            <a href="clientes.php?action=novo" class="btn btn-success btn-sm">
              ➕ Novo Cliente
            </a>
          </div>
        </div>

        <?php if (empty($clientes)): ?>
        <div class="empty-state" style="padding:4rem; text-align:center; color:#64748b">
          <h3>Nenhum cliente encontrado</h3>
          <p>Cadastre o primeiro cliente para começar a usar o sistema.</p>
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
                <th>Status</th>
                <th>Cadastro</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clientes as $cl): ?>
              <tr>
                <td class="text-muted"><?= $cl['id'] ?></td>
                <td><strong><?= sanitize($cl['razao_social']) ?></strong></td>
                <td><?= sanitize($cl['cnpj']) ?></td>
                <td><?= sanitize($cl['email']) ?></td>
                <td>
                  <span class="badge <?= $cl['ativo'] ? 'badge-fechado' : 'badge-cancelado' ?>">
                    <?= $cl['ativo'] ? 'Ativo' : 'Inativo' ?>
                  </span>
                </td>
                <td class="text-muted"><?= date('d/m/Y', strtotime($cl['criado_em'])) ?></td>
                <td>
                  <div style="display:flex;gap:.35rem">
                    <a href="clientes.php?action=editar&id=<?= $cl['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
                    <form method="POST" action="clientes.php" style="display:inline"
                          onsubmit="return confirm('Excluir este cliente e todos os seus chamados?')">
                      <input type="hidden" name="action" value="excluir">
                      <input type="hidden" name="cliente_id" value="<?= $cl['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
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
