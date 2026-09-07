<?php
$search  = $_GET['q'] ?? '';
$statusF = $_GET['status'] ?? '';
$sexoF   = $_GET['sexo'] ?? '';
$racaF   = $_GET['raca'] ?? '';
$pastoF  = (int)($_GET['pasto_id'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = "(a.brinco LIKE ? OR a.nome LIKE ? OR a.raca LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($statusF) { $where[] = "a.status = ?"; $params[] = $statusF; }
if ($sexoF)   { $where[] = "a.sexo = ?";   $params[] = $sexoF; }
if ($racaF)   { $where[] = "a.raca = ?";   $params[] = $racaF; }
if ($pastoF)  { $where[] = "a.pasto_id = ?"; $params[] = $pastoF; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM animais a WHERE $whereStr");
$countStmt->execute($params);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
  SELECT a.*, p.nome as pasto_nome,
    COALESCE(
      (SELECT peso FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1),
      a.peso_inicial
    ) as peso_atual,
    (SELECT data FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1) as data_pesagem
  FROM animais a
  LEFT JOIN pastagens p ON a.pasto_id=p.id
  WHERE $whereStr
  ORDER BY a.created_at DESC
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$animais = $stmt->fetchAll();

$racasAll = $db->query("SELECT DISTINCT raca FROM animais WHERE raca IS NOT NULL ORDER BY raca")->fetchAll(PDO::FETCH_COLUMN);
$pastosAll = $db->query("SELECT id, nome FROM pastagens ORDER BY nome")->fetchAll();

// Resolve pastagem name for filter label
$pastoNome = '';
if ($pastoF) {
    $ps = $db->prepare("SELECT nome FROM pastagens WHERE id=?");
    $ps->execute([$pastoF]);
    $pastoNome = $ps->fetchColumn() ?: '';
}
?>

<!-- Barra de Ações Superior -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0 fw-bold">Inventário do Rebanho</h5>
    <small class="text-muted tabular-nums">
      <?= (int)$total ?> animais listados <?= $pastoNome ? '• Pasto: <strong>' . e($pastoNome) . '</strong>' : '' ?>
    </small>
  </div>
  <div class="d-flex gap-2">
    <a href="/animais/novo" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i> Novo Animal
    </a>
  </div>
</div>

<!-- Filtros Rápidos & Barra de Busca -->
<div class="filter-toolbar">
  <div class="filter-pills">
    <a href="/animais<?= $pastoF ? '?pasto_id='.$pastoF : '' ?>" class="filter-pill <?= empty($statusF) && empty($sexoF) ? 'active' : '' ?>">
      Todos
    </a>
    <a href="?status=ativo<?= $pastoF ? '&pasto_id='.$pastoF : '' ?>" class="filter-pill <?= $statusF === 'ativo' ? 'active' : '' ?>">
      Ativos
    </a>
    <a href="?status=prenha<?= $pastoF ? '&pasto_id='.$pastoF : '' ?>" class="filter-pill <?= $statusF === 'prenha' ? 'active' : '' ?>">
      Prenhas
    </a>
    <a href="?status=doente<?= $pastoF ? '&pasto_id='.$pastoF : '' ?>" class="filter-pill <?= $statusF === 'doente' ? 'active' : '' ?>">
      Em Tratamento
    </a>
    <a href="?sexo=F<?= $pastoF ? '&pasto_id='.$pastoF : '' ?>" class="filter-pill <?= $sexoF === 'F' ? 'active' : '' ?>">
      Fêmeas
    </a>
    <a href="?sexo=M<?= $pastoF ? '&pasto_id='.$pastoF : '' ?>" class="filter-pill <?= $sexoF === 'M' ? 'active' : '' ?>">
      Machos
    </a>
  </div>

  <form method="GET" class="d-flex align-items-center gap-2" style="max-width: 500px; width: 100%;">
    <?php if ($statusF): ?><input type="hidden" name="status" value="<?= e($statusF) ?>"><?php endif; ?>
    <?php if ($sexoF): ?><input type="hidden" name="sexo" value="<?= e($sexoF) ?>"><?php endif; ?>

    <select name="pasto_id" class="form-select form-select-sm" style="max-width: 150px;" onchange="this.form.submit()">
      <option value="">Todos os Pastos</option>
      <?php foreach ($pastosAll as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $pastoF === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar brinco, nome..." value="<?= e($search) ?>" autocomplete="off">
      <button class="btn btn-secondary btn-sm" type="submit">
        <i class="bi bi-search"></i>
      </button>
      <?php if ($search || $statusF || $sexoF || $pastoF): ?>
        <a href="/animais" class="btn btn-outline-secondary btn-sm" title="Limpar Filtros">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Tabela de Alta Densidade -->
<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th style="width: 220px;">Identificação</th>
        <th>Raça / Categoria</th>
        <th>Idade</th>
        <th>Peso Atual <span class="badge bg-light text-secondary border fw-normal cursor-pointer ms-1" onclick="toggleGlobalPesoUnit()" title="Clique para alternar entre kg e arroba (@)" style="font-size:0.68rem; cursor:pointer;"><i class="bi bi-arrow-left-right me-1"></i>kg / @</span></th>
        <th>Localização</th>
        <th>Status</th>
        <th class="text-end" style="width: 120px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($animais)): ?>
        <tr>
          <td colspan="7" class="text-center text-muted py-5">
            <i class="bi bi-search fs-3 d-block mb-2 text-muted"></i>
            Nenhum animal encontrado com os filtros selecionados.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($animais as $a): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($a['foto_url'])): ?>
                <img src="<?= e($a['foto_url']) ?>" alt="Foto" class="table-animal-thumb">
              <?php else: ?>
                <div class="table-animal-avatar">
                  <?= e(substr($a['brinco'], 0, 2)) ?>
                </div>
              <?php endif; ?>
              <div style="min-width: 0;">
                <a href="/animais/<?= $a['id'] ?>" class="fw-bold text-primary text-decoration-none d-block">
                  <?= e($a['brinco']) ?>
                </a>
                <small class="text-muted d-block text-truncate" style="max-width: 140px;">
                  <?= e($a['nome'] ?: 'Sem nome') ?>
                </small>
              </div>
            </div>
          </td>
          <td>
            <div class="fw-600 small"><?= e($a['raca'] ?? 'Nelore') ?></div>
            <small class="text-secondary"><?= sexoLabel($a['sexo']) ?></small>
          </td>
          <td class="small tabular-nums text-secondary">
            <?= calcIdade($a['data_nascimento']) ?>
          </td>
          <td>
            <?php if ($a['peso_atual']): ?>
              <?= renderPesoBadge($a['peso_atual']) ?>
              <br><small class="text-muted tabular-nums" style="font-size:0.72rem;"><?= $a['data_pesagem'] ? formatDate($a['data_pesagem']) : 'Inicial' ?></small>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
          <td class="small text-secondary">
            <?= e($a['pasto_nome'] ?? 'Sem pasto') ?>
          </td>
          <td>
            <?= statusBadge($a['status']) ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="/animais/<?= $a['id'] ?>" class="btn btn-secondary btn-sm" title="Ver Prontuário">
                <i class="bi bi-eye"></i>
              </a>
              <a href="/animais/<?= $a['id'] ?>/editar" class="btn btn-secondary btn-sm" title="Editar">
                <i class="bi bi-pencil"></i>
              </a>
              <button type="button" onclick="confirmDelete(<?= $a['id'] ?>, '<?= e($a['brinco']) ?>')" class="btn btn-secondary btn-sm text-danger" title="Excluir">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Paginação -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3 pt-2">
  <small class="text-muted tabular-nums">Página <?= $page ?> de <?= $totalPages ?></small>
  <nav>
    <ul class="pagination pagination-sm mb-0">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>
<?php endif; ?>

<form id="deleteForm" method="POST" style="display:none">
  <?= csrf_field() ?>
</form>

<?php $scripts = <<<JS
<script>
function confirmDelete(id, brinco) {
  if (confirm('Excluir o animal ' + brinco + '? Esta ação removerá também os registros de pesagem e saúde associados.')) {
    const f = document.getElementById('deleteForm');
    f.action = '/animais/' + id + '/excluir';
    f.submit();
  }
}
</script>
JS;
