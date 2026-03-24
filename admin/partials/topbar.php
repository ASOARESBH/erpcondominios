<?php
$pageTitles = [
    'index.php'           => 'Dashboard',
    'chamados.php'        => 'Chamados',
    'chamado_detalhe.php' => 'Detalhe do Chamado',
    'clientes.php'        => 'Clientes',
];
$currentPage  = basename($_SERVER['PHP_SELF']);
$pageTitle    = $pageTitles[$currentPage] ?? 'Admin';
?>
<header class="topbar">
  <div style="display:flex;align-items:center;gap:.75rem">
    <button class="mobile-toggle" onclick="toggleSidebar()">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <span class="topbar-title"><?= $pageTitle ?></span>
  </div>
  <div class="topbar-right">
    <div class="avatar" style="background:#16a34a;width:32px;height:32px;font-size:.7rem">AD</div>
    <span style="font-size:.85rem;font-weight:600;color:var(--text-muted)">Admin</span>
  </div>
</header>
