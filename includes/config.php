<?php
/**
 * ERP Condomínio - Sistema de Chamados
 * Configuração Central e Auditoria Sênior
 */

// 1. Configurações de Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'inlaud99_erpcondo');
define('DB_PASS', 'Admin259087'); // Ajustado conforme histórico
define('DB_NAME', 'inlaud99_erpcondo');
define('DB_CHARSET', 'utf8mb4');

// 2. Credenciais Admin
define('ADMIN_USER', 'inlaud99_admin');
define('ADMIN_PASS', 'Admin259087');

// 3. Configurações de Ambiente
define('SITE_NAME', 'ERP Condomínio - Suporte');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// 4. Sistema de Log Centralizado (Sênior)
function sys_log($message, $type = 'INFO') {
    $log_file = dirname(__DIR__) . '/sistema_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $log_entry = "[$timestamp] [$type] [IP: $ip] $message" . PHP_EOL;
    
    if (!file_exists($log_file)) {
        @touch($log_file);
        @chmod($log_file, 0666);
    }
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// 5. Conexão PDO com Tratamento de Exceções
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            sys_log("FALHA CRÍTICA DE CONEXÃO: " . $e->getMessage(), 'ERROR');
            die('Erro de conexão com o banco de dados. Verifique o log do sistema.');
        }
    }
    return $pdo;
}

// 6. Iniciar Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 7. Funções de Segurança e Login
function isClientLoggedIn(): bool {
    return isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']);
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_user']) && !empty($_SESSION['admin_user']);
}

function requireClientLogin(): void {
    if (!isClientLoggedIn()) {
        $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        header("Location: $base/index.php");
        exit;
    }
}

function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        header("Location: $base/login.php");
        exit;
    }
}

function sanitize($str) {
    if ($str === null) return '';
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// 8. Auxiliares de Negócio
function formatCNPJ($cnpj) {
    $cnpj = preg_replace('/\D/', '', $cnpj);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}

function slaHours($priority) {
    return match ($priority) {
        'critica' => 2,
        'alta'    => 8,
        'media'   => 24,
        default   => 72,
    };
}

function slaStatus($createdAt, $priority, $status) {
    if (in_array($status, ['fechado', 'cancelado'])) {
        return ['label' => 'Concluído', 'class' => 'sla-ok', 'percent' => 100];
    }
    $slaH = slaHours($priority);
    $created = new DateTime($createdAt);
    $now = new DateTime();
    $deadline = (clone $created)->modify("+{$slaH} hours");
    $total = $deadline->getTimestamp() - $created->getTimestamp();
    $elapsed = $now->getTimestamp() - $created->getTimestamp();
    $percent = min(100, round(($elapsed / $total) * 100));
    $remaining = $deadline->getTimestamp() - $now->getTimestamp();

    if ($remaining <= 0) return ['label' => 'Vencido', 'class' => 'sla-danger', 'percent' => 100];
    $h = floor($remaining / 3600);
    return ['label' => "{$h}h restantes", 'class' => ($percent >= 80 ? 'sla-warning' : 'sla-ok'), 'percent' => $percent];
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'agora';
    if ($diff < 3600) return floor($diff/60) . 'm atrás';
    if ($diff < 86400) return floor($diff/3600) . 'h atrás';
    return floor($diff/86400) . 'd atrás';
}

function statusBadge($status) {
    $map = [
        'aberto' => ['Aberto', 'badge-aberto'],
        'em_andamento' => ['Em Andamento', 'badge-andamento'],
        'aguardando' => ['Aguardando', 'badge-aguardando'],
        'fechado' => ['Fechado', 'badge-fechado'],
        'cancelado' => ['Cancelado', 'badge-cancelado']
    ];
    $i = $map[$status] ?? ['-', ''];
    return "<span class='badge {$i[1]}'>{$i[0]}</span>";
}

function priorityBadge($priority) {
    $map = [
        'baixa' => ['Baixa', 'pri-baixa'],
        'media' => ['Média', 'pri-media'],
        'alta' => ['Alta', 'pri-alta'],
        'critica' => ['Crítica', 'pri-critica']
    ];
    $i = $map[$priority] ?? ['-', ''];
    return "<span class='badge {$i[1]}'>{$i[0]}</span>";
}
