<?php
$search  = $_GET['q'] ?? '';
$tipoF   = $_GET['tipo'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search) { $where[] = "(a.brinco LIKE ? OR a.nome LIKE ? OR s.descricao LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($tipoF)  { $where[] = "s.tipo = ?"; $params[] = $tipoF; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM saude s JOIN animais a ON s.animal_id=a.id WHERE $whereStr");
$countStmt->execute($params);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
  SELECT s.*, a.brinco, a.nome as animal_nome 
  FROM saude s 
  JOIN animais a ON s.animal_id=a.id 
  WHERE $whereStr 
  ORDER BY s.data DESC, s.created_at DESC 
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$registros = $stmt->fetchAll();
$tipos     = $db->query("SELECT DISTINCT tipo FROM saude ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0 fw-bold">Manejo Sanitário & Clínico</h5>
    <small class="text-muted tabular-nums"><?= (int)$total ?> ocorrências e vacinações registradas</small>
  </div>
  <a href="/saude/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Novo Registro Clínico
  </a>
</div>

<!-- Barra de Filtros -->
<div class="filter-toolbar">
  <form method="GET" class="d-flex align-items-center gap-2 w-100" style="max-width: 550px;">
    <select name="tipo" class="form-select form-select-sm" style="max-width: 170px;" onchange="this.form.submit()">
      <option value="">Todos os Tipos</option>
      <?php foreach ($tipos as $t): ?>
        <option value="<?= e($t) ?>" <?= $tipoF === $t ? 'selected' : '' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por brinco, nome ou descrição..." value="<?= e($search) ?>" autocomplete="off">
      <button class="btn btn-secondary btn-sm" type="submit">
        <i class="bi bi-search"></i>
      </button>
      <?php if ($search || $tipoF): ?>
        <a href="/saude" class="btn btn-outline-secondary btn-sm" title="Limpar Filtros">
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
        <th style="width: 200px;">Animal</th>
        <th>Tipo / Evento</th>
        <th>Descrição Clínica</th>
        <th>Medicamento / Posologia</th>
        <th>Data Aplicação</th>
        <th>Próxima Dose / Reforço</th>
        <th class="text-end" style="width: 100px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($registros)): ?>
        <tr>
          <td colspan="7" class="text-center text-muted py-5">
            <i class="bi bi-heart-pulse fs-3 d-block mb-2 text-muted"></i>
            Nenhum evento sanitário registrado com os filtros aplicados.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($registros as $s): ?>
        <tr>
          <td>
            <a href="/animais/<?= $s['animal_id'] ?>" class="fw-bold text-primary text-decoration-none">
              <?= e($s['brinco']) ?>
            </a>
            <?php if (!empty($s['animal_nome'])): ?>
              <small class="text-muted d-block"><?= e($s['animal_nome']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-light text-dark border">
              <?= e($s['tipo']) ?>
            </span>
          </td>
          <td class="small text-secondary">
            <?= e($s['descricao']) ?>
          </td>
          <td class="small">
            <?php if (!empty($s['medicamento'])): ?>
              <strong class="text-primary"><?= e($s['medicamento']) ?></strong>
              <?php if (!empty($s['dose'])): ?>
                <span class="text-muted small">(<?= e($s['dose']) ?>)</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="small tabular-nums text-secondary">
            <?= formatDate($s['data']) ?>
          </td>
          <td class="small tabular-nums">
            <?php if (!empty($s['proxima_data'])): ?>
              <?php $isAtrasado = ($s['proxima_data'] < date('Y-m-d')); ?>
              <span class="<?= $isAtrasado ? 'text-danger fw-bold' : 'text-secondary' ?>">
                <i class="bi <?= $isAtrasado ? 'bi-exclamation-triangle-fill' : 'bi-calendar-check' ?> me-1"></i>
                <?= formatDate($s['proxima_data']) ?>
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="/saude/<?= $s['id'] ?>/editar" class="btn btn-secondary btn-sm" title="Editar">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="/saude/<?= $s['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Excluir este registro sanitário?')">
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
