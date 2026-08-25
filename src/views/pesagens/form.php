<?php
$animaisStmt = $db->query("SELECT id, brinco, nome FROM animais ORDER BY brinco");
$animais     = $animaisStmt->fetchAll();
$isEdit      = isset($pesagem);
$p           = $pesagem ?? [];
$preAnimal   = $p['animal_id'] ?? ($_GET['animal_id'] ?? null);
?>
<div class="mb-3">
  <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/pesagens' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
</div>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="form-section">
      <div class="form-section-title">
        <i class="bi bi-rulers text-primary"></i> <?= $isEdit ? 'Editar Pesagem' : 'Registrar Pesagem' ?>
      </div>
      <form method="POST" action="<?= $isEdit ? '/pesagens/'.$p['id'].'/atualizar' : '/pesagens/salvar' ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Animal *</label>
          <select name="animal_id" class="form-select" required>
            <option value="">— Selecione o animal —</option>
            <?php foreach ($animais as $a): ?>
              <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Peso (kg) *</label>
          <input type="number" step="0.01" name="peso" class="form-control" required placeholder="0.00" min="1" max="2000" value="<?= e($p['peso'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Data da Pesagem *</label>
          <input type="date" name="data" class="form-control" required value="<?= e($p['data'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="mb-4">
          <label class="form-label">Observação</label>
          <textarea name="observacao" class="form-control" rows="2" placeholder="Opcional..."><?= e($p['observacao'] ?? '') ?></textarea>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Salvar Pesagem' ?>
          </button>
          <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/pesagens' ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>
