<?php
$search  = $_GET['q'] ?? '';
$tipoF   = $_GET['tipo'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search) { $where[] = "(a.brinco LIKE ? OR a.nome LIKE ? OR r.resultado LIKE ? OR r.touro_brinco LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($tipoF)  { $where[] = "r.tipo = ?"; $params[] = $tipoF; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM reproducao r JOIN animais a ON r.animal_id=a.id WHERE $whereStr");
$countStmt->execute($params);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
  SELECT r.*, a.brinco, a.nome as animal_nome, a.sexo 
  FROM reproducao r 
  JOIN animais a ON r.animal_id=a.id 
  WHERE $whereStr 
  ORDER BY r.data DESC, r.created_at DESC 
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$registros = $stmt->fetchAll();
$tipos     = $db->query("SELECT DISTINCT tipo FROM reproducao ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0 fw-bold">Manejo Reprodutivo</h5>
    <small class="text-muted tabular-nums"><?= (int)$total ?> coberturas, inseminações e partos registrados</small>
  </div>
  <a href="/reproducao/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Novo Evento Reprodutivo
  </a>
</div>

<!-- Filtros Rápidos -->
<div class="filter-toolbar">
  <form method="GET" class="d-flex align-items-center gap-2 w-100" style="max-width: 550px;">
    <select name="tipo" class="form-select form-select-sm" style="max-width: 190px;" onchange="this.form.submit()">
      <option value="">Todos os Eventos</option>
      <?php foreach ($tipos as $t): ?>
        <option value="<?= e($t) ?>" <?= $tipoF === $t ? 'selected' : '' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar fêmea, touro ou resultado..." value="<?= e($search) ?>" autocomplete="off">
      <button class="btn btn-secondary btn-sm" type="submit">
        <i class="bi bi-search"></i>
      </button>
      <?php if ($search || $tipoF): ?>
        <a href="/reproducao" class="btn btn-outline-secondary btn-sm" title="Limpar Filtros">
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
        <th style="width: 200px;">Matriz / Fêmea</th>
        <th>Tipo de Evento</th>
        <th>Data</th>
        <th>Touro / Sêmen</th>
        <th>Diagnóstico / Resultado</th>
        <th>Observações</th>
        <th class="text-end" style="width: 100px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($registros)): ?>
        <tr>
          <td colspan="7" class="text-center text-muted py-5">
            <i class="bi bi-diagram-3 fs-3 d-block mb-2 text-muted"></i>
            Nenhum evento reprodutivo encontrado.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($registros as $r): ?>
        <tr>
          <td>
            <a href="/animais/<?= $r['animal_id'] ?>" class="fw-bold text-primary text-decoration-none">
              <?= e($r['brinco']) ?>
            </a>
            <?php if (!empty($r['animal_nome'])): ?>
              <small class="text-muted d-block"><?= e($r['animal_nome']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-light text-dark border">
              <?= e($r['tipo']) ?>
            </span>
          </td>
          <td class="tabular-nums small text-secondary">
            <?= formatDate($r['data']) ?>
          </td>
          <td class="small text-secondary">
            <?= e($r['touro_brinco'] ?? '—') ?>
          </td>
          <td class="small fw-600">
            <?= e($r['resultado'] ?? 'Pendente') ?>
          </td>
          <td class="small text-muted">
            <?= e($r['observacao'] ?? '—') ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="/reproducao/<?= $r['id'] ?>/editar" class="btn btn-secondary btn-sm" title="Editar">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="/reproducao/<?= $r['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Excluir este registro reprodutivo?')">
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
