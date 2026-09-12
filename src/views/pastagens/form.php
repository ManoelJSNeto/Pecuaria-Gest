<?php
$isEdit = isset($pastagem);
$p      = $pastagem ?? [];
?>
<div class="mb-3 d-flex align-items-center justify-content-between">
  <a href="/pastagens" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar para Pastagens
  </a>
  <span class="text-muted small"><i class="bi bi-shield-check text-success me-1"></i>Gestão de Lotação e Manejo de Pasto</span>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card shadow-sm border">
      <div class="card-header bg-white py-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <div class="action-icon-box icon-green" style="width:40px; height:40px; font-size:1.2rem;">
            <i class="bi bi-tree-fill"></i>
          </div>
          <div>
            <h6 class="mb-0 fw-bold" style="font-size:1.05rem;"><?= $isEdit ? 'Editar Pastagem / Piquete' : 'Cadastrar Nova Pastagem / Piquete' ?></h6>
            <small class="text-muted">Defina a capacidade de suporte para controle de lotação do gado</small>
          </div>
        </div>
      </div>

      <div class="card-body p-4">
        <!-- Banner de Orientação Amigável -->
        <div class="flow-helper-banner mb-4">
          <i class="bi bi-info-circle-fill text-success fs-5"></i>
          <div>
            <strong>Controle de Lotação:</strong> Cadastre cada piquete com a quantidade recomendada de cabeças (capacidade). O sistema indicará se o pasto estiver com folga ou sobrecarregado.
          </div>
        </div>

        <form method="POST" action="<?= $isEdit ? '/pastagens/'.$p['id'].'/atualizar' : '/pastagens/salvar' ?>" id="formPastagem">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- Nome da Pastagem -->
            <div class="col-12">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                Nome da Pastagem ou Piquete *
              </label>
              <input type="text" name="nome" id="inputNomePasto" class="form-control form-control-lg" required
                     value="<?= e($p['nome'] ?? '') ?>" placeholder="Ex: Piquete 01, Pasto da Sede, Manga Nova..."
                     style="font-size:1.05rem;">
              <div class="quick-chip-group mt-2">
                <span class="quick-chip" onclick="definirNomePasto('Piquete 1')">Piquete 1</span>
                <span class="quick-chip" onclick="definirNomePasto('Piquete 2')">Piquete 2</span>
                <span class="quick-chip" onclick="definirNomePasto('Pasto da Sede')">Pasto da Sede</span>
                <span class="quick-chip" onclick="definirNomePasto('Pasto do Retiro')">Pasto do Retiro</span>
                <span class="quick-chip" onclick="definirNomePasto('Manga de Confinamento')">Confinamento</span>
              </div>
            </div>

            <!-- Área em Hectares -->
            <div class="col-md-6">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                Área (em hectares)
              </label>
              <div class="input-group">
                <input type="number" step="0.01" name="area_ha" id="inputArea" class="form-control"
                       value="<?= e($p['area_ha'] ?? '') ?>" placeholder="Ex: 15.5">
                <span class="input-group-text bg-light text-muted fw-bold">ha</span>
              </div>
              <small class="text-muted">Tamanho estimado do piquete.</small>
            </div>

            <!-- Capacidade em Cabeças -->
            <div class="col-md-6">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                Capacidade Máxima Suportada
              </label>
              <div class="input-group">
                <input type="number" name="capacidade" id="inputCapacidade" class="form-control fw-bold"
                       value="<?= e($p['capacidade'] ?? '') ?>" placeholder="Ex: 30" min="0">
                <span class="input-group-text bg-light text-muted fw-bold">cabeças</span>
              </div>
              <small class="text-muted">Quantos animais cabem sem degradar o pasto.</small>
            </div>

            <!-- Situação / Status -->
            <div class="col-12">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                Situação do Pasto
              </label>
              <select name="status" class="form-select form-select-lg" style="font-size:1rem;">
                <option value="ativa" <?= ($p['status']??'ativa')==='ativa'?'selected':'' ?>>🌿 Ativa (Disponível para colocar gado)</option>
                <option value="reforma" <?= ($p['status']??'')==='reforma'?'selected':'' ?>>🚜 Em Reforma / Veda (Em descanso)</option>
                <option value="inativa" <?= ($p['status']??'')==='inativa'?'selected':'' ?>>⛔ Inativa (Indisponível)</option>
              </select>
            </div>

            <!-- Observações e Tipo de Capim -->
            <div class="col-12">
              <label class="form-label small text-muted">Observações Adicionais (Tipo de capim, bebedouros, etc.)</label>
              <textarea name="observacao" class="form-control" rows="3" placeholder="Ex: Capim Braquiária Brizantha, cocho coberto e bebedouro automático..."><?= e($p['observacao'] ?? '') ?></textarea>
            </div>

            <!-- Botões Grandes de Ação -->
            <div class="col-12 pt-3 border-top">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-flow-primary py-3 justify-content-center" style="font-size:1.05rem;">
                  <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                  <?= $isEdit ? 'Salvar Alterações da Pastagem' : 'Confirmar e Cadastrar Pastagem' ?>
                </button>
                <a href="/pastagens" class="btn btn-link text-muted text-decoration-none text-center">
                  Cancelar e voltar
                </a>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function definirNomePasto(nome) {
  const inNome = document.getElementById('inputNomePasto');
  if (inNome) {
    inNome.value = nome;
    inNome.focus();
  }
}
</script>
