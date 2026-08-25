<?php
$pastagens = $db->query("
  SELECT p.*, COUNT(a.id) as total_animais
  FROM pastagens p
  LEFT JOIN animais a ON a.pasto_id=p.id AND a.status NOT IN ('vendido','morto')
  GROUP BY p.id ORDER BY p.nome
")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small"><?= count($pastagens) ?> pastagens cadastradas</div>
  <a href="/pastagens/novo" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Cadastrar Pastagem</a>
</div>

<div class="row g-3">
  <?php foreach ($pastagens as $p): ?>
  <?php
    $ocupacao    = ($p['capacidade'] > 0) ? min(100, round($p['total_animais'] / $p['capacidade'] * 100)) : 0;
    $corOcup     = $ocupacao >= 90 ? 'danger' : ($ocupacao >= 70 ? 'warning' : 'success');
    $statusColor = $p['status'] === 'ativa' ? 'success' : 'secondary';
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="mb-0 fw-700" style="color:#1a4d2e"><?= e($p['nome']) ?></h5>
            <small class="text-muted"><?= $p['area_ha'] ? number_format($p['area_ha'],1).' ha' : '—' ?></small>
          </div>
          <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($p['status']) ?></span>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6 text-center p-2" style="background:#f8faf8;border-radius:8px">
            <div class="fw-800 fs-4" style="color:#1a4d2e"><?= $p['total_animais'] ?></div>
            <small class="text-muted">Animais</small>
          </div>
          <div class="col-6 text-center p-2" style="background:#f8faf8;border-radius:8px">
            <div class="fw-800 fs-4" style="color:#1a4d2e"><?= $p['capacidade'] ?? '—' ?></div>
            <small class="text-muted">Capacidade</small>
          </div>
        </div>
        <?php if ($p['capacidade'] > 0): ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Ocupação</span>
            <span class="fw-600 text-<?= $corOcup ?>"><?= $ocupacao ?>%</span>
          </div>
          <div class="progress" style="height:8px">
            <div class="progress-bar bg-<?= $corOcup ?>" style="width:<?= $ocupacao ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($p['observacao']): ?>
          <p class="text-muted small mb-3"><?= e($p['observacao']) ?></p>
        <?php endif; ?>
        <div class="d-flex gap-2">
          <a href="/animais?pasto_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
            <i class="bi bi-list-ul me-1"></i>Ver Animais
          </a>
          <a href="/pastagens/<?= $p['id'] ?>/editar" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
          <form method="POST" action="/pastagens/<?= $p['id'] ?>/excluir" onsubmit="return confirm('Excluir pastagem?')">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($pastagens)): ?>
  <div class="col-12 text-center text-muted py-5">
    <i class="bi bi-tree fs-1 d-block mb-3"></i>
    Nenhuma pastagem cadastrada ainda.
    <br><a href="/pastagens/novo" class="btn btn-primary mt-3">Cadastrar Pastagem</a>
  </div>
  <?php endif; ?>
</div>
