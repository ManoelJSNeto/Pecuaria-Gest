<?php
$isEdit = isset($pastagem);
$p      = $pastagem ?? [];
?>
<!-- Topo com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/pastagens" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Pastagens &gt; <?= $isEdit ? 'Editar Pastagem' : 'Cadastrar Pastagem / Piquete' ?>
    </span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaPastagem()">
    <i class="bi bi-info-circle"></i> <span>Instruções de Pasto</span>
  </button>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-tree text-success"></i> <?= $isEdit ? 'Editar Pastagem' : 'Cadastrar Piquete / Pastagem' ?></h6>
        <span class="text-muted small">Capacidade de suporte e manejo rotacionado</span>
      </div>

      <div class="aws-container-body">
        <form method="POST" action="<?= $isEdit ? '/pastagens/'.$p['id'].'/atualizar' : '/pastagens/salvar' ?>" id="formPastagem">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- Nome da Pastagem -->
            <div class="col-12">
              <label class="form-label fw-bold">Nome da Pastagem ou Piquete *</label>
              <input type="text" name="nome" id="inputNomePasto" class="form-control" required
                     value="<?= e($p['nome'] ?? '') ?>" placeholder="Ex: Piquete 01, Pasto da Sede, Manga Nova...">
              <div class="aws-form-hint">Nome de referência utilizado pelos peões e vaqueiros na fazenda.</div>
            </div>

            <!-- Área em Hectares -->
            <div class="col-md-6">
              <label class="form-label">Área (hectares)</label>
              <div class="input-group">
                <input type="number" step="0.01" name="area_ha" id="inputArea" class="form-control"
                       value="<?= e($p['area_ha'] ?? '') ?>" placeholder="0.00">
                <span class="input-group-text">ha</span>
              </div>
              <div class="aws-form-hint">Tamanho total da área cercada.</div>
            </div>

            <!-- Capacidade em Cabeças -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Capacidade de Suporte (cabeças)</label>
              <div class="input-group">
                <input type="number" name="capacidade" id="inputCapacidade" class="form-control"
                       value="<?= e($p['capacidade'] ?? '') ?>" placeholder="0" min="0">
                <span class="input-group-text">cabeças</span>
              </div>
              <div class="aws-form-hint">Lotação recomendada para não degradar a forrageira.</div>
            </div>

            <!-- Situação / Status -->
            <div class="col-12">
              <label class="form-label fw-bold">Situação Operacional</label>
              <select name="status" class="form-select">
                <option value="ativa" <?= ($p['status']??'ativa')==='ativa'?'selected':'' ?>>Ativa (Disponível para entrada de animais)</option>
                <option value="reforma" <?= ($p['status']??'')==='reforma'?'selected':'' ?>>Em Reforma / Veda (Em descanso para recuperação)</option>
                <option value="inativa" <?= ($p['status']??'')==='inativa'?'selected':'' ?>>Inativa (Indisponível)</option>
              </select>
            </div>

            <!-- Observações e Tipo de Capim -->
            <div class="col-12">
              <label class="form-label">Observações (Capim, aguadas, cochos)</label>
              <textarea name="observacao" class="form-control" rows="3" placeholder="Ex: Capim Braquiária Brizantha, cocho coberto e bebedouro automático..."><?= e($p['observacao'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="aws-wizard-actions">
            <a href="/pastagens" class="aws-btn-secondary">Cancelar</a>
            <button type="submit" class="aws-btn-primary">
              <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Cadastrar Pastagem' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
window.abrirAjudaPastagem = function() {
  const title = 'Guia: Controle de Pastagens e Lotação';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-tree"></i> Gestão de Pastagens</h7>
      <p>O controle por piquetes permite evitar o superpastejo, garantindo que o capim descanse no tempo certo para manter o ganho de peso do gado.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-speedometer2"></i> Capacidade de Suporte</h7>
      <p>Defina a quantidade máxima de animais recomendada para o piquete. O sistema calcula a taxa de lotação em tempo real e avisa se o pasto estiver sobrecarregado.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-arrow-repeat"></i> Manejo Rotacionado</h7>
      <div class="aws-help-tip-box">
        Quando um piquete estiver em descanso ou adubação, altere o status para <strong>Em Reforma / Veda</strong> para que novos animais não sejam alocados nele por engano.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaPastagem;
});
</script>
