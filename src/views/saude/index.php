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

$stmt = $db->prepare("SELECT s.*, a.brinco, a.nome as animal_nome FROM saude s JOIN animais a ON s.animal_id=a.id WHERE $whereStr ORDER BY s.data DESC, s.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$registros = $stmt->fetchAll();
$tipos     = $db->query("SELECT DISTINCT tipo FROM saude ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small"><?= $total ?> registros de saúde</div>
  <a href="/saude/novo" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Registrar Evento de Saúde</a>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2">
      <div class="col-sm-5">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por animal ou descrição..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="tipo" class="form-select form-select-sm">
          <option value="">Todos os tipos</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= e($t) ?>" <?= $tipoF===$t?'selected':'' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-sm btn-primary w-100">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Animal</th><th>Tipo</th><th>Descrição</th><th>Medicamento</th><th>Data</th><th>Próxima</th><th>Custo</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($registros)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($registros as $s): ?>
        <?php
          $tipoColor = match(strtolower($s['tipo'] ?? '')) {
            'vacinação','vacinacao' => 'success',
            'tratamento'           => 'danger',
            'vermifugação','vermifugacao' => 'warning',
            'exame'                => 'info',
            default                => 'secondary',
          };
        ?>
        <tr>
          <td><a href="/animais/<?= $s['animal_id'] ?>" class="fw-700 text-decoration-none" style="color:#1a4d2e"><?= e($s['brinco']) ?></a><small class="text-muted d-block"><?= e($s['animal_nome'] ?? '') ?></small></td>
          <td><span class="badge bg-<?= $tipoColor ?>"><?= e($s['tipo']) ?></span></td>
          <td class="small"><?= e($s['descricao']) ?></td>
          <td class="small"><?= e($s['medicamento'] ?? '—') ?><?php if($s['dose']): ?> <span class="text-muted">(<?= e($s['dose']) ?>)</span><?php endif; ?></td>
          <td class="small text-muted"><?= formatDate($s['data']) ?></td>
          <td class="small <?= ($s['proxima_data'] && $s['proxima_data'] < date('Y-m-d')) ? 'text-danger fw-600' : 'text-muted' ?>"><?= formatDate($s['proxima_data']) ?></td>
          <td class="small"><?= $s['custo'] ? 'R$ '.number_format($s['custo'],2,',','.') : '—' ?></td>
          <td>
            <form method="POST" action="/saude/<?= $s['id'] ?>/excluir" onsubmit="return confirm('Excluir registro?')">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
