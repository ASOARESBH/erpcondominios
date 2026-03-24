<?php
require_once '../includes/config.php';
requireAdminLogin();

$db = getDB();

// Estatísticas gerais
$stats = $db->query('
    SELECT
      COUNT(*) AS total,
      SUM(status = "aberto") AS abertos,
      SUM(status = "em_andamento") AS andamento,
      SUM(status = "aguardando") AS aguardando,
      SUM(status = "fechado") AS fechados,
      SUM(status = "cancelado") AS cancelados
    FROM chamados
')->fetch();

// Chamados com SLA vencido (abertos há mais tempo que o SLA)
$slaVencidos = $db->query("
    SELECT COUNT(*) FROM chamados
    WHERE status NOT IN ('fechado','cancelado')
    AND (
      (prioridade = 'critica'  AND criado_em < DATE_SUB(NOW(), INTERVAL 2  HOUR))  OR
      (prioridade = 'alta'     AND criado_em < DATE_SUB(NOW(), INTERVAL 8  HOUR))  OR
      (prioridade = 'media'    AND criado_em < DATE_SUB(NOW(), INTERVAL 24 HOUR))  OR
      (prioridade = 'baixa'    AND criado_em < DATE_SUB(NOW(), INTERVAL 72 HOUR))
    )
")->fetchColumn();

// Clientes ativos
$totalClientes = $db->query('SELECT COUNT(*) FROM clientes WHERE ativo = 1')->fetchColumn();

// Chamados recentes
$recentes = $db->query('
    SELECT c.*, cl.razao_social, cl.cnpj
    FROM chamados c
    JOIN clientes cl ON cl.id = c.cliente_id
    ORDER BY c.criado_em DESC
    LIMIT 10
')->fetchAll();

// Chamados por prioridade (para gráfico simples)
$porPrioridade = $db->query('
    SELECT prioridade, COUNT(*) as qtd
    FROM chamados
    WHERE status NOT IN ("fechado","cancelado")
    GROUP BY prioridade
')->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard – ERP Condomínio</title>
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
      <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.3rem;font-weight:800">Dashboard</h1>
        <p class="text-muted text-small">Visão geral do sistema de suporte · <?= date('d/m/Y H:i') ?></p>
      </div>

      <!-- Stats -->
      <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
        <div class="stat-card">
          <div class="stat-icon blue">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
          </div>
          <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Chamados</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div><div class="stat-value"><?= $stats['abertos'] ?></div><div class="stat-label">Abertos</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-4.3"/>
            </svg>
          </div>
          <div><div class="stat-value"><?= $stats['andamento'] ?></div><div class="stat-label">Em Andamento</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>
          <div><div class="stat-value"><?= $stats['fechados'] ?></div><div class="stat-label">Fechados</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <div><div class="stat-value" style="color:var(--danger)"><?= $slaVencidos ?></div><div class="stat-label">SLA Vencido</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue" style="background:#f0fdf4;color:#16a34a">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <div><div class="stat-value"><?= $totalClientes ?></div><div class="stat-label">Clientes</div></div>
        </div>
      </div>

      <!-- Chamados Recentes + Resumo -->
      <div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem">
        <div class="card">
          <div class="card-header">
            <span class="card-title">Chamados Recentes</span>
            <a href="chamados.php" class="btn btn-outline btn-sm">Ver todos</a>
          </div>
          <?php if (empty($recentes)): ?>
          <div class="empty-state"><p>Nenhum chamado registrado ainda.</p></div>
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
                  <th>Abertura</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentes as $ch): ?>
                <tr>
                  <td><span class="ticket-number"><?= sanitize($ch['numero']) ?></span></td>
                  <td>
                    <div style="font-size:.8rem;font-weight:600"><?= sanitize(mb_substr($ch['razao_social'],0,25)) ?></div>
                    <div class="text-muted text-small"><?= sanitize($ch['cnpj']) ?></div>
                  </td>
                  <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($ch['assunto']) ?></td>
                  <td><?= priorityBadge($ch['prioridade']) ?></td>
                  <td><?= statusBadge($ch['status']) ?></td>
                  <td class="text-muted text-small"><?= timeAgo($ch['criado_em']) ?></td>
                  <td>
                    <a href="chamado_detalhe.php?id=<?= $ch['id'] ?>" class="btn btn-outline btn-sm">Abrir</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <!-- Resumo lateral -->
        <div style="display:flex;flex-direction:column;gap:1rem">
          <div class="card">
            <div class="card-header"><span class="card-title">Abertos por Prioridade</span></div>
            <div class="card-body">
              <?php
              $prios = ['critica'=>['Crítica','var(--danger)'],'alta'=>['Alta','var(--accent)'],'media'=>['Média','var(--warning)'],'baixa'=>['Baixa','var(--secondary)']];
              $totalAbertos = array_sum($porPrioridade) ?: 1;
              foreach ($prios as $key => [$label, $color]):
                $qtd = $porPrioridade[$key] ?? 0;
                $pct = round(($qtd / $totalAbertos) * 100);
              ?>
              <div style="margin-bottom:.75rem">
                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.25rem">
                  <span style="font-weight:600"><?= $label ?></span>
                  <span class="text-muted"><?= $qtd ?></span>
                </div>
                <div class="sla-bar-bg">
                  <div class="sla-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><span class="card-title">Ações Rápidas</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:.5rem">
              <a href="chamados.php?status=aberto" class="btn btn-outline btn-sm btn-block">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Chamados Abertos
              </a>
              <a href="chamados.php?status=em_andamento" class="btn btn-outline btn-sm btn-block">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-4.3"/>
                </svg>
                Em Andamento
              </a>
              <a href="clientes.php" class="btn btn-outline btn-sm btn-block">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                </svg>
                Gerenciar Clientes
              </a>
              <a href="clientes.php?action=novo" class="btn btn-success btn-sm btn-block">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Novo Cliente
              </a>
            </div>
          </div>
        </div>
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
