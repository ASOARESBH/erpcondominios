<?php
/**
 * ERP Condomínio - Ação de Abrir Chamado
 * Auditoria Sênior V10: Correção Definitiva de Gravação
 */
require_once '../includes/config.php';
requireClientLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    header("Location: ../painel.php");
    exit;
}

$db         = getDB();
$clientId   = $_SESSION['cliente_id'];
$clientName = $_SESSION['cliente_nome'];
$assunto    = trim($_POST['assunto']    ?? '');
$descricao  = trim($_POST['descricao']  ?? '');
$prioridade = trim($_POST['prioridade'] ?? 'media');
$agora      = date('Y-m-d H:i:s');

if (empty($assunto) || empty($descricao)) {
    header('Location: ../painel.php?tab=chamados&erro=campos_obrigatorios');
    exit;
}

// Gerar número único do chamado
$stmtCount = $db->query('SELECT COUNT(*) FROM chamados');
$count = $stmtCount->fetchColumn();
$numero = 'CHM' . date('Y') . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

// Upload do anexo
$anexoNome = null;
if (!empty($_FILES['anexo']['name'])) {
    $file = $_FILES['anexo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $anexoNome = uniqid('anexo_', true) . '.' . $ext;
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $anexoNome);
}

try {
    // QUERY V10: Todas as colunas obrigatórias enviadas explicitamente
    $sql = "INSERT INTO chamados (numero, cliente_id, assunto, descricao, prioridade, status, anexo, criado_em, atualizado_em) 
            VALUES (?, ?, ?, ?, ?, 'aberto', ?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $numero,
        $clientId,
        $assunto,
        $descricao,
        $prioridade,
        $anexoNome,
        $agora,
        $agora
    ]);

    $chamadoId = $db->lastInsertId();

    // Histórico Status
    $db->prepare('INSERT INTO historico_status (chamado_id, status_de, status_para, observacao, autor, criado_em) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$chamadoId, 'novo', 'aberto', 'Chamado aberto pelo cliente', $clientName, $agora]);

    // Mensagem Boas-vindas
    $slaH = slaHours($prioridade);
    $msgBoasVindas = "Olá! Seu chamado {$numero} foi registrado com sucesso.\n\nPrioridade: " . strtoupper($prioridade) . "\nSLA de retorno: {$slaH} horas\n\nNossa equipe irá analisar e retornar em breve.";
    
    $db->prepare('INSERT INTO mensagens (chamado_id, autor, nome, mensagem, criado_em) VALUES (?, "admin", "Sistema ERP", ?, ?)')
       ->execute([$chamadoId, $msgBoasVindas, $agora]);

    header("Location: ../painel.php?tab=chamados&ticket={$chamadoId}&novo=1");
    exit;

} catch (PDOException $e) {
    sys_log("ERRO ABRIR CHAMADO: " . $e->getMessage(), 'CRITICAL');
    header('Location: ../painel.php?tab=chamados&erro=banco');
    exit;
}
