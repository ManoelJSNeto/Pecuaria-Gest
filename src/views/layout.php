<?php
$flash = getFlash();
$user  = currentUser();
$userInitial = strtoupper(substr($user['nome'] ?? 'A', 0, 1));

// Distribuição semântica em blocos funcionais
$navSections = [
    'Visão Geral' => [
        ['href' => '/dashboard',      'icon' => 'bi-speedometer2',   'label' => 'Dashboard',        'perm' => null],
        ['href' => '/relatorios',     'icon' => 'bi-bar-chart-line', 'label' => 'Relatórios',       'perm' => 'ver_relatorios'],
    ],
    'Rebanho & Manejo' => [
        ['href' => '/animais',        'icon' => 'bi-tag-fill',       'label' => 'Animais',          'perm' => 'ver_animais'],
        ['href' => '/pesagens',       'icon' => 'bi-rulers',         'label' => 'Pesagens',         'perm' => 'ver_pesagens'],
        ['href' => '/saude',          'icon' => 'bi-heart-pulse',    'label' => 'Saúde & Vacinas',  'perm' => 'ver_saude'],
        ['href' => '/reproducao',     'icon' => 'bi-diagram-3',      'label' => 'Reprodução',       'perm' => 'ver_reproducao'],
    ],
    'Gestão Comercial' => [
        ['href' => '/compras',        'icon' => 'bi-truck',          'label' => 'Compras & Entradas', 'perm' => 'ver_animais'],
        ['href' => '/vendas',         'icon' => 'bi-cash-coin',      'label' => 'Vendas & Saídas',    'perm' => 'ver_animais'],
    ],
    'Campo & Estrutura' => [
        ['href' => '/pastagens',      'icon' => 'bi-tree',           'label' => 'Pastagens',        'perm' => 'ver_pastagens'],
        ['href' => '/sincronizacoes', 'icon' => 'bi-arrow-repeat',    'label' => 'Sinc. Mobile',     'perm' => null],
    ],
    'Administração' => [
        ['href' => '/alertas',        'icon' => 'bi-bell',           'label' => 'Alertas',          'perm' => 'ver_alertas'],
        ['href' => '/usuarios',       'icon' => 'bi-people-fill',    'label' => 'Equipe & Acessos', 'perm' => 'gerenciar_usuarios'],
        ['href' => '/configuracoes',  'icon' => 'bi-gear-fill',       'label' => 'Configurações',    'perm' => 'gerenciar_configuracoes'],
    ]
];

$db = getDb();
$alertasNaoLidos = $db->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#1b3116">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="PecuáriaGest">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
<link rel="manifest" href="/manifest.json">
<title><?= e($pageTitle ?? 'PecuáriaGest') ?> — PecuáriaGest</title>

<!-- Tipografia Técnica: Inter com fallback nativo -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
<script>
  (function() {
    try {
      const u = localStorage.getItem('pecuaria_peso_unit') || (document.cookie.match(/pecuaria_peso_unit=([^;]+)/) || [])[1];
      if (u === 'arroba') {
        document.documentElement.classList.add('mode-arroba');
      }
    } catch(e) {}
  })();
</script>
</head>
<body class="<?= (($_COOKIE['pecuaria_peso_unit'] ?? '') === 'arroba') ? 'mode-arroba' : '' ?>">

<!-- Sidebar com Rolagem Autônoma e Seções Semânticas -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <img src="/favicon.svg" alt="PecuáriaGest Logo" class="brand-logo">
    <div class="brand-text">
      <h4>PecuáriaGest</h4>
      <small>Gestão de Precisão</small>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($navSections as $sectionTitle => $items): ?>
      <?php
        // Filtra itens por permissão
        $visibleItems = array_filter($items, function($it) {
            return empty($it['perm']) || can($it['perm']);
        });
        if (empty($visibleItems)) continue;
      ?>
      <div class="nav-section-label"><?= e($sectionTitle) ?></div>
      <?php foreach ($visibleItems as $item): ?>
        <?php $active = ($currentPage === ltrim($item['href'], '/')); ?>
        <a href="<?= $item['href'] ?>" class="nav-link <?= $active ? 'active' : '' ?>">
          <i class="bi <?= $item['icon'] ?>"></i>
          <span><?= $item['label'] ?></span>
          <?php if ($item['href'] === '/alertas' && $alertasNaoLidos > 0): ?>
            <span class="badge bg-danger"><?= $alertasNaoLidos ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="nav-section-label mt-2">Sessão</div>
    <?php if ($user): ?>
      <a href="/logout" class="nav-link text-danger" style="opacity: 0.9;">
        <i class="bi bi-box-arrow-left text-danger"></i>
        <span>Encerrar Sessão</span>
      </a>
    <?php else: ?>
      <a href="/login" class="nav-link text-success">
        <i class="bi bi-box-arrow-in-right text-success"></i>
        <span>Entrar no Painel</span>
      </a>
    <?php endif; ?>
  </nav>

  <!-- Rodapé da Sidebar: Usuário ativo sempre alcançável -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= e($user ? $userInitial : 'C') ?></div>
      <div class="user-info">
        <strong><?= e($user['nome'] ?? 'Operador de Campo') ?></strong>
        <small><?= e($user['cargo'] ?? ($user['tipo'] ?? 'Colaborador')) ?></small>
      </div>
    </div>
  </div>
