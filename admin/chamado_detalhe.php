<?php
/**
 * ERP Condomínio – Detalhe do Chamado (Admin)
 * Auditoria Sênior V10: Correção Definitiva de Gravação
 */
require_once '../includes/config.php';
requireAdminLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: chamados.php');
    exit;
}

// Buscar chamado inicial
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

$successMsg = '';
$errorMsg   = '';

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $agora  = date('Y-m-d H:i:s');

    try {
        // 1. Atualizar status (Finalização)
        if ($action === 'update_status') {
            $novoStatus = $_POST['novo_status'] ?? '';
            $obs        = trim($_POST['observacao'] ?? '');
            $statusValidos = ['aberto','em_andamento','aguardando','fechado','cancelado'];

            if (in_array($novoStatus, $statusValidos)) {
                $fechadoEm = in_array($novoStatus, ['fechado','cancelado']) ? $agora : null;

                // QUERY V10: Todas as colunas obrigatórias enviadas explicitamente
                $sqlStatus = "UPDATE chamados SET status = ?, fechado_em = ?, atualizado_em = ? WHERE id = ?";
                $resStatus = $db->prepare($sqlStatus)->execute([$novoStatus, $fechadoEm, $agora, $id]);
                
                if ($resStatus) {
                    // Registrar no histórico
                    $db->prepare('
                        INSERT INTO historico_status (chamado_id, status_de, status_para, observacao, autor, criado_em)
                        VALUES (?, ?, ?, ?, "Equipe de Suporte", ?)
                    ')->execute([$id, $chamado['status'], $novoStatus, $obs, $agora]);

                    // Mensagem automática para o cliente no chat
                    $statusLabels = [
                        'aberto' => 'Aberto',
                        'em_andamento' => 'Em Andamento',
                        'aguardando' => 'Aguardando Retorno',
                        'fechado' => 'Fechado / Resolvido',
                        'cancelado' => 'Cancelado'
                    ];
                    
                    $msgAuto = "📢 **Atualização de Status**: " . $statusLabels[$novoStatus];
                    if ($obs) $msgAuto .= "\n\n**Observação**: " . $obs;
                    if ($novoStatus === 'fechado') $msgAuto .= "\n\n✅ Chamado finalizado com sucesso!";

                    $db->prepare('INSERT INTO mensagens (chamado_id, autor, nome, mensagem, criado_em) VALUES (?, "admin", "Equipe de Suporte", ?, ?)')
                       ->execute([$id, $msgAuto, $agora]);

                    $successMsg = 'Chamado atualizado com sucesso!';
                    $chamado['status'] = $novoStatus;
                }
            }
        }

        // 2. Enviar mensagem avulsa
        if ($action === 'send_msg') {
            $mensagem = trim($_POST['mensagem'] ?? '');
            if (!empty($mensagem)) {
                $db->prepare('INSERT INTO mensagens (chamado_id, autor, nome, mensagem, criado_em) VALUES (?, "admin", "Equipe de Suporte", ?, ?)')
                   ->execute([$id, $mensagem, $agora]);
                $db->prepare('UPDATE chamados SET atualizado_em = ? WHERE id = ?')->execute([$agora, $id]);
                $successMsg = 'Mensagem enviada com sucesso!';
            }
        }

        // 3. Atualizar prioridade
        if ($action === 'update_priority') {
            $novaPrio = $_POST['nova_prioridade'] ?? '';
            $priosValidas = ['baixa','media','alta','critica'];
            if (in_array($novaPrio, $priosValidas)) {
                $db->prepare('UPDATE chamados SET prioridade = ?, atualizado_em = ? WHERE id = ?')
                   ->execute([$novaPrio, $agora, $id]);
                $chamado['prioridade'] = $novaPrio;
                $successMsg = 'Prioridade atualizada com sucesso!';
            }
        }
    } catch (PDOException $e) {
        $errorMsg = 'Erro interno: ' . $e->getMessage();
        sys_log("EXCEÇÃO PDO CHAMADO DETALHE: " . $e->getMessage(), 'CRITICAL');
    }
}

