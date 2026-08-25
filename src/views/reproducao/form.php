<?php
$animaisStmt = $db->query("SELECT id, brinco, nome, sexo FROM animais ORDER BY brinco");
$animais     = $animaisStmt->fetchAll();
$isEdit      = isset($reproducao);
$r           = $reproducao ?? [];
$preAnimal   = $r['animal_id'] ?? ($_GET['animal_id'] ?? null);
?>
<div class="mb-3">
  <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/reproducao' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
</div>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="form-section">
      <div class="form-section-title">
        <i class="bi bi-diagram-3 text-info"></i> <?= $isEdit ? 'Editar Evento Reprodutivo' : 'Registrar Evento Reprodutivo' ?>
      </div>
      <form method="POST" action="<?= $isEdit ? '/reproducao/'.$r['id'].'/atualizar' : '/reproducao/salvar' ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Animal *</label>
            <select name="animal_id" class="form-select" required>
              <option value="">— Selecione —</option>
              <?php foreach ($animais as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                  <?= e($a['brinco']) ?> (<?= $a['sexo']==='F'?'♀ Fêmea':'♂ Macho' ?>)<?= $a['nome']?' — '.e($a['nome']):'' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo de Evento *</label>
            <select name="tipo" class="form-select" required>
              <option value="">— Selecione —</option>
              <?php foreach (['Inseminação Artificial','Cobertura Natural','Diagnóstico de Gestação','Parto','Aborto','Desmame','Outro'] as $t): ?>
                <option value="<?= $t ?>" <?= ($r['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Data *</label>
            <input type="date" name="data" class="form-control" required value="<?= e($r['data'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Touro / Brinco do Pai</label>
            <input type="text" name="touro_brinco" class="form-control" placeholder="Opcional" value="<?= e($r['touro_brinco'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Resultado</label>
            <input type="text" name="resultado" class="form-control" list="resultList" placeholder="Ex: Positivo, Prenha, Gemelar..." value="<?= e($r['resultado'] ?? '') ?>">
            <datalist id="resultList">
              <option value="Positivo"><option value="Negativo"><option value="Prenha">
              <option value="Nascimento normal"><option value="Nascimento gemelar">
            </datalist>
          </div>
          <div class="col-12">
            <label class="form-label">Observação</label>
            <textarea name="observacao" class="form-control" rows="2" placeholder="Detalhes..."><?= e($r['observacao'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Salvar Evento' ?>
              </button>
              <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/reproducao' ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
