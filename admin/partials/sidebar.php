<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar admin-sidebar" id="admin-sidebar">
  <div class="sidebar-logo">
    <img src="../assets/logo.png" alt="ERP Condomínio">
    <div style="font-size:.65rem;color:#64748b;margin-top:.25rem;text-transform:uppercase;letter-spacing:.08em">Painel Admin</div>
  </div>

  <div class="sidebar-user">
    <div style="display:flex;align-items:center;gap:.6rem">
      <div class="avatar" style="background:#16a34a">AD</div>
      <div>
        <div class="sidebar-user-name">Administrador</div>
        <div class="sidebar-user-cnpj"><?= sanitize($_SESSION['admin_user'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Painel</div>

    <a href="index.php" class="nav-item <?= $currentPage==='index.php'?'active':'' ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
      </svg>
      Dashboard
    </a>

    <div class="nav-section-title" style="margin-top:.5rem">Chamados</div>

    <a href="chamados.php" class="nav-item <?= $currentPage==='chamados.php'?'active':'' ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
      </svg>
      Todos os Chamados
    </a>

    <a href="chamados.php?status=aberto" class="nav-item">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
      </svg>
      Abertos
    </a>

    <a href="chamados.php?status=em_andamento" class="nav-item">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-4.3"/>
      </svg>
      Em Andamento
    </a>

    <div class="nav-section-title" style="margin-top:.5rem">Clientes</div>

    <a href="clientes.php" class="nav-item <?= $currentPage==='clientes.php'?'active':'' ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
      Clientes
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="../index.php" class="nav-item" style="color:#94a3b8" target="_blank">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
        <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
      </svg>
      Portal do Cliente
    </a>
    <a href="logout.php" class="nav-item" style="color:#f87171">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Sair
    </a>
  </div>
</aside>
