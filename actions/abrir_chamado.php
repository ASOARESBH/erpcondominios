<?php
require_once '../includes/config.php';
requireClientLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../painel.php');
    exit;
}

$assunto    = trim($_POST['assunto']    ?? '');
$descricao  = trim($_POST['descricao']  ?? '');
$prioridade = trim($_POST['prioridade'] ?? 'media');

if (empty($assunto) || empty($descricao)) {
    header('Location: ../painel.php?tab=chamados&erro=campos_obrigatorios');
    exit;
}

$prioridades = ['baixa','media','alta','critica'];
if (!in_array($prioridade, $prioridades)) $prioridade = 'media';

// Gerar número único do chamado: CHM + ano + sequencial 5 dígitos
$db = getDB();
$count = $db->query('SELECT COUNT(*) FROM chamados')->fetchColumn();
$numero = 'CHM' . date('Y') . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

// Upload do anexo
$anexoNome = null;
if (!empty($_FILES['anexo']['name'])) {
    $file     = $_FILES['anexo'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['pdf','png','jpg','jpeg','gif','docx','xlsx','txt','zip'];
    $maxSize  = MAX_FILE_SIZE;

    if (!in_array($ext, $allowed)) {
        header('Location: ../painel.php?tab=chamados&erro=tipo_arquivo');
        exit;
    }
    if ($file['size'] > $maxSize) {
        header('Location: ../painel.php?tab=chamados&erro=tamanho_arquivo');
        exit;
    }

    $anexoNome = uniqid('anexo_', true) . '.' . $ext;
    $destino   = UPLOAD_DIR . $anexoNome;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        $anexoNome = null; // Falha silenciosa no upload
    }
}

$stmt = $db->prepare('
    INSERT INTO chamados (numero, cliente_id, assunto, descricao, prioridade, status, anexo)
    VALUES (?, ?, ?, ?, ?, "aberto", ?)
');
$stmt->execute([
    $numero,
    $_SESSION['client_id'],
    $assunto,
    $descricao,
    $prioridade,
    $anexoNome
]);

$chamadoId = $db->lastInsertId();

// Registrar no histórico
$db->prepare('
    INSERT INTO historico_status (chamado_id, status_de, status_para, observacao, autor)
    VALUES (?, "novo", "aberto", "Chamado aberto pelo cliente", ?)
')->execute([$chamadoId, $_SESSION['client_name']]);

// Mensagem automática de boas-vindas
$slaH = slaHours($prioridade);
$db->prepare('
    INSERT INTO mensagens (chamado_id, autor, nome, mensagem)
    VALUES (?, "admin", "Sistema ERP Condomínio", ?)
')->execute([
    $chamadoId,
    "Olá! Seu chamado {$numero} foi registrado com sucesso.\n\nPrioridade: " . strtoupper($prioridade) . "\nSLA de retorno: {$slaH} horas\n\nNossa equipe irá analisar e retornar em breve. Você pode acompanhar o status e enviar mensagens por aqui."
]);

header("Location: ../painel.php?tab=chamados&ticket={$chamadoId}&novo=1");
exit;
