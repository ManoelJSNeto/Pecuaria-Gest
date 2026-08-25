<?php
$animaisStmt = $db->query("SELECT id, brinco, nome FROM animais ORDER BY brinco");
$animais     = $animaisStmt->fetchAll();
$isEdit      = isset($saude);
$s           = $saude ?? [];
$preAnimal   = $s['animal_id'] ?? ($_GET['animal_id'] ?? null);
?>
<div class="mb-3">
  <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
</div>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="form-section">
      <div class="form-section-title">
        <i class="bi bi-heart-pulse text-danger"></i> <?= $isEdit ? 'Editar Evento de Saúde' : 'Registrar Evento de Saúde' ?>
      </div>
      <form method="POST" action="<?= $isEdit ? '/saude/'.$s['id'].'/atualizar' : '/saude/salvar' ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Animal *</label>
            <select name="animal_id" class="form-select" required>
              <option value="">— Selecione —</option>
              <?php foreach ($animais as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                  <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo de Evento *</label>
            <select name="tipo" class="form-select" required>
              <option value="">— Selecione —</option>
              <?php foreach (['Vacinação','Tratamento','Vermifugação','Exame','Curativo','Cirurgia','Parto','Outro'] as $t): ?>
                <option value="<?= $t ?>" <?= ($s['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Descrição *</label>
            <input type="text" name="descricao" class="form-control" required placeholder="Ex: Vacinação contra aftosa — dose única" value="<?= e($s['descricao'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Data do Evento *</label>
            <input type="date" name="data" class="form-control" required value="<?= e($s['data'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Próximo Evento</label>
            <input type="date" name="proxima_data" class="form-control" value="<?= e($s['proxima_data'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Custo (R$)</label>
            <input type="number" step="0.01" name="custo" class="form-control" placeholder="0.00" value="<?= e($s['custo'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Medicamento</label>
            <input type="text" name="medicamento" class="form-control" list="medList" placeholder="Nome do medicamento" value="<?= e($s['medicamento'] ?? '') ?>">
            <datalist id="medList">
              <?php foreach (['Aftosa','Brucelose','Carbúnculo','Clostridiose','Botulismo','Ivermectina','Vitamina ADE','Antibiótico','Vermifugo'] as $m): ?>
                <option value="<?= $m ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-3">
            <label class="form-label">Dose</label>
            <input type="text" name="dose" class="form-control" placeholder="Ex: 5ml" value="<?= e($s['dose'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Veterinário</label>
            <input type="text" name="veterinario" class="form-control" placeholder="Nome" value="<?= e($s['veterinario'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Observação</label>
            <textarea name="observacao" class="form-control" rows="2" placeholder="Informações adicionais..."><?= e($s['observacao'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Salvar Registro' ?>
              </button>
              <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
