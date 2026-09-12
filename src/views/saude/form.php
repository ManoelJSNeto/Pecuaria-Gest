<?php
$animaisStmt = $db->query("SELECT id, brinco, nome FROM animais WHERE status != 'morto' ORDER BY brinco");
$animais     = $animaisStmt->fetchAll();
$isEdit      = isset($saude);
$s           = $saude ?? [];
$preAnimal   = $s['animal_id'] ?? ($_GET['animal_id'] ?? null);
?>
<!-- Topo com Breadcrumbs e Ajuda -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <span class="text-muted small">
      Saúde &gt; <?= $isEdit ? 'Editar Evento' : 'Novo Registro Sanitário' ?>
    </span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="abrirAjudaSaude()">
    <i class="bi bi-info-circle"></i> <span>Instruções de Sanidade</span>
  </button>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="aws-container">
      <div class="aws-container-header">
        <h6><i class="bi bi-heart-pulse text-danger"></i> <?= $isEdit ? 'Editar Evento de Saúde' : 'Registrar Vacinação ou Tratamento' ?></h6>
        <span class="text-muted small">Manejo sanitário e controle de carência</span>
      </div>

      <div class="aws-container-body">
        <form method="POST" action="<?= $isEdit ? '/saude/'.$s['id'].'/atualizar' : '/saude/salvar' ?>" id="formSaude">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- 1. Qual é o animal? -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Animal (Brinco) *</label>
              <select name="animal_id" id="selectAnimal" class="form-select" required>
                <option value="">— Selecione o brinco —</option>
                <?php foreach ($animais as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="aws-form-hint">Animal que recebeu a medicação ou vacina.</div>
            </div>

            <!-- 2. Tipo de Manejo Sanitário -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Tipo de Evento *</label>
              <select name="tipo" id="selectTipo" class="form-select" required>
                <option value="">— Selecione o tipo —</option>
                <?php foreach (['Vacinação','Vermifugação','Tratamento','Curativo','Cirurgia','Exame','Parto','Recuperado / Alta','Óbito','Outro'] as $t): ?>
                  <option value="<?= $t ?>" <?= ($s['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
              <div class="aws-form-hint">Classificação para laudo e auditoria sanitária.</div>
            </div>

            <!-- 3. Descrição -->
            <div class="col-12">
              <label class="form-label fw-bold">Descrição do Procedimento *</label>
              <input type="text" name="descricao" id="inputDescricao" class="form-control" required
                     placeholder="Ex: Vacinação contra febre aftosa — dose semestral"
                     value="<?= e($s['descricao'] ?? '') ?>">
            </div>

            <!-- 4. Medicamento e Dose -->
            <div class="col-md-6">
              <label class="form-label">Medicamento / Princípio Ativo</label>
              <input type="text" name="medicamento" id="inputMedicamento" class="form-control" list="medList"
                     placeholder="Ex: Ivermectina, Aftosa..." value="<?= e($s['medicamento'] ?? '') ?>">
              <datalist id="medList">
                <?php foreach (['Aftosa','Brucelose','Carbúnculo','Clostridiose','Botulismo','Ivermectina','Vitamina ADE','Antibiótico','Vermífugo','Cicatrizante'] as $m): ?>
                  <option value="<?= $m ?>">
                <?php endforeach; ?>
              </datalist>
            </div>

            <div class="col-md-3">
              <label class="form-label">Dose Aplicada</label>
              <input type="text" name="dose" id="inputDose" class="form-control" placeholder="Ex: 5ml" value="<?= e($s['dose'] ?? '') ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">Custo (R$)</label>
              <input type="number" step="0.01" name="custo" class="form-control" placeholder="0.00" value="<?= e($s['custo'] ?? '') ?>">
            </div>

            <!-- 5. Datas e Veterinário -->
            <div class="col-md-4">
              <label class="form-label fw-bold">Data da Aplicação *</label>
              <input type="date" name="data" class="form-control" required value="<?= e($s['data'] ?? date('Y-m-d')) ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">Próxima Dose / Retorno</label>
              <input type="date" name="proxima_data" class="form-control" value="<?= e($s['proxima_data'] ?? '') ?>">
              <div class="aws-form-hint">Gera lembrete automático no painel de alertas.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Aplicador / Veterinário</label>
              <input type="text" name="veterinario" class="form-control" placeholder="Nome do profissional" value="<?= e($s['veterinario'] ?? '') ?>">
            </div>

            <!-- 6. Observações Adicionais -->
            <div class="col-12">
              <label class="form-label">Observações Clínicas (Opcional)</label>
              <textarea name="observacao" class="form-control" rows="2" placeholder="Reação adversa, lote do medicamento, local da injeção..."><?= e($s['observacao'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="aws-wizard-actions">
            <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="aws-btn-secondary">Cancelar</a>
            <button type="submit" class="aws-btn-primary">
              <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Gravar Registro Sanitário' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
window.abrirAjudaSaude = function() {
  const title = 'Guia: Manejo Sanitário e Vacinas';
  const html = `
    <div class="aws-help-section">
      <h7><i class="bi bi-heart-pulse"></i> Controle Sanitário</h7>
      <p>Registrar vacinas e medicamentos garante a rastreabilidade do rebanho e o cumprimento dos períodos de carência exigidos pelos frigoríficos antes do abate.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-calendar-check"></i> Próxima Dose e Alertas</h7>
      <p>Se o medicamento exigir reforço (como vacinas anuais ou vermifugações periódicas), preencha o campo <strong>Próxima Dose</strong>. O sistema colocará um aviso automático no Dashboard quando a data estiver próxima.</p>
    </div>

    <div class="aws-help-section">
      <h7><i class="bi bi-shield-plus"></i> Emissão de Laudos</h7>
      <div class="aws-help-tip-box">
        Todos os registros de saúde alimentam automaticamente o <strong>Laudo Sanitário em PDF</strong> na aba de Relatórios, pronto para fiscalização ou venda.
      </div>
    </div>
  `;
  openAwsHelpDrawer(title, html);
};

document.addEventListener('DOMContentLoaded', () => {
  const btnTop = document.getElementById('btnTopHelp');
  if (btnTop) btnTop.onclick = abrirAjudaSaude;
});
</script>
