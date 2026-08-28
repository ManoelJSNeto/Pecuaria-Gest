<?php
$flash = getFlash();
$user  = currentUser();
$userInitial = strtoupper(substr($user['nome'] ?? 'A', 0, 1));

$navItems = [
    ['href' => '/dashboard',      'icon' => 'bi-speedometer2',   'label' => 'Dashboard',        'perm' => null],
    ['href' => '/animais',        'icon' => 'bi-heart-fill',     'label' => 'Animais',          'perm' => 'ver_animais'],
    ['href' => '/pesagens',       'icon' => 'bi-rulers',         'label' => 'Pesagens',         'perm' => 'ver_pesagens'],
    ['href' => '/saude',          'icon' => 'bi-heart-pulse',    'label' => 'Saúde',            'perm' => 'ver_saude'],
    ['href' => '/pastagens',      'icon' => 'bi-tree',           'label' => 'Pastagens',        'perm' => 'ver_pastagens'],
    ['href' => '/reproducao',     'icon' => 'bi-diagram-3',      'label' => 'Reprodução',       'perm' => 'ver_reproducao'],
    ['href' => '/relatorios',     'icon' => 'bi-bar-chart-line', 'label' => 'Relatórios',       'perm' => 'ver_relatorios'],
    ['href' => '/alertas',        'icon' => 'bi-bell',           'label' => 'Alertas',          'perm' => 'ver_alertas'],
    ['href' => '/sincronizacoes', 'icon' => 'bi-arrow-repeat',    'label' => 'Sinc. Mobile',     'perm' => null],
    ['href' => '/usuarios',       'icon' => 'bi-people-fill',    'label' => 'Equipe & Acessos', 'perm' => 'gerenciar_usuarios'],
    ['href' => '/configuracoes',  'icon' => 'bi-gear-fill',       'label' => 'Configurações',    'perm' => 'gerenciar_configuracoes'],
];

$db = getDb();
$alertasNaoLidos = $db->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#1a4d2e">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="PecuáriaGest">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
<link rel="manifest" href="/manifest.json">
<title><?= e($pageTitle ?? 'PecuáriaGest') ?> — PecuáriaGest</title>
<link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <i class="bi bi-tag-fill text-white fs-4"></i>
    </div>
    <div class="brand-text">
      <h4>PecuáriaGest</h4>
      <small>Gestão Integrada</small>
    </div>
  </div>

  <div class="sidebar-nav">
    <div class="nav-section-label">Principal</div>
    <?php foreach ($navItems as $item): ?>
      <?php 
        if (!empty($item['perm']) && !can($item['perm'])) {
            continue;
        }
        $active = ($currentPage === ltrim($item['href'], '/'));
      ?>
      <a href="<?= $item['href'] ?>" class="nav-link <?= $active ? 'active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
        <?php if ($item['href'] === '/alertas' && $alertasNaoLidos > 0): ?>
          <span class="badge bg-danger"><?= $alertasNaoLidos ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <div class="nav-section-label mt-2">Conta</div>
    <?php if ($user): ?>
      <a href="/logout" class="nav-link text-danger-emphasis">
        <i class="bi bi-box-arrow-left"></i>
        Sair
      </a>
    <?php else: ?>
      <a href="/login" class="nav-link text-success">
        <i class="bi bi-box-arrow-in-right"></i>
        Entrar no Painel
      </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= e($user ? $userInitial : 'C') ?></div>
      <div class="user-info">
        <strong><?= e($user['nome'] ?? 'Operador de Campo') ?></strong>
        <small><?= e($user['cargo'] ?? ($user['tipo'] ?? 'Colaborador')) ?></small>
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
      <?php if ($alertasNaoLidos > 0 && $user): ?>
        <a href="/alertas" class="btn btn-sm btn-outline-danger position-relative">
          <i class="bi bi-bell-fill"></i>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem"><?= $alertasNaoLidos ?></span>
        </a>
      <?php endif; ?>
      <?php if ($user): ?>
        <span class="text-muted small d-none d-md-block">Bem-vindo, <?= e($user['nome'] ?? '') ?></span>
      <?php else: ?>
        <a href="/login" class="btn btn-sm btn-outline-success">
          <i class="bi bi-box-arrow-in-right me-1"></i>Entrar no Painel
        </a>
      <?php endif; ?>
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

<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.querySelector('.sidebar').classList.toggle('open');
});

// Limpa qualquer Service Worker ou Cache antigo no navegador
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(registrations => {
    for (let reg of registrations) reg.unregister();
  });
}
if ('caches' in window) {
  caches.keys().then(keys => {
    for (let k of keys) caches.delete(k);
  });
}
</script>
<?= $scripts ?? '' ?>
</body>
</html>
