<?php
$search  = $_GET['q'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search) { $where[] = "(a.brinco LIKE ? OR a.nome LIKE ?)"; $params = ["%$search%", "%$search%"]; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM pesagens pe JOIN animais a ON pe.animal_id=a.id WHERE $whereStr");
$countStmt->execute($params);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
  SELECT pe.*, a.brinco, a.nome as animal_nome, a.sexo 
  FROM pesagens pe 
  JOIN animais a ON pe.animal_id=a.id 
  WHERE $whereStr 
  ORDER BY pe.data DESC, pe.created_at DESC 
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$pesagens = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0 fw-bold">Histórico de Pesagens</h5>
    <small class="text-muted tabular-nums"><?= (int)$total ?> pesagens registradas no sistema</small>
  </div>
  <a href="/pesagens/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Nova Pesagem
  </a>
</div>

<!-- Filtros Rápidos & Busca -->
<div class="filter-toolbar">
  <form method="GET" class="d-flex align-items-center gap-2 w-100" style="max-width: 450px;">
    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por brinco ou nome do animal..." value="<?= e($search) ?>" autocomplete="off">
      <button class="btn btn-secondary btn-sm" type="submit">
        <i class="bi bi-search"></i>
      </button>
      <?php if ($search): ?>
        <a href="/pesagens" class="btn btn-outline-secondary btn-sm" title="Limpar Busca">
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
        <th style="width: 220px;">Animal</th>
        <th>Data</th>
        <th class="text-end">Peso Registrado <span class="badge bg-light text-secondary border fw-normal cursor-pointer ms-1" onclick="toggleGlobalPesoUnit()" title="Alternar kg / @" style="font-size:0.68rem; cursor:pointer;"><i class="bi bi-arrow-left-right me-1"></i>kg / @</span></th>
        <th>Canal / Origem</th>
        <th>Observações</th>
        <th class="text-end" style="width: 100px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pesagens)): ?>
        <tr>
          <td colspan="6" class="text-center text-muted py-5">
            <i class="bi bi-rulers fs-3 d-block mb-2 text-muted"></i>
            Nenhum registro de pesagem encontrado.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($pesagens as $p): ?>
        <tr>
          <td>
            <a href="/animais/<?= $p['animal_id'] ?>" class="fw-bold text-primary text-decoration-none">
              <?= e($p['brinco']) ?>
            </a>
            <?php if (!empty($p['animal_nome'])): ?>
              <small class="text-muted d-block"><?= e($p['animal_nome']) ?></small>
            <?php endif; ?>
          </td>
          <td class="tabular-nums small text-secondary">
            <?= formatDate($p['data']) ?>
          </td>
          <td class="text-end tabular-nums">
            <?= renderPesoBadge($p['peso']) ?>
          </td>
          <td class="small">
            <span class="badge bg-light text-dark border">
              <i class="bi <?= ($p['origem'] ?? 'web') === 'mobile' ? 'bi-phone' : 'bi-laptop' ?> me-1"></i>
              <?= ucfirst(e($p['origem'] ?? 'web')) ?>
            </span>
          </td>
          <td class="small text-secondary">
            <?= e($p['observacao'] ?? '—') ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="/pesagens/<?= $p['id'] ?>/editar" class="btn btn-secondary btn-sm" title="Editar">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="/pesagens/<?= $p['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Excluir esta pesagem?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm text-danger" title="Excluir">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
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
