<?php
$registros = $db->query("SELECT r.*, a.brinco, a.nome as animal_nome, a.sexo FROM reproducao r JOIN animais a ON r.animal_id=a.id ORDER BY r.data DESC LIMIT 50")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small"><?= count($registros) ?> registros reprodutivos</div>
  <a href="/reproducao/novo" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Registrar Evento</a>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Animal</th><th>Tipo</th><th>Data</th><th>Touro</th><th>Resultado</th><th>Obs.</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($registros)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Nenhum registro reprodutivo.</td></tr>
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
          <td>
            <form method="POST" action="/reproducao/<?= $r['id'] ?>/excluir" onsubmit="return confirm('Excluir registro?')">
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
