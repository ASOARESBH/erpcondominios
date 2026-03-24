<?php
require_once '../includes/config.php';
requireAdminLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: chamados.php');
    exit;
}

$stmt = $db->prepare('
    SELECT c.*, cl.razao_social, cl.cnpj, cl.email, cl.telefone
    FROM chamados c
    JOIN clientes cl ON cl.id = c.cliente_id
    WHERE c.id = ?
');
$stmt->execute([$id]);
$chamado = $stmt->fetch();

if (!$chamado) {
    header('Location: chamados.php');
    exit;
}

// Processar ações POST
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Atualizar status
    if ($action === 'update_status') {
        $novoStatus = $_POST['novo_status'] ?? '';
        $obs        = trim($_POST['observacao'] ?? '');
        $statusValidos = ['aberto','em_andamento','aguardando','fechado','cancelado'];

        if (in_array($novoStatus, $statusValidos) && $novoStatus !== $chamado['status']) {
            $fechadoEm = in_array($novoStatus, ['fechado','cancelado']) ? date('Y-m-d H:i:s') : null;

            $db->prepare('UPDATE chamados SET status = ?, fechado_em = ?, atualizado_em = NOW() WHERE id = ?')
               ->execute([$novoStatus, $fechadoEm, $id]);

            $db->prepare('
                INSERT INTO historico_status (chamado_id, status_de, status_para, observacao, autor)
                VALUES (?, ?, ?, ?, "Equipe de Suporte")
            ')->execute([$id, $chamado['status'], $novoStatus, $obs]);

            // Mensagem automática para o cliente
            $statusLabels = ['aberto'=>'Aberto','em_andamento'=>'Em Andamento','aguardando'=>'Aguardando Retorno','fechado'=>'Fechado','cancelado'=>'Cancelado'];
            $msgAuto = "Status atualizado para: **{$statusLabels[$novoStatus]}**";
            if ($obs) $msgAuto .= "\n\n{$obs}";
            if ($novoStatus === 'fechado') {
                $msgAuto .= "\n\nSeu chamado foi finalizado com sucesso! Obrigado por utilizar nosso suporte. Se precisar de mais ajuda, abra um novo chamado.";
            }

            $db->prepare('INSERT INTO mensagens (chamado_id, autor, nome, mensagem) VALUES (?, "admin", "Equipe de Suporte", ?)')
               ->execute([$id, $msgAuto]);

            $successMsg = 'Status atualizado com sucesso!';
            $chamado['status'] = $novoStatus;
        }
    }

    // Enviar mensagem
    if ($action === 'send_msg') {
        $mensagem = trim($_POST['mensagem'] ?? '');
        if (!empty($mensagem)) {
            $db->prepare('INSERT INTO mensagens (chamado_id, autor, nome, mensagem) VALUES (?, "admin", "Equipe de Suporte", ?)')
               ->execute([$id, $mensagem]);
            $db->prepare('UPDATE chamados SET atualizado_em = NOW() WHERE id = ?')->execute([$id]);
            $successMsg = 'Mensagem enviada!';
        }
    }

    // Atualizar prioridade
    if ($action === 'update_priority') {
        $novaPrio = $_POST['nova_prioridade'] ?? '';
        $priosValidas = ['baixa','media','alta','critica'];
        if (in_array($novaPrio, $priosValidas)) {
            $db->prepare('UPDATE chamados SET prioridade = ?, atualizado_em = NOW() WHERE id = ?')
               ->execute([$novaPrio, $id]);
            $chamado['prioridade'] = $novaPrio;
            $successMsg = 'Prioridade atualizada!';
        }
    }
}

// Recarregar chamado atualizado
$stmt->execute([$id]);
$chamado = $stmt->fetch();

// Mensagens
$mStmt = $db->prepare('SELECT * FROM mensagens WHERE chamado_id = ? ORDER BY criado_em ASC');
$mStmt->execute([$id]);
$mensagens = $mStmt->fetchAll();

// Histórico
$hStmt = $db->prepare('SELECT * FROM historico_status WHERE chamado_id = ? ORDER BY criado_em DESC');
$hStmt->execute([$id]);
$historico = $hStmt->fetchAll();

$sla = slaStatus($chamado['criado_em'], $chamado['prioridade'], $chamado['status']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chamado <?= sanitize($chamado['numero']) ?> – Admin</title>
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
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
        <a href="chamados.php" class="btn btn-outline btn-sm">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          Voltar
        </a>
        <span class="ticket-number" style="font-size:.9rem"><?= sanitize($chamado['numero']) ?></span>
        <?= priorityBadge($chamado['prioridade']) ?>
        <?= statusBadge($chamado['status']) ?>
      </div>

      <?php if ($successMsg): ?>
      <div class="alert alert-success"><?= sanitize($successMsg) ?></div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
      <div class="alert alert-danger"><?= sanitize($errorMsg) ?></div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start">

        <!-- Coluna principal -->
        <div style="display:flex;flex-direction:column;gap:1.25rem">

          <!-- Info do chamado -->
          <div class="card">
            <div class="card-header">
              <span class="card-title"><?= sanitize($chamado['assunto']) ?></span>
            </div>
            <div class="card-body">
              <div class="ticket-info-grid">
                <div class="ticket-info-item">
                  <label>Número</label>
                  <span class="ticket-number"><?= sanitize($chamado['numero']) ?></span>
                </div>
                <div class="ticket-info-item">
                  <label>Cliente</label>
                  <span><?= sanitize($chamado['razao_social']) ?></span>
                </div>
                <div class="ticket-info-item">
                  <label>CNPJ</label>
                  <span><?= sanitize($chamado['cnpj']) ?></span>
                </div>
                <div class="ticket-info-item">
                  <label>E-mail</label>
                  <span><?= sanitize($chamado['email']) ?></span>
                </div>
                <div class="ticket-info-item">
                  <label>Telefone</label>
                  <span><?= sanitize($chamado['telefone'] ?: 'Não informado') ?></span>
                </div>
                <div class="ticket-info-item">
                  <label>Aberto em</label>
                  <span><?= date('d/m/Y H:i', strtotime($chamado['criado_em'])) ?></span>
                </div>
                <div class="ticket-info-item">
                  <label>Última atualização</label>
                  <span><?= timeAgo($chamado['atualizado_em']) ?></span>
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

              <div style="font-weight:600;margin-bottom:.5rem">Descrição do Cliente</div>
              <div style="background:var(--bg);border-radius:var(--radius);padding:1rem;font-size:.9rem;line-height:1.7;white-space:pre-wrap"><?= sanitize($chamado['descricao']) ?></div>

              <?php if ($chamado['anexo']): ?>
              <div style="margin-top:1rem">
                <span style="font-weight:600">Anexo: </span>
                <a href="<?= sanitize('../' . UPLOAD_URL . $chamado['anexo']) ?>" target="_blank" class="btn btn-outline btn-sm">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                  </svg>
                  Baixar Anexo
                </a>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Chat -->
          <div class="card">
            <div class="card-header">
              <span class="card-title">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:.3rem">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Mensagens (<?= count($mensagens) ?>)
              </span>
            </div>
            <div class="chat-messages" id="chat-msgs" style="max-height:380px;overflow-y:auto;padding:1rem">
              <?php if (empty($mensagens)): ?>
              <div class="empty-state" style="padding:1.5rem"><p>Nenhuma mensagem ainda.</p></div>
              <?php else: ?>
              <?php foreach ($mensagens as $msg): ?>
              <div class="msg-bubble <?= $msg['autor'] ?>">
                <div><?= nl2br(sanitize($msg['mensagem'])) ?></div>
                <div class="msg-meta"><?= sanitize($msg['nome']) ?> · <?= date('d/m H:i', strtotime($msg['criado_em'])) ?></div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <form method="POST" action="" class="chat-input-area">
              <input type="hidden" name="action" value="send_msg">
              <textarea name="mensagem" class="form-control" placeholder="Responder ao cliente..." rows="2" required></textarea>
              <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
              </button>
            </form>
          </div>

          <!-- Histórico -->
          <?php if (!empty($historico)): ?>
          <div class="card">
            <div class="card-header"><span class="card-title">Histórico de Status</span></div>
            <div class="card-body">
              <div class="history-list">
                <?php foreach ($historico as $h): ?>
                <div class="history-item">
                  <div class="history-dot"></div>
                  <div>
                    <strong><?= sanitize($h['autor']) ?></strong>: <?= sanitize($h['status_de']) ?> → <strong><?= sanitize($h['status_para']) ?></strong>
                    <?php if ($h['observacao']): ?><br><span class="text-muted"><?= sanitize($h['observacao']) ?></span><?php endif; ?>
                    <div class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Coluna lateral: ações -->
        <div style="display:flex;flex-direction:column;gap:1rem;position:sticky;top:80px">

          <!-- Atualizar Status -->
          <div class="card">
            <div class="card-header">
              <span class="card-title">Atualizar Status</span>
            </div>
            <div class="card-body">
              <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <div class="form-group">
                  <label class="form-label">Novo Status</label>
                  <select name="novo_status" class="form-control" required>
                    <option value="aberto"       <?= $chamado['status']==='aberto'?'selected':'' ?>>🔵 Aberto</option>
                    <option value="em_andamento" <?= $chamado['status']==='em_andamento'?'selected':'' ?>>🟡 Em Andamento</option>
                    <option value="aguardando"   <?= $chamado['status']==='aguardando'?'selected':'' ?>>🟠 Aguardando Cliente</option>
                    <option value="fechado"      <?= $chamado['status']==='fechado'?'selected':'' ?>>✅ Fechado / Resolvido</option>
                    <option value="cancelado"    <?= $chamado['status']==='cancelado'?'selected':'' ?>>❌ Cancelado</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Observação (opcional)</label>
                  <textarea name="observacao" class="form-control" rows="3" placeholder="Informe o que foi feito ou o motivo da atualização..."></textarea>
                </div>
                <button type="submit" class="btn btn-success btn-block">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                  Salvar Status
                </button>
              </form>

              <?php if (!in_array($chamado['status'], ['fechado','cancelado'])): ?>
              <div class="divider"></div>
              <div style="display:flex;flex-direction:column;gap:.4rem">
                <form method="POST" action="">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="novo_status" value="fechado">
                  <input type="hidden" name="observacao" value="Chamado finalizado pela equipe de suporte.">
                  <button type="submit" class="btn btn-success btn-block btn-sm"
                          onclick="return confirm('Confirma o fechamento deste chamado?')">
                    ✅ Fechar Chamado (Resolvido)
                  </button>
                </form>
                <form method="POST" action="">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="novo_status" value="cancelado">
                  <input type="hidden" name="observacao" value="Chamado cancelado pela equipe.">
                  <button type="submit" class="btn btn-danger btn-block btn-sm"
                          onclick="return confirm('Confirma o cancelamento deste chamado?')">
                    ❌ Cancelar Chamado
                  </button>
                </form>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Alterar Prioridade -->
          <div class="card">
            <div class="card-header"><span class="card-title">Prioridade</span></div>
            <div class="card-body">
              <form method="POST" action="">
                <input type="hidden" name="action" value="update_priority">
                <div class="form-group">
                  <select name="nova_prioridade" class="form-control">
                    <option value="baixa"   <?= $chamado['prioridade']==='baixa'?'selected':'' ?>>🟢 Baixa (SLA 72h)</option>
                    <option value="media"   <?= $chamado['prioridade']==='media'?'selected':'' ?>>🟡 Média (SLA 24h)</option>
                    <option value="alta"    <?= $chamado['prioridade']==='alta'?'selected':'' ?>>🟠 Alta (SLA 8h)</option>
                    <option value="critica" <?= $chamado['prioridade']==='critica'?'selected':'' ?>>🔴 Crítica (SLA 2h)</option>
                  </select>
                </div>
                <button type="submit" class="btn btn-warning btn-block btn-sm">Atualizar Prioridade</button>
              </form>
            </div>
          </div>

          <!-- Info rápida -->
          <div class="card">
            <div class="card-header"><span class="card-title">Informações</span></div>
            <div class="card-body" style="font-size:.8rem">
              <div style="margin-bottom:.5rem">
                <span class="text-muted">SLA definido:</span><br>
                <strong><?= slaHours($chamado['prioridade']) ?> horas</strong>
              </div>
              <div style="margin-bottom:.5rem">
                <span class="text-muted">Abertura:</span><br>
                <strong><?= date('d/m/Y H:i', strtotime($chamado['criado_em'])) ?></strong>
              </div>
              <?php if ($chamado['fechado_em']): ?>
              <div>
                <span class="text-muted">Fechamento:</span><br>
                <strong><?= date('d/m/Y H:i', strtotime($chamado['fechado_em'])) ?></strong>
              </div>
              <?php endif; ?>
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
const chatMsgs = document.getElementById('chat-msgs');
if (chatMsgs) chatMsgs.scrollTop = chatMsgs.scrollHeight;
</script>
</body>
</html>
