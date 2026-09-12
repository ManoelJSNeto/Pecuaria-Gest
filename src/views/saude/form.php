<?php
$animaisStmt = $db->query("SELECT id, brinco, nome FROM animais WHERE status != 'morto' ORDER BY brinco");
$animais     = $animaisStmt->fetchAll();
$isEdit      = isset($saude);
$s           = $saude ?? [];
$preAnimal   = $s['animal_id'] ?? ($_GET['animal_id'] ?? null);
?>
<div class="mb-3 d-flex align-items-center justify-content-between">
  <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Voltar
  </a>
  <span class="text-muted small"><i class="bi bi-shield-check text-success me-1"></i>Sanidade Animal • Registro Rastreado</span>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm border">
      <div class="card-header bg-white py-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <div class="action-icon-box icon-purple" style="width:40px; height:40px; font-size:1.2rem;">
            <i class="bi bi-heart-pulse-fill"></i>
          </div>
          <div>
            <h6 class="mb-0 fw-bold" style="font-size:1.05rem;"><?= $isEdit ? 'Editar Evento de Saúde' : 'Anotar Vacina ou Tratamento de Saúde' ?></h6>
            <small class="text-muted">Registro de vacinações, vermifugações e cuidados veterinários</small>
          </div>
        </div>
      </div>

      <div class="card-body p-4">
        <!-- Banner de Orientação Amigável -->
        <div class="flow-helper-banner mb-4">
          <i class="bi bi-info-circle-fill text-success fs-5"></i>
          <div>
            <strong>Passo Simples:</strong> Escolha o animal e toque em uma das ações rápidas abaixo (como Vacinação ou Vermifugação) para preencher automaticamente.
          </div>
        </div>

        <form method="POST" action="<?= $isEdit ? '/saude/'.$s['id'].'/atualizar' : '/saude/salvar' ?>" id="formSaude">
          <?= csrf_field() ?>

          <div class="row g-3">
            <!-- 1. Qual é o animal? -->
            <div class="col-md-6">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                <i class="bi bi-tag-fill text-success me-1"></i> Qual é o animal? *
              </label>
              <select name="animal_id" id="selectAnimal" class="form-select form-select-lg" required style="font-size:1rem;">
                <option value="">— Clique para selecionar o brinco —</option>
                <?php foreach ($animais as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= $preAnimal == $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['brinco']) ?><?= $a['nome'] ? ' — '.e($a['nome']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- 2. Tipo de Manejo Sanitário -->
            <div class="col-md-6">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                <i class="bi bi-clipboard2-pulse text-primary me-1"></i> Tipo de Evento *
              </label>
              <select name="tipo" id="selectTipo" class="form-select form-select-lg" required style="font-size:1rem;">
                <option value="">— Selecione o tipo —</option>
                <?php foreach (['Vacinação','Vermifugação','Tratamento','Curativo','Cirurgia','Exame','Parto','Recuperado / Alta','Óbito','Outro'] as $t): ?>
                  <option value="<?= $t ?>" <?= ($s['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Atalhos de 1 Toque para Tipos Comuns -->
            <div class="col-12">
              <span class="text-muted small fw-bold text-uppercase" style="font-size:0.75rem;">Atalhos rápidos:</span>
              <div class="quick-chip-group">
                <span class="quick-chip" onclick="selecionarTipoRapido('Vacinação', 'Vacinação contra aftosa / clostridiose', 'Aftosa', '5ml')">💉 Vacinação</span>
                <span class="quick-chip" onclick="selecionarTipoRapido('Vermifugação', 'Aplicação periódica de vermífugo', 'Ivermectina', '1ml/50kg')">🪱 Vermifugação</span>
                <span class="quick-chip" onclick="selecionarTipoRapido('Tratamento', 'Tratamento com antibiótico / anti-inflamatório', 'Antibiótico', '10ml')">💊 Tratamento</span>
                <span class="quick-chip" onclick="selecionarTipoRapido('Curativo', 'Curativo de umbigo ou ferimento', 'Iodo / Cicatrizante', '')">🩹 Curativo</span>
                <span class="quick-chip" onclick="selecionarTipoRapido('Exame', 'Exame clínico de rotina', '', '')">🩺 Exame Clínico</span>
              </div>
            </div>

            <!-- 3. Descrição do que foi feito -->
            <div class="col-12">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                Descrição do Procedimento *
              </label>
              <input type="text" name="descricao" id="inputDescricao" class="form-control" required
                     placeholder="Ex: Vacinação contra febre aftosa — dose semestral"
                     value="<?= e($s['descricao'] ?? '') ?>" style="font-size:1rem;">
            </div>

            <!-- 4. Medicamento e Dose -->
            <div class="col-md-6">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                Medicamento / Vacina Utilizado
              </label>
              <input type="text" name="medicamento" id="inputMedicamento" class="form-control" list="medList"
                     placeholder="Ex: Ivermectina, Aftosa..." value="<?= e($s['medicamento'] ?? '') ?>">
              <datalist id="medList">
                <?php foreach (['Aftosa','Brucelose','Carbúnculo','Clostridiose','Botulismo','Ivermectina','Vitamina ADE','Antibiótico','Vermífugo','Cicatrizante'] as $m): ?>
                  <option value="<?= $m ?>">
                <?php endforeach; ?>
              </datalist>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">Dose Aplicada</label>
              <input type="text" name="dose" id="inputDose" class="form-control" placeholder="Ex: 5ml" value="<?= e($s['dose'] ?? '') ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">Custo (R$)</label>
              <input type="number" step="0.01" name="custo" class="form-control" placeholder="0.00" value="<?= e($s['custo'] ?? '') ?>">
            </div>

            <!-- 5. Datas e Veterinário -->
            <div class="col-md-4">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                <i class="bi bi-calendar-event me-1 text-secondary"></i> Data da Aplicação *
              </label>
              <input type="date" name="data" class="form-control" required value="<?= e($s['data'] ?? date('Y-m-d')) ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                <i class="bi bi-bell me-1 text-warning"></i> Próxima Dose / Retorno
              </label>
              <input type="date" name="proxima_data" class="form-control" value="<?= e($s['proxima_data'] ?? '') ?>">
              <small class="text-muted" style="font-size:0.75rem;">Gera alerta automático no painel.</small>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold text-dark" style="font-size:0.95rem;">
                <i class="bi bi-person-badge me-1 text-primary"></i> Aplicador / Veterinário
              </label>
              <input type="text" name="veterinario" class="form-control" placeholder="Nome de quem aplicou" value="<?= e($s['veterinario'] ?? '') ?>">
            </div>

            <!-- 6. Observações Adicionais -->
            <div class="col-12">
              <label class="form-label small text-muted">Observações do Manejo (Opcional)</label>
              <textarea name="observacao" class="form-control" rows="2" placeholder="Reação do animal, lote do medicamento..."><?= e($s['observacao'] ?? '') ?></textarea>
            </div>

            <!-- Botões Grandes de Salvar -->
            <div class="col-12 pt-3 border-top">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-flow-primary py-3 justify-content-center" style="font-size:1.05rem;">
                  <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                  <?= $isEdit ? 'Salvar Alterações de Saúde' : 'Confirmar e Gravar Manejo de Saúde' ?>
                </button>
                <a href="<?= !empty($preAnimal) ? '/animais/'.$preAnimal : '/saude' ?>" class="btn btn-link text-muted text-decoration-none text-center">
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
function selecionarTipoRapido(tipo, desc, med, dose) {
  const selTipo = document.getElementById('selectTipo');
  const inDesc = document.getElementById('inputDescricao');
  const inMed = document.getElementById('inputMedicamento');
  const inDose = document.getElementById('inputDose');

  if (selTipo) selTipo.value = tipo;
  if (inDesc && (!inDesc.value || inDesc.value.startsWith('Vacinação') || inDesc.value.startsWith('Aplicação') || inDesc.value.startsWith('Tratamento') || inDesc.value.startsWith('Curativo') || inDesc.value.startsWith('Exame'))) {
    inDesc.value = desc;
  }
  if (inMed && med) inMed.value = med;
  if (inDose && dose) inDose.value = dose;
}
</script>