</aside>

<!-- Área de Conteúdo Principal -->
<main class="main-content">
  <header class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-secondary d-md-none" id="sidebarToggle" aria-label="Abrir Menu">
        <i class="bi bi-list fs-5"></i>
      </button>
      <h4><?= e($pageTitle ?? '') ?></h4>
    </div>
    <div class="d-flex align-items-center gap-3">
      <!-- Seletor Rápido de Unidade de Peso: kg ⟷ @ (Arroba) -->
      <div class="unit-toggle-container" title="Alternar unidade de exibição de peso no sistema">
        <span class="unit-toggle-label d-none d-sm-inline"><i class="bi bi-sliders me-1"></i>Peso:</span>
        <button type="button" class="btn-unit-opt active" id="btnTopUnitKg" onclick="setGlobalPesoUnit('kg')" title="Exibir pesos em Quilogramas">kg</button>
        <button type="button" class="btn-unit-opt" id="btnTopUnitArr" onclick="setGlobalPesoUnit('arroba')" title="Exibir pesos em Arrobas (@ comercial - 50% carcaça)">@ Arroba</button>
      </div>

      <?php if ($alertasNaoLidos > 0 && $user): ?>
        <a href="/alertas" class="btn btn-sm btn-outline-danger position-relative" title="Alertas Pendentes">
          <i class="bi bi-bell-fill"></i>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem"><?= $alertasNaoLidos ?></span>
        </a>
      <?php endif; ?>
      <?php if ($user): ?>
        <span class="text-secondary small d-none d-md-block fw-500">Fazenda Modelo</span>
      <?php else: ?>
        <a href="/login" class="btn btn-sm btn-primary">
          <i class="bi bi-box-arrow-in-right me-1"></i>Entrar no Painel
        </a>
      <?php endif; ?>
    </div>
  </header>

  <section class="page-body">
    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert" style="border-radius: var(--radius-sm);">
        <i class="bi bi-info-circle-fill me-2"></i><?= e($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?= $content ?>
  </section>
</main>

<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.querySelector('.sidebar').classList.toggle('open');
});

// ── Gestão Global de Unidade de Peso (kg ⟷ Arrobas) ──
function getGlobalPesoUnit() {
  return localStorage.getItem('pecuaria_peso_unit') || 'kg';
}

function setGlobalPesoUnit(unit) {
  const isArroba = (unit === 'arroba');
  localStorage.setItem('pecuaria_peso_unit', isArroba ? 'arroba' : 'kg');
  document.cookie = 'pecuaria_peso_unit=' + (isArroba ? 'arroba' : 'kg') + ';path=/;max-age=31536000';
  
  if (isArroba) {
    document.body.classList.add('mode-arroba');
    document.documentElement.classList.add('mode-arroba');
  } else {
    document.body.classList.remove('mode-arroba');
    document.documentElement.classList.remove('mode-arroba');
  }

  // Atualiza os botões do seletor
  const btnKg = document.getElementById('btnTopUnitKg');
  const btnArr = document.getElementById('btnTopUnitArr');
  if (btnKg && btnArr) {
    btnKg.classList.toggle('active', !isArroba);
    btnArr.classList.toggle('active', isArroba);
  }
}

function toggleGlobalPesoUnit() {
  const current = getGlobalPesoUnit();
  setGlobalPesoUnit(current === 'arroba' ? 'kg' : 'arroba');
}

// Inicializa botões no carregamento
document.addEventListener('DOMContentLoaded', () => {
  const unit = getGlobalPesoUnit();
  setGlobalPesoUnit(unit);
});
</script>
<?= $scripts ?? '' ?>
</body>
</html>
