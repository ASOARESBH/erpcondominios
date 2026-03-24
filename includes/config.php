<?php
// ============================================================
//  ERP Condomínio - Sistema de Chamados
//  Configuração do Banco de Dados
//  Altere as credenciais abaixo para o seu servidor HostGator
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'inlaud99_erpcondo');
define('DB_PASS', 'inlaud99_admin');
define('DB_NAME', 'inlaud99_erpcondo');
define('DB_CHARSET', 'utf8mb4');

// Credenciais do administrador do sistema
define('ADMIN_USER', 'inlaud99_admin');
define('ADMIN_PASS', 'Admin259087');

// Configurações gerais
define('SITE_NAME', 'ERP Condomínio - Suporte');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// SLA em horas por prioridade
define('SLA_BAIXA',   72);
define('SLA_MEDIA',   24);
define('SLA_ALTA',     8);
define('SLA_CRITICA',  2);

// Conexão PDO
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// Iniciar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funções auxiliares
function isClientLoggedIn(): bool {
    return isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function requireClientLogin(): void {
    if (!isClientLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: ../admin/login.php');
        exit;
    }
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function formatCNPJ(string $cnpj): string {
    $cnpj = preg_replace('/\D/', '', $cnpj);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}

function slaHours(string $priority): int {
    return match ($priority) {
        'critica' => SLA_CRITICA,
        'alta'    => SLA_ALTA,
        'media'   => SLA_MEDIA,
        default   => SLA_BAIXA,
    };
}

function slaStatus(string $createdAt, string $priority, string $status): array {
    if (in_array($status, ['fechado', 'cancelado'])) {
        return ['label' => 'Concluído', 'class' => 'sla-ok', 'percent' => 100];
    }
    $slaHours = slaHours($priority);
    $created  = new DateTime($createdAt);
    $now      = new DateTime();
    $deadline = (clone $created)->modify("+{$slaHours} hours");
    $elapsed  = $now->getTimestamp() - $created->getTimestamp();
    $total    = $deadline->getTimestamp() - $created->getTimestamp();
    $percent  = min(100, round(($elapsed / $total) * 100));
    $remaining = $deadline->getTimestamp() - $now->getTimestamp();

    if ($remaining <= 0) {
        return ['label' => 'SLA Vencido', 'class' => 'sla-danger', 'percent' => 100, 'remaining' => 'Vencido'];
    }
    $hours = floor($remaining / 3600);
    $mins  = floor(($remaining % 3600) / 60);
    $label = $hours > 0 ? "{$hours}h {$mins}min restantes" : "{$mins}min restantes";
    $class = $percent >= 80 ? 'sla-warning' : 'sla-ok';
    return ['label' => $label, 'class' => $class, 'percent' => $percent];
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'agora mesmo';
    if ($diff < 3600)  return floor($diff/60) . ' min atrás';
    if ($diff < 86400) return floor($diff/3600) . 'h atrás';
    return floor($diff/86400) . ' dias atrás';
}

function statusBadge(string $status): string {
    $map = [
        'aberto'      => ['Aberto',       'badge-aberto'],
        'em_andamento'=> ['Em Andamento',  'badge-andamento'],
        'aguardando'  => ['Aguardando',    'badge-aguardando'],
        'fechado'     => ['Fechado',       'badge-fechado'],
        'cancelado'   => ['Cancelado',     'badge-cancelado'],
    ];
    $info = $map[$status] ?? ['Desconhecido', 'badge-default'];
    return "<span class=\"badge {$info[1]}\">{$info[0]}</span>";
}

function priorityBadge(string $priority): string {
    $map = [
        'baixa'   => ['Baixa',   'pri-baixa'],
        'media'   => ['Média',   'pri-media'],
        'alta'    => ['Alta',    'pri-alta'],
        'critica' => ['Crítica', 'pri-critica'],
    ];
    $info = $map[$priority] ?? ['Normal', 'pri-baixa'];
    return "<span class=\"badge {$info[1]}\">{$info[0]}</span>";
}
