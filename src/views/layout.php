<?php
$flash = getFlash();
$user  = currentUser();
$userInitial = strtoupper(substr($user['nome'] ?? 'A', 0, 1));

$navItems = [
    ['href' => '/dashboard',      'icon' => 'bi-speedometer2',   'label' => 'Dashboard'],
    ['href' => '/campo',          'icon' => 'bi-phone',          'label' => 'Modo Campo (PWA)'],
    ['href' => '/animais',        'icon' => 'bi-heart-fill',     'label' => 'Animais'],
    ['href' => '/pesagens',       'icon' => 'bi-rulers',         'label' => 'Pesagens'],
    ['href' => '/saude',          'icon' => 'bi-heart-pulse',    'label' => 'Saúde'],
    ['href' => '/pastagens',      'icon' => 'bi-tree',           'label' => 'Pastagens'],
    ['href' => '/reproducao',     'icon' => 'bi-diagram-3',      'label' => 'Reprodução'],
    ['href' => '/relatorios',     'icon' => 'bi-bar-chart-line', 'label' => 'Relatórios'],
    ['href' => '/alertas',        'icon' => 'bi-bell',           'label' => 'Alertas'],
    ['href' => '/sincronizacoes', 'icon' => 'bi-arrow-repeat',    'label' => 'Sinc. Mobile'],
];

$db = getDb();
$alertasNaoLidos = $db->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#1a4d2e">
<link rel="manifest" href="/manifest.json">
<title><?= e($pageTitle ?? 'PecuáriaGest') ?> — PecuáriaGest</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🐄</div>
    <h5>PecuáriaGest</h5>
    <small>Sistema de Gestão</small>
  </div>

  <div class="sidebar-nav">
    <div class="nav-section-label">Principal</div>
    <?php foreach ($navItems as $item): ?>
      <?php $active = ($currentPage === ltrim($item['href'], '/')); ?>
      <a href="<?= $item['href'] ?>" class="nav-link <?= $active ? 'active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
        <?php if ($item['href'] === '/alertas' && $alertasNaoLidos > 0): ?>
          <span class="badge bg-danger"><?= $alertasNaoLidos ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <div class="nav-section-label mt-2">Conta</div>
    <a href="/logout" class="nav-link text-danger-emphasis">
      <i class="bi bi-box-arrow-left"></i>
      Sair
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= e($userInitial) ?></div>
      <div class="user-info">
        <strong><?= e($user['nome'] ?? '') ?></strong>
        <small><?= e($user['tipo'] ?? 'admin') ?></small>
      </div>
    </div>
  </div>
</nav>

<!-- Main -->
<div class="main-content">
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="sidebarToggle">
        <i class="bi bi-list"></i>
      </button>
      <h4><?= e($pageTitle ?? '') ?></h4>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if ($alertasNaoLidos > 0): ?>
        <a href="/alertas" class="btn btn-sm btn-outline-danger position-relative">
          <i class="bi bi-bell-fill"></i>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem"><?= $alertasNaoLidos ?></span>
        </a>
      <?php endif; ?>
      <span class="text-muted small d-none d-md-block">Bem-vindo, <?= e($user['nome'] ?? '') ?></span>
    </div>
  </div>

  <div class="page-body">
    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> flash-bar alert-dismissible fade show" role="alert">
        <?= e($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?= $content ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.querySelector('.sidebar').classList.toggle('open');
});
</script>
<?= $scripts ?? '' ?>
</body>
</html>
