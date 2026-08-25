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

$stmt = $db->prepare("SELECT r.*, a.brinco, a.nome as animal_nome, a.sexo FROM reproducao r JOIN animais a ON r.animal_id=a.id WHERE $whereStr ORDER BY r.data DESC, r.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$registros = $stmt->fetchAll();
$tipos     = $db->query("SELECT DISTINCT tipo FROM reproducao ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small"><?= $total ?> registros reprodutivos</div>
  <a href="/reproducao/novo" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Registrar Evento</a>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2">
      <div class="col-sm-6">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por animal, touro ou resultado..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-4">
        <select name="tipo" class="form-select form-select-sm">
          <option value="">Todos os tipos de evento</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= e($t) ?>" <?= $tipoF===$t?'selected':'' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-search me-1"></i>Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Animal</th><th>Tipo</th><th>Data</th><th>Touro / Pai</th><th>Resultado</th><th>Obs.</th><th class="text-end">Ações</th></tr></thead>
      <tbody>
        <?php if (empty($registros)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Nenhum registro reprodutivo encontrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($registros as $r): ?>
        <?php
          $tipoColor = match(strtolower($r['tipo'] ?? '')) {
            'inseminação artificial','inseminacao artificial' => 'primary',
            'cobertura natural'                              => 'success',
            'diagnóstico de gestação','diagnostico de gestacao' => 'info',
            'parto'                                          => 'warning',
            'aborto'                                         => 'danger',
            default                                          => 'secondary',
          };
        ?>
        <tr>
          <td><a href="/animais/<?= $r['animal_id'] ?>" class="fw-700 text-decoration-none" style="color:#1a4d2e"><?= e($r['brinco']) ?></a><small class="text-muted d-block"><?= e($r['animal_nome'] ?? '') ?></small></td>
          <td><span class="badge bg-<?= $tipoColor ?>"><?= e($r['tipo']) ?></span></td>
          <td class="small text-muted"><?= formatDate($r['data']) ?></td>
          <td class="small"><?= e($r['touro_brinco'] ?? '—') ?></td>
          <td class="small fw-600"><?= e($r['resultado'] ?? '—') ?></td>
          <td class="small text-muted"><?= e($r['observacao'] ?? '—') ?></td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
              <a href="/reproducao/<?= $r['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-2" title="Editar"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="/reproducao/<?= $r['id'] ?>/excluir" onsubmit="return confirm('Excluir registro reprodutivo?')">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Excluir"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
    <small class="text-muted">Página <?= $page ?> de <?= $totalPages ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
