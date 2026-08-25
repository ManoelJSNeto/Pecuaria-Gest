<?php
$animais = $db->query("SELECT id, brinco, nome FROM animais WHERE status NOT IN ('vendido','morto') ORDER BY brinco")->fetchAll();
?>
<div class="mb-3">
  <a href="/alertas" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>
<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="form-section">
      <div class="form-section-title"><i class="bi bi-bell-fill text-warning"></i> Criar Alerta</div>
      <form method="POST" action="/alertas/salvar">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Animal (opcional)</label>
          <select name="animal_id" class="form-select">
            <option value="">— Geral / Sem animal —</option>
            <?php foreach ($animais as $a): ?>
              <option value="<?= $a['id'] ?>"><?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo *</label>
          <select name="tipo" class="form-select" required>
            <option value="">— Selecione —</option>
            <?php foreach (['saude','vacina','pesagem','reproducao','geral'] as $t): ?>
              <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-4">
          <label class="form-label">Mensagem *</label>
          <textarea name="mensagem" class="form-control" rows="3" required placeholder="Descreva o alerta..."></textarea>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i> Criar Alerta</button>
          <a href="/alertas" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>
