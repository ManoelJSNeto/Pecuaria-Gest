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
    (SELECT data  FROM pesagens WHERE animal_id=a.id ORDER BY data DESC, id DESC LIMIT 1) as data_pesagem
  FROM animais a
  LEFT JOIN pastagens p ON a.pasto_id=p.id
  WHERE $whereStr
  ORDER BY a.created_at DESC
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$animais = $stmt->fetchAll();

$racasAll = $db->query("SELECT DISTINCT raca FROM animais WHERE raca IS NOT NULL ORDER BY raca")->fetchAll(PDO::FETCH_COLUMN);

// Resolve pastagem name for filter label
$pastoNome = '';
if ($pastoF) {
    $ps = $db->prepare("SELECT nome FROM pastagens WHERE id=?");
    $ps->execute([$pastoF]);
    $pastoNome = $ps->fetchColumn() ?: '';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small">
    <?= $total ?> animais encontrados
    <?php if ($pastoNome): ?><span class="badge bg-success ms-1">Pastagem: <?= e($pastoNome) ?></span><?php endif; ?>
  </div>
  <a href="/animais/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Cadastrar Animal
  </a>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <?php if ($pastoF): ?><input type="hidden" name="pasto_id" value="<?= $pastoF ?>"><?php endif; ?>
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por brinco, nome, raça..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">Todos os status</option>
          <?php foreach (['ativo','doente','prenha','desmamado','vendido','morto'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <select name="sexo" class="form-select form-select-sm">
          <option value="">Ambos sexos</option>
          <option value="M" <?= $sexoF==='M'?'selected':'' ?>>Macho</option>
          <option value="F" <?= $sexoF==='F'?'selected':'' ?>>Fêmea</option>
        </select>
      </div>
      <div class="col-sm-2">
        <select name="raca" class="form-select form-select-sm">
          <option value="">Todas as raças</option>
          <?php foreach ($racasAll as $r): ?>
            <option value="<?= e($r) ?>" <?= $racaF===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-sm btn-primary w-100">
          <i class="bi bi-search me-1"></i>Filtrar
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Animal</th><th>Raça / Sexo</th><th>Idade</th>
            <th>Peso Atual</th><th>Pastagem</th><th>Status</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($animais)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum animal encontrado.</td></tr>
          <?php endif; ?>
          <?php foreach ($animais as $a): ?>
            <?php
              $bgColor   = $a['sexo'] === 'M' ? '#e3f2fd' : '#fce4ec';
              $iconClass = $a['sexo'] === 'M' ? 'bi-gender-male text-primary' : 'bi-gender-female text-danger';
            ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="animal-avatar" style="background:<?= $bgColor ?>"><i class="bi <?= $iconClass ?> fs-5"></i></div>
                  <div>
                    <a href="/animais/<?= $a['id'] ?>" class="fw-700 text-decoration-none d-block" style="color:#1a4d2e">
                      <?= e($a['brinco']) ?>
                    </a>
                    <small class="text-muted"><?= e($a['nome'] ?? '—') ?></small>
                  </div>
                </div>
              </td>
              <td>
                <div class="small fw-600"><?= e($a['raca'] ?? '—') ?></div>
                <small class="text-muted"><?= sexoLabel($a['sexo']) ?></small>
              </td>
              <td class="small"><?= calcIdade($a['data_nascimento']) ?></td>
              <td>
                <?php if ($a['peso_atual']): ?>
                  <span class="fw-700"><?= number_format($a['peso_atual'],1) ?></span> <small class="text-muted">kg</small>
                  <br><small class="text-muted"><?= $a['data_pesagem'] ? formatDate($a['data_pesagem']) : 'Inicial' ?></small>
                <?php else: ?>
                  <span class="text-muted small">Sem registro</span>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($a['pasto_nome'] ?? '—') ?></td>
              <td><?= statusBadge($a['status']) ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a href="/animais/<?= $a['id'] ?>" class="btn btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                  <a href="/animais/<?= $a['id'] ?>/editar" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                  <button onclick="confirmDelete(<?= $a['id'] ?>, '<?= e($a['brinco']) ?>')" class="btn btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
    <small class="text-muted">Página <?= $page ?> de <?= $totalPages ?></small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<form id="deleteForm" method="POST" style="display:none">
  <?= csrf_field() ?>
</form>

<?php $scripts = <<<JS
<script>
function confirmDelete(id, brinco) {
  if (confirm('Excluir o animal ' + brinco + '? Esta ação não pode ser desfeita.')) {
    const f = document.getElementById('deleteForm');
    f.action = '/animais/' + id + '/excluir';
    f.submit();
  }
}
</script>
JS;
