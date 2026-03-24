<?php
require_once '../includes/config.php';
requireClientLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../painel.php');
    exit;
}

$chamadoId = (int)($_POST['chamado_id'] ?? 0);
$mensagem  = trim($_POST['mensagem'] ?? '');
$redirect  = $_POST['redirect'] ?? '../painel.php?tab=chat';

if (empty($mensagem) || $chamadoId <= 0) {
    header('Location: ' . $redirect);
    exit;
}

$db = getDB();

// Verificar se o chamado pertence ao cliente
$stmt = $db->prepare('SELECT id, status FROM chamados WHERE id = ? AND cliente_id = ?');
$stmt->execute([$chamadoId, $_SESSION['client_id']]);
$chamado = $stmt->fetch();

if (!$chamado || in_array($chamado['status'], ['fechado','cancelado'])) {
    header('Location: ' . $redirect);
    exit;
}

$db->prepare('
    INSERT INTO mensagens (chamado_id, autor, nome, mensagem)
    VALUES (?, "cliente", ?, ?)
')->execute([$chamadoId, $_SESSION['client_name'], $mensagem]);

// Atualizar timestamp do chamado
$db->prepare('UPDATE chamados SET atualizado_em = NOW() WHERE id = ?')->execute([$chamadoId]);

header('Location: ' . $redirect);
exit;
