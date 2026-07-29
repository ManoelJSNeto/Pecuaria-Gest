<?php
$isEdit = isset($pastagem);
$p      = $pastagem ?? [];
?>
<div class="mb-3">
  <a href="/pastagens" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="form-section">
      <div class="form-section-title">
        <i class="bi bi-tree text-success"></i>
        <?= $isEdit ? 'Editar Pastagem' : 'Cadastrar Pastagem' ?>
      </div>
      <form method="POST" action="<?= $isEdit ? '/pastagens/'.$p['id'].'/atualizar' : '/pastagens/salvar' ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Nome da Pastagem *</label>
            <input type="text" name="nome" class="form-control" required value="<?= e($p['nome'] ?? '') ?>" placeholder="Ex: Pasto A, Pastagem Norte...">
          </div>
          <div class="col-md-6">
            <label class="form-label">Área (hectares)</label>
            <input type="number" step="0.01" name="area_ha" class="form-control" value="<?= e($p['area_ha'] ?? '') ?>" placeholder="0.0">
          </div>
          <div class="col-md-6">
            <label class="form-label">Capacidade (cabeças)</label>
            <input type="number" name="capacidade" class="form-control" value="<?= e($p['capacidade'] ?? '') ?>" placeholder="0">
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="ativa"   <?= ($p['status']??'ativa')==='ativa'?'selected':'' ?>>Ativa</option>
              <option value="inativa" <?= ($p['status']??'')==='inativa'?'selected':'' ?>>Inativa</option>
              <option value="reforma" <?= ($p['status']??'')==='reforma'?'selected':'' ?>>Em reforma</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Observação</label>
            <textarea name="observacao" class="form-control" rows="3" placeholder="Tipo de capim, condições, etc."><?= e($p['observacao'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Salvar Alterações' : 'Cadastrar' ?>
              </button>
              <a href="/pastagens" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
