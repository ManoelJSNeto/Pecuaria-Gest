<?php
$alertas = $db->query("SELECT al.*, a.brinco, a.nome as animal_nome FROM alertas al LEFT JOIN animais a ON al.animal_id=a.id ORDER BY al.lido ASC, al.created_at DESC")->fetchAll();
$naoLidos = array_filter($alertas, fn($al) => !$al['lido']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small"><?= count($alertas) ?> alertas — <?= count($naoLidos) ?> não lidos</div>
  <div class="d-flex gap-2">
    <?php if (count($naoLidos) > 0): ?>
      <a href="/alertas/ler-todos" class="btn btn-sm btn-outline-success"><i class="bi bi-check-all me-1"></i> Marcar todos como lidos</a>
    <?php endif; ?>
    <a href="/alertas/novo" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Criar Alerta</a>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Status</th><th>Animal</th><th>Tipo</th><th>Mensagem</th><th>Data</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($alertas)): ?>
          <tr><td colspan="6" class="text-center text-muted py-5">
            <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
            Nenhum alerta cadastrado!
          </td></tr>
        <?php endif; ?>
        <?php foreach ($alertas as $al): ?>
        <?php
          $tipoColor = match($al['tipo']) {
            'saude','vacina' => 'danger',
            'pesagem'        => 'primary',
            'reproducao'     => 'info',
            default          => 'secondary',
          };
          $tipoIcon = match($al['tipo']) {
            'saude'      => 'bi-heart-pulse-fill',
            'vacina'     => 'bi-shield-plus',
            'pesagem'    => 'bi-rulers',
            'reproducao' => 'bi-diagram-3',
            default      => 'bi-exclamation-circle',
          };
        ?>
        <tr class="<?= !$al['lido'] ? 'table-warning' : '' ?>" style="<?= !$al['lido'] ? 'opacity:1' : 'opacity:.65' ?>">
          <td>
            <?php if ($al['lido']): ?>
              <span class="badge bg-secondary"><i class="bi bi-check"></i> Lido</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark"><i class="bi bi-circle-fill" style="font-size:.5rem"></i> Novo</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($al['animal_id']): ?>
              <a href="/animais/<?= $al['animal_id'] ?>" class="fw-700 text-decoration-none" style="color:#1a4d2e"><?= e($al['brinco'] ?? '') ?></a>
              <small class="text-muted d-block"><?= e($al['animal_nome'] ?? '') ?></small>
            <?php else: ?>
              <span class="text-muted small">Geral</span>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-<?= $tipoColor ?>"><i class="bi <?= $tipoIcon ?> me-1"></i><?= ucfirst($al['tipo']) ?></span></td>
          <td><?= e($al['mensagem']) ?></td>
          <td class="small text-muted"><?= formatDateTime($al['created_at']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <?php if (!$al['lido']): ?>
                <a href="/alertas/<?= $al['id'] ?>/ler" class="btn btn-sm btn-outline-success py-0 px-2" title="Marcar como lido"><i class="bi bi-check-lg"></i></a>
              <?php endif; ?>
              <form method="POST" action="/alertas/<?= $al['id'] ?>/excluir" onsubmit="return confirm('Excluir alerta?')">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
