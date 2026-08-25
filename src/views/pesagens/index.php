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

$stmt = $db->prepare("SELECT pe.*, a.brinco, a.nome as animal_nome, a.sexo FROM pesagens pe JOIN animais a ON pe.animal_id=a.id WHERE $whereStr ORDER BY pe.data DESC, pe.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$pesagens = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small"><?= $total ?> pesagens registradas</div>
  <a href="/pesagens/novo" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Registrar Pesagem</a>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por brinco ou nome..." value="<?= e($search) ?>">
      <button class="btn btn-sm btn-primary">Buscar</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Animal</th><th>Data</th><th>Peso (kg)</th><th>Origem</th><th>Observação</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($pesagens)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma pesagem registrada.</td></tr>
        <?php endif; ?>
        <?php foreach ($pesagens as $p): ?>
        <tr>
          <td>
            <a href="/animais/<?= $p['animal_id'] ?>" class="fw-700 text-decoration-none" style="color:#1a4d2e"><?= e($p['brinco']) ?></a>
            <small class="text-muted d-block"><?= e($p['animal_nome'] ?? '') ?></small>
          </td>
          <td class="small"><?= formatDate($p['data']) ?></td>
          <td class="fw-700"><?= number_format($p['peso'],1) ?></td>
          <td class="small text-muted"><?= e($p['origem'] ?? 'web') ?></td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
              <a href="/pesagens/<?= $p['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-2" title="Editar"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="/pesagens/<?= $p['id'] ?>/excluir" onsubmit="return confirm('Excluir pesagem?')">
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
      <?php for ($i=1;$i<=$totalPages;$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