// Recarregar dados do chamado após POST
$stmt->execute([$id]);
$chamado = $stmt->fetch();

// Buscar mensagens e histórico para exibição
$mensagens = $db->prepare('SELECT * FROM mensagens WHERE chamado_id = ? ORDER BY criado_em ASC');
$mensagens->execute([$id]);
$listaMensagens = $mensagens->fetchAll();

$historico = $db->prepare('SELECT * FROM historico_status WHERE chamado_id = ? ORDER BY criado_em DESC');
$historico->execute([$id]);
$listaHistorico = $historico->fetchAll();

$sla = slaStatus($chamado['criado_em'], $chamado['prioridade'], $chamado['status']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chamado <?= sanitize($chamado['numero']) ?> – Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include 'partials/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'partials/topbar.php'; ?>

    <div class="page-content">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
        <a href="chamados.php" class="btn btn-outline btn-sm">Voltar</a>
        <span class="ticket-number" style="font-size:.9rem"><?= sanitize($chamado['numero']) ?></span>
        <?= priorityBadge($chamado['prioridade']) ?>
        <?= statusBadge($chamado['status']) ?>
      </div>

      <?php if ($successMsg): ?><div class="alert alert-success">✅ <?= sanitize($successMsg) ?></div><?php endif; ?>
      <?php if ($errorMsg): ?><div class="alert alert-danger">❌ <?= sanitize($errorMsg) ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

        <!-- Coluna principal -->
        <div style="display:flex;flex-direction:column;gap:1.5rem">

          <!-- Info do chamado -->
          <div class="card">
            <div class="card-header"><span class="card-title"><?= sanitize($chamado['assunto']) ?></span></div>
            <div class="card-body">
              <div class="ticket-info-grid">
                <div class="ticket-info-item"><label>Número</label><span class="ticket-number"><?= sanitize($chamado['numero']) ?></span></div>
                <div class="ticket-info-item"><label>Cliente</label><span><?= sanitize($chamado['razao_social']) ?></span></div>
                <div class="ticket-info-item"><label>CNPJ</label><span><?= sanitize($chamado['cnpj']) ?></span></div>
                <div class="ticket-info-item"><label>E-mail</label><span><?= sanitize($chamado['email']) ?></span></div>
                <div class="ticket-info-item"><label>Telefone</label><span><?= sanitize($chamado['telefone'] ?: 'Não informado') ?></span></div>
                <div class="ticket-info-item"><label>Aberto em</label><span><?= date('d/m/Y H:i', strtotime($chamado['criado_em'])) ?></span></div>
                <div class="ticket-info-item"><label>Atualização</label><span><?= timeAgo($chamado['atualizado_em']) ?></span></div>
                <div class="ticket-info-item">
                  <label>SLA</label>
                  <div class="sla-bar-wrap <?= $sla['class'] ?>" style="min-width:160px">
                    <div class="sla-bar-bg"><div class="sla-bar-fill" style="width:<?= $sla['percent'] ?>%"></div></div>
                    <div class="sla-label"><?= $sla['label'] ?></div>
                  </div>
                </div>
              </div>

              <div class="divider"></div>

              <div style="font-weight:600;margin-bottom:.5rem">Descrição do Cliente</div>
              <div style="background:var(--bg);border-radius:var(--radius);padding:1rem;font-size:.9rem;line-height:1.7;white-space:pre-wrap"><?= sanitize($chamado['descricao']) ?></div>

              <?php if ($chamado['anexo']): ?>
              <div style="margin-top:1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                <span style="font-weight:600; display: block; margin-bottom: 0.5rem;">📎 Arquivo Anexo:</span>
                <a href="<?= sanitize('../' . UPLOAD_URL . $chamado['anexo']) ?>" target="_blank" class="btn btn-outline btn-sm">Baixar / Visualizar Arquivo</a>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Chat -->
          <div class="card">
            <div class="card-header"><span class="card-title">Mensagens do Atendimento</span></div>
            <div class="chat-messages" id="chat-msgs" style="max-height:450px;overflow-y:auto;padding:1.5rem; background: #f8fafc;">
              <?php if (empty($listaMensagens)): ?>
                <div class="empty-state"><p>Nenhuma mensagem trocada ainda.</p></div>
              <?php else: ?>
                <?php foreach ($listaMensagens as $msg): ?>
                <div class="msg-bubble <?= $msg['autor'] ?>">
                  <div style="white-space: pre-wrap;"><?= nl2br(sanitize($msg['mensagem'])) ?></div>
                  <div class="msg-meta"><?= sanitize($msg['nome']) ?> · <?= date('d/m H:i', strtotime($msg['criado_em'])) ?></div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <form method="POST" action="" class="chat-input-area" style="padding: 1rem; border-top: 1px solid #e2e8f0;">
              <input type="hidden" name="action" value="send_msg">
              <textarea name="mensagem" class="form-control" placeholder="Digite sua resposta para o cliente..." rows="3" required style="resize: none;"></textarea>
              <button type="submit" class="btn btn-primary" style="padding: 0 1.5rem;">Enviar Resposta</button>
            </form>
          </div>

          <!-- Histórico -->
          <?php if (!empty($listaHistorico)): ?>
          <div class="card">
            <div class="card-header"><span class="card-title">Linha do Tempo de Status</span></div>
            <div class="card-body">
              <div class="history-list">
                <?php foreach ($listaHistorico as $h): ?>
                <div class="history-item">
                  <div class="history-dot"></div>
                  <div>
                    <strong><?= sanitize($h['autor']) ?></strong> alterou para <strong><?= sanitize($h['status_para']) ?></strong>
                    <?php if ($h['observacao']): ?><br><span class="text-muted italic">"<?= sanitize($h['observacao']) ?>"</span><?php endif; ?>
                    <div class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Coluna lateral -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;position:sticky;top:80px">

          <!-- Ações de Status -->
          <div class="card" style="border-top: 4px solid #16a34a;">
            <div class="card-header"><span class="card-title">Gerenciar Status</span></div>
            <div class="card-body">
              <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <div class="form-group">
                  <label class="form-label">Alterar para:</label>
                  <select name="novo_status" class="form-control" required>
                    <option value="aberto"       <?= $chamado['status']==='aberto'?'selected':'' ?>>🔵 Aberto</option>
                    <option value="em_andamento" <?= $chamado['status']==='em_andamento'?'selected':'' ?>>🟡 Em Andamento</option>
                    <option value="aguardando"   <?= $chamado['status']==='aguardando'?'selected':'' ?>>🟠 Aguardando Cliente</option>
                    <option value="fechado"      <?= $chamado['status']==='fechado'?'selected':'' ?>>✅ Fechado / Resolvido</option>
                    <option value="cancelado"    <?= $chamado['status']==='cancelado'?'selected':'' ?>>❌ Cancelado</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Nota de Encerramento/Status</label>
                  <textarea name="observacao" class="form-control" rows="3" placeholder="Descreva a solução ou motivo..."></textarea>
                </div>
                <button type="submit" class="btn btn-success btn-block" style="height: 45px; font-weight: 600;">Salvar Alteração</button>
              </form>
            </div>
          </div>

          <!-- Prioridade -->
          <div class="card">
            <div class="card-header"><span class="card-title">Ajustar Prioridade</span></div>
            <div class="card-body">
              <form method="POST" action="">
                <input type="hidden" name="action" value="update_priority">
                <div class="form-group">
                  <select name="nova_prioridade" class="form-control">
                    <option value="baixa"   <?= $chamado['prioridade']==='baixa'?'selected':'' ?>>🟢 Baixa</option>
                    <option value="media"   <?= $chamado['prioridade']==='media'?'selected':'' ?>>🟡 Média</option>
                    <option value="alta"    <?= $chamado['prioridade']==='alta'?'selected':'' ?>>🟠 Alta</option>
                    <option value="critica" <?= $chamado['prioridade']==='critica'?'selected':'' ?>>🔴 Crítica</option>
                  </select>
                </div>
                <button type="submit" class="btn btn-warning btn-block btn-sm">Atualizar Prioridade</button>
              </form>
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
