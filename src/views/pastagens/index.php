<?php
$pastagens = $db->query("
  SELECT p.*, COUNT(a.id) as total_animais
  FROM pastagens p
  LEFT JOIN animais a ON a.pasto_id=p.id AND a.status NOT IN ('vendido','morto')
  GROUP BY p.id ORDER BY p.nome
")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0 fw-bold">Gestão de Pastagens & Piquetes</h5>
    <small class="text-muted tabular-nums"><?= count($pastagens) ?> áreas cadastradas</small>
  </div>
  <a href="/pastagens/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i> Nova Pastagem
  </a>
</div>

<div class="row g-3">
  <?php foreach ($pastagens as $p): ?>
  <?php
    $ocupacao = ($p['capacidade'] > 0) ? min(100, round($p['total_animais'] / $p['capacidade'] * 100)) : 0;
    $corOcup  = $ocupacao >= 90 ? '#991b1b' : ($ocupacao >= 70 ? '#d97706' : '#234d1b');
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100">
      <div class="card-body p-3 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h6 class="mb-0 fw-bold text-primary"><?= e($p['nome']) ?></h6>
              <small class="text-muted tabular-nums"><?= $p['area_ha'] ? number_format($p['area_ha'], 1).' ha' : 'Área não informada' ?></small>
            </div>
            <span class="badge-status <?= $p['status'] === 'ativa' ? 'ativo' : 'neutro' ?>">
              <?= ucfirst(e($p['status'])) ?>
            </span>
          </div>

          <div class="row g-2 mb-3 mt-1">
            <div class="col-6 text-center p-2" style="background:var(--bg-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-subtle);">
              <div class="fw-bold fs-5 tabular-nums text-primary"><?= (int)$p['total_animais'] ?></div>
              <small class="text-muted" style="font-size:0.72rem; text-transform:uppercase;">Cabeças</small>
            </div>
            <div class="col-6 text-center p-2" style="background:var(--bg-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-subtle);">
              <div class="fw-bold fs-5 tabular-nums text-secondary"><?= $p['capacidade'] ?? '—' ?></div>
              <small class="text-muted" style="font-size:0.72rem; text-transform:uppercase;">Capacidade</small>
            </div>
          </div>

          <?php if ($p['capacidade'] > 0): ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-secondary" style="font-size:0.75rem;">Taxa de Lotação</span>
              <span class="fw-bold tabular-nums" style="color:<?= $corOcup ?>; font-size:0.75rem;"><?= $ocupacao ?>%</span>
            </div>
            <div class="progress" style="height:6px; background-color:var(--bg-subtle); border-radius:3px;">
              <div class="progress-bar" style="width:<?= $ocupacao ?>%; background-color:<?= $corOcup ?>;"></div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($p['observacao'])): ?>
            <p class="text-secondary small mb-3" style="font-size:0.78rem;"><?= e($p['observacao']) ?></p>
          <?php endif; ?>
        </div>

        <div class="d-flex gap-2 pt-2 border-top">
          <a href="/animais?pasto_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary flex-grow-1">
            <i class="bi bi-list-ul me-1"></i>Ver Rebanho
          </a>
          <a href="/pastagens/<?= $p['id'] ?>/editar" class="btn btn-sm btn-secondary" title="Editar">
            <i class="bi bi-pencil"></i>
          </a>
          <form method="POST" action="/pastagens/<?= $p['id'] ?>/excluir" onsubmit="return confirm('Excluir esta pastagem?')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-secondary text-danger" title="Excluir">
              <i class="bi bi-trash"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($pastagens)): ?>
  <div class="col-12 text-center text-muted py-5">
    <i class="bi bi-tree fs-2 d-block mb-2 text-muted"></i>
    Nenhuma pastagem cadastrada ainda.
    <br><a href="/pastagens/novo" class="btn btn-primary btn-sm mt-3">Cadastrar Pastagem</a>
  </div>
  <?php endif; ?>
</div>
