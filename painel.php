<?php
require_once 'includes/config.php';
requireClientLogin();

$db        = getDB();
$clientId  = $_SESSION['client_id'];
$clientName= $_SESSION['client_name'];
$clientCNPJ= $_SESSION['client_cnpj'];

// Buscar estatísticas do cliente
$stats = $db->prepare('
    SELECT
      COUNT(*) AS total,
      SUM(status = "aberto") AS abertos,
      SUM(status = "em_andamento") AS andamento,
      SUM(status = "fechado") AS fechados
    FROM chamados WHERE cliente_id = ?
');
$stats->execute([$clientId]);
$s = $stats->fetch();

// Buscar todos os chamados
$chamados = $db->prepare('SELECT * FROM chamados WHERE cliente_id = ? ORDER BY criado_em DESC');
$chamados->execute([$clientId]);
$listaChamados = $chamados->fetchAll();

// Aba ativa
$tab = $_GET['tab'] ?? 'chamados';
$ticketId = (int)($_GET['ticket'] ?? 0);

// Detalhes de um chamado específico
$ticketDetail = null;
$mensagens    = [];
$historico    = [];
if ($ticketId > 0) {
    $stmt = $db->prepare('SELECT * FROM chamados WHERE id = ? AND cliente_id = ?');
    $stmt->execute([$ticketId, $clientId]);
    $ticketDetail = $stmt->fetch();
    if ($ticketDetail) {
        $mStmt = $db->prepare('SELECT * FROM mensagens WHERE chamado_id = ? ORDER BY criado_em ASC');
        $mStmt->execute([$ticketId]);
        $mensagens = $mStmt->fetchAll();

        $hStmt = $db->prepare('SELECT * FROM historico_status WHERE chamado_id = ? ORDER BY criado_em DESC');
        $hStmt->execute([$ticketId]);
        $historico = $hStmt->fetchAll();
    }
}

// Iniciais do cliente para avatar
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', $clientName), 0, 2))));
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
</head>
<body>
<div class="app-wrapper">

  <!-- ═══ SIDEBAR ═══ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="assets/logo.png" alt="ERP Condomínio">
    </div>

    <div class="sidebar-user">
      <div style="display:flex;align-items:center;gap:.6rem">
        <div class="avatar"><?= $initials ?></div>
        <div>
          <div class="sidebar-user-name"><?= sanitize($clientName) ?></div>
          <div class="sidebar-user-cnpj"><?= sanitize($clientCNPJ) ?></div>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu Principal</div>

      <a href="painel.php?tab=chamados" class="nav-item <?= $tab==='chamados'?'active':'' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        Meus Chamados
        <?php if ($s['abertos'] > 0): ?>
          <span class="tag-novo"><?= $s['abertos'] ?></span>
        <?php endif; ?>
      </a>

      <a href="painel.php?tab=chat" class="nav-item <?= $tab==='chat'?'active':'' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Chat
      </a>

      <div class="nav-section-title" style="margin-top:.5rem">Conta</div>

      <a href="painel.php?tab=perfil" class="nav-item <?= $tab==='perfil'?'active':'' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        Meu Perfil
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="logout.php" class="nav-item" style="color:#f87171">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sair
      </a>
    </div>
  </aside>

  <!-- ═══ MAIN ═══ -->
  <div class="main-content">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem">
        <button class="mobile-toggle" onclick="toggleSidebar()">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
        <span class="topbar-title">
          <?php
          $titles = ['chamados'=>'Meus Chamados','chat'=>'Chat de Suporte','perfil'=>'Meu Perfil'];
          echo $titles[$tab] ?? 'Portal de Suporte';
          ?>
        </span>
      </div>
      <div class="topbar-right">
        <div class="avatar" style="width:32px;height:32px;font-size:.7rem"><?= $initials ?></div>
        <span style="font-size:.85rem;font-weight:600;color:var(--text-muted)"><?= sanitize(explode(' ', $clientName)[0]) ?></span>
      </div>
    </header>

    <div class="page-content">

      <!-- ══ TAB: CHAMADOS ══ -->
      <?php if ($tab === 'chamados' && !$ticketDetail): ?>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
          </div>
          <div>
            <div class="stat-value"><?= $s['total'] ?></div>
            <div class="stat-label">Total de Chamados</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <div class="stat-value"><?= $s['abertos'] ?></div>
            <div class="stat-label">Abertos</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-4.3"/>
            </svg>
          </div>
          <div>
            <div class="stat-value"><?= $s['andamento'] ?></div>
            <div class="stat-label">Em Andamento</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>
          <div>
            <div class="stat-value"><?= $s['fechados'] ?></div>
            <div class="stat-label">Fechados</div>
          </div>
        </div>
      </div>

      <!-- Lista de Chamados -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:.3rem">
              <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            Todos os Chamados
          </span>
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-novo-chamado')">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Abrir Chamado
          </button>
        </div>

        <?php if (empty($listaChamados)): ?>
        <div class="empty-state">
          <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
          <h3>Nenhum chamado encontrado</h3>
          <p>Clique em "Abrir Chamado" para registrar seu primeiro atendimento.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Número</th>
                <th>Assunto</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>SLA</th>
                <th>Abertura</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($listaChamados as $ch): ?>
              <?php $sla = slaStatus($ch['criado_em'], $ch['prioridade'], $ch['status']); ?>
              <tr>
                <td><span class="ticket-number"><?= sanitize($ch['numero']) ?></span></td>
                <td>
                  <div style="font-weight:600;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= sanitize($ch['assunto']) ?>
                  </div>
                  <div class="text-muted text-small"><?= timeAgo($ch['criado_em']) ?></div>
                </td>
                <td><?= priorityBadge($ch['prioridade']) ?></td>
                <td><?= statusBadge($ch['status']) ?></td>
                <td>
                  <div class="sla-bar-wrap <?= $sla['class'] ?>">
                    <div class="sla-bar-bg">
                      <div class="sla-bar-fill" style="width:<?= $sla['percent'] ?>%"></div>
                    </div>
                    <div class="sla-label"><?= $sla['label'] ?></div>
                  </div>
                </td>
                <td class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($ch['criado_em'])) ?></td>
                <td>
                  <a href="painel.php?tab=chamados&ticket=<?= $ch['id'] ?>" class="btn btn-outline btn-sm">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    Ver
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- ══ TAB: DETALHE DO CHAMADO ══ -->
      <?php elseif ($tab === 'chamados' && $ticketDetail): ?>

      <div style="margin-bottom:1rem">
        <a href="painel.php?tab=chamados" class="btn btn-outline btn-sm">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          Voltar aos Chamados
        </a>
      </div>

      <?php $sla = slaStatus($ticketDetail['criado_em'], $ticketDetail['prioridade'], $ticketDetail['status']); ?>

      <div class="card mb-2">
        <div class="card-header">
          <div>
            <span class="ticket-number"><?= sanitize($ticketDetail['numero']) ?></span>
            <span style="margin-left:.75rem;font-weight:700;font-size:1rem"><?= sanitize($ticketDetail['assunto']) ?></span>
          </div>
          <div style="display:flex;gap:.5rem;align-items:center">
            <?= priorityBadge($ticketDetail['prioridade']) ?>
            <?= statusBadge($ticketDetail['status']) ?>
          </div>
        </div>
        <div class="card-body">
          <div class="ticket-info-grid">
            <div class="ticket-info-item">
              <label>Número</label>
              <span class="ticket-number"><?= sanitize($ticketDetail['numero']) ?></span>
            </div>
            <div class="ticket-info-item">
              <label>Aberto em</label>
              <span><?= date('d/m/Y \à\s H:i', strtotime($ticketDetail['criado_em'])) ?></span>
            </div>
            <div class="ticket-info-item">
              <label>Última atualização</label>
              <span><?= timeAgo($ticketDetail['atualizado_em']) ?></span>
            </div>
            <div class="ticket-info-item">
              <label>SLA</label>
              <div class="sla-bar-wrap <?= $sla['class'] ?>" style="min-width:160px">
                <div class="sla-bar-bg">
                  <div class="sla-bar-fill" style="width:<?= $sla['percent'] ?>%"></div>
                </div>
                <div class="sla-label"><?= $sla['label'] ?></div>
              </div>
            </div>
          </div>

          <div class="divider"></div>

          <div style="font-weight:600;margin-bottom:.5rem">Descrição</div>
          <div style="background:var(--bg);border-radius:var(--radius);padding:1rem;font-size:.9rem;line-height:1.7;white-space:pre-wrap"><?= sanitize($ticketDetail['descricao']) ?></div>

          <?php if ($ticketDetail['anexo']): ?>
          <div style="margin-top:1rem">
            <span style="font-weight:600">Anexo: </span>
            <a href="<?= sanitize(UPLOAD_URL . $ticketDetail['anexo']) ?>" target="_blank" class="btn btn-outline btn-sm">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
              </svg>
              Baixar Anexo
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Chat do Chamado -->
      <div class="card mb-2">
        <div class="card-header">
          <span class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:.3rem">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Mensagens do Chamado
          </span>
        </div>
        <div class="chat-messages" style="max-height:320px;overflow-y:auto;padding:1rem" id="chat-msgs">
          <?php if (empty($mensagens)): ?>
          <div class="empty-state" style="padding:1.5rem">
            <p>Nenhuma mensagem ainda. Envie uma mensagem abaixo.</p>
          </div>
          <?php else: ?>
          <?php foreach ($mensagens as $msg): ?>
          <div class="msg-bubble <?= $msg['autor'] ?>">
            <div><?= nl2br(sanitize($msg['mensagem'])) ?></div>
            <div class="msg-meta"><?= sanitize($msg['nome']) ?> · <?= date('d/m H:i', strtotime($msg['criado_em'])) ?></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php if (!in_array($ticketDetail['status'], ['fechado','cancelado'])): ?>
        <form method="POST" action="actions/enviar_mensagem.php" class="chat-input-area">
          <input type="hidden" name="chamado_id" value="<?= $ticketDetail['id'] ?>">
          <input type="hidden" name="redirect" value="painel.php?tab=chamados&ticket=<?= $ticketDetail['id'] ?>">
          <textarea name="mensagem" class="form-control" placeholder="Digite sua mensagem..." rows="2" required></textarea>
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
          </button>
        </form>
        <?php else: ?>
        <div style="padding:.75rem 1rem;background:var(--bg);border-top:1px solid var(--border);font-size:.8rem;color:var(--text-muted);text-align:center">
          Este chamado está encerrado. Para nova solicitação, abra um novo chamado.
        </div>
        <?php endif; ?>
      </div>

      <!-- Histórico -->
      <?php if (!empty($historico)): ?>
      <div class="card">
        <div class="card-header">
          <span class="card-title">Histórico de Atualizações</span>
        </div>
        <div class="card-body">
          <div class="history-list">
            <?php foreach ($historico as $h): ?>
            <div class="history-item">
              <div class="history-dot"></div>
              <div>
                <strong><?= sanitize($h['autor']) ?></strong> alterou status de
                <strong><?= sanitize($h['status_de']) ?></strong> para <strong><?= sanitize($h['status_para']) ?></strong>
                <?php if ($h['observacao']): ?>
                  — <?= sanitize($h['observacao']) ?>
                <?php endif; ?>
                <div class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ══ TAB: CHAT GERAL ══ -->
      <?php elseif ($tab === 'chat'): ?>

      <div class="card" style="height:calc(100vh - 140px);display:flex;flex-direction:column">
        <div class="card-header">
          <span class="card-title">Chat de Suporte</span>
          <span class="text-muted text-small">Selecione um chamado para conversar</span>
        </div>
        <div style="display:flex;flex:1;overflow:hidden">
          <!-- Lista de chamados no chat -->
          <div style="width:260px;border-right:1px solid var(--border);overflow-y:auto;flex-shrink:0">
            <?php if (empty($listaChamados)): ?>
            <div style="padding:1rem;text-align:center;color:var(--text-muted);font-size:.8rem">
              Nenhum chamado aberto.<br>
              <a href="painel.php?tab=chamados">Abrir chamado</a>
            </div>
            <?php else: ?>
            <?php foreach ($listaChamados as $ch): ?>
            <?php $isActive = (isset($_GET['chat_ticket']) && $_GET['chat_ticket'] == $ch['id']); ?>
            <a href="painel.php?tab=chat&chat_ticket=<?= $ch['id'] ?>"
               style="display:block;padding:.75rem 1rem;border-bottom:1px solid var(--border);text-decoration:none;background:<?= $isActive ? '#eff6ff' : 'transparent' ?>;transition:background .15s"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='<?= $isActive ? '#eff6ff' : 'transparent' ?>'">
              <div style="font-weight:600;font-size:.8rem;color:var(--text)"><?= sanitize($ch['assunto']) ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);margin-top:.15rem">
                <span class="ticket-number" style="font-size:.65rem"><?= sanitize($ch['numero']) ?></span>
                &nbsp;<?= statusBadge($ch['status']) ?>
              </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Área de chat -->
          <?php
          $chatTicketId = (int)($_GET['chat_ticket'] ?? 0);
          $chatTicket = null;
          $chatMsgs   = [];
          if ($chatTicketId > 0) {
              $stmt = $db->prepare('SELECT * FROM chamados WHERE id = ? AND cliente_id = ?');
              $stmt->execute([$chatTicketId, $clientId]);
              $chatTicket = $stmt->fetch();
              if ($chatTicket) {
                  $mStmt = $db->prepare('SELECT * FROM mensagens WHERE chamado_id = ? ORDER BY criado_em ASC');
                  $mStmt->execute([$chatTicketId]);
                  $chatMsgs = $mStmt->fetchAll();
              }
          }
          ?>
          <div style="flex:1;display:flex;flex-direction:column;overflow:hidden">
            <?php if (!$chatTicket): ?>
            <div class="chat-select-hint">
              <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              <span>Selecione um chamado ao lado para iniciar o chat</span>
            </div>
            <?php else: ?>
            <div style="padding:.75rem 1rem;border-bottom:1px solid var(--border);background:var(--bg)">
              <div style="font-weight:700;font-size:.875rem"><?= sanitize($chatTicket['assunto']) ?></div>
              <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($chatTicket['numero']) ?> · <?= statusBadge($chatTicket['status']) ?></div>
            </div>
            <div class="chat-messages" id="chat-msgs" style="flex:1;overflow-y:auto;padding:1rem">
              <?php if (empty($chatMsgs)): ?>
              <div class="empty-state" style="padding:1.5rem"><p>Nenhuma mensagem ainda.</p></div>
              <?php else: ?>
              <?php foreach ($chatMsgs as $msg): ?>
              <div class="msg-bubble <?= $msg['autor'] ?>">
                <div><?= nl2br(sanitize($msg['mensagem'])) ?></div>
                <div class="msg-meta"><?= sanitize($msg['nome']) ?> · <?= date('d/m H:i', strtotime($msg['criado_em'])) ?></div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if (!in_array($chatTicket['status'], ['fechado','cancelado'])): ?>
            <form method="POST" action="actions/enviar_mensagem.php" class="chat-input-area">
              <input type="hidden" name="chamado_id" value="<?= $chatTicket['id'] ?>">
              <input type="hidden" name="redirect" value="painel.php?tab=chat&chat_ticket=<?= $chatTicket['id'] ?>">
              <textarea name="mensagem" class="form-control" placeholder="Digite sua mensagem..." rows="2" required></textarea>
              <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
              </button>
            </form>
            <?php else: ?>
            <div style="padding:.75rem 1rem;background:var(--bg);border-top:1px solid var(--border);font-size:.8rem;color:var(--text-muted);text-align:center">
              Chamado encerrado.
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ══ TAB: PERFIL ══ -->
      <?php elseif ($tab === 'perfil'): ?>

      <?php
      $perfilMsg = '';
      $perfilErr = '';
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_perfil'])) {
          $novaSenha   = trim($_POST['nova_senha']   ?? '');
          $confirmSenha= trim($_POST['confirm_senha'] ?? '');
          $senhaAtual  = trim($_POST['senha_atual']   ?? '');

          $stmt = $db->prepare('SELECT senha FROM clientes WHERE id = ?');
          $stmt->execute([$clientId]);
          $row = $stmt->fetch();

          if (!password_verify($senhaAtual, $row['senha'])) {
              $perfilErr = 'Senha atual incorreta.';
          } elseif (strlen($novaSenha) < 6) {
              $perfilErr = 'A nova senha deve ter pelo menos 6 caracteres.';
          } elseif ($novaSenha !== $confirmSenha) {
              $perfilErr = 'As senhas não coincidem.';
          } else {
              $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
              $db->prepare('UPDATE clientes SET senha = ? WHERE id = ?')->execute([$hash, $clientId]);
              $perfilMsg = 'Senha alterada com sucesso!';
          }
      }

      $stmtClient = $db->prepare('SELECT * FROM clientes WHERE id = ?');
      $stmtClient->execute([$clientId]);
      $clientData = $stmtClient->fetch();
      ?>

      <div style="max-width:560px">
        <div class="card mb-2">
          <div class="card-header">
            <span class="card-title">Dados da Conta</span>
          </div>
          <div class="card-body">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
              <div class="avatar" style="width:56px;height:56px;font-size:1.1rem"><?= $initials ?></div>
              <div>
                <div style="font-weight:700;font-size:1rem"><?= sanitize($clientData['razao_social']) ?></div>
                <div class="text-muted text-small"><?= sanitize($clientData['email']) ?></div>
              </div>
            </div>
            <div class="ticket-info-grid">
              <div class="ticket-info-item">
                <label>CNPJ</label>
                <span><?= sanitize($clientData['cnpj']) ?></span>
              </div>
              <div class="ticket-info-item">
                <label>Telefone</label>
                <span><?= sanitize($clientData['telefone'] ?: 'Não informado') ?></span>
              </div>
              <div class="ticket-info-item">
                <label>E-mail</label>
                <span><?= sanitize($clientData['email']) ?></span>
              </div>
              <div class="ticket-info-item">
                <label>Cliente desde</label>
                <span><?= date('d/m/Y', strtotime($clientData['criado_em'])) ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <span class="card-title">Alterar Senha</span>
          </div>
          <div class="card-body">
            <?php if ($perfilMsg): ?><div class="alert alert-success"><?= sanitize($perfilMsg) ?></div><?php endif; ?>
            <?php if ($perfilErr): ?><div class="alert alert-danger"><?= sanitize($perfilErr) ?></div><?php endif; ?>
            <form method="POST" action="">
              <input type="hidden" name="action_perfil" value="1">
              <div class="form-group">
                <label class="form-label">Senha Atual</label>
                <input type="password" name="senha_atual" class="form-control" placeholder="Digite sua senha atual" required>
              </div>
              <div class="form-group">
                <label class="form-label">Nova Senha</label>
                <input type="password" name="nova_senha" class="form-control" placeholder="Mínimo 6 caracteres" required>
              </div>
              <div class="form-group">
                <label class="form-label">Confirmar Nova Senha</label>
                <input type="password" name="confirm_senha" class="form-control" placeholder="Repita a nova senha" required>
              </div>
              <button type="submit" class="btn btn-primary">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Salvar Nova Senha
              </button>
            </form>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div><!-- /app-wrapper -->

