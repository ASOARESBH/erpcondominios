<?php
require_once '../includes/config.php';
requireAdminLogin();

$db = getDB();

// Filtros
$statusFiltro    = $_GET['status']    ?? '';
$prioFiltro      = $_GET['prioridade'] ?? '';
$buscaFiltro     = trim($_GET['busca'] ?? '');
$clienteFiltro   = (int)($_GET['cliente'] ?? 0);

$where  = ['1=1'];
$params = [];

if ($statusFiltro) {
    $where[]  = 'c.status = ?';
    $params[] = $statusFiltro;
}
if ($prioFiltro) {
    $where[]  = 'c.prioridade = ?';
    $params[] = $prioFiltro;
}
if ($buscaFiltro) {
    $where[]  = '(c.numero LIKE ? OR c.assunto LIKE ? OR cl.razao_social LIKE ?)';
    $params[] = "%{$buscaFiltro}%";
    $params[] = "%{$buscaFiltro}%";
    $params[] = "%{$buscaFiltro}%";
}
if ($clienteFiltro) {
    $where[]  = 'c.cliente_id = ?';
    $params[] = $clienteFiltro;
}

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT c.*, cl.razao_social, cl.cnpj
    FROM chamados c
    JOIN clientes cl ON cl.id = c.cliente_id
    WHERE {$whereStr}
    ORDER BY
      FIELD(c.status,'aberto','em_andamento','aguardando','fechado','cancelado'),
      FIELD(c.prioridade,'critica','alta','media','baixa'),
      c.criado_em ASC
");
$stmt->execute($params);
$chamados = $stmt->fetchAll();

// Lista de clientes para filtro
$clientes = $db->query('SELECT id, razao_social FROM clientes ORDER BY razao_social')->fetchAll();

// Mensagem de sucesso/erro
$msg = $_GET['msg'] ?? '';
$msgs = [
    'status_ok'    => ['success', 'Status do chamado atualizado com sucesso!'],
    'msg_ok'       => ['success', 'Mensagem enviada com sucesso!'],
    'erro_status'  => ['danger',  'Erro ao atualizar o status. Tente novamente.'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chamados – Admin ERP Condomínio</title>
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

      <?php if ($msg && isset($msgs[$msg])): ?>
      <div class="alert alert-<?= $msgs[$msg][0] ?>"><?= $msgs[$msg][1] ?></div>
      <?php endif; ?>

      <!-- Filtros -->
      <div class="card mb-2">
        <div class="card-body" style="padding:.875rem 1.25rem">
          <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
            <div style="flex:1;min-width:180px">
              <label class="form-label">Busca</label>
              <input type="text" name="busca" class="form-control" placeholder="Número, assunto ou cliente..."
                     value="<?= sanitize($buscaFiltro) ?>">
            </div>
            <div style="min-width:140px">
              <label class="form-label">Status</label>
              <select name="status" class="form-control">
                <option value="">Todos</option>
                <option value="aberto"       <?= $statusFiltro==='aberto'?'selected':'' ?>>Aberto</option>
                <option value="em_andamento" <?= $statusFiltro==='em_andamento'?'selected':'' ?>>Em Andamento</option>
                <option value="aguardando"   <?= $statusFiltro==='aguardando'?'selected':'' ?>>Aguardando</option>
                <option value="fechado"      <?= $statusFiltro==='fechado'?'selected':'' ?>>Fechado</option>
                <option value="cancelado"    <?= $statusFiltro==='cancelado'?'selected':'' ?>>Cancelado</option>
              </select>
            </div>
            <div style="min-width:130px">
              <label class="form-label">Prioridade</label>
              <select name="prioridade" class="form-control">
                <option value="">Todas</option>
                <option value="critica" <?= $prioFiltro==='critica'?'selected':'' ?>>Crítica</option>
                <option value="alta"    <?= $prioFiltro==='alta'?'selected':'' ?>>Alta</option>
                <option value="media"   <?= $prioFiltro==='media'?'selected':'' ?>>Média</option>
                <option value="baixa"   <?= $prioFiltro==='baixa'?'selected':'' ?>>Baixa</option>
              </select>
            </div>
            <div style="min-width:180px">
              <label class="form-label">Cliente</label>
              <select name="cliente" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($clientes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $clienteFiltro==$cl['id']?'selected':'' ?>>
                  <?= sanitize(mb_substr($cl['razao_social'],0,35)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:flex;gap:.5rem">
              <button type="submit" class="btn btn-primary btn-sm">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Filtrar
              </button>
              <a href="chamados.php" class="btn btn-outline btn-sm">Limpar</a>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabela -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">
            <?= count($chamados) ?> chamado<?= count($chamados) !== 1 ? 's' : '' ?> encontrado<?= count($chamados) !== 1 ? 's' : '' ?>
          </span>
        </div>

        <?php if (empty($chamados)): ?>
        <div class="empty-state">
          <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
          <h3>Nenhum chamado encontrado</h3>
          <p>Tente ajustar os filtros acima.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Número</th>
                <th>Cliente</th>
                <th>Assunto</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>SLA</th>
                <th>Abertura</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($chamados as $ch): ?>
              <?php $sla = slaStatus($ch['criado_em'], $ch['prioridade'], $ch['status']); ?>
              <tr>
                <td><span class="ticket-number"><?= sanitize($ch['numero']) ?></span></td>
                <td>
                  <div style="font-size:.8rem;font-weight:600;max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= sanitize($ch['razao_social']) ?>
                  </div>
                  <div class="text-muted text-small"><?= sanitize($ch['cnpj']) ?></div>
                </td>
                <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                  <?= sanitize($ch['assunto']) ?>
                </td>
                <td><?= priorityBadge($ch['prioridade']) ?></td>
                <td><?= statusBadge($ch['status']) ?></td>
                <td>
                  <div class="sla-bar-wrap <?= $sla['class'] ?>" style="min-width:110px">
                    <div class="sla-bar-bg">
                      <div class="sla-bar-fill" style="width:<?= $sla['percent'] ?>%"></div>
                    </div>
                    <div class="sla-label"><?= $sla['label'] ?></div>
                  </div>
                </td>
                <td class="text-muted text-small" style="white-space:nowrap">
                  <?= date('d/m/Y', strtotime($ch['criado_em'])) ?><br>
                  <?= date('H:i', strtotime($ch['criado_em'])) ?>
                </td>
                <td>
                  <a href="chamado_detalhe.php?id=<?= $ch['id'] ?>" class="btn btn-primary btn-sm">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    Abrir
                  </a>
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
</script>
</body>
</html>