<!-- ═══ MODAL: NOVO CHAMADO ═══ -->
<div class="modal-overlay" id="modal-novo-chamado">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:.3rem">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Abrir Novo Chamado
      </span>
      <button class="modal-close" onclick="closeModal('modal-novo-chamado')">✕</button>
    </div>
    <form method="POST" action="actions/abrir_chamado.php" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Assunto <span style="color:var(--danger)">*</span></label>
          <input type="text" name="assunto" class="form-control" placeholder="Descreva brevemente o problema" required maxlength="255">
        </div>
        <div class="form-group">
          <label class="form-label">Prioridade</label>
          <select name="prioridade" class="form-control">
            <option value="baixa">🟢 Baixa – Dúvida geral (SLA: 72h)</option>
            <option value="media" selected>🟡 Média – Problema funcional (SLA: 24h)</option>
            <option value="alta">🟠 Alta – Impacto operacional (SLA: 8h)</option>
            <option value="critica">🔴 Crítica – Sistema fora do ar (SLA: 2h)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Descrição detalhada <span style="color:var(--danger)">*</span></label>
          <textarea name="descricao" class="form-control" placeholder="Descreva o problema com o máximo de detalhes possível..." rows="5" required></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Anexo (opcional)</label>
          <div class="file-drop" id="file-drop-area" onclick="document.getElementById('anexo-input').click()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:.5rem">
              <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
            </svg>
            <div id="file-label">Clique para selecionar ou arraste o arquivo aqui</div>
            <div class="text-small text-muted">PDF, PNG, JPG, DOCX – máx. 5MB</div>
          </div>
          <input type="file" id="anexo-input" name="anexo" style="display:none"
                 accept=".pdf,.png,.jpg,.jpeg,.gif,.docx,.xlsx,.txt,.zip"
                 onchange="updateFileLabel(this)">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-novo-chamado')">Cancelar</button>
        <button type="submit" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
          Abrir Chamado
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Modal
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

// Sidebar mobile
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// File label
function updateFileLabel(input) {
  const label = document.getElementById('file-label');
  if (input.files.length > 0) {
    label.textContent = '📎 ' + input.files[0].name;
    document.getElementById('file-drop-area').style.borderColor = 'var(--primary-light)';
    document.getElementById('file-drop-area').style.background = '#eff6ff';
  }
}

// Drag & drop
const dropArea = document.getElementById('file-drop-area');
if (dropArea) {
  dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('dragover'); });
  dropArea.addEventListener('dragleave', () => dropArea.classList.remove('dragover'));
  dropArea.addEventListener('drop', e => {
    e.preventDefault();
    dropArea.classList.remove('dragover');
    const input = document.getElementById('anexo-input');
    input.files = e.dataTransfer.files;
    updateFileLabel(input);
  });
}

// Scroll chat to bottom
const chatMsgs = document.getElementById('chat-msgs');
if (chatMsgs) chatMsgs.scrollTop = chatMsgs.scrollHeight;
</script>
</body>
</html>
